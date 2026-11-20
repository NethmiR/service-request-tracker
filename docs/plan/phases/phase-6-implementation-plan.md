# Phase 6 Implementation Plan. Assignment and Status Workflow

## 1. Purpose

This document breaks Phase 6 of the [implementation plan](../implementation-plan.md) into ordered, verifiable steps. Phase 6 delivers the full support workflow with integrity under concurrency. On the backend it adds the request dashboard for technicians and admins, self assignment of open requests, reassignment by the current assignee, status changes by the current assignee and a list of the staff users a request can be reassigned to. Every change locks the request row, re-validates the latest stored state and writes its history records in one transaction. The transition rules live in the status enum, the workflow rules live in one workflow service, and every request response tells the frontend which actions the viewing user may take. It also extends the development seeder with requests that moved through the workflow, and adds an opt in PostgreSQL test group that proves the row locking and the check constraints. On the frontend it adds the dashboard page with the assign to me action, the reassign dialog and the status change controls on the request detail page, and reloads the latest request after a conflict response.

Every step follows the main plan, in particular sections 2 (User Roles and Access), 3.8 (Request Dashboard), 3.9 (Assignment), 3.10 (Status Workflow), 3.11 (History Records), 5 (Security Requirements) and 6 (Engineering Standards). It also keeps every convention set in the [Phase 1 plan](phase-1-implementation-plan.md), the [Phase 2 plan](phase-2-implementation-plan.md), the [Phase 3 plan](phase-3-implementation-plan.md), the [Phase 4 plan](phase-4-implementation-plan.md) and the [Phase 5 plan](phase-5-implementation-plan.md), such as the file header comments, enum naming, snake_case JSON, the single error shape, ISO 8601 UTC timestamps, the `EnsurePasswordChanged` route group, the middleware priority list, the page size of 15, the `UserReferenceResource` for people on request screens and the shared paginated and single resource hooks. Where this document and the main plan disagree, the main plan wins and this document must be corrected. Step B1 corrects the main plan first, because one confirmed decision below changes section 3.9.

Phase 6 does not add the overview page, its figures or any search or filter on the dashboard. The overview belongs to Phase 7, and the main plan describes no dashboard filters.

## 2. Decisions

### 2.1 Confirmed Decisions

These decisions were confirmed before writing this plan.

| Topic | Decision |
| --- | --- |
| Reassignment and status | Reassigning a request does not change its status. Only the first assignment changes the status, from `OPEN` to `ASSIGNED`. A reassignment writes one assignee history record and no status history record. This replaces the sentence "The status is set to ASSIGNED for the new assignee" in section 3.9 of the main plan and the first assumption in its section 8. Step B1 updates the main plan. |
| Reassignment in every status | Once a request has an assignee, the current assignee may reassign it in every status, which is `ASSIGNED`, `IN_PROGRESS` and `RESOLVED`. An `OPEN` request has no assignee, so it cannot be reassigned. It is taken with self assignment. |
| Reopening a resolved request | The current assignee may move a `RESOLVED` request back to `ASSIGNED` or `IN_PROGRESS`, because resolved work sometimes has to continue. This confirms the second assumption in section 8 of the main plan. |
| Allowed actions | The backend tells the frontend which actions the viewing user may take. The summary and detail resources gain an `available_actions` object with `can_assign_to_self`, `can_reassign` and `allowed_statuses`, worked out from the central rules for the user who sends the request. The frontend shows controls only from this object and never repeats the rules. |
| Assignee choices | The reassign list holds every technician and admin, including users who still have a pending password change. The current assignee is left out of the list on the frontend and rejected by the backend. |
| Test database | The existing SQLite suite stays as it is. A new opt in PHPUnit group named `postgres` runs against a separate local PostgreSQL test database. It proves the row locking with two real connections and proves the check constraints. The group is skipped when that database is not configured, so `composer test` keeps working without PostgreSQL. This settles the question that the Phase 1 plan left open for Phase 6. |

### 2.2 Decisions Made in This Plan

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Endpoint paths | `GET /api/service-requests` lists the dashboard, `GET /api/service-requests/assignees` lists the assignee choices, `POST /api/service-requests/{serviceRequest}/assignment` assigns an open request to the signed in user, `POST /api/service-requests/{serviceRequest}/reassignment` reassigns it and `PATCH /api/service-requests/{serviceRequest}/status` changes its status. Assignment and reassignment are `POST` because each one creates a history record. The status change is `PATCH` because it updates one field of the request. |
| Workflow responses | Every workflow endpoint returns `200` with the full detail resource, including the fresh `available_actions`, so the frontend can show the new state without a second request. |
| Transition table | Status changes follow the table below. `OPEN` is never a target and is left only through self assignment. Setting the current status again is a no op and is rejected. |
| Gates | The dashboard uses the existing `view-all-service-requests` Gate. A new `handle-service-requests` Gate allows the `ADMIN` and `TECHNICIAN` roles and guards self assignment, reassignment, status changes and the assignee choices. The two Gates allow the same roles today, but they stand for two rows of the capability table in section 2 of the main plan, so each capability can change on its own. Both are applied as route middleware, so a requester receives `403 FORBIDDEN` even for an unknown request id, as the Phase 4 check order requires. |
| Record level checks | `ServiceRequestPolicy` gains `reassign` and `changeStatus`, which allow only the current assignee and deny everyone else with `403 FORBIDDEN`. The controllers call them through `Gate::authorize` before the service runs. Self assignment has no record level rule beyond the Gate, because its only record condition is the status, which is a workflow rule. |
| Re-validation under the lock | The workflow service reloads the request with `lockForUpdate` inside the transaction and checks the latest status and assignee again. If the actor is no longer the assignee, or an open request has already been taken, the change is rejected with `409 CONCURRENT_UPDATE` and nothing is written. A losing self assignment race therefore always returns `409 CONCURRENT_UPDATE`. |
| Error codes | A no op status change returns the existing `409 STATUS_UNCHANGED`. A status the transition table forbids, such as `OPEN`, returns the existing `409 INVALID_STATUS_TRANSITION`. A reassignment to the current assignee is a no op transition, so it returns a new `409 ASSIGNEE_UNCHANGED`, as section 5.8 of the main plan requires for no op transitions. An assignee who is not a technician or admin, or who does not exist, fails validation with `422` on `assignee_id`. |
| No expected state from the client | The client sends only the change it wants. It does not send the status or assignee it saw. The backend validates the change against the latest stored state, exactly as section 3.10 of the main plan describes, and the frontend reloads after any `409`. |
| Remarks | The remark is required and trimmed for self assignment and reassignment, and optional for status changes, where an empty or blank value is stored as `null`. Every remark holds at most `ServiceRequestLimits::REMARK_MAX_LENGTH`, which is 1000 characters. On self assignment the remark is stored on the assignee history record, and the status record of the same change has no remark, matching the example response in the Phase 5 plan. |
| Order of writes | Self assignment writes the assignee history record, then the status history record, then the new status on the request. Every write happens inside one transaction, so a failure at any point writes nothing. |
| Touching the request | Every successful change updates `service_requests.updated_at`, including a reassignment that leaves the status unchanged, so the row always shows its last workflow activity. |
| Assignee choices | `GET /api/service-requests/assignees` returns every technician and admin as `{ "id", "name" }` through `UserReferenceResource`, ordered by name and then by id. It is not paginated. The number of staff users is small, so this is a deliberate exception to the server side pagination rule, like the session list in Phase 3. |
| Dashboard order and size | Newest first, ordered by `created_at` descending and then by `id` descending, 15 requests per page, the same as My Requests. No search or filter. |
| Dashboard fields | The dashboard reuses `ServiceRequestSummaryResource`, now with `available_actions`. My Requests returns the same object, which shows all actions as unavailable for a requester. |
| Rate limit | The workflow endpoints share the per user `api` limiter. Section 5.5 of the main plan names its own limits only for sign in, password change, admin password reset and user creation. |
| PostgreSQL test safety | The PostgreSQL test case refuses to run unless the configured database name ends with `_test`, because the test group truncates every table. A wrong value can never wipe the development database. |
| Lock proof without threads | PHP tests run in one process, so the lock test holds the row lock on a second connection and sets a short `lock_timeout` on the main connection. The blocked workflow call fails with PostgreSQL's lock timeout error and writes nothing. After the competing change commits, the same call returns `409 CONCURRENT_UPDATE`. Together these prove that changes are serialized and that exactly one of two competing self assignments succeeds. |
| After assign to me on the dashboard | The user stays on the dashboard. A success notification is shown and the current page reloads, so the row shows the new assignee. |
| Conflict handling on the frontend | Any `409` response, and a `403 FORBIDDEN` from a workflow call, means the request changed under the user. The frontend closes the open dialog, shows the error message from `ERROR_MESSAGES` as an error notification and reloads the request or the dashboard page. |
| Detail page back link | Technicians and admins go back to the dashboard. Requesters go back to My requests, as before. |
| Navigation | A Requests item at `/service-requests` is added for `STAFF_ROLES`, after Overview. The longest match rule in `findActiveNavigationItem` makes it the active item on `/service-requests/{id}` for staff, while `/service-requests/mine` and `/service-requests/new` keep their own items active. |

