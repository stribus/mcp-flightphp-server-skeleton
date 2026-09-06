# MCP Server for VS Code Copilot

A PHP-based Model Context Protocol (MCP) server skeleton that supports both **HTTP** and **stdio** communication methods. This server can be integrated with VS Code Copilot and other MCP-compatible clients.

## Features

- **Dual Communication**: HTTP REST API and stdio (JSON-RPC 2.0)
- **Auto-discovery**: Automatically discovers tools, prompts, and resources
- **Code Generation**: Built-in commands to generate new tools and prompts
- **Flight PHP Framework**: Lightweight and fast PHP framework
- **Logging**: Comprehensive logging for debugging
- **Testing Scripts**: Ready-to-use test scripts for both modes

## Quick Start

1. **Install**
Run this command from the directory in which you want to install your new Flight PHP application. (this will require PHP 7.4 or newer)

   ```bash
    composer create-project stribus/mcp-flightphp-server-skeleton cool-project-name
   ```

2. **Configure the environment**

   ```bash
   cp .env.example .env      # Linux / macOS
   copy .env.example .env    # Windows
   ```

   `.env` is git-ignored — keep your credentials out of version control. The skeleton also runs without a `.env`;
   see the comments in `.env.example` for the database variables.

3. **Test HTTP Server**

   ```bash
   composer start
   # or
   php -S localhost:8000 -t public
   ```

4. **Test stdio Server**

   ```bash
   php mcp-server.php
   ```

## Usage Methods

### 1. HTTP Server Mode

The HTTP server exposes MCP functionality via REST endpoints:

**Start the server:**

```bash
composer start
```

**Available endpoints:**

MCP is a JSON-RPC protocol, not REST: every method travels in the body of a POST to a
single endpoint. There is no `/tools/list` URL.

- `POST /mcp` - the MCP endpoint. Every JSON-RPC method goes here.
- `GET /mcp` - Server-Sent Events stream. Disabled by default; see below.
- `DELETE /mcp` - terminate a session.
- `GET /health` - health check.
- `GET /` - this documentation, as JSON.

**Sessions:** a successful `initialize` returns an `Mcp-Session-Id` header. Send it back on
every subsequent request. After initialization, clients must also send
`MCP-Protocol-Version: 2025-06-18`.

**Origin validation:** the endpoint rejects browser requests from unknown origins with 403,
as the spec requires to prevent DNS rebinding attacks. Configure with `MCP_ALLOWED_ORIGINS`.

**Test the HTTP server:**

```bash
# Windows PowerShell - full transport test suite
.\test-http-server.ps1

# Or manually
curl -i -X POST http://localhost:8000/mcp -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}'
```

**Server-Sent Events are off by default.** `GET /mcp` answers `405`, which the specification
explicitly permits. The reason is measured, not theoretical: PHP's built-in server
(`composer start`) handles one request at a time, and on Windows it cannot fork at all -
setting `PHP_CLI_SERVER_WORKERS` makes PHP print `forking is not supported on this platform`.
An open stream would therefore make the whole server unresponsive. Set `MCP_HTTP_SSE=true`
only when running behind Apache or nginx with php-fpm.

### 2. stdio Mode (JSON-RPC 2.0)

The stdio server communicates via standard input/output using JSON-RPC 2.0 protocol:

**Start the server:**

```bash
php mcp-server.php
```

**Test JSON-RPC commands:**

```bash
# Windows PowerShell
.\test-mcp-server.ps1

# Or send commands manually
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' | php mcp-server.php
echo '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' | php mcp-server.php
echo '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"hello-world-tool","arguments":{"firstName":"VS Code","lastName":"Copilot"}}}' | php mcp-server.php
```

### 3. VS Code Integration

This repository ships a ready-to-use `.vscode/mcp.json`. It needs no editing, because
VS Code expands `${workspaceFolder}` to wherever you cloned the project:

```json
{
  "servers": {
    "flightphp-mcp-skeleton": {
      "type": "stdio",
      "command": "php",
      "args": ["${workspaceFolder}/mcp-server.php"]
    }
  }
}
```

For clients that cannot expand variables, such as Claude Desktop, `composer create-project`
generates `mcp-config.json` with this installation's absolute path already filled in. A
template lives in `mcp-config.example.json`; regenerate at any time with:

