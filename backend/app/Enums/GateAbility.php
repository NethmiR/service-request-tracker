<?php

// Names the role based Gates registered in the application service provider.
// Record level abilities checked through policies stay in PolicyAbility.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum GateAbility: string
{
    use ProvidesValues;

    case ManageUsers = 'manage-users';
    case ViewAllServiceRequests = 'view-all-service-requests';
}
