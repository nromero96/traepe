<?php

namespace App\Modules\Identity\Application\Authorization;

/** Resolves a server-authenticated internal identifier; never accepts a client actor claim. */
interface AuthenticatedActorDirectory
{
    public function publicId(int $authenticatedId): ?string;
}
