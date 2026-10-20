<?php

namespace App\Service\Implements;

use App\Models\User;
use App\Service\Interfaces\IAuthService;
use Override;

class AuthService implements IAuthService
{
    #[Override]
    public function register(array $data): array
    {
        /** @var User $user */
        $user = User::create($data);
        $token = $user->createToken('auth_token')->plainTextToken;
        return [
            'name' => $user->name,
            'email' => $user->email,
            'token_type' => 'Bearer',
            'token' => $token,
        ];
    }
}
