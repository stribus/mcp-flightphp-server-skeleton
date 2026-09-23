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
            // The spec maps an unknown prompt name to invalid params.
            throw new \Exception("Unknown prompt: {$name}", -32602);
        }

        return $this->prompts[$name];
    }

    /**
     * True when no prompt is registered, without building the listing.
     */
    public function isEmpty(): bool
    {
        return [] === $this->prompts;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        $prompts = array_map(fn ($prompt) => [
            'name' => $prompt->getName(),
            'description' => $prompt->getDescription(),
            'title' => $prompt->getTitle() ?? $prompt->getName(),
            // "promptText" is not part of the spec, and producing it meant
            // calling getPromptText([]) on every prompt at list time - running
            // prompt logic with empty context just to render a listing.
            'arguments' => $prompt->getArguments(),
        ], $this->prompts);

        // Keyed by prompt name, so array_map would emit a JSON object.
        return array_values($prompts);
    }
}
