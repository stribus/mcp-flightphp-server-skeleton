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

        // JSON-RPC 2.0 allows an id to be a string, a number or null. Anything
        // else (an array, an object, a boolean) makes the request invalid.
        if (false === $isNotification && false === self::isValidId($request['id'])) {
            return $this->error(null, -32600, 'Invalid Request');
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
                    // Every list result is wrapped in a named array. Omitting
                    // nextCursor means there are no further pages.
                    $result = ['tools' => $this->service->listTools()];

                    break;

                case 'tools/call':
                    $params = $request['params'] ?? [];
                    $result = $this->service->callTool($params);

                    break;

                case 'resources/list':
                    // resources/list takes a pagination cursor, not a uri.
                    $result = ['resources' => $this->service->listResources()];

                    break;

                case 'resources/read':
                    $uri = $request['params']['uri'] ?? '';
                    $result = $this->service->getResource($uri);

                    break;

                case 'prompts/list':
                    $result = ['prompts' => $this->service->listPrompts()];

                    break;

                case 'prompts/get':
                    $params = $request['params'] ?? [];
                    $name = $params['name'] ?? '';
                    // The spec names this "arguments"; "context" is kept as a
                    // fallback so prompts written against 1.x keep working.
                    $arguments = $params['arguments'] ?? $params['context'] ?? [];
                    $result = $this->service->getPrompt($name, $arguments);

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

            $code = $this->errorCode($e);

            return $this->error($id, $code, $this->errorMessage($code), $e->getMessage());
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

        $result = [
            // This server implements exactly one revision. The spec says to
            // echo the client's version when supported and otherwise answer
            // with one we do support - with a single revision both cases
            // produce the same value, and the client decides whether to go on.
            // Supporting more revisions would mean reading
            // $params['protocolVersion'] here and checking it against a list.
            'protocolVersion' => self::PROTOCOL_VERSION,
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
     * The standard message that goes with a JSON-RPC error code.
     *
     * Details belong in "data"; "message" stays the canonical short label so
     * clients can match on it.
     */
    private function errorMessage(int $code): string
    {
        $messages = [
            -32700 => 'Parse error',
            -32600 => 'Invalid Request',
            -32601 => 'Method not found',
            -32602 => 'Invalid params',
            -32603 => 'Internal error',
            -32002 => 'Resource not found',
        ];

        return $messages[$code] ?? 'Internal error';
    }

    /**
     * Builds a JSON-RPC error response.
     *
     * Public and static so both transports can produce the same envelope for
     * failures that happen before the controller runs (bad Origin, parse
     * errors, unknown sessions).
     *
     * $id is deliberately untyped. It used to be ?int, but JSON-RPC ids are
     * routinely strings, and a TypeError raised here - inside the very code
     * that reports errors - escaped every catch block and killed the stdio
     * server. An id that is not a valid JSON-RPC id is reported as null.
     *
     * @param mixed $id
     *
     * @return array<string,mixed>
     */
    public static function error($id, int $code, string $message, ?string $data = null): array
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
            'id' => self::isValidId($id) ? $id : null,
            'error' => $error,
        ];
    }

    /**
     * True for the id types JSON-RPC 2.0 permits: string, integer or null.
     *
     * @param mixed $id
     */
    public static function isValidId($id): bool
    {
        return null === $id || is_int($id) || is_string($id);
    }
}