**Transition table.**

| Current status | Allowed target statuses through a status change |
| --- | --- |
| `OPEN` | none, the request leaves `OPEN` only through self assignment |
| `ASSIGNED` | `IN_PROGRESS`, `RESOLVED` |
| `IN_PROGRESS` | `ASSIGNED`, `RESOLVED` |
| `RESOLVED` | `ASSIGNED`, `IN_PROGRESS` |

## 3. Current State of the Code

The following was found in the repository before Phase 6 starts. Phases 1 to 5 are complete and committed, and the working copy is clean.

### 3.1 Backend

- [routes/api.php](../../../backend/routes/api.php) holds `service-requests.store`, `service-requests.mine` and `service-requests.show` inside the `EnsurePasswordChanged` group, outside the `manage-users` group. `mine` is registered before the numeric `{serviceRequest}` route.
- [bootstrap/app.php](../../../backend/bootstrap/app.php) sets the middleware priority so `EnsurePasswordChanged` and `Authorize` run before `SubstituteBindings`. A route level Gate therefore runs before the request id is resolved, and a policy check with the model must run in the controller.
- [ServiceRequestStatus.php](../../../backend/app/Enums/ServiceRequestStatus.php) holds the four cases and no behaviour.
- [ErrorCode.php](../../../backend/app/Enums/ErrorCode.php) and [lang/en/errors.php](../../../backend/lang/en/errors.php) already hold `CONFLICT`, `INVALID_STATUS_TRANSITION`, `STATUS_UNCHANGED` and `CONCURRENT_UPDATE`, all mapped to `409`. `ASSIGNEE_UNCHANGED` does not exist.
- [GateAbility.php](../../../backend/app/Enums/GateAbility.php) holds `ManageUsers` and `ViewAllServiceRequests`. [PolicyAbility.php](../../../backend/app/Enums/PolicyAbility.php) holds `Delete` and `View`. [UserRole.php](../../../backend/app/Enums/UserRole.php) has `staff()`, which returns the admin and technician cases.
- [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php) defines both Gates and registers `SessionPolicy` and `ServiceRequestPolicy` in `configureAuthorization()`.
- [ServiceRequestPolicy.php](../../../backend/app/Policies/ServiceRequestPolicy.php) has only `view`, which returns `Response::denyAsNotFound()` for requesters who do not own the request.
- [ServiceRequest.php](../../../backend/app/Models/ServiceRequest.php) has `currentAssigneeHistory()` as `hasOne(...)->latestOfMany()`, which picks the record with the highest id, and `currentAssignee()`, which throws under strict mode when the relation was not eager loaded. `status` and `requested_by_user_id` are not fillable.
- [ServiceRequestHistoryRecorder.php](../../../backend/app/Services/ServiceRequests/ServiceRequestHistoryRecorder.php) has only `recordCreation` and never opens a transaction.
- [ServiceRequestService.php](../../../backend/app/Services/ServiceRequests/ServiceRequestService.php) has `createRequest`, `paginateRequestsOf`, `loadDetail`, the page size constant and the `SUMMARY_RELATIONS` and `DETAIL_RELATIONS` constants.
- [ServiceRequestSummaryResource.php](../../../backend/app/Http/Resources/ServiceRequestSummaryResource.php) defines the list fields once, and [ServiceRequestDetailResource.php](../../../backend/app/Http/Resources/ServiceRequestDetailResource.php) extends it.
- [ServiceRequestLimits.php](../../../backend/app/Support/ServiceRequests/ServiceRequestLimits.php) already holds `REMARK_MAX_LENGTH = 1000`, and both remark columns use it.
- The status history table has the `status_histories_initial_record_check` constraint, so every status record written by the workflow must have both a from status and an actor.
- [DevelopmentSeeder.php](../../../backend/database/seeders/DevelopmentSeeder.php) creates 40 open requests through the factory, held in `OPEN_REQUEST_COUNT`, spread over the last twelve months. [DevelopmentSeederTest.php](../../../backend/tests/Feature/Seeders/DevelopmentSeederTest.php) asserts that every request is open.
- [InteractsWithServiceRequests.php](../../../backend/tests/Concerns/InteractsWithServiceRequests.php) has `assignThroughHistory`, which writes a status record on every assignment, including reassignments. [ShowServiceRequestTest.php](../../../backend/tests/Feature/ServiceRequests/ShowServiceRequestTest.php) asserts three status records after two assignments. Both follow the old reassignment rule and must change.
- [ServiceRequestAccessTest.php](../../../backend/tests/Feature/ServiceRequests/ServiceRequestAccessTest.php) fails when a `service-requests.*` route is not listed in its `ROUTES` constant, so every new route must be added there.
- [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php) checks `auth:sanctum`, `EnsurePasswordChanged` and the `manage-users` Gate. It still has a docblock on `apiRoutes()`, and [NewServiceRequest.php](../../../backend/app/Data/ServiceRequests/NewServiceRequest.php) has a docblock on its constructor. Both break the rule in section 6.3 of the main plan that a file holds only its header comment.
- [tests/TestCase.php](../../../backend/tests/TestCase.php) registers the in memory SQLite connection and makes it the default. SQLite ignores `lockForUpdate` and the PostgreSQL check constraints are skipped by `CheckConstraint` outside PostgreSQL.
- Laravel's global `TrimStrings` and `ConvertEmptyStringsToNull` middleware are active. The Form Requests still trim in `prepareForValidation`, following the established pattern, so validation never depends on global middleware.
- Mockery cannot mock final classes, and every service is final. Tests force a failure inside a transaction through a model event listener instead.

### 3.2 Frontend

- [constants/routes.ts](../../../frontend/constants/routes.ts) already holds `AppRoute.ServiceRequests` at `/service-requests`, which no page uses yet.
- [constants/navigation.ts](../../../frontend/constants/navigation.ts) holds Overview, My requests, New request, Users and Account.
- [app/(authenticated)/(workspace)/(staff)/layout.tsx](../../../frontend/app/(authenticated)/(workspace)/(staff)/layout.tsx) guards staff pages, and its header comment says Phase 6 adds the dashboard there.
- [constants/api-endpoints.ts](../../../frontend/constants/api-endpoints.ts) holds `ServiceRequests`, `MyServiceRequests` and `serviceRequestEndpoint(id)`.
- [enums/error-code.ts](../../../frontend/enums/error-code.ts) and [constants/error-messages.ts](../../../frontend/constants/error-messages.ts) already hold the four conflict codes. `ASSIGNEE_UNCHANGED` is missing. [constants/http.ts](../../../frontend/constants/http.ts) has no `409` member in `HttpStatus`.
- [service-request-columns.tsx](../../../frontend/components/service-requests/service-request-columns.tsx) exports one column per field, and its header comment says Phase 6 builds the dashboard from the same definitions. [enums/ui/service-request-table-column.ts](../../../frontend/enums/ui/service-request-table-column.ts) has no requester or action column.
- [service-request-detail-panel.tsx](../../../frontend/components/service-requests/service-request-detail-panel.tsx) always links back to My requests and shows no workflow controls.
- [use-resource.ts](../../../frontend/hooks/shared/use-resource.ts) returns `state` and `reload()` only. There is no way to show a record returned by a write without loading it again.
- [constants/service-request-limits.ts](../../../frontend/constants/service-request-limits.ts) mirrors the title and description limits but not the remark limit.
- [Dialog](../../../frontend/components/shared/dialog.tsx), [ConfirmDialog](../../../frontend/components/shared/confirm-dialog.tsx), [Select](../../../frontend/components/shared/select.tsx) (string values only), [Textarea](../../../frontend/components/shared/textarea.tsx), [FormField](../../../frontend/components/shared/form-field.tsx), [resolve-form-errors.ts](../../../frontend/lib/forms/resolve-form-errors.ts), [message-for-error.ts](../../../frontend/lib/http/message-for-error.ts) and [is-handled-by-route-guards.ts](../../../frontend/lib/http/is-handled-by-route-guards.ts) exist. [create-user-dialog.tsx](../../../frontend/components/users/create-user-dialog.tsx) shows the pattern for a form inside a dialog.
- Next.js resolves `/service-requests` from a page in the `(staff)` group and `/service-requests/mine`, `/service-requests/new` and `/service-requests/[serviceRequestId]` from the folder outside it. No two groups resolve to the same URL, which `route-groups.md` requires.

## 4. Request Flows

These flows describe the behaviour the steps below must produce. They are the reference for the tests.

### 4.1 Endpoints

