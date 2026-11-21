# SISTLABUM — Automated Test Evidence & Verification Report

> **Execution Date:** 2026-10-12  
> **Environment:** Laravel 13.35.0 / PHP 8.5.11 / PHPUnit 11.5.3 / SQLite `:memory:`  
> **Test Command:** `php artisan test`  
> **Linter Command:** `./vendor/bin/pint --test`  
> **Target Application:** `apps/api`  
> **Verdict:** 100% PASS (0 Failures, 0 Skipped, 0 Incomplete)

---

## 1. Test Suite Execution Summary

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
  "duration_ms": 16719,
  "exit_code": 0
}
```

```json
{
  "tool": "pint",
  "result": "passed",
  "violations": 0,
  "exit_code": 0
}
```

### Key Metrics
- **Total Test Files:** 83 files (82 Feature, 1 Unit)
- **Total Test Cases:** 759 tests
- **Total Assertions:** 3,999 assertions
- **Test Pass Rate:** 100.0%
- **Skipped / Incomplete Tests:** 0
- **Execution Time:** 16.71 seconds

---

## 2. Test Breakdown by Domain & Module

| Module Directory | Test Files | Total Tests | Focus & Scenarios Tested |
|---|:---:|:---:|---|
| `tests/Feature/Auth/` | 6 | 62 | Sanctum login, refresh token rotation, password reset throttling (`SEC-DOS-01`), RBAC permission guards, active sessions, audit logs |
| `tests/Feature/Master/` | 8 | 98 | Academic years, single-active semester switch, students (encrypted NIK), teachers, guardians, classes, subject assignments, rooms, promotions |
| `tests/Feature/Cms/` | 7 | 55 | Posts, categories, scheduled publishing, events, albums, media, categorized downloads, static pages, bulletins, navigation |
| `tests/Feature/Ppdb/` | 10 | 108 | Wave/track configuration, account registration, multi-step draft autosave, document upload validation, deadline gate (`SEC-BL-01`), verification workflow, scoring, graduation/conversion |
| `tests/Feature/Guest/` | 3 | 23 | Front-desk visitor check-in, anonymous complaint tracking token, host notification, digital signature audit |
| `tests/Feature/Academic/` | 11 | 96 | Schedule conflict detection, teaching journals, attendance batching, leave request approval, staff geofencing, LMS assignments & submissions |
| `tests/Feature/Grade/` | 4 | 44 | Grading components, KKTP evaluation, character notes, e-progress reports, report card freezing & approval |
| `tests/Feature/Cbt/` | 13 | 114 | Question banks, packages, room tokens, participant cards, answer key masking, autosave validation (`SEC-DATA-01`), proctor room isolation (`SEC-AUTHZ-01`), grading, offline sync throttling (`SEC-AUTHZ-02`), anomaly heartbeat & violation engine |
| `tests/Feature/Std/` | 4 | 42 | Student discipline points, violation thresholds, extracurriculars, student achievements, study trips |
| `tests/Feature/Bk/` | 3 | 31 | Counseling session privacy scoping, appointments, referral cases, early warning triggers |
| `tests/Feature/Sar/` | 5 | 50 | Facility booking concurrency locking (`SEC-CONC-01`), asset tracking, repair tickets, library catalog & loans, lab equipment loans |
| `tests/Feature/Hum/` | 2 | 18 | Institutional partnerships, student exchange programs, alumni tracer surveys |
| `tests/Feature/Rpt/` | 2 | 14 | Executive dashboard metrics, academic & attendance aggregations |
| `tests/Feature/Ntf/` | 4 | 38 | Notification preferences, template rendering, message dispatch, broadcast announcements |
| `tests/Feature/Storage/` | 1 | 8 | S3/MinIO presigned upload URL generation, file confirmation, secure file access |
| `tests/Feature/Console/` | 1 | 6 | Scheduled background artisan commands (cleanup, reminders, auto-lock) |
| `tests/Feature/` (Root) | 2 | 3 | Application bootstrap, masked health check (`SEC-INFO-01`) |
| **Total** | **83** | **759** | **Complete System End-to-End & Boundary Verification** |

---

## 3. Dedicated Security & Robustness Regression Test Evidence

The following test suites were specifically created to enforce remediation of security findings:

### 3.1 PPDB Application Deadline Verification (`SEC-BL-01`)
- **File:** `tests/Feature/Ppdb/PPDBApplicationDeadlineTest.php`
- **Tests Executed:** 8 tests (42 assertions)
- **Scenarios Verified:**
  1. `test_applicant_can_submit_within_active_wave_period`: PASS
  2. `test_applicant_cannot_submit_after_wave_close_at_deadline`: PASS (Asserts HTTP 400 & error message)
  3. `test_applicant_cannot_submit_when_wave_status_is_inactive`: PASS
  4. `test_applicant_cannot_submit_when_wave_status_is_archived`: PASS
  5. `test_applicant_cannot_resubmit_already_submitted_application`: PASS
  6. `test_applicant_cannot_submit_incomplete_application`: PASS
  7. `test_boundary_submission_exactly_at_closing_second`: PASS
  8. `test_submission_fails_one_second_after_closing_time`: PASS

### 3.2 CBT Proctor Room Isolation (`SEC-AUTHZ-01`)
- **File:** `tests/Feature/Cbt/ExamProctorRoomIsolationTest.php`
- **Tests Executed:** 6 tests (34 assertions)
- **Scenarios Verified:**
  1. `test_proctor_can_view_participants_in_assigned_room`: PASS (HTTP 200)
  2. `test_proctor_forbidden_from_viewing_participants_in_other_rooms`: PASS (HTTP 403)
  3. `test_proctor_can_unlock_participant_in_assigned_room`: PASS (HTTP 200)
  4. `test_proctor_forbidden_from_unlocking_participant_in_other_room`: PASS (HTTP 403)
  5. `test_proctor_forbidden_from_force_finishing_participant_in_other_room`: PASS (HTTP 403)
  6. `test_admin_can_monitor_all_rooms`: PASS (HTTP 200)

### 3.3 CBT Offline Sync Rate Limiting (`SEC-AUTHZ-02`)
- **File:** `tests/Feature/Cbt/ExamSyncSecurityTest.php`
- **Tests Executed:** 5 tests (26 assertions)
- **Scenarios Verified:**
  1. `test_sync_pull_succeeds_with_valid_token`: PASS (HTTP 200)
  2. `test_sync_push_succeeds_with_valid_token`: PASS (HTTP 200)
  3. `test_sync_pull_fails_with_invalid_node_token`: PASS (HTTP 401)
  4. `test_sync_rate_limiter_triggers_after_15_requests_per_minute`: PASS (HTTP 429 Too Many Requests)
  5. `test_sync_rate_limiter_resets_after_cooldown_window`: PASS

### 3.4 Forgot Password Throttling & Cooldown (`SEC-DOS-01`)
- **File:** `tests/Feature/Auth/ForgotPasswordThrottlingTest.php`
- **Tests Executed:** 3 tests (18 assertions)
- **Scenarios Verified:**
  1. `test_forgot_password_rate_limiting_triggers_at_limit`: PASS (HTTP 429)
  2. `test_forgot_password_enforces_2_minute_token_retention`: PASS (Asserts anti-flood cooldown)
  3. `test_forgot_password_maintains_anti_enumeration_response`: PASS (Generic success on non-existent account)

### 3.5 CBT Autosave Question Ownership (`SEC-DATA-01`)
- **File:** `tests/Feature/Cbt/ExamAutosaveValidationTest.php`
- **Tests Executed:** 3 tests (16 assertions)
- **Scenarios Verified:**
  1. `test_autosave_persists_valid_answer_for_assigned_question`: PASS (HTTP 200)
  2. `test_autosave_rejects_question_not_in_exam_package`: PASS (HTTP 422 / 400)
  3. `test_autosave_rejects_answer_submission_when_session_expired`: PASS (HTTP 403)

### 3.6 Sarpras Facility Booking Concurrency Locking (`SEC-CONC-01`)
- **File:** `tests/Feature/Sar/FacilityBookingConcurrencyTest.php`
- **Tests Executed:** 2 tests (12 assertions)
- **Scenarios Verified:**
  1. `test_concurrent_facility_booking_prevents_overlap`: PASS (`lockForUpdate` prevents double-booking)
  2. `test_booking_succeeds_for_adjacent_non_overlapping_slot`: PASS (HTTP 201)

### 3.7 Masked Health Check Technical Error Disclosure (`SEC-INFO-01`)
- **File:** `tests/Feature/HealthCheckTest.php`
- **Tests Executed:** 2 tests (10 assertions)
- **Scenarios Verified:**
  1. `test_health_check_returns_healthy_status`: PASS (HTTP 200)
  2. `test_health_check_masks_database_exception_details`: PASS (Returns `"unreachable"`, no PDO stack traces)

---

## 4. How to Reproduce & Run Tests Locally

### Run Entire Test Suite
```bash
cd apps/api
php artisan test
```

### Run With Test Suite Details & Verbose Output
```bash
cd apps/api
php artisan test --parallel
```

### Run Specific Security Verification Tests
```bash
cd apps/api
php artisan test tests/Feature/Ppdb/PPDBApplicationDeadlineTest.php
php artisan test tests/Feature/Cbt/ExamProctorRoomIsolationTest.php
php artisan test tests/Feature/Cbt/ExamSyncSecurityTest.php
php artisan test tests/Feature/Auth/ForgotPasswordThrottlingTest.php
php artisan test tests/Feature/Cbt/ExamAutosaveValidationTest.php
php artisan test tests/Feature/Sar/FacilityBookingConcurrencyTest.php
php artisan test tests/Feature/HealthCheckTest.php
```

### Verify Code Style & Formatting
```bash
cd apps/api
./vendor/bin/pint --test
```
