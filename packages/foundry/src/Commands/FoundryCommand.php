<?php

declare(strict_types=1);

namespace Raid\Foundry\Commands;

use Illuminate\Console\Command;
use Raid\Foundry\Installers\CatalogInstaller;
use Throwable;

class FoundryCommand extends Command
{
    public function __construct(private readonly string $operation)
    {
        $this->signature = match ($operation) {
            'install' => 'foundry:install {--module=Catalog} {--dry-run}',
            'add' => 'foundry:add {capability} {--module=Catalog} {--dry-run}',
            'make-module' => 'foundry:make-module {name} {--blueprint=catalog.standard} {--dry-run}',
            'doctor' => 'foundry:doctor {--module=Catalog}',
            'rollback' => 'foundry:rollback {--module=Catalog}',
        };
        $this->description = 'Inspect, configure or verify the installed Catalog integration';
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            if ($this->operation === 'add' && $this->argument('capability') !== 'catalog') {
                throw new \RuntimeException('Only the installed Catalog recipe is supported.');
            }
            if ($this->operation === 'make-module' && $this->option('blueprint') !== 'catalog.standard') {
                throw new \RuntimeException('Only catalog.standard is supported.');
            }
            $module = $this->operation === 'make-module' ? $this->argument('name') : $this->option('module');
            $installer = new CatalogInstaller(base_path(), config('app-modules.modules_directory', 'app-modules'), config('app-modules.modules_namespace', 'Modules'));
            if ($this->operation === 'rollback') {
                $installer->rollback($module);
                $this->info('Owned files restored. Run composer dump-autoload and clear application caches. No database changes were made.');

                return self::SUCCESS;
            }
            $plan = $installer->plan($module);
            $preview = $plan;
            foreach ($preview['operations'] as &$operation) {
                if ($operation['status'] === 'conflict') {
                    $operation['manual_patch'] = $operation['content'];
                }
                unset($operation['content']);
            }
            unset($operation);
            $this->line(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if ($plan['conflicts'] !== []) {
                $this->error('Preflight conflicts: files preserved. Review the listed manual patches.');

                return self::FAILURE;
            }
            if ($this->operation === 'doctor') {
                $installer->verify($plan, runtime: true);
                $this->info('Catalog files, module registration, bindings, authentication and routes verified.');
            } elseif (! $this->option('dry-run')) {
                $installer->apply($plan);
                $this->info('Files verified. Run composer dump-autoload, then foundry:doctor in a new process.');
            }
            $this->warn('Database migrations are listed separately in this plan. Run php artisan migrate only as a reviewed operator step; Foundry never executes migrations.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());
            $this->line('Completed/pending operations are recorded in .foundry/installations.json. Retry the same command to resume.');

            return self::FAILURE;
        }
    }
}