| Method | Path | Route name | Guard | Success response |
| --- | --- | --- | --- | --- |
| GET | `/api/service-requests` | `service-requests.index` | `view-all-service-requests` Gate | `200` with a page of all requests |
| GET | `/api/service-requests/assignees` | `service-requests.assignees` | `handle-service-requests` Gate | `200` with every technician and admin |
| POST | `/api/service-requests/{serviceRequest}/assignment` | `service-requests.assign` | `handle-service-requests` Gate | `200` with the updated request |
| POST | `/api/service-requests/{serviceRequest}/reassignment` | `service-requests.reassign` | Gate, then policy `reassign` | `200` with the updated request |
| PATCH | `/api/service-requests/{serviceRequest}/status` | `service-requests.status` | Gate, then policy `changeStatus` | `200` with the updated request |

All routes also require `auth:sanctum`, the `api` limiter and `EnsurePasswordChanged`. `{serviceRequest}` must be a positive whole number. Anything else returns `404`.

Request bodies look like this.

```json
{ "remark": "Taking this one, I am at the office today." }
```

```json
{ "assignee_id": 5, "remark": "Ishara handles network hardware." }
```

```json
{ "status": "IN_PROGRESS", "remark": "Replacing the network adapter." }
```

Every summary and detail response now carries `available_actions`. A dashboard item looks like this.

```json
{
  "id": 42,
  "title": "Laptop does not connect to the office Wi-Fi",
  "status": "OPEN",
  "tags": ["HARDWARE", "NETWORK"],
  "requested_by": { "id": 7, "name": "Kasun Perera" },
  "assignee": null,
  "created_at": "2026-11-20T09:15:00Z",
  "available_actions": {
    "can_assign_to_self": true,
    "can_reassign": false,
    "allowed_statuses": []
  }
}
```

For the current assignee of an `IN_PROGRESS` request, `available_actions` is `{ "can_assign_to_self": false, "can_reassign": true, "allowed_statuses": ["ASSIGNED", "RESOLVED"] }`. For a requester, every flag is `false` and the list is empty.

An assignee choices response looks like this.

```json
{
  "data": [
    { "id": 1, "name": "System Administrator" },
    { "id": 5, "name": "Ishara Fernando" },
    { "id": 4, "name": "Tharindu Perera" }
  ]
}
```

### 4.2 Viewing the Dashboard

1. A technician or admin opens `/service-requests`, and the page calls `GET /api/service-requests?page={n}`.
2. The backend returns the requested page of every request, newest first, with tags, requester and current assignee eager loaded.
3. Each row shows the requester, the tags, the status, the assignee and the creation time. Rows whose `available_actions.can_assign_to_self` is `true` show an "Assign to me" button.
4. A page beyond the last returns an empty `data` array, and the frontend moves to the last page, as the other lists already do.

### 4.3 Assigning an Open Request to Oneself

1. The user chooses "Assign to me" on the dashboard or on the detail page and enters the mandatory remark in a dialog.
2. The frontend posts the remark to `POST /api/service-requests/{id}/assignment`.
3. The `handle-service-requests` Gate rejects requesters with `403 FORBIDDEN`. Route model binding returns `404` for an unknown id.
4. The backend trims and validates the remark.
5. In one transaction, the backend reloads the request with `lockForUpdate` and its current assignee.
6. If the status is no longer `OPEN`, it returns `409 CONCURRENT_UPDATE` and writes nothing.
7. Otherwise it writes the assignee history record with no previous assignee, the actor as the new assignee and the remark, then the status history record from `OPEN` to `ASSIGNED` with the actor and no remark, then sets the status to `ASSIGNED`.
8. It returns `200` with the detail resource.
9. On the dashboard, the frontend shows the success notification and reloads the page. On the detail page, it shows the returned request.

When two users take the same open request at the same moment, the second transaction waits for the first one's row lock, then finds the status `ASSIGNED` and returns `409 CONCURRENT_UPDATE`. Exactly one assignment is stored.

### 4.4 Reassigning a Request

1. The current assignee chooses "Reassign" on the detail page. The dialog loads `GET /api/service-requests/assignees`, leaves out the current assignee and asks for the new assignee and the mandatory remark.
2. The frontend posts both to `POST /api/service-requests/{id}/reassignment`.
3. The Gate rejects requesters. The controller checks `ServiceRequestPolicy::reassign`, which returns `403 FORBIDDEN` for anyone who is not the current assignee, including an open request with no assignee.
4. The backend validates that `assignee_id` belongs to an existing technician or admin and that the remark is present.
5. In one transaction, it reloads the request with the lock. If the actor is no longer the current assignee, it returns `409 CONCURRENT_UPDATE`. If the new assignee is the current assignee, it returns `409 ASSIGNEE_UNCHANGED`. In both cases nothing is written.
6. Otherwise it writes one assignee history record from the actor to the new assignee with the remark and touches the request. The status stays as it was, and no status record is written.
7. It returns `200` with the detail resource. The actor is no longer the assignee, so `available_actions` now offers nothing to them, and the frontend hides the controls.

### 4.5 Changing the Status

1. The current assignee picks one of `available_actions.allowed_statuses` on the detail page and may enter a remark.
2. The frontend sends both to `PATCH /api/service-requests/{id}/status`.
3. The Gate rejects requesters. The controller checks `ServiceRequestPolicy::changeStatus`, which returns `403 FORBIDDEN` for anyone who is not the current assignee.
4. The backend validates that `status` is a `ServiceRequestStatus` value and that the remark is at most 1000 characters.
5. In one transaction, it reloads the request with the lock. If the actor is no longer the current assignee, it returns `409 CONCURRENT_UPDATE`. If the target equals the current status, it returns `409 STATUS_UNCHANGED`. If the transition table does not allow the target, it returns `409 INVALID_STATUS_TRANSITION`. In every case nothing is written.
6. Otherwise it writes one status history record with the current status as from status, the target as to status, the actor and the remark, and sets the new status.
7. It returns `200` with the detail resource, and the frontend shows it.

### 4.6 Conflicts on the Frontend

1. A workflow call fails with any `409` or with `403 FORBIDDEN`.
2. The frontend closes the dialog, shows the error message as an error notification, and reloads the request on the detail page or the current page on the dashboard.
3. The reloaded `available_actions` shows only the actions that are still possible.

## 5. Backend Steps

All commands run from the `backend` folder. Every new file starts with its two to three line header comment and contains no other comments. Every touched file keeps its header comment accurate.

### Step B1. Update the main plan for the confirmed workflow rules

In [docs/plan/implementation-plan.md](../implementation-plan.md), make these changes so the main plan stays the single source of truth.

1. In section 3.9, replace the second bullet with: "Once a request has an assignee, only the user currently assigned can reassign it to another technician or admin, in any status. The new assignee must be different from the current one. Reassignment does not change the status."
2. In section 3.9, keep the bullet about history records. It already says a status record is written only when the status changes.
3. In section 3.10, after the bullet about allowed target statuses, add: "The current assignee may move a RESOLVED request back to ASSIGNED or IN_PROGRESS."
4. Remove section 8. Its first assumption is replaced by the reassignment rule above, its second assumption is now part of section 3.10, and its third assumption is already stated in section 3.7, which says a request has one or more tags.

### Step B2. Add the new error code, the Gate ability, the policy abilities and the role helper

1. In [ErrorCode.php](../../../backend/app/Enums/ErrorCode.php), add `case AssigneeUnchanged = 'ASSIGNEE_UNCHANGED';` after `StatusUnchanged`, and add it to the `Response::HTTP_CONFLICT` arm of `httpStatus()`.
2. In [lang/en/errors.php](../../../backend/lang/en/errors.php), add `'ASSIGNEE_UNCHANGED' => 'The request is already assigned to the selected user.'`.
3. In [GateAbility.php](../../../backend/app/Enums/GateAbility.php), add `case HandleServiceRequests = 'handle-service-requests';`.
4. In [PolicyAbility.php](../../../backend/app/Enums/PolicyAbility.php), add `case Reassign = 'reassign';` and `case ChangeStatus = 'change-status';`. Laravel turns an ability with a hyphen into the camel case policy method, so `change-status` calls `changeStatus`.
5. In [UserRole.php](../../../backend/app/Enums/UserRole.php), add `public static function staffValues(): array`, which returns the values of `staff()`. The assignee validation rule and the assignee query use it.

The frontend mirror of the error code is added in Step F1, within the same phase. The existing `ErrorCodeTest` covers the new case automatically.

### Step B3. Add the transition rules to the status enum

In [ServiceRequestStatus.php](../../../backend/app/Enums/ServiceRequestStatus.php), add the rules and update the header comment to say that the transition rules live here.

| Method | Responsibility |
| --- | --- |
| `isAssignable(): bool` | Returns `true` only for `Open`. Self assignment is the only way out of `Open`. |
| `allowedTargets(): array` | Returns the list of statuses a status change may move to, from the transition table in section 2.2. `Open` returns an empty list. No status ever lists itself or `Open`. |
| `canTransitionTo(self $target): bool` | Returns whether `$target` is in `allowedTargets()`. |

`allowedTargets()` is a single `match` expression, so the transition table exists in exactly one place.

### Step B4. Extend the model

In [ServiceRequest.php](../../../backend/app/Models/ServiceRequest.php), add one method and update the header comment.

