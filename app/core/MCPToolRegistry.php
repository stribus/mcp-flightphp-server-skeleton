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
            throw new \Exception("Tool '{$name}' not found", -32601);
        }

        return $this->tools[$name];
    }

    public function list(): array
    {
        return array_map(function ($tool) {
            // $required = [];
            // $arguments = method_exists($tool, 'getArguments') ? $tool->getArguments() : [];
            $inputSchema = $tool->getInputSchema();
            $outputSchema = $tool->getOutputSchema();

            $return = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'title' => $tool->getTitle() ?? $tool->getName(),
                'inputSchema' => $inputSchema,
            ];

            if (null !== $outputSchema) {
                $return['outputSchema'] = $outputSchema;
            }

            return $return;
        }, $this->tools);
    }
}
