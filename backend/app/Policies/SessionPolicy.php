<?php

// Decides which users may act on a stored session.
// The owner may end their own session, and any user who passes the manage users Gate may end any session.

namespace App\Policies;

use App\Data\Sessions\ActiveSession;
use App\Enums\GateAbility;
use App\Models\User;

final class SessionPolicy
{
    public function delete(User $actor, ActiveSession $session): bool
    {
        return $session->userId === (int) $actor->getKey()
            || $actor->can(GateAbility::ManageUsers->value);
    }
}
