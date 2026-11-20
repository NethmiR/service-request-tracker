<?php

// Lists the roles a user can hold in the system.
// The frontend mirrors these values in enums/user-role.ts.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum UserRole: string
{
    use ProvidesValues;

    case Admin = 'ADMIN';
    case Technician = 'TECHNICIAN';
    case Requester = 'REQUESTER';
}
