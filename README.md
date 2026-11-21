# NNR Co. IT Service Request Tracker

## Overview

The IT Service Request Tracker lets employees of NNR Co. raise IT service requests and lets the IT Support division pick them up, assign them and resolve them. Every status change and every assignment is recorded in a history that is never edited. Each request carries a priority chosen when it is raised, and both request lists can be filtered by status, assignee and priority and searched by any part of the title.

The system has three roles.

| Role | What it can do |
| --- | --- |
| Requester | Raise requests, view their own requests, manage their own password and sessions |
| Technician | Everything a requester can do, plus view all requests, take open requests, reassign and change the status of requests assigned to them, and view the overview page |
| Admin | Everything a technician can do, plus create users, reset passwords and view or clear the sessions of any user |

The repository holds two applications.

- **backend** is a Laravel 13 REST API on PHP 8.3 with PostgreSQL. It uses Laravel Sanctum in SPA cookie mode.
- **frontend** is a Next.js 16 application with TypeScript and the App Router.

Sanctum in SPA cookie mode was chosen over API tokens or JWT because the browser never holds a readable token. The session lives in an HttpOnly cookie, every state changing request is protected by CSRF, and sessions are stored on the server, which makes signing out, listing sessions and clearing sessions take effect immediately.

The full specification is in the [implementation plan](docs/plan/implementation-plan.md). Each phase has its own plan in [docs/plan/phases](docs/plan/phases).

## Prerequisites

- PHP 8.3 or newer with the `pdo_pgsql`, `pdo_sqlite`, `mbstring`, `openssl` and `intl` extensions. `pdo_sqlite` is needed only to run the backend tests.
- Composer 2.
- Node.js 20.9 or newer with npm.
- PostgreSQL. Development used PostgreSQL 17.

Check the PHP extensions with `php -m`.

## Repository Layout

| Folder | Contents |
| --- | --- |
| `backend` | The Laravel API, its migrations, seeders and tests |
| `frontend` | The Next.js application |
| `docs` | The implementation plan and the phase plans |

## Backend Setup

Run every command in this section from the `backend` folder.

1. Install the dependencies.

   ```
   composer install
   ```

2. Create the environment file.

   ```
   cp .env.example .env
   ```

3. Generate the application key.

   ```
   php artisan key:generate
   ```

