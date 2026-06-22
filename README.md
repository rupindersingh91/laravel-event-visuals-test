# Event Visuals — Coding Test

Two distinct browsing views on top of a seeded event dataset: an animated card grid (Visual 1) and a clustered map view (Visual 2), with attendee registration and email reminders.

---

## Requirements

- PHP 8.3
- Composer
- Node.js 20+ / npm
- SQLite (bundled with PHP on most systems)

---

## Setup

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

The default `.env` uses SQLite (`DB_CONNECTION=sqlite`). The database file lives at `database/database.sqlite`; Laravel creates it automatically on first migration.

### 3. Migrate and seed

The seeder defaults to **1,250,000 events** (~2.5 GB on disk). For a faster local run, cap it:

```bash
SEED_ROWS=2000 php artisan migrate:fresh --seed
```

Omit `SEED_ROWS` to seed the full dataset (takes a few minutes; first page load is ~3 s on a laptop).

### 4. Start the dev server

```bash
composer dev
```

This runs four processes concurrently via `concurrently`: the PHP dev server, a queue worker, Laravel Pail (log tail), and the Vite asset server.

| URL | Page |
|-----|------|
| `http://localhost:8000/events` | Standard list view |
| `http://localhost:8000/events-visual-1` | Animated card grid |
| `http://localhost:8000/events-visual-2` | Clustered map view |

---

## Emails

The mail driver is set to `log` — no SMTP required. Emails are written to the application log.

**View confirmation and reminder emails:**

```bash
tail -f storage/logs/laravel.log
```

Look for `Swift_Message` / `Symfony\Component\Mime\Email` blocks in the output. Each registration triggers an immediate confirmation; reminders are queued separately (see below).

The queue worker started by `composer dev` processes jobs automatically. To process the queue manually instead:

```bash
php artisan queue:work --tries=1
```

---

## Reminder emails

Two reminder windows are supported: **3 days before** and **24 hours before** an event. The command is idempotent — safe to run as often as needed:

```bash
php artisan events:send-reminders
```

This is also scheduled to run every 6 hours automatically (when a scheduler is running):

```bash
php artisan schedule:run
```

---

## Docker / Laravel Sail (optional)

A `docker-compose.yml` is included if you prefer a containerised environment. It mounts the project into `/var/www/html` and keeps the SQLite database on a named volume (`sqlite`) separate from the Windows/Mac host filesystem to avoid cross-OS file-locking issues.

```bash
docker compose up -d
docker compose exec app bash
# then run the setup steps above inside the container
```

---

## Design decisions

### Nearest-anchor reverse geocoding

Events carry only a `(latitude, longitude)` with no address. Rather than hitting a geocoding API (rate limits, cost, offline fragility), a set of 75 labelled city anchors is embedded in `App\Support\CityAnchors`. The nearest anchor is found via minimum squared Euclidean distance — cheap, deterministic, and accurate enough for city-level display on a global dataset. The same anchor list drives the city filter's bounding-box query.

### Deterministic local placeholder images

The spec requires locally-served images with no external URLs. Nine placeholder JPEG files live under `public/images/events/`. Each event derives its image set from `abs(crc32(type . id))` — a deterministic hash that assigns 2–3 images per event consistently across requests, without storing anything in the database or touching the payload JSON.

### `created_time` as the indexed date filter

The events table has a `created_time` Unix timestamp column already indexed. Using it as the date filter axis avoids adding a new column or parsing the payload JSON on every query. The filter inputs (date strings) are converted to UTC Unix ranges via `Carbon::parse(..., 'UTC')` before the `WHERE` clause, keeping the comparison fast and the index hot.

### UTC storage, client-local display

`created_time` and `starts_at_iso` are stored and transported in UTC. Timezone conversion happens entirely in the browser using `Intl.DateTimeFormat` with `resolvedOptions().timeZone` — the user's local time is applied without any server knowledge of where they are, and without shipping a timezone database to the client.

### Bounding-box location filter

When a city is selected, `CityAnchors::boundingBox()` returns a `±0.6°` latitude/longitude rectangle around that city's anchor. Two `BETWEEN` clauses on the already-indexed `(latitude, longitude)` composite index filter events to that city without a spatial extension or full-table scan.

### Reminder idempotency via `sent_at` columns

`event_attendees` carries two nullable timestamps: `reminder_3d_sent_at` and `reminder_24h_sent_at`. The `events:send-reminders` command checks each column before queuing a mail and stamps it immediately after. Running the command multiple times — or overlapping scheduler ticks — never sends a duplicate. No Redis locks or job deduplication middleware required.
