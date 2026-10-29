# Matriks Inventaris Rute Tanpa Guard Permission — SISTLABUM

- **Dokumen**: `docs/audit/ROUTE_GUARD_MATRIX.md`
- **Tanggal**: 27 Oktober 2026
- **Status Audit**: FASE 0 (Baseline & Inventaris)
- **Target Tindak Lanjut**: FASE 1 (Keamanan Kritis P0) & FASE 2 (Integritas Backend)
- **Rujukan Temuan**: `LAPORAN_AUDIT_SISTEM.md` (Temuan 🔴 SEC-01 & 🟠 SEC-02)
- **Kepatuhan Aturan**: `AGENTS.md` §6.3 (*"Setiap endpoint deny-by-default: tanpa guard permission = tidak boleh dirilis."*)

---

## 1. Ringkasan Eksekutif & Statistik Rute

| Kategori Rute | Jumlah | Persentase | Status Keamanan |
|---|:---:|:---:|---|
| Total Rute Terdaftar di Aplikasi (`apps/api`) | **695** | 100% | Basis seluruh endpoint API |
| Rute di bawah Guard Autentikasi (`auth:sanctum`) | **642** | 92.4% | Wajib login / token Sanctum |
| Rute Sanctum **SUDAH** Memiliki Guard `permission:` | **227** | 35.4% | Aman (Deny-by-default di layer rute aktif) |
| Rute Sanctum **BELUM** Memiliki Guard `permission:` (SEC-01) | **415** | 64.6% | **Risiko SEC-01**: Otorisasi hanya di layer service |

### Distribusi Rute Tanpa Guard Layer Rute (Total 415 Rute)
- 🔴 **Prioritas 0 (P0 - Keamanan Kritis & Mutasi Sensitif)**: **64 endpoint**
  - Notifikasi broadcast massal & log pengiriman ber-PII
  - Dashboard eksekutif, rekapitulasi laporan agregat & antrean approval
  - Catatan konseling BK, deteksi dini early warning, dan permohonan konseling
  - Penguncian & penerbitan rapor (progress report & e-rapor semester)
  - Manajemen sesi CBT, live monitor pengawas, dan hasil nilai ujian
  - Pengaturan sanksi & ambang batas poin pelanggaran
- 👤 **Self-Service & Persona-Scoped**: **94 endpoint**
  - Portal siswa (`/me/*`), portal orang tua (`/me/children/*`), portal guru mandiri (`/me/teacher/*`), dan portal staf (`/me/staff/*`)
  - Dilindungi oleh pemeriksaan kepemilikan data (IDOR policy) di level model & service, namun perlu standardisasi kontrak.
- ⚙️ **Operasional Domain & Master Data (P1/P2)**: **253 endpoint**
  - Sarpras (fasilitas, aset, tiket, perpustakaan, laboratorium)
  - Humas (kemitraan, alumni, tracer study)
  - Kesiswaan (ekskul, kompetisi, prestasi, study trip)
  - Akademik (jadwal, presensi harian, materi ajar)
- 📡 **Telemetri Klien & Utilitas Sistem**: **4 endpoint**
  - Ingestion audit log klien (`POST /audit-logs` - SEC-02) & signed URL generator

---

## 2. Prioritas 0 (P0) — Endpoint Kritis & Mutasi Sensitif (Eksekusi Wajib FASE 1)

> Endpoint di bawah ini memiliki risiko tertinggi terhadap kebocoran privasi (UU PDP), eskalasi hak akses pimpinan, integritas nilai rapor, dan integritas ujian CBT. Wajib dipasangi guard `permission:` di layer rute pada FASE 1.

| Method | Path | Controller@Method | Permission Saat Ini | Permission Usulan | Justifikasi Risiko |
|---|---|---|---|---|---|
| `GET` | `/api/v1/approvals/pending` | `Rpt\ApprovalController@pending` | `(none) / auth:sanctum` | **`executive.approval.manage`** | Kritis: Eksekusi antrean persetujuan pimpinan |
| `POST` | `/api/v1/approvals/{id}/review` | `Rpt\ApprovalController@review` | `(none) / auth:sanctum` | **`executive.approval.manage`** | Kritis: Eksekusi antrean persetujuan pimpinan |
| `GET` | `/api/v1/counseling/cases` | `Bk\CounselingCaseController@index` | `(none) / auth:sanctum` | **`counseling.case.read`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `POST` | `/api/v1/counseling/cases` | `Bk\CounselingCaseController@store` | `(none) / auth:sanctum` | **`counseling.case.write`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `GET` | `/api/v1/counseling/cases/{id}` | `Bk\CounselingCaseController@show` | `(none) / auth:sanctum` | **`counseling.case.read`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `PUT` | `/api/v1/counseling/cases/{id}` | `Bk\CounselingCaseController@update` | `(none) / auth:sanctum` | **`counseling.case.write`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `POST` | `/api/v1/counseling/cases/{id}/sessions` | `Bk\CounselingCaseController@storeSession` | `(none) / auth:sanctum` | **`counseling.case.write`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `GET` | `/api/v1/counseling/referrals` | `Bk\CounselingReferralController@index` | `(none) / auth:sanctum` | **`counseling.case.read`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `POST` | `/api/v1/counseling/referrals` | `Bk\CounselingReferralController@store` | `(none) / auth:sanctum` | **`counseling.case.write`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `PATCH` | `/api/v1/counseling/referrals/{id}/status` | `Bk\CounselingReferralController@updateStatus` | `(none) / auth:sanctum` | **`counseling.case.write`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `GET` | `/api/v1/counseling/requests` | `Bk\CounselingRequestController@index` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Manajemen permohonan konseling |
| `POST` | `/api/v1/counseling/requests` | `Bk\CounselingRequestController@store` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Manajemen permohonan konseling |
| `DELETE` | `/api/v1/counseling/requests/{id}` | `Bk\CounselingRequestController@cancel` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Manajemen permohonan konseling |
| `GET` | `/api/v1/counseling/requests/{id}` | `Bk\CounselingRequestController@show` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Manajemen permohonan konseling |
| `PATCH` | `/api/v1/counseling/requests/{id}/complete` | `Bk\CounselingRequestController@complete` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Manajemen permohonan konseling |
| `PATCH` | `/api/v1/counseling/requests/{id}/reject` | `Bk\CounselingRequestController@reject` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Manajemen permohonan konseling |
| `PATCH` | `/api/v1/counseling/requests/{id}/schedule` | `Bk\CounselingRequestController@schedule` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Manajemen permohonan konseling |
| `GET` | `/api/v1/counseling/sessions/{id}` | `Bk\CounselingSessionController@show` | `(none) / auth:sanctum` | **`counseling.case.read`** | Sangat Sensitif: Perlindungan catatan konseling anak (UU PDP) |
| `GET` | `/api/v1/dashboard/executive` | `Rpt\DashboardController@executive` | `(none) / auth:sanctum` | **`dashboard.executive`** | Kritis: Akses data ringkasan analitik eksekutif |
| `GET` | `/api/v1/early-warnings` | `Bk\EarlyWarningController@index` | `(none) / auth:sanctum` | **`counseling.case.read`** | Sangat Sensitif: Data deteksi dini risiko siswa |
| `POST` | `/api/v1/early-warnings/evaluate` | `Bk\EarlyWarningController@evaluate` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Data deteksi dini risiko siswa |
| `GET` | `/api/v1/early-warnings/summary` | `Bk\EarlyWarningController@summary` | `(none) / auth:sanctum` | **`counseling.case.read`** | Sangat Sensitif: Data deteksi dini risiko siswa |
| `GET` | `/api/v1/early-warnings/{id}` | `Bk\EarlyWarningController@show` | `(none) / auth:sanctum` | **`counseling.case.read`** | Sangat Sensitif: Data deteksi dini risiko siswa |
| `PATCH` | `/api/v1/early-warnings/{id}/handle` | `Bk\EarlyWarningController@handle` | `(none) / auth:sanctum` | **`bk.counseling.manage`** | Sangat Sensitif: Data deteksi dini risiko siswa |
| `POST` | `/api/v1/exam-sessions/{sessionId}/manage` | `Cbt\ExamSessionController@manage` | `(none) / auth:sanctum` | **`cbt.proctor.action`** | Kritis: Pengawas membuka sesi terkunci/tambah waktu |
| `GET` | `/api/v1/exams/{id}/anomalies` | `Cbt\ExamSessionController@anomalies` | `(none) / auth:sanctum` | **`cbt.proctor.action`** | Kritis: Live monitoring pengawas & anomali ujian |
| `POST` | `/api/v1/exams/{id}/auto-grade` | `Cbt\ExamGradingController@autoGrade` | `(none) / auth:sanctum` | **`cbt.exam.manage`** | Kritis: Nilai ujian, item analysis, auto-grade |
| `GET` | `/api/v1/exams/{id}/essay-submissions` | `Cbt\ExamGradingController@essaySubmissions` | `(none) / auth:sanctum` | **`cbt.exam.manage`** | Kritis: Nilai ujian, item analysis, auto-grade |
| `POST` | `/api/v1/exams/{id}/grade-essay` | `Cbt\ExamGradingController@gradeEssay` | `(none) / auth:sanctum` | **`cbt.exam.manage`** | Kritis: Nilai ujian, item analysis, auto-grade |
| `GET` | `/api/v1/exams/{id}/item-analysis` | `Cbt\ExamGradingController@itemAnalysis` | `(none) / auth:sanctum` | **`cbt.exam.manage`** | Kritis: Nilai ujian, item analysis, auto-grade |
| `GET` | `/api/v1/exams/{id}/minutes/pdf` | `Cbt\ExamReportController@minutesPdf` | `(none) / auth:sanctum` | **`cbt.exam.manage`** | Kritis: Nilai ujian, item analysis, auto-grade |
| `GET` | `/api/v1/exams/{id}/monitor` | `Cbt\ExamSessionController@monitor` | `(none) / auth:sanctum` | **`cbt.proctor.action`** | Kritis: Live monitoring pengawas & anomali ujian |
| `GET` | `/api/v1/exams/{id}/report` | `Cbt\ExamReportController@report` | `(none) / auth:sanctum` | **`cbt.exam.manage`** | Kritis: Nilai ujian, item analysis, auto-grade |
| `GET` | `/api/v1/exams/{id}/results` | `Cbt\ExamGradingController@results` | `(none) / auth:sanctum` | **`cbt.exam.manage`** | Kritis: Nilai ujian, item analysis, auto-grade |
| `PATCH` | `/api/v1/grade-periods/{id}/lock` | `GradePeriodController@lock` | `(none) / auth:sanctum` | **`academic.grade.write`** | Kritis: Penguncian periode penilaian akademik |
| `PATCH` | `/api/v1/grade-periods/{id}/lock-period` | `GradePeriodController@lock` | `(none) / auth:sanctum` | **`academic.grade.write`** | Kritis: Penguncian periode penilaian akademik |
| `GET` | `/api/v1/notification-logs` | `Ntf\MessagingController@indexLogs` | `(none) / auth:sanctum` | **`notification.broadcast`** | Kritis: Broadcast massal & PII log pengiriman |
| `GET` | `/api/v1/notification-logs/{id}` | `Ntf\MessagingController@showLog` | `(none) / auth:sanctum` | **`notification.broadcast`** | Kritis: Broadcast massal & PII log pengiriman |
| `POST` | `/api/v1/notification-logs/{id}/retry` | `Ntf\MessagingController@retry` | `(none) / auth:sanctum` | **`notification.broadcast`** | Kritis: Broadcast massal & PII log pengiriman |
| `POST` | `/api/v1/notification-templates/send` | `Ntf\MessagingController@send` | `(none) / auth:sanctum` | **`notification.broadcast`** | Kritis: Broadcast massal & PII log pengiriman |
| `POST` | `/api/v1/notifications/broadcast` | `Ntf\NotificationController@broadcast` | `(none) / auth:sanctum` | **`notification.broadcast`** | Kritis: Broadcast massal & PII log pengiriman |
| `GET` | `/api/v1/point-thresholds` | `Std\PointTypeController@indexThresholds` | `(none) / auth:sanctum` | **`student.point.read`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `POST` | `/api/v1/point-thresholds` | `Std\PointTypeController@storeThreshold` | `(none) / auth:sanctum` | **`student.point.write`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `DELETE` | `/api/v1/point-thresholds/{id}` | `Std\PointTypeController@destroyThreshold` | `(none) / auth:sanctum` | **`student.point.write`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `GET` | `/api/v1/point-thresholds/{id}` | `Std\PointTypeController@showThreshold` | `(none) / auth:sanctum` | **`student.point.read`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `PUT` | `/api/v1/point-thresholds/{id}` | `Std\PointTypeController@updateThreshold` | `(none) / auth:sanctum` | **`student.point.write`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `GET` | `/api/v1/point-types` | `Std\PointTypeController@indexTypes` | `(none) / auth:sanctum` | **`student.point.read`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `POST` | `/api/v1/point-types` | `Std\PointTypeController@storeType` | `(none) / auth:sanctum` | **`student.point.write`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `DELETE` | `/api/v1/point-types/{id}` | `Std\PointTypeController@destroyType` | `(none) / auth:sanctum` | **`student.point.write`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `GET` | `/api/v1/point-types/{id}` | `Std\PointTypeController@showType` | `(none) / auth:sanctum` | **`student.point.read`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `PUT` | `/api/v1/point-types/{id}` | `Std\PointTypeController@updateType` | `(none) / auth:sanctum` | **`student.point.write`** | Kritis: Konfigurasi ambang sanksi & jenis pelanggaran |
| `PATCH` | `/api/v1/progress-reports/bulk-approve` | `ProgressReportController@bulkApprove` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Pengesahan & penerbitan progress report |
| `POST` | `/api/v1/progress-reports/bulk-approve` | `ProgressReportController@bulkApprove` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Pengesahan & penerbitan progress report |
| `POST` | `/api/v1/progress-reports/generate` | `ProgressReportController@generate` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Pengesahan & penerbitan progress report |
| `PATCH` | `/api/v1/progress-reports/{id}/approve` | `ProgressReportController@approve` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Pengesahan & penerbitan progress report |
| `PATCH` | `/api/v1/report-cards/bulk-approve` | `ReportCardController@bulkApprove` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Penguncian & pengesahan rapor semester |
| `POST` | `/api/v1/report-cards/bulk-approve` | `ReportCardController@bulkApprove` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Penguncian & pengesahan rapor semester |
| `PATCH` | `/api/v1/report-cards/bulk-lock` | `ReportCardController@bulkLock` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Penguncian & pengesahan rapor semester |
| `POST` | `/api/v1/report-cards/bulk-lock` | `ReportCardController@bulkLock` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Penguncian & pengesahan rapor semester |
| `POST` | `/api/v1/report-cards/generate` | `ReportCardController@generate` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Penguncian & pengesahan rapor semester |
| `PATCH` | `/api/v1/report-cards/{id}/approve` | `ReportCardController@approve` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Penguncian & pengesahan rapor semester |
| `PATCH` | `/api/v1/report-cards/{id}/lock` | `ReportCardController@lock` | `(none) / auth:sanctum` | **`academic.report.generate`** | Kritis: Penguncian & pengesahan rapor semester |
| `GET` | `/api/v1/reports/{type}` | `Rpt\ReportController@show` | `(none) / auth:sanctum` | **`reports.view`** | Kritis: Ekspor dan akses laporan lintas-sekolah |
| `GET` | `/api/v1/reports/{type}/export` | `Rpt\ReportController@export` | `(none) / auth:sanctum` | **`reports.view`** | Kritis: Ekspor dan akses laporan lintas-sekolah |

