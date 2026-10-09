<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\LoyaltyController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Called by PayMongo itself, not by a logged-in user — must stay outside auth and CSRF.
Route::post('/webhooks/paymongo', [WebhookController::class, 'paymongo'])->name('webhooks.paymongo');

// Guests only (logged-in users are redirected to the dashboard)
Route::middleware('guest')->group(function () {
    Route::view('/', 'landing')->name('landing');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::view('/forgot-password', 'auth.forgot')->name('password.request');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/waiting', function () {
        return auth()->user()->has_access ? redirect()->route('dashboard') : view('auth.waiting');
    })->name('waiting');

    // Admin-only: staff get 403, even without access
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('logs');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/toggle-access', [UserController::class, 'toggleAccess'])->name('users.toggle-access');
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('/users/{user}/link', [UserController::class, 'link'])->name('users.link');       // Supabase mode
        Route::post('/users/{user}/unlink', [UserController::class, 'unlink'])->name('users.unlink'); // Supabase mode

        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{orderId}/mark-paid', [PaymentController::class, 'markPaid'])->name('payments.mark-paid');

        Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::post('/refunds/{orderId}/approve', [RefundController::class, 'approve'])->name('refunds.approve');
        Route::post('/refunds/{orderId}/reject', [RefundController::class, 'reject'])->name('refunds.reject');
        Route::post('/refunds/{orderId}/retry', [RefundController::class, 'retry'])->name('refunds.retry');       // mock mode (PayMongo)
        Route::post('/refunds/{orderId}/complete', [RefundController::class, 'complete'])->name('refunds.complete'); // Supabase mode (HitPay)

        Route::put('/loyalty/settings', [LoyaltyController::class, 'updateSettings'])->name('loyalty.settings.update');
        Route::post('/loyalty/{member}/adjust', [LoyaltyController::class, 'adjust'])->name('loyalty.adjust');
    });

    // Admin-only actions on the shared catalog (Supabase mode)
    Route::middleware(['role:admin', 'has.access'])->group(function () {
        Route::post('/products/{product}/toggle-active', [OperationsController::class, 'toggleProduct'])->name('ops.products.toggle');
    });

    // Needs granted access; new accounts are redirected to /waiting
    Route::middleware('has.access')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/drivers.json', [DashboardController::class, 'drivers'])->name('dashboard.drivers');
        Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
        Route::get('/loyalty/{member}', [LoyaltyController::class, 'show'])->name('loyalty.show');
        Route::get('/s/{slug}', [DashboardController::class, 'section'])->name('section'); // live pages in Supabase mode, placeholders otherwise
    });
});
