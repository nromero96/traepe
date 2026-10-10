<?php

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\LocalOtp;
use App\Modules\Identity\Domain\LocalConsent;
use App\Modules\Identity\Domain\OtpPolicy;
use App\Modules\Identity\Domain\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class PostgresLocalOtp implements LocalOtp
{
    public function activeIdentity(int $id): ?string
    {
        $value = DB::table('users')->where('id', $id)->where('status', 'active')->value('public_id');

        return is_string($value) ? $value : null;
    }

    public function recordSessionEnd(int $id): void
    {
        DB::table('identity_audit')->insert([
            'public_id' => (string) Str::ulid(), 'actor_id' => $id, 'operation' => 'session_ended',
            'correlation_id' => Context::get('correlation_id') ?? (string) Str::ulid(), 'recorded_at' => now(),
        ]);
    }

    public function request(PhoneNumber $phone): string
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $key = hash_hmac('sha256', $phone->value, (string) config('app.key'));

        return DB::transaction(function () use ($key, $phone): string {
            // Serializes first requests too; row locking alone cannot lock an absent row.
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
            $row = DB::table('identity_otp_challenges')->where('phone_key', $key)->lockForUpdate()->first();
            if ($row !== null && now()->diffInSeconds($row->issued_at, true) < OtpPolicy::RESEND) {
                throw new TooManyRequestsHttpException(OtpPolicy::RESEND);
            }
            $id = (string) Str::ulid();
            DB::table('identity_otp_challenges')->updateOrInsert(['phone_key' => $key], [
                'public_id' => $id,
                'phone_ciphertext' => Crypt::encryptString($phone->value),
                'code_ciphertext' => Crypt::encryptString(str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)),
                'attempts' => 0, 'issued_at' => now(), 'expires_at' => now()->addSeconds(OtpPolicy::TTL), 'consumed_at' => null,
            ]);

            return $id;
        });
    }

    public function verify(string $challenge, string $code, string $name, LocalConsent $consent): ?string
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return DB::transaction(function () use ($challenge, $code, $name, $consent): ?string {
            $row = DB::table('identity_otp_challenges')->where('public_id', $challenge)->lockForUpdate()->first();
            if ($row === null || ! (new OtpPolicy)->allowsVerification(now()->getTimestamp(), Carbon::parse($row->expires_at)->getTimestamp(), $row->attempts, $row->consumed_at !== null)) {
                return null;
            }
            DB::table('identity_otp_challenges')->where('public_id', $challenge)->increment('attempts');
            if (! hash_equals(Crypt::decryptString($row->code_ciphertext), $code)) {
                return null;
            }
            $user = DB::table('users')->where('phone_key', $row->phone_key)->lockForUpdate()->first();
            DB::table('identity_otp_challenges')->where('public_id', $challenge)->update(['consumed_at' => now()]);
            if ($user !== null && $user->status !== 'active') {
                return null;
            }
            if ($user === null) {
                $publicId = (string) Str::ulid();
                $userId = DB::table('users')->insertGetId([
                    'public_id' => $publicId, 'phone_key' => $row->phone_key, 'phone_ciphertext' => $row->phone_ciphertext,
                    'name' => '', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('identity_profiles')->insert(['user_id' => $userId, 'name_ciphertext' => Crypt::encryptString($name)]);
            } else {
                $publicId = $user->public_id;
                $userId = $user->id;
            }
            DB::table('identity_consents')->insertOrIgnore([
                'public_id' => (string) Str::ulid(), 'user_id' => $userId, 'version' => $consent->version,
                'accepted_at' => now(), 'correlation_id' => Context::get('correlation_id') ?? (string) Str::ulid(),
            ]);
            DB::table('identity_audit')->insert([
                'public_id' => (string) Str::ulid(), 'actor_id' => $userId, 'operation' => 'otp_verified',
                'correlation_id' => Context::get('correlation_id') ?? (string) Str::ulid(), 'recorded_at' => now(),
            ]);

            return $publicId;
        });
    }
}
