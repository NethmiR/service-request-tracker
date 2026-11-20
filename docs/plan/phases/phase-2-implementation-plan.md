# Phase 2 Implementation Plan. Authentication and Mandatory Password Change

## 1. Purpose

This document breaks Phase 2 of the [implementation plan](../implementation-plan.md) into ordered, verifiable steps. Phase 2 delivers secure sign in, sign out, the current user endpoint, the self password change and the mandatory password change flow, together with the users, sessions and authentication audit tables.

Every step follows the main plan, in particular sections 3.1 to 3.5 (authentication requirements), 5 (Security Requirements) and 6 (Engineering Standards). It also keeps every convention set in the [Phase 1 plan](phase-1-implementation-plan.md), such as the file header comments, enum naming, snake_case JSON and the single error shape. Where this document and the main plan disagree, the main plan wins and this document must be corrected.

## 2. Decisions

### 2.1 Confirmed Decisions

These decisions were confirmed before writing this plan.

| Topic | Decision |
| --- | --- |
| Failed sign in response | 401 with the new error code `INVALID_CREDENTIALS` and one generic message, whether the email exists or not. Because the Phase 1 HTTP client publishes the `Unauthenticated` event on every 401, Step F3 changes it to publish the event only when the code is `UNAUTHENTICATED`. |
| Home pages in this phase | The real role based home routes are used from the start. Technicians and admins land on `/overview` and requesters land on `/service-requests/mine`. Both are placeholder pages in this phase and later phases fill them in, so the redirect logic never changes. |
| Sign in throttling | Only failed attempts count. A successful sign in clears the email and IP counter. The defaults are 5 failures per minute per email and IP combination and 20 failures per minute per IP, both configurable through environment variables. |

### 2.2 Decisions Made in This Plan

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Endpoint paths | `POST /api/auth/sign-in`, `POST /api/auth/sign-out`, `GET /api/auth/user` and `PATCH /api/auth/password`. Every path sits under `auth` so later features keep their own prefixes. |
| Success responses | Sign in, current user and password change return `200` with the current user resource inside the default `data` wrapper. Sign out returns `204` with no body. |
| Current user fields | `id`, `name`, `email`, `role` and `password_change_required`. The last field is a boolean derived from an empty `password_changed_at` column, and its name matches the `PASSWORD_CHANGE_REQUIRED` error code. |
| Pending password change state | An empty `users.password_changed_at` means a change is pending, exactly as section 4 of the main plan describes. Creating a user or resetting a password leaves the column empty, and a successful self change fills it with the current time. |
| Terminating other sessions | On a password change, the other sessions of the user are deleted from the `sessions` table. Sanctum's `AuthenticateSession` middleware also rejects any session whose stored password hash no longer matches, so both mechanisms protect against stale sessions. Deleting the rows also keeps the Phase 3 session list accurate. |
| Remember me | Not offered. The main plan does not describe it. The `remember_token` column is still created because Laravel's session guard reads it on sign out, and strict model mode would otherwise throw. |
| Initial admin | The seeder reads the name, email and password from environment variables through a config file, creates the admin with a pending password change and does nothing if the email already exists. |
| Sign in from non browser clients | Sign in needs a session, which Sanctum only starts for requests from a stateful frontend origin. A sign in request without a session returns `400 BAD_REQUEST` instead of a server error. |
| Bearer tokens | Sanctum token lookup is switched off, because the personal access tokens table does not exist. Without this, any request with an `Authorization: Bearer` header would cause a database error and a 500 response. |
| Development seeder | Deferred to Phase 5, where demo requests exist. In Phase 2, extra users for manual testing can be created with the user factory in Tinker, and Phase 4 adds user creation through the UI. |
| Frontend proxy check | The proxy can only see whether the session cookie exists, because the cookie is encrypted with the backend key. A missing cookie redirects to sign in. A present cookie may still belong to a signed out guest session, so the client side guard remains responsible for the final redirect. The cookie name comes from a server only environment variable. |
| Return path after sign in | Not included. After signing in, users always go to their role home page. |

## 3. Current State of the Code

The following was found in the repository before Phase 2 starts. Phase 1 is complete.

### 3.1 Backend

- Sanctum 4 is installed, `statefulApi()` is enabled in [bootstrap/app.php](../../../backend/bootstrap/app.php) and [config/sanctum.php](../../../backend/config/sanctum.php) keeps the `authenticate_session` middleware, which Phase 2 relies on.
- [config/session.php](../../../backend/config/session.php) uses the database driver with encryption, a secure cookie by default, HttpOnly and SameSite Lax.
- The shared enums exist in `app/Enums`. `AuthAuditEvent` already holds every audit event and `ErrorCode` already holds `PASSWORD_CHANGE_REQUIRED`. `INVALID_CREDENTIALS` does not exist yet.
- `ApiExceptionRenderer`, `ApiErrorResponse` and the abstract `DomainException` produce the shared error shape. A thrown `ThrottleRequestsException` is already rendered as `429 TOO_MANY_REQUESTS` with its `Retry-After` header.
- The central password policy is defined through `Password::defaults()` in [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php), together with `Model::shouldBeStrict()`.
- [routes/api.php](../../../backend/routes/api.php) holds only its header comment.
- `database/migrations` and `database/factories` are empty folders. [DatabaseSeeder.php](../../../backend/database/seeders/DatabaseSeeder.php) has an empty `run` method.
- [config/auth.php](../../../backend/config/auth.php) references `App\Models\User`, which does not exist yet.
- [phpunit.xml](../../../backend/phpunit.xml) still sets `SESSION_DRIVER=array`, as Phase 1 planned.
- [tests/TestCase.php](../../../backend/tests/TestCase.php) registers the in memory SQLite connection.

### 3.2 Frontend

- [app/page.tsx](../../../frontend/app/page.tsx) is the temporary component preview. It imports [components/shared/component-preview.tsx](../../../frontend/components/shared/component-preview.tsx) and [constants/component-preview.ts](../../../frontend/constants/component-preview.ts). Nothing else imports these two files.
- [lib/http/http-client.ts](../../../frontend/lib/http/http-client.ts) publishes `HttpEvent.Unauthenticated` on every 401 and `HttpEvent.PasswordChangeRequired` on a 403 with that code. Nothing subscribes to these events yet.
- [constants/api-endpoints.ts](../../../frontend/constants/api-endpoints.ts) holds only `CsrfCookie`.
- [constants/routes.ts](../../../frontend/constants/routes.ts) already holds every page path, including `SignIn`, `ChangePassword`, `Overview` and `MyServiceRequests`.
- [components/shared/app-providers.tsx](../../../frontend/components/shared/app-providers.tsx) wraps the app in `NotificationProvider` only.
- [proxy.ts](../../../frontend/proxy.ts) generates the CSP nonce and has no redirect logic.
- The feature folders `components/auth`, `services/auth`, `hooks/auth` and `types/auth` hold only a `.gitkeep` file.

