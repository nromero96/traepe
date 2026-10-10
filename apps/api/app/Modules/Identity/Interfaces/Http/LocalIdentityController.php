<?php

namespace App\Modules\Identity\Interfaces\Http;

use App\Modules\Identity\Application\LocalOtp;
use App\Modules\Identity\Domain\LocalConsent;
use App\Modules\Identity\Domain\OtpPolicy;
use App\Modules\Identity\Domain\PhoneNumber;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LocalIdentityController
{
    public function request(Request $request, LocalOtp $otp): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{1,14}$/D']]);
        $id = $otp->request(new PhoneNumber($data['phone']));

        return ApiResponse::resource($request, 'otp_challenges', $id, ['expires_in' => OtpPolicy::TTL, 'resend_after' => OtpPolicy::RESEND], 202);
    }

    public function verify(Request $request, LocalOtp $otp): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'ulid'], 'code' => ['required', 'string', 'regex:/^[0-9]{6}$/D'],
            'name' => ['required', 'string', 'max:120'], 'consent_version' => ['required', 'in:local-v1'],
            'consent_accepted' => ['required', 'accepted'],
        ]);
        $id = $otp->verify($data['challenge_id'], $data['code'], $data['name'], new LocalConsent((bool) $data['consent_accepted'], $data['consent_version']));
        if ($id === null) {
            return ApiResponse::error($request, 'identity_verification_failed', 'No se pudo verificar la solicitud.', 422);
        }
        $provider = Auth::createUserProvider('users');
        $user = $provider?->retrieveByCredentials(['public_id' => $id, 'status' => 'active']);
        if ($user === null) {
            return ApiResponse::error($request, 'identity_verification_failed', 'No se pudo verificar la solicitud.', 422);
        }
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ApiResponse::resource($request, 'users', $id, ['status' => 'active']);
    }

    public function me(Request $request, LocalOtp $identity): JsonResponse
    {
        $id = $identity->activeIdentity((int) Auth::guard('web')->id());
        abort_if($id === null, 401);

        return ApiResponse::resource($request, 'users', $id, ['status' => 'active']);
    }

    public function logout(Request $request, LocalOtp $identity): JsonResponse
    {
        try {
            $identity->recordSessionEnd((int) Auth::guard('web')->id());
        } finally {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::resource($request, 'sessions', 'current', ['ended' => true]);
    }
}
