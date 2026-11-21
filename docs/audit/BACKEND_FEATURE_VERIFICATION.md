# Verifikasi Fitur Backend SISTLABUM (Master Verification Table)
**Dokumen Referensi:** `docs/audit/BACKEND_FEATURE_VERIFICATION.md`  
**Sumber Kebenaran Tunggal (SSOT):** `13_MASTER_MODULES.md` & `04_PRD_BACKEND.md`  
**Status Verifikasi:** `VERIFIED`, `PARTIAL`, `NOT_IMPLEMENTED`, `BROKEN`, `BLOCKED`, `UNTESTED`, `NEEDS_REVIEW`

---

## 1. Tabel Master Verifikasi 75 Fitur Backend

| ID | Modul | Nama Fitur | Implementasi Utama | Test Automated | Security / Auth | Status | Bukti Kode / Method |
|:---|:---:|:---|:---|:---|:---:|:---:|:---|
| **BE-AUTH-01** | AUTH | Autentikasi & Sesi | `AuthController`, `AuthService` | `LoginTest`, `SessionTest` | Sanctum, Token Expiry, Logout | `VERIFIED` | `AuthController@login`, `me`, `logout`, `refresh` |
| **BE-AUTH-02** | AUTH | Lupa & Reset Password | `AuthController`, `PasswordResetService` | `PasswordTest` | Token Hash, Expiry 60m | `VERIFIED` | `AuthController@forgotPassword`, `resetPassword` |
| **BE-AUTH-03** | AUTH | RBAC Multi-Role | `RbacController`, `RbacService` | `RbacTest` | `role:...`, `permission:...` | `VERIFIED` | `RbacController@roles`, `assignRole`, `syncPermissions` |
| **BE-AUTH-04** | AUTH | Kebijakan Sandi & Ganti Berkala | `AuthController`, `AuthService` | `PasswordTest` | Argon2id/Bcrypt, History check | `VERIFIED` | `AuthController@changePassword`, `validatePasswordPolicy` |
| **BE-AUTH-05** | AUTH | Log Audit Keamanan | `AuditLogController`, `AuditLogService` | `AuditLogTest` | `audit.read`, Anti-Tamper | `VERIFIED` | `AuditLogController@index`, `show`, `Auditable` trait |
| **BE-MST-01** | MST | Tahun Ajaran & Semester | `AcademicYearController`, `SemesterController` | `AcademicYearTest` | `master.academic_year.manage` | `VERIFIED` | `AcademicYearController@index`, `store`, `activate` |
| **BE-MST-02** | MST | Profil Siswa & Biodata | `StudentController`, `StudentService` | `StudentTest` | Encrypted NIK, Scoped Read | `VERIFIED` | `StudentController@index`, `show`, `store`, `import` |
| **BE-MST-03** | MST | Guru & Tenaga Kependidikan | `TeacherController`, `StaffController` | `TeacherStaffTest` | `master.teacher.manage` | `VERIFIED` | `TeacherController@index`, `store`, `import` |
| **BE-MST-04** | MST | Orang Tua / Wali & Relasi | `GuardianController`, `GuardianService` | `GuardianTest` | Relasi Siswa-Wali, Anti-IDOR | `VERIFIED` | `GuardianController@index`, `attachStudentGuardian` |
| **BE-MST-05** | MST | Kelas, Rombel & Penempatan | `ClassController`, `ClassService` | `ClassTest` | `master.class.manage` | `VERIFIED` | `ClassController@index`, `addStudents`, `transferStudent` |
| **BE-MST-06** | MST | Mata Pelajaran & Penugasan | `SubjectController`, `TeachingAssignmentController` | `SubjectAssignmentTest` | `master.subject.manage` | `VERIFIED` | `SubjectController@index`, `TeachingAssignmentController@store` |
| **BE-MST-07** | MST | Ruangan & Fasilitas Dasar | `RoomController`, `RoomService` | `RoomTest` | `master.room.manage` | `VERIFIED` | `RoomController@index`, `store`, `update`, `destroy` |
| **BE-MST-08** | MST | Kenaikan Kelas & Kelulusan | `PromotionController`, `PromotionService` | `PromotionTest` | `master.promotion.manage` | `VERIFIED` | `PromotionController@promote`, `graduate`, `preview` |
| **BE-CMS-01** | CMS | Profil Sekolah & Pengaturan | `SiteSettingController`, `SiteSettingService` | `SiteSettingTest` | Public Cache / Auth Admin | `VERIFIED` | `SiteSettingController@show`, `update` |
| **BE-CMS-02** | CMS | Berita & Artikel Dinamis | `PostController`, `PostService` | `PostTest` | `cms.post.manage`, Slug unique | `VERIFIED` | `PostController@index`, `store`, `showBySlug`, `publish` |
| **BE-CMS-03** | CMS | Kategori & Taksonomi | `PostCategoryController` | `PostTest` | `cms.post.manage` | `VERIFIED` | `PostCategoryController@index`, `store`, `destroy` |
| **BE-CMS-04** | CMS | Galeri Foto & Buletin | `AlbumController`, `BulletinController` | `GalleryTest` | `cms.gallery.manage` | `VERIFIED` | `AlbumController@index`, `BulletinController@index` |
| **BE-CMS-05** | CMS | Agenda & Kalender Kegiatan | `EventController`, `EventService` | `EventTest` | Visibility Public/Internal | `VERIFIED` | `EventController@index`, `store`, `upcoming` |
| **BE-CMS-06** | CMS | Pusat Unduhan Dokumen | `DownloadController`, `DownloadService` | `DownloadTest` | `cms.download.manage` | `VERIFIED` | `DownloadController@index`, `store`, `incrementDownload` |
| **BE-CMS-07** | CMS | Halaman Dinamis & Menu | `PageController`, `NavigationMenuController` | `PageTest` | `cms.page.manage` | `VERIFIED` | `PageController@index`, `showBySlug`, `NavigationMenuController` |
| **BE-PPDB-01** | PPDB | Gelombang & Jalur | `PPDBWaveController`, `PPDBTrackController` | `PPDBConfigurationTest` | `ppdb.wave.manage` | `VERIFIED` | `PPDBWaveController@index`, `store`, `PPDBTrackController` |
| **BE-PPDB-02** | PPDB | Akun Calon Siswa & OTP | `PPDBAccountController`, `PPDBAccountService` | `PPDBAccountTest` | Public Register, OTP Verification | `VERIFIED` | `PPDBAccountController@register`, `verifyOtp`, `login` |
| **BE-PPDB-03** | PPDB | Formulir Bertahap & Draf | `PPDBApplicationController`, `PPDBApplicationService` | `PPDBApplicationTest` | Applicant Ownership Check | `VERIFIED` | `PPDBApplicationController@getDraft`, `saveStep`, `submit` |
| **BE-PPDB-04** | PPDB | Unggah Berkas Persyaratan | `PPDBDocumentController`, `PPDBDocumentService` | `PPDBDocumentTest` | Private File, MIME & Size limit | `VERIFIED` | `PPDBDocumentController@upload`, `download`, `destroy` |
| **BE-PPDB-05** | PPDB | Verifikasi Berkas Panitia | `PPDBVerificationController`, `PPDBVerificationService` | `PPDBVerificationTest` | `ppdb.applicant.verify` | `VERIFIED` | `PPDBVerificationController@verifyDocument`, `verifyApplication` |
| **BE-PPDB-06** | PPDB | Seleksi & Perangkingan Kuota | `PPDBSelectionController`, `PPDBSelectionService` | `PPDBSelectionTest` | `ppdb.selection.run` | `VERIFIED` | `PPDBSelectionController@runSelection`, `getRankings` |
| **BE-PPDB-07** | PPDB | Pengumuman Kelulusan | `PPDBResultController`, `PPDBResultService` | `PPDBResultTest` | Scheduled Publish, Scoped Search | `VERIFIED` | `PPDBResultController@publicCheck`, `applicantResult` |
| **BE-PPDB-08** | PPDB | Daftar Ulang & Konversi Siswa | `PPDBEnrollmentController`, `PPDBEnrollmentService` | `PPDBEnrollmentTest` | Transaksi DB, Auto User Generation | `VERIFIED` | `PPDBEnrollmentController@enroll`, `convertToStudent` |
| **BE-PPDB-09** | PPDB | Kuitansi & Statistik PPDB | `PPDBReceiptAndStatsController`, `PPDBReceiptAndStatsService` | `PPDBReceiptAndStatsTest` | Signed Receipt QR, Real-Time Stats | `VERIFIED` | `PPDBReceiptAndStatsController@receiptPdf`, `stats` |
| **BE-GUEST-01** | GUEST | Buku Tamu Digital | `GuestController`, `GuestService` | `GuestTest` | Guest token, Front desk checkout | `VERIFIED` | `GuestController@store`, `index`, `checkout` |
| **BE-GUEST-02** | GUEST | Aduan Whistleblowing | `ComplaintController`, `ComplaintService` | `ComplaintTest` | Anonymous Tracking Code, Confidential | `VERIFIED` | `ComplaintController@store`, `track`, `updateStatus` |
| **BE-GUEST-03** | GUEST | Tanda Tangan & Swafoto Tamu | `GuestController`, `GuestService` | `GuestSignatureTest` | Signature File Verification | `VERIFIED` | `GuestController@uploadSignature`, `signatureView` |
| **BE-ACD-01** | ACD | Kalender & Jam Pelajaran | `AcademicYearController`, `TimeSlotController` | `ScheduleTest` | `academic.schedule.manage` | `VERIFIED` | `TimeSlotController@index`, `store`, `AcademicYearController` |
| **BE-ACD-02** | ACD | Plotting Jadwal Pelajaran | `ScheduleController`, `ScheduleService` | `ScheduleTest` | Conflict Detection (Room & Teacher) | `VERIFIED` | `ScheduleController@index`, `store`, `bulkStore`, `byClass` |
| **BE-ACD-03** | ACD | Jurnal Mengajar Guru | `JournalController`, `JournalService` | `JournalTest` | Guru Ajar Ownership, Anti-IDOR | `VERIFIED` | `JournalController@index`, `store`, `show`, `todayPending` |
| **BE-ACD-04** | ACD | Presensi Kelas & Gerbang | `AttendanceController`, `AcademicAttendanceService` | `AttendanceTest` | Kelas / Gerbang source separation | `VERIFIED` | `AttendanceController@recordBatch`, `gateScan`, `summary` |
| **BE-ACD-05** | ACD | Presensi Guru & Staf | `StaffAttendanceController`, `StaffAttendanceService` | `StaffAttendanceTest` | Selfie Photo, Geofence / Notes | `VERIFIED` | `StaffAttendanceController@clockIn`, `clockOut`, `history` |
| **BE-ACD-06** | ACD | Repositori Bahan Ajar | `TeachingMaterialController`, `TeachingMaterialService` | `TeachingMaterialTest` | Private/Public Storage, Scoped Class | `VERIFIED` | `TeachingMaterialController@index`, `store`, `download` |
| **BE-ACD-07** | ACD | Tugas & Submission LMS | `AssignmentController`, `LmsService` | `LmsTest` | Deadline Enforcement, Anti-Late | `VERIFIED` | `AssignmentController@store`, `submit`, `gradeSubmission` |
| **BE-GRD-01** | GRD | Komponen Penilaian | `GradeComponentController`, `GradeComponentService` | `GradeTest` | KKTP threshold, Weight validation | `VERIFIED` | `GradeComponentController@index`, `store`, `byAssignment` |
| **BE-GRD-02** | GRD | Input Nilai & Kalkulasi Akhir | `GradeController`, `GradeCalculationService` | `GradeTest` | Batch upsert, Auto weighting | `VERIFIED` | `GradeController@batchStore`, `calculateFinalScore` |
| **BE-GRD-03** | GRD | E-Progress Report & Rapor QR | `ReportCardController`, `ReportCardService` | `ReportCardTest` | Digital QR Sign, Verification Token | `VERIFIED` | `ReportCardController@generatePdf`, `PublicVerificationController` |
| **BE-GRD-04** | GRD | Catatan Karakter Walas | `CharacterNoteController`, `CharacterNoteService` | `CharacterNoteTest` | Wali Kelas Scoping, Dimension score | `VERIFIED` | `CharacterNoteController@index`, `storeBatch`, `summary` |
| **BE-CBT-01** | CBT | Bank Soal | `QuestionBankController`, `QuestionController` | `QuestionBankTest` | `cbt.bank.write`, Versioning on edit | `VERIFIED` | `QuestionBankController@index`, `QuestionController@store` |
| **BE-CBT-02** | CBT | Pembuatan Ujian | `ExamController`, `ExamService` | `ExamManagementTest` | Exam Status Machine, Package distribution | `VERIFIED` | `ExamController@index`, `store`, `assignQuestions` |
| **BE-CBT-03** | CBT | Token & Plotting Ruang | `ExamRoomTokenController`, `ExamParticipantCardController` | `ExamTokenAndRoomTest` | Token Expiry, Auto room plotting | `VERIFIED` | `ExamRoomTokenController@regenerate`, `distributeParticipants` |
| **BE-CBT-04** | CBT | Sesi Ujian Siswa & Autosave | `ExamSessionController`, `ExamSessionService` | `ExamExecutionTest` | Strip Answer Keys, Seeded Shuffle | `VERIFIED` | `ExamSessionController@start`, `questions`, `saveAnswer` |
| **BE-CBT-05** | CBT | Anti-Cheat & Pelanggaran | `ExamSessionController`, `ExamSessionService` | `ExamExecutionTest` | Auto-Locking, Proctor Action Audit | `VERIFIED` | `ExamSessionController@logViolation`, `manage` (unlock) |
| **BE-CBT-06** | CBT | Monitoring Live | `ExamSessionController@monitor`, `ExamSessionService` | `ExamMonitoringTest` | Live Room Progress, Heartbeat Status, Student List | `VERIFIED` | `ExamSessionController@monitor`, `manage` |
| **BE-CBT-07** | CBT | Koreksi Otomatis & Uraian | `ExamGradingController`, `ExamGradingService` | `ExamGradingAndAnalysisTest` | Auto Scoring PG/BS, Rubrik Uraian | `VERIFIED` | `ExamGradingController@autoGrade`, `gradeEssay` |
| **BE-CBT-08** | CBT | Analisis Butir Soal | `ExamGradingController`, `ExamGradingService` | `ExamGradingAndAnalysisTest` | Difficulty Index & Discrimination | `VERIFIED` | `ExamGradingController@itemAnalysis`, `recalculate` |
| **BE-CBT-09** | CBT | Remedial & Pengayaan | `ExamGradingController@createRemedial`, `ExamGradingService` | `ExamRemedialTest` | KKTP threshold, Package Cloning, Score Capping | `VERIFIED` | `ExamGradingController@createRemedial`, `ExamController@store` |
| **BE-CBT-10** | CBT | Berita Acara & Rekap Ujian | `ExamReportController`, `ExamReportService` | `ExamReportMinutesTest` | Summary JSON, Official Minutes Document / PDF | `VERIFIED` | `ExamReportController@report`, `minutes` |
| **BE-CBT-11** | CBT | Server Lokal & Sinkronisasi | `ExamSyncController`, `ExamSyncService` | `ExamLocalSyncTest` | Encrypted Exam Package, Idempotent Result Sync | `VERIFIED` | `ExamSyncController@pullPackage`, `pushResults` |
| **BE-CBT-12** | CBT | Deteksi Anomali Perangkat/IP | `ExamSessionController`, `ExamSessionService` | `ExamAnomalyHeartbeatTest` | IP / Device Tracking, Blur Count, Time Gap Anomaly | `VERIFIED` | `ExamSessionController@heartbeat`, `anomalies` |
| **BE-STD-01** | STD | Poin Pelanggaran & Prestasi | `PointTypeController`, `StudentPointController` | `StudentDisciplineTest` | Sanksi Threshold, In-App Notification | `VERIFIED` | `StudentPointController@store`, `studentSummary` |
| **BE-STD-02** | STD | Ekstrakurikuler & OSIS | `ExtracurricularController`, `ExtracurricularService` | `ExtracurricularTest` | Pembina Scoping, Attendance | `VERIFIED` | `ExtracurricularController@index`, `registerMember` |
| **BE-STD-03** | STD | Prestasi & Lomba | `CompetitionController`, `AchievementController` | `CompetitionAndAchievementTest` | Reward Points, Certificate Upload | `VERIFIED` | `CompetitionController@store`, `AchievementController@store` |
| **BE-STD-04** | STD | Study Trip & Perizinan | `TripController`, `StudyTripService` | `StudyTripTest` | Guardian Consent, Non-Financial | `VERIFIED` | `TripController@store`, `submitConsent`, `manifest` |
| **BE-BK-01** | BK | Kasus & Sesi Konseling Privat | `CounselingCaseController`, `CounselingSessionController` | `CounselingCaseAndSessionTest` | Strictly Counselor/Admin, Private | `VERIFIED` | `CounselingCaseController@store`, `CounselingSessionController` |
| **BE-BK-02** | BK | Deteksi Dini (Early Warning) | `EarlyWarningController`, `EarlyWarningService` | `EarlyWarningTest` | Rule Evaluation (Att, Grade, Points) | `VERIFIED` | `EarlyWarningController@index`, `evaluate`, `handle` |
| **BE-BK-03** | BK | Permohonan & Rujukan Konseling | `CounselingRequestController`, `CounselingReferralController` | `CounselingRequestTest` | Student/Parent Request Flow | `VERIFIED` | `CounselingRequestController@store`, `CounselingReferralController` |
| **BE-SAR-01** | SAR | Pelaporan Kerusakan (Helpdesk) | `TicketController`, `TicketService` | `TicketTest` | SLA tracking, Event logs, Photo | `VERIFIED` | `TicketController@store`, `updateStatus`, `addEvent` |
| **BE-SAR-02** | SAR | Inventaris Aset & Audit | `AssetController`, `AssetAuditController` | `AssetAndAuditTest` | QR Code Label, Stock Opname | `VERIFIED` | `AssetController@index`, `AssetAuditController@store` |
| **BE-SAR-03** | SAR | Booking Fasilitas & Ruangan | `BookingController`, `BookingService` | `FacilityAndBookingTest` | Conflict Detection, Multi-Item | `VERIFIED` | `BookingController@store`, `approve`, `reject`, `calendar` |
| **BE-SAR-04** | SAR | Sirkulasi Pustaka & Denda | `LibraryController`, `LibraryService` | `LibraryAndLabTest` | Barcode, Overdue Fine Calculation | `VERIFIED` | `LibraryController@borrow`, `returnBook`, `activeLoans` |
| **BE-HUM-01** | HUM | Kemitraan MoU & Pertukaran | `PartnershipController`, `ExchangeProgramController` | `PartnershipAndExchangeTest` | Expiry tracking, Participant quotas | `VERIFIED` | `PartnershipController@store`, `ExchangeProgramController` |
| **BE-HUM-02** | HUM | Penelusuran Alumni & Tracer | `AlumniController`, `TracerSurveyController` | `AlumniAndTracerTest` | Questionnaire responses, Public privacy | `VERIFIED` | `AlumniController@index`, `TracerSurveyController@submitResponse` |
| **BE-RPT-01** | RPT | Laporan Rekap Lintas Domain | `ReportController`, `ReportService` | `ReportTest` | Multi-module recap, CSV Export | `VERIFIED` | `ReportController@show`, `export` |
| **BE-RPT-02** | RPT | Dashboard Eksekutif & Persona | `DashboardController`, `DashboardService` | `DashboardTest` | Real-time CBT, Cache 2m, Anti-IDOR | `VERIFIED` | `DashboardController@executive`, `teacher`, `student`, `guardian` |
| **BE-NTF-01** | NTF/SYS | Push & In-App Broadcast | `NotificationController`, `NotificationService` | `NotificationTest` | FCM Token, Broadcast, Deep Link | `VERIFIED` | `NotificationController@index`, `registerDevice`, `broadcast` |
| **BE-NTF-02** | NTF/SYS | WhatsApp & Email Gateway | `MessagingController`, `MessagingService` | `MessagingTest` | Parameterized template, Delivery retry | `VERIFIED` | `MessagingController@storeTemplate`, `send`, `retryLog` |
| **BE-NTF-03** | NTF/SYS | Pengumuman Terarah & Pelacak | `AnnouncementController`, `AnnouncementService` | `AnnouncementTest` | Target role/class, Reader Tracking | `VERIFIED` | `AnnouncementController@store`, `markAsRead`, `readers` |
| **BE-NTF-04** | NTF/SYS | Preferensi Notifikasi | `NotificationPreferenceController`, `NotificationPreferenceService` | `NotificationPreferenceTest` | Channel selection (Push/WA/Email) | `VERIFIED` | `NotificationPreferenceController@index`, `update` |
| **BE-FILE-01** | NTF/SYS | Upload Bertanda Tangan | `FileController`, `FileStorageService` | `FileStorageTest` | Presigned URL, Anti-IDOR, TTL <= 5m | `VERIFIED` | `FileController@presign`, `uploadSigned`, `confirm`, `url` |