| Method | Responsibility |
| --- | --- |
| `isAssignedTo(User $user): bool` | Calls `loadMissing('currentAssigneeHistory')` and returns `true` when the latest assignee history record names the user. Listings already eager load the relation, so `loadMissing` adds no query there, and a model resolved by route binding loads it once. |

### Step B5. Register the Gate and extend the policy

1. In `AppServiceProvider::configureAuthorization()`, add `Gate::define(GateAbility::HandleServiceRequests->value, fn (User $user): bool => in_array($user->role, UserRole::staff(), true))`.
2. In [ServiceRequestPolicy.php](../../../backend/app/Policies/ServiceRequestPolicy.php), add `reassign(User $user, ServiceRequest $serviceRequest): Response` and `changeStatus(User $user, ServiceRequest $serviceRequest): Response`. Both return `Response::allow()` when `$serviceRequest->isAssignedTo($user)` and `Response::deny()` otherwise, which the renderer maps to `403 FORBIDDEN`. Update the header comment so it covers viewing and working on a request.

The policy holds no role logic. An assignee is always a technician or admin, because only they can be assigned.

### Step B6. Create the data transfer objects

Create final readonly classes in `app/Data/ServiceRequests`.

| Class | Properties |
| --- | --- |
| `SelfAssignment` | `string $remark` |
| `Reassignment` | `int $assigneeId`, `string $remark` |
| `StatusChange` | `ServiceRequestStatus $status`, `?string $remark` |
| `AvailableActions` | `bool $canAssignToSelf`, `bool $canReassign`, `array $allowedStatuses` holding `ServiceRequestStatus` cases |

`AvailableActions` has a static `for(ServiceRequest $serviceRequest, User $viewer): self` factory, which is the only place where the actions offered to a user are worked out.

- `canAssignToSelf` is `$viewer->can(GateAbility::HandleServiceRequests->value)` and `$serviceRequest->status->isAssignable()`.
- `canReassign` is `$viewer->can(PolicyAbility::Reassign->value, $serviceRequest)`.
- `allowedStatuses` is `$serviceRequest->status->allowedTargets()` when `$viewer->can(PolicyAbility::ChangeStatus->value, $serviceRequest)` is true, and an empty list otherwise.

It combines the same Gate, policy and enum rules the endpoints enforce, so the frontend can never be offered an action the backend would reject for the current state.

In [NewServiceRequest.php](../../../backend/app/Data/ServiceRequests/NewServiceRequest.php), remove the constructor docblock, so the file holds only its header comment.

### Step B7. Create the domain exceptions

Create these in `app/Exceptions`. Each one extends `DomainException` and returns its code from `errorCode()`.

| Exception | Error code |
| --- | --- |
| `ConcurrentUpdateException` | `ConcurrentUpdate` |
| `InvalidStatusTransitionException` | `InvalidStatusTransition` |
| `StatusUnchangedException` | `StatusUnchanged` |
| `AssigneeUnchangedException` | `AssigneeUnchanged` |

### Step B8. Create the Form Requests

Create these in `app/Http/Requests/ServiceRequests`. Field names are class constants, and every remark rule uses `ServiceRequestLimits::REMARK_MAX_LENGTH`.

**`Concerns/NormalizesRemark.php`** is a trait with `public const string REMARK_FIELD = 'remark';` and a `prepareForValidation()` method that trims `remark` when it is a string and turns a value that is empty after trimming into `null`. The three requests below use it, so the remark is normalized once.

**`AssignServiceRequestRequest`**

- `remark` is `required`, `string` and `max:1000`.
- `authorize()` returns `true`, because the Gate middleware already authorizes the route.
- `toSelfAssignment(): SelfAssignment` builds the DTO from `validated()`.

**`ReassignServiceRequestRequest`**

- `assignee_id` is `required`, `integer` and `Rule::exists(User::class, 'id')->whereIn('role', UserRole::staffValues())`.
- `remark` is `required`, `string` and `max:1000`.
- `authorize()` returns `true`. The record level check runs in the controller through the policy, because the middleware priority resolves the model after `Authorize`, as the Phase 5 plan explains.
- `toReassignment(): Reassignment` builds the DTO.

**`ChangeServiceRequestStatusRequest`**

- `status` is `required`, `string` and `Rule::enum(ServiceRequestStatus::class)`. Every status value passes validation, including `OPEN`, so a forbidden target reaches the transition table and returns `409`, as section 5.8 of the main plan requires.
- `remark` is `nullable`, `string` and `max:1000`.
- `authorize()` returns `true`.
- `toStatusChange(): StatusChange` builds the DTO, converting the status with `ServiceRequestStatus::from()`.

A required remark that holds only spaces becomes `null` and fails `required`, so it returns `422` on `remark`. Fields the client sends beyond these, such as `assigned_to_user_id` on the self assignment or `changed_by_user_id`, are never read.

### Step B9. Extend the history recorder

In [ServiceRequestHistoryRecorder.php](../../../backend/app/Services/ServiceRequests/ServiceRequestHistoryRecorder.php), add these methods. The recorder stays the only class that writes history, and it still never opens a transaction.

| Method | Responsibility |
| --- | --- |
| `recordStatusChange(ServiceRequest $serviceRequest, ServiceRequestStatus $fromStatus, ServiceRequestStatus $toStatus, User $actor, ?string $remark): ServiceRequestStatusHistory` | Writes one status record with both a from status and an actor, which the initial record check constraint requires for every record after the first. |
| `recordAssigneeChange(ServiceRequest $serviceRequest, ?User $previousAssignee, User $newAssignee, string $remark): ServiceRequestAssigneeHistory` | Writes one assignee record. `$previousAssignee` is `null` only for the first assignment. |

### Step B10. Create the workflow service

**`app/Services/ServiceRequests/ServiceRequestWorkflowService.php`** is a final class that holds every workflow rule that depends on stored state. It receives `ServiceRequestHistoryRecorder` and `ServiceRequestService` through its constructor.

A private method, `lockForChange(ServiceRequest $serviceRequest): ServiceRequest`, runs inside the caller's transaction. It reloads the row with `ServiceRequest::query()->whereKey($serviceRequest->getKey())->lockForUpdate()->firstOrFail()` and then loads `currentAssigneeHistory.assignedTo` on the locked instance. The assignee history is loaded after the lock is held, and history rows are only ever written by a transaction that holds the same lock, so the current assignee read here is always the latest one.

`assignToSelf(User $actor, ServiceRequest $serviceRequest, SelfAssignment $assignment): ServiceRequest`

1. Opens a database transaction and calls `lockForChange`.
2. Throws `ConcurrentUpdateException` when `$locked->status->isAssignable()` is false.
3. Calls `recordAssigneeChange($locked, null, $actor, $assignment->remark)`.
4. Calls `recordStatusChange($locked, ServiceRequestStatus::Open, ServiceRequestStatus::Assigned, $actor, null)`.
5. Sets the status to `Assigned` and saves the request, which also updates `updated_at`.
6. Commits and returns the detail of a fresh instance.

`reassign(User $actor, ServiceRequest $serviceRequest, Reassignment $reassignment): ServiceRequest`

1. Opens a database transaction and calls `lockForChange`.
2. Throws `ConcurrentUpdateException` when `$locked->isAssignedTo($actor)` is false.
3. Loads the new assignee with `User::query()->findOrFail($reassignment->assigneeId)`.
4. Throws `AssigneeUnchangedException` when the new assignee is the current assignee, compared with `is()`.
5. Calls `recordAssigneeChange($locked, $actor, $newAssignee, $reassignment->remark)`.
6. Calls `$locked->touch()`. The status is not changed and no status record is written.
7. Commits and returns the detail of a fresh instance.

`changeStatus(User $actor, ServiceRequest $serviceRequest, StatusChange $change): ServiceRequest`

1. Opens a database transaction and calls `lockForChange`.
2. Throws `ConcurrentUpdateException` when `$locked->isAssignedTo($actor)` is false.
3. Throws `StatusUnchangedException` when the target equals the current status.
4. Throws `InvalidStatusTransitionException` when `$locked->status->canTransitionTo($change->status)` is false.
5. Calls `recordStatusChange($locked, $locked->status, $change->status, $actor, $change->remark)`.
6. Sets the new status and saves the request.
7. Commits and returns the detail of a fresh instance.

The detail is always built from `$serviceRequest->fresh()` passed to `ServiceRequestService::loadDetail`, so the response never carries the relations loaded before the write. The service receives the model resolved by route binding but never trusts its state. It always reads the locked copy, which is what makes a stale model safe and what the concurrency tests rely on.

### Step B11. Extend the service request service

In [ServiceRequestService.php](../../../backend/app/Services/ServiceRequests/ServiceRequestService.php), add two methods and update the header comment so it mentions the dashboard and the assignee choices.

| Method | Responsibility |
| --- | --- |
| `paginateAllRequests(): LengthAwarePaginator` | Returns every request with `SUMMARY_RELATIONS`, ordered by `created_at` descending and then by `id` descending, paginated by the page size. It shares the ordering with `paginateRequestsOf` through a private query builder method, so the order is defined once. |
| `listAssignableUsers(): Collection` | Returns every user whose role is in `UserRole::staffValues()`, ordered by `name` and then by `id`, selecting only `id` and `name`. |

