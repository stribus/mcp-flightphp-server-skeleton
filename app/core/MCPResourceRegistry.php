<?php

namespace app\core;

use app\helpers\MCPResourceInterface;

class MCPResourceRegistry
{
    /** @var MCPResourceInterface[] */
    private array $resources = [];

    public function register(MCPResourceInterface $resource): void
    {
        $this->resources[strtolower($resource->getSchema())] = $resource;
    }


    // returns a resource by its schema
    // schema is the URI scheme, e.g. 'mcp://resource_name'
    public function get(string $schema): MCPResourceInterface
    {
        if (strpos($schema, '://') === false) {
            throw new \Exception("Invalid URI: {$schema}", -32600);
        }
        if (strpos($schema, '://') > 0) {
            //$schema = substr($schema, strpos($schema, '://'));
            $schema = strstr($schema, '://', true) ?: $schema;
        }
        if (!isset($this->resources[strtolower($schema)])) {
            throw new \Exception("Resource '{$schema}' not found", -32601);
        }

        return $this->resources[strtolower($schema)];
    }

    public function list(): array {

        return array_map(fn($resource) => [
            'name' => $resource->getName(),
            'description' => $resource->getDescription(),
            'title' => $resource->getTitle() ?? $resource->getName(),
        ], $this->resources);
    }
}
