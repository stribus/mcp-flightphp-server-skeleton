<?php

use app\config\Env;
use app\controllers\MCPServerController;
use app\core\MCPEventStream;
use app\core\MCPSessionStore;

if (empty($app)) {
    $app = Flight::app();
}

Env::load();

/** Single MCP endpoint, as required by the Streamable HTTP transport. */
const MCP_ENDPOINT = '/mcp';

/**
 * Origins allowed to reach the MCP endpoint.
 *
 * The spec requires servers to validate Origin to prevent DNS rebinding
 * attacks. Override with MCP_ALLOWED_ORIGINS (comma-separated), or set it to
 * "*" only if you understand the risk.
 */
$mcpAllowedOrigins = Env::list('MCP_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://127.0.0.1',
]);

/**
 * Answers with a JSON-RPC error and stops.
 */
$mcpFail = function (int $status, int $code, string $message, $id = null): void {
    Flight::jsonHalt(MCPServerController::error($id, $code, $message), $status);
};

/**
 * True when the Origin header is absent (non-browser client) or allowed.
 *
 * @param string[] $allowed
 */
$mcpOriginAllowed = function (array $allowed): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

    // Native MCP clients send no Origin; only browsers do.
    if (null === $origin || '' === $origin) {
        return true;
    }

    if (in_array('*', $allowed, true)) {
        return true;
    }

    foreach ($allowed as $candidate) {
        // Match scheme and host, ignoring the port so any localhost port works.
        if ($origin === $candidate || 0 === strpos($origin, $candidate . ':')) {
            return true;
        }
    }

    return false;
};

$app->before('start', function () use ($mcpAllowedOrigins, $mcpOriginAllowed, $mcpFail) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if (MCP_ENDPOINT !== $path) {
        // The informational endpoints (GET /, GET /health) are read-only and
        // expose nothing sensitive, so they keep the open CORS policy every
        // route had before the MCP endpoint moved to /mcp. Dropping it broke
        // cross-origin dashboards polling /health.
        Flight::response()->header('Access-Control-Allow-Origin', '*');
        Flight::response()->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
        Flight::response()->header('Access-Control-Allow-Headers', 'Content-Type');

        if ('OPTIONS' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            Flight::halt(204);
        }

        return;
    }

    // Only the protocol endpoint gets strict Origin validation: that is the
    // one the spec requires to be protected against DNS rebinding.

    if (false === $mcpOriginAllowed($mcpAllowedOrigins)) {
        $mcpFail(403, -32600, 'Origin not allowed');
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

    if (null !== $origin) {
        Flight::response()->header('Access-Control-Allow-Origin', $origin);
        Flight::response()->header('Vary', 'Origin');
        Flight::response()->header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
        Flight::response()->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id, MCP-Protocol-Version');
        Flight::response()->header('Access-Control-Expose-Headers', 'Mcp-Session-Id');
    }

    if ('OPTIONS' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
        Flight::halt(204);
    }
});

/**
 * POST /mcp - the JSON-RPC entry point.
 */
