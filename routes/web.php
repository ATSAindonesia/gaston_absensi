<?php

use App\Http\Controllers\Member\MemberScanController;
use App\Http\Controllers\ScanAttendanceController;
use App\Http\Controllers\SessionQrController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('member')->name('member.')->middleware(['admin.area', 'role:anggota'])->group(function () {
        Route::view('dashboard', 'member.dashboard')->name('dashboard');
        Route::post('scan', MemberScanController::class)->name('scan');
    });

    Route::get('absen/{token}', ScanAttendanceController::class)->name('attendance.scan');

    Route::middleware(['role:admin,superadmin', 'admin.layout'])->group(function () {
        Route::livewire('admin', 'pages::admin.dashboard')->name('admin.dashboard');
        Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
        Route::livewire('admin/sessions', 'pages::admin.sessions')->name('admin.sessions');
        Route::livewire('admin/sessions/{session}', 'pages::admin.session-detail')->name('admin.sessions.show');
        Route::get('admin/sessions/{session}/qr', [SessionQrController::class, 'show'])->name('admin.sessions.qr');

        Route::middleware(['role:superadmin'])->group(function () {
            Route::livewire('admin/sports', 'pages::admin.sports')->name('admin.sports');
            Route::livewire('admin/roles', 'pages::admin.roles')->name('admin.roles');
        });

        Route::redirect('admin/settings', 'admin/settings/profile');
        Route::livewire('admin/settings/profile', 'pages::settings.profile')->name('admin.settings.profile');
        Route::livewire('admin/settings/appearance', 'pages::settings.appearance')->name('admin.settings.appearance');
        Route::livewire('admin/settings/security', 'pages::settings.security')
            ->middleware(['password.confirm'])
            ->name('admin.settings.security');
    });
});

require __DIR__.'/settings.php';
