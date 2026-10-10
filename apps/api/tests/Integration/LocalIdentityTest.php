<?php

namespace Tests\Integration;

use App\Modules\Identity\Application\LocalOtp;
use App\Modules\Identity\Domain\LocalConsent;
use App\Modules\Identity\Domain\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Process\Process;

final class LocalIdentityTest extends PostgresTestCase
{
    private function code(string $id): string
    {
        return Crypt::decryptString(DB::table('identity_otp_challenges')->where('public_id', $id)->value('code_ciphertext'));
    }

    public function test_registration_consent_session_and_replay(): void
    {
        config(['session.driver' => 'array']);
        $this->app->detectEnvironment(fn () => 'testing');
        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '+12025550123'])->assertStatus(202);
        $id = $response->json('data.id');
        $payload = ['challenge_id' => $id, 'code' => $this->code($id), 'name' => 'Local Test', 'consent_version' => 'local-v1', 'consent_accepted' => true];
        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertOk()->assertJsonPath('data.type', 'users');
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('identity_profiles')->count());
        $this->assertSame(1, DB::table('identity_consents')->count());
        $this->assertSame(1, DB::table('identity_audit')->count());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonMissingPath('data.attributes.phone');
        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertUnprocessable()->assertJsonPath('error.code', 'identity_verification_failed');
        $this->postJson('/api/v1/auth/logout')->assertOk();
        // Real HTTP requests resolve fresh guards; this harness shares its container.
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertStringNotContainsString('Local Test', DB::table('identity_profiles')->value('name_ciphertext'));
    }

    public function test_rejections_attempt_limits_expiry_and_blocked_identity(): void
    {
        $otp = app(LocalOtp::class);
        $id = $otp->request(new PhoneNumber('+12025550124'));
        $code = $this->code($id);
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->assertNull($otp->verify($id, $wrong, 'Local Test', new LocalConsent(true)));
        }
        $this->assertNull($otp->verify($id, $code, 'Local Test', new LocalConsent(true)));
        $this->travel(61)->seconds();
        $newId = $otp->request(new PhoneNumber('+12025550124'));
        $this->assertNull($otp->verify($id, $code, 'Local Test', new LocalConsent(true)));
        $newCode = $this->code($newId);
        $publicId = $otp->verify($newId, $newCode, 'Local Test', new LocalConsent(true));
        $this->assertNotNull($publicId);
        DB::table('users')->where('public_id', $publicId)->update(['status' => 'blocked']);
        $this->travel(61)->seconds();
        $blocked = $otp->request(new PhoneNumber('+12025550124'));
        $this->assertNull($otp->verify($blocked, $this->code($blocked), 'Local Test', new LocalConsent(true)));
        $expired = $otp->request(new PhoneNumber('+12025550125'));
        $expiredCode = $this->code($expired);
        $this->travel(300)->seconds();
        $this->assertNull($otp->verify($expired, $expiredCode, 'Local Test', new LocalConsent(true)));
    }

    public function test_resend_is_rate_limited_without_rotating_the_code(): void
    {
        $otp = app(LocalOtp::class);
        $otp->request(new PhoneNumber('+12025550126'));
        $this->expectException(TooManyRequestsHttpException::class);
        $otp->request(new PhoneNumber('+12025550126'));
    }

    public function test_independent_processes_cannot_consume_the_same_challenge_twice(): void
    {
        $id = app(LocalOtp::class)->request(new PhoneNumber('+12025550127'));
        $input = json_encode(['challenge' => $id, 'code' => $this->code($id)], JSON_THROW_ON_ERROR);
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/identity-race-worker.php')], base_path(), ['TRAEPE_TEST_DATABASE' => $this->probeDatabase]);
            $worker->setInput($input)->setTimeout(30)->start();
            $workers[] = $worker;
        }
        $results = [];
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait());
            $results[] = $worker->getOutput();
        }
        sort($results);
        $this->assertSame(['accepted', 'rejected'], $results);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('identity_consents')->count());
        $this->assertSame(1, DB::table('identity_audit')->count());
    }

    public function test_failure_rolls_back_consumption_identity_consent_and_audit(): void
    {
        $otp = app(LocalOtp::class);
        $id = $otp->request(new PhoneNumber('+12025550128'));
        $code = $this->code($id);
        DB::statement("ALTER TABLE identity_audit ADD CONSTRAINT forced_failure CHECK (operation = 'unreachable')");
        try {
            $otp->verify($id, $code, 'Local Test', new LocalConsent(true));
            $this->fail('Expected atomic write rejection.');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('users')->count());
            $this->assertSame(0, DB::table('identity_profiles')->count());
            $this->assertSame(0, DB::table('identity_consents')->count());
            $this->assertNull(DB::table('identity_otp_challenges')->value('consumed_at'));
            $this->assertSame(0, DB::table('identity_otp_challenges')->value('attempts'));
        }
        DB::statement('ALTER TABLE identity_audit DROP CONSTRAINT forced_failure');
        $this->assertNotNull($otp->verify($id, $code, 'Local Test', new LocalConsent(true)));
    }

    public function test_consent_and_audit_history_reject_update_and_delete(): void
    {
        $otp = app(LocalOtp::class);
        $id = $otp->request(new PhoneNumber('+12025550129'));
        $otp->verify($id, $this->code($id), 'Local Test', new LocalConsent(true));
        foreach (['identity_consents', 'identity_audit'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    $operation === 'delete' ? DB::table($table)->delete() : DB::table($table)->update(['correlation_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']);
                    $this->fail('Expected immutable history rejection.');
                } catch (QueryException $error) {
                    $this->assertSame('P0001', $error->errorInfo[0]);
                }
            }
        }
    }
}
