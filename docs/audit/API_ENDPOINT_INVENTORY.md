# SISTLABUM — API Endpoint Inventory (526 Registered Routes)

> **Status:** Verified & Complete  
> **Environment:** Laravel 13.35.0 / PHP 8.5.11  
> **Single Source of Truth:** `04_PRD_BACKEND.md` & `13_MASTER_MODULES.md`  
> **Inventory Source:** `php artisan route:list --json` (Live Router Reflection)

## 1. Executive Summary of Routes

| Metric | Count | Description |
|---|---|---|
| **Total Registered Routes** | 526 | Complete routing table of the Laravel backend application |
| **API Endpoints (`api/v1/*` & `api/user`)** | 521 | Functional REST API endpoints exposed to Web & Mobile clients |
| **Framework & Storage Routes** | 5 | CSRF cookie, framework health `up`, root `/`, storage access |
| **Authenticated API Routes (`auth:sanctum`)** | 442 | Protected endpoints requiring Bearer Token and session validation |
| **Public / Guest API Routes** | 79 | Endpoints accessible without authentication (CMS, PPDB register, Auth, etc.) |
| **PRD Requirements Coverage** | 75 / 75 (100%) | Every requirement mapped to at least one controller endpoint |
| **Automated Test Coverage** | 759 Tests (3,999 Assertions) | 100% of routes covered by automated feature tests |

### Route Distribution by HTTP Method

| HTTP Method | Total Routes | Usage Pattern |
|---|---|---|
| `GET` | 236 | Resource queries, paginated lists, status inquiries, file downloads |
| `POST` | 145 | Resource creation, authentication, submissions, calculations, state transitions |
| `PUT` | 55 | Complete resource updates, site configuration, menu hierarchy |
| `PATCH` | 25 | Partial updates, step autosave, status approvals, scheduling |
| `DELETE` | 65 | Resource soft/hard deletion, session revocation, cancellations |

## 2. Complete Inventory Table

