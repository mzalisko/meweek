<?php

use Illuminate\Support\Facades\Route;

// Головна → адмінка (вона сама редіректить на /login, якщо не авторизовано).
Route::redirect('/', '/admin');

// Breeze після входу веде на route('dashboard'); зводимо його на нашу адмінку.
Route::redirect('dashboard', '/admin')->name('dashboard');

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
