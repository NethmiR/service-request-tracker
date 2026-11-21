# Phase 8 Implementation Plan. Security Hardening and Release Readiness

## 1. Purpose

This document breaks Phase 8 of the [implementation plan](../implementation-plan.md) into ordered, verifiable steps. Phase 8 confirms that the system is ready for production. It adds no business features. It reviews every route against the authorization rules and HTTP semantics of section 5, verifies cookie flags, CORS, CSRF, rate limits, security headers and the production configuration, checks that no response exposes sensitive fields and that no secrets are committed, confirms the engineering standards of section 6 and completes the README so a fresh setup works from the documented commands alone.

Phase 8 also closes the items earlier phases handed over to it. These are the missing Phase 7 tests, trusted proxies for real client addresses, a cache store that works with more than one application server, a lock timeout for workflow changes and the production web server settings.

Every step follows the main plan, in particular sections 5 (Security Requirements) and 6 (Engineering Standards). It also keeps every convention set in the [Phase 1 plan](phase-1-implementation-plan.md), the [Phase 2 plan](phase-2-implementation-plan.md), the [Phase 3 plan](phase-3-implementation-plan.md), the [Phase 4 plan](phase-4-implementation-plan.md), the [Phase 5 plan](phase-5-implementation-plan.md), the [Phase 6 plan](phase-6-implementation-plan.md) and the [Phase 7 plan](phase-7-implementation-plan.md). These include the file header comments, enum naming, snake_case JSON, the single error shape, ISO 8601 UTC timestamps, the `EnsurePasswordChanged` route group, the middleware priority list and the in memory SQLite test database. Where this document and the main plan disagree, the main plan wins and this document must be corrected. Step B1 adds the new engineering rules to the main plan first, so the main plan stays the single source of truth.

Phase 8 does not add email integration, frontend automated tests, deployment scripts, container images or a web server configuration file. The main plan describes none of them. The README documents the web server settings that production must apply.

## 2. Decisions

### 2.1 Confirmed Decisions

These decisions were confirmed before writing this plan.

| Topic | Decision |
| --- | --- |
| Missing Phase 7 tests | The Phase 7 test commit (Phase 7 Steps B13 and B14) was never made. Phase 8 opens with Step B0, which writes those tests exactly as the Phase 7 plan specifies, in its own commit, before any hardening work. If a test exposes a defect in the Phase 7 code, the fix goes into the same commit and is recorded in section 10. |
| Trusted proxies | A new `TRUSTED_PROXIES` environment variable holds a comma separated list of proxy addresses or CIDR ranges. It is empty by default, which trusts no proxy. Production lists the addresses of its load balancer or reverse proxy, so audit records and rate limits see the real client address. This settles the Phase 3 handover. |
| Cache store | The database cache store becomes the production store. A migration creates the `cache` and `cache_locks` tables, `CACHE_STORE=database` becomes the documented value and the config default stays `database`. Rate limit counters and the dummy password hash then work across several application servers. This settles the Phase 1 handover. |
| Workflow lock timeout | Workflow transactions set a short PostgreSQL `lock_timeout`. A change that waits longer than the timeout for the request row lock is rejected with the existing `409 CONCURRENT_UPDATE` and writes nothing. The timeout is configurable. This settles the Phase 6 handover. |
| Overview read consistency | Not changed. The overview queries keep running outside a transaction, and the limitation stays documented in the README. This settles the Phase 7 handover. |

### 2.2 Decisions Made in This Plan

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Route review form | The review of section 5 is turned into tests rather than a one time manual reading. A new `RouteAccessMatrixTest` holds the expected access of every API route for guests, users with a pending password change, requesters, technicians and admins, and fails when a new route has no entry. The route table in section 4.1 of this plan is its source. |
| CSRF in tests | Laravel skips CSRF validation while running unit tests. A test only subclass of `ValidateCsrfToken` that turns this skip off is swapped in through the `sanctum.middleware.validate_csrf_token` config key, which Sanctum reads on every stateful request. This proves `419 CSRF_TOKEN_MISMATCH` without touching application code. |
| Sensitive field check | A new `SensitiveFieldExposureTest` calls every endpoint that returns a body and walks the whole JSON tree. It fails on password hashes, remember tokens, session payloads, raw session ids, internal foreign key columns and `password_changed_at`, and it allows emails and temporary passwords only on the endpoints that are meant to return them. |
| Enum parity | A new backend test, `FrontendEnumParityTest`, reads the TypeScript enum files in `frontend/enums` and compares their member names and values with the PHP enums. Section 6.3 of the main plan requires exact matches, and the repository holds both applications, so the check is automated instead of done by eye. |
| Queue default | `config/queue.php` defaults to `sync`, because no jobs table exists and the application dispatches no jobs. The current default, `database`, would fail if `QUEUE_CONNECTION` were ever missing. |
| Lock timeout value | 3000 milliseconds by default, read from `WORKFLOW_LOCK_TIMEOUT_MILLISECONDS`. Workflow transactions hold the lock for a few milliseconds, so a wait of three seconds means something is wrong, and the user receives a clear conflict instead of a hanging request. |
| Lock timeout detection | PostgreSQL reports a lock timeout with SQLSTATE `55P03` (`lock_not_available`). Only that SQLSTATE is mapped to `CONCURRENT_UPDATE`. Every other database error still becomes `500 SERVER_ERROR`. |
| README layout | One root `README.md` is the complete guide for both applications, covering setup, migration, seeding, tests, environment variables, the HTTP status codes of section 5.8, the production checklist and the known limitations. `backend/README.md` and `frontend/README.md` become short pages with the commands of their own application and a link to the root guide. |
| HTTP status documentation | Section 5.8 of the main plan says status codes are "applied consistently and documented". The root README holds a table of every error code, its HTTP status and when it is returned, built from `ErrorCode.php`. |
| Non code files | `.gitignore`, `.gitattributes`, `.editorconfig`, `public/.htaccess`, `robots.txt` and the Markdown files are tooling and documentation, not application code, so the header comment rule of section 6.3 does not apply to them. They are left unchanged. |
| Laravel Boost files | `backend/AGENTS.md`, `backend/CLAUDE.md` and the `laravel/boost` development dependency are developer tooling that never ships to production. They are left unchanged. |
| Dependency audit | `composer audit` and `npm audit --omit=dev` must report no high or critical advisories. A fix stays inside the existing version constraints and the PHP 8.3 platform pin. An advisory that cannot be fixed that way is recorded in section 10 with its reason. |

## 3. Current State of the Code

