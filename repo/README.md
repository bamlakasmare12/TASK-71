# ResearchHub - Research Services Reservation & Catalog System

An offline-first, enterprise-grade platform for internal research support centers where staff and learners book limited resources such as consultation slots, equipment time, or editorial review services.

**Stack:** Laravel 11 (PHP 8.3) + Livewire 3 + Alpine.js + Tailwind CSS + PostgreSQL 16 + Redis 7

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Access & Credentials](#access--credentials)
3. [Architecture Overview](#architecture-overview)
4. [Feature Map](#feature-map)
5. [Module Reference](#module-reference)
6. [Docker Infrastructure](#docker-infrastructure)
7. [Queue & Scheduler](#queue--scheduler)
8. [Backup & Restore Procedure](#backup--restore-procedure)
9. [Testing](#testing)
10. [Security Model](#security-model)

---

## Quick Start

### Prerequisites

- Docker 24+ and Docker Compose v2
- No external internet required after initial image pull (fully offline)

### Docker Startup

```bash
# Clone and enter
cd repo

# Generate self-signed TLS certificate (first time only)
./docker/nginx/generate-cert.sh

# Build and start all containers
docker compose up --build -d

# Verify all 6 containers are healthy
docker compose ps

# Run initial database seed (first time only)
docker compose exec app php artisan db:seed

# Open the application (HTTPS with self-signed cert)
open https://localhost:4443
```

### Non-Docker Local Development

```bash
# Requirements: PHP 8.3, Composer 2, PostgreSQL 16, Redis 7, Node.js 20+
composer install
cp .env.example .env
php artisan key:generate

# Configure .env: DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_DATABASE=researchhub
# Enable pg_trgm: psql -d researchhub -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"

php artisan migrate --seed
php artisan serve --port=8000

# In separate terminals:
php artisan queue:work redis --sleep=3 --tries=3
php artisan schedule:work
```

---

## Access & Credentials

| URL | Description |
|-----|-------------|
| `https://localhost:4443` | Web UI (Docker / Nginx) |
| `http://localhost:8000` | Web UI (Local `artisan serve`) |

### Default Users

All passwords meet the 12-character complexity requirement (uppercase + lowercase + number + special character).

| Role | Username | Password | Capabilities |
|------|----------|----------|-------------|
| **Administrator** | `admin` | `Admin123!@#456` | Full system access, import/export, policy management, user management |
| **Content Editor** | `editor` | `Editor123!@#456` | Service CRUD, time slot management, scheduling |
| **Learner** | `learner` | `Learner123!@#456` | Browse catalog, book reservations, manage own bookings |

> Passwords expire every 90 days. On first login after expiry, users are redirected to the password change screen.

---

## Architecture Overview

```
+-----------------------------------------------------------------+
|                  Nginx (:4000 HTTP / :4443 HTTPS)                |
+-----------------------------------------------------------------+
|                   Laravel 11 + Livewire 3                       |
|  +----------+  +----------+  +----------+  +--------------+    |
|  |  Actions  |  | Services |  | Policies |  |  Middleware   |   |
|  | (Biz Lgc) |  | (Domain) |  |  (RBAC)  |  |  (Security)  |   |
|  +----------+  +----------+  +----------+  +--------------+    |
|  +----------------------------------------------------------+   |
|  |              Livewire 3 Components (UI)                   |  |
|  |  Auth | Catalog | Reservations | Admin Import/Export      |  |
|  +----------------------------------------------------------+   |
+------------------+------------------+---------------------------+
|  PostgreSQL 16   |     Redis 7      |  Queue Workers            |
|  (Data + Sessions|  (Cache + Queue) |  + Scheduler (Cron)       |
|   + Audit Logs)  |                  |                           |
+------------------+------------------+---------------------------+
```

**Design pattern:** Action-based architecture. Business logic lives in `app/Actions/*` classes, consumed by Livewire components. Services handle cross-cutting concerns (audit, CAPTCHA, password policy, reservation state machine). No logic in controllers.

---

## Feature Map

### Feature 1: Identity, Authentication & Security

| Capability | Implementation |
|---|---|
| Username/password login (offline) | `app/Actions/Auth/AttemptLogin.php` |
| Brute-force lockout (5 attempts / 15 min) | `AttemptLogin::execute()` increments `failed_login_attempts`, sets `locked_until` |
| Local GD-based CAPTCHA (no external deps) | `app/Services/CaptchaService.php` generates base64 PNG via GD |
| Password complexity (12 char, upper/lower/num/special) | `app/Services/PasswordPolicyService.php` |
| Last-5 password history prevention | `PasswordPolicyService::matchesPreviousPasswords()` + `password_histories` table |
| 90-day password rotation | `User::isPasswordExpired()` + `EnsurePasswordNotExpired` middleware |
| Database sessions with 20-min idle timeout | `app/Http/Middleware/SessionTimeout.php` + `SESSION_DRIVER=database` |
| Single-logout across all devices | `app/Actions/Auth/PerformLogout.php` purges all session rows for user |
| Step-up re-authentication (5-min elevation) | `app/Http/Middleware/StepUpAuth.php` |
| Login anomaly detection (device fingerprint) | `AttemptLogin::execute()` compares fingerprint, logs to audit |
| Encrypted sensitive fields (AES-256-CBC) | `User` model `encrypted` cast on `phone_encrypted`, `external_id_encrypted` |
| Immutable audit logs | `app/Models/AuditLog.php` + `app/Services/AuditService.php` |
| Role-based access (Learner/Editor/Admin) | `app/Http/Middleware/CheckRole.php` + `app/Enums/UserRole.php` |

**Livewire Components:**
- `app/Livewire/Auth/LoginForm.php` -- login with CAPTCHA integration
- `app/Livewire/Auth/ChangePasswordForm.php` -- password rotation
- `app/Livewire/Auth/StepUpVerification.php` -- critical action re-auth

**Views:**
- `resources/views/livewire/auth/login-form.blade.php`
- `resources/views/livewire/auth/change-password-form.blade.php`
- `resources/views/livewire/auth/step-up-verification.blade.php`
- `resources/views/components/layouts/guest.blade.php` (auth layout)
- `resources/views/components/layouts/app.blade.php` (app layout with session timeout modal)

### Feature 2: Dynamic Dictionaries & Service Catalog

| Capability | Implementation |
|---|---|
| Admin-configurable enums (service types, eligibility) | `app/Models/DataDictionary.php` + `data_dictionaries` table (JSONb) |
| JSON-based form validation rules | `app/Models/FormRule.php` + `app/Services/DataDictionaryService.php` |
| Service CRUD (editor/admin) | `app/Actions/Catalog/ManageService.php` |
| Tags (many-to-many) | `app/Models/Tag.php` + `service_tag` pivot |
| Target audience JSON arrays | `Service.target_audience` column with array cast |
| Free vs fee-based pricing | `Service.price` + `Service.is_free` (auto-computed) |
| Search by normalized title (debounced 300ms) | `CatalogList` with `wire:model.live.debounce.300ms` + `scopeSearch()` |
| Filter by category, tags, audience, price | `CatalogList` -- 6 filter properties with live binding |
| Sort by earliest/price/alphabetical | `CatalogList::render()` match expression |
| Favorite toggle with immediate feedback | `app/Actions/Catalog/ToggleFavorite.php` + heart icon |
| Recently viewed tracking (server-side) | `app/Actions/Catalog/RecordRecentView.php` + `user_recently_viewed` table |
| Loading skeletons | `catalog-list.blade.php` -- 6-card animated pulse grid |
| Empty state | Icon + "No services found" message |
| RBAC: editor/admin modify, learner read-only | `app/Policies/ServicePolicy.php` + `role:editor,admin` middleware |

**Livewire Components:**
- `app/Livewire/Catalog/CatalogList.php` -- searchable catalog with pagination
- `app/Livewire/Catalog/ServiceDetail.php` -- detail view with booking
- `app/Livewire/Catalog/ServiceManager.php` -- create/edit services

**Views:**
- `resources/views/livewire/catalog/catalog-list.blade.php`
- `resources/views/livewire/catalog/service-detail.blade.php`
- `resources/views/livewire/catalog/service-manager.blade.php`

### Feature 3: Reservation Lifecycle Engine

| Capability | Implementation |
|---|---|
| Time slot creation with capacity | `app/Models/TimeSlot.php` + `app/Livewire/Reservations/TimeSlotManager.php` |
| Booking with pessimistic DB locking | `ReservationService::createReservation()` -- `lockForUpdate()` inside transaction |
| Race condition prevention | `UNIQUE(user_id, time_slot_id)` constraint + `booked_count` checks |
| 30-minute pending expiry | `app/Jobs/ExpireUnconfirmedReservation.php` -- dispatched with 30-min delay |
| Confirm / Cancel / Reschedule | `ReservationService::confirm()`, `cancel()`, `reschedule()` |
| Free cancellation > 24h before start | `Reservation::isFreeCancellation()` |
| Late cancellation penalty ($25 / 50 pts) | `ReservationService::cancel()` -- creates `Penalty` record |
| Check-in window (-15min to +10min) | `TimeSlot::isCheckInWindowOpen()` |
| Late arrival = partial attendance | `ReservationService::checkIn()` -- checks `isLateArrival()` |
| Partial attendance cannot extend to next slot | `checkOut()` caps at `timeSlot.end_time` |
| No-show detection | `app/Console/Commands/ProcessNoShows.php` -- nightly cron |
| 2 breaches in 60 days = 7-day freeze | `ReservationService::evaluateFreezes()` |
| Countdown timer for pending reservations | Alpine.js timer in `reservation-dashboard.blade.php` |
| Disabled check-in button with tooltip | Tooltip: "Check-in opens 15 minutes before start time" |
| Audit log every state transition | `AuditService::log()` in every ReservationService method |
| Laravel Policies for authorization | `app/Policies/ReservationPolicy.php` |

**State Machine:**
```
pending --> confirmed --> checked_in --> completed
   |            |             |
expired     cancelled    partial_attendance --> completed
                |
             no_show (if check-in window closes without check-in)
```

**Livewire Components:**
- `app/Livewire/Reservations/ReservationDashboard.php` -- user's booking timeline
- `app/Livewire/Reservations/TimeSlotManager.php` -- editor slot management

**Views:**
- `resources/views/livewire/reservations/reservation-dashboard.blade.php`
- `resources/views/livewire/reservations/time-slot-manager.blade.php`

**Jobs & Commands:**
- `app/Jobs/ExpireUnconfirmedReservation.php` -- delayed queue job
- `app/Console/Commands/ProcessNoShows.php` -- `reservations:process-no-shows`
- Schedule: `routes/console.php` -- runs nightly at midnight

### Feature 4: Offline Data Import/Export

| Capability | Implementation |
|---|---|
| CSV/JSON file import for Services and Users | `app/Services/ImportExportService.php` |
| CSV/JSON export with incremental sync | `exportServices()` / `exportUsers()` with `since` filter |
| pg_trgm duplicate detection (trigram similarity) | `detectServiceDuplicates()` -- raw SQL `similarity()` with GIN index |
| Exact ID/username/email matching | `detectUserDuplicates()` |
| Conflict resolution: prefer newest / admin override / skip | `processRow()` + `resolveConflict()` |
| Field mapping UI (source to destination) | `app/Livewire/Admin/ImportManager.php` step 2 |
| Chunked queue processing (50 rows/job) | `app/Jobs/ProcessImportChunk.php` |
| Import progress bar with live polling | `wire:poll.2s` in processing step |
| Conflict review with side-by-side comparison | Step 4 in import view |
| Error log with row numbers | Stored in `import_batches.error_log` JSONb |
| Step-up auth required for export | `step-up` middleware on `/admin/export` route |
| Passwords NEVER exported | `exportUsers()` explicitly excludes password field |
| Sensitive fields only with explicit opt-in | `include_sensitive` checkbox decrypts phone/external IDs |
| MIME type validation | `mimes:csv,txt,json` + 10MB max |
| Import batch tracking | `import_batches` + `import_conflicts` tables |

**Livewire Components:**
- `app/Livewire/Admin/ImportManager.php` -- 5-step wizard (upload, map, process, review, done)
- `app/Livewire/Admin/ExportManager.php` -- export with format/incremental/sensitive options

**Views:**
- `resources/views/livewire/admin/import-manager.blade.php`
- `resources/views/livewire/admin/export-manager.blade.php`

---

## Module Reference

### Database Migrations (ordered)

| File | Tables Created |
|---|---|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` |
| `0001_01_01_000003_create_password_histories_table.php` | `password_histories` |
| `0001_01_01_000004_create_audit_logs_table.php` | `audit_logs` |
| `0001_01_01_000005_create_data_dictionaries_table.php` | `data_dictionaries`, `form_rules` |
| `0002_01_01_000001_create_services_table.php` | `services`, `tags`, `service_tag`, `user_favorites`, `user_recently_viewed` |
| `0003_01_01_000001_create_reservations_tables.php` | `time_slots`, `reservations`, `penalties` |
| `0004_01_01_000001_create_import_export_tables.php` | `import_batches`, `import_conflicts` + pg_trgm index |

### Models (14 total)

| Model | Table | Key Features |
|---|---|---|
| `User` | `users` | Encrypted casts, role enum, booking freeze, password expiry |
| `PasswordHistory` | `password_histories` | Last-5 password tracking |
| `AuditLog` | `audit_logs` | Immutable event log |
| `DataDictionary` | `data_dictionaries` | Dynamic enum management |
| `FormRule` | `form_rules` | JSON validation rule storage |
| `Service` | `services` | Catalog item with audience/pricing |
| `Tag` | `tags` | Auto-slug generation |
| `UserFavorite` | `user_favorites` | Favorite toggle |
| `UserRecentlyViewed` | `user_recently_viewed` | View tracking |
| `TimeSlot` | `time_slots` | Availability windows with capacity |
| `Reservation` | `reservations` | State machine with lifecycle |
| `Penalty` | `penalties` | Fee/points deductions |
| `ImportBatch` | `import_batches` | Import job tracking |
| `ImportConflict` | `import_conflicts` | Duplicate resolution queue |

### Services (6 total)

| Service | Purpose |
|---|---|
| `AuditService` | Centralized immutable audit logging |
| `CaptchaService` | GD-based offline CAPTCHA generation/verification |
| `PasswordPolicyService` | Complexity validation, history checking |
| `DataDictionaryService` | Dynamic enum/rule retrieval with caching |
| `ReservationService` | Full reservation state machine (create/confirm/cancel/check-in/no-show) |
| `ImportExportService` | CSV/JSON parsing, export, duplicate detection, row processing |

### Middleware (4 custom)

| Middleware | Alias | Purpose |
|---|---|---|
| `SessionTimeout` | (global web) | 20-minute idle logout |
| `CheckRole` | `role` | Role-based route protection |
| `StepUpAuth` | `step-up` | Password re-entry for critical actions |
| `EnsurePasswordNotExpired` | `password.not-expired` | Force password rotation |

---

## Docker Infrastructure

### Containers

| Container | Image | Port | Purpose |
|---|---|---|---|
| `researchhub-app` | PHP 8.3-FPM (custom) | 9000 (internal) | Laravel application |
| `researchhub-nginx` | nginx:1.25-alpine | **4000** (HTTP), **4443** (HTTPS) | Web server / reverse proxy with TLS |
| `researchhub-db` | postgres:16-alpine | 5432 | PostgreSQL database |
| `researchhub-redis` | redis:7-alpine | 6379 | Queue broker + cache |
| `researchhub-queue` | PHP 8.3-FPM (custom) | -- | Queue worker (`queue:work redis`) |
| `researchhub-scheduler` | PHP 8.3-FPM (custom) | -- | Laravel scheduler (60s loop) |

### Commands

```bash
# Start everything
docker compose up --build -d

# Check container health
docker compose ps

# Run migrations
docker compose exec app php artisan migrate --force

# Seed database
docker compose exec app php artisan db:seed

# Tail queue worker logs
docker compose logs -f queue

# Tail scheduler logs
docker compose logs -f scheduler

# Tail application logs
docker compose exec app tail -f storage/logs/laravel.log

# Enter app shell
docker compose exec app sh

# Stop everything
docker compose down

# Full reset (destroys data)
docker compose down -v
docker compose up --build -d
docker compose exec app php artisan migrate:fresh --seed
```

---

## Queue & Scheduler

### Queue Jobs

| Job | Trigger | Purpose |
|---|---|---|
| `ExpireUnconfirmedReservation` | Dispatched on booking with 30-min delay | Cancels unconfirmed reservation, releases slot |
| `ProcessImportChunk` | Dispatched during import (50 rows/chunk) | Processes import rows with duplicate detection |

### Scheduled Commands

| Command | Schedule | Purpose |
|---|---|---|
| `reservations:process-no-shows` | Daily at midnight | Marks no-shows, evaluates 2-breach freeze |

Monitor queue health:
```bash
# Docker
docker compose logs -f queue

# Local
php artisan queue:work redis --verbose
```

---

## Backup & Restore Procedure

### Daily Automated Backup (pg_dump)

Set up a cron job or use the scheduler container. Retains 30 days of snapshots.

```bash
#!/bin/bash
BACKUP_DIR="/backups/researchhub"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

mkdir -p "$BACKUP_DIR"

# Create compressed backup
docker compose exec -T db pg_dump \
  -U researchhub \
  -d researchhub \
  --format=custom \
  --compress=9 \
  > "$BACKUP_DIR/researchhub_${TIMESTAMP}.dump"

# Verify backup integrity
docker compose exec -T db pg_restore \
  --list "$BACKUP_DIR/researchhub_${TIMESTAMP}.dump" > /dev/null 2>&1

if [ $? -eq 0 ]; then
  echo "[$(date)] Backup successful: researchhub_${TIMESTAMP}.dump"
else
  echo "[$(date)] ERROR: Backup verification failed!" >&2
fi

# Prune backups older than retention period
find "$BACKUP_DIR" -name "*.dump" -mtime +${RETENTION_DAYS} -delete
echo "[$(date)] Pruned backups older than ${RETENTION_DAYS} days"
```

### Restore Procedure

Tested monthly as per policy.

```bash
# 1. Stop all application containers (keep DB running)
docker compose stop app queue scheduler nginx

# 2. Identify the backup to restore
ls -la /backups/researchhub/
# Example: researchhub_20260408_000000.dump

# 3. Drop and recreate the database
docker compose exec db psql -U researchhub -c \
  "DROP DATABASE IF EXISTS researchhub_restore;"
docker compose exec db psql -U researchhub -c \
  "CREATE DATABASE researchhub_restore OWNER researchhub;"
docker compose exec db psql -U researchhub -d researchhub_restore -c \
  "CREATE EXTENSION IF NOT EXISTS pg_trgm;"

# 4. Restore from backup
docker compose exec -T db pg_restore \
  -U researchhub \
  -d researchhub_restore \
  --no-owner \
  --no-privileges \
  < /backups/researchhub/researchhub_20260408_000000.dump

# 5. Verify row counts
docker compose exec db psql -U researchhub -d researchhub_restore -c \
  "SELECT 'users' as tbl, count(*) FROM users
   UNION ALL SELECT 'services', count(*) FROM services
   UNION ALL SELECT 'reservations', count(*) FROM reservations
   UNION ALL SELECT 'audit_logs', count(*) FROM audit_logs;"

# 6. Swap databases (atomic rename)
docker compose exec db psql -U researchhub -c "
  SELECT pg_terminate_backend(pid)
    FROM pg_stat_activity
    WHERE datname = 'researchhub' AND pid <> pg_backend_pid();
  ALTER DATABASE researchhub RENAME TO researchhub_old;
  ALTER DATABASE researchhub_restore RENAME TO researchhub;"

# 7. Restart all containers
docker compose start app queue scheduler nginx

# 8. Verify application health
curl -s https://localhost:4443/up | head -1

# 9. Drop the old database after confirming everything works
docker compose exec db psql -U researchhub -c \
  "DROP DATABASE IF EXISTS researchhub_old;"
```

### Point-in-Time Recovery Notes

- All state changes are recorded in the immutable `audit_logs` table
- Reservation state transitions are audited and can be replayed if needed
- Import batches track all changes with error logs for traceability

---

## Testing

### Run All Tests

```bash
# Via Docker
docker compose exec app php artisan test

# Local
php artisan test

# With coverage
php artisan test --coverage
```

### Test Suites

| Suite | File | Count | Covers |
|---|---|---|---|
| Login | `tests/Feature/Auth/LoginTest.php` | 9 | Login success/failure, lockout, CAPTCHA, password expiry |
| Password Policy | `tests/Feature/Auth/PasswordPolicyTest.php` | 8 | Complexity rules, history prevention, pruning |
| Session/Logout | `tests/Feature/Auth/SessionAndLogoutTest.php` | 4 | Logout, audit log, expired password redirect |
| Step-Up Auth | `tests/Feature/Auth/StepUpAuthTest.php` | 4 | Verify success/failure, audit logging |
| CAPTCHA Unit | `tests/Unit/CaptchaServiceTest.php` | 4 | Generation, verification, case-insensitivity, single-use |
| Catalog | `tests/Feature/Catalog/CatalogTest.php` | 8 | Search, filters, favorites, RBAC, recently viewed |
| Reservations | `tests/Feature/Reservation/ReservationTest.php` | 13 | Full lifecycle, penalties, freeze, check-in windows |
| Import/Export | `tests/Feature/ImportExport/ImportExportTest.php` | 12 | CSV/JSON parsing, export exclusions, duplicate detection |
| Adversarial Boundary | `tests/Unit/ReservationBoundaryTest.php` | 10 | Time-window spoofing, breach edge cases |
| CAPTCHA Adversarial | `tests/Unit/CaptchaAdversarialTest.php` | 5 | Payload tampering, replay, format validation |

---

## Security Model

### Authentication

- Strictly offline -- no external OAuth, SAML, or reCAPTCHA
- Sessions stored in PostgreSQL (`SESSION_DRIVER=database`)
- Session idle timeout: 20 minutes (with 2-minute JavaScript warning modal)
- Brute-force: 5 failures trigger 15-minute lockout; CAPTCHA required after 3 failures
- Step-up auth: 5-minute elevated token for critical actions

### Authorization (RBAC)

| Action | Learner | Editor | Admin |
|---|:---:|:---:|:---:|
| Browse catalog | Yes | Yes | Yes |
| Book reservations | Yes | -- | -- |
| Create/edit services | -- | Yes | Yes |
| Manage time slots | -- | Yes | Yes |
| Import data | -- | -- | Yes |
| Export data (step-up required) | -- | -- | Yes |
| Manage policies | -- | -- | Yes |

### Data Protection

- Sensitive fields (`phone`, `external_id`) encrypted at rest via Laravel `encrypted` cast (AES-256-CBC)
- Passwords hashed with bcrypt (12 rounds)
- Passwords NEVER exported
- Audit logs are append-only (no UPDATE/DELETE in application layer)
- TLS recommended for local network via Nginx configuration

### Reservation Policy Enforcement

| Rule | Value |
|---|---|
| Pending expiry | 30 minutes |
| Free cancellation window | > 24 hours before start |
| Late cancellation penalty | $25.00 fee or 50 points |
| No-show breach threshold | 2 within 60 days |
| Booking freeze duration | 7 days |
| Check-in window | -15 min to +10 min from start |
| Late arrival classification | Partial attendance (cannot extend) |

---

## Project Structure

```
repo/
|-- app/
|   |-- Actions/           # Business logic (Auth, Catalog)
|   |-- Console/Commands/  # Artisan commands (ProcessNoShows)
|   |-- Enums/             # UserRole, ReservationStatus, AuditAction
|   |-- Http/Middleware/    # CheckRole, StepUpAuth, SessionTimeout, EnsurePasswordNotExpired
|   |-- Jobs/              # ExpireUnconfirmedReservation, ProcessImportChunk
|   |-- Livewire/          # UI components (Auth, Catalog, Reservations, Admin)
|   |-- Models/            # 14 Eloquent models
|   |-- Policies/          # ReservationPolicy, ServicePolicy
|   |-- Providers/         # AppServiceProvider (policy registration)
|   +-- Services/          # Domain services (6 total)
|-- bootstrap/app.php      # Middleware registration
|-- database/
|   |-- migrations/        # 9 migration files
|   +-- seeders/           # DatabaseSeeder with sample data
|-- docker/
|   |-- nginx/default.conf
|   |-- php/Dockerfile, start.sh, php-local.ini
|   +-- postgres/init.sql  # pg_trgm + unaccent extensions
|-- docker-compose.yml     # 6 containers
|-- resources/views/       # 13 Blade templates
|-- routes/
|   |-- web.php            # All routes with middleware
|   +-- console.php        # Scheduler registration
+-- tests/                 # 10 test files, 70+ test cases
```
