# Connecting MCP clients

This skeleton speaks two transports — **stdio** and **Streamable HTTP** — and ships ready-made
configuration for the three clients most people use. None of the committed files contain a path
from anyone's machine: each client has its own way of naming the project root, and the files below
use it.

> A Portuguese translation of this page is available at
> [`CLIENTS-MCP-SETUP.pt-BR.md`](CLIENTS-MCP-SETUP.pt-BR.md).

## At a glance

The three clients disagree on almost every detail, which is where most setup mistakes come from:

| Client | Config file | Top-level key | Project-root variable |
|---|---|---|---|
| Claude Code | `.mcp.json` (project root) | `mcpServers` | `${CLAUDE_PROJECT_DIR}` |
| VS Code | `.vscode/mcp.json` | `servers` | `${workspaceFolder}` |
| Claude Desktop | `mcp-config.json` | `mcpServers` | none — needs an absolute path |

The first two are committed to this repository and need no editing. The third is generated at
install time.

## Claude Code

### stdio — the default

`.mcp.json` is already in the repository root and requires no changes:

```json
{
  "mcpServers": {
    "flightphp-mcp-skeleton": {
      "type": "stdio",
      "command": "php",
      "args": ["${CLAUDE_PROJECT_DIR:-.}/mcp-server.php"]
    }
  }
}
```

The `${VAR:-default}` form matters here. `CLAUDE_PROJECT_DIR` is the documented way to name the
project root, and it is used when Claude Code provides it. When it does not — it was absent on
Claude Code 2.1.263, for instance — the `.` fallback takes over, and Claude Code launches
project-scoped servers from the project directory, so the relative path resolves to the same file.

Without the fallback, Claude Code does not fail outright: it logs a missing-variable warning and
passes the unexpanded `${CLAUDE_PROJECT_DIR}` text through as a literal path, which is easy to
miss. The fallback avoids that entirely.

Open the project in Claude Code. Because this is a *project-scoped* server, Claude Code asks you
to approve it the first time. Then check it with the `/mcp` slash command, or from a terminal:

```bash
claude mcp list
```

The server should appear as connected, with no warning underneath. If it does not connect, see
Troubleshooting below for the absolute-path escape hatch.

### Adding it by hand, in another scope

The committed file covers the *project* scope. For a personal entry that is not shared, use the
CLI. Note the `--` separator: it is mandatory, and everything after it is passed to the server
untouched.

```bash
# Just for you, only in this project (the default scope)
claude mcp add flightphp-mcp-skeleton -- php "$PWD/mcp-server.php"

# Just for you, in every project
claude mcp add --scope user flightphp-mcp-skeleton -- php /absolute/path/to/mcp-server.php
```

Scopes write to different places: `local` and `user` both live in `~/.claude.json`, while
`project` is the `.mcp.json` file you already have.

### HTTP — the alternative

The skeleton also serves MCP over HTTP, which Claude Code supports directly:

```bash
composer start   # in one terminal
claude mcp add --transport http flightphp-mcp-http http://localhost:8000/mcp
```

The trade-off is that **you have to start the server yourself**. With stdio, Claude Code launches
the process for you and shuts it down afterwards. Use HTTP when the server already runs somewhere,
or when you want several clients sharing one instance.

Do not use `--transport sse`: the SSE transport is deprecated in Claude Code, and its
documentation directs you to HTTP servers instead. This skeleton ships SSE turned off by default
for a related reason — see the note in [`README.md`](README.md).

To remove a server:

```bash
claude mcp remove flightphp-mcp-skeleton
```

## VS Code

`.vscode/mcp.json` is committed and needs no editing:

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

Two details differ from Claude Code and are easy to get wrong: the top-level key is `servers`, not
`mcpServers`, and the project-root variable is `${workspaceFolder}`.

## Claude Desktop

Claude Desktop expands no variables, so it needs a real absolute path. Rather than asking you to
edit a placeholder, `composer create-project` writes `mcp-config.json` with the correct path for
your installation. Regenerate it whenever the project moves:

```bash
php scripts/generate-mcp-config.php --force
```

The result looks like this, with your own path in place:

```json
{
  "mcpServers": {
    "flightphp-mcp-skeleton": {
      "command": "php",
      "args": ["/absolute/path/to/your-project/mcp-server.php"],
      "env": {}
    }
  }
}
```

Copy that block into Claude Desktop's own configuration file. `mcp-config.json` is git-ignored,
because its contents are specific to one machine; the versioned template is
`mcp-config.example.json`.

## Verifying the connection

Independently of any client, you can talk to the server directly:

```bash
# stdio
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' | php mcp-server.php

# Full test suites
.\test-mcp-server.ps1     # stdio, 25 assertions
.\test-http-server.ps1    # HTTP transport, 18 assertions
```

A healthy `initialize` response carries `protocolVersion`, `capabilities` and `serverInfo`.

## Troubleshooting

**The server does not appear.** Confirm `php` is on your `PATH` — every configuration above
invokes it by name. `php --version` should work from the same terminal that launches the client.

**Claude Code cannot find `mcp-server.php`.** The committed `.mcp.json` resolves the path through
`${CLAUDE_PROJECT_DIR:-.}`, which relies either on Claude Code setting that variable or on it
launching the server from the project directory. If neither holds in your setup, register the
server with an absolute path instead:

```bash
claude mcp add flightphp-mcp-skeleton -- php "$PWD/mcp-server.php"
```

That writes a *local*-scope entry, which takes effect for you without changing the shared file.
Alternatively, export `CLAUDE_PROJECT_DIR` yourself before launching Claude Code.

**It connects but exposes no tools.** Tools are discovered by scanning `app/tools`, so a class is
only found if it implements `MCPToolInterface` and its constructor takes no arguments.
`ClassAutoLoader` instantiates with no parameters, so a required constructor argument makes the
class silently invisible.

**Nothing works over stdio, and the output looks like HTML.** Something wrote to stdout that was
not an MCP message. The specification forbids that, and it corrupts the stream. Check for stray
`echo`, `var_dump` or `print_r` calls in your tools; write to `mcp-server.log` instead.

**The HTTP transport returns 403.** The endpoint validates the `Origin` header against an
allowlist to prevent DNS rebinding attacks. Native clients send no `Origin` and are unaffected;
browser-based ones need `MCP_ALLOWED_ORIGINS` set in `.env`.

**Logs.** The stdio server appends every request and response to `mcp-server.log`. That file is
git-ignored and grows on each run.

## Adding your own tools

```bash
vendor/bin/runway make:tool MyTool
vendor/bin/runway make:prompt MyPrompt
```

Run these from the project root — Runway resolves `commands/*.php` and `.runway-config.json`
relative to the current directory. Generated classes are discovered automatically; there is no
registry to edit.
