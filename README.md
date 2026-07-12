# Marketplace Catalog

Imports a product catalog from an external XML feed and exposes it through an authenticated web app.

- **backend/** — Symfony 6.4 API (PostgreSQL, Doctrine migrations)
- **frontend/** — Nuxt 4

## Run with Docker

```bash
docker compose up --build
```

- Frontend: http://localhost:3000
- Backend:  http://localhost:8000

One-time setup (in another terminal):

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console app:catalog:import resources/products.xml
```

## Login

Auth is passwordless (email + one-time code). Seeded user: **user@example.com**.
Email is mocked — the code is written to `backend/var/login_codes.log`:

```bash
docker compose exec backend cat var/login_codes.log
```

To login enter the seeded email on the login page, then the 6-digit code from login_codes.log.

## Catalog import

```bash
php bin/console app:catalog:import resources/products.xml
```

Parses the feed, inserts/updates categories & products, and deletes rows no longer
present. Source is configurable via `MARKETPLACE_FEED_URL` (a path or URL).

## Run without Docker

Backend (PHP 8.2+, Composer, PostgreSQL):

```bash
cd backend
composer install
# committed .env defaults to Docker host "db"; for a local run, create .env.local
# with DATABASE_URL pointing at your Postgres on host 127.0.0.1
php bin/console doctrine:migrations:migrate
php -S localhost:8000 -t public
```

Frontend (Node 22+):

```bash
cd frontend
npm install
npm run dev
```

## Environment

- Backend: `backend/.env` holds committed, non-secret defaults. Machine-specific
  values (e.g. a local `DATABASE_URL`) go in `backend/.env.local`, which is gitignored.
- Frontend: override the API base with `NUXT_PUBLIC_API_BASE`
  (defaults to `http://localhost:8000`).

## API

REST JSON under `/api`, protected with a Bearer token. Anonymous requests to
category/product endpoints return `401`. See `DECISIONS.md` for design notes.
