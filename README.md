# Dust to Done — Cleaning Service Booking Platform

A production-ready home-cleaning marketplace: customers book cleanings online, an admin
team runs operations, and cleaners manage their assigned jobs. Built as a **Laravel 13 REST
API** + **Next.js 15 frontend**, backed by **MySQL**.

> **Out of scope (by design):** online payments / payment gateways, promo codes & discounts,
> referral programs. Bookings carry a calculated price and a manual "mark as paid" toggle
> (cash / bank transfer on completion) — there is no payment processing.

---

## Architecture

```
cleaning project software/
├── backend/        Laravel 13 REST API (Sanctum token auth, MySQL, queues, scheduler)
├── frontend/       Next.js 15 App Router (TypeScript, Tailwind, shadcn/ui, TanStack Query)
├── docker-compose.yml   MySQL + Mailpit + phpMyAdmin + backend + frontend
└── README.md
```

- **API:** versioned under `/api/v1`, JSON only, consistent envelope
  `{ "data": …, "meta": {…}, "message": "…" }`. Token (Bearer) auth via Laravel Sanctum.
- **Roles:** `super_admin`, `admin`, `cleaner`, `customer` — a single `users` table with a
  `role` column; cleaners and customers each have a 1:1 profile table. Authorization is
  enforced on the API with role middleware and policies, not just in the UI.
- **Frontend auth:** the browser never sees the API token. The Next.js app stores it in an
  **httpOnly cookie** set by `/session/*` route handlers, and all API calls go through a
  same-origin **BFF proxy** (`/bff/[...path]`) that attaches the token server-side.
  `middleware.ts` guards `/admin`, `/cleaner` and `/account` by role.
- **Three experiences, one API:** public website + customer account, cleaner portal, admin panel.

---

## Quick start with Docker (recommended)

Requires Docker + Docker Compose.

```bash
docker compose up --build
```

This starts everything and seeds demo data automatically:

| Service      | URL                      |
| ------------ | ------------------------ |
| Frontend     | http://localhost:3000    |
| API          | http://localhost:8000    |
| Mailpit (email inbox) | http://localhost:8025 |
| phpMyAdmin   | http://localhost:8081    |
| MySQL        | localhost:3307 (root/root) |

Stop with `Ctrl+C`; reset data with `docker compose down -v`.

---

## Local development (without Docker)

### Prerequisites

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `zip`
- Composer 2
- Node.js 20+
- MySQL 8 (or MariaDB 10.6+)

### Backend

```bash
cd backend
cp .env.example .env           # adjust DB credentials if needed
composer install
php artisan key:generate
php artisan migrate --seed      # builds schema + realistic demo data
php artisan storage:link
php artisan serve               # http://localhost:8000
```

Process the queue (emails + in-app notifications) and the scheduler in separate terminals:

```bash
php artisan queue:work          # queued mail & notifications
php artisan schedule:work       # hourly reminders, daily unassigned-booking alerts
```

Use the `log` mail driver (default in `.env.example`) to write emails to
`storage/logs/laravel.log`, or point SMTP at [Mailpit](https://github.com/axllent/mailpit)
for a visual inbox in development.

### Frontend

```bash
cd frontend
cp .env.example .env.local      # BACKEND_URL defaults to http://localhost:8000
npm install
npm run dev                     # http://localhost:3000
```

---

## Seed accounts (development only)

All seeded accounts use the password **`password`**.

| Role        | Email                         |
| ----------- | ----------------------------- |
| Super admin | `superadmin@dusttodone.test`  |
| Admin       | `admin@dusttodone.test`       |
| Cleaner     | `cleaner@dusttodone.test`     |
| Customer    | `customer@dusttodone.test`    |

The seeder also creates 6 cleaners (mixed verification states), 5 customers with Dubai
addresses, 6 services, and ~40 bookings spread across the last 6 months and the next 2 weeks
with every status represented, plus published and unmoderated reviews. Dates are always
relative to the seed run date.

---

## Key environment variables

**Backend (`backend/.env`)**

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Base URL of the API (e.g. `http://localhost:8000`) |
| `FRONTEND_URL` | Frontend origin — used in emailed links (reset/verify/invite) |
| `CORS_ALLOWED_ORIGINS` | Comma-separated allowed origins for CORS |
| `DB_*` | MySQL connection |
| `SANCTUM_TOKEN_EXPIRATION` | Token lifetime in minutes (default 14 days) |
| `MAIL_*` | SMTP / log mail configuration |
| `QUEUE_CONNECTION` | `database` (default) |

**Frontend (`frontend/.env.local`)**

| Variable | Purpose |
| --- | --- |
| `BACKEND_URL` | Server-side base URL the BFF proxy forwards to |
| `BACKEND_API_PREFIX` | API prefix (default `/api/v1`) |
| `NEXT_PUBLIC_SITE_NAME` | Display name |

---

## Testing

Backend feature tests cover auth & roles, service/catalog & settings, customer addresses,
staff management, booking creation rules (lead time, business hours, accepting-bookings),
cleaner eligibility & overlap prevention, the status machine, cancellation/reschedule
cutoffs, and review moderation → rating recalculation.

```bash
cd backend
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`), so they never
touch your development data.

---

## Business rules (enforced in the API)

- **Price** = `service.price_per_hour × duration`, snapshotted on creation; admins may override.
- **Lead time & business hours**: customer bookings must respect `booking_lead_time_hours`
  and start/finish within business hours; admins can bypass lead time.
- **Not accepting bookings**: public/customer booking returns `503`; admins can still book.
- **Cleaner assignment** requires: verified + active + available, has the service as a skill,
  the slot is inside working hours and not in time off, and no overlapping booking.
- **Status machine**: `pending → confirmed → in_progress → completed`, with `cancelled`
  reachable from pending/confirmed; confirming requires an assigned cleaner; every transition
  is written to booking status history. Completed/cancelled are final (super admin may reopen).
- **Reviews** are once-per-completed-booking and start unpublished; publishing recalculates the
  cleaner's `rating_avg` / `rating_count`.
- Computed fields (`completed_jobs_count`, `rating_avg`, lifetime spend, revenue) are never
  directly editable.

---

## Deployment notes

- **API:** run behind Nginx + PHP-FPM (or `php artisan serve` for simple setups). Configure a
  real queue worker (`queue:work` via Supervisor) and a cron entry for the scheduler:
  `* * * * * php /path/to/backend/artisan schedule:run`. Set `APP_ENV=production`,
  `APP_DEBUG=false`, and cache config/routes.
- **Frontend:** `npm run build && npm run start`, or run under PM2 / a Node host. Set
  `BACKEND_URL` to the internal API address.
- Restrict `CORS_ALLOWED_ORIGINS` and `SANCTUM_STATEFUL_DOMAINS` to your production frontend.
