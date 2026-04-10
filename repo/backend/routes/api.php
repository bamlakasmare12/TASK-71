<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ReservationController;
use Illuminate\Support\Facades\Route;

// Public API auth (session-based for offline-first internal use)
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

// Authenticated API routes
Route::middleware(['auth', 'password.not-expired'])->group(function () {
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::post('/auth/step-up', [AuthController::class, 'stepUpVerify'])->name('api.auth.step-up');

    // Catalog
    Route::get('/catalog', [CatalogController::class, 'index'])->name('api.catalog.index');
    Route::get('/catalog/dictionaries', [CatalogController::class, 'dictionaries'])->name('api.catalog.dictionaries');
    Route::get('/catalog/favorites', [CatalogController::class, 'favorites'])->name('api.catalog.favorites');
    Route::get('/catalog/{service}', [CatalogController::class, 'show'])->name('api.catalog.show');
    Route::post('/catalog/{service}/favorite', [CatalogController::class, 'toggleFavorite'])->name('api.catalog.favorite');

    // Catalog management (editor/admin)
    Route::middleware('role:editor,admin')->group(function () {
        Route::post('/catalog', [CatalogController::class, 'store'])->name('api.catalog.store');
        Route::put('/catalog/{service}', [CatalogController::class, 'update'])->name('api.catalog.update');
    });

    // Reservations
    Route::get('/reservations', [ReservationController::class, 'index'])->name('api.reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('api.reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('api.reservations.show');
    Route::post('/reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->name('api.reservations.confirm');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('api.reservations.cancel');
    Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->name('api.reservations.check-in');
    Route::post('/reservations/{reservation}/check-out', [ReservationController::class, 'checkOut'])->name('api.reservations.check-out');
    Route::post('/reservations/{reservation}/reschedule', [ReservationController::class, 'reschedule'])->name('api.reservations.reschedule');

    // Admin routes — all critical admin actions require step-up verification
    Route::middleware(['role:admin', 'step-up'])->prefix('admin')->group(function () {
        // User management
        Route::get('/users', [AdminController::class, 'users'])->name('api.admin.users');
        Route::put('/users/{user}/role', [AdminController::class, 'changeRole'])->name('api.admin.users.role');
        Route::delete('/users/{user}', [AdminController::class, 'deactivateUser'])->name('api.admin.users.deactivate');

        // Dictionary management (policy edit — step-up enforced)
        Route::get('/dictionaries', [AdminController::class, 'dictionaries'])->name('api.admin.dictionaries');
        Route::post('/dictionaries', [AdminController::class, 'storeDictionary'])->name('api.admin.dictionaries.store');
        Route::put('/dictionaries/{dictionary}', [AdminController::class, 'updateDictionary'])->name('api.admin.dictionaries.update');
        Route::delete('/dictionaries/{dictionary}', [AdminController::class, 'deleteDictionary'])->name('api.admin.dictionaries.delete');

        // Form rules management (policy edit — step-up enforced)
        Route::get('/form-rules', [AdminController::class, 'formRules'])->name('api.admin.form-rules');
        Route::post('/form-rules', [AdminController::class, 'storeFormRule'])->name('api.admin.form-rules.store');
        Route::put('/form-rules/{formRule}', [AdminController::class, 'updateFormRule'])->name('api.admin.form-rules.update');
        Route::delete('/form-rules/{formRule}', [AdminController::class, 'deleteFormRule'])->name('api.admin.form-rules.delete');

        // Import management (step-up enforced)
        Route::post('/import/upload', [AdminController::class, 'importUpload'])->name('api.admin.import.upload');
        Route::post('/import/{batchId}/process', [AdminController::class, 'importProcess'])->name('api.admin.import.process');
        Route::get('/import/{batchId}/status', [AdminController::class, 'importStatus'])->name('api.admin.import.status');
        Route::post('/import/conflicts/{conflictId}/resolve', [AdminController::class, 'importResolveConflict'])->name('api.admin.import.resolve');
        Route::post('/import/{batchId}/finish', [AdminController::class, 'importFinish'])->name('api.admin.import.finish');

        // Export (step-up enforced)
        Route::post('/export', [AdminController::class, 'exportData'])->name('api.admin.export');
    });
});
