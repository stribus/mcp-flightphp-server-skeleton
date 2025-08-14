<?php

namespace app\helpers;

interface MCPToolInterface
{
    public function getName(): string;

    public function getTitle(): string;

    public function getDescription(): string;

    public function getArguments(): array;

    public function execute(array $arguments): mixed;

    public function getInputSchema(): array;

    public function getOutputSchema(): ?array;
}
