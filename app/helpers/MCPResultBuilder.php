<?php

declare(strict_types=1);

namespace app\helpers;

/**
 * Turns plain return values into the envelopes the MCP specification expects.
 *
 * Tools and prompts stay free to return whatever is natural - an associative
 * array, a string - and this class wraps it. An implementation that already
 * produces a valid envelope is passed through untouched, so advanced cases
 * (images, resource links, multi-message prompts) remain possible.
 *
 * Keeping the conversion here is what lets existing tools become compliant
 * without a single edit.
 */
class MCPResultBuilder
{
    /**
     * Builds a CallToolResult from whatever a tool returned.
     *
     * @param mixed           $raw          Return value of MCPToolInterface::execute()
     * @param null|array<mixed> $outputSchema Declared output schema, when the tool has one
     *
     * @return array<string,mixed>
     */
    public static function toolResult($raw, ?array $outputSchema = null): array
    {
        if (true === self::isContentEnvelope($raw)) {
            // The tool built its own blocks; only guarantee isError is present.
            $raw['isError'] = $raw['isError'] ?? false;

            return $raw;
        }

        $result = [
            'content' => [self::textBlock(self::stringify($raw))],
            'isError' => false,
        ];

        // When a tool declares an output schema the spec says the server MUST
        // return structured results conforming to it, alongside the text block.
        if (null !== $outputSchema && is_array($raw)) {
            $result['structuredContent'] = $raw;
        }

        return $result;
    }

    /**
     * Builds a CallToolResult describing a failed execution.
     *
     * Tool failures are reported in the result with isError, not as JSON-RPC
     * errors - the spec reserves those for protocol problems such as an
     * unknown tool or invalid arguments.
     *
     * @return array<string,mixed>
     */
    public static function errorResult(string $message): array
    {
        return [
            'content' => [self::textBlock($message)],
            'isError' => true,
        ];
    }

    /**
     * Builds a GetPromptResult from whatever a prompt returned.
     *
     * @param mixed $raw Return value of MCPPromptInterface::getPromptText()
     *
     * @return array<string,mixed>
     */
    public static function promptResult($raw, string $description = ''): array
    {
        if (true === self::isMessageList($raw)) {
            return [
                'description' => $description,
                'messages' => $raw['messages'] ?? $raw,
            ];
        }

        return [
            'description' => $description,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => self::textBlock(self::stringify($raw)),
                ],
            ],
        ];
    }

    /**
     * Builds a ReadResourceResult from whatever a resource returned.
     *
     * @param mixed $raw Return value of MCPResourceInterface::getContent()
     *
     * @return array<string,mixed>
     */
    public static function resourceContents($raw, string $uri, string $mimeType = 'text/plain'): array
    {
        // Already a full result.
        if (is_array($raw) && isset($raw['contents']) && is_array($raw['contents'])) {
            return $raw;
        }

        // A single contents entry, identified by carrying its own uri.
        if (is_array($raw) && isset($raw['uri'])) {
            return ['contents' => [$raw]];
        }

        $entry = [
            'uri' => $uri,
            'mimeType' => is_array($raw) ? 'application/json' : $mimeType,
        ];

        // Contents hold either text or base64 "blob"; anything structured is
        // rendered as JSON text.
        $entry['text'] = self::stringify($raw);

        return ['contents' => [$entry]];
    }

    /**
     * Normalises an $arguments declaration into the shape prompts/list expects.
     *
     * Two conventions exist in this codebase: tools use a list of maps each
     * carrying a "name" key, prompts use a map keyed by argument name. Both are
     * accepted so neither has to be rewritten.
     *
     * @param array<mixed> $arguments
     *
     * @return array<int,array<string,mixed>>
     */
    public static function normalizeArguments(array $arguments): array
    {
        $normalized = [];

        foreach ($arguments as $key => $argument) {
            if (false === is_array($argument)) {
                continue;
            }

            // List-of-maps carries its own name; map-keyed-by-name does not.
            $name = $argument['name'] ?? (is_string($key) ? $key : null);

            if (null === $name) {
                continue;
            }

            $normalized[] = [
                'name' => (string) $name,
                'description' => (string) ($argument['description'] ?? ''),
                'required' => (bool) ($argument['required'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * True when the value already looks like a CallToolResult.
     *
     * @param mixed $raw
     */
    private static function isContentEnvelope($raw): bool
    {
        if (false === is_array($raw) || false === isset($raw['content']) || false === is_array($raw['content'])) {
            return false;
        }

        // An empty content list is valid but indistinguishable from a plain
        // payload that happens to use the key, so require at least one block.
        if ([] === $raw['content']) {
            return false;
        }

        foreach ($raw['content'] as $block) {
            if (false === is_array($block) || false === isset($block['type'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * True when the value already looks like a GetPromptResult message list.
     *
     * @param mixed $raw
     */
    private static function isMessageList($raw): bool
    {
        if (false === is_array($raw)) {
            return false;
        }

        $messages = $raw['messages'] ?? $raw;

        if (false === is_array($messages) || [] === $messages) {
            return false;
        }

        foreach ($messages as $message) {
            if (false === is_array($message) || false === isset($message['role'], $message['content'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string,string>
     */
    private static function textBlock(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    /**
     * Renders any return value as the text that accompanies a result.
     *
     * @param mixed $raw
     */
    private static function stringify($raw): string
    {
        if (is_string($raw)) {
            return $raw;
        }

        if (null === $raw) {
            return '';
        }

        if (is_scalar($raw)) {
            return var_export($raw, true);
        }

        $json = json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false === $json ? '' : $json;
    }
}
