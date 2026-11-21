# Phase 5 Implementation Plan. Service Request Creation and History

## 1. Purpose

This document breaks Phase 5 of the [implementation plan](../implementation-plan.md) into ordered, verifiable steps. Phase 5 delivers raising and viewing service requests. On the backend it adds the tags, service requests, request tag and both history tables, seeds the predefined tags, and adds request creation, the My Requests list and the request detail with both histories, guarded by a policy. It also adds the development seeder that Phase 2 deferred to this phase. On the frontend it adds the New request page with tag selection, the paginated My Requests page and the request detail page with the status history and assignee history timelines.

Every step follows the main plan, in particular sections 2 (User Roles and Access), 3.7 (Service Requests), 3.11 (History Records), 4 (Data Model), 5 (Security Requirements) and 6 (Engineering Standards). It also keeps every convention set in the [Phase 1 plan](phase-1-implementation-plan.md), the [Phase 2 plan](phase-2-implementation-plan.md), the [Phase 3 plan](phase-3-implementation-plan.md) and the [Phase 4 plan](phase-4-implementation-plan.md), such as the file header comments, enum naming, snake_case JSON, the single error shape, ISO 8601 UTC timestamps, the `EnsurePasswordChanged` route group, the middleware priority list and the page size of 15. Where this document and the main plan disagree, the main plan wins and this document must be corrected.

Phase 5 does not add the request dashboard, assignment, reassignment or status changes. Those belong to Phase 6. Phase 5 creates both history tables and shows their records, but the only history record written in this phase is the initial status record of a new request. Assignee history records are written for the first time in Phase 6, and Phase 5 tests create them through factories to prove the detail page and the current assignee.

## 2. Decisions

### 2.1 Confirmed Decisions

These decisions were confirmed before writing this plan.

| Topic | Decision |
| --- | --- |
| Initial status record | Creating a request writes one status history record with an empty `from_status`, `to_status` set to `OPEN`, an empty `changed_by_user_id` and no remark. An empty "changed by" marks the record as written by the system on creation. Every later record, written from Phase 6 onward, has both a from status and a user. A database check constraint enforces that `from_status` and `changed_by_user_id` are either both empty or both filled. |
| After creating a request | The frontend shows a success notification and navigates to the new request's detail page. |
| Timeline order | The status history and the assignee history on the detail page are shown newest first. |
| Text limits | The title is required, trimmed and at most 150 characters. The description is required, trimmed and at most 5000 characters. |

### 2.2 Decisions Made in This Plan

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Endpoint paths | `POST /api/service-requests` creates a request, `GET /api/service-requests/mine` lists the signed in user's requests and `GET /api/service-requests/{serviceRequest}` returns one request with both histories. Phase 6 adds `GET /api/service-requests` for the dashboard and the workflow routes under the same prefix. |
| Tags on the wire | Tags are sent and returned as the `ServiceRequestTag` enum values, such as `["HARDWARE", "NETWORK"]`, never as database ids. The backend maps them to tag rows. This keeps the frontend independent of database ids and matches the mirrored enums. |
| Tag validation | `tags` must be a list with at least one item, as section 8 of the main plan assumes. Each item must be a distinct enum value that exists in the `tags` table, which covers both "enum membership and existence" from section 5.9. |
| Who may view a request | A new `view-all-service-requests` Gate allows the `ADMIN` and `TECHNICIAN` roles. `ServiceRequestPolicy::view` allows the requester of the request or anyone who passes that Gate. A denied check returns `404 NOT_FOUND` through `Response::denyAsNotFound()`, so a requester cannot tell another user's request from one that does not exist, as section 5.8 requires. Phase 6 reuses the same Gate for the dashboard. |
| Where the policy check runs | The policy check runs inside the controller through `Gate::authorize`, not as route middleware. The Phase 4 middleware priority list runs `Authorize` before `SubstituteBindings`, so a route level `can:view,serviceRequest` would receive the raw id instead of the model. |
| Check order | Guests receive `401`. A user with a pending password change receives `403 PASSWORD_CHANGE_REQUIRED` for every route, including unknown ids, because `EnsurePasswordChanged` already runs before route model binding. An unknown id and a request the user may not view both return `404`. |
| Current assignee | Not stored on the request. `ServiceRequest::currentAssigneeHistory()` is a `hasOne(...)->latestOfMany()` relation on the assignee history, and the current assignee is its `assignedTo` user. It is eager loaded in the list and the detail, as section 6.1 requires. In Phase 5 every real request is unassigned, so the field is `null` until Phase 6 writes assignee history. |
| User references | Every user shown on a request, such as the requester, the assignee and the history actors, is serialized as `{ "id": 1, "name": "..." }` through a new `UserReferenceResource`. Emails and roles are not exposed on request screens. |
| List order and size | Newest first, ordered by `created_at` descending and then by `id` descending, 15 requests per page, held in a named constant. No search or filter. |
| List fields | `id`, `title`, `status`, `tags`, `requested_by`, `assignee` and `created_at`. `requested_by` is always the signed in user on My Requests, and it is included so Phase 6 can reuse the same resource for the dashboard. |
| Detail fields | The list fields plus `description`, `status_history` and `assignee_history`. History records are ordered by `created_at` descending and then by `id` descending, because several records written in one transaction share the same timestamp. |
| Tag order | Tags are returned in the order the enum defines them. The tag seeder inserts the rows in enum order and the relation orders by the tag id. |
| Create response | `201` with the same detail resource, so the frontend can navigate straight to the detail page without a second request. |
| Rate limit | Request creation shares the per user `api` limiter. Section 5.5 of the main plan names its own limits only for sign in, password change, admin password reset and user creation. |
| Remark length | Remarks are created in Phase 6, but the columns are created now. Both remark columns hold at most 1000 characters, held in the same limits class as the title and description so the Phase 6 Form Requests use the same value. |
| Append only histories | Both history models throw on update and delete, like the audit log. The rule moves into a shared `AppendOnly` model trait, and `AuthAuditLog` uses the same trait, so the rule is written once. |
| Development seeder | `DevelopmentSeeder` runs only in the `local` environment and is called by hand, never from `DatabaseSeeder`. It creates demo technicians and requesters with a password read from `DEVELOPMENT_USER_PASSWORD`, and open requests spread over the last twelve months, each with its initial status record. Phase 6 extends it with assigned, in progress and resolved requests written through the workflow service. |
| Shared frontend loading logic | The paging logic of `useUsers` and the single record logic of `useManagedUser` move into two shared hooks, `usePaginatedResource` and `useResource`. The users hooks become thin wrappers, as Phase 4 did for the sessions hook, so the request hooks do not duplicate them. The repeated "loading" and "failed with retry" blocks move into two shared components for the same reason. |
| Navigation | A New request item is added for every role, after My requests. The detail page has no navigation item, so no item is highlighted on `/service-requests/{id}` until Phase 6 adds the dashboard item at `/service-requests`, which then becomes the active parent. |
| Back link on the detail page | Points to My requests in this phase. Phase 6 points it to the dashboard for technicians and admins. |

## 3. Current State of the Code

The following was found in the repository before Phase 5 starts. Phases 1, 2, 3 and 4 are complete and committed. The working copy holds an uncommitted change to [user-detail-panel.tsx](../../../frontend/components/users/user-detail-panel.tsx) and [user-messages.ts](../../../frontend/constants/user-messages.ts) that adds a notification when admins reset their own password. Commit or discard it before starting Phase 5, so the Phase 5 commits stay focused.

### 3.1 Backend