### Step B12. Extend the resources

1. In [ServiceRequestSummaryResource.php](../../../backend/app/Http/Resources/ServiceRequestSummaryResource.php), add `available_actions` built from `AvailableActions::for($this->resource, $request->user())` and serialized through a new resource. Update the header comment. The detail resource inherits the field through its parent.
2. Create **`app/Http/Resources/AvailableActionsResource.php`**, which wraps an `AvailableActions` object and returns exactly `can_assign_to_self`, `can_reassign` and `allowed_statuses` as the list of enum values.

Every listing already eager loads `currentAssigneeHistory`, and the Gate and policy checks run in memory, so `available_actions` adds no query per row. The query count test in Step B18 proves it for the dashboard.

### Step B13. Create the controllers

Create single action invokable controllers in `app/Http/Controllers/ServiceRequests`. Each one only reads the request, authorizes through the Gate where the record matters, calls a service and returns a resource.

| Controller | Behaviour |
| --- | --- |
| `ListServiceRequestsController` | Returns `ServiceRequestSummaryResource::collection($service->paginateAllRequests())`. |
| `ListServiceRequestAssigneesController` | Returns `UserReferenceResource::collection($service->listAssignableUsers())`. |
| `AssignServiceRequestController` | Calls `assignToSelf($request->user(), $serviceRequest, $request->toSelfAssignment())` and returns `ServiceRequestDetailResource`. |
| `ReassignServiceRequestController` | Calls `Gate::authorize(PolicyAbility::Reassign->value, $serviceRequest)`, then `reassign(...)` with `$request->toReassignment()`, and returns `ServiceRequestDetailResource`. |
| `ChangeServiceRequestStatusController` | Calls `Gate::authorize(PolicyAbility::ChangeStatus->value, $serviceRequest)`, then `changeStatus(...)` with `$request->toStatusChange()`, and returns `ServiceRequestDetailResource`. |

### Step B14. Register the routes

Add the routes inside the existing `EnsurePasswordChanged` group in [routes/api.php](../../../backend/routes/api.php), next to the Phase 5 service request routes and outside the `manage-users` group. The two parameter free `GET` routes are registered before the numeric `{serviceRequest}` route.

```php
Route::get('service-requests', ListServiceRequestsController::class)
    ->middleware(Authorize::using(GateAbility::ViewAllServiceRequests->value))
    ->name('service-requests.index');

Route::middleware(Authorize::using(GateAbility::HandleServiceRequests->value))->group(function (): void {
    Route::get('service-requests/assignees', ListServiceRequestAssigneesController::class)
        ->name('service-requests.assignees');
    Route::post('service-requests/{serviceRequest}/assignment', AssignServiceRequestController::class)
        ->whereNumber('serviceRequest')
        ->name('service-requests.assign');
    Route::post('service-requests/{serviceRequest}/reassignment', ReassignServiceRequestController::class)
        ->whereNumber('serviceRequest')
        ->name('service-requests.reassign');
    Route::patch('service-requests/{serviceRequest}/status', ChangeServiceRequestStatusController::class)
        ->whereNumber('serviceRequest')
        ->name('service-requests.status');
});
```

The existing `service-requests.store` route stays first, because a `POST` to `service-requests` and a `GET` to the same path do not clash. No new rate limiter is needed.

### Step B15. Extend the development seeder

In [DevelopmentSeeder.php](../../../backend/database/seeders/DevelopmentSeeder.php), move part of the demo requests through the workflow so Phase 7 has realistic figures. Update the header comment.

1. Rename `OPEN_REQUEST_COUNT` to `REQUEST_COUNT`, still 40, and add `RESOLVED_REQUEST_COUNT = 16`, `IN_PROGRESS_REQUEST_COUNT = 8`, `ASSIGNED_REQUEST_COUNT = 8` and `REASSIGNED_REQUEST_COUNT = 6`. The remaining 8 requests stay open.
2. After creating the requests, sort them by `created_at` ascending. The oldest become resolved, the next become in progress, the next become assigned and the newest stay open, which is how a real queue looks.
3. The workflow is run through `ServiceRequestWorkflowService`, resolved from the container, so the seeded data obeys every rule and passes every check constraint. The actors are the demo technicians and, when it exists, the initial admin.
4. Each step happens at a time after the previous one. The first assignment happens between one and 48 hours after creation, a reassignment a few hours later, moving to in progress a few hours later and resolving between one and ten days later, each capped at the current time. Before each workflow call the seeder sets the clock with `Date::setTestNow()` and it resets the clock in a `finally` block, so the history records carry these times. The interval bounds are named constants.
5. The first `REASSIGNED_REQUEST_COUNT` requests that leave `OPEN` are reassigned once to a different staff user before their later steps, so the assignee history timeline and the Phase 7 workload graph show reassignments.

Running the seeder twice still creates no duplicate users. It adds another set of requests, as it did in Phase 5.

### Step B16. Add the PostgreSQL test group

**Configuration.** Create **`config/postgres_testing.php`**. It reads `TEST_POSTGRES_HOST`, `TEST_POSTGRES_PORT`, `TEST_POSTGRES_DATABASE`, `TEST_POSTGRES_USERNAME`, `TEST_POSTGRES_PASSWORD` and `TEST_POSTGRES_SSLMODE`, the last one defaulting to `require` like the main connection. Following Laravel convention, `env()` is called only in config files. The header comment says the values are used only by the `postgres` test group.

**Test case.** Create **`tests/PostgresTestCase.php`**, an abstract class that extends `Tests\TestCase`.

- `public const string GROUP = 'postgres';` names the PHPUnit group. Test classes declare `#[Group(PostgresTestCase::GROUP)]`.
- `createApplication()` calls the parent, then registers two PostgreSQL connections from `config('postgres_testing')`, `pgsql_testing` and `pgsql_testing_competitor`, and makes `pgsql_testing` the default. The second connection exists only so a test can hold a lock while the application works on the first one.
- When the database name is empty, it calls `markTestSkipped()` with a message held in a constant that names the environment variables to set.
- When the database name does not end with `_test`, held in a constant, it throws a `RuntimeException` with a clear message and never touches the database.
- It uses Laravel's `DatabaseTruncation` trait instead of `RefreshDatabase`. A row written inside the test transaction would be invisible to the second connection, so the data must be committed. Truncation empties every table after each test.
- Its `setUp()` seeds the tags through `TagSeeder`, which is idempotent.

**Scripts and environment.**

1. In [composer.json](../../../backend/composer.json), add a `test:postgres` script that clears the config and runs `php artisan test --group=postgres`.
2. Add these placeholders to `backend/.env.example`, with an empty database name so the group is skipped until a developer sets it.

```dotenv
TEST_POSTGRES_HOST=127.0.0.1
TEST_POSTGRES_PORT=5432
TEST_POSTGRES_DATABASE=
TEST_POSTGRES_USERNAME=your-test-database-username
TEST_POSTGRES_PASSWORD=your-test-database-password
TEST_POSTGRES_SSLMODE=require
```

3. In the local `.env`, point these at a new, empty database such as `service_request_tracker_test`, created once with `createdb`. A local server without SSL may set `TEST_POSTGRES_SSLMODE=prefer` for this test database only. The main connection keeps requiring SSL, as section 5.11 of the main plan requires.

`composer test` runs every test, including the `postgres` group when it is configured. `composer test:postgres` runs only that group.

### Step B17. Update the Phase 5 test helper and the affected tests

1. In [InteractsWithServiceRequests.php](../../../backend/tests/Concerns/InteractsWithServiceRequests.php), change `assignThroughHistory` so it writes a status record only for the first assignment, when `$previousAssignee` is `null`, and changes the status to `ASSIGNED` only then. This matches the confirmed reassignment rule. Add `assignThroughWorkflow(ServiceRequest $serviceRequest, User $assignee): ServiceRequest`, which calls `ServiceRequestWorkflowService::assignToSelf` with a remark constant, for tests that need a real assignment. Update the header comment so it no longer says the workflow arrives in Phase 6.
2. In [ShowServiceRequestTest.php](../../../backend/tests/Feature/ServiceRequests/ShowServiceRequestTest.php), change the test with two assignments to expect two status records, the first assignment and the initial record, and the status `ASSIGNED`.
3. In `ShowServiceRequestTest`, `ListOwnServiceRequestsTest` and `CreateServiceRequestTest`, add `available_actions` to the exact field assertions, and assert that a requester receives `can_assign_to_self` and `can_reassign` as `false` and an empty `allowed_statuses`.
4. In [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php), remove the docblock on `apiRoutes()`.

### Step B18. Write the Phase 6 tests

All feature tests use `RefreshDatabase`, `InteractsWithFrontendSession`, `InteractsWithServiceRequests` and `SeedsServiceRequestTags`, following the Phase 5 tests, unless they belong to the `postgres` group.

**`tests/Unit/Enums/ServiceRequestStatusTest.php`**

