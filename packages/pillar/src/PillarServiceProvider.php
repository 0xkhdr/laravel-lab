<?php

declare(strict_types=1);

namespace Raid\Pillar;

use Illuminate\Support\ServiceProvider;

class PillarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/repository-cache.php', 'repository-cache');
        $this->mergeConfigFrom(__DIR__.'/../config/app-modules.php', 'app-modules');
    }
}
