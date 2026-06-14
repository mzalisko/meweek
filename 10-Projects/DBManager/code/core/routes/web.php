<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Admin routes — protected by auth middleware.
// Task 1: stub route; Task 2 will replace with the full Livewire layout.
Route::middleware('auth')->group(function () {
    Route::get('/admin', function () {
        return response('ok');
    })->name('admin.values');
});

require __DIR__.'/auth.php';