---

## 2. Verifikasi 7 Scheduled Jobs (Section 17 PRD)

| Nama Job | Jadwal Cron | Fungsi Bisnis | Command Artisan | Status | Bukti Implementasi & Test |
|---|---|---|---|:---:|---|
| **ppdb-close-wave** | `* * * * *` (Tiap menit) | Menutup otomatis gelombang pendaftaran yang melewati `close_at` | `php artisan ppdb:close-wave` | `VERIFIED` | `PpdbCloseWaveCommand.php`, `ScheduledJobsTest` |
| **exam-auto-submit** | `* * * * *` (Tiap menit) | Submit otomatis sesi ujian CBT yang melewati deadline | `php artisan exam:auto-submit` | `VERIFIED` | `ExamAutoSubmitCommand.php`, `ScheduledJobsTest` |
| **daily-attendance-summary** | `0 23 * * *` (Harian, 23:00) | Rekap presensi harian & deteksi dini siswa bermasalah (BK) | `php artisan attendance:daily-summary` | `VERIFIED` | `DailyAttendanceSummaryCommand.php`, `ScheduledJobsTest` |
| **progress-report-reminder** | `0 0 1 * *` (Bulanan) | Pengingat berkala pengisian nilai guru & catatan karakter | `php artisan academic:progress-report-reminder` | `VERIFIED` | `ProgressReportReminderCommand.php`, `ScheduledJobsTest` |
| **mou-expiry-reminder** | `0 7 * * *` (Harian, 07:00) | Peringatan jatuh tempo naskah kerja sama kemitraan MoU | `php artisan partnership:mou-expiry-reminder` | `VERIFIED` | `MouExpiryReminderCommand.php`, `ScheduledJobsTest` |
| **ticket-sla-reminder** | `0 * * * *` (Tiap jam) | Peringatan tiket sarpras pending yang melanggar batas SLA | `php artisan sarpras:ticket-sla-reminder` | `VERIFIED` | `TicketSlaReminderCommand.php`, `ScheduledJobsTest` |
| **backup-verify** | `0 0 * * 0` (Mingguan) | Verifikasi integritas cadangan basis data & penyimpanan | `php artisan system:backup-verify` | `VERIFIED` | `BackupVerifyCommand.php`, `ScheduledJobsTest` |
