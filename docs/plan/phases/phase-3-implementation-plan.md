# Phase 3 Implementation Plan. Application Layout and Session Management

## 1. Purpose

This document breaks Phase 3 of the [implementation plan](../implementation-plan.md) into ordered, verifiable steps. Phase 3 delivers the signed in application shell and self service session control. On the backend it adds listing the user's own active sessions, clearing a single session and clearing every other session, with audit records. On the frontend it replaces the temporary top bar with a sidebar layout whose navigation depends on the user's role, adds page level role guards and builds the account page with the password change form and the active sessions list.

Every step follows the main plan, in particular sections 2 (User Roles and Access), 3.4 (Session Management), 3.5 (Authentication Audit), 5 (Security Requirements) and 6 (Engineering Standards). It also keeps every convention set in the [Phase 1 plan](phase-1-implementation-plan.md) and the [Phase 2 plan](phase-2-implementation-plan.md), such as the file header comments, enum naming, snake_case JSON, the single error shape and the `EnsurePasswordChanged` route group. Where this document and the main plan disagree, the main plan wins and this document must be corrected.

Phase 3 does not build admin session management. Viewing and clearing any user's sessions belongs to Phase 4 (User Management), and this phase prepares the pieces Phase 4 reuses.

## 2. Decisions

### 2.1 Confirmed Decisions

These decisions were confirmed before writing this plan.

| Topic | Decision |
| --- | --- |
| Clearing the current session | Not allowed through the single session action. The UI marks the current session as "This device" with no end button. The backend rejects the attempt with `409` and the new error code `CURRENT_SESSION_NOT_CLEARABLE`. Users end the current session with Sign out, so there is one way to do it. |
| Navigation items for later pages | The main plan is followed as written. Phase 3 builds no placeholder pages for the requests dashboard, the new request form or the users list. The navigation configuration holds only the items whose pages exist, which are Overview, My requests and Account. Phase 4 adds Users, Phase 5 adds New request and Phase 6 adds the requests dashboard, each in the same change as its page. Until Phase 4, admins and technicians see the same navigation, and requesters do not see Overview. |
| Device description | A small frontend helper turns the stored user agent into a label such as `Chrome on Windows`, using an ordered pattern table held in constants. It falls back to `Unknown device`. No new dependency is added. The full user agent is shown as a tooltip. |
| Opening a page the role cannot use | The frontend replaces the URL with the user's role home page, following the same pattern as the existing guards. The backend still rejects every data call the role is not allowed to make. |

### 2.2 Decisions Made in This Plan

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Endpoint paths | `GET /api/sessions` lists the user's own sessions, `DELETE /api/sessions/others` clears every other session and `DELETE /api/sessions/{sessionIdentifier}` clears one session. Phase 4 adds the admin routes under `/api/users/{user}/sessions`. |
| Session identifier | Each session is exposed through an HMAC SHA-256 of its raw id, keyed with the application key and a fixed purpose prefix. The result is a 64 character lowercase hexadecimal string. It cannot be reversed into the session id, and it cannot be computed without the application key. The backend maps it back by recomputing the identifier for each of the owner's active sessions and comparing with `hash_equals`. Rotating `APP_KEY` changes every identifier, which is harmless because the identifiers are never stored. |
| Active session | A session is active when its `last_activity` is within `SESSION_LIFETIME` minutes, which is the same rule Laravel's database session handler uses to treat a session as expired. Expired rows are never listed. Laravel's session garbage collection lottery removes them, and clearing other sessions also deletes the user's expired rows. Guest sessions have no user and are never listed. |
| Session list size | The session list is not paginated. The number of active sessions per user is small and bounded by the session lifetime, so this is a deliberate exception to the server side pagination rule, which applies to the request and user lists. |
| Session fields | `id` (the identifier), `ip_address`, `user_agent`, `last_active_at` and `is_current`. The raw session id, the payload and the user id are never serialized. |
| Timestamp format | Timestamps in API responses are ISO 8601 strings in UTC, such as `2026-11-20T09:15:00Z`. Later phases use the same format. The frontend formats them in the browser's time zone. |
| Clear responses | Both clear endpoints return `204` with no body. The frontend reloads the session list afterwards, so the backend stays the source of truth. |
| Audit records | One `SESSION_CLEARED_BY_OWNER` row is written for each active session that is ended. Both the actor and the target are the owner, so Phase 4 can query cleared sessions per user the same way for owner and admin events. Clearing other sessions when none are active writes no audit row. |
| Form Requests | The two clear endpoints accept no request body. The identifier is constrained by a route pattern, so a malformed value never reaches a controller and returns `404`. No Form Request is created for them. Every endpoint that accepts input keeps using a Form Request. |
| Record level authorization | A `SessionPolicy` with a `delete` ability checks that the session belongs to the acting user. The lookup is already scoped to the owner, so the policy is defence in depth in this phase. Phase 4 extends it for admins. Ability names come from a backend enum. |
| Role groups on the frontend | `STAFF_ROLES` holds Admin and Technician and `ALL_ROLES` holds every role. Navigation items and route guards refer to these groups instead of listing roles again. Phase 4 adds `ADMIN_ROLES`. |
| Page level role guards | A `(staff)` route group inside `(workspace)` has a layout that wraps its pages in `RequireRole` with `STAFF_ROLES`. The overview page moves into this group without changing its URL. Phase 6 places the requests dashboard in the same group, and Phase 4 adds an `(admin)` group for the users pages. |
| Sidebar layout | On wide screens the sidebar is a fixed column with the brand, the navigation and an account summary with sign out at the bottom. Below the `lg` breakpoint it collapses into a top bar with a menu button that shows and hides the navigation. Items use text labels without icons, so no icon library is needed. |
| Account page | `/account` is reachable by every role and shows the user's name, email and role, the reusable change password form and the active sessions list. The separate `/change-password` page stays for the mandatory change, because a pending user must not see the workspace. |
| Confirmation before ending sessions | Ending one session and ending all other sessions both ask for confirmation in a dialog. A new shared `ConfirmDialog` component is built on the existing `Dialog`, and Phase 4 reuses it for password resets. |
| Data loading state | A frontend only `LoadStatus` enum with `Loading`, `Loaded` and `Failed` describes the state of a loaded list. Phases 4 to 7 reuse it. |
| UI wording | The session actions are labelled "End session" and "End all other sessions", so they are not confused with signing out of the current session. |