$app->route('POST ' . MCP_ENDPOINT, function () use ($mcpFail) {
    $sessions = new MCPSessionStore();

    // The client must echo the negotiated version after initialization. When
    // the header is absent the spec says to assume 2025-03-26.
    $protocolVersion = $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ?? '2025-03-26';

    if (MCPServerController::PROTOCOL_VERSION !== $protocolVersion && '2025-03-26' !== $protocolVersion) {
        $mcpFail(400, -32600, 'Unsupported MCP-Protocol-Version: ' . $protocolVersion);
    }

    $sessionId = $_SERVER['HTTP_MCP_SESSION_ID'] ?? null;

    if (null !== $sessionId && false === $sessions->exists($sessionId)) {
        // A client holding an expired session must start a new one.
        $mcpFail(404, -32600, 'Session not found');
    }

    $input = file_get_contents('php://input');
    $request = json_decode((string) $input, true);

    if (JSON_ERROR_NONE !== json_last_error()) {
        $mcpFail(400, -32700, 'Parse error');
    }

    try {
        $response = (new MCPServerController())->handleRequest($request);
    } catch (\Throwable $e) {
        // handleRequest() already turns failures into JSON-RPC errors. This is
        // the last line of defence: without it an unexpected Throwable falls
        // through to Tracy, which answers with an HTML debug page and HTTP 200
        // - something an automated client would read as success.
        $mcpFail(500, -32603, 'Internal error', is_array($request) ? ($request['id'] ?? null) : null);

        return;
    }

    if (null === $response) {
        // Notifications and responses get 202 with no body, per the spec.
        if (null !== $sessionId) {
            $sessions->touch($sessionId);
        }

        Flight::halt(202);
    }

    // A successful initialize opens a session the client carries from here on.
    $isInitialize = is_array($request) && 'initialize' === ($request['method'] ?? null);

    if (true === $isInitialize && false === isset($response['error'])) {
        Flight::response()->header('Mcp-Session-Id', $sessions->create());
    } elseif (null !== $sessionId) {
        $sessions->touch($sessionId);
    }

    Flight::response()->header('MCP-Protocol-Version', MCPServerController::PROTOCOL_VERSION);
    Flight::jsonHalt($response);
});

/**
 * GET /mcp - opens the SSE stream, when enabled.
 *
 * Disabled by default: the built-in PHP server handles one request at a time
 * and cannot fork on Windows, so an open stream would make the whole server
 * unresponsive. Enable with MCP_HTTP_SSE=true behind Apache/nginx + php-fpm.
 */
$app->route('GET ' . MCP_ENDPOINT, function () {
    if (false === Env::bool('MCP_HTTP_SSE', false)) {
        // Returning 405 here is explicitly allowed by the spec.
        Flight::response()->header('Allow', 'POST, DELETE, OPTIONS');
        Flight::halt(405, 'SSE is disabled. Set MCP_HTTP_SSE=true to enable it.');
    }

    $sessions = new MCPSessionStore();
    $sessionId = $_SERVER['HTTP_MCP_SESSION_ID'] ?? null;

    if (null === $sessionId || false === $sessions->exists($sessionId)) {
        Flight::halt(404, 'Unknown session');
    }

    (new MCPEventStream($sessions))->run($sessionId, (int) Env::get('MCP_HTTP_SSE_MAX_SECONDS', '0'));
});

/**
 * DELETE /mcp - explicit session termination.
 */
$app->route('DELETE ' . MCP_ENDPOINT, function () {
    $sessions = new MCPSessionStore();
    $sessionId = $_SERVER['HTTP_MCP_SESSION_ID'] ?? null;

    if (null === $sessionId || false === $sessions->destroy($sessionId)) {
        Flight::halt(404, 'Unknown session');
    }

    Flight::halt(204);
});

// Health check endpoint
$app->route('GET /health', function () {
    Flight::json([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'mcp_version' => MCPServerController::PROTOCOL_VERSION,
    ]);
});

// Human-facing documentation endpoint
$app->route('GET /', function () {
    Flight::json([
        'name' => 'MCP Server',
        'version' => Env::get('MCP_SERVER_VERSION', '1.0.0'),
        'description' => 'This is the main API endpoint for the MCP Server.',
        'mcp_version' => MCPServerController::PROTOCOL_VERSION,
        'endpoints' => [
            'POST ' . MCP_ENDPOINT => 'JSON-RPC 2.0 endpoint',
            'GET ' . MCP_ENDPOINT => 'SSE stream (requires MCP_HTTP_SSE=true)',
            'DELETE ' . MCP_ENDPOINT => 'Terminate a session',
            'GET /health' => 'Health check',
            'GET /' => 'This documentation',
        ],
    ]);
});

// 404 Not Found handler
$app->map('notFound', function () {
    Flight::json([
        'jsonrpc' => '2.0',
        'id' => null,
        'error' => [
            'code' => -32601,
            'message' => 'Method not found',
        ],
    ], 404);
});
