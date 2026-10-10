<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\LocalConsent;
use App\Modules\Identity\Domain\PhoneNumber;

interface LocalOtp
{
    public function request(PhoneNumber $phone): string;

    public function verify(string $challenge, string $code, string $name, LocalConsent $consent): ?string;

    public function activeIdentity(int $id): ?string;

    public function recordSessionEnd(int $id): void;
}
