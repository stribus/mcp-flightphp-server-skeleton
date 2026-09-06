<?php

namespace app\controllers;

use app\config\Env;
use app\core\MCPService;

class MCPServerController
{
    /** Protocol revision this server implements. */
    public const PROTOCOL_VERSION = '2025-06-18';

    private MCPService $service;

    public function __construct()
    {
        // initialize the MCP service
        $this->service = new MCPService();
    }

    /**
     * Handles a single JSON-RPC message.
     *
     * Returns null when the message is a notification. Per JSON-RPC 2.0 a
     * notification carries no "id" and MUST NOT be answered - callers are
     * responsible for sending nothing at all in that case.
     *
     * @param mixed $request Decoded JSON-RPC message
     *
     * @return null|array<string,mixed>
     */
    public function handleRequest($request): ?array
    {
        // A notification is identified by the absence of "id", not by its name.
        $isNotification = is_array($request) && false === array_key_exists('id', $request);

        // Validate JSON-RPC structure
        if (false === is_array($request) || false === isset($request['jsonrpc']) || '2.0' !== $request['jsonrpc']) {
            return $isNotification ? null : $this->error($request['id'] ?? null, -32600, 'Invalid Request');
        }

        if (false === isset($request['method']) || false === is_string($request['method'])) {
            return $isNotification ? null : $this->error($request['id'] ?? null, -32600, 'Invalid Request');
        }

        $method = $request['method'];
        $id = $request['id'] ?? null;

        // Every notifications/* message is accepted silently, including
        // notifications/initialized, which the client sends after initialize.
        if (str_starts_with($method, 'notifications/')) {
            return null;
        }

        try {
            switch ($method) {
                case 'initialize':
                    $result = $this->initialize($request['params'] ?? []);

                    break;

                case 'ping':
                    // The spec defines ping as returning an empty result object.
                    $result = new \stdClass();

                    break;

                case 'tools/list':
                    $result = $this->service->listTools();

                    break;

                case 'tools/call':
                    $params = $request['params'] ?? [];
                    $result = $this->service->callTool($params);

                    break;

                case 'resources/list':
                    $uri = $request['params']['uri'] ?? '';
                    $result = $this->service->listResources($uri);

                    break;

                case 'resources/read':
                    $uri = $request['params']['uri'] ?? '';
                    $result = $this->service->getResource($uri);

                    break;

                case 'prompts/list':
                    $result = $this->service->listPrompts();

                    break;

                case 'prompts/get':
                    $params = $request['params'] ?? [];
                    $name = $params['name'] ?? '';
                    $context = $params['context'] ?? [];
                    $result = $this->service->getPrompt($name, $context);

                    break;

                default:
                    return $isNotification ? null : $this->error($id, -32601, 'Method not found');
            }
        } catch (\Throwable $e) {
            // Catching Throwable, not Exception: a TypeError raised inside a
            // tool used to escape to the stdio exception handler and exit(1),
            // killing the server for every subsequent request.
            if (true === $isNotification) {
                return null;
            }

            return $this->error($id, $this->errorCode($e), 'Internal error', $e->getMessage());
        }

        if (true === $isNotification) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * Builds the InitializeResult.
     *
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>
     */
    private function initialize(array $params): array
    {
        $capabilities = [];

        if (true === $this->service->hasTools()) {
            $capabilities['tools'] = ['listChanged' => false];
        }
        if (true === $this->service->hasPrompts()) {
            $capabilities['prompts'] = ['listChanged' => false];
        }
        if (true === $this->service->hasResources()) {
            $capabilities['resources'] = ['subscribe' => false, 'listChanged' => false];
        }

        $requested = $params['protocolVersion'] ?? null;

        $result = [
            // If the client asked for a version we implement, echo it back.
            // Otherwise answer with ours and let the client decide.
            'protocolVersion' => self::PROTOCOL_VERSION === $requested ? $requested : self::PROTOCOL_VERSION,
            // An empty PHP array encodes as [], but capabilities must be an object.
            'capabilities' => [] === $capabilities ? new \stdClass() : $capabilities,
            // serverInfo is required by the spec and was previously missing.
            'serverInfo' => [
                'name' => Env::get('MCP_SERVER_NAME', 'FlightPHP MCP Server'),
                'version' => Env::get('MCP_SERVER_VERSION', '1.0.0'),
            ],
        ];

        $instructions = Env::get('MCP_SERVER_INSTRUCTIONS');

        if (null !== $instructions) {
            $result['instructions'] = $instructions;
        }

        return $result;
    }

    /**
     * Maps an exception onto a JSON-RPC error code.
     *
     * Registries throw with the code already set; anything else is an
     * unexpected server fault.
     */
    private function errorCode(\Throwable $e): int
    {
        $code = $e->getCode();

        return is_int($code) && 0 > $code ? $code : -32603;
    }

    /**
     * @return array<string,mixed>
     */
    private function error(?int $id, int $code, string $message, ?string $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (null !== $data) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
    }
}