## 3. Current State of the Code

The following was found in the repository before Phase 3 starts. Phases 1 and 2 are complete.

### 3.1 Backend

- [routes/api.php](../../../backend/routes/api.php) holds the four auth routes and an empty `EnsurePasswordChanged` group inside the `auth:sanctum` and `api` throttle group. Phase 3 routes go inside that inner group.
- [SessionTerminator.php](../../../backend/app/Services/Sessions/SessionTerminator.php) already has `terminateOtherSessions(User, string)` and `terminateAllSessions(User)`, using the table name from `config('session.table')`.
- [AuthAuditEvent.php](../../../backend/app/Enums/AuthAuditEvent.php) already holds `SessionClearedByOwner` and `SessionClearedByAdmin`. [AuthAuditLogger.php](../../../backend/app/Services/Audit/AuthAuditLogger.php) records any `AuthAuditEntry`.
- [RequestContext.php](../../../backend/app/Data/Auth/RequestContext.php) carries the IP address and user agent for audit records.
- The [sessions migration](../../../backend/database/migrations/2026_11_20_000002_create_sessions_table.php) has `id`, `user_id`, `ip_address`, `user_agent`, `payload` and `last_activity`, with indexes on `user_id` and `last_activity`.
- Laravel's `DatabaseSessionHandler` treats a session as expired when `last_activity` is older than the session lifetime, and its `write` method only updates an existing row when the row was read at the start of the request. A request already in flight on a cleared device therefore cannot bring the deleted row back.
- [ErrorCode.php](../../../backend/app/Enums/ErrorCode.php) has no code for refusing to clear the current session.
- There is no `app/Policies` folder and no Gate or Policy is registered yet.
- [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php) walks every API route, so new routes are checked for `auth:sanctum` and `EnsurePasswordChanged` automatically.
- [InteractsWithFrontendSession.php](../../../backend/tests/Concerns/InteractsWithFrontendSession.php) supports signing in through the API and switching between clients. [ChangePasswordTest.php](../../../backend/tests/Feature/Auth/ChangePasswordTest.php) has a private `sessionCountFor` helper that the session tests also need.

### 3.2 Frontend

- [(workspace)/layout.tsx](../../../frontend/app/(authenticated)/(workspace)/layout.tsx) renders the temporary [workspace-top-bar.tsx](../../../frontend/components/shared/workspace-top-bar.tsx). Both header comments say Phase 3 replaces them.
- [overview/page.tsx](../../../frontend/app/(authenticated)/(workspace)/overview/page.tsx) and [service-requests/mine/page.tsx](../../../frontend/app/(authenticated)/(workspace)/service-requests/mine/page.tsx) are placeholders. Any signed in role can open either one.
- [constants/routes.ts](../../../frontend/constants/routes.ts) already holds `AppRoute.Account`, `ROLE_HOME_ROUTES` and `PUBLIC_ROUTES`.
- [RequireAuthentication](../../../frontend/components/auth/require-authentication.tsx) handles guests and pending password changes. No role guard exists.
- [ChangePasswordForm](../../../frontend/components/auth/change-password-form.tsx) is reusable. It shows its own success notification and calls `onSuccess(user)`.
- [constants/api-endpoints.ts](../../../frontend/constants/api-endpoints.ts) holds only the CSRF cookie and auth endpoints.
- [constants/auth-messages.ts](../../../frontend/constants/auth-messages.ts) has two entries on one line (`SignOutFailed` and `OverviewTitle`), which breaks the formatting standard.
- `DISPLAY_LOCALE` exists in [constants/application.ts](../../../frontend/constants/application.ts) and only the stat card uses it. There is no date formatting helper.
- The `components/sessions`, `services/sessions`, `hooks/sessions` and `types/sessions` folders hold only a `.gitkeep` file.
- There is no frontend test runner, so frontend behaviour is verified with lint, the type check, the build and the manual checks in section 7, as in Phase 2.

## 4. Request Flows

These flows describe the behaviour the steps below must produce. They are the reference for the tests.

### 4.1 Endpoints

| Method | Path | Route name | Success response |
| --- | --- | --- | --- |
| GET | `/api/sessions` | `sessions.index` | `200` with the list of active sessions under `data` |
| DELETE | `/api/sessions/others` | `sessions.destroy-others` | `204` with no body |
| DELETE | `/api/sessions/{sessionIdentifier}` | `sessions.destroy` | `204` with no body |

All three routes require `auth:sanctum`, the `api` rate limiter and `EnsurePasswordChanged`. A session list response looks like this.

```json
{
  "data": [
    {
      "id": "3f9a5c0e7b1d4a2e9c8b6f5d4e3a2b1c0d9e8f7a6b5c4d3e2f1a0b9c8d7e6f5a",
      "ip_address": "203.0.113.24",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36",
      "last_active_at": "2026-11-20T09:15:00Z",
      "is_current": true
    }
  ]
}
```

Sessions are ordered by `last_active_at`, newest first.

### 4.2 Listing Sessions

