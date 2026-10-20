<?php

namespace App\Service\Implements;

use App\Exceptions\AuthException;
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
        $data['password'] = base64_encode($data['password']);
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

        $users = DB::select("SELECT * FROM users WHERE password = '$password' AND email = '$email'");

        if (!$users){
            throw new AuthException(
                message: 'Email atau password salah!',
                code: 404
            );
        }

        /** @var User $user */
        $user = $users[0];
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token_type' => 'Bearer',
            'token' => $token,
        ];
    }

    #[Override]
    public function resetPassword(array $data): array
    {
        // SECURE VERSION
        /** @var User $user */
        $user = User::where('email', $data['email'])->first();


        // VULN VERSION
        // /** @var User $user */
        // $user = User::where('email', $data['email'])->first();
        // throw_if(!$user, new AuthException(
        //     message: "User tidak ditemukan",
        //     code: 404,
        // ));

        // $user->update([
        //     'password' => base64_encode($data['password']),
        // ]);

        // $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'token_type' => 'Bearer',
            // 'token' => $token
        ];
    }
}
