# SISTLABUM — Backend Hardening Baseline & Environment Verification

> **Evaluation Date:** 2026-10-12  
> **Target Project:** `SISTLABUM` (`apps/api`)  
> **Status:** HARDENING BASELINE ESTABLISHED & VERIFIED

---

## 1. Verified Runtime Versions & Environment Diagnostics

| Component | Verified Runtime Version | Diagnostic Command | State |
|---|---|---|:---:|
| **PHP Engine** | PHP 8.5.11 (cli) (built: Sep 22 2026 13:32:06) (NTS) | `php -v` | Active |
| **Composer** | Composer version 2.10.3 (2026-08-27) | `composer --version` | Active |
| **Laravel Framework** | Laravel 13.35.0 | `php artisan --version` | Active |
| **PHPUnit** | PHPUnit 11.5.3 | `artisan test` runner | Active |
| **PostgreSQL Database** | PostgreSQL 17.0 on port 5432 (`/tmp:5432`) | `pg_isready` / `psql -l` | Active |
| **Bruno CLI (`bru`)** | Not installed on host system (`bru not found`) | `which bru` | Offline |
| **Docker Daemon** | Socket unavailable (`/var/run/docker.sock` inactive) | `docker ps` | Offline |
| **Dependency Advisories** | No security vulnerabilities found | `composer audit` | **CLEAN (0)** |
| **Code Style & Standards**| PSR-12 Compliant | `./vendor/bin/pint --test` | **CLEAN (0)** |

---

## 2. Source of Truth Document Locations

Canonical PRD and architecture documents are maintained in the root directory:
- [**`13_MASTER_MODULES.md`**](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/13_MASTER_MODULES.md): Single Source of Truth for all 75 features across 14 modules.
- [**`04_PRD_BACKEND.md`**](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/04_PRD_BACKEND.md): Technical requirements and contracts.
- [**`08_DATABASE_SCHEMA.md`**](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/08_DATABASE_SCHEMA.md): Database structures, relationships, and constraints.
- [**`09_SECURITY_PRIVACY.md`**](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/09_SECURITY_PRIVACY.md): Privacy standards, counseling isolation, and anti-leakage rules.
- [**`10_TESTING_QA.md`**](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/10_TESTING_QA.md): QA automation benchmarks and Definition of Done.
- [**`KEPUTUSAN_BISNIS_DAN_BATASAN_SISTEM.md`**](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/KEPUTUSAN_BISNIS_DAN_BATASAN_SISTEM.md): Documented scope boundaries for v1 (SPP payment gateway, WhatsApp deep links, and Dapodik spreadsheet format).

---

## 3. Baseline Audit Metrics Comparison

| Metric | Previous Audit Report Claim | Actual Hardening Baseline | Variance / Findings |
|---|:---:|:---:|---|
| **PRD Requirements** | 75 | **75** | Exact match (100% PASS_VERIFIED) |
| **Total Registered Routes**| 526 | **526** | Exact match (`php artisan route:list --json`) |
| **Functional REST API Endpoints** | 521 | **521** | Exact match (520 `/api/v1/*` + 1 `/api/user`) |
| **Non-API Utility Routes** | 5 | **5** | Exact match (`/up`, `/sanctum/csrf-cookie`, `/`, `/storage/*`) |
| **Bruno Request Files** | 521 | **521** | 1:1 match across 16 categories |
| **Bruno Environment Files** | 2 | **2** | `Local.bru` and `Testing.bru` (Total `.bru` files: 523) |
| **Automated Test Cases** | 759 | **760** | **+1 Test** (`ProductionExceptionHandlerTest.php`) |
| **Total Assertions** | 3,999 | **4,005** | **+6 Assertions** (Production 500 error sanitization) |
| **Test Failures / Skipped**| 0 | **0** | Zero failures, zero skipped |
| **PostgreSQL Migrations** | 35 | **35** | 100% executed on live PostgreSQL 17 |
| **Security Findings** | 9 | **9 + Hardening** | All 9 remediated + 500 error sanitization enforced |

---

## 4. Initial Gaps Identified & Hardened in Baseline

1. **Production 500 Exception Sanitization:**
   - *Gap:* `bootstrap/app.php` lacked an explicit fallback for general `\Throwable` under production (`APP_DEBUG=false`), creating potential for HTML error traces or technical leaks during critical unexpected infrastructure crashes.
   - *Hardening:* Added production `\Throwable` handler returning standard JSON error envelope `{ error: { code: "INTERNAL_SERVER_ERROR", message: "Terjadi kesalahan internal server" } }` with `X-Request-Id`.
   - *Verification:* Verified via [`ProductionExceptionHandlerTest.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/ProductionExceptionHandlerTest.php).
2. **Environment Configuration Template (`.env.example`):**
   - *Gap:* Missing explicit production variables for `FRONTEND_URL`, `ADMIN_URL`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, and PostgreSQL defaults.
   - *Hardening:* Updated [`apps/api/.env.example`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/.env.example) to reflect full production parameters.
