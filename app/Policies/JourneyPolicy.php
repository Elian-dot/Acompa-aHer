<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Journey;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JourneyPolicy
{
    /** Iniciar o consultar el propio acompañamiento. */
    public function start(User $user): bool
    {
        return $user->can(PermissionName::StartJourney->value) && $user->can(PermissionName::ShareJourney->value);
    }

    public function update(User $user, Journey $journey): Response
    {
        if ($journey->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $this->start($user) ? Response::allow() : Response::deny();
    }
}
