# Decisions

Some notes on the important choices made in this project, plus the
assumptions, trade-offs, and things I'd improve with more time.

## Stack

- **Symfony 6.4 (LTS)** for the backend. The task allows "Symfony 5 or newer"; 6.4
  runs on modern PHP 8.2, supports native PHP attributes (no extra annotation
  package), and is supported longer than 5.4.
- **Nuxt 4** frontend, **PostgreSQL 16** database, **Docker Compose** to run
  everything, **Doctrine Migrations** for the schema.
- **Two separate apps** (backend API + frontend SPA) rather than one, matching the task requirement. 
  Using a monorepo which is easier to review.

## Data model

### Categories are a tree
The feed gives a category as one string: `Top | Mid | Leaf`. Each level is stored
as its own `Category` row that points to its parent (a self-reference). This models
the real tree and handles any depth, not just three levels.

Each category also stores its **full path** (e.g. `Electronics | Computers | Laptops`)
in a unique column. This is the key that makes the importer fast and safe: a category
can be found (or created) with one indexed lookup, and duplicates are impossible.

### Products
Fields map directly to the feed. Two deliberate type choices:
- **Price is `DECIMAL`, not float** — money must not use floating point (rounding
  errors). Doctrine returns it as a string, which is correct.
- **SKU is unique** — it's the product's natural key and the match key for the import.
- **Title and stock are required (NOT NULL)**; **description is optional** because a
  feed item may omit it.

### Indexes
Unique on `sku`, `email`, and category `path`; plain indexes on `price` and `stock`
because the products page filters on them; the category foreign key is auto-indexed.

## Catalog import (console command)

- **Streaming XML with `XMLReader`.** The feed can hold 50k+ products, so it's read
  one product at a time instead of loading the whole file into memory.
- **Preloaded lookup maps.** Existing SKUs and category paths are loaded once into
  memory as `key -> id` maps, so we don't run a database query per row.
- **Batch writes.** Every 500 products the changes are flushed and the memory is
  cleared, keeping memory flat regardless of feed size.
- **Insert / update / delete.** New SKUs are inserted, existing ones updated, and
  anything no longer in the feed is deleted (products first, then empty categories,
  deepest first to respect the parent link). Re-running the same feed changes nothing
  (idempotent).
- **Configurable source.** The feed location comes from `MARKETPLACE_FEED_URL` and can
  be overridden by a command argument. `XMLReader` accepts both a URL and a local file
  path, so a local sample (`backend/resources/products.xml`) is used for testing since
  the real marketplace URL is a placeholder.

## Authentication (passwordless)

- **Email + one-time code.** The user requests a code, receives it, and logs in with
  email + code. No passwords are stored.
- **Codes are hashed, short-lived, single-use.** A 6-digit code is stored hashed
  (SHA-256), expires after 10 minutes, and is cleared once used.
- **Email is mocked.** Instead of sending a real email, the code is written to
  `backend/var/login_codes.log`. Swapping in a real mailer later is a one-file change.
- **Seeded user via migration.** One account (`user@example.com`) is created in a
  database migration, as required — not a fixture.

## API design

- **REST-style JSON under `/api`.** List endpoints for categories and products,
  paginated, plus the login endpoints.
- **Token authentication.** After verifying the code, the server issues a random
  opaque token stored in an `api_token` table. The frontend sends it as
  `Authorization: Bearer <token>`, validated by Symfony's built-in access-token
  firewall.
- **Anonymous users are blocked.** `access_control` requires a valid token for
  everything under `/api` except the login and health endpoints, so category and
  product data can't be read without logging in.
- **Filtering/pagination on the server.** Price min/max and hide-out-of-stock are
  applied in the database query (not in the frontend), and the same filters drive both
  the page of results and the total count.

## Frontend

- **`useApiFetch` vs `$fetch`.** Page data that needs auth (categories, products) uses
  a small `useApiFetch` wrapper that adds the token header and is SSR-friendly. Login
  actions use plain `$fetch` because they run on a button click and need no token.
- **Token in a cookie** + a global route guard that redirects to `/login` when there's
  no token.
- **CORS** is enabled (NelmioCorsBundle) so the frontend (`localhost:3000`) can call
  the backend (`localhost:8000`) in development.

## Environment / config

- **`.env` is committed** (Symfony convention: non-secret defaults); machine-specific
  values and secrets go in `.env.local`, which is gitignored. The root `.env` (Docker
  Postgres credentials) is ignored because Compose already has safe defaults.

## Assumptions

- "Number of products" per category counts products attached **directly** to that
  category (products attach to their leaf category), not the whole subtree.
- The marketplace feed URL is a placeholder, so the importer is tested against a local
  sample XML file.
- Login email delivery is mocked, as the assignment allows.

## Trade-offs

- **Path column for categories** keeps the importer simple and fast, at the cost of a
  denormalised string that must stay consistent — acceptable because only the importer
  writes it.
- **Run state on the importer service** (instance properties) keeps the code readable
  but makes the service single-use per run; fine for a CLI command.
- **Opaque token stored in plain form** is simple and revocable; hashing it at rest
  would be a hardening improvement.

## What I'd improve with more time

- Automated unit and integration tests for the import process and authentication flow.
- Hash the API token at rest, rate-limit the code-request endpoint, and add a cleanup
  for expired tokens.
- Category product counts across the whole subtree (if needed) via a closure table or
  materialised path, and `created_at`/`updated_at` timestamps for auditability.
- Product and category search functionality.
- A product details endpoint and corresponding frontend page to display the full product information.
- A category details endpoint and page with additional category information and related products.