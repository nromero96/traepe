<?php

namespace Tests\Unit;

use App\Modules\Identity\Domain\LocalConsent;
use App\Modules\Identity\Domain\OtpPolicy;
use App\Modules\Identity\Domain\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IdentityPolicyTest extends TestCase
{
    public function test_phone_requires_canonical_international_format(): void
    {
        $this->assertSame('+12025550123', (new PhoneNumber('+12025550123'))->value);
        $this->expectException(InvalidArgumentException::class);
        new PhoneNumber('1202 5550123');
    }

    public function test_expiry_attempts_and_consumption_boundaries(): void
    {
        $policy = new OtpPolicy;
        $this->assertTrue($policy->allowsVerification(299, 300, 4, false));
        $this->assertFalse($policy->allowsVerification(300, 300, 4, false));
        $this->assertFalse($policy->allowsVerification(299, 300, 5, false));
        $this->assertFalse($policy->allowsVerification(299, 300, 0, true));
    }

    public function test_consent_cannot_be_constructed_without_explicit_acceptance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LocalConsent(false);
    }
}
