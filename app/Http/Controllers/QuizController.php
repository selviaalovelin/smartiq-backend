<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAssignment;
use App\Models\QuizParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->authenticatedUser($request);
        return response()->json([
            'message' => 'Data kuis berhasil diambil.',
            'data' => $user->quizzes()->with('questions')->latest()->get(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);
        return response()->json([
            'message' => 'Data kuis berhasil diambil.',
            'data' => $quiz->load('questions'),
        ]);
    }

    public function byPin(Request $request, $pin)
    {
        if (!preg_match('/^\d{6}$/', $pin)) {
            throw ValidationException::withMessages([
                'pin' => ['PIN harus 6 digit angka.'],
            ]);
        }

        $assignmentId = $request->query('assignment_id');
        if ($assignmentId) {
            $assignment = QuizAssignment::with('quiz.questions')
                ->whereHas('quiz', fn ($query) => $query->where('pin', $pin))
                ->find($assignmentId);

            if (!$assignment) {
                abort(404, 'Tugas kuis tidak ditemukan.');
            }

            if ($assignment->deadline && strtotime($assignment->deadline) < time()) {
                abort(422, 'Batas waktu tugas sudah berakhir.');
            }

            return response()->json([
                'message' => 'Data kuis berhasil ditemukan.',
                'data' => $assignment->quiz,
            ]);
        }

        $quiz = Quiz::with('questions')->where('pin', $pin)->first();
        if (!$quiz) {
            abort(404, 'Kuis tidak ditemukan.');
        }

        if (!in_array($quiz->status, ['waiting', 'started'], true)) {
            abort(422, 'Kuis belum dibuka oleh pengajar atau sudah selesai.');
        }

        return response()->json([
            'message' => 'Data kuis berhasil ditemukan.',
            'data' => $quiz,
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
                'title' => trim($request->input('title')),
                'category' => $request->input('category') ? trim($request->input('category')) : null,
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
            $quiz->update([
                'title' => trim($request->input('title')),
                'category' => $request->input('category') ? trim($request->input('category')) : null,
            ]);

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
        $quiz = $this->ownedQuiz($request, $id);
        $quiz->delete();

        return response()->json(['message' => 'Kuis berhasil dihapus.']);
    }

    public function deleteLiveReport(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);

        DB::transaction(function () use ($quiz) {
            $quiz->participants()->whereNull('assignment_id')->delete();
            $quiz->update(['status' => 'draft']);
        });

        return response()->json([
            'message' => 'Laporan kuis live berhasil dihapus.',
            'data' => $quiz->fresh('questions'),
        ]);
    }

    public function join(Request $request, $id)
    {
        $quiz = Quiz::find($id);
        if (!$quiz) {
            abort(404, 'Kuis tidak ditemukan.');
        }

        $this->validate($request, ['name' => 'required|string|max:100']);

        $assignmentId = $request->input('assignment_id');
        if ($assignmentId) {
            $assignment = QuizAssignment::where('quiz_id', $quiz->id)->find($assignmentId);
            if (!$assignment) {
                abort(404, 'Tugas kuis tidak ditemukan.');
            }
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
            'score' => 0,
        ]);

        return response()->json([
            'message' => 'Peserta berhasil bergabung.',
            'data' => [
                'quiz' => $quiz->load('questions'),
                'participant' => $participant,
            ],
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

        return response()->json([
            'message' => 'Data peserta kuis berhasil diambil.',
            'data' => $participants,
        ]);
    }

    public function start(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);
        if ($quiz->questions()->count() === 0) {
            abort(422, 'Kuis belum memiliki soal.');
        }
        $quiz->update(['status' => 'started']);

        return response()->json([
            'message' => 'Kuis dimulai.',
            'data' => $quiz->fresh('questions'),
        ]);
    }

    public function open(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);
        if ($quiz->questions()->count() === 0) {
            abort(422, 'Kuis belum memiliki soal.');
        }
        $quiz->participants()->whereNull('assignment_id')->delete();
        $quiz->update(['status' => 'waiting']);

        return response()->json([
            'message' => 'Ruang kuis dibuka.',
            'data' => $quiz->fresh('questions'),
        ]);
    }

    public function finish(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($request, $id);
        $quiz->update(['status' => 'finished']);

        return response()->json([
            'message' => 'Kuis selesai.',
            'data' => $quiz->fresh('questions'),
        ]);
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
        $quiz = Quiz::find($id);
        if (!$quiz) {
            abort(404, 'Kuis tidak ditemukan.');
        }

        $participant = $quiz->participants()->find($participantId);
        if (!$participant) {
            abort(404, 'Peserta tidak ditemukan.');
        }

        if ($participant->assignment_id) {
            $assignment = QuizAssignment::find($participant->assignment_id);
            if (!$assignment) {
                abort(404, 'Tugas kuis tidak ditemukan.');
            }
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

        $question = $quiz->questions()->find($request->input('question_id'));
        if (!$question) {
            abort(404, 'Soal kuis tidak ditemukan.');
        }

        $isCorrect = $question->correct === $request->input('selected_option');

        QuizAnswer::updateOrCreate(
            ['participant_id' => $participant->id, 'quiz_question_id' => $question->id],
            ['selected_option' => $request->input('selected_option'), 'is_correct' => $isCorrect]
        );

        $participant->score = $participant->answers()->where('is_correct', true)->count();
        $participant->save();

        return response()->json([
            'message' => 'Jawaban berhasil disimpan.',
            'data' => [
                'is_correct' => $isCorrect,
                'score' => $participant->score,
            ],
        ]);
    }

    public function leaderboard($id)
    {
        $quiz = Quiz::withCount('questions')->find($id);
        if (!$quiz) {
            abort(404, 'Kuis tidak ditemukan.');
        }

        return response()->json([
            'message' => 'Data papan peringkat berhasil diambil.',
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
        if (!is_string($image) || empty($image)) {
            return true;
        }

        if (strlen($image) > 2800000) {
            return false;
        }

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp|gif);base64,/', $image)) {
            return false;
        }

        $payload = substr($image, strpos($image, ',') + 1);
        $decoded = base64_decode($payload, true);
        return $decoded !== false && strlen($decoded) <= 2 * 1024 * 1024;
    }

    private function saveQuestions(Quiz $quiz, array $questions)
    {
        foreach ($questions as $index => $question) {
            $text = trim($question['text'] ?? '');
            if (empty($text)) {
                throw ValidationException::withMessages([
                    "questions.{$index}.text" => ['Pertanyaan wajib diisi.'],
                ]);
            }

            $answers = array_values($question['answers'] ?? []);
            if (count($answers) !== 4 || in_array('', array_map('trim', $answers), true)) {
                throw ValidationException::withMessages([
                    "questions.{$index}.answers" => ['Setiap soal harus memiliki empat jawaban.'],
                ]);
            }

            if (!in_array($question['correct'] ?? '', ['A', 'B', 'C', 'D'], true)) {
                throw ValidationException::withMessages([
                    "questions.{$index}.correct" => ['Jawaban benar tidak valid.'],
                ]);
            }

            $image = $question['image'] ?? null;
            if ($image !== null && $image !== '' && !$this->isValidQuestionImage($image)) {
                throw ValidationException::withMessages([
                    "questions.{$index}.image" => ['Gambar soal harus berupa JPG, PNG, WEBP, atau GIF dengan ukuran maksimal 2 MB.'],
                ]);
            }

            $timeLimit = (int) ($question['timeLimit'] ?? 10);
            if ($timeLimit < 5 || $timeLimit > 300) {
                throw ValidationException::withMessages([
                    "questions.{$index}.timeLimit" => ['Batas waktu soal harus 5 sampai 300 detik.'],
                ]);
            }

            $quiz->questions()->create([
                'text' => $text,
                'image' => ($image !== '') ? $image : null,
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
    }
}