- [routes/api.php](../../../backend/routes/api.php) holds the auth routes, the session routes and the user management routes. The session and user routes sit inside the `EnsurePasswordChanged` group, which sits inside the `auth:sanctum` and `api` throttle group.
- [bootstrap/app.php](../../../backend/bootstrap/app.php) sets the middleware priority so that `EnsurePasswordChanged` and `Authorize` run before `SubstituteBindings`.
- [ServiceRequestStatus.php](../../../backend/app/Enums/ServiceRequestStatus.php) and [ServiceRequestTag.php](../../../backend/app/Enums/ServiceRequestTag.php) exist with the `ProvidesValues` trait. Neither holds any behaviour yet. Transition rules arrive in Phase 6.
- [ErrorCode.php](../../../backend/app/Enums/ErrorCode.php) already holds `NOT_FOUND`, `VALIDATION_FAILED` and the Phase 6 conflict codes. Phase 5 needs no new error code.
- [PolicyAbility.php](../../../backend/app/Enums/PolicyAbility.php) holds `Delete`. [GateAbility.php](../../../backend/app/Enums/GateAbility.php) holds `ManageUsers`.
- [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php) registers the `manage-users` Gate and `SessionPolicy` in `configureAuthorization()`.
- [CheckConstraint.php](../../../backend/app/Support/Database/CheckConstraint.php) has `allowedValues` and `lowercase`. A PostgreSQL `IN` check passes for an empty value, so `allowedValues` already works for nullable columns. It has no method for a constraint across two columns.
- [AuthAuditLog.php](../../../backend/app/Models/AuthAuditLog.php) enforces the append only rule in its own `booted()` method, with the message in `APPEND_ONLY_MESSAGE`, which [AuthAuditLogTest.php](../../../backend/tests/Feature/Audit/AuthAuditLogTest.php) references.
- [User.php](../../../backend/app/Models/User.php) has no relations. `Model::shouldBeStrict()` is on outside production, so lazy loading throws in tests and in local development.
- [DatabaseSeeder.php](../../../backend/database/seeders/DatabaseSeeder.php) calls only `InitialAdminSeeder`, and its header comment says Phase 5 adds the tags. `InitialAdminSeeder` throws when the admin environment variables are invalid, so tests must never run `DatabaseSeeder`.
- [UserFactory.php](../../../backend/database/factories/UserFactory.php) has the role states and `DEFAULT_PASSWORD`.
- [UserManagementService.php](../../../backend/app/Services/Users/UserManagementService.php) shows the patterns for a page size constant, transactions and paginated queries. [UserResource.php](../../../backend/app/Http/Resources/UserResource.php) shows the ISO 8601 UTC timestamp format.
- [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php) checks every API route for `auth:sanctum` and `EnsurePasswordChanged`, so the new routes are checked automatically. [UserManagementAccessTest.php](../../../backend/tests/Feature/Users/UserManagementAccessTest.php) shows the data provider pattern for access tests.
- PostgreSQL limits identifiers to 63 bytes and silently truncates longer names. Laravel's generated names for some of the new history table indexes and foreign keys are longer than that, for example `service_request_status_histories_service_request_id_created_at_index`, so they must be named explicitly.

### 3.2 Frontend

- [constants/routes.ts](../../../frontend/constants/routes.ts) already holds `ServiceRequests`, `MyServiceRequests`, `NewServiceRequest` and `serviceRequestDetailRoute(serviceRequestId)`.
- [constants/navigation.ts](../../../frontend/constants/navigation.ts) holds Overview, My requests, Users and Account. The Phase 3 and Phase 4 plans say Phase 5 adds New request for every role.
- [enums/service-request-status.ts](../../../frontend/enums/service-request-status.ts) and [enums/service-request-tag.ts](../../../frontend/enums/service-request-tag.ts) mirror the backend enums, and [constants/enum-labels.ts](../../../frontend/constants/enum-labels.ts) already holds `SERVICE_REQUEST_STATUS_LABELS` and `SERVICE_REQUEST_TAG_LABELS`.
- [service-requests/mine/page.tsx](../../../frontend/app/(authenticated)/(workspace)/service-requests/mine/page.tsx) is a placeholder whose text comes from `AuthMessage.MyServiceRequestsTitle` and `AuthMessage.MyServiceRequestsPlaceholder` in [constants/auth-messages.ts](../../../frontend/constants/auth-messages.ts).
- [Checkbox](../../../frontend/components/shared/checkbox.tsx), [Textarea](../../../frontend/components/shared/textarea.tsx), [FormField](../../../frontend/components/shared/form-field.tsx), [Table](../../../frontend/components/shared/table.tsx), [Pagination](../../../frontend/components/shared/pagination.tsx), [ContentSection](../../../frontend/components/shared/content-section.tsx), [EmptyState](../../../frontend/components/shared/empty-state.tsx), [Badge](../../../frontend/components/shared/badge.tsx) and [PageHeader](../../../frontend/components/shared/page-header.tsx) exist. `FormField` links one control through `htmlFor`, so a group of checkboxes needs its own `fieldset` based component.
- [use-users.ts](../../../frontend/hooks/users/use-users.ts) holds the paging logic, including the request counter that ignores late responses and the move to the last page when a later page comes back empty. [use-managed-user.ts](../../../frontend/hooks/users/use-managed-user.ts) holds the logic for loading one record. Both would be duplicated by the request hooks.
- [users-panel.tsx](../../../frontend/components/users/users-panel.tsx), [user-detail-panel.tsx](../../../frontend/components/users/user-detail-panel.tsx) and [sessions-section.tsx](../../../frontend/components/sessions/sessions-section.tsx) each repeat the same centred spinner and the same danger `Alert` with a retry button and its own `isRetrying` state.
- [resolve-form-errors.ts](../../../frontend/lib/forms/resolve-form-errors.ts) maps an `ApiError` to an alert message and field errors for any form. [is-handled-by-route-guards.ts](../../../frontend/lib/http/is-handled-by-route-guards.ts) and [message-for-error.ts](../../../frontend/lib/http/message-for-error.ts) exist.
- [constants/route-parameters.ts](../../../frontend/constants/route-parameters.ts) holds `POSITIVE_WHOLE_NUMBER_PATTERN`, which the user detail page uses to reject invalid ids.
- `PAGE_QUERY_PARAMETER` exists in [constants/http.ts](../../../frontend/constants/http.ts). `FIRST_USERS_PAGE` is exported from `use-users.ts`.
- The `components/service-requests`, `services/service-requests`, `hooks/service-requests` and `types/service-requests` folders hold only a `.gitkeep` file.
- Next.js resolves static segments before dynamic ones, so `/service-requests/mine` and `/service-requests/new` are never read as a `[serviceRequestId]` value.

## 4. Request Flows

These flows describe the behaviour the steps below must produce. They are the reference for the tests.

### 4.1 Endpoints

| Method | Path | Route name | Success response |
| --- | --- | --- | --- |
| POST | `/api/service-requests` | `service-requests.store` | `201` with the created request under `data` |
| GET | `/api/service-requests/mine` | `service-requests.mine` | `200` with a page of the user's requests |
| GET | `/api/service-requests/{serviceRequest}` | `service-requests.show` | `200` with the request and both histories under `data` |

All routes require `auth:sanctum`, the `api` limiter and `EnsurePasswordChanged`. `{serviceRequest}` must be a positive whole number. Anything else returns `404`.

A create request body looks like this.

```json
{
  "title": "Laptop does not connect to the office Wi-Fi",
  "description": "Since this morning the laptop cannot see the office network. Other devices work.",
  "tags": ["HARDWARE", "NETWORK"]
}
```

A My Requests response looks like this. The `links` and `meta` blocks are Laravel's default paginated resource envelope, already typed on the frontend.

```json
{
  "data": [
    {
      "id": 42,
      "title": "Laptop does not connect to the office Wi-Fi",
      "status": "OPEN",
      "tags": ["HARDWARE", "NETWORK"],
      "requested_by": { "id": 7, "name": "Kasun Perera" },
      "assignee": null,
      "created_at": "2026-11-20T09:15:00Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "path": "...", "per_page": 15, "to": 1, "total": 1, "links": [] }
}
```

A detail and create response looks like this. The example shows a request that Phase 6 has already assigned, so both timelines hold records. A request created in Phase 5 has only the initial status record and an empty assignee history.

