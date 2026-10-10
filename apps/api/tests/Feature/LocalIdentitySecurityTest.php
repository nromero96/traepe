<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\LocalOtp;
use App\Modules\Identity\Domain\PhoneNumber;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class LocalIdentitySecurityTest extends TestCase
{
    public function test_otp_mutations_require_csrf_and_do_not_echo_input(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '+12025550123']);
        $response->assertStatus(419)->assertJsonPath('error.code', 'csrf_token_mismatch');
        $this->assertStringNotContainsString('+12025550123', $response->getContent());
    }

    public function test_delivery_command_fails_outside_local_interactive_use(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        $this->artisan('identity:local-otp', ['challenge' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'])
            ->expectsOutput('Interactive local environment required.')->assertFailed();
    }

    public function test_invalid_phone_and_missing_consent_are_rejected_before_storage(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        $this->postJson('/api/v1/auth/otp/request', ['phone' => 'invalid-private-phone'])
            ->assertUnprocessable()->assertJsonMissingPath('error.details.phone.value');
        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'code' => '123456', 'name' => 'Local Test',
            'consent_version' => 'local-v1', 'consent_accepted' => false,
        ])->assertUnprocessable();
    }

    public function test_local_adapter_denies_production_before_accessing_storage(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            app(LocalOtp::class)->request(new PhoneNumber('+12025550123'));
            $this->fail('Production request accepted.');
        } catch (HttpException $error) {
            $this->assertSame(404, $error->getStatusCode());
        }
    }
}
