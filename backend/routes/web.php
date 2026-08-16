<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->away(rtrim(config('app.frontend_url'), '/')));

Route::middleware('guest')->group(function (): void {
    Route::get('/auth/discord', [AuthController::class, 'redirect'])->name('auth.discord');
    Route::get('/auth/discord/callback', [AuthController::class, 'callback'])->name('auth.discord.callback');
});