```json
{
  "data": {
    "id": 42,
    "title": "Laptop does not connect to the office Wi-Fi",
    "description": "Since this morning the laptop cannot see the office network. Other devices work.",
    "status": "ASSIGNED",
    "tags": ["HARDWARE", "NETWORK"],
    "requested_by": { "id": 7, "name": "Kasun Perera" },
    "assignee": { "id": 3, "name": "Nimali Silva" },
    "created_at": "2026-11-20T09:15:00Z",
    "status_history": [
      {
        "id": 88,
        "from_status": "OPEN",
        "to_status": "ASSIGNED",
        "changed_by": { "id": 3, "name": "Nimali Silva" },
        "remark": null,
        "created_at": "2026-11-20T10:02:00Z"
      },
      {
        "id": 87,
        "from_status": null,
        "to_status": "OPEN",
        "changed_by": null,
        "remark": null,
        "created_at": "2026-11-20T09:15:00Z"
      }
    ],
    "assignee_history": [
      {
        "id": 21,
        "assigned_from": null,
        "assigned_to": { "id": 3, "name": "Nimali Silva" },
        "remark": "Taking this one.",
        "created_at": "2026-11-20T10:02:00Z"
      }
    ]
  }
}
```

### 4.2 Creating a Request

1. The user opens `/service-requests/new`, enters a title and a description and selects at least one tag.
2. The frontend posts the body to `POST /api/service-requests`.
3. The backend trims the title and the description, then validates the input. Any `status`, `requested_by_user_id` or other field sent by the client is ignored.
4. In one transaction, the backend creates the request with the signed in user as requester and `OPEN` as status, attaches the tags and writes the initial status history record.
5. It returns `201` with the detail resource.
6. The frontend shows the success notification and navigates to `/service-requests/{id}`.

### 4.3 Listing My Requests

1. The My Requests page calls `GET /api/service-requests/mine?page={n}`.
2. The backend returns the requested page of requests whose requester is the signed in user, newest first, with tags, requester and current assignee eager loaded.
3. A page beyond the last returns an empty `data` array with correct `meta`, and the frontend moves to the last page, as the users list already does.

### 4.4 Viewing a Request

1. The detail page calls `GET /api/service-requests/{id}`.
2. Route model binding loads the request, or returns `404` when the id does not exist.
3. The controller checks `ServiceRequestPolicy::view`. A requester who does not own the request receives `404 NOT_FOUND` with the same body as an unknown id.
4. The backend eager loads the tags, the requester, the current assignee and both histories with their users, and returns the detail resource.
5. The frontend shows the request fields, the status history and the assignee history, both newest first. A `404` shows the not found message.

## 5. Backend Steps

All commands run from the `backend` folder. Every new file starts with its two to three line header comment and contains no other comments. Every touched file keeps its header comment accurate.

### Step B1. Update the main plan for the initial status record

The main plan describes status history records without an empty from status or actor. In [docs/plan/implementation-plan.md](../implementation-plan.md), update the status history list in section 3.11 so that "From status" and "Status changed by" both say they are empty only for the initial record written when the request is created, and add that record to the "History records are written only by the system" paragraph. Update the `service_request_status_histories` entry in section 4 the same way. This keeps the main plan the single source of truth.

### Step B2. Add the shared limits, the Gate ability and the policy ability

1. Create **`app/Support/ServiceRequests/ServiceRequestLimits.php`**, a final class with `public const int TITLE_MAX_LENGTH = 150;`, `public const int DESCRIPTION_MAX_LENGTH = 5000;` and `public const int REMARK_MAX_LENGTH = 1000;`. The migrations, the Form Request and the Phase 6 Form Requests all read these values. The frontend mirrors the first two in Step F4.
2. In [GateAbility.php](../../../backend/app/Enums/GateAbility.php), add `case ViewAllServiceRequests = 'view-all-service-requests';`.
3. In [PolicyAbility.php](../../../backend/app/Enums/PolicyAbility.php), add `case View = 'view';`.
4. In [UserRole.php](../../../backend/app/Enums/UserRole.php), add `public static function staff(): array` that returns `[self::Admin, self::Technician]`. It mirrors the frontend `STAFF_ROLES`, and Phase 6 reuses it for the assignee choices. Update the header comment.

### Step B3. Extend the check constraint helper

In [CheckConstraint.php](../../../backend/app/Support/Database/CheckConstraint.php), add one method and update the header comment.

| Method | Responsibility |
| --- | --- |
| `nullTogether(string $table, string $constraintName, string $firstColumn, string $secondColumn): void` | Adds `CHECK ((first IS NULL) = (second IS NULL))` with the given name. The name is passed in because a generated name would pass the PostgreSQL limit of 63 bytes. Like the other methods, it does nothing outside PostgreSQL. |

### Step B4. Create the migrations

Create the migrations with `php artisan make:migration` in the order below, so they get increasing timestamps after the Phase 2 migrations. Every migration is reversible, holds its table name in a constant, uses `timestampTz` columns like the existing migrations, and restricts deletion on every foreign key, as section 4 of the main plan requires. Explicit names are given wherever Laravel's generated name would be longer than 63 bytes.

**`create_tags_table`**

| Column | Definition |
| --- | --- |
| `id` | `id()` |
| `name` | `string('name', 20)->unique()` |

After the table is created, call `CheckConstraint::allowedValues('tags', 'name', ServiceRequestTag::values())`. The table has no timestamps, because the tags never change.

**`create_service_requests_table`**

| Column | Definition |
| --- | --- |
| `id` | `id()` |
| `title` | `string('title', ServiceRequestLimits::TITLE_MAX_LENGTH)` |
| `description` | `text('description')` |
| `requested_by_user_id` | `foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete()` |
| `status` | `string('status', 20)->index()` |
| timestamps | `timestampsTz()` |

Add a composite index on `['requested_by_user_id', 'created_at']`, which serves both the requester filter and the newest first order of My Requests, and a single index on `created_at` for the Phase 6 dashboard and the Phase 7 monthly figures. After the table is created, call `CheckConstraint::allowedValues('service_requests', 'status', ServiceRequestStatus::values())`.

**`create_service_request_tag_table`**

| Column | Definition |
| --- | --- |
| `service_request_id` | `foreignId('service_request_id')->constrained()->restrictOnDelete()` |
| `tag_id` | `foreignId('tag_id')->index()->constrained()->restrictOnDelete()` |

The primary key is the composite `['service_request_id', 'tag_id']`, as section 4 of the main plan requires. The `tag_id` index serves the Phase 7 tag distribution.

**`create_service_request_status_histories_table`**. The table is append only, so it has `created_at` and no `updated_at`.

| Column | Definition |
| --- | --- |
| `id` | `id()` |
| `service_request_id` | `foreignId('service_request_id')->constrained()->restrictOnDelete()` |
| `from_status` | `string('from_status', 20)->nullable()` |
| `to_status` | `string('to_status', 20)` |
| `changed_by_user_id` | `foreignId('changed_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete()` |
| `remark` | `string('remark', ServiceRequestLimits::REMARK_MAX_LENGTH)->nullable()` |
| `created_at` | `timestampTz('created_at')->useCurrent()` |

Add an index on `['service_request_id', 'created_at']` named `status_histories_request_created_index`. After the table is created, call `CheckConstraint::allowedValues` for `from_status` and for `to_status` with `ServiceRequestStatus::values()`, and `CheckConstraint::nullTogether('service_request_status_histories', 'status_histories_initial_record_check', 'from_status', 'changed_by_user_id')`.

**`create_service_request_assignee_histories_table`**. Also append only.

| Column | Definition |
| --- | --- |
| `id` | `id()` |
| `service_request_id` | `foreignId('service_request_id')->constrained()->restrictOnDelete()` |
| `assigned_from_user_id` | `foreignId('assigned_from_user_id')->nullable()->constrained('users', indexName: 'assignee_histories_assigned_from_foreign')->restrictOnDelete()` |
| `assigned_to_user_id` | `foreignId('assigned_to_user_id')->index()->constrained('users')->restrictOnDelete()` |
| `remark` | `string('remark', ServiceRequestLimits::REMARK_MAX_LENGTH)` |
| `created_at` | `timestampTz('created_at')->useCurrent()` |

