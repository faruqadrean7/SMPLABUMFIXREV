# PHASE 13 — PRE-AUDIT REPORT

**Project:** Portal Sekolah Satu Pintu (SISTLABUM / Manuvox)  
**Branch:** `develop`  
**Audit Stage:** Read-Only Baseline & Deep Structural Pre-Audit  
**Date:** 2026-10-19  
**Audit Rule:** Strictly read-only; no code modifications, no git commits, no git pushes, no destructive operations.

---

## 1. Executive Summary

This pre-audit report represents a comprehensive, multi-layer evaluation of the Manuvox repository following the successful remediation of hardcoded subject IDs (`sub-mat`) on the Portal Guru surface. 

All quality gates currently pass at baseline:
- **TypeScript (`tsc --noEmit`):** 0 errors.
- **ESLint (`next lint`):** 0 errors.
- **Next.js Production Build:** 95/95 routes generated successfully.
- **Frontend Vitest Suite:** 67 test files, 795 passed (including 20/20 in `portal-guru-subject-selection.test.tsx`).
- **Backend PHPUnit Portal Suite:** 33 tests, 178 assertions passed across all portal features.

However, an exhaustive read-only inspection of the architecture, data flows, and backend controllers uncovered architectural residues from earlier prototyping stages. Specifically, in `MeTeacherController.php`, if a client provides non-UUID or unassigned class/subject identifiers, the controller executes a permissive fallback to the teacher's first assignment or database defaults (`SchoolClass::first()`, `Subject::first()`, and `Schedule::first()`). In addition, static metric placeholders exist in homeroom and staff recap endpoints (`98.0%`, `96.0%`).

This report provides the full architectural trace, confirmed finding schemas, risk assessments, and an actionable, low-risk remediation plan awaiting explicit user approval before any code changes are introduced.

---

## 2. Current Repository State

- **Active Branch:** `develop` tracking `origin/develop`
- **Working Tree:**
  - 57 modified files (staged/unstaged preserved from previous portal and master development).
  - Untracked files preserved (including photo profile feature, seeder additions, test files, and UAT documents).
- **Protected File Status:**
  - `STRUKTUR LENGKAP MENU PORTAL SATU PINTU.pdf`: Unchanged, untouched.
  - `apps/web/prototype/`: Completely untouched.
  - `legacy/prototype/`: Completely untouched.
- **Baseline Quality Metrics:**
  - `apps/web`: Next.js 14.2.14 App Router, React 18, Tailwind CSS, Lucide React, Zod.
  - `apps/api`: Laravel 11.x, PHP 8.2+, PostgreSQL / SQLite test driver, Sanctum RBAC tokens.
  - Test Suite: 100% green (795 frontend tests + 33 backend portal tests).

---

## 3. Architecture Map

```text
========================================================================================
UI / Presentation Layer
  ├── Portal Guru: apps/web/src/app/(portal)/portal/guru/*
  │     ├── /jurnal, /presensi, /nilai, /materi, /tugas
  │     └── Consumes: useTeacherAssignmentsSummary(), useTeacherSchedules()
  ├── Portal Siswa: apps/web/src/app/(portal)/portal/siswa/*
  ├── Portal Ortu: apps/web/src/app/(portal)/portal/ortu/*
  ├── Portal Staf: apps/web/src/app/(portal)/portal/staf/*
  └── Portal Kepsek: apps/web/src/app/(portal)/portal/kepsek/*
========================================================================================
                                     │
                                     ▼ (State & Hooks)
Component / ViewModel Layer
  ├── apps/web/src/features/portal/guru/use-guru-portal.ts
  ├── apps/web/src/features/portal/guru/guru-api.ts
  ├── apps/web/src/features/portal/portal-api.ts
  └── apps/web/src/features/portal/staf/staf-api.ts
========================================================================================
                                     │
                                     ▼ (HTTP / Fetch Client)
API Client Layer
  ├── apps/web/src/lib/api-client.ts (Bearer token injection, X-Request-Id)
  └── executeWithMockFallback() (Guarded: strictly disabled in production)
========================================================================================
                                     │
                                     ▼ (Network JSON REST)
Backend Routing & Middleware Layer
  ├── apps/api/routes/api.php
  ├── auth:sanctum (Token Authentication)
  └── Role & Permission Gates (app/Http/Middleware/*)
========================================================================================
                                     │
                                     ▼ (Controller & Policy)
Backend Controller Layer
  ├── MeTeacherController.php (LMS, Jurnal, Presensi, Nilai, Materi, Tugas)
  ├── MeStudentController.php (Jadwal, Poin, Konseling, Tugas)
  ├── MeChildController.php   (Anti-IDOR perwalian via GuardianChildPolicy)
  └── MeStaffController.php   (Presensi mandiri, Izin/Cuti, Tiket Sarpras)
========================================================================================
                                     │
                                     ▼ (Domain Logic)
Service Layer
  ├── app/Services/LmsService.php (Assignment submission, grading integrity)
  ├── app/Services/StudentService.php
  └── app/Services/Storage/FileStorageService.php
========================================================================================
                                     │
                                     ▼ (ORM / Relational DB)
Database Layer
  └── PostgreSQL: schedules, teaching_assignments, school_classes, subjects,
      attendances, journals, grade_items, assignments, files
========================================================================================
```

