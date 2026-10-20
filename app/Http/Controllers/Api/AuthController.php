<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
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
}