Add an index on `['service_request_id', 'created_at']` named `assignee_histories_request_created_index`. The `assigned_to_user_id` index serves the Phase 7 workload graph, as section 4 of the main plan requires.

**Check.** `php artisan migrate` and `php artisan migrate:rollback --step=5` both succeed against the local PostgreSQL database. In `psql`, `\d service_request_status_histories` shows the three check constraints and the explicitly named index, and no identifier is truncated.

### Step B5. Create the append only trait and the models

**`app/Models/Concerns/AppendOnly.php`** is a trait.

- It declares `public const string APPEND_ONLY_MESSAGE = 'This record is append only and cannot be updated or deleted.';`. Constants in traits are available from PHP 8.2, so this fits the PHP 8.3 target.
- Its `bootAppendOnly()` static method registers `updating` and `deleting` listeners that throw a `LogicException` with that message. Laravel calls `boot{TraitName}` automatically.

**[AuthAuditLog.php](../../../backend/app/Models/AuthAuditLog.php)** uses the trait, and its own constant and `booted()` method are removed. `AuthAuditLogTest` keeps passing unchanged, because it reads the message through `AuthAuditLog::APPEND_ONLY_MESSAGE`, which now comes from the trait. Update its header comment.

**`app/Models/Tag.php`**

- `public $timestamps = false;`
- `$fillable` is `name`.
- `casts()` returns `name` as `ServiceRequestTag::class`.

**`app/Models/ServiceRequest.php`** uses `HasFactory`.

- `$fillable` is `title` and `description`. `status` and `requested_by_user_id` are never fillable and are set only by the service, as section 5.9 requires.
- `casts()` returns `status` as `ServiceRequestStatus::class`, and `created_at` and `updated_at` as `immutable_datetime`.
- `requester(): BelongsTo` to `User` through `requested_by_user_id`.
- `tags(): BelongsToMany` to `Tag` through `service_request_tag`, ordered by the tag id.
- `statusHistories(): HasMany`, ordered by `created_at` descending and then `id` descending.
- `assigneeHistories(): HasMany`, ordered the same way.
- `currentAssigneeHistory(): HasOne`, built as `hasOne(ServiceRequestAssigneeHistory::class)->latestOfMany()`. This is the single relation that identifies the current assignee, as section 6.1 requires.
- `currentAssignee(): ?User` returns `$this->currentAssigneeHistory?->assignedTo`. Strict mode makes it throw if the relation was not eager loaded, which catches a missing eager load in tests.
- `isRequestedBy(User $user): bool` compares the requester id with the user's id.

**`app/Models/ServiceRequestStatusHistory.php`** uses `HasFactory` and `AppendOnly`.

- `public const UPDATED_AT = null;`
- `$fillable` is `from_status`, `to_status`, `changed_by_user_id` and `remark`. These are only ever set by the history recorder, never from request input.
- `casts()` returns `from_status` and `to_status` as `ServiceRequestStatus::class` and `created_at` as `immutable_datetime`.
- `serviceRequest(): BelongsTo` and `changedBy(): BelongsTo` to `User` through `changed_by_user_id`.
- `isInitialRecord(): bool` returns `true` when `from_status` is `null`.

**`app/Models/ServiceRequestAssigneeHistory.php`** uses `HasFactory` and `AppendOnly`.

- `public const UPDATED_AT = null;`
- `$fillable` is `assigned_from_user_id`, `assigned_to_user_id` and `remark`.
- `casts()` returns `created_at` as `immutable_datetime`.
- `serviceRequest(): BelongsTo`, `assignedFrom(): BelongsTo` and `assignedTo(): BelongsTo`, both to `User`.

**[User.php](../../../backend/app/Models/User.php)** gains `serviceRequests(): HasMany` through `requested_by_user_id`. Update its header comment.

### Step B6. Create the tag seeder and update the database seeder

**`database/seeders/TagSeeder.php`** inserts one row per `ServiceRequestTag` case in enum order with `Tag::query()->upsert(...)` on the `name` column, so running it again changes nothing.

**[DatabaseSeeder.php](../../../backend/database/seeders/DatabaseSeeder.php)** calls `TagSeeder` before `InitialAdminSeeder`, so the tags exist even when the admin configuration is still missing. Update its header comment so it no longer says Phase 5 adds the tags.

### Step B7. Create the factories

**`database/factories/ServiceRequestFactory.php`**

- The default state creates an `OPEN` request with a sentence as title, paragraphs as description, both within the limits, and a requester from `User::factory()`.
- `configure()` uses `afterCreating` to attach one random tag when none were attached and to write the initial status history record with the request's `created_at`, so every factory made request is consistent with what the service writes. The tags must be seeded first, and the factory throws a clear `LogicException` when the tags table is empty.
- States: `requestedBy(User $user)`, `withTags(ServiceRequestTag ...$tags)` and `createdAt(CarbonInterface $time)`.

**`database/factories/ServiceRequestStatusHistoryFactory.php`** and **`database/factories/ServiceRequestAssigneeHistoryFactory.php`** create history records directly. They exist only so the Phase 5 tests can build histories that Phase 6 will write through the workflow. Their header comments say that the application writes history only through the recorder.

### Step B8. Create the data transfer object

Create **`app/Data/ServiceRequests/NewServiceRequest.php`**, a final readonly class with `string $title`, `string $description` and `array $tags`, holding a list of `ServiceRequestTag` cases. The constructor rejects an empty tag list with an `InvalidArgumentException`, which never happens after validation and protects the service from other callers.

### Step B9. Create the Form Request

**`app/Http/Requests/ServiceRequests/CreateServiceRequestRequest.php`**. Field names are class constants. Limits come from `ServiceRequestLimits`.

- `prepareForValidation()` trims `title` and `description`, only when each value is a string, following `CreateUserRequest`.
- `title` is `required`, `string` and `max:150`.
- `description` is `required`, `string` and `max:5000`.
- `tags` is `required`, `array`, `list` and `min:1`.
- `tags.*` is `required`, `string`, `distinct`, `Rule::enum(ServiceRequestTag::class)` and `Rule::exists(Tag::class, 'name')`.
- `authorize()` returns `true`, because every signed in user may create a request and authentication is handled by route middleware.
- `toNewServiceRequest(): NewServiceRequest` builds the DTO from `validated()`, converting each tag with `ServiceRequestTag::from()`.

### Step B10. Create the history recorder

**`app/Services/ServiceRequests/ServiceRequestHistoryRecorder.php`** is the only class that writes history records. It is a final class.

| Method | Responsibility |
| --- | --- |
| `recordCreation(ServiceRequest $serviceRequest): ServiceRequestStatusHistory` | Writes the initial status record with an empty from status, `to_status` set to the request's status, an empty actor and no remark. |

Phase 6 adds `recordStatusChange` and `recordAssigneeChange` here. The recorder never opens a transaction, because every caller already holds one.

### Step B11. Create the service request service

**`app/Services/ServiceRequests/ServiceRequestService.php`** is a final class. It receives `ServiceRequestHistoryRecorder` through its constructor. A public constant holds the page size, `15`. Two private constants hold the eager loaded relations, so the list and the detail always load the same set.

| Constant | Relations |
| --- | --- |
| `SUMMARY_RELATIONS` | `tags`, `requester`, `currentAssigneeHistory.assignedTo` |
| `DETAIL_RELATIONS` | the summary relations plus `statusHistories.changedBy`, `assigneeHistories.assignedFrom` and `assigneeHistories.assignedTo` |

`createRequest(User $requester, NewServiceRequest $newRequest): ServiceRequest`

1. Opens a database transaction.
2. Creates the request from the title and the description, associates the requester and sets the status to `ServiceRequestStatus::Open`, ignoring anything else.
3. Looks up the tag ids for the given enum values and attaches them.
4. Calls `recordCreation`.
5. Commits and returns `loadDetail()` of the new request.

`paginateRequestsOf(User $requester): LengthAwarePaginator`

1. Returns the requester's requests with `SUMMARY_RELATIONS`, ordered by `created_at` descending and `id` descending, paginated by the page size.

