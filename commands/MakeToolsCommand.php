<?php


declare(strict_types=1);

namespace flight\commands;

use Ahc\Cli\Input\Command;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;



class MakeToolsCommand extends AbstractBaseCommand
{
    /**
     * Constructor for the MakeToolsCommand class.
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('make:tool', 'Create a tool', $config);
        $this->argument('<tool>', 'The name of the tool to create (with or without the Tool suffix)');
    }

    /**
     * Executes the function
     *
     * @return void
     */
    public function execute(string $tool)
    {
        $io = $this->app()->io();
        if (isset($this->config['app_root']) === false) {
            $io->error('app_root not set in .runway-config.json', true);
            return;
        }

        if (!preg_match('/Tool$/', $tool)) {
            $tool .= 'Tool';
        }

        $toolPath = getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'tools' . DIRECTORY_SEPARATOR . $tool . '.php';
        if (file_exists($toolPath) === true) {
            $io->error($tool . ' already exists.', true);
            return;
        }


        if (is_dir(dirname($toolPath)) === false) {
            $io->info('Creating directory ' . dirname($toolPath), true);
            mkdir(dirname($toolPath), 0755, true);
        }

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = new PhpNamespace('app\\tools');
        $namespace->addUse('app\\helpers\\AbstractMCPTool');

        $class = new ClassType($tool);
        $class->setExtends('app\\helpers\\AbstractMCPTool');

        $class->addComment('Tool for ' . $tool);

        $class->addProperty('name')
            ->setVisibility('protected')
            ->setType('string')
            ->setValue(strtolower($tool))
            ->addComment('@var string Name Unique identifier for the tool');

        $class->addProperty('description')
            ->setVisibility('protected')
            ->setType('string')
            ->setValue('Tool for ' . $tool)
            ->addComment('@var string Description Human-readable description of functionality');

        $class->addProperty('title')
            ->setType('?string')
            ->setVisibility('protected')
            ->setValue('Tool for ' . $tool)
            ->addComment('@var ?string Title Optional human-readable name of the tool for display purposes.');

        $class->addProperty('arguments')
            ->setVisibility('protected')
            ->setType('array')
            ->setValue([])
            ->addComment('@var array<string|bool> Arguments List of arguments that the tool accepts. Each argument is an associative array with keys "name", "type", "description" and "required".');

        $class->addProperty('outputSchema')
            ->setVisibility('protected')
            ->setType('null|array|string')
            ->setValue([
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'description' => 'Output of the tool',
                ],
            ])
            ->addComment('@var null|array|string Output schema Schema of the output returned by the tool. Can be a JSON schema or a string description.');

        $class->addMethod('__construct')
            ->addComment('Constructor')
            ->setVisibility('public')
            ->setBody('// Initialize any dependencies or properties here');

        $executeMethod = $class->addMethod('execute')
            ->addComment('Executes the tool with the provided arguments')
            ->setVisibility('public')
            ->setBody("// Implement the tool execution logic here \nreturn [];")
            ->setReturnType('array');

        $executeMethod->addParameter('arguments')
            ->setType('array');

        $namespace->add($class);
        $file->addNamespace($namespace);

        $this->persistClass($tool, $file);
        $io->ok($tool . ' created successfully created at ' . $toolPath, true);
    }

    /**
     * Saves the class name to a file
     *
     * @param string    $toolName  Name of the Tool
     * @param PhpFile   $file      Class Object from Nette\PhpGenerator
     *
     * @return void
     */
    protected function persistClass(string $toolName, PhpFile $file)
    {
        $printer = new \Nette\PhpGenerator\PsrPrinter();
        file_put_contents(getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'tools' . DIRECTORY_SEPARATOR . $toolName . '.php', $printer->printFile($file));
    }
}