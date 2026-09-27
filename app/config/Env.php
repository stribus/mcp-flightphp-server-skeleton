<?php

declare(strict_types=1);

namespace app\config;

use Dotenv\Dotenv;

/**
 * Shared access to environment configuration.
 *
 * Both entry points need the same values, but they bootstrap differently:
 * mcp-server.php loads .env itself, while the HTTP path never did. This
 * centralises loading so either transport reports the same serverInfo.
 */
class Env
{
    private static bool $loaded = false;

    /**
     * Loads .env once per process. A missing file is not an error - the
     * skeleton must run without one.
     */
    public static function load(): void
    {
        if (true === self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (false === defined('ABSPATH') || false === file_exists(ABSPATH . '.env')) {
            return;
        }

        Dotenv::createImmutable(ABSPATH)->safeLoad();
    }

    /**
     * Reads a variable, falling back to $default when unset or empty.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        // The ?? chain already skips unset and null entries, so the only
        // "missing" value left to handle is getenv()'s false.
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (false === $value || '' === $value) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Reads a variable as a boolean. Accepts 1/true/yes/on, case-insensitive.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if (null === $value) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Reads a comma-separated variable as a list of trimmed, non-empty values.
     *
     * @return string[]
     */
    public static function list(string $key, array $default = []): array
    {
        $value = self::get($key);

        if (null === $value) {
            return $default;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($item) => '' !== $item));
    }
}
