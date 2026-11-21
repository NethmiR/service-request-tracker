# Phase 7 Implementation Plan. Overview and Analytics

## 1. Purpose

This document breaks Phase 7 of the [implementation plan](../implementation-plan.md) into ordered, verifiable steps. Phase 7 delivers the overview page for technicians and admins. On the backend it adds one overview endpoint that returns the count boxes, the highlight lists and the graph datasets, all computed with aggregate queries on indexed columns. It adds a dedicated Gate for the overview, a reporting calendar that builds the last twelve months in a configurable time zone, and one new index for the resolution figures. On the frontend it replaces the placeholder overview page with stat cards, four highlight lists and four charts, built as reusable components with hand drawn SVG and no new dependency.

Every step follows the main plan, in particular sections 2 (User Roles and Access), 3.12 (Overview Page), 4 (Data Model), 5 (Security Requirements) and 6 (Engineering Standards). It also keeps every convention set in the [Phase 1 plan](phase-1-implementation-plan.md), the [Phase 2 plan](phase-2-implementation-plan.md), the [Phase 3 plan](phase-3-implementation-plan.md), the [Phase 4 plan](phase-4-implementation-plan.md), the [Phase 5 plan](phase-5-implementation-plan.md) and the [Phase 6 plan](phase-6-implementation-plan.md). These include the file header comments, enum naming, snake_case JSON, the single error shape, ISO 8601 UTC timestamps, the `EnsurePasswordChanged` route group, the middleware priority list, the `UserReferenceResource` for people on request screens, the current assignee taken from the latest assignee history record, the shared single resource hook and the shared loading components. Where this document and the main plan disagree, the main plan wins and this document must be corrected. Step B1 adds the confirmed figure definitions to the main plan first, so the main plan stays the single source of truth.

Phase 7 does not add filters, date range pickers, exports or drill down pages to the overview. The main plan describes none of them. Security hardening and the README belong to Phase 8.

## 2. Decisions

### 2.1 Confirmed Decisions

These decisions were confirmed before writing this plan.

| Topic | Decision |
| --- | --- |
| Counting resolved requests | Only requests whose status is `RESOLVED` now are counted. Each one counts once, in the month of its latest move to `RESOLVED`. A request that was resolved and then reopened is not counted while it is open again. This settles the question the Phase 6 plan left open for Phase 7. |
| Resolution time | The time from the creation of the request to its latest move to `RESOLVED`. The monthly average covers the requests counted as resolved in that month, as defined above. |
| Charts | Hand built SVG components styled with Tailwind, with no chart library. They use SVG attributes and Tailwind classes only, so the Content Security Policy needs no change. |
| Month boundaries | A new `OVERVIEW_TIME_ZONE` environment variable decides where one month ends and the next begins. It defaults to `UTC`, the application time zone. Production can set, for example, `Asia/Colombo` without a code change. Stored timestamps stay in UTC. |

### 2.2 Decisions Made in This Plan

The following smaller decisions are made in this plan and are listed here so they can be challenged.

| Topic | Decision |
| --- | --- |
| Endpoint | `GET /api/overview`, route name `overview.show`, returns every figure in one response under the default `data` wrapper. One request keeps the page consistent, because every figure is computed at the same moment. |
| Gate | A new `view-overview` Gate allows the `ADMIN` and `TECHNICIAN` roles. "View the overview page" is its own row in the capability table in section 2 of the main plan, so it gets its own Gate, following the reasoning the Phase 6 plan used for `handle-service-requests`. It is applied as route middleware, so a requester receives `403 FORBIDDEN`. |
| Count boxes | Three counts, for `OPEN`, `ASSIGNED` and `IN_PROGRESS`, as section 3.12 of the main plan lists. They are returned as a list of `{ "status", "count" }` items in enum order, so the frontend maps them through the existing status labels and no status name is written as a JSON key. A status with no requests is returned with `0`. |
| Reporting window | The four graphs cover the current month and the eleven months before it, twelve months in total, oldest first. The current month is included even though it is not complete, so today's activity is visible. |
| Month keys | Each month is identified by a `YYYY-MM` string in the reporting time zone, such as `2026-11`. The response also returns `time_zone`, so the page can say which time zone the months follow. |
| Created against resolved | One dataset with twelve items, each holding `month`, `created` and `resolved`. A request counts as created in the month of its `created_at`. |
| Average resolution time | Returned as `average_resolution_hours` on the same monthly items, rounded to one decimal, or `null` for a month with no resolved request. The frontend draws a gap for `null` instead of a zero. |
| Tag distribution | Counts the requests created in the reporting window that carry each tag. A request with two tags counts under both, so the total can exceed the number of requests. All four tags are always returned, in enum order, with `0` when unused. The window matches the other trend graphs. |
| Workload | For every technician and admin, the number of requests currently assigned to them in `ASSIGNED` and in `IN_PROGRESS`, returned separately so the chart can stack them. Resolved requests are not workload. Every staff user is listed, including users with no requests and users with a pending password change, matching the assignee choices in Phase 6. Users are ordered by name and then by id. |
| Current assignee in aggregates | The current assignee is the assignee history record with the highest id for each request, which is exactly the record `currentAssigneeHistory()->latestOfMany()` picks. Aggregate queries use the same rule, so the overview and the request screens always agree. |
| Highlight lists | Four lists of at most 5 requests each, held in a named constant. "Oldest open" holds `OPEN` requests, oldest first. "Assigned to you" holds `ASSIGNED` and `IN_PROGRESS` requests whose current assignee is the viewing user, oldest first. "No recent status change" holds unresolved requests whose latest status record is older than the configured number of days, longest waiting first. "Recently resolved" holds `RESOLVED` requests, most recently resolved first. |
| Stale threshold | A new `OVERVIEW_STALE_AFTER_DAYS` environment variable, defaulting to 7. Only status records count as a status change, as section 3.12 of the main plan says, so a reassignment does not reset the clock. Every request has its initial status record, so an open request that was never assigned waits from its creation. The value is returned in the response so the frontend never mirrors it. |
| Highlight item fields | `id`, `title`, `status`, `requested_by`, `assignee`, `created_at` and `last_status_changed_at`, serialized through a new `OverviewRequestResource`. The last field is the time of the request's latest status record. For a resolved request it is the resolution time, and for a stale request it shows how long the request has waited. Tags, the description and `available_actions` are left out, because the lists link to the detail page. |
| Duration arithmetic | PostgreSQL and SQLite compute the difference between two timestamps differently. One support class, `DurationExpression`, returns the right expression for the connection driver, following the pattern of `CheckConstraint`. Month counting itself uses only range comparisons with bound timestamps, so it needs no date functions and works the same on both databases. |
| New index | A composite index on `service_request_status_histories` over `to_status`, `service_request_id` and `created_at` serves the latest resolution of every resolved request. Every other figure is served by the indexes created in Phase 5. |
| Caching | No server cache. The figures are computed on each request with a fixed number of aggregate queries. A cache would show stale numbers right after a workflow change, which the highlights must not do. |
| Rate limit | The overview shares the per user `api` limiter. Section 5.5 of the main plan names no separate limit for it. |
| Refreshing | The page loads the overview once on mount and offers a Refresh button, with the time the figures were produced. There is no automatic polling. |
| Chart accessibility | Each chart is an SVG with `role="img"` and an accessible name. Each also renders a visually hidden table with the same numbers, so screen reader users get the exact figures. Each bar and point has an SVG `title` element, which browsers show as a native tooltip. |
| Chart colours | Charts use the existing colour tokens through Tailwind `fill-*` and `stroke-*` classes. A frontend only `ChartTone` enum names the tones a series can use. No new colour token is needed. |

