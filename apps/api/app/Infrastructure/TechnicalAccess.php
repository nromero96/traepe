<?php

namespace App\Infrastructure;

use Illuminate\Http\Request;

final class TechnicalAccess
{
    public function allows(Request $request): bool
    {
        $password = (string) config('technical.password');

        return app()->environment('local')
            && strlen($password) >= 32
            && $request->getUser() === 'technical'
            && hash_equals($password, (string) $request->getPassword());
    }
}
