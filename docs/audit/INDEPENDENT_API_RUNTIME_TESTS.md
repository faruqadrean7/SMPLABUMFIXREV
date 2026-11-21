# SISTLABUM — Independent API Runtime & Automated Test Report

> **Audit Date:** 2026-10-12  
> **Environment:** macOS / PHP 8.5.11 / Laravel 13.35.0  
> **Target Application:** `apps/api`  
> **Methodology:** Dual-Verification (Live HTTP curl probes + PHPUnit automated feature suite)  
> **Status:** 100% PASS (0 Failures, 0 Skipped, 0 Regressions)

---

## 1. Live Runtime HTTP Probe Verification

A standalone instance of the API was launched on `http://127.0.0.1:8001` to probe live request handling, middleware pipelines, error envelopes, and authentication boundaries using `curl`:

### 1.1 Masked Health Check (`GET /api/v1/health`)
- **Execution:** `curl -s -w "\nHTTP_STATUS:%{http_code}\n" http://127.0.0.1:8001/api/v1/health`
- **HTTP Status:** `200 OK`
- **Response Payload:**
  ```json
  {
    "status": "degraded",
    "timestamp": "2026-10-12T01:47:01+00:00",
    "services": {
      "database": "ok",
      "redis": "unreachable"
    },
    "version": "1.1.0"
  }
  ```
- **Audit Verification:** Confirms `SEC-INFO-01`. When Redis connection is offline, the exception is cleanly masked to `"unreachable"` without leaking database credentials, hostnames, or PHP stack traces.

### 1.2 Unauthenticated Endpoint Access Rejection (`GET /api/user`)
- **Execution:** `curl -s -H "Accept: application/json" -w "\nHTTP_STATUS:%{http_code}\n" http://127.0.0.1:8001/api/user`
- **HTTP Status:** `401 Unauthorized`
- **Response Payload:**
  ```json
  {
    "error": {
      "code": "UNAUTHENTICATED",
      "message": "Sesi tidak valid atau telah berakhir",
      "details": [],
      "requestId": "e7f8c346-8ab9-4a02-a169-1795974c373b"
    }
  }
  ```
- **Audit Verification:** Confirms uniform error envelope, requestId generation for distributed tracing, and strict Sanctum token enforcement.

### 1.3 Request Validation Failure (`POST /api/v1/auth/login`)
- **Execution:** `curl -s -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{}' -w "\nHTTP_STATUS:%{http_code}\n" http://127.0.0.1:8001/api/v1/auth/login`
- **HTTP Status:** `400 Bad Request / Validation Failed`
- **Response Payload:**
  ```json
  {
    "error": {
      "code": "VALIDATION_FAILED",
      "message": "Data tidak valid",
      "details": [
        {"field": "identifier", "message": "Identitas (username, email, NIS, atau NIP) wajib diisi"},
        {"field": "password", "message": "Kata sandi wajib diisi"}
      ],
      "requestId": "2154f453-5014-4a37-85a0-76641a73cd2e"
    }
  }
  ```
- **Audit Verification:** Confirms FormRequest input validation with per-field errors in Bahasa Indonesia.

### 1.4 Public Navigation Resource Query (`GET /api/v1/public/navigation-menu`)
- **Execution:** `curl -s -H "Accept: application/json" -w "\nHTTP_STATUS:%{http_code}\n" http://127.0.0.1:8001/api/v1/public/navigation-menu`
- **HTTP Status:** `200 OK`
- **Response Payload:** Hierarchical navigation menu array for portal web (`Beranda`, `Profil`, `Berita`, `PPDB`, etc.).
- **Audit Verification:** Confirms public endpoint accessibility without Bearer token.

### 1.5 Scoped Data Boundary (`GET /api/v1/posts` vs `GET /api/v1/public/posts`)
- **Execution:** 
  - `GET /api/v1/posts` (Internal CMS management) -> Returns `401 Unauthorized` without token.
  - `GET /api/v1/public/posts` (Public blog view) -> Returns `200 OK` with paginated `{ data: [], meta: { ... } }`.
- **Audit Verification:** Proves proper route separation between authenticated CMS backoffice and public consumer views.

---

## 2. Automated Test Suite Execution Evidence

### 2.1 Execution Results
- **Command:** `php artisan test`
- **Runner:** PHPUnit 11.5.3 (PHP 8.5.11 / SQLite `:memory:`)
- **Exit Code:** `0`

```json
{
  "tool": "phpunit",
  "result": "passed",
  "tests": 759,
  "passed": 759,
  "failed": 0,
  "skipped": 0,
  "incomplete": 0,
  "assertions": 3999,
  "duration_ms": 16719
}
```

### 2.2 Comparison with Previous Audit Report
- **Claimed Tests:** 759 tests
- **Actual Verified Tests:** 759 tests (Exact match)
- **Claimed Assertions:** 3,999 assertions
- **Actual Verified Assertions:** 3,999 assertions (Exact match)
- **Failures / Errors:** 0
- **Skipped / Incomplete:** 0

### 2.3 Code Quality & Linter Evidence
- **Command:** `./vendor/bin/pint --test`
- **Exit Code:** `0`
- **Result:** `PASS - No code style issues found!`

---

## 3. Findings & Verdict

The runtime tests and automated test suite provide verifiable proof that the backend implementation adheres strictly to the PRD specifications, enforces robust authorization boundaries, sanitizes errors, and processes inputs deterministically.
