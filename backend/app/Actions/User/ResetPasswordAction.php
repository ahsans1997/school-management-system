<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Str;

class ResetPasswordAction
{
    /**
     * Reset the user's password. When none is supplied, a temporary one is
     * generated and returned so the admin can share it out of band.
     *
     * @return array{password: string|null} The generated password, or null when one was supplied.
     */
    public function execute(User $user, ?string $password = null): array
    {
        $generated = $password === null;
        $newPassword = $password ?? Str::password(12);

        $user->update(['password' => $newPassword]); // hashed by the model cast

        return ['password' => $generated ? $newPassword : null];
    }
}
