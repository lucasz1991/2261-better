<?php

use App\Livewire\Admin\ClaimRatings;
use App\Livewire\Admin\Employees;
use App\Livewire\Admin\Tests\FormScriptTests;
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
        Route::get('/form-script-tests/screenshot', function (Request $request) {
            abort_unless($request->user()?->can('form_tests.run'), 403);

            $relativePath = str_replace('\\', '/', (string) $request->query('path', ''));

            abort_if($relativePath === '' || str_contains($relativePath, '..'), 404);

            $basePath = realpath(storage_path('app/public/screenshots/regulierungs-check'));
            $imagePath = realpath(storage_path('app/public/screenshots/regulierungs-check/'.$relativePath));

            abort_unless($basePath && $imagePath, 404);

            $basePath = rtrim(str_replace('\\', '/', $basePath), '/').'/';
            $imagePathForCheck = str_replace('\\', '/', $imagePath);

            abort_unless(
                str_starts_with($imagePathForCheck, $basePath)
                && str_ends_with(strtolower($imagePathForCheck), '.png'),
                404
            );

            return response()->file($imagePath, [
                'Content-Type' => 'image/png',
            ]);
        })->name('form-script-tests.screenshot');
        Route::get('/form-script-tests', FormScriptTests::class)->name('form-script-tests');
        Route::get('/employees', Employees::class)->name('employees');
});
