#!/usr/bin/env pwsh

Write-Host "🚀 Testing MCP HTTP Server..." -ForegroundColor Green
Write-Host ""

# Start HTTP server using composer script
Write-Host "Starting HTTP server on localhost:8000..." -ForegroundColor Yellow
$serverProcess = Start-Process -FilePath "composer" -ArgumentList "start" -WindowStyle Hidden -PassThru

# Wait for server to start
Start-Sleep -Seconds 5

try {
    Write-Host "📡 Testing all endpoints..." -ForegroundColor Cyan
    Write-Host ""
    
    # Test GET endpoint
    Write-Host "1. Testing GET / endpoint..." -ForegroundColor Yellow
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000/" -UseBasicParsing -TimeoutSec 10
        Write-Host "✅ GET / successful (Status: $($response.StatusCode))" -ForegroundColor Green
        $jsonResponse = $response.Content | ConvertFrom-Json
        Write-Host "   📄 Server Info: $($jsonResponse.name) v$($jsonResponse.version)" -ForegroundColor Gray
    }
    catch {
        Write-Host "❌ GET / failed: $($_.Exception.Message)" -ForegroundColor Red
    }

    Write-Host ""
    
    # Test health endpoint
    Write-Host "2. Testing GET /health endpoint..." -ForegroundColor Yellow
    try {
        $healthResponse = Invoke-WebRequest -Uri "http://localhost:8000/health" -UseBasicParsing -TimeoutSec 10
        Write-Host "✅ GET /health successful (Status: $($healthResponse.StatusCode))" -ForegroundColor Green
        $healthJson = $healthResponse.Content | ConvertFrom-Json
        Write-Host "   💚 Status: $($healthJson.status) | Timestamp: $($healthJson.timestamp)" -ForegroundColor Gray
    }
    catch {
        Write-Host "❌ GET /health failed: $($_.Exception.Message)" -ForegroundColor Red
    }

    Write-Host ""

    # Test JSON-RPC initialize
    Write-Host "3. Testing JSON-RPC initialize method..." -ForegroundColor Yellow
    try {
        $initBody = @{
            jsonrpc = "2.0"
            id = 1
            method = "initialize"
            params = @{}
        } | ConvertTo-Json

        $initResponse = Invoke-RestMethod -Uri "http://localhost:8000/" -Method POST -Body $initBody -ContentType "application/json" -TimeoutSec 10
        Write-Host "✅ JSON-RPC initialize successful" -ForegroundColor Green
        Write-Host "   🔧 Protocol Version: $($initResponse.result.protocolVersion)" -ForegroundColor Gray
        Write-Host "   📝 Instructions: $($initResponse.result.instructions)" -ForegroundColor Gray
    }
    catch {
        Write-Host "❌ JSON-RPC initialize failed: $($_.Exception.Message)" -ForegroundColor Red
    }

    Write-Host ""

    # Test JSON-RPC tools/list
    Write-Host "4. Testing JSON-RPC tools/list method..." -ForegroundColor Yellow
    try {
        $toolsBody = @{
            jsonrpc = "2.0"
            id = 2
            method = "tools/list"
            params = @{}
        } | ConvertTo-Json

        $toolsResponse = Invoke-RestMethod -Uri "http://localhost:8000/" -Method POST -Body $toolsBody -ContentType "application/json" -TimeoutSec 10
        Write-Host "✅ JSON-RPC tools/list successful" -ForegroundColor Green
        $toolCount = ($toolsResponse.result | Get-Member -MemberType NoteProperty).Count
        Write-Host "   🛠️  Available tools: $toolCount" -ForegroundColor Gray
        foreach ($toolName in $toolsResponse.result.PSObject.Properties.Name) {
            $tool = $toolsResponse.result.$toolName
            Write-Host "      - $($tool.name): $($tool.description)" -ForegroundColor Gray
        }
    }
    catch {
        Write-Host "❌ JSON-RPC tools/list failed: $($_.Exception.Message)" -ForegroundColor Red
    }

    Write-Host ""

    # Test JSON-RPC tools/call
    Write-Host "5. Testing JSON-RPC tools/call method..." -ForegroundColor Yellow
    try {
        $callBody = @{
            jsonrpc = "2.0"
            id = 3
            method = "tools/call"
            params = @{
                name = "hello-world-tool"
                arguments = @{
                    firstName = "HTTP"
                    lastName = "Server"
                }
            }
        } | ConvertTo-Json -Depth 3

        $callResponse = Invoke-RestMethod -Uri "http://localhost:8000/" -Method POST -Body $callBody -ContentType "application/json" -TimeoutSec 10
        Write-Host "✅ JSON-RPC tools/call successful" -ForegroundColor Green
        Write-Host "   💬 Tool result: $($callResponse.result.message)" -ForegroundColor Gray
    }
    catch {
        Write-Host "❌ JSON-RPC tools/call failed: $($_.Exception.Message)" -ForegroundColor Red
    }

    Write-Host ""

    # Test JSON-RPC prompts/list
    Write-Host "6. Testing JSON-RPC prompts/list method..." -ForegroundColor Yellow
    try {
        $promptsBody = @{
            jsonrpc = "2.0"
            id = 4
            method = "prompts/list"
            params = @{}
        } | ConvertTo-Json

        $promptsResponse = Invoke-RestMethod -Uri "http://localhost:8000/" -Method POST -Body $promptsBody -ContentType "application/json" -TimeoutSec 10
        Write-Host "✅ JSON-RPC prompts/list successful" -ForegroundColor Green
        $promptCount = ($promptsResponse.result | Get-Member -MemberType NoteProperty).Count
        Write-Host "   📜 Available prompts: $promptCount" -ForegroundColor Gray
        foreach ($promptName in $promptsResponse.result.PSObject.Properties.Name) {
            $prompt = $promptsResponse.result.$promptName
            Write-Host "      - $($prompt.name): $($prompt.description)" -ForegroundColor Gray
        }
    }
    catch {
        Write-Host "❌ JSON-RPC prompts/list failed: $($_.Exception.Message)" -ForegroundColor Red
    }

}
finally {
    # Stop the server
    Write-Host ""
    Write-Host "🛑 Stopping HTTP server..." -ForegroundColor Yellow
    if ($serverProcess -and !$serverProcess.HasExited) {
        $serverProcess.Kill()
        $serverProcess.WaitForExit(5000)
    }
    
    # Also kill any remaining PHP processes that might be running on port 8000
    Get-Process | Where-Object { 
        $_.ProcessName -eq "php" -and 
        $_.CommandLine -like "*8000*" 
    } | Stop-Process -Force -ErrorAction SilentlyContinue
}

Write-Host ""
Write-Host "🎉 HTTP Server tests completed!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Summary:" -ForegroundColor Cyan
Write-Host "   • Server runs on: http://localhost:8000" -ForegroundColor White
Write-Host "   • Health check: http://localhost:8000/health" -ForegroundColor White
Write-Host "   • JSON-RPC endpoint: POST http://localhost:8000/" -ForegroundColor White
Write-Host "   • Available tools: hello-world-tool" -ForegroundColor White
Write-Host "   • Available prompts: generate_sql" -ForegroundColor White
