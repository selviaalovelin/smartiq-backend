<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $email = strtolower(trim($request->input('email')));
        $user = User::create([
            'name' => strstr($email, '@', true) ?: $email,
            'email' => $email,
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'data' => $this->userData($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower(trim($request->input('email'))))->first();
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Email atau kata sandi salah.'], 422);
        }

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => $this->userData($user),
        ]);
    }

    private function userData(User $user)
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
