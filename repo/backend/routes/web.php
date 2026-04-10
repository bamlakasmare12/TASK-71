<?php

use App\Actions\Auth\PerformLogout;
use App\Livewire\Auth\ChangePasswordForm;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\RegisterForm;
use App\Livewire\Auth\StepUpVerification;
use App\Livewire\Catalog\CatalogList;
use App\Livewire\Catalog\ServiceDetail;
use App\Livewire\Catalog\ServiceManager;
use App\Livewire\Admin\ExportManager;
use App\Livewire\Admin\ImportManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Reservations\ReservationDashboard;
use App\Livewire\Reservations\TimeSlotManager;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', LoginForm::class)->name('login');
    Route::get('/register', RegisterForm::class)->name('register');
});

// Auth routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', function (PerformLogout $action) {
        $action->execute(allDevices: true);
        return redirect()->route('login');
    })->name('logout');

    // Password change (accessible even with expired password)
    Route::get('/password/change', ChangePasswordForm::class)->name('auth.password.change');

    // Step-up verification
    Route::get('/auth/verify', StepUpVerification::class)->name('auth.step-up');

    // Session keep-alive ping
    Route::post('/api/ping', function () {
        return response()->json(['status' => 'ok']);
    })->name('api.ping');

    // Protected routes (require non-expired password)
    Route::middleware([\App\Http\Middleware\EnsurePasswordNotExpired::class])->group(function () {
        // Dashboard
        Route::get('/', function () {
            return view('dashboard');
        })->name('dashboard');

        // Service Catalog
        Route::get('/catalog', CatalogList::class)->name('catalog');
        Route::get('/services/{service}', ServiceDetail::class)->name('services.show');

        // Service Management (editor/admin only)
        Route::middleware('role:editor,admin')->group(function () {
            Route::get('/services-manage/create', ServiceManager::class)->name('services.create');
            Route::get('/services-manage/{serviceId}/edit', ServiceManager::class)->name('services.edit');
            Route::get('/services/{service}/time-slots', TimeSlotManager::class)->name('services.time-slots');
        });

        // Reservations (learner)
        Route::get('/reservations', ReservationDashboard::class)->name('reservations');

        // Admin routes - all critical admin actions require step-up verification
        Route::middleware(['role:admin', 'step-up'])->prefix('admin')->group(function () {
            Route::get('/import', ImportManager::class)->name('admin.import');
            Route::get('/export', ExportManager::class)->name('admin.export');
            Route::get('/users', UserManager::class)->name('admin.users');
            Route::get('/dictionaries', \App\Livewire\Admin\DictionaryManager::class)->name('admin.dictionaries');
        });
    });
});
