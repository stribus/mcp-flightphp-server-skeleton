<?php

namespace app\core;

use app\helpers\MCPToolInterface;

class MCPToolRegistry
{
    /** @var MCPToolInterface[] */
    private array $tools = [];

    public function register(MCPToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function get(string $name): MCPToolInterface
    {
        if (!isset($this->tools[$name])) {
            // The spec maps an unknown tool to invalid params, not to an
            // unknown method - the method (tools/call) does exist.
            throw new \Exception("Unknown tool: {$name}", -32602);
        }

        return $this->tools[$name];
    }

    /**
     * True when no tool is registered, without building every tool's schema.
     */
    public function isEmpty(): bool
    {
        return [] === $this->tools;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        $tools = array_map(function ($tool) {
            $outputSchema = $tool->getOutputSchema();

            $return = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'title' => $tool->getTitle() ?? $tool->getName(),
                'inputSchema' => $tool->getInputSchema(),
            ];

            if (null !== $outputSchema) {
                $return['outputSchema'] = $outputSchema;
            }

            return $return;
        }, $this->tools);

        // The registry is keyed by tool name, and array_map preserves string
        // keys, which would encode as a JSON object. The spec wants an array.
        return array_values($tools);
    }
}
