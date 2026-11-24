<?php

use App\Http\Controllers\{BusinessController, TenantController};
use App\Http\Middleware\ActiveAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ActiveAccount::class])->prefix('manage/tenants')->group(function () {
    Route::post('rental-preview', [TenantController::class, 'preview'])->name('tenants.rental-preview');
    Route::get('businesses', [BusinessController::class, 'index'])->name('tenant-businesses.index');
    Route::post('businesses/{business}/reconcile', [BusinessController::class, 'reconcile'])->name('tenant-businesses.reconcile');
    Route::name('tenant-businesses.')->prefix('{tenant}/businesses')->group(function () {
        Route::get('create', [BusinessController::class, 'create'])->name('create');
        Route::post('/', [BusinessController::class, 'store'])->name('store');
        Route::get('{business}/edit', [BusinessController::class, 'edit'])->name('edit');
        Route::put('{business}', [BusinessController::class, 'update'])->name('update');
        Route::post('{business}/archive', [BusinessController::class, 'archive'])->name('archive');
    });
});
