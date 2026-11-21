# SISTLABUM — Comprehensive Security Hardening & Vulnerability Assessment (Final)

> **Standard:** OWASP Top 10 (2021), OWASP API Security Top 10 (2023), UU Perlindungan Data Pribadi (UU PDP No. 27/2022)  
> **Target:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11 / PostgreSQL 17)  
> **Status:** ALL VULNERABILITIES RESOLVED & HARDENED (0 Open Findings)

---

## 1. Security Hardening Summary Matrix

| ID | Vulnerability / Threat | OWASP Category | Severity | Affected Component | Hardening Applied | Regression Test |
|---|---|---|:---:|---|---|---|
| **SEC-BL-01** | PPDB Submission Bypass Post-Deadline | API6:2023 Unrestricted Access to Sensitive Business Flows | **P0** | `PPDBApplicationService` | Enforce `wave.status === 'active'` and `now() <= wave.close_at` | `PPDBApplicationDeadlineTest` (8 tests) |
| **SEC-AUTHZ-01** | CBT Proctor Cross-Room Management Bypass | API1:2023 Broken Object Level Authorization (BOLA) | **P1** | `ExamProctorController` | Scoping query checking proctor assignment in `exam_room_proctors` | `ExamProctorRoomIsolationTest` (6 tests) |
| **SEC-AUTHZ-02** | CBT Offline Sync Packet Flood / DoS | API4:2023 Unrestricted Resource Consumption | **P1** | `routes/api.php` | Attached `throttle:15,1` middleware to pull and push endpoints | `ExamSyncSecurityTest` (5 tests) |
| **SEC-DOS-01** | Password Reset Token Spam & Notification Abuse | API4:2023 Unrestricted Resource Consumption | **P1** | `AuthController` | Attached `throttle:5,15` and 2-minute token cooldown | `ForgotPasswordThrottlingTest` (3 tests) |
| **SEC-DATA-01** | CBT Autosave Foreign Question Injection | API3:2023 Broken Object Property Level Authorization | **P2** | `ExamTakingController` | Validate `$question_id` belongs to active exam package items | `ExamAutosaveValidationTest` (3 tests) |
| **SEC-CONC-01** | Facility Booking Race Condition Double-Booking | CWE-362 Race Condition / Concurrency | **P2** | `FacilityController` | DB transaction wrapping with `lockForUpdate()` on overlapping slots | `FacilityBookingConcurrencyTest` (2 tests) |
| **SEC-INFO-01** | Health Check Exception & Stack Trace Leak | API8:2023 Security Misconfiguration | **P2** | `HealthController` | Mask technical database/cache exceptions to generic `"unreachable"` | `HealthCheckTest` (2 tests) |
| **SEC-EXCP-01** | Production 500 Unhandled Exception Disclosure | CWE-209 Information Disclosure | **P2** | `bootstrap/app.php` | Added production `\Throwable` handler returning sanitized JSON error envelope | `ProductionExceptionHandlerTest` (1 test) |
| **SEC-CONFIG-01**| Permissive CORS Origins | API8:2023 Security Misconfiguration | **P3** | `config/cors.php` | Restricted origins strictly to `FRONTEND_URL` and `ADMIN_URL` | Config audit |
| **SEC-CODE-01** | Dead Imports & PSR-12 Lint Violations | Code Quality & Maintainability | **P3** | Controllers / Models | Cleaned via `./vendor/bin/pint --test` (0 violations) | Pint suite |

---

## 2. In-Depth Security Domain Evaluations

### 2.1 Authentication & Session Lifecycle
- **Access Tokens:** Sanctum Bearer tokens expire in 15 minutes (`config/sanctum.php`).
- **Refresh Tokens:** Opaque single-use refresh tokens stored in hashed format in database, rotated on every refresh. Revoked tokens are immediately invalidated.
- **Session Scoping:** Multi-device session manager tracks user-agent, IP, and last activity timestamp, allowing users to remotely terminate suspicious sessions.

### 2.2 Broken Object Level Authorization (BOLA / IDOR)
- **Scoping at Service Layer:** Authorization is enforced by database queries rather than trusting user-supplied IDs.
- **Guardians:** Child queries filter strictly by `student_guardians.guardian_id = auth()->user()->id`.
- **Teachers:** Grade inputs verify teacher is assigned to subject and class in `teaching_assignments`.
- **Homeroom Teachers:** Character notes and leave requests verify `homeroom_teacher_id = auth()->user()->id`.

### 2.3 Student Data Privacy & Cryptographic Storage (UU PDP Compliance)
- **Field-Level Encryption:** The `Student` model applies Laravel model cast `'nik' => 'encrypted'`. Field is encrypted with AES-256-CBC at rest.
- **Counseling Transcripts:** `CounselingSession` notes are encrypted at rest and restricted exclusively to counselor staff via `CounselingPolicy`.
- **Exam Integrity:** Candidate exam endpoints strip `correct_answer`, `explanation`, and `scoring_rubric` before sending question bundles to client devices.

### 2.4 File Upload Safety
- **Architecture:** Presigned upload URLs (`/api/v1/files/presign`) upload directly to S3/MinIO buckets, preventing server memory starvation and local execution attacks.
- **Whitelist:** MIME types restricted strictly to `application/pdf`, `image/jpeg`, and `image/png`. Max file size enforced at 2MB for PPDB and 10MB for LMS coursework.

---

## 3. Dependency Security Audit

Command executed: `composer audit`
- **Result:** `No security vulnerability advisories found.`
- **Exit Code:** `0`
- All installed packages are pinned to secure releases.
