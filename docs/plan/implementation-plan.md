# NNR Co. IT Service Request Tracker Implementation Plan

## 1. Introduction

### 1.1 Purpose

This document turns the initial plan into a clear requirements specification and a phased implementation plan for the IT Service Request Tracker of NNR Co. It defines what the system does, the rules it enforces, the security it must provide and the engineering standards every phase follows.

### 1.2 Scope

The system lets employees raise IT service requests and lets the IT Support division pick them up, assign them and resolve them. It covers authentication, session management, user management, service requests with full status and assignee history, and an analytics overview for the support team.

This is a fundamental system. Anything not described in this document is out of scope. The following are explicitly excluded from this version.

- Email integration. Temporary passwords are displayed once in the UI instead of being mailed.
- Public registration. Only admins create users.

### 1.3 Technology

- The backend is a Laravel 13 REST API running on PHP 8.3 with PostgreSQL.
- Authentication uses Laravel Sanctum in SPA cookie based mode.
- The frontend is Next.js 16 with TypeScript and the App Router.
- Axios handles HTTP requests and shared React contexts handle state.

## 2. User Roles and Access

The system has three roles named ADMIN, TECHNICIAN and REQUESTER. An admin holds every capability of the other two roles in addition to user management.

| Capability | Requester | Technician | Admin |
| --- | --- | --- | --- |
| Create service requests | Yes | Yes | Yes |
| View own request history | Yes | Yes | Yes |
| View all requests in the dashboard | No | Yes | Yes |
| Assign an open request to self | No | Yes | Yes |
| Change the status of a request assigned to self | No | Yes | Yes |
| Reassign a request currently assigned to self | No | Yes | Yes |
| View the overview page | No | Yes | Yes |
| Manage users and reset their passwords | No | No | Yes |
| Change own password | Yes | Yes | Yes |
| View and clear own sessions | Yes | Yes | Yes |
| View and clear any user's sessions | No | No | Yes |

After signing in, technicians and admins land on the overview page. Requesters go directly to their request history.

## 3. Functional Requirements

### 3.1 Authentication

- Users sign in with email and password and sign out to end the current session.
- A current user endpoint returns only safe identity fields, which are id, name, email, role and whether a password change is pending.
- There is no landing page. The root route redirects to sign in, or to the role based home page when a session already exists.

### 3.2 Mandatory Password Change

- A user who has never changed their password must change it before doing anything else. This applies to newly created users and to users whose password was reset by an admin.
- While the change is pending, the backend rejects every protected endpoint except changing the password, reading the current user and signing out. The rejection carries a dedicated error code so the frontend can route the user to the change password page.
- The pending state is enforced on the server. Hiding pages in the frontend is only a convenience.

### 3.3 Self Password Change

- A signed in user changes their password by providing the current password, a new password and its confirmation.
- The new password must satisfy the password policy and must differ from the current one.
- A successful change clears the pending flag, regenerates the current session and terminates all other sessions of that user.

### 3.4 Session Management

- Sessions are stored in the database and linked to the user, with IP address, user agent and last activity time.
- Users can view their active sessions, clear a single session or clear all sessions other than the current one.
- Admins can view and clear the sessions of any user.

### 3.5 Authentication Audit

Every authentication related event is stored in an append only audit table. The recorded events are successful sign in, failed sign in, throttled sign in, sign out, password changed by the user, password reset by an admin, user created by an admin, session cleared by its owner and session cleared by an admin.

Each record holds the event type, the acting user, the target user where relevant, the attempted email for failed sign ins, the IP address, the user agent and the timestamp. The application never updates or deletes audit records. A viewing interface for the audit trail is not part of this version.

### 3.6 User Management

User management is available to admins only.

- Admins see a paginated list of users with name, email, role, password change status and creation date.
- Admins create a user by entering a name, an email and a role. The system generates a secure temporary password, stores only its hash and returns the plain value only once in the creation response. The UI shows it in a dialog that cannot be reopened.
- Admins reset a user's password to a newly generated temporary password. It is shown once in the same way, the user is marked as having a pending password change and all of that user's sessions are terminated.
- Admins view and clear the sessions of any user from the user management area.