- A data provider covers every pair of current and target status, and `canTransitionTo` matches the transition table for each pair.
- `isAssignable` is `true` only for `OPEN`.
- No status lists itself or `OPEN` in `allowedTargets`.

**`tests/Unit/Authorization/HandleServiceRequestsGateTest.php`**

- The `handle-service-requests` Gate allows an admin and a technician and denies a requester.

**`tests/Unit/Policies/ServiceRequestPolicyTest.php`**

- `reassign` and `changeStatus` allow the current assignee.
- They deny the previous assignee after a reassignment, another technician, an admin who is not the assignee and a requester, each with a response whose status is `403`.
- They deny everyone on an open request.

**`tests/Feature/ServiceRequests/ServiceRequestAccessTest.php`**

- Add the five new routes to `ROUTES`, so the coverage test passes and the guest and pending password change cases run for them.
- A requester receives `403 FORBIDDEN` on every staff route, both for an existing request id and for an unknown one.
- A technician requesting an unknown id on each workflow route receives `404 NOT_FOUND`.

**`tests/Feature/ServiceRequests/ListServiceRequestsTest.php`**

- A technician and an admin each receive every request, from every requester, with `data`, `links` and `meta`, and `meta.per_page` equals the page size constant.
- Requests are ordered newest first by `created_at`, with the id breaking ties, and the second page holds the next requests.
- Each item has exactly the summary fields, including `available_actions`.
- An open request offers `can_assign_to_self` to the viewer. A request assigned to the viewer offers `can_reassign` and the allowed statuses. A request assigned to someone else offers nothing.
- A page beyond the last returns an empty `data` array.
- The number of database queries is the same for one request and for fifteen requests.

**`tests/Feature/ServiceRequests/ServiceRequestAssigneesTest.php`**

- The list holds every technician and admin, including one with a pending password change, and no requester.
- It is ordered by name, then by id.
- Each item has exactly `id` and `name`.

**`tests/Feature/ServiceRequests/AssignServiceRequestTest.php`**

- A technician assigning an open request receives `200` with the detail resource, status `ASSIGNED`, the technician as assignee and `available_actions` offering reassignment and the allowed statuses.
- The database holds one new assignee history record with an empty previous assignee, the technician as new assignee and the trimmed remark, and one new status record from `OPEN` to `ASSIGNED` with the technician as actor and no remark.
- An admin can assign an open request to themselves.
- A missing remark, a remark of only spaces and a remark over 1000 characters each return `422` on `remark` and write nothing.
- Assigning a request that is already assigned returns `409 CONCURRENT_UPDATE` and writes nothing, whether the request is assigned to someone else or to the same user.
- Client supplied `assignee_id`, `assigned_to_user_id` and `status` fields are ignored.

**`tests/Feature/ServiceRequests/ReassignServiceRequestTest.php`**

- A data provider covers `ASSIGNED`, `IN_PROGRESS` and `RESOLVED`. In each, the current assignee reassigns to another technician, receives `200`, the new user is the assignee, the status is unchanged, one assignee history record is written from the old to the new assignee with the remark, and no status record is written.
- Reassigning to an admin works.
- After a reassignment, the new assignee can change the status and the previous assignee receives `403 FORBIDDEN`.
- Reassigning to the current assignee returns `409 ASSIGNEE_UNCHANGED` and writes nothing.
- Reassigning to a requester, to an unknown user id or to a non numeric value returns `422` on `assignee_id`.
- A missing remark returns `422` on `remark`.
- A technician who is not the assignee, an admin who is not the assignee and anyone acting on an open request receive `403 FORBIDDEN`, and nothing is written.

**`tests/Feature/ServiceRequests/ChangeServiceRequestStatusTest.php`**

- A data provider covers every allowed transition in the table. Each returns `200`, writes one status record with the right from status, to status, actor and remark, and stores the new status.
- Moving a resolved request back to in progress works.
- A missing remark and a blank remark are both stored as `null`. A remark over 1000 characters returns `422`.
- Setting the current status returns `409 STATUS_UNCHANGED`. Setting `OPEN` returns `409 INVALID_STATUS_TRANSITION`. An unknown value returns `422` on `status`. Nothing is written in any of these cases.
- A user who is not the assignee receives `403 FORBIDDEN`, and nothing is written.

**`tests/Feature/ServiceRequests/WorkflowConsistencyTest.php`** runs on SQLite and proves the re-validation and the transactions deterministically. The service is called directly with a model instance loaded before the change, which is exactly what a request in flight holds when another change commits first.

- A self assignment with a stale open instance, after another technician assigned the request, throws `ConcurrentUpdateException`, and exactly one assignee history record exists.
- A status change and a reassignment with a stale instance, after the actor reassigned the request away in another call, each throw `ConcurrentUpdateException` and write nothing.
- When a `creating` listener on `ServiceRequestStatusHistory` throws during a self assignment, the assignee history record written before it is rolled back and the status stays `OPEN`. The listener is registered only inside that test.

**`tests/Feature/Postgres/RowLockingTest.php`** extends `PostgresTestCase` and belongs to the `postgres` group.

- The competitor connection begins a transaction and performs a self assignment for technician A through the workflow service, with the default connection switched to the competitor for that call and switched back afterwards. Its transaction stays open, so it holds the row lock.
- On the main connection, with `SET lock_timeout` set to a short value held in a constant, a self assignment for technician B fails with a `QueryException` whose SQLSTATE is `55P03`, which shows that it waited for the lock. Nothing is written for technician B.
- After the competitor commits and `lock_timeout` is reset, the same self assignment for technician B returns `ConcurrentUpdateException`.
- The database holds exactly one assignee history record, naming technician A, one status record from `OPEN` to `ASSIGNED`, and the status `ASSIGNED`.
- A second test repeats the pattern for a status change against a reassignment and shows that the status change waits and then fails with `ConcurrentUpdateException`.

**`tests/Feature/Postgres/CheckConstraintsTest.php`** extends `PostgresTestCase` and belongs to the `postgres` group. Each case inserts a row through the query builder and expects a `QueryException` whose SQLSTATE is `23514`.

- A service request with a status outside the enum.
- A status history record with an empty from status and an actor, and one with a from status and no actor.
- A user with an uppercase email and a user with an unknown role.
- A tag with an unknown name and an audit log row with an unknown event.
- A full workflow run of self assignment, status changes, reassignment and reopening succeeds, which proves that every record the workflow writes satisfies the constraints.

**`tests/Feature/Auth/ProtectedRoutesTest.php`**

- Add `test_every_staff_service_request_route_requires_its_gate`. A constant maps `service-requests.index` to the `view-all-service-requests` Gate and the four other new route names to the `handle-service-requests` Gate, and the test asserts that each route's gathered middleware contains the matching `Authorize::using(...)` string.

**`tests/Feature/Seeders/DevelopmentSeederTest.php`**

- Use `REQUEST_COUNT` instead of `OPEN_REQUEST_COUNT`.
- The number of requests in each status equals the seeder constants.
- Every request that is not open has at least one assignee history record, and `REASSIGNED_REQUEST_COUNT` requests have two.
- Every status record other than the initial one has a from status and an actor, and every history record is not older than its request and follows the previous record of the same request.
- `Date::setTestNow()` is reset after the seeder runs.

**Regression.** Every Phase 1 to Phase 5 test passes after the changes in Step B17.

### Step B19. Format and verify

