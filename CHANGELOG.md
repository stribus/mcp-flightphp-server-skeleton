# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Releases are tagged `MAJOR.MINOR.PATCH`, without a `v` prefix. Composer accepts either
form and strips the prefix when normalising, so the two resolve identically; the bare
form is used for consistency with the most recent existing tag, `1.0.1`. Packagist reads
versions from these tags, so `composer.json` carries no `version` field.

## [Unreleased]

### Added

- `app/helpers/MCPResultBuilder.php` — converts plain return values into the envelopes the
  MCP specification requires. Implementations that already return a valid envelope pass
  through untouched.
- `AbstractMCPTool::validateArguments()` — enforces required arguments before `execute()`
  runs, reporting failures as `-32602`. Tools no longer hand-roll the check.
- `app/resources/ServerInfoResource.php` — the first working resource implementation, so
  the pattern is documented by example rather than only in prose.
- `app/config/Env.php` — shared environment access for both transports. `MCP_SERVER_NAME`
  and `MCP_SERVER_VERSION` were present in `.env` but read by no code; they now populate
  `serverInfo`.
- Streamable HTTP session handling: `Mcp-Session-Id`, `DELETE /mcp`, and
  `app/core/MCPSessionStore.php`.
- Server-Sent Events via `app/core/MCPEventStream.php`, behind `MCP_HTTP_SSE`
  (default `false`).
- `ping`, and silent acceptance of every `notifications/*` message.
- `.mcp.json` for Claude Code, using `${CLAUDE_PROJECT_DIR:-.}` so the committed file resolves to
  the right path on every machine. The fallback is deliberate: `CLAUDE_PROJECT_DIR` was not set on
  Claude Code 2.1.263, and without a default Claude Code passes the unexpanded text through as a
  literal path.
- `.vscode/mcp.json`, `mcp-config.example.json` and `scripts/generate-mcp-config.php`.
- `CLIENTS-MCP-SETUP.md` and its Portuguese translation `CLIENTS-MCP-SETUP.pt-BR.md`, covering
  Claude Code, VS Code and Claude Desktop in one place, with a comparison of the three formats and
  a troubleshooting section. These replace `VS-CODE-SETUP.md`.
- `.gitattributes`, PHPStan (level 5) and PHP-CS-Fixer.

### Changed

- **Breaking.** The HTTP endpoint moved from `POST /` to `POST /mcp`. `GET` and `DELETE`
  are served on the same path.
- **Breaking.** `tools/list`, `prompts/list` and `resources/list` return arrays wrapped in
  `tools`, `prompts` and `resources`. They previously returned objects keyed by name,
  because `array_map` preserves the string keys the registries use.
- **Breaking.** `tools/call` returns `content[]` plus `isError`, and `structuredContent`
  when the tool declares an `outputSchema`. It previously returned the raw payload.
- **Breaking.** `prompts/get` returns `{description, messages[]}` instead of a bare
  string, and reads `params.arguments`. `params.context` still works as a fallback.
- **Breaking.** `resources/read` returns `{contents[]}` instead of raw content.
- `resources/list` entries now carry the required `uri`, plus `mimeType`.
- `initialize` returns `serverInfo`, which the specification requires, and `capabilities`
  entries are objects rather than empty arrays that serialised as `[]`.
- `Origin` is validated against an allowlist instead of `Access-Control-Allow-Origin: *`.
- Unknown tool or prompt is `-32602`; a missing resource is `-32002`. Error messages match
  their codes instead of always reading "Internal error".
- A failure inside a tool is reported as a result with `isError: true`, not as a JSON-RPC
  error — the distinction the specification draws between protocol and execution errors.
- The `make:tool` template emits an object-typed `outputSchema` and a kebab-case name.
  The `make:prompt` template emits arguments in the shape its own docblock described.

### Fixed

- `scripts/generate-mcp-config.php` refused to overwrite an existing `mcp-config.json`, while the
  documentation said to run it to regenerate. The guard is still the default, so
  `post-create-project-cmd` cannot clobber a hand-edited file, but `--force` now regenerates and
  the docs say so.
- `make:prompt` derived the name with `strtolower()`, turning `MyCustomPrompt` into `mycustom`.
  It now produces snake_case (`my_custom`), matching the `generate_sql` example that ships with
  the skeleton. Both generators also keep acronyms intact: `GenerateSQLTool` becomes
  `generate-sql-tool`, not `generate-s-q-l-tool`.
- **The `make:tool` and `make:prompt` generators were broken in every fresh install.**
  `composer.json` allows `flightphp/runway` `^0.2 || ^1.1`, and since `composer.lock` is not
  versioned, `composer create-project` resolved to 1.x while development happened on 0.2.4. Two
  incompatibilities followed. Runway 1.x does `$config = require 'app/config/config.php'` and then
  writes into it as an array; that file returned nothing, so the generators died with "Cannot use
  a scalar value as an array". And 1.x passes the application config to commands, moving
  `app_root` under a `runway` key, where the commands were not looking. `config.php` now returns
  an array, and both commands resolve `app_root` from either shape, so the generators work on
  both lines.
- The skeleton could not run on a case-sensitive filesystem: `MCPService` imported
  `app\Helpers\ClassAutoLoader` with a capital `H` while the class declares
  `app\helpers`. Fatal on Linux, silent on Windows.
- `notifications/initialized` was answered with `-32601`. JSON-RPC notifications carry no
  `id` and must not be answered at all; both transports now stay silent.
- `AbstractMCPPrompt::getArguments()` returned a hardcoded empty array, so every prompt
  reported no arguments regardless of what it declared.
- `prompts/list` called `getPromptText([])` on every prompt to build a non-standard
  `promptText` field, running prompt logic with empty context on every listing.
- A `TypeError` raised inside a tool escaped to the stdio exception handler and called
  `exit(1)`, killing the server for every later request.
- `tools/call` without a `name` parameter produced a PHP warning instead of `-32602`.
- `getInputSchema()` emitted `"properties": []` for a tool with no arguments, which is not
  valid JSON Schema.
- `getOutputSchema()` produced `type: "text"`, which is not a JSON Schema type.
- `AbstractMCPResource::getTitle()` threw when `$title` was unset; it now falls back to
  the name.
- `set_error_handler()` was given a callback whose signature PHP does not accept.
- `.env` and `mcp-server.log` were tracked in git, so every generated project inherited
  them.
- `mcp-config.json` carried a hardcoded path from the author's machine, pointing at a
  directory that does not exist.
- `README.md` documented `POST /tools/list` and similar routes that never existed.
- `VS-CODE-SETUP.md` used the Claude Desktop config format while presenting it as the VS
  Code one, and referenced a `MCP-README.md` that is not in the repository. It has been
  replaced by `CLIENTS-MCP-SETUP.md`.

## [1.0.1]

- Corrected the package name to `stribus/mcp-flightphp-server-skeleton`.
- Fixed the directory names created by `post-create-project-cmd`.
- Added installation and usage instructions to `README.md`.

## [1.0.0]

- Initial release: MCP server over stdio and HTTP, automatic discovery of tools, prompts
  and resources, and the `make:tool` / `make:prompt` generators.
