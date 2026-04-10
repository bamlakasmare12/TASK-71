# Business Logic Questions Log

1. [Auth Lockout + CAPTCHA Trigger] (High)
   - **Question**: Should CAPTCHA be required starting on the 3rd failed login attempt, or only after the account reaches the 5-attempt lockout threshold?
   - **My Understanding**: Requirement states lockout at 5 failed attempts and CAPTCHA requirement after brute-force threshold pressure; this implies CAPTCHA should activate near/at the lockout threshold, not too early.
   - **Solution**: Enforce CAPTCHA at or after the 5th failed attempt (or 4th as a warning if desired), and document exact threshold to keep UX and security policy consistent.

2. [Reservation Confirmation Window] (High)
   - **Question**: Can a pending reservation be manually confirmed exactly at the 30-minute mark, or does it expire strictly once current time is equal to expiry timestamp?
   - **My Understanding**: Requirement says "expires after 30 minutes," which usually means confirmation is valid strictly before expiry and invalid at/after expiry.
   - **Solution**: Treat `now >= expires_at` as expired; show immediate UI countdown and disable confirm action at boundary to avoid race confusion.

3. [Late Cancellation Penalty Mode] (High)
   - **Question**: Is the post-24-hour penalty always monetary ($25), always points (50), or configurable per organization/policy?
   - **My Understanding**: Requirement says "$25.00 fee or 50 points," implying policy-configurable mode.
   - **Solution**: Store penalty mode in admin policy config (dictionary/form rule/policy table) and apply exactly one mode per cancellation event, with audit trail.

4. [No-Show Freeze Counting Logic] (High)
   - **Question**: For "2 breaches within 60 days," is day-60 inclusive and does freeze re-apply/extend if additional breaches occur while already frozen?
   - **My Understanding**: Day-60 should be inclusive for fairness and predictability; freeze should not stack infinitely unless policy explicitly says extend.
   - **Solution**: Count breaches where `created_at >= now - 60 days`; apply freeze if threshold met and user is not already frozen, or define explicit extension rule if desired.

5. [Check-In Boundary and Partial Attendance] (High)
   - **Question**: At exactly start time, should attendance be "on-time checked-in" or "partial attendance" (late)?
   - **My Understanding**: Requirement says late arrivals are partial; exactly at start should be on-time, after start should be partial.
   - **Solution**: Window open from `start-15m` to `start+10m` inclusive; classify partial attendance only when `now > start_time`.

6. [Reschedule vs Cancellation Penalty] (High)
   - **Question**: If user reschedules inside the paid-cancellation window, should penalty apply the same as cancel, or is reschedule penalty-free?
   - **My Understanding**: Requirement emphasizes lifecycle and fairness but does not explicitly penalize reschedule.
   - **Solution**: Keep reschedule penalty-free by default and document this as policy; if organization wants strictness, add admin toggle "penalize late reschedules."

7. [Single-Logout Semantics Across Devices] (High)
   - **Question**: Does "single-logout across devices" mean logout from one device invalidates all sessions (global logout), or only user-triggered "logout all sessions" action?
   - **My Understanding**: Requirement wording suggests global invalidation behavior when logout is requested as a security action.
   - **Solution**: Provide explicit "Logout all devices" behavior that purges all session rows for user, and keep regular logout scope clearly documented.

8. [Step-Up Verification Freshness] (High)
   - **Question**: How long should step-up privilege remain active, and must it be one-time per action or reusable for a short time window?
   - **My Understanding**: Requirement implies short-lived elevated access for critical actions, typically 5 minutes.
   - **Solution**: Use 5-minute elevation token/session flag, require re-verification after timeout, and log every successful/failed step-up attempt.

9. [Dynamic Validation Rule Precedence] (High)
   - **Question**: When static validation and admin-defined form rules conflict (e.g., static min=3 but dynamic min=20), which rule takes precedence?
   - **My Understanding**: Admin policy/rules are intended to reduce hardcoding and should be authoritative where safe.
   - **Solution**: Merge rules with "strictest wins" strategy (e.g., higher min, lower max), reject incompatible rule definitions, and surface clear admin warnings.

10. [Duplicate Detection Tie-Breaking] (High)
   - **Question**: If normalized-title similarity returns multiple near-duplicates and no exact project/patent match, how should system pick canonical record?
   - **My Understanding**: Exact IDs/project/patent should dominate; fuzzy matches should enter conflict resolution, not auto-merge blindly.
   - **Solution**: Apply deterministic ranking: exact ID > exact project/patent > highest similarity score; if ambiguous, require admin resolution.

11. [Sensitive Data Export Controls] (Medium)
   - **Question**: Should decrypted sensitive fields be exportable only via explicit per-field mapping approval, or one global "include sensitive" switch?
   - **My Understanding**: Requirement implies field-level explicit mapping for safer governance.
   - **Solution**: Implement per-field export mapping approval (default masked), enforce step-up, and log full metadata of who exported which fields and why.

12. [Backup Retention and Restore Validation] (Medium)
   - **Question**: Is monthly restore testing sufficient if backup runs daily with 30-day retention, or should verification be automated on every backup?
   - **My Understanding**: Requirement mandates monthly tested restore, but operational resilience improves with automated post-backup integrity checks.
   - **Solution**: Keep monthly full restore drill as compliance baseline, plus automated daily dump integrity check and alerting for failed snapshots.