`loadDetail(ServiceRequest $serviceRequest): ServiceRequest`

1. Loads `DETAIL_RELATIONS` on the given model and returns it.

Phase 6 adds the dashboard listing here and puts the workflow in its own service.

### Step B12. Create the Gate and the policy

1. In `AppServiceProvider::configureAuthorization()`, add `Gate::define(GateAbility::ViewAllServiceRequests->value, fn (User $user): bool => in_array($user->role, UserRole::staff(), true))`.
2. Create **`app/Policies/ServiceRequestPolicy.php`**, a final class with `view(User $user, ServiceRequest $serviceRequest): Response`. It returns `Response::allow()` when `$serviceRequest->isRequestedBy($user)` or `$user->can(GateAbility::ViewAllServiceRequests->value)`, and `Response::denyAsNotFound()` otherwise. The role rule stays in the Gate, so the policy holds no role logic.
3. Register it with `Gate::policy(ServiceRequest::class, ServiceRequestPolicy::class)` next to `SessionPolicy`. Laravel could discover a model policy by itself, but explicit registration keeps every policy visible in one place. Update the provider's header comment.

### Step B13. Create the resources

**`app/Http/Resources/UserReferenceResource.php`** returns exactly `id` and `name`.

**`app/Http/Resources/ServiceRequestSummaryResource.php`** returns exactly these fields.

| Field | Value |
| --- | --- |
| `id` | the id |
| `title` | the title |
| `status` | the enum value |
| `tags` | the list of tag enum values |
| `requested_by` | `UserReferenceResource` of the requester |
| `assignee` | `UserReferenceResource` of `currentAssignee()`, or `null` |
| `created_at` | `toIso8601ZuluString()` |

**`app/Http/Resources/ServiceRequestStatusHistoryResource.php`** returns `id`, `from_status` as the enum value or `null`, `to_status` as the enum value, `changed_by` as a `UserReferenceResource` or `null`, `remark` and `created_at` in ISO 8601 UTC.

**`app/Http/Resources/ServiceRequestAssigneeHistoryResource.php`** returns `id`, `assigned_from` as a `UserReferenceResource` or `null`, `assigned_to` as a `UserReferenceResource`, `remark` and `created_at` in ISO 8601 UTC.

**`app/Http/Resources/ServiceRequestDetailResource.php`** returns every field of the summary resource, merged through the summary resource's `toArray` so the shared fields are defined once, plus `description`, `status_history` as a collection of the status history resource and `assignee_history` as a collection of the assignee history resource.

No resource returns `requested_by_user_id`, `updated_at`, pivot data or any user email, role or password field.

### Step B14. Create the controllers

Create single action invokable controllers in `app/Http/Controllers/ServiceRequests`. Each one only reads the request, calls the service and returns a resource.

| Controller | Behaviour |
| --- | --- |
| `CreateServiceRequestController` | Calls `createRequest($request->user(), $request->toNewServiceRequest())` and returns `ServiceRequestDetailResource` with status `201`. |
| `ListOwnServiceRequestsController` | Returns `ServiceRequestSummaryResource::collection($service->paginateRequestsOf($request->user()))`. |
| `ShowServiceRequestController` | Receives `ServiceRequest $serviceRequest` through implicit model binding, calls `Gate::authorize(PolicyAbility::View->value, $serviceRequest)` and returns `new ServiceRequestDetailResource($service->loadDetail($serviceRequest))`. |

### Step B15. Register the routes

Add the routes inside the existing `EnsurePasswordChanged` group in [routes/api.php](../../../backend/routes/api.php), outside the `manage-users` group. The `mine` route is registered before the parameter route, and the parameter is constrained to numbers, so `mine` can never be read as an id.

```php
Route::post('service-requests', CreateServiceRequestController::class)->name('service-requests.store');
Route::get('service-requests/mine', ListOwnServiceRequestsController::class)->name('service-requests.mine');
Route::get('service-requests/{serviceRequest}', ShowServiceRequestController::class)
    ->whereNumber('serviceRequest')
    ->name('service-requests.show');
```

No new rate limiter is needed.

### Step B16. Create the development seeder

**`config/development_seed.php`** reads `DEVELOPMENT_USER_PASSWORD`. Following Laravel convention, `env()` is called only in config files.

**`database/seeders/DevelopmentSeeder.php`**

1. Throws a `RuntimeException` with a message held in a constant unless the application runs in the `local` environment, so demo data can never reach production or a shared server.
2. Validates the password with `Password::defaults()` and throws a clear message without the password when it is missing or weak, following `InitialAdminSeeder`.
3. Calls `TagSeeder`.
4. Creates demo technicians and requesters with fixed `example.test` emails and names held in constants, using the user factory with the configured password and a completed password change. Users whose email already exists are skipped, so the seeder can run twice.
5. Creates a number of open requests, held in a constant, spread across the demo requesters and the initial admin, with random tags and `created_at` values spread over the last twelve months. It uses the request factory, so each request gets its initial status record with the same timestamp.

It is run by hand with `php artisan db:seed --class=DevelopmentSeeder` and is never called from `DatabaseSeeder`.

### Step B17. Update the environment file

Add this placeholder to `backend/.env.example` and a real value to the local `.env`.

```dotenv
DEVELOPMENT_USER_PASSWORD=change-this-placeholder
```

The placeholder intentionally fails the password policy, so the development seeder refuses to run until a real value is set.

### Step B18. Write the Phase 5 tests

All feature tests use `RefreshDatabase` and `InteractsWithFrontendSession`. Every test class that needs tags declares `protected bool $seed = true;` and `protected string $seeder = TagSeeder::class;`, so only the tags are seeded and `InitialAdminSeeder` never runs in tests.

**`tests/Concerns/InteractsWithServiceRequests.php`** is a new trait with these helpers.

- `signInAs(User $user): User` signs in through the API and asserts success.
- `createServiceRequestFor(User $requester, ServiceRequestTag ...$tags): ServiceRequest` creates a request through the factory.
- `assignThroughHistory(ServiceRequest $serviceRequest, User $assignee, ?User $previousAssignee = null): void` writes an assignee history record and the matching status record through the history factories, standing in for the Phase 6 workflow.

**`tests/Feature/ServiceRequests/ServiceRequestAccessTest.php`** uses a data provider over every `service-requests.*` route with a sample method and URI, following `UserManagementAccessTest`.

- Every route named `service-requests.*` is covered by the provider, so a new Phase 6 route must be added to it.
- A guest receives `401 UNAUTHENTICATED`.
- A user with a pending password change receives `403 PASSWORD_CHANGE_REQUIRED`, both for an existing request id and for an unknown one.
- An unknown id and a non numeric id return `404 NOT_FOUND` on the show route.

**`tests/Feature/ServiceRequests/CreateServiceRequestTest.php`**

- A valid request returns `201` with exactly the detail fields under `data`, status `OPEN`, the signed in user as `requested_by`, `assignee` set to `null`, the tags in enum order and an empty `assignee_history`.
- The response holds one status history record with `from_status` and `changed_by` set to `null` and `to_status` set to `OPEN`, and the database holds exactly that one row for the request.
- The title and the description are stored trimmed.
- Each role can create a request.
- Client supplied `status`, `requested_by_user_id`, `requested_by`, `id` and `created_at` fields are ignored.
- A missing title, a title over 150 characters, a missing description, a description over 5000 characters, missing tags, an empty tag list, a non list value, duplicate tags and an unknown tag each return `422` on the right field and create no request, no tag link and no history row.
- When a tag value passes the enum rule but its row is missing, the request returns `422`. The test deletes one tag row before posting.

**`tests/Feature/ServiceRequests/ListOwnServiceRequestsTest.php`**

- The response has `data`, `links` and `meta`, and `meta.per_page` equals the page size constant.
- Only the signed in user's requests are listed, never those of other users, for every role.
- Requests are ordered newest first by `created_at`, with the id breaking ties, and the second page holds the next requests.
- Each item has exactly `id`, `title`, `status`, `tags`, `requested_by`, `assignee` and `created_at`, `created_at` is an ISO 8601 UTC string, and `requested_by` has exactly `id` and `name`.
- A request with assignee history shows the latest assignee, and one without shows `null`.
- A page beyond the last returns an empty `data` array.
- The number of database queries is the same for one request and for fifteen requests, measured with `DB::enableQueryLog()`. Strict mode already throws on lazy loading, and this test documents the eager loading rule of section 6.1 explicitly.

