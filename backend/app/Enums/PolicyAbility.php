<?php

// Lists the policy ability names checked through the Gate.
// These values never leave the backend, and later phases add their abilities here.

namespace App\Enums;

use App\Enums\Concerns\ProvidesValues;

enum PolicyAbility: string
{
    use ProvidesValues;

    case Delete = 'delete';
    case View = 'view';
}
