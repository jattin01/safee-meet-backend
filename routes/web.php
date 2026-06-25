<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\IncidentsController;
use App\Http\Controllers\RevenueController;

Route::get('/dashboard', function () {
    return view('dashboard.index');
});


Route::get('/users', [UserController::class, 'index'])->name('users');
Route::get('/verification', [VerificationController::class, 'index'])->name('verification');
Route::get('/orders', [OrderController::class, 'index'])->name('orders');
Route::get('/subscription', [SubscriptionsController::class, 'index'])->name('subscription');
Route::get('/incidents', [IncidentsController::class, 'index'])->name('incidents');
Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue');