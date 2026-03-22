<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\KirkconnelController;
use App\Http\Controllers\MapEditorController;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/games', function () {
    return Inertia::render('Games');
})->name('games');

// ── Kirkconnel (auth required) ────────────────────────────────────────────────
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('games/kirkconnel')
    ->name('kirkconnel.')
    ->group(function () {
        Route::get('/',                          [KirkconnelController::class, 'lobby'])->name('lobby');
        Route::post('/create',                   [KirkconnelController::class, 'create'])->name('create');
        Route::post('/join/{game}',              [KirkconnelController::class, 'join'])->name('join');
        Route::get('/play/{game}',               [KirkconnelController::class, 'show'])->name('game');
        Route::post('/start/{game}',             [KirkconnelController::class, 'start'])->name('start');
        Route::post('/action/{game}',            [KirkconnelController::class, 'action'])->name('action');
        Route::post('/reconnect',                [KirkconnelController::class, 'reconnect'])->name('reconnect');
        Route::get('/map-editor',                [MapEditorController::class, 'index'])->name('map-editor');
        Route::post('/map-editor',               [MapEditorController::class, 'store'])->name('map-editor.store');
        Route::post('/map-editor/{map}/publish', [MapEditorController::class, 'publish'])->name('map-editor.publish');
    });
