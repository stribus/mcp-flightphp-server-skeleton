# Upgrading from 1.x to 2.0

Version 2.0 makes the skeleton conform to the MCP specification revision `2025-06-18`.
Version 1.x did not: no MCP client could complete a handshake with it, and every list and
call response deviated from the protocol.

Because the wire format changed, this is a breaking release. What follows is everything
you have to touch.

## What does not change

**Your tools and prompts keep working as they are.** The `execute()` and `getPromptText()`
contracts are unchanged. A tool still returns a plain array, a prompt still returns a
plain string, and the framework now wraps those into the envelopes the specification
requires.

This was deliberate: the conversion lives in `app/helpers/MCPResultBuilder.php` rather
than in your code, so an upgrade does not mean rewriting every tool you have written.

If you want more control, a tool may now return a complete envelope itself — a `content`
array of typed blocks, including images or resource links — and it will be passed through
untouched.

## 1. The HTTP endpoint moved

`POST /` is gone. The MCP endpoint is now `/mcp`, serving `POST`, `GET` and `DELETE` on a
single path, as the Streamable HTTP transport requires.

```diff
- curl -X POST http://localhost:8000/ ...
+ curl -X POST http://localhost:8000/mcp ...
```

Update any client configuration, reverse-proxy rule or script that pointed at the root.
`GET /` and `GET /health` are unchanged.

Two related requirements now apply to HTTP clients:

- After initialization, send `MCP-Protocol-Version: 2025-06-18`. An unsupported value is
  answered with `400`.
- A successful `initialize` returns an `Mcp-Session-Id` header. Send it back on every
  subsequent request; requests carrying an unknown session get `404`.

## 2. Origin validation

The server previously sent `Access-Control-Allow-Origin: *` unconditionally. It now
validates the `Origin` header against an allowlist, which the specification requires to
prevent DNS rebinding attacks.

Requests with no `Origin` header — every native MCP client — are unaffected. Browser
clients from another origin need it configured:

```dotenv
MCP_ALLOWED_ORIGINS=http://localhost,http://127.0.0.1,https://your-app.example.com
```

Any port on a listed host is accepted.

## 3. Response shapes

If you wrote anything that consumes this server's responses directly, it needs updating.

| Method | 1.x | 2.0 |
|---|---|---|
| `tools/list` | `result` was an object keyed by tool name | `result.tools` is an array |
| `tools/call` | `result` was the raw return value | `result.content[]`, `result.isError`, plus `result.structuredContent` when an `outputSchema` is declared |
| `prompts/list` | object keyed by prompt name, with a non-standard `promptText` | `result.prompts` is an array; `promptText` is gone |
| `prompts/get` | a bare string | `result.description` and `result.messages[]` |
| `resources/list` | object keyed by scheme, no `uri` | `result.resources` is an array, each entry carrying `uri` and `mimeType` |
| `resources/read` | the raw content | `result.contents[]` |

`prompts/get` now reads its arguments from `params.arguments`, which is what the
specification names. `params.context` is still accepted, so 1.x callers keep working.

## 4. Error codes

- Unknown tool or prompt: `-32601` becomes `-32602`. The method exists; the parameter is
  what is wrong.
- Missing resource: `-32002`.
- A missing required argument is now rejected with `-32602` before `execute()` runs. If
  your tool validated its own arguments and threw, you can delete that code.
- **A failure inside a tool is no longer a JSON-RPC error.** It comes back as a normal
  result with `isError: true` and the message in `content`. Client code that only checked
  for an `error` member will now see these as successes; check `isError`.

## 5. Client configuration

`mcp-config.json` is no longer tracked, because its contents are specific to one machine.

- **VS Code** reads `.vscode/mcp.json`, which is versioned and needs no editing — it uses
  `${workspaceFolder}`. Note the top-level key is `servers`, not `mcpServers`; the old
  file used the Claude Desktop format.
- **Other clients** get an `mcp-config.json` generated at install time with the correct
  absolute path. Regenerate it at any time:

  ```bash
  php scripts/generate-mcp-config.php
  ```

## 6. Environment

Copy the new keys from `.env.example`. `MCP_SERVER_NAME` and `MCP_SERVER_VERSION` existed
in 1.x but were read by nothing; they now populate the `serverInfo` block that clients
require during the handshake.

New optional keys: `MCP_SERVER_INSTRUCTIONS`, `MCP_ALLOWED_ORIGINS`, `MCP_HTTP_SSE`,
`MCP_HTTP_SSE_MAX_SECONDS`.

## 7. Server-Sent Events

`GET /mcp` opens an SSE stream, but it is **disabled by default** and answers `405`, which
the specification explicitly permits.

The reason is measured, not theoretical. PHP's built-in server (`composer start`) handles
one request at a time, and on Windows it cannot fork at all — setting
`PHP_CLI_SERVER_WORKERS` makes PHP print `forking is not supported on this platform`. With
a stream open, other requests time out. Since a real client opens the GET stream as soon
as it connects, enabling SSE would make the documented quick start unusable.

Turn it on only behind Apache or nginx with php-fpm:

```dotenv
MCP_HTTP_SSE=true
```

Resuming a broken stream via `Last-Event-ID` is not implemented; the specification marks
it optional.

## 8. Staying on 1.x

The old line remains installable:

```bash
composer create-project stribus/mcp-flightphp-server-skeleton my-project "^1.0"
```

Be aware that 1.x does not interoperate with MCP clients, and that it cannot run on a
case-sensitive filesystem — `MCPService` imported `app\Helpers\ClassAutoLoader` with a
capital `H` against a class declaring `app\helpers`, which is fatal on Linux.
