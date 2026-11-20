<?php

// Registers application wide services and boot time configuration.
// Defines the central password policy and the strict model and database safeguards.

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public const int MINIMUM_PASSWORD_LENGTH = 12;

    public function register(): void {}

    public function boot(): void
    {
        Password::defaults(fn (): Password => $this->passwordPolicy());

        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    private function passwordPolicy(): Password
    {
        $policy = Password::min(self::MINIMUM_PASSWORD_LENGTH)
            ->mixedCase()
            ->numbers()
            ->symbols();

        return $this->app->isProduction() ? $policy->uncompromised() : $policy;
    }
}
