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
        $session = $this->read($id);

        if (null === $session) {
            return;
        }

        $session['lastSeen'] = time();
        $this->write($id, $session);
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
     * @param array<string,mixed> $message
     */
    public function push(string $id, array $message): void
    {
        $session = $this->read($id);

        if (null === $session) {
            return;
        }

        $session['queue'][] = $message;
        $session['lastSeen'] = time();
        $this->write($id, $session);
    }

    /**
     * Removes and returns every queued message.
     *
     * @return array<int,array<string,mixed>>
     */
    public function drain(string $id): array
    {
        $session = $this->read($id);

        if (null === $session || [] === $session['queue']) {
            return [];
        }

        $queue = $session['queue'];
        $session['queue'] = [];
        $session['lastSeen'] = time();
        $this->write($id, $session);

        return $queue;
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

        $path = $this->path($id);

        if (false === is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if (false === $raw) {
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
