<?php

declare(strict_types=1);

namespace app\core;

/**
 * Server-Sent Events emitter for the Streamable HTTP transport.
 *
 * Opened by GET on the MCP endpoint. The stream drains whatever the session
 * has queued and keeps the connection alive with comment-only heartbeats.
 *
 * IMPORTANT: PHP's built-in server (`php -S`) handles one request at a time.
 * On Windows it cannot fork at all - setting PHP_CLI_SERVER_WORKERS makes PHP
 * print "forking is not supported on this platform" - so an open stream blocks
 * every other request. That is why SSE is opt-in via MCP_HTTP_SSE and only
 * usable behind Apache/nginx with php-fpm.
 */
class MCPEventStream
{
    /** Seconds between heartbeats when the queue is empty. */
    private const HEARTBEAT_SECONDS = 15;

    /** Seconds between queue polls. */
    private const POLL_SECONDS = 1;

    private MCPSessionStore $sessions;

    public function __construct(MCPSessionStore $sessions)
    {
        $this->sessions = $sessions;
    }

    /**
     * Streams until the client disconnects or the session goes away.
     *
     * @param int $maxSeconds Upper bound on stream lifetime; 0 means unbounded
     */
    public function run(string $sessionId, int $maxSeconds = 0): void
    {
        $this->sendHeaders();

        // The stream must outlive the default execution limit, and we want the
        // loop to notice the client hanging up so the session can be released.
        set_time_limit(0);
        ignore_user_abort(false);

        // Flight wraps route handlers in ob_start() and later calls
        // ob_get_clean(). Streaming means closing that buffer so bytes reach
        // the client immediately - done once here, restored once at the end.
        while (0 < ob_get_level()) {
            ob_end_flush();
        }

        $eventId = 0;
        $startedAt = time();
        $lastBeat = 0;

        $this->write(': stream opened' . "\n\n");

        while (true) {
            if (1 === connection_aborted()) {
                break;
            }

            if (false === $this->sessions->exists($sessionId)) {
                break;
            }

            if (0 < $maxSeconds && time() - $startedAt >= $maxSeconds) {
                break;
            }

            foreach ($this->sessions->drain($sessionId) as $message) {
                ++$eventId;
                $this->sendEvent($eventId, $message);
                $lastBeat = time();
            }

            if (time() - $lastBeat >= self::HEARTBEAT_SECONDS) {
                // A comment keeps proxies from closing an idle connection and
                // is ignored by every SSE client.
                $this->write(': heartbeat' . "\n\n");
                $lastBeat = time();
            }

            $this->sessions->touch($sessionId);
            sleep(self::POLL_SECONDS);
        }

        // Hand a fresh buffer back to the framework. Without it Flight's
        // ob_get_clean() returns false and Response::write() raises a
        // TypeError once the stream ends.
        ob_start();
    }

    private function sendHeaders(): void
    {
        if (true === headers_sent()) {
            return;
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        // Tells nginx not to buffer the stream.
        header('X-Accel-Buffering: no');
    }

    /**
     * @param array<string,mixed> $message
     */
    private function sendEvent(int $eventId, array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            return;
        }

        // Event ids act as a per-stream cursor for Last-Event-ID resumption,
        // which this skeleton does not implement yet.
        $this->write('id: ' . $eventId . "\n" . 'data: ' . $json . "\n\n");
    }

    private function write(string $chunk): void
    {
        // Buffers were already closed in run(); a plain flush is enough.
        echo $chunk;
        flush();
    }
}