## 3. Current State of the Code

The following was found in the repository before Phase 7 starts. Phases 1 to 6 are complete and committed. The working copy holds uncommitted Phase 6 follow up changes to [change-status-form.tsx](../../../frontend/components/service-requests/change-status-form.tsx), [service-request-workflow-section.tsx](../../../frontend/components/service-requests/service-request-workflow-section.tsx), [select.tsx](../../../frontend/components/shared/select.tsx), [service-request-workflow-messages.ts](../../../frontend/constants/service-request-workflow-messages.ts) and the [Phase 6 plan](phase-6-implementation-plan.md). They make the status select start on the current status. Commit or discard them before starting Phase 7, so the Phase 7 commits stay focused.

### 3.1 Backend

- [routes/api.php](../../../backend/routes/api.php) holds the auth, session, service request and user management routes. Every route added from Phase 3 onward sits inside the `EnsurePasswordChanged` group, which sits inside the `auth:sanctum` and `api` throttle group.
- [bootstrap/app.php](../../../backend/bootstrap/app.php) sets the middleware priority so `EnsurePasswordChanged` and `Authorize` run before `SubstituteBindings`. The overview route has no route parameter, so binding plays no part.
- [GateAbility.php](../../../backend/app/Enums/GateAbility.php) holds `ManageUsers`, `ViewAllServiceRequests` and `HandleServiceRequests`. [AppServiceProvider.php](../../../backend/app/Providers/AppServiceProvider.php) defines all three in `configureAuthorization()`, and the two staff Gates use `UserRole::staff()`.
- [ServiceRequestStatus.php](../../../backend/app/Enums/ServiceRequestStatus.php) holds the four statuses and the transition rules. [ServiceRequestTag.php](../../../backend/app/Enums/ServiceRequestTag.php) holds the four tags. Both use `ProvidesValues`.
- [ServiceRequest.php](../../../backend/app/Models/ServiceRequest.php) has `requester()`, `tags()`, `statusHistories()` and `assigneeHistories()` (both ordered newest first), `currentAssigneeHistory()` as `hasOne(...)->latestOfMany()` and `currentAssignee()`, which needs the relation eager loaded under strict mode.
- [ServiceRequestService.php](../../../backend/app/Services/ServiceRequests/ServiceRequestService.php) has `listAssignableUsers()`, which returns every technician and admin with `id` and `name`, ordered by name and then by id. The workload reuses it.
- [ServiceRequestWorkflowService.php](../../../backend/app/Services/ServiceRequests/ServiceRequestWorkflowService.php) writes every status and assignee change. Tests can build realistic history through it while the clock is moved.
- The [service requests migration](../../../backend/database/migrations/2026_11_20_000005_create_service_requests_table.php) indexes `status`, `created_at` and `['requested_by_user_id', 'created_at']`. The [request tag migration](../../../backend/database/migrations/2026_11_20_000006_create_service_request_tag_table.php) indexes `tag_id`. The [status history migration](../../../backend/database/migrations/2026_11_20_000007_create_service_request_status_histories_table.php) indexes `['service_request_id', 'created_at']` and `changed_by_user_id`. The [assignee history migration](../../../backend/database/migrations/2026_11_20_000008_create_service_request_assignee_histories_table.php) indexes `['service_request_id', 'created_at']` and `assigned_to_user_id`. No index serves "the latest `RESOLVED` record of each request".
- [CheckConstraint.php](../../../backend/app/Support/Database/CheckConstraint.php) shows the pattern for SQL that depends on the connection driver.
- [SessionIdentifier.php](../../../backend/app/Services/Sessions/SessionIdentifier.php) shows the `#[Config(...)]` container attribute, which lets a class receive a configuration value without calling `config()` itself.
- [config/app.php](../../../backend/config/app.php) sets `timezone` to `UTC`, so Eloquent writes and reads every timestamp in UTC.
- [DevelopmentSeeder.php](../../../backend/database/seeders/DevelopmentSeeder.php) creates 40 requests over the last twelve months and moves 16 to resolved, 8 to in progress and 8 to assigned through the workflow service, with 6 reassignments. That is enough data for every figure, so the seeder does not change.
- [InteractsWithServiceRequests.php](../../../backend/tests/Concerns/InteractsWithServiceRequests.php) has `createServiceRequestFor`, `serviceRequestFactoryFor` and `assignThroughWorkflow`. [ServiceRequestFactory.php](../../../backend/database/factories/ServiceRequestFactory.php) has `createdAt()`, which also dates the initial status record.
- [ProtectedRoutesTest.php](../../../backend/tests/Feature/Auth/ProtectedRoutesTest.php) checks `auth:sanctum` and `EnsurePasswordChanged` on every API route, so the new route is checked automatically. Its `STAFF_SERVICE_REQUEST_GATES` constant maps staff route names to their Gates.
- [tests/TestCase.php](../../../backend/tests/TestCase.php) runs every test on in memory SQLite. SQLite has no `EXTRACT(EPOCH FROM ...)`, so duration arithmetic must be written per driver.

### 3.2 Frontend

