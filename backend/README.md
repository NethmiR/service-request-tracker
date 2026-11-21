# NNR Co. IT Service Request Tracker API

Laravel 13 REST API for the NNR Co. IT Service Request Tracker. It runs on PHP 8.3 or newer with PostgreSQL and authenticates the frontend with Laravel Sanctum session cookies.

The [root README](../README.md) is the full guide. It covers prerequisites, every environment variable, the error codes, the production checklist and the known limitations.

## Setup

```
composer install
cp .env.example .env
php artisan key:generate
```

Fill in the database values in `.env`, then create the tables and seed the tags and the test users.

```
php artisan migrate
php artisan db:seed
```

For demo service requests as well, run `php artisan db:seed --class=DevelopmentSeeder`. Each run adds another forty requests.

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
