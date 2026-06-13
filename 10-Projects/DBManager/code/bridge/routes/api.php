<?php

use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/publish', IngestController::class);
Route::get('/v1/data', DataController::class)
    ->middleware(['bridge.log', 'throttle:120,1']);
