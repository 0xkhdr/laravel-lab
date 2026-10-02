<?php

declare(strict_types=1);

namespace Raid\Foundry;

use Illuminate\Support\ServiceProvider;
use Raid\Foundry\Commands\FoundryCommand;

class FoundryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(array_map(fn (string $operation) => new FoundryCommand($operation), ['install', 'add', 'make-module', 'doctor', 'rollback']));
        }
    }
}
