<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', ['uses' => 'WelcomeController@index', 'as' => 'welcome_index'], function () { 
    return view('welcome');
});

Route::get('/match', ['uses' => 'Match\MatchController@index', 'as' => 'match_index'], function () {
    return view('templates.match.index');
});

Route::get('/match/{matchId}', ['uses' => 'Match\MatchController@activeMatch', 'as' => 'active_match'], function () {
    return view('templates.match.match');
});

Route::post('/match', 'Match\MatchController@store');