## 4. Request Flows

These flows describe the behaviour the steps below must produce. They are the reference for the tests.

### 4.1 Sign In

1. The frontend calls `GET /sanctum/csrf-cookie` through the existing request interceptor, which sets the `XSRF-TOKEN` cookie and starts a guest session.
2. The frontend posts the email and password to `POST /api/auth/sign-in`.
3. The backend lowercases and trims the email, then validates the input.
4. The backend checks both rate limit counters. If either is exhausted, it records `SIGN_IN_THROTTLED` and returns `429` with `Retry-After`.
5. The backend looks up the user by email. If none exists, it still verifies the password against a dummy hash so the response time does not reveal whether the account exists.
6. On failure, it increments both counters, records `SIGN_IN_FAILED` with the attempted email and returns `401 INVALID_CREDENTIALS`.
7. On success, it clears the email and IP counter, signs the user in through the `web` guard, which regenerates the session id and the CSRF token, records `SIGN_IN_SUCCEEDED` and returns the current user.
8. The frontend stores the user in the auth context and navigates to the change password page when a change is pending, or to the role home page otherwise.

### 4.2 Mandatory Password Change

1. While `password_changed_at` is empty, every protected endpoint except `GET /api/auth/user`, `POST /api/auth/sign-out` and `PATCH /api/auth/password` returns `403 PASSWORD_CHANGE_REQUIRED`.
2. The HTTP client publishes `HttpEvent.PasswordChangeRequired`, the auth context marks the user as pending, and the authenticated layout redirects to `/change-password`.
3. The authenticated layout also redirects a pending user to `/change-password` on every navigation, so the user rarely reaches a blocked call. The backend remains the boundary.

### 4.3 Password Change

1. The frontend sends `current_password`, `password` and `password_confirmation` to `PATCH /api/auth/password`.
2. The backend validates the current password, the policy, the confirmation and that the new password differs from the current one.
3. In one transaction, it stores the new hash, sets `password_changed_at`, deletes the user's other session rows and records `PASSWORD_CHANGED`.
4. It regenerates the current session id and destroys the old session row.
5. At the end of the request, Sanctum's `AuthenticateSession` middleware stores the new password hash in the current session, so the current session stays signed in.
6. It returns the updated current user. The frontend shows a success notification and navigates to the role home page.

### 4.4 Sign Out

1. The frontend posts to `POST /api/auth/sign-out`.
2. The backend records `SIGNED_OUT`, logs out the `web` guard, invalidates the session and regenerates the CSRF token, then returns `204`.
3. The frontend clears the auth context, and the authenticated layout redirects to `/sign-in`.

## 5. Backend Steps

All commands run from the `backend` folder. Every new file starts with its two to three line header comment and contains no other comments.

### Step B1. Add the new error code

1. In [app/Enums/ErrorCode.php](../../../backend/app/Enums/ErrorCode.php), add `case InvalidCredentials = 'INVALID_CREDENTIALS';` after `Unauthenticated`, and map it to `Response::HTTP_UNAUTHORIZED` in `httpStatus()`.
2. In [lang/en/errors.php](../../../backend/lang/en/errors.php), add `'INVALID_CREDENTIALS' => 'The email or password is incorrect.'`. The message is the same for unknown emails and wrong passwords.
3. The frontend mirror is added in Step F2 in the same commit.

The existing `ErrorCodeTest` covers the new case automatically.

### Step B2. Add a helper for PostgreSQL check constraints

Laravel 13's schema builder has no method for check constraints, so they are added with raw statements. To keep that logic in one place, create **`app/Support/Database/CheckConstraint.php`**, a final class with static methods.

| Method | Responsibility |
| --- | --- |
| `allowedValues(string $table, string $column, array $values): void` | Adds `CHECK (column IN (...))`, named `{table}_{column}_check`. Values are quoted with the connection's PDO quoting, never interpolated directly. |
| `lowercase(string $table, string $column): void` | Adds `CHECK (column = LOWER(column))`, named `{table}_{column}_lowercase_check`. |

Both methods run only when the default connection driver is `pgsql` and do nothing otherwise, so the SQLite test database still migrates. Dropping the table in `down()` removes the constraints with it. Phase 5 reuses this helper for the status and tag columns.

### Step B3. Create the migrations

Create the migrations with `php artisan make:migration` so they get timestamped names. Every migration is reversible.

**`create_users_table`**

| Column | Definition |
| --- | --- |
| `id` | `id()` |
| `name` | `string('name')` |
| `email` | `string('email')->unique()` |
| `password` | `string('password')` |
| `role` | `string('role', 20)` |
| `password_changed_at` | `timestampTz('password_changed_at')->nullable()` |
| `remember_token` | `rememberToken()` |
| timestamps | `timestampsTz()` |

After the table is created, call `CheckConstraint::allowedValues('users', 'role', UserRole::values())` and `CheckConstraint::lowercase('users', 'email')`. The role column is also indexed because Phase 4 filters by role and Phase 6 lists technicians and admins.

**`create_sessions_table`**, following Laravel's database session schema.

| Column | Definition |
| --- | --- |
| `id` | `string('id')->primary()` |
| `user_id` | `foreignId('user_id')->nullable()->index()->constrained()->restrictOnDelete()` |
| `ip_address` | `string('ip_address', 45)->nullable()` |
| `user_agent` | `text('user_agent')->nullable()` |
| `payload` | `longText('payload')` |
| `last_activity` | `integer('last_activity')->index()` |

**`create_auth_audit_logs_table`**. The table is append only, so it has `created_at` and no `updated_at`.

