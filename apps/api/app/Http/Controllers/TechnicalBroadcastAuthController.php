<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TechnicalBroadcastAuthController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/\A[0-9]+\.[0-9]+\z/'],
            'channel_name' => ['required', 'string', 'max:100'],
        ]);

        abort_unless($data['channel_name'] === 'private-technical.v1', 403);
        $secret = (string) config('broadcasting.connections.reverb.secret');
        abort_if($secret === '', 503);

        return response()->json(['auth' => config('broadcasting.connections.reverb.key').':'.
            hash_hmac('sha256', $data['socket_id'].':'.$data['channel_name'], $secret)]);
    }
}
