<?php

use App\Livewire\AccessManager;
use App\Livewire\AuditManager;
use App\Livewire\BulkOperations;
use App\Livewire\SitesManager;
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
Route::middleware(['auth', 'admin.access'])->group(function () {
    // /admin — головний екран адмінки (грід значень), без зайвого редіректу.
    Route::get('/admin', ValuesGrid::class)->name('admin.home');
    Route::get('/admin/site', ValuesGrid::class)->name('admin.site');
    Route::get('/admin/values', ValuesGrid::class)->name('admin.values');
    Route::get('/admin/bulk', BulkOperations::class)->name('admin.bulk');
    Route::get('/admin/sites', SitesManager::class)->name('admin.sites');
    Route::get('/admin/access', AccessManager::class)->name('admin.access');
    Route::get('/admin/audit', AuditManager::class)->name('admin.audit');
});

require __DIR__.'/auth.php';
