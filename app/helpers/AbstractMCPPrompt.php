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

    /**
     * Declared arguments, in the shape prompts/list expects.
     *
     * This used to return a hardcoded empty array, so every prompt reported no
     * arguments at all no matter what it declared.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getArguments(): array
    {
        return MCPResultBuilder::normalizeArguments($this->arguments);
    }
}
