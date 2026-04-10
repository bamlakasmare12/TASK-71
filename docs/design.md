# ResearchHub System Design

This document analyzes the architecture/design guarantees you provided against what is currently implemented in the repository at `repo/`.

## 1. Executive View

The project is a Laravel 11 + Livewire 3 platform that already implements the core functional domains:

- offline/session-based identity and RBAC
- service catalog + dynamic dictionaries/rules
- reservation lifecycle engine with queue/scheduler jobs
- import/export with pg_trgm duplicate detection

The implementation is strong and test-backed, but there are important deltas from the target guarantees (notably CAPTCHA endpoint behavior, some security-flow details, and stack/version mismatches in Docker/runtime assumptions).

## 2. Actual Architecture (As Built)

## 2.1 Layering and Responsibilities

- **Presentation/UI:** Livewire components under `app/Livewire/*` with Blade views.
- **API layer:** controllers under `app/Http/Controllers/Api/*`, routed in `routes/api.php`.
- **Business logic:** mostly in Action/Service classes (`app/Actions/*`, `app/Services/*`).
- **Security gates:** middleware (`CheckRole`, `EnsurePasswordNotExpired`, `StepUpAuth`, `SessionTimeout`) and policies (`ReservationPolicy`, `ServicePolicy`).
- **Data layer:** Eloquent models + PostgreSQL migrations.
- **Background processing:** queue jobs (`ExpireUnconfirmedReservation`, `ProcessImportChunk`) + scheduled command (`reservations:process-no-shows`).

## 2.2 Runtime Topology (Docker)

`docker-compose.yml` defines:

- `app` (PHP-FPM)
- `nginx` (TLS termination + reverse proxy)
- `db` (PostgreSQL 16)
- `redis` (cache/queue backend)
- `queue` (`php artisan queue:work redis`)
- `scheduler` (`artisan schedule:run` loop)

Healthchecks and `depends_on` are configured for DB/Redis readiness.

## 3. Tech Stack vs Requested Stack

| Area | Requested | Implemented | Status |
|---|---|---|---|
| Backend framework | Laravel 11 | Laravel 11 (`^11.31`) | Implemented |
| PHP runtime | 8.3 | Composer allows `^8.2`; Dockerfile uses `php:8.4-fpm-alpine` | Partial / drift |
| Frontend | Livewire 3 + Alpine + Tailwind | Present and used | Implemented |
| Database | PostgreSQL 16 | PostgreSQL 16 container + pgsql driver | Implemented |
| Queue management | PostgreSQL queue management | Queue driver set to Redis in `.env.example`; jobs tables still exist | Partial |
| Dockerized | 100% docker-compose | Implemented with 6 services | Implemented |

## 4. Domain-by-Domain Implementation Analysis

## 4.1 Identity, Security, and Offline Auth

### Implemented

- Database sessions (`SESSION_DRIVER=database`, sessions table in migration).
- 20-minute idle timeout (`SessionTimeout` middleware).
- Role model (`learner`, `editor`, `admin`) and route-level role middleware.
- Password policy:
  - min length 12
  - uppercase/lowercase/number/special requirements
  - 90-day expiry enforcement
  - last-5 password reuse prevention (`password_histories`)
- Account lockout after 5 failed attempts for 15 minutes.
- Audit logging for login failures, lockouts, password changes, step-up events, reservation transitions.
- Encrypted model casts on sensitive user fields (`phone_encrypted`, `external_id_encrypted`).
- Immutable audit log protections:
  - model-level update/delete prevention
  - PostgreSQL triggers preventing UPDATE/DELETE

### Partial / Divergent

- **CAPTCHA endpoint guarantee mismatch:**
  - requested: custom endpoint returning base64 image + hashed payload
  - implemented: `CaptchaService` only, consumed by Livewire login; no API endpoint and no hashed payload contract
- **CAPTCHA threshold mismatch:**
  - requested: CAPTCHA requirement after 5 failed attempts
  - implemented: `User::requiresCaptcha()` triggers at 3 failed attempts
- **Step-up route style mismatch:**
  - requested: signed middleware route
  - implemented: session timestamp (`step_up_verified_at`) + middleware check
- **Single-logout scope mismatch (API path):**
  - web logout action purges other sessions (`PerformLogout`)
  - API logout only invalidates current session, does not purge all user sessions

## 4.2 Dynamic Dictionaries and Catalog

### Implemented

- `data_dictionaries` and `form_rules` tables (JSONB metadata/rules).
- Admin dictionary/form-rule CRUD via API + Livewire manager.
- Dynamic validation merged into service and reservation validation paths.
- Services with tags, audience JSON arrays, price/free model, active flag.
- Favorites and recently viewed persistence.
- Catalog search/filter/sort and favorites toggling.
- RBAC for catalog writes (`editor`/`admin` only).

### Partial / Divergent

- “normalized title search” in general catalog uses `ILIKE` search; no unaccent/token normalization path for standard catalog queries.
- Strong title similarity detection is implemented specifically in import conflict detection via `pg_trgm`.

## 4.3 Reservation Lifecycle Engine

### Implemented

