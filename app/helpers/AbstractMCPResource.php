<?php

namespace app\helpers;

abstract class AbstractMCPResource implements MCPResourceInterface
{
    protected string $name;
    protected string $description;
    /** URI scheme this resource answers for, e.g. "config" in config://server. */
    protected string $schema;
    protected ?string $title = null;
    protected ?string $uri = null;
    /** Default MIME type reported for this resource's contents. */
    protected string $mimeType = 'text/plain';

    public function __construct()
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * Falls back to the name, matching AbstractMCPTool and AbstractMCPPrompt.
     *
     * Without the fallback an unset $title - which was not even nullable -
     * threw "must not be accessed before initialization".
     */
    public function getTitle(): string
    {
        return $this->title ?? $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Canonical URI for this resource, required by resources/list.
     *
     * Defaults to "<schema>://" so a resource that exposes a single item does
     * not have to spell it out.
     */
    public function getUri(): string
    {
        return $this->uri ?? $this->schema . '://';
    }
}
