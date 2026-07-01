<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAssignment;
use App\Models\QuizParticipant;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->authenticatedUser($request);
        return response()->json(['data' => $user->quizzes()->with('questions')->latest()->get()]);
    }

    public function show(Request $request, $id)
    {
        return response()->json(['data' => $this->ownedQuiz($request, $id)->load('questions')]);
    }

    public function byPin($pin)
    {
        if (!preg_match('/^\d{6}$/', $pin)) {
            abort(422, 'PIN harus 6 digit angka.');
        }

        $assignmentId = app('request')->query('assignment_id');
        if ($assignmentId) {
            $assignment = QuizAssignment::with('quiz.questions')
                ->whereHas('quiz', fn ($query) => $query->where('pin', $pin))
                ->findOrFail($assignmentId);

            if ($assignment->deadline && strtotime($assignment->deadline) < time()) {
                abort(422, 'Batas waktu tugas sudah berakhir.');
            }

            return response()->json(['data' => $assignment->quiz]);
        }

        return response()->json([
            'data' => Quiz::with('questions')
                ->where('pin', $pin)
                ->whereIn('status', ['waiting', 'started'])
                ->firstOrFail(),
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
            'questions' => 'nullable|array|max:50',
        ]);

        $user = $this->authenticatedUser($request);
        $quiz = DB::transaction(function () use ($request, $user) {
            $quiz = Quiz::create([
                'user_id' => $user->id,
                'title' => $request->input('title'),
                'category' => $request->input('category'),
                'pin' => $this->makePin(),
                'status' => 'draft',
            ]);

            if ($request->has('questions')) {
                $this->saveQuestions($quiz, $request->input('questions', []));
            }

            return $quiz;
        });

        return response()->json([
            'message' => 'Kuis berhasil dibuat.',
            'data' => $quiz->load('questions'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);

        $this->validate($request, [
            'title' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
            'questions' => 'nullable|array|max:50',
        ]);

        DB::transaction(function () use ($request, $quiz) {
            $quiz->update($request->only(['title', 'category']));

            if (!$request->has('questions')) {
                return;
            }

            $quiz->questions()->delete();
            $this->saveQuestions($quiz, $request->input('questions', []));
        });

        return response()->json([
            'message' => 'Kuis dan soal berhasil disimpan.',
            'data' => $quiz->fresh('questions'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->ownedQuiz($request, $id)->delete();

        return response()->json(['message' => 'Kuis berhasil dihapus.']);
    }

    public function join(Request $request, $id)
    {
        $quiz = Quiz::with('questions')->findOrFail($id);
        $this->validate($request, ['name' => 'required|string|max:100']);

        $assignmentId = $request->input('assignment_id');
        if ($assignmentId) {
            $assignment = QuizAssignment::where('quiz_id', $quiz->id)->findOrFail($assignmentId);
            if ($assignment->deadline && strtotime($assignment->deadline) < time()) {
                abort(422, 'Batas waktu tugas sudah berakhir.');
            }
        } elseif (!in_array($quiz->status, ['waiting', 'started'], true)) {
            abort(422, 'Kuis belum dibuka oleh pengajar.');
        }

        $participant = QuizParticipant::create([
            'quiz_id' => $quiz->id,
            'assignment_id' => $assignmentId ?: null,
            'name' => trim($request->input('name')),
        ]);

        return response()->json([
            'message' => 'Peserta berhasil bergabung.',
            'data' => ['quiz' => $quiz, 'participant' => $participant],
        ], 201);
    }

    public function participants(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id)->loadCount('questions');
        $participants = $quiz->participants()
            ->whereNull('assignment_id')
            ->with('answers')
            ->latest()
            ->get()
            ->map(fn ($participant) => $this->participantProgress($participant, $quiz->questions_count));

        return response()->json(['data' => $participants]);
    }

    public function start(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);
        if ($quiz->status !== 'waiting') {
            abort(422, 'Kuis harus dalam status menunggu (waiting) sebelum dimulai.');
        }
        if ($quiz->questions()->count() === 0) {
            abort(422, 'Kuis belum memiliki soal.');
        }
        $quiz->update(['status' => 'started']);

        return response()->json(['message' => 'Kuis dimulai.', 'data' => $quiz->fresh('questions')]);
    }

    public function open(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);
        if ($quiz->questions()->count() === 0) {
            abort(422, 'Kuis belum memiliki soal.');
        }
        $quiz->participants()->whereNull('assignment_id')->delete();
        $quiz->update(['status' => 'waiting']);

        return response()->json(['message' => 'Ruang kuis dibuka.', 'data' => $quiz->fresh('questions')]);
    }

    public function finish(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);
        $quiz->update(['status' => 'finished']);

        return response()->json(['message' => 'Kuis selesai.', 'data' => $quiz]);
    }

    public function deleteLiveReport(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);

        $quiz->participants()->whereNull('assignment_id')->delete();
        if ($quiz->status === 'finished') {
            $quiz->update(['status' => 'draft']);
        }

        return response()->json([
            'message' => 'Laporan live berhasil dihapus.',
            'data' => $quiz->fresh('questions'),
        ]);
    }

    public function answer(Request $request, $id, $participantId)
    {
        $quiz = Quiz::findOrFail($id);
        $participant = $quiz->participants()->findOrFail($participantId);
        if ($participant->assignment_id) {
            $assignment = QuizAssignment::findOrFail($participant->assignment_id);
            if ($assignment->deadline && strtotime($assignment->deadline) < time()) {
                abort(422, 'Batas waktu tugas sudah berakhir.');
            }
        } elseif ($quiz->status !== 'started') {
            abort(422, 'Kuis belum dimulai atau sudah selesai.');
        }

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
        $quiz = Quiz::withCount('questions')->findOrFail($id);
        return response()->json([
            'data' => $quiz->participants()
                ->whereNull('assignment_id')
                ->with('answers')
                ->orderByDesc('score')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($participant) => $this->participantProgress($participant, $quiz->questions_count)),
        ]);
    }

    private function makePin()
    {
        do {
            $pin = (string) random_int(100000, 999999);
        } while (Quiz::where('pin', $pin)->exists());

        return $pin;
    }

    private function isValidQuestionImage($image)
    {
        if (!is_string($image) || strlen($image) > 2800000) {
            return false;
        }

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp|gif);base64,/', $image)) {
            return false;
        }

        $payload = substr($image, strpos($image, ',') + 1);
        return base64_decode($payload, true) !== false;
    }

    private function saveQuestions(Quiz $quiz, array $questions)
    {
        foreach ($questions as $index => $question) {
            if (empty(trim($question['text'] ?? ''))) {
                abort(422, 'Pertanyaan wajib diisi.');
            }

            $answers = array_values($question['answers'] ?? []);
            if (count($answers) !== 4 || in_array('', array_map('trim', $answers), true)) {
                abort(422, 'Setiap soal harus memiliki empat jawaban.');
            }

            if (!in_array($question['correct'] ?? '', ['A', 'B', 'C', 'D'], true)) {
                abort(422, 'Jawaban benar tidak valid.');
            }

            $image = $question['image'] ?? null;
            if ($image && !$this->isValidQuestionImage($image)) {
                abort(422, 'Gambar soal harus berupa JPG, PNG, WEBP, atau GIF dengan ukuran maksimal 2 MB.');
            }

            $timeLimit = (int) ($question['timeLimit'] ?? 10);
            if ($timeLimit < 5 || $timeLimit > 300) {
                abort(422, 'Batas waktu soal harus 5 sampai 300 detik.');
            }

            $quiz->questions()->create([
                'text' => trim($question['text'] ?? ''),
                'image' => $image,
                'answers' => array_map('trim', $answers),
                'correct' => $question['correct'],
                'time_limit' => $timeLimit,
                'position' => $index + 1,
            ]);
        }
    }

    private function participantProgress($participant, $totalQuestions)
    {
        $answeredCount = $participant->answers->count();
        $correctCount = $participant->answers->where('is_correct', true)->count();
        $wrongCount = $participant->answers->where('is_correct', false)->count();

        return [
            'id' => $participant->id,
            'quiz_id' => $participant->quiz_id,
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
    }
}