- [(staff)/overview/page.tsx](../../../frontend/app/(authenticated)/(workspace)/(staff)/overview/page.tsx) is a placeholder that renders `AuthMessage.OverviewTitle` and `AuthMessage.OverviewPlaceholder`. Its header comment says Phase 7 replaces it. It already sits in the `(staff)` group, so requesters are redirected by `RequireRole`.
- [constants/auth-messages.ts](../../../frontend/constants/auth-messages.ts) still holds `OverviewTitle` and `OverviewPlaceholder`, and its header comment mentions the placeholder.
- [constants/navigation.ts](../../../frontend/constants/navigation.ts) already holds the Overview item for `STAFF_ROLES`, and technicians and admins already land on `/overview` after signing in.
- [StatCard](../../../frontend/components/shared/stat-card.tsx), [ContentSection](../../../frontend/components/shared/content-section.tsx), [SectionLoader](../../../frontend/components/shared/section-loader.tsx), [LoadFailureAlert](../../../frontend/components/shared/load-failure-alert.tsx), [EmptyState](../../../frontend/components/shared/empty-state.tsx), [Button](../../../frontend/components/shared/button.tsx) and [ServiceRequestStatusBadge](../../../frontend/components/service-requests/service-request-status-badge.tsx) exist.
- [use-resource.ts](../../../frontend/hooks/shared/use-resource.ts) loads one record, keeps the loaded value while `reload()` runs and ignores late responses.
- [constants/enum-labels.ts](../../../frontend/constants/enum-labels.ts) holds the status and tag labels. [status-tone.ts](../../../frontend/lib/service-requests/status-tone.ts) maps statuses to badge tones. [format-date-time.ts](../../../frontend/lib/utilities/format-date-time.ts) formats ISO timestamps with `DISPLAY_LOCALE`.
- [globals.css](../../../frontend/app/globals.css) defines the colour tokens `primary`, `success`, `warning`, `danger`, `muted`, `border` and `border-subtle`, which Tailwind exposes as `fill-*` and `stroke-*` utilities.
- [content-security-policy.ts](../../../frontend/lib/security/content-security-policy.ts) allows inline `style` attributes but no inline `<style>` element without the nonce. The SVG charts in this plan need neither.
- The `components/overview`, `services/overview`, `hooks/overview` and `types/overview` folders hold only a `.gitkeep` file.

## 4. Request Flows

These flows describe the behaviour the steps below must produce. They are the reference for the tests.

### 4.1 Endpoint

| Method | Path | Route name | Guard | Success response |
| --- | --- | --- | --- | --- |
| GET | `/api/overview` | `overview.show` | `view-overview` Gate | `200` with every overview figure |

The route also requires `auth:sanctum`, the `api` limiter and `EnsurePasswordChanged`. A response looks like this, shortened to one item per list.

```json
{
  "data": {
    "generated_at": "2026-11-21T08:30:00Z",
    "time_zone": "UTC",
    "status_counts": [
      { "status": "OPEN", "count": 8 },
      { "status": "ASSIGNED", "count": 8 },
      { "status": "IN_PROGRESS", "count": 8 }
    ],
    "highlights": {
      "stale_after_days": 7,
      "oldest_open": [
        {
          "id": 33,
          "title": "Printer on the second floor shows a paper jam",
          "status": "OPEN",
          "requested_by": { "id": 7, "name": "Kasun Silva" },
          "assignee": null,
          "created_at": "2026-10-02T06:10:00Z",
          "last_status_changed_at": "2026-10-02T06:10:00Z"
        }
      ],
      "assigned_to_viewer": [],
      "stale": [],
      "recently_resolved": []
    },
    "monthly_trends": [
      { "month": "2025-12", "created": 3, "resolved": 2, "average_resolution_hours": 51.4 },
      { "month": "2026-01", "created": 4, "resolved": 0, "average_resolution_hours": null }
    ],
    "tag_counts": [
      { "tag": "HARDWARE", "count": 12 },
      { "tag": "SOFTWARE", "count": 10 },
      { "tag": "NETWORK", "count": 9 },
      { "tag": "OTHER", "count": 4 }
    ],
    "staff_workload": [
      { "user": { "id": 5, "name": "Ishara Fernando" }, "assigned": 2, "in_progress": 3 }
    ]
  }
}
```

`monthly_trends` always holds exactly twelve items, oldest first. `tag_counts` always holds every tag. `staff_workload` holds every technician and admin.

### 4.2 Loading the Overview

1. A technician or admin signs in and lands on `/overview`, or opens it from the sidebar.
2. The page calls `GET /api/overview`.
3. The `view-overview` Gate rejects requesters with `403 FORBIDDEN`. In practice a requester never gets here, because `RequireRole` redirects them first.
4. The backend reads the current time once and builds the twelve reporting months in the configured time zone.
5. It runs the aggregate queries for the counts, the monthly figures, the tag counts and the workload, then the four highlight queries, which eager load the requester and the current assignee.
6. It returns `200` with the response above.
7. The page shows the count boxes, then the highlights, then the charts, with the time the figures were produced.
8. Refresh calls the endpoint again. The current figures stay visible until the new ones arrive.

### 4.3 How Each Figure Is Computed

All boundaries are bound as query parameters in the application time zone, which is how Eloquent writes timestamps, so the comparisons match the stored values on both databases.

| Figure | Query |
| --- | --- |
| Status counts | `COUNT(*)` grouped by `status` on `service_requests`, limited to the three counted statuses. Served by the `status` index. |
| Created per month | One query on `service_requests` limited to the reporting window, selecting one `SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END)` per month. Served by the `created_at` index. |
| Resolved per month and average resolution time | A subquery selects `MAX(created_at)` as `resolved_at` per request from the status history where `to_status` is `RESOLVED`, grouped by request. It is joined to `service_requests` where `status` is `RESOLVED` and `resolved_at` falls in the window. Per month, the outer query selects one conditional `SUM` for the count and one conditional `AVG` of the seconds between the request's `created_at` and `resolved_at`. Served by the new status history index. |
| Tag counts | `COUNT(*)` grouped by tag name over `service_request_tag` joined to `tags` and to `service_requests` limited to the window. Served by the `tag_id` and `created_at` indexes. |
| Workload | A subquery selects `MAX(id)` per request from the assignee history, the current assignee rule. It is joined to the assignee history and to `service_requests` where `status` is `ASSIGNED` or `IN_PROGRESS`, and grouped by `assigned_to_user_id` and `status`. The result is merged with `listAssignableUsers()`, so staff users with no requests appear with zeros. |
| Last status change | A correlated subquery selecting `MAX(created_at)` from the status history of the request. Served by the `['service_request_id', 'created_at']` index. It orders and filters the stale list and fills `last_status_changed_at` on every highlight item. |

The number of queries is fixed and does not grow with the number of requests or users.

## 5. Backend Steps

All commands run from the `backend` folder. Every new file starts with its two to three line header comment and contains no other comments. Every touched file keeps its header comment accurate.

### Step B1. Add the confirmed figure definitions to the main plan

In [docs/plan/implementation-plan.md](../implementation-plan.md), make these changes so the main plan stays the single source of truth.

1. In section 3.12, after the graphs bullet, add: "A request counts as resolved once, in the month of its latest move to RESOLVED, and only while its status is RESOLVED. Its resolution time runs from its creation to that move. Months follow a configurable reporting time zone, which defaults to UTC."
2. In section 3.12, change the highlights bullet so that "the requests with no status change beyond a configurable number of days" reads "the unresolved requests with no status change beyond a configurable number of days".
3. In section 4, add to the **service_request_status_histories** bullet: "It is also indexed on to status, request and creation time, which serves the resolution figures of the overview."

