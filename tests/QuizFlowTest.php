<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;

class QuizFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_teacher_can_reset_password_with_valid_token()
    {
        $email = 'reset+'.uniqid().'@smartq.test';

        $this->json('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $plainToken = 'reset-token-uji';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => hash('sha256', $plainToken),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $this->json('POST', '/api/auth/reset-password', [
            'email' => $email,
            'token' => $plainToken,
            'password' => 'password456',
            'password_confirmation' => 'password456',
        ])->seeStatusCode(200);

        $this->json('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'password456',
        ])->seeStatusCode(200);
    }

    public function test_reset_password_with_expired_token_fails()
    {
        $email = 'expired_reset+'.uniqid().'@smartq.test';

        $this->json('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $plainToken = 'reset-token-expired';
        // Create an expired token (created 2 hours ago)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => hash('sha256', $plainToken),
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ]
        );

        $this->json('POST', '/api/auth/reset-password', [
            'email' => $email,
            'token' => $plainToken,
            'password' => 'password456',
            'password_confirmation' => 'password456',
        ])->seeStatusCode(422)
          ->seeJson([
              'message' => 'Link reset kata sandi sudah kedaluwarsa.'
          ]);

        // Verify it was deleted from the database
        $this->assertNull(DB::table('password_reset_tokens')->where('email', $email)->first());
    }

    public function test_new_quiz_with_invalid_questions_is_not_saved()
    {
        $email = 'pengajar-draft+'.uniqid().'@smartq.test';

        $this->json('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $token = json_decode($this->response->getContent(), true)['data']['token'];
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->json('POST', '/api/quizzes', [
            'title' => 'Kuis Belum Valid',
            'category' => 'Pengujian',
            'questions' => [[
                'text' => '',
                'answers' => ['A', 'B', 'C', 'D'],
                'correct' => 'A',
                'timeLimit' => 10,
            ]],
        ], $headers)->seeStatusCode(422);

        $this->json('GET', '/api/quizzes', [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['data' => []]);
    }

    public function test_assignment_quiz_can_be_answered_without_live_start()
    {
        $email = 'pengajar-tugas+'.uniqid().'@smartq.test';

        $this->json('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $token = json_decode($this->response->getContent(), true)['data']['token'];
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->json('POST', '/api/quizzes', [
            'title' => 'Kuis Tugas',
            'category' => 'Pengujian',
            'questions' => [[
                'text' => 'Jawaban benar?',
                'answers' => ['A', 'B', 'C', 'D'],
                'correct' => 'C',
                'timeLimit' => 10,
            ]],
        ], $headers)->seeStatusCode(201);

        $quiz = json_decode($this->response->getContent(), true)['data'];
        $question = $quiz['questions'][0];

        $this->json('POST', '/api/assignments', [
            'quiz_id' => $quiz['id'],
            'deadline' => date('Y-m-d H:i:s', time() + 86400),
            'host' => 'Pengajar',
        ], $headers)->seeStatusCode(201);

        $assignment = json_decode($this->response->getContent(), true)['data'];

        $this->get('/api/quizzes/pin/'.$quiz['pin'].'?assignment_id='.$assignment['id'])
            ->seeStatusCode(200);

        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants', [
            'name' => 'Peserta Tugas',
            'assignment_id' => $assignment['id'],
        ])->seeStatusCode(201);

        $participant = json_decode($this->response->getContent(), true)['data']['participant'];

        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants/'.$participant['id'].'/answers', [
            'question_id' => $question['id'],
            'selected_option' => 'C',
        ])->seeStatusCode(200)
            ->seeJson(['is_correct' => true, 'score' => 1]);

        $this->json('GET', '/api/quizzes/'.$quiz['id'].'/participants', [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['data' => []]);

        $this->json('GET', '/api/assignments/'.$assignment['id'].'/participants', [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['name' => 'Peserta Tugas', 'score' => 1]);

        $this->get('/api/quizzes/'.$quiz['id'].'/leaderboard')
            ->seeStatusCode(200)
            ->seeJson(['data' => []]);
    }

    public function test_complete_live_quiz_flow()
    {
        $email = 'pengajar+'.uniqid().'@smartq.test';

        $this->json('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $token = json_decode($this->response->getContent(), true)['data']['token'];
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->json('POST', '/api/quizzes', [
            'title' => 'Kuis Integrasi',
            'category' => 'Pengujian',
        ], $headers);
        $this->seeStatusCode(201);

        $quiz = json_decode($this->response->getContent(), true)['data'];

        $this->json('POST', '/api/auth/register', [
            'email' => 'pengajar-lain+'.uniqid().'@smartq.test',
            'password' => 'password123',
        ])->seeStatusCode(201);
        $otherToken = json_decode($this->response->getContent(), true)['data']['token'];
        $this->json('GET', '/api/quizzes', [], ['Authorization' => 'Bearer '.$otherToken])
            ->seeStatusCode(200)
            ->seeJson(['data' => []]);

        $this->json('PUT', '/api/quizzes/'.$quiz['id'], [
            'title' => $quiz['title'],
            'category' => $quiz['category'],
            'questions' => [[
                'text' => 'Dua ditambah dua?',
                'answers' => ['Satu', 'Dua', 'Tiga', 'Empat'],
                'correct' => 'D',
                'timeLimit' => 10,
            ]],
        ], $headers)->seeStatusCode(200);

        $question = json_decode($this->response->getContent(), true)['data']['questions'][0];

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/open', [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['status' => 'waiting']);

        $this->get('/api/quizzes/pin/'.$quiz['pin'])
            ->seeStatusCode(200);

        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants', [
            'name' => 'Peserta Uji',
        ])->seeStatusCode(201);

        $participant = json_decode($this->response->getContent(), true)['data']['participant'];

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/start', [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['status' => 'started']);

        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants/'.$participant['id'].'/answers', [
            'question_id' => $question['id'],
            'selected_option' => 'D',
        ])->seeStatusCode(200)
            ->seeJson(['is_correct' => true, 'score' => 1]);

        $this->get('/api/quizzes/'.$quiz['id'].'/leaderboard')
            ->seeStatusCode(200)
            ->seeJson(['name' => 'Peserta Uji', 'score' => 1]);

        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/finish', [], $headers)
            ->seeStatusCode(200)
            ->seeJson(['status' => 'finished']);

        $this->json('POST', '/api/auth/logout', [], $headers)
            ->seeStatusCode(200);
        $this->json('GET', '/api/quizzes', [], $headers)
            ->seeStatusCode(401);
    }

    public function test_open_or_start_quiz_without_questions_fails()
    {
        $email = 'pengajar-kosong+'.uniqid().'@smartq.test';

        // Register teacher
        $this->json('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $token = json_decode($this->response->getContent(), true)['data']['token'];
        $headers = ['Authorization' => 'Bearer '.$token];

        // Create quiz without questions
        $this->json('POST', '/api/quizzes', [
            'title' => 'Kuis Tanpa Soal',
            'category' => 'Pengujian',
        ], $headers)->seeStatusCode(201);

        $quiz = json_decode($this->response->getContent(), true)['data'];

        // Try to open quiz
        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/open', [], $headers)
            ->seeStatusCode(422)
            ->seeJson(['message' => 'Kuis belum memiliki soal.']);

        // Try to start quiz (fails because status is 'draft')
        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/start', [], $headers)
            ->seeStatusCode(422)
            ->seeJson(['message' => 'Kuis harus dalam status menunggu (waiting) sebelum dimulai.']);
    }

    public function test_participant_quiz_flow_and_validation()
    {
        $email = 'pengajar-flow+'.uniqid().'@smartq.test';

        // Register teacher
        $this->json('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $token = json_decode($this->response->getContent(), true)['data']['token'];
        $headers = ['Authorization' => 'Bearer '.$token];

        // Create quiz with 1 question
        $this->json('POST', '/api/quizzes', [
            'title' => 'Kuis Flow Uji',
            'category' => 'Uji',
            'questions' => [[
                'text' => 'Berapa satu tambah satu?',
                'answers' => ['Satu', 'Dua', 'Tiga', 'Empat'],
                'correct' => 'B',
                'timeLimit' => 10,
            ]],
        ], $headers)->seeStatusCode(201);

        $quiz = json_decode($this->response->getContent(), true)['data'];
        $question = $quiz['questions'][0];

        // Open quiz room
        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/open', [], $headers)->seeStatusCode(200);

        // 1. Join with empty name (should fail)
        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants', [
            'name' => '   ',
        ])->seeStatusCode(422)
          ->seeJson(['message' => 'Nama tidak boleh kosong.']);

        // 2. Join participant 1
        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants', [
            'name' => 'Peserta Satu',
        ])->seeStatusCode(201);

        $participant1 = json_decode($this->response->getContent(), true)['data']['participant'];

        // 3. Join with duplicate name (should fail)
        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants', [
            'name' => 'Peserta Satu',
        ])->seeStatusCode(422)
          ->seeJson(['message' => 'Nama ini sudah digunakan oleh peserta lain.']);

        // Start the quiz
        $this->json('PUT', '/api/quizzes/'.$quiz['id'].'/start', [], $headers)->seeStatusCode(200);

        // 4. Submit answer first time
        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants/'.$participant1['id'].'/answers', [
            'question_id' => $question['id'],
            'selected_option' => 'B',
        ])->seeStatusCode(200)
          ->seeJson(['is_correct' => true, 'score' => 1]);

        // 5. Submit answer second time (should fail)
        $this->json('POST', '/api/quizzes/'.$quiz['id'].'/participants/'.$participant1['id'].'/answers', [
            'question_id' => $question['id'],
            'selected_option' => 'A',
        ])->seeStatusCode(422)
          ->seeJson(['message' => 'Anda sudah menjawab pertanyaan ini.']);
    }
}
