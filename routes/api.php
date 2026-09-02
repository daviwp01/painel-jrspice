<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExternalRegistrationController;

Route::post('/external/register', [ExternalRegistrationController::class, 'store'])->name('api.external.register');
