<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\IncidentsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;



Route::middleware('guest:admin')->group(function () {

    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

});

Route::middleware('auth:admin')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/admins', [AdminController::class, 'index'])->name('admins.index');
    Route::get('/admins/data', [AdminController::class, 'data'])->name('admins.data');
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
    Route::get('/incidents', [IncidentsController::class, 'index'])->name('incidents');
    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue');
    Route::get('/terms', [TermsController::class, 'index'])->name('terms.index');
    Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::post('/terms', [TermsController::class, 'update'])->name('terms.update');

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.dashboard');
    Route::get('/super-admin/dashboard', [SuperAdminDashboardController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('super-admin.dashboard');
});