| Column | Definition |
| --- | --- |
| `id` | `id()` |
| `event` | `string('event', 50)->index()` |
| `actor_user_id` | `foreignId('actor_user_id')->nullable()->index()->constrained('users')->restrictOnDelete()` |
| `target_user_id` | `foreignId('target_user_id')->nullable()->index()->constrained('users')->restrictOnDelete()` |
| `attempted_email` | `string('attempted_email')->nullable()` |
| `ip_address` | `string('ip_address', 45)->nullable()` |
| `user_agent` | `string('user_agent', 512)->nullable()` |
| `created_at` | `timestampTz('created_at')->useCurrent()->index()` |

After the table is created, call `CheckConstraint::allowedValues('auth_audit_logs', 'event', AuthAuditEvent::values())`.

The password reset tokens, cache and jobs tables are not created. The system has no email based reset, the cache store is `file` and the queue is `sync`.

**Check.** `php artisan migrate` and `php artisan migrate:rollback` both succeed against the local PostgreSQL database, and `\d users` in `psql` shows both check constraints.

### Step B4. Create the models

**`app/Models/User.php`** extends `Illuminate\Foundation\Auth\User` and uses `HasFactory`. It does not use `HasApiTokens` or `Notifiable`.

- `$fillable` is `name`, `email`, `password` and `role`. `password_changed_at` is not fillable and changes only through the methods below.
- `$hidden` is `password` and `remember_token`.
- `casts()` returns `role` as `UserRole::class`, `password` as `hashed` and `password_changed_at` as `immutable_datetime`.
- An `email` attribute mutator, defined with `Attribute::make(set: ...)`, stores the value trimmed and lowercase. The database check constraint is the second line of defence.
- `requiresPasswordChange(): bool` returns `true` when `password_changed_at` is `null`.
- `markPasswordChanged(): void` sets `password_changed_at` to the current time. It does not save, so the service controls the transaction.
- `markPasswordChangeRequired(): void` sets `password_changed_at` to `null`. Phase 4 uses it for admin resets.
- `hasRole(UserRole $role): bool` is a small helper that later Gates use.

**`app/Models/AuthAuditLog.php`**

- `public const UPDATED_AT = null;` so Eloquent writes only `created_at`.
- `$fillable` is `event`, `actor_user_id`, `target_user_id`, `attempted_email`, `ip_address` and `user_agent`.
- `casts()` returns `event` as `AuthAuditEvent::class` and `created_at` as `immutable_datetime`.
- `actor()` and `targetUser()` are `belongsTo(User::class, ...)` relations.
- In `booted()`, the `updating` and `deleting` model events throw a `LogicException` whose message is a class constant. This turns the append only rule into code. Query builder updates are not used anywhere in the application.

### Step B5. Create the user factory and the initial admin seeder

**`database/factories/UserFactory.php`**

- A public constant `DEFAULT_PASSWORD` holds a password that satisfies the policy, for example `Factory-Password-2026!`. Tests sign in with it.
- The default state creates a requester with a unique safe email, the hashed default password, `password_changed_at` set to now and a random remember token.
- States: `admin()`, `technician()`, `requester()` and `withPendingPasswordChange()`.

**`config/initial_admin.php`** reads `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL` and `INITIAL_ADMIN_PASSWORD`. Following Laravel convention, `env()` is called only in config files.

**`database/seeders/InitialAdminSeeder.php`**

1. Reads the three values from `config('initial_admin')`.
2. Validates them with the `Validator` facade. The name is required, the email is required and valid, and the password must pass `Password::defaults()`. If validation fails, it throws a `RuntimeException` with a clear message listing the missing or invalid variables, and never prints the password.
3. If a user with the lowercase email already exists, it stops without changing anything.
4. Otherwise, it creates the user with the `Admin` role and an empty `password_changed_at`, so the first sign in forces a password change.

**`database/seeders/DatabaseSeeder.php`** calls `InitialAdminSeeder`. Update its header comment to say that it seeds data required in every environment, and that Phase 5 adds the tags.

### Step B6. Create the data transfer objects

Create final readonly classes in `app/Data/Auth`.

| Class | Properties |
| --- | --- |
| `SignInCredentials` | `string $email`, `string $password` |
| `PasswordChange` | `string $newPassword` |
| `RequestContext` | `?string $ipAddress`, `?string $userAgent` |

`RequestContext` has a static `fromRequest(Request $request): self` factory that truncates the user agent to the audit column length, held in a named constant. Services receive this object instead of the whole request, so they stay independent of HTTP.

Create `app/Data/Audit/AuthAuditEntry.php` with `AuthAuditEvent $event`, `?User $actor`, `?User $targetUser`, `?string $attemptedEmail` and `RequestContext $context`.

### Step B7. Create the domain exceptions

Create these in `app/Exceptions`. Each one extends `DomainException` and returns its code from `errorCode()`.

| Exception | Error code |
| --- | --- |
| `InvalidCredentialsException` | `InvalidCredentials` |
| `PasswordChangeRequiredException` | `PasswordChangeRequired` |
| `SessionRequiredException` | `BadRequest` |

Throttling does not need a new exception. The rate limiter throws Laravel's `ThrottleRequestsException` with the `Retry-After` header, which the Phase 1 renderer already maps to `TOO_MANY_REQUESTS`.

### Step B8. Create the authentication audit logger

**`app/Services/Audit/AuthAuditLogger.php`** is a final class with one public method, `record(AuthAuditEntry $entry): void`. It creates one `AuthAuditLog` row from the entry. Each recorded event maps to these fields.

| Event | Actor | Target | Attempted email |
| --- | --- | --- | --- |
| `SIGN_IN_SUCCEEDED` | signed in user | none | none |
| `SIGN_IN_FAILED` | none | matching user when the email exists | the submitted email |
| `SIGN_IN_THROTTLED` | none | none | the submitted email |
| `SIGNED_OUT` | signed out user | none | none |
| `PASSWORD_CHANGED` | the user | none | none |

Linking the target on failed attempts is safe because the audit trail is never exposed through the API. The remaining events in `AuthAuditEvent` are recorded by Phases 3 and 4.

### Step B9. Create the sign in rate limiter and the named limiters

**`config/rate_limits.php`** holds every limit, each read from an environment variable with a safe default.

| Key | Environment variable | Default |
| --- | --- | --- |
| `sign_in.max_failures_per_email_and_ip` | `SIGN_IN_MAX_FAILURES_PER_EMAIL_AND_IP` | 5 |
| `sign_in.max_failures_per_ip` | `SIGN_IN_MAX_FAILURES_PER_IP` | 20 |
| `sign_in.decay_seconds` | `SIGN_IN_DECAY_SECONDS` | 60 |
| `password_change.max_attempts_per_minute` | `PASSWORD_CHANGE_MAX_ATTEMPTS_PER_MINUTE` | 5 |
| `api.max_requests_per_minute` | `API_MAX_REQUESTS_PER_MINUTE` | 120 |