### Step B2. Add the Gate ability and register the Gate

1. In [GateAbility.php](../../../backend/app/Enums/GateAbility.php), add `case ViewOverview = 'view-overview';`.
2. In `AppServiceProvider::configureAuthorization()`, add `Gate::define(GateAbility::ViewOverview->value, fn (User $user): bool => in_array($user->role, UserRole::staff(), true))`.

### Step B3. Add the overview configuration

1. Create **`config/overview.php`**. Following Laravel convention, `env()` is called only in config files.

   | Key | Environment variable | Default |
   | --- | --- | --- |
   | `time_zone` | `OVERVIEW_TIME_ZONE` | `UTC` |
   | `stale_after_days` | `OVERVIEW_STALE_AFTER_DAYS` | `7`, cast to `int` |

2. Add these placeholders to `backend/.env.example` and the same keys to the local `.env`.

   ```dotenv
   OVERVIEW_TIME_ZONE=UTC
   OVERVIEW_STALE_AFTER_DAYS=7
   ```

### Step B4. Add the resolution index

Create a migration with `php artisan make:migration add_overview_index_to_service_request_status_histories_table`. It is reversible.

- `up()` adds an index on `['to_status', 'service_request_id', 'created_at']` named `status_histories_status_request_created_index`, held in a class constant. The name is set explicitly because Laravel's generated name would be longer than PostgreSQL's 63 byte limit, as the Phase 5 plan explains.
- `down()` drops the index by the same name.

**Check.** `php artisan migrate` and `php artisan migrate:rollback --step=1` both succeed against the local PostgreSQL database, and `\d service_request_status_histories` in `psql` shows the index.

### Step B5. Create the duration expression

**`app/Support/Database/DurationExpression.php`** is a final class with one static method, `secondsBetween(string $startColumn, string $endColumn): string`. It returns the SQL fragment for the number of seconds from the start column to the end column, with both columns wrapped through the connection's query grammar.

| Driver | Fragment |
| --- | --- |
| `pgsql` | `EXTRACT(EPOCH FROM (end - start))` |
| `sqlite` | `((julianday(end) - julianday(start)) * 86400)` |

Any other driver throws a `LogicException` whose message is a class constant. The driver names and the number of seconds in a day are class constants. The SQLite branch exists so the test suite can prove the averages, and the header comment says so. Column names are never taken from input, so the fragment holds no user data.

### Step B6. Create the reporting calendar

**`app/Data/Overview/ReportingMonth.php`** is a final readonly class with `string $key`, `CarbonImmutable $startsAt` and `CarbonImmutable $endsAt`. `$key` is the `YYYY-MM` string in the reporting time zone. Both times are in the application time zone, ready to be bound, and `$endsAt` is exclusive.

**`app/Services/Overview/ReportingCalendar.php`** is a final class.

- Its constructor receives the reporting time zone through `#[Config('overview.time_zone')]` and the application time zone through `#[Config('app.timezone')]`, as `SessionIdentifier` receives the application key.
- A public constant `MONTH_COUNT = 12` and a private constant for the key format, `Y-m`.
- `timeZone(): string` returns the reporting time zone.
- `months(CarbonImmutable $now): array` returns twelve `ReportingMonth` objects, oldest first. It converts `$now` to the reporting time zone, takes the start of the current month, and goes back eleven months. Each month's start and end are computed in the reporting time zone and then converted to the application time zone.

An unknown time zone name makes Carbon throw on the first overview request, which shows up as a server error and in the log. The tests in Step B13 cover a valid non UTC zone.

### Step B7. Create the data transfer objects

Create final readonly classes in `app/Data/Overview`.

| Class | Properties |
| --- | --- |
| `StatusCount` | `ServiceRequestStatus $status`, `int $count` |
| `MonthlyTrend` | `string $month`, `int $created`, `int $resolved`, `?float $averageResolutionSeconds` |
| `TagCount` | `ServiceRequestTag $tag`, `int $count` |
| `StaffWorkload` | `User $user`, `int $assigned`, `int $inProgress` |
| `OverviewHighlights` | `int $staleAfterDays`, and the four lists `Collection $oldestOpen`, `Collection $assignedToViewer`, `Collection $stale`, `Collection $recentlyResolved` of `ServiceRequest` models |
| `Overview` | `CarbonImmutable $generatedAt`, `string $timeZone`, `array $statusCounts`, `OverviewHighlights $highlights`, `array $monthlyTrends`, `array $tagCounts`, `array $staffWorkload` |

### Step B8. Create the statistics queries

**`app/Services/Overview/ServiceRequestStatistics.php`** is a final class that holds every aggregate query of section 4.3 except the highlights. It receives `ServiceRequestService` through its constructor for `listAssignableUsers()`. Table names, column names and select aliases are class constants. Every boundary is bound as a parameter, never interpolated.

| Method | Responsibility |
| --- | --- |
| `statusCounts(): array` | Returns one `StatusCount` per counted status, in enum order, with `0` for a missing status. The counted statuses are a private constant holding `Open`, `Assigned` and `InProgress`. |
| `monthlyTrends(array $months): array` | Runs the created query and the resolved query from section 4.3 and returns one `MonthlyTrend` per month, in the order given. Sums are cast with `(int)`, because an empty window returns `null`. The average is cast to `float`, or stays `null` when no request was resolved in that month. |
| `tagCounts(ReportingMonth $firstMonth, ReportingMonth $lastMonth): array` | Returns one `TagCount` per `ServiceRequestTag` case, in enum order, with `0` for an unused tag. |
| `staffWorkload(): array` | Runs the workload query and returns one `StaffWorkload` per staff user from `listAssignableUsers()`, in that order, with zeros for users with no current requests. |

A private method builds the list of conditional `SUM` or `AVG` select expressions for the twelve months, with their bindings and aliases such as `month_0`, so the month logic is written once. The resolved query uses `DurationExpression::secondsBetween()` for the average. The latest assignee subquery is built once in a private method, and its header comment states that it follows the `latestOfMany()` rule of the highest id.

### Step B9. Create the highlights repository

**`app/Services/Overview/OverviewHighlightsRepository.php`** is a final class. It receives the stale threshold through `#[Config('overview.stale_after_days')]`. A public constant `HIGHLIGHT_LIMIT = 5`.

Every method returns a `Collection` of at most `HIGHLIGHT_LIMIT` `ServiceRequest` models, built from one private base query. The base query eager loads `requester` and `currentAssigneeHistory.assignedTo`, and adds `last_status_changed_at` through `selectSub` with the correlated subquery from section 4.3. The subquery is written explicitly and not through `withMax`, because the `statusHistories` relation carries an `ORDER BY`, which PostgreSQL rejects inside an aggregate subquery without a `GROUP BY`. The model casts `last_status_changed_at` with `withCasts` to `immutable_datetime`.

