<?php

declare(strict_types=1);

namespace app\core;

/**
 * File-backed store for Streamable HTTP sessions.
 *
 * PHP's shared-nothing model keeps nothing between requests, so a session
 * started by one POST has to be recognisable by the next one. Each session is
 * a single JSON file holding its timestamps and a queue of messages waiting to
 * be delivered over the SSE stream.
 */
class MCPSessionStore
{
    /** Sessions untouched for this long are collected. */
    private const TTL_SECONDS = 3600;

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? ABSPATH . 'app/log/sessions';
    }

    /**
     * Creates a session and returns its id.
     *
     * The spec requires the id to be cryptographically secure and to contain
     * only visible ASCII; hex satisfies both.
     */
    public function create(): string
    {
        $this->ensureDirectory();
        $this->gc();

        $id = bin2hex(random_bytes(16));

        $this->write($id, [
            'created' => time(),
            'lastSeen' => time(),
            'queue' => [],
        ]);

        return $id;
    }

    public function exists(string $id): bool
    {
        return null !== $this->read($id);
    }

    /**
     * Marks the session as recently used, so the collector leaves it alone.
     */
    public function touch(string $id): void
    {
        $this->mutate($id, static function (array $session): array {
            $session['lastSeen'] = time();

            return [$session, null];
        });
    }

    public function destroy(string $id): bool
    {
        if (false === $this->isValidId($id)) {
            return false;
        }

        $path = $this->path($id);

        if (false === is_file($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * Queues a server-to-client message for delivery on the SSE stream.
     *
     * This is the extension point for server-initiated notifications: call it
     * from a tool, a job or anywhere else, and the open stream for that
     * session delivers the message. Nothing in the skeleton calls it yet.
     *
     * Returns false when the session no longer exists.
     *
     * @param array<string,mixed> $message
     */
    public function push(string $id, array $message): bool
    {
        return true === $this->mutate($id, static function (array $session) use ($message): array {
            $session['queue'][] = $message;
            $session['lastSeen'] = time();

            return [$session, true];
        });
    }

    /**
     * Removes and returns every queued message.
     *
     * @return array<int,array<string,mixed>>
     */
    public function drain(string $id): array
    {
        $queue = $this->mutate($id, static function (array $session): array {
            $queue = $session['queue'];

            if ([] === $queue) {
                return [null, []];
            }

            $session['queue'] = [];
            $session['lastSeen'] = time();

            return [$session, $queue];
        });

        return is_array($queue) ? $queue : [];
    }

    /**
     * Session ids are used to build file paths, so anything but the exact
     * generated shape is rejected outright.
     */
    public function isValidId(string $id): bool
    {
        return 1 === preg_match('/^[a-f0-9]{32}$/', $id);
    }

    /**
     * Deletes sessions that have not been touched within the TTL.
     */
    public function gc(): void
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.json');

        if (false === $files) {
            return;
        }

        $cutoff = time() - self::TTL_SECONDS;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /**
     * @return null|array<string,mixed>
     */
    private function read(string $id): ?array
    {
        if (false === $this->isValidId($id)) {
            return null;
        }

        $handle = @fopen($this->path($id), 'r');

        if (false === $handle) {
            return null;
        }

        try {
            // A shared lock: without it a reader could catch a writer between
            // truncating the file and writing it back, see empty JSON, and
            // report a live session as missing (a spurious 404).
            if (false === flock($handle, LOCK_SH)) {
                return null;
            }

            return $this->decode(stream_get_contents($handle));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Applies $change to a session under one exclusive lock.
     *
     * touch(), push() and drain() are read-modify-write cycles. Done as a
     * plain read followed by a write, two of them racing lose an update - a
     * push() landing between drain()'s read and write would be wiped. Holding
     * LOCK_EX across the whole cycle serialises them.
     *
     * The file is opened with "r+", which never creates it, so a touch() racing
     * a DELETE cannot bring a destroyed session back to life.
     *
     * $change receives the session and returns [newSession, result]; a null
     * newSession means "nothing to write". Returns the result, or null when
     * the session does not exist.
     *
     * @param callable(array<string,mixed>): array{0: null|array<string,mixed>, 1: mixed} $change
     *
     * @return mixed
     */
    private function mutate(string $id, callable $change)
    {
        if (false === $this->isValidId($id)) {
            return null;
        }

        $handle = @fopen($this->path($id), 'r+');

        if (false === $handle) {
            return null;
        }

        try {
            if (false === flock($handle, LOCK_EX)) {
                return null;
            }

            $session = $this->decode(stream_get_contents($handle));

            if (null === $session) {
                return null;
            }

            [$updated, $result] = $change($session);

            if (null !== $updated) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, (string) json_encode($updated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                fflush($handle);
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param false|string $raw
     *
     * @return null|array<string,mixed>
     */
    private function decode($raw): ?array
    {
        if (false === $raw || '' === $raw) {
            return null;
        }

        $session = json_decode($raw, true);

        if (false === is_array($session)) {
            return null;
        }

        $session['queue'] = $session['queue'] ?? [];

        return $session;
    }

    /**
     * @param array<string,mixed> $session
     */
    private function write(string $id, array $session): void
    {
        $this->ensureDirectory();

        file_put_contents(
            $this->path($id),
            json_encode($session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function path(string $id): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.json';
    }

    private function ensureDirectory(): void
    {
        if (false === is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }
}
