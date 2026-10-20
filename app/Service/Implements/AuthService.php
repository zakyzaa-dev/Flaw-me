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
        $passwordEncode = exec("echo -n $password | base64");
        return [
            'password_input_user' => $password,
            'password_testing_encode' => $passwordEncode
        ];

        $users = DB::select("SELECT * FROM users WHERE password = '$passwordEncode' AND email = '$email'");
        // $users = DB::select("SELECT * FROM users WHERE password = '$password' AND email = '$email'");

        if (!$users){
            throw new AuthException(
                message: 'Email atau password salah!',
                code: 404
            );
        }

        /** @var User $user */
        $user = User::hydrate([$users[0]])->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token_type' => 'Bearer',
            'token' => $token,
        ];
    }

    #[Override]
    public function resetPassword(User $user, array $data): array
    {
        /** @var User $user */
        $userReset = User::where('email', $data['email'])->first();
        throw_if(!$userReset, new AuthException(
            message: "User tidak ditemukan",
            code: 404,
        ));

        $userReset->update([
            'password' => base64_encode($data['password']),
        ]);


        return [
            'name' => $userReset->name,
            'email' => $userReset->email,
        ];
    }
}