Phase 4 adds the limits for user creation and admin password resets to the same file.

**`app/Services/Auth/SignInRateLimiter.php`** is a final class that wraps Laravel's `RateLimiter` and holds the two counter keys. The email and IP key is built from the lowercase email and the IP address, and the IP key from the IP address alone. Key prefixes are class constants.

| Method | Responsibility |
| --- | --- |
| `ensureNotLimited(string $email, ?string $ipAddress): void` | If either counter is exhausted, throws `ThrottleRequestsException` with a `Retry-After` header holding the longer of the two waiting times. |
| `recordFailure(string $email, ?string $ipAddress): void` | Increments both counters with the configured decay. |
| `clearFailures(string $email, ?string $ipAddress): void` | Clears only the email and IP counter, so one user signing in successfully does not lift the per IP limit for an attacker using the same address. |

**`app/Enums/RateLimiterName.php`** is a backend only string backed enum with `Api = 'api'` and `PasswordChange = 'password-change'`.

**Named limiters.** Add a private `configureRateLimiting()` method to `AppServiceProvider`, called from `boot()`.

- `RateLimiter::for(RateLimiterName::Api->value, ...)` returns `Limit::perMinute(...)` keyed by the authenticated user's id.
- `RateLimiter::for(RateLimiterName::PasswordChange->value, ...)` returns `Limit::perMinute(...)` keyed by the authenticated user's id.

Routes reference the limiters through `ThrottleRequests::using(RateLimiterName::Api->value)`, so no limiter name is written as a string in the routes file.

### Step B10. Create the services

**`app/Services/Auth/DummyPasswordHash.php`** returns a hash used only to equalize timing for unknown emails. It reads the hash from the cache under a constant key. If the hash is missing or `Hash::needsRehash()` reports that the cost settings changed, it creates one with `Hash::make()` from a random string and caches it forever. The verification cost therefore always matches real accounts.

**`app/Services/Sessions/SessionTerminator.php`** deletes rows in the `sessions` table through the query builder, using the table name from `config('session.table')`.

| Method | Responsibility |
| --- | --- |
| `terminateOtherSessions(User $user, string $currentSessionId): int` | Deletes every session of the user except the current one. |
| `terminateAllSessions(User $user): int` | Deletes every session of the user. Phase 4 uses it on admin password reset. |

**`app/Services/Auth/AuthenticationService.php`** receives the `StatefulGuard` for `web`, `SignInRateLimiter`, `DummyPasswordHash` and `AuthAuditLogger` through its constructor.

`signIn(SignInCredentials $credentials, RequestContext $context): User`

1. Calls `ensureNotLimited`. If it throws, the service first records `SIGN_IN_THROTTLED` and then rethrows the exception.
2. Loads the user by email.
3. Verifies the password with `Hash::check` against the user's hash, or against the dummy hash when no user was found.
4. On failure, calls `recordFailure`, records `SIGN_IN_FAILED` and throws `InvalidCredentialsException`.
5. On success, calls `clearFailures`, calls `$guard->login($user)` and records `SIGN_IN_SUCCEEDED`. In Laravel 13, `login()` already calls `regenerate(true)` on the session, which issues a new session id, destroys the old session row and regenerates the CSRF token. The service does not repeat it, and the tests in Step B14 prove that the id changes.
6. Rehashes the password if `Hash::needsRehash()` is true, so a raised bcrypt cost is applied on the next sign in.

`signOut(User $user, Session $session, RequestContext $context): void`

1. Records `SIGNED_OUT`.
2. Calls `$guard->logout()`.
3. Calls `$session->invalidate()` and `$session->regenerateToken()`.

**`app/Services/Auth/PasswordChangeService.php`** receives `SessionTerminator` and `AuthAuditLogger`.

`change(User $user, PasswordChange $change, Session $session, RequestContext $context): User`

1. Regenerates the session id with `$session->regenerate(true)`, so the old id is destroyed before the new one is excluded from deletion.
2. Opens a database transaction.
3. Sets the new password, calls `markPasswordChanged()` and saves the user.
4. Calls `terminateOtherSessions($user, $session->getId())`.
5. Records `PASSWORD_CHANGED`.
6. Commits and returns the fresh user.

The same `User` instance is held by the guard, so Sanctum's `AuthenticateSession` middleware stores the new password hash in the current session at the end of the request.

### Step B11. Create the middleware

**`app/Http/Middleware/EnsurePasswordChanged.php`** throws `PasswordChangeRequiredException` when the authenticated user `requiresPasswordChange()`. It runs after `auth:sanctum`, so a user is always present.

**`app/Http/Middleware/EnsureSessionStarted.php`** throws `SessionRequiredException` when `$request->hasSession()` is false. It protects the sign in route from clients outside the stateful frontend origins, which would otherwise cause a 500 error.

Both are referenced by class name in the routes file, so no aliases are needed.

### Step B12. Create the Form Requests, the resource and the controllers

**Form Requests** in `app/Http/Requests/Auth`. Field names and length limits are class constants.

`SignInRequest`

- `prepareForValidation()` lowercases and trims `email`.
- `email` is `required`, `string`, `email` and `max:255`.
- `password` is `required`, `string` and `max:255`.
- `toCredentials(): SignInCredentials` builds the DTO from `validated()`.

`ChangePasswordRequest`

- `current_password` is `required`, `string` and `current_password:web`.
- `password` is `required`, `string`, `max:72`, `confirmed`, `Password::defaults()` and `different:current_password`. The maximum of 72 is a named constant because bcrypt only reads the first 72 bytes.
- `toPasswordChange(): PasswordChange` builds the DTO.

Neither request contains authorization logic. Both `authorize()` methods return `true`, because authentication is handled by route middleware and every signed in user may change their own password.

**API Resource.** `app/Http/Resources/CurrentUserResource.php` returns exactly `id`, `name`, `email`, `role` as the enum value and `password_change_required` as a boolean. Phase 4 creates a separate resource for the users list.

**Controllers** in `app/Http/Controllers/Auth`. Each one is a single action invokable controller that only calls the request, the service and the resource.