4. Create an empty PostgreSQL database, then open `.env` and fill in these values.
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` for that database.
   - `DB_SSLMODE`. Keep `require` when the database server accepts SSL. A local PostgreSQL server installed with default settings has no SSL, so set `DB_SSLMODE=disable` for it on your own machine only.

5. Create the tables.

   ```
   php artisan migrate
   ```

   On a database that already holds service requests, this gives every existing request the Medium priority.

6. Seed the tags, the priorities and the test users.

   ```
   php artisan db:seed
   ```

`db:seed` creates the four tags (Hardware, Software, Network and Other), the four priorities (Low, Medium, High and Critical) and the test users below. Running it again changes nothing.

## Test Users and Demo Data

`php artisan db:seed` creates these test users.

| Email | Password | Name | Role |
| --- | --- | --- | --- |
| `tech1@test.com` | `Tech1@test.com` | Technician 1 | Technician |
| `admin1@test.com` | `Admin1@test.com` | Administrator 1 | Admin |
| `admin2@test.com` | `Admin2@test.com` | Administrator 2 | Admin |
| `req1@test.com` | `Req1@test.com` | Requester 1 | Requester |
| `tech2@test.com` | `Tech2@test.com` | Technician 2 | Technician |

The test user seeder skips any email that already exists and creates the users without a pending password change. The passwords are written in the seeder on purpose, so never run `db:seed` against a shared or production database.

To also fill the database with service requests that have moved through the workflow, run this from the `backend` folder.

```
php artisan db:seed --class=DevelopmentSeeder
```

It creates the tags, the priorities and the test users first, then forty requests with random priorities spread over the last twelve months. Each run adds another forty, so use `php artisan migrate:fresh --seed` followed by this command to start again from a clean database. The requests are raised by the requester and the admins and handled by the technicians and the admins.

## Frontend Setup

Run every command in this section from the `frontend` folder.

1. Install the exact locked dependencies.

   ```
   npm ci
   ```

2. Create the environment file.

   ```
   cp .env.example .env
   ```

The example values work for local development. `NEXT_PUBLIC_API_URL` is the address of the backend. `SESSION_COOKIE_NAME` must equal the backend `SESSION_COOKIE` value, because the frontend uses the presence of that cookie to send signed out visitors to the sign in page.

## Running Locally

Start the backend from the `backend` folder. It listens on port 8000.

```
php artisan serve
```

Start the frontend from the `frontend` folder in a second terminal. It listens on port 3000.

```
npm run dev
```

Open http://localhost:3000 and sign in with one of the users listed in [Test Users and Demo Data](#test-users-and-demo-data).

Both applications run on `localhost`, so the browser shares the session cookie between them and `SESSION_DOMAIN` stays empty. Open the frontend through `localhost` and not `127.0.0.1`, because `SANCTUM_STATEFUL_DOMAINS` and `FRONTEND_URL` name `localhost`.

## Tests and Checks

From the `backend` folder:

| Command | What it does |
| --- | --- |
| `composer test` | Clears the cached configuration and runs the full backend test suite |
| `composer lint` | Checks the code style with Pint without changing files |
| `vendor/bin/pint` | Fixes the code style |

From the `frontend` folder:

| Command | What it does |
| --- | --- |
| `npm run lint` | Runs ESLint |
| `npm run typecheck` | Generates the Next.js route types, then runs the TypeScript compiler without emitting files |
| `npm run build` | Builds the production bundle |

The backend tests run on an in memory SQLite database and the array cache, as set in `backend/phpunit.xml`. They never touch the database configured in `.env`. The PostgreSQL check constraints and the row locking of the workflow are therefore verified by hand against PostgreSQL, as the [Phase 6 plan](docs/plan/phases/phase-6-implementation-plan.md) describes. The frontend has no automated tests.

## Environment Variables

Every application reads its settings from its own `.env` file. Never commit a `.env` file. Only the two `.env.example` files, which hold placeholders, are committed.

### Backend

| Variable | Purpose | Local value | Production |
| --- | --- | --- | --- |
| `APP_NAME` | Application name | `"NNR Co Service Request Tracker"` | Same |
| `APP_ENV` | Environment name | `local` | `production` |
| `APP_KEY` | Encryption key for cookies and session payloads | Generated by `php artisan key:generate` | Generated once on the server and kept secret |
| `APP_DEBUG` | Detailed error pages for developers | `true` | `false` |
| `APP_URL` | Address of the API | `http://localhost:8000` | The `https` address of the API |
| `FRONTEND_URL` | The only origin CORS allows | `http://localhost:3000` | The `https` address of the frontend |
| `LOG_CHANNEL` | Log channel | `stack` | `stack` |
| `LOG_STACK` | Channels inside the stack | `single` | `daily` |
| `LOG_LEVEL` | Lowest level written to the log | `debug` | `warning` |
| `DB_CONNECTION` | Database driver | `pgsql` | `pgsql` |
| `DB_HOST` | Database host | Your database host | The production database host |
| `DB_PORT` | Database port | `5432` | The production port |
| `DB_DATABASE` | Database name | Your database name | The production database name |
| `DB_USERNAME` | Database user | Your database user | The production user |
| `DB_PASSWORD` | Database password | Your database password | The production password |
| `DB_SSLMODE` | SSL mode of the database connection | `require`, or `disable` for a local server without SSL | `require` |
| `SESSION_DRIVER` | Where sessions are stored | `database` | `database` |
| `SESSION_LIFETIME` | Idle session lifetime in minutes | `120` | As required |
| `SESSION_ENCRYPT` | Encrypts session payloads | `true` | `true` |
| `SESSION_COOKIE` | Name of the session cookie | `service_request_tracker_session` | Same value as the frontend `SESSION_COOKIE_NAME` |
| `SESSION_DOMAIN` | Domain of the session cookie | Empty | The shared parent domain, such as `.example.com` |
| `SESSION_SECURE_COOKIE` | Sends cookies only over HTTPS | `false` | `true` |
| `SESSION_SAME_SITE` | SameSite attribute of the cookies | `lax` | `lax` |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts that receive cookie based authentication | `localhost:3000` | The frontend host, such as `app.example.com` |
| `BCRYPT_ROUNDS` | Cost of password hashing | `12` | `12` or higher |
| `CACHE_STORE` | Store for rate limit counters | `database` | `database` |
| `QUEUE_CONNECTION` | Queue driver. The application dispatches no jobs | `sync` | `sync` |
| `SIGN_IN_MAX_FAILURES_PER_EMAIL_AND_IP` | Failed sign ins allowed per email and address | `5` | `5` |
| `SIGN_IN_MAX_FAILURES_PER_IP` | Failed sign ins allowed per address | `20` | `20` |
| `SIGN_IN_DECAY_SECONDS` | Window of the sign in limits in seconds | `60` | `60` |
| `PASSWORD_CHANGE_MAX_ATTEMPTS_PER_MINUTE` | Password change attempts per user | `5` | `5` |
| `USER_CREATION_MAX_ATTEMPTS_PER_MINUTE` | User creations per admin | `10` | `10` |
| `ADMIN_PASSWORD_RESET_MAX_ATTEMPTS_PER_MINUTE` | Password resets per admin | `10` | `10` |
| `API_MAX_REQUESTS_PER_MINUTE` | Requests per signed in user across the API | `120` | `120` |
| `OVERVIEW_TIME_ZONE` | Time zone that decides where reporting months begin | `UTC` | A valid time zone identifier, such as `Asia/Colombo` |
| `OVERVIEW_STALE_AFTER_DAYS` | Days without a status change before an unresolved request is highlighted | `7` | As required |
| `TRUSTED_PROXIES` | Comma separated proxy addresses or CIDR ranges allowed to report the client address | Empty, which trusts no proxy | The load balancer or reverse proxy addresses, when one is used |
| `WORKFLOW_LOCK_TIMEOUT_MILLISECONDS` | How long a workflow change waits for the request row lock before it is rejected | `3000` | `3000` |

