<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\AuthController as PhoneOtpAuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\VerificationApiController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SosController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;

/*
|--------------------------------------------------------------------------
| SAFEE MEET API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // ── Auth (Firebase — used by the shipped app; public) ──────────────────
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('check-user-exists', [AuthController::class, 'checkUserExists']);
        Route::post('verify-phone', [AuthController::class, 'verifyPhone']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('email-otp/send', [AuthController::class, 'sendEmailOtp']);
        Route::post('email-otp/verify', [AuthController::class, 'verifyEmailOtp']);
        Route::post('social/validate', [AuthController::class, 'socialValidate']);
    });

    // ── Auth (phone + OTP — newer flow, not yet wired into the app; public) ─
    Route::prefix('auth/phone')->group(function (): void {
        Route::post('register', [PhoneOtpAuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('login', [PhoneOtpAuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('login-or-register', [PhoneOtpAuthController::class, 'loginOrRegister'])->middleware('throttle:5,1');
        Route::post('verify-otp', [PhoneOtpAuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    });

    // ── Everything below requires a valid Sanctum token ─────────────────────
   // Route::middleware('auth:sanctum')->group(function (): void {

        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        Route::prefix('auth/phone')->group(function (): void {
            Route::get('me', [PhoneOtpAuthController::class, 'me']);
            Route::post('logout', [PhoneOtpAuthController::class, 'logout']);
        });

        Route::apiResource('meetings', MeetingController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('meetings/{meeting}/accept', [MeetingController::class, 'accept']);
        Route::post('meetings/{meeting}/approve', [MeetingController::class, 'approve']);
        Route::post('meetings/{meeting}/deny', [MeetingController::class, 'deny']);
        Route::post('meetings/{meeting}/reschedule', [MeetingController::class, 'reschedule']);
        Route::post('meetings/{meeting}/cancel', [MeetingController::class, 'cancel']);
        Route::post('meetings/{meeting}/arrive', [MeetingController::class, 'arrive']);
        Route::post('meetings/{meeting}/location', [MeetingController::class, 'pingLocation']);
        Route::post('meetings/{meeting}/complete', [MeetingController::class, 'complete']);
        Route::post('meetings/{meeting}/review', [ReviewController::class, 'store']);

        Route::post('device/fcm-token', [DeviceController::class, 'syncFcmToken']);

        Route::get('reviews', [ReviewController::class, 'index']);
        Route::post('reviews/{review}/helpful', [ReviewController::class, 'markHelpful']);

        Route::get('/emergency-contact', [EmergencyContactController::class, 'index']);
        Route::post('/emergency-contact', [EmergencyContactController::class, 'store']);
        Route::delete('/emergency-contact/{id}', [EmergencyContactController::class, 'destroy']);

        // SOS / Safety
        Route::post('/sos/trigger', [SosController::class, 'trigger']);
        Route::post('/sos/{incident}/resolve', [SosController::class, 'resolve']);
        Route::post('/reports', [ReportController::class, 'store']);
        Route::get('/reports', [ReportController::class, 'index']);

        // Subscriptions
        Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
        Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
        Route::post('/subscriptions/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel']);


        Route::prefix('members')->group(function (): void {
            Route::get('search', [MemberController::class, 'searchByPin']);
            Route::get('qr', [MemberController::class, 'searchByQR']);
            Route::get('recent-searches', [MemberController::class, 'recentSearches']);
        });

        Route::prefix('verification')->group(function (): void {
            Route::post('/submit', [VerificationController::class,'submitVerification',]);
            Route::get('status', [VerificationApiController::class, 'status']);
            Route::get('progress', [VerificationApiController::class, 'progress']);
            Route::post('id', [VerificationApiController::class, 'uploadId']);
            Route::post('selfie', [VerificationApiController::class, 'uploadSelfie']);
        });
   // });
});

