<?php

use App\Http\Controllers\RentalOverrideController;
use App\Http\Middleware\ActiveAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ActiveAccount::class])
    ->prefix('tenants/{tenant}/assignments/{assignment}/rental-overrides')
    ->name('rental-overrides.')
    ->group(function () {
        Route::get('/', [RentalOverrideController::class, 'index'])->name('index');
        Route::get('create', [RentalOverrideController::class, 'create'])->name('create');
        Route::post('/', [RentalOverrideController::class, 'store'])->name('store');
        Route::post('preview', [RentalOverrideController::class, 'preview'])->name('preview');
        Route::get('{override}/edit', [RentalOverrideController::class, 'edit'])->name('edit');
        Route::put('{override}', [RentalOverrideController::class, 'update'])->name('update');
        Route::post('{override}/submit', [RentalOverrideController::class, 'submit'])->name('submit');
        Route::post('{override}/approve', [RentalOverrideController::class, 'approve'])->name('approve');
        Route::post('{override}/reject', [RentalOverrideController::class, 'reject'])->name('reject');
        Route::post('{override}/revoke', [RentalOverrideController::class, 'revoke'])->name('revoke');
    });
