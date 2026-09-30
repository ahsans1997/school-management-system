<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Auth\TokenIssuer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    /**
     * Verify credentials and issue tokens. Credentials are checked
     * server-side; a disabled account is rejected.
     *
     * @return array{user: User, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws ValidationException
     */
    public function execute(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been disabled.'],
            ]);
        }

        return ['user' => $user, ...$this->tokens->issue($user)];
    }
}
