<?php

// Makes feature tests behave like the browser frontend that uses Sanctum cookie authentication.
// Requests are stateful, cookies are carried by hand and every request starts from a clean in memory auth and session state.

namespace Tests\Concerns;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

trait InteractsWithFrontendSession
{
    protected const string FRONTEND_HOST = 'frontend.test';

    protected const string CSRF_COOKIE_ROUTE = '/sanctum/csrf-cookie';

    protected const string SIGN_IN_ROUTE = '/api/auth/sign-in';

    protected function setUpInteractsWithFrontendSession(): void
    {
        config(['sanctum.stateful' => [self::FRONTEND_HOST]]);

        $this->withHeaders([
            'Origin' => 'http://'.self::FRONTEND_HOST,
            'Referer' => 'http://'.self::FRONTEND_HOST,
        ]);
        $this->withCredentials();
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->resetInMemoryAuthenticationState();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function rememberSessionCookie(TestResponse $response): void
    {
        $cookie = $response->getCookie(config('session.cookie'), false);

        if ($cookie instanceof Cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
    }

    protected function currentSessionCookieValue(): ?string
    {
        return $this->unencryptedCookies[config('session.cookie')] ?? null;
    }

    protected function useSessionCookieValue(string $value): void
    {
        $this->withUnencryptedCookie(config('session.cookie'), $value);
    }

    protected function switchToFreshClient(): void
    {
        $this->unencryptedCookies = [];
        $this->defaultCookies = [];
    }

    protected function startGuestSession(): TestResponse
    {
        $response = $this->getJson(self::CSRF_COOKIE_ROUTE);

        $this->rememberSessionCookie($response);

        return $response;
    }

    protected function signInThroughApi(User $user, string $password = UserFactory::DEFAULT_PASSWORD): TestResponse
    {
        $this->startGuestSession();

        $response = $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $this->rememberSessionCookie($response);

        return $response;
    }

    private function resetInMemoryAuthenticationState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
