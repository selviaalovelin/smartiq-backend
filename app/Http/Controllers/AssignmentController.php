<?php

namespace App\Http\Controllers;

use App\Models\QuizAssignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->authenticatedUser($request);
        return response()->json([
            'data' => QuizAssignment::with('quiz')
                ->whereHas('quiz', fn ($query) => $query->where('user_id', $user->id))
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authenticatedUser($request);
        $this->validate($request, [
            'quiz_id' => 'required|exists:quizzes,id',
            'deadline' => 'required|date|after:now',
            'host' => 'nullable|string|max:100',
        ]);
        if (!$user->quizzes()->whereKey($request->input('quiz_id'))->exists()) {
            abort(404);
        }

        $assignment = QuizAssignment::create([
            'quiz_id' => $request->input('quiz_id'),
            'deadline' => $request->input('deadline'),
            'host' => $request->input('host') ?: 'Pengajar',
        ]);

        return response()->json([
            'message' => 'Tugas kuis berhasil dibuat.',
            'data' => $assignment->load('quiz'),
        ], 201);
    }
}