The following was found in the repository before Phase 8 starts. Phases 1 to 6 are complete and committed. Phase 7 is committed except for its test commit. The working tree is clean on the `dev` branch.

### 3.1 Baseline Checks

| Check | Result |
| --- | --- |
| `php artisan test` | 387 tests, 3772 assertions, all passing |
| `vendor/bin/pint --test` | Passing |
| `npm run lint` | No errors or warnings |
| `npm run typecheck` | Passing |
| Header comment scan of every tracked `.php`, `.ts`, `.tsx`, `.mjs` and `.css` file | Every file has its header comment. One extra docblock remains in [TemporaryPasswordGeneratorTest.php](../../../backend/tests/Unit/Services/Users/TemporaryPasswordGeneratorTest.php) at line 60. |
| Em dash and en dash scan | No matches |
| `any` and `unknown` scan in TypeScript | No matches in code |
| `console.`, `dangerouslySetInnerHTML`, `localStorage` and `sessionStorage` in the frontend | No matches |
| Git history | Only the two `.env.example` files were ever committed. No `APP_KEY` value or private key appears in any commit. |

### 3.2 Backend

- **Missing Phase 7 tests.** `tests/Feature/Overview`, `tests/Unit/Services/Overview`, `tests/Unit/Authorization/ViewOverviewGateTest.php` and `tests/Concerns/BuildsWorkflowHistory.php` do not exist. [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php) still has `STAFF_SERVICE_REQUEST_GATES` and does not check `overview.show`. The overview code itself is committed in [app/Services/Overview](../../../backend/app/Services/Overview).
- **Routes.** [routes/api.php](../../../backend/routes/api.php) holds 23 API routes. Sign in is public, the three auth routes sit in the `auth:sanctum` and `api` throttle group, and every other route sits in the nested `EnsurePasswordChanged` group. Sanctum adds `GET /sanctum/csrf-cookie` and Laravel adds the `GET /up` health route.
- **Middleware.** [bootstrap/app.php](../../../backend/bootstrap/app.php) prepends `AddSecurityHeaders` and `EnsureJsonResponse`, enables `statefulApi()` and sets the full middleware priority list. Laravel's default global stack already contains `Illuminate\Http\Middleware\TrustProxies`, which reads the static `TrustProxies::at()` and `TrustProxies::withHeaders()` settings. Nothing calls them yet, so no proxy is trusted and `$request->ip()` is always the direct peer address.
- **Client address use.** `RequestContext::fromRequest` and `AppServiceProvider::rateLimitKey` read `$request->ip()`, so audit records and the sign in limiter depend on the trusted proxy setting.
- **Cache.** [config/cache.php](../../../backend/config/cache.php) defaults to `database`, but no `cache` table exists. `backend/.env.example` sets `CACHE_STORE=file`, which only works on a single server. `SignInRateLimiter`, the named limiters and `DummyPasswordHash` all use the default store. Tests use the `array` store from [phpunit.xml](../../../backend/phpunit.xml).
- **Queue.** [config/queue.php](../../../backend/config/queue.php) defaults to `database`, but no jobs table exists. `QUEUE_CONNECTION=sync` in the example file hides this.
- **Workflow locking.** [ServiceRequestWorkflowService.php](../../../backend/app/Services/ServiceRequests/ServiceRequestWorkflowService.php) runs each change in `DB::transaction` and locks the row with `lockForUpdate`. No lock timeout is set, so a stuck lock makes the request wait until PostgreSQL's default, which is no timeout.
- **Session and cookie config.** [config/session.php](../../../backend/config/session.php) defaults to the database driver, encrypted payloads, a secure cookie, `http_only` without an override and SameSite Lax. Tests cover the behaviour of sessions but not the cookie flags.
- **CSRF.** Sanctum's `EnsureFrontendRequestsAreStateful` reads `config('sanctum.middleware.validate_csrf_token')` on every stateful request. Laravel's `PreventRequestForgery::runningUnitTests()` skips validation in tests, so no test proves `419` today.
- **Security headers and CORS.** [SecurityHeadersTest.php](../../../backend/tests/Feature/Infrastructure/SecurityHeadersTest.php) and [CorsTest.php](../../../backend/tests/Feature/Infrastructure/CorsTest.php) already cover the headers, HSTS in production and the CORS preflight.
- **Error handling.** [ApiExceptionRenderer.php](../../../backend/app/Exceptions/ApiExceptionRenderer.php) maps every exception to the error shape, and [ErrorResponseTest.php](../../../backend/tests/Feature/Infrastructure/ErrorResponseTest.php) proves that no stack trace leaks even with debug mode on.
- **Production safeguards.** `AppServiceProvider` already turns on `uncompromised()` passwords, turns off strict models and prohibits destructive commands in production. `config/app.php` defaults `APP_DEBUG` to `false`. `config/database.php` defaults `sslmode` to `require`.
- **README.** [backend/README.md](../../../backend/README.md) says that setup instructions are added in Phase 8. There is no root README.

### 3.3 Frontend

- [next.config.ts](../../../frontend/next.config.ts) sets `poweredByHeader: false` and applies the static headers from [security-headers.ts](../../../frontend/lib/security/security-headers.ts), with HSTS only in production.
- [proxy.ts](../../../frontend/proxy.ts) builds a fresh nonce based Content Security Policy for every page and redirects visitors without a session cookie. [content-security-policy.ts](../../../frontend/lib/security/content-security-policy.ts) relaxes the policy only in development.
- [eslint.config.mjs](../../../frontend/eslint.config.mjs) already blocks `any`, written `unknown` types, `dangerouslySetInnerHTML` and browser storage.
- [config/environment.ts](../../../frontend/config/environment.ts) and [config/server-environment.ts](../../../frontend/config/server-environment.ts) fail fast when a required variable is missing.
- [frontend/README.md](../../../frontend/README.md) is still the create-next-app boilerplate.
- No feature folder holds a `.gitkeep` file and the Phase 7 placeholder messages are gone.

## 4. Review Reference

These tables are the reference for the tests and the manual checks below.

### 4.1 Route Access Matrix

Columns show the response for a request that reaches the route with otherwise valid input. "Gate" means the route middleware rejects the role with `403 FORBIDDEN` for any id, existing or unknown. "Policy" means a record level rule decides inside the controller.

