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
// Task 2: render the admin shell layout (placeholder; Task 4 will swap to ValuesGrid).
Route::middleware('auth')->group(function () {
    Route::get('/admin', function () {
        return view('admin.placeholder');
    })->name('admin.values');
});

require __DIR__.'/auth.php';
