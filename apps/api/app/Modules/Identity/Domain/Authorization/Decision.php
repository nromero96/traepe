<?php

namespace App\Modules\Identity\Domain\Authorization;

enum Decision: string
{
    case Allowed = 'allowed';
    case InactiveActor = 'inactive_actor';
    case UnknownResource = 'unknown_resource';
    case ScopeMismatch = 'scope_mismatch';
    case ExplicitDeny = 'explicit_deny';
    case NoGrant = 'no_grant';

    public function allowed(): bool
    {
        return $this === self::Allowed;
    }
}
