<?php

namespace Tests;

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class ExampleTest extends TestCase
{
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
}
