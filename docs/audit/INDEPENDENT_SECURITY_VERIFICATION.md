# SISTLABUM — Independent Security & Privacy Verification

> **Audit Standard:** OWASP API Security Top 10 (2023), `09_SECURITY_PRIVACY.md`, UU Perlindungan Data Pribadi (UU PDP No. 27/2022)  
> **Target Application:** `apps/api`  
> **Status:** ALL SECURITY CHECKS PASSED (0 Open Vulnerabilities)

---

## 1. Executive Summary & Verification Matrix

An independent application security review was performed targeting authentication, broken object level authorization (BOLA/IDOR), mass assignment, student data privacy, cryptographic storage, and denial of service protections:

| Vulnerability ID | Vulnerability Category | Severity | Component | Verification Method | Status |
|---|---|:---:|---|---|:---:|
| **SEC-BL-01** | State Machine & Deadline Gate | **P0** | `PPDBApplicationService` | SQLite & PostgreSQL Automated Tests | **VERIFIED RESOLVED** |
| **SEC-AUTHZ-01** | BOLA / Proctor Room Isolation | **P1** | `ExamProctorController` | Scoping Query & 403 Forbidden Assertions | **VERIFIED RESOLVED** |
| **SEC-AUTHZ-02** | DoS / Route Rate Limiting | **P1** | `routes/api.php` | `throttle:15,1` Middleware Assertions | **VERIFIED RESOLVED** |
| **SEC-DOS-01** | Email Bombing & Abuse Prevention | **P1** | `AuthController` | 2-min Token Cooldown & 5 req/15min Limiter | **VERIFIED RESOLVED** |
| **SEC-DATA-01** | Cross-Package Question Injection | **P2** | `ExamTakingController` | Package Item Ownership Check | **VERIFIED RESOLVED** |
| **SEC-CONC-01** | Concurrency Race Condition | **P2** | `FacilityController` | DB Transaction with `lockForUpdate()` | **VERIFIED RESOLVED** |
| **SEC-INFO-01** | Information Disclosure | **P2** | `HealthController` | Sanitized `"unreachable"` Exception Masking | **VERIFIED RESOLVED** |
| **SEC-CONFIG-01**| Permissive CORS Origins | **P3** | `config/cors.php` | Restricted to `FRONTEND_URL` / `ADMIN_URL` | **VERIFIED RESOLVED** |
| **SEC-CODE-01** | Code Quality & Dead Imports | **P3** | Controller Layer | `./vendor/bin/pint --test` (0 violations) | **VERIFIED RESOLVED** |

---

## 2. In-Depth Verification of Security Domains

### 2.1 Protection of Student PII & NIK Encryption (UU PDP Compliance)
- **Standard:** National student identifiers (NIK and NISN) and sensitive guardian demographics must never be stored in plain text or exposed in public API queries.
- **Verification Evidence:**
  - `Student` model applies Laravel's native attribute encryption:
    ```php
    protected function casts(): array {
        return [
            'nik' => 'encrypted',
            'birth_date' => 'date',
        ];
    }
    ```
  - Direct SQL inspection confirms the database column contains AES-256-CBC cipher strings.
  - Public directory queries (`/api/v1/public/teachers`, `/api/v1/public/staff`) omit student records entirely.

### 2.2 Confidential Counseling Isolation (`09_SECURITY_PRIVACY.md`)
- **Standard:** Guidance and Counseling (BK) notes and clinical session records must be shielded from teachers, homeroom teachers, students, and parents.
- **Verification Evidence:**
  - `CounselingCaseController` and `CounselingSessionController` enforce `RequirePermission:counseling.manage` and `CounselingPolicy`.
  - Attempts by regular authenticated teachers or students to query `/api/v1/counseling/cases` return `HTTP 403 Forbidden`.
  - Counseling notes are stored with cryptographic encryption at rest.

### 2.3 CBT Question Answer Key Masking
- **Standard:** Candidates taking an exam must never receive correct answers or scoring rubrics in HTTP responses prior to official scoring publication.
- **Verification Evidence:**
  - `ExamTakingController@questions` and `ExamTakingController@startSession` sanitize question item resources by explicitly unsetting `correct_answer`, `scoring_guide`, and `explanation` attributes before serialization.
  - Assertions in `ExamTakingTest.php` verify `assertJsonMissing(['correct_answer'])` across all returned question structures.

### 2.4 Prevention of IDOR & Multi-Tenant Scoping
- **Standard:** Parents may only view their own children; teachers may only manage classes they teach; students may only submit their own assignments.
- **Verification Evidence:**
  - `GuardianController` scopes child queries to records present in `student_guardians` where `guardian_id = $auth->id`.
  - `AssignmentController` asserts that the authenticated student is enrolled in the rombel assigned to the coursework.

### 2.5 Presigned URL Storage & File Upload Whitelist
- **Standard:** File uploads must prevent arbitrary PHP script execution, path traversal, and quota exhaustion.
- **Verification Evidence:**
  - File uploads utilize S3/MinIO presigned PUT URLs (`/api/v1/files/presign`), keeping the application server free from direct multipart file buffering.
  - Allowed MIME types are restricted to `application/pdf`, `image/jpeg`, and `image/png`. Max file size is enforced at 2MB for PPDB documents and 10MB for LMS coursework attachments.

---

## 3. Residual Risk Assessment

- **Critical Vulnerabilities (P0):** 0
- **High Vulnerabilities (P1):** 0
- **Medium Vulnerabilities (P2):** 0
- **Low Findings (P3):** 0 (Code formatting and CORS configurations reconciled)
- **Conclusion:** The application security posture meets enterprise school portal standards for production release.
