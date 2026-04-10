# ResearchHub API Specification

This spec documents the API that is actually implemented in the codebase under `repo/routes/api.php` and related controllers/resources.

## Overview

- **Base path:** `/api`
- **Auth model:** session-based (`web` guard), primarily intended for internal/offline usage
- **Primary audience:** Livewire UI via internal API client (`App\Services\InternalApiClient`) and authenticated browser clients
- **Content type:** `application/json`

## Authentication, RBAC, and Step-Up

- Most endpoints require an authenticated session (`auth` middleware).
- Most protected endpoints also require non-expired password (`password.not-expired` middleware).
- Role restrictions:
  - `learner`: read-only catalog + own reservation operations
  - `editor`: service catalog management
  - `admin`: user/dictionary/import/export administration
- Critical admin routes additionally require **step-up elevation** (`step-up` middleware), valid for 5 minutes.

## Common Error Shapes

Errors are not fully unified, but these are common patterns in current implementation:

```json
{
  "error": "code_or_message",
  "message": "human readable detail"
}
```

Or validation errors:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["...validation message..."]
  }
}
```

Typical statuses used:

- `200` success
- `201` created (dictionary/form-rule creation, import upload)
- `401` unauthenticated / invalid credentials
- `403` forbidden, expired password, or step-up required
- `422` validation or domain rule failure
- `423` locked account

## Resource Shapes

### User (`App\Http\Resources\UserResource`)

```json
{
  "id": 1,
  "username": "admin",
  "name": "System Administrator",
  "email": "admin@researchhub.local",
  "role": "admin",
  "role_label": "Administrator",
  "is_active": true,
  "is_booking_frozen": false,
  "booking_frozen_until": null,
  "password_updated_at": "2026-04-09T10:00:00+00:00",
  "created_at": "2026-04-09T10:00:00+00:00"
}
```

### Service (`App\Http\Resources\ServiceResource`)

```json
{
  "id": 10,
  "title": "Research Methodology Consultation",
  "slug": "research-methodology-consultation",
  "description": "...",
  "service_type": "consultation",
  "category": "Research Support",
  "eligibility_notes": "...",
  "target_audience": ["faculty", "graduate"],
  "price": "0.00",
  "is_free": true,
  "project_number": null,
  "patent_number": null,
  "is_active": true,
  "tags": [{"id": 1, "name": "Writing", "slug": "writing"}],
  "next_available_slot": {
    "id": 50,
    "start_time": "2026-04-12T09:00:00+00:00",
    "end_time": "2026-04-12T10:00:00+00:00",
    "capacity": 2,
    "booked_count": 1
  },
  "created_at": "2026-04-09T10:00:00+00:00",
  "updated_at": "2026-04-09T10:00:00+00:00"
}
```

### Reservation (`App\Http\Resources\ReservationResource`)

```json
{
  "id": 200,
  "user_id": 3,
  "service_id": 10,
  "time_slot_id": 50,
  "status": "pending",
  "status_label": "Pending Confirmation",
  "confirmed_at": null,
  "checked_in_at": null,
  "checked_out_at": null,
  "cancelled_at": null,
  "cancellation_reason": null,
  "expires_at": "2026-04-09T10:30:00+00:00",
  "can_cancel": true,
  "can_check_in": false,
  "is_free_cancellation": true,
  "service": {},
  "time_slot": {
    "id": 50,
    "start_time": "2026-04-12T09:00:00+00:00",
    "end_time": "2026-04-12T10:00:00+00:00"
  },
  "penalties": [],
  "created_at": "2026-04-09T10:00:00+00:00"
}
```

## Endpoints

## 1) Auth

### `POST /auth/login`

- **Auth:** public
- **Body:**
  - `username` (string, required)
  - `password` (string, required)
  - `captcha` (string, optional; required when CAPTCHA flow is triggered)
- **Success `200`:**

```json
{
  "user": {"id": 1, "username": "admin", "role": "admin"}
}
```

- **Errors:**
  - `401` `{ "error": "failed" }`
  - `422` `{ "error": "captcha_required" }`
  - `423` `{ "error": "locked" }`
  - `403` `{ "error": "password_expired" }`

### `GET /auth/me`

- **Auth:** required
- **Response `200`:** `UserResource`

### `POST /auth/logout`

- **Auth:** required
- **Response `200`:**

```json
{ "message": "Logged out." }
```

### `POST /auth/step-up`

- **Auth:** required
- **Body:**
  - `password` (string, required)
- **Success `200`:**

```json
{
  "message": "Step-up verified.",
  "elevated_until": "2026-04-09T10:05:00+00:00"
}
```

- **Error `403`:** invalid password

## 2) Catalog

### `GET /catalog`

- **Auth:** required
- **Query params (optional):**
  - `search`, `category`, `audience`, `service_type`, `tag`
  - `price_filter` (`free`|`paid`)
  - `sort` (`earliest`|`price_low`|`price_high`|`title`)
- **Response `200`:** paginated `ServiceResource[]`

### `GET /catalog/{service}`

- **Auth:** required
- **Response `200`:** `ServiceResource`

### `GET /catalog/dictionaries`

- **Auth:** required
- **Response `200`:**

```json
{
  "service_types": {"consultation": "Consultation"},
  "audiences": {"faculty": "Faculty"}
}
```

### `GET /catalog/favorites`

- **Auth:** required
- **Response `200`:** paginated `ServiceResource[]` for current user only

### `POST /catalog/{service}/favorite`

- **Auth:** required
- **Response `200`:**

```json
{ "favorited": true }
```

### `POST /catalog`

- **Auth:** required
- **Role:** `editor` or `admin`
- **Body (base rules):**
  - `title`, `description`, `service_type`, `price`
  - optional: `category`, `eligibility_notes`, `target_audience[]`, `is_active`, `tags[]`, `project_number`, `patent_number`
- **Dynamic validation:** merged from active `form_rules` for `entity=service`
- **Response `200`:** created `ServiceResource`

### `PUT /catalog/{service}`

- **Auth:** required
- **Role:** `editor` or `admin`
- **Body:** same as create (partial allowed)
- **Response `200`:** updated `ServiceResource`

## 3) Reservations

### `GET /reservations`

- **Auth:** required
- **Query:** `filter` = `all`|`upcoming`|`past` (default `all`)
- **Response `200`:** paginated `ReservationResource[]` scoped to current user

### `POST /reservations`

- **Auth:** required
- **Policy:** learner-only + not booking-frozen
- **Body:**
  - `time_slot_id` (required)
  - plus optional dynamic rules from `form_rules` for `entity=reservation`
- **Response `200`:** created reservation (`status=pending`, 30-minute expiry)
- **Error `422`:** domain failures (slot unavailable, frozen user, etc.)

### `GET /reservations/{reservation}`

- **Auth:** required
- **Policy:** owner, editor, or admin
- **Response `200`:** `ReservationResource` with penalties

### `POST /reservations/{reservation}/confirm`

- **Auth:** required
- **Owner required:** explicit owner check in controller
- **Response `200`:** reservation with `status=confirmed`

### `POST /reservations/{reservation}/cancel`

- **Auth:** required
- **Policy:** owner cancellable reservation or admin
- **Body:** `reason` (optional)
- **Response `200`:** reservation with `status=cancelled`

### `POST /reservations/{reservation}/check-in`

- **Auth:** required
- **Policy + domain:** must be in strict window (`slot_start -15m` to `slot_start +10m`)
- **Response `200`:** `status=checked_in` or `status=partial_attendance`

### `POST /reservations/{reservation}/check-out`

- **Auth:** required
- **Policy:** owner/admin/editor
- **Response `200`:** `status=completed`

### `POST /reservations/{reservation}/reschedule`

- **Auth:** required
- **Body:** `new_time_slot_id` (required)
- **Behavior:** cancels existing reservation and creates a new pending reservation
- **Response `200`:** new reservation resource

## 4) Admin (Step-Up Required)

All routes below require:

- authenticated user
- non-expired password
- role `admin`
- valid step-up elevation

Base prefix: `/admin`

### User Management

- `GET /admin/users` - list users (search/role filtering)
- `PUT /admin/users/{user}/role` - body: `role` (`learner|editor|admin`)
- `DELETE /admin/users/{user}` - deactivate user (`is_active=false`), cannot self-deactivate

### Data Dictionaries

- `GET /admin/dictionaries?type=...`
- `POST /admin/dictionaries`
  - required: `type`, `key`, `label`
  - optional: `metadata`, `sort_order`, `is_active`
- `PUT /admin/dictionaries/{dictionary}`
- `DELETE /admin/dictionaries/{dictionary}`

### Form Rules

- `GET /admin/form-rules?entity=...`
- `POST /admin/form-rules`
  - required: `entity`, `field`, `rules`
  - optional: `is_active`
- `PUT /admin/form-rules/{formRule}`
- `DELETE /admin/form-rules/{formRule}`

### Import

- `POST /admin/import/upload`
  - supports file upload or pre-stored path
  - required: `entity` (`services|users`), `conflict_strategy` (`skip|prefer_newest|admin_override`)
  - returns batch id + inferred field mapping
- `POST /admin/import/{batchId}/process`
  - required: `field_mapping`
  - dispatches chunked jobs (`50` rows/chunk)
- `GET /admin/import/{batchId}/status`
  - returns counts, status, error log, unresolved conflict count
- `POST /admin/import/conflicts/{conflictId}/resolve`
  - required: `resolution` (`overwrite|skip`)
- `POST /admin/import/{batchId}/finish`
  - fails `422` if unresolved conflicts remain

### Export

- `POST /admin/export`
  - required: `entity` (`services|users`), `format` (`csv|json`)
  - optional: `include_sensitive` (bool), `since` (date)
  - response contains file payload as string content:

```json
{
  "data": {
    "filename": "services_export_2026-04-09_100000.csv",
    "content": "...",
    "format": "csv",
    "mime_type": "text/csv"
  }
}
```

## Notes on Current Behavior

- CAPTCHA is implemented as a service used by Livewire login, but there is **no dedicated REST CAPTCHA endpoint** in `routes/api.php`.
- API routes are designed around internal session usage and are also consumed from Livewire via `InternalApiClient`.
