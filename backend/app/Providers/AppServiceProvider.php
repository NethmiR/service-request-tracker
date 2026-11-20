<?php

// Registers application wide services and boot time configuration.
// Defines the central password policy, the rate limiters, the authorization policies and the strict model and database safeguards.

namespace App\Providers;

use App\Data\Sessions\ActiveSession;
use App\Enums\RateLimiterName;
use App\Policies\SessionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public const int MINIMUM_PASSWORD_LENGTH = 12;

    public function register(): void {}

    public function boot(): void
    {
        Password::defaults(fn (): Password => $this->passwordPolicy());

        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Sanctum::getAccessTokenFromRequestUsing(fn (): ?string => null);

        $this->configureRateLimiting();

        $this->configureAuthorization();
    }

    private function passwordPolicy(): Password
    {
        $policy = Password::min(self::MINIMUM_PASSWORD_LENGTH)
            ->mixedCase()
            ->numbers()
            ->symbols();

        return $this->app->isProduction() ? $policy->uncompromised() : $policy;
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            RateLimiterName::Api->value,
            fn (Request $request): Limit => Limit::perMinute((int) config('rate_limits.api.max_requests_per_minute'))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
        );

        RateLimiter::for(
            RateLimiterName::PasswordChange->value,
            fn (Request $request): Limit => Limit::perMinute((int) config('rate_limits.password_change.max_attempts_per_minute'))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
        );
    }

    private function configureAuthorization(): void
    {
        Gate::policy(ActiveSession::class, SessionPolicy::class);
    }
}
