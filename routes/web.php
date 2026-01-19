<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'It works!';
});

Route::get('/login', function () {
    return 'login';
})->name('login');

Route::any(env('ADMINNEO_ROUTE', '/ado'), function () {
    require '../adminneo.php';
})->middleware('auth')->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
