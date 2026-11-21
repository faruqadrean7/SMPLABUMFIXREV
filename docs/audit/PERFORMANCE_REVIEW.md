# SISTLABUM — Performance, Query Profiling & Scalability Review (Final)

> **Application:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11 / PostgreSQL 17)  
> **Evaluation Focus:** Query Efficiency, N+1 Elimination, Index Optimization & High-Load Architecture  
> **Status:** AUDITED & OPTIMIZED

---

## 1. Query Architecture & N+1 Prevention

### 1.1 Eager Loading Enforcement
A thorough inspection of Eloquent queries across all controllers confirmed that relationships are systematically eager-loaded using `with()` to prevent N+1 query cascades:
- **Student Directory (`StudentController@index`):** Eager loads `classStudents.class`, `guardians`, and `user` in a single joined query rather than querying per row.
- **CBT Exam Engine (`ExamTakingController@questions`):** Eager loads `packageItems.question` with options in one indexed query.
- **Attendance Summaries (`AttendanceController@summary`):** Utilizes SQL aggregate functions (`COUNT(CASE WHEN status = ... THEN 1 END)`) grouped by `class_id` rather than iterating through individual student attendance models.

---

## 2. Database Indexing Strategy

All high-frequency filter and foreign key columns are explicitly indexed in migrations:

| Table | Index Name | Indexed Columns | Query Purpose |
|---|---|---|---|
| `announcements` | `announcements_target_type_publish_at_index` | `target_type, publish_at` | Targeted announcements retrieval |
| `announcements` | `announcements_expires_at_index` | `expires_at` | Cleanup cron and active filtering |
| `notification_preferences` | `notification_preferences_user_id_index` | `user_id` | User preference lookup on message dispatch |
| `class_students` | `class_students_class_academic_unique` | `class_id, student_id, academic_year_id` | Unique enrollment constraint & fast join |
| `cbt_sync_logs` | `cbt_sync_logs_exam_id_index` | `exam_id` | Node sync status inquiry |
| `teaching_assignments` | `teaching_assignments_unique` | `teacher_id, subject_id, class_id, semester_id` | Schedule conflict lookup |
| `facility_bookings` | `facility_bookings_interval_index` | `facility_id, start_at, end_at` | Overlapping reservation conflict detection |

---

## 3. High-Load Traffic & Peak Event Architecture

### 3.1 CBT Exam Autosave & Heartbeats
During school-wide exams, hundreds of concurrent students submit answer autosaves every few seconds:
- **Lightweight Payloads:** `POST /api/v1/cbt/exams/{id}/save-answer` accepts minimal JSON (`question_id`, `answer`, `is_flagged`), bypassing unnecessary relationship loads.
- **Fast Upsert:** Uses `updateOrCreate` on `(exam_session_id, question_id)` with database-level primary keys.
- **Offline Node Sync:** For schools with unreliable internet, offline local servers buffer student answers and synchronize in batches via `/api/v1/cbt/sync/push`, protected by `throttle:15,1`.

### 3.2 PPDB Admission Registration Peaks
During admission wave openings:
- **Storage Offloading:** Document uploads do not stream multipart bytes through the PHP application server; clients upload directly to S3/MinIO using presigned PUT URLs (`/api/v1/files/presign`).
- **Dashboard Caching:** PPDB registration statistics are cached for 5 minutes (`Cache::remember('ppdb_stats_overview', 300, ...)`), preventing repetitive count and aggregation queries on the `ppdb_applications` table.

---

## 4. Scalability Recommendations for Staging & Production

1. **Redis Deployment:**
   - Configure `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis` in `.env`.
   - Separate Redis database indexes for cache (`CACHE_DB=1`) and queues (`QUEUE_DB=2`).
2. **PostgreSQL Connection Pool:**
   - Deploy PgBouncer in front of PostgreSQL to pool connections during CBT concurrency surges, keeping active backend connections stable.
3. **Queue Concurrency:**
   - Run 4 to 8 Supervisor queue worker processes (`php artisan queue:work redis --tries=3 --timeout=90`) to dispatch emails, push notifications, and PDF report cards asynchronously.
