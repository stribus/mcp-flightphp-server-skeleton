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
            'type' => 'array',
            'description' => 'List of columns to include in the SQL query',
            'required' => true,
        ],
    ];

    public function getPromptText(array $context): string
    {
        $table = $context['table'] ?? '';
        $columns = $context['columns'] ?? [];

        $colStr = implode(', ', $columns);

        return "Write a SQL query using the table '{$table}' with the columns: {$colStr}.";
    }
}