| Controller | Behaviour |
| --- | --- |
| `SignInController` | Calls `AuthenticationService::signIn` and returns `CurrentUserResource`. |
| `SignOutController` | Calls `AuthenticationService::signOut` and returns `response()->noContent()`. |
| `CurrentUserController` | Returns `CurrentUserResource` for `$request->user()`. |
| `ChangePasswordController` | Calls `PasswordChangeService::change` and returns `CurrentUserResource`. |

### Step B13. Register the routes and disable bearer tokens

**`routes/api.php`**

```php
Route::post('auth/sign-in', SignInController::class)
    ->middleware(EnsureSessionStarted::class)
    ->name('auth.sign-in');

Route::middleware(['auth:sanctum', ThrottleRequests::using(RateLimiterName::Api->value)])->group(function (): void {
    Route::get('auth/user', CurrentUserController::class)->name('auth.user');
    Route::post('auth/sign-out', SignOutController::class)->name('auth.sign-out');
    Route::patch('auth/password', ChangePasswordController::class)
        ->middleware(ThrottleRequests::using(RateLimiterName::PasswordChange->value))
        ->name('auth.password');

    Route::middleware(EnsurePasswordChanged::class)->group(function (): void {
    });
});
```

The inner group stays empty in this phase. Every route added from Phase 3 onward goes inside it, which keeps the rule that only the three auth routes above are reachable while a password change is pending. `'auth:sanctum'` is the documented Sanctum middleware string and is kept as is, matching the existing Phase 1 tests.

**Bearer tokens.** In `AppServiceProvider::boot`, call `Sanctum::getAccessTokenFromRequestUsing(fn (): ?string => null)`. The guard then never queries the missing personal access tokens table.

### Step B14. Update the environment and test configuration

**`backend/.env.example`** gains these placeholders. Update the local `.env` with real values.

```dotenv
INITIAL_ADMIN_NAME="System Administrator"
INITIAL_ADMIN_EMAIL=admin@example.com
INITIAL_ADMIN_PASSWORD=change-this-placeholder

SIGN_IN_MAX_FAILURES_PER_EMAIL_AND_IP=5
SIGN_IN_MAX_FAILURES_PER_IP=20
SIGN_IN_DECAY_SECONDS=60
PASSWORD_CHANGE_MAX_ATTEMPTS_PER_MINUTE=5
API_MAX_REQUESTS_PER_MINUTE=120
```

The placeholder password intentionally fails the password policy, so the seeder refuses to run until a real value is set.

**`phpunit.xml`**. Change `SESSION_DRIVER` to `database` with `force="true"`, as Phase 1 planned. Tests then exercise the real session table, including session regeneration and termination.

**`tests/Concerns/InteractsWithFrontendSession.php`** is a trait that makes feature tests behave like the browser frontend.

- In its setup, it sets `sanctum.stateful` to a test frontend host held in a constant and adds the matching `Origin` and `Referer` headers, so Sanctum treats requests as stateful without depending on `.env`.
- It calls `withCredentials()` so JSON requests send cookies.
- `rememberSessionCookie(TestResponse $response): void` copies the encrypted session cookie from a response into the next request with `withUnencryptedCookie()`, because the value is already encrypted.
- `switchToFreshClient(): void` forgets the cached auth guards with `$this->app['auth']->forgetGuards()` and drops the in memory session store, so a test can act as a second browser. Laravel keeps both in memory between requests in the same test.

CSRF validation is skipped automatically while running tests, so no token handling is needed.

### Step B15. Write the Phase 2 tests

All feature tests use `RefreshDatabase` and the trait from Step B14. Routes used only by a test are registered in that test's `setUp` method.

**`tests/Feature/Auth/SignInTest.php`**

- Valid credentials return 200 with exactly `id`, `name`, `email`, `role` and `password_change_required` under `data`, and the user is authenticated.
- The session row in the database belongs to the user, and the session cookie after sign in differs from the guest session cookie received from `/sanctum/csrf-cookie`. The guest session row no longer exists.
- The email is matched case insensitively and with surrounding spaces removed.
- A wrong password and an unknown email return identical 401 bodies with `INVALID_CREDENTIALS`.
- For an unknown email, `Hash::check` is still called once, verified with a spy.
- Missing or malformed fields return 422 with `VALIDATION_FAILED`.
- Each outcome writes one audit row with the right event, actor, target, attempted email and IP address.
- A sign in request without a stateful origin returns 400 with `BAD_REQUEST`.
- A user with a pending change can sign in, and the response reports `password_change_required` as `true`.

**`tests/Feature/Auth/SignInThrottlingTest.php`**

- After the configured number of failures for one email and IP, the next attempt returns 429 with `TOO_MANY_REQUESTS` and a `Retry-After` header, even with the correct password, and writes `SIGN_IN_THROTTLED`.
- Failures spread across different emails from one IP hit the per IP limit.
- A successful sign in clears the email and IP counter.
- The counters reset after the decay time, using `travel()`.

**`tests/Feature/Auth/SignOutTest.php`**

- Signing out returns 204, writes `SIGNED_OUT`, removes the session row, and a following call to `/api/auth/user` returns 401.
- Signing out without a session returns 401 with `UNAUTHENTICATED`.

**`tests/Feature/Auth/CurrentUserTest.php`**

- A guest receives 401 with `UNAUTHENTICATED`.
- A signed in user receives exactly the safe fields. The response never contains `password`, `remember_token`, `password_changed_at` or timestamps.
- A request with a random `Authorization: Bearer` header and no session returns 401, not 500.

**`tests/Feature/Auth/PasswordChangeRequiredTest.php`**

- A test route registered inside `auth:sanctum` and `EnsurePasswordChanged` returns 403 with `PASSWORD_CHANGE_REQUIRED` for a pending user and 200 for a user who has changed their password.
- A pending user can still call the current user, sign out and password change endpoints.

**`tests/Feature/Auth/ChangePasswordTest.php`**

- A wrong current password returns 422 with an error on `current_password`.
- Passwords that break the policy, a confirmation mismatch and a new password equal to the current one each return 422 on `password`.
- A successful change returns 200 with `password_change_required` set to `false`, stores a new hash, fills `password_changed_at` and writes `PASSWORD_CHANGED`.
- The session id changes, the current session stays signed in on the next request, and every other session row of the user is deleted. A second client signed in before the change receives 401 afterwards.
- Sessions of other users are not touched.
- Exceeding the password change limit returns 429.

