<?php

namespace app\tools;

use app\helpers\AbstractMCPTool;

class HelloWorldTool extends AbstractMCPTool
{
    protected string $name = 'hello-world-tool';
    protected string $description = 'Tool example to return a hello world message.';
    protected ?string $title = 'Hello World Tool';

    protected array $arguments = [
        [
            'name' => 'firstName',
            'type' => 'string',
            'description' => 'First name of the user',
            'required' => true,
        ],
        [
            'name' => 'lastName',
            'type' => 'string',
            'description' => 'Last name of the user',
            'required' => false,
        ],
    ];

    protected null|array|string $outputSchema = [
        'type' => 'object',
        'properties' => [
            'message' => [
                'type' => 'string',
                'description' => 'A hello world message including the provided first and last names.',
            ],
        ],
    ];

    //private \PDO $pdo;

    public function __construct()
    {
        // $pdo = \Flight::db();
        // if (!$pdo instanceof \PDO) {
        //     throw new \Exception('PDO não está configurado corretamente.', -32603);
        // }
        // $this->pdo = $pdo;
    }

    public function execute(array $arguments): mixed
    {
        $firstName = $arguments['firstName'] ?? '';
        $lastName = $arguments['lastName'] ?? '';

        if (empty($firstName)) {
            throw new \InvalidArgumentException('O parâmetro "firstName" é obrigatório.');
        }

        return [
            'message' => 'Hello ' . $firstName . ' ' . $lastName,
        ];
    }
}
