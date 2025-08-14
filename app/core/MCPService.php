<?php

namespace app\core;

use app\Helpers\ClassAutoLoader;
use app\helpers\MCPPromptInterface;
use app\helpers\MCPResourceInterface;
use app\helpers\MCPToolInterface;

class MCPService
{
    private MCPToolRegistry $tools;
    private MCPResourceRegistry $resources;
    private MCPPromptRegistry $prompts;

    public function __construct()
    {
        $this->tools = new MCPToolRegistry();
        $this->resources = new MCPResourceRegistry();
        $this->prompts = new MCPPromptRegistry();

        // Register tools 
        // This will load tools from the app/tools directory
        // and register them in the MCPToolRegistry
        // Tools are expected to implement MCPToolInterface
        // and provide an execute() method
        // This allows the MCP server to handle various tools dynamically
        foreach (ClassAutoLoader::autoloadClasses(ABSPATH.'/app/tools', MCPToolInterface::class) as $tool) {
            if ($tool instanceof MCPToolInterface) {
                $this->tools->register($tool);
            }
        }

        // Register resources
        // This will load resources from the app/resources directory
        // and register them in the MCPResourceRegistry
        // Resources are expected to implement MCPResourceInterface
        // and provide methods like listResources() and getContent()
        // This allows the MCP server to handle various resources dynamically
        foreach (ClassAutoLoader::autoloadClasses(ABSPATH.'/app/resources', MCPResourceInterface::class) as $resource) {
            if ($resource instanceof MCPResourceInterface) {
                $this->resources->register($resource);
            }
        }

        // Register prompts
        // This will load prompts from the app/prompts directory
        // and register them in the MCPPromptRegistry
        // Prompts are expected to implement MCPPromptInterface
        // and provide a getPromptText() method
        // This allows the MCP server to handle various prompts dynamically
        foreach (ClassAutoLoader::autoloadClasses(ABSPATH.'/app/prompts', MCPPromptInterface::class) as $prompt) {
            if ($prompt instanceof MCPPromptInterface) {
                $this->prompts->register($prompt);
            }
        }
    }

    public function listTools(): array
    {
        return $this->tools->list();
    }

    public function callTool(array $params): mixed
    {
        return $this->tools->get($params['name'])->execute($params['arguments'] ?? []);
    }

    public function listResources(string $uri): array
    {
        if (!isset($this->resources)) {
            return [];
        }
        if (empty($uri)) {
            return $this->resources->list();
        }

        return $this->resources->get($uri)->listResources($uri);
    }

    public function getResource(string $uri): mixed
    {
        return $this->resources->get($uri)->getContent($uri);
    }

    public function listPrompts(): array
    {
        return $this->prompts->list();
    }

    public function getPrompt(string $name, array $context): string
    {
        return $this->prompts->get($name)->getPromptText($context);
    }

    // check if there are any tools registered
    public function hasTools(): bool
    {
        return isset($this->tools) && !empty($this->tools->list());
    }

    // check if there are any prompts registered
    public function hasPrompts(): bool
    {
        return isset($this->prompts) && !empty($this->prompts->list());
    }

    // check if there are any resources registered
    public function hasResources(): bool
    {
        return isset($this->resources) && !empty($this->resources->list());
    }
}
