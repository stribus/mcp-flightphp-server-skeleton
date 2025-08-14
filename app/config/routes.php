<?php



use app\controllers\MCPServerController;



if (empty($app)) {
    $app = Flight::app();
}

// CORS headers
// This allows the server to accept requests from any origin, which is useful for development
$app->before('start', function () {
    Flight::response()->header('Access-Control-Allow-Origin: *');
    Flight::response()->header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    Flight::response()->header('Access-Control-Allow-Headers: Content-Type, Authorization');
    Flight::response()->header('Content-Type: application/json');

    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        Flight::response()->status(200);
        Flight::stop();
    }

});

// JSON-RPC 2.0 endpoint
// This is the main entry point for JSON-RPC requests
$app->route('POST /', function () {
    try {
        $input = file_get_contents('php://input');
        $request = json_decode($input, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $response = [
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error',
                ],
            ];
            Flight::jsonHalt($response, 400);
        }

        $mcpServer = new MCPServerController();

        $response = $mcpServer->handleRequest($request);
        if (!isset($response['jsonrpc']) || '2.0' !== $response['jsonrpc']) {
            $response = [
                'jsonrpc' => '2.0',
                'id' => $request['id'] ?? null,
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request',
                ],
            ];
        }
        if (!isset($response['id'])) {
            $response['id'] = $request['id'] ?? null;
        }
        Flight::jsonHalt($response);
    } catch (Exception $e) {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $request['id'] ?? null,
            'error' => [
                'code' => -32603,
                'message' => 'Internal error',
                'data' => $e->getMessage(),
            ],
        ];
        Flight::jsonHalt($response, 500);
    }
});

// Health check endpoint
$app->route('GET /health', function () {
    Flight::json([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'mcp_version' => '2025-06-18',
    ]);
});

// Main API endpoint
$app->route('GET /', function () {
    Flight::json([
        'name' => 'MCP Server',
        'version' => '1.0.0',
        'description' => 'This is the main API endpoint for the MCP Server.',
        'mcp_version' => '2025-06-18',
        'endpoints' => [
            'POST /' => 'JSON-RPC 2.0 endpoint',
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