Displaying temporary passwords and admin password resets are temporary privileges that will be removed once email integration is added. Temporary password delivery is placed behind a single interface with a display once implementation so it can be replaced by email delivery without touching the rest of the system.

### 3.7 Service Requests

A service request holds the following information.

- Title
- Description
- Tags, chosen from the predefined set Hardware, Software, Network and Other. A request has one or more tags.
- Requested by, which is always the signed in user taken from the session and never from client input
- Creation timestamp
- Status, which is always OPEN on creation regardless of client input

The current assignee is not stored on the request. It is derived from the latest assignee history record, and a request with no assignee history is unassigned.

Any role can create a request. Every user has a My Requests page listing the requests they raised, newest first, with server side pagination. A request detail page shows all request fields together with the status history and the assignee history. Requesters can open only their own requests, while technicians and admins can open any request.

### 3.8 Request Dashboard

Technicians and admins have a dashboard listing all service requests, newest first, with server side pagination. Open requests offer an action to assign them to the current user.

### 3.9 Assignment

- A technician or admin can assign an OPEN request to themselves. The status automatically becomes ASSIGNED.
- Once a request has an assignee, only the user currently assigned can reassign it to another technician or admin, in any status. The new assignee must be different from the current one. Reassignment does not change the status.
- A remark is mandatory for every assignment and reassignment.
- Each assignment writes exactly one assignee history record and, when the status changes, exactly one status history record, all in the same database transaction.
- When two users try to take the same open request at the same time, exactly one succeeds and the other receives a conflict response.

### 3.10 Status Workflow

- The statuses are OPEN, ASSIGNED, IN_PROGRESS and RESOLVED. A new request is OPEN.
- A request leaves OPEN only through assignment and can never return to OPEN.
- Only the current assignee can change the status. Allowed target statuses are ASSIGNED, IN_PROGRESS and RESOLVED.
- The current assignee may move a RESOLVED request back to ASSIGNED or IN_PROGRESS.
- Setting a request to the status it already has is rejected.
- Every change is validated against the latest stored state of the request inside a locked transaction. If another user changed the request first and the change is no longer valid, it is rejected with a conflict response and nothing is written.
- Transition rules live in one central place on the backend. The frontend shows only actions the current user is allowed to take, but the backend remains the single source of truth.

### 3.11 History Records

Status history records hold the following.

- From status, which is empty only for the initial record written when the request is created
- To status
- Status changed by, which is empty only for the initial record written when the request is created
- Timestamp
- Remark, which is optional

Assignee history records hold the following.

- Assigned from, which is empty for the first assignment
- Assigned to
- Timestamp
- Remark, which is mandatory

History records are written only by the system as part of a successful change and are never edited or deleted. Creating a request writes one initial status record in the same transaction, with an empty from status, OPEN as the to status, an empty status changed by and no remark. The empty status changed by marks it as written by the system on creation. Every later status record has both a from status and a user. A failed change never creates history.

### 3.12 Overview Page

The overview page is visible to technicians and admins.

- Count boxes show the number of open, assigned and in progress requests.
- A highlights section shows the current system state to review, namely the oldest open requests waiting for assignment, the unresolved requests assigned to the viewing user, the requests with no status change beyond a configurable number of days and the most recently resolved requests.
- Graphs show long term trends, namely requests created against requests resolved per month over the last twelve months, the distribution of requests by tag, the average resolution time per month and the current workload of each technician and admin.
- All figures are computed with aggregate queries on indexed columns and delivered through a dedicated endpoint.

## 4. Data Model

All tables are created through reversible Laravel migrations. Enum columns are backed by PHP enums and protected by PostgreSQL check constraints. Users and requests are never deleted by the application, so foreign keys restrict deletion.