1. Run `vendor/bin/pint`, then `composer lint`.
2. Run `composer test` and confirm every test passes. Without the PostgreSQL test settings, the `postgres` group is reported as skipped.
3. Create the local test database, set the `TEST_POSTGRES_*` values in `.env`, run `composer test:postgres` and confirm every test in the group passes.
4. Run `php artisan migrate:fresh --seed`, then `php artisan db:seed --class=DevelopmentSeeder`, and confirm in `psql` that the requests are spread across all four statuses with matching history.
5. With `php artisan serve` running, confirm that `curl -i http://localhost:8000/api/service-requests` returns `401` with the error shape.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before writing Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/03-api-reference/03-file-conventions/route-groups.md` (two groups must not resolve to the same URL), `page.md`, `layout.md`, `dynamic-routes.md`, and the `useRouter` and `usePathname` references in `01-app/03-api-reference/04-functions/`.

The steps are split in three parts. Steps F1 to F5 add the shared pieces. Steps F6 to F10 build the workflow feature. Steps F11 to F13 add the page and verify.

### Step F1. Mirror the error code, the endpoints and the limits

1. In `enums/error-code.ts`, add `AssigneeUnchanged = "ASSIGNEE_UNCHANGED"` after `StatusUnchanged`.
2. In `constants/error-messages.ts`, add the message `The request is already assigned to this person.`.
3. In `constants/http.ts`, add `Conflict = 409` to `HttpStatus`.
4. In `constants/api-endpoints.ts`, add `ServiceRequestAssignees = "/api/service-requests/assignees"` to `ApiEndpoint`, and the helpers `serviceRequestAssignmentEndpoint(serviceRequestId)`, `serviceRequestReassignmentEndpoint(serviceRequestId)` and `serviceRequestStatusEndpoint(serviceRequestId)`, each built on `serviceRequestEndpoint`.
5. In `constants/service-request-limits.ts`, add `SERVICE_REQUEST_REMARK_MAX_LENGTH = 1000`, and update the header comment so it mentions the remark.

### Step F2. Add the types and the UI enum members

**Types.** Each file mirrors the wire format in snake_case.

| File | Type |
| --- | --- |
| `types/service-requests/available-actions.ts` | `AvailableActions` with `can_assign_to_self: boolean`, `can_reassign: boolean` and `allowed_statuses: ServiceRequestStatus[]` |
| `types/service-requests/assign-service-request-request.ts` | `AssignServiceRequestRequest` with `remark: string` |
| `types/service-requests/reassign-service-request-request.ts` | `ReassignServiceRequestRequest` with `assignee_id: number` and `remark: string` |
| `types/service-requests/change-service-request-status-request.ts` | `ChangeServiceRequestStatusRequest` with `status: ServiceRequestStatus` and `remark: string \| null` |

In `types/service-requests/service-request-summary.ts`, add `available_actions: AvailableActions` and update the header comment. `ServiceRequestDetail` inherits it.

**Enum.** In `enums/ui/service-request-table-column.ts`, add `RequestedBy = "requested-by"` and `Actions = "actions"`.

### Step F3. Add the constants

1. Create **`constants/service-request-workflow-fields.ts`** with `AssignServiceRequestField`, `ReassignServiceRequestField` and `ChangeServiceRequestStatusField`, each typed with `satisfies Record<string, keyof ...>` against its request type, plus their field name types and field lists, following `constants/service-request-fields.ts`.
2. Create **`constants/service-request-workflow-messages.ts`** with `ServiceRequestWorkflowMessage`. It holds the dashboard title, description, list title, list description, table caption, empty state text and load failure message, the requested by and actions column headers, the "Assign to me" label, the assign dialog title, description and submit label, the remark label, placeholder and the required and optional hints, the "Reassign" label, the reassign dialog title, description, assignee label, placeholder, the "no other technician or admin" text, the assignees load failure message and submit label, the work section title and description, the status label, placeholder and submit label, the assigned, reassigned and status changed notifications and the back to dashboard label. It also exports `formatReassignedNotification(assigneeName)` and `formatStatusChangedNotification(status)`, which reads the label from `SERVICE_REQUEST_STATUS_LABELS`, and `statusOptions(statuses: readonly ServiceRequestStatus[]): SelectOption<ServiceRequestStatus>[]`.
3. In `constants/navigation.ts`, add `ServiceRequests: "Requests"` to `NavigationMessage` and an item with `AppRoute.ServiceRequests` and `STAFF_ROLES`, placed after Overview.

### Step F4. Let the single resource hook show a written record

In [use-resource.ts](../../../frontend/hooks/shared/use-resource.ts), add `replace(resource: Resource): void` to `ResourceResult`. It increases the request counter, so a load still in flight cannot overwrite the new value, and sets the state to `Loaded` with the given record. The workflow returns the updated request, so the detail page shows it without a second request. Update the header comment. The users detail page keeps working unchanged, because it does not use the new member.

### Step F5. Add the workflow helpers

- **`lib/service-requests/is-workflow-conflict.ts`** exports `isWorkflowConflict(error: ApiError | null): boolean`. It returns `true` when the status is `HttpStatus.Conflict` or the code is `ErrorCode.Forbidden`, because both mean that the request changed under the user.
- **`lib/service-requests/service-request-back-link.ts`** exports `serviceRequestBackLink(role: UserRole): { route: AppRoute; label: string }`. Roles in `STAFF_ROLES` get the dashboard with the back to dashboard label, and every other role gets My requests with the existing back label.

### Step F6. Extend the service request service

In [service-request-service.ts](../../../frontend/services/service-requests/service-request-service.ts), add these methods and update the header comment.

| Method | Call | Returns |
| --- | --- | --- |
| `listServiceRequests(page: number)` | `GET ApiEndpoint.ServiceRequests` with `params: { page }` | `PaginatedResponse<ServiceRequestSummary>` |
| `listAssignees()` | `GET ApiEndpoint.ServiceRequestAssignees` | `UserReference[]`, unwrapped |
| `assignToSelf(serviceRequestId, request: AssignServiceRequestRequest)` | `POST serviceRequestAssignmentEndpoint(...)` | `ServiceRequestDetail`, unwrapped |
| `reassign(serviceRequestId, request: ReassignServiceRequestRequest)` | `POST serviceRequestReassignmentEndpoint(...)` | `ServiceRequestDetail`, unwrapped |
| `changeStatus(serviceRequestId, request: ChangeServiceRequestStatusRequest)` | `PATCH serviceRequestStatusEndpoint(...)` | `ServiceRequestDetail`, unwrapped |

### Step F7. Build the hooks

- **`hooks/service-requests/use-service-requests.ts`** returns `usePaginatedResource` with a module level function that calls `serviceRequestService.listServiceRequests`, following `use-my-service-requests.ts`.
- **`hooks/service-requests/use-service-request-assignees.ts`** returns `useResource` with a module level loader that calls `serviceRequestService.listAssignees`. The reassign dialog mounts its form only while it is open, so the list is loaded each time the dialog opens and is always current.

### Step F8. Build the workflow dialogs and the status form

Create these client components in `components/service-requests/`. All visible text comes from `ServiceRequestWorkflowMessage`, `UiMessage` or the enum label records. Each form follows `create-user-dialog.tsx`: values in state, the dialog is not dismissible while the request runs, field errors come from `resolveFormErrors` with the matching field list, and an alert shows any other error.

| Component | Responsibility |
| --- | --- |
| `assign-to-me-dialog.tsx` | Props are `serviceRequest: ServiceRequestSummary \| null`, `onClose`, `onAssigned(detail: ServiceRequestDetail)` and `onConflict(error: ApiError)`. It is open while `serviceRequest` is not `null` and shows the request title. It has a required remark `Textarea` with `maxLength` and the required hint. On submit it calls `assignToSelf`. On success it clears the remark and calls `onAssigned`. When `isWorkflowConflict` is true it clears the remark and calls `onConflict`. Failures handled by the route guards show nothing. Both the dashboard and the detail page use it. |
| `reassign-dialog.tsx` | Props are `serviceRequest: ServiceRequestDetail`, `open`, `onClose`, `onReassigned(detail)` and `onConflict(error)`. Its form part mounts only while open and calls `useServiceRequestAssignees()`. It shows `SectionLoader` while loading and `LoadFailureAlert` on failure. Otherwise it shows a `Select` of the assignees except the current assignee, with ids converted to strings for the options and back to numbers on submit, and a required remark `Textarea`. When no other staff user exists, it shows the "no other technician or admin" text and disables submit. On submit it calls `reassign` and handles success and conflicts like the assign dialog. |
| `change-status-form.tsx` | Props are `serviceRequest: ServiceRequestDetail`, `onChanged(detail)` and `onConflict(error)`. It shows a `Select` built with `statusOptions(serviceRequest.available_actions.allowed_statuses)` and a placeholder, and an optional remark `Textarea` with the optional hint. A blank remark is sent as `null`. On submit it calls `changeStatus`. On success it resets the form and calls `onChanged`. Conflicts call `onConflict`. |

### Step F9. Build the workflow section of the detail page

**`components/service-requests/service-request-workflow-section.tsx`** is a client component with the props `serviceRequest: ServiceRequestDetail`, `onUpdated(detail)` and `onConflict(error)`.

- It renders nothing when `can_assign_to_self` and `can_reassign` are both `false` and `allowed_statuses` is empty, so requesters and staff with no available action never see it.
- Otherwise it renders a `ContentSection` with the work section title and description. Its actions slot holds the "Assign to me" button when `can_assign_to_self` is true and the "Reassign" button when `can_reassign` is true. Its body holds `ChangeStatusForm` when `allowed_statuses` is not empty.
- It owns the open state of `AssignToMeDialog` and `ReassignDialog`.
- On success it shows the matching notification, `formatReassignedNotification` with the new assignee's name for a reassignment and `formatStatusChangedNotification` for a status change, and calls `onUpdated`.

In [service-request-detail-panel.tsx](../../../frontend/components/service-requests/service-request-detail-panel.tsx), make these changes and update the header comment.

1. Read `replace` together with `state` and `reload` from `useServiceRequest`.
2. Render `ServiceRequestWorkflowSection` between `ServiceRequestDetails` and the two timelines, passing `replace` as `onUpdated`.
3. Its `onConflict` handler shows `messageForError(error)` as an error notification and calls `reload()`.
4. Replace both links to My requests with `serviceRequestBackLink(user.role)` from `useAuth()`, for the back link and for the not found state.

The timelines need no change. A reassignment adds only an assignee record, so the status timeline does not grow, which matches the confirmed rule.

### Step F10. Build the dashboard components

1. In [service-request-columns.tsx](../../../frontend/components/service-requests/service-request-columns.tsx), add `SERVICE_REQUEST_REQUESTED_BY_COLUMN`, which shows `requested_by.name`. Update the header comment so it no longer says Phase 6 builds the dashboard.
2. Create **`components/service-requests/service-requests-table.tsx`**, a presentational component built on the shared `Table` with the title, requested by, tags, status, assignee and created columns, plus an actions column built by a local `createAssignColumn(onAssign)` function. The actions cell shows a small "Assign to me" `Button` when `available_actions.can_assign_to_self` is true and nothing otherwise. Props are `serviceRequests` and `onAssign(serviceRequest)`. It shows the dashboard caption and empty state.
3. Create **`components/service-requests/service-requests-dashboard-panel.tsx`**, a client component that calls `useServiceRequests()`. It renders a `ContentSection` with `SectionLoader` while loading, `LoadFailureAlert` on failure, and otherwise `ServiceRequestsTable` and `Pagination` driven by `meta` and disabled while `isChangingPage` is true. It owns `AssignToMeDialog` and the request selected for it. On success it shows the assigned notification and calls `reload()`, so the user stays on the same page. On a conflict it shows `messageForError(error)` as an error notification and calls `reload()`.

### Step F11. Add the dashboard page

```text
app/(authenticated)/(workspace)/
  (staff)/
    layout.tsx                                  unchanged, RequireRole with STAFF_ROLES
    overview/page.tsx                           unchanged
    service-requests/page.tsx                   new, the dashboard at /service-requests
  (admin)/...                                   unchanged
  service-requests/
    mine/page.tsx                               unchanged
    new/page.tsx                                unchanged
    [serviceRequestId]/page.tsx                 unchanged
  account/page.tsx                              unchanged