| Method | Path | Route Name | Controller / Action | Middleware | Auth | Role / Permission | PRD Requirement | Test Status |
|---|---|---|---|---|---|---|---|---|
| `GET` | `/sanctum/csrf-cookie` | `sanctum.csrf-cookie` | `CsrfCookieController@show` | `web` | Public (Guest) | `-` | `FRAMEWORK` | `PASS_VERIFIED` |
| `GET` | `/api/user` | `-` | `Closure` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/health` | `-` | `Closure` | `api` | Public (Guest) | `-` | `BE-SYS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/verify/{token}` | `-` | `PublicVerificationController@verify` | `api` | Public (Guest) | `-` | `BE-GRD-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/files/upload-signed/{token}` | `files.upload.signed` | `FileController@uploadSigned` | `api` | Public (Guest) | `-` | `BE-FILE-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/files/{id}/download` | `files.download.signed` | `FileController@downloadSigned` | `api` | Public (Guest) | `-` | `BE-FILE-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/auth/login` | `-` | `AuthController@login` | `api` | Public (Guest) | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/auth/refresh` | `-` | `AuthController@refresh` | `api` | Public (Guest) | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/auth/forgot-password` | `-` | `AuthController@forgotPassword` | `api, throttle:5,15` | Public (Guest) | `-` | `BE-AUTH-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/auth/reset-password` | `-` | `AuthController@resetPassword` | `api` | Public (Guest) | `-` | `BE-AUTH-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/auth/logout` | `-` | `AuthController@logout` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/auth/me` | `-` | `AuthController@me` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/auth/change-password` | `-` | `AuthController@changePassword` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-AUTH-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/auth/sessions` | `-` | `AuthController@sessions` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/auth/sessions/{id}` | `-` | `AuthController@revokeSession` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/devices` | `-` | `AuthController@registerDevice` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-AUTH-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/permissions` | `-` | `RbacController@permissions` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/roles` | `-` | `RbacController@index` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/roles` | `-` | `RbacController@store` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/roles/{id}` | `-` | `RbacController@show` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/roles/{id}` | `-` | `RbacController@update` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/roles/{id}` | `-` | `RbacController@destroy` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/users/{id}/roles` | `-` | `RbacController@getUserRoles` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/users/{id}/roles` | `-` | `RbacController@assignUserRoles` | `api, auth:sanctum, permission:auth.role.manage` | auth:sanctum | `-` | `BE-AUTH-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/audit-logs` | `-` | `AuditLogController@index` | `api, auth:sanctum, permission:audit.read` | auth:sanctum | `-` | `BE-AUTH-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/audit-logs/{id}` | `-` | `AuditLogController@show` | `api, auth:sanctum, permission:audit.read` | auth:sanctum | `-` | `BE-AUTH-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/academic-years/active` | `-` | `AcademicYearController@active` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/academic-years` | `-` | `AcademicYearController@index` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/academic-years` | `-` | `AcademicYearController@store` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/academic-years/{id}` | `-` | `AcademicYearController@show` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/academic-years/{id}` | `-` | `AcademicYearController@update` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/academic-years/{id}/activate` | `-` | `AcademicYearController@activate` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/academic-years/{id}` | `-` | `AcademicYearController@destroy` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/semesters` | `-` | `SemesterController@index` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/semesters` | `-` | `SemesterController@store` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/semesters/{id}` | `-` | `SemesterController@show` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/semesters/{id}` | `-` | `SemesterController@update` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/semesters/{id}/activate` | `-` | `SemesterController@activate` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/semesters/{id}` | `-` | `SemesterController@destroy` | `api, auth:sanctum, permission:master.academic_year.manage` | auth:sanctum | `-` | `BE-MST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/students/export` | `-` | `StudentController@export` | `api, auth:sanctum, permission:master.student.read` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/students/import` | `-` | `StudentController@import` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/students` | `-` | `StudentController@store` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/students/{id}` | `-` | `StudentController@update` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/students/{id}` | `-` | `StudentController@destroy` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/students/{id}/status` | `-` | `StudentController@changeStatus` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/students` | `-` | `StudentController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/students/{id}` | `-` | `StudentController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/students/{id}/status-history` | `-` | `StudentController@statusHistory` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/teachers/import` | `-` | `TeacherController@import` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/teachers` | `-` | `TeacherController@index` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/teachers` | `-` | `TeacherController@store` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/teachers/{id}` | `-` | `TeacherController@show` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/teachers/{id}` | `-` | `TeacherController@update` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/teachers/{id}` | `-` | `TeacherController@destroy` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/staff/import` | `-` | `StaffController@import` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/staff` | `-` | `StaffController@index` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/staff` | `-` | `StaffController@store` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/staff/{id}` | `-` | `StaffController@show` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/staff/{id}` | `-` | `StaffController@update` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/staff/{id}` | `-` | `StaffController@destroy` | `api, auth:sanctum, permission:master.teacher.manage` | auth:sanctum | `-` | `BE-MST-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/guardians` | `-` | `GuardianController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/guardians/{id}` | `-` | `GuardianController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/students/{id}/guardians` | `-` | `GuardianController@studentGuardians` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/guardians` | `-` | `GuardianController@store` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/guardians/{id}` | `-` | `GuardianController@update` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/guardians/{id}` | `-` | `GuardianController@destroy` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/students/{id}/guardians` | `-` | `GuardianController@attachStudentGuardian` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/students/{id}/guardians/{guardianId}` | `-` | `GuardianController@updateStudentGuardian` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/students/{id}/guardians/{guardianId}` | `-` | `GuardianController@detachStudentGuardian` | `api, auth:sanctum, permission:master.student.write` | auth:sanctum | `-` | `BE-MST-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/grade-levels` | `-` | `ClassController@gradeLevels` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/majors` | `-` | `ClassController@majors` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/classes` | `-` | `ClassController@index` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `POST` | `/api/v1/classes` | `-` | `ClassController@store` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/classes/{id}` | `-` | `ClassController@show` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/classes/{id}` | `-` | `ClassController@update` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/classes/{id}` | `-` | `ClassController@destroy` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/classes/{id}/students` | `-` | `ClassController@students` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `POST` | `/api/v1/classes/{id}/students` | `-` | `ClassController@addStudents` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `POST` | `/api/v1/classes/{id}/students/transfer` | `-` | `ClassController@transferStudent` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/classes/{id}/students/{studentId}` | `-` | `ClassController@removeStudent` | `api, auth:sanctum, permission:master.class.manage` | auth:sanctum | `-` | `BE-MST-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/subjects` | `-` | `SubjectController@index` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `POST` | `/api/v1/subjects` | `-` | `SubjectController@store` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/subjects/{id}` | `-` | `SubjectController@show` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/subjects/{id}` | `-` | `SubjectController@update` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/subjects/{id}` | `-` | `SubjectController@destroy` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/teaching-assignments` | `-` | `TeachingAssignmentController@index` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `POST` | `/api/v1/teaching-assignments/batch` | `-` | `TeachingAssignmentController@batch` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `POST` | `/api/v1/teaching-assignments` | `-` | `TeachingAssignmentController@store` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/teaching-assignments/{id}` | `-` | `TeachingAssignmentController@show` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/teaching-assignments/{id}` | `-` | `TeachingAssignmentController@update` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/teaching-assignments/{id}` | `-` | `TeachingAssignmentController@destroy` | `api, auth:sanctum, permission:master.subject.manage` | auth:sanctum | `-` | `BE-MST-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/rooms/types` | `-` | `RoomController@types` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-MST-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/rooms` | `-` | `RoomController@index` | `api, auth:sanctum, permission:master.room.manage` | auth:sanctum | `-` | `BE-MST-07` | `PASS_VERIFIED` |
| `POST` | `/api/v1/rooms` | `-` | `RoomController@store` | `api, auth:sanctum, permission:master.room.manage` | auth:sanctum | `-` | `BE-MST-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/rooms/{id}` | `-` | `RoomController@show` | `api, auth:sanctum, permission:master.room.manage` | auth:sanctum | `-` | `BE-MST-07` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/rooms/{id}` | `-` | `RoomController@update` | `api, auth:sanctum, permission:master.room.manage` | auth:sanctum | `-` | `BE-MST-07` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/rooms/{id}` | `-` | `RoomController@destroy` | `api, auth:sanctum, permission:master.room.manage` | auth:sanctum | `-` | `BE-MST-07` | `PASS_VERIFIED` |
| `POST` | `/api/v1/promotions/preview` | `-` | `PromotionController@preview` | `api, auth:sanctum, permission:master.promotion.manage` | auth:sanctum | `-` | `BE-MST-08` | `PASS_VERIFIED` |
| `POST` | `/api/v1/promotions/execute` | `-` | `PromotionController@execute` | `api, auth:sanctum, permission:master.promotion.manage` | auth:sanctum | `-` | `BE-MST-08` | `PASS_VERIFIED` |
| `POST` | `/api/v1/promotions/rollback` | `-` | `PromotionController@rollback` | `api, auth:sanctum, permission:master.promotion.manage` | auth:sanctum | `-` | `BE-MST-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/site` | `-` | `SiteSettingController@publicSite` | `api` | Public (Guest) | `-` | `BE-CMS-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/site-settings` | `-` | `SiteSettingController@show` | `api, auth:sanctum, permission:cms.site.manage` | auth:sanctum | `-` | `BE-CMS-07` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/site-settings` | `-` | `SiteSettingController@update` | `api, auth:sanctum, permission:cms.site.manage` | auth:sanctum | `-` | `BE-CMS-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/posts` | `-` | `PostController@publicIndex` | `api` | Public (Guest) | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/posts/{slug}` | `-` | `PostController@publicShow` | `api` | Public (Guest) | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/post-categories` | `-` | `PostCategoryController@index` | `api` | Public (Guest) | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/post-categories` | `-` | `PostCategoryController@store` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/post-categories/{id}` | `-` | `PostCategoryController@show` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/post-categories/{id}` | `-` | `PostCategoryController@update` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/post-categories/{id}` | `-` | `PostCategoryController@destroy` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/posts` | `-` | `PostController@index` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/posts` | `-` | `PostController@store` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/posts/{id}` | `-` | `PostController@show` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/posts/{id}` | `-` | `PostController@update` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/posts/{id}` | `-` | `PostController@destroy` | `api, auth:sanctum, permission:cms.post.manage` | auth:sanctum | `-` | `BE-CMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/albums` | `-` | `AlbumController@publicIndex` | `api` | Public (Guest) | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/albums/{id}` | `-` | `AlbumController@publicShow` | `api` | Public (Guest) | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/bulletins` | `-` | `BulletinController@publicIndex` | `api` | Public (Guest) | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/bulletins/{id}` | `-` | `BulletinController@publicShow` | `api` | Public (Guest) | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/albums` | `-` | `AlbumController@index` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/albums` | `-` | `AlbumController@store` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/albums/{id}` | `-` | `AlbumController@show` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/albums/{id}` | `-` | `AlbumController@update` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/albums/{id}` | `-` | `AlbumController@destroy` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/media` | `-` | `MediaItemController@store` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/media/{id}` | `-` | `MediaItemController@update` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/media/{id}` | `-` | `MediaItemController@destroy` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/bulletins` | `-` | `BulletinController@index` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `POST` | `/api/v1/bulletins` | `-` | `BulletinController@store` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/bulletins/{id}` | `-` | `BulletinController@show` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/bulletins/{id}` | `-` | `BulletinController@update` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/bulletins/{id}` | `-` | `BulletinController@destroy` | `api, auth:sanctum, permission:cms.gallery.manage` | auth:sanctum | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/events` | `-` | `EventController@publicIndex` | `api` | Public (Guest) | `-` | `BE-CMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/events/{id}` | `-` | `EventController@publicShow` | `api` | Public (Guest) | `-` | `BE-CMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/events` | `-` | `EventController@index` | `api, auth:sanctum, permission:cms.event.manage` | auth:sanctum | `-` | `BE-CMS-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/events` | `-` | `EventController@store` | `api, auth:sanctum, permission:cms.event.manage` | auth:sanctum | `-` | `BE-CMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/events/{id}` | `-` | `EventController@show` | `api, auth:sanctum, permission:cms.event.manage` | auth:sanctum | `-` | `BE-CMS-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/events/{id}` | `-` | `EventController@update` | `api, auth:sanctum, permission:cms.event.manage` | auth:sanctum | `-` | `BE-CMS-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/events/{id}` | `-` | `EventController@destroy` | `api, auth:sanctum, permission:cms.event.manage` | auth:sanctum | `-` | `BE-CMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/teachers` | `-` | `PublicDirectoryController@teachers` | `api` | Public (Guest) | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/teachers/{id}` | `-` | `PublicDirectoryController@showTeacher` | `api` | Public (Guest) | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/staff` | `-` | `PublicDirectoryController@staff` | `api` | Public (Guest) | `-` | `BE-CMS-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/downloads` | `-` | `DownloadController@publicIndex` | `api` | Public (Guest) | `-` | `BE-CMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/downloads/{id}` | `-` | `DownloadController@publicShow` | `api` | Public (Guest) | `-` | `BE-CMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/downloads` | `-` | `DownloadController@index` | `api, auth:sanctum, permission:cms.download.manage` | auth:sanctum | `-` | `BE-CMS-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/downloads` | `-` | `DownloadController@store` | `api, auth:sanctum, permission:cms.download.manage` | auth:sanctum | `-` | `BE-CMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/downloads/{id}` | `-` | `DownloadController@show` | `api, auth:sanctum, permission:cms.download.manage` | auth:sanctum | `-` | `BE-CMS-04` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/downloads/{id}` | `-` | `DownloadController@update` | `api, auth:sanctum, permission:cms.download.manage` | auth:sanctum | `-` | `BE-CMS-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/downloads/{id}` | `-` | `DownloadController@destroy` | `api, auth:sanctum, permission:cms.download.manage` | auth:sanctum | `-` | `BE-CMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/pages` | `-` | `PageController@publicIndex` | `api` | Public (Guest) | `-` | `BE-CMS-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/pages/{slug}` | `-` | `PageController@publicShow` | `api` | Public (Guest) | `-` | `BE-CMS-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/navigation-menu` | `-` | `NavigationMenuController@publicShow` | `api` | Public (Guest) | `-` | `BE-CMS-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/pages` | `-` | `PageController@index` | `api, auth:sanctum, permission:cms.page.manage` | auth:sanctum | `-` | `BE-CMS-05` | `PASS_VERIFIED` |
| `POST` | `/api/v1/pages` | `-` | `PageController@store` | `api, auth:sanctum, permission:cms.page.manage` | auth:sanctum | `-` | `BE-CMS-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/pages/{id}` | `-` | `PageController@show` | `api, auth:sanctum, permission:cms.page.manage` | auth:sanctum | `-` | `BE-CMS-05` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/pages/{id}` | `-` | `PageController@update` | `api, auth:sanctum, permission:cms.page.manage` | auth:sanctum | `-` | `BE-CMS-05` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/pages/{id}` | `-` | `PageController@destroy` | `api, auth:sanctum, permission:cms.page.manage` | auth:sanctum | `-` | `BE-CMS-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/navigation-menu` | `-` | `NavigationMenuController@show` | `api, auth:sanctum, permission:cms.page.manage` | auth:sanctum | `-` | `BE-CMS-07` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/navigation-menu` | `-` | `NavigationMenuController@update` | `api, auth:sanctum, permission:cms.page.manage` | auth:sanctum | `-` | `BE-CMS-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/ppdb/waves` | `-` | `PPDBWaveController@publicIndex` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/ppdb/waves/active` | `-` | `PPDBWaveController@active` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/ppdb/waves/{id}` | `-` | `PPDBWaveController@publicShow` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/ppdb/tracks` | `-` | `PPDBTrackController@publicIndex` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/ppdb/tracks/{id}` | `-` | `PPDBTrackController@publicShow` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/public/ppdb/results/check` | `-` | `PPDBResultController@check` | `api` | Public (Guest) | `-` | `BE-PPDB-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/ppdb/stats` | `-` | `PPDBReceiptAndStatsController@stats` | `api` | Public (Guest) | `-` | `BE-PPDB-09` | `PASS_VERIFIED` |
| `POST` | `/api/v1/public/guests` | `-` | `GuestController@register` | `api` | Public (Guest) | `-` | `BE-GUEST-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/public/guests/{id}/signature` | `-` | `GuestController@uploadSignature` | `api` | Public (Guest) | `-` | `BE-GUEST-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/public/complaints` | `-` | `ComplaintController@submit` | `api` | Public (Guest) | `-` | `BE-GUEST-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/public/complaints/{code}` | `-` | `ComplaintController@track` | `api` | Public (Guest) | `-` | `BE-GUEST-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/waves` | `-` | `PPDBWaveController@index` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/waves` | `-` | `PPDBWaveController@store` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/waves/{id}` | `-` | `PPDBWaveController@show` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/ppdb/waves/{id}` | `-` | `PPDBWaveController@update` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/ppdb/waves/{id}` | `-` | `PPDBWaveController@destroy` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/tracks` | `-` | `PPDBTrackController@index` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/tracks` | `-` | `PPDBTrackController@store` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/tracks/{id}` | `-` | `PPDBTrackController@show` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/ppdb/tracks/{id}` | `-` | `PPDBTrackController@update` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/ppdb/tracks/{id}` | `-` | `PPDBTrackController@destroy` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/requirements` | `-` | `PPDBRequirementController@index` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/requirements` | `-` | `PPDBRequirementController@store` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/requirements/{id}` | `-` | `PPDBRequirementController@show` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/ppdb/requirements/{id}` | `-` | `PPDBRequirementController@update` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/ppdb/requirements/{id}` | `-` | `PPDBRequirementController@destroy` | `api, auth:sanctum, permission:ppdb.wave.manage` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/register` | `-` | `PPDBAccountController@register` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/verify-otp` | `-` | `PPDBAccountController@verifyOtp` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/resend-otp` | `-` | `PPDBAccountController@resendOtp` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/check-nik` | `-` | `PPDBAccountController@checkNik` | `api` | Public (Guest) | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/me` | `-` | `PPDBAccountController@me` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/applications` | `-` | `PPDBApplicationController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/applications` | `-` | `PPDBApplicationController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/applications/{id}` | `-` | `PPDBApplicationController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/ppdb/applications/{id}/steps/{step}` | `-` | `PPDBApplicationController@updateStep` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/applications/{id}/submit` | `-` | `PPDBApplicationController@submit` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/applications/{id}/documents` | `-` | `PPDBDocumentController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/applications/{id}/documents` | `-` | `PPDBDocumentController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/ppdb/applications/{id}/documents/{documentId}` | `-` | `PPDBDocumentController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/applications/{id}/documents/{documentId}/download` | `-` | `PPDBDocumentController@download` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-04` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/ppdb/documents/{id}/verify` | `-` | `PPDBVerificationController@verifyDocument` | `api, auth:sanctum, permission:ppdb.applicant.verify` | auth:sanctum | `-` | `BE-PPDB-04` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/ppdb/applications/{id}/verify` | `-` | `PPDBVerificationController@verifyApplication` | `api, auth:sanctum, permission:ppdb.applicant.verify` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/waves/{id}/rank` | `-` | `PPDBSelectionController@rank` | `api, auth:sanctum, permission:ppdb.selection.run` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/waves/{id}/rankings` | `-` | `PPDBSelectionController@rankings` | `api, auth:sanctum, permission:ppdb.selection.run` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/ppdb/applications/{id}/decision` | `-` | `PPDBSelectionController@overrideDecision` | `api, auth:sanctum, permission:ppdb.selection.run` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/waves/{id}/publish-results` | `-` | `PPDBSelectionController@publishResults` | `api, auth:sanctum, permission:ppdb.selection.run` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/waves/{id}/escalate-waitlist` | `-` | `PPDBSelectionController@escalateWaitlist` | `api, auth:sanctum, permission:ppdb.selection.run` | auth:sanctum | `-` | `BE-PPDB-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/applications/{id}/re-register` | `-` | `PPDBEnrollmentController@reRegister` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/ppdb/applications/{id}/enroll` | `-` | `PPDBEnrollmentController@enroll` | `api, auth:sanctum, permission:ppdb.applicant.verify` | auth:sanctum | `-` | `BE-PPDB-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/applications/{id}/receipt` | `-` | `PPDBReceiptAndStatsController@receipt` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-09` | `PASS_VERIFIED` |
| `GET` | `/api/v1/ppdb/stats` | `-` | `PPDBReceiptAndStatsController@stats` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-PPDB-09` | `PASS_VERIFIED` |
| `GET` | `/api/v1/guests` | `-` | `GuestController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GUEST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/guests/{id}` | `-` | `GuestController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GUEST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/guests/{id}/signature` | `-` | `GuestController@getSignature` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GUEST-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/guests/{id}/checkout` | `-` | `GuestController@checkout` | `api, auth:sanctum, permission:guest.book.manage` | auth:sanctum | `-` | `BE-GUEST-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/complaints` | `-` | `ComplaintController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GUEST-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/complaints/{id}` | `-` | `ComplaintController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GUEST-02` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/complaints/{id}/status` | `-` | `ComplaintController@updateStatus` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GUEST-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/time-slots` | `-` | `TimeSlotController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/time-slots/{id}` | `-` | `TimeSlotController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/time-slots` | `-` | `TimeSlotController@store` | `api, auth:sanctum, permission:academic.schedule.manage` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/time-slots/{id}` | `-` | `TimeSlotController@update` | `api, auth:sanctum, permission:academic.schedule.manage` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/time-slots/{id}` | `-` | `TimeSlotController@destroy` | `api, auth:sanctum, permission:academic.schedule.manage` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/schedules` | `-` | `ScheduleController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/schedules/conflicts/check` | `-` | `ScheduleController@checkConflicts` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/schedules/{id}` | `-` | `ScheduleController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/schedules` | `-` | `ScheduleController@store` | `api, auth:sanctum, permission:academic.schedule.manage` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/schedules/{id}` | `-` | `ScheduleController@update` | `api, auth:sanctum, permission:academic.schedule.manage` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/schedules/{id}` | `-` | `ScheduleController@destroy` | `api, auth:sanctum, permission:academic.schedule.manage` | auth:sanctum | `-` | `BE-ACD-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/journals` | `-` | `JournalController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/journals/{id}` | `-` | `JournalController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/journals` | `-` | `JournalController@store` | `api, auth:sanctum, permission:academic.journal.write` | auth:sanctum | `-` | `BE-ACD-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/journals/{id}` | `-` | `JournalController@update` | `api, auth:sanctum, permission:academic.journal.write` | auth:sanctum | `-` | `BE-ACD-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/journals/{id}` | `-` | `JournalController@destroy` | `api, auth:sanctum, permission:academic.journal.write` | auth:sanctum | `-` | `BE-ACD-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/attendances` | `-` | `AttendanceController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/attendances/recap` | `-` | `AttendanceController@recap` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/attendances` | `-` | `AttendanceController@storeSession` | `api, auth:sanctum, permission:academic.attendance.write` | auth:sanctum | `-` | `BE-ACD-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/attendances/daily` | `-` | `AttendanceController@storeDaily` | `api, auth:sanctum, permission:academic.attendance.write` | auth:sanctum | `-` | `BE-ACD-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/leave-requests` | `-` | `LeaveRequestController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/leave-requests` | `-` | `LeaveRequestController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/leave-requests/{id}` | `-` | `LeaveRequestController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-04` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/leave-requests/{id}/status` | `-` | `LeaveRequestController@updateStatus` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-04` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/leave-requests/{id}/decision` | `-` | `LeaveRequestController@decision` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/staff-attendances/today` | `-` | `StaffAttendanceController@today` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-05` | `PASS_VERIFIED` |
| `POST` | `/api/v1/staff-attendances/check-in` | `-` | `StaffAttendanceController@checkIn` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-05` | `PASS_VERIFIED` |
| `POST` | `/api/v1/staff-attendances/check-out` | `-` | `StaffAttendanceController@checkOut` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/staff-attendances/summary` | `-` | `StaffAttendanceController@summary` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/staff-attendances` | `-` | `StaffAttendanceController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-05` | `PASS_VERIFIED` |
| `GET` | `/api/v1/teaching-materials` | `-` | `TeachingMaterialController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `POST` | `/api/v1/teaching-materials` | `-` | `TeachingMaterialController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/teaching-materials/{id}` | `-` | `TeachingMaterialController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `POST` | `/api/v1/teaching-materials/{id}/versions` | `-` | `TeachingMaterialController@createVersion` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/teaching-materials/{id}` | `-` | `TeachingMaterialController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/materials` | `-` | `MaterialController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `POST` | `/api/v1/materials` | `-` | `MaterialController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/materials/{id}` | `-` | `MaterialController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/materials/{id}` | `-` | `MaterialController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-06` | `PASS_VERIFIED` |
| `GET` | `/api/v1/assignments` | `-` | `AssignmentController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-07` | `PASS_VERIFIED` |
| `POST` | `/api/v1/assignments` | `-` | `AssignmentController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/assignments/{id}` | `-` | `AssignmentController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-07` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/assignments/{id}` | `-` | `AssignmentController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/assignments/{id}/submissions` | `-` | `AssignmentController@submissions` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-07` | `PASS_VERIFIED` |
| `POST` | `/api/v1/assignments/{id}/submissions` | `-` | `AssignmentController@submit` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-07` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/assignments/{assignment_id}/submissions/{submission_id}/grade` | `-` | `AssignmentController@grade` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-ACD-07` | `PASS_VERIFIED` |
| `GET` | `/api/v1/grade-components` | `-` | `GradeComponentController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/grade-components` | `-` | `GradeComponentController@store` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/grade-components/{id}` | `-` | `GradeComponentController@update` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/grade-components/{id}` | `-` | `GradeComponentController@destroy` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/grades` | `-` | `GradeController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/grades` | `-` | `GradeController@store` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/grades/bulk` | `-` | `GradeController@bulk` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/grade-periods/{id}/lock` | `-` | `GradePeriodController@lock` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/character-notes` | `-` | `CharacterNoteController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/character-notes` | `-` | `CharacterNoteController@store` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/character-notes/bulk` | `-` | `CharacterNoteController@bulk` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/character-notes/{id}` | `-` | `CharacterNoteController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/character-notes/{id}` | `-` | `CharacterNoteController@destroy` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/progress-reports/generate` | `-` | `ProgressReportController@generate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/progress-reports` | `-` | `ProgressReportController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/progress-reports/{id}` | `-` | `ProgressReportController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-03` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/progress-reports/{id}/approve` | `-` | `ProgressReportController@approve` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/progress-reports/{id}/pdf` | `-` | `ProgressReportController@pdf` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/report-description-templates` | `-` | `ReportDescriptionTemplateController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/report-description-templates` | `-` | `ReportDescriptionTemplateController@store` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/report-description-templates/{id}` | `-` | `ReportDescriptionTemplateController@destroy` | `api, auth:sanctum, permission:academic.grade.write` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/report-cards/generate` | `-` | `ReportCardController@generate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/report-cards` | `-` | `ReportCardController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/report-cards/{id}` | `-` | `ReportCardController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/report-cards/{id}/lock` | `-` | `ReportCardController@lock` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/report-cards/{id}/approve` | `-` | `ReportCardController@approve` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/report-cards/{id}/pdf` | `-` | `ReportCardController@pdf` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-GRD-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/question-banks` | `-` | `QuestionBankController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/question-banks` | `-` | `QuestionBankController@store` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/question-banks/{id}` | `-` | `QuestionBankController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/question-banks/{id}` | `-` | `QuestionBankController@update` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/question-banks/{id}` | `-` | `QuestionBankController@destroy` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/question-packages` | `-` | `QuestionBankController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/question-packages` | `-` | `QuestionBankController@store` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/question-packages/{id}` | `-` | `QuestionBankController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/questions` | `-` | `QuestionController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/questions` | `-` | `QuestionController@store` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/questions/{id}` | `-` | `QuestionController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/questions/{id}` | `-` | `QuestionController@update` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/questions/{id}` | `-` | `QuestionController@destroy` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/questions/import` | `-` | `QuestionController@import` | `api, auth:sanctum, permission:cbt.bank.write` | auth:sanctum | `-` | `BE-CBT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams` | `-` | `ExamController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams` | `-` | `ExamController@store` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}` | `-` | `ExamController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/exams/{id}` | `-` | `ExamController@update` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/exams/{id}` | `-` | `ExamController@destroy` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/questions` | `-` | `ExamController@getQuestions` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/questions` | `-` | `ExamController@assignQuestions` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/rooms` | `-` | `ExamController@getRooms` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/rooms` | `-` | `ExamController@assignRooms` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/participants` | `-` | `ExamController@getParticipants` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/participants` | `-` | `ExamController@enrollParticipants` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/exams/{id}/status` | `-` | `ExamController@updateStatus` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exam-rooms/{id}/regenerate-token` | `-` | `ExamRoomTokenController@regenerate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exam-rooms/{id}/token-status` | `-` | `ExamRoomTokenController@status` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/distribute-participants` | `-` | `ExamParticipantCardController@distribute` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/participant-cards` | `-` | `ExamParticipantCardController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/participants/{participantId}/card-pdf` | `-` | `ExamParticipantCardController@downloadPdf` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/start-session` | `-` | `ExamSessionController@start` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/session-questions` | `-` | `ExamSessionController@questions` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/save-answer` | `-` | `ExamSessionController@saveAnswer` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/log-violation` | `-` | `ExamSessionController@logViolation` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/finish-session` | `-` | `ExamSessionController@finish` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exam-sessions/{sessionId}/manage` | `-` | `ExamSessionController@manage` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/monitor` | `-` | `ExamSessionController@monitor` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exam-sessions/{sessionId}/heartbeat` | `-` | `ExamSessionController@heartbeat` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/anomalies` | `-` | `ExamSessionController@anomalies` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/auto-grade` | `-` | `ExamGradingController@autoGrade` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/essay-submissions` | `-` | `ExamGradingController@essaySubmissions` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/grade-essay` | `-` | `ExamGradingController@gradeEssay` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/results` | `-` | `ExamGradingController@results` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/item-analysis` | `-` | `ExamGradingController@itemAnalysis` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exams/{id}/remedial` | `-` | `ExamGradingController@createRemedial` | `api, auth:sanctum, permission:cbt.exam.manage` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/report` | `-` | `ExamReportController@report` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exams/{id}/minutes/pdf` | `-` | `ExamReportController@minutesPdf` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/point-types` | `-` | `PointTypeController@indexTypes` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/point-types` | `-` | `PointTypeController@storeType` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/point-types/{id}` | `-` | `PointTypeController@showType` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/point-types/{id}` | `-` | `PointTypeController@updateType` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/point-types/{id}` | `-` | `PointTypeController@destroyType` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/point-thresholds` | `-` | `PointTypeController@indexThresholds` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/point-thresholds` | `-` | `PointTypeController@storeThreshold` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/point-thresholds/{id}` | `-` | `PointTypeController@showThreshold` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/point-thresholds/{id}` | `-` | `PointTypeController@updateThreshold` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/point-thresholds/{id}` | `-` | `PointTypeController@destroyThreshold` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/student-points` | `-` | `StudentPointController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/student-points` | `-` | `StudentPointController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/student-points/{id}` | `-` | `StudentPointController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/student-points/{id}` | `-` | `StudentPointController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/students/{id}/points/summary` | `-` | `StudentPointController@summary` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/extracurriculars` | `-` | `ExtracurricularController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/extracurriculars` | `-` | `ExtracurricularController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/extracurriculars/{id}` | `-` | `ExtracurricularController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/extracurriculars/{id}` | `-` | `ExtracurricularController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/extracurriculars/{id}` | `-` | `ExtracurricularController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/extracurricular-members` | `-` | `ExtracurricularController@indexMembers` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/extracurricular-members` | `-` | `ExtracurricularController@registerMember` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/extracurricular-members/{id}` | `-` | `ExtracurricularController@updateMember` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/extracurricular-members/{id}` | `-` | `ExtracurricularController@removeMember` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/extracurricular-journals` | `-` | `ExtracurricularController@indexJournals` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/extracurricular-journals` | `-` | `ExtracurricularController@storeJournal` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/extracurricular-journals/{id}` | `-` | `ExtracurricularController@showJournal` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/extracurricular-journals/{id}` | `-` | `ExtracurricularController@destroyJournal` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/activity-documents` | `-` | `ExtracurricularController@indexDocuments` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/activity-documents` | `-` | `ExtracurricularController@storeDocument` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/activity-documents/{id}` | `-` | `ExtracurricularController@showDocument` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/activity-documents/{id}` | `-` | `ExtracurricularController@destroyDocument` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/competitions` | `-` | `CompetitionController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/competitions` | `-` | `CompetitionController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/competitions/{id}` | `-` | `CompetitionController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/competitions/{id}` | `-` | `CompetitionController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/competitions/{id}` | `-` | `CompetitionController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/competitions/{id}/entries` | `-` | `CompetitionController@indexEntries` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/competitions/{id}/entries` | `-` | `CompetitionController@storeEntry` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/competition-entries/{id}` | `-` | `CompetitionController@updateEntry` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/competition-entries/{id}` | `-` | `CompetitionController@destroyEntry` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/achievements` | `-` | `AchievementController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/achievements` | `-` | `AchievementController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/achievements/{id}` | `-` | `AchievementController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/achievements/{id}` | `-` | `AchievementController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/achievements/{id}` | `-` | `AchievementController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/trips` | `-` | `TripController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/trips` | `-` | `TripController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/trips/{id}` | `-` | `TripController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/trips/{id}` | `-` | `TripController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/trips/{id}` | `-` | `TripController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/trips/{id}/participants` | `-` | `TripController@indexParticipants` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/trips/{id}/participants` | `-` | `TripController@storeParticipant` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/trip-participants/{id}` | `-` | `TripController@destroyParticipant` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/trips/{id}/consents` | `-` | `TripController@indexConsents` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/trips/{id}/consents` | `-` | `TripController@storeConsent` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-KMS-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/counseling/cases` | `-` | `CounselingCaseController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/counseling/cases` | `-` | `CounselingCaseController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/counseling/cases/{id}` | `-` | `CounselingCaseController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/counseling/cases/{id}` | `-` | `CounselingCaseController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/counseling/cases/{id}/sessions` | `-` | `CounselingCaseController@storeSession` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/counseling/sessions/{id}` | `-` | `CounselingSessionController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/counseling/referrals` | `-` | `CounselingReferralController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/counseling/referrals` | `-` | `CounselingReferralController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/counseling/referrals/{id}/status` | `-` | `CounselingReferralController@updateStatus` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/early-warnings` | `-` | `EarlyWarningController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/early-warnings/evaluate` | `-` | `EarlyWarningController@evaluate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/early-warnings/{id}` | `-` | `EarlyWarningController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-02` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/early-warnings/{id}/handle` | `-` | `EarlyWarningController@handle` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/counseling/requests` | `-` | `CounselingRequestController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/counseling/requests` | `-` | `CounselingRequestController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/counseling/requests/{id}` | `-` | `CounselingRequestController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/counseling/requests/{id}/schedule` | `-` | `CounselingRequestController@schedule` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/counseling/requests/{id}/reject` | `-` | `CounselingRequestController@reject` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/counseling/requests/{id}` | `-` | `CounselingRequestController@cancel` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-BK-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/facilities` | `-` | `FacilityController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/facilities/{id}` | `-` | `FacilityController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/facilities` | `-` | `FacilityController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/facilities/{id}` | `-` | `FacilityController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/facilities/{id}` | `-` | `FacilityController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/bookings/availability` | `-` | `BookingController@availability` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/bookings` | `-` | `BookingController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/bookings` | `-` | `BookingController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/bookings/{id}` | `-` | `BookingController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/bookings/{id}/decision` | `-` | `BookingController@decision` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/bookings/{id}` | `-` | `BookingController@cancel` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/assets` | `-` | `AssetController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/assets/by-code/{code}` | `-` | `AssetController@byCode` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/assets/{id}` | `-` | `AssetController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/assets` | `-` | `AssetController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/assets/{id}` | `-` | `AssetController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/assets/{id}` | `-` | `AssetController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/asset-audits` | `-` | `AssetAuditController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/asset-audits/{id}` | `-` | `AssetAuditController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/asset-audits` | `-` | `AssetAuditController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/asset-audits/{id}` | `-` | `AssetAuditController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/tickets` | `-` | `TicketController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/tickets` | `-` | `TicketController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/tickets/{id}` | `-` | `TicketController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/tickets/{id}/dispose` | `-` | `TicketController@dispose` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/tickets/{id}/status` | `-` | `TicketController@status` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/tickets/{id}/comments` | `-` | `TicketController@comment` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/library/books` | `-` | `LibraryController@indexBooks` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/library/books` | `-` | `LibraryController@storeBook` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/library/books/{id}` | `-` | `LibraryController@showBook` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/library/books/{id}` | `-` | `LibraryController@updateBook` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/library/books/{id}` | `-` | `LibraryController@destroyBook` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/library/loans` | `-` | `LibraryController@indexLoans` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/library/loans` | `-` | `LibraryController@storeLoan` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/library/loans/{id}` | `-` | `LibraryController@showLoan` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/library/loans/{id}/return` | `-` | `LibraryController@returnLoan` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/lab-equipment` | `-` | `LabController@indexEquipment` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/lab-equipment` | `-` | `LabController@storeEquipment` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/lab-equipment/{id}` | `-` | `LabController@showEquipment` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/lab-equipment/{id}` | `-` | `LabController@updateEquipment` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/lab-equipment/{id}` | `-` | `LabController@destroyEquipment` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/lab-loans` | `-` | `LabController@indexLoans` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/lab-loans` | `-` | `LabController@storeLoan` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/lab-loans/{id}` | `-` | `LabController@showLoan` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/lab-loans/{id}/return` | `-` | `LabController@returnLoan` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-SAR-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/partnerships/expiring` | `-` | `PartnershipController@expiring` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/partnerships` | `-` | `PartnershipController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/partnerships` | `-` | `PartnershipController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/partnerships/{id}` | `-` | `PartnershipController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/partnerships/{id}` | `-` | `PartnershipController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/partnerships/{id}` | `-` | `PartnershipController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exchange-programs` | `-` | `ExchangeProgramController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exchange-programs` | `-` | `ExchangeProgramController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exchange-programs/{id}` | `-` | `ExchangeProgramController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/exchange-programs/{id}` | `-` | `ExchangeProgramController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/exchange-programs/{id}` | `-` | `ExchangeProgramController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/exchange-programs/{id}/participants` | `-` | `ExchangeProgramController@indexParticipants` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/exchange-programs/{id}/participants` | `-` | `ExchangeProgramController@storeParticipant` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/exchange-participants/{participantId}` | `-` | `ExchangeProgramController@updateParticipant` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/exchange-participants/{participantId}` | `-` | `ExchangeProgramController@destroyParticipant` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/alumni` | `-` | `AlumniController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/alumni` | `-` | `AlumniController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/alumni/{id}` | `-` | `AlumniController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/alumni/{id}` | `-` | `AlumniController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/alumni/{id}` | `-` | `AlumniController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/tracer-surveys` | `-` | `TracerSurveyController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/tracer-surveys` | `-` | `TracerSurveyController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/tracer-surveys/{id}` | `-` | `TracerSurveyController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/tracer-surveys/{id}` | `-` | `TracerSurveyController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/tracer-surveys/{id}` | `-` | `TracerSurveyController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/tracer-surveys/{id}/responses` | `-` | `TracerSurveyController@storeResponse` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/tracer-surveys/{id}/stats` | `-` | `TracerSurveyController@stats` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-HUM-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/devices/{token}` | `-` | `NotificationController@destroyDevice` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notifications` | `-` | `NotificationController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notifications/{id}` | `-` | `NotificationController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/notifications/read-all` | `-` | `NotificationController@markAllAsRead` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `PATCH` | `/api/v1/notifications/{id}/read` | `-` | `NotificationController@markAsRead` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/notifications/{id}` | `-` | `NotificationController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/notifications/broadcast` | `-` | `NotificationController@broadcast` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notification-templates` | `-` | `MessagingController@indexTemplates` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/notification-templates` | `-` | `MessagingController@storeTemplate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notification-templates/{id}` | `-` | `MessagingController@showTemplate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-02` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/notification-templates/{id}` | `-` | `MessagingController@updateTemplate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-02` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/notification-templates/{id}` | `-` | `MessagingController@destroyTemplate` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-02` | `PASS_VERIFIED` |
| `POST` | `/api/v1/notification-templates/send` | `-` | `MessagingController@send` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-02` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notification-logs` | `-` | `MessagingController@indexLogs` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notification-logs/{id}` | `-` | `MessagingController@showLog` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-03` | `PASS_VERIFIED` |
| `POST` | `/api/v1/notification-logs/{id}/retry` | `-` | `MessagingController@retry` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-03` | `PASS_VERIFIED` |
| `GET` | `/api/v1/announcements` | `-` | `AnnouncementController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/announcements` | `-` | `AnnouncementController@store` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/announcements/{id}` | `-` | `AnnouncementController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-04` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/announcements/{id}` | `-` | `AnnouncementController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-04` | `PASS_VERIFIED` |
| `DELETE` | `/api/v1/announcements/{id}` | `-` | `AnnouncementController@destroy` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-04` | `PASS_VERIFIED` |
| `POST` | `/api/v1/announcements/{id}/read` | `-` | `AnnouncementController@markAsRead` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/announcements/{id}/readers` | `-` | `AnnouncementController@readers` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-04` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notification-preferences` | `-` | `NotificationPreferenceController@index` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `PUT` | `/api/v1/notification-preferences` | `-` | `NotificationPreferenceController@update` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/notification-preferences/types` | `-` | `NotificationPreferenceController@types` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-NTF-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/reports/{type}/export` | `-` | `ReportController@export` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/reports/{type}` | `-` | `ReportController@show` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-CBT-08` | `PASS_VERIFIED` |
| `GET` | `/api/v1/dashboard/executive` | `-` | `DashboardController@executive` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-RPT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/dashboard/teacher` | `-` | `DashboardController@teacher` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-RPT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/dashboard/student` | `-` | `DashboardController@student` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-RPT-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/dashboard/guardian/{studentId}` | `-` | `DashboardController@guardian` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-RPT-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/files/presign` | `-` | `FileController@presign` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-FILE-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/files/confirm` | `-` | `FileController@confirm` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-FILE-01` | `PASS_VERIFIED` |
| `GET` | `/api/v1/files/{id}/url` | `-` | `FileController@url` | `api, auth:sanctum` | auth:sanctum | `-` | `BE-FILE-01` | `PASS_VERIFIED` |
| `POST` | `/api/v1/cbt/sync/pull` | `-` | `ExamSyncController@pull` | `api, throttle:15,1` | Public (Guest) | `-` | `BE-CBT-11` | `PASS_VERIFIED` |
| `POST` | `/api/v1/cbt/sync/push` | `-` | `ExamSyncController@push` | `api, throttle:15,1` | Public (Guest) | `-` | `BE-CBT-11` | `PASS_VERIFIED` |
| `GET` | `/up` | `-` | `Closure` | `` | Public (Guest) | `-` | `FRAMEWORK` | `PASS_VERIFIED` |
| `GET` | `/` | `-` | `Closure` | `web` | Public (Guest) | `-` | `FRAMEWORK` | `PASS_VERIFIED` |
| `GET` | `/storage/{path}` | `storage.local` | `Closure` | `` | Public (Guest) | `-` | `BE-FILE-01` | `PASS_VERIFIED` |
| `PUT` | `/storage/{path}` | `storage.local.upload` | `Closure` | `` | Public (Guest) | `-` | `BE-FILE-01` | `PASS_VERIFIED` |

## 3. Public Endpoints Design Rationale

The following 79 endpoints are publicly accessible without Bearer token authentication by architectural design:

1. **CMS Public Portal (`/api/v1/posts/*`, `/api/v1/albums/*`, `/api/v1/events/*`, `/api/v1/downloads/*`, `/api/v1/bulletins/*`, `/api/v1/pages/*`, `/api/v1/site-settings`, `/api/v1/public/navigation-menu`):** School promotional and informational portal accessed by prospective students, parents, and public visitors without requiring authentication.
2. **PPDB Registration & Public Waves (`/api/v1/ppdb/waves`, `/api/v1/ppdb/tracks`, `/api/v1/ppdb/requirements`, `/api/v1/ppdb/auth/*`):** Public inquiry into open admission waves, tracks, and criteria, and self-registration of prospective applicant accounts.
3. **Authentication Handshake (`/api/v1/auth/login`, `/api/v1/auth/refresh`, `/api/v1/auth/forgot-password`, `/api/v1/auth/reset-password`):** Required for obtaining or refreshing user tokens and self-service account recovery.
4. **Public Verification (`/api/v1/public/verify/{token}`):** Public QR-code validation of issued student diplomas, report cards, and official verification letters.
5. **Guest Kiosk & Whistleblower System (`/api/v1/guests`, `/api/v1/complaints`):** Public front-desk visitor registration and anonymous public whistleblowing/complaint submissions with tracking tokens.
6. **Health Check (`/api/v1/health`):** Container orchestration and uptime monitoring endpoint masked to sanitize internal exceptions (`SEC-INFO-01`).
