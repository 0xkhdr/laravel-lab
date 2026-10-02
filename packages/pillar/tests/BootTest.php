<?php

declare(strict_types=1);

namespace Raid\Pillar\Tests;

use Orchestra\Testbench\TestCase;
use Raid\Pillar\PillarServiceProvider;

final class BootTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PillarServiceProvider::class];
    }

    public function test_isolated_package_boots(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(PillarServiceProvider::class));
    }
}
