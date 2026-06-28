<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;

class FrontendCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_teacher_can_delete_live_report_and_live_participant_answers()
    {
        $headers = $this->registerTeacher('hapus-live');
        $quiz = $this->createQuiz($headers, 'Kuis Laporan Live');

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/open', [], $headers)
            ->seeStatusCode(200);

        $liveParticipant = $this->joinParticipant($quiz['id'], 'Peserta Live');

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/start', [], $headers)
            ->seeStatusCode(200);
        $this->answerQuestion($quiz, $liveParticipant['id']);

        $answerId = DB::table('quiz_answers')
            ->where('participant_id', $liveParticipant['id'])
            ->value('id');

        $this->json('DELETE', '/api/quizzes/'.$quiz['id'].'/live-report', [], $headers)
            ->seeStatusCode(200)
            ->seeJson([
                'message' => 'Laporan kuis live berhasil dihapus.',
                'status' => 'draft',
            ]);

        $response = json_decode($this->response->getContent(), true);
        $this->assertSame($quiz['id'], $response['data']['id']);
        $this->assertCount(1, $response['data']['questions']);
        $this->notSeeInDatabase('quiz_participants', ['id' => $liveParticipant['id']]);
        $this->notSeeInDatabase('quiz_answers', ['id' => $answerId]);
        $this->seeInDatabase('quizzes', ['id' => $quiz['id'], 'status' => 'draft']);
        $this->seeInDatabase('quiz_questions', ['id' => $quiz['questions'][0]['id']]);
    }

    public function test_deleting_live_report_preserves_assignment_participants_and_answers()
    {
        $headers = $this->registerTeacher('simpan-tugas');
        $quiz = $this->createQuiz($headers, 'Kuis Campuran');
        $assignment = $this->createAssignment($headers, $quiz['id']);
        $assignmentParticipant = $this->joinParticipant(
            $quiz['id'],
            'Peserta Tugas',
            $assignment['id']
        );
        $this->answerQuestion($quiz, $assignmentParticipant['id']);

        $assignmentAnswerId = DB::table('quiz_answers')
            ->where('participant_id', $assignmentParticipant['id'])
            ->value('id');

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/open', [], $headers)
            ->seeStatusCode(200);
        $liveParticipant = $this->joinParticipant($quiz['id'], 'Peserta Live');

        $this->json('DELETE', '/api/quizzes/'.$quiz['id'].'/live-report', [], $headers)
            ->seeStatusCode(200);

        $this->notSeeInDatabase('quiz_participants', ['id' => $liveParticipant['id']]);
        $this->seeInDatabase('quiz_assignments', ['id' => $assignment['id']]);
        $this->seeInDatabase('quiz_participants', [
            'id' => $assignmentParticipant['id'],
            'assignment_id' => $assignment['id'],
        ]);
        $this->seeInDatabase('quiz_answers', ['id' => $assignmentAnswerId]);
    }

    public function test_teacher_can_delete_assignment_with_its_participants_and_answers()
    {
        $headers = $this->registerTeacher('hapus-tugas');
        $quiz = $this->createQuiz($headers, 'Kuis Tugas Dihapus');
        $assignment = $this->createAssignment($headers, $quiz['id']);
        $participant = $this->joinParticipant($quiz['id'], 'Peserta Tugas', $assignment['id']);
        $this->answerQuestion($quiz, $participant['id']);

        $answerId = DB::table('quiz_answers')
            ->where('participant_id', $participant['id'])
            ->value('id');

        $this->json('DELETE', '/api/assignments/'.$assignment['id'], [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['message' => 'Tugas kuis berhasil dihapus.']);

        $this->notSeeInDatabase('quiz_assignments', ['id' => $assignment['id']]);
        $this->notSeeInDatabase('quiz_participants', ['id' => $participant['id']]);
        $this->notSeeInDatabase('quiz_answers', ['id' => $answerId]);
        $this->seeInDatabase('quizzes', ['id' => $quiz['id']]);
        $this->seeInDatabase('quiz_questions', ['id' => $quiz['questions'][0]['id']]);
    }

    public function test_other_user_cannot_delete_assignment()
    {
        $ownerHeaders = $this->registerTeacher('pemilik-tugas');
        $quiz = $this->createQuiz($ownerHeaders, 'Kuis Milik Pengajar');
        $assignment = $this->createAssignment($ownerHeaders, $quiz['id']);
        $participant = $this->joinParticipant($quiz['id'], 'Peserta Aman', $assignment['id']);
        $otherHeaders = $this->registerTeacher('pengajar-lain');

        $this->json('DELETE', '/api/assignments/'.$assignment['id'], [], $otherHeaders)
            ->seeStatusCode(403);

        $this->seeInDatabase('quiz_assignments', ['id' => $assignment['id']]);
        $this->seeInDatabase('quiz_participants', ['id' => $participant['id']]);
    }

    public function test_deleting_missing_assignment_returns_json_404()
    {
        $headers = $this->registerTeacher('tugas-tidak-ada');

        $this->json('DELETE', '/api/assignments/999999999', [], $headers)
            ->seeStatusCode(404)
            ->seeJson(['message' => 'Data tugas tidak ditemukan.']);

        $this->assertStringContainsString(
            'application/json',
            $this->response->headers->get('Content-Type')
        );
    }

    public function test_assignment_index_returns_quiz_questions()
    {
        $headers = $this->registerTeacher('daftar-tugas');
        $quiz = $this->createQuiz($headers, 'Kuis Dengan Soal');
        $assignment = $this->createAssignment($headers, $quiz['id']);

        $this->json('GET', '/api/assignments', [], $headers)
            ->seeStatusCode(200);

        $response = json_decode($this->response->getContent(), true);
        $this->assertCount(1, $response['data']);
        $this->assertSame($assignment['id'], $response['data'][0]['id']);
        $this->assertSame($quiz['id'], $response['data'][0]['quiz']['id']);
        $this->assertCount(1, $response['data'][0]['quiz']['questions']);
        $this->assertGreaterThan(0, count($response['data'][0]['quiz']['questions']));
        $this->assertSame(
            $quiz['questions'][0]['id'],
            $response['data'][0]['quiz']['questions'][0]['id']
        );
    }

    public function test_live_leaderboard_excludes_assignment_participants()
    {
        $headers = $this->registerTeacher('leaderboard-live');
        $quiz = $this->createQuiz($headers, 'Kuis Leaderboard');
        $assignment = $this->createAssignment($headers, $quiz['id']);
        $assignmentParticipant = $this->joinParticipant(
            $quiz['id'],
            'Peserta Tugas',
            $assignment['id']
        );
        $this->answerQuestion($quiz, $assignmentParticipant['id']);

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/open', [], $headers)
            ->seeStatusCode(200);
        $liveParticipant = $this->joinParticipant($quiz['id'], 'Peserta Live');
        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/start', [], $headers)
            ->seeStatusCode(200);
        $this->answerQuestion($quiz, $liveParticipant['id']);

        $this->get('/api/quizzes/'.$quiz['id'].'/leaderboard')
            ->seeStatusCode(200);

        $response = json_decode($this->response->getContent(), true);
        $this->assertCount(1, $response['data']);
        $this->assertSame('Peserta Live', $response['data'][0]['name']);
        $this->assertNull($response['data'][0]['assignment_id']);
    }

    public function test_live_leaderboard_orders_score_desc_then_created_at_asc()
    {
        $headers = $this->registerTeacher('urutan-leaderboard');
        $quiz = $this->createQuiz($headers, 'Kuis Urutan Leaderboard');

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/open', [], $headers)
            ->seeStatusCode(200);

        $lowerScore = $this->joinParticipant($quiz['id'], 'Skor Rendah');
        $laterHighScore = $this->joinParticipant($quiz['id'], 'Skor Tinggi Terlambat');
        $earlierHighScore = $this->joinParticipant($quiz['id'], 'Skor Tinggi Awal');

        DB::table('quiz_participants')->where('id', $lowerScore['id'])->update([
            'score' => 1,
            'created_at' => '2026-01-01 10:00:00',
        ]);
        DB::table('quiz_participants')->where('id', $laterHighScore['id'])->update([
            'score' => 2,
            'created_at' => '2026-01-01 10:02:00',
        ]);
        DB::table('quiz_participants')->where('id', $earlierHighScore['id'])->update([
            'score' => 2,
            'created_at' => '2026-01-01 10:01:00',
        ]);

        $this->get('/api/quizzes/'.$quiz['id'].'/leaderboard')
            ->seeStatusCode(200);

        $response = json_decode($this->response->getContent(), true);
        $this->assertSame([
            'Skor Tinggi Awal',
            'Skor Tinggi Terlambat',
            'Skor Rendah',
        ], array_column($response['data'], 'name'));
    }

    public function test_protected_endpoint_without_token_returns_json_401()
    {
        $this->json('GET', '/api/quizzes')
            ->seeStatusCode(401)
            ->seeJson(['message' => 'Silakan masuk terlebih dahulu.']);

        $this->assertStringContainsString(
            'application/json',
            $this->response->headers->get('Content-Type')
        );
    }

    public function test_validation_error_returns_json_422()
    {
        $this->json('POST', '/api/auth/register', [
            'email' => 'bukan-email',
            'password' => 'pendek',
        ])->seeStatusCode(422)
            ->seeJson(['message' => 'Data yang diberikan tidak valid.']);

        $response = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString(
            'application/json',
            $this->response->headers->get('Content-Type')
        );
    }

    public function test_unknown_api_route_returns_json_404()
    {
        $this->json('GET', '/api/route-yang-tidak-ada')
            ->seeStatusCode(404)
            ->seeJson(['message' => 'Data tidak ditemukan.']);

        $this->assertStringContainsString(
            'application/json',
            $this->response->headers->get('Content-Type')
        );
    }

    public function test_finish_response_keeps_questions_for_frontend_state()
    {
        $headers = $this->registerTeacher('finish-questions');
        $quiz = $this->createQuiz($headers, 'Kuis Finish Lengkap');

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/finish', [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['status' => 'finished']);

        $response = json_decode($this->response->getContent(), true);
        $this->assertArrayHasKey('questions', $response['data']);
        $this->assertCount(1, $response['data']['questions']);
        $this->assertSame(
            $quiz['questions'][0]['id'],
            $response['data']['questions'][0]['id']
        );
    }

    private function registerTeacher($prefix)
    {
        $this->json('POST', '/api/auth/register', [
            'email' => $prefix.'+'.uniqid().'@smartq.test',
            'password' => 'password123',
        ])->seeStatusCode(201);

        $token = json_decode($this->response->getContent(), true)['data']['token'];

        return ['Authorization' => 'Bearer '.$token];
    }

    private function createQuiz(array $headers, $title)
    {
        $this->json('POST', '/api/quizzes', [
            'title' => $title,
            'category' => 'Pengujian',
            'questions' => [[
                'text' => 'Dua ditambah dua?',
                'answers' => ['Satu', 'Dua', 'Tiga', 'Empat'],
                'correct' => 'D',
                'timeLimit' => 10,
            ]],
        ], $headers)->seeStatusCode(201);

        return json_decode($this->response->getContent(), true)['data'];
    }

    private function createAssignment(array $headers, $quizId)
    {
        $this->json('POST', '/api/assignments', [
            'quiz_id' => $quizId,
            'deadline' => date('Y-m-d H:i:s', time() + 86400),
            'host' => 'Pengajar',
        ], $headers)->seeStatusCode(201);

        return json_decode($this->response->getContent(), true)['data'];
    }

    private function joinParticipant($quizId, $name, $assignmentId = null)
    {
        $payload = ['name' => $name];
        if ($assignmentId !== null) {
            $payload['assignment_id'] = $assignmentId;
        }

        $this->json('POST', '/api/quizzes/'.$quizId.'/participants', $payload)
            ->seeStatusCode(201);

        return json_decode($this->response->getContent(), true)['data']['participant'];
    }

    private function answerQuestion(array $quiz, $participantId)
    {
        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants/'.$participantId.'/answers', [
            'question_id' => $quiz['questions'][0]['id'],
            'selected_option' => 'D',
        ])->seeStatusCode(200);
    }
}
