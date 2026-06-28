<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
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
        $user = $this->authenticatedUser($request);
        $quiz = Quiz::find($id);

        if (!$quiz) {
            abort(404, 'Data kuis tidak ditemukan.');
        }

        if ((int) $quiz->user_id !== (int) $user->id) {
            abort(403, 'Anda tidak memiliki akses ke kuis ini.');
        }

        return $quiz;
    }
}
