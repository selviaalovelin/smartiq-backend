<?php

namespace App\Http\Controllers;

use App\Models\QuizAssignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index()
    {
        return response()->json(['data' => QuizAssignment::with('quiz')->latest()->get()]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'quiz_id' => 'required|exists:quizzes,id',
            'deadline' => 'required|date',
            'host' => 'nullable|string|max:100',
        ]);

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
