<?php

namespace app\helpers;

abstract class AbstractMCPPrompt implements MCPPromptInterface
{
    protected string $name;
    protected string $description;
    protected ?string $title = null;
    protected array $arguments = [];

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title ?? $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getArguments(): array
    {
        return [];
    }
}
