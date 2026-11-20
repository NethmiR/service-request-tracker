# Phase 4 Implementation Plan. User Management

## 1. Purpose

This document breaks Phase 4 of the [implementation plan](../implementation-plan.md) into ordered, verifiable steps. Phase 4 gives admins control over accounts. On the backend it adds listing users, viewing one user, creating a user with a generated temporary password, resetting a user's password and viewing and clearing the sessions of any user, all behind one admin Gate and recorded in the audit table. It also places temporary password delivery behind a single interface with a display once implementation. On the frontend it adds the admin only users list with a create user dialog, a user detail page with the reset password action and the user's sessions, and a one time temporary password dialog.

Every step follows the main plan, in particular sections 2 (User Roles and Access), 3.5 (Authentication Audit), 3.6 (User Management), 5 (Security Requirements) and 6 (Engineering Standards). It also keeps every convention set in the [Phase 1 plan](phase-1-implementation-plan.md), the [Phase 2 plan](phase-2-implementation-plan.md) and the [Phase 3 plan](phase-3-implementation-plan.md), such as the file header comments, enum naming, snake_case JSON, the single error shape, ISO 8601 UTC timestamps, the `EnsurePasswordChanged` route group and the reuse of the Phase 3 session pieces. Where this document and the main plan disagree, the main plan wins and this document must be corrected.

Phase 4 does not add editing, deactivating or deleting users, searching or filtering the users list, or email delivery. The main plan does not describe them, so they are out of scope.

## 2. Decisions

### 2.1 Confirmed Decisions

These decisions were confirmed before writing this plan.

| Topic | Decision |
| --- | --- |
| Screen layout | `/users` shows the paginated users list with a "Create user" button that opens a dialog. Clicking a row opens `/users/{id}`, a detail page with the user's profile, the "Reset password" action and that user's sessions. This adds `GET /api/users/{user}`. |
| Admin resetting their own password | Allowed. The admin receives a temporary password, every session of the admin is ended, including the current one, and the next sign in forces a password change. The frontend shows the one time dialog first and only signs the admin out after the dialog is closed, so the password is never lost. |
| Admin session actions | An admin can end one session of a user and can end all sessions of a user. Each ended session writes one audit row. |

### 2.2 Decisions Made in This Plan

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Endpoint paths | `GET /api/users`, `POST /api/users`, `GET /api/users/{user}`, `POST /api/users/{user}/password-reset`, `GET /api/users/{user}/sessions`, `DELETE /api/users/{user}/sessions` and `DELETE /api/users/{user}/sessions/{sessionIdentifier}`. The reset is a `POST` because it creates a new credential and is not idempotent. |
| Admin authorization | One Gate, `manage-users`, allows only the `ADMIN` role. Every `/api/users` route is placed in a group guarded by `Authorize::using(GateAbility::ManageUsers->value)`, so controllers hold no role logic. A failed check returns `403 FORBIDDEN`. Gate names come from a new backend enum, `GateAbility`. |
| Check order for unknown users | A non admin or a user with a pending password change receives `403` even for a user id that does not exist, so the routes never reveal which ids exist to someone who may not use them. This needs `EnsurePasswordChanged` and `Authorize` to run before route model binding, which is configured through the middleware priority list. An admin requesting an unknown id receives `404 NOT_FOUND`. |
| Session policy | `SessionPolicy::delete` allows the owner, as in Phase 3, and now also any user who passes the `manage-users` Gate, as the Phase 3 handover planned. |
| Users list order and size | Newest first, ordered by `id` descending, which uses the primary key index and matches creation order. 15 users per page, held in a named constant. No search or filter. |
| User fields | `id`, `name`, `email`, `role`, `password_change_required` and `created_at`. The users list and the detail page share one resource, `UserResource`. `CurrentUserResource` stays unchanged. |
| Created and reset response | Both return `200` (reset) or `201` (create) with `{"data": {"user": {...}, "temporary_password": "..."}}`. `temporary_password` is `string \| null`. The display once delivery always returns the value. A future email delivery returns `null`, and the frontend simply skips the dialog, so no frontend change is needed when email arrives. |
| No caching of temporary passwords | The create and reset responses carry `Cache-Control: no-store, private`, so neither the browser nor a proxy stores the plain password. |
| Delivery interface | `TemporaryPasswordDelivery` has one method, `deliver(User $user, string $temporaryPassword): ?string`, which returns the value to display once or `null`. The binding to `DisplayOnceTemporaryPasswordDelivery` is the only place that changes when email delivery is added. Delivery runs after the database transaction commits, so a rolled back change never delivers a password. |
| Temporary password format | 16 characters from a cryptographically secure generator, with at least one lowercase letter, one uppercase letter, one digit and one symbol. Characters that are easy to misread when copied by hand (`0`, `O`, `o`, `1`, `l`, `I`) are left out. The length is a named constant and the password passes `Password::defaults()`. |
| Duplicate email | Validated with a unique rule and returned as `422` on `email`. If two admins create the same email at the same moment, the database unique index rejects the second insert, the service turns that into the same `422` and nothing else is written. |
| Rate limits | User creation and admin password resets each get their own named limiter keyed by the admin's id, defaulting to 10 per minute and configurable through environment variables. All `/api/users` routes also share the existing per user `api` limiter. |
| Audit events | `USER_CREATED_BY_ADMIN` and `PASSWORD_RESET_BY_ADMIN` have the admin as actor and the affected user as target. A session ended through the admin routes records `SESSION_CLEARED_BY_ADMIN` with the admin as actor and the owner as target, unless the admin is ending their own session, which records `SESSION_CLEARED_BY_OWNER`, because the main plan names the event by who ended the session. |
| Ending all sessions of a user | Ends every session of the user except the acting admin's current session. For another user this ends everything. For the admin's own row it keeps the current browser signed in, exactly like "End all other sessions" on the account page. One audit row is written per active session ended. Expired rows are deleted without audit rows. |
| Ending the admin's own current session | Rejected with the existing `409 CURRENT_SESSION_NOT_CLEARABLE`, as on the account page. The UI shows no end button on it. |
| Session logic reuse | `SessionManagementService` gains the admin methods, and the Phase 3 self service methods become thin calls to them with the owner as the actor, so the clearing logic exists once. |
| Users list page state | The current page number is kept in component state. Reloading the browser returns to page 1. |
| Frontend session reuse | The Phase 3 `useOwnSessions` hook and `ActiveSessionsSection` component are generalized into a session list hook and a `SessionsSection` component that receive their data source and their text as props. The account page and the user detail page both use them, so no session UI logic is duplicated. |
| Temporary password dialog | Shows the password in a read only monospace field with a "Copy" button that uses the Clipboard API, and a warning that it will not be shown again. It cannot be closed with Escape or the backdrop, only with an explicit "I have saved the password" button, so it is never dismissed by accident. The value lives only in component state and is cleared when the dialog closes. |
| Paginated response check | Phase 1 planned to check `PaginatedResponse` against a real Laravel 13 response in Phase 5. The users list is now the first paginated endpoint, so the check moves to this phase. |

## 3. Current State of the Code

The following was found in the repository before Phase 4 starts. Phases 1, 2 and 3 are complete.

### 3.1 Backend

