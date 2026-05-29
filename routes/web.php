<?php

use App\Livewire\Admin\ClaimRatings;
use App\Livewire\Admin\Employees;
use App\Livewire\AdminConfig;
use App\Livewire\AdminDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check() && in_array(auth()->user()->role, ['admin', 'staff'], true)) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('admin.login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => redirect()->route('admin.login'))->name('login');

    Route::get('/administrator/login', function (Request $request) {
        $request->session()->put('url.intended', route('admin.dashboard'));

        return view('auth.admin-login');
    })->name('admin.login');

    Route::view('/administrator/forgot-password', 'auth.forgot-password')->name('password.request');

    Route::get('/administrator/reset-password/{token}', function (Request $request, string $token) {
        return view('auth.reset-password', ['request' => $request]);
    })->name('password.reset');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))->name('dashboard');
});

Route::middleware(['auth:sanctum', 'auth.status', config('jetstream.auth_session'), 'verified', 'role:admin,staff'])
    ->prefix('administrator')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::get('/index', AdminDashboard::class)->name('index');
        Route::get('/config', AdminConfig::class)->name('config');
        Route::get('/reviews', ClaimRatings::class)->name('reviews');
        Route::get('/employees', Employees::class)->name('employees');
});