1. The account page calls `GET /api/sessions`.
2. The backend loads the active sessions of the signed in user, selecting only the id, user id, IP address, user agent and last activity columns. The payload column is never read.
3. For each row it computes the identifier and marks the row whose raw id equals the current session id as current.
4. It returns the sessions through `SessionResource`.

### 4.3 Clearing One Session

1. The user confirms ending a session that is not marked as current. The frontend calls `DELETE /api/sessions/{id}`.
2. A value that is not 64 lowercase hexadecimal characters fails the route pattern and returns `404 NOT_FOUND`.
3. The backend looks for the identifier among the user's own active sessions. If none matches, because the session belongs to someone else, has expired or was already ended, it returns `404 NOT_FOUND`. The response is the same in every case, so it never reveals whether another user's session exists.
4. The `SessionPolicy` confirms the session belongs to the user.
5. If the session is the current one, the backend returns `409 CURRENT_SESSION_NOT_CLEARABLE` and writes nothing.
6. In one transaction, it deletes the session row and records `SESSION_CLEARED_BY_OWNER`.
7. It returns `204`. The frontend shows a success notification and reloads the list.
8. The ended device's next request finds no session row, receives `401 UNAUTHENTICATED` and is sent to sign in by the existing HTTP client and auth context.

### 4.4 Clearing Every Other Session

1. The user confirms ending all other sessions. The frontend calls `DELETE /api/sessions/others`.
2. In one transaction, the backend loads the user's other active sessions, deletes every session row of the user except the current one, which also removes expired rows, and records one `SESSION_CLEARED_BY_OWNER` row for each active session that was ended.
3. It returns `204`. The frontend shows a success notification and reloads the list, which now holds only the current session.

### 4.5 Navigation and Role Guards

1. After sign in, `resolveSignedInRoute` still sends technicians and admins to `/overview` and requesters to `/service-requests/mine`. Phase 3 does not change it.
2. The sidebar shows the navigation items whose role list contains the user's role.
3. When a requester opens `/overview`, `RequireRole` in the `(staff)` layout shows the page loader and replaces the URL with `/service-requests/mine`.
4. `RequireAuthentication` keeps running first, so guests still go to sign in and pending users still go to `/change-password` before any role check.

## 5. Backend Steps

All commands run from the `backend` folder. Every new file starts with its two to three line header comment and contains no other comments. Every touched file keeps its header comment accurate.

### Step B1. Add the new error code

1. In [app/Enums/ErrorCode.php](../../../backend/app/Enums/ErrorCode.php), add `case CurrentSessionNotClearable = 'CURRENT_SESSION_NOT_CLEARABLE';` after `ConcurrentUpdate`, and add it to the `Response::HTTP_CONFLICT` arm of `httpStatus()`.
2. In [lang/en/errors.php](../../../backend/lang/en/errors.php), add `'CURRENT_SESSION_NOT_CLEARABLE' => 'The current session cannot be ended here. Use sign out instead.'`.
3. The frontend mirror is added in Step F5, within the same phase.

The existing `ErrorCodeTest` covers the new case automatically.

### Step B2. Create the session identifier

**`app/Services/Sessions/SessionIdentifier.php`** is a final class that turns raw session ids into the non reversible identifiers the API exposes.

- `public const string PATTERN = '[a-f0-9]{64}';` is the route pattern for identifiers.
- Private constants hold the algorithm, `sha256`, and a purpose prefix, for example `session-identifier:`, so the HMAC can never be confused with another use of the same key.
- The constructor receives the application key through the `#[Config('app.key')]` container attribute, so the class never calls `config()` itself and can be built directly in a unit test.
- `forSessionId(string $sessionId): string` returns `hash_hmac` of the purpose prefix followed by the session id.
- `matches(string $identifier, string $sessionId): bool` compares `forSessionId($sessionId)` with the identifier through `hash_equals`, so the comparison takes constant time.

### Step B3. Create the active session data object

**`app/Data/Sessions/ActiveSession.php`** is a final readonly class with these properties.

| Property | Type | Notes |
| --- | --- | --- |
| `sessionId` | `string` | The raw session id. Used only inside the backend and never serialized. |
| `identifier` | `string` | The value from `SessionIdentifier`. |
| `userId` | `int` | The owner. Used by the policy. |
| `ipAddress` | `?string` | |
| `userAgent` | `?string` | |
| `lastActiveAt` | `CarbonImmutable` | Built from the `last_activity` timestamp. |
| `isCurrent` | `bool` | True when the session is the one making the request. |

### Step B4. Create the active session repository

**`app/Services/Sessions/ActiveSessionRepository.php`** is a final class that reads sessions through the query builder. It receives `SessionIdentifier` through its constructor and reads the table name from `config('session.table')`, as `SessionTerminator` does.

| Method | Responsibility |
| --- | --- |
| `forUser(User $user, string $currentSessionId): Collection` | Returns the user's active sessions as `ActiveSession` objects, ordered by `last_activity` descending and then by `id`. It selects only `id`, `user_id`, `ip_address`, `user_agent` and `last_activity`. |
| `findForUser(User $user, string $identifier, string $currentSessionId): ?ActiveSession` | Returns the session from `forUser` whose identifier matches, using `SessionIdentifier::matches`, or `null`. |

A private method returns the activity threshold as `now()->subMinutes((int) config('session.lifetime'))->getTimestamp()`. A session is active when `last_activity` is greater than or equal to the threshold, which mirrors the handler's expiry rule. The file keeps only its header comment and uses no docblocks, as section 6.3 of the main plan requires.

Phase 4 reuses both methods for the admin view, passing the admin's own session id, which never matches the viewed user's sessions.

### Step B5. Extend the session terminator

