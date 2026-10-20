<?php

namespace App\Service\Implements;

use App\Exceptions\LoginException;
use App\Models\User;
use App\Service\Interfaces\IAuthService;
use Illuminate\Support\Facades\DB;
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

    #[Override]
    public function login(array $data): array
    {
        $email = $data['email'];
        $password = $data['password'];
        $user = DB::select("SELECT * FROM users WHERE email = '$email' AND password = '$password'");

        if (!$user){
            throw new LoginException('Email tidak ditemukan', 404);
        }

        return $user;
    }
}