| Route name | Method and path | Guest | Pending change | Requester | Technician | Admin | Limiter |
| --- | --- | --- | --- | --- | --- | --- | --- |
| (Sanctum) | GET `/sanctum/csrf-cookie` | `204` | `204` | `204` | `204` | `204` | none |
| (health) | GET `/up` | `200` | `200` | `200` | `200` | `200` | none |
| `auth.sign-in` | POST `/api/auth/sign-in` | allowed | allowed | allowed | allowed | allowed | `SignInRateLimiter` |
| `auth.user` | GET `/api/auth/user` | `401` | allowed | allowed | allowed | allowed | `api` |
| `auth.sign-out` | POST `/api/auth/sign-out` | `401` | allowed | allowed | allowed | allowed | `api` |
| `auth.password` | PATCH `/api/auth/password` | `401` | allowed | allowed | allowed | allowed | `api`, `password-change` |
| `sessions.index` | GET `/api/sessions` | `401` | `403` pending | allowed | allowed | allowed | `api` |
| `sessions.destroy-others` | DELETE `/api/sessions/others` | `401` | `403` pending | allowed | allowed | allowed | `api` |
| `sessions.destroy` | DELETE `/api/sessions/{id}` | `401` | `403` pending | own only, else `404` | own only, else `404` | own only, else `404` | `api` |
| `overview.show` | GET `/api/overview` | `401` | `403` pending | Gate | allowed | allowed | `api` |
| `service-requests.store` | POST `/api/service-requests` | `401` | `403` pending | allowed | allowed | allowed | `api` |
| `service-requests.mine` | GET `/api/service-requests/mine` | `401` | `403` pending | allowed | allowed | allowed | `api` |
| `service-requests.index` | GET `/api/service-requests` | `401` | `403` pending | Gate | allowed | allowed | `api` |
| `service-requests.assignees` | GET `/api/service-requests/assignees` | `401` | `403` pending | Gate | allowed | allowed | `api` |
| `service-requests.assign` | POST `/api/service-requests/{id}/assignment` | `401` | `403` pending | Gate | open only, else `409` | open only, else `409` | `api` |
| `service-requests.reassign` | POST `/api/service-requests/{id}/reassignment` | `401` | `403` pending | Gate | Policy, assignee only | Policy, assignee only | `api` |
| `service-requests.status` | PATCH `/api/service-requests/{id}/status` | `401` | `403` pending | Gate | Policy, assignee only | Policy, assignee only | `api` |
| `service-requests.show` | GET `/api/service-requests/{id}` | `401` | `403` pending | Policy, own only, else `404` | allowed | allowed | `api` |
| `users.index` | GET `/api/users` | `401` | `403` pending | Gate | Gate | allowed | `api` |
| `users.store` | POST `/api/users` | `401` | `403` pending | Gate | Gate | allowed | `api`, `user-creation` |
| `users.show` | GET `/api/users/{id}` | `401` | `403` pending | Gate | Gate | allowed | `api` |
| `users.password-reset` | POST `/api/users/{id}/password-reset` | `401` | `403` pending | Gate | Gate | allowed | `api`, `admin-password-reset` |
| `users.sessions.index` | GET `/api/users/{id}/sessions` | `401` | `403` pending | Gate | Gate | allowed | `api` |
| `users.sessions.destroy-all` | DELETE `/api/users/{id}/sessions` | `401` | `403` pending | Gate | Gate | allowed | `api` |
| `users.sessions.destroy` | DELETE `/api/users/{id}/sessions/{id}` | `401` | `403` pending | Gate | Gate | allowed | `api` |

"`403` pending" means `403 PASSWORD_CHANGE_REQUIRED`, for every role and for unknown ids, because `EnsurePasswordChanged` runs before `Authorize` and `SubstituteBindings`.

### 4.2 HTTP Semantics Checklist

| Status | Rule from section 5.8 | Where it is proven |
| --- | --- | --- |
| `401` | Unauthenticated requests | `RouteAccessMatrixTest` for every route, `CurrentUserTest`, `SignOutTest` |
| `403` | Wrong role or pending password change | `RouteAccessMatrixTest`, `PasswordChangeRequiredTest`, the access tests of Phases 4 to 7 |
| `404` | Unknown records and requests a requester does not own | `ServiceRequestAccessTest`, `ShowServiceRequestTest`, `UserManagementAccessTest`, `ClearSessionTest` |
| `409` | Prohibited, no op and conflicting changes | `ChangeServiceRequestStatusTest`, `ReassignServiceRequestTest`, `AssignServiceRequestTest`, `WorkflowConsistencyTest`, the new `WorkflowLockTimeoutTest` |
| `419` | CSRF token mismatch | The new `CsrfProtectionTest` |
| `422` | Invalid input | The validation cases of every write endpoint test |
| `429` | Rate limited requests | `SignInThrottlingTest`, `ChangePasswordTest`, `CreateUserTest`, `ResetUserPasswordTest`, `ErrorResponseTest`, the new `RateLimitCoverageTest` |

## 5. Backend Steps

All commands run from the `backend` folder. On this machine `php` and `composer` come from Herd and are available in PowerShell. Every new file starts with its two to three line header comment and contains no other comments. Every touched file keeps its header comment accurate.

### Step B0. Complete the missing Phase 7 tests

Write the tests of Phase 7 Step B13 exactly as the [Phase 7 plan](phase-7-implementation-plan.md) specifies. In summary:

1. **`tests/Concerns/BuildsWorkflowHistory.php`** with `createServiceRequestAt`, `assignAt`, `reassignAt`, `changeStatusAt` and `resolveAt`. Each helper moves the clock with `travelTo()` and calls the real `ServiceRequestWorkflowService`.
2. **`tests/Unit/Authorization/ViewOverviewGateTest.php`**. The Gate allows an admin and a technician and denies a requester.
3. **`tests/Unit/Services/Overview/ReportingCalendarTest.php`**. Twelve months oldest first, contiguous boundaries, the January year roll over and the `Asia/Colombo` cases.
4. **`tests/Feature/Overview/OverviewAccessTest.php`**. Guest `401`, pending technician and admin `403 PASSWORD_CHANGE_REQUIRED`, requester `403 FORBIDDEN`, technician and admin `200`.
5. **`tests/Feature/Overview/OverviewResponseTest.php`**. The exact keys, the empty database figures, no email or internal id columns and a fixed query count for one and fifteen requests per highlight list.
6. **`tests/Feature/Overview/OverviewStatusCountsTest.php`**, **`OverviewHighlightsTest.php`**, **`OverviewTrendsTest.php`** and **`OverviewWorkloadTest.php`** with every case listed in Phase 7 Step B13.
7. In [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php), rename `STAFF_SERVICE_REQUEST_GATES` to `STAFF_ROUTE_GATES`, add `'overview.show' => GateAbility::ViewOverview` and rename the test to `test_every_staff_route_requires_its_gate`.

