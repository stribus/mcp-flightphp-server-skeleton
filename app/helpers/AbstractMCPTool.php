<?php

namespace app\helpers;

abstract class AbstractMCPTool implements MCPToolInterface
{
    protected string $name;
    protected string $description;
    protected ?string $title = null;
    // Input structure
    protected array $arguments = [];
    // Output structure
    protected null|array|string $outputSchema = null;

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title ?? $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getInputSchema(): array
    {
        $properties = [];
        $required = [];

        // Iterate the declaration itself rather than normalizeArguments()'s
        // output: that list is reindexed from 0, so looking the "type" back up
        // by index only worked for list-shaped $arguments. With the map-keyed
        // convention every argument was silently announced as a string.
        foreach ($this->arguments as $key => $parameter) {
            $name = self::argumentName($key, $parameter);

            if (null === $name) {
                continue;
            }

            $properties[$name] = [
                'type' => $parameter['type'] ?? 'string',
                'description' => (string) ($parameter['description'] ?? ''),
            ];

            if (true === (bool) ($parameter['required'] ?? false)) {
                $required[] = $name;
            }
        }

        $inputSchema = [
            'type' => 'object',
            // An empty PHP array encodes as [], but JSON Schema requires
            // "properties" to be an object even when the tool takes no input.
            'properties' => [] === $properties ? new \stdClass() : $properties,
        ];

        if ([] !== $required) {
            $inputSchema['required'] = $required;
        }

        return $inputSchema;
    }

    /**
     * Rejects a call that omits a required argument.
     *
     * Doing this in the base class means no tool has to hand-roll the check,
     * and the failure is reported as -32602 (invalid params) rather than as a
     * generic internal error.
     *
     * @param array<string,mixed> $arguments
     *
     * @throws \InvalidArgumentException
     */
    public function validateArguments(array $arguments): void
    {
        $missing = [];

        foreach (MCPResultBuilder::normalizeArguments($this->arguments) as $parameter) {
            if (false === $parameter['required']) {
                continue;
            }

            $value = $arguments[$parameter['name']] ?? null;

            if (null === $value || '' === $value) {
                $missing[] = $parameter['name'];
            }
        }

        if ([] !== $missing) {
            throw new \InvalidArgumentException(
                'Missing required argument(s): ' . implode(', ', $missing),
                -32602
            );
        }
    }

    public function getOutputSchema(): ?array
    {
        if (is_array($this->outputSchema) && !empty($this->outputSchema)) {
            if (isset($this->outputSchema['type'])) {
                // if the outputSchema is already a valid schema, return it as is
                return $this->outputSchema;
            }
            $outputSchema = [
                'type' => 'object',
                'properties' => [],
            ];

            foreach ($this->outputSchema as $key => $output) {
                if (is_array($output)) {
                    // Same two conventions as $arguments: a list of maps with
                    // "name", or a map keyed by name. Reading only $output['name']
                    // broke the second one with an undefined-key warning.
                    $name = self::argumentName($key, $output);

                    if (null === $name) {
                        continue;
                    }

                    $outputSchema['properties'][$name] = [
                        'type' => $output['type'] ?? 'string',
                        'description' => $output['description'] ?? '',
                    ];
                } elseif (is_string($output)) {
                    $outputSchema['properties'][$output] = [
                        'type' => 'string',
                        'description' => $output,
                    ];
                }
            }

            return $outputSchema;
        }
        if (is_string($this->outputSchema)) {
            // "text" is not a JSON Schema type; the valid set is object,
            // array, string, number, integer, boolean and null.
            return ['type' => 'string', 'description' => $this->outputSchema];
        }

        return null;
    }

    /**
     * Resolves a declared field's name under either supported convention.
     *
     * A list of maps carries its own "name"; a map keyed by name does not.
     * Mirrors MCPResultBuilder::normalizeArguments(), but keeps the caller on
     * the original entry so fields such as "type" are not lost.
     *
     * @param int|string $key
     * @param mixed      $declaration
     */
    private static function argumentName($key, $declaration): ?string
    {
        if (false === is_array($declaration)) {
            return null;
        }

        $name = $declaration['name'] ?? (is_string($key) ? $key : null);

        return null === $name ? null : (string) $name;
    }
}