- [routes/api.php](../../../backend/routes/api.php) holds the four auth routes and the three session routes inside the `EnsurePasswordChanged` group, which sits inside the `auth:sanctum` and `api` throttle group.
- [User.php](../../../backend/app/Models/User.php) has `markPasswordChangeRequired()`, `hasRole()` and an email mutator that trims and lowercases. `password_changed_at` defaults to `null` through `$attributes`, so a newly created user always has a pending password change. `password` is cast as `hashed`.
- The [users migration](../../../backend/database/migrations/2026_11_20_000001_create_users_table.php) has a unique index on `email`, an index on `role` and the role and lowercase email check constraints. There is no index on `created_at`, which is why the list orders by `id`.
- [AuthAuditEvent.php](../../../backend/app/Enums/AuthAuditEvent.php) already holds `PasswordResetByAdmin`, `UserCreatedByAdmin` and `SessionClearedByAdmin`. [AuthAuditLogger.php](../../../backend/app/Services/Audit/AuthAuditLogger.php) records any `AuthAuditEntry`.
- [SessionTerminator.php](../../../backend/app/Services/Sessions/SessionTerminator.php) already has `terminateSession`, `terminateOtherSessions` and `terminateAllSessions`.
- [ActiveSessionRepository.php](../../../backend/app/Services/Sessions/ActiveSessionRepository.php) has `forUser(User, string)` and `findForUser(User, string, string)`. `is_current` is computed by comparing each row with the given session id, so passing the admin's session id never marks another user's session as current.
- [SessionManagementService.php](../../../backend/app/Services/Sessions/SessionManagementService.php) has `listOwnSessions`, `findOwnSession`, `clearOwnSession` and `clearOtherOwnSessions`, with a private builder for `SESSION_CLEARED_BY_OWNER` entries.
- [SessionPolicy.php](../../../backend/app/Policies/SessionPolicy.php) allows only the owner. Its header comment and [SessionPolicyTest.php](../../../backend/tests/Unit/Policies/SessionPolicyTest.php) say admins gain their rule in Phase 4.
- [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php) defines the password policy, the `api` and `password-change` limiters and registers `SessionPolicy`. It has no Gates and no container bindings.
- [PolicyAbility.php](../../../backend/app/Enums/PolicyAbility.php) holds `Delete`. [RateLimiterName.php](../../../backend/app/Enums/RateLimiterName.php) holds `Api` and `PasswordChange`.
- [config/rate_limits.php](../../../backend/config/rate_limits.php) holds the sign in, password change and api limits. Its Phase 2 plan says Phase 4 adds the user creation and admin reset limits.
- [ApiExceptionRenderer.php](../../../backend/app/Exceptions/ApiExceptionRenderer.php) maps `AccessDeniedHttpException` to `403 FORBIDDEN` and a missing model to `404 NOT_FOUND`. A database `UniqueConstraintViolationException` would currently become `500 SERVER_ERROR`.
- [bootstrap/app.php](../../../backend/bootstrap/app.php) does not change the middleware priority. Laravel's default priority runs `SubstituteBindings` before `Authorize`, and `EnsurePasswordChanged` is not in the priority list.
- [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php) walks every API route for `auth:sanctum` and `EnsurePasswordChanged`. [InteractsWithFrontendSession.php](../../../backend/tests/Concerns/InteractsWithFrontendSession.php) and [InteractsWithStoredSessions.php](../../../backend/tests/Concerns/InteractsWithStoredSessions.php) support several clients and direct session rows.
- [UserFactory.php](../../../backend/database/factories/UserFactory.php) has `admin()`, `technician()`, `requester()` and `withPendingPasswordChange()` states.
- Laravel's `Str::password()` does not guarantee both an uppercase and a lowercase letter, so it cannot be used for temporary passwords without a retry loop. A dedicated generator is built instead.

### 3.2 Frontend

- [constants/routes.ts](../../../frontend/constants/routes.ts) already holds `AppRoute.Users`. There is no helper for the user detail path.
- [constants/role-groups.ts](../../../frontend/constants/role-groups.ts) holds `ALL_ROLES` and `STAFF_ROLES`. `ADMIN_ROLES` does not exist yet.
- [constants/navigation.ts](../../../frontend/constants/navigation.ts) holds Overview, My requests and Account. The Phase 3 plan says Phase 4 adds Users with `ADMIN_ROLES`.
- [app/(authenticated)/(workspace)/(staff)/layout.tsx](../../../frontend/app/(authenticated)/(workspace)/(staff)/layout.tsx) shows the pattern for a role guarded route group. There is no `(admin)` group.
- [use-own-sessions.ts](../../../frontend/hooks/sessions/use-own-sessions.ts) loads and clears the user's own sessions through `sessionService`. [active-sessions-section.tsx](../../../frontend/components/sessions/active-sessions-section.tsx) holds the loading, failure, confirmation and notification logic, with the account page text from `SessionMessage`. [sessions-table.tsx](../../../frontend/components/sessions/sessions-table.tsx) is presentational and already reusable.
- [ConfirmDialog](../../../frontend/components/shared/confirm-dialog.tsx), [Dialog](../../../frontend/components/shared/dialog.tsx) with its `dismissible` prop, [Table](../../../frontend/components/shared/table.tsx), [Pagination](../../../frontend/components/shared/pagination.tsx), [Select](../../../frontend/components/shared/select.tsx), [FormField](../../../frontend/components/shared/form-field.tsx), [ContentSection](../../../frontend/components/shared/content-section.tsx) and [PageHeader](../../../frontend/components/shared/page-header.tsx) exist.
- [resolve-auth-form-errors.ts](../../../frontend/lib/auth/resolve-auth-form-errors.ts) turns an `ApiError` into an alert message and field errors. It lives under `lib/auth` but contains nothing auth specific.
- [types/shared/paginated-response.ts](../../../frontend/types/shared/paginated-response.ts) says its fields are checked against a real response in Phase 5.
- [auth-context.tsx](../../../frontend/contexts/auth-context.tsx) exposes `refreshUser()`, which sets the state to signed out when the current user call fails.
- `USER_ROLE_LABELS` exists in [constants/enum-labels.ts](../../../frontend/constants/enum-labels.ts) and `formatDateTime` in [format-date-time.ts](../../../frontend/lib/utilities/format-date-time.ts).
- The `components/users`, `services/users`, `hooks/users` and `types/users` folders hold only a `.gitkeep` file.
- Next.js 16 passes route `params` to pages as a `Promise`, typed with the global `PageProps<'/route'>` helper, as `01-app/03-api-reference/03-file-conventions/dynamic-routes.md` describes.

## 4. Request Flows

These flows describe the behaviour the steps below must produce. They are the reference for the tests.

### 4.1 Endpoints

| Method | Path | Route name | Extra middleware | Success response |
| --- | --- | --- | --- | --- |
| GET | `/api/users` | `users.index` | | `200` with a page of users |
| POST | `/api/users` | `users.store` | user creation limiter, no store cache headers | `201` with the user and the temporary password |
| GET | `/api/users/{user}` | `users.show` | | `200` with the user |
| POST | `/api/users/{user}/password-reset` | `users.password-reset` | admin reset limiter, no store cache headers | `200` with the user and the temporary password |
| GET | `/api/users/{user}/sessions` | `users.sessions.index` | | `200` with the user's active sessions |
| DELETE | `/api/users/{user}/sessions` | `users.sessions.destroy-all` | | `204` with no body |
| DELETE | `/api/users/{user}/sessions/{sessionIdentifier}` | `users.sessions.destroy` | | `204` with no body |

All routes require `auth:sanctum`, the `api` limiter, `EnsurePasswordChanged` and the `manage-users` Gate. `{user}` must be a positive whole number and `{sessionIdentifier}` must match `SessionIdentifier::PATTERN`. Anything else returns `404`.

A users list response looks like this. The `links` and `meta` blocks are Laravel's default paginated resource envelope.

