#!/usr/bin/env pwsh

Write-Host "Starting MCP Server for VS Code Copilot..." -ForegroundColor Green
Write-Host ""
Write-Host "To test, you can send JSON-RPC messages like:" -ForegroundColor Yellow
Write-Host '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' -ForegroundColor Cyan
Write-Host ""
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Yellow
Write-Host ""

php mcp-server.php