Then run Phase 7 Step B14: Pint, the full suite, and the `EXPLAIN` check that the resolved per month query uses `status_histories_status_request_created_index` on the development database.

If a test fails because the Phase 7 code differs from the Phase 7 plan, fix the code when the plan is right, or correct the Phase 7 plan when the code is right and the plan was wrong. Record each case in section 10 of this plan.

**Check.** `composer test` passes with every Phase 1 to Phase 7 test and none skipped.

### Step B1. Record the new engineering rules in the main plan

In [docs/plan/implementation-plan.md](../implementation-plan.md), make these changes so the main plan stays the single source of truth.

1. In section 5.5, add a bullet: "Rate limit counters are stored in the shared database cache, so the limits hold across several application servers."
2. In section 5.10, add a bullet: "When the API runs behind a load balancer or reverse proxy, only the configured proxy addresses are trusted to report the client address."
3. In section 6.1, extend the bullet that starts "Every operation that writes more than one row" with: "A change that waits longer than a configurable lock timeout for the row lock is rejected with a conflict response and writes nothing."
4. In section 4, after the **sessions** bullet, add: "**cache** and **cache_locks** are the Laravel database cache tables that hold rate limit counters."

### Step B2. Trust the configured proxies

1. Create **`config/trusted_proxies.php`**. Following Laravel convention, `env()` is called only in config files.

   | Key | Environment variable | Default |
   | --- | --- | --- |
   | `addresses` | `TRUSTED_PROXIES` | empty list. The value is split on commas, trimmed, and empty entries are dropped, as `config/sanctum.php` does for `stateful`. |

2. Create **`app/Support/Http/TrustedProxyConfigurator.php`**, a final class with one static method, `configure(array $addresses): void`.
   - When the list is empty it does nothing, so no proxy is trusted.
   - Otherwise it calls `TrustProxies::at($addresses)` and `TrustProxies::withHeaders(...)` with the `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port` and `X-Forwarded-Proto` flags from `Symfony\Component\HttpFoundation\Request`, held in a class constant. The AWS ELB and `X-Forwarded-Prefix` headers are not trusted, because the main plan names no such setup.
   - Its header comment says that `TrustProxies` is already in Laravel's global middleware stack and that this class only feeds it the configured addresses.
3. In [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php), add a private `configureTrustedProxies()` method, called from `boot()`, that calls `TrustedProxyConfigurator::configure((array) config('trusted_proxies.addresses'))`. Update the header comment to mention trusted proxies.
4. Add `TRUSTED_PROXIES=` to `backend/.env.example` and the local `.env`.

The `Secure` session cookie and HSTS keep working behind a TLS terminating proxy, because `X-Forwarded-Proto` from a trusted proxy marks the request as secure.

### Step B3. Store the cache in the database

1. Create a migration with `php artisan make:migration create_cache_table`. It is reversible and follows Laravel's own cache schema.

   | Table | Columns |
   | --- | --- |
   | `cache` | `string('key')->primary()`, `mediumText('value')`, `integer('expiration')->index()` |
   | `cache_locks` | `string('key')->primary()`, `string('owner')`, `integer('expiration')->index()` |

   `down()` drops both tables.
2. In `backend/.env.example` and the local `.env`, change `CACHE_STORE=file` to `CACHE_STORE=database`. `config/cache.php` already defaults to `database`. Update its header comment so it says the database store is the production store and backs rate limiting.
3. Keep `CACHE_STORE=array` in `phpunit.xml`, so tests stay isolated from each other.
4. In [config/queue.php](../../../backend/config/queue.php), change the default to `env('QUEUE_CONNECTION', 'sync')` and update its header comment to say the application dispatches no jobs in this version.

The database cache removes an expired entry when it is next read, so rate limit rows do not grow without bound for active keys. Rows for keys that are never read again stay until `php artisan cache:clear` is run, which the README mentions.

**Check.** `php artisan migrate` and `php artisan migrate:rollback --step=1` both succeed against the local PostgreSQL database, and six failed sign ins in the browser create rows in the `cache` table.

### Step B4. Add the workflow lock timeout

1. Create **`config/workflow.php`**.

   | Key | Environment variable | Default |
   | --- | --- | --- |
   | `lock_timeout_milliseconds` | `WORKFLOW_LOCK_TIMEOUT_MILLISECONDS` | `3000`, cast to `int` |

2. Create **`app/Support/Database/LockTimeout.php`**, a final class following the pattern of `CheckConstraint`.

   | Method | Responsibility |
   | --- | --- |
   | `applyToCurrentTransaction(int $milliseconds): void` | On PostgreSQL only, runs `SET LOCAL lock_timeout` with the value written as an integer followed by `ms`. `SET` cannot take bound parameters, so the value is cast to `int` before it is formatted, and no input ever reaches the statement. On any other driver it does nothing. `SET LOCAL` ends with the transaction, so the setting never leaks to other queries. |
   | `isLockTimeout(QueryException $exception): bool` | Returns `true` when `$exception->errorInfo[0]` equals the SQLSTATE `55P03`, held in a class constant. |

   The driver name, the SQLSTATE and the unit suffix are class constants.
3. In [ServiceRequestWorkflowService.php](../../../backend/app/Services/ServiceRequests/ServiceRequestWorkflowService.php):
   - Receive the timeout through `#[Config('workflow.lock_timeout_milliseconds')]` in the constructor, as `ReportingCalendar` receives its settings.
   - Add a private `runLocked(Closure $change): void` method. It calls `DB::transaction`, and inside it first calls `LockTimeout::applyToCurrentTransaction()` and then runs `$change`. Around the transaction it catches `QueryException`, throws `ConcurrentUpdateException` when `LockTimeout::isLockTimeout()` is true and rethrows anything else unchanged.
   - `assignToSelf`, `reassign` and `changeStatus` call `runLocked` instead of `DB::transaction`. Their rules do not change.
   - Update the header comment to mention the lock timeout.
4. Add `WORKFLOW_LOCK_TIMEOUT_MILLISECONDS=3000` to `backend/.env.example` and the local `.env`.

The exception is caught outside the transaction, because PostgreSQL aborts a transaction after a lock timeout and Laravel has already rolled it back by then. Nothing is written.

### Step B5. Write the hardening tests

All feature tests use `RefreshDatabase` and `InteractsWithFrontendSession` where they sign in, following the earlier phases. Routes used only by a test are registered in that test's `setUp` method.