```json
{
  "data": [
    {
      "id": 7,
      "name": "Kasun Perera",
      "email": "kasun.perera@example.com",
      "role": "TECHNICIAN",
      "password_change_required": true,
      "created_at": "2026-11-20T09:15:00Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "path": "...", "per_page": 15, "to": 1, "total": 1, "links": [] }
}
```

A create or reset response looks like this.

```json
{
  "data": {
    "user": {
      "id": 7,
      "name": "Kasun Perera",
      "email": "kasun.perera@example.com",
      "role": "TECHNICIAN",
      "password_change_required": true,
      "created_at": "2026-11-20T09:15:00Z"
    },
    "temporary_password": "Rk7#vQm2xTz9!pWc"
  }
}
```

### 4.2 Listing and Viewing Users

1. The users page calls `GET /api/users?page={n}`.
2. The backend returns the requested page, newest first. A page number beyond the last page returns an empty `data` array with correct `meta`.
3. The detail page calls `GET /api/users/{id}`. An unknown id returns `404 NOT_FOUND` to an admin.

### 4.3 Creating a User

1. The admin opens the create user dialog and enters a name, an email and a role.
2. The frontend posts them to `POST /api/users`.
3. The backend trims the name, trims and lowercases the email, then validates the input. A duplicate email returns `422` on `email`.
4. The backend generates a temporary password.
5. In one transaction, it creates the user with the hashed temporary password, the chosen role and an empty `password_changed_at`, and records `USER_CREATED_BY_ADMIN`.
6. After the commit, it hands the user and the plain password to `TemporaryPasswordDelivery`, which returns the value to display.
7. It returns `201` with the user and the temporary password, with `Cache-Control: no-store, private`.
8. The frontend closes the create dialog, opens the temporary password dialog and reloads the first page of the list.
9. The new user signs in with the temporary password and is sent to the change password page, as built in Phase 2.

### 4.4 Resetting a Password

1. The admin chooses "Reset password" on the detail page and confirms in a dialog. When the target is the admin, the dialog explains that every session, including the current one, will end.
2. The frontend posts to `POST /api/users/{id}/password-reset` with no body.
3. The backend generates a temporary password.
4. In one transaction, it stores the new hash, sets `password_changed_at` to `null`, deletes every session row of the target and records `PASSWORD_RESET_BY_ADMIN`.
5. After the commit, it calls the delivery and returns `200` with the user and the temporary password, with `Cache-Control: no-store, private`.
6. The target's devices receive `401 UNAUTHENTICATED` on their next request and are sent to sign in. Sanctum's `AuthenticateSession` middleware also rejects any session whose stored password hash no longer matches.
7. When the target is another user, the frontend shows the temporary password dialog and reloads the user and the sessions list.
8. When the target is the admin, the current session row no longer exists. The frontend shows the temporary password dialog and sends no other request while it is open. When the admin closes the dialog, the frontend calls `refreshUser()`, which receives `401`, and the guards send the admin to sign in.

### 4.5 Viewing and Ending a User's Sessions

1. The detail page calls `GET /api/users/{id}/sessions`. The backend lists the user's active sessions exactly as in Phase 3, using the admin's session id to compute `is_current`. Only the admin's own current session can ever be marked as current.
2. Ending one session calls `DELETE /api/users/{id}/sessions/{sessionIdentifier}`. The backend looks for the identifier among that user's active sessions, returns `404 NOT_FOUND` when none matches, checks `SessionPolicy`, rejects the admin's own current session with `409 CURRENT_SESSION_NOT_CLEARABLE`, then deletes the row and records the audit event in one transaction.
3. Ending all sessions calls `DELETE /api/users/{id}/sessions`. In one transaction, the backend loads the user's active sessions that are not the admin's current one, deletes every session row of the user except the admin's current session and records one audit row per active session ended.
4. Both return `204`. The frontend shows a success notification and reloads the list.

### 4.6 Navigation and Role Guards

1. The sidebar shows Users only to admins.
2. When a technician or requester opens `/users` or `/users/{id}`, `RequireRole` in the `(admin)` layout shows the page loader and replaces the URL with the role's home page.
3. `RequireAuthentication` still runs first, so guests go to sign in and pending users go to `/change-password` before any role check.

## 5. Backend Steps

All commands run from the `backend` folder. Every new file starts with its two to three line header comment and contains no other comments. Every touched file keeps its header comment accurate.

### Step B1. Add the Gate ability enum and the rate limiter names

1. Create **`app/Enums/GateAbility.php`**, a backend only string backed enum using `ProvidesValues`, with `case ManageUsers = 'manage-users';`. Its header comment says it names the role based Gates, while record level abilities stay in `PolicyAbility`.
2. In [RateLimiterName.php](../../../backend/app/Enums/RateLimiterName.php), add `case UserCreation = 'user-creation';` and `case AdminPasswordReset = 'admin-password-reset';`.

### Step B2. Add the rate limits

In [config/rate_limits.php](../../../backend/config/rate_limits.php), add these keys and update the header comment to mention user management.

| Key | Environment variable | Default |
| --- | --- | --- |
| `user_creation.max_attempts_per_minute` | `USER_CREATION_MAX_ATTEMPTS_PER_MINUTE` | 10 |
| `admin_password_reset.max_attempts_per_minute` | `ADMIN_PASSWORD_RESET_MAX_ATTEMPTS_PER_MINUTE` | 10 |

### Step B3. Create the data transfer objects

Create final readonly classes in `app/Data/Users`.

| Class | Properties |
| --- | --- |
| `NewUser` | `string $name`, `string $email`, `UserRole $role` |
| `IssuedCredentials` | `User $user`, `?string $temporaryPassword` |

`IssuedCredentials::$temporaryPassword` holds the value returned by the delivery, so it is `null` once email delivery replaces the display once implementation.

### Step B4. Create the temporary password generator

**`app/Services/Users/TemporaryPasswordGenerator.php`** is a final class with one public method, `generate(): string`.

- Private constants hold the length, `16`, and four character sets: lowercase letters, uppercase letters, digits and the symbols `!@#$%^&*-_=+?`. The letter and digit sets leave out `0`, `O`, `o`, `1`, `l` and `I`.
- It picks one character from each set and fills the remaining positions from the union of all sets. Each pick uses `random_int`, which is cryptographically secure.
- It shuffles the result with `Random\Randomizer::shuffleBytes()` using the default secure engine, so the guaranteed characters are not always at the start. PHP 8.2 added this class, so it fits the PHP 8.3 target.

The generator only uses single byte characters, so `shuffleBytes` is safe. It is not behind an interface, because the main plan only names delivery as the swappable part.

### Step B5. Create the temporary password delivery interface and implementation

**`app/Contracts/Users/TemporaryPasswordDelivery.php`** is an interface with `deliver(User $user, string $temporaryPassword): ?string`. Its header comment says the return value is shown once to the admin, and that an email implementation returns `null` after sending.

**`app/Services/Users/DisplayOnceTemporaryPasswordDelivery.php`** is a final class implementing the interface. `deliver` returns `$temporaryPassword` unchanged. Its header comment states that it exists only until email integration is added, as section 3.6 of the main plan requires.

**Binding.** In [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php), add a public `$bindings` property that maps `TemporaryPasswordDelivery::class` to `DisplayOnceTemporaryPasswordDelivery::class`. This is the single line that changes when email delivery arrives. Update the header comment to mention container bindings.

### Step B6. Register the Gate and extend the session policy

