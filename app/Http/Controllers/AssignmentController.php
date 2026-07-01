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

        $quiz = $user->quizzes()->whereKey($request->input('quiz_id'))->first();
        if (!$quiz) {
            abort(404);
        }

        if ($quiz->questions()->count() === 0) {
            abort(422, 'Kuis belum memiliki soal.');
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

    public function participants(Request $request, $id)
    {
        $user = $this->authenticatedUser($request);
        $assignment = QuizAssignment::with('quiz')
            ->whereHas('quiz', fn ($query) => $query->where('user_id', $user->id))
            ->findOrFail($id);

        $totalQuestions = $assignment->quiz->questions()->count();
        $participants = $assignment->participants()
            ->with('answers')
            ->latest()
            ->get()
            ->map(function ($participant) use ($totalQuestions) {
                $answeredCount = $participant->answers->count();
                $correctCount = $participant->answers->where('is_correct', true)->count();
                $wrongCount = $participant->answers->where('is_correct', false)->count();

                return [
                    'id' => $participant->id,
                    'quiz_id' => $participant->quiz_id,
                    'assignment_id' => $participant->assignment_id,
                    'name' => $participant->name,
                    'score' => $participant->score,
                    'answered_count' => $answeredCount,
                    'correct_count' => $correctCount,
                    'wrong_count' => $wrongCount,
                    'total_questions' => $totalQuestions,
                    'progress_percent' => $totalQuestions ? round(($answeredCount / $totalQuestions) * 100) : 0,
                    'created_at' => $participant->created_at,
                    'updated_at' => $participant->updated_at,
                ];
            });

        return response()->json(['data' => $participants]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->authenticatedUser($request);
        $assignment = QuizAssignment::whereHas('quiz', fn ($query) => $query->where('user_id', $user->id))
            ->findOrFail($id);

        $assignment->participants()->delete();
        $assignment->delete();

        return response()->json(['message' => 'Laporan tugas berhasil dihapus.']);
    }
}
