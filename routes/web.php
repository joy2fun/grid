<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'It works!';
});

Route::any('/ado', function () {
    require '../adminneo.php';
})->middleware('auth')->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
