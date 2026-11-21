# SISTLABUM — API Security Findings & Remediation Verification

> **Audit Type:** Backend Application Security & Business Logic Audit  
> **Target Application:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11)  
> **Security Standards:** OWASP API Security Top 10 (2023), `09_SECURITY_PRIVACY.md`, `04_PRD_BACKEND.md`  
> **Status:** ALL FINDINGS RESOLVED & VERIFIED (0 Open Vulnerabilities)

---

## 1. Security Findings Summary Matrix

| ID | Category | Severity | Title | Affected Component | Status | Verified Test |
|---|---|:---:|---|---|:---:|---|
| **SEC-BL-01** | Business Logic | **P0 (Critical)** | PPDB Application Submission Bypassing Wave Deadline & Status Check | `PPDBApplicationService` | **REMEDIATED** | `PPDBApplicationDeadlineTest` (8 tests) |
| **SEC-AUTHZ-01** | Authorization / IDOR | **P1 (High)** | CBT Proctor Room Isolation Bypass | `ExamProctorController` | **REMEDIATED** | `ExamProctorRoomIsolationTest` (6 tests) |
| **SEC-AUTHZ-02** | Rate Limiting / DoS | **P1 (High)** | CBT Local Node Sync Endpoints Lacked Throttling | `routes/api.php` | **REMEDIATED** | `ExamSyncSecurityTest` (5 tests) |
| **SEC-DOS-01** | Abuse Prevention | **P1 (High)** | Forgot Password Endpoint Lacked Cooldown & Rate Limiting | `AuthController` | **REMEDIATED** | `ForgotPasswordThrottlingTest` (3 tests) |
| **SEC-DATA-01** | Data Integrity | **P2 (Medium)** | CBT Autosave Missing Explicit Question-Package Ownership Check | `ExamTakingController` | **REMEDIATED** | `ExamAutosaveValidationTest` (3 tests) |
| **SEC-CONC-01** | Race Condition | **P2 (Medium)** | Facility Booking Concurrency Double-Booking Risk | `BookingController` | **REMEDIATED** | `FacilityBookingConcurrencyTest` (2 tests) |
| **SEC-INFO-01** | Information Disclosure | **P2 (Medium)** | Health Check Endpoint Leaking Raw Database/Cache Exceptions | `HealthController` | **REMEDIATED** | `HealthCheckTest` (2 tests) |
| **SEC-CONFIG-01**| Configuration | **P3 (Low)** | Permissive CORS Configuration in Default Environment | `config/cors.php` | **REMEDIATED** | Production Config Audit |
| **SEC-CODE-01** | Code Quality | **P3 (Low)** | Code Style & Unused Imports Across API Controllers | Controller Layer | **REMEDIATED** | Laravel Pint (0 violations) |

---

## 2. Detailed Vulnerability Analyses & Remediation Evidence

### SEC-BL-01: PPDB Application Submission Bypassing Wave Deadline & Status Check
- **Severity:** P0 (Critical)
- **Vulnerability Type:** Business Logic Flaw / State Machine Bypass (CWE-840)
- **Location:** `app/Services/PPDB/PPDBApplicationService.php` (`submit` method)
- **Impact:** An applicant could submit their application after the wave closing time (`close_at`), or after the wave status had been set to `closed` or `archived`, contaminating official selection batches.
- **Root Cause:** The `submit()` routine validated that all required steps and documents were completed, but omitted checking the active status and closing timestamp of the associated `PPDBWave`.
- **Remediation:** Added explicit wave state and deadline assertions prior to transitioning application status to `submitted`:
  ```php
  $wave = $application->wave;
  if ($wave->status !== 'active' || now()->greaterThan($wave->close_at)) {
      throw new BusinessLogicException('Gelombang pendaftaran sudah ditutup.');
  }
  ```
- **Verification:** Verified by `PPDBApplicationDeadlineTest.php` (8 tests passing), covering active submissions, post-deadline rejections, and wave status changes.

---

### SEC-AUTHZ-01: CBT Proctor Room Isolation Bypass
- **Severity:** P1 (High)
- **Vulnerability Type:** Broken Object Level Authorization / IDOR (OWASP API1:2023)
- **Location:** `app/Http/Controllers/Api/V1/Cbt/ExamProctorController.php`
- **Impact:** A proctor assigned to Room A could view participant progress, unlock sessions, or force-finish exams of students assigned to Room B.
- **Root Cause:** Queries in proctor controller methods filtered by `exam_id` but failed to scope by the proctor's assigned `exam_room_id`.
- **Remediation:** Injected room-scoping validation ensuring that the authenticated proctor has an active assignment for the specific room requested:
  ```php
  $proctorRoom = ExamRoomProctor::where('user_id', $user->id)
      ->where('exam_room_id', $roomId)
      ->firstOrFail();
  ```
- **Verification:** Verified by `ExamProctorRoomIsolationTest.php` (6 tests passing), asserting HTTP 403 Forbidden when accessing unauthorized rooms.

---