The configuration files read a few more variables that have safe defaults and that production never needs to change, such as `SESSION_TABLE`, `SESSION_PATH` and `CACHE_PREFIX`. They are left out of `.env.example` on purpose.

### Frontend

| Variable | Purpose | Local value | Production |
| --- | --- | --- | --- |
| `NEXT_PUBLIC_API_URL` | Address of the backend. It is written into the bundle at build time | `http://localhost:8000` | The `https` address of the API, set before `npm run build` |
| `SESSION_COOKIE_NAME` | Name of the backend session cookie, read only on the server | `service_request_tracker_session` | Same value as the backend `SESSION_COOKIE` |

The frontend stops with a clear error when either variable is missing.

## HTTP Status Codes and Error Codes

Every failed request returns the same JSON shape.

```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_FAILED",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

`message` is a readable message, `code` is a value from the table below, and `errors` appears only for validation failures, with the messages of each field. Responses never include stack traces or internal details, even when debug mode is on. The same codes are defined in `backend/app/Enums/ErrorCode.php` and `frontend/enums/error-code.ts`, and a backend test keeps them identical.

| Code | HTTP status | When it is returned |
| --- | --- | --- |
| `BAD_REQUEST` | 400 | The request cannot be processed, for example a sign in from a client that is not a configured frontend origin and therefore has no session |
| `UNAUTHENTICATED` | 401 | Any protected endpoint without a valid session |
| `INVALID_CREDENTIALS` | 401 | Sign in with a wrong email or password. The message is the same whether the email exists or not |
| `FORBIDDEN` | 403 | The signed in user lacks the role for the endpoint, or is not the current assignee of the request they try to reassign or change |
| `PASSWORD_CHANGE_REQUIRED` | 403 | The user has a pending password change and calls any endpoint other than the current user, sign out and change password |
| `NOT_FOUND` | 404 | An unknown route, user, request or session, a request that a requester does not own, or a session that belongs to someone else |
| `METHOD_NOT_ALLOWED` | 405 | A route called with a method it does not support |
| `CONFLICT` | 409 | A general conflict with the current state of a record |
| `INVALID_STATUS_TRANSITION` | 409 | A status change the workflow does not allow, such as a move back to OPEN |
| `STATUS_UNCHANGED` | 409 | A status change to the status the request already has |
| `ASSIGNEE_UNCHANGED` | 409 | A reassignment to the user who is already assigned |
| `CONCURRENT_UPDATE` | 409 | Another user changed the request first, so the change is no longer valid, for example taking a request that is no longer open. Also returned when the change waits longer than the lock timeout |
| `CURRENT_SESSION_NOT_CLEARABLE` | 409 | An attempt to clear the session making the request. Sign out is used for that |
| `CSRF_TOKEN_MISMATCH` | 419 | A state changing request without a valid `X-XSRF-TOKEN` header |
| `VALIDATION_FAILED` | 422 | Invalid input. The `errors` field lists the problems |
| `TOO_MANY_REQUESTS` | 429 | A rate limit was exceeded. The `Retry-After` header gives the wait in seconds |
| `SERVER_ERROR` | 500 | An unexpected error |
| `SERVICE_UNAVAILABLE` | 503 | The API is in maintenance mode |

## Production Checklist

### Backend environment

- `APP_ENV=production` and `APP_DEBUG=false`.
- An `APP_KEY` generated on the server with `php artisan key:generate` and never shared.
- `APP_URL` and `FRONTEND_URL` with `https`.
- `DB_SSLMODE=require`.
- `SESSION_SECURE_COOKIE=true`.
- `SESSION_DOMAIN` set to the shared parent domain, such as `.example.com`.
- `SANCTUM_STATEFUL_DOMAINS` set to the frontend host, such as `app.example.com`.
- `CACHE_STORE=database`, so rate limits hold across several application servers.
- `TRUSTED_PROXIES` set to the proxy addresses when the API runs behind a load balancer or reverse proxy. Leave it empty when there is none.
- A valid `OVERVIEW_TIME_ZONE`. Check it with `php artisan tinker --execute="echo in_array(config('overview.time_zone'), DateTimeZone::listIdentifiers(), true) ? 'valid' : 'invalid';"`.
- `LOG_STACK=daily` and `LOG_LEVEL=warning`.

### Hosts

Both applications must run on hosts that share the same parent domain, such as `api.example.com` and `app.example.com`, so the browser sends the session cookie to the API.

### Deploy commands

Run these from the `backend` folder on every deploy.

```
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

