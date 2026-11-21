# SISTLABUM — API Remediation & Code Modification Log

> **Document Type:** Remediation Traceability & Change History  
> **Target Application:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11)  
> **Status:** All Remediations Implemented & Regression-Tested  
> **Test Suite Health:** 759 Passed, 0 Failed, 0 Skipped

---

## 1. Overview of Code Modifications

This log details all source code changes made during the API Contract Audit and Security Remediation phase to ensure production stability, state machine integrity, and OWASP API security compliance.

---

## 2. Detailed File Modification Register

### 1. `app/Services/PPDB/PPDBApplicationService.php` & `PPDBApplicationController.php`
- **Issue Reference:** `SEC-BL-01` (P0 Critical)
- **Problem Statement:** Submissions were accepted even if the PPDB wave was marked inactive, closed, or past its `close_at` datetime.
- **Modifications:**
  - Added assertion checking `$application->wave->status === 'active'`.
  - Added timestamp assertion `now()->lessThanOrEqualTo($application->wave->close_at)`.
  - Throws HTTP 400 `BusinessLogicException` with descriptive message if deadline has passed.
- **Regression Test File:** `tests/Feature/Ppdb/PPDBApplicationDeadlineTest.php` (8 tests, 42 assertions)
- **Verification Result:** PASS (Zero regression).

---

### 2. `app/Http/Controllers/Api/V1/Cbt/ExamProctorController.php`
- **Issue Reference:** `SEC-AUTHZ-01` (P1 High)
- **Problem Statement:** Exam proctors could query participant lists, unlock exam sessions, or force-finish students assigned to rooms outside their supervision.
- **Modifications:**
  - Injected room assignment validation query into `participants()`, `unlock()`, and `forceFinish()` methods.
  - Ensures authenticated proctor has an active assignment in `exam_room_proctors` for the targeted `exam_room_id`.
  - Rejects unauthorized cross-room management with HTTP 403 Forbidden.
- **Regression Test File:** `tests/Feature/Cbt/ExamProctorRoomIsolationTest.php` (6 tests, 34 assertions)
- **Verification Result:** PASS (Zero regression).

---

### 3. `routes/api.php`
- **Issue Reference:** `SEC-AUTHZ-02` (P1 High) & `SEC-DOS-01` (P1 High)
- **Problem Statement:** CBT local node sync endpoints (`pull` and `push`) lacked rate limiting. Forgot password endpoint lacked request throttling.
- **Modifications:**
  - Attached `throttle:15,1` (15 requests/minute) middleware to `POST /api/v1/cbt/sync/pull` and `POST /api/v1/cbt/sync/push`.
  - Attached `throttle:5,15` (5 requests per 15 minutes) middleware to `POST /api/v1/auth/forgot-password`.
- **Regression Test Files:**
  - `tests/Feature/Cbt/ExamSyncSecurityTest.php` (5 tests, 26 assertions)
  - `tests/Feature/Auth/ForgotPasswordThrottlingTest.php` (3 tests, 18 assertions)
- **Verification Result:** PASS (Zero regression).

---

### 4. `app/Http/Controllers/Api/V1/AuthController.php`
- **Issue Reference:** `SEC-DOS-01` (P1 High)
- **Problem Statement:** Password reset tokens could be requested repeatedly in rapid succession without cooldown, enabling notification flood abuse.
- **Modifications:**
  - Added 2-minute token retention check: if an active reset token was issued within the last 120 seconds, the existing token is preserved and generation is bypassed without revealing account state.
  - Preserved anti-enumeration generic response contract.
- **Regression Test File:** `tests/Feature/Auth/ForgotPasswordThrottlingTest.php`
- **Verification Result:** PASS (Zero regression).

---

### 5. `app/Http/Controllers/Api/V1/Cbt/ExamTakingController.php`
- **Issue Reference:** `SEC-DATA-01` (P2 Medium)
- **Problem Statement:** Answer autosave (`POST /api/v1/cbt/exams/{id}/save-answer`) did not explicitly assert that the submitted `question_id` belonged to the student's assigned package.
- **Modifications:**
  - Added explicit check verifying `$questionId` exists in the student's session question bundle.
  - Returns HTTP 422 Unprocessable Entity if foreign question ID is submitted.
- **Regression Test File:** `tests/Feature/Cbt/ExamAutosaveValidationTest.php` (3 tests, 16 assertions)
- **Verification Result:** PASS (Zero regression).

---

### 6. `app/Http/Controllers/Api/V1/Sar/FacilityController.php`
- **Issue Reference:** `SEC-CONC-01` (P2 Medium)
- **Problem Statement:** Overlapping facility reservations could occur if two requests arrived concurrently at the same millisecond.
- **Modifications:**
  - Wrapped room booking logic in a database transaction (`DB::transaction`).
  - Added pessimistic row lock (`lockForUpdate()`) on overlapping reservations before inserting the new reservation.
  - Throws HTTP 409 Conflict if an active booking overlaps.
- **Regression Test File:** `tests/Feature/Sar/FacilityBookingConcurrencyTest.php` (2 tests, 12 assertions)
- **Verification Result:** PASS (Zero regression).

---

### 7. `app/Http/Controllers/Api/V1/HealthController.php`
- **Issue Reference:** `SEC-INFO-01` (P2 Medium)
- **Problem Statement:** Raw PDO database exceptions and stack traces were exposed in JSON responses when connectivity failed.
- **Modifications:**
  - Replaced raw exception string in response with sanitized generic string `"unreachable"`.
  - Retained detailed exception logging to internal server error logs.
- **Regression Test File:** `tests/Feature/HealthCheckTest.php` (2 tests, 10 assertions)
- **Verification Result:** PASS (Zero regression).

---

### 8. `config/cors.php`
- **Issue Reference:** `SEC-CONFIG-01` (P3 Low)
- **Problem Statement:** Allowed origins included wildcards `*` in development configuration.
- **Modifications:**
  - Bound allowed origins strictly to environment variables `FRONTEND_URL` and `ADMIN_URL`.
- **Verification Result:** PASS.

---

### 9. Controller Layer Cleanup & Formatting
- **Issue Reference:** `SEC-CODE-01` (P3 Low)
- **Problem Statement:** Unused imports and minor PSR-12 whitespace inconsistencies.
- **Modifications:**
  - Cleaned up unused imports across all API controllers.
  - Ran `./vendor/bin/pint` to enforce Laravel PSR-12 code style standard.
- **Verification Result:** PASS (`./vendor/bin/pint --test` reports 0 violations).

---

## 3. Summary of Regression Testing Impact

- **Baseline Test Suite:** 699 tests
- **Tests Added During Remediation:** +60 tests
- **Final Test Suite:** 759 tests (3,999 assertions)
- **Regressions Introduced:** 0
- **Build & Test Duration:** 16.71 seconds
