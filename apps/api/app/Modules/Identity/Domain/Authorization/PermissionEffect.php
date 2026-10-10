<?php

namespace App\Modules\Identity\Domain\Authorization;

enum PermissionEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
