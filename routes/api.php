<?php

use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\DoctorController;
use Illuminate\Support\Facades\Route;

Route::get('/doctors', [DoctorController::class, 'index'])->name('doctors.index');
Route::get('/consultations', [ConsultationController::class, 'index'])->name('consultations.index');
Route::post('/consultations', [ConsultationController::class, 'store'])->name('consultations.store');
