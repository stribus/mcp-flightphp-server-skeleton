<?php

declare(strict_types=1);

namespace flight\commands;

use Ahc\Cli\Input\Command;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

class MakePromptCommand extends AbstractBaseCommand
{
    /**
     * Constructor for the MakePromptCommand class.
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('make:prompt', 'Create a prompt', $config);
        $this->argument('<prompt>', 'The name of the prompt to create (with or without the Prompt suffix)');
    }

    /**
     * Executes the function
     *
     * @return void
     */
    public function execute(string $prompt)
    {
        $io = $this->app()->io();
        if (isset($this->config['app_root']) === false) {
            $io->error('app_root not set in .runway-config.json', true);
            return;
        }

        if (!preg_match('/Prompt$/', $prompt)) {
            $prompt .= 'Prompt';
        }

        $promptPath = getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'prompts' . DIRECTORY_SEPARATOR . $prompt . '.php';
        if (file_exists($promptPath) === true) {
            $io->error($prompt . ' already exists.', true);
            return;
        }

        if (is_dir(dirname($promptPath)) === false) {
            $io->info('Creating directory ' . dirname($promptPath), true);
            mkdir(dirname($promptPath), 0755, true);
        }

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = new PhpNamespace('app\\prompts');
        $namespace->addUse('app\\helpers\\AbstractMCPPrompt');

        $class = new ClassType($prompt);
        $class->setExtends('app\\helpers\\AbstractMCPPrompt');

        $class->addComment('Prompt for ' . $prompt);

        $class->addProperty('name')
            ->setVisibility('protected')
            ->setType('string')
            ->setValue(strtolower(preg_replace('/Prompt$/', '', $prompt)))
            ->addComment('@var string Name Unique identifier for the prompt');

        $class->addProperty('description')
            ->setVisibility('protected')
            ->setType('string')
            ->setValue('Prompt for ' . $prompt)
            ->addComment('@var string Description Human-readable description of the prompt functionality');

        $class->addProperty('title')
            ->setType('?string')
            ->setVisibility('protected')
            ->setValue('Prompt for ' . $prompt)
            ->addComment('@var ?string Title Optional human-readable name of the prompt for display purposes.');

        $class->addProperty('arguments')
            ->setVisibility('protected')
            ->setType('array')
            ->setValue([
                'input' => [
                    'type' => 'string',
                    'description' => 'Input text for the prompt',
                    'required' => true,
                ],
            ])
            ->addComment('@var array Arguments List of arguments that the prompt accepts. Each argument is an associative array with keys "name", "type", "description" and "required".');

        $getPromptTextMethod = $class->addMethod('getPromptText')
            ->addComment('Returns the prompt text based on the provided context')
            ->setVisibility('public')
            ->setBody("// Implement the prompt text generation logic here\n// Use the context array to customize the prompt\n\$input = \$context['input'] ?? '';\n\nreturn \"Your prompt text with input: {\$input}\";")
            ->setReturnType('string');

        $getPromptTextMethod->addParameter('context')
            ->setType('array');

        $namespace->add($class);
        $file->addNamespace($namespace);

        $this->persistClass($prompt, $file);
        $io->ok($prompt . ' created successfully at ' . $promptPath, true);
    }

    /**
     * Saves the class name to a file
     *
     * @param string    $promptName  Name of the Prompt
     * @param PhpFile   $file      Class Object from Nette\PhpGenerator
     *
     * @return void
     */
    protected function persistClass(string $promptName, PhpFile $file)
    {
        $printer = new \Nette\PhpGenerator\PsrPrinter();
        file_put_contents(getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'prompts' . DIRECTORY_SEPARATOR . $promptName . '.php', $printer->printFile($file));
    }
}