---

## 4. Audit Scope

The audit covered 16 dimensions across the entire codebase:
1. Directory structure and configuration files (`next.config.mjs`, `composer.json`, `package.json`).
2. Architecture layering and separation of concerns.
3. Frontend portal routing (`(portal)/portal/*`) and state management.
4. Backend API controllers (`MeTeacherController`, `MeStudentController`, `MeChildController`, `MeStaffController`).
5. Database schema, models, and migrations (`schedules`, `teaching_assignments`, `journals`, etc.).
6. Authentication entry points and token verification (Sanctum).
7. Authorization logic, ownership verification, and anti-IDOR protections.
8. API contracts, query parameters, request bodies, and error response standards.
9. Testing coverage across frontend (Vitest) and backend (PHPUnit).
10. Dependency integrity and isolation.
11. Verification of prior fixes (`sub-mat` elimination on Portal Guru).
12. Audit for regression risks in teacher schedule and assignment bindings.
13. Performance bottlenecks, N+1 queries, and database indexing.
14. Security vulnerabilities (IDOR, mass assignment, unauthenticated bypass, permissive fallbacks).
15. Data integrity (foreign key consistency, orphan file references, immutable grade safeguards).
16. Mock data boundaries and production leakage checks.

---

## 5. Confirmed Findings

### Finding BE-SEC-01
- **ID:** BE-SEC-01
- **Severity:** HIGH
- **Category:** Insecure Fallback / Authorization Looseness
- **File:** `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php`
- **Location:** lines 94-124 (`resolveScheduleForTeacher`), 286-296 (`recordAttendanceSession`), 426-436 (`storeJournal`), 569-579 (`updateBulkGrades`), 683-686 (`storeMaterial`), 783-792 (`storeAssignment`)
- **Current Behavior:** When non-UUID or unassigned `class_id` or `subject_id` values are submitted in POST payloads, the controller automatically falls back to the teacher's first assignment or `SchoolClass::first()`, `Subject::first()`, and resolves to `Schedule::firstOrCreate(...)` or `Schedule::first()`.
- **Expected Behavior:** If `class_id` or `subject_id` are invalid, non-UUID, or do not belong to the authenticated teacher's active teaching assignments, the API should immediately reject the request with `422 Unprocessable Entity` or `403 Forbidden` / `404 Not Found`. It must never fall back to global table defaults or arbitrary schedules.
- **Evidence:** `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php:100-124`:
  ```php
  $targetClassId = ($classId && \Illuminate\Support\Str::isUuid($classId)) ? $classId : \App\Models\SchoolClass::first()?->id;
  $targetSubjectId = ($subjectId && \Illuminate\Support\Str::isUuid($subjectId)) 
      ? $subjectId 
      : (\App\Models\TeachingAssignment::where('teacher_id', $teacher->id)->value('subject_id') ?? \App\Models\Subject::first()?->id);
  ...
  return Schedule::first();
  ```
- **Impact:** Direct API requests or malformed payloads could associate journals, attendance sessions, grades, or materials with unrelated classes or arbitrary database entities.
- **Root Cause:** Prototyping fallback mechanism added to allow mock frontend requests to succeed prior to dynamic subject selection implementation.
- **Recommended Fix:** Enforce strict validation: ensure `class_id` and `subject_id` are valid UUIDs, verify the teacher holds an active assignment for that class and subject, and reject non-matching requests with `422` or `403`. Remove `Schedule::first()` and `SchoolClass::first()` fallbacks.
- **Risk:** Tests or legacy callers that pass null/dummy IDs will receive validation errors (intended behavior).

