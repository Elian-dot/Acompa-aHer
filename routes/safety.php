<?php

use App\Http\Controllers\Api\V1\CommunityStatisticsController;
use App\Http\Controllers\Api\V1\EmergencyContactController;
use App\Http\Controllers\Api\V1\HourlyIncidenceController;
use App\Http\Controllers\Api\V1\JourneyController;
use App\Http\Controllers\Api\V1\JourneyParticipantController;
use App\Http\Controllers\Api\V1\MapController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\ReferenceMapController;
use App\Http\Controllers\Api\V1\ReportAttachmentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportModerationController;
use App\Http\Controllers\Api\V1\SosController;
use App\Http\Controllers\Api\V1\WalkingRouteController;
use App\Http\Controllers\SafetyPageController;
use App\Http\Controllers\TrackingController;
use App\Http\Middleware\PreventSensitiveResponseCaching;
use Illuminate\Support\Facades\Route;

Route::get('mapa', [SafetyPageController::class, 'map'])->name('public.mapa');
Route::get('rutas', [SafetyPageController::class, 'routes'])->name('public.rutas');

Route::get('seguimiento/{token}', [TrackingController::class, 'page'])
    ->middleware(PreventSensitiveResponseCaching::class)->name('tracking.page');

Route::middleware(['auth', PreventSensitiveResponseCaching::class])->group(function (): void {
    Route::get('contactos', [SafetyPageController::class, 'contacts'])->name('contacts');
    Route::get('moderacion', [SafetyPageController::class, 'moderation'])->name('moderation');
});

Route::prefix('api/v1')->name('api.v1.')->middleware([PreventSensitiveResponseCaching::class])->group(function (): void {
    Route::get('map', [MapController::class, 'index'])->middleware('throttle:map-read')->name('map.index');
    Route::get('reference-map', ReferenceMapController::class)->middleware('throttle:map-read')->name('reference_map');
    Route::get('hourly-incidence', HourlyIncidenceController::class)->middleware('throttle:map-read')->name('hourly_incidence');
    Route::get('community-statistics', CommunityStatisticsController::class)->middleware('throttle:map-read')->name('community_statistics');
    Route::post('walking-routes', [WalkingRouteController::class, 'store'])->middleware('throttle:walking-routes')->name('walking_routes.store');

    Route::prefix('tracking/{token}')->name('tracking.')->middleware('throttle:tracking')->group(function (): void {
        Route::get('/', [TrackingController::class, 'state'])->name('show');
        Route::post('acknowledge', [TrackingController::class, 'acknowledge'])->name('acknowledge');
        Route::post('push-subscription', [TrackingController::class, 'subscribe'])->name('push_subscription.store');
        Route::delete('push-subscription', [TrackingController::class, 'unsubscribe'])->name('push_subscription.destroy');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('journeys/current', [JourneyController::class, 'current'])->name('journeys.current');
        Route::post('journeys', [JourneyController::class, 'store'])->middleware('throttle:journey-start')->name('journeys.store');
        Route::post('journeys/{journey}/locations', [JourneyController::class, 'location'])->middleware('throttle:journey-location')->name('journeys.locations.store');
        Route::post('journeys/{journey}/activity', [JourneyController::class, 'activity'])->name('journeys.activity.store');
        Route::post('journeys/{journey}/finish', [JourneyController::class, 'finish'])->name('journeys.finish');
        Route::delete('journeys/{journey}/participants/{participant}', [JourneyParticipantController::class, 'destroy'])->scopeBindings()->name('journeys.participants.destroy');
        Route::post('journeys/{journey}/participants/{participant}/renew', [JourneyParticipantController::class, 'renew'])->scopeBindings()->name('journeys.participants.renew');
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push_subscriptions.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push_subscriptions.destroy');

        Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:report-submission')->name('reports.store');
        Route::get('moderation/reports', [ReportModerationController::class, 'index'])->name('moderation.index');
        Route::patch('moderation/reports/{report}', [ReportModerationController::class, 'update'])->name('moderation.update');
        Route::get('report-attachments/{attachment}', [ReportAttachmentController::class, 'show'])->name('attachments.show');
        Route::apiResource('emergency-contacts', EmergencyContactController::class)->parameters(['emergency-contacts' => 'contact'])->except('show');
        Route::post('sos', [SosController::class, 'store'])->middleware('throttle:sos-preparation')->name('sos.store');
    });
});
