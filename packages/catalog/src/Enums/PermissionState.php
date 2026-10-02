<?php

declare(strict_types=1);

namespace Raid\Catalog\Enums;

enum PermissionState: string
{
    case Unconfigured = 'unconfigured';
    case Unrestricted = 'unrestricted';
    case Denied = 'denied';
    case Restricted = 'restricted';
    case Inherited = 'inherited';
}