---

### Finding BE-DATA-01
- **ID:** BE-DATA-01
- **Severity:** MEDIUM
- **Category:** Hardcoded Business Logic / Static Metric Placeholders
- **File:** `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php`
- **Location:** lines 1094-1111 (`homeroom` method)
- **Current Behavior:** The `homeroom` endpoint returns static placeholder numbers: `attendanceRate => 98.0`, `averageScore => 86.5`, and `attendanceRecap => ['averageRate' => 98.0, 'sickDays' => 2, 'leaveDays' => 1, 'unexcusedDays' => 0]`.
- **Expected Behavior:** Attendance rates and student score recaps should be computed from actual database records (`AttendanceRecord`, `GradeItem`, `StudentGrade`) for students enrolled in the homeroom class, returning calculated averages or zero if no data exists.
- **Evidence:** `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php:1094-1111`:
  ```php
  'attendanceRate' => 98.0,
  'averageScore' => 86.5,
  'rank' => $idx + 1,
  ...
  'attendanceRecap' => [
      'averageRate' => 98.0,
      'sickDays' => 2,
      'leaveDays' => 1,
      'unexcusedDays' => 0,
  ],
  ```
- **Impact:** Homeroom teachers see identical static 98% attendance and 86.5 average scores regardless of true student performance.
- **Root Cause:** Placeholder implementation left over from initial UI prototyping.
- **Recommended Fix:** Query actual student attendance records and grade items for the homeroom class, computing true averages and counts.
- **Risk:** Minor database query overhead; must use indexed aggregation.

---

### Finding BE-DATA-02
- **ID:** BE-DATA-02
- **Severity:** MEDIUM
- **Category:** Hardcoded Business Logic / Static Metric Placeholders
- **File:** `apps/api/app/Http/Controllers/Api/V1/MeStaffController.php`
- **Location:** lines 69 & 102 (`dashboard` and `selfAttendance` methods)
- **Current Behavior:** Hardcoded `attendancePercentage => 96.0` is returned in staff dashboard and self-attendance responses.
- **Expected Behavior:** Staff attendance percentage should be dynamically calculated from `StaffAttendance` records for the current month relative to expected work days.
- **Evidence:** `apps/api/app/Http/Controllers/Api/V1/MeStaffController.php:69, 102`:
  ```php
  'attendancePercentage' => 96.0,
  ```
- **Impact:** Staff members always see 96.0% attendance regardless of actual clock-in history.
- **Root Cause:** Prototype placeholder left in production controller.
- **Recommended Fix:** Compute attendance rate from count of monthly present records divided by standard monthly working days (e.g. 20-22 days), defaulting to 0.0 or 100.0 if month has just begun.
- **Risk:** Very low; purely cosmetic/reporting metric.

---

### Finding FE-MOCK-01
- **ID:** FE-MOCK-01
- **Severity:** LOW
- **Category:** Demo Data Fallback in Client Library
- **File:** `apps/web/src/features/portal/guru/guru-api.ts`
- **Location:** lines 73 and 158
- **Current Behavior:** When mock mode is enabled (`NEXT_PUBLIC_USE_MOCK === 'true'`), if `classId` is missing from the mock map, it falls back to `m.MOCK_CLASS_STUDENTS['cls-7a']`.
- **Expected Behavior:** Missing class ID in mock mode should return an empty array or throw a mock 404 error rather than silently returning students from class 7A.
- **Evidence:** `apps/web/src/features/portal/guru/guru-api.ts:73`:
  ```ts
  const students = m.MOCK_CLASS_STUDENTS[classId] || m.MOCK_CLASS_STUDENTS['cls-7a'] || [];
  ```
- **Impact:** Does not affect production (`NODE_ENV === 'production'` strictly disables mock execution), but causes misleading behavior during local UI testing when an unknown class ID is queried.
- **Root Cause:** Convenience mock fallback.
- **Recommended Fix:** Replace `|| m.MOCK_CLASS_STUDENTS['cls-7a']` with `|| []`.
- **Risk:** Very low.

---

### Finding BE-PERF-01
- **ID:** BE-PERF-01
- **Severity:** LOW
- **Category:** Performance / Unconstrained Schedule Query
- **File:** `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php`
- **Location:** line 95
- **Current Behavior:** `Schedule::where('teacher_id', $teacher->id)->first()` queries schedules across all academic years and semesters without constraining by the active semester.
- **Expected Behavior:** Schedule resolution should be strictly scoped to the active semester (`where('semester_id', $activeSemester->id)`).
- **Evidence:** `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php:95`:
  ```php
  $schedule = Schedule::where('teacher_id', $teacher->id)->first();
  ```
