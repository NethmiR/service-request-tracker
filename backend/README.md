# NNR Co. IT Service Request Tracker API

Laravel 13 REST API for the NNR Co. IT Service Request Tracker. It runs on PHP 8.3 or newer with PostgreSQL and authenticates the frontend with Laravel Sanctum session cookies.

The [root README](../README.md) is the full guide. It covers prerequisites, every environment variable, the error codes, the production checklist and the known limitations.

## Setup

```
composer install
cp .env.example .env
php artisan key:generate
```

Fill in the database values and the initial admin values in `.env`, then create the tables and seed the tags and the initial admin.

```
php artisan migrate
php artisan db:seed
```

For local demo data, set `DEVELOPMENT_USER_PASSWORD` in `.env` and run `php artisan db:seed --class=DevelopmentSeeder`.

## Running

```
php artisan serve
```

The API listens on http://localhost:8000.

## Tests and Formatting

| Command | What it does |
| --- | --- |
| `composer test` | Runs the full test suite on an in memory SQLite database |
| `composer lint` | Checks the code style with Pint |
| `vendor/bin/pint` | Fixes the code style |
