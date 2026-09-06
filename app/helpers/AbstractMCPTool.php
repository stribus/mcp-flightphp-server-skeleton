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

        foreach (MCPResultBuilder::normalizeArguments($this->arguments) as $index => $parameter) {
            $declared = $this->arguments[$index] ?? [];

            $properties[$parameter['name']] = [
                'type' => $declared['type'] ?? 'string',
                'description' => $parameter['description'],
            ];

            if (true === $parameter['required']) {
                $required[] = $parameter['name'];
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

            foreach ($this->outputSchema as $output) {
                if (is_array($output)) {
                    $outputSchema['properties'][$output['name']] = [
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
}