- **users** holds name, a unique lowercase email, the password hash, the role and the time the password was last changed by the user. An empty value in that column means a password change is pending.
- **sessions** is the Laravel database session table with user id, IP address, user agent, payload and last activity.
- **auth_audit_logs** holds the audit fields described in section 3.5 with indexes on event type, user and time.
- **tags** holds the four predefined tags, seeded from the tag enum.
- **service_requests** holds title, description, the requesting user, status and timestamps. It is indexed on status, requesting user and creation time.
- **service_request_tag** links requests and tags with a composite primary key.
- **service_request_status_histories** holds the request, from status, to status, the user who made the change, the optional remark and the creation time. The from status and the user are empty only for the initial record written when the request is created, and a check constraint keeps them either both empty or both filled. It is indexed on request and creation time.
- **service_request_assignee_histories** holds the request, the previous assignee, the new assignee, the mandatory remark and the creation time. It is indexed on request and creation time and on the new assignee. The latest record of each request identifies its current assignee.

Seeders create the predefined tags and an initial admin whose credentials come from environment variables. Factories and a development seeder create demo users and requests for local use only.

## 5. Security Requirements

### 5.1 Authentication Approach

Sanctum SPA cookie authentication is used because the browser never holds a readable token. Authentication lives in an HttpOnly session cookie, every state changing request is protected by CSRF, and sessions are stored on the server, which is what makes listing and clearing sessions possible. The frontend first requests the Sanctum CSRF cookie, then Axios sends the XSRF token header with credentials on every request. The frontend origin is listed in the Sanctum stateful domains and both applications share the same top level domain.

### 5.2 Sessions and Cookies

- Sessions use the database driver with encrypted payloads.
- Session cookies are HttpOnly, Secure in production and SameSite Lax.
- Idle session lifetime is configured through environment variables.
- The session id is regenerated on sign in and on password change. On sign out the session is invalidated and the CSRF token is regenerated.
- Raw session ids are never sent to the frontend. Session listings expose a non reversible identifier that the backend maps back to the session.

### 5.3 CORS

CORS allows only the configured frontend origin with credentials enabled. Allowed methods and headers are limited to what the API uses and CORS applies only to API and CSRF cookie paths.

### 5.4 Passwords

- Passwords are hashed with Laravel's hashing service and never stored, logged or returned in plain form.
- One central password policy requires a minimum length of twelve characters with mixed case, numbers and symbols, and checks against known breached passwords in production.
- Temporary passwords are generated with a cryptographically secure generator, satisfy the same policy and are returned only once.

### 5.5 Brute Force and Abuse Protection

- Sign in is rate limited per email and IP combination and per IP. Exceeding the limit returns 429 with a retry time and is recorded in the audit table.
- Password change, admin password reset and user creation have their own rate limits.
- All authenticated endpoints share a per user rate limit.

### 5.6 Account Enumeration Prevention

Failed sign ins return one generic message whether the email exists or not. Password verification runs against a dummy hash for unknown emails so response time does not reveal which accounts exist.

### 5.7 Authorization

- Every API route except sign in and the CSRF cookie requires Sanctum authentication.
- Role checks use Gates and record level checks use Policies. Controllers never contain inline role logic.
- The pending password change is enforced by middleware on all protected routes.
- Frontend role based hiding is only a convenience. The backend enforces every rule.

### 5.8 HTTP Semantics

Status codes are applied consistently and documented.

- 401 for unauthenticated requests
- 403 for authenticated users without the required role or with a pending password change
- 404 for unknown records and for requests a requester does not own, so their existence is not revealed
- 409 for prohibited transitions, no op transitions and changes that conflict with a concurrent update
- 419 for CSRF token mismatch
- 422 for invalid input
- 429 for rate limited requests

### 5.9 Input Validation and Data Exposure

- Every write endpoint uses a Form Request with explicit rules for type, length, enum membership and existence.
- Only validated data reaches services, and models use explicit fillable attributes.
- Ownership fields and the initial status are always set by the server.
- API Resources whitelist the fields returned. Password hashes, remember tokens and session payloads are never serialized.

### 5.10 Transport and Headers

- Production runs only over HTTPS with HSTS enabled.
- Both applications send X-Content-Type-Options, X-Frame-Options, Referrer-Policy and Permissions-Policy headers. The frontend also sends a Content Security Policy.
- Debug mode is disabled in production and server version details are not exposed.

