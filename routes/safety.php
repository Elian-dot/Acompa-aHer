<?php

use App\Http\Controllers\Api\V1\EmergencyContactController;
use App\Http\Controllers\Api\V1\MapController;
use App\Http\Controllers\Api\V1\ReferenceMapController;
use App\Http\Controllers\Api\V1\ReportAttachmentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportModerationController;
use App\Http\Controllers\Api\V1\SosController;
use App\Http\Controllers\Api\V1\WalkingRouteController;
use App\Http\Controllers\SafetyPageController;
use App\Http\Middleware\PreventSensitiveResponseCaching;
use Illuminate\Support\Facades\Route;

Route::get('mapa', [SafetyPageController::class, 'map'])->name('public.mapa');
Route::get('rutas', [SafetyPageController::class, 'routes'])->name('public.rutas');

Route::middleware(['auth', PreventSensitiveResponseCaching::class])->group(function (): void {
    Route::get('contactos', [SafetyPageController::class, 'contacts'])->name('contacts');
    Route::get('moderacion', [SafetyPageController::class, 'moderation'])->name('moderation');
});

Route::prefix('api/v1')->name('api.v1.')->middleware([PreventSensitiveResponseCaching::class])->group(function (): void {
    Route::get('map', [MapController::class, 'index'])->middleware('throttle:map-read')->name('map.index');
    Route::get('reference-map', ReferenceMapController::class)->middleware('throttle:map-read')->name('reference_map');
    Route::post('walking-routes', [WalkingRouteController::class, 'store'])->middleware('throttle:walking-routes')->name('walking_routes.store');

    Route::middleware('auth')->group(function (): void {
        Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:report-submission')->name('reports.store');
        Route::get('moderation/reports', [ReportModerationController::class, 'index'])->name('moderation.index');
        Route::patch('moderation/reports/{report}', [ReportModerationController::class, 'update'])->name('moderation.update');
        Route::get('report-attachments/{attachment}', [ReportAttachmentController::class, 'show'])->name('attachments.show');
        Route::apiResource('emergency-contacts', EmergencyContactController::class)->parameters(['emergency-contacts' => 'contact'])->except('show');
        Route::post('sos', [SosController::class, 'store'])->middleware('throttle:sos-preparation')->name('sos.store');
    });
});
