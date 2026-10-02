<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modules Base Directory
    |--------------------------------------------------------------------------
    |
    | The base directory (relative to the application root) where all modules
    | are stored. Each module is a self-contained subdirectory.
    |
    | Default: app-modules
    |
    | Example structure:
    |   app-modules/
    |   ├── user/
    |   │   ├── composer.json
    |   │   ├── src/
    |   │   │   ├── Actions/
    |   │   │   ├── Models/
    |   │   │   ├── Providers/UserServiceProvider.php
    |   │   │   └── ...
    |   │   ├── routes/user-routes.php
    |   │   └── tests/
    |   └── order/
    |       └── ...
    |
    */
    'modules_directory' => env('APP_MODULES_DIRECTORY', 'app-modules'),

    /*
    |--------------------------------------------------------------------------
    | Modules Root Namespace
    |--------------------------------------------------------------------------
    |
    | The root PHP namespace for all modules. Each module's namespace is derived
    | as: {modules_namespace}\{StudlyModuleName}
    |
    | Default: Modules
    |
    | Example: Modules\User, Modules\Order, Modules\Catalog
    |
    */
    'modules_namespace' => env('APP_MODULES_NAMESPACE', 'Modules'),

];