In [SessionTerminator.php](../../../backend/app/Services/Sessions/SessionTerminator.php), add the following method and update the header comment so it no longer says that session management comes later.

| Method | Responsibility |
| --- | --- |
| `terminateSession(User $user, string $sessionId): int` | Deletes the row with the given id only when it belongs to the user, and returns the number of deleted rows. |

`terminateOtherSessions` and `terminateAllSessions` stay unchanged.

### Step B6. Create the domain exceptions

Create these in `app/Exceptions`. Each one extends `DomainException` and returns its code from `errorCode()`.

| Exception | Error code |
| --- | --- |
| `SessionNotFoundException` | `NotFound` |
| `CurrentSessionNotClearableException` | `CurrentSessionNotClearable` |

### Step B7. Create the policy and the ability enum

**`app/Enums/PolicyAbility.php`** is a backend only string backed enum. Phase 3 adds `case Delete = 'delete';`. Later phases add their abilities here, so no ability name is written as a string in a controller.

**`app/Policies/SessionPolicy.php`** is a final class with `delete(User $actor, ActiveSession $session): bool`, which returns `true` when `$session->userId` equals the actor's id. It contains no role logic in this phase. Phase 4 adds the admin rule through the user management Gate.

**Registration.** Add a private `configureAuthorization()` method to [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php), called from `boot()`, that calls `Gate::policy(ActiveSession::class, SessionPolicy::class)`. Laravel's automatic policy discovery only covers models, so the registration is explicit. Update the provider's header comment to mention authorization.

### Step B8. Create the session management service

**`app/Services/Sessions/SessionManagementService.php`** is a final class. It receives `ActiveSessionRepository`, `SessionTerminator` and `AuthAuditLogger` through its constructor.

`listOwnSessions(User $owner, string $currentSessionId): Collection`

1. Returns `forUser($owner, $currentSessionId)`.

`findOwnSession(User $owner, string $identifier, string $currentSessionId): ActiveSession`

1. Returns `findForUser(...)`, or throws `SessionNotFoundException` when it returns `null`.

`clearOwnSession(User $owner, ActiveSession $session, RequestContext $context): void`

1. Throws `CurrentSessionNotClearableException` when `$session->isCurrent` is true.
2. Opens a database transaction.
3. Calls `terminateSession($owner, $session->sessionId)`. If it deletes no row, because another request ended the session first, it throws `SessionNotFoundException`, and the transaction writes nothing.
4. Records `SESSION_CLEARED_BY_OWNER` with the owner as actor and target.

`clearOtherOwnSessions(User $owner, string $currentSessionId, RequestContext $context): int`

1. Opens a database transaction.
2. Loads the owner's active sessions and keeps those that are not current.
3. Calls `terminateOtherSessions($owner, $currentSessionId)`.
4. Records one `SESSION_CLEARED_BY_OWNER` entry for each session kept in step 2.
5. Returns the number of active sessions ended.

Audit entries are built through a private method so the entry is defined once.

### Step B9. Create the API resource

**`app/Http/Resources/SessionResource.php`** wraps an `ActiveSession` and returns exactly these fields.

| Field | Value |
| --- | --- |
| `id` | `$this->identifier` |
| `ip_address` | `$this->ipAddress` |
| `user_agent` | `$this->userAgent` |
| `last_active_at` | `$this->lastActiveAt->toIso8601ZuluString()` |
| `is_current` | `$this->isCurrent` |

The raw session id, the user id and the payload are never returned.

### Step B10. Create the controllers

Create single action invokable controllers in `app/Http/Controllers/Sessions`. Each one only reads the request, calls the service and returns a resource or an empty response.

| Controller | Behaviour |
| --- | --- |
| `ListSessionsController` | Calls `listOwnSessions($request->user(), $request->session()->getId())` and returns `SessionResource::collection(...)`. |
| `ClearSessionController` | Receives the `sessionIdentifier` route parameter. Calls `findOwnSession`, then `Gate::authorize(PolicyAbility::Delete->value, $session)`, then `clearOwnSession` with `RequestContext::fromRequest($request)`, and returns `response()->noContent()`. |
| `ClearOtherSessionsController` | Calls `clearOtherOwnSessions` and returns `response()->noContent()`. |

A failed policy check raises an authorization exception that the existing renderer maps to `403 FORBIDDEN`.

### Step B11. Register the routes

Add the routes inside the existing `EnsurePasswordChanged` group in [routes/api.php](../../../backend/routes/api.php). The `others` route is registered before the parameter route, and the parameter is constrained by `SessionIdentifier::PATTERN`, so `others` can never be read as an identifier.

```php
Route::middleware(EnsurePasswordChanged::class)->group(function (): void {
    Route::get('sessions', ListSessionsController::class)->name('sessions.index');
    Route::delete('sessions/others', ClearOtherSessionsController::class)->name('sessions.destroy-others');
    Route::delete('sessions/{sessionIdentifier}', ClearSessionController::class)
        ->where('sessionIdentifier', SessionIdentifier::PATTERN)
        ->name('sessions.destroy');
});
```

No new rate limiter is needed. The routes share the per user `api` limiter.

### Step B12. Write the Phase 3 tests

All feature tests use `RefreshDatabase` and `InteractsWithFrontendSession`.

**`tests/Concerns/InteractsWithStoredSessions.php`** is a new trait with these helpers.

- `storeSession(User $user, array $attributes = []): string` inserts a session row directly, with a random id, an empty payload and the current time as `last_activity` unless overridden, and returns the id. It is used for sessions of other users and for expired sessions.
- `sessionCountFor(User $user): int` counts the user's session rows.
- `sessionIdentifierFor(string $sessionId): string` returns the identifier through the container bound `SessionIdentifier`.
- `currentSessionIdFor(User $user): string` returns the id of the only session row of a user who signed in once.

