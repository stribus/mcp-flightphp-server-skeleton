<?php

namespace app\resources;

use app\config\Env;
use app\helpers\AbstractMCPResource;

/**
 * Reference resource exposing this server's own configuration.
 *
 * Resources are keyed by the scheme in getSchema(), so everything under
 * "config://" reaches this class. Like tools and prompts, it is discovered
 * simply by living in app/resources - there is nothing to register.
 *
 * Note the no-argument constructor: ClassAutoLoader instantiates with
 * newInstanceArgs() and no parameters, so a required constructor argument
 * would make the class silently undiscoverable. Pull dependencies inside
 * getContent() instead.
 */
class ServerInfoResource extends AbstractMCPResource
{
    protected string $name = 'server-info';
    protected string $description = 'Name, version and protocol revision of this MCP server.';
    protected string $schema = 'config';
    protected ?string $title = 'Server Information';
    protected ?string $uri = 'config://server';
    protected string $mimeType = 'application/json';

    /**
     * Entries this resource exposes.
     *
     * Returning an empty array is fine: the registry then advertises the
     * resource itself, using getUri(), getName() and the rest.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listResources(string $uri): array
    {
        return [
            [
                'uri' => 'config://server',
                'name' => 'server-info',
                'title' => 'Server Information',
                'description' => 'Name, version and protocol revision of this MCP server.',
                'mimeType' => 'application/json',
            ],
        ];
    }

    /**
     * Contents for a URI under this scheme.
     *
     * Returning a plain array is enough - the framework wraps it into the MCP
     * "contents" envelope and serialises it as JSON text.
     *
     * @return mixed
     */
    public function getContent(string $uri)
    {
        if ('config://server' !== $uri) {
            throw new \Exception("Resource not found: {$uri}", -32002);
        }

        return [
            'name' => Env::get('MCP_SERVER_NAME', 'FlightPHP MCP Server'),
            'version' => Env::get('MCP_SERVER_VERSION', '1.0.0'),
            'protocolVersion' => \app\controllers\MCPServerController::PROTOCOL_VERSION,
            'transports' => ['stdio', 'http'],
        ];
    }
}
