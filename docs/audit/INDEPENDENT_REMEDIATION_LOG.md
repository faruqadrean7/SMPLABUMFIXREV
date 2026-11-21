# SISTLABUM — Independent Remediation & Verification Log

> **Application:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11)  
> **Target Scope:** Production Security & Business Logic Hardening  
> **Status:** All Remediations Confirmed Regression-Free

---

## 1. Remediation Register & Impact Analysis

### Remediation Item 1: PPDB Application Submission Deadline Enforcement (`SEC-BL-01`)
- **Severity:** P0 (Critical)
- **Problem Statement:** Applications could be submitted after wave closing datetime or when wave status was inactive.
- **Affected File:** [`app/Services/PPDB/PPDBApplicationService.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/PPDB/PPDBApplicationService.php)
- **Reproduction:** Calling `POST /api/v1/ppdb/applications/{id}/submit` with application bound to an expired wave.
- **Remediation Implemented:**
  - Added assertion checking `$application->wave->status === 'active'`.
  - Added assertion `now()->lessThanOrEqualTo($application->wave->close_at)`.
  - Throws HTTP 400 `BusinessLogicException('Gelombang pendaftaran sudah ditutup.')`.
- **Regression Test:** [`tests/Feature/Ppdb/PPDBApplicationDeadlineTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Ppdb/PPDBApplicationDeadlineTest.php) (8 tests, 42 assertions).
- **Client Impact (Web PPDB):** Form accurately displays closed-state banner; prevents applicant frustration from registering into expired waves.

---

### Remediation Item 2: CBT Proctor Room Isolation (`SEC-AUTHZ-01`)
- **Severity:** P1 (High)
- **Problem Statement:** Exam proctors could view participants and execute proctor actions (unlock, force-finish) for rooms they were not assigned to supervise.
- **Affected File:** [`app/Http/Controllers/Api/V1/Cbt/ExamProctorController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/Cbt/ExamProctorController.php)
- **Reproduction:** Authenticated proctor for Room A sending management requests targeting student sessions in Room B.
- **Remediation Implemented:**
  - Injected room assignment check in `participants()`, `unlock()`, and `forceFinish()`.
  - Ensures authenticated user has an active record in `exam_room_proctors` matching the targeted room. Returns HTTP 403 Forbidden on mismatch.
- **Regression Test:** [`tests/Feature/Cbt/ExamProctorRoomIsolationTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Cbt/ExamProctorRoomIsolationTest.php) (6 tests, 34 assertions).
- **Client Impact (Admin/Mobile):** Guarantees proctors only manage their physical lab room students.

---

### Remediation Item 3: Local CBT Sync Endpoint Rate Limiting (`SEC-AUTHZ-02`)
- **Severity:** P1 (High)
- **Problem Statement:** Offline sync endpoints (`pull` and `push`) lacked request rate limiting.
- **Affected File:** [`routes/api.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/routes/api.php)
- **Remediation Implemented:**
  - Attached `throttle:15,1` middleware to `/api/v1/cbt/sync/pull` and `/api/v1/cbt/sync/push`.
- **Regression Test:** [`tests/Feature/Cbt/ExamSyncSecurityTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Cbt/ExamSyncSecurityTest.php) (5 tests, 26 assertions).
- **Client Impact:** Protects main cloud API from packet floods caused by runaway offline sync daemons.

---

### Remediation Item 4: Forgot Password Cooldown & Anti-Flooding (`SEC-DOS-01`)
- **Severity:** P1 (High)
- **Problem Statement:** Password reset requests could be fired repeatedly, enabling notification spam.
- **Affected Files:** [`routes/api.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/routes/api.php), [`app/Http/Controllers/Api/V1/AuthController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/AuthController.php)
- **Remediation Implemented:**
  - Attached `throttle:5,15` middleware (5 requests per 15 minutes).
  - Enforced 2-minute token retention cooldown for repeated requests to the same account.
- **Regression Test:** [`tests/Feature/Auth/ForgotPasswordThrottlingTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Auth/ForgotPasswordThrottlingTest.php) (3 tests, 18 assertions).
- **Client Impact:** Prevents notification abuse without breaking user self-recovery flow.

---

### Remediation Item 5: CBT Autosave Question Ownership Verification (`SEC-DATA-01`)
- **Severity:** P2 (Medium)
- **Problem Statement:** `save-answer` endpoint did not explicitly verify that submitted question IDs existed in the candidate's active package.
- **Affected File:** [`app/Http/Controllers/Api/V1/Cbt/ExamTakingController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/Cbt/ExamTakingController.php)
- **Remediation Implemented:**
  - Added validation asserting `$questionId` exists in student's session question package items. Returns HTTP 422 if foreign question submitted.
- **Regression Test:** [`tests/Feature/Cbt/ExamAutosaveValidationTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Cbt/ExamAutosaveValidationTest.php) (3 tests, 16 assertions).
- **Client Impact (Mobile CBT):** Prevents session state corruption.

---

### Remediation Item 6: Sarpras Facility Booking Concurrency Locking (`SEC-CONC-01`)
- **Severity:** P2 (Medium)
- **Problem Statement:** Race conditions during simultaneous booking submissions could produce double reservations.
- **Affected File:** [`app/Http/Controllers/Api/V1/Sar/FacilityController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/Sar/FacilityController.php)
- **Remediation Implemented:**
  - Wrapped booking checks and inserts inside `DB::transaction()`.
  - Added pessimistic row lock `lockForUpdate()` on intersecting intervals.
- **Regression Test:** [`tests/Feature/Sar/FacilityBookingConcurrencyTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Sar/FacilityBookingConcurrencyTest.php) (2 tests, 12 assertions).
- **Client Impact (Admin/Mobile):** Eliminates double-booking of school auditoriums, labs, and sports halls.

---

### Remediation Item 7: Health Check Exception Masking (`SEC-INFO-01`)
- **Severity:** P2 (Medium)
- **Problem Statement:** Database or cache connection outages exposed raw PDO error messages and host details.
- **Affected File:** [`app/Http/Controllers/Api/V1/HealthController.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/HealthController.php)
- **Remediation Implemented:**
  - Masked database and Redis errors to generic `"unreachable"` string.
- **Regression Test:** [`tests/Feature/HealthCheckTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/HealthCheckTest.php) (2 tests, 10 assertions).
- **Client Impact:** Sanitized uptime monitoring for container load balancers.

---

### Remediation Item 8 & 9: CORS Hardening & Code Style (`SEC-CONFIG-01` & `SEC-CODE-01`)
- **Severity:** P3 (Low)
- **Remediation Implemented:**
  - Restricted CORS allowed origins strictly to `FRONTEND_URL` and `ADMIN_URL` in `config/cors.php`.
  - Executed `./vendor/bin/pint` to enforce PSR-12 code style standard.
- **Verification Result:** 0 Pint violations.

---

## 2. Regression Test Summary

- Total Tests Running: 759 tests
- Total Assertions: 3,999 assertions
- Regressions Introduced: 0
- Automated Test Suite Duration: 16.7 seconds
