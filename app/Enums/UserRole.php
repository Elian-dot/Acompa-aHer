<?php

namespace App\Enums;

enum UserRole: string
{
    case RegisteredUser = 'registered_user';
    case CommunityModerator = 'community_moderator';
    case MunicipalAdministrator = 'municipal_administrator';

    /**
     * @return list<PermissionName>
     */
    public function permissions(): array
    {
        $registeredUserPermissions = [
            PermissionName::CreateReport,
            PermissionName::StartJourney,
            PermissionName::ShareJourney,
            PermissionName::ManageEmergencyContacts,
        ];

        return match ($this) {
            self::RegisteredUser => $registeredUserPermissions,
            self::CommunityModerator => [
                ...$registeredUserPermissions,
                PermissionName::ModerateReport,
                PermissionName::ViewDisaggregatedStatistics,
            ],
            self::MunicipalAdministrator => [
                ...$registeredUserPermissions,
                PermissionName::ViewDisaggregatedStatistics,
                PermissionName::ExportStatistics,
                PermissionName::ReceiveCriticalZoneAlerts,
            ],
        };
    }
}
