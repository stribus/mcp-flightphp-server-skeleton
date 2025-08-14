<?php

/**
 * MCP Server for VS Code Copilot via stdio
 * 
 * This script runs as a standalone MCP server that communicates
 * via stdin/stdout following the JSON-RPC 2.0 protocol.
 * 
 * Usage: php mcp-server.php
 */

// Define absolute path
define('ABSPATH', str_replace('\\', '/', __DIR__) . '/');

// Include autoloader and dependencies
require_once ABSPATH . 'vendor/autoload.php';

// Load environment configuration
if (file_exists(ABSPATH . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ABSPATH);
    $dotenv->load();
}

// Register app namespace for autoloading
spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    $file = ABSPATH . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Set basic configuration for MCP server
date_default_timezone_set('America/New_York');
error_reporting(E_ALL);

use app\controllers\MCPServerController;

/**
 * Main MCP Server class for stdio communication
 */
class MCPStdioServer
{
    private MCPServerController $controller;
    private $logFile;

    public function __construct()
    {
        $this->controller = new MCPServerController();
        
        // Enable error logging for debugging
        $this->logFile = ABSPATH . 'mcp-server.log';
        
        // Disable output buffering to ensure immediate response
        ob_implicit_flush(true);
        
        // Set error handlers
        set_error_handler([$this, 'errorHandler']);
        set_exception_handler([$this, 'exceptionHandler']);
    }

    /**
     * Start the stdio server loop
     */
    public function start(): void
    {
        $this->log("MCP Server started at " . date('Y-m-d H:i:s'));
        
        // Read from stdin line by line
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            $this->log("Received: " . $line);
            
            try {
                // Parse JSON request
                $request = json_decode($line, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->sendError(null, -32700, 'Parse error: ' . json_last_error_msg());
                    continue;
                }
                
                // Process request
                $response = $this->controller->handleRequest($request);
                
                // Send response
                $this->sendResponse($response);
                
            } catch (Exception $e) {
                $this->log("Exception: " . $e->getMessage());
                $this->sendError(
                    $request['id'] ?? null, 
                    -32603, 
                    'Internal error: ' . $e->getMessage()
                );
            }
        }
        
        $this->log("MCP Server stopped at " . date('Y-m-d H:i:s'));
    }

    /**
     * Send JSON-RPC response to stdout
     */
    private function sendResponse(array $response): void
    {
        $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            $this->log("JSON encode error: " . json_last_error_msg());
            return;
        }
        
        $this->log("Sending: " . $json);
        
        // Write to stdout
        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
    }

    /**
     * Send JSON-RPC error response
     */
    private function sendError(?int $id, int $code, string $message): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        $this->sendResponse($response);
    }

    /**
     * Log message to file for debugging
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Custom error handler
     */
    public function errorHandler(int $severity, string $message, string $file, int $line): void
    {
        $this->log("PHP Error [$severity]: $message in $file:$line");
    }

    /**
     * Custom exception handler
     */
    public function exceptionHandler(Throwable $exception): void
    {
        $this->log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
        
        $this->sendError(null, -32603, 'Internal server error');
        exit(1);
    }
}

// Check if running in CLI mode
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from command line\n";
    exit(1);
}

// Start the MCP server
try {
    $server = new MCPStdioServer();
    $server->start();
} catch (Exception $e) {
    error_log("Failed to start MCP server: " . $e->getMessage());
    exit(1);
}
