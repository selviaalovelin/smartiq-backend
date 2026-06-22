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
    $router->post('/auth/register', 'AuthController@register');
    $router->post('/auth/login', 'AuthController@login');

    $router->get('/quizzes', 'QuizController@index');
    $router->post('/quizzes', 'QuizController@store');
    $router->get('/quizzes/pin/{pin}', 'QuizController@byPin');
    $router->get('/quizzes/{id}', 'QuizController@show');
    $router->put('/quizzes/{id}', 'QuizController@update');
    $router->delete('/quizzes/{id}', 'QuizController@destroy');
    $router->get('/quizzes/{id}/participants', 'QuizController@participants');
    $router->post('/quizzes/{id}/participants', 'QuizController@join');
    $router->post('/quizzes/{id}/participants/{participantId}/answers', 'QuizController@answer');
    $router->get('/quizzes/{id}/leaderboard', 'QuizController@leaderboard');

    $router->get('/assignments', 'AssignmentController@index');
    $router->post('/assignments', 'AssignmentController@store');
});
