<?php

use App\Livewire\ValuesGrid;
use Illuminate\Support\Facades\Route;

// Головна → адмінка (вона сама редіректить на /login, якщо не авторизовано).
Route::redirect('/', '/admin');

// Breeze після входу веде на route('dashboard'); зводимо його на нашу адмінку.
Route::redirect('dashboard', '/admin')->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Admin routes — protected by auth middleware.
Route::middleware('auth')->group(function () {
    Route::get('/admin', ValuesGrid::class)->name('admin.values');
});

require __DIR__.'/auth.php';
