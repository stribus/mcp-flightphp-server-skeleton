@echo off
echo Starting MCP Server for VS Code Copilot...
echo.
echo To test, you can send JSON-RPC messages like:
echo {"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}
echo.
echo Press Ctrl+C to stop the server
echo.

php mcp-server.php