**`tests/Support/EnforcedValidateCsrfToken.php`** is a test only class that extends `Illuminate\Foundation\Http\Middleware\ValidateCsrfToken` and overrides `runningUnitTests()` to return `false`. Its header comment states that it exists only so tests can prove CSRF protection. Add the `Tests\Support` folder under the existing `Tests\` autoload, so no Composer change is needed.

**`tests/Feature/Security/RouteAccessMatrixTest.php`**

- A constant maps every API route name to the roles its Gate allows, built from section 4.1. Routes with no role Gate list every role.
- `test_every_api_route_has_an_access_entry` walks the route collection and fails when a route under `api/` has no entry, or when an entry names a route that no longer exists. Every future route must be classified.
- A data provider yields every route except sign in. For each one, a guest receives `401 UNAUTHENTICATED`, both with an existing id and with an unknown id where the route has parameters.
- For every route outside the three allowed auth routes, a requester, a technician and an admin with a pending password change each receive `403 PASSWORD_CHANGE_REQUIRED`, for an existing and an unknown id.
- For every route and every role its Gate does not allow, a signed in user of that role receives `403 FORBIDDEN`, for an existing and an unknown id.
- Sample URIs replace `{serviceRequest}` and `{user}` with a stored id or with an unknown id held in a constant, and `{sessionIdentifier}` with a value that matches `SessionIdentifier::PATTERN`. Requests send an empty body, because the guards run before validation.

Allowed outcomes for permitted roles, such as `404` for a request a requester does not own or `409` for a request that is not open, stay proven by the existing feature tests listed in section 4.2.

**`tests/Feature/Security/SessionCookieTest.php`**

- After signing in, the session cookie is `HttpOnly`, has `SameSite=lax` and path `/`, and its value is not the session id stored in the database.
- With `session.secure` set to `true`, both the session cookie and the `XSRF-TOKEN` cookie carry `Secure`.
- The `XSRF-TOKEN` cookie from `GET /sanctum/csrf-cookie` is not `HttpOnly`, because the frontend must read it, and has `SameSite=lax`.
- The stored session payload is encrypted. Base64 decoding the `payload` column does not reveal the `login_web` key or the user id.
- Signing out replaces the `XSRF-TOKEN` value, which proves the CSRF token is regenerated as section 5.2 requires.

**`tests/Feature/Security/CsrfProtectionTest.php`**

- In `setUp`, set `sanctum.middleware.validate_csrf_token` to `EnforcedValidateCsrfToken::class`.
- A stateful `POST /api/auth/sign-in` without an `X-XSRF-TOKEN` header returns `419 CSRF_TOKEN_MISMATCH` in the error shape.
- A stateful sign in that sends the `XSRF-TOKEN` cookie value from `GET /sanctum/csrf-cookie` in the `X-XSRF-TOKEN` header, exactly as Axios does, is accepted and returns `200`.
- A token from a different session returns `419`.
- A stateful `GET /api/auth/user` without a token is not rejected for CSRF, because reading requests are not checked.
- After signing in, `PATCH /api/auth/password` without a token returns `419`, which proves protected state changing routes are covered and not only sign in.

**`tests/Feature/Security/RateLimitCoverageTest.php`**

- Every `RateLimiterName` case has a registered limiter, checked with `RateLimiter::limiter()`.
- Every API route except sign in gathers `ThrottleRequests::using(RateLimiterName::Api->value)`.
- `auth.password`, `users.store` and `users.password-reset` gather their own named limiter in addition to `api`.
- The `api` limiter keys by the authenticated user. Two users each get the full allowance, and exceeding it returns `429 TOO_MANY_REQUESTS` with `Retry-After`. The test lowers `rate_limits.api.max_requests_per_minute` to keep the request count small.

**`tests/Feature/Security/SensitiveFieldExposureTest.php`**

- The fixture holds an admin, a technician and a requester, several requests with status and assignee history, and several stored sessions.
- As the admin, it calls every `GET` API route, plus `users.store`, `users.password-reset`, `service-requests.store`, `service-requests.assign`, `service-requests.reassign`, `service-requests.status` and `auth.sign-in`, and collects each JSON body.
- A recursive walk over each body fails on any key named `password`, `remember_token`, `payload` or `password_changed_at`, on any key that ends in `_user_id`, and on `service_request_id` or `tag_id`.
- A key named `email` is allowed only in the bodies of `auth.sign-in`, `auth.user`, `users.index`, `users.show`, `users.store` and `users.password-reset`. A key named `temporary_password` is allowed only in `users.store` and `users.password-reset`.
- No string value in any body equals a raw session id from the `sessions` table.

**`tests/Feature/Security/TrustedProxiesTest.php`**

- `tearDown` calls `TrustProxies::flushState()` so the static setting never leaks into other tests.
- With no trusted proxies, a failed sign in sent from `REMOTE_ADDR` `10.0.0.1` with `X-Forwarded-For: 203.0.113.9` records `10.0.0.1` in the audit row. The forwarded header is ignored.
- After `TrustedProxyConfigurator::configure(['10.0.0.1'])`, the same request records `203.0.113.9`.
- With `10.0.0.1` trusted, a request from `10.0.0.2` with the same header still records `10.0.0.2`.
- The test reads the addresses through `config('trusted_proxies.addresses')` after setting it, so the config wiring is exercised and not only the configurator.

**`tests/Unit/Support/Database/LockTimeoutTest.php`**

- `isLockTimeout` returns `true` for a `QueryException` whose `PDOException` has `errorInfo[0]` set to `55P03`, and `false` for `40001`, `23505` and a missing `errorInfo`.
- `applyToCurrentTransaction` runs no statement on SQLite, checked with `DB::listen`.

**`tests/Feature/ServiceRequests/WorkflowLockTimeoutTest.php`**

- SQLite cannot produce a lock timeout, so the test registers `DB::connection()->beforeExecuting()` with a callback that throws a `QueryException` carrying SQLSTATE `55P03` when the history insert of the change runs.
- For self assignment, reassignment and a status change, the response is `409 CONCURRENT_UPDATE`, no history row is written and the request keeps its status and assignee.
- With the callback throwing SQLSTATE `23505` instead, the response is `500 SERVER_ERROR`, which proves only the lock timeout is mapped.

**`tests/Feature/Infrastructure/DatabaseCacheTest.php`**

- With `cache.default` set to `database` for the test, a sign in failure writes a counter row to the `cache` table and `DummyPasswordHash` stores its hash there, so the migration matches what the store expects.

**`tests/Feature/Infrastructure/FrontendEnumParityTest.php`**

- For each pair below, it reads the TypeScript file from `base_path('../frontend/enums/...')`, extracts `Name = "VALUE"` members with a regular expression held in a constant, and asserts the same member names and values in the same order as `cases()` of the PHP enum.

  | PHP enum | TypeScript file |
  | --- | --- |
  | `UserRole` | `user-role.ts` |
  | `ServiceRequestStatus` | `service-request-status.ts` |
  | `ServiceRequestTag` | `service-request-tag.ts` |
  | `ErrorCode` | `error-code.ts` |

- It also asserts that `frontend/constants/error-messages.ts` holds one entry per `ErrorCode` case.
- A missing file fails the test with a clear message, because the repository always holds both applications.

**Regression.** Every Phase 1 to Phase 7 test passes unchanged.

### Step B6. Fix the coding standard findings

1. In [TemporaryPasswordGeneratorTest.php](../../../backend/tests/Unit/Services/Users/TemporaryPasswordGeneratorTest.php), remove the docblock at line 60. The method keeps its `array` return type.
2. Run these searches from the repository root and fix every match outside a header comment.

   | Rule | Command |
   | --- | --- |
   | No docblocks or block comments | `git grep -nE "^\s*(/\*|\*\s|\*/)" -- "*.php" "*.ts" "*.tsx" "*.mjs"` |
   | No line comments after the header | Review the output of `git grep -nE "^\s*//" -- "*.php" "*.ts" "*.tsx" "*.mjs"` and confirm that every match is in the first lines of its file |
   | No em or en dashes | `git grep -nP "\xE2\x80[\x93\x94]"`, which matches the UTF-8 bytes of both dashes |
   | No emojis | `git grep -nP "\xF0\x9F"`, which matches the first bytes of every common emoji |
   | No `any` or `unknown` | `git grep -nwE "any|unknown" -- "frontend/*.ts" "frontend/*.tsx"`, checking that matches are only inside strings or comments of the header |
   | No `env()` outside config | `git grep -n "env(" -- "backend/app" "backend/routes" "backend/database" "backend/bootstrap"` returns nothing |
   | No hardcoded role, status or tag strings in PHP | `git grep -nE "'(ADMIN|TECHNICIAN|REQUESTER|OPEN|ASSIGNED|IN_PROGRESS|RESOLVED|HARDWARE|SOFTWARE|NETWORK|OTHER)'" -- "backend/app" "backend/routes"` returns only the enum files |

3. Spot check naming against section 6.3 in the files added since Phase 5, looking for abbreviations such as `req`, `res`, `usr` or `cfg`.

### Step B7. Verify the production configuration

Run these checks once with a temporary production environment on the development machine. Copy `.env` to a scratch location first and restore it afterwards, so the real local file is never lost.

1. Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database` and a real `SANCTUM_STATEFUL_DOMAINS`, then run `php artisan config:clear`.
2. Run `php artisan config:show app`, `session`, `cors`, `sanctum`, `database`, `cache`, `queue`, `trusted_proxies`, `workflow`, `overview` and `rate_limits`. Confirm that debug is off, the session is encrypted, `http_only` is true, `secure` is true, SameSite is `lax`, `sslmode` is `require`, CORS lists only the frontend origin and the four methods, the cache store is `database` and the queue is `sync`.
3. Confirm that `OVERVIEW_TIME_ZONE` is a valid identifier with `php artisan tinker --execute="echo in_array(config('overview.time_zone'), DateTimeZone::listIdentifiers(), true) ? 'valid' : 'invalid';"`. The Phase 7 plan handed this check to Phase 8.
4. Run `php artisan config:cache`, `php artisan route:cache` and `php artisan event:cache`. They must succeed without errors, which proves no route or config file uses a closure that cannot be cached. `php artisan optimize` is not used, because it also runs `view:cache`, which fails in this API only backend that has no `resources/views` folder. Then run `php artisan route:list --path=api -v` and compare the middleware of every route with section 4.1.
5. Run `php artisan about --only=environment` and confirm production, debug off and the cached state.
6. Run `curl -i http://localhost:8000/api/auth/user` and confirm a `401` error body with no stack trace, the four common security headers and `Strict-Transport-Security`.
7. Run `php artisan migrate:fresh` and confirm it is refused, which proves `DB::prohibitDestructiveCommands` works in production.
8. Run `php artisan optimize:clear`, restore the local `.env` and run `php artisan config:clear`.