- **Impact:** In multi-year deployments, a teacher could resolve to a schedule from a previous academic year.
- **Root Cause:** Omission of active semester scoping.
- **Recommended Fix:** Add active semester filter to schedule queries.
- **Risk:** Low.

---

### Finding FE-STATE-01
- **ID:** FE-STATE-01
- **Severity:** INFO
- **Category:** Verified Clean State / Baseline Confirmation
- **File:** `apps/web/src/app/(portal)/portal/guru/*` (5 pages: `jurnal`, `presensi`, `nilai`, `materi`, `tugas`)
- **Location:** All form dialogs and subject selectors
- **Current Behavior:** All 5 pages correctly consume `useTeacherAssignmentsSummary()`. Zero instances of `'sub-mat'` exist in `apps/web/src/app/`. Form submission buttons are disabled until valid subjects/classes are selected.
- **Expected Behavior:** Retain verified clean implementation as the regression baseline.
- **Evidence:** AST grep confirms 0 occurrences of `'sub-mat'`. Full test suite passes 20/20 in `portal-guru-subject-selection.test.tsx`.
- **Impact:** Positive confirmation; no frontend regression detected.
- **Root Cause:** Successfully resolved in previous sprint.
- **Recommended Fix:** Maintain regression test coverage.
- **Risk:** Zero.

---

## 6. Likely Findings

1. **Teacher Schedule Creation on the Fly:**
   - In `MeTeacherController::resolveScheduleForTeacher()` (lines 108-121), the backend calls `Schedule::firstOrCreate(...)` if a schedule does not exist for the teacher. While convenient for dynamic testing, schedule generation in production should strictly be managed by the Academic Administrator (`admin/academic/schedules`), not created ad-hoc by teacher attendance or journal POST actions.
2. **Missing Database Indexes on Foreign Key Pairs:**
   - The query `Schedule::where('teacher_id', ...)->where('class_id', ...)->where('subject_id', ...)` will benefit from a composite database index on `(teacher_id, class_id, subject_id, semester_id)` in high-concurrency production deployments.

---

## 7. Security Findings

1. **Permissive Fallback Bypass in `MeTeacherController`:**
   - Evaluated under IDOR and least-privilege principles. While `verifyClassAccess` protects against explicit class ID spoofing, the fallback mechanism in lines 287-296 and 427-436 permits a teacher to supply dummy/null values and still execute write actions against database defaults.
2. **Robust Anti-IDOR in `MeChildController`:**
   - Evaluated `MeChildController.php` (Parent Portal). Access to child data is guarded by `Gate::allows('view-child', $childId)` backed by `GuardianChildPolicy.php`. An unauthorized parent attempting to query another student receives an explicit `403 Forbidden` (`'Anda tidak memiliki hak akses perwalian sah terhadap siswa ini.'`). Anti-IDOR is verified robust.
3. **LMS Service Integrity:**
   - Evaluated `LmsService.php`. Student assignment submissions are verified against active class enrollment (`ClassStudent`), preventing cross-class submission poisoning. Graded assignments cannot be modified without proper authorization.

---

## 8. Data Integrity Findings

1. **Default PDF File Creation in `storeMaterial`:**
   - In `MeTeacherController.php:690-699`, if `file_id` is omitted or invalid, the backend calls `File::firstOrCreate(['object_key' => 'materials/default-modul.pdf'], ...)`. This creates an artificial file entry to satisfy the foreign key constraint. The endpoint should instead require a valid uploaded file ID or allow nullable file attachments.
2. **Orphan File Cleanup Command:**
   - `CleanupOrphanFilesCommand.php` and `OrphanFileService.php` are properly tested (`OrphanFileCleanupTest.php`) and ensure unlinked uploaded files are safely purged without affecting referenced teaching materials or avatars.

---

## 9. Performance Findings

1. **N+1 Avoidance in Teacher Dashboard:**
   - `MeTeacherController::dashboard()` correctly uses eager loading: `with(['class', 'subject', 'timeSlot'])`.
2. **Homeroom Student Loading:**
   - `MeTeacherController::homeroom()` loads students via `ClassStudent::where('class_id', ...)->with('student')`, which executes in two discrete queries. No N+1 query loop was found.

