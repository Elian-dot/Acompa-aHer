<?php

namespace App\Enums;

enum PermissionName: string
{
    case CreateReport = 'report:create';
    case StartJourney = 'journey:start';
    case ShareJourney = 'journey:share';
    case ManageEmergencyContacts = 'emergency-contact:manage';
    case ModerateReport = 'report:moderate';
    case ViewDisaggregatedStatistics = 'statistics:view-disaggregated';
    case ExportStatistics = 'statistics:export';
    case ReceiveCriticalZoneAlerts = 'zone-alert:receive';
}
