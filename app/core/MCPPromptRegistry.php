<?php

namespace app\core;

use app\helpers\MCPPromptInterface;

class MCPPromptRegistry
{
    /** @var MCPPromptInterface[] */
    private array $prompts = [];

    public function register(MCPPromptInterface $prompt): void
    {
        $this->prompts[$prompt->getName()] = $prompt;
    }

    public function get(string $name): MCPPromptInterface
    {
        if (!isset($this->prompts[$name])) {
            throw new \Exception("Prompt '{$name}' not found", -32601);
        }

        return $this->prompts[$name];
    }

    public function list(): array
    {
        return array_map(fn ($prompt) => [
            'name' => $prompt->getName(),
            'description' => $prompt->getDescription(),
            'title' => $prompt->getTitle() ?? $prompt->getName(),
            'promptText' => $prompt->getPromptText([]),
            'arguments' => method_exists($prompt, 'getArguments') ? $prompt->getArguments() : [],
        ], $this->prompts);
    }
}
