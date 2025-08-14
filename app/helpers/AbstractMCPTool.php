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
        $inputSchema = [
            'type' => 'object',
            'properties' => [],
        ];
        $required = [];
        foreach ($this->arguments as $parameter) {
            $inputSchema['properties'][$parameter['name']] = [
                'type' => $parameter['type'] ?? 'string',
                'description' => $parameter['description'] ?? '',
            ];
            if (!empty($parameter['required']) && true === $parameter['required']) {
                $required[] = $parameter['name'];
            }
        }
        if (!empty($required)) {
            $inputSchema['required'] = $required;
        }

        return $inputSchema;
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
            return ['type' => 'text', 'description' => $this->outputSchema];
        }

        return null;
    }
}
