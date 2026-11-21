# SISTLABUM — Remediation & Hardening Changelog (Final)

> **Application:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11 / PostgreSQL 17)  
> **Status:** All Remediations Confirmed & Regression Tested (760 Tests Passing)

---

## 1. Summary of Code Modifications

This changelog records all architectural hardening, business logic corrections, and security remediations executed across the codebase:

---

## 2. Detailed Remediation Entries

### 1. Production Unhandled Exception Envelope Sanitization (`SEC-EXCP-01`)
- **Severity:** P2 (Medium) / Information Disclosure
- **Root Cause:** `bootstrap/app.php` lacked an explicit fallback for general `\Throwable` under `APP_DEBUG=false`, potentially exposing internal error traces during unhandled infrastructure crashes.
- **Files Modified:** [`bootstrap/app.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/bootstrap/app.php)
- **Remediation:** Added production `\Throwable` handler returning standard JSON error envelope `{ error: { code: "INTERNAL_SERVER_ERROR", message: "Terjadi kesalahan internal server" } }` with `X-Request-Id`.
- **Regression Test:** [`tests/Feature/ProductionExceptionHandlerTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/ProductionExceptionHandlerTest.php) (1 test, 6 assertions).
- **Result:** PASS.

---

### 2. PPDB Application Submission Deadline Enforcement (`SEC-BL-01`)
- **Severity:** P0 (Critical) / State Machine Bypass
- **Root Cause:** `PPDBApplicationService::submit()` validated completed steps and documents, but omitted asserting the parent wave's active status and `close_at` datetime.
- **Files Modified:** [`app/Services/PPDB/PPDBApplicationService.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/PPDB/PPDBApplicationService.php)
- **Remediation:** Added check ensuring `$application->wave->status === 'active'` and `now()->lessThanOrEqualTo($application->wave->close_at)`.
- **Regression Test:** [`tests/Feature/Ppdb/PPDBApplicationDeadlineTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Ppdb/PPDBApplicationDeadlineTest.php) (8 tests, 42 assertions).
- **Result:** PASS.

---

### 3. CBT Proctor Room Isolation Authorization (`SEC-AUTHZ-01`)
- **Severity:** P1 (High) / Broken Object Level Authorization (BOLA)
- **Root Cause:** Proctor management actions (view participants, unlock, force-finish) filtered by exam ID without checking the proctor's assigned room ID.
- **Files Modified:** [`app/Http/Controllers/Api/V1/Cbt/ExamProctorController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/Cbt/ExamProctorController.php)
- **Remediation:** Injected room assignment verification query against `exam_room_proctors`.
- **Regression Test:** [`tests/Feature/Cbt/ExamProctorRoomIsolationTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Cbt/ExamProctorRoomIsolationTest.php) (6 tests, 34 assertions).
- **Result:** PASS.

---

### 4. Local CBT Sync Endpoint Rate Limiting (`SEC-AUTHZ-02`)
- **Severity:** P1 (High) / Denial of Service
- **Root Cause:** Offline node sync endpoints (`pull` and `push`) lacked route-level rate limiting.
- **Files Modified:** [`routes/api.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/routes/api.php)
- **Remediation:** Attached `throttle:15,1` middleware to offline synchronization routes.
- **Regression Test:** [`tests/Feature/Cbt/ExamSyncSecurityTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Cbt/ExamSyncSecurityTest.php) (5 tests, 26 assertions).
- **Result:** PASS.

---

### 5. Forgot Password Cooldown & Throttling (`SEC-DOS-01`)
- **Severity:** P1 (High) / Abuse Prevention
- **Root Cause:** Password reset endpoint permitted rapid repeated token requests without cooldown.
- **Files Modified:** [`routes/api.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/routes/api.php), [`app/Http/Controllers/Api/V1/AuthController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/AuthController.php)
- **Remediation:** Attached `throttle:5,15` and enforced a 2-minute token retention cooldown for repeated requests.
- **Regression Test:** [`tests/Feature/Auth/ForgotPasswordThrottlingTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Auth/ForgotPasswordThrottlingTest.php) (3 tests, 18 assertions).
- **Result:** PASS.

---

### 6. CBT Autosave Question Ownership Verification (`SEC-DATA-01`)
- **Severity:** P2 (Medium) / Cross-Exam Injection
- **Root Cause:** `save-answer` did not verify that the submitted question ID belonged to the candidate's active exam package.
- **Files Modified:** [`app/Http/Controllers/Api/V1/Cbt/ExamTakingController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/Cbt/ExamTakingController.php)
- **Remediation:** Added validation verifying question ownership within the student's assigned package items.
- **Regression Test:** [`tests/Feature/Cbt/ExamAutosaveValidationTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Cbt/ExamAutosaveValidationTest.php) (3 tests, 16 assertions).
- **Result:** PASS.

---

### 7. Sarpras Facility Booking Concurrency Locking (`SEC-CONC-01`)
- **Severity:** P2 (Medium) / Race Condition
- **Root Cause:** Non-atomic check and insert permitted overlapping bookings under concurrent requests.
- **Files Modified:** [`app/Http/Controllers/Api/V1/Sar/FacilityController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/Sar/FacilityController.php)
- **Remediation:** Wrapped booking check inside a database transaction with `lockForUpdate()`.
- **Regression Test:** [`tests/Feature/Sar/FacilityBookingConcurrencyTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Sar/FacilityBookingConcurrencyTest.php) (2 tests, 12 assertions).
- **Result:** PASS.

---

### 8. Health Check Technical Exception Masking (`SEC-INFO-01`)
- **Severity:** P2 (Medium) / Information Disclosure
- **Root Cause:** Database or Redis outages returned raw PDO stack traces in JSON responses.
- **Files Modified:** [`app/Http/Controllers/Api/V1/HealthController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/HealthController.php)
- **Remediation:** Sanitized error output to generic string `"unreachable"`.
- **Regression Test:** [`tests/Feature/HealthCheckTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/HealthCheckTest.php) (2 tests, 10 assertions).
- **Result:** PASS.

---

### 9. CORS Configuration Hardening (`SEC-CONFIG-01`)
- **Severity:** P3 (Low) / Configuration
- **Files Modified:** [`config/cors.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/config/cors.php)
- **Remediation:** Restricted origins strictly to `FRONTEND_URL` and `ADMIN_URL`.
- **Result:** PASS.

---

### 10. Environment Configuration Template Hardening
- **Severity:** P3 (Low) / Deployment Readiness
- **Files Modified:** [`apps/api/.env.example`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/.env.example)
- **Remediation:** Added explicit production defaults for PostgreSQL, frontend domains, cookie security flags, and S3 credentials.
- **Result:** PASS.
