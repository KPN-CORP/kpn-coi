<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PasswordResetBotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Webhook endpoint for the messaging bot. It checks whether a phone number
| belongs to a non-employee account and replies with a magic reset link or a
| message telling the user to contact an admin.
|
*/

Route::post('/reset-password', PasswordResetBotController::class)
    ->name('api.reset-password');
