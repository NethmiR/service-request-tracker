<?php

// Registers application wide services and boot time configuration.
// Later steps add the password policy and strict model behaviour here.

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
