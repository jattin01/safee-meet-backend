<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SAFEE MEET — API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Auth (Public) ─────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register',          [AuthController::class, 'register']);
        Route::post('login',             [AuthController::class, 'login']);
        Route::post('check-user-exists', [AuthController::class, 'checkUserExists']);
        Route::post('verify-phone',       [AuthController::class, 'verifyPhone']);
        Route::post('verify-email',       [AuthController::class, 'verifyEmail']);
        Route::post('email-otp/send',     [AuthController::class, 'sendEmailOtp']);
        Route::post('email-otp/verify',   [AuthController::class, 'verifyEmailOtp']);
        Route::post('social/validate',    [AuthController::class, 'socialValidate']);
    });

    // ── Auth (Protected) ──────────────────────────────────────────────────
    Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });

    // ── Members (Protected) ───────────────────────────────────────────────
    Route::prefix('members')->middleware('auth:sanctum')->group(function () {
        Route::get('search', [MemberController::class, 'searchByPin']);
        Route::get('qr',     [MemberController::class, 'searchByQR']);
    });

});
