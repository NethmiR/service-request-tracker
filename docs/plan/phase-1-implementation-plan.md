# Phase 1 Implementation Plan. Foundation and Shared Infrastructure

## 1. Purpose

This document breaks Phase 1 of the [implementation plan](implementation-plan.md) into ordered, verifiable steps. Phase 1 sets up the conventions and plumbing every later phase relies on. It adds no business features, no database tables and no screens beyond a temporary component preview.

Every step follows the rules of the main plan, in particular sections 5 (Security Requirements) and 6 (Engineering Standards). Where this document and the main plan disagree, the main plan wins and this document must be corrected.

## 2. Confirmed Decisions

These decisions were confirmed before writing this plan and apply to every phase.

| Topic | Decision |
| --- | --- |
| Backend test database | A dedicated local PostgreSQL database named `service_request_tracker_test`. SQLite is not used, because it cannot prove the check constraints and row locking required later. |
| Frontend Content Security Policy | A fresh nonce per request generated in `proxy.ts`, as recommended by the Next.js 16 documentation. All pages render dynamically. |
| Base UI components | Built by hand with Tailwind CSS 4 and the native `<dialog>` element, with `clsx` and `tailwind-merge` for class names. No component library. |
| PHP target | PHP 8.3. Composer is pinned to the 8.3 platform even though local development runs PHP 8.5 through Herd. Code must not use PHP 8.4 or 8.5 features. |
| JSON key casing | snake_case end to end. API data properties are snake_case on both sides and frontend types mirror the wire format with no transformation. TypeScript variables, functions and components stay camelCase or PascalCase. |

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Enum case naming | PHP and TypeScript enum cases use PascalCase names with uppercase values, for example `InProgress = 'IN_PROGRESS'`. Tag values are `HARDWARE`, `SOFTWARE`, `NETWORK` and `OTHER`. Display labels live in frontend constants. |
| Error codes | Phase 1 creates the generic codes and the conflict codes already defined in section 5.8. Codes that belong to a single later feature, such as invalid credentials, are added in the phase that introduces them, on both sides at the same time. |
| Frontend only error code | There is none. Network failures on the frontend reuse `SERVICE_UNAVAILABLE`, which the backend also returns in maintenance mode, so the enums stay identical. |
| Mirrored enums | The frontend mirrors every enum that crosses the API boundary, namely roles, statuses, tags and error codes. Audit events never leave the backend, so the frontend does not mirror them. |
| HTTP methods | The API uses GET, POST, PATCH and DELETE only. Updates use PATCH and there is no PUT. |
| Success envelope | Laravel API Resources keep their default `data` wrapper, so paginated responses carry `links` and `meta`. |
| Theme | Light theme only for this version. |
| Sanctum tokens | Only SPA cookie mode is used. The personal access tokens migration is not published and the `HasApiTokens` trait is not used. |
| Notifications context | The app wide notification context and toast region are built in Phase 1 as shared plumbing. The auth context stays in Phase 2. |

## 3. Current State of the Code

The following was found in the repository before Phase 1 starts.

### 3.1 Backend

- Laravel 13.43 is installed. API routing is wired in [bootstrap/app.php](../../backend/bootstrap/app.php) and [routes/api.php](../../backend/routes/api.php) is empty.
- Sanctum is not installed and `statefulApi` is not enabled.
- [composer.lock](../../backend/composer.lock) was resolved on PHP 8.5 and pins Symfony 8.1 components that require PHP 8.4.1 or newer. As it stands, the project cannot be installed on a PHP 8.3 server.
- [config/cors.php](../../backend/config/cors.php) already limits origins to `FRONTEND_URL` with credentials, but allows every method and header.
- [config/session.php](../../backend/config/session.php) defaults to the database driver, but `.env.example` sets `SESSION_DRIVER=file`. Payload encryption is off by default and the secure cookie flag has no default.
- [config/database.php](../../backend/config/database.php) defaults `sslmode` to `prefer`, so SSL is not guaranteed when the variable is missing.
- The exception handler only forces JSON rendering for `api/*`. It does not produce the shared error shape.
- There are no migrations, models, enums, middleware, factories or tests. The `tests/Feature` and `tests/Unit` folders referenced by [phpunit.xml](../../backend/phpunit.xml) do not exist.
- [config/auth.php](../../backend/config/auth.php) and [DatabaseSeeder.php](../../backend/database/seeders/DatabaseSeeder.php) reference `App\Models\User`, which does not exist yet. The model is created in Phase 2.
- [phpunit.xml](../../backend/phpunit.xml) uses SQLite in memory.
- Config files, the service provider, the base controller and the seeder still contain the default Laravel comments, which section 6.3 forbids.
- [composer.json](../../backend/composer.json) still carries the skeleton name and description.

### 3.2 Frontend

- Next.js 16.4.6 with React 19.3, TypeScript strict mode, Tailwind CSS 4 and the Next ESLint presets.
- [app/page.tsx](../../frontend/app/page.tsx) and [app/layout.tsx](../../frontend/app/layout.tsx) are the create-next-app boilerplate, and `public/` holds the boilerplate SVG files.
- Axios is not installed. There is no folder structure, no enums, no constants and no security headers.
- [.gitignore](../../frontend/.gitignore) ignores `.env*`, so [.env.example](../../frontend/.env.example) is never committed.
- [tsconfig.json](../../frontend/tsconfig.json) has `allowJs` enabled.
- In Next.js 16, Middleware is renamed Proxy. The file is `proxy.ts` and it exports a `proxy` function. The bundled guides in `node_modules/next/dist/docs/` are the reference for this version, as [AGENTS.md](../../frontend/AGENTS.md) requires.

