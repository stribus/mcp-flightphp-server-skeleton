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
            // The spec defines a dedicated code for a missing resource.
            throw new \Exception("Resource not found: {$schema}", -32002);
        }

        return $this->resources[strtolower($schema)];
    }

    /**
     * True when no resource is registered.
     *
     * Deliberately does not call list(): that runs every resource's
     * listResources(), which is user code free to do I/O or throw. Answering
     * "is there anything?" must not be able to fail the initialize handshake.
     */
    public function isEmpty(): bool
    {
        return [] === $this->resources;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        $resources = [];

        foreach ($this->resources as $resource) {
            // A resource may expose several entries; when it exposes none,
            // fall back to describing itself.
            $entries = $resource->listResources($resource->getUri());

            if ([] === $entries) {
                $entries = [[
                    // "uri" is required on every Resource and was missing
                    // entirely from this listing before.
                    'uri' => $resource->getUri(),
                    'name' => $resource->getName(),
                    'title' => $resource->getTitle(),
                    'description' => $resource->getDescription(),
                    'mimeType' => $resource->getMimeType(),
                ]];
            }

            foreach ($entries as $entry) {
                $resources[] = $entry;
            }
        }

        return $resources;
    }
}
