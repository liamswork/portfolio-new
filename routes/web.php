<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [

    ]);
});

Route::get('/games', function () {
    return Inertia::render('Games');
})->name('games');

Route::get('/games/tower', function () {
    return Inertia::render('Games/Tower');
})->name('tower');


//Route::middleware([
//    'auth:sanctum',
//    config('jetstream.auth_session'),
//    'verified',
//])->group(function () {
//    Route::get('/dashboard', function () {
//        return Inertia::render('Dashboard');
//    })->name('dashboard');
//});