On the first deploy only, run `php artisan db:seed --force` after the migration to create the tags.

Do not use `php artisan optimize`, because its view cache step fails in this API only backend, which has no views. `migrate:fresh`, `migrate:refresh`, `migrate:reset` and `db:wipe` are refused in production.

### Server settings

- HTTPS only, with HTTP redirected to HTTPS. The API sends `Strict-Transport-Security` in production.
- `expose_php = Off` in `php.ini`.
- The web server version hidden, for example `server_tokens off` in nginx, or `ServerTokens Prod` with `ServerSignature Off` in Apache.
- The web server document root set to `backend/public`.

### Frontend

Set the production `NEXT_PUBLIC_API_URL` and `SESSION_COOKIE_NAME`, then build and start the application behind HTTPS from the `frontend` folder.

```
npm ci
npm run build
npm run start
```

### Maintenance

The database cache removes an expired row only when the same key is read again. Run `php artisan cache:clear` from time to time to remove expired rows that are never read again. It also resets the current rate limit counters.

## Known Limitations

- Email integration is not included. Temporary passwords for new users and password resets are shown once in the user interface instead of being mailed.
- The frontend has no automated tests. Its behaviour is verified by hand.
- The overview figures are read outside a transaction, so a change made while the page loads can make one figure briefly disagree with another. A refresh shows a consistent state.
- Lists keep their page number in component state, so a page refresh returns to the first page.
- Authentication audit records are stored but have no viewing screen.
- The title search has no trigram index. It runs within the rows left by the other filters, which is enough for the expected volume. A `pg_trgm` index on `title` can be added later without changing the query.
- Requests created before priorities were added all have the Medium priority, because their real priority was never recorded.

## AI Use & Verification

The full statement is in the [AI usage declaration](docs/reports/ai-usage-declaration.docx).

**Tools used**

- Claude Code (desktop app, VS Code extension and CLI) for planning, design and implementation.
- ChatGPT (official website) for initial setup guides and quick knowledge lookups during development.

**Work assisted**

- The brainstorming, product research and initial plan were done manually.
- Claude assisted with the comprehensive phased plan, the separate plan for each phase, the design documents, the code and the tests. Issues found in testing were sent back to implementation until they were resolved.

**Verification**

- Every plan and design document was reviewed before the related implementation started.
- Every code file was reviewed and tested before it became part of the project. The backend test suite and the frontend lint and type checks are the automated gate. The frontend was also verified by hand.
