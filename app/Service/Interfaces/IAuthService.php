<?php

namespace App\Service\Interfaces;

use App\Models\User;

interface IAuthService
{
    public function register(array $data): array;
    public function login(array $data): array;
    public function resetPassword(User $user,array $data): array;
}