The checks that depend on the production server, namely `expose_php = Off` in `php.ini`, the web server version token, HTTPS termination and the trusted proxy addresses, go into the production checklist of the README in Step D1.

### Step B8. Audit dependencies and secrets

1. Run `composer check-platform-reqs --lock` and confirm nothing requires PHP 8.4 or newer.
2. Run `composer audit` and confirm no high or critical advisory. Fix any finding inside the existing constraints, then run the full suite again.
3. Run `git log --all --name-only --pretty=format:` and confirm the only environment files ever committed are the two `.env.example` files.
4. Run `git log --all -p -G "APP_KEY=base64|PRIVATE KEY|DB_PASSWORD=[^y]"` and confirm no real secret appears. `DB_PASSWORD=your-database-password` is the expected placeholder.
5. Confirm `backend/.env.example` and `frontend/.env.example` hold placeholders only and list every variable the code reads. Search `git grep -nhoE "env\('[A-Z_]+'" -- backend/config` and `git grep -nhoE "process\.env\.[A-Z_]+" -- frontend` and compare the names with the example files. Variables with safe defaults that production never needs to change, such as `SESSION_TABLE`, may stay out of the example file, and the README says so.

### Step B9. Format and verify

1. Run `vendor/bin/pint`, then `composer lint`.
2. Run `composer test` and confirm every test passes with none skipped.
3. Run `php artisan migrate` against the development database and confirm the cache tables exist.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before changing any Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/02-guides/content-security-policy.md`, `01-app/02-guides/production-checklist.md` if present, `01-app/03-api-reference/05-config/01-next-config-js/headers.md` and `poweredByHeader.md`, and `01-app/02-guides/environment-variables.md`.

### Step F1. Verify the production build and headers

1. Run `npm run build` with the production values of `NEXT_PUBLIC_API_URL` and `SESSION_COOKIE_NAME`, because `NEXT_PUBLIC_` values are inlined at build time.
2. Confirm that no source map is served. `productionBrowserSourceMaps` is not set, so `.next/static` must hold no `.map` file.
3. Run `npm run start`, then `curl -I http://localhost:3000/sign-in` twice. Confirm:
   - `Content-Security-Policy` holds a nonce that differs between the two responses, `script-src` has no `'unsafe-inline'` or `'unsafe-eval'`, `connect-src` lists only `'self'` and the API origin, and `upgrade-insecure-requests` is present.
   - `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and `Strict-Transport-Security` are present.
   - `X-Powered-By` is absent.
4. Run `curl -I http://localhost:3000/overview` without cookies and confirm a redirect to `/sign-in`.
5. Open every page in a browser as an admin and confirm the console shows no CSP violation and nothing is written to local or session storage.