---

## 3. Rute Self-Service & Pembatasan Cakupan Persona (IDOR Scoped)

> Endpoint self-service diakses oleh pengguna biasa (Siswa, Orang Tua, Guru) untuk melihat data dirinya sendiri. Proteksi utama adalah verifikasi kepemilikan IDOR di level Service & Policy (`$user->id == $resource->user_id` atau relasi anak binaan).

| Method | Path | Controller@Method | Permission Saat Ini | Proteksi Cakupan Usulan |
|---|---|---|---|---|
| `GET` | `/api/user` | `Closure` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |
| `POST` | `/api/v1/auth/2fa/disable` | `TwoFactorAuthController@disable` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |
| `POST` | `/api/v1/auth/change-password` | `AuthController@changePassword` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |
| `POST` | `/api/v1/auth/logout` | `AuthController@logout` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |
| `GET` | `/api/v1/auth/me` | `AuthController@me` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |
| `GET` | `/api/v1/auth/sessions` | `AuthController@sessions` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |
| `DELETE` | `/api/v1/auth/sessions/{id}` | `AuthController@revokeSession` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |
| `POST` | `/api/v1/devices` | `AuthController@registerDevice` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Device Token)` |
| `DELETE` | `/api/v1/devices/{token}` | `Ntf\NotificationController@destroyDevice` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Device Token)` |
| `GET` | `/api/v1/me/children` | `MeChildController@index` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/children/{child}` | `MeChildController@show` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/children/{child}/attendance` | `MeChildController@attendance` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/children/{child}/character-notes` | `MeChildController@characterNotes` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/children/{child}/grades` | `MeChildController@grades` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/children/{child}/invoices` | `MeFinanceController@childInvoices` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/children/{child}/progress-reports` | `MeChildController@progressReports` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/children/{child}/report-cards` | `MeChildController@reportCards` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/counseling-requests` | `MeStudentController@counselingRequests` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/counseling-requests` | `MeStudentController@storeCounselingRequest` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/exams` | `MeStudentController@exams` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/grades` | `MeStudentController@grades` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/invoices` | `MeFinanceController@myInvoices` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/invoices/{id}` | `MeFinanceController@showInvoice` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/invoices/{id}/payments` | `MeFinanceController@submitPayment` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/notification-preferences` | `Ntf\NotificationPreferenceController@index` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `PUT` | `/api/v1/me/notification-preferences` | `Ntf\NotificationPreferenceController@update` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/notifications` | `Ntf\NotificationController@index` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `POST|PATCH` | `/api/v1/me/notifications/read-all` | `Ntf\NotificationController@markAllAsRead` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `PATCH` | `/api/v1/me/notifications/{id}/read` | `Ntf\NotificationController@markAsRead` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/payment-providers` | `MeFinanceController@paymentProviders` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/payments/{id}/receipt` | `MeFinanceController@getReceipt` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/schedule` | `MeStudentController@schedule` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/staff/dashboard` | `MeStaffController@dashboard` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/staff/leave-requests` | `MeStaffController@leaveRequests` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/staff/leave-requests` | `MeStaffController@storeLeaveRequest` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/staff/self-attendance` | `MeStaffController@selfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/staff/self-attendance` | `MeStaffController@recordSelfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/staff/tickets` | `MeStaffController@tickets` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/student-dashboard` | `MeStudentController@dashboard` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/tasks` | `MeStudentController@tasks` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/tasks/{id}/submit` | `MeStudentController@submitTask` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/assignments` | `MeTeacherController@assignments` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/assignments` | `MeTeacherController@storeAssignment` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/assignments/{id}/submissions` | `MeTeacherController@submissions` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/attendance/sessions` | `MeTeacherController@attendanceSessions` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/attendance/sessions` | `MeTeacherController@recordAttendanceSession` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/classes` | `MeTeacherController@classes` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/classes/{classId}/students` | `MeTeacherController@classStudents` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/counseling-referrals` | `MeTeacherController@storeCounselingReferral` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/dashboard` | `MeTeacherController@dashboard` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/exams` | `MeTeacherController@exams` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/exams` | `MeTeacherController@storeExam` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/facility-bookings` | `MeTeacherController@facilityBookings` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/facility-bookings` | `MeTeacherController@storeFacilityBooking` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/grade-components` | `MeTeacherController@gradeComponents` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/grades` | `MeTeacherController@grades` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/grades/bulk` | `MeTeacherController@updateBulkGrades` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/grades/lock` | `MeTeacherController@lockGrades` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/homeroom` | `MeTeacherController@homeroom` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/homeroom/leave-requests` | `MeTeacherController@homeroomLeaveRequests` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `PATCH` | `/api/v1/me/teacher/homeroom/leave-requests/{id}/decision` | `MeTeacherController@decideHomeroomLeaveRequest` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/journals` | `MeTeacherController@journals` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/journals` | `MeTeacherController@storeJournal` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/leave-requests` | `MeTeacherController@leaveRequests` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/leave-requests` | `MeTeacherController@storeLeaveRequest` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/materials` | `MeTeacherController@materials` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/materials` | `MeTeacherController@storeMaterial` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/question-banks` | `MeTeacherController@questionBanks` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/question-banks` | `MeTeacherController@storeQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `DELETE` | `/api/v1/me/teacher/question-banks/{id}` | `MeTeacherController@destroyQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/question-banks/{id}` | `MeTeacherController@showQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `PUT` | `/api/v1/me/teacher/question-banks/{id}` | `MeTeacherController@updateQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/question-banks/{id}/questions` | `MeTeacherController@storeQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/questions` | `MeTeacherController@questions` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/questions` | `MeTeacherController@storeDirectQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `DELETE` | `/api/v1/me/teacher/questions/{id}` | `MeTeacherController@destroyQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/questions/{id}` | `MeTeacherController@showQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `PUT` | `/api/v1/me/teacher/questions/{id}` | `MeTeacherController@updateQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/schedules` | `MeTeacherController@schedules` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/me/teacher/self-attendance` | `MeTeacherController@selfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/self-attendance` | `MeTeacherController@recordSelfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/student-points` | `MeTeacherController@storeStudentPoint` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/submissions/{id}/grade` | `MeTeacherController@gradeSubmission` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `POST` | `/api/v1/me/teacher/tickets` | `MeTeacherController@storeTicket` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `PUT` | `/api/v1/notification-preferences` | `Ntf\NotificationPreferenceController@update` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` |
| `PATCH` | `/api/v1/notifications/read-all` | `Ntf\NotificationController@markAllAsRead` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` |
| `DELETE` | `/api/v1/notifications/{id}` | `Ntf\NotificationController@destroy` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` |
| `PATCH` | `/api/v1/notifications/{id}/read` | `Ntf\NotificationController@markAsRead` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` |
| `GET` | `/api/v1/salary-slips` | `TeacherController@mySalarySlips` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/salary-slips/{slipId}/download` | `TeacherController@downloadMySalarySlip` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/teacher/me/salary-slips` | `TeacherController@mySalarySlips` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/teacher/me/salary-slips/{slipId}/download` | `TeacherController@downloadMySalarySlip` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/teacher/me/teaching-recap` | `TeacherController@myTeachingRecap` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` |
| `GET` | `/api/v1/user` | `AuthController@me` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` |

