<?php

// Lists the roles a user can hold in the system and groups the staff roles of the IT Support division.
// The frontend mirrors these values in enums/user-role.ts and the staff group in STAFF_ROLES.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum UserRole: string
{
    use ProvidesValues;

    case Admin = 'ADMIN';
    case Technician = 'TECHNICIAN';
    case Requester = 'REQUESTER';

    public static function staff(): array
    {
        return [self::Admin, self::Technician];
    }
}
