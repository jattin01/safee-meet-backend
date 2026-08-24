<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BackgroundCheckController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\VerificationLevelController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\IncidentsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/terms-and-conditions', [TermsController::class, 'public'])->name('terms.public');
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'public'])->name('privacy-policy.public');

// TEMPORARY — for testing Telesign OTP sending. Remove after verifying.
// Usage: /test-telesign-otp?phone=91XXXXXXXXXX
Route::get('/test-telesign-otp', function (\Illuminate\Http\Request $request) {
    $phone = $request->query('phone');

    if (! $phone) {
        return response()->json(['error' => 'Pass ?phone=91XXXXXXXXXX in the URL'], 400);
    }

    $otp = (string) rand(100000, 999999);
    $result = app(\App\Services\Sms\TelesignSmsService::class)->sendOtp($phone, $otp);

    return response()->json([
        'sent' => $result,
        'otp_used' => $otp,
        'note' => 'Check storage/logs/laravel.log for full Telesign response/error details.',
    ]);
});

Route::middleware('guest:admin')->group(function () {

    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

});

Route::middleware('auth:admin')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
    Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
    Route::get('/admins', [AdminController::class, 'index'])->name('admins.index');
    Route::get('/admins/data', [AdminController::class, 'data'])->name('admins.data');
    Route::post('/admins', [AdminController::class, 'store'])->name('admins.store');
    Route::post('/admins/{admin}/status', [AdminController::class, 'updateStatus'])->name('admins.status.update');
    Route::get('/verification', [VerificationController::class, 'index'])->name('verification');
    Route::get('/verification/{verification}', [VerificationController::class, 'show'])->name('verification.show');
    Route::get('/verification/{verification}/{asset}', [VerificationController::class, 'showAsset'])
        ->whereIn('asset', ['front', 'back', 'selfie'])
        ->name('verification.files.show');
    Route::post('/verification/{verification}/approve', [VerificationController::class, 'approve'])
        ->name('verification.approve');
    Route::post('/verification/{verification}/reject', [VerificationController::class, 'reject'])
        ->name('verification.reject');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::match(['get', 'post'], '/subscription', [SubscriptionsController::class, 'index'])->name('subscription');

    Route::get('/features', [FeatureController::class, 'index'])->name('features.index');
    Route::post('/features', [FeatureController::class, 'store'])->name('features.store');
    Route::post('/features/matrix', [FeatureController::class, 'saveMatrix'])->name('features.matrix');
    Route::put('/features/{feature}', [FeatureController::class, 'update'])->name('features.update');
    Route::delete('/features/{feature}', [FeatureController::class, 'destroy'])->name('features.destroy');

    Route::get('/verification-levels', [VerificationLevelController::class, 'index'])->name('verification-levels.index');
    Route::post('/verification-levels', [VerificationLevelController::class, 'store'])->name('verification-levels.store');
    Route::put('/verification-levels/{verificationLevel}', [VerificationLevelController::class, 'update'])->name('verification-levels.update');
    Route::delete('/verification-levels/{verificationLevel}', [VerificationLevelController::class, 'destroy'])->name('verification-levels.destroy');
    Route::post('/verification-levels/{verificationLevel}/restore', [VerificationLevelController::class, 'restore'])->name('verification-levels.restore');
    Route::get('/incidents', [IncidentsController::class, 'index'])->name('incidents');
    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue');
    Route::get('/terms', [TermsController::class, 'index'])->name('terms.index');
    Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::post('/users/{id}/status', [UserController::class, 'updateStatus'])->name('users.status.update');
    Route::post('/users/{user}/background-check/recheck', [BackgroundCheckController::class, 'recheck'])
        ->name('users.background-check.recheck');
    Route::post('/terms', [TermsController::class, 'update'])->name('terms.update');
    Route::get('/privacy', [PrivacyPolicyController::class, 'index'])->name('privacy-policy.index');
    Route::post('/privacy', [PrivacyPolicyController::class, 'update'])->name('privacy-policy.update');

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.dashboard');
    Route::get('/super-admin/dashboard', [SuperAdminDashboardController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('super-admin.dashboard');
});
