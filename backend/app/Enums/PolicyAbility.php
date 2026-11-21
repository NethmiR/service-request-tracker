<?php

// Lists the policy ability names checked through the Gate.
// These values never leave the backend, and a hyphenated value maps to the camel case policy method.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum PolicyAbility: string
{
    use ProvidesValues;

    case Delete = 'delete';
    case View = 'view';
    case Reassign = 'reassign';
    case ChangeStatus = 'change-status';
}
