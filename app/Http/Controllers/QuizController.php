<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizParticipant;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Quiz::with('questions')->latest()->get()]);
    }

    public function show($id)
    {
        return response()->json(['data' => Quiz::with('questions')->findOrFail($id)]);
    }

    public function byPin($pin)
    {
        return response()->json(['data' => Quiz::with('questions')->where('pin', $pin)->firstOrFail()]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
        ]);

        $quiz = Quiz::create([
            'title' => $request->input('title'),
            'category' => $request->input('category'),
            'pin' => $this->makePin(),
        ]);

        return response()->json([
            'message' => 'Kuis berhasil dibuat.',
            'data' => $quiz->load('questions'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $this->validate($request, [
            'title' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
            'questions' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $quiz) {
            $quiz->update($request->only(['title', 'category']));

            if (!$request->has('questions')) {
                return;
            }

            $quiz->questions()->delete();
            foreach ($request->input('questions', []) as $index => $question) {
                $answers = array_values($question['answers'] ?? []);
                if (count($answers) !== 4 || in_array('', array_map('trim', $answers), true)) {
                    abort(422, 'Setiap soal harus memiliki empat jawaban.');
                }

                if (!in_array($question['correct'] ?? '', ['A', 'B', 'C', 'D'], true)) {
                    abort(422, 'Jawaban benar tidak valid.');
                }

                $quiz->questions()->create([
                    'text' => trim($question['text'] ?? ''),
                    'image' => $question['image'] ?? null,
                    'answers' => $answers,
                    'correct' => $question['correct'],
                    'time_limit' => max(5, min(300, (int) ($question['timeLimit'] ?? 10))),
                    'position' => $index + 1,
                ]);
            }
        });

        return response()->json([
            'message' => 'Kuis dan soal berhasil disimpan.',
            'data' => $quiz->fresh('questions'),
        ]);
    }

    public function destroy($id)
    {
        Quiz::findOrFail($id)->delete();

        return response()->json(['message' => 'Kuis berhasil dihapus.']);
    }

    public function join(Request $request, $id)
    {
        $quiz = Quiz::with('questions')->findOrFail($id);
        $this->validate($request, ['name' => 'required|string|max:100']);

        $participant = QuizParticipant::create([
            'quiz_id' => $quiz->id,
            'name' => trim($request->input('name')),
        ]);

        return response()->json([
            'message' => 'Peserta berhasil bergabung.',
            'data' => ['quiz' => $quiz, 'participant' => $participant],
        ], 201);
    }

    public function participants($id)
    {
        $quiz = Quiz::findOrFail($id);
        return response()->json(['data' => $quiz->participants()->latest()->get()]);
    }

    public function answer(Request $request, $id, $participantId)
    {
        $quiz = Quiz::findOrFail($id);
        $participant = $quiz->participants()->findOrFail($participantId);

        $this->validate($request, [
            'question_id' => 'required|integer',
            'selected_option' => 'required|in:A,B,C,D',
        ]);

        $question = $quiz->questions()->findOrFail($request->input('question_id'));
        $isCorrect = $question->correct === $request->input('selected_option');

        QuizAnswer::updateOrCreate(
            ['participant_id' => $participant->id, 'quiz_question_id' => $question->id],
            ['selected_option' => $request->input('selected_option'), 'is_correct' => $isCorrect]
        );

        $participant->score = $participant->answers()->where('is_correct', true)->count();
        $participant->save();

        return response()->json([
            'data' => ['is_correct' => $isCorrect, 'score' => $participant->score],
        ]);
    }

    public function leaderboard($id)
    {
        $quiz = Quiz::findOrFail($id);
        return response()->json([
            'data' => $quiz->participants()->orderByDesc('score')->orderBy('created_at')->get(),
        ]);
    }

    private function makePin()
    {
        do {
            $pin = (string) random_int(100000, 999999);
        } while (Quiz::where('pin', $pin)->exists());

        return $pin;
    }
}
