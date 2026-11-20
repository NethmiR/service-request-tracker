<?php

// Decides which users may act on a stored session.
// In this phase only the owner of a session may end it.

namespace App\Policies;

use App\Data\Sessions\ActiveSession;
use App\Models\User;

final class SessionPolicy
{
    public function delete(User $actor, ActiveSession $session): bool
    {
        return $session->userId === (int) $actor->getKey();
    }
}
