<?php

declare(strict_types=1);

/**
 * Writes mcp-config.json with this installation's real path.
 *
 * Clients such as Claude Desktop cannot expand variables the way VS Code
 * expands ${workspaceFolder}, so they need an absolute path. Rather than
 * asking the user to edit a placeholder, resolve it at install time - the
 * project can then be cloned anywhere.
 *
 * Run automatically by composer's post-create-project-cmd.
 */

$root = dirname(__DIR__);
$target = $root . DIRECTORY_SEPARATOR . 'mcp-config.json';

// Never clobber an edited config by accident: post-create-project-cmd runs
// this on every install, and a user may have tuned the file by hand. Passing
// --force is the explicit way to regenerate, e.g. after moving the project.
$force = in_array('--force', $argv, true);

if (true === file_exists($target) && false === $force) {
    echo "mcp-config.json already exists. Pass --force to regenerate it.\n";

    exit(0);
}

$config = [
    'mcpServers' => [
        'flightphp-mcp-skeleton' => [
            'command' => 'php',
            'args' => [$root . DIRECTORY_SEPARATOR . 'mcp-server.php'],
            'env' => new stdClass(),
        ],
    ],
];

$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if (false === $json) {
    fwrite(STDERR, "Failed to encode mcp-config.json\n");

    exit(1);
}

file_put_contents($target, $json . "\n");

echo "Wrote mcp-config.json for {$root}\n";
