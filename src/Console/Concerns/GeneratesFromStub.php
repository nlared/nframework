<?php

namespace Nlared\Nframework\Console\Concerns;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

trait GeneratesFromStub
{
    protected Filesystem $files;

    protected function makeFromStub(string $stubFileName, string $defaultFileName): int
    {
        $this->files = $this->files ?? new Filesystem();

        $name = (string) ($this->argument('name') ?? $defaultFileName);
        $name = trim($name);
        if ($name === '') {
            $name = $defaultFileName;
        }
        if (!str_ends_with($name, '.php')) {
            $name .= '.php';
        }

        $basePathOption = $this->option('path');
        $basePath = $basePathOption !== null && $basePathOption !== ''
            ? base_path((string) $basePathOption)
            : base_path('docs');

        $destination = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        if ($this->files->exists($destination) && !$this->option('force')) {
            $this->components->error("El archivo {$destination} ya existe. Usa --force para sobrescribir.");
            return SymfonyCommand::FAILURE;
        }

        $this->files->ensureDirectoryExists(dirname($destination));
        $stubPath = dirname(__DIR__, 3) . '/stubs/' . $stubFileName;
        $this->files->put($destination, $this->files->get($stubPath));

        $this->components->info("Template generado: {$destination}");
        return SymfonyCommand::SUCCESS;
    }
}
