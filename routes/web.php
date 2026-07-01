<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return response()->json([
        'name' => 'SMARTIQ Backend',
        'framework' => $router->app->version(),
    ]);
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->get('/health', 'Controller@health');
    $router->post('/auth/register', 'AuthController@register');
    $router->post('/auth/login', 'AuthController@login');
    $router->post('/auth/logout', 'AuthController@logout');
    $router->post('/auth/forgot-password', 'AuthController@forgotPassword');
    $router->post('/auth/reset-password', 'AuthController@resetPassword');

    $router->get('/quizzes', 'QuizController@index');
    $router->post('/quizzes', 'QuizController@store');
    $router->get('/quizzes/pin/{pin}', 'QuizController@byPin');
    $router->get('/quizzes/{id}', 'QuizController@show');
    $router->put('/quizzes/{id}', 'QuizController@update');
    $router->delete('/quizzes/{id}', 'QuizController@destroy');
    $router->put('/quizzes/{id}/open', 'QuizController@open');
    $router->put('/quizzes/{id}/start', 'QuizController@start');
    $router->put('/quizzes/{id}/finish', 'QuizController@finish');
    $router->delete('/quizzes/{id}/live-report', 'QuizController@deleteLiveReport');
    $router->get('/quizzes/{id}/participants', 'QuizController@participants');
    $router->post('/quizzes/{id}/participants', 'QuizController@join');
    $router->post('/quizzes/{id}/participants/{participantId}/answers', 'QuizController@answer');
    $router->get('/quizzes/{id}/leaderboard', 'QuizController@leaderboard');

    $router->get('/assignments', 'AssignmentController@index');
    $router->post('/assignments', 'AssignmentController@store');
    $router->get('/assignments/{id}/participants', 'AssignmentController@participants');
    $router->delete('/assignments/{id}', 'AssignmentController@destroy');
});