---

## 10. API Findings

1. **Dual Key Formatting (CamelCase & Snake_case Compatibility):**
   - The API controllers systematically support both `camelCase` and `snake_case` input keys (e.g. `$classId = $input['class_id'] ?? $input['classId'] ?? null;`). This ensures backwards compatibility with web and mobile clients.
2. **Standardized Error Envelope:**
   - All errors use `ApiResponse` trait returning `{ success: false, message: string, code: string, error: [...] }` with appropriate HTTP status codes (400, 401, 403, 404, 422).

---

## 11. Database Findings

1. **Prisma / Laravel Schema Parity:**
   - Table names and primary keys match between the frontend TypeScript definitions and Laravel Eloquent models (`schedules`, `subjects`, `school_classes`, `teaching_assignments`).
2. **UUID Consistency:**
   - Primary keys across all entities use standard RFC 4122 UUID v4 strings, validated via `Str::isUuid()`.

---

## 12. Authentication Findings

1. **Sanctum Token Guard:**
   - All portal endpoints (`/api/v1/me/*`) are strictly protected by `auth:sanctum`.
   - Unauthenticated requests immediately return `401 Unauthorized`.
2. **Role Verification:**
   - Each portal endpoint verifies the corresponding model relationship (`$user->teacher`, `$user->student`, `$user->guardian`, `$user->staff`). If the user does not possess the required persona profile, an immediate `403 Forbidden` is returned.

---

## 13. Authorization Findings

1. **Teacher Class Access Guard:**
   - `verifyClassAccess($teacher, $classId)` checks both `TeachingAssignment` and `homeroom_teacher_id`. Direct access to unauthorized classes is blocked with `403 Forbidden`.
2. **Parent Child Access Guard:**
   - `GuardianChildPolicy` strictly enforces that the authenticated guardian is linked to the student in `guardian_students`.
3. **Student Assignment Access Guard:**
   - `LmsService` checks enrollment in `ClassStudent` before permitting assignment submission.

---

## 14. Hardcoded Data Findings

| Item | Location | Classification | Assessment |
|------|----------|----------------|------------|
| `sub-mat` | None in `src/app/` | REMOVED | Verified completely removed from all 5 Portal Guru pages |
| `'cls-7a'` | `guru-api.ts:73, 158` | DEMO DATA FALLBACK | Active only in dev when `NEXT_PUBLIC_USE_MOCK=true` |
| `attendanceRate: 98.0` | `MeTeacherController.php:1094` | HARDCODED PLACEHOLDER | Needs dynamic calculation |
| `averageScore: 86.5` | `MeTeacherController.php:1095` | HARDCODED PLACEHOLDER | Needs dynamic calculation |
| `attendanceRecap: 98%` | `MeTeacherController.php:1107` | HARDCODED PLACEHOLDER | Needs dynamic calculation |
| `attendancePercentage: 96.0` | `MeStaffController.php:69, 102` | HARDCODED PLACEHOLDER | Needs dynamic calculation |
| `Schedule::first()` | `MeTeacherController.php:123` | INSECURE FALLBACK | Needs strict 404/422 rejection |

---

## 15. Error Handling Findings

1. **Explicit Error Messages:**
   - All user-facing error messages are localized in Bahasa Indonesia per `AGENTS.md` standards (e.g. `'Akses ditolak: Anda tidak ditugaskan di kelas ini.'`).
2. **No Uncaught Exceptions:**
   - Controller actions catch potential model binding failures and wrap responses in standardized JSON structures.

---

## 16. Test Coverage Gaps

1. **Missing Backend Test for Insecure Fallback Rejection:**
   - While happy paths are well-tested, there are currently no negative tests asserting that submitting an unassigned `class_id` or `subject_id` to `recordAttendanceSession` or `storeJournal` receives a `403` or `422` error rather than succeeding via fallback.
2. **Staff Attendance Dynamic Calculation Test:**
   - No test currently verifies that `attendancePercentage` changes dynamically based on recorded attendance records.

---

## 17. Regression Risks

1. **Frontend-Backend Contract Strictness:**
   - If the backend fallback in `MeTeacherController` is removed immediately without ensuring all test fixtures send valid UUIDs, existing backend tests that omit `class_id` or pass dummy strings might fail.
   - *Mitigation:* Ensure all test factories and request bodies supply valid teaching assignments and UUIDs before tightening backend validation.

