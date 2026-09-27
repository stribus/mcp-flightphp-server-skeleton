<?php

namespace app\prompts;

use app\helpers\AbstractMCPPrompt;

/**
 * Class example prompt for generating SQL queries.
 */
class GenerateSQLPrompt extends AbstractMCPPrompt
{
    protected string $name = 'generate_sql';
    protected string $description = 'Generate SQL query based on table columns';
    protected ?string $title = 'Generate SQL Query';
    protected array $arguments = [
        'table' => [
            'type' => 'string',
            'description' => 'Name of the table for which the SQL query will be generated',
            'required' => true,
        ],
        'columns' => [
            'type' => 'string',
            'description' => 'Comma-separated list of columns to include in the SQL query',
            'required' => true,
        ],
    ];

    public function getPromptText(array $context): string
    {
        $table = $context['table'] ?? '';
        $columns = $context['columns'] ?? '';

        // The spec sends every prompt argument as a string, so a list arrives
        // as "id, name". An array is still accepted for callers that send one.
        if (is_string($columns)) {
            $columns = array_filter(
                array_map('trim', explode(',', $columns)),
                static fn (string $column): bool => '' !== $column
            );
        }

        $colStr = implode(', ', $columns);

        return "Write a SQL query using the table '{$table}' with the columns: {$colStr}.";
    }
}
