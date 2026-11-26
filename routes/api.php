<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExternalRegistrationController;
use App\Http\Controllers\Api\TimeToCargoWebhookController;

Route::post('/external/register', [ExternalRegistrationController::class, 'store'])
    ->middleware('throttle:external-register')
    ->name('api.external.register');

// TimeToCargo → POST /api/webhooks/timetocargo (HMAC verified in controller).
Route::post('/webhooks/timetocargo', TimeToCargoWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.webhooks.timetocargo');
