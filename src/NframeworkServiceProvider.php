<?php

namespace Nlared\Nframework;

use Illuminate\Support\ServiceProvider;
use Nlared\Nframework\Console\MakeDatabindingAjaxCommand;
use Nlared\Nframework\Console\MakeDatatableAjaxCommand;

class NframeworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeDatabindingAjaxCommand::class,
                MakeDatatableAjaxCommand::class,
            ]);
        }
    }
}