### Step F2. Confirm the frontend standards

1. Run `npm run lint` and `npm run typecheck` and confirm both pass with no warnings.
2. Search the whole frontend, not only the folders of one phase, for `console.`, `dangerouslySetInnerHTML`, `localStorage`, `sessionStorage` and `innerHTML`, excluding `node_modules` and `.next`, and confirm there are no matches.
3. Search `app` and `components` for string literals between JSX tags and in `aria-label`, `title` and `placeholder` attributes, and confirm every visible text comes from a constant.
4. Read [temporary-password-dialog.tsx](../../../frontend/components/users/temporary-password-dialog.tsx), [users-panel.tsx](../../../frontend/components/users/users-panel.tsx) and [user-detail-panel.tsx](../../../frontend/components/users/user-detail-panel.tsx) and confirm the temporary password lives only in component state and is set back to `null` when the dialog closes, as section 5.12 of the main plan requires.
5. Confirm [http-client.ts](../../../frontend/lib/http/http-client.ts) is the only file that creates an Axios instance for API calls, apart from the plain CSRF request in [csrf-cookie.ts](../../../frontend/lib/http/csrf-cookie.ts), with `git grep -n "axios" -- "frontend/*.ts" "frontend/*.tsx"`.

### Step F3. Audit dependencies

Run `npm audit --omit=dev` and confirm no high or critical advisory. Fix any finding with an exact version that stays inside the current major versions, then repeat Steps F1 and F2.

## 7. Documentation Steps

### Step D1. Write the root README

Create **`README.md`** at the repository root. It is written for a developer setting up the project for the first time and for whoever deploys it. It holds these sections.

1. **Overview.** What the system does, the three roles and the two applications, with links to `docs/plan/implementation-plan.md` and the phase plans.
2. **Prerequisites.** PHP 8.3 or newer with the `pdo_pgsql`, `mbstring`, `openssl` and `intl` extensions, Composer 2, Node.js 20.9 or newer with npm, and PostgreSQL. Record the PostgreSQL version used during development, read with `SELECT version();`.
3. **Repository layout.** `backend`, `frontend` and `docs`.
4. **Backend setup.** In order: `composer install`, copy `.env.example` to `.env`, `php artisan key:generate`, fill in the database values and the initial admin values, `php artisan migrate`, `php artisan db:seed`. Explain that `db:seed` creates the four tags and the initial admin, that the initial admin must change the password on first sign in and that the seeder refuses a weak `INITIAL_ADMIN_PASSWORD`.
5. **Demo data.** `php artisan db:seed --class=DevelopmentSeeder`, which runs only when `APP_ENV=local` and needs `DEVELOPMENT_USER_PASSWORD`.
6. **Frontend setup.** `npm ci`, copy `.env.example` to `.env`, and explain that `SESSION_COOKIE_NAME` must equal the backend's `SESSION_COOKIE`.
7. **Running locally.** `php artisan serve` on port 8000 and `npm run dev` on port 3000, opening `http://localhost:3000` and signing in as the initial admin. Explain that both run on `localhost`, so the session cookie is shared without setting `SESSION_DOMAIN`.
8. **Tests and checks.** `composer test`, `composer lint`, `npm run lint`, `npm run typecheck` and `npm run build`. Explain that backend tests run on in memory SQLite and never touch the configured database, and that the PostgreSQL check constraints and row locking are verified manually, as the Phase 6 plan describes.
9. **Environment variables.** One table per application with each variable, its purpose, its local value and what production must set. Every variable in the two example files appears, including `TRUSTED_PROXIES`, `CACHE_STORE`, `WORKFLOW_LOCK_TIMEOUT_MILLISECONDS`, `OVERVIEW_TIME_ZONE` and `OVERVIEW_STALE_AFTER_DAYS`.
10. **HTTP status codes and error codes.** The error shape and a table of every `ErrorCode` value with its HTTP status and when it is returned, matching section 5.8 of the main plan.
11. **Production checklist.**
    - Backend environment: `APP_ENV=production`, `APP_DEBUG=false`, an `APP_KEY` generated on the server, `APP_URL` and `FRONTEND_URL` with `https`, `DB_SSLMODE=require`, `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN` set to the shared parent domain such as `.example.com`, `SANCTUM_STATEFUL_DOMAINS` set to the frontend host, `CACHE_STORE=database`, `TRUSTED_PROXIES` set to the proxy addresses when a proxy is used, a valid `OVERVIEW_TIME_ZONE`, and `LOG_STACK=daily` with `LOG_LEVEL=warning`.
    - Both applications on hosts that share the same parent domain, such as `api.example.com` and `app.example.com`, as section 5.1 of the main plan requires.
    - Deploy commands: `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan db:seed --force` on the first deploy only, then `php artisan config:cache`, `php artisan route:cache` and `php artisan event:cache`. Do not use `php artisan optimize`, because its view cache step fails in this API only backend. Note that `migrate:fresh` and similar commands are refused in production.
    - Server settings: HTTPS only with HTTP redirected to HTTPS, `expose_php = Off` in `php.ini`, the web server version hidden (for example `server_tokens off` in nginx or `ServerTokens Prod` with `ServerSignature Off` in Apache), and the document root set to `backend/public`.
    - Frontend: build with the production `NEXT_PUBLIC_API_URL` and `SESSION_COOKIE_NAME`, run `npm run start` behind HTTPS.
    - Maintenance: run `php artisan cache:clear` occasionally to remove expired cache rows that are never read again.
12. **Known limitations.** Email integration is not included and temporary passwords are shown once, the frontend has no automated tests, the overview figures are read outside a transaction, lists keep their page number in component state, and audit records have no viewing screen.

The README uses plain text with no em dashes, emojis or decorative punctuation, as section 6.3 of the main plan requires.

### Step D2. Replace the application READMEs

1. Replace [backend/README.md](../../../backend/README.md) with a short page: what the API is, the commands for setup, migration, seeding, tests and formatting, and a link to the root README for the full guide and the production checklist.
2. Replace [frontend/README.md](../../../frontend/README.md), removing the create-next-app boilerplate, with a short page: what the frontend is, the commands for install, development, lint, type check, build and start, the two environment variables and a link to the root README.

### Step D3. Prove a fresh setup works