Move the private `sessionCountFor` helper out of `ChangePasswordTest` and use the trait there instead.

**`tests/Unit/Services/Sessions/SessionIdentifierTest.php`**

- The identifier is 64 lowercase hexadecimal characters and matches `SessionIdentifier::PATTERN`.
- The same session id and key always give the same identifier.
- Different session ids give different identifiers, and a different key gives a different identifier for the same session id.
- The identifier never contains the session id.
- `matches` returns true for the right pair and false otherwise.

**`tests/Feature/Sessions/ListSessionsTest.php`**

- A guest receives `401 UNAUTHENTICATED`.
- A user with a pending password change receives `403 PASSWORD_CHANGE_REQUIRED`.
- A user signed in on two clients sees exactly two sessions, newest first, and only the session of the requesting client has `is_current` set to `true`.
- Each item has exactly `id`, `ip_address`, `user_agent`, `last_active_at` and `is_current`.
- The response body never contains a raw session id, and `id` equals the identifier of the matching row.
- Sessions of other users are never listed.
- A session whose `last_activity` is older than the session lifetime is not listed. The test uses `travel()` or a stored row with an old timestamp.

**`tests/Feature/Sessions/ClearSessionTest.php`**

- Ending another session of the user returns `204`, deletes only that row and writes one `SESSION_CLEARED_BY_OWNER` row with the user as actor and target and the request IP address.
- The ended client's next call to `/api/auth/user` returns `401`, while the current client stays signed in.
- Ending the current session returns `409 CURRENT_SESSION_NOT_CLEARABLE`, keeps the row and writes no audit row.
- Using the identifier of another user's session returns `404 NOT_FOUND`, keeps that row and writes no audit row.
- Using an identifier with the right format that matches no session returns `404`.
- A malformed identifier, such as one that is too short or contains uppercase letters, returns `404`.
- Ending an expired session returns `404`.
- Ending the same session twice returns `204` and then `404`.
- A user with a pending password change receives `403 PASSWORD_CHANGE_REQUIRED`.

**`tests/Feature/Sessions/ClearOtherSessionsTest.php`**

- With three clients signed in, the call returns `204`, keeps only the current session, writes two `SESSION_CLEARED_BY_OWNER` rows and the two other clients receive `401` afterwards.
- Expired rows of the user are deleted but not audited.
- Sessions of other users are not touched.
- With no other sessions, the call returns `204` and writes no audit row.

**`tests/Unit/Policies/SessionPolicyTest.php`**

- `delete` allows the owner and denies any other user, including an admin, in this phase.

**`ProtectedRoutesTest`** needs no change. It confirms automatically that the three new routes carry `auth:sanctum` and `EnsurePasswordChanged`.

### Step B13. Format and verify

1. Run `vendor/bin/pint`, then `composer lint`.
2. Run `composer test` and confirm every Phase 1, Phase 2 and Phase 3 test passes.
3. With `php artisan serve` running, confirm that `curl -i http://localhost:8000/api/sessions` returns `401` with the error shape.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before writing Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/03-api-reference/03-file-conventions/route-groups.md` (especially the rule that two groups must not resolve to the same URL), `layout.md`, `01-app/01-getting-started/` on layouts, pages, linking and navigating, and the `usePathname` and `useRouter` references in `01-app/03-api-reference/04-functions/`.

The steps are split in two parts. Steps F1 to F4 build the layout and the role guards. Steps F5 to F14 build the account page and session management.

### Step F1. Fix the auth messages and add the role groups and navigation constants

1. In [constants/auth-messages.ts](../../../frontend/constants/auth-messages.ts), put `SignOutFailed` and `OverviewTitle` on separate lines.
2. Create **`constants/role-groups.ts`**. It exports `ALL_ROLES: readonly UserRole[]`, built from `Object.values(UserRole)`, and `STAFF_ROLES: readonly UserRole[]`, holding `UserRole.Admin` and `UserRole.Technician`.
3. Create **`types/shared/navigation-item.ts`** with `NavigationItem`, holding `route: AppRoute`, `label: string` and `allowedRoles: readonly UserRole[]`.
4. Create **`constants/navigation.ts`**. It exports `NavigationMessage`, holding the navigation landmark label, the item labels (`Overview`, `My requests`, `Account`), the menu button labels (`Open menu`, `Close menu`) and the account summary label. It also exports `NAVIGATION_ITEMS: readonly NavigationItem[]` in display order.

   | Route | Label | Allowed roles |
   | --- | --- | --- |
   | `AppRoute.Overview` | Overview | `STAFF_ROLES` |
   | `AppRoute.MyServiceRequests` | My requests | `ALL_ROLES` |
   | `AppRoute.Account` | Account | `ALL_ROLES` |

   The header comment states that each later phase adds its item here in the same change as its page.

### Step F2. Add the navigation helpers

Create **`lib/routing/navigation.ts`** with two pure functions.

| Function | Responsibility |
| --- | --- |
| `navigationItemsForRole(role: UserRole): NavigationItem[]` | Returns the items whose `allowedRoles` contain the role, keeping the configured order. |
| `findActiveNavigationItem(items: readonly NavigationItem[], pathname: string): NavigationItem \| null` | Returns the item whose route equals the pathname, or else the item with the longest route that is a parent path of the pathname, such as `/service-requests` for `/service-requests/12`. It returns `null` when nothing matches. |

The longest match rule keeps one item active when routes share a prefix. For example, `/service-requests/mine` activates My requests and not the Phase 6 dashboard item at `/service-requests`.

### Step F3. Add the role guard and the staff route group

**`components/auth/require-role.tsx`** is a client component with the props `allowedRoles: readonly UserRole[]` and `children`. It reads `useAuth()` and `useRouter()`.

- When the user's role is not in `allowedRoles`, it calls `router.replace(ROLE_HOME_ROUTES[user.role])` inside an effect and renders `PageLoader`.
- Otherwise it renders its children.
- It is always rendered inside `RequireAuthentication`, so the user is present. If the user is `null`, it renders `PageLoader` and lets `RequireAuthentication` redirect.

**Route group.**

1. Create **`app/(authenticated)/(workspace)/(staff)/layout.tsx`**, a server component that wraps its children in `RequireRole` with `STAFF_ROLES`. Its header comment explains that every page for technicians and admins only lives in this group and that Phase 6 adds the requests dashboard here.
2. Move `app/(authenticated)/(workspace)/overview/page.tsx` to `app/(authenticated)/(workspace)/(staff)/overview/page.tsx` with `git mv`. The URL stays `/overview`.

The resulting structure is shown below.

```text
app/(authenticated)/
  layout.tsx                                RequireAuthentication
  change-password/page.tsx                  unchanged
  (workspace)/
    layout.tsx                              sidebar shell
    (staff)/
      layout.tsx                            RequireRole with STAFF_ROLES
      overview/page.tsx                     moved, same URL
    service-requests/mine/page.tsx          unchanged, every role
    account/page.tsx                        new, every role
