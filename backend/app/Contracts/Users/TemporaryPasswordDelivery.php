<?php

// Hands a newly issued temporary password to its recipient.
// The returned value is shown once to the admin, and an email implementation returns null after sending.

namespace App\Contracts\Users;

use App\Models\User;

interface TemporaryPasswordDelivery
{
    public function deliver(User $user, string $temporaryPassword): ?string;
}
