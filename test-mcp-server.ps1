#!/usr/bin/env pwsh

Write-Host "Testing MCP Server..." -ForegroundColor Green
Write-Host ""

# Test initialize
Write-Host "1. Testing initialize method..." -ForegroundColor Yellow
'{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' | php mcp-server.php

Write-Host ""
Write-Host "2. Testing tools/list method..." -ForegroundColor Yellow
'{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' | php mcp-server.php

Write-Host ""
Write-Host "3. Testing tools/call method..." -ForegroundColor Yellow
'{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"hello-world-tool","arguments":{"firstName":"VS Code","lastName":"Copilot"}}}' | php mcp-server.php

Write-Host ""
Write-Host "4. Testing prompts/list method..." -ForegroundColor Yellow
'{"jsonrpc":"2.0","id":4,"method":"prompts/list","params":{}}' | php mcp-server.php

Write-Host ""
Write-Host "Tests completed! Check mcp-server.log for detailed logs." -ForegroundColor Green
