<?php

namespace Nlared\Nframework\Console;

use Illuminate\Console\Command;
use Nlared\Nframework\Console\Concerns\GeneratesFromStub;

class MakeDatabindingAjaxCommand extends Command
{
    use GeneratesFromStub;

    protected $signature = 'make:databindingajax
                            {name? : Nombre del archivo destino}
                            {--path= : Ruta base relativa al proyecto Laravel}
                            {--force : Sobrescribe el archivo si ya existe}';

    protected $description = 'Genera un archivo PHP basado en el template databindingajax';

    protected $aliases = ['make:databingajax'];

    public function handle(): int
    {
        return $this->makeFromStub('databindingajax.stub', 'databindingajax');
    }
}
