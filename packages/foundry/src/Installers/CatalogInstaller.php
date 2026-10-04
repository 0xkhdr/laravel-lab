<?php

declare(strict_types=1);

namespace Raid\Foundry\Installers;

use Illuminate\Support\Str;
use Raid\Catalog\Contracts\SkuPolicy;
use RuntimeException;
use Throwable;

final class CatalogInstaller
{
    public function __construct(
        private readonly string $root,
        private readonly string $moduleDirectory = 'app-modules',
        private readonly string $rootNamespace = 'Modules',
        private readonly string $templates = __DIR__.'/../../resources/catalog',
    ) {}

    public function plan(string $name = 'Catalog'): array
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*(?:-[A-Za-z0-9]+)*$/', $name)
            || ! preg_match('/^[A-Za-z][A-Za-z0-9\\\\]*$/', $this->rootNamespace)) {
            throw new RuntimeException('Invalid module name or namespace.');
        }
        $module = Str::studly($name);
        $slug = Str::kebab($module);
        $base = trim($this->moduleDirectory, '/').'/'.$slug;
        $this->path($this->moduleDirectory);
        $this->path($base);
        $namespace = $this->rootNamespace.'\\'.$module;
        $metadata = $this->json($this->templates.'/blueprint.json');
        $journal = $this->journal();
        $prior = $journal['installations']['catalog'] ?? [];
        $conflicts = [];
        if ($prior && $prior['module_path'] !== $base && $prior['status'] !== 'restored') {
            $conflicts[] = 'Catalog already has a schema owner at '.$prior['module_path'];
        }
        if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
            $conflicts[] = 'PHP 8.4 is required.';
        }
        $installed = $this->installed();
        foreach (['laravel/framework', 'raid/pillar', 'raid/catalog', 'internachi/modular', 'spatie/laravel-translatable', 'laravel/sanctum'] as $package) {
            if (! isset($installed[$package])) {
                $conflicts[] = 'Install '.$package.' with Composer before applying this recipe.';
            }
        }
        if (isset($installed['laravel/framework']) && ! str_starts_with(ltrim($installed['laravel/framework']['version'], 'v'), '12.')) {
            $conflicts[] = 'Laravel 12 is required.';
        }
        $schema = '';
        foreach (glob($this->path('database/migrations').'/*.php') ?: [] as $file) {
            $schema .= file_get_contents($file);
        }
        if (! preg_match('/->uuid\([\'\"]id[\'\"]\)/', $schema) || ! str_contains($schema, 'is_catalog_admin') || ! str_contains($schema, 'personal_access_tokens')) {
            $conflicts[] = 'Prerequisite: application-owned UUID users, is_catalog_admin boolean and Sanctum personal_access_tokens with UUID morph keys. Review authentication migrations manually.';
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->templates, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.stub')) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($this->templates) + 1, -5);
            $relative = str_replace(['CatalogServiceProvider.php', 'catalog-routes.php'], [$module.'ServiceProvider.php', $slug.'-routes.php'], $relative);
            $content = str_replace(['{{ namespace }}', '{{ json_namespace }}', '{{ module }}', '{{ slug }}'], [$namespace, str_replace('\\', '\\\\', $namespace), $module, $slug], file_get_contents($file->getPathname()));
            $files[$base.'/'.$relative] = ['content' => $content, 'kind' => 'generate', 'template_identity' => 'catalog:'.$relative];
        }
        $tables = ['brands', 'categories', 'regions', 'products', 'user_brand_permissions', 'user_category_permissions', 'user_product_permissions', 'catalog_permission_states'];
        $migrations = [];
        $catalogPath = $installed['raid/catalog']['install_path'] ?? null;
        foreach ($tables as $index => $table) {
            $filename = sprintf('2026_01_02_00000%d_create_%s_table.php', $index, $table);
            $path = $base.'/database/migrations/'.$filename;
            $migrations[] = ['template_identity' => 'catalog:schema:'.$table.':1', 'path' => $path, 'operation' => 'operator runs php artisan migrate separately'];
            $template = $catalogPath ? $catalogPath.'/database/migrations/create_'.$table.'_table.php' : null;
            if (! $template || ! is_file($template)) {
                $conflicts[] = 'Missing installed Catalog schema template: '.$table;

                continue;
            }
            foreach ($this->migrationCopies($table) as $copy) {
                if ($copy !== $path) {
                    $conflicts[] = 'Duplicate migration identity '.$table.' already owned by '.$copy;
                }
            }
            $files[$path] = ['content' => file_get_contents($template), 'kind' => 'generate', 'template_identity' => 'catalog:schema:'.$table.':1'];
        }
        $shared = [
            'composer.json' => ['version' => 1, 'type' => 'autoload', 'key' => $namespace.'\\', 'value' => $base.'/src/'],
            'bootstrap/providers.php' => ['version' => 1, 'type' => 'provider', 'value' => $namespace.'\\Providers\\'.$module.'ServiceProvider'],
        ];
        foreach ($shared as $relative => $contribution) {
            try {
                $content = file_get_contents($this->path($relative));
                $old = ($prior['status'] ?? null) === 'restored' ? null : ($prior['operations'][hash('sha256', $relative)] ?? null);
                $value = CatalogSharedFiles::value($content, $contribution);
                if ($old) {
                    $contribution = $this->contribution($old, $contribution);
                    if ($value === null && $old['status'] === 'completed') {
                        throw new RuntimeException('Catalog registration removed; review before reinstalling.');
                    }
                } else {
                    $contribution['owned'] = $value === null;
                }
                $content = CatalogSharedFiles::patch($content, $contribution);
                $contribution['original_hash'] ??= $old['planned_hash'] ?? hash('sha256', $content);
                $files[$relative] = ['content' => $content, 'kind' => 'edit',
                    'template_identity' => $relative === 'composer.json' ? 'catalog:autoload' : 'catalog:provider',
                    'contribution' => $contribution];
            } catch (Throwable $error) {
                $conflicts[] = 'Shared configuration conflict: '.$relative.': '.$error->getMessage();
            }
        }
        ksort($files);
        $operations = [];
        foreach ($files as $relative => $file) {
            try {
                if (str_ends_with($relative, '.php')) {
                    token_get_all($file['content'], TOKEN_PARSE);
                } elseif (str_ends_with($relative, '.json')) {
                    json_decode($file['content'], true, 512, JSON_THROW_ON_ERROR);
                }
                if (str_contains($file['content'], '{{ ')) {
                    throw new RuntimeException('Unresolved blueprint placeholder.');
                }
            } catch (Throwable $error) {
                $conflicts[] = 'Invalid generated content for '.$relative.': '.$error->getMessage();
            }
            $path = $this->path($relative);
            $current = $this->hash($path);
            $desired = hash('sha256', $file['content']);
            $id = hash('sha256', $relative);
            $owned = $prior['operations'][$id] ?? null;
            $conflict = $file['kind'] === 'generate' && $current !== null && $current !== $desired
                && (! $owned || $current !== ($owned['hash'] ?? null));
            $parent = dirname($path);
            while (! is_dir($parent)) {
                $parent = dirname($parent);
            }
            if (! is_writable($parent)) {
                $conflict = true;
            }
            $operation = $file + ['id' => $id, 'path' => $relative, 'expected_previous_hash' => $current, 'planned_hash' => $desired,
                'ownership' => 'application', 'status' => $conflict ? 'conflict' : ($current === $desired ? 'unchanged' : ($current === null ? 'create' : 'update')),
                'conflict_reason' => $conflict ? 'Customized/unowned file or unwritable destination. Preserve this file; manually review proposed content.' : null,
                'verification' => 'sha256 after atomic write'];
            if ($conflict) {
                $conflicts[] = $relative.': '.$operation['conflict_reason'];
            }
            $operations[] = $operation;
        }

        return $metadata + ['module' => $module, 'namespace' => $namespace, 'module_path' => $base,
            'prerequisites' => ['Composer-installed runtime packages', 'PHP 8.4 / Laravel 12', 'UUID users schema and administrator flag', 'composer dump-autoload after configuration'],
            'operations' => $operations, 'migrations' => $migrations, 'conflicts' => array_values(array_unique($conflicts))];
    }

    public function apply(array $plan, ?callable $afterWrite = null): void
    {
        if ($plan['conflicts'] !== []) {
            throw new RuntimeException('Preflight conflicts: no files written.');
        }
        // Preflight occurs before even creating the journal or lock directory.
        foreach ($plan['operations'] as $operation) {
            if ($this->hash($this->path($operation['path'])) !== $operation['expected_previous_hash']) {
                throw new RuntimeException('Stale plan: '.$operation['path'].' changed. Inspect again.');
            }
        }
        $lock = $this->lock();
        try {
            $journal = $this->journal();
            $prior = $journal['installations']['catalog'] ?? [];
            $record = $plan;
            unset($record['conflicts'], $record['prerequisites'], $record['migrations']);
            $record['operations'] = [];
            $record['status'] = 'pending';
            foreach ($plan['operations'] as $operation) {
                $old = ($prior['status'] ?? null) === 'restored' ? [] : ($prior['operations'][$operation['id']] ?? []);
                $entry = $operation;
                unset($entry['content']);
                $entry['before_hash'] = array_key_exists('before_hash', $old) ? $old['before_hash'] : $operation['expected_previous_hash'];
                $entry['backup'] = $old['backup'] ?? null;
                if (! isset($operation['contribution']) && $operation['status'] === 'unchanged' && isset($old['hash']) && $old['hash'] !== $operation['planned_hash']) {
                    // Matching manual changes are adopted without claiming permission to undo them.
                    $entry['before_hash'] = $operation['expected_previous_hash'];
                    $entry['backup'] = null;
                }
                $entry['status'] = 'pending';
                $record['operations'][$operation['id']] = $entry;
            }
            // Preserve owned editor records omitted from an unchanged plan.
            if (($prior['status'] ?? null) !== 'restored') {
                $record['operations'] += $prior['operations'] ?? [];
            }
            $journal['installations']['catalog'] = $record;
            if ($this->completeUnchanged($plan, $prior)) {
                $this->verify($plan);

                return;
            }
            $this->saveJournal($journal);
            foreach ($plan['operations'] as $index => $operation) {
                $path = $this->path($operation['path']);
                $entry = &$journal['installations']['catalog']['operations'][$operation['id']];
                // Check again under the lock, including customization after preflight.
                if ($this->hash($path) !== $operation['expected_previous_hash']) {
                    throw new RuntimeException('File changed during apply: '.$operation['path']);
                }
                if ($operation['status'] !== 'unchanged') {
                    if ($operation['expected_previous_hash'] !== null) {
                        $backup = '.foundry/backups/'.$operation['id'].'/'.$operation['expected_previous_hash'].'.bak';
                        $this->atomicWrite($this->path($backup), file_get_contents($path));
                        if ($entry['backup'] === null) {
                            $entry['backup'] = $backup;
                        }
                        $this->saveJournal($journal);
                    }
                    $this->atomicWrite($path, $operation['content']);
                    if ($afterWrite) {
                        $afterWrite($index + 1, $operation);
                    }
                }
                if ($this->hash($path) !== $operation['planned_hash']) {
                    throw new RuntimeException('Write verification failed: '.$operation['path']);
                }
                $entry['hash'] = $operation['planned_hash'];
                $entry['status'] = 'completed';
                $this->saveJournal($journal);
                unset($entry);
            }
            $this->verify($plan);
            $journal['installations']['catalog']['status'] = 'completed';
            $this->saveJournal($journal);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function verify(array $plan, bool $runtime = false): void
    {
        foreach ($plan['operations'] as $operation) {
            if ($this->hash($this->path($operation['path'])) !== $operation['planned_hash']) {
                throw new RuntimeException('Verification failed for '.$operation['path']);
            }
        }
        if ($runtime) {
            $provider = $plan['namespace'].'\\Providers\\'.$plan['module'].'ServiceProvider';
            $user = $plan['namespace'].'\\Models\\CatalogUser';
            $policy = $plan['namespace'].'\\Policies\\ApplicationSku';
            if (! class_exists($provider) || ! app()->providerIsLoaded($provider)
                || config('auth.providers.users.model') !== $user || config('catalog.user_model') !== $user
                || ! method_exists($user, 'getCachedPermissions')
                || ! (app(SkuPolicy::class) instanceof $policy)
                || ! app('router')->getRoutes()->getByName('catalog.products.store')) {
                throw new RuntimeException('Runtime integration incomplete. Run composer dump-autoload, clear caches and retry doctor.');
            }
        }
    }

    public function rollback(string $module): void
    {
        $journal = $this->journal();
        $record = $journal['installations']['catalog'] ?? null;
        if (! $record || $record['module'] !== Str::studly($module)) {
            throw new RuntimeException('No owned Catalog installation for this module.');
        }
        $lock = $this->lock();
        try {
            $journal = $this->journal();
            $record = $journal['installations']['catalog'] ?? null;
            if (! $record || $record['module'] !== Str::studly($module)) {
                throw new RuntimeException('Catalog installation changed before recovery. Inspect again.');
            }
            // Build every recovery output before changing any application file.
            $recovery = [];
            foreach ($record['operations'] as $id => $entry) {
                $path = $this->path($entry['path']);
                $actual = $this->hash($path);
                if (in_array($entry['path'], ['composer.json', 'bootstrap/providers.php'], true)) {
                    $default = $entry['path'] === 'composer.json'
                        ? ['version' => 1, 'type' => 'autoload', 'key' => $record['namespace'].'\\', 'value' => $record['module_path'].'/src/']
                        : ['version' => 1, 'type' => 'provider', 'value' => $record['namespace'].'\\Providers\\'.$record['module'].'ServiceProvider'];
                    try {
                        $contribution = $this->contribution($entry, $default);
                        $content = file_get_contents($path);
                        $value = CatalogSharedFiles::value($content, $contribution);
                        if ($value !== null && $value !== $contribution['value']) {
                            throw new RuntimeException('Catalog contribution changed.');
                        }
                        if ($contribution['owned']) {
                            $content = CatalogSharedFiles::patch($content, $contribution, remove: true);
                            // An exact, verified snapshot can preserve original formatting.
                            if ($actual === ($contribution['original_hash'] ?? $entry['planned_hash']) && isset($entry['backup'])
                                && $this->hash($this->path($entry['backup'])) === $entry['before_hash']) {
                                $content = file_get_contents($this->path($entry['backup']));
                            }
                        }
                    } catch (Throwable $error) {
                        throw new RuntimeException('Recovery conflict: '.$entry['path'].': '.$error->getMessage(), previous: $error);
                    }
                } else {
                    if ($actual !== $entry['planned_hash'] && $actual !== $entry['before_hash']) {
                        throw new RuntimeException('Recovery conflict: preserve customized '.$entry['path']);
                    }
                    $content = $actual === null ? null : file_get_contents($path);
                    if ($actual !== $entry['before_hash']) {
                        if ($entry['before_hash'] === null) {
                            $content = null;
                        } else {
                            if (! isset($entry['backup']) || $this->hash($this->path($entry['backup'])) !== $entry['before_hash']) {
                                throw new RuntimeException('Missing or corrupt recovery backup: '.$entry['path']);
                            }
                            $content = file_get_contents($this->path($entry['backup']));
                        }
                    }
                }
                $recovery[$id] = ['expected' => $actual, 'content' => $content];
            }
            foreach (array_reverse(array_keys($record['operations'])) as $id) {
                $entry = &$journal['installations']['catalog']['operations'][$id];
                $path = $this->path($entry['path']);
                $output = $recovery[$id];
                if ($this->hash($path) !== $output['expected']) {
                    throw new RuntimeException('Recovery conflict: file changed during recovery: '.$entry['path']);
                }
                if ($output['content'] === null) {
                    if ($output['expected'] !== null && ! unlink($path)) {
                        throw new RuntimeException('Cannot remove '.$entry['path']);
                    }
                } elseif (hash('sha256', $output['content']) !== $output['expected']) {
                    $this->atomicWrite($path, $output['content']);
                }
                $entry['status'] = 'restored';
                $this->saveJournal($journal);
                unset($entry);
            }
            $journal['installations']['catalog']['status'] = 'restored';
            $this->saveJournal($journal);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function contribution(array $entry, array $default): array
    {
        if (isset($entry['contribution'])) {
            $contribution = $entry['contribution'];
            if (($contribution['version'] ?? null) !== 1 || ! is_bool($contribution['owned'] ?? null)
                || array_diff_assoc($default, $contribution) !== []) {
                throw new RuntimeException('Unsupported or mismatched Catalog contribution metadata. Review the journal.');
            }

            return $contribution;
        }
        // Schema-1 whole-file records: infer ownership only from verified before evidence.
        if ($entry['before_hash'] === $entry['planned_hash']) {
            return $default + ['owned' => false];
        }
        $backup = isset($entry['backup']) ? $this->path($entry['backup']) : null;
        if ($backup && $this->hash($backup) === $entry['before_hash']) {
            $before = file_get_contents($backup);
        } elseif ($this->hash($this->path($entry['path'])) === $entry['before_hash']) {
            $before = file_get_contents($this->path($entry['path']));
        } else {
            throw new RuntimeException('Legacy ownership cannot be proven: missing/corrupt backup. Preserve files and review the journal.');
        }
        $value = CatalogSharedFiles::value($before, $default);
        if ($value !== null && $value !== $default['value']) {
            throw new RuntimeException('Legacy backup contains a different Catalog contribution.');
        }

        return $default + ['owned' => $value === null];
    }

    private function completeUnchanged(array $plan, array $prior): bool
    {
        return ($prior['status'] ?? null) === 'completed'
            && ($prior['recipe_version'] ?? null) === $plan['recipe_version']
            && ($prior['blueprint_version'] ?? null) === $plan['blueprint_version']
            && array_all($plan['operations'], fn (array $operation): bool => $operation['status'] === 'unchanged'
                && (! isset($operation['contribution']) || isset($prior['operations'][$operation['id']]['contribution'])));
    }

    private function migrationCopies(string $table): array
    {
        $copies = [];
        foreach (['database/migrations', $this->moduleDirectory] as $directory) {
            $path = $this->path($directory);
            if (! is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')
                    && (preg_match('/(?:^|_)create_'.preg_quote($table, '/').'_table\.php$/', $file->getFilename())
                        || preg_match('/Schema::create\(\s*[\'\"]'.preg_quote($table, '/').'[\'\"]/', file_get_contents($file->getPathname())))) {
                    $copies[] = substr($file->getPathname(), strlen($this->root) + 1);
                }
            }
        }

        return $copies;
    }

    private function installed(): array
    {
        $file = $this->path('vendor/composer/installed.json');
        if (! is_file($file)) {
            return [];
        }
        $json = $this->json($file);
        $packages = [];
        foreach ($json['packages'] ?? $json as $package) {
            $path = $package['install-path'] ?? '../'.($package['name'] ?? '');
            $package['install_path'] = realpath(str_starts_with($path, '/') ? $path : dirname($file).'/'.$path) ?: null;
            $packages[$package['name']] = $package;
        }

        return $packages;
    }

    private function journal(): array
    {
        $path = $this->path('.foundry/installations.json');

        $journal = is_file($path) ? $this->json($path) : ['schema_version' => 1, 'installations' => []];
        if (($journal['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported Foundry journal schema. Preserve files and review compatibility.');
        }

        return $journal;
    }

    private function saveJournal(array $journal): void
    {
        $this->atomicWrite($this->path('.foundry/installations.json'), json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    private function lock()
    {
        $directory = $this->path('.foundry');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create Foundry journal directory.');
        }
        $lock = fopen($this->path('.foundry/apply.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another Foundry apply is active. Retry after it completes.');
        }

        return $lock;
    }

    private function path(string $relative): string
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\') || str_contains($relative, "\0")
            || array_intersect(explode('/', $relative), ['..', '.', '']) !== []) {
            throw new RuntimeException('Unsafe path: '.$relative);
        }
        $path = rtrim($this->root, '/');
        foreach (explode('/', $relative) as $segment) {
            $path .= '/'.$segment;
            if (is_link($path)) {
                throw new RuntimeException('Symlink destination is not supported: '.$relative);
            }
        }

        return $path;
    }

    private function hash(string $path): ?string
    {
        if (file_exists($path) && ! is_file($path)) {
            throw new RuntimeException('Expected a file: '.$path);
        }

        return is_file($path) ? hash_file('sha256', $path) : null;
    }

    private function json(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Missing required file: '.$path);
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function atomicWrite(string $path, string $content): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create '.$directory);
        }
        $temporary = tempnam($directory, '.foundry-');
        if (! $temporary) {
            throw new RuntimeException('Cannot create atomic temporary file.');
        }
        try {
            if (file_put_contents($temporary, $content) !== strlen($content) || ! chmod($temporary, is_file($path) ? fileperms($path) & 0777 : 0644) || ! rename($temporary, $path)) {
                throw new RuntimeException('Atomic write failed: '.$path);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
