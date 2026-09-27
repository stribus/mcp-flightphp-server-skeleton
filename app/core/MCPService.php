<?php

namespace app\core;

use app\helpers\ClassAutoLoader;
use app\helpers\MCPPromptInterface;
use app\helpers\MCPResourceInterface;
use app\helpers\MCPResultBuilder;
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

    /**
     * Runs a tool and returns a spec-shaped CallToolResult.
     *
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>
     */
    public function callTool(array $params): array
    {
        $name = $params['name'] ?? null;

        if (false === is_string($name) || '' === $name) {
            throw new \InvalidArgumentException('Missing required parameter: name', -32602);
        }

        // A missing tool or a missing argument is a protocol error, so it is
        // raised and surfaces as a JSON-RPC error.
        $tool = $this->tools->get($name);
        $arguments = $params['arguments'] ?? [];

        // Checked by capability rather than by class: a tool may implement
        // MCPToolInterface directly instead of extending AbstractMCPTool, and
        // it should not silently lose argument validation for doing so.
        if (method_exists($tool, 'validateArguments')) {
            $tool->validateArguments($arguments);
        }

        try {
            $result = $tool->execute($arguments);
        } catch (\Throwable $e) {
            // A failure inside the tool is an execution error, which the spec
            // reports in the result with isError - never as a JSON-RPC error.
            return MCPResultBuilder::errorResult($e->getMessage());
        }

        return MCPResultBuilder::toolResult($result, $tool->getOutputSchema());
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listResources(string $uri = ''): array
    {
        if ('' === $uri) {
            return $this->resources->list();
        }

        return $this->resources->get($uri)->listResources($uri);
    }

    /**
     * Reads a resource and returns a spec-shaped ReadResourceResult.
     *
     * @return array<string,mixed>
     */
    public function getResource(string $uri): array
    {
        if ('' === $uri) {
            throw new \InvalidArgumentException('Missing required parameter: uri', -32602);
        }

        $resource = $this->resources->get($uri);

        return MCPResultBuilder::resourceContents(
            $resource->getContent($uri),
            $uri,
            $resource->getMimeType()
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listPrompts(): array
    {
        return $this->prompts->list();
    }

    /**
     * Renders a prompt and returns a spec-shaped GetPromptResult.
     *
     * @param array<string,mixed> $arguments
     *
     * @return array<string,mixed>
     */
    public function getPrompt(string $name, array $arguments): array
    {
        if ('' === $name) {
            throw new \InvalidArgumentException('Missing required parameter: name', -32602);
        }

        $prompt = $this->prompts->get($name);

        return MCPResultBuilder::promptResult(
            $prompt->getPromptText($arguments),
            $prompt->getDescription()
        );
    }

    // The has*() checks feed initialize's capabilities. They only ask whether
    // anything is registered, so they must not build the listings: that runs
    // user code (schemas, listResources()) that could throw and fail the
    // whole handshake over one broken item.

    public function hasTools(): bool
    {
        return false === $this->tools->isEmpty();
    }

    public function hasPrompts(): bool
    {
        return false === $this->prompts->isEmpty();
    }

    public function hasResources(): bool
    {
        return false === $this->resources->isEmpty();
    }
}