1. In `AppServiceProvider::configureAuthorization()`, add `Gate::define(GateAbility::ManageUsers->value, fn (User $user): bool => $user->hasRole(UserRole::Admin))`.
2. In `AppServiceProvider::configureRateLimiting()`, register the two new limiters with `Limit::perMinute(...)` from the config keys in Step B2, keyed by the authenticated user's id, following the existing `password-change` limiter.
3. In [SessionPolicy.php](../../../backend/app/Policies/SessionPolicy.php), change `delete` so it returns `true` when the actor owns the session or when `$actor->can(GateAbility::ManageUsers->value)` is true. The role rule stays in the Gate, so the policy contains no role logic. Update the header comment.

### Step B7. Run the guards before route model binding

In [bootstrap/app.php](../../../backend/bootstrap/app.php), inside `withMiddleware`, use the middleware priority list helpers (`prependToPriorityList`) so that `EnsurePasswordChanged` and `Illuminate\Auth\Middleware\Authorize` both run before `Illuminate\Routing\Middleware\SubstituteBindings`. Without this, Laravel resolves `{user}` first, so a technician or a pending user would see `404` for unknown ids and `403` for existing ones, which reveals which ids exist.

Authentication already runs before binding in Laravel's default priority, so guests still receive `401`. The tests in Step B14 prove the order for every role and state. If `Authorize` is already in the default list, confirm the helper moves it instead of adding a second entry, by printing `app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewarePriority()` in Tinker.

### Step B8. Handle a duplicate email from the database

A duplicate email is a validation outcome on a single field, so it keeps the `422` shape and gets no domain exception. The service in Step B10 catches `Illuminate\Database\UniqueConstraintViolationException` on insert and throws `ValidationException::withMessages([CreateUserRequest::EMAIL_FIELD => [__('validation.unique', ['attribute' => CreateUserRequest::EMAIL_FIELD])]])`. The existing renderer maps it to `422 VALIDATION_FAILED`, so the response is identical to the one from the Form Request rule. No new error code is needed.

### Step B9. Create the Form Request and the resources

**`app/Http/Requests/Users/CreateUserRequest.php`**. Field names and the maximum length are class constants.

- `prepareForValidation()` trims `name`, and trims and lowercases `email`, only when each value is a string, following `SignInRequest`.
- `name` is `required`, `string` and `max:255`.
- `email` is `required`, `string`, `email`, `max:255` and `Rule::unique(User::class, 'email')`.
- `role` is `required`, `string` and `Rule::enum(UserRole::class)`.
- `authorize()` returns `true`, because the Gate middleware already authorizes the route and Form Requests hold no role logic.
- `toNewUser(): NewUser` builds the DTO from `validated()`, converting the role with `UserRole::from()`.

The password reset endpoint accepts no body, so it has no Form Request, as Phase 3 decided for body less endpoints.

**`app/Http/Resources/UserResource.php`** returns exactly `id`, `name`, `email`, `role` as the enum value, `password_change_required` from `requiresPasswordChange()` and `created_at` through `toIso8601ZuluString()`. `password`, `remember_token`, `password_changed_at` and `updated_at` are never returned.

**`app/Http/Resources/IssuedCredentialsResource.php`** wraps an `IssuedCredentials` and returns `user` as a `UserResource` and `temporary_password` as the nullable string.

### Step B10. Create the user management service

**`app/Services/Users/UserManagementService.php`** is a final class. It receives `TemporaryPasswordGenerator`, `TemporaryPasswordDelivery`, `SessionTerminator` and `AuthAuditLogger` through its constructor. A public constant holds the page size, `15`.

`paginateUsers(): LengthAwarePaginator`

1. Returns `User::query()->orderByDesc('id')->paginate(self::PAGE_SIZE)`. The users table has no relations to load.

`createUser(User $admin, NewUser $newUser, RequestContext $context): IssuedCredentials`

1. Generates the temporary password.
2. Opens a database transaction.
3. Creates the user with the name, email, role and the plain temporary password, which the `hashed` cast stores as a hash. `password_changed_at` stays `null` through the model default.
4. Records `USER_CREATED_BY_ADMIN` with the admin as actor and the new user as target.
5. Commits. A `UniqueConstraintViolationException` raised by the insert is turned into the `ValidationException` described in Step B8.
6. Calls `deliver($user, $temporaryPassword)` and returns `IssuedCredentials` with the user and the delivered value.

`resetPassword(User $admin, User $target, RequestContext $context): IssuedCredentials`

1. Generates the temporary password.
2. Opens a database transaction.
3. Sets the target's password, calls `markPasswordChangeRequired()` and saves.
4. Calls `terminateAllSessions($target)`. When the target is the admin, this also deletes the current session row, which is the confirmed behaviour.
5. Records `PASSWORD_RESET_BY_ADMIN` with the admin as actor and the target as target.
6. Commits, then calls `deliver` and returns `IssuedCredentials` with the refreshed target.

The plain password is held only in local variables and the returned DTO. It is never logged, stored or added to an exception message.

When the admin resets their own password, the session row is deleted inside the request. Laravel's `DatabaseSessionHandler` only updates a row it read at the start of the request, so the end of request session write cannot bring the row back, as the Phase 3 plan already confirmed for cleared sessions.

### Step B11. Extend the session management service

In [SessionManagementService.php](../../../backend/app/Services/Sessions/SessionManagementService.php), add the admin methods and make the self service methods delegate to them, so each rule is written once. Update the header comment so it covers both the owner and the admin.

| Method | Responsibility |
| --- | --- |
| `listUserSessions(User $owner, string $actorSessionId): Collection` | Returns `forUser($owner, $actorSessionId)`. |
| `findUserSession(User $owner, string $identifier, string $actorSessionId): ActiveSession` | Returns `findForUser(...)`, or throws `SessionNotFoundException`. |
| `clearUserSession(User $actor, User $owner, ActiveSession $session, RequestContext $context): void` | Throws `CurrentSessionNotClearableException` when `$session->isCurrent`. In one transaction, calls `terminateSession($owner, $session->sessionId)`, throws `SessionNotFoundException` when no row was deleted, and records one audit entry. |
| `clearUserSessions(User $actor, User $owner, string $actorSessionId, RequestContext $context): int` | In one transaction, loads the owner's active sessions that are not current, calls `terminateOtherSessions($owner, $actorSessionId)`, records one audit entry per loaded session and returns their count. |

- `listOwnSessions`, `findOwnSession`, `clearOwnSession` and `clearOtherOwnSessions` keep their signatures, so the Phase 3 controllers do not change. They call the methods above with the owner as both actor and owner.
- The private entry builder becomes `sessionClearedEntry(User $actor, User $owner, RequestContext $context): AuthAuditEntry`. It records `SessionClearedByOwner` when the actor and owner are the same user, compared with `is()`, and `SessionClearedByAdmin` otherwise, with the actor as actor and the owner as target.
- `terminateOtherSessions($owner, $actorSessionId)` is correct in both cases. For another user the admin's session id matches none of their rows, so every row is deleted. For the admin's own row the current session is kept.

### Step B12. Create the controllers

Create single action invokable controllers in `app/Http/Controllers/Users`. Each one only reads the request, calls a service and returns a resource or an empty response. The route parameter `{user}` is resolved by implicit model binding.

