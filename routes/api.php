<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\VerificationApiController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SAFEE MEET API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('login-or-register', [AuthController::class, 'loginOrRegister'])->middleware('throttle:5,1');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    });

   
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        Route::apiResource('meetings', MeetingController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('meetings/{meeting}/accept', [MeetingController::class, 'accept']);
        Route::post('meetings/{meeting}/reschedule', [MeetingController::class, 'reschedule']);
        Route::post('meetings/{meeting}/cancel', [MeetingController::class, 'cancel']);
        Route::post('meetings/{meeting}/arrive', [MeetingController::class, 'arrive']);
        Route::post('meetings/{meeting}/location', [MeetingController::class, 'pingLocation']);
        Route::post('meetings/{meeting}/complete', [MeetingController::class, 'complete']);
        Route::post('meetings/{meeting}/review', [ReviewController::class, 'store']);

        Route::get('reviews', [ReviewController::class, 'index']);
        Route::post('reviews/{review}/helpful', [ReviewController::class, 'markHelpful']);

                
        Route::get('/emergency-contact/{id}', [EmergencyContactController::class, 'index']);
        Route::post('/emergency-contact', [EmergencyContactController::class, 'store']);
        Route::delete('/emergency-contact/{id}', [EmergencyContactController::class, 'destroy']);

        Route::prefix('members')->group(function (): void {
            Route::get('search', [MemberController::class, 'searchByPin']);
            Route::get('qr', [MemberController::class, 'searchByQR']);
        });

        Route::prefix('verification')->group(function (): void {
            Route::get('status', [VerificationApiController::class, 'status']);
            Route::get('progress', [VerificationApiController::class, 'progress']);
            Route::post('id', [VerificationApiController::class, 'uploadId']);
            Route::post('selfie', [VerificationApiController::class, 'uploadSelfie']);
        });
    });

