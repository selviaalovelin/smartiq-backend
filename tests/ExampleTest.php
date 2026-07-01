<?php

namespace Tests;

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_that_base_endpoint_returns_a_successful_response()
    {
        $this->get('/');

        $this->seeStatusCode(200)
            ->seeJson([
                'name' => 'SMARTIQ Backend',
                'framework' => $this->app->version(),
            ]);
    }

    public function test_health_endpoint_returns_successful_response()
    {
        $this->get('/api/health');

        $this->seeStatusCode(200)
            ->seeJsonStructure([
                'status',
                'services' => [
                    'database'
                ]
            ])
            ->seeJson([
                'status' => 'UP',
                'services' => [
                    'database' => 'UP'
                ]
            ]);
    }

    public function test_user_registration_success()
    {
        $email = 'register_test_' . uniqid() . '@smartq.test';
        $this->json('POST', '/api/auth/register', [
            'name' => '  M Fazri Riyadi  ',
            'email' => $email,
            'password' => 'password123',
        ]);

        $this->seeStatusCode(201)
            ->seeJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'token',
                ]
            ])
            ->seeJson([
                'message' => 'Akun berhasil dibuat.',
                'name' => 'M Fazri Riyadi',
                'email' => $email,
            ]);
    }

    public function test_user_registration_validation_fails()
    {
        // Missing fields
        $this->json('POST', '/api/auth/register', [])
            ->seeStatusCode(422);

        // Invalid email
        $this->json('POST', '/api/auth/register', [
            'name' => 'Test',
            'email' => 'invalid-email',
            'password' => 'password123',
        ])->seeStatusCode(422);

        // Password too short
        $this->json('POST', '/api/auth/register', [
            'name' => 'Test',
            'email' => 'valid@test.com',
            'password' => 'short',
        ])->seeStatusCode(422);
    }

    public function test_user_login_success()
    {
        $email = 'login_test_' . uniqid() . '@smartq.test';
        
        // First register the user
        $this->json('POST', '/api/auth/register', [
            'name' => 'M Fazri Riyadi',
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        // Try to log in
        $this->json('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $this->seeStatusCode(200)
            ->seeJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'token',
                ]
            ])
            ->seeJson([
                'message' => 'Login berhasil.',
                'name' => 'M Fazri Riyadi',
                'email' => $email,
            ]);
    }

    public function test_user_login_fails_with_invalid_credentials()
    {
        // Non-existent user
        $this->json('POST', '/api/auth/login', [
            'email' => 'nonexistent@smartq.test',
            'password' => 'password123',
        ])->seeStatusCode(422)
          ->seeJson([
              'message' => 'Email atau kata sandi salah.'
          ]);

        // Wrong password
        $email = 'login_fail_' . uniqid() . '@smartq.test';
        $this->json('POST', '/api/auth/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123',
        ])->seeStatusCode(201);

        $this->json('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'wrongpassword',
        ])->seeStatusCode(422)
          ->seeJson([
              'message' => 'Email atau kata sandi salah.'
          ]);
    }
}
