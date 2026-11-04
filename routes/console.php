<?php

use App\Models\Journey;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('model:prune', ['--model' => [Journey::class]])
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Delete journeys and dependent data 24 hours after closure');
