<?php

declare(strict_types=1);

namespace Raid\Foundry\Tests;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Raid\Foundry\Installers\CatalogInstaller;
use RuntimeException;
use Symfony\Component\Process\Process;

final class InstallerTest extends TestCase
{
    private string $root;

    private CatalogInstaller $installer;

    public function test_process_termination_before_checkpoint_resumes_without_finally(): void
    {
        $code = 'require '.var_export(__DIR__.'/../vendor/autoload.php', true).';'
            .'$installer = new \\Raid\\Foundry\\Installers\\CatalogInstaller('.var_export($this->root, true).');'
            .'$installer->apply($installer->plan(), function ($count) { if ($count === 3) { exit(42); } });';
        $process = new Process([PHP_BINARY, '-r', $code]);
        $this->assertSame(42, $process->run());
        $journal = json_decode(file_get_contents($this->root.'/.foundry/installations.json'), true);
        $this->assertSame('pending', $journal['installations']['catalog']['status']);
        $this->installer->apply($this->installer->plan());
        $journal = json_decode(file_get_contents($this->root.'/.foundry/installations.json'), true);
        $this->assertSame('completed', $journal['installations']['catalog']['status']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/foundry-'.bin2hex(random_bytes(8));
        foreach (['bootstrap', 'database/migrations', 'vendor/composer', 'app/Models'] as $directory) {
            mkdir($this->root.'/'.$directory, 0755, true);
        }
        file_put_contents($this->root.'/composer.json', json_encode(['name' => 'example/consumer', 'autoload' => ['psr-4' => ['App\\' => 'app/']]], JSON_PRETTY_PRINT)."\n");
        file_put_contents($this->root.'/bootstrap/providers.php', "<?php\n\nuse App\\Providers\\AppServiceProvider;\n\nreturn [\n    AppServiceProvider::class,\n];\n");
        file_put_contents($this->root.'/database/migrations/0001_users.php', "<?php\n// Consumer prerequisite schema:\n\$table->uuid('id')->primary();\n\$table->boolean('is_catalog_admin');\n// personal_access_tokens with uuidMorphs\n");
        file_put_contents($this->root.'/app/Models/User.php', '<?php namespace App\\Models; class User {}');
        $packages = [];
        foreach (['laravel/framework', 'raid/pillar', 'raid/catalog', 'internachi/modular', 'spatie/laravel-translatable', 'laravel/sanctum'] as $name) {
            $packages[] = ['name' => $name, 'version' => InstalledVersions::getPrettyVersion($name), 'install-path' => InstalledVersions::getInstallPath($name)];
        }
        file_put_contents($this->root.'/vendor/composer/installed.json', json_encode(['packages' => $packages]));
        $this->installer = new CatalogInstaller($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (File::allFiles($this->root, true) as $file) {
            $snapshot[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }
        ksort($snapshot);

        return $snapshot;
    }

    public function test_preview_is_write_free_apply_rerun_and_migrations_are_separate(): void
    {
        $before = $this->snapshot();
        $plan = $this->installer->plan();
        $this->assertSame([], $plan['conflicts']);
        $this->assertSame($before, $this->snapshot());
        $this->assertCount(8, $plan['migrations']);
        $this->assertGreaterThan(30, count($plan['operations']));
        $this->installer->apply($plan);
        $this->installer->verify($plan);
        $journal = json_decode(file_get_contents($this->root.'/.foundry/installations.json'), true);
        $this->assertSame('completed', $journal['installations']['catalog']['status']);
        $this->assertTrue(array_all($journal['installations']['catalog']['operations'], fn (array $entry): bool => $entry['status'] === 'completed'));
        $after = $this->snapshot();
        $again = $this->installer->plan();
        $this->installer->apply($again);
        $this->assertSame($after, $this->snapshot());
        $this->assertFalse(file_exists($this->root.'/database/database.sqlite'));
    }

    public function test_customized_files_are_preserved_before_any_writes(): void
    {
        $this->installer->apply($this->installer->plan());
        $path = $this->root.'/app-modules/catalog/src/Policies/ApplicationSku.php';
        file_put_contents($path, file_get_contents($path)."\n// custom application policy\n");
        $before = $this->snapshot();
        $plan = $this->installer->plan();
        $this->assertNotEmpty($plan['conflicts']);
        try {
            $this->installer->apply($plan);
            $this->fail('Conflict was overwritten');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Preflight', $error->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_missing_dependency_and_anchor_reject_without_writes(): void
    {
        file_put_contents($this->root.'/vendor/composer/installed.json', '{"packages":[]}');
        file_put_contents($this->root.'/bootstrap/providers.php', '<?php return unknown_providers();');
        $before = $this->snapshot();
        $plan = $this->installer->plan();
        $this->assertGreaterThan(5, count($plan['conflicts']));
        $this->assertStringContainsString('Manual patch', implode('\n', $plan['conflicts']));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_custom_module_name_and_configured_directory(): void
    {
        $installer = new CatalogInstaller($this->root, 'capabilities');
        $plan = $installer->plan('ProductHub');
        $this->assertSame([], $plan['conflicts']);
        $this->assertSame('capabilities/product-hub', $plan['module_path']);
        $installer->apply($plan);
        $this->assertFileExists($this->root.'/capabilities/product-hub/src/Providers/ProductHubServiceProvider.php');
        $this->assertStringContainsString('Modules\\ProductHub', file_get_contents($this->root.'/capabilities/product-hub/src/Policies/ApplicationSku.php'));
    }

    public function test_invalid_paths_and_symlinks_are_rejected(): void
    {
        $before = $this->snapshot();
        try {
            $this->installer->plan('../outside');
            $this->fail('Traversal accepted');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Invalid', $error->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        symlink(sys_get_temp_dir(), $this->root.'/app-modules');
        $this->expectException(RuntimeException::class);
        $this->installer->plan();
    }

    public function test_duplicate_migration_identity_is_a_preflight_conflict(): void
    {
        file_put_contents($this->root.'/database/migrations/2099_01_01_000001_create_products_table.php', '<?php // Already owned');
        $plan = $this->installer->plan();
        $this->assertStringContainsString('Duplicate migration identity products', implode('\n', $plan['conflicts']));
    }

    public function test_stale_plan_cannot_overwrite_a_later_customization(): void
    {
        $plan = $this->installer->plan();
        file_put_contents($this->root.'/composer.json', '{"custom":true}');
        $before = $this->snapshot();
        try {
            $this->installer->apply($plan);
            $this->fail('Stale plan accepted');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Stale plan', $error->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_interruption_after_atomic_write_resumes_and_records_truthful_ownership(): void
    {
        $plan = $this->installer->plan();
        try {
            $this->installer->apply($plan, function (int $completed): void {
                if ($completed === 3) {
                    throw new RuntimeException('Injected interruption');
                }
            });
            $this->fail('No interruption');
        } catch (RuntimeException $error) {
            $this->assertSame('Injected interruption', $error->getMessage());
        }
        $journal = json_decode(file_get_contents($this->root.'/.foundry/installations.json'), true);
        $statuses = array_count_values(array_column($journal['installations']['catalog']['operations'], 'status'));
        $this->assertSame(2, $statuses['completed']);
        $this->assertGreaterThan(0, $statuses['pending']);
        $retry = $this->installer->plan();
        $this->assertSame([], $retry['conflicts']);
        $this->installer->apply($retry);
        $this->installer->verify($retry);
        $this->assertCount(8, File::files($this->root.'/app-modules/catalog/database/migrations'));
        $after = $this->snapshot();
        $this->installer->apply($this->installer->plan());
        $this->assertSame($after, $this->snapshot());
    }

    public function test_recovery_restores_original_configuration_and_preserves_customizations(): void
    {
        $before = $this->snapshot();
        $this->installer->apply($this->installer->plan());
        $this->installer->rollback('Catalog');
        $after = array_filter($this->snapshot(), fn (string $key): bool => ! str_starts_with($key, '.foundry/'), ARRAY_FILTER_USE_KEY);
        $this->assertSame($before, $after);
        $this->installer->rollback('Catalog');
        $after = array_filter($this->snapshot(), fn (string $key): bool => ! str_starts_with($key, '.foundry/'), ARRAY_FILTER_USE_KEY);
        $this->assertSame($before, $after);
    }

    public function test_recipe_upgrade_preserves_custom_policy_and_resource(): void
    {
        $this->installer->apply($this->installer->plan());
        foreach (['Policies/ApplicationSku.php', 'Http/Resources/ProductResource.php'] as $file) {
            $path = $this->root.'/app-modules/catalog/src/'.$file;
            file_put_contents($path, file_get_contents($path)."\n// application customization\n");
        }
        $templates = $this->root.'/upgraded-blueprint';
        File::copyDirectory(__DIR__.'/../resources/catalog', $templates);
        $metadata = json_decode(file_get_contents($templates.'/blueprint.json'), true);
        $metadata['recipe_version'] = '1.2.0';
        $metadata['blueprint_version'] = '1.2.0';
        file_put_contents($templates.'/blueprint.json', json_encode($metadata));
        $before = $this->snapshot();
        $upgraded = new CatalogInstaller($this->root, templates: $templates);
        $plan = $upgraded->plan();
        $this->assertCount(2, $plan['conflicts']);
        $this->assertSame('1.2.0', $plan['recipe_version']);
        $this->assertSame($before, $this->snapshot());
        try {
            $upgraded->rollback('Catalog');
            $this->fail('Customized files removed');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Recovery conflict', $error->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }
}