**`tests/Feature/Auth/ProtectedRoutesTest.php`**

- Every registered route under `/api` except sign in is protected by `auth:sanctum`. The test walks the route collection, so new routes in later phases are checked automatically.
- Every protected route other than the three allowed auth routes also has `EnsurePasswordChanged`. In this phase that set is empty, and the test protects later phases.

**`tests/Feature/Audit/AuthAuditLogTest.php`**

- Updating or deleting an `AuthAuditLog` through Eloquent throws a `LogicException`.

**`tests/Feature/Seeders/InitialAdminSeederTest.php`**

- With valid configuration, the seeder creates one admin with a lowercase email and a pending password change.
- Running it twice creates no duplicate and does not change the existing admin.
- Missing values or a weak password make it throw, and the message never contains the password.

### Step B16. Format and verify

1. Run `vendor/bin/pint`, then `composer lint`.
2. Run `composer test` and confirm every Phase 1 and Phase 2 test passes.
3. Run `php artisan migrate:fresh --seed` locally and confirm the admin row exists with an empty `password_changed_at`.
4. With `php artisan serve` running, confirm that `curl -i http://localhost:8000/api/auth/user` returns 401 with the error shape.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before writing Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/02-guides/authentication.md` (especially "Optimistic checks with Proxy"), `01-app/01-getting-started/16-proxy.md`, `01-app/02-guides/redirecting.md` and the route groups and layouts references in `01-app/03-api-reference/03-file-conventions/`.

### Step F1. Remove the component preview

1. Delete `components/shared/component-preview.tsx`, which holds the interactive parts of the preview (form, table, dialog and toasts).
2. Delete `constants/component-preview.ts`, which holds the sample text and data for the preview.
3. Do not delete `app/page.tsx`. Next.js needs a page at `/`, so Step F12 replaces its contents with the root redirect.
4. Run a search for `component-preview` and confirm that nothing else references the deleted files.

Steps F1 and F12 go in the same commit, so the build never breaks.

### Step F2. Mirror the new error code and add the endpoints

- In `enums/error-code.ts`, add `InvalidCredentials = "INVALID_CREDENTIALS"` after `Unauthenticated`.
- In `constants/error-messages.ts`, add the message `The email or password is incorrect.`.
- In `constants/api-endpoints.ts`, add these entries.

| Member | Path |
| --- | --- |
| `SignIn` | `/api/auth/sign-in` |
| `SignOut` | `/api/auth/sign-out` |
| `CurrentUser` | `/api/auth/user` |
| `ChangePassword` | `/api/auth/password` |

### Step F3. Adjust the HTTP layer

1. In `lib/http/http-client.ts`, publish `HttpEvent.Unauthenticated` only when `apiError.hasCode(ErrorCode.Unauthenticated)`, instead of on every 401. Failed sign ins return 401 with `INVALID_CREDENTIALS` and must not be treated as an expired session.
2. Add `retryAfterSeconds: number | null` to `ApiError` and `ApiErrorDetails` in `lib/http/api-error.ts`.
3. In `lib/http/create-api-error.ts`, read the `Retry-After` response header for 429 responses and parse it into whole seconds, or `null` when it is missing or invalid. The header name comes from `HttpHeader.RetryAfter`, and the backend already exposes it through CORS.

### Step F4. Add the server only environment value for the proxy

1. Add `SESSION_COOKIE_NAME=service_request_tracker_session` to `.env.example` and the local `.env`. It must equal the backend's `SESSION_COOKIE`. It has no `NEXT_PUBLIC_` prefix, so it never reaches the browser bundle.
2. Create `config/server-environment.ts`. It reads `process.env.SESSION_COOKIE_NAME`, throws `DeveloperMessage.MissingSessionCookieName` when it is missing and exports a frozen `serverEnvironment` object with `sessionCookieName`. Only `proxy.ts` and modules it imports may import this file. The header comment states this.
3. Add `MissingSessionCookieName` and `AuthProviderMissing` to `constants/developer-messages.ts`.

### Step F5. Add the auth types, enums and constants

**Types** in `types/auth/`, mirroring the wire format in snake_case.

| File | Type |
| --- | --- |
| `current-user.ts` | `CurrentUser` with `id: number`, `name: string`, `email: string`, `role: UserRole` and `password_change_required: boolean` |
| `sign-in-request.ts` | `SignInRequest` with `email` and `password` |
| `change-password-request.ts` | `ChangePasswordRequest` with `current_password`, `password` and `password_confirmation` |

**Enums.**

- `enums/ui/auth-status.ts` holds `AuthStatus` with `Loading`, `Authenticated` and `Unauthenticated`.
- `enums/ui/autocomplete-token.ts` holds `AutocompleteToken` with `Username = "username"`, `CurrentPassword = "current-password"` and `NewPassword = "new-password"`, so the browser offers the right password manager behaviour.

**Constants.**

- `constants/auth-fields.ts` holds `SignInField` and `ChangePasswordField`, typed with `satisfies Record<string, keyof SignInRequest>` and the matching change password type. Form inputs and field error lookups use these, so a renamed field fails the type check.
- `constants/auth-messages.ts` holds the feature text, such as page titles and descriptions, field labels, button labels, the pending change explanation, the success notification, the placeholder text and the sign out failure message. It also exports `formatRetryAfterMessage(seconds: number): string`.
- `constants/password-policy.ts` holds `MINIMUM_PASSWORD_LENGTH = 12`, mirroring the backend constant, and the policy hint text built from it. The backend still validates the policy.
- `constants/routes.ts` gains `ROLE_HOME_ROUTES: Record<UserRole, AppRoute>`, mapping `Admin` and `Technician` to `AppRoute.Overview` and `Requester` to `AppRoute.MyServiceRequests`, and `PUBLIC_ROUTES: ReadonlySet<string>` holding `AppRoute.SignIn`. Because the record is typed, a new role without a home route fails the build.
- `constants/application.ts` gains the page title template used by the metadata of each page.

**Helper.** `lib/auth/resolve-signed-in-route.ts` exports `resolveSignedInRoute(user: CurrentUser): AppRoute`. It returns `AppRoute.ChangePassword` when a change is pending, and the role home route otherwise. This is the only place where the post sign in destination is decided.

### Step F6. Build the auth service

**`services/auth/auth-service.ts`** holds `AuthService`, which extends `BaseApiService`, and exports one shared instance, `authService`.

| Method | Call | Returns |
| --- | --- | --- |
| `signIn(request: SignInRequest)` | `POST ApiEndpoint.SignIn` | `CurrentUser` |
| `signOut()` | `POST ApiEndpoint.SignOut` | `void` |
| `getCurrentUser()` | `GET ApiEndpoint.CurrentUser` | `CurrentUser` |
| `changePassword(request: ChangePasswordRequest)` | `PATCH ApiEndpoint.ChangePassword` | `CurrentUser` |

The methods unwrap the `data` property of `ApiResourceResponse<CurrentUser>`, so callers receive the user directly.

### Step F7. Build the auth context

**`contexts/auth-context.tsx`** is a client component. `AuthProvider` holds `status: AuthStatus` and `user: CurrentUser | null`, and exposes these actions through the context value.

| Action | Behaviour |
| --- | --- |
| `signIn(request)` | Calls the service, stores the user and sets the status to `Authenticated`. It returns the user and lets `ApiError` propagate to the form. |
| `signOut()` | Calls the service, then clears the user and sets the status to `Unauthenticated`. On failure, it shows the sign out failure notification and keeps the state, unless the failure was `UNAUTHENTICATED`, in which case the event handler has already cleared it. |
| `changePassword(request)` | Calls the service and stores the returned user, which clears the pending flag. |
| `refreshUser()` | Loads the current user again. |

Behaviour of the provider:

- On mount, it loads the current user once. Success sets `Authenticated`, and any `ApiError` sets `Unauthenticated` with no user.
- It subscribes to `HttpEvent.Unauthenticated` and clears the user. It subscribes to `HttpEvent.PasswordChangeRequired` and sets `password_change_required` to `true` on the stored user. Both subscriptions are removed when the provider unmounts.
- It never navigates. Navigation is handled by the guards in Step F9, which react to the state. This keeps one place per concern.
- The context value is memoized with `useMemo` and the actions with `useCallback`.

**`hooks/auth/use-auth.ts`** returns the context and throws `DeveloperMessage.AuthProviderMissing` outside the provider.

**`components/shared/app-providers.tsx`** wraps the children in `AuthProvider` inside `NotificationProvider`, because the auth context uses notifications. Remove the "Phase 2 adds the auth provider here" line from its header comment.

### Step F8. Add the signed out redirect to the proxy

**`lib/routing/signed-out-redirect.ts`** exports `getSignedOutRedirect(request: NextRequest): NextResponse | null`. It returns a redirect to `AppRoute.SignIn` when the path is not in `PUBLIC_ROUTES` and the request has no cookie named `serverEnvironment.sessionCookieName`. Otherwise it returns `null`.

**`proxy.ts`** calls `getSignedOutRedirect` first and returns the redirect when there is one. Otherwise it continues with the existing nonce and Content Security Policy logic, which stays in its own module. Update its header comment to mention both jobs.

This check is a routing convenience only, as section 5.12 of the main plan states. The backend rejects every unauthenticated call regardless.

### Step F9. Build the route guards and the shared loading screen

**`components/shared/page-loader.tsx`** centres a `Spinner` with the loading label from `UiMessage`. Guards show it while the auth status is `Loading` or while a redirect is in progress, so protected content never flashes.

Create these client components in `components/auth/`. Each one reads `useAuth()`, `usePathname()` and `useRouter()` and calls `router.replace` inside an effect.

| Component | Rule |
| --- | --- |
| `require-authentication.tsx` | `Unauthenticated` redirects to `AppRoute.SignIn`. A pending change on any path other than `AppRoute.ChangePassword` redirects there. Otherwise it renders its children. |
| `require-guest.tsx` | `Authenticated` redirects to `resolveSignedInRoute(user)`. Otherwise it renders its children. |
| `root-redirect.tsx` | `Authenticated` redirects to `resolveSignedInRoute(user)` and `Unauthenticated` redirects to `AppRoute.SignIn`. It always renders the page loader. |
| `sign-out-button.tsx` | A `Button` that calls `signOut()` and shows its loading state. |

### Step F10. Build the forms

**`components/auth/sign-in-form.tsx`** is a client component.

- It has an email `Input` with `type="email"` and `AutocompleteToken.Username`, and a password `Input` with `AutocompleteToken.CurrentPassword`, each inside `FormField`.
- On submit, it calls `signIn` from the context and disables the form while the request runs.
- On `INVALID_CREDENTIALS`, it shows the error message in a danger `Alert` above the form and clears the password field.
- On `TOO_MANY_REQUESTS`, it shows `formatRetryAfterMessage(retryAfterSeconds)` when the value is known, and the generic message otherwise.
- On `VALIDATION_FAILED`, it shows each field's first error through `firstFieldError`.
- Any other failure shows the error message in the alert.
- After success, `RequireGuest` performs the navigation, so the form does not navigate itself.

**`components/auth/change-password-form.tsx`** is a client component. It is reusable, so the Phase 3 account page can use it as well.

- It has fields for the current password, the new password and the confirmation, with the policy hint under the new password.
- On submit, it calls `changePassword` from the context and shows field errors from `VALIDATION_FAILED`.
- On success, it clears every field, shows the success notification and calls an `onSuccess(user: CurrentUser)` prop.
- Password values live only in component state and are never logged or stored, as section 5.12 of the main plan requires.

### Step F11. Create the route groups, layouts and pages

```text
app/
  page.tsx                                  root redirect
  (public)/
    layout.tsx                              centred card layout wrapped in RequireGuest
    sign-in/page.tsx                        sign in form
  (authenticated)/
    layout.tsx                              wrapped in RequireAuthentication
    change-password/page.tsx                change password card with sign out
    (workspace)/
      layout.tsx                            temporary top bar, replaced by the sidebar in Phase 3
      overview/page.tsx                     placeholder for technicians and admins
      service-requests/mine/page.tsx        placeholder for requesters
