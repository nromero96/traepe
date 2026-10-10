<?php

namespace App\Modules\Identity\Infrastructure;

use Illuminate\Foundation\Auth\User;

/**
 * @property string $status
 * @property string|null $public_id
 */
final class IdentityUser extends User
{
    protected $table = 'users';

    protected $guarded = ['id'];

    protected $hidden = ['id', 'password', 'remember_token', 'phone_key', 'phone_ciphertext', 'email', 'name'];
}