| Method | Rule and order |
| --- | --- |
| `oldestOpen(): Collection` | Status `OPEN`, ordered by `created_at` and then by `id`, both ascending. |
| `assignedTo(User $viewer): Collection` | Status `ASSIGNED` or `IN_PROGRESS`, with `whereHas('currentAssigneeHistory', ...)` matching the viewer, ordered by `created_at` and then by `id`, both ascending. |
| `stale(CarbonImmutable $now): Collection` | Status `OPEN`, `ASSIGNED` or `IN_PROGRESS`, with the last status change earlier than `$now` minus the threshold in days, ordered by the last status change and then by `id`, both ascending. |
| `recentlyResolved(): Collection` | Status `RESOLVED`, ordered by the last status change and then by `id`, both descending. While a request is resolved, its latest status record is its latest move to `RESOLVED`, because a reassignment writes no status record. |
| `staleAfterDays(): int` | Returns the threshold, so the response can carry it. |

### Step B10. Create the overview service

**`app/Services/Overview/OverviewService.php`** is a final class. It receives `ReportingCalendar`, `ServiceRequestStatistics` and `OverviewHighlightsRepository` through its constructor.

`build(User $viewer): Overview`

1. Reads the current time once with `CarbonImmutable::now()`, so every figure uses the same moment.
2. Builds the twelve months through the calendar.
3. Collects the status counts, the monthly trends, the tag counts for the first and last month, the workload and the four highlight lists.
4. Returns an `Overview` with the time, the reporting time zone and every figure.

The service only reads, so it opens no transaction. The figures may come from slightly different moments if a workflow change commits between two queries. That is acceptable for an overview and is noted in section 9.

### Step B11. Create the resources and the controller

Create these in `app/Http/Resources`. Each one whitelists its fields.

| Resource | Fields |
| --- | --- |
| `OverviewRequestResource` | `id`, `title`, `status` as the enum value, `requested_by` through `UserReferenceResource`, `assignee` through `UserReferenceResource` or `null`, `created_at` and `last_status_changed_at`, both through `toIso8601ZuluString()` |
| `StatusCountResource` | `status` as the enum value and `count` |
| `MonthlyTrendResource` | `month`, `created`, `resolved` and `average_resolution_hours`, which is the seconds divided by the seconds in an hour and rounded to one decimal, or `null`. The rounding precision and the seconds per hour are class constants. |
| `TagCountResource` | `tag` as the enum value and `count` |
| `StaffWorkloadResource` | `user` through `UserReferenceResource`, `assigned` and `in_progress` |
| `OverviewResource` | `generated_at` in ISO 8601 UTC, `time_zone`, `status_counts`, `highlights` with `stale_after_days` and the four lists through `OverviewRequestResource::collection`, `monthly_trends`, `tag_counts` and `staff_workload`, each through its resource collection |

No resource exposes an email, a role, `requested_by_user_id` or any other internal column.

**`app/Http/Controllers/Overview/ShowOverviewController.php`** is a single action invokable controller. It calls `OverviewService::build($request->user())` and returns `new OverviewResource(...)`.

### Step B12. Register the route

Add the route inside the existing `EnsurePasswordChanged` group in [routes/api.php](../../../backend/routes/api.php), before the service request routes.

```php
Route::get('overview', ShowOverviewController::class)
    ->middleware(Authorize::using(GateAbility::ViewOverview->value))
    ->name('overview.show');
```

No new rate limiter is needed.

### Step B13. Write the Phase 7 tests

All feature tests use `RefreshDatabase`, `InteractsWithFrontendSession`, `InteractsWithServiceRequests` and `SeedsServiceRequestTags`, following the Phase 6 tests.

**`tests/Concerns/BuildsWorkflowHistory.php`** is a new trait for tests that need history at chosen times. Each helper moves the clock with `travelTo()` and calls the real `ServiceRequestWorkflowService`, so every record follows the workflow rules.

- `createServiceRequestAt(CarbonImmutable $time, ServiceRequestTag ...$tags): ServiceRequest` creates a request through the factory with `createdAt($time)`.
- `assignAt(ServiceRequest $serviceRequest, User $assignee, CarbonImmutable $time): void`
- `reassignAt(ServiceRequest $serviceRequest, User $from, User $to, CarbonImmutable $time): void`
- `changeStatusAt(ServiceRequest $serviceRequest, User $actor, ServiceRequestStatus $status, CarbonImmutable $time): void`
- `resolveAt(ServiceRequest $serviceRequest, User $assignee, CarbonImmutable $time): void` moves an assigned request to `RESOLVED`.

Each test fixes the current time with `travelTo()` at its start, so month boundaries are deterministic.

**`tests/Unit/Authorization/ViewOverviewGateTest.php`**

- The `view-overview` Gate allows an admin and a technician and denies a requester.

**`tests/Unit/Services/Overview/ReportingCalendarTest.php`** builds the calendar directly with its two time zones.

- It returns twelve months, oldest first, ending with the current month, with keys such as `2025-12` to `2026-11`.
- Each month ends exactly where the next begins, and the last month ends at the start of next month.
- The year rolls over correctly when the current month is January.
- With `Asia/Colombo` as the reporting time zone, November starts at `2026-10-31T18:30:00Z`, and a current time of `2026-11-30T19:00:00Z` already belongs to December.

**`tests/Feature/Overview/OverviewAccessTest.php`**

- A guest receives `401 UNAUTHENTICATED`.
- A technician and an admin with a pending password change receive `403 PASSWORD_CHANGE_REQUIRED`.
- A requester receives `403 FORBIDDEN`.
- A technician and an admin receive `200`.

**`tests/Feature/Overview/OverviewResponseTest.php`**

- The response has exactly `generated_at`, `time_zone`, `status_counts`, `highlights`, `monthly_trends`, `tag_counts` and `staff_workload` under `data`.
- `highlights` has exactly `stale_after_days` and the four lists. Each highlight item has exactly the seven fields from Step B11, and the timestamps are ISO 8601 UTC strings.
- On an empty database, the three counts are `0`, the four lists are empty, `monthly_trends` holds twelve items with `0`, `0` and `null`, and `tag_counts` holds all four tags with `0`.
- The response body never contains an email, `requested_by_user_id` or `assigned_to_user_id`.
- The number of database queries is the same with one request per highlight list as with fifteen requests per list.

**`tests/Feature/Overview/OverviewStatusCountsTest.php`**

- The counts match the number of requests in `OPEN`, `ASSIGNED` and `IN_PROGRESS`, in that order, and resolved requests are not counted anywhere.

**`tests/Feature/Overview/OverviewHighlightsTest.php`**