```

- **`(public)/layout.tsx`** is a server component that renders the application name and a centred card, with `RequireGuest` around the children.
- **`(public)/sign-in/page.tsx`** exports metadata with the sign in title and renders `PageHeader` and `SignInForm`.
- **`(authenticated)/layout.tsx`** is a server component that wraps the children in `RequireAuthentication`.
- **`(authenticated)/change-password/page.tsx`** is a small client component in `components/auth/change-password-panel.tsx`, rendered by a server page that sets the metadata. When the change is pending, it explains why the change is required. Its `onSuccess` handler navigates with `router.replace(resolveSignedInRoute(user))`. It includes `SignOutButton`, because a pending user may still sign out.
- **`(authenticated)/(workspace)/layout.tsx`** renders a simple top bar with the application name, the user's name, a role `Badge` with the label from `USER_ROLE_LABELS` and `SignOutButton`. The nested `(workspace)` group exists so the Phase 3 sidebar layout can replace this file without affecting the change password page.
- **`overview/page.tsx`** and **`service-requests/mine/page.tsx`** each render a `PageHeader` and an `EmptyState` with placeholder text from `constants/auth-messages.ts`. Phases 5 and 7 replace their contents. Role based page guards, for example stopping a requester from opening `/overview`, are added in Phase 3 together with the role based navigation.

### Step F12. Replace the root page

Replace the contents of `app/page.tsx` with a server component that renders `RootRedirect`. Update its header comment to state that the root route sends signed in users to their home page and everyone else to sign in. This step completes the removal started in Step F1.

### Step F13. Remove placeholder files

The `.gitkeep` file in a feature folder is deleted once that folder has a real file. In this phase, delete these.

- `components/auth/.gitkeep`
- `services/auth/.gitkeep`
- `hooks/auth/.gitkeep`
- `types/auth/.gitkeep`

The `.gitkeep` files in the `users`, `service-requests`, `sessions` and `overview` folders stay until their phases add files.

### Step F14. Verify the frontend

1. Run `npm run lint` and confirm there are no errors or warnings.
2. Run `npm run typecheck` and confirm it passes.
3. Run `npm run build` and confirm it succeeds.
4. Clear the cookies for `localhost`, open `http://localhost:3000/overview` and confirm the proxy redirects to `/sign-in`.

