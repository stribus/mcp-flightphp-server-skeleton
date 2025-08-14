<?php

namespace app\helpers;

abstract class AbstractMCPResource implements MCPResourceInterface
{
    protected string $name;
    protected string $schema;
    protected string $title;
    protected string $description;
    protected ?string $uri = null;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

}