- Time-slot management with capacity and overlap checks.
- Reservation create/confirm/cancel/check-in/check-out/reschedule flows.
- Transactional booking with pessimistic lock on `time_slots`.
- Unique reservation constraint by `user_id + time_slot_id`.
- 30-minute expiry via delayed queue job.
- Late cancellation penalties persisted to `penalties`.
- Nightly no-show processing and freeze rule:
  - 2 no-shows in 60 days => 7-day booking freeze
- Check-in window logic: `start -15m` to `start +10m`.
- Partial attendance for late arrivals.
- Audit logging per transition.
- Policy-based authorization.

### Partial / Divergent

- Requested “DB constraints” for check-in timing are not implemented as database constraints; enforcement is in application/domain logic.

## 4.4 Offline Import/Export and Sync

### Implemented

- CSV/JSON import/export for services and users.
- pg_trgm enabled and trigram index on `services.title`.
- Duplicate detection:
  - exact id/project/patent (services)
  - exact username/email (users)
  - title similarity via trigram threshold
- Conflict strategies: `skip`, `prefer_newest`, `admin_override`.
- Manual conflict resolution flow.
- Chunked async processing (`ProcessImportChunk` jobs).
- Step-up protection on admin import/export routes.
- Export excludes password hashes.

### Partial / Divergent

- Requested “decrypt sensitive data only if explicitly mapped by admin” is simplified to `include_sensitive` boolean.
- Import and export are API/Livewire backed; no dedicated external sync protocol beyond file workflows.

## 5. Design Guarantees Matrix

| Guarantee | Status | Evidence / Notes |
|---|---|---|
| Laravel 11 core API + business layer | Implemented | `composer.json`, controllers/services/actions |
| Logic encapsulated in Actions for API + Livewire reuse | Partial | many actions/services reused; some Livewire components still mutate models directly |
| Livewire exhaustive UI states | Partial | good handling in many components; not uniformly exhaustive everywhere |
| PostgreSQL relational + JSONB | Implemented | migrations include JSONB dictionaries/rules/import logs |
| DB sessions + cross-device logout | Partial | DB sessions implemented; cross-device purge only guaranteed in web logout action |
| Local GD CAPTCHA only | Implemented | `CaptchaService` uses GD and local session state |
| CAPTCHA API endpoint with hashed payload | Not implemented | no route/controller endpoint for this contract |
| Step-up auth for critical actions | Implemented | `step-up` middleware on admin API/web routes |
| Signed step-up route flow | Not implemented | session timestamp-based approach used |
| Encrypted sensitive attributes | Implemented | encrypted casts in `User` model |
| 30-min expiry job | Implemented | `ExpireUnconfirmedReservation` dispatched with delay |
| Nightly no-show freeze rule | Implemented | scheduled command + `ReservationService::evaluateFreezes()` |
| Check-in window strictness | Implemented (app layer) | `TimeSlot::isCheckInWindowOpen()` + service checks |
| Dynamic JSON rules | Implemented | `form_rules` + `DataDictionaryService` |
| pg_trgm duplicate detection | Implemented | migration extension/index + raw similarity query |
| Fully dockerized offline deployment | Implemented | compose stack with internal bridge network |

## 6. Data Architecture (Implemented)

Primary tables and purpose:

- `users`, `sessions`, `password_histories`, `audit_logs`
- `data_dictionaries`, `form_rules`
- `services`, `tags`, `service_tag`, `user_favorites`, `user_recently_viewed`
- `time_slots`, `reservations`, `penalties`
- `import_batches`, `import_conflicts`

Notable database-level guarantees:

- `audit_logs` append-only trigger protections
- uniqueness on dictionary keys per type
- uniqueness on user/service favorites
- uniqueness on reservation (`user_id`, `time_slot_id`)
- trigram index for duplicate detection performance

## 7. Operational Characteristics

- Queue backend: Redis (`QUEUE_CONNECTION=redis`) with dedicated `queue` worker container.
- Scheduler: dedicated `scheduler` container running every 60 seconds.
- TLS: nginx configured for HTTPS via self-signed certs (`:4443`).
- DB bootstrap: init SQL enables `pg_trgm` and `unaccent`.

## 8. Testing Evidence

Implemented tests broadly align with requested QA focus:

- API auth, catalog, reservations, admin, import/export suites.
- Security flows (brute force, single logout, password rotation).
- Integration tests for step-up, tenant isolation, import conflicts, concurrency.
- Unit tests for reservation boundaries and CAPTCHA.

This provides strong confidence in core policy and lifecycle behavior already in place.

## 9. Gaps to Close for Full Spec Conformance

1. Add dedicated CAPTCHA API endpoint returning image + verifiable payload contract.
2. Align CAPTCHA trigger threshold with policy decision (currently 3, requested 5).
3. Make API logout optionally support full session purge to satisfy single-logout guarantee consistently.
4. Consider signed-route flavor for step-up if strict conformance is required.
5. Resolve runtime version drift (requested PHP 8.3 vs Dockerfile 8.4 vs composer `^8.2`).
6. If required, shift queue lifecycle state persistence fully to DB-backed queue instead of Redis.

## 10. Conclusion

The system is already a robust implementation of the intended architecture, especially around security controls, reservation lifecycle rules, and offline import/export operations. Most requested guarantees are present, with a small set of policy-contract mismatches and implementation drifts that can be closed without major architectural rework.