| Controller | Behaviour |
| --- | --- |
| `ListUsersController` | Returns `UserResource::collection($service->paginateUsers())`. |
| `ShowUserController` | Returns `new UserResource($user)`. |
| `CreateUserController` | Calls `createUser($request->user(), $request->toNewUser(), RequestContext::fromRequest($request))` and returns `IssuedCredentialsResource` with status `201`. |
| `ResetUserPasswordController` | Calls `resetPassword($request->user(), $user, RequestContext::fromRequest($request))` and returns `IssuedCredentialsResource`. |
| `ListUserSessionsController` | Calls `listUserSessions($user, $request->session()->getId())` and returns `SessionResource::collection(...)`. |
| `ClearUserSessionController` | Calls `findUserSession`, then `Gate::authorize(PolicyAbility::Delete->value, $session)`, then `clearUserSession($request->user(), $user, $session, ...)`, and returns `response()->noContent()`. |
| `ClearUserSessionsController` | Calls `clearUserSessions($request->user(), $user, $request->session()->getId(), ...)` and returns `response()->noContent()`. |

### Step B13. Register the routes

**Cache header options.** Create **`app/Support/Http/CacheHeaderOptions.php`**, a final class with `public const string NO_STORE = 'no_store;private';`, so the option string is not written in the routes file.

Add the routes inside the existing `EnsurePasswordChanged` group in [routes/api.php](../../../backend/routes/api.php). The parameter free sessions route is registered before the identifier route, and every parameter is constrained.

```php
Route::middleware(Authorize::using(GateAbility::ManageUsers->value))->group(function (): void {
    Route::get('users', ListUsersController::class)->name('users.index');
    Route::post('users', CreateUserController::class)
        ->middleware([
            ThrottleRequests::using(RateLimiterName::UserCreation->value),
            SetCacheHeaders::using(CacheHeaderOptions::NO_STORE),
        ])
        ->name('users.store');
    Route::get('users/{user}', ShowUserController::class)->whereNumber('user')->name('users.show');
    Route::post('users/{user}/password-reset', ResetUserPasswordController::class)
        ->whereNumber('user')
        ->middleware([
            ThrottleRequests::using(RateLimiterName::AdminPasswordReset->value),
            SetCacheHeaders::using(CacheHeaderOptions::NO_STORE),
        ])
        ->name('users.password-reset');
    Route::get('users/{user}/sessions', ListUserSessionsController::class)
        ->whereNumber('user')
        ->name('users.sessions.index');
    Route::delete('users/{user}/sessions', ClearUserSessionsController::class)
        ->whereNumber('user')
        ->name('users.sessions.destroy-all');
    Route::delete('users/{user}/sessions/{sessionIdentifier}', ClearUserSessionController::class)
        ->whereNumber('user')
        ->where('sessionIdentifier', SessionIdentifier::PATTERN)
        ->name('users.sessions.destroy');
});
```

### Step B14. Update the environment file

Add these placeholders to `backend/.env.example` and the same keys to the local `.env`.

```dotenv
USER_CREATION_MAX_ATTEMPTS_PER_MINUTE=10
ADMIN_PASSWORD_RESET_MAX_ATTEMPTS_PER_MINUTE=10
```

### Step B15. Write the Phase 4 tests

All feature tests use `RefreshDatabase` and `InteractsWithFrontendSession`. Session tests also use `InteractsWithStoredSessions`.

**`tests/Concerns/InteractsWithAuditLogs.php`** is a new trait with `auditLogsFor(AuthAuditEvent $event): Collection`. Move `sessionClearedByOwnerLogs()` from `InteractsWithStoredSessions` onto it as a call with `SessionClearedByOwner`, and update the Phase 3 tests that use it.

**`tests/Unit/Services/Users/TemporaryPasswordGeneratorTest.php`**

- The password is 16 characters long and contains at least one lowercase letter, one uppercase letter, one digit and one symbol. The check runs over many generated values.
- It never contains `0`, `O`, `o`, `1`, `l` or `I`.
- Many generated values are all different.
- Every generated value passes `Validator` with `Password::defaults()`.

**`tests/Unit/Services/Users/DisplayOnceTemporaryPasswordDeliveryTest.php`**

- `deliver` returns the given password unchanged.
- The container resolves `TemporaryPasswordDelivery` to `DisplayOnceTemporaryPasswordDelivery`.

**`tests/Feature/Users/UserManagementAccessTest.php`** uses a data provider over every `users.*` route with a sample method and URI.

- A guest receives `401 UNAUTHENTICATED`.
- A technician and a requester each receive `403 FORBIDDEN`, both for an existing user id and for an id that does not exist.
- An admin with a pending password change receives `403 PASSWORD_CHANGE_REQUIRED`, both for an existing and for an unknown id.
- An admin requesting an unknown user id receives `404 NOT_FOUND`, and a non numeric id returns `404`.

**`tests/Feature/Users/ListUsersTest.php`**

- An admin receives `200` with `data`, `links` and `meta`, and `meta.per_page` equals the page size constant.
- Users are ordered newest first, and the second page holds the next users.
- Each item has exactly `id`, `name`, `email`, `role`, `password_change_required` and `created_at`, and `created_at` is an ISO 8601 UTC string.
- The body never contains `password`, `remember_token` or `password_changed_at`.
- A page beyond the last returns an empty `data` array.

**`tests/Feature/Users/ShowUserTest.php`**

- An admin receives exactly the same fields for one user.

**`tests/Feature/Users/CreateUserTest.php`**

- A valid request returns `201` with the user under `data.user` and a non empty `data.temporary_password`, and the response has `Cache-Control` containing `no-store`.
- The stored user has the chosen role, a lowercase trimmed email, an empty `password_changed_at` and a hash that matches the returned password through `Hash::check`.
- The stored hash is not equal to the plain password.
- One `USER_CREATED_BY_ADMIN` row is written with the admin as actor, the new user as target and the request IP address.
- A user is created for each role.
- The new user can sign in with the temporary password and the sign in response reports `password_change_required` as `true`.
- Missing fields, an invalid email, a name over 255 characters and an unknown role each return `422` on the right field and create no user and no audit row.
- A duplicate email, including one that differs only in case or surrounding spaces, returns `422` on `email`.
- A race on the same email is simulated by a `User::creating` listener, registered only in that test, that inserts a row with the same email just before the real insert. The response is `422` on `email`, only the listener's row exists and no audit row is written.
- Client supplied `password`, `password_changed_at` or `id` fields are ignored.
- Exceeding the user creation limit returns `429`.

**`tests/Feature/Users/ResetUserPasswordTest.php`**

- Resetting another user returns `200` with `data.user` and `data.temporary_password`, and `Cache-Control` contains `no-store`.
- The target's hash now matches the returned password, the old password no longer signs in, and `password_change_required` is `true`.
- Every session row of the target is deleted, and a client signed in as the target receives `401` on its next request.
- Sessions of other users, including the admin's current session, are not touched.
- One `PASSWORD_RESET_BY_ADMIN` row is written with the admin as actor and the target as target.
- Resetting a user who already has a pending change succeeds.
- An admin resetting their own password receives `200` with a temporary password, every session row of the admin is deleted including the current one, the next request from the same client returns `401`, and signing in with the temporary password reports `password_change_required` as `true`.
- Exceeding the admin reset limit returns `429`.
- When a fake `TemporaryPasswordDelivery` that returns `null` is bound, the response has `temporary_password` set to `null`, which proves the frontend contract for future email delivery.

**`tests/Feature/Users/UserSessionsTest.php`**

