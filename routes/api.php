<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\DoctorController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1')
    ->name('auth.register');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

Route::get('/doctors', [DoctorController::class, 'index'])->name('doctors.index');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/consultations', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::post('/consultations', [ConsultationController::class, 'store'])->name('consultations.store');
});