1. Clone the repository into a new temporary folder outside the working copy and create a new empty PostgreSQL database for it.
2. Follow the root README word for word, without using any knowledge that is not written there. Run every setup, migration, seeding and test command for both applications.
3. Sign in as the initial admin, change the password, create a technician and a requester, raise a request as the requester, take and resolve it as the technician, and open the overview.
4. Fix the README wherever a step was missing, wrong or unclear, and repeat until the setup works from the documented commands alone.
5. Delete the temporary folder and drop the temporary database.

## 8. End to End Verification

Run both applications with production builds behind the local development servers, `php artisan serve` and `npm run start`, after Steps B9, F1 and D3. Confirm each item in a browser with the developer tools open.

1. The full backend suite passes with none skipped, and Pint, ESLint, the type check and the frontend build all pass.
2. A guest who opens any page is sent to `/sign-in`, and every API call made from the browser console without a session returns `401` with the error shape.
3. Each role sees only its permitted navigation, and typing a forbidden page sends the user to their home page. Calling a forbidden endpoint from the console returns `403 FORBIDDEN`.
4. A user with a pending password change receives `403 PASSWORD_CHANGE_REQUIRED` from every endpoint except the three auth routes and is held on `/change-password`.
5. The session cookie is `HttpOnly` and `SameSite=Lax`, the `XSRF-TOKEN` cookie is readable, and removing the `X-XSRF-TOKEN` header from a replayed `PATCH` in the network tab returns `419`.
6. Six quick failed sign ins return `429` with a retry time, and the `cache` table holds the counters.
7. With two `psql` sessions, one holding `SELECT ... FOR UPDATE` on a request inside an open transaction, a status change on that request from the browser returns `409 CONCURRENT_UPDATE` after about three seconds and shows the conflict message. Nothing is written.
8. With `TRUSTED_PROXIES=127.0.0.1` and a request sent with `curl` carrying `X-Forwarded-For: 203.0.113.9`, the failed sign in audit row stores `203.0.113.9`. With the variable empty, it stores `127.0.0.1`.
9. The network tab shows no password hash, remember token, raw session id or internal user id column in any response, and temporary passwords appear only in the create and reset responses, with `Cache-Control: no-store`.
10. Every response from both applications carries the security headers, the frontend carries a nonce based Content Security Policy, and neither sends `X-Powered-By`.
11. The browser console shows no CSP violations on any page, and nothing is written to local or session storage.
12. The fresh setup in Step D3 succeeded from the README alone.

## 9. Done Criteria

Phase 8 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| Every route is reviewed against the authorization rules and HTTP semantics in section 5 | `RouteAccessMatrixTest` passes with an entry for every API route, the checklist in section 4.2 is covered and Step B7.4 compares the cached route list with section 4.1. |
| Cookie flags, CORS, CSRF, rate limits, security headers and production configuration are verified | `SessionCookieTest`, `CsrfProtectionTest`, `RateLimitCoverageTest`, `CorsTest`, `SecurityHeadersTest`, `TrustedProxiesTest` and `DatabaseCacheTest` pass, and Steps B7 and F1 succeed. |
| No response exposes sensitive fields | `SensitiveFieldExposureTest` passes and item 9 of section 8 succeeds. |
| No secrets are committed | Step B8 finds only placeholder values in the history and the example files. |
| The code follows the standards in section 6 | Step B6 and Step F2 find no violation, `FrontendEnumParityTest` passes, and Pint, ESLint and the type check pass. |
| The README covers setup, migration, seeding and test commands for both applications | Steps D1 and D2 are complete. |
| The full test suite passes | `composer test` passes with every Phase 1 to Phase 8 test, including the Phase 7 tests from Step B0, and none skipped. |
| A fresh setup works from the documented commands alone | Step D3 succeeds. |
| Earlier handovers are closed | Step B0 adds the Phase 7 tests, Step B2 adds trusted proxies, Step B3 the shared cache store, Step B4 the lock timeout, Step B7.3 the time zone check, and the README documents the web server settings and the remaining limitations. |
| The main plan reflects the new engineering rules | Step B1 is complete. |

## 10. Known Limitations and Notes

- **Phase 7 deviations.** Any difference between the Phase 7 code and the Phase 7 plan found in Step B0 is listed here when Step B0 is carried out, with the side that was corrected.
- **Dependency advisories.** Any advisory from Step B8 or Step F3 that cannot be fixed inside the current constraints is listed here with its reason.
- **Step B5 carried out late.** The Step B5 tests were not in the repository when Steps B6 to B9 started, so they were written first in the same session. `RouteAccessMatrixTest` also needed `SeedsServiceRequestTags`, because its fixture creates a service request.
- **No view cache.** The backend has no `resources/views` folder, so `php artisan optimize` fails at `view:cache`. Step B7.4 and the deploy commands use `config:cache`, `route:cache` and `event:cache` instead. `php artisan optimize:clear` still works and is used for cleanup.
- **Dependency audit results.** `composer audit` and `npm audit --omit=dev` reported no advisories, so no dependency was updated.
- **Lock timeout in tests.** SQLite cannot produce a lock timeout, so the automated test simulates the PostgreSQL error, and item 7 of section 8 proves the real behaviour on PostgreSQL.
- **Overview read consistency.** The overview queries still run outside a transaction, as confirmed in section 2.1. A refresh always shows a consistent state again.
- **Expired cache rows.** The database cache removes an expired row only when it is read again, so rows for keys that are never read stay until `php artisan cache:clear`.
- **Production server settings.** HTTPS, the PHP and web server version settings and the proxy addresses depend on the production server and are listed in the README checklist. They cannot be proven from the repository.
- **Frontend tests.** The frontend still has no test runner. Its security behaviour is verified through Steps F1 and F2 and section 8.
- **Email integration.** Temporary passwords are still shown once. When email delivery arrives, a new `TemporaryPasswordDelivery` binding replaces the display once implementation and section 3.6 of the main plan removes the display and admin reset privileges.

## 11. Suggested Commit Sequence

Each commit leaves both applications working.

1. `test(backend): add the missing overview access, highlight, trend and workload tests` covers Step B0.
2. `docs: record trusted proxies, the shared cache store and the workflow lock timeout in the main plan` covers Step B1.
3. `feat(backend): trust configured proxies, store the cache in the database and add the workflow lock timeout` covers Steps B2 to B4.
4. `test(backend): add route access, cookie, CSRF, rate limit, sensitive field, proxy and enum parity tests` covers Step B5.
5. `chore: fix coding standard findings and verify the production configuration and dependencies` covers Steps B6 to B9 and F1 to F3, including any dependency updates.
6. `docs: complete the README with setup, migration, seeding, test and production instructions` covers Steps D1 to D3.
