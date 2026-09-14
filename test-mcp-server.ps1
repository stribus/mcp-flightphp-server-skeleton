#!/usr/bin/env pwsh
#
# Exercises the stdio transport and checks each response against the shapes
# required by MCP specification revision 2025-06-18.
#
# Each `php mcp-server.php` invocation handles one line and exits.

$ErrorActionPreference = 'Continue'
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

function Invoke-Stdio {
    param([string]$Message)
    return ($Message | php mcp-server.php)
}

Write-Host 'Testing the MCP stdio transport...' -ForegroundColor Cyan
Write-Host ''

Write-Host 'Handshake' -ForegroundColor Cyan

$raw = Invoke-Stdio ('{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"' + $Protocol + '"}}')
$init = $raw | ConvertFrom-Json
Assert-That 'initialize returns protocolVersion' ($init.result.protocolVersion -eq $Protocol)
Assert-That 'initialize returns serverInfo' ($null -ne $init.result.serverInfo.name)
Assert-That 'capabilities is an object, not an array' ($raw -match '"capabilities":\{')

# A notification carries no id and must produce no output whatsoever.
$notif = Invoke-Stdio '{"jsonrpc":"2.0","method":"notifications/initialized"}'
Assert-That 'notification produces no output' ([string]::IsNullOrWhiteSpace($notif)) "got: $notif"

$ping = Invoke-Stdio '{"jsonrpc":"2.0","id":2,"method":"ping"}' | ConvertFrom-Json
Assert-That 'ping returns a result' ($null -ne $ping.result)

Write-Host ''
Write-Host 'Tools' -ForegroundColor Cyan

$rawTools = Invoke-Stdio '{"jsonrpc":"2.0","id":3,"method":"tools/list","params":{}}'
$tools = $rawTools | ConvertFrom-Json
Assert-That 'tools/list wraps an array in "tools"' ($rawTools -match '"tools":\[')
Assert-That 'tools/list includes hello-world-tool' (@($tools.result.tools | Where-Object { $_.name -eq 'hello-world-tool' }).Count -eq 1)

$callBody = '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"hello-world-tool","arguments":{"firstName":"VS Code","lastName":"Copilot"}}}'
$call = Invoke-Stdio $callBody | ConvertFrom-Json
Assert-That 'tools/call returns a content array' (@($call.result.content).Count -ge 1)
Assert-That 'tools/call content block is typed' ($call.result.content[0].type -eq 'text')
Assert-That 'tools/call reports isError false' ($call.result.isError -eq $false)
Assert-That 'tools/call returns structuredContent' ($null -ne $call.result.structuredContent.message) 'required when the tool declares an outputSchema'

$missing = Invoke-Stdio '{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"hello-world-tool","arguments":{}}}' | ConvertFrom-Json
Assert-That 'missing required argument returns -32602' ($missing.error.code -eq -32602) "got $($missing.error.code)"

$unknown = Invoke-Stdio '{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"no-such-tool","arguments":{}}}' | ConvertFrom-Json
Assert-That 'unknown tool returns -32602' ($unknown.error.code -eq -32602) "got $($unknown.error.code)"

Write-Host ''
Write-Host 'Prompts' -ForegroundColor Cyan

$rawPrompts = Invoke-Stdio '{"jsonrpc":"2.0","id":7,"method":"prompts/list","params":{}}'
$prompts = $rawPrompts | ConvertFrom-Json
Assert-That 'prompts/list wraps an array in "prompts"' ($rawPrompts -match '"prompts":\[')
Assert-That 'prompts/list drops the non-standard promptText' ($rawPrompts -notmatch 'promptText')

$sql = $prompts.result.prompts | Where-Object { $_.name -eq 'generate_sql' }
Assert-That 'declared prompt arguments are reported' (@($sql.arguments).Count -eq 2) "got $(@($sql.arguments).Count)"

$get = Invoke-Stdio '{"jsonrpc":"2.0","id":8,"method":"prompts/get","params":{"name":"generate_sql","arguments":{"table":"users","columns":["id"]}}}' | ConvertFrom-Json
Assert-That 'prompts/get returns messages' (@($get.result.messages).Count -ge 1)
Assert-That 'prompts/get message has a role' ($get.result.messages[0].role -eq 'user')
Assert-That 'prompts/get message content is typed' ($get.result.messages[0].content.type -eq 'text')
Assert-That 'prompts/get honours params.arguments' ($get.result.messages[0].content.text -match 'users')

Write-Host ''
Write-Host 'Resources' -ForegroundColor Cyan

$rawResources = Invoke-Stdio '{"jsonrpc":"2.0","id":9,"method":"resources/list","params":{}}'
$resources = $rawResources | ConvertFrom-Json
Assert-That 'resources/list wraps an array in "resources"' ($rawResources -match '"resources":\[')
Assert-That 'each resource carries the required uri' ($null -ne $resources.result.resources[0].uri)

$read = Invoke-Stdio '{"jsonrpc":"2.0","id":10,"method":"resources/read","params":{"uri":"config://server"}}' | ConvertFrom-Json
Assert-That 'resources/read returns a contents array' (@($read.result.contents).Count -ge 1)
Assert-That 'contents entry carries uri and mimeType' ($null -ne $read.result.contents[0].uri -and $null -ne $read.result.contents[0].mimeType)

$noResource = Invoke-Stdio '{"jsonrpc":"2.0","id":11,"method":"resources/read","params":{"uri":"nothing://here"}}' | ConvertFrom-Json
Assert-That 'unknown resource returns -32002' ($noResource.error.code -eq -32002) "got $($noResource.error.code)"

Write-Host ''
Write-Host "Passed: $script:Passed   Failed: $script:Failed" -ForegroundColor $(if ($script:Failed -eq 0) { 'Green' } else { 'Red' })
Write-Host 'Detailed request and response logs are in mcp-server.log' -ForegroundColor DarkGray

if ($script:Failed -gt 0) { exit 1 }
