<?php

// Delivers a temporary password by returning it so the admin sees it exactly once.
// Exists only until email integration is added, as section 3.6 of the main plan requires.

namespace App\Services\Users;

use App\Contracts\Users\TemporaryPasswordDelivery;
use App\Models\User;

final class DisplayOnceTemporaryPasswordDelivery implements TemporaryPasswordDelivery
{
    public function deliver(User $user, string $temporaryPassword): ?string
    {
        return $temporaryPassword;
    }
}