```

1. Create **`(staff)/service-requests/page.tsx`**, a server component that exports metadata with the dashboard title and renders `PageHeader` and `ServiceRequestsDashboardPanel`.
2. Update the header comment of `(staff)/layout.tsx` so it no longer says Phase 6 adds the dashboard.

The `(staff)` page resolves only to `/service-requests`, and the folder outside the group serves only the deeper paths, so no two groups resolve to the same URL. A requester who types `/service-requests` is sent to their home page by `RequireRole`.

### Step F12. Check the shared states

1. Confirm that the users list, the user detail page, My requests and the request detail page still load, page and retry as before the change to `use-resource.ts`.
2. Confirm that a requester's request detail page shows no workflow section and still links back to My requests.

### Step F13. Verify the frontend

1. Run `npm run lint` and confirm there are no errors or warnings.
2. Run `npm run typecheck` and confirm it passes.
3. Run `npm run build` and confirm it succeeds and lists `/service-requests` next to the Phase 5 request pages.
4. Search the new files for string literals in JSX and confirm every visible text comes from a constant.
5. Search for `dangerouslySetInnerHTML`, `localStorage`, `sessionStorage` and `console.` in `components/service-requests`, `hooks/service-requests` and `lib/service-requests` and confirm there are no matches.

## 7. End to End Verification

Run both applications locally with `php artisan serve` and `npm run dev`, and repeat the checks with `npm run start` after a build. Run `php artisan db:seed --class=DevelopmentSeeder` first for demo data. Use two browser profiles signed in as two different technicians where a check needs two users. Confirm each item in a browser with the developer tools open.

1. A technician and an admin see Requests in the sidebar after Overview. A requester does not, and a requester who types `/service-requests` is sent to My requests without the dashboard flashing.
2. The dashboard lists every request newest first with requester, tags, status, assignee and creation time, and the pagination moves between pages. Only open requests show "Assign to me".
3. Technician A chooses "Assign to me" on an open request. Submitting without a remark shows the remark error. With a remark, the success notification appears, the user stays on the dashboard and the row shows technician A as assignee with status Assigned.
4. The detail page of that request shows technician A as assignee, a status record "Changed from Open to Assigned" by technician A, an assignee record "Assigned to" technician A with the remark, and the work section with "Reassign" and the status form offering In progress and Resolved.
5. Technician A moves the request to In progress with a remark, then to Resolved without a remark. Each change shows its notification, updates the status badge and adds one status record. The status form then offers Assigned and In progress, and moving back to In progress works.
6. Technician A reassigns the request to technician B. The list in the dialog does not show technician A. After the change the status stays In progress, the assignee history shows the reassignment with the remark, no new status record appears and the work section disappears for technician A.
7. Technician B opens the request and sees the work section. Reassigning back to technician A works in every status, including Resolved.
8. Both technicians open the same open request on its detail page. Technician A assigns it first. When technician B then submits "Assign to me", the dialog closes, the conflict message appears and the page reloads with technician A as assignee and no assign button.
9. Technician A opens a request assigned to them in two tabs, reassigns it in the first tab and then changes the status in the second. The conflict message appears and the second tab reloads without the work section.
10. A requester opening their own assigned request sees both timelines but no work section, and the back link goes to My requests. A technician's back link goes to the dashboard.
11. Sending `PATCH /api/service-requests/{id}/status` with `OPEN` from the browser console as the assignee returns `409 INVALID_STATUS_TRANSITION`, the current status returns `409 STATUS_UNCHANGED`, and a reassignment to the current assignee returns `409 ASSIGNEE_UNCHANGED`. A requester calling any workflow endpoint receives `403 FORBIDDEN`.
12. In `psql`, every status record other than the initial record of each request has a from status and an actor, every assignment and reassignment has a remark, and no request has two assignee records written at the same moment by different users.
13. The network tab shows that no workflow response contains an email, a password hash or `requested_by_user_id`.
14. The browser console shows no CSP violations, and nothing is written to local or session storage.

## 8. Done Criteria

Phase 6 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| The dashboard lists all requests for technicians and admins, newest first and paginated | `ListServiceRequestsTest` passes and item 2 of section 7 succeeds. |
| Self assignment, reassignment and status change lock the row, re-validate the state and write their history in one transaction | `AssignServiceRequestTest`, `ReassignServiceRequestTest`, `ChangeServiceRequestStatusTest`, `WorkflowConsistencyTest` and `RowLockingTest` pass. |
| Transition rules are centralized in the status enum and the workflow service | `ServiceRequestStatusTest` passes and no other class lists allowed statuses. |
| Tests cover valid transitions, no op and prohibited transitions, conflicting concurrent changes, reassignment by a non assignee and concurrent self assignment | The cases listed in Step B18 pass, including the `postgres` group. |
| Requests move through the workflow correctly and every change is recorded | Items 3 to 7 of section 7 succeed and the history assertions in the workflow tests pass. |
| Conflicting changes are rejected without corrupting data | Items 8, 9 and 12 of section 7 succeed and `RowLockingTest` proves exactly one winner. |
| The dashboard, the assign to me action, the reassign dialog and the status change controls exist, and a conflict shows a clear message and reloads the request | Steps F8 to F11 are complete and items 3 to 9 of section 7 succeed. |
| The PostgreSQL check constraints hold for every record the workflow writes | `CheckConstraintsTest` passes. |
| The main plan reflects the confirmed workflow rules | Step B1 is complete. |
| Coding standards | Pint, ESLint and the TypeScript check pass, every new file has only its header comment, and no strings are hardcoded outside enums and constants. |

## 9. Known Limitations and Handover to Later Phases

- **Overview figures.** Phase 7 reads the seeded workflow history. Every request has its initial status record and every later record has an actor, so the last status change, the monthly resolved counts, the average resolution time and the current workload can all be computed from the history tables and the current assignee relation. The `assigned_to_user_id` index serves the workload graph.
- **Repeated resolution.** A request can be resolved, reopened and resolved again. Phase 7 must decide whether resolution time counts to the first or the latest move to `RESOLVED`.
- **Lock waits.** No `lock_timeout` is configured for the application. Workflow transactions are short, so a waiting request is delayed by milliseconds. If production ever shows long waits, Phase 8 can set a lock timeout and map the error to `409 CONCURRENT_UPDATE`.
- **PostgreSQL test group.** The locking and constraint tests run only where a PostgreSQL test database is configured. Phase 8 should run `composer test:postgres` as part of release readiness.
- **Dashboard page in the URL.** The current page is kept in component state, as on the other lists, so a browser reload returns to page 1.
- **No filters.** The dashboard has no search or filter, because the main plan describes none.
- **Frontend tests.** The frontend still has no test runner, so the workflow screens are verified manually through section 7.

## 10. Suggested Commit Sequence

Each commit leaves both applications working.

1. `docs: confirm the reassignment and reopening rules in the main plan` covers Step B1.
2. `feat(backend): add transition rules, workflow service and dashboard, assignment, reassignment and status endpoints` covers Steps B2 to B14.
3. `feat(backend): move development seed requests through the workflow` covers Step B15.
4. `test(backend): add workflow tests and the opt in PostgreSQL locking and constraint group` covers Steps B16 to B19.
5. `feat(frontend): add workflow types, constants, helpers and service methods` covers Steps F1 to F7.
6. `feat(frontend): add request dashboard, assign to me, reassign and status change controls` covers Steps F8 to F13.
