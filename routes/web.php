<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::inertia('rutas', 'rutas')->name('rutas');
        Route::inertia('mapa', 'mapa')->name('mapa');
        Route::inertia('horarios', 'horarios')->name('horarios');
        Route::inertia('reportes', 'reportes')->name('reportes');
        Route::inertia('sos', 'sos')->name('sos');
        Route::inertia('acompanamiento', 'acompanamiento')->name('acompanamiento');
        Route::inertia('inactividad', 'inactividad')->name('inactividad');
        Route::inertia('comunidad', 'comunidad')->name('comunidad');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
