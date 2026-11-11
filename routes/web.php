<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/cadastro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/cadastro', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('home');

    Route::get('/refeicoes', [MealController::class, 'index'])->name('meals.index');
    Route::post('/refeicoes', [MealController::class, 'store'])->name('meals.store');
    Route::get('/refeicoes/{meal}/foto', [MealController::class, 'photo'])->name('meals.photo');
    Route::delete('/refeicoes/{meal}', [MealController::class, 'destroy'])->name('meals.destroy');

    Route::post('/peso', [TrackingController::class, 'storeWeight'])->name('weight.store');
    Route::delete('/peso/{log}', [TrackingController::class, 'destroyWeight'])->name('weight.destroy');

    Route::post('/atividades', [TrackingController::class, 'storeActivity'])->name('activity.store');
    Route::delete('/atividades/{log}', [TrackingController::class, 'destroyActivity'])->name('activity.destroy');

    Route::post('/agua', [TrackingController::class, 'storeWater'])->name('water.store');
    Route::delete('/agua/{log}', [TrackingController::class, 'destroyWater'])->name('water.destroy');

    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::put('/metas', [TrackingController::class, 'updateGoals'])->name('goals.update');
});