**`tests/Feature/ServiceRequests/ShowServiceRequestTest.php`**

- The requester receives exactly the detail fields for their own request.
- A different requester receives `404 NOT_FOUND`, and the body equals the body for an id that does not exist.
- A technician and an admin can each open a request raised by someone else.
- With two assignments written through the helper, `assignee` is the latest assignee, `assignee_history` has two records newest first, the first assignment has `assigned_from` set to `null`, and `status_history` lists the records newest first with the initial record last.
- History records written in the same second are ordered by id, newest first.
- The body never contains an email, a password hash or `requested_by_user_id`.

**`tests/Feature/ServiceRequests/HistoryRecordsTest.php`**

- Updating or deleting a status history record or an assignee history record through Eloquent throws a `LogicException` with `APPEND_ONLY_MESSAGE`.

**`tests/Feature/Seeders/TagSeederTest.php`**

- The seeder creates exactly the four tags in enum order.
- Running it twice creates no duplicates.

**`tests/Feature/Seeders/DevelopmentSeederTest.php`**

- Outside the `local` environment it throws and writes nothing.
- In the `local` environment with a valid password, it creates the demo users with a completed password change, the configured number of open requests, and one initial status record per request.
- A missing or weak password makes it throw, and the message never contains the password.
- Running it twice creates no duplicate users.

**`tests/Unit/Policies/ServiceRequestPolicyTest.php`**

- `view` allows the requester, a technician and an admin, and denies another requester with a response whose status is `404`.

**`tests/Unit/Authorization/ViewAllServiceRequestsGateTest.php`**

- The `view-all-service-requests` Gate allows an admin and a technician and denies a requester.

**`ProtectedRoutesTest`** needs no change. It confirms automatically that the three new routes carry `auth:sanctum` and `EnsurePasswordChanged`.

**Regression.** `AuthAuditLogTest` must pass unchanged after the trait refactor in Step B5.

### Step B19. Format and verify