- An admin listing another user's sessions sees exactly that user's active sessions, with the Phase 3 fields and `is_current` set to `false` on every item. Expired sessions are not listed.
- An admin listing their own sessions through the admin route sees their current session with `is_current` set to `true`.
- Ending one session of another user returns `204`, deletes only that row, writes one `SESSION_CLEARED_BY_ADMIN` row with the admin as actor and the owner as target, and the ended client receives `401`.
- Using the identifier of a session that belongs to a different user than the one in the URL returns `404`, keeps the row and writes no audit row.
- A malformed identifier returns `404`.
- Ending the admin's own current session through the admin route returns `409 CURRENT_SESSION_NOT_CLEARABLE`.
- Ending another of the admin's own sessions through the admin route writes `SESSION_CLEARED_BY_OWNER`.
- Ending all sessions of another user returns `204`, deletes every row of that user including expired rows, writes one `SESSION_CLEARED_BY_ADMIN` row per active session and leaves other users untouched.
- Ending all of the admin's own sessions through the admin route keeps the current session.
- Ending all sessions of a user with no sessions returns `204` and writes no audit row.

**`tests/Unit/Policies/SessionPolicyTest.php`**

- Replace `test_admin_may_not_delete_another_users_session_in_this_phase` with a test that an admin may delete another user's session. Keep the owner and technician tests. Update the header comment.

**`tests/Unit/Authorization/ManageUsersGateTest.php`**

- The `manage-users` Gate allows an admin and denies a technician and a requester.

**`tests/Feature/Auth/ProtectedRoutesTest.php`**

- Add `test_every_user_management_route_requires_the_manage_users_gate`. It walks every route whose name starts with a constant `users.` prefix and asserts that its gathered middleware contains the `Authorize` middleware string built from `Authorize::using(GateAbility::ManageUsers->value)`. New user management routes in later phases are checked automatically.

**Phase 3 regression.** `ListSessionsTest`, `ClearSessionTest` and `ClearOtherSessionsTest` must pass unchanged after the service refactor in Step B11.

### Step B16. Format and verify

