<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmergencyContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageEmergencyContacts->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, EmergencyContact $contact): Response
    {
        return $contact->user_id === $user->id
            ? ($this->viewAny($user) ? Response::allow() : Response::deny())
            : Response::denyAsNotFound();
    }

    public function delete(User $user, EmergencyContact $contact): Response
    {
        return $this->update($user, $contact);
    }
}
