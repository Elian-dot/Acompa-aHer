<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function create(User $user): bool
    {
        return $user->can(PermissionName::CreateReport->value);
    }

    public function moderate(User $user, ?Report $report = null): bool
    {
        return $user->can(PermissionName::ModerateReport->value);
    }
}
