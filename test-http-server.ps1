#!/usr/bin/env pwsh
#
# Exercises the Streamable HTTP transport against the local server.
#
# Focuses on the transport contract defined by the MCP specification:
# session handling, notification status codes, Origin validation and protocol
# version negotiation. Tool and prompt payload shapes are reported but not
# asserted here - test-mcp-server.ps1 covers those over stdio.

$ErrorActionPreference = 'Continue'
$BaseUrl = 'http://localhost:8000'
$Endpoint = "$BaseUrl/mcp"
$Protocol = '2025-06-18'

$script:Passed = 0
$script:Failed = 0

function Assert-That {
    param([string]$Name, [bool]$Condition, [string]$Detail = '')

    if ($Condition) {
        Write-Host "  PASS  $Name" -ForegroundColor Green
        $script:Passed++
    } else {
        Write-Host "  FAIL  $Name" -ForegroundColor Red
        if ($Detail) { Write-Host "        $Detail" -ForegroundColor DarkYellow }
        $script:Failed++
    }
}

function Invoke-Mcp {
    param(
        [string]$Body,
        [hashtable]$Headers = @{},
        [string]$Method = 'POST'
    )

    $Headers['Content-Type'] = 'application/json'
    try {
        return Invoke-WebRequest -Uri $Endpoint -Method $Method -Body $Body `
            -Headers $Headers -UseBasicParsing -TimeoutSec 10 -SkipHttpErrorCheck
    } catch {
        return $null
    }
}

Write-Host 'Starting HTTP server on localhost:8000...' -ForegroundColor Yellow
$serverProcess = Start-Process -FilePath 'composer' -ArgumentList 'start' -WindowStyle Hidden -PassThru
Start-Sleep -Seconds 5

try {
    Write-Host ''
    Write-Host 'Informational endpoints' -ForegroundColor Cyan

    $root = Invoke-WebRequest -Uri "$BaseUrl/" -UseBasicParsing -TimeoutSec 10 -SkipHttpErrorCheck
    Assert-That 'GET / returns 200' ($root.StatusCode -eq 200) "got $($root.StatusCode)"

    $health = Invoke-WebRequest -Uri "$BaseUrl/health" -UseBasicParsing -TimeoutSec 10 -SkipHttpErrorCheck
    Assert-That 'GET /health returns 200' ($health.StatusCode -eq 200) "got $($health.StatusCode)"

    Write-Host ''
    Write-Host 'Initialization' -ForegroundColor Cyan

    $initBody = '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"' + $Protocol + '"}}'
    $init = Invoke-Mcp -Body $initBody

    Assert-That 'POST /mcp initialize returns 200' ($init.StatusCode -eq 200) "got $($init.StatusCode)"

    $initJson = $init.Content | ConvertFrom-Json
    Assert-That 'initialize reports serverInfo' ($null -ne $initJson.result.serverInfo.name) 'serverInfo is required by the spec'
    Assert-That 'initialize reports protocolVersion' ($initJson.result.protocolVersion -eq $Protocol)

    # capabilities must be a JSON object, never an array.
    $capsRaw = ($init.Content | Select-String -Pattern '"capabilities":(\[|\{)').Matches[0].Groups[1].Value
    Assert-That 'capabilities is an object, not an array' ($capsRaw -eq '{') "serialized as $capsRaw"

    $sessionId = $init.Headers['Mcp-Session-Id']
    if ($sessionId -is [array]) { $sessionId = $sessionId[0] }
    Assert-That 'initialize issues Mcp-Session-Id' (-not [string]::IsNullOrWhiteSpace($sessionId))

    $session = @{ 'Mcp-Session-Id' = $sessionId; 'MCP-Protocol-Version' = $Protocol }

    Write-Host ''
    Write-Host 'Transport rules' -ForegroundColor Cyan

    $notif = Invoke-Mcp -Body '{"jsonrpc":"2.0","method":"notifications/initialized"}' -Headers $session.Clone()
    Assert-That 'notification returns 202' ($notif.StatusCode -eq 202) "got $($notif.StatusCode)"
    Assert-That 'notification returns no body' ($notif.Content.Length -eq 0) "body was $($notif.Content.Length) bytes"

    $sse = Invoke-WebRequest -Uri $Endpoint -Method GET -Headers $session.Clone() `
        -UseBasicParsing -TimeoutSec 10 -SkipHttpErrorCheck
    Assert-That 'GET /mcp returns 405 while SSE is disabled' ($sse.StatusCode -eq 405) "got $($sse.StatusCode)"

    $evil = Invoke-Mcp -Body '{"jsonrpc":"2.0","id":2,"method":"ping"}' `
        -Headers @{ 'Origin' = 'https://evil.example.com' }
    Assert-That 'foreign Origin is rejected with 403' ($evil.StatusCode -eq 403) "got $($evil.StatusCode)"

    $local = Invoke-Mcp -Body '{"jsonrpc":"2.0","id":3,"method":"ping"}' `
        -Headers @{ 'Origin' = 'http://localhost:3000' }
    Assert-That 'localhost Origin is accepted' ($local.StatusCode -eq 200) "got $($local.StatusCode)"

    $badVersion = Invoke-Mcp -Body '{"jsonrpc":"2.0","id":4,"method":"ping"}' `
        -Headers @{ 'MCP-Protocol-Version' = '1999-01-01' }
    Assert-That 'unsupported MCP-Protocol-Version returns 400' ($badVersion.StatusCode -eq 400) "got $($badVersion.StatusCode)"

    Write-Host ''
    Write-Host 'MCP methods' -ForegroundColor Cyan

    $tools = Invoke-Mcp -Body '{"jsonrpc":"2.0","id":5,"method":"tools/list","params":{}}' -Headers $session.Clone()
    Assert-That 'tools/list returns 200' ($tools.StatusCode -eq 200) "got $($tools.StatusCode)"
    Write-Host "        $($tools.Content)" -ForegroundColor DarkGray

    $callBody = '{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"hello-world-tool","arguments":{"firstName":"HTTP","lastName":"Server"}}}'
    $call = Invoke-Mcp -Body $callBody -Headers $session.Clone()
    Assert-That 'tools/call returns 200' ($call.StatusCode -eq 200) "got $($call.StatusCode)"
    Write-Host "        $($call.Content)" -ForegroundColor DarkGray

    $prompts = Invoke-Mcp -Body '{"jsonrpc":"2.0","id":7,"method":"prompts/list","params":{}}' -Headers $session.Clone()
    Assert-That 'prompts/list returns 200' ($prompts.StatusCode -eq 200) "got $($prompts.StatusCode)"
    Write-Host "        $($prompts.Content)" -ForegroundColor DarkGray

    Write-Host ''
    Write-Host 'Session teardown' -ForegroundColor Cyan

    $delete = Invoke-WebRequest -Uri $Endpoint -Method DELETE -Headers $session.Clone() `
        -UseBasicParsing -TimeoutSec 10 -SkipHttpErrorCheck
    Assert-That 'DELETE /mcp returns 204' ($delete.StatusCode -eq 204) "got $($delete.StatusCode)"

    $afterDelete = Invoke-Mcp -Body '{"jsonrpc":"2.0","id":8,"method":"ping"}' -Headers $session.Clone()
    Assert-That 'terminated session returns 404' ($afterDelete.StatusCode -eq 404) "got $($afterDelete.StatusCode)"
}
finally {
    Write-Host ''
    Write-Host 'Stopping HTTP server...' -ForegroundColor Yellow
    if ($serverProcess -and !$serverProcess.HasExited) {
        $serverProcess.Kill()
        $serverProcess.WaitForExit(5000)
    }
    Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like '*localhost:8000*' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

Write-Host ''
Write-Host "Passed: $script:Passed   Failed: $script:Failed" -ForegroundColor $(if ($script:Failed -eq 0) { 'Green' } else { 'Red' })
Write-Host ''
Write-Host 'Endpoints:' -ForegroundColor Cyan
Write-Host "   POST   $Endpoint   JSON-RPC 2.0" -ForegroundColor White
Write-Host "   GET    $Endpoint   SSE stream (requires MCP_HTTP_SSE=true)" -ForegroundColor White
Write-Host "   DELETE $Endpoint   Terminate a session" -ForegroundColor White
Write-Host "   GET    $BaseUrl/health" -ForegroundColor White

if ($script:Failed -gt 0) { exit 1 }