- "Oldest open" holds only open requests, oldest first, and at most `HIGHLIGHT_LIMIT` items.
- "Assigned to viewer" holds only the viewer's `ASSIGNED` and `IN_PROGRESS` requests. It leaves out requests assigned to someone else, resolved requests and a request the viewer reassigned away. Two staff users each see only their own requests.
- "Stale" holds an unresolved request whose last status change is older than the threshold, and leaves out one that changed status within the threshold. A reassignment does not remove a request from the list. A resolved request is never listed. The order is longest waiting first.
- Changing `overview.stale_after_days` in the test changes the result and the returned `stale_after_days`.
- "Recently resolved" lists resolved requests, most recently resolved first, and leaves out a request that was resolved and then reopened.
- `last_status_changed_at` equals the time of the request's latest status record.

**`tests/Feature/Overview/OverviewTrendsTest.php`**

- `monthly_trends` holds twelve months in order, ending with the current month.
- `created` counts requests in their creation month. A request created just before the first month and one created in the current month are counted correctly.
- A request resolved in one month counts as resolved in that month. A request resolved in an earlier month, reopened and resolved again in a later month counts only in the later month. A request resolved and then reopened, and still open, is not counted.
- Two requests resolved in the same month after 24 and 48 hours give `average_resolution_hours` of `36.0`, and a reopened request's time runs from its creation to its latest resolution. A month with no resolution has `null`.
- With `overview.time_zone` set to `Asia/Colombo`, a request created at `2026-10-31T19:00:00Z` counts in `2026-11`, and the response returns that time zone.
- A request with two tags counts under both tags, a request created before the window counts under neither, and unused tags are returned with `0`.

**`tests/Feature/Overview/OverviewWorkloadTest.php`**

- Every technician and admin is listed, ordered by name and then by id, including one with no requests and one with a pending password change. No requester is listed.
- `assigned` and `in_progress` match the requests currently assigned to each user in those statuses. Resolved requests are not counted.
- After a reassignment, the request counts only for the new assignee.

**`tests/Feature/Auth/ProtectedRoutesTest.php`**

- Rename `STAFF_SERVICE_REQUEST_GATES` to `STAFF_ROUTE_GATES`, add `'overview.show' => GateAbility::ViewOverview`, and rename the test to `test_every_staff_route_requires_its_gate`.

**Regression.** Every Phase 1 to Phase 6 test passes unchanged, apart from the rename above.

### Step B14. Format and verify

1. Run `vendor/bin/pint`, then `composer lint`.
2. Run `composer test` and confirm every test passes with none skipped.
3. Run `php artisan migrate`, then `php artisan db:seed --class=DevelopmentSeeder` if the development database has no demo data.
4. With `php artisan serve` running and the browser signed in as a technician, open `/api/overview` through the frontend and compare the counts with `SELECT status, COUNT(*) FROM service_requests GROUP BY status` in `psql`.
5. In `psql`, run `EXPLAIN` on the resolved per month query and confirm it uses `status_histories_status_request_created_index`.
6. Confirm that `curl -i http://localhost:8000/api/overview` returns `401` with the error shape.

## 6. Frontend Steps

All commands run from the `frontend` folder. Before writing Next.js specific code, read the matching guide in `node_modules/next/dist/docs/`, as [AGENTS.md](../../../frontend/AGENTS.md) requires. The guides that matter for this phase are `01-app/03-api-reference/03-file-conventions/page.md`, `01-app/03-api-reference/04-functions/generate-metadata.md` for the static `metadata` export, and `01-app/02-guides/content-security-policy.md`, to confirm that inline SVG with attributes and classes needs no CSP change.

The steps are split in three parts. Steps F1 to F5 add the shared pieces. Steps F6 to F8 build the charts and the overview components. Steps F9 to F11 replace the page and verify.

### Step F1. Add the endpoint

In [constants/api-endpoints.ts](../../../frontend/constants/api-endpoints.ts), add `Overview = "/api/overview"` to `ApiEndpoint`. No new error code is needed in this phase.

### Step F2. Add the overview types

Create these in `types/overview/`. Each file mirrors the wire format in snake_case.

| File | Type |
| --- | --- |
| `status-count.ts` | `StatusCount` with `status: ServiceRequestStatus` and `count: number` |
| `overview-request.ts` | `OverviewRequest` with `id: number`, `title: string`, `status: ServiceRequestStatus`, `requested_by: UserReference`, `assignee: UserReference \| null`, `created_at: string` and `last_status_changed_at: string` |
| `overview-highlights.ts` | `OverviewHighlights` with `stale_after_days: number` and `oldest_open`, `assigned_to_viewer`, `stale` and `recently_resolved`, each `OverviewRequest[]` |
| `monthly-trend.ts` | `MonthlyTrend` with `month: string`, `created: number`, `resolved: number` and `average_resolution_hours: number \| null` |
| `tag-count.ts` | `TagCount` with `tag: ServiceRequestTag` and `count: number` |
| `staff-workload.ts` | `StaffWorkload` with `user: UserReference`, `assigned: number` and `in_progress: number` |
| `overview.ts` | `Overview` with `generated_at: string`, `time_zone: string`, `status_counts: StatusCount[]`, `highlights: OverviewHighlights`, `monthly_trends: MonthlyTrend[]`, `tag_counts: TagCount[]` and `staff_workload: StaffWorkload[]` |

### Step F3. Add the enum and the constants

**Enum.** `enums/ui/chart-tone.ts` holds `ChartTone` with `Primary`, `Success`, `Warning` and `Muted`.

**Constants.**

1. Create **`constants/overview-messages.ts`** with `OverviewMessage`. It holds the page title and description, the refresh label, the load failure message, the count box descriptions, the highlights section title, the four highlight titles, descriptions and empty texts, the "created" and "last status change" item labels, the four chart titles and descriptions, the series labels ("Created", "Resolved", "Average resolution time", "Assigned", "In progress"), the axis labels ("Requests", "Hours"), the "no data in this period" text and the hidden table captions. It also exports these functions.

   | Function | Returns |
   | --- | --- |
   | `formatGeneratedAt(isoTimestamp)` | `Updated {date and time}`, using `formatDateTime` |
   | `formatStaleDescription(days)` | `Unresolved requests with no status change for more than {days} days.`, with `day` for 1 |
   | `formatTimeZoneNote(timeZone)` | `Months follow the {timeZone} time zone.` |
   | `formatAverageResolution(hours)` | `{hours} hours`, or the "no data" text for `null` |

2. Create **`constants/charts.ts`**. It holds the SVG view box width and height for each chart type, the plot padding, the number of axis ticks, the bar gap ratio and the point radius, as named constants. Charts never use raw numbers inline.

The placeholder overview page still uses `AuthMessage.OverviewTitle` and `AuthMessage.OverviewPlaceholder` until Step F9 replaces it, so removing them is part of Step F9.

