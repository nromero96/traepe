<?php

namespace App\Modules\Identity\Application\Authorization;

use App\Modules\Identity\Domain\Authorization\PermissionRule;

/** Actor state and role/grant rules must be read from trusted Identity storage. */
interface AuthorizationDirectory
{
    public function isActive(string $actorPublicId): bool;

    /** @return iterable<PermissionRule> */
    public function rules(string $actorPublicId): iterable;
}
