<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{user: User, token: string}
     */
    public function login(string $username, string $password, ?string $deviceName = null): array
    {
        $user = User::where('username', $username)->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $password])) {
            throw ValidationException::withMessages([
                'username' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['This account has been deactivated.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName ?: 'api-token')->plainTextToken;

        return [
            'user' => $user->load(['branch', 'roles.permissions']),
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
