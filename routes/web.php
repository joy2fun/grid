<?php

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SetupController::class, 'index']);
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::get('/login', function () {
    return 'login';
})->name('login');

Route::any(env('ADMINNEO_ROUTE', '/ado'), function () {
    require '../adminneo.php';
})->middleware('auth')->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
