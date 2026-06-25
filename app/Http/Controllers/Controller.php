<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function authenticatedUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            abort(401, 'Silakan masuk terlebih dahulu.');
        }

        $user = User::where('api_token', hash('sha256', $token))->first();
        if (!$user) {
            abort(401, 'Sesi tidak valid. Silakan masuk kembali.');
        }

        return $user;
    }

    protected function ownedQuiz(Request $request, $id)
    {
        return $this->authenticatedUser($request)->quizzes()->findOrFail($id);
    }
}