---

## 4. Rute Telemetri Klien & Utilitas Sistem (SEC-02)

| Method | Path | Controller@Method | Permission Saat Ini | Rekomendasi Mitigasi FASE 1 |
|---|---|---|---|---|
| `POST` | `/api/v1/audit-logs` | `AuditLogController@storeTelemetry` | `(none) / auth:sanctum` | **`auth:sanctum + FormRequest + throttle`** |
| `POST` | `/api/v1/files/confirm` | `Storage\FileController@confirm` | `(none) / auth:sanctum` | **`auth:sanctum (Signed URL Generator)`** |
| `POST` | `/api/v1/files/presign` | `Storage\FileController@presign` | `(none) / auth:sanctum` | **`auth:sanctum (Signed URL Generator)`** |
| `GET` | `/api/v1/files/{id}/url` | `Storage\FileController@url` | `(none) / auth:sanctum` | **`auth:sanctum (Signed URL Generator)`** |

---

## 5. Inventaris Lengkap Seluruh 415 Rute Tanpa Guard Permission

| # | Method | Path | Controller@Method | Permission Saat Ini | Permission Usulan | Kategori |
|:---:|---|---|---|---|---|---|
| 1 | `GET` | `/api/user` | `Closure` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |
| 2 | `GET` | `/api/v1/academic-years/active` | `AcademicYearController@active` | `(none) / auth:sanctum` | `academic.schedule.read` | Master - Data Akademik |
| 3 | `GET` | `/api/v1/achievements` | `Std\AchievementController@index` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Prestasi & Lomba |
| 4 | `POST` | `/api/v1/achievements` | `Std\AchievementController@store` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 5 | `DELETE` | `/api/v1/achievements/{id}` | `Std\AchievementController@destroy` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 6 | `GET` | `/api/v1/achievements/{id}` | `Std\AchievementController@show` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Prestasi & Lomba |
| 7 | `PUT` | `/api/v1/achievements/{id}` | `Std\AchievementController@update` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 8 | `PATCH` | `/api/v1/achievements/{id}/verify` | `Std\AchievementController@verify` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 9 | `GET` | `/api/v1/activity-documents` | `Std\ExtracurricularController@indexDocuments` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Ekskul & OSIS |
| 10 | `POST` | `/api/v1/activity-documents` | `Std\ExtracurricularController@storeDocument` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 11 | `DELETE` | `/api/v1/activity-documents/{id}` | `Std\ExtracurricularController@destroyDocument` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 12 | `GET` | `/api/v1/activity-documents/{id}` | `Std\ExtracurricularController@showDocument` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Ekskul & OSIS |
| 13 | `GET` | `/api/v1/alumni` | `Hum\AlumniController@index` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 14 | `POST` | `/api/v1/alumni` | `Hum\AlumniController@store` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 15 | `DELETE` | `/api/v1/alumni/{id}` | `Hum\AlumniController@destroy` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 16 | `GET` | `/api/v1/alumni/{id}` | `Hum\AlumniController@show` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 17 | `PUT` | `/api/v1/alumni/{id}` | `Hum\AlumniController@update` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 18 | `GET` | `/api/v1/announcements` | `Ntf\AnnouncementController@index` | `(none) / auth:sanctum` | `auth:sanctum (Authenticated Read)` | Operasional - Pengumuman Terarah |
| 19 | `POST` | `/api/v1/announcements` | `Ntf\AnnouncementController@store` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Pengumuman Terarah |
| 20 | `DELETE` | `/api/v1/announcements/{id}` | `Ntf\AnnouncementController@destroy` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Pengumuman Terarah |
| 21 | `GET` | `/api/v1/announcements/{id}` | `Ntf\AnnouncementController@show` | `(none) / auth:sanctum` | `auth:sanctum (Authenticated Read)` | Operasional - Pengumuman Terarah |
| 22 | `PUT` | `/api/v1/announcements/{id}` | `Ntf\AnnouncementController@update` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Pengumuman Terarah |
| 23 | `POST` | `/api/v1/announcements/{id}/read` | `Ntf\AnnouncementController@markAsRead` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Pengumuman Terarah |
| 24 | `GET` | `/api/v1/announcements/{id}/readers` | `Ntf\AnnouncementController@readers` | `(none) / auth:sanctum` | `auth:sanctum (Authenticated Read)` | Operasional - Pengumuman Terarah |
| 25 | `GET` | `/api/v1/approvals/pending` | `Rpt\ApprovalController@pending` | `(none) / auth:sanctum` | `executive.approval.manage` | P0 - Eksekutif & Persetujuan |
| 26 | `POST` | `/api/v1/approvals/{id}/review` | `Rpt\ApprovalController@review` | `(none) / auth:sanctum` | `executive.approval.manage` | P0 - Eksekutif & Persetujuan |
| 27 | `GET` | `/api/v1/asset-audits` | `Sar\AssetAuditController@index` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 28 | `POST` | `/api/v1/asset-audits` | `Sar\AssetAuditController@store` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 29 | `GET` | `/api/v1/asset-audits/{id}` | `Sar\AssetAuditController@show` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 30 | `PATCH` | `/api/v1/asset-audits/{id}` | `Sar\AssetAuditController@update` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 31 | `GET` | `/api/v1/assets` | `Sar\AssetController@index` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 32 | `POST` | `/api/v1/assets` | `Sar\AssetController@store` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 33 | `GET` | `/api/v1/assets/by-code/{code}` | `Sar\AssetController@byCode` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 34 | `GET` | `/api/v1/assets/summary` | `Sar\AssetController@summary` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 35 | `DELETE` | `/api/v1/assets/{id}` | `Sar\AssetController@destroy` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 36 | `GET` | `/api/v1/assets/{id}` | `Sar\AssetController@show` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 37 | `PUT` | `/api/v1/assets/{id}` | `Sar\AssetController@update` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 38 | `GET` | `/api/v1/assets/{id}/qr` | `Sar\AssetController@qr` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Aset |
| 39 | `GET` | `/api/v1/assignments` | `AssignmentController@index` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Tugas & Pengumpulan |
| 40 | `POST` | `/api/v1/assignments` | `AssignmentController@store` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Tugas & Pengumpulan |
| 41 | `PATCH` | `/api/v1/assignments/{assignment_id}/submissions/{submission_id}/grade` | `AssignmentController@grade` | `(none) / auth:sanctum` | `academic.grade.write` | Operasional - LMS Tugas & Pengumpulan |
| 42 | `DELETE` | `/api/v1/assignments/{id}` | `AssignmentController@destroy` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Tugas & Pengumpulan |
| 43 | `GET` | `/api/v1/assignments/{id}` | `AssignmentController@show` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Tugas & Pengumpulan |
| 44 | `PUT` | `/api/v1/assignments/{id}` | `AssignmentController@update` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Tugas & Pengumpulan |
| 45 | `GET` | `/api/v1/assignments/{id}/submissions` | `AssignmentController@submissions` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Tugas & Pengumpulan |
| 46 | `POST` | `/api/v1/assignments/{id}/submissions` | `AssignmentController@submit` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Tugas & Pengumpulan |
| 47 | `GET` | `/api/v1/attendances` | `AttendanceController@index` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Presensi Siswa |
| 48 | `GET` | `/api/v1/attendances/recap` | `AttendanceController@recap` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Presensi Siswa |
| 49 | `POST` | `/api/v1/audit-logs` | `AuditLogController@storeTelemetry` | `(none) / auth:sanctum` | `auth:sanctum + FormRequest + throttle` | Telemetri Klien (SEC-02) |
| 50 | `POST` | `/api/v1/auth/2fa/disable` | `TwoFactorAuthController@disable` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |
| 51 | `POST` | `/api/v1/auth/change-password` | `AuthController@changePassword` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |
| 52 | `POST` | `/api/v1/auth/logout` | `AuthController@logout` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |
| 53 | `GET` | `/api/v1/auth/me` | `AuthController@me` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |
| 54 | `GET` | `/api/v1/auth/sessions` | `AuthController@sessions` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |
| 55 | `DELETE` | `/api/v1/auth/sessions/{id}` | `AuthController@revokeSession` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |
| 56 | `GET` | `/api/v1/bookings` | `Sar\BookingController@index` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 57 | `POST` | `/api/v1/bookings` | `Sar\BookingController@store` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 58 | `GET` | `/api/v1/bookings/availability` | `Sar\BookingController@availability` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 59 | `GET` | `/api/v1/bookings/summary` | `Sar\BookingController@summary` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 60 | `DELETE` | `/api/v1/bookings/{id}` | `Sar\BookingController@cancel` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 61 | `GET` | `/api/v1/bookings/{id}` | `Sar\BookingController@show` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 62 | `PATCH` | `/api/v1/bookings/{id}/decision` | `Sar\BookingController@decision` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 63 | `GET` | `/api/v1/character-notes` | `CharacterNoteController@index` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Catatan Karakter |
| 64 | `GET` | `/api/v1/character-notes/class-overview` | `CharacterNoteController@matrix` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Catatan Karakter |
| 65 | `GET` | `/api/v1/character-notes/matrix` | `CharacterNoteController@matrix` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Catatan Karakter |
| 66 | `GET` | `/api/v1/character-notes/{id}` | `CharacterNoteController@show` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Catatan Karakter |
| 67 | `DELETE` | `/api/v1/competition-entries/{id}` | `Std\CompetitionController@destroyEntry` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 68 | `PUT` | `/api/v1/competition-entries/{id}` | `Std\CompetitionController@updateEntry` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 69 | `GET` | `/api/v1/competitions` | `Std\CompetitionController@index` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Prestasi & Lomba |
| 70 | `POST` | `/api/v1/competitions` | `Std\CompetitionController@store` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 71 | `GET` | `/api/v1/competitions/summary` | `Std\CompetitionController@summary` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Prestasi & Lomba |
| 72 | `DELETE` | `/api/v1/competitions/{id}` | `Std\CompetitionController@destroy` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 73 | `GET` | `/api/v1/competitions/{id}` | `Std\CompetitionController@show` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Prestasi & Lomba |
| 74 | `PUT` | `/api/v1/competitions/{id}` | `Std\CompetitionController@update` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 75 | `GET` | `/api/v1/competitions/{id}/entries` | `Std\CompetitionController@indexEntries` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Prestasi & Lomba |
| 76 | `POST` | `/api/v1/competitions/{id}/entries` | `Std\CompetitionController@storeEntry` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Prestasi & Lomba |
| 77 | `GET` | `/api/v1/complaints` | `ComplaintController@index` | `(none) / auth:sanctum` | `complaint.read` | Operasional - Aspirasi & Buku Tamu |
| 78 | `GET` | `/api/v1/complaints/{id}` | `ComplaintController@show` | `(none) / auth:sanctum` | `complaint.read` | Operasional - Aspirasi & Buku Tamu |
| 79 | `PATCH` | `/api/v1/complaints/{id}/status` | `ComplaintController@updateStatus` | `(none) / auth:sanctum` | `complaint.read` | Operasional - Aspirasi & Buku Tamu |
| 80 | `GET` | `/api/v1/counseling/cases` | `Bk\CounselingCaseController@index` | `(none) / auth:sanctum` | `counseling.case.read` | P0 - BK & Konseling Sensitif |
| 81 | `POST` | `/api/v1/counseling/cases` | `Bk\CounselingCaseController@store` | `(none) / auth:sanctum` | `counseling.case.write` | P0 - BK & Konseling Sensitif |
| 82 | `GET` | `/api/v1/counseling/cases/{id}` | `Bk\CounselingCaseController@show` | `(none) / auth:sanctum` | `counseling.case.read` | P0 - BK & Konseling Sensitif |
| 83 | `PUT` | `/api/v1/counseling/cases/{id}` | `Bk\CounselingCaseController@update` | `(none) / auth:sanctum` | `counseling.case.write` | P0 - BK & Konseling Sensitif |
| 84 | `POST` | `/api/v1/counseling/cases/{id}/sessions` | `Bk\CounselingCaseController@storeSession` | `(none) / auth:sanctum` | `counseling.case.write` | P0 - BK & Konseling Sensitif |
| 85 | `GET` | `/api/v1/counseling/referrals` | `Bk\CounselingReferralController@index` | `(none) / auth:sanctum` | `counseling.case.read` | P0 - BK & Konseling Sensitif |
| 86 | `POST` | `/api/v1/counseling/referrals` | `Bk\CounselingReferralController@store` | `(none) / auth:sanctum` | `counseling.case.write` | P0 - BK & Konseling Sensitif |
| 87 | `PATCH` | `/api/v1/counseling/referrals/{id}/status` | `Bk\CounselingReferralController@updateStatus` | `(none) / auth:sanctum` | `counseling.case.write` | P0 - BK & Konseling Sensitif |
| 88 | `GET` | `/api/v1/counseling/requests` | `Bk\CounselingRequestController@index` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 89 | `POST` | `/api/v1/counseling/requests` | `Bk\CounselingRequestController@store` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 90 | `DELETE` | `/api/v1/counseling/requests/{id}` | `Bk\CounselingRequestController@cancel` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 91 | `GET` | `/api/v1/counseling/requests/{id}` | `Bk\CounselingRequestController@show` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 92 | `PATCH` | `/api/v1/counseling/requests/{id}/complete` | `Bk\CounselingRequestController@complete` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 93 | `PATCH` | `/api/v1/counseling/requests/{id}/reject` | `Bk\CounselingRequestController@reject` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 94 | `PATCH` | `/api/v1/counseling/requests/{id}/schedule` | `Bk\CounselingRequestController@schedule` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 95 | `GET` | `/api/v1/counseling/sessions/{id}` | `Bk\CounselingSessionController@show` | `(none) / auth:sanctum` | `counseling.case.read` | P0 - BK & Konseling Sensitif |
| 96 | `GET` | `/api/v1/dashboard/executive` | `Rpt\DashboardController@executive` | `(none) / auth:sanctum` | `dashboard.executive` | P0 - Eksekutif & Persetujuan |
| 97 | `GET` | `/api/v1/dashboard/guardian/{studentId?}` | `Rpt\DashboardController@guardian` | `(none) / auth:sanctum` | `reports.view` | Operasional - Dashboard Persona |
| 98 | `GET` | `/api/v1/dashboard/student` | `Rpt\DashboardController@student` | `(none) / auth:sanctum` | `reports.view` | Operasional - Dashboard Persona |
| 99 | `GET` | `/api/v1/dashboard/teacher` | `Rpt\DashboardController@teacher` | `(none) / auth:sanctum` | `reports.view` | Operasional - Dashboard Persona |
| 100 | `POST` | `/api/v1/devices` | `AuthController@registerDevice` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Device Token)` | Self-Service - Akun & Sesi |
| 101 | `DELETE` | `/api/v1/devices/{token}` | `Ntf\NotificationController@destroyDevice` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Device Token)` | Self-Service - Akun & Sesi |
| 102 | `GET` | `/api/v1/early-warnings` | `Bk\EarlyWarningController@index` | `(none) / auth:sanctum` | `counseling.case.read` | P0 - BK & Konseling Sensitif |
| 103 | `POST` | `/api/v1/early-warnings/evaluate` | `Bk\EarlyWarningController@evaluate` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 104 | `GET` | `/api/v1/early-warnings/summary` | `Bk\EarlyWarningController@summary` | `(none) / auth:sanctum` | `counseling.case.read` | P0 - BK & Konseling Sensitif |
| 105 | `GET` | `/api/v1/early-warnings/{id}` | `Bk\EarlyWarningController@show` | `(none) / auth:sanctum` | `counseling.case.read` | P0 - BK & Konseling Sensitif |
| 106 | `PATCH` | `/api/v1/early-warnings/{id}/handle` | `Bk\EarlyWarningController@handle` | `(none) / auth:sanctum` | `bk.counseling.manage` | P0 - BK & Konseling Sensitif |
| 107 | `POST` | `/api/v1/exam-rooms/{id}/regenerate-token` | `Cbt\ExamRoomTokenController@regenerate` | `(none) / auth:sanctum` | `cbt.proctor.action` | Operasional - Ujian CBT |
| 108 | `GET` | `/api/v1/exam-rooms/{id}/token-status` | `Cbt\ExamRoomTokenController@status` | `(none) / auth:sanctum` | `cbt.proctor.action` | Operasional - Ujian CBT |
| 109 | `POST` | `/api/v1/exam-sessions/{sessionId}/heartbeat` | `Cbt\ExamSessionController@heartbeat` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 110 | `POST` | `/api/v1/exam-sessions/{sessionId}/manage` | `Cbt\ExamSessionController@manage` | `(none) / auth:sanctum` | `cbt.proctor.action` | P0 - CBT Pengawasan & Live Monitor |
| 111 | `GET` | `/api/v1/exams` | `ExamController@index` | `(none) / auth:sanctum` | `cbt.exam.manage` | Operasional - Ujian CBT |
| 112 | `GET` | `/api/v1/exams/{id}` | `ExamController@show` | `(none) / auth:sanctum` | `cbt.exam.manage` | Operasional - Ujian CBT |
| 113 | `GET` | `/api/v1/exams/{id}/anomalies` | `Cbt\ExamSessionController@anomalies` | `(none) / auth:sanctum` | `cbt.proctor.action` | P0 - CBT Pengawasan & Live Monitor |
| 114 | `POST` | `/api/v1/exams/{id}/auto-grade` | `Cbt\ExamGradingController@autoGrade` | `(none) / auth:sanctum` | `cbt.exam.manage` | P0 - CBT Evaluasi & Hasil |
| 115 | `GET` | `/api/v1/exams/{id}/essay-submissions` | `Cbt\ExamGradingController@essaySubmissions` | `(none) / auth:sanctum` | `cbt.exam.manage` | P0 - CBT Evaluasi & Hasil |
| 116 | `POST` | `/api/v1/exams/{id}/finish-session` | `Cbt\ExamSessionController@finish` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 117 | `POST` | `/api/v1/exams/{id}/grade-essay` | `Cbt\ExamGradingController@gradeEssay` | `(none) / auth:sanctum` | `cbt.exam.manage` | P0 - CBT Evaluasi & Hasil |
| 118 | `GET` | `/api/v1/exams/{id}/item-analysis` | `Cbt\ExamGradingController@itemAnalysis` | `(none) / auth:sanctum` | `cbt.exam.manage` | P0 - CBT Evaluasi & Hasil |
| 119 | `POST` | `/api/v1/exams/{id}/log-violation` | `Cbt\ExamSessionController@logViolation` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 120 | `GET` | `/api/v1/exams/{id}/minutes/pdf` | `Cbt\ExamReportController@minutesPdf` | `(none) / auth:sanctum` | `cbt.exam.manage` | P0 - CBT Evaluasi & Hasil |
| 121 | `GET` | `/api/v1/exams/{id}/monitor` | `Cbt\ExamSessionController@monitor` | `(none) / auth:sanctum` | `cbt.proctor.action` | P0 - CBT Pengawasan & Live Monitor |
| 122 | `GET` | `/api/v1/exams/{id}/participants/{participantId}/card-pdf` | `Cbt\ExamParticipantCardController@downloadPdf` | `(none) / auth:sanctum` | `cbt.exam.manage` | Operasional - Ujian CBT |
| 123 | `GET` | `/api/v1/exams/{id}/report` | `Cbt\ExamReportController@report` | `(none) / auth:sanctum` | `cbt.exam.manage` | P0 - CBT Evaluasi & Hasil |
| 124 | `GET` | `/api/v1/exams/{id}/results` | `Cbt\ExamGradingController@results` | `(none) / auth:sanctum` | `cbt.exam.manage` | P0 - CBT Evaluasi & Hasil |
| 125 | `GET` | `/api/v1/exams/{id}/review` | `Cbt\ExamSessionController@review` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 126 | `POST` | `/api/v1/exams/{id}/save-answer` | `Cbt\ExamSessionController@saveAnswer` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 127 | `GET` | `/api/v1/exams/{id}/session-questions` | `Cbt\ExamSessionController@questions` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 128 | `POST` | `/api/v1/exams/{id}/start-session` | `Cbt\ExamSessionController@start` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 129 | `POST` | `/api/v1/exams/{id}/violations` | `Cbt\ExamSessionController@logViolation` | `(none) / auth:sanctum` | `cbt.exam.take` | Siswa - Sesi Ujian CBT Aktif |
| 130 | `DELETE` | `/api/v1/exchange-participants/{participantId}` | `Hum\ExchangeProgramController@destroyParticipant` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 131 | `PUT` | `/api/v1/exchange-participants/{participantId}` | `Hum\ExchangeProgramController@updateParticipant` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 132 | `GET` | `/api/v1/exchange-programs` | `Hum\ExchangeProgramController@index` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 133 | `POST` | `/api/v1/exchange-programs` | `Hum\ExchangeProgramController@store` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 134 | `DELETE` | `/api/v1/exchange-programs/{id}` | `Hum\ExchangeProgramController@destroy` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 135 | `GET` | `/api/v1/exchange-programs/{id}` | `Hum\ExchangeProgramController@show` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 136 | `PUT` | `/api/v1/exchange-programs/{id}` | `Hum\ExchangeProgramController@update` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 137 | `GET` | `/api/v1/exchange-programs/{id}/participants` | `Hum\ExchangeProgramController@indexParticipants` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 138 | `POST` | `/api/v1/exchange-programs/{id}/participants` | `Hum\ExchangeProgramController@storeParticipant` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 139 | `GET` | `/api/v1/extracurricular-journals` | `Std\ExtracurricularController@indexJournals` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Ekskul & OSIS |
| 140 | `POST` | `/api/v1/extracurricular-journals` | `Std\ExtracurricularController@storeJournal` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 141 | `DELETE` | `/api/v1/extracurricular-journals/{id}` | `Std\ExtracurricularController@destroyJournal` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 142 | `GET` | `/api/v1/extracurricular-journals/{id}` | `Std\ExtracurricularController@showJournal` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Ekskul & OSIS |
| 143 | `GET` | `/api/v1/extracurricular-members` | `Std\ExtracurricularController@indexMembers` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Ekskul & OSIS |
| 144 | `POST` | `/api/v1/extracurricular-members` | `Std\ExtracurricularController@registerMember` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 145 | `DELETE` | `/api/v1/extracurricular-members/{id}` | `Std\ExtracurricularController@removeMember` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 146 | `PUT` | `/api/v1/extracurricular-members/{id}` | `Std\ExtracurricularController@updateMember` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 147 | `GET` | `/api/v1/extracurriculars` | `Std\ExtracurricularController@index` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Ekskul & OSIS |
| 148 | `POST` | `/api/v1/extracurriculars` | `Std\ExtracurricularController@store` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 149 | `DELETE` | `/api/v1/extracurriculars/{id}` | `Std\ExtracurricularController@destroy` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 150 | `GET` | `/api/v1/extracurriculars/{id}` | `Std\ExtracurricularController@show` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Ekskul & OSIS |
| 151 | `PUT` | `/api/v1/extracurriculars/{id}` | `Std\ExtracurricularController@update` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Ekskul & OSIS |
| 152 | `GET` | `/api/v1/facilities` | `Sar\FacilityController@index` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 153 | `POST` | `/api/v1/facilities` | `Sar\FacilityController@store` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 154 | `DELETE` | `/api/v1/facilities/{id}` | `Sar\FacilityController@destroy` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 155 | `GET` | `/api/v1/facilities/{id}` | `Sar\FacilityController@show` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 156 | `PUT` | `/api/v1/facilities/{id}` | `Sar\FacilityController@update` | `(none) / auth:sanctum` | `facility.booking.manage` | Operasional - Sarpras Fasilitas |
| 157 | `POST` | `/api/v1/files/confirm` | `Storage\FileController@confirm` | `(none) / auth:sanctum` | `auth:sanctum (Signed URL Generator)` | Utilitas Berkas |
| 158 | `POST` | `/api/v1/files/presign` | `Storage\FileController@presign` | `(none) / auth:sanctum` | `auth:sanctum (Signed URL Generator)` | Utilitas Berkas |
| 159 | `GET` | `/api/v1/files/{id}/url` | `Storage\FileController@url` | `(none) / auth:sanctum` | `auth:sanctum (Signed URL Generator)` | Utilitas Berkas |
| 160 | `GET` | `/api/v1/grade-components` | `GradeComponentController@index` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Komponen Nilai |
| 161 | `GET` | `/api/v1/grade-levels` | `ClassController@gradeLevels` | `(none) / auth:sanctum` | `master.class.manage` | Master - Data Akademik |
| 162 | `GET` | `/api/v1/grade-periods` | `GradePeriodController@index` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 163 | `PATCH` | `/api/v1/grade-periods/{id}/lock` | `GradePeriodController@lock` | `(none) / auth:sanctum` | `academic.grade.write` | P0 - Nilai & Rapor |
| 164 | `PATCH` | `/api/v1/grade-periods/{id}/lock-period` | `GradePeriodController@lock` | `(none) / auth:sanctum` | `academic.grade.write` | P0 - Nilai & Rapor |
| 165 | `GET` | `/api/v1/grades` | `GradeController@index` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Input Nilai Buku Rapor |
| 166 | `GET` | `/api/v1/grades/matrix` | `GradeController@matrix` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Input Nilai Buku Rapor |
| 167 | `GET` | `/api/v1/guardians` | `GuardianController@index` | `(none) / auth:sanctum` | `master.student.read` | Master - Wali Murid (Scoped) |
| 168 | `GET` | `/api/v1/guardians/{id}` | `GuardianController@show` | `(none) / auth:sanctum` | `master.student.read` | Master - Wali Murid (Scoped) |
| 169 | `GET` | `/api/v1/guests/{id}` | `GuestController@show` | `(none) / auth:sanctum` | `guest.book.read` | Operasional - Aspirasi & Buku Tamu |
| 170 | `GET` | `/api/v1/guests/{id}/signature` | `GuestController@getSignature` | `(none) / auth:sanctum` | `guest.book.read` | Operasional - Aspirasi & Buku Tamu |
| 171 | `GET` | `/api/v1/journals` | `JournalController@index` | `(none) / auth:sanctum` | `academic.journal.read` | Operasional - Jurnal Mengajar |
| 172 | `GET` | `/api/v1/journals/{id}` | `JournalController@show` | `(none) / auth:sanctum` | `academic.journal.read` | Operasional - Jurnal Mengajar |
| 173 | `GET` | `/api/v1/lab-equipment` | `Sar\LabController@indexEquipment` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 174 | `POST` | `/api/v1/lab-equipment` | `Sar\LabController@storeEquipment` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 175 | `GET` | `/api/v1/lab-equipment/summary` | `Sar\LabController@summary` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 176 | `DELETE` | `/api/v1/lab-equipment/{id}` | `Sar\LabController@destroyEquipment` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 177 | `GET` | `/api/v1/lab-equipment/{id}` | `Sar\LabController@showEquipment` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 178 | `PUT` | `/api/v1/lab-equipment/{id}` | `Sar\LabController@updateEquipment` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 179 | `GET` | `/api/v1/lab-loans` | `Sar\LabController@indexLoans` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 180 | `POST` | `/api/v1/lab-loans` | `Sar\LabController@storeLoan` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 181 | `GET` | `/api/v1/lab-loans/{id}` | `Sar\LabController@showLoan` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 182 | `POST` | `/api/v1/lab-loans/{id}/return` | `Sar\LabController@returnLoan` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Lab |
| 183 | `GET` | `/api/v1/learning-modules` | `TeachingMaterialController@index` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Bahan Ajar |
| 184 | `POST` | `/api/v1/learning-modules` | `TeachingMaterialController@store` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 185 | `DELETE` | `/api/v1/learning-modules/{id}` | `TeachingMaterialController@destroy` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 186 | `GET` | `/api/v1/learning-modules/{id}` | `TeachingMaterialController@show` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Bahan Ajar |
| 187 | `POST` | `/api/v1/learning-modules/{id}/versions` | `TeachingMaterialController@createVersion` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 188 | `GET` | `/api/v1/leave-requests` | `LeaveRequestController@index` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Permohonan Izin / Sakit |
| 189 | `POST` | `/api/v1/leave-requests` | `LeaveRequestController@store` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Permohonan Izin / Sakit |
| 190 | `GET` | `/api/v1/leave-requests/{id}` | `LeaveRequestController@show` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Permohonan Izin / Sakit |
| 191 | `PATCH` | `/api/v1/leave-requests/{id}/decision` | `LeaveRequestController@decision` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Permohonan Izin / Sakit |
| 192 | `PATCH` | `/api/v1/leave-requests/{id}/status` | `LeaveRequestController@updateStatus` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Permohonan Izin / Sakit |
| 193 | `GET` | `/api/v1/library/books` | `Sar\LibraryController@indexBooks` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 194 | `POST` | `/api/v1/library/books` | `Sar\LibraryController@storeBook` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 195 | `DELETE` | `/api/v1/library/books/{id}` | `Sar\LibraryController@destroyBook` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 196 | `GET` | `/api/v1/library/books/{id}` | `Sar\LibraryController@showBook` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 197 | `PUT` | `/api/v1/library/books/{id}` | `Sar\LibraryController@updateBook` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 198 | `GET` | `/api/v1/library/loans` | `Sar\LibraryController@indexLoans` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 199 | `POST` | `/api/v1/library/loans` | `Sar\LibraryController@storeLoan` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 200 | `GET` | `/api/v1/library/loans/{id}` | `Sar\LibraryController@showLoan` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 201 | `POST` | `/api/v1/library/loans/{id}/return` | `Sar\LibraryController@returnLoan` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 202 | `GET` | `/api/v1/library/summary` | `Sar\LibraryController@summary` | `(none) / auth:sanctum` | `library.book.manage` | Operasional - Sarpras Pustaka |
| 203 | `GET` | `/api/v1/majors` | `ClassController@majors` | `(none) / auth:sanctum` | `master.class.manage` | Master - Data Akademik |
| 204 | `GET` | `/api/v1/materials` | `MaterialController@index` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Bahan Ajar |
| 205 | `POST` | `/api/v1/materials` | `MaterialController@store` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 206 | `DELETE` | `/api/v1/materials/{id}` | `MaterialController@destroy` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 207 | `GET` | `/api/v1/materials/{id}` | `MaterialController@show` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Bahan Ajar |
| 208 | `PUT` | `/api/v1/materials/{id}` | `MaterialController@update` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 209 | `GET` | `/api/v1/me` | `AuthController@me` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 210 | `GET` | `/api/v1/me/children` | `MeChildController@index` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 211 | `GET` | `/api/v1/me/children/{child}` | `MeChildController@show` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 212 | `GET` | `/api/v1/me/children/{child}/attendance` | `MeChildController@attendance` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 213 | `GET` | `/api/v1/me/children/{child}/character-notes` | `MeChildController@characterNotes` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 214 | `GET` | `/api/v1/me/children/{child}/grades` | `MeChildController@grades` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 215 | `GET` | `/api/v1/me/children/{child}/invoices` | `MeFinanceController@childInvoices` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 216 | `GET` | `/api/v1/me/children/{child}/progress-reports` | `MeChildController@progressReports` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 217 | `GET` | `/api/v1/me/children/{child}/report-cards` | `MeChildController@reportCards` | `(none) / auth:sanctum` | `auth:sanctum + GuardianPolicy (IDOR Scoped)` | Self-Service - Portal Wali Murid |
| 218 | `GET` | `/api/v1/me/counseling-requests` | `MeStudentController@counselingRequests` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 219 | `POST` | `/api/v1/me/counseling-requests` | `MeStudentController@storeCounselingRequest` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 220 | `GET` | `/api/v1/me/exams` | `MeStudentController@exams` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 221 | `GET` | `/api/v1/me/grades` | `MeStudentController@grades` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 222 | `GET` | `/api/v1/me/invoices` | `MeFinanceController@myInvoices` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 223 | `GET` | `/api/v1/me/invoices/{id}` | `MeFinanceController@showInvoice` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 224 | `POST` | `/api/v1/me/invoices/{id}/payments` | `MeFinanceController@submitPayment` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 225 | `GET` | `/api/v1/me/notification-preferences` | `Ntf\NotificationPreferenceController@index` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 226 | `PUT` | `/api/v1/me/notification-preferences` | `Ntf\NotificationPreferenceController@update` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 227 | `GET` | `/api/v1/me/notifications` | `Ntf\NotificationController@index` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 228 | `POST|PATCH` | `/api/v1/me/notifications/read-all` | `Ntf\NotificationController@markAllAsRead` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 229 | `PATCH` | `/api/v1/me/notifications/{id}/read` | `Ntf\NotificationController@markAsRead` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 230 | `GET` | `/api/v1/me/payment-providers` | `MeFinanceController@paymentProviders` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 231 | `GET` | `/api/v1/me/payments/{id}/receipt` | `MeFinanceController@getReceipt` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 232 | `GET` | `/api/v1/me/schedule` | `MeStudentController@schedule` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 233 | `GET` | `/api/v1/me/staff/dashboard` | `MeStaffController@dashboard` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` | Self-Service - Portal Staf Mandiri |
| 234 | `GET` | `/api/v1/me/staff/leave-requests` | `MeStaffController@leaveRequests` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` | Self-Service - Portal Staf Mandiri |
| 235 | `POST` | `/api/v1/me/staff/leave-requests` | `MeStaffController@storeLeaveRequest` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` | Self-Service - Portal Staf Mandiri |
| 236 | `GET` | `/api/v1/me/staff/self-attendance` | `MeStaffController@selfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` | Self-Service - Portal Staf Mandiri |
| 237 | `POST` | `/api/v1/me/staff/self-attendance` | `MeStaffController@recordSelfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` | Self-Service - Portal Staf Mandiri |
| 238 | `GET` | `/api/v1/me/staff/tickets` | `MeStaffController@tickets` | `(none) / auth:sanctum` | `auth:sanctum + StaffPolicy (IDOR Scoped)` | Self-Service - Portal Staf Mandiri |
| 239 | `GET` | `/api/v1/me/student-dashboard` | `MeStudentController@dashboard` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 240 | `GET` | `/api/v1/me/tasks` | `MeStudentController@tasks` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 241 | `POST` | `/api/v1/me/tasks/{id}/submit` | `MeStudentController@submitTask` | `(none) / auth:sanctum` | `auth:sanctum + StudentPolicy (IDOR Scoped)` | Self-Service - Portal Siswa Mandiri |
| 242 | `GET` | `/api/v1/me/teacher/assignments` | `MeTeacherController@assignments` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 243 | `POST` | `/api/v1/me/teacher/assignments` | `MeTeacherController@storeAssignment` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 244 | `GET` | `/api/v1/me/teacher/assignments/{id}/submissions` | `MeTeacherController@submissions` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 245 | `GET` | `/api/v1/me/teacher/attendance/sessions` | `MeTeacherController@attendanceSessions` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 246 | `POST` | `/api/v1/me/teacher/attendance/sessions` | `MeTeacherController@recordAttendanceSession` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 247 | `GET` | `/api/v1/me/teacher/classes` | `MeTeacherController@classes` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 248 | `GET` | `/api/v1/me/teacher/classes/{classId}/students` | `MeTeacherController@classStudents` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 249 | `POST` | `/api/v1/me/teacher/counseling-referrals` | `MeTeacherController@storeCounselingReferral` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 250 | `GET` | `/api/v1/me/teacher/dashboard` | `MeTeacherController@dashboard` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 251 | `GET` | `/api/v1/me/teacher/exams` | `MeTeacherController@exams` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 252 | `POST` | `/api/v1/me/teacher/exams` | `MeTeacherController@storeExam` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 253 | `GET` | `/api/v1/me/teacher/facility-bookings` | `MeTeacherController@facilityBookings` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 254 | `POST` | `/api/v1/me/teacher/facility-bookings` | `MeTeacherController@storeFacilityBooking` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 255 | `GET` | `/api/v1/me/teacher/grade-components` | `MeTeacherController@gradeComponents` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 256 | `GET` | `/api/v1/me/teacher/grades` | `MeTeacherController@grades` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 257 | `POST` | `/api/v1/me/teacher/grades/bulk` | `MeTeacherController@updateBulkGrades` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 258 | `POST` | `/api/v1/me/teacher/grades/lock` | `MeTeacherController@lockGrades` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 259 | `GET` | `/api/v1/me/teacher/homeroom` | `MeTeacherController@homeroom` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 260 | `GET` | `/api/v1/me/teacher/homeroom/leave-requests` | `MeTeacherController@homeroomLeaveRequests` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 261 | `PATCH` | `/api/v1/me/teacher/homeroom/leave-requests/{id}/decision` | `MeTeacherController@decideHomeroomLeaveRequest` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 262 | `GET` | `/api/v1/me/teacher/journals` | `MeTeacherController@journals` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 263 | `POST` | `/api/v1/me/teacher/journals` | `MeTeacherController@storeJournal` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 264 | `GET` | `/api/v1/me/teacher/leave-requests` | `MeTeacherController@leaveRequests` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 265 | `POST` | `/api/v1/me/teacher/leave-requests` | `MeTeacherController@storeLeaveRequest` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 266 | `GET` | `/api/v1/me/teacher/materials` | `MeTeacherController@materials` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 267 | `POST` | `/api/v1/me/teacher/materials` | `MeTeacherController@storeMaterial` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 268 | `GET` | `/api/v1/me/teacher/question-banks` | `MeTeacherController@questionBanks` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 269 | `POST` | `/api/v1/me/teacher/question-banks` | `MeTeacherController@storeQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 270 | `DELETE` | `/api/v1/me/teacher/question-banks/{id}` | `MeTeacherController@destroyQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 271 | `GET` | `/api/v1/me/teacher/question-banks/{id}` | `MeTeacherController@showQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 272 | `PUT` | `/api/v1/me/teacher/question-banks/{id}` | `MeTeacherController@updateQuestionBank` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 273 | `POST` | `/api/v1/me/teacher/question-banks/{id}/questions` | `MeTeacherController@storeQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 274 | `GET` | `/api/v1/me/teacher/questions` | `MeTeacherController@questions` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 275 | `POST` | `/api/v1/me/teacher/questions` | `MeTeacherController@storeDirectQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 276 | `DELETE` | `/api/v1/me/teacher/questions/{id}` | `MeTeacherController@destroyQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 277 | `GET` | `/api/v1/me/teacher/questions/{id}` | `MeTeacherController@showQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 278 | `PUT` | `/api/v1/me/teacher/questions/{id}` | `MeTeacherController@updateQuestion` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 279 | `GET` | `/api/v1/me/teacher/schedules` | `MeTeacherController@schedules` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 280 | `GET` | `/api/v1/me/teacher/self-attendance` | `MeTeacherController@selfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 281 | `POST` | `/api/v1/me/teacher/self-attendance` | `MeTeacherController@recordSelfAttendance` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 282 | `POST` | `/api/v1/me/teacher/student-points` | `MeTeacherController@storeStudentPoint` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 283 | `POST` | `/api/v1/me/teacher/submissions/{id}/grade` | `MeTeacherController@gradeSubmission` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 284 | `POST` | `/api/v1/me/teacher/tickets` | `MeTeacherController@storeTicket` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 285 | `GET` | `/api/v1/notification-logs` | `Ntf\MessagingController@indexLogs` | `(none) / auth:sanctum` | `notification.broadcast` | P0 - Notifikasi & Audit Log |
| 286 | `GET` | `/api/v1/notification-logs/{id}` | `Ntf\MessagingController@showLog` | `(none) / auth:sanctum` | `notification.broadcast` | P0 - Notifikasi & Audit Log |
| 287 | `POST` | `/api/v1/notification-logs/{id}/retry` | `Ntf\MessagingController@retry` | `(none) / auth:sanctum` | `notification.broadcast` | P0 - Notifikasi & Audit Log |
| 288 | `GET` | `/api/v1/notification-preferences` | `Ntf\NotificationPreferenceController@index` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 289 | `PUT` | `/api/v1/notification-preferences` | `Ntf\NotificationPreferenceController@update` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` | Self-Service - Inbox Notifikasi & Preferensi |
| 290 | `GET` | `/api/v1/notification-preferences/types` | `Ntf\NotificationPreferenceController@types` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 291 | `GET` | `/api/v1/notification-templates` | `Ntf\MessagingController@indexTemplates` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Template Notifikasi |
| 292 | `POST` | `/api/v1/notification-templates` | `Ntf\MessagingController@storeTemplate` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Template Notifikasi |
| 293 | `POST` | `/api/v1/notification-templates/send` | `Ntf\MessagingController@send` | `(none) / auth:sanctum` | `notification.broadcast` | P0 - Notifikasi & Audit Log |
| 294 | `DELETE` | `/api/v1/notification-templates/{id}` | `Ntf\MessagingController@destroyTemplate` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Template Notifikasi |
| 295 | `GET` | `/api/v1/notification-templates/{id}` | `Ntf\MessagingController@showTemplate` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Template Notifikasi |
| 296 | `PUT` | `/api/v1/notification-templates/{id}` | `Ntf\MessagingController@updateTemplate` | `(none) / auth:sanctum` | `notification.broadcast` | Operasional - Template Notifikasi |
| 297 | `GET` | `/api/v1/notifications` | `Ntf\NotificationController@index` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 298 | `POST` | `/api/v1/notifications/broadcast` | `Ntf\NotificationController@broadcast` | `(none) / auth:sanctum` | `notification.broadcast` | P0 - Notifikasi & Audit Log |
| 299 | `PATCH` | `/api/v1/notifications/read-all` | `Ntf\NotificationController@markAllAsRead` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` | Self-Service - Inbox Notifikasi & Preferensi |
| 300 | `DELETE` | `/api/v1/notifications/{id}` | `Ntf\NotificationController@destroy` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` | Self-Service - Inbox Notifikasi & Preferensi |
| 301 | `GET` | `/api/v1/notifications/{id}` | `Ntf\NotificationController@show` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 302 | `PATCH` | `/api/v1/notifications/{id}/read` | `Ntf\NotificationController@markAsRead` | `(none) / auth:sanctum` | `auth:sanctum (Self-service Inbox/Preferences)` | Self-Service - Inbox Notifikasi & Preferensi |
| 303 | `GET` | `/api/v1/partnerships` | `Hum\PartnershipController@index` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 304 | `POST` | `/api/v1/partnerships` | `Hum\PartnershipController@store` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 305 | `GET` | `/api/v1/partnerships/expiring` | `Hum\PartnershipController@expiring` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 306 | `DELETE` | `/api/v1/partnerships/{id}` | `Hum\PartnershipController@destroy` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 307 | `GET` | `/api/v1/partnerships/{id}` | `Hum\PartnershipController@show` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 308 | `PUT` | `/api/v1/partnerships/{id}` | `Hum\PartnershipController@update` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 309 | `GET` | `/api/v1/point-thresholds` | `Std\PointTypeController@indexThresholds` | `(none) / auth:sanctum` | `student.point.read` | P0 - Konfigurasi Sanksi & Poin |
| 310 | `POST` | `/api/v1/point-thresholds` | `Std\PointTypeController@storeThreshold` | `(none) / auth:sanctum` | `student.point.write` | P0 - Konfigurasi Sanksi & Poin |
| 311 | `DELETE` | `/api/v1/point-thresholds/{id}` | `Std\PointTypeController@destroyThreshold` | `(none) / auth:sanctum` | `student.point.write` | P0 - Konfigurasi Sanksi & Poin |
| 312 | `GET` | `/api/v1/point-thresholds/{id}` | `Std\PointTypeController@showThreshold` | `(none) / auth:sanctum` | `student.point.read` | P0 - Konfigurasi Sanksi & Poin |
| 313 | `PUT` | `/api/v1/point-thresholds/{id}` | `Std\PointTypeController@updateThreshold` | `(none) / auth:sanctum` | `student.point.write` | P0 - Konfigurasi Sanksi & Poin |
| 314 | `GET` | `/api/v1/point-types` | `Std\PointTypeController@indexTypes` | `(none) / auth:sanctum` | `student.point.read` | P0 - Konfigurasi Sanksi & Poin |
| 315 | `POST` | `/api/v1/point-types` | `Std\PointTypeController@storeType` | `(none) / auth:sanctum` | `student.point.write` | P0 - Konfigurasi Sanksi & Poin |
| 316 | `DELETE` | `/api/v1/point-types/{id}` | `Std\PointTypeController@destroyType` | `(none) / auth:sanctum` | `student.point.write` | P0 - Konfigurasi Sanksi & Poin |
| 317 | `GET` | `/api/v1/point-types/{id}` | `Std\PointTypeController@showType` | `(none) / auth:sanctum` | `student.point.read` | P0 - Konfigurasi Sanksi & Poin |
| 318 | `PUT` | `/api/v1/point-types/{id}` | `Std\PointTypeController@updateType` | `(none) / auth:sanctum` | `student.point.write` | P0 - Konfigurasi Sanksi & Poin |
| 319 | `GET` | `/api/v1/ppdb/applications` | `PPDBApplicationController@index` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 320 | `POST` | `/api/v1/ppdb/applications` | `PPDBApplicationController@store` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 321 | `GET` | `/api/v1/ppdb/applications/{id}` | `PPDBApplicationController@show` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 322 | `GET` | `/api/v1/ppdb/applications/{id}/documents` | `PPDBDocumentController@index` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 323 | `POST` | `/api/v1/ppdb/applications/{id}/documents` | `PPDBDocumentController@store` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 324 | `DELETE` | `/api/v1/ppdb/applications/{id}/documents/{documentId}` | `PPDBDocumentController@destroy` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 325 | `GET` | `/api/v1/ppdb/applications/{id}/documents/{documentId}/download` | `PPDBDocumentController@download` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 326 | `POST` | `/api/v1/ppdb/applications/{id}/re-register` | `PPDBEnrollmentController@reRegister` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 327 | `GET` | `/api/v1/ppdb/applications/{id}/receipt` | `PPDBReceiptAndStatsController@receipt` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 328 | `PATCH` | `/api/v1/ppdb/applications/{id}/steps/{step}` | `PPDBApplicationController@updateStep` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 329 | `POST` | `/api/v1/ppdb/applications/{id}/submit` | `PPDBApplicationController@submit` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 330 | `GET` | `/api/v1/ppdb/me` | `PPDBAccountController@me` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 331 | `GET` | `/api/v1/ppdb/stats` | `PPDBReceiptAndStatsController@stats` | `(none) / auth:sanctum` | `ppdb.applicant.read` | Operasional - PPDB |
| 332 | `GET` | `/api/v1/progress-reports` | `ProgressReportController@index` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 333 | `PATCH` | `/api/v1/progress-reports/bulk-approve` | `ProgressReportController@bulkApprove` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 334 | `POST` | `/api/v1/progress-reports/bulk-approve` | `ProgressReportController@bulkApprove` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 335 | `GET` | `/api/v1/progress-reports/class-status` | `ProgressReportController@classStatus` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 336 | `POST` | `/api/v1/progress-reports/generate` | `ProgressReportController@generate` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 337 | `GET` | `/api/v1/progress-reports/preview` | `ProgressReportController@preview` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 338 | `GET` | `/api/v1/progress-reports/{id}` | `ProgressReportController@show` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 339 | `PATCH` | `/api/v1/progress-reports/{id}/approve` | `ProgressReportController@approve` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 340 | `GET` | `/api/v1/progress-reports/{id}/pdf` | `ProgressReportController@pdf` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 341 | `GET` | `/api/v1/report-cards` | `ReportCardController@index` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 342 | `PATCH` | `/api/v1/report-cards/bulk-approve` | `ReportCardController@bulkApprove` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 343 | `POST` | `/api/v1/report-cards/bulk-approve` | `ReportCardController@bulkApprove` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 344 | `PATCH` | `/api/v1/report-cards/bulk-lock` | `ReportCardController@bulkLock` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 345 | `POST` | `/api/v1/report-cards/bulk-lock` | `ReportCardController@bulkLock` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 346 | `GET` | `/api/v1/report-cards/class-status` | `ReportCardController@classStatus` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 347 | `POST` | `/api/v1/report-cards/generate` | `ReportCardController@generate` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 348 | `GET` | `/api/v1/report-cards/preview` | `ReportCardController@preview` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 349 | `GET` | `/api/v1/report-cards/{id}` | `ReportCardController@show` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 350 | `PATCH` | `/api/v1/report-cards/{id}/approve` | `ReportCardController@approve` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 351 | `PATCH` | `/api/v1/report-cards/{id}/lock` | `ReportCardController@lock` | `(none) / auth:sanctum` | `academic.report.generate` | P0 - Nilai & Rapor |
| 352 | `GET` | `/api/v1/report-cards/{id}/pdf` | `ReportCardController@pdf` | `(none) / auth:sanctum` | `academic.grade.read` | Operasional - Laporan Rapor |
| 353 | `GET` | `/api/v1/report-description-templates` | `ReportDescriptionTemplateController@index` | `(none) / auth:sanctum` | `academic.grade.write` | Operasional - Template Deskripsi Capaian |
| 354 | `GET` | `/api/v1/reports/{type}` | `Rpt\ReportController@show` | `(none) / auth:sanctum` | `reports.view` | P0 - Laporan Rekapitulasi |
| 355 | `GET` | `/api/v1/reports/{type}/export` | `Rpt\ReportController@export` | `(none) / auth:sanctum` | `reports.view` | P0 - Laporan Rekapitulasi |
| 356 | `GET` | `/api/v1/rooms/types` | `RoomController@types` | `(none) / auth:sanctum` | `master.room.manage` | Master - Sarana & Ruang |
| 357 | `GET` | `/api/v1/salary-slips` | `TeacherController@mySalarySlips` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 358 | `GET` | `/api/v1/salary-slips/{slipId}/download` | `TeacherController@downloadMySalarySlip` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 359 | `GET` | `/api/v1/schedules` | `ScheduleController@index` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - Jadwal Pelajaran |
| 360 | `POST` | `/api/v1/schedules/conflicts/check` | `ScheduleController@checkConflicts` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - Jadwal Pelajaran |
| 361 | `GET` | `/api/v1/schedules/{id}` | `ScheduleController@show` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - Jadwal Pelajaran |
| 362 | `GET` | `/api/v1/staff-attendances` | `StaffAttendanceController@index` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Presensi Guru & Staf |
| 363 | `POST` | `/api/v1/staff-attendances` | `StaffAttendanceController@store` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Presensi Guru & Staf |
| 364 | `POST` | `/api/v1/staff-attendances/check-in` | `StaffAttendanceController@checkIn` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Presensi Guru & Staf |
| 365 | `POST` | `/api/v1/staff-attendances/check-out` | `StaffAttendanceController@checkOut` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Presensi Guru & Staf |
| 366 | `GET` | `/api/v1/staff-attendances/settings` | `StaffAttendanceController@getSettings` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Presensi Guru & Staf |
| 367 | `PUT` | `/api/v1/staff-attendances/settings` | `StaffAttendanceController@updateSettings` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Presensi Guru & Staf |
| 368 | `GET` | `/api/v1/staff-attendances/summary` | `StaffAttendanceController@summary` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Presensi Guru & Staf |
| 369 | `GET` | `/api/v1/staff-attendances/today` | `StaffAttendanceController@today` | `(none) / auth:sanctum` | `academic.attendance.read` | Operasional - Presensi Guru & Staf |
| 370 | `PATCH` | `/api/v1/staff-attendances/{id}` | `StaffAttendanceController@correct` | `(none) / auth:sanctum` | `academic.attendance.write` | Operasional - Presensi Guru & Staf |
| 371 | `GET` | `/api/v1/student-points` | `Std\StudentPointController@index` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Poin |
| 372 | `POST` | `/api/v1/student-points` | `Std\StudentPointController@store` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Poin |
| 373 | `DELETE` | `/api/v1/student-points/{id}` | `Std\StudentPointController@destroy` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Poin |
| 374 | `GET` | `/api/v1/student-points/{id}` | `Std\StudentPointController@show` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Poin |
| 375 | `GET` | `/api/v1/students` | `StudentController@index` | `(none) / auth:sanctum` | `master.student.read` | Master - Siswa (Scoped) |
| 376 | `GET` | `/api/v1/students/{id}` | `StudentController@show` | `(none) / auth:sanctum` | `master.student.read` | Master - Siswa (Scoped) |
| 377 | `GET` | `/api/v1/students/{id}/guardians` | `GuardianController@studentGuardians` | `(none) / auth:sanctum` | `master.student.read` | Master - Siswa (Scoped) |
| 378 | `GET` | `/api/v1/students/{id}/points/summary` | `Std\StudentPointController@summary` | `(none) / auth:sanctum` | `master.student.read` | Master - Siswa (Scoped) |
| 379 | `GET` | `/api/v1/students/{id}/status-history` | `StudentController@statusHistory` | `(none) / auth:sanctum` | `master.student.read` | Master - Siswa (Scoped) |
| 380 | `GET` | `/api/v1/teacher/me/salary-slips` | `TeacherController@mySalarySlips` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 381 | `GET` | `/api/v1/teacher/me/salary-slips/{slipId}/download` | `TeacherController@downloadMySalarySlip` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 382 | `GET` | `/api/v1/teacher/me/teaching-recap` | `TeacherController@myTeachingRecap` | `(none) / auth:sanctum` | `auth:sanctum + TeacherPolicy (IDOR Scoped)` | Self-Service - Portal Guru Mandiri |
| 383 | `GET` | `/api/v1/teaching-materials` | `TeachingMaterialController@index` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Bahan Ajar |
| 384 | `POST` | `/api/v1/teaching-materials` | `TeachingMaterialController@store` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 385 | `DELETE` | `/api/v1/teaching-materials/{id}` | `TeachingMaterialController@destroy` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 386 | `GET` | `/api/v1/teaching-materials/{id}` | `TeachingMaterialController@show` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - LMS Bahan Ajar |
| 387 | `POST` | `/api/v1/teaching-materials/{id}/versions` | `TeachingMaterialController@createVersion` | `(none) / auth:sanctum` | `academic.journal.write` | Operasional - LMS Bahan Ajar |
| 388 | `GET` | `/api/v1/tickets` | `Sar\TicketController@index` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 389 | `POST` | `/api/v1/tickets` | `Sar\TicketController@store` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 390 | `GET` | `/api/v1/tickets/summary` | `Sar\TicketController@summary` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 391 | `GET` | `/api/v1/tickets/technicians` | `Sar\TicketController@technicians` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 392 | `GET` | `/api/v1/tickets/{id}` | `Sar\TicketController@show` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 393 | `POST` | `/api/v1/tickets/{id}/comments` | `Sar\TicketController@comment` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 394 | `POST` | `/api/v1/tickets/{id}/dispose` | `Sar\TicketController@dispose` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 395 | `PATCH` | `/api/v1/tickets/{id}/status` | `Sar\TicketController@status` | `(none) / auth:sanctum` | `facility.asset.manage` | Operasional - Sarpras Helpdesk |
| 396 | `GET` | `/api/v1/time-slots` | `TimeSlotController@index` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - Jadwal Pelajaran |
| 397 | `GET` | `/api/v1/time-slots/{id}` | `TimeSlotController@show` | `(none) / auth:sanctum` | `academic.schedule.read` | Operasional - Jadwal Pelajaran |
| 398 | `GET` | `/api/v1/tracer-surveys` | `Hum\TracerSurveyController@index` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 399 | `POST` | `/api/v1/tracer-surveys` | `Hum\TracerSurveyController@store` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 400 | `DELETE` | `/api/v1/tracer-surveys/{id}` | `Hum\TracerSurveyController@destroy` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 401 | `GET` | `/api/v1/tracer-surveys/{id}` | `Hum\TracerSurveyController@show` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 402 | `PUT` | `/api/v1/tracer-surveys/{id}` | `Hum\TracerSurveyController@update` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 403 | `POST` | `/api/v1/tracer-surveys/{id}/responses` | `Hum\TracerSurveyController@storeResponse` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 404 | `GET` | `/api/v1/tracer-surveys/{id}/stats` | `Hum\TracerSurveyController@stats` | `(none) / auth:sanctum` | `cms.site.manage` | Operasional - Humas & Kemitraan |
| 405 | `DELETE` | `/api/v1/trip-participants/{id}` | `Std\TripController@destroyParticipant` | `(none) / auth:sanctum` | `auth:sanctum` | Operasional Umum |
| 406 | `GET` | `/api/v1/trips` | `Std\TripController@index` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Study Trip |
| 407 | `POST` | `/api/v1/trips` | `Std\TripController@store` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Study Trip |
| 408 | `DELETE` | `/api/v1/trips/{id}` | `Std\TripController@destroy` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Study Trip |
| 409 | `GET` | `/api/v1/trips/{id}` | `Std\TripController@show` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Study Trip |
| 410 | `PUT` | `/api/v1/trips/{id}` | `Std\TripController@update` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Study Trip |
| 411 | `GET` | `/api/v1/trips/{id}/consents` | `Std\TripController@indexConsents` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Study Trip |
| 412 | `POST` | `/api/v1/trips/{id}/consents` | `Std\TripController@storeConsent` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Study Trip |
| 413 | `GET` | `/api/v1/trips/{id}/participants` | `Std\TripController@indexParticipants` | `(none) / auth:sanctum` | `student.point.read` | Operasional - Kesiswaan Study Trip |
| 414 | `POST` | `/api/v1/trips/{id}/participants` | `Std\TripController@storeParticipant` | `(none) / auth:sanctum` | `student.point.write` | Operasional - Kesiswaan Study Trip |
| 415 | `GET` | `/api/v1/user` | `AuthController@me` | `(none) / auth:sanctum` | `auth:sanctum (Self-service / Sesi Pengguna)` | Self-Service - Akun & Sesi |