### 5.11 Secrets and Configuration

- All secrets and environment specific values come from environment variables.
- Each application has an example environment file with placeholder values only, and real secrets are never committed.
- The database connection requires SSL.

### 5.12 Frontend Security

- Tokens and passwords are never stored in local storage or session storage.
- A temporary password lives only in component state and is cleared when its dialog closes.
- The Next.js proxy redirects signed out users to sign in, but it is a routing convenience and not a security boundary.
- Raw HTML rendering is never used for user supplied content.

### 5.13 Logging

Password fields are excluded from logs and from flashed input. Production error responses never include stack traces or internal details.

## 6. Engineering Standards

### 6.1 Backend

- Controllers stay thin and delegate to service classes that hold business logic. Data moves between layers through typed data transfer objects.
- Form Requests validate input, Policies authorize, API Resources shape output and Eloquent models cast columns to PHP enums.
- Domain errors are thrown as dedicated exception classes and mapped to HTTP responses in one central handler.
- Every operation that writes more than one row runs inside a database transaction. Assignment and status changes lock the request row and re-validate its current status and assignee before writing, which serializes concurrent changes and keeps data consistent.
- The current assignee is resolved from the latest assignee history record through a single latest of many relation that is eager loaded in listings.
- Queries eager load relations to avoid N+1 problems, use indexes and paginate on the server.
- Dependencies are injected through constructors and swappable parts sit behind interfaces, following SOLID principles.
- Migrations, seeders and factories follow Laravel conventions.
- Feature tests cover authentication, authorization, the workflow rules and concurrency behaviour.

### 6.2 Frontend

- App Router route groups separate the public sign in area from the signed in sidebar layout. Server components are the default and client components are used only where interaction is needed.
- The folder structure is feature based with space for shared files. Components, services, hooks and types each have sub folders per feature, namely auth, users, service requests, sessions and overview, plus a shared folder for reusable parts.
- Base components such as button, input, select, table, dialog, badge, pagination, stat card and page header are built once and reused by every feature.
- One Axios instance is configured with credentials, the XSRF header and interceptors that convert every failure into a typed error object. The interceptors send users to sign in on 401, to change password on a pending password change, and refresh the CSRF cookie and retry once on 419.
- Each feature has a service class wrapping its endpoints so components never call Axios directly.
- Shared contexts provide the authenticated user and app wide notifications.
- Loading, empty, validation, authorization and failure states are handled for every page, with route level error and not found boundaries.

### 6.3 Shared Rules

- Type safety is strict on both sides. There are no any or unknown types in application code.
- No hardcoded strings. Roles, statuses, tags, audit events, error codes, routes, endpoints and UI messages come from enums or constants.
- Backend and frontend enums and response types match exactly.
- Each file starts with a two to three line comment describing its purpose. No other comments appear in the code.
- Naming follows each framework's conventions and uses full, meaningful words without unclear acronyms.
- All code and content text is plain and natural, without em dashes, emojis or unnecessary punctuation and line breaks.

### 6.4 Error Handling

The backend returns one JSON error shape for every failure, containing a readable message, an error code from the shared enum and field level errors for validation failures. The frontend handles errors only through the central interceptor and the typed error object, so error logic is defined once on each side without duplication.

## 7. Implementation Phases

Each phase leaves the system in a working, testable state and builds on the previous one.

### Phase 1. Foundation and Shared Infrastructure

**Goal** is to establish the conventions and plumbing every later phase relies on.

**Backend**
- Install and configure Sanctum for SPA cookie authentication, the database session driver, CORS and stateful domains.
- Create the shared enums for roles, statuses, tags, audit events and error codes.
- Build the central exception handler, the JSON error shape, the base domain exception and the security headers middleware.
- Configure environment files with placeholders and the central password policy.

**Frontend**
- Set up the feature based folder structure, strict TypeScript settings and matching enums and types.
- Build the Axios instance, the typed error object, the route and endpoint constants and the base UI components.
- Configure security headers and the Content Security Policy.

**Done when** both applications start, the API returns the standard error shape for unknown routes and the frontend renders base components.

### Phase 2. Authentication and Mandatory Password Change

**Goal** is secure sign in, sign out and the forced password change flow.

**Backend**
- Create the users, sessions and audit tables, the initial admin seeder and the user factory.
- Implement sign in, sign out, current user and self password change with rate limiting and enumeration protection.
- Add the pending password change middleware and record all authentication events in the audit table.
- Add feature tests for sign in, 401 rejection, throttling, the forced password change and session regeneration.

**Frontend**
- Build the sign in page and the root redirect.
- Build the auth context and the proxy based redirect for signed out users.
- Build the change password page and enforce the redirect while a change is pending.

**Done when** the seeded admin can sign in, is forced to change the password, then reaches a placeholder home page and can sign out.

### Phase 3. Application Layout and Session Management

**Goal** is the signed in shell and self service session control.

**Backend**
- Implement listing own sessions, clearing a single session and clearing all other sessions, with audit records.
- Add tests confirming users cannot see or clear another user's sessions.

**Frontend**
- Build the sidebar layout with navigation items driven by the user's role and role based home redirects.
- Build the account page with password change and the active sessions list.

**Done when** each role sees only its permitted navigation and users can manage their own sessions.

### Phase 4. User Management

**Goal** is admin control over accounts.

**Backend**
- Implement listing users, creating a user with a temporary password, resetting a password and viewing and clearing any user's sessions.
- Implement the temporary password delivery interface with the display once implementation.
- Add tests confirming only admins reach these endpoints and that reset terminates sessions and sets the pending flag.

**Frontend**
- Build the users list, the create user form, the one time temporary password dialog, the reset password action and the user sessions view.

**Done when** an admin can create users of each role, who can then sign in and complete the mandatory password change.

### Phase 5. Service Request Creation and History

**Goal** is raising and viewing requests.

**Backend**
- Create the tags, service requests, request tag and both history tables, and seed the tags.
- Implement request creation, the My Requests list and request detail with histories, guarded by policies.
- Add tests for creation, validation failures and requesters being unable to read others' requests.

**Frontend**
- Build the create request form with tag selection, the My Requests page with pagination and the request detail page with both history timelines.

**Done when** every role can raise requests and see their own history, and a requester cannot open anyone else's request.

### Phase 6. Assignment and Status Workflow

**Goal** is the full support workflow with integrity under concurrency.

**Backend**
- Implement the dashboard list, self assignment, reassignment and status change with row locking, state re-validation and history writes in one transaction.
- Centralize transition rules in the status enum and workflow service.
- Add tests for valid transitions, no op and prohibited transitions, conflicting concurrent changes, reassignment by a non assignee and concurrent self assignment.

**Frontend**
- Build the request dashboard, the assign to me action with a mandatory remark, the reassign dialog and the status change controls with an optional remark.
- On a conflict response, show a clear message and reload the latest request.

**Done when** requests move through the workflow correctly, every change is recorded and conflicting changes are rejected without corrupting data.

### Phase 7. Overview and Analytics

**Goal** is the technician and admin overview page.

**Backend**
- Implement the overview endpoint with the count boxes, highlights and graph datasets using aggregate queries.
- Add tests confirming requesters cannot access it and the figures are correct for seeded data.

**Frontend**
- Build the overview page with stat cards, highlight lists and charts as reusable components.

**Done when** technicians and admins see accurate counts, highlights and trends on sign in.

### Phase 8. Security Hardening and Release Readiness

**Goal** is to verify the system is ready for production.

- Review every route against the authorization rules and HTTP semantics in section 5.
- Verify cookie flags, CORS, CSRF, rate limits, security headers and production configuration.
- Check that no response exposes sensitive fields and that no secrets are committed.
- Confirm the code follows the standards in section 6, including file header comments, enum usage and naming.
- Complete the README with setup, migration, seeding and test commands for both applications.

**Done when** the full test suite passes and a fresh setup works from the documented commands alone.