### Step F4. Add the chart and month helpers

Each helper is a pure function with no React import.

- **`lib/charts/chart-scale.ts`** exports `niceMaximum(values: readonly number[]): number`, which rounds the largest value up to a readable axis maximum and returns `1` when every value is `0`. It also exports `axisTicks(maximum: number, tickCount: number): number[]` and `scaleValue(value: number, maximum: number, length: number): number`.
- **`lib/charts/chart-tone-classes.ts`** exports `CHART_TONE_FILL_CLASSES` and `CHART_TONE_STROKE_CLASSES`, each a `Record<ChartTone, string>` such as `fill-primary` and `stroke-primary`. The typed records make the build fail when a tone has no class.
- **`lib/overview/format-reporting-month.ts`** exports `formatReportingMonth(month: string): string`, which returns `Nov 2026`, and `formatReportingMonthShort(month: string): string`, which returns `Nov`. Both split the `YYYY-MM` key, build a date with `Date.UTC` and format it with `DISPLAY_LOCALE` and `timeZone: "UTC"`. The month therefore never shifts in browsers behind UTC. The month key pattern is a constant.

### Step F5. Build the service and the hook

- **`services/overview/overview-service.ts`** holds `OverviewService`, which extends `BaseApiService`, and exports one shared instance, `overviewService`. Its only method, `getOverview()`, calls `GET ApiEndpoint.Overview` and returns `Overview`, unwrapped from `ApiResourceResponse<Overview>`.
- **`hooks/overview/use-overview.ts`** returns `useResource` with a module level loader that calls `overviewService.getOverview`, following `use-service-request-assignees.ts`.

### Step F6. Build the shared chart components

Create these in `components/shared/charts/`. They are server compatible presentational components with no state, typed with explicit props interfaces and generic over the datum type, with no `any` or `unknown`. Each one renders a `figure` holding an `svg` with `role="img"`, `aria-labelledby` pointing at a `useId` title, a `viewBox` from `constants/charts.ts`, the class `h-auto w-full`, and a visually hidden data table with the caption passed in. Bars and points get an SVG `title` with the category, the series label and the value. All text comes from props.

| Component | Responsibility |
| --- | --- |
| `chart-series.ts` | Holds the shared `ChartSeries<Datum>` type, with `key: string`, `label: string`, `tone: ChartTone` and `value: (datum: Datum) => number \| null`, and the `ChartCategory<Datum>` accessor types for the category key and label. |
| `chart-legend.tsx` | Lists the series with a coloured square from `CHART_TONE_FILL_CLASSES` and the label. |
| `chart-data-table.tsx` | The visually hidden table, with one row per category and one column per series. `null` shows the "no data" text passed in. |
| `column-chart.tsx` | Vertical bars for one or more series per category, drawn side by side. It draws horizontal grid lines and value labels from `axisTicks`, and category labels under the bars. Props: `data`, `categoryKey`, `categoryLabel`, `series`, `title`, `valueAxisLabel` and `tableCaption`. |
| `line-chart.tsx` | One or more series as lines with a point at each value. A `null` value breaks the line, so missing months show as gaps and not as zeros. It takes the same props as the column chart. |
| `stacked-bar-chart.tsx` | Horizontal bars, one row per category, with the series stacked from left to right and the total at the end of the bar. The view box height grows with the number of rows. It takes the same props as the column chart. |

Category labels that do not fit are shortened by the caller, for example with `formatReportingMonthShort`. The full label stays in the hidden table and the SVG titles.

### Step F7. Build the overview components

Create these in `components/overview/`. All visible text comes from `OverviewMessage`, `ServiceRequestMessage`, `UiMessage` or the enum label records.

| Component | Responsibility |
| --- | --- |
| `status-count-cards.tsx` | Renders one `StatCard` per item of `status_counts`, with the label from `SERVICE_REQUEST_STATUS_LABELS` and the matching description, in a responsive grid of three columns from the `sm` breakpoint. |
| `highlight-list.tsx` | Renders an `OverviewRequest[]` as a list. Each item shows the title as a `next/link` to `serviceRequestDetailRoute(id)` with `formatRequestReference(id)`, the `ServiceRequestStatusBadge`, the requester, the assignee or the unassigned text, and one timestamp in a `time` element. A `timestamp` prop chooses between `created_at` and `last_status_changed_at` and its label. An empty list shows the empty text passed in. |
| `overview-highlights.tsx` | Renders the four lists in a grid of two columns from the `lg` breakpoint, each inside a `ContentSection`. Oldest open and assigned to viewer show the creation time, while stale and recently resolved show the last status change. The stale description comes from `formatStaleDescription(stale_after_days)`. |
| `request-volume-chart.tsx` | A `ContentSection` with a `ColumnChart` of `monthly_trends`, with the Created series in `ChartTone.Primary` and the Resolved series in `ChartTone.Success`, a legend and the time zone note. |
| `resolution-time-chart.tsx` | A `ContentSection` with a `LineChart` of `average_resolution_hours` per month in `ChartTone.Primary`, with the Hours axis label. When every month is `null` it shows the "no data in this period" text in place of the chart. |
| `tag-distribution-chart.tsx` | A `ContentSection` with a `ColumnChart` of `tag_counts`, with category labels from `SERVICE_REQUEST_TAG_LABELS` and one series in `ChartTone.Primary`. |
| `staff-workload-chart.tsx` | A `ContentSection` with a `StackedBarChart` of `staff_workload`, with the user's name as the category, Assigned in `ChartTone.Muted` and In progress in `ChartTone.Warning`, and a legend. When there are no staff users it shows an `EmptyState`. |
| `overview-panel.tsx` | A client component that calls `useOverview()`. It shows `SectionLoader` while loading and `LoadFailureAlert` with retry on failure. Otherwise it shows, in order, a toolbar with `formatGeneratedAt(generated_at)` and a secondary Refresh `Button` with its loading state, then `StatusCountCards`, then `OverviewHighlights`, then the request volume and resolution time charts side by side from the `xl` breakpoint, then the tag distribution and workload charts side by side from the same breakpoint. Refresh calls `reload()`, which keeps the current figures on screen until the new ones arrive. |

### Step F8. Check the chart rendering in both modes

1. Open the overview with `npm run dev` and confirm the browser console shows no CSP violation from the charts.
2. Repeat with `npm run build` and `npm run start`, where `style-src` uses the nonce, and confirm the same.
3. Confirm that each chart scales with the window width, that the labels stay readable at a narrow width, and that hovering a bar or point shows its native tooltip.

### Step F9. Replace the overview page