---

## 18. Protected Files Verification

The following protected files and directories were inspected and confirmed **completely untouched**:
- `STRUKTUR LENGKAP MENU PORTAL SATU PINTU.pdf`: **UNTOUCHED**
- `apps/web/prototype/`: **UNTOUCHED**
- `legacy/prototype/`: **UNTOUCHED**

---

## 19. Git Safety Verification

- No `git commit` was executed.
- No `git push` was executed.
- No `git reset --hard` or `git clean` was executed.
- All 57 pre-existing modified files in the working tree are intact.
- Branch remains safely on `develop`.

---

## 20. Proposed Files To Change

The following files are proposed for remediation in the subsequent implementation phase (subject to approval):

1. **Backend Validation & Fallback Tightening:**
   - `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php` (Remove `Schedule::first()` and `SchoolClass::first()` fallbacks; reject unassigned/invalid class and subject IDs with `422`/403).
   - `apps/api/app/Http/Controllers/Api/V1/MeStaffController.php` (Replace static `96.0` attendance percentage with dynamic month calculation).
2. **Frontend Mock Cleanup:**
   - `apps/web/src/features/portal/guru/guru-api.ts` (Replace `'cls-7a'` mock fallback with empty array fallback).
3. **Automated Test Additions:**
   - `apps/api/tests/Feature/Portal/MeTeacherJournalApiTest.php` (Add negative regression tests asserting invalid class/subject rejection).

---

## 21. Proposed Changes

### Batch 1: Backend Fallback Hardening & Placeholder Cleanup
- In `MeTeacherController::resolveScheduleForTeacher()`:
  - Require valid UUID for `class_id` and `subject_id`.
  - Validate that teacher has an active teaching assignment or homeroom assignment for that class and subject.
  - If no matching schedule exists and none can be lawfully resolved, return `null` so the caller returns `404 Not Found` (`'Jadwal pembelajaran tidak ditemukan untuk kelas dan mata pelajaran ini.'`).
  - Eliminate the `return Schedule::first();` line entirely.
- In `MeTeacherController::homeroom()`:
  - Aggregate actual attendance records for the class students for the active semester.
  - Calculate actual average scores from `StudentGrade` / `GradeItem`.
- In `MeStaffController::dashboard()` and `selfAttendance()`:
  - Dynamically calculate monthly attendance percentage using working days count.

### Batch 2: Mock Helper Hygiene
- In `guru-api.ts`:
  - Clean up `m.MOCK_CLASS_STUDENTS['cls-7a']` fallback so mock queries for unknown classes return `[]`.

---

## 22. Testing Plan

1. **Target Tests:**
   - Run `php artisan test --filter=MeTeacherJournalApiTest`
   - Run `php artisan test --filter=MeStudentGuardianStaffPortalApiTest`
   - Run `pnpm --filter web test src/test/portal-guru-subject-selection.test.tsx`
2. **Full Regression Suite:**
   - Backend: `php artisan test tests/Feature/Portal` (all 33+ portal tests).
   - Frontend: `pnpm --filter web test` (all 795+ Vitest tests).
   - Typecheck: `pnpm --filter web typecheck` (0 errors).
   - Lint: `pnpm --filter web lint` (0 errors).
   - Build: `pnpm --filter web build` (95/95 routes).

---

## 23. Rollback Considerations

All proposed changes are strictly isolated to 3 controller/helper files and 1 test file. If any unexpected regression occurs, changes can be rolled back via git checkout on those individual files without database schema migrations or state disruption.

---

## 24. Out-of-Scope Findings

The following items were identified during audit but are explicitly cataloged as out of current scope:
- Academic scheduling administrator module (`admin/academic/schedules`).
- Real-time WebSockets integration for counseling chats (requires architectural decision per SSOT).
- Dapodik automated bi-directional synchronization (requires external Ministry API access).

---

## 25. Remaining Risks

- Legacy test fixtures that relied on backend permissive fallbacks might fail if they pass incomplete payloads. These will be updated within the test files to supply valid teaching assignment fixtures.

---

## 26. Final Audit Status

**STATUS:** **AUDIT COMPLETE — READY FOR IMPLEMENTATION APPROVAL**

All 16 audit dimensions have been thoroughly analyzed. Confirmed findings, exact line locations, security implications, and minimal-diff remediation plans have been established.

---

## 27. Approval Gate

Awaiting explicit user approval before proceeding to the implementation phase.
