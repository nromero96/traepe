<?php

namespace App\Modules\Identity\Application\Authorization;

use App\Modules\Identity\Domain\Authorization\Decision;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use DateTimeImmutable;

/** Fixed fixtures for local wiring verification, never a production platform identity. */
final readonly class LocalAuthorizationProbe
{
    public const CAPABILITY = 'identity.local.probe';

    public const SCOPE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public const RESOURCE_PUBLIC_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    public function __construct(private AuthenticatedActorDirectory $actors, private PermissionService $permissions) {}

    public static function scope(): Scope
    {
        return new Scope('platform', self::SCOPE_PUBLIC_ID);
    }

    public static function resource(): ResourceReference
    {
        return new ResourceReference('identity.local.probe', self::RESOURCE_PUBLIC_ID);
    }

    public function evaluate(int $authenticatedId, DateTimeImmutable $serverNow): Decision
    {
        $actor = $this->actors->publicId($authenticatedId);
        if ($actor === null) {
            return Decision::InactiveActor;
        }

        return $this->permissions->decide($actor, self::CAPABILITY, self::scope(), self::resource(), $serverNow);
    }
}
