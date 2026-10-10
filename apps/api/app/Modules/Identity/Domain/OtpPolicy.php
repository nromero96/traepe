<?php

namespace App\Modules\Identity\Domain;

final class OtpPolicy
{
    public const TTL = 300;

    public const RESEND = 60;

    public const MAX_ATTEMPTS = 5;

    public function allowsVerification(int $now, int $expires, int $attempts, bool $consumed): bool
    {
        return ! $consumed && $now < $expires && $attempts < self::MAX_ATTEMPTS;
    }
}
