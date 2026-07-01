<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $this->validate($request, [
            'name' => 'sometimes|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|max:100',
        ]);

        $email = strtolower(trim($request->input('email')));
        $plainToken = Str::random(60);
        $user = User::create([
            'name' => $request->input('name') ?: (strstr($email, '@', true) ?: $email),
            'email' => $email,
            'password' => Hash::make($request->input('password')),
            'api_token' => hash('sha256', $plainToken),
        ]);

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'data' => $this->userData($user, $plainToken),
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

        $plainToken = Str::random(60);
        $user->api_token = hash('sha256', $plainToken);
        $user->save();

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => $this->userData($user, $plainToken),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $this->authenticatedUser($request);
        $user->api_token = null;
        $user->save();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function forgotPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|max:150',
        ]);

        $email = strtolower(trim($request->input('email')));
        $user = User::where('email', $email)->first();

        if ($user) {
            $plainToken = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => hash('sha256', $plainToken),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $resetUrl = rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/')
                .'/?reset_token='.$plainToken.'&email='.urlencode($email);
            $this->sendResetLink($email, $resetUrl);
        }

        return response()->json([
            'message' => 'Jika email terdaftar, link reset kata sandi akan dikirim.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|max:150',
            'token' => 'required|string',
            'password' => 'required|string|min:8|max:100|confirmed',
        ]);

        $email = strtolower(trim($request->input('email')));
        $reset = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$reset || !hash_equals($reset->token, hash('sha256', $request->input('token')))) {
            return response()->json(['message' => 'Link reset kata sandi tidak valid.'], 422);
        }

        if (strtotime($reset->created_at) < time() - 3600) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['message' => 'Link reset kata sandi sudah kedaluwarsa.'], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'Akun tidak ditemukan.'], 422);
        }

        $user->password = Hash::make($request->input('password'));
        $user->api_token = null;
        $user->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'Kata sandi berhasil diganti.']);
    }

    private function userData(User $user, $token)
    {
        return [
            'id' => (int) $user->id,
            'name' => trim($user->name),
            'email' => strtolower(trim($user->email)),
            'token' => $token,
        ];
    }

    private function sendResetLink($email, $resetUrl)
    {
        $subject = 'Reset Kata Sandi SMARTQ';
        $message = "Halo,\n\nKlik link berikut untuk mengganti kata sandi SMARTQ:\n{$resetUrl}\n\nLink ini berlaku selama 1 jam.\nJika Anda tidak meminta reset, abaikan email ini.";
        $from = env('MAIL_FROM_ADDRESS', 'no-reply@smartq.local');
        $headers = "From: SMARTQ <{$from}>\r\nContent-Type: text/plain; charset=UTF-8";

        $sent = @mail($email, $subject, $message, $headers);

        if (!$sent || env('APP_DEBUG', false)) {
            $logPath = storage_path('logs/password-reset.log');
            file_put_contents($logPath, '['.date('Y-m-d H:i:s')."] {$email} {$resetUrl}\n", FILE_APPEND);
        }
    }
}
