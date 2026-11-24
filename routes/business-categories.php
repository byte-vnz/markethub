<?php

use App\Http\Controllers\BusinessCategoryController;
use App\Http\Middleware\ActiveAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ActiveAccount::class])->prefix('settings')->group(function () {
    Route::prefix('business-categories')->name('business-categories.')->group(function () {
        Route::get('/', [BusinessCategoryController::class, 'index'])->name('index');
        Route::get('/create', [BusinessCategoryController::class, 'create'])->name('create');
        Route::post('/', [BusinessCategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [BusinessCategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [BusinessCategoryController::class, 'update'])->name('update');
        Route::post('/{category}/rates', [BusinessCategoryController::class, 'storeRate'])->name('rates.store');
        Route::put('/{category}/rates/{rate}', [BusinessCategoryController::class, 'updateRate'])->name('rates.update');
        Route::post('/{category}/rates/{rate}/approve', [BusinessCategoryController::class, 'approveRate'])->name('rates.approve');
        Route::post('/{category}/rates/{rate}/retire', [BusinessCategoryController::class, 'retireRate'])->name('rates.retire');
    });

    Route::prefix('rental-policies')->name('rental-policies.')->group(function () {
        Route::get('/', [BusinessCategoryController::class, 'policies'])->name('index');
        Route::post('/', [BusinessCategoryController::class, 'storePolicy'])->name('store');
        Route::put('/{policy}', [BusinessCategoryController::class, 'updatePolicy'])->name('update');
        Route::post('/{policy}/approve', [BusinessCategoryController::class, 'approvePolicy'])->name('approve');
        Route::post('/{policy}/retire', [BusinessCategoryController::class, 'retirePolicy'])->name('retire');
    });
});
