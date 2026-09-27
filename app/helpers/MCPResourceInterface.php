<?php

namespace app\helpers;

interface MCPResourceInterface
{
    public function getName(): string;

    /**
     * URI scheme this resource answers for, e.g. "config" in config://server.
     *
     * The registry is keyed by this value, lowercased.
     */
    public function getSchema(): string;

    public function getTitle(): string;

    public function getDescription(): string;

    /**
     * Canonical URI, required on every entry of resources/list.
     */
    public function getUri(): string;

    /**
     * MIME type reported for this resource's contents.
     */
    public function getMimeType(): string;

    /**
     * Entries this resource exposes, each shaped like an MCP Resource
     * (uri, name, title, description, mimeType).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listResources(string $uri): array;

    /**
     * Contents for a URI.
     *
     * Return a string for text, or a full contents array to control mimeType
     * or return binary data as "blob". The framework wraps a plain return
     * value into {"contents": [...]}.
     *
     * @return mixed
     */
    public function getContent(string $uri);
}
