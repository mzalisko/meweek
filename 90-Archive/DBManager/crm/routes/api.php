<?php

use App\Http\Controllers\Api\MonitoringWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/monitoring/numbers', MonitoringWebhookController::class)
    ->middleware('throttle:60,1');