Replace the contents of [(staff)/overview/page.tsx](../../../frontend/app/(authenticated)/(workspace)/(staff)/overview/page.tsx) with a server component that exports metadata with `OverviewMessage.PageTitle` and renders `PageHeader` with the title and description, followed by `OverviewPanel`. Update its header comment so it describes the overview and no longer mentions Phase 7. The `(staff)` layout and its header comment do not change.

Then, in [constants/auth-messages.ts](../../../frontend/constants/auth-messages.ts), remove `OverviewTitle` and `OverviewPlaceholder`, and update the header comment so it covers only sign in, change password and sign out. Search for both names to confirm nothing else references them.

### Step F10. Remove placeholder files

Delete the `.gitkeep` file of each feature folder that now holds a real file.

- `components/overview/.gitkeep`
- `services/overview/.gitkeep`
- `hooks/overview/.gitkeep`
- `types/overview/.gitkeep`

After this step no feature folder holds a `.gitkeep` file.

### Step F11. Verify the frontend

1. Run `npm run lint` and confirm there are no errors or warnings.
2. Run `npm run typecheck` and confirm it passes.
3. Run `npm run build` and confirm it succeeds and lists `/overview`.
4. Search the new files for string literals in JSX and for raw numbers in SVG attributes, and confirm every visible text comes from a constant and every dimension from `constants/charts.ts`.
5. Search for `dangerouslySetInnerHTML`, `localStorage`, `sessionStorage` and `console.` in `components/overview`, `components/shared/charts`, `hooks/overview`, `lib/charts` and `lib/overview` and confirm there are no matches.

## 7. End to End Verification

Run both applications locally with `php artisan serve` and `npm run dev`, and repeat the checks with `npm run start` after a build. Run `php artisan db:seed --class=DevelopmentSeeder` first for demo data. Confirm each item in a browser with the developer tools open.

1. A technician signs in and lands on `/overview`. The page shows the three count boxes, the four highlight lists and the four charts.
2. The count boxes match `SELECT status, COUNT(*) FROM service_requests GROUP BY status` in `psql`.
3. "Oldest open" lists open requests oldest first. Clicking a title opens its detail page.
4. "Assigned to you" lists only the signed in technician's assigned and in progress requests. A second technician in another browser profile sees a different list.
5. "No recent status change" lists unresolved requests whose last status change is older than seven days, and its description names the threshold. Setting `OVERVIEW_STALE_AFTER_DAYS=30`, then running `php artisan config:clear` and refreshing, shortens the list and updates the description.
6. "Recently resolved" lists resolved requests, most recent first.
7. The request volume chart shows twelve months ending with the current month. The totals of created and resolved match `psql` counts for the same window.
8. The resolution time chart shows a gap for months with no resolution, and its values match a manual average for one month computed in `psql`.
9. The tag chart shows all four tags, and the workload chart lists every technician and admin with stacked assigned and in progress bars.
10. A technician resolves one of their requests in another tab and chooses Refresh on the overview. The in progress count drops by one, the request leaves "Assigned to you", appears first in "Recently resolved", and the resolved bar of the current month grows by one.
11. The technician reassigns a request to a colleague and refreshes. The workload moves from one person to the other, and the request stays in "No recent status change" if it was there.
12. Setting `OVERVIEW_TIME_ZONE=Asia/Colombo`, then clearing the config and refreshing, changes the time zone note, and requests created late on the last day of a month in UTC move to the next month.
13. A requester who types `/overview` is sent to My requests without the overview flashing. Calling `GET /api/overview` from the browser console as a requester returns `403 FORBIDDEN`.
14. A screen reader or the accessibility tree shows each chart's name and its hidden data table.
15. The network tab shows that the overview response holds no email, password hash or internal user id column.
16. The browser console shows no CSP violations, and nothing is written to local or session storage.

## 8. Done Criteria

Phase 7 is complete when every item below is true.

| Criterion from the main plan | How it is verified |
| --- | --- |
| The overview endpoint returns the count boxes, the highlights and the graph datasets using aggregate queries | `OverviewResponseTest`, `OverviewStatusCountsTest`, `OverviewHighlightsTest`, `OverviewTrendsTest` and `OverviewWorkloadTest` pass, the query count test shows a fixed number of queries, and Step B14.5 confirms the index is used. |
| Requesters cannot access the overview | `OverviewAccessTest`, `ViewOverviewGateTest` and the updated `ProtectedRoutesTest` pass, and item 13 of section 7 succeeds. |
| The figures are correct for seeded data | The figure tests pass on the data they build, and items 2 to 9 of section 7 match the development database. |
| The overview page shows stat cards, highlight lists and charts as reusable components | Steps F6 to F9 are complete, and the chart components in `components/shared/charts` take generic data and series. |
| Technicians and admins see accurate counts, highlights and trends on sign in | Items 1 and 10 to 12 of section 7 succeed. |
| The main plan reflects the confirmed figure definitions and the new index | Step B1 is complete. |
| Coding standards | Pint, ESLint and the TypeScript check pass, every new file has only its header comment, and no strings or dimensions are hardcoded outside enums and constants. |

## 9. Known Limitations and Handover to Later Phases

- **Read consistency.** The overview queries run outside a transaction, so a workflow change that commits between two queries can make one figure include it and another not. The next refresh is consistent again. Running the queries in a repeatable read transaction would remove this, and Phase 8 can revisit it if it ever matters.
- **No drill down.** The highlight lists show at most five requests and have no "view all" link, because the dashboard has no filters. A filtered dashboard would be a new feature outside the main plan.
- **Partial current month.** The current month is included before it ends, so its bars are lower than complete months.
- **Time zone validation.** An invalid `OVERVIEW_TIME_ZONE` produces a server error on the overview request and an entry in the log. Phase 8 checks the production configuration, including this value.
- **Duration expression drivers.** `DurationExpression` supports PostgreSQL and SQLite only. Another database would need a new branch.
- **Scaling.** The resolved and workload subqueries scan every resolved request and every assignee record. This is fine for the expected volume. A much larger data set would need materialized figures, which the main plan does not describe.
- **Frontend tests.** The frontend still has no test runner, so the overview and the chart components are verified manually through section 7.
- **Phase 8.** Phase 8 reviews the new route against section 5, confirms `OVERVIEW_TIME_ZONE` and `OVERVIEW_STALE_AFTER_DAYS` in the production configuration and adds both to the README.

## 10. Suggested Commit Sequence

Each commit leaves both applications working.

1. `docs: define the resolution figures and the overview index in the main plan` covers Step B1.
2. `feat(backend): add the overview gate, reporting calendar, statistics and overview endpoint` covers Steps B2 to B12.
3. `test(backend): add overview access, highlight, trend and workload tests` covers Steps B13 and B14.
4. `feat(frontend): add overview types, service, hook and reusable SVG charts` covers Steps F1 to F6.
5. `feat(frontend): add the overview page with count boxes, highlights and charts` covers Steps F7 to F11.
