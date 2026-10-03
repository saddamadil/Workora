<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('files.index') : view('landing'))->name('home');

// Public: reached by a share link with no sign-in.
Route::prefix('s/{token}')->name('share.')->group(function () {
    Route::get('/', [PublicShareController::class, 'show'])->name('show');
    Route::post('/unlock', [PublicShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
    Route::get('/preview', [PublicShareController::class, 'preview'])->name('preview');
    Route::get('/download', [PublicShareController::class, 'download'])->name('download');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::patch('/files/{file}', [FileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('/files/{file}/share', [FileController::class, 'share'])->name('files.share');
    Route::post('/files/{file}/drive', [DriveController::class, 'export'])->name('files.drive');

    Route::get('/shared', [ShareLinkController::class, 'index'])->name('shares.index');
    Route::delete('/shared/{link}', [ShareLinkController::class, 'destroy'])->name('shares.destroy');

    Route::prefix('drive')->name('drive.')->group(function () {
        Route::get('/', [DriveController::class, 'index'])->name('index');
        Route::get('/connect', [DriveController::class, 'connect'])->name('connect');
        Route::get('/callback', [DriveController::class, 'callback'])->name('callback');
        Route::post('/disconnect', [DriveController::class, 'disconnect'])->name('disconnect');
        Route::post('/import', [DriveController::class, 'import'])->name('import');
    });
});
