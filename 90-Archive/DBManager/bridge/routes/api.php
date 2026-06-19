<?php

use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\GeoIngestController;
use App\Http\Controllers\Api\GeoServeController;
use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/publish', IngestController::class);
Route::post('/internal/geodb', GeoIngestController::class);
Route::get('/v1/data', DataController::class)
    ->middleware(['bridge.log', 'throttle:120,1']);
Route::get('/v1/geodb', GeoServeController::class)->middleware(['bridge.log', 'throttle:30,1']);
