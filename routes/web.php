<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SafetyPageController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

require __DIR__.'/safety.php';

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('rutas', [SafetyPageController::class, 'routes'])->name('rutas');
        Route::get('mapa', [SafetyPageController::class, 'map'])->name('mapa');
        Route::inertia('horarios', 'horarios')->name('horarios');
        Route::get('reportes', [SafetyPageController::class, 'reports'])->name('reportes');
        Route::get('sos', [SafetyPageController::class, 'sos'])->name('sos');
        Route::inertia('acompanamiento', 'acompanamiento')->name('acompanamiento');
        Route::inertia('inactividad', 'inactividad')->name('inactividad');
        Route::inertia('comunidad', 'comunidad')->name('comunidad');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