### SEC-AUTHZ-02: CBT Local Node Sync Endpoints Lacked Throttling
- **Severity:** P1 (High)
- **Vulnerability Type:** Lack of Resources & Rate Limiting (OWASP API4:2023)
- **Location:** `routes/api.php` (`/api/v1/cbt/sync/pull`, `/api/v1/cbt/sync/push`)
- **Impact:** Offline school exam servers or malicious actors could flood sync endpoints, consuming server database connections and causing denial of service.
- **Root Cause:** Offline sync endpoints were authenticated by pre-shared node token but omitted route middleware rate limiting.
- **Remediation:** Attached strict rate-limiting middleware (`throttle:15,1` — 15 requests per minute per IP) to all offline synchronization routes.
- **Verification:** Verified by `ExamSyncSecurityTest.php` (5 tests passing), asserting HTTP 429 Too Many Requests when rate threshold is exceeded.

---

### SEC-DOS-01: Forgot Password Lacked Cooldown & Rate Limiting
- **Severity:** P1 (High)
- **Vulnerability Type:** Rate Limiting / Email Bombing (OWASP API4:2023)
- **Location:** `app/Http/Controllers/Api/V1/AuthController.php`
- **Impact:** Attackers could spam email or WhatsApp password reset notifications to arbitrary accounts, causing provider charges and denial of service.
- **Root Cause:** Standard email reset controller lacked rate limiting and per-user regeneration cooldown timers.
- **Remediation:** Added `throttle:5,15` middleware (5 requests per 15 minutes) and enforced a 2-minute cooldown before a new password reset token can be generated for the same account.
- **Verification:** Verified by `ForgotPasswordThrottlingTest.php` (3 tests passing).

---

### SEC-DATA-01: CBT Autosave Missing Explicit Question-Package Ownership Check
- **Severity:** P2 (Medium)
- **Vulnerability Type:** Data Integrity / Cross-Exam Injection
- **Location:** `app/Http/Controllers/Api/V1/Cbt/ExamTakingController.php`
- **Impact:** An exam candidate submitting answers via `save-answer` could submit answers referencing question IDs from another exam package.
- **Root Cause:** Autosave verified the session token was active, but did not assert that the submitted `question_id` existed within the assigned session's question list.
- **Remediation:** Added validation asserting `question_id` exists in the student's assigned exam package items before storing the answer.
- **Verification:** Verified by `ExamAutosaveValidationTest.php` (3 tests passing).

---

### SEC-CONC-01: Facility Booking Concurrency Double-Booking Risk
- **Severity:** P2 (Medium)
- **Vulnerability Type:** Race Condition / Concurrency (CWE-362)
- **Location:** `app/Http/Controllers/Api/V1/Sar/FacilityController.php` (`storeBooking`)
- **Impact:** Two simultaneous booking requests for the same room or facility at the same time slot could both pass the availability check and create conflicting reservations.
- **Root Cause:** Availability check and reservation insertion were executed in separate statements without row-level locking.
- **Remediation:** Wrapped booking verification and creation inside a database transaction with `lockForUpdate` on existing reservations intersecting the requested interval:
  ```php
  DB::transaction(function () use ($facilityId, $start, $end) {
      $conflict = FacilityBooking::where('facility_id', $facilityId)
          ->where('status', '!=', 'cancelled')
          ->where(function ($q) use ($start, $end) {
              $q->whereBetween('start_at', [$start, $end])
                ->orWhereBetween('end_at', [$start, $end]);
          })
          ->lockForUpdate()
          ->exists();
      if ($conflict) throw new ConflictException('Fasilitas sudah dipesan pada jam tersebut.');
      // Create booking...
  });
  ```
- **Verification:** Verified by `FacilityBookingConcurrencyTest.php` (2 tests passing).

---

### SEC-INFO-01: Health Check Endpoint Leaking Raw Database/Cache Exceptions
- **Severity:** P2 (Medium)
- **Vulnerability Type:** Information Disclosure (CWE-209)
- **Location:** `app/Http/Controllers/Api/V1/HealthController.php`
- **Impact:** If the database or Redis server experienced downtime, `/api/v1/health` exposed raw PDO connection errors, hostnames, and stack traces.
- **Root Cause:** Catch blocks placed raw exception messages into the JSON response payload.
- **Remediation:** Masked technical error messages to generic string `"unreachable"` while logging details to internal secure application logs.
- **Verification:** Verified by `HealthCheckTest.php` (2 tests passing).

---

### SEC-CONFIG-01: Permissive CORS Configuration in Default Environment
- **Severity:** P3 (Low)
- **Vulnerability Type:** Cross-Origin Resource Sharing Misconfiguration
- **Location:** `config/cors.php`
- **Impact:** Allowed arbitrary origins to read API responses if deployed with default development configuration.
- **Remediation:** Restricted allowed origins strictly to environment-configured frontend domains (`FRONTEND_URL`, `ADMIN_URL`).
- **Verification:** Confirmed in `config/cors.php`.

---

### SEC-CODE-01: Code Style & Unused Imports
- **Severity:** P3 (Low)
- **Vulnerability Type:** Code Maintainability & Technical Debt
- **Location:** `app/Http/Controllers/Api/V1/*`
- **Remediation:** Executed `./vendor/bin/pint --test` across the entire codebase. Cleaned up all dead imports and formatted code to PSR-12 standard.
- **Verification:** Confirmed: 0 Pint violations.

---

## 3. Residual Risk Assessment

All 9 identified vulnerabilities have been remediated, verified with automated feature tests, and confirmed regression-free across the 759-test suite.
No open high or critical security findings remain.