## 4. Conventions Applied in This Phase

### 4.1 File Header Comments

Every source file starts with a two to three line comment describing its purpose, and contains no other comments. This applies to new files and to existing files touched in this phase.

PHP files place the header after the opening tag and a blank line.

```php
<?php

// Maps every exception to the shared JSON error shape.
// This is the only place where exceptions become HTTP responses.

namespace App\Exceptions;
```

TypeScript files place the header on the first lines, before any `'use client'` directive or import.

```ts
// Creates the shared Axios instance used by every feature service.
// Configures credentials, the XSRF header and the error interceptors.
```

### 4.2 Naming

- PHP classes, enums and enum cases use PascalCase. Methods and variables use camelCase. Test methods use snake_case with a `test_` prefix, following Laravel convention.
- TypeScript files and folders use kebab-case. Components, classes, enums and types use PascalCase. Functions and variables use camelCase.
- Names use full words. For example `ServiceRequestStatus`, not `SrStatus`, and `mergeClassNames`, not `cn`.

### 4.3 Text

All code, messages and content are plain. No em dashes, no emojis, no decorative punctuation.

## 5. Backend Steps

All commands run from the `backend` folder. On this machine `php` and `composer` come from Herd.

### Step B1. Pin the PHP platform and rename the package

1. Run `composer config platform.php 8.3.0`. This makes Composer resolve dependencies as if PHP 8.3 were installed.
2. Run `composer update`. Composer moves the Symfony components from 8.1 to the newest 7.x release that supports PHP 8.3. Laravel 13 accepts both.
3. Run `composer check-platform-reqs --lock` and confirm nothing requires PHP 8.4 or newer.
4. In [composer.json](../../backend/composer.json), change `name` to `nnr-co/service-request-tracker-api` and `description` to `IT Service Request Tracker REST API for NNR Co.` Remove the unused `pestphp/pest-plugin` entry from `allow-plugins`.
5. Add a `lint` script that runs `pint --test`.

**Check.** `composer install` succeeds on a clean checkout and `php artisan --version` reports Laravel 13.

### Step B2. Bring existing files in line with the comment standard

Remove every default comment and add a header comment to each of these files. Keep their behaviour unchanged unless a later step says otherwise.

- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Controller.php`
- `bootstrap/app.php` and `bootstrap/providers.php`
- `routes/api.php` and `routes/console.php`. Remove the skeleton `inspire` command so that `console.php` holds only its header.
- Every file in `config/`
- `database/seeders/DatabaseSeeder.php`. Remove the unused imports. Phase 2 fills in the `run` method.
- `tests/TestCase.php`
- `public/index.php` and `artisan`

**Check.** A search for `/**`, `/*` and `//` in `app`, `bootstrap`, `config`, `database`, `routes`, `tests` and `public` returns only the header comments.

### Step B3. Install and configure Sanctum for SPA cookie authentication

1. Run `composer require laravel/sanctum`.
2. Publish only the configuration with `php artisan vendor:publish --tag=sanctum-config`. Do not publish the personal access tokens migration.
3. Configure `config/sanctum.php`.
   - `stateful` reads a comma separated list from `SANCTUM_STATEFUL_DOMAINS` with no hardcoded fallback hosts.
   - `guard` is `['web']`.
   - `expiration` is `null`, because the session lifetime controls expiry.
   - `token_prefix` is an empty string.
   - Keep the default `middleware` entries. Sanctum's `authenticate_session` middleware is what logs out other sessions when a password hash changes, which Phase 2 relies on.
4. In `bootstrap/app.php`, call `$middleware->statefulApi()`. Requests from the frontend origin then get cookies, the session and CSRF protection, while other API requests stay stateless.

Sanctum registers `GET /sanctum/csrf-cookie` automatically. It needs the sessions table, so it works from Phase 2 onward (see section 8).

### Step B4. Configure sessions and cookies

Update `config/session.php` so the defaults are secure even when an environment variable is missing.

| Key | Value |
| --- | --- |
| `driver` | `env('SESSION_DRIVER', 'database')` |
| `lifetime` | `(int) env('SESSION_LIFETIME', 120)` |
| `expire_on_close` | `false` |
| `encrypt` | `env('SESSION_ENCRYPT', true)` |
| `cookie` | `env('SESSION_COOKIE', 'service_request_tracker_session')` |
| `domain` | `env('SESSION_DOMAIN')` |
| `secure` | `env('SESSION_SECURE_COOKIE', true)` |
| `http_only` | `true`, with no environment override |
| `same_site` | `env('SESSION_SAME_SITE', 'lax')` |
| `partitioned` | `false` |
| `serialization` | `json`, as already set |

Locally, `SESSION_DOMAIN` stays empty. The cookie is then host only for `localhost`, and because cookies ignore ports, the frontend on `localhost:3000` still sends it and can read `XSRF-TOKEN`. In production, `SESSION_DOMAIN` is set to the shared parent domain, for example `.example.com`.

### Step B5. Restrict CORS

Update `config/cors.php`.

| Key | Value |
| --- | --- |
| `paths` | `['api/*', 'sanctum/csrf-cookie']` |
| `allowed_methods` | `['GET', 'POST', 'PATCH', 'DELETE']` |
| `allowed_origins` | `[env('FRONTEND_URL')]` |
| `allowed_origins_patterns` | `[]` |
| `allowed_headers` | `['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN']` |
| `exposed_headers` | `['Retry-After']`, so the frontend can read the retry time of a 429 response |
| `max_age` | `7200` |
| `supports_credentials` | `true` |

### Step B6. Require SSL for the database

In `config/database.php`, change the `sslmode` default from `prefer` to `require`. Only the local test environment overrides it, in Step B13.

### Step B7. Create the shared enums

Create these files in `app/Enums`. Every enum is string backed and uses the `ProvidesValues` trait.

**`app/Enums/Concerns/ProvidesValues.php`** is a trait with a static `values(): array` method that returns the list of backing values. Later phases use it for validation rules and check constraints.

**`app/Enums/UserRole.php`**

| Case | Value |
| --- | --- |
| `Admin` | `ADMIN` |
| `Technician` | `TECHNICIAN` |
| `Requester` | `REQUESTER` |

**`app/Enums/ServiceRequestStatus.php`**

| Case | Value |
| --- | --- |
| `Open` | `OPEN` |
| `Assigned` | `ASSIGNED` |
| `InProgress` | `IN_PROGRESS` |
| `Resolved` | `RESOLVED` |

Transition rules are added to this enum in Phase 6, not now.

**`app/Enums/ServiceRequestTag.php`**

| Case | Value |
| --- | --- |
| `Hardware` | `HARDWARE` |
| `Software` | `SOFTWARE` |
| `Network` | `NETWORK` |
| `Other` | `OTHER` |

**`app/Enums/AuthAuditEvent.php`** holds the events listed in section 3.5.

| Case | Value |
| --- | --- |
| `SignInSucceeded` | `SIGN_IN_SUCCEEDED` |
| `SignInFailed` | `SIGN_IN_FAILED` |
| `SignInThrottled` | `SIGN_IN_THROTTLED` |
| `SignedOut` | `SIGNED_OUT` |
| `PasswordChanged` | `PASSWORD_CHANGED` |
| `PasswordResetByAdmin` | `PASSWORD_RESET_BY_ADMIN` |
| `UserCreatedByAdmin` | `USER_CREATED_BY_ADMIN` |
| `SessionClearedByOwner` | `SESSION_CLEARED_BY_OWNER` |
| `SessionClearedByAdmin` | `SESSION_CLEARED_BY_ADMIN` |

**`app/Enums/ErrorCode.php`** holds every error code, together with the HTTP status for each one. The `httpStatus(): int` method returns the status from the table below, using the `Symfony\Component\HttpFoundation\Response` status constants rather than raw numbers. The `message(): string` method returns the translated message from `lang/en/errors.php`.

| Case | Value | HTTP status |
| --- | --- | --- |
| `BadRequest` | `BAD_REQUEST` | 400 |
| `Unauthenticated` | `UNAUTHENTICATED` | 401 |
| `Forbidden` | `FORBIDDEN` | 403 |
| `PasswordChangeRequired` | `PASSWORD_CHANGE_REQUIRED` | 403 |
| `NotFound` | `NOT_FOUND` | 404 |
| `MethodNotAllowed` | `METHOD_NOT_ALLOWED` | 405 |
| `Conflict` | `CONFLICT` | 409 |
| `InvalidStatusTransition` | `INVALID_STATUS_TRANSITION` | 409 |
| `StatusUnchanged` | `STATUS_UNCHANGED` | 409 |
| `ConcurrentUpdate` | `CONCURRENT_UPDATE` | 409 |
| `CsrfTokenMismatch` | `CSRF_TOKEN_MISMATCH` | 419 |
| `ValidationFailed` | `VALIDATION_FAILED` | 422 |
| `TooManyRequests` | `TOO_MANY_REQUESTS` | 429 |
| `ServerError` | `SERVER_ERROR` | 500 |
| `ServiceUnavailable` | `SERVICE_UNAVAILABLE` | 503 |

**`lang/en/errors.php`** maps each error code value to a short, plain, user facing message. For example, `NOT_FOUND` maps to `The requested resource was not found.` and `SERVER_ERROR` maps to `Something went wrong. Please try again later.` No message reveals internal details.

### Step B8. Build the JSON error shape and the base domain exception

**The error shape.** Every failure returns this body and nothing else.

```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_FAILED",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

`message` and `code` are always present. `errors` is present only for validation failures and maps each field name to its list of messages.

**`app/Http/Responses/ApiErrorResponse.php`** is a final class that extends `Illuminate\Http\JsonResponse`. Its constructor takes an `ErrorCode`, an optional array of field errors and optional extra headers. It builds the body above, using the code's message and HTTP status. This class is the only place where the error body is assembled.

**`app/Exceptions/DomainException.php`** is an abstract class that extends `RuntimeException`. It declares `abstract public function errorCode(): ErrorCode` and uses the code's message as the exception message. Every business rule violation in later phases, such as an invalid status transition, extends this class.

### Step B9. Build the central exception renderer

**`app/Exceptions/ApiExceptionRenderer.php`** is a final class with one public method, `render(Throwable $exception): ApiErrorResponse`. Laravel converts some exceptions before render callbacks run. For example, a missing model becomes `NotFoundHttpException`, a failed policy becomes `AccessDeniedHttpException` and a CSRF mismatch becomes an `HttpException` with status 419. The mapping below works on those converted exceptions.

| Exception | Error code | Notes |
| --- | --- | --- |
| `Illuminate\Http\Exceptions\HttpResponseException` | none | Returns `null` so Laravel sends the prepared response unchanged. |
| `App\Exceptions\DomainException` | `$exception->errorCode()` | |
| `Illuminate\Auth\AuthenticationException` | `Unauthenticated` | |
| `Illuminate\Validation\ValidationException` | `ValidationFailed` | Field errors from `$exception->errors()`. |
| Any `HttpExceptionInterface` with status 403 | `Forbidden` | |
| Status 404 | `NotFound` | |
| Status 405 | `MethodNotAllowed` | Keeps the `Allow` header. |
| Status 409 | `Conflict` | |
| Status 419 | `CsrfTokenMismatch` | |
| Status 429 | `TooManyRequests` | Keeps the `Retry-After` and rate limit headers. |
| Status 503 | `ServiceUnavailable` | Covers maintenance mode. |
| Any other 4xx status | `BadRequest` | |
| Anything else | `ServerError` | Always the generic message, even when debug mode is on. |

The `HttpResponseException` row means the method actually returns `?ApiErrorResponse`. The status to code mapping is a single `match` expression inside this class. Headers of an `HttpExceptionInterface` are passed through to the response. No response ever contains a stack trace, exception class, file or line, in any environment. Developers read details in the log, which is still written through normal exception reporting.

**Register the renderer** in `bootstrap/app.php`.

- Replace the current `shouldRenderJsonWhen` closure with one render callback that resolves `ApiExceptionRenderer` from the container and calls it for every `Throwable`.
- Call `dontReport` with `DomainException::class`, because business rule violations are expected outcomes and not errors.
- Call `dontFlash` with `current_password`, `password` and `password_confirmation`. These are the only password field names the API uses, which satisfies section 5.13.

### Step B10. Force JSON for every request

**`app/Http/Middleware/EnsureJsonResponse.php`** sets the `Accept` header of every incoming request to `application/json`. Register it with `$middleware->prepend()` so it runs globally and first.

Without it, a request that does not ask for JSON would make Laravel try to redirect a guest to a `login` route that does not exist, which would produce a 500 error instead of a 401. With it, validation, authentication and errors always behave as an API.

### Step B11. Add the security headers middleware

**`app/Http/Middleware/AddSecurityHeaders.php`** adds these headers to every response. Header names and values are class constants.

| Header | Value |
| --- | --- |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=()` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains`, sent only when the application runs in production |

The middleware also calls `header_remove('X-Powered-By')`, so PHP does not reveal its version. Register it globally with `$middleware->append()`. Hiding the web server version is a server setting and is verified in Phase 8.

### Step B12. Define the central password policy and strict model behaviour

In `AppServiceProvider::boot`, do the following.

1. Define the one password policy with `Password::defaults()`. The minimum length is 12, held in a named class constant, and the rule requires mixed case, numbers and symbols. In production it also calls `uncompromised()` to check against known breached passwords. Every later rule that validates a password uses `Password::defaults()`.
2. Call `Model::shouldBeStrict(! $this->app->isProduction())`. Outside production this makes lazy loading, unknown attributes and silently discarded attributes throw, which catches N+1 problems early, as section 6.1 requires.
3. Call `DB::prohibitDestructiveCommands($this->app->isProduction())`, so commands such as `migrate:fresh` cannot run against production.

### Step B13. Configure the environment files and the test database

**`backend/.env.example`** holds placeholders only.

```dotenv
APP_NAME="NNR Co Service Request Tracker"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=your-database-host
DB_PORT=5432
DB_DATABASE=your-database-name
DB_USERNAME=your-database-username
DB_PASSWORD=your-database-password
DB_SSLMODE=require

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_COOKIE=service_request_tracker_session
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=localhost:3000

BCRYPT_ROUNDS=12
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Update the existing local `backend/.env` with the same keys, keeping its real database credentials. `SESSION_SECURE_COOKIE` is `false` only because local development runs over HTTP. Production sets it to `true`, along with `APP_ENV=production` and `APP_DEBUG=false`.

**Test database.**

1. Create the database with `createdb -U postgres service_request_tracker_test`.
2. Add `.env.testing` to `backend/.gitignore`.
3. Create `backend/.env.testing.example` with placeholders for `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=service_request_tracker_test`, `DB_USERNAME`, `DB_PASSWORD` and `DB_SSLMODE=disable`. Each developer copies it to `.env.testing`. SSL is disabled only for this local test database. Every other environment keeps `require`.
4. In [phpunit.xml](../../backend/phpunit.xml), remove the `DB_CONNECTION`, `DB_DATABASE` and `DB_URL` entries so the values come from `.env.testing`. Laravel loads that file automatically when `APP_ENV` is `testing`. Keep `SESSION_DRIVER=array` for now. Phase 2 switches it to `database` together with the sessions migration.
5. Create the `tests/Feature` and `tests/Unit` folders through the tests in Step B14.

### Step B14. Write the Phase 1 tests

All tests extend `Tests\TestCase`. Routes that exist only for a test are registered inside that test's `setUp` method and never in `routes/api.php`.

**`tests/Feature/Infrastructure/ErrorResponseTest.php`**

- An unknown API route returns 404 with exactly the keys `message` and `code`, and the code is `NOT_FOUND`.
- An unknown route outside `/api` also returns the JSON error shape.
- A POST to the GET only `/up` route returns 405 with `METHOD_NOT_ALLOWED` and an `Allow` header.
- A test route that throws a `DomainException` subclass returns that subclass's code and HTTP status.
- A test route that fails validation returns 422 with `VALIDATION_FAILED` and the field errors under `errors`.
- A test route protected by `auth:sanctum`, called without a session, returns 401 with `UNAUTHENTICATED`.
- A test route with `throttle:1,1`, called twice, returns 429 with `TOO_MANY_REQUESTS` and a `Retry-After` header.
- A test route that throws a `RuntimeException`, with debug mode switched on, returns 500 with `SERVER_ERROR`, the generic message and no `exception`, `file`, `line` or `trace` keys.
- A request sent without an `Accept` header still receives JSON.

**`tests/Feature/Infrastructure/SecurityHeadersTest.php`**

- Every response carries the four common security headers.
- `Strict-Transport-Security` is absent in the testing environment and present when the environment is switched to production.

**`tests/Feature/Infrastructure/CorsTest.php`**

- A preflight `OPTIONS` request from the configured frontend origin is allowed with credentials, and the response lists only the allowed methods and headers.
- A preflight from any other origin receives no `Access-Control-Allow-Origin` header.

**`tests/Feature/Infrastructure/PasswordPolicyTest.php`**

- Validating against `Password::defaults()` rejects passwords that are shorter than 12 characters or lack mixed case, a number or a symbol, and accepts a password that meets every rule.

**`tests/Unit/Enums/ErrorCodeTest.php`**

- Every `ErrorCode` case returns a status between 400 and 599 and a non empty message. This catches a new case added without a translation.

These Phase 1 tests do not touch the database, but the suite is already configured for PostgreSQL so Phase 2 can use `RefreshDatabase` straight away.

### Step B15. Format and verify

1. Run `vendor/bin/pint` and then `composer lint` to confirm formatting.
2. Run `composer test` and confirm every test passes.
3. Start the server with `php artisan serve` and run `curl -i http://localhost:8000/api/does-not-exist`. Confirm a 404 with the JSON error shape and the security headers.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before writing Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/01-getting-started/16-proxy.md`, `01-app/02-guides/content-security-policy.md`, `01-app/03-api-reference/03-file-conventions/error.md` and `not-found.md`.

### Step F1. Install dependencies and remove boilerplate

1. Run `npm install --save-exact axios clsx tailwind-merge`.
2. Delete `public/file.svg`, `public/globe.svg`, `public/next.svg`, `public/vercel.svg` and `public/window.svg`.
3. In [package.json](../../frontend/package.json), rename the package to `service-request-tracker-frontend` and add a `typecheck` script that runs `tsc --noEmit`.
4. In [.gitignore](../../frontend/.gitignore), add `!.env.example` below `.env*` so the example file is committed.

### Step F2. Tighten TypeScript

In [tsconfig.json](../../frontend/tsconfig.json), keep `strict` and add the following.

| Option | Value |
| --- | --- |
| `allowJs` | `false` |
| `noUncheckedIndexedAccess` | `true` |
| `noImplicitReturns` | `true` |
| `noImplicitOverride` | `true` |
| `noFallthroughCasesInSwitch` | `true` |
| `forceConsistentCasingInFileNames` | `true` |

### Step F3. Enforce the standards with ESLint

Extend [eslint.config.mjs](../../frontend/eslint.config.mjs), remove its default comments and add its header comment. Add these rules so that the rules of sections 5.12 and 6.3 are checked automatically rather than by memory.

| Rule | Purpose |
| --- | --- |
| `@typescript-eslint/no-explicit-any` set to error | No `any` types. |
| `no-restricted-syntax` with the `TSUnknownKeyword` selector | No written `unknown` types. Implicit catch variables remain allowed and are narrowed with `instanceof ApiError`. |
| `react/no-danger` set to error | Raw HTML rendering is never used. |
| `no-restricted-globals` and `no-restricted-properties` for `localStorage` and `sessionStorage` | Nothing is stored in browser storage. |
| `@typescript-eslint/consistent-type-imports` set to error | Type only imports stay type only. |

### Step F4. Create the feature based folder structure

The project keeps its current layout without a `src` folder. Each feature folder that holds no files yet gets an empty `.gitkeep` file so the structure is committed.

```text
frontend/
  app/                         routes, layouts and boundaries
  components/
    shared/                    base components reused by every feature
    auth/  users/  service-requests/  sessions/  overview/
  services/
    auth/  users/  service-requests/  sessions/  overview/
    shared/                    base service class
  hooks/
    shared/
    auth/  users/  service-requests/  sessions/  overview/
  types/
    shared/                    API envelopes and error types
    auth/  users/  service-requests/  sessions/  overview/
  contexts/                    shared React contexts
  enums/                       enums mirrored from the backend
    ui/                        frontend only enums for UI variants
  constants/                   routes, endpoints, labels and messages
  lib/
    http/                      Axios instance, errors and CSRF handling
    security/                  security headers and CSP builder
    utilities/                 small pure helpers
  config/                      validated environment values
  proxy.ts                     Next.js proxy
```

### Step F5. Validate environment configuration

1. Set [.env.example](../../frontend/.env.example) to `NEXT_PUBLIC_API_URL=http://localhost:8000`. Update the local `.env` with the same key.
2. Create `config/environment.ts`. It reads `process.env.NEXT_PUBLIC_API_URL` by its literal name, so Next.js can inline it, and throws a clear error at startup if the value is missing. It exports a frozen `environment` object with `apiBaseUrl` and `apiOrigin`. The CSP builder uses `apiOrigin`.

### Step F6. Mirror the backend enums

Create TypeScript string enums whose member names and values match the PHP enums from Step B7 exactly.

- `enums/user-role.ts`, holding `UserRole`
- `enums/service-request-status.ts`, holding `ServiceRequestStatus`
- `enums/service-request-tag.ts`, holding `ServiceRequestTag`
- `enums/error-code.ts`, holding `ErrorCode`

For example:

```ts
export enum ServiceRequestStatus {
  Open = 'OPEN',
  Assigned = 'ASSIGNED',
  InProgress = 'IN_PROGRESS',
  Resolved = 'RESOLVED',
}
```

Frontend only enums for UI variants live in `enums/ui/`. They are `ButtonVariant`, `ComponentSize`, `BadgeTone`, `AlertTone` and `NotificationType`.

### Step F7. Create the constants

- **`constants/routes.ts`** holds an `AppRoute` enum with every page path the main plan defines. It also exports one function, `serviceRequestDetailRoute(serviceRequestId: number): string`, for the dynamic detail path.

  | Member | Path |
  | --- | --- |
  | `Root` | `/` |
  | `SignIn` | `/sign-in` |
  | `ChangePassword` | `/change-password` |
  | `Overview` | `/overview` |
  | `ServiceRequests` | `/service-requests` |
  | `MyServiceRequests` | `/service-requests/mine` |
  | `NewServiceRequest` | `/service-requests/new` |
  | `Users` | `/users` |
  | `Account` | `/account` |

- **`constants/api-endpoints.ts`** holds an `ApiEndpoint` enum. Phase 1 adds only `CsrfCookie = '/sanctum/csrf-cookie'`. Each later phase adds its endpoints here in the same change as the matching backend routes.
- **`constants/http.ts`** holds an `HttpStatus` enum for the statuses the client reacts to (401, 403, 419, 422 and 429), an `HttpHeader` enum, the XSRF cookie and header names, and the request timeout in milliseconds.
- **`constants/enum-labels.ts`** holds the display labels as `Record<UserRole, string>`, `Record<ServiceRequestStatus, string>` and `Record<ServiceRequestTag, string>`. Because the records are typed, TypeScript reports any enum member that has no label.
- **`constants/error-messages.ts`** holds the UI message for each error code as a `Record<ErrorCode, string>`. The frontend owns its UI text. Backend messages are used only for field level validation errors.
- **`constants/ui-messages.ts`** holds the general UI text, such as loading, empty, retry, close and not found messages.
- **`constants/application.ts`** holds the application name and the page metadata description.

### Step F8. Define the shared types

These types live in `types/shared/` and mirror the backend wire format in snake_case.

- **`api-error-response.ts`** holds `ApiErrorResponse`, with `message: string`, `code: ErrorCode` and the optional `errors?: Record<string, string[]>`.
- **`api-resource-response.ts`** holds `ApiResourceResponse<Resource>`, with `data: Resource`.
- **`paginated-response.ts`** holds `PaginatedResponse<Resource>` with `data`, `links` and `meta`. `links` has `first`, `last`, `prev` and `next`, each `string | null`. `meta` has `current_page`, `from`, `last_page`, `path`, `per_page`, `to`, `total` and the `links` array. When the first paginated endpoint is built in Phase 5, these fields are checked against a real Laravel 13 response and corrected if they differ.

### Step F9. Build the HTTP layer

**`lib/http/api-error.ts`** holds the `ApiError` class, which extends `Error` and is the only error type the rest of the application sees. It exposes `status: number`, `code: ErrorCode`, `message: string` and `fieldErrors: Record<string, string[]>`, plus the helpers `firstFieldError(field: string): string | undefined` and `hasCode(code: ErrorCode): boolean`.

**`lib/http/create-api-error.ts`** converts an `AxiosError<ApiErrorResponse>` into an `ApiError`.

- A response whose body matches the error shape, checked with a type guard against the `ErrorCode` values, keeps its status, code, message and field errors.
- A response with any other body, such as an HTML page from a proxy, becomes `SERVER_ERROR` with the received status.
- No response at all, such as a network failure or timeout, becomes `SERVICE_UNAVAILABLE` with status 0.

**`lib/http/http-events.ts`** is a small typed publisher with an `HttpEvent` enum whose members are `Unauthenticated` and `PasswordChangeRequired`. It offers `subscribe(event, listener)`, which returns an unsubscribe function, and `publish(event)`. The interceptor cannot use the Next.js router, so it publishes these events, and the auth context subscribes to them in Phase 2 to navigate.

**`lib/http/csrf-cookie.ts`** offers `ensureCsrfCookie()` and `refreshCsrfCookie()`. They call `ApiEndpoint.CsrfCookie` through a plain request that skips the interceptors. Concurrent callers share one in flight promise, so the cookie is never fetched twice at the same moment.

**`lib/http/http-client.ts`** creates and exports the single Axios instance.

- `baseURL` is `environment.apiBaseUrl`.
- `withCredentials` and `withXSRFToken` are both `true`. The second option is required because the API is on a different origin.
- `xsrfCookieName` is `XSRF-TOKEN` and `xsrfHeaderName` is `X-XSRF-TOKEN`, taken from the constants.
- The default headers are `Accept: application/json` and `X-Requested-With: XMLHttpRequest`.
- `timeout` comes from the constant.
- **Request interceptor.** For POST, PATCH and DELETE, it awaits `ensureCsrfCookie()` when the `XSRF-TOKEN` cookie is not present yet.
- **Response error interceptor.**
  1. On 419, if the request has not been retried yet, it calls `refreshCsrfCookie()` and retries the original request once. The retry flag is a typed property added to `InternalAxiosRequestConfig` through module augmentation in `lib/http/axios.d.ts`.
  2. On 401, it publishes `HttpEvent.Unauthenticated`.
  3. On 403 with code `PASSWORD_CHANGE_REQUIRED`, it publishes `HttpEvent.PasswordChangeRequired`.
  4. In every failure case, it rejects with the `ApiError` produced by `createApiError`. Cancelled requests are passed through unchanged.

**`services/shared/base-api-service.ts`** is an abstract `BaseApiService` class. It receives the Axios instance through its constructor, defaulting to the shared one, and exposes protected typed helpers, `get<Response>`, `post<Response, Body>`, `patch<Response, Body>` and `delete<Response>`, that return the response body. Every feature service in later phases extends it, so components never import Axios.

### Step F10. Build the notifications context

- **`contexts/notification-context.tsx`** is a client component. `NotificationProvider` holds a list of notifications, each with an id, a `NotificationType` and a message, and exposes `notify` and `dismiss`. Notifications close automatically after a duration held in a constant.
- **`hooks/shared/use-notification.ts`** returns the context and throws a clear error when it is used outside the provider.
- **`components/shared/toast-region.tsx`** renders the active notifications in an `aria-live="polite"` region.
- **`components/shared/app-providers.tsx`** is a client component that wraps its children in `NotificationProvider` and renders the toast region. The root layout renders it. Phase 2 adds the auth provider here.

### Step F11. Build the base components

All base components live in `components/shared/`. They are typed with explicit props interfaces, accept a `className` that is merged through `mergeClassNames`, and use the colour tokens from `globals.css`. Each component is a server component unless it needs state, effects or event handlers, in which case it is marked `'use client'`.

First, create the supporting files.

- **`lib/utilities/merge-class-names.ts`** exports `mergeClassNames(...classNames)`, built on `clsx` and `tailwind-merge`.
- **`app/globals.css`** defines the design tokens with Tailwind's `@theme`. These cover the primary, surface, border, muted text, danger, warning and success colours and the font variables. Remove the boilerplate dark mode block.

Then build the components.

| Component | Responsibility |
| --- | --- |
| `button.tsx` | Variants from `ButtonVariant` and sizes from `ComponentSize`. It has a loading state that disables the button and shows a spinner, and its `type` defaults to `button`. |
| `input.tsx` | Text input forwarding native props, with `aria-invalid` set when an error is present. |
| `textarea.tsx` | Multi line input with the same error handling. |
| `select.tsx` | Native select with typed `options` of value and label. |
| `checkbox.tsx` | Single checkbox with a label. Phase 5 uses it for tag selection. |
| `form-field.tsx` | Label, control slot, hint and error message, linked with `useId`, `htmlFor` and `aria-describedby`. |
| `table.tsx` | Generic `Table<Row>` that takes column definitions, each with a key, a header and a cell renderer, plus a row key function. It shows an empty state when there are no rows. |
| `dialog.tsx` | Native `<dialog>` opened with `showModal()` and closed through `onClose`. It provides a title, closes on Escape and returns focus natively. |
| `badge.tsx` | Small label with a tone from `BadgeTone`. |
| `pagination.tsx` | Previous and next controls plus the page indicator, driven by the `meta` of `PaginatedResponse`. It calls `onPageChange`. |
| `stat-card.tsx` | Label and number for the overview count boxes. |
| `page-header.tsx` | Page title, optional description and an actions slot. |
| `spinner.tsx` | Accessible loading indicator with a visually hidden label. |
| `empty-state.tsx` | Title, description and an optional action for empty lists. |
| `alert.tsx` | Inline message with a tone from `AlertTone`, used for page level errors. |

All visible text comes from `constants/ui-messages.ts` or from props.

### Step F12. Add the security headers and the Content Security Policy

**`lib/security/security-headers.ts`** exports the static header list. The values are the same as the backend's in Step B11, plus `Strict-Transport-Security: max-age=31536000; includeSubDomains`, which is included only when `NODE_ENV` is `production`.

**[next.config.ts](../../frontend/next.config.ts)**

- Set `poweredByHeader: false`.
- Add an async `headers()` function that applies the static header list to the `/:path*` source.
- Keep the existing Turbopack Tailwind rule and remove the placeholder comment.

**`lib/security/content-security-policy.ts`** exports `buildContentSecurityPolicy(nonce: string): string`. In production it produces the following policy.

```text
default-src 'self';
script-src 'self' 'nonce-{nonce}' 'strict-dynamic';
style-src 'self' 'nonce-{nonce}';
style-src-attr 'unsafe-inline';
img-src 'self' blob: data:;
font-src 'self';
connect-src 'self' {apiOrigin};
object-src 'none';
base-uri 'self';
form-action 'self';
frame-ancestors 'none';
upgrade-insecure-requests;
```

In development, it changes three things.

- `script-src` adds `'unsafe-eval'`, which React needs for debugging.
- `style-src` becomes `'self' 'unsafe-inline'`, because the development server injects styles without a nonce. A nonce would make browsers ignore `'unsafe-inline'`.
- `connect-src` adds `ws:` for hot reload, and `upgrade-insecure-requests` is left out so that the plain HTTP API on localhost keeps working.

`style-src-attr 'unsafe-inline'` allows only inline `style` attributes, which React and the charts in Phase 7 rely on. Inline `<style>` elements still need the nonce. Scripts never use `'unsafe-inline'`.

**[proxy.ts](../../frontend/proxy.ts)** at the project root exports a `proxy` function, as the Next.js 16 guide describes. It does the following.

1. Generates the nonce with `crypto.randomUUID()`, base64 encoded.
2. Builds the policy with `buildContentSecurityPolicy`.
3. Sets `x-nonce` and `Content-Security-Policy` on the forwarded request headers, so Next.js applies the nonce to its own scripts.
4. Sets `Content-Security-Policy` on the response.

Its `config.matcher` excludes `_next/static`, `_next/image` and `favicon.ico`, and skips prefetch requests through the `next-router-prefetch` and `purpose` headers, as the guide shows. Phase 2 adds the signed out redirect to this same file, keeping the CSP logic in its own module.

### Step F13. Build the root layout, the error boundaries and the temporary preview

- **[app/layout.tsx](../../frontend/app/layout.tsx)**
  - Keep the Geist fonts. They are self hosted at build time, so `font-src 'self'` is enough.
  - Set the metadata from `constants/application.ts`.
  - Call `await connection()` from `next/server` so every page renders dynamically and receives the per request nonce.
  - Wrap the children in `AppProviders`.
- **`app/error.tsx`** is a client error boundary. It shows an `Alert` with the generic message and a retry button that calls `reset`.
- **`app/global-error.tsx`** is the last resort boundary. It renders its own `html` and `body`, as the Next.js guide requires.
- **`app/not-found.tsx`** shows an `EmptyState` with the not found message and a link to `AppRoute.Root`.
- **[app/page.tsx](../../frontend/app/page.tsx)** is replaced by a temporary component preview. It renders every base component in its main states, such as button variants, a loading button, a field with an error, a table with rows, an empty table, badges, pagination, stat cards, an alert, a dialog and a toast trigger. Its header comment states that Phase 2 replaces it with the root redirect. The interactive parts are in a small client component, `components/shared/component-preview.tsx`, which is also deleted in Phase 2.

The `(public)` and `(authenticated)` route groups are created in Phases 2 and 3, together with their first pages, because route groups without pages would only be empty folders.

### Step F14. Verify the frontend

1. Run `npm run lint` and confirm it reports no errors or warnings.
2. Run `npm run typecheck` and confirm it passes.
3. Run `npm run build` and confirm it succeeds.
4. Run `npm run start` and then `curl -I http://localhost:3000`. Confirm the response has a `Content-Security-Policy` with a nonce, plus `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy`, and no `X-Powered-By`.
5. Load the page twice and confirm the nonce changes between the two loads.
6. Open the preview in a browser, in both `npm run dev` and `npm run start`. Confirm that every component renders, the dialog opens and closes, toasts appear and disappear, and the browser console shows no CSP violations.

## 7. Done Criteria

Phase 1 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| Both applications start | `php artisan serve` and `npm run start` both run without errors. |
| The API returns the standard error shape for unknown routes | `ErrorResponseTest` passes and the manual `curl` in Step B15 shows the shape. |
| The frontend renders base components | The preview page in Step F13 renders every component with no console errors. |
| Sanctum, sessions, CORS and stateful domains are configured | Steps B3 to B5 are complete and `CorsTest` passes. |
| Shared enums exist and match on both sides | The enum files from Steps B7 and F6 have identical member names and values. |
| Security headers on both applications and the CSP on the frontend | `SecurityHeadersTest` passes and the checks in Step F14 succeed. |
| Environment files with placeholders and a central password policy | Step B13 and Step F5 are complete, `PasswordPolicyTest` passes and no real secret is committed. |
| Coding standards | Pint, ESLint and the TypeScript check pass, and every touched file has only its header comment. |

## 8. Known Limitations and Handover to Later Phases

- **The CSRF cookie route needs the sessions table.** Sanctum's `/sanctum/csrf-cookie` route starts a session, and the database session driver needs the sessions table. Following the main plan, that table is created in Phase 2, so this route and any browser request from the frontend origin return a server error until Phase 2. No Phase 1 feature needs them. Phase 2 also switches `SESSION_DRIVER` in `phpunit.xml` to `database`.
- **Error codes grow per phase.** New codes are added to `ErrorCode.php`, `lang/en/errors.php`, `enums/error-code.ts` and `constants/error-messages.ts` in the same change. The typed message record makes the frontend build fail if a code has no message.
- **Endpoints grow per phase.** Each phase adds its routes to `routes/api.php` and its entries to `constants/api-endpoints.ts` together.
- **Cache store.** The file cache store backs rate limiting and is fine for a single server. If production runs more than one application server, Phase 8 must move rate limiting to a shared store.
- **Production web server settings.** Hiding the server version, `expose_php = Off` and HTTPS termination are checked in Phase 8.

## 9. Suggested Commit Sequence

Each commit leaves both applications working.

1. `chore(backend): pin PHP 8.3 platform and normalize file headers` covers Steps B1 and B2.
2. `feat(backend): configure Sanctum SPA auth, sessions, CORS and database SSL` covers Steps B3 to B6.
3. `feat(backend): add shared enums, error shape and central exception renderer` covers Steps B7 to B10.
4. `feat(backend): add security headers, password policy and strict models` covers Steps B11 and B12.
5. `test(backend): configure PostgreSQL test database and add infrastructure tests` covers Steps B13 to B15.
6. `chore(frontend): tighten TypeScript and ESLint and set up feature folders` covers Steps F1 to F5.
7. `feat(frontend): add shared enums, constants, types and HTTP layer` covers Steps F6 to F9.
8. `feat(frontend): add notifications context and base components` covers Steps F10 and F11.
9. `feat(frontend): add security headers, nonce based CSP and error boundaries` covers Steps F12 to F14.
