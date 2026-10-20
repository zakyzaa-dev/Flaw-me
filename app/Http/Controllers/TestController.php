<?php

namespace App\Http\Controllers;

use App\Models\User;

class TestController extends Controller
{
    public function getAllUsers()
    {
        $users = User::all();
        return response()->json([
            'message' => 'Success get all users',
            'data' => $users,
        ], 200);
    }
}