```

Role home routes are unchanged. `ROLE_HOME_ROUTES` in [constants/routes.ts](../../../frontend/constants/routes.ts) stays the single mapping, and each role's home page is reachable by that role, so the guard can never redirect in a loop.

### Step F4. Build the sidebar layout

Create these components in `components/shared/`. The navigation and account summary read `useAuth()`, so they are client components.

| Component | Responsibility |
| --- | --- |
| `sidebar-navigation.tsx` | Renders a `nav` landmark labelled with `NavigationMessage`, holding a list of `next/link` links from `navigationItemsForRole(user.role)`. The active item from `findActiveNavigationItem` gets `aria-current="page"` and the active style. It accepts an optional `onNavigate` callback that runs when a link is clicked. |
| `sidebar-account-summary.tsx` | Shows the user's name, email and a role `Badge` with the label from `USER_ROLE_LABELS`, followed by `SignOutButton`. |
| `workspace-shell.tsx` | The signed in layout. From the `lg` breakpoint it shows a fixed sidebar with `Brand` at the top, `SidebarNavigation` in the middle and `SidebarAccountSummary` at the bottom, next to the white content panel in a `main` element. Below `lg` it shows a top bar with `Brand` and a menu `Button` that toggles the navigation panel. The button sets `aria-expanded` and points `aria-controls` at the panel id from `useId`. The panel closes through the `onNavigate` callback when a link is chosen. |

Then do the following.

1. Replace the contents of [(workspace)/layout.tsx](../../../frontend/app/(authenticated)/(workspace)/layout.tsx) with a server component that renders `WorkspaceShell` around its children. Update its header comment to describe the sidebar layout.
2. Delete `components/shared/workspace-top-bar.tsx` and search for `workspace-top-bar` to confirm nothing else references it.
3. Use only the existing colour tokens from `globals.css`. No new tokens are needed.

All visible text comes from `NavigationMessage`, `UiMessage` or existing constants.

### Step F5. Mirror the new error code and add the endpoints

- In `enums/error-code.ts`, add `CurrentSessionNotClearable = "CURRENT_SESSION_NOT_CLEARABLE"` after `ConcurrentUpdate`.
- In `constants/error-messages.ts`, add the message `The current session cannot be ended here. Use sign out instead.`.
- In `constants/api-endpoints.ts`, add `Sessions = "/api/sessions"` and `OtherSessions = "/api/sessions/others"`, and export `sessionEndpoint(sessionIdentifier: string): string`, which returns `${ApiEndpoint.Sessions}/${encodeURIComponent(sessionIdentifier)}`. Update the header comment to mention the helper for dynamic endpoints.

### Step F6. Add the session types, enum and constants

**Types.**

| File | Type |
| --- | --- |
| `types/sessions/user-session.ts` | `UserSession` with `id: string`, `ip_address: string \| null`, `user_agent: string \| null`, `last_active_at: string` and `is_current: boolean` |
| `types/sessions/user-agent-pattern.ts` | `UserAgentPattern` with `pattern: RegExp` and `label: string` |

**Enum.** `enums/ui/load-status.ts` holds `LoadStatus` with `Loading`, `Loaded` and `Failed`.

**Constants.**

- `constants/session-messages.ts` holds `SessionMessage` with the section title and description, the column headers, the "This device" label, the "End session" and "End all other sessions" button labels, both confirmation dialog titles and descriptions, the success notifications, the "session already ended" notification, the load failure message, the unknown IP address text and the "no other sessions" hint. It also exports `formatDeviceLabel(browser: string | null, platform: string | null): string`, which returns `Chrome on Windows` when both parts are known, only the known part when one is missing and `Unknown device` when both are missing.
- `constants/account-messages.ts` holds `AccountMessage` with the page title, the page description, the profile section title and labels and the password section title and description.
- `constants/user-agent-patterns.ts` holds `BROWSER_PATTERNS` and `PLATFORM_PATTERNS`, each a `readonly UserAgentPattern[]` checked in order. Order matters because user agents contain each other's tokens.

  | Browser pattern | Label |
  | --- | --- |
  | `Edg/` | Edge |
  | `OPR/` | Opera |
  | `Firefox/` or `FxiOS/` | Firefox |
  | `Chrome/` or `CriOS/` | Chrome |
  | `Version/` followed later by `Safari/` | Safari |

  | Platform pattern | Label |
  | --- | --- |
  | `iPhone` or `iPad` | iOS |
  | `Android` | Android |
  | `Windows NT` | Windows |
  | `CrOS` | ChromeOS |
  | `Mac OS X` | macOS |
  | `Linux` | Linux |

### Step F7. Add the formatting helpers

- **`lib/utilities/format-date-time.ts`** exports `formatDateTime(isoTimestamp: string): string`. It uses one module level `Intl.DateTimeFormat` with `DISPLAY_LOCALE`, `dateStyle: "medium"` and `timeStyle: "short"`, so the value appears in the browser's time zone. Later phases reuse it for request and history timestamps.
- **`lib/sessions/describe-user-agent.ts`** exports `describeUserAgent(userAgent: string | null): string`. It finds the first matching browser and platform patterns and returns `formatDeviceLabel(browser, platform)`. A `null` user agent returns `Unknown device`.
- **`lib/http/message-for-error.ts`** exports `messageForError(error: ApiError | null): string`. It returns the error's message, which `createApiError` already takes from `ERROR_MESSAGES`, or the `SERVER_ERROR` message when the error is not an `ApiError`. Components call it as `messageForError(error instanceof ApiError ? error : null)`, following the existing pattern in `resolve-auth-form-errors.ts`, so no `unknown` type is written.

### Step F8. Build the session service

**`services/sessions/session-service.ts`** holds `SessionService`, which extends `BaseApiService`, and exports one shared instance, `sessionService`.

| Method | Call | Returns |
| --- | --- | --- |
| `listOwnSessions()` | `GET ApiEndpoint.Sessions` | `UserSession[]`, unwrapped from `ApiResourceResponse<UserSession[]>` |
| `clearSession(sessionIdentifier: string)` | `DELETE sessionEndpoint(sessionIdentifier)` | `void` |
| `clearOtherSessions()` | `DELETE ApiEndpoint.OtherSessions` | `void` |

### Step F9. Build the sessions hook

**`hooks/sessions/use-own-sessions.ts`** is the single place that loads and changes the user's sessions.

- Its state is a discriminated union: `Loading` with no sessions, `Loaded` with the sessions, or `Failed` with the `ApiError`.
- It loads once on mount, following the same `isActive` cleanup pattern as the auth context, so a late response after unmount is ignored.
- It returns `state`, `reload()`, `clearSession(session: UserSession)` and `clearOtherSessions()`. Both clear actions call the service, then `reload()`, and let an `ApiError` propagate to the caller.
- `reload()` keeps showing the current list while it runs instead of switching back to `Loading`, so the table does not flicker after an action.
- Callbacks use `useCallback` and the returned object uses `useMemo`.

Phase 4 adds a matching hook for any user's sessions and reuses the same presentational components.

### Step F10. Build the shared confirmation dialog

**`components/shared/confirm-dialog.tsx`** is a client component built on `Dialog`.

- Props: `open`, `title`, `description`, `confirmLabel`, an optional `confirmVariant` that defaults to `ButtonVariant.Danger`, `isConfirming`, `onConfirm` and `onCancel`.
- The footer holds a cancel `Button` with `UiMessage.Cancel` and a confirm `Button` that shows its loading state while `isConfirming` is true.
- While `isConfirming` is true, both buttons are disabled and Escape does not close the dialog, so a running request is never left without feedback.

### Step F11. Build the session components

Create these in `components/sessions/`.

| Component | Responsibility |
| --- | --- |
| `sessions-table.tsx` | A presentational component built on the shared `Table`. Columns are Device, IP address, Last active and an action column. The device cell shows `describeUserAgent(session.user_agent)` with the full user agent in a `title` attribute and a "This device" `Badge` when `is_current` is true. A missing IP address shows the unknown text. The action cell shows an "End session" button for sessions that are not current and nothing for the current one. Props are `sessions`, `onClearSession(session)` and `clearingSessionId`. It holds no data loading, so Phase 4 can reuse it. |
| `active-sessions-section.tsx` | A client component that receives the result of `useOwnSessions` as props. It shows `Spinner` while loading, a danger `Alert` with a retry button when loading failed, and otherwise `SessionsTable`. Its header holds the "End all other sessions" button, which is disabled when no other session exists, with the hint text shown instead. It owns the two `ConfirmDialog` instances and the pending state. |

Behaviour of the actions in `active-sessions-section.tsx`:

- On success, it shows the matching success notification.
- On `NOT_FOUND`, it shows the "session already ended" notification and reloads, because the session is gone either way.
- On any other failure, it shows `messageForError(...)` in an error notification.
- `UNAUTHENTICATED` and `PASSWORD_CHANGE_REQUIRED` are already handled by the HTTP client events and the guards, so the component does nothing extra for them.

### Step F12. Build the account page

**Components** in `components/auth/`.

| Component | Responsibility |
| --- | --- |
| `account-profile.tsx` | A client component that shows the user's name, email and role label in a description list. |
| `account-panel.tsx` | A client component that calls `useOwnSessions()` and renders three sections: the profile, the password section with `ChangePasswordForm` and `ActiveSessionsSection`. The form's `onSuccess` handler calls `reload()`, because a password change ends every other session and regenerates the current one. The form already shows its own success notification. |

**Shared section component.** Create `components/shared/content-section.tsx`, a server component with a heading, an optional description, an optional actions slot and its children inside a bordered card. The account sections use it, and later phases reuse it for detail and overview sections.

**Page.** Create **`app/(authenticated)/(workspace)/account/page.tsx`**, a server component that exports metadata with `AccountMessage.PageTitle` and renders `PageHeader` followed by `AccountPanel`.

[ChangePasswordPanel](../../../frontend/components/auth/change-password-panel.tsx) and the `/change-password` page stay unchanged. They remain the destination for the mandatory change.

### Step F13. Remove placeholder files

Delete the `.gitkeep` file of each feature folder that now holds a real file.

- `components/sessions/.gitkeep`
- `services/sessions/.gitkeep`
- `hooks/sessions/.gitkeep`
- `types/sessions/.gitkeep`

The `.gitkeep` files in the `users`, `service-requests` and `overview` folders stay until their phases add files.

### Step F14. Verify the frontend

1. Run `npm run lint` and confirm there are no errors or warnings.
2. Run `npm run typecheck` and confirm it passes.
3. Run `npm run build` and confirm it succeeds and lists `/overview`, `/service-requests/mine` and `/account`.
4. Search the new files for string literals in JSX and confirm every visible text comes from a constant.

## 7. End to End Verification

Run both applications locally with `php artisan serve` and `npm run dev`, and repeat the checks with `npm run start` after a build. Create one technician and one requester with the user factory in Tinker, because user creation through the UI arrives in Phase 4. Confirm each item in a browser with the developer tools open.

1. The admin signs in and lands on `/overview`. The sidebar shows Overview, My requests and Account, with Overview marked as current.
2. The technician sees the same three items.
3. The requester lands on `/service-requests/mine` and sees only My requests and Account.
4. The requester types `/overview` and is sent to `/service-requests/mine` without the overview content flashing.
5. A pending user still goes to `/change-password` before any role check, and the change password page shows no sidebar.
6. At a narrow window width, the sidebar becomes a top bar. The menu button opens and closes the navigation, choosing an item closes it, and `aria-expanded` follows the state.
7. The account page shows the user's name, email and role, the change password form and the active sessions list, with the current session marked "This device" and no end button on it.
8. With the same user signed in on a second browser profile, the list shows two sessions with readable device labels such as `Chrome on Windows`.
9. Ending the second session asks for confirmation, shows the success notification and removes it from the list. The second browser is sent to `/sign-in` on its next request.
10. With three browsers signed in, "End all other sessions" leaves only the current session, and both other browsers are sent to `/sign-in`.
11. Changing the password from the account page shows the success notification and the session list reloads with only the current session.
12. Sending `DELETE /api/sessions/{current identifier}` from the browser console returns `409` with `CURRENT_SESSION_NOT_CLEARABLE`.
13. The network tab shows that no response contains a raw session id, and the `id` values are 64 character hexadecimal strings.
14. The `auth_audit_logs` table holds one `SESSION_CLEARED_BY_OWNER` row for each ended session, with the owner as actor and target.
15. The browser console shows no CSP violations, and nothing is written to local or session storage.

## 8. Done Criteria

Phase 3 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| Users can list their own sessions, clear a single session and clear all other sessions | `ListSessionsTest`, `ClearSessionTest` and `ClearOtherSessionsTest` pass, and items 7 to 11 of section 7 succeed. |
| Clearing sessions writes audit records | The audit assertions in the session tests pass and item 14 of section 7 succeeds. |
| Users cannot see or clear another user's sessions | The cross user tests in `ListSessionsTest` and `ClearSessionTest` and `SessionPolicyTest` pass. |
| Raw session ids are never sent to the frontend | The field and body assertions in `ListSessionsTest` pass, `SessionIdentifierTest` passes and item 13 of section 7 succeeds. |
| The sidebar layout shows navigation driven by the user's role, with role based home redirects | Items 1 to 4 and 6 of section 7 succeed. |
| Each role sees only its permitted navigation | Items 1 to 3 of section 7 succeed. |
| The account page offers password change and the active sessions list | Items 7 and 11 of section 7 succeed. |
| Coding standards | Pint, ESLint and the TypeScript check pass, every new file has only its header comment, and no strings are hardcoded outside enums and constants. |

## 9. Known Limitations and Handover to Later Phases

- **Admin session management.** Phase 4 adds `GET /api/users/{user}/sessions` and the matching clear routes behind the admin Gate. It reuses `ActiveSessionRepository`, `SessionTerminator`, `SessionManagementService`, `SessionPolicy`, `SessionResource`, `SessionsTable` and `ConfirmDialog`, records `SESSION_CLEARED_BY_ADMIN` with the admin as actor and the owner as target, and adds a hook for any user's sessions.
- **Navigation items grow per phase.** Phase 4 adds Users with `ADMIN_ROLES` and an `(admin)` route group. Phase 5 adds New request for every role. Phase 6 adds the requests dashboard for `STAFF_ROLES` inside the `(staff)` group. Each item is added to `NAVIGATION_ITEMS` in the same change as its page.
- **Device labels are a heuristic.** Uncommon browsers show only the platform or `Unknown device`. The full user agent stays available as a tooltip.
- **IP addresses behind a proxy.** Laravel stores the address it sees. If production runs behind a load balancer or reverse proxy, trusted proxies must be configured so the real client address is stored. Phase 8 checks this together with the other production settings.
- **Expired session rows.** Expired rows stay in the table until Laravel's session garbage collection removes them. They are never listed or accepted.
- **Frontend tests.** The frontend still has no test runner, so role navigation and session flows are verified manually through section 7.

## 10. Suggested Commit Sequence

Each commit leaves both applications working.

1. `feat(backend): add session listing and clearing with identifiers, policy and audit records` covers Steps B1 to B11.
2. `test(backend): add session management tests` covers Steps B12 and B13.
3. `feat(frontend): add role based sidebar layout and staff route guard` covers Steps F1 to F4.
4. `feat(frontend): add account page with password change and active sessions` covers Steps F5 to F14.
