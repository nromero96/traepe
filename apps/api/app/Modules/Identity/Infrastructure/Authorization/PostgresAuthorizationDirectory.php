<?php

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Domain\Authorization\PermissionEffect;
use App\Modules\Identity\Domain\Authorization\PermissionRule;
use App\Modules\Identity\Domain\Authorization\Scope;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgresAuthorizationDirectory implements AuthorizationDirectory
{
    public function isActive(string $actorPublicId): bool
    {
        return DB::table('users')->where('public_id', $actorPublicId)->where('status', 'active')->exists();
    }

    public function rules(string $actorPublicId): iterable
    {
        // Recheck actor state in each query; never load another user's assignments.
        $roles = DB::table('identity_role_assignments as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('identity_role_permissions as rp', 'rp.role_id', '=', 'a.role_id')
            ->join('identity_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('u.public_id', $actorPublicId)->where('u.status', 'active')
            ->select(['p.code', 'a.scope_type', 'a.scope_public_id'])
            ->selectRaw("'allow'::text AS effect, NULL::timestamptz AS expires_at");
        $grants = DB::table('identity_permission_grants as g')
            ->join('users as u', 'u.id', '=', 'g.user_id')
            ->join('identity_permissions as p', 'p.id', '=', 'g.permission_id')
            ->where('u.public_id', $actorPublicId)->where('u.status', 'active')
            ->select(['p.code', 'g.scope_type', 'g.scope_public_id', 'g.effect', 'g.expires_at']);
        // One SQL statement gives role and exception reads the same PostgreSQL snapshot.
        $rows = $roles->unionAll($grants)->get();
        $rules = [];
        foreach ($rows as $row) {
            $rules[] = new PermissionRule(
                $actorPublicId, $row->code, new Scope($row->scope_type, $row->scope_public_id),
                PermissionEffect::from($row->effect), $row->expires_at === null ? null : new DateTimeImmutable($row->expires_at),
            );
        }

        return $rules;
    }
}