1. Run `vendor/bin/pint`, then `composer lint`.
2. Run `composer test` and confirm every Phase 1 to Phase 4 test passes.
3. With `php artisan serve` running, confirm that `curl -i http://localhost:8000/api/users` returns `401` with the error shape.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before writing Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/03-api-reference/03-file-conventions/dynamic-routes.md` (especially the `PageProps` helper and `params` as a `Promise`), `route-groups.md`, `page.md`, `not-found.md` and the `notFound` function in `01-app/03-api-reference/04-functions/`.

The steps are split in three parts. Steps F1 to F5 add the shared pieces and generalize the Phase 3 session code. Steps F6 to F10 build the users feature. Steps F11 to F13 add the pages and verify.

### Step F1. Add the admin role group, the routes and the navigation item

1. In [constants/role-groups.ts](../../../frontend/constants/role-groups.ts), add `ADMIN_ROLES: readonly UserRole[]` holding `UserRole.Admin`.
2. In [constants/routes.ts](../../../frontend/constants/routes.ts), add `userDetailRoute(userId: number): string`, which returns `${AppRoute.Users}/${userId}`, below `serviceRequestDetailRoute`.
3. In [constants/navigation.ts](../../../frontend/constants/navigation.ts), add `Users: "Users"` to `NavigationMessage` and a Users item with `AppRoute.Users` and `ADMIN_ROLES`, placed after My requests and before Account. The longest match rule in `findActiveNavigationItem` already keeps Users active on `/users/12`.

### Step F2. Mirror the endpoints

In [constants/api-endpoints.ts](../../../frontend/constants/api-endpoints.ts), add `Users = "/api/users"` to `ApiEndpoint` and these helpers below the enum.

| Function | Returns |
| --- | --- |
| `userEndpoint(userId: number)` | `/api/users/{userId}` |
| `userPasswordResetEndpoint(userId: number)` | `/api/users/{userId}/password-reset` |
| `userSessionsEndpoint(userId: number)` | `/api/users/{userId}/sessions` |
| `userSessionEndpoint(userId: number, sessionIdentifier: string)` | `/api/users/{userId}/sessions/{encoded identifier}` |

No new error code is needed in this phase.

### Step F3. Check and correct the paginated response type

1. With the backend running and an admin signed in, call `GET /api/users` from the browser and compare the body with [types/shared/paginated-response.ts](../../../frontend/types/shared/paginated-response.ts).
2. Correct any field that differs, such as a `meta.links` item property.
3. Update the header comment so it no longer says the check happens in Phase 5.

### Step F4. Move the form error helper to a shared place

The create user form needs the same alert and field error handling as the auth forms.

1. Move `lib/auth/resolve-auth-form-errors.ts` to `lib/forms/resolve-form-errors.ts` with `git mv`.
2. Rename `AuthFormErrors` to `FormErrors`, `emptyAuthFormErrors` to `emptyFormErrors` and `resolveAuthFormErrors` to `resolveFormErrors`, and update the header comment to say it is shared by every form.
3. Update the imports in `sign-in-form.tsx` and `change-password-form.tsx`, then search for `resolve-auth-form-errors` to confirm nothing still references the old path.

The helper imports `formatRetryAfterMessage` from `constants/auth-messages.ts`, and it is the only file that does. Move that function to `constants/ui-messages.ts` in the same step, since a rate limit message is not auth specific, and update the import in the moved helper.

### Step F5. Generalize the session list hook and section

**Types.** Create **`types/sessions/session-list-source.ts`** with `SessionListSource`, holding `listSessions: () => Promise<UserSession[]>`, `clearSession: (sessionIdentifier: string) => Promise<void>` and `clearSessions: () => Promise<void>`. `clearSessions` ends every session that can be ended, which never includes the current one.

**Hook.**

1. Create **`hooks/sessions/use-session-list.ts`** by moving the body of `use-own-sessions.ts` into `useSessionList(source: SessionListSource): SessionList`. It keeps the same discriminated union state, the request counter that ignores late responses, the non flickering `reload()` and the `useCallback` and `useMemo` usage. It returns `state`, `reload`, `clearSession(session)` and `clearSessions()`. The effect depends on `source`, so a new source loads again.
2. Rewrite **`hooks/sessions/use-own-sessions.ts`** as a thin wrapper that passes a module level constant source built from `sessionService.listOwnSessions`, `clearSession` and `clearOtherSessions`. Rename the exported types to `SessionListState` and `SessionList` and keep `useOwnSessions` as the export.
3. Create **`hooks/sessions/use-user-sessions.ts`** with `useUserSessions(userId: number): SessionList`. It builds the source with `useMemo` from the user service methods in Step F8, keyed by `userId`.

**Text.** In [constants/session-messages.ts](../../../frontend/constants/session-messages.ts), keep the shared table text in `SessionMessage` and move the text that differs per screen into a `SessionSectionCopy` type and two constants.

| Field | `OWN_SESSIONS_COPY` | `USER_SESSIONS_COPY` |
| --- | --- | --- |
| `title` | Active sessions | Active sessions |
| `description` | Devices that are signed in to your account. End any session you do not recognise. | Devices where this user is signed in. Ending a session signs that device out. |
| `endAllLabel` | End all other sessions | End all sessions |
| `endAllTitle` | End all other sessions? | End all sessions of this user? |
| `endAllDescription` | Every device except this one will be signed out and must sign in again. | Every device of this user will be signed out. Your own current session is never ended here. |
| `endAllSucceeded` | All other sessions have been ended. | All sessions of this user have been ended. |
| `nothingToEnd` | This is your only active session. | This user has no other active sessions. |
| `loadFailed` | Your active sessions could not be loaded. | The sessions of this user could not be loaded. |

`SessionSectionCopy` lives in `types/sessions/session-section-copy.ts`.

**Component.**

1. Rename `components/sessions/active-sessions-section.tsx` to `components/sessions/sessions-section.tsx` with `git mv`, and rename the component to `SessionsSection`.
2. Its props become the `SessionList` result plus `copy: SessionSectionCopy`. Every text that differs per screen comes from `copy`, and the shared table text keeps coming from `SessionMessage`.
3. The behaviour is unchanged: loading spinner, failure alert with retry, the two confirmation dialogs, the `NOT_FOUND` notification and reload, and silent handling of `UNAUTHENTICATED` and `PASSWORD_CHANGE_REQUIRED`.
4. In [account-panel.tsx](../../../frontend/components/auth/account-panel.tsx), render `SessionsSection` with `OWN_SESSIONS_COPY`.
5. Search for `active-sessions-section` and `ActiveSessionsSection` to confirm nothing references the old names.

### Step F6. Add the user types, enum and constants

**Types** in `types/users/`, mirroring the wire format in snake_case.

| File | Type |
| --- | --- |
| `managed-user.ts` | `ManagedUser` with `id: number`, `name: string`, `email: string`, `role: UserRole`, `password_change_required: boolean` and `created_at: string` |
| `create-user-request.ts` | `CreateUserRequest` with `name: string`, `email: string` and `role: UserRole` |
| `issued-credentials.ts` | `IssuedCredentials` with `user: ManagedUser` and `temporary_password: string \| null` |

**Enum.** `enums/ui/user-table-column.ts` holds `UserTableColumn` with `Name`, `Email`, `Role`, `PasswordStatus` and `CreatedAt`, following `SessionTableColumn`.

**Constants.**

- `constants/user-fields.ts` holds `CreateUserField`, typed with `satisfies Record<string, keyof CreateUserRequest>`, the `CreateUserFieldName` type and the `CREATE_USER_FIELDS` list, following `constants/auth-fields.ts`.
- `constants/user-messages.ts` holds `UserMessage` with the users page title and description, the table caption, column headers and empty state text, the password status labels ("Change pending" and "Active"), the "Create user" button, the create dialog title, description, field labels, role placeholder and submit label, the detail page title and description, the profile labels, the "Reset password" button, the reset confirmation title and descriptions for another user and for the admin themselves, the temporary password dialog title, description, warning, field label, "Copy", "Copied" and "I have saved the password" labels, the copy failure message, the created and reset success notifications, the user load failure message and the back to users link label. It also exports `USER_ROLE_OPTIONS: SelectOption<UserRole>[]`, built from `ALL_ROLES` and `USER_ROLE_LABELS`, so the role select lists every role in one place.

### Step F7. Add the password status badge mapping

Create **`lib/users/password-status.ts`** with `passwordStatusLabel(user: ManagedUser): string` and `passwordStatusTone(user: ManagedUser): BadgeTone`. A pending change uses the warning tone and an active password uses the success tone. The users table and the detail profile both use these two functions.

### Step F8. Build the user service

**`services/users/user-service.ts`** holds `UserService`, which extends `BaseApiService`, and exports one shared instance, `userService`.

| Method | Call | Returns |
| --- | --- | --- |
| `listUsers(page: number)` | `GET ApiEndpoint.Users` with `params: { page }` | `PaginatedResponse<ManagedUser>` |
| `getUser(userId: number)` | `GET userEndpoint(userId)` | `ManagedUser`, unwrapped |
| `createUser(request: CreateUserRequest)` | `POST ApiEndpoint.Users` | `IssuedCredentials`, unwrapped |
| `resetPassword(userId: number)` | `POST userPasswordResetEndpoint(userId)` | `IssuedCredentials`, unwrapped |
| `listUserSessions(userId: number)` | `GET userSessionsEndpoint(userId)` | `UserSession[]`, unwrapped |
| `clearUserSession(userId: number, sessionIdentifier: string)` | `DELETE userSessionEndpoint(...)` | `void` |
| `clearUserSessions(userId: number)` | `DELETE userSessionsEndpoint(userId)` | `void` |

The query parameter name `page` is a constant in `constants/http.ts`.

### Step F9. Build the user hooks

**`hooks/users/use-users.ts`** loads one page of users.

- Its state is a discriminated union with `LoadStatus`: `Loading`, `Loaded` with the `PaginatedResponse<ManagedUser>`, or `Failed` with the `ApiError`.
- It holds the current page in state, starting at 1, and loads whenever the page changes, ignoring late responses with the same request counter pattern as the session hook.
- It returns `state`, `page`, `goToPage(page: number)`, `isChangingPage` and `reload()`. While a new page loads, the previous page stays visible and `isChangingPage` disables the pagination controls.
- When a loaded page is empty and is not page 1, it moves to the last page reported by `meta.last_page`.

**`hooks/users/use-managed-user.ts`** loads one user by id with the same state shape and returns `state` and `reload()`. A `NOT_FOUND` error is kept in the `Failed` state so the page can show the not found message.

### Step F10. Build the user components

Create these in `components/users/`. All visible text comes from `UserMessage`, `UiMessage` or existing constants.

| Component | Responsibility |
| --- | --- |
| `users-table.tsx` | Presentational, built on the shared `Table`. Columns are Name (a `next/link` to `userDetailRoute(user.id)`), Email, Role (a `Badge` with the label from `USER_ROLE_LABELS`), Password (a `Badge` from `passwordStatusLabel` and `passwordStatusTone`) and Created (`formatDateTime` inside a `time` element). It shows the users empty state when there are no rows. |
| `temporary-password-dialog.tsx` | A client component built on `Dialog` with `dismissible` set to `false`. Props are `credentials: IssuedCredentials \| null` and `onClose`. It is open while `credentials` holds a non null `temporary_password`. It shows the user's name and email, a read only monospace `Input` with the password, a "Copy" button that calls `navigator.clipboard.writeText` and switches its label to "Copied", an error notification when copying fails, and the warning that the password will not be shown again. Its only footer button is "I have saved the password", which calls `onClose`. |
| `create-user-dialog.tsx` | A client component built on `Dialog`. It holds the form values in state, with a name `Input`, an email `Input` with `type="email"` and a role `Select` with `USER_ROLE_OPTIONS` and a placeholder. On submit it calls `userService.createUser`, disables the form and makes the dialog not dismissible while the request runs, and shows field errors through `resolveFormErrors` with `CREATE_USER_FIELDS`. On success it clears the form and calls `onCreated(credentials)`. The parent closes it and opens the temporary password dialog. |
| `users-panel.tsx` | A client component used by the users page. It calls `useUsers()`, renders the "Create user" button in the page actions, a `Spinner` while loading, a danger `Alert` with retry on failure, and otherwise `UsersTable` and `Pagination` driven by `meta`. It owns the create dialog and the temporary password dialog. After a user is created, it shows the success notification, goes to page 1 and reloads. |
| `user-profile.tsx` | Shows the user's name, email, role badge, password status badge and created date in a description list inside `ContentSection`, with the "Reset password" button in the section actions. |
| `user-detail-panel.tsx` | A client component that receives `userId`. It calls `useManagedUser(userId)` and `useUserSessions(userId)`. It shows a back link to `AppRoute.Users`, a `Spinner` while the user loads, an `EmptyState` with the not found text and the back link on `NOT_FOUND`, a danger `Alert` with retry on other failures, and otherwise `UserProfile` followed by `SessionsSection` with `USER_SESSIONS_COPY`. It owns the reset confirmation and the temporary password dialog. |

**Reset behaviour in `user-detail-panel.tsx`.**

- The confirmation uses `ConfirmDialog`. Its description is the self reset text when `userId` equals `useAuth().user.id`, and the standard text otherwise.
- On success for another user, it shows the success notification, opens the temporary password dialog, and reloads the user and the sessions.
- On success for the admin themselves, it opens the temporary password dialog and sends no other request. When the dialog closes, it clears the credentials and calls `refreshUser()` from the auth context. That call receives `401`, the context becomes signed out and `RequireAuthentication` sends the admin to `/sign-in`.
- On failure, it shows `messageForError(...)` in an error notification, except for `UNAUTHENTICATED` and `PASSWORD_CHANGE_REQUIRED`, which the HTTP client events and guards already handle.

The temporary password is held only in the state of the panel that received it and is set back to `null` when the dialog closes, as section 5.12 of the main plan requires. It is never logged, put in a URL or written to browser storage.

### Step F11. Add the admin route group and the pages

```text
app/(authenticated)/(workspace)/
  (staff)/...                               unchanged
  (admin)/
    layout.tsx                              RequireRole with ADMIN_ROLES
    users/page.tsx                          users list
    users/[userId]/page.tsx                 user detail
  service-requests/mine/page.tsx            unchanged
  account/page.tsx                          unchanged
