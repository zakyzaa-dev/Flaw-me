<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Service\Interfaces\IAuthService;

class AuthController extends Controller
{
    protected IAuthService $_authService;

    public function __construct(IAuthService $service)
    {
        $this->_authService = $service;
    }

    public function register(RegisterRequest $regist)
    {
        $dataCreds = $regist->validated();
        $register = $this->_authService->register($dataCreds);
        return response()->json([
            'Message' => 'Register successful',
            'data' => $register,
        ],201);
    }

    public function login(LoginRequest $login)
    {
        $dataLogin = $login->all();
        $login = $this->_authService->login($dataLogin);

        return response()->json([
            'message' => 'Login success',
            'data' => $login
        ], 200);
    }

    public function resetPassword(ResetPasswordRequest $reset)
    {
        $dataReset = $reset->validated();
        $reset = $this->_authService->resetPassword($dataReset);

        return response()->json([
            'message' => 'Reset password success',
            'data' => $reset
        ], 201);
    }
}
