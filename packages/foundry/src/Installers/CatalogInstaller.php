<?php

declare(strict_types=1);

namespace Raid\Foundry\Installers;

use Illuminate\Support\Str;
use Raid\Catalog\Contracts\SkuPolicy;
use RuntimeException;

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
        $composer = $this->json($this->path('composer.json'));
        $prefix = $namespace.'\\';
        $mapping = $base.'/src/';
        $existing = $composer['autoload']['psr-4'][$prefix] ?? null;
        if ($existing !== null && $existing !== $mapping) {
            $conflicts[] = 'Autoload namespace already maps to a different path: '.$prefix;
        } elseif ($existing === null) {
            $composer['autoload']['psr-4'][$prefix] = $mapping;
            $files['composer.json'] = ['content' => json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n", 'kind' => 'edit', 'template_identity' => 'catalog:autoload'];
        }
        $provider = $namespace.'\\Providers\\'.$module.'ServiceProvider';
        $providers = file_get_contents($this->path('bootstrap/providers.php'));
        $aliases = [$provider];
        if (preg_match('/use\s+'.preg_quote($provider, '/').'(?:\s+as\s+(\w+))?\s*;/', $providers, $import)) {
            $aliases[] = $import[1] ?? $module.'ServiceProvider';
        }
        $count = 0;
        foreach ($aliases as $alias) {
            $count += substr_count($providers, $alias.'::class');
        }
        if ($count > 1) {
            $conflicts[] = 'Duplicate Catalog provider registrations in bootstrap/providers.php.';
        } elseif ($count === 0) {
            if (! preg_match('/return\s*\[([\s\S]*?)\];\s*$/', $providers, $array)
                || ! preg_match('/^\s*(?:[A-Za-z_\\\\][A-Za-z0-9_\\\\]*::class\s*,\s*)*$/', $array[1])) {
                $conflicts[] = 'Cannot safely edit bootstrap/providers.php. Manual patch: add '.$provider.'::class to the returned provider array once.';
            } else {
                $providers = str_replace($array[0], 'return ['.$array[1].'    '.$provider."::class,\n];\n", $providers);
                $files['bootstrap/providers.php'] = ['content' => $providers, 'kind' => 'edit', 'template_identity' => 'catalog:provider'];
            }
        }
        ksort($files);
        foreach ($prior['operations'] ?? [] as $entry) {
            if ($entry['kind'] === 'edit' && ($prior['status'] ?? null) !== 'restored') {
                $actual = $this->hash($this->path($entry['path']));
                $allowed = [$entry['hash'] ?? $entry['planned_hash']];
                if ($entry['status'] === 'pending') {
                    $allowed[] = $entry['expected_previous_hash'];
                }
                if (! in_array($actual, $allowed, true)) {
                    $conflicts[] = 'Customized owned configuration: '.$entry['path'].'. Preserve it and review the autoload/provider changes manually.';
                }
            }
            if ($entry['kind'] === 'edit' && ! isset($files[$entry['path']])) {
                $path = $this->path($entry['path']);
                $hash = $this->hash($path);
                if ($hash !== ($entry['hash'] ?? $entry['planned_hash'])) {
                    $conflicts[] = 'Customized owned configuration: '.$entry['path'].'. Review its registration/autoload patch manually.';
                }
                if ($hash !== null) {
                    $files[$entry['path']] = ['content' => file_get_contents($path), 'kind' => 'edit', 'template_identity' => $entry['template_identity']];
                }
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
                $old = $prior['operations'][$operation['id']] ?? [];
                $entry = $operation;
                unset($entry['content']);
                $entry['before_hash'] = array_key_exists('before_hash', $old) ? $old['before_hash'] : $operation['expected_previous_hash'];
                $entry['backup'] = $old['backup'] ?? null;
                if ($operation['status'] === 'unchanged' && isset($old['hash']) && $old['hash'] !== $operation['planned_hash']) {
                    // Matching manual changes are adopted without claiming permission to undo them.
                    $entry['before_hash'] = $operation['expected_previous_hash'];
                    $entry['backup'] = null;
                }
                $entry['status'] = 'pending';
                $record['operations'][$operation['id']] = $entry;
            }
            // Preserve owned editor records omitted from an unchanged plan.
            $record['operations'] += $prior['operations'] ?? [];
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
        foreach ($record['operations'] as $entry) {
            $actual = $this->hash($this->path($entry['path']));
            if ($actual !== $entry['planned_hash'] && $actual !== $entry['before_hash']) {
                throw new RuntimeException('Recovery conflict: preserve customized '.$entry['path']);
            }
            if ($entry['before_hash'] !== null && (! isset($entry['backup']) || $this->hash($this->path($entry['backup'])) !== $entry['before_hash'])) {
                if ($actual !== $entry['before_hash']) {
                    throw new RuntimeException('Missing or corrupt recovery backup: '.$entry['path']);
                }
            }
        }
        $lock = $this->lock();
        try {
            foreach (array_reverse(array_keys($record['operations'])) as $id) {
                $entry = &$journal['installations']['catalog']['operations'][$id];
                $path = $this->path($entry['path']);
                if ($this->hash($path) !== $entry['before_hash']) {
                    if ($entry['before_hash'] === null) {
                        if (! unlink($path)) {
                            throw new RuntimeException('Cannot remove '.$entry['path']);
                        }
                    } else {
                        $this->atomicWrite($path, file_get_contents($this->path($entry['backup'])));
                    }
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

    private function completeUnchanged(array $plan, array $prior): bool
    {
        return ($prior['status'] ?? null) === 'completed'
            && ($prior['recipe_version'] ?? null) === $plan['recipe_version']
            && ($prior['blueprint_version'] ?? null) === $plan['blueprint_version']
            && array_all($plan['operations'], fn (array $operation): bool => $operation['status'] === 'unchanged');
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

        return is_file($path) ? $this->json($path) : ['schema_version' => 1, 'installations' => []];
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