```

1. **`(admin)/layout.tsx`** is a server component that wraps its children in `RequireRole` with `ADMIN_ROLES`. Its header comment explains that every admin only page lives in this group.
2. **`(admin)/users/page.tsx`** is a server component that exports metadata with the users page title and renders `PageHeader` and `UsersPanel`. The "Create user" button lives inside `UsersPanel`, because it needs client state, and is rendered next to the table header.
3. **`(admin)/users/[userId]/page.tsx`** is an async server component typed with `PageProps<'/users/[userId]'>`. It awaits `params`, checks `userId` against a positive whole number pattern held in a constant and calls `notFound()` when it does not match. Otherwise it renders `PageHeader` with the detail title and `UserDetailPanel` with the number.

Two route groups must not resolve to the same URL, and `/users` exists only in `(admin)`, so the structure is valid.

### Step F12. Remove placeholder files

Delete the `.gitkeep` file of each feature folder that now holds a real file.

- `components/users/.gitkeep`
- `services/users/.gitkeep`
- `hooks/users/.gitkeep`
- `types/users/.gitkeep`

The `.gitkeep` files in the `service-requests` and `overview` folders stay until their phases add files.

### Step F13. Verify the frontend

1. Run `npm run lint` and confirm there are no errors or warnings.
2. Run `npm run typecheck` and confirm it passes.
3. Run `npm run build` and confirm it succeeds and lists `/users` and `/users/[userId]`.
4. Search the new files for string literals in JSX and confirm every visible text comes from a constant.
5. Search for `localStorage`, `sessionStorage` and `console.` in `components/users` and `hooks/users` and confirm there are no matches.

## 7. End to End Verification

Run both applications locally with `php artisan serve` and `npm run dev`, and repeat the checks with `npm run start` after a build. Confirm each item in a browser with the developer tools open.

1. The admin signs in and the sidebar shows Overview, My requests, Users and Account. A technician and a requester do not see Users.
2. A technician and a requester who type `/users` or `/users/1` are sent to their home page without the users content flashing.
3. The users page lists the admin with the role badge, the password status and the creation date. With more than 15 users, the pagination controls move between pages.
4. Creating a technician shows the temporary password dialog. Escape and the backdrop do not close it, "Copy" copies the password and "I have saved the password" closes it. The new user appears at the top of the list with "Change pending".
5. The dialog cannot be reopened, and the network tab shows `Cache-Control: no-store` on the create response.
6. Creating a user with an existing email, in a different case, shows the error under the email field.
7. The new technician signs in with the temporary password in a second browser profile, is sent to `/change-password`, changes the password and lands on `/overview`. The users list then shows "Active" for that user.
8. Repeat items 4 and 7 for a requester and an admin, so every role is created once.
9. The technician's detail page shows the profile and the technician's active session from the second browser.
10. Ending that session asks for confirmation, shows the success notification and removes it from the list. The second browser is sent to `/sign-in` on its next request.
11. With the technician signed in on two browsers, "End all sessions" ends both and both browsers are sent to `/sign-in`.
12. Resetting the technician's password shows the temporary password dialog, the technician's browsers are sent to `/sign-in`, the old password no longer works and the temporary password leads to `/change-password`.
13. On the admin's own detail page, the current session is marked "This device" with no end button. "End all sessions" keeps the current browser signed in.
14. Resetting the admin's own password shows the self reset warning in the confirmation, then the temporary password dialog. The page stays usable until the dialog is closed, after which the browser goes to `/sign-in`. Signing in with the temporary password leads to `/change-password`.
15. Opening `/users/999999` as an admin shows the not found message, and `/users/abc` shows the not found page.
16. The `auth_audit_logs` table holds one row for each user creation, password reset and ended session above, with the right actor, target and event.
17. The browser console shows no CSP violations, no response contains a password hash, and nothing is written to local or session storage.

## 8. Done Criteria

Phase 4 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| Admins can list users, create a user with a temporary password, reset a password and view and clear any user's sessions | `ListUsersTest`, `ShowUserTest`, `CreateUserTest`, `ResetUserPasswordTest` and `UserSessionsTest` pass, and items 3 to 14 of section 7 succeed. |
| The temporary password delivery interface exists with the display once implementation | `DisplayOnceTemporaryPasswordDeliveryTest` and the null delivery test in `ResetUserPasswordTest` pass. |
| Only admins reach these endpoints | `UserManagementAccessTest`, `ManageUsersGateTest` and the new `ProtectedRoutesTest` case pass. |
| Reset terminates sessions and sets the pending flag | The session and pending flag assertions in `ResetUserPasswordTest` pass and item 12 of section 7 succeeds. |
| Temporary passwords are secure, satisfy the policy and are returned only once | `TemporaryPasswordGeneratorTest` passes, the `no-store` assertions pass and items 4 and 5 of section 7 succeed. |
| User management events are recorded in the audit table | The audit assertions in the user tests pass and item 16 of section 7 succeeds. |
| An admin can create users of each role, who can then sign in and complete the mandatory password change | Items 7 and 8 of section 7 succeed. |
| Coding standards | Pint, ESLint and the TypeScript check pass, every new file has only its header comment, and no strings are hardcoded outside enums and constants. |

## 9. Known Limitations and Handover to Later Phases

- **Email integration.** When email arrives, a new class implementing `TemporaryPasswordDelivery` replaces the binding in `AppServiceProvider`, returns `null` and the frontend skips the dialog automatically. Section 3.6 of the main plan then removes the display and the admin reset privileges.
- **No editing or deactivation.** Names, emails and roles cannot be changed after creation, and users cannot be deactivated. The main plan does not describe these features.
- **Users list page in the URL.** The current page is not kept in the URL, so a browser reload returns to page 1.
- **Navigation items grow per phase.** Phase 5 adds New request for every role and Phase 6 adds the requests dashboard for `STAFF_ROLES` inside the `(staff)` group.
- **Assignee choices.** Phase 6 needs a list of technicians and admins for reassignment. It adds its own endpoint under the service request routes for staff, because `/api/users` stays admin only.
- **Check constraints in tests.** SQLite still skips the PostgreSQL check constraints. Phase 6 reconsiders moving the tests to PostgreSQL.
- **Frontend tests.** The frontend still has no test runner, so the user management flows are verified manually through section 7.

## 10. Suggested Commit Sequence

Each commit leaves both applications working.

1. `feat(backend): add manage users gate, temporary password delivery and user management endpoints` covers Steps B1 to B14.
2. `test(backend): add user management and admin session tests` covers Steps B15 and B16.
3. `refactor(frontend): share form errors and generalize the sessions hook and section` covers Steps F3 to F5.
4. `feat(frontend): add users list, create user, user detail and temporary password dialog` covers Steps F1, F2 and F6 to F13.