1. Run `vendor/bin/pint`, then `composer lint`.
2. Run `composer test` and confirm every Phase 1 to Phase 5 test passes.
3. Run `php artisan migrate:fresh --seed` locally, then `php artisan db:seed --class=DevelopmentSeeder`, and confirm the tags, the demo users and the demo requests exist.
4. With `php artisan serve` running, confirm that `curl -i http://localhost:8000/api/service-requests/mine` returns `401` with the error shape.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before writing Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/03-api-reference/03-file-conventions/dynamic-routes.md` (the `PageProps` helper and `params` as a `Promise`), `page.md`, `not-found.md`, `route-groups.md`, and the `useRouter` and `notFound` references in `01-app/03-api-reference/04-functions/`.

The steps are split in three parts. Steps F1 to F3 extract the shared loading pieces from the users feature. Steps F4 to F10 build the service requests feature. Steps F11 to F13 add the pages and verify.

### Step F1. Extract the shared loading hooks

1. In [constants/http.ts](../../../frontend/constants/http.ts), add `FIRST_PAGE = 1` next to `PAGE_QUERY_PARAMETER`.
2. Create **`hooks/shared/use-paginated-resource.ts`** by moving the body of `useUsers` into `usePaginatedResource<Resource>(loadPage: (page: number) => Promise<PaginatedResponse<Resource>>): PaginatedResource<Resource>`. It keeps the same discriminated union state with `LoadStatus`, the request counter that ignores late responses, the move to `meta.last_page` when a later page comes back empty, `isChangingPage`, `goToPage` and the non flickering `reload()`. The loaded state holds the response under `page` instead of `users`. The effect depends on `loadPage`, so callers pass a stable function.
3. Create **`hooks/shared/use-resource.ts`** by moving the body of `useManagedUser` into `useResource<Resource>(load: () => Promise<Resource>): ResourceResult<Resource>`, with the loaded state holding the value under `resource`. A `NOT_FOUND` error stays in the `Failed` state, as before.
4. Rewrite **`hooks/users/use-users.ts`** as a thin wrapper that passes a module level function calling `userService.listUsers`, and **`hooks/users/use-managed-user.ts`** as a thin wrapper that builds its loader with `useCallback` keyed by `userId`.
5. Update [users-panel.tsx](../../../frontend/components/users/users-panel.tsx) and [user-detail-panel.tsx](../../../frontend/components/users/user-detail-panel.tsx) for the renamed state properties and replace `FIRST_USERS_PAGE` with `FIRST_PAGE`. Search for `FIRST_USERS_PAGE` and confirm nothing still references it.

The users screens behave exactly as before.

### Step F2. Extract the shared loading components

1. Create **`components/shared/section-loader.tsx`**, a server component that renders the centred large `Spinner` the panels repeat today.
2. Create **`components/shared/load-failure-alert.tsx`**, a client component with the props `title`, `error: ApiError | null` and `onRetry: () => Promise<void>`. It renders the danger `Alert` with `messageForError(error)` and a retry `Button` whose loading state it owns.
3. Use both in `users-panel.tsx`, `user-detail-panel.tsx` and `sessions-section.tsx`, and remove their local `isRetrying` state. Search for `isRetrying` and confirm it appears only in `load-failure-alert.tsx`.

### Step F3. Add the checkbox group and the timeline

Create these shared components in `components/shared/`.

| Component | Responsibility |
| --- | --- |
| `checkbox-group.tsx` | A client component that renders a `fieldset` with a `legend`, one `Checkbox` per option, an optional hint and an optional error. Props are `legend`, `options: SelectOption<Value>[]`, `values: readonly Value[]`, `onChange(values: Value[])`, `hint`, `error`, `required` and `disabled`. The hint and the error are linked to the `fieldset` through `aria-describedby`, and `aria-invalid` is set when an error is present. The selected values keep the order of `options`. |
| `timeline.tsx` | A server component that renders an ordered list of entries, each with a heading, a meta line and an optional body. Props are `items` with a key, a heading, a `dateTime` value with its formatted text and an optional body, plus `emptyTitle` and `emptyDescription` for the `EmptyState` shown when there are no items. Both history timelines use it, and Phase 6 reuses it unchanged. |

### Step F4. Mirror the endpoints and add the constants

1. In [constants/api-endpoints.ts](../../../frontend/constants/api-endpoints.ts), add `ServiceRequests = "/api/service-requests"` and `MyServiceRequests = "/api/service-requests/mine"` to `ApiEndpoint`, and the helper `serviceRequestEndpoint(serviceRequestId: number): string`.
2. In [constants/navigation.ts](../../../frontend/constants/navigation.ts), add `NewServiceRequest: "New request"` to `NavigationMessage` and an item with `AppRoute.NewServiceRequest` and `ALL_ROLES`, placed after My requests.
3. Create **`constants/service-request-limits.ts`** with `SERVICE_REQUEST_TITLE_MAX_LENGTH = 150` and `SERVICE_REQUEST_DESCRIPTION_MAX_LENGTH = 5000`. Its header comment says they mirror `ServiceRequestLimits` on the backend, which still validates them.
4. Create **`constants/service-request-fields.ts`** with `CreateServiceRequestField`, typed with `satisfies Record<string, keyof CreateServiceRequestRequest>`, the `CreateServiceRequestFieldName` type and the `CREATE_SERVICE_REQUEST_FIELDS` list, following `constants/user-fields.ts`.
5. Create **`constants/service-request-messages.ts`** with `ServiceRequestMessage`. It holds the My Requests title, description, list title, table caption, column headers and empty state text, the "New request" button, the new request page title and description, the field labels and hints, the tags legend, the "select at least one tag" message, the submit and cancel labels, the created notification, the detail page title and description, the details section labels, the "Unassigned" text, the history section titles and descriptions, the empty history texts, the "recorded when the request was created" text for the initial status record, the remark label, the load failure messages, the not found title and description and the back link label. It also exports formatting functions for the history headings, for example `formatStatusChange(from, to)` returning `Changed from Assigned to In progress`, `formatInitialStatus(status)` returning `Created with status Open`, `formatAssignment(assignee)` and `formatReassignment(previous, next)`, and `formatCharacterLimit(maximum)` for the field hints. Finally it exports `SERVICE_REQUEST_TAG_OPTIONS: SelectOption<ServiceRequestTag>[]`, built from `Object.values(ServiceRequestTag)` and `SERVICE_REQUEST_TAG_LABELS`.
6. In [constants/auth-messages.ts](../../../frontend/constants/auth-messages.ts), remove `MyServiceRequestsTitle` and `MyServiceRequestsPlaceholder`, and update the header comment so it mentions only the overview placeholder that Phase 7 replaces.

### Step F5. Add the types and the UI enum

**Types.** Each file mirrors the wire format in snake_case.

| File | Type |
| --- | --- |
| `types/shared/user-reference.ts` | `UserReference` with `id: number` and `name: string` |
| `types/service-requests/service-request-summary.ts` | `ServiceRequestSummary` with `id`, `title`, `status: ServiceRequestStatus`, `tags: ServiceRequestTag[]`, `requested_by: UserReference`, `assignee: UserReference \| null` and `created_at: string` |
| `types/service-requests/status-history-entry.ts` | `StatusHistoryEntry` with `id`, `from_status: ServiceRequestStatus \| null`, `to_status: ServiceRequestStatus`, `changed_by: UserReference \| null`, `remark: string \| null` and `created_at: string` |
| `types/service-requests/assignee-history-entry.ts` | `AssigneeHistoryEntry` with `id`, `assigned_from: UserReference \| null`, `assigned_to: UserReference`, `remark: string` and `created_at: string` |
| `types/service-requests/service-request-detail.ts` | `ServiceRequestDetail`, which extends `ServiceRequestSummary` with `description: string`, `status_history: StatusHistoryEntry[]` and `assignee_history: AssigneeHistoryEntry[]` |
| `types/service-requests/create-service-request-request.ts` | `CreateServiceRequestRequest` with `title: string`, `description: string` and `tags: ServiceRequestTag[]` |

`UserReference` lives in `types/shared` because Phase 6 uses it for the assignee choices as well.

**Enum.** `enums/ui/service-request-table-column.ts` holds `ServiceRequestTableColumn` with `Title`, `Tags`, `Status`, `Assignee` and `CreatedAt`, following `UserTableColumn`. Phase 6 adds `RequestedBy` and `Actions`.

### Step F6. Add the status tone mapping

Create **`lib/service-requests/status-tone.ts`** with `SERVICE_REQUEST_STATUS_TONES: Record<ServiceRequestStatus, BadgeTone>`, mapping `Open` to `Info`, `Assigned` to `Neutral`, `InProgress` to `Warning` and `Resolved` to `Success`. Because the record is typed, a new status without a tone fails the build. The list, the detail and the Phase 6 dashboard all use it.

### Step F7. Build the service request service

**`services/service-requests/service-request-service.ts`** holds `ServiceRequestService`, which extends `BaseApiService`, and exports one shared instance, `serviceRequestService`.

| Method | Call | Returns |
| --- | --- | --- |
| `createServiceRequest(request: CreateServiceRequestRequest)` | `POST ApiEndpoint.ServiceRequests` | `ServiceRequestDetail`, unwrapped |
| `listMyServiceRequests(page: number)` | `GET ApiEndpoint.MyServiceRequests` with `params: { page }` | `PaginatedResponse<ServiceRequestSummary>` |
| `getServiceRequest(serviceRequestId: number)` | `GET serviceRequestEndpoint(serviceRequestId)` | `ServiceRequestDetail`, unwrapped |

### Step F8. Build the hooks

- **`hooks/service-requests/use-my-service-requests.ts`** returns `usePaginatedResource` with a module level function that calls `serviceRequestService.listMyServiceRequests`.
- **`hooks/service-requests/use-service-request.ts`** returns `useResource` with a loader built through `useCallback` keyed by `serviceRequestId`. Phase 6 uses its `reload()` after a conflict response.

### Step F9. Build the presentational components

Create these in `components/service-requests/`. All visible text comes from `ServiceRequestMessage`, `UiMessage` or the enum label records.

| Component | Responsibility |
| --- | --- |
| `service-request-status-badge.tsx` | A `Badge` with the label from `SERVICE_REQUEST_STATUS_LABELS` and the tone from `SERVICE_REQUEST_STATUS_TONES`. |
| `service-request-tags.tsx` | A list of neutral `Badge` elements, one per tag, with labels from `SERVICE_REQUEST_TAG_LABELS`. |
| `service-request-columns.tsx` | Exports one `TableColumn<ServiceRequestSummary>` per column: the title as a `next/link` to `serviceRequestDetailRoute(id)`, the tags, the status badge, the assignee name or "Unassigned", and the creation time through `formatDateTime` inside a `time` element. Phase 6 builds the dashboard table from the same definitions plus its own columns. |
| `my-service-requests-table.tsx` | Builds the shared `Table` from the title, tags, status, assignee and created columns, with the My Requests caption and empty state. The empty state's action is a link to `AppRoute.NewServiceRequest`. |
| `service-request-details.tsx` | A `ContentSection` whose heading is the request title. It shows the status badge, the tags, the requester, the assignee or "Unassigned" and the creation time in a description list, followed by the description. The description is rendered as plain text with `whitespace-pre-wrap` and `break-words`, so line breaks are kept and no raw HTML is ever rendered, as section 5.12 of the main plan requires. |
| `status-history-timeline.tsx` | A `ContentSection` with the shared `Timeline`. The initial record's heading uses `formatInitialStatus` and its meta line says it was recorded when the request was created. Other records use `formatStatusChange` with the actor's name. The remark, when present, is the body, rendered as plain text. |
| `assignee-history-timeline.tsx` | A `ContentSection` with the shared `Timeline`. The first assignment uses `formatAssignment` and later ones use `formatReassignment`. The remark is always the body. |

Both timelines receive the records already in newest first order from the API and do not sort them again.

### Step F10. Build the feature panels

| Component | Responsibility |
| --- | --- |
| `my-service-requests-panel.tsx` | A client component that calls `useMyServiceRequests()`. It renders a `ContentSection` with a "New request" link styled as a button in its actions, `SectionLoader` while loading, `LoadFailureAlert` on failure, and otherwise `MyServiceRequestsTable` and `Pagination` driven by `meta`, disabled while `isChangingPage` is true. |
| `create-service-request-form.tsx` | A client component that holds the title, the description and the selected tags in state. It has a title `Input` with `maxLength`, a description `Textarea` with `maxLength`, both inside `FormField` with the character limit hints, and a `CheckboxGroup` with `SERVICE_REQUEST_TAG_OPTIONS`. When no tag is selected, it shows the "select at least one tag" error on the group and sends nothing, as a convenience only, because the backend validates again. On submit it calls `serviceRequestService.createServiceRequest`, disables the form and shows field errors through `resolveFormErrors` with `CREATE_SERVICE_REQUEST_FIELDS`. A nested `tags.0` error, which only a tampered request can cause, falls back to the alert message, as the helper already does. On success it shows the created notification and calls `router.push(serviceRequestDetailRoute(request.id))`. A cancel link returns to `AppRoute.MyServiceRequests`. |
| `service-request-detail-panel.tsx` | A client component that receives `serviceRequestId` and calls `useServiceRequest(serviceRequestId)`. It shows a back link to `AppRoute.MyServiceRequests`, `SectionLoader` while loading, an `EmptyState` with the not found text and the back link on `NOT_FOUND`, `LoadFailureAlert` on other failures, and otherwise `ServiceRequestDetails`, `StatusHistoryTimeline` and `AssigneeHistoryTimeline`. `UNAUTHENTICATED` and `PASSWORD_CHANGE_REQUIRED` are left to the HTTP client events and the guards, as in earlier phases. |

### Step F11. Add the pages

```text
app/(authenticated)/(workspace)/
  (staff)/...                                   unchanged
  (admin)/...                                   unchanged
  service-requests/
    mine/page.tsx                               replaced, every role
    new/page.tsx                                new, every role
    [serviceRequestId]/page.tsx                 new, every role
  account/page.tsx                              unchanged