## 7. End to End Verification

Run both applications locally with `php artisan serve` and `npm run dev`, and repeat the checks with `npm run start` after a build. Confirm each item in a browser with the developer tools open.

1. Opening `/` with no cookies goes to `/sign-in`.
2. A wrong password shows the generic message and stays on the sign in page with no redirect loop. An unknown email shows the same message.
3. Six quick failures show the retry message with a number of seconds.
4. The seeded admin signs in and lands on `/change-password`. Opening `/overview` directly sends the admin back to `/change-password`.
5. Signing out from the change password page works and returns to `/sign-in`.
6. Signing in again and changing the password shows the success notification and lands on `/overview`.
7. A second browser profile signed in before the change is sent to `/sign-in` on its next request.
8. Reloading `/overview` keeps the admin signed in, and opening `/sign-in` while signed in redirects to `/overview`.
9. Signing out from the top bar returns to `/sign-in`, and the back button does not show protected content.
10. A requester created in Tinker with the factory's pending password state lands on `/change-password` and, after the change, on `/service-requests/mine`.
11. The browser console shows no CSP violations, the session cookie is HttpOnly and SameSite Lax, and nothing is written to local or session storage.
12. The `auth_audit_logs` table holds one row for each event above.

## 8. Done Criteria

Phase 2 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| The users, sessions and audit tables, the initial admin seeder and the user factory exist | Step B3 migrations run and roll back on PostgreSQL, and `InitialAdminSeederTest` passes. |
| Sign in, sign out, current user and self password change work with rate limiting and enumeration protection | `SignInTest`, `SignInThrottlingTest`, `SignOutTest`, `CurrentUserTest` and `ChangePasswordTest` pass. |
| The pending password change is enforced on the server | `PasswordChangeRequiredTest` and `ProtectedRoutesTest` pass. |
| Every authentication event is recorded in the append only audit table | The audit assertions in the auth tests and `AuthAuditLogTest` pass. |
| Tests cover sign in, 401 rejection, throttling, the forced password change and session regeneration | The backend suite passes with `composer test`. |
| The sign in page, root redirect, auth context, proxy redirect and change password page exist | Steps F7 to F12 are complete and the checks in section 7 succeed. |
| The seeded admin can sign in, is forced to change the password, then reaches a placeholder home page and can sign out | Items 4 to 6 and 9 of section 7 succeed. |
| Coding standards | Pint, ESLint and the TypeScript check pass, every new file has only its header comment, and no strings are hardcoded outside enums and constants. |

## 9. Known Limitations and Handover to Later Phases

- **Placeholder pages.** `/overview` and `/service-requests/mine` are placeholders. Phase 5 fills the requester page and Phase 7 fills the overview page.
- **Role page guards and navigation.** Phase 3 adds the sidebar, role based navigation and page level role guards. Until then, a requester who types `/overview` sees the placeholder. The page holds no data, and the backend enforces every rule once real endpoints exist.
- **Protected route group.** Every route added from Phase 3 onward goes inside the `EnsurePasswordChanged` group in `routes/api.php`, and `ProtectedRoutesTest` fails if one is missed.
- **Session cookie in the proxy.** A signed out browser still holds a guest session cookie, so the proxy lets it through and the client guard performs the redirect after one `401`. This is expected, because the proxy is only a convenience.
- **Audit events for later phases.** `SESSION_CLEARED_BY_OWNER` is recorded in Phase 3, and `PASSWORD_RESET_BY_ADMIN`, `USER_CREATED_BY_ADMIN` and `SESSION_CLEARED_BY_ADMIN` in Phase 4. Phase 4 reuses `SessionTerminator::terminateAllSessions` and `User::markPasswordChangeRequired`.
- **Check constraints in tests.** SQLite skips the PostgreSQL check constraints, so they are verified manually in Step B3. Phase 6 reconsiders moving the tests to PostgreSQL.
- **Development seeder.** Demo users and requests are added in Phase 5.

## 10. Suggested Commit Sequence

Each commit leaves both applications working.

1. `feat(backend): add users, sessions and audit tables with models, factory and initial admin seeder` covers Steps B1 to B5.
2. `feat(backend): implement sign in, sign out, current user and password change` covers Steps B6 to B13.
3. `test(backend): switch tests to database sessions and add authentication tests` covers Steps B14 to B16.
4. `feat(frontend): add auth types, service, context and proxy redirect` covers Steps F2 to F8.
5. `feat(frontend): add sign in, change password and root redirect and remove the component preview` covers Steps F1 and F9 to F14.
