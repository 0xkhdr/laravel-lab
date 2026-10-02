<?php

declare(strict_types=1);

return [
    'user_model' => null,
    'auth_guard' => env('CATALOG_AUTH_GUARD', 'web'),
    'permission_cache_ttl' => 900,
];