```

1. **`service-requests/mine/page.tsx`** is a server component that exports metadata with the My Requests title and renders `PageHeader` and `MyServiceRequestsPanel`. Update its header comment so it no longer mentions the placeholder.
2. **`service-requests/new/page.tsx`** is a server component that exports metadata with the new request title and renders `PageHeader` and `CreateServiceRequestForm` inside a `ContentSection`.
3. **`service-requests/[serviceRequestId]/page.tsx`** is an async server component typed with `PageProps<'/service-requests/[serviceRequestId]'>`. It awaits `params`, checks `serviceRequestId` against `POSITIVE_WHOLE_NUMBER_PATTERN` and calls `notFound()` when it does not match. Otherwise it renders `PageHeader` with the detail title and `ServiceRequestDetailPanel` with the number.

These pages sit outside the `(staff)` group, because every role may raise requests and open its own requests. Access to other users' requests is decided by the backend. Phase 6 adds the dashboard page at `(staff)/service-requests/page.tsx`. That page resolves to `/service-requests`, which no page in this phase uses, so the two route groups never resolve to the same URL.

### Step F12. Remove placeholder files

Delete the `.gitkeep` file of each feature folder that now holds a real file.

- `components/service-requests/.gitkeep`
- `services/service-requests/.gitkeep`
- `hooks/service-requests/.gitkeep`
- `types/service-requests/.gitkeep`

The `.gitkeep` files in the `overview` folders stay until Phase 7.

### Step F13. Verify the frontend

1. Run `npm run lint` and confirm there are no errors or warnings.
2. Run `npm run typecheck` and confirm it passes.
3. Run `npm run build` and confirm it succeeds and lists `/service-requests/mine`, `/service-requests/new` and `/service-requests/[serviceRequestId]`.
4. Search the new files for string literals in JSX and confirm every visible text comes from a constant.
5. Search for `dangerouslySetInnerHTML`, `localStorage`, `sessionStorage` and `console.` in `components/service-requests` and `hooks/service-requests` and confirm there are no matches.

## 7. End to End Verification

Run both applications locally with `php artisan serve` and `npm run dev`, and repeat the checks with `npm run start` after a build. Run `php artisan db:seed --class=DevelopmentSeeder` first for demo data. Confirm each item in a browser with the developer tools open.

1. Every role sees New request in the sidebar, after My requests.
2. A requester opens New request. Submitting with no tag shows the tag error without a network request. Submitting an empty title or description shows the field errors.
3. A valid request shows the success notification and lands on its detail page, which shows the title, the description with its line breaks, the tags in enum order, status Open, the requester, "Unassigned", one status record "Created with status Open" recorded at creation, and the empty assignee history text.
4. The new request appears at the top of My requests with its status badge and tags. With more than 15 requests, the pagination controls move between pages.
5. A description containing HTML such as `<b>bold</b>` is shown as literal text.
6. A second requester who opens the first requester's request URL sees the not found message, and the network tab shows `404` with `NOT_FOUND`.
7. A technician and an admin can open the same request URL and see the full details.
8. Opening `/service-requests/999999` shows the not found message, and `/service-requests/abc` shows the not found page.
9. A demo request created with `assignThroughHistory` in Tinker shows its assignee, both timelines newest first and the remark under each assignment.
10. A pending user still goes to `/change-password` before any request page.
11. The network tab shows that no request response contains an email, a password hash or `requested_by_user_id`.
12. The `service_request_status_histories` table holds exactly one row per created request, with empty `from_status` and `changed_by_user_id`.
13. The users list and the user detail page still load, page and retry as before the hook and component refactors.
14. The browser console shows no CSP violations, and nothing is written to local or session storage.

## 8. Done Criteria

Phase 5 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| The tags, service requests, request tag and both history tables exist, and the tags are seeded | Step B4 migrations run and roll back on PostgreSQL with every check constraint present, and `TagSeederTest` passes. |
| Request creation, the My Requests list and request detail with histories work, guarded by policies | `CreateServiceRequestTest`, `ListOwnServiceRequestsTest`, `ShowServiceRequestTest`, `ServiceRequestPolicyTest` and `ViewAllServiceRequestsGateTest` pass. |
| Tests cover creation, validation failures and requesters being unable to read others' requests | The validation cases in `CreateServiceRequestTest` and the cross user cases in `ShowServiceRequestTest` pass. |
| The requester and the initial status are always set by the server | The ignored field cases in `CreateServiceRequestTest` pass. |
| History records are written only by the system and never edited or deleted | The initial record assertions in `CreateServiceRequestTest` and `HistoryRecordsTest` pass. |
| The current assignee comes from the latest assignee history record, eager loaded in listings | The assignee cases and the query count case in the list and show tests pass. |
| Factories and a development seeder create demo users and requests for local use only | `DevelopmentSeederTest` passes. |
| The create form with tag selection, the paginated My Requests page and the detail page with both timelines exist | Steps F9 to F11 are complete and items 1 to 9 of section 7 succeed. |
| Every role can raise requests and see their own history, and a requester cannot open anyone else's request | Items 2 to 7 of section 7 succeed. |
| Coding standards | Pint, ESLint and the TypeScript check pass, every new file has only its header comment, and no strings are hardcoded outside enums and constants. |

## 9. Known Limitations and Handover to Later Phases

- **Workflow.** Phase 6 adds the dashboard at `GET /api/service-requests`, self assignment, reassignment and status changes with row locking. It reuses `ServiceRequestService` for the dashboard listing, adds `recordStatusChange` and `recordAssigneeChange` to `ServiceRequestHistoryRecorder`, uses `ServiceRequestLimits::REMARK_MAX_LENGTH` in its Form Requests and reuses the `view-all-service-requests` Gate. Every status record it writes has both a from status and an actor, as the check constraint requires.
- **Dashboard page and navigation.** Phase 6 adds the dashboard page inside the `(staff)` group and its navigation item for `STAFF_ROLES`, which then becomes the active item on detail pages. It also points the detail page back link to the dashboard for technicians and admins, and adds the requester and action columns from the shared column definitions.
- **Conflict handling.** Phase 6 calls `reload()` from `useServiceRequest` after a `409` response.
- **Development seeder.** Phase 6 extends `DevelopmentSeeder` with assigned, in progress and resolved requests written through the workflow service, so Phase 7 has realistic figures.
- **Overview figures.** Phase 7 can read the last status change of every request from the status history alone, because every request has at least its initial record. The `created_at` indexes, the `tag_id` index and the `assigned_to_user_id` index serve its aggregate queries.
- **Check constraints and locking in tests.** SQLite still skips the PostgreSQL check constraints, including the new initial record constraint, so they are verified manually in Step B4. Phase 6 reconsiders moving the tests to PostgreSQL, together with row locking.
- **My Requests page in the URL.** The current page is kept in component state, as on the users list, so a browser reload returns to page 1.
- **Frontend tests.** The frontend still has no test runner, so the request flows are verified manually through section 7.

## 10. Suggested Commit Sequence

Each commit leaves both applications working.

1. `docs: describe the initial status history record in the main plan` covers Step B1.
2. `feat(backend): add tags, service requests and history tables with models, factories and seeders` covers Steps B2 to B7.
3. `feat(backend): add service request creation, my requests list and request detail` covers Steps B8 to B15.
4. `feat(backend): add development seeder with demo users and requests` covers Steps B16 and B17.
5. `test(backend): add service request and seeder tests` covers Steps B18 and B19.
6. `refactor(frontend): share the paginated and single resource hooks and the loading components` covers Steps F1 and F2.
7. `feat(frontend): add new request form, my requests list and request detail with history timelines` covers Steps F3 to F13.