```bash
php scripts/generate-mcp-config.php
```

`mcp-config.json` is git-ignored, since its contents are specific to one machine.

## Creating Tools

Tools are executable functions that can be called by MCP clients.

### Generate a new tool:

```bash
vendor/bin/runway make:tool MyCustomTool
```

### Tool Structure:

```php
<?php

namespace app\tools;

use app\helpers\AbstractMCPTool;

class MyCustomTool extends AbstractMCPTool
{
    protected string $name = 'my-custom-tool';
    protected string $description = 'Description of what this tool does';
    protected ?string $title = 'My Custom Tool';

    protected array $arguments = [
        [
            'name' => 'input',
            'type' => 'string',
            'description' => 'Input parameter description',
            'required' => true,
        ],
    ];

    protected null|array|string $outputSchema = [
        'type' => 'object',
        'properties' => [
            'result' => [
                'type' => 'string',
                'description' => 'The result of the operation',
            ],
        ],
    ];

    public function execute(array $arguments): mixed
    {
        $input = $arguments['input'] ?? '';
        
        // Your tool logic here
        
        return [
            'result' => 'Processed: ' . $input,
        ];
    }
}
```

### Tool Properties:

- **`$name`**: Unique identifier for the tool
- **`$description`**: What the tool does
- **`$title`**: Human-readable title
- **`$arguments`**: Array of input parameters with types and validation
- **`$outputSchema`**: Expected output structure
- **`execute()`**: Main logic that processes arguments and returns results

## Creating Prompts

Prompts are templates that can generate dynamic text based on context.

### Generate a new prompt:

```bash
vendor/bin/runway make:prompt MyCustomPrompt
```

### Prompt Structure:

```php
<?php

namespace app\prompts;

use app\helpers\AbstractMCPPrompt;

class MyCustomPrompt extends AbstractMCPPrompt
{
    protected string $name = 'my_custom_prompt';
    protected string $description = 'Generate custom content based on input';
    protected ?string $title = 'My Custom Prompt';
    
    protected array $arguments = [
        'topic' => [
            'type' => 'string',
            'description' => 'The topic to generate content about',
            'required' => true,
        ],
        'style' => [
            'type' => 'string',
            'description' => 'Writing style (formal, casual, technical)',
            'required' => false,
        ],
    ];

    public function getPromptText(array $context): string
    {
        $topic = $context['topic'] ?? '';
        $style = $context['style'] ?? 'formal';

        return "Write a {$style} explanation about {$topic}. " .
               "Include examples and practical applications.";
    }
}
```

### Prompt Properties:

- **`$name`**: Unique identifier for the prompt
- **`$description`**: What the prompt generates
- **`$title`**: Human-readable title
- **`$arguments`**: Context variables the prompt expects
- **`getPromptText()`**: Method that generates the prompt text based on context

## Project Structure

```
├── app/
│   ├── tools/           # Tool implementations
│   ├── prompts/         # Prompt implementations
│   ├── resources/       # Resource implementations
│   ├── helpers/         # Abstract base classes
│   └── core/            # MCP service registries
├── commands/            # Runway commands for code generation
├── public/              # HTTP server entry point
├── mcp-server.php       # stdio server entry point
└── vendor/              # Dependencies
```

## Available MCP Methods

### JSON-RPC 2.0 Methods:

- **`initialize`** - Initialize the MCP connection
- **`tools/list`** - List all available tools
- **`tools/call`** - Execute a specific tool
- **`prompts/list`** - List all available prompts
- **`prompts/get`** - Get a specific prompt
- **`resources/list`** - List all available resources
- **`resources/read`** - Read a specific resource

## Logging and Debugging

The server generates detailed logs in `mcp-server.log` for debugging purposes. Monitor this file to see:

- Incoming requests
- Server responses
- Errors and exceptions
- Tool execution details

## Common Issues

1. **Server won't start**: Ensure PHP is in your PATH and dependencies are installed
2. **JSON parsing errors**: Verify JSON-RPC message formatting
3. **Tool not found**: Check tool is properly registered and follows naming conventions
4. **Permission errors**: Verify write permissions for log files

## Requirements

- PHP 7.4 or higher
- Composer
- ext-json extension

## License

MIT License - see LICENSE file for details.
