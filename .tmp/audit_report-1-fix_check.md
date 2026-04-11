# audit_report-1 Fix Check (Static)

Reviewed findings from `.tmp/audit_report-1.md` against current repository state (static-only, no execution).

## Overall
Result: 12 / 12 previously reported issues are fixed based on static evidence.

## Issue-by-Issue Verification

### B-01 (Blocker) - Privilege escalation via public role-select registration
Current status: Fixed
Evidence: `app/Livewire/Auth/RegisterForm.php:58`, `resources/views/livewire/auth/register-form.blade.php:21`

### H-01 (High) - Prompt-required REST-style API layer missing
Current status: Fixed
Evidence: `bootstrap/app.php:11`, `routes/api.php:3`, `app/Http/Controllers/Api/ReservationController.php:14`

### H-02 (High) - Critical-action step-up verification incomplete
Current status: Fixed
Evidence: `routes/web.php:65`, `routes/api.php:43`, `routes/api.php:49`, `routes/api.php:55`

### H-03 (High) - Cancellation policy mismatch (fee OR points)
Current status: Fixed
Evidence: `app/Services/ReservationService.php:124`, `app/Services/ReservationService.php:126`, `app/Services/ReservationService.php:127`

### H-04 (High) - Catalog filtering/sorting incomplete
Current status: Fixed
Evidence: `resources/views/livewire/catalog/catalog-list.blade.php:24`, `resources/views/livewire/catalog/catalog-list.blade.php:45`, `app/Livewire/Catalog/CatalogList.php:103`

### H-05 (High) - TLS-in-transit not implemented
Current status: Fixed (static configuration evidence)
Evidence: `docker/nginx/default.conf:12`, `docker/nginx/default.conf:18`, `docker/nginx/generate-cert.sh:14`, `docker-compose.yml:25`

### H-06 (High) - Admin dictionary/form-rule management missing
Current status: Fixed
Evidence: `routes/web.php:69`, `app/Livewire/Admin/DictionaryManager.php:12`, `resources/views/livewire/admin/dictionary-manager.blade.php:31`

### H-07 (High) - README access URL/port mismatch
Current status: Fixed
Evidence: `README.md:78`, `docker-compose.yml:25`

### M-01 (Medium) - Reservation rescheduling flow not exposed
Current status: Fixed
Evidence: `app/Livewire/Reservations/ReservationDashboard.php:77`, `resources/views/livewire/reservations/reservation-dashboard.blade.php:110`, `resources/views/livewire/reservations/reservation-dashboard.blade.php:143`

### M-02 (Medium) - Audit immutability not enforced at persistence boundary
Current status: Fixed
Evidence: `app/Models/AuditLog.php:35`, `database/migrations/0004_01_01_000002_enforce_audit_log_immutability.php:21`, `database/migrations/0004_01_01_000002_enforce_audit_log_immutability.php:38`

### M-03 (Medium) - Learner-only booking not strictly enforced
Current status: Fixed
Evidence: `app/Services/ReservationService.php:29`, `app/Livewire/Catalog/ServiceDetail.php:49`

### L-01 (Low) - Monthly restore testing lacked static evidence artifact
Current status: Fixed
Evidence: `README.md:483`, `docs/ops/restore-drill-checklist.md:35`

## Notes
- This is a static fix check only and confirms code/config/documentation evidence.
- Runtime verification remains out of scope for this document.
- API + Livewire architecture handling is documented in `docs/architecture-decision-api-livewire.md:5`.
