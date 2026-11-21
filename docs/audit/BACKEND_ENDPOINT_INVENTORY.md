# Inventaris Endpoint API Backend SISTLABUM
**Dokumen Referensi:** `docs/audit/BACKEND_ENDPOINT_INVENTORY.md`  
**Base URL:** `/api/v1`  
**Total Rute Terdaftar:** 518 Routes (512 di bawah prefix `api/v1`)

---

## 1. Inventaris Endpoint per Modul

### A. Modul AUTH (Autentikasi & Hak Akses)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `POST` | `/api/v1/auth/login` | Guest | `AuthController@login` | Login multi-peran dengan kredensial | `VERIFIED` |
| `POST` | `/api/v1/auth/refresh` | Guest | `AuthController@refresh` | Refresh token sesi | `VERIFIED` |
| `POST` | `/api/v1/auth/forgot-password` | Guest | `AuthController@forgotPassword` | Permohonan reset password via token | `VERIFIED` |
| `POST` | `/api/v1/auth/reset-password` | Guest | `AuthController@resetPassword` | Eksekusi reset password | `VERIFIED` |
| `POST` | `/api/v1/auth/logout` | `auth:sanctum` | `AuthController@logout` | Revoke token dan hapus sesi | `VERIFIED` |
| `GET` | `/api/v1/auth/me` | `auth:sanctum` | `AuthController@me` | Ambil profil user dan permissions aktif | `VERIFIED` |
| `POST` | `/api/v1/auth/change-password` | `auth:sanctum` | `AuthController@changePassword` | Ganti password berkala dengan validasi histori | `VERIFIED` |
| `GET` | `/api/v1/rbac/roles` | `auth:sanctum`, `permission:auth.role.manage` | `RbacController@roles` | Daftar seluruh peran sistem | `VERIFIED` |
| `POST` | `/api/v1/rbac/users/{id}/roles` | `auth:sanctum`, `permission:auth.role.manage` | `RbacController@assignRole` | Penetapan peran ganda ke pengguna | `VERIFIED` |
| `GET` | `/api/v1/audit-logs` | `auth:sanctum`, `permission:audit.read` | `AuditLogController@index` | Riwayat log audit aktivitas keamanan berpaginasi | `VERIFIED` |

### B. Modul MST (Master Data Sekolah)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/academic-years` | `auth:sanctum` | `AcademicYearController@index` | Daftar tahun ajaran & semester | `VERIFIED` |
| `POST` | `/api/v1/academic-years` | `auth:sanctum`, `permission:master.academic_year.manage` | `AcademicYearController@store` | Buat tahun ajaran baru | `VERIFIED` |
| `POST` | `/api/v1/academic-years/{id}/activate` | `auth:sanctum`, `permission:master.academic_year.manage` | `AcademicYearController@activate` | Tetapkan tahun ajaran aktif | `VERIFIED` |
| `GET` | `/api/v1/students` | `auth:sanctum`, `permission:master.student.read` | `StudentController@index` | Daftar siswa terfilter | `VERIFIED` |
| `POST` | `/api/v1/students` | `auth:sanctum`, `permission:master.student.write` | `StudentController@store` | Tambah siswa baru | `VERIFIED` |
| `POST` | `/api/v1/students/import` | `auth:sanctum`, `permission:master.student.write` | `StudentController@import` | Impor massal data siswa via spreadsheet CSV | `VERIFIED` |
| `GET` | `/api/v1/teachers` | `auth:sanctum` | `TeacherController@index` | Daftar guru pengajar | `VERIFIED` |
| `POST` | `/api/v1/teachers` | `auth:sanctum`, `permission:master.teacher.manage` | `TeacherController@store` | Tambah guru baru | `VERIFIED` |
| `POST` | `/api/v1/teachers/import` | `auth:sanctum`, `permission:master.teacher.manage` | `TeacherController@import` | Impor massal guru via CSV | `VERIFIED` |
| `GET` | `/api/v1/staff` | `auth:sanctum` | `StaffController@index` | Daftar staf kependidikan | `VERIFIED` |
| `POST` | `/api/v1/staff` | `auth:sanctum`, `permission:master.teacher.manage` | `StaffController@store` | Tambah staf kependidikan baru | `VERIFIED` |
| `GET` | `/api/v1/guardians` | `auth:sanctum` | `GuardianController@index` | Daftar orang tua / wali | `VERIFIED` |
| `POST` | `/api/v1/students/{id}/guardians` | `auth:sanctum`, `permission:master.student.write` | `GuardianController@attachStudentGuardian` | Hubungkan wali murid dengan anak | `VERIFIED` |
| `GET` | `/api/v1/classes` | `auth:sanctum` | `ClassController@index` | Daftar kelas dan rombel | `VERIFIED` |
| `POST` | `/api/v1/classes/{id}/students` | `auth:sanctum`, `permission:master.class.manage` | `ClassController@addStudents` | Penempatan siswa ke kelas | `VERIFIED` |
| `POST` | `/api/v1/classes/{id}/students/transfer` | `auth:sanctum`, `permission:master.class.manage` | `ClassController@transferStudent` | Mutasi / pindah rombel siswa | `VERIFIED` |
| `GET` | `/api/v1/subjects` | `auth:sanctum` | `SubjectController@index` | Daftar mata pelajaran | `VERIFIED` |
| `POST` | `/api/v1/teaching-assignments` | `auth:sanctum`, `permission:master.subject.manage` | `TeachingAssignmentController@store` | Penetapan guru pengampu mata pelajaran | `VERIFIED` |
| `GET` | `/api/v1/rooms` | `auth:sanctum` | `RoomController@index` | Daftar ruang kelas & laboratorium | `VERIFIED` |
| `POST` | `/api/v1/promotions/promote` | `auth:sanctum`, `permission:master.promotion.manage` | `PromotionController@promote` | Eksekusi kenaikan kelas massal | `VERIFIED` |
| `POST` | `/api/v1/promotions/graduate` | `auth:sanctum`, `permission:master.promotion.manage` | `PromotionController@graduate` | Eksekusi kelulusan siswa tingkat akhir | `VERIFIED` |

### C. Modul CMS (Konten & Profil Sekolah)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/site-settings` | Public | `SiteSettingController@show` | Ambil identitas sekolah & konfigurasi situs | `VERIFIED` |
| `PUT` | `/api/v1/site-settings` | `auth:sanctum`, `permission:cms.site.manage` | `SiteSettingController@update` | Perbarui konfigurasi sekolah | `VERIFIED` |
| `GET` | `/api/v1/posts` | Public | `PostController@index` | Daftar artikel berita & publikasi | `VERIFIED` |
| `GET` | `/api/v1/posts/{slug}` | Public | `PostController@showBySlug` | Detail artikel berita publik | `VERIFIED` |
| `POST` | `/api/v1/posts` | `auth:sanctum`, `permission:cms.post.manage` | `PostController@store` | Buat artikel baru | `VERIFIED` |
| `GET` | `/api/v1/albums` | Public | `AlbumController@index` | Galeri foto & dokumentasi kegiatan | `VERIFIED` |
| `GET` | `/api/v1/bulletins` | Public | `BulletinController@index` | Daftar buletin mading digital | `VERIFIED` |
| `GET` | `/api/v1/events` | Public | `EventController@index` | Kalender agenda kegiatan sekolah | `VERIFIED` |
| `GET` | `/api/v1/downloads` | Public | `DownloadController@index` | Pusat unduhan berkas & dokumen resmi | `VERIFIED` |
| `GET` | `/api/v1/pages/{slug}` | Public | `PageController@showBySlug` | Halaman statis kustom sekolah | `VERIFIED` |
| `GET` | `/api/v1/navigation-menus` | Public | `NavigationMenuController@index` | Struktur hierarki navigasi menu | `VERIFIED` |

### D. Modul PPDB (Penerimaan Peserta Didik Baru)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/ppdb/waves` | Public | `PPDBWaveController@index` | Daftar gelombang pendaftaran | `VERIFIED` |
| `POST` | `/api/v1/ppdb/waves` | `auth:sanctum`, `permission:ppdb.wave.manage` | `PPDBWaveController@store` | Buka gelombang pendaftaran baru | `VERIFIED` |
| `GET` | `/api/v1/ppdb/tracks` | Public | `PPDBTrackController@index` | Daftar jalur seleksi per gelombang | `VERIFIED` |
| `POST` | `/api/v1/ppdb/auth/register` | Public | `PPDBAccountController@register` | Registrasi akun calon siswa baru | `VERIFIED` |
| `POST` | `/api/v1/ppdb/auth/verify-otp` | Public | `PPDBAccountController@verifyOtp` | Verifikasi kode OTP pendaftar | `VERIFIED` |
| `GET` | `/api/v1/ppdb/applications/{id}/draft` | `auth:sanctum` | `PPDBApplicationController@getDraft` | Ambil formulir draf bertahap pendaftar | `VERIFIED` |
| `PUT` | `/api/v1/ppdb/applications/{id}/step` | `auth:sanctum` | `PPDBApplicationController@saveStep` | Simpan progres langkah pendaftaran | `VERIFIED` |
| `POST` | `/api/v1/ppdb/applications/{id}/submit` | `auth:sanctum` | `PPDBApplicationController@submit` | Finalisasi dan submit pendaftaran | `VERIFIED` |
| `POST` | `/api/v1/ppdb/applications/{id}/documents` | `auth:sanctum` | `PPDBDocumentController@upload` | Unggah berkas persyaratan berkas | `VERIFIED` |
| `POST` | `/api/v1/ppdb/applications/{id}/verify` | `auth:sanctum`, `permission:ppdb.applicant.verify` | `PPDBVerificationController@verifyApplication` | Verifikasi berkas oleh panitia PPDB | `VERIFIED` |
| `POST` | `/api/v1/ppdb/selection/run` | `auth:sanctum`, `permission:ppdb.selection.run` | `PPDBSelectionController@runSelection` | Eksekusi seleksi otomatis & ranking kuota | `VERIFIED` |
| `GET` | `/api/v1/ppdb/results/check` | Public | `PPDBResultController@publicCheck` | Pencarian pengumuman kelulusan publik | `VERIFIED` |
| `POST` | `/api/v1/ppdb/enrollment/{id}/confirm` | `auth:sanctum` | `PPDBEnrollmentController@enroll` | Konfirmasi daftar ulang online | `VERIFIED` |
| `POST` | `/api/v1/ppdb/enrollment/{id}/convert-student`| `auth:sanctum`, `permission:ppdb.applicant.verify` | `PPDBEnrollmentController@convertToStudent` | Konversi otomatis calon siswa menjadi siswa aktif | `VERIFIED` |
| `GET` | `/api/v1/ppdb/applications/{id}/receipt` | `auth:sanctum` | `PPDBReceiptAndStatsController@receiptPdf` | Unduh kuitansi & tanda bukti PPDB | `VERIFIED` |
| `GET` | `/api/v1/ppdb/stats` | `auth:sanctum`, `permission:ppdb.applicant.read` | `PPDBReceiptAndStatsController@stats` | Statistik tren pendaftaran per jalur/wilayah | `VERIFIED` |

### E. Modul GUEST (Buku Tamu & Whistleblowing)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `POST` | `/api/v1/guests` | Public | `GuestController@store` | Pengisian formulir tamu kedinasan/umum | `VERIFIED` |
| `GET` | `/api/v1/guests` | `auth:sanctum`, `permission:guest.book.read` | `GuestController@index` | Daftar tamu resepsionis | `VERIFIED` |
| `POST` | `/api/v1/guests/{id}/checkout` | `auth:sanctum`, `permission:guest.book.manage` | `GuestController@checkout` | Checkout jam kepulangan tamu | `VERIFIED` |
| `POST` | `/api/v1/guests/{id}/signature` | Public | `GuestController@uploadSignature` | Unggah tanda tangan digital dan swafoto | `VERIFIED` |
| `POST` | `/api/v1/complaints` | Public | `ComplaintController@store` | Pengaduan masyarakat / whistleblowing | `VERIFIED` |
| `GET` | `/api/v1/complaints/track` | Public | `ComplaintController@track` | Lacak progres aduan dengan kode rahasia | `VERIFIED` |
| `PATCH`| `/api/v1/complaints/{id}/status` | `auth:sanctum`, `permission:complaint.manage` | `ComplaintController@updateStatus` | Tindak lanjut status aduan oleh pihak sekolah | `VERIFIED` |

### F. Modul ACD (Akademik, KBM & LMS)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/time-slots` | `auth:sanctum` | `TimeSlotController@index` | Daftar jam pelajaran sekolah | `VERIFIED` |
| `GET` | `/api/v1/schedules` | `auth:sanctum` | `ScheduleController@index` | Jadwal KBM per kelas / guru | `VERIFIED` |
| `POST` | `/api/v1/schedules/bulk` | `auth:sanctum`, `permission:academic.schedule.manage` | `ScheduleController@bulkStore` | Plotting jadwal massal dengan deteksi konflik | `VERIFIED` |
| `GET` | `/api/v1/journals` | `auth:sanctum` | `JournalController@index` | Riwayat jurnal mengajar guru | `VERIFIED` |
| `POST` | `/api/v1/journals` | `auth:sanctum`, `permission:academic.journal.write` | `JournalController@store` | Pengisian jurnal KBM harian | `VERIFIED` |
| `POST` | `/api/v1/attendances/batch` | `auth:sanctum`, `permission:academic.attendance.write` | `AttendanceController@recordBatch` | Presensi siswa harian di kelas | `VERIFIED` |
| `POST` | `/api/v1/attendances/gate-scan` | `auth:sanctum`, `permission:academic.attendance.write` | `AttendanceController@gateScan` | Pemindaian presensi gerbang otomatis | `VERIFIED` |
| `POST` | `/api/v1/staff-attendances/clock-in` | `auth:sanctum` | `StaffAttendanceController@clockIn` | Presensi masuk guru & staf (swafoto + GPS) | `VERIFIED` |
| `POST` | `/api/v1/staff-attendances/clock-out` | `auth:sanctum` | `StaffAttendanceController@clockOut` | Presensi pulang guru & staf | `VERIFIED` |
| `GET` | `/api/v1/teaching-materials` | `auth:sanctum` | `TeachingMaterialController@index` | Repositori modul & bahan ajar KBM | `VERIFIED` |
| `POST` | `/api/v1/assignments` | `auth:sanctum`, `permission:academic.journal.write` | `AssignmentController@store` | Pembuatan tugas LMS siswa | `VERIFIED` |
| `POST` | `/api/v1/assignments/{id}/submissions` | `auth:sanctum` | `AssignmentController@submit` | Pengumpulan jawaban tugas siswa | `VERIFIED` |

### G. Modul GRD (Penilaian & E-Rapor)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/grade-components` | `auth:sanctum` | `GradeComponentController@index` | Komponen penilaian mata pelajaran | `VERIFIED` |
| `POST` | `/api/v1/grade-components` | `auth:sanctum`, `permission:academic.grade.write` | `GradeComponentController@store` | Buat bobot komponen penilaian | `VERIFIED` |
| `POST` | `/api/v1/grades/batch` | `auth:sanctum`, `permission:academic.grade.write` | `GradeController@batchStore` | Input nilai siswa massal per kelas | `VERIFIED` |
| `GET` | `/api/v1/report-cards/generate-pdf` | `auth:sanctum`, `permission:academic.report.generate` | `ReportCardController@generatePdf` | Cetak E-Rapor / Progress Report resmi | `VERIFIED` |
| `GET` | `/api/v1/public/verify/{token}` | Public | `PublicVerificationController@verify` | Verifikasi keaslian dokumen rapor ber-QR | `VERIFIED` |
| `GET` | `/api/v1/character-notes` | `auth:sanctum` | `CharacterNoteController@index` | Catatan karakter walas per dimensi P5 | `VERIFIED` |
| `POST` | `/api/v1/character-notes/batch` | `auth:sanctum`, `permission:academic.grade.write` | `CharacterNoteController@storeBatch` | Pengisian catatan karakter siswa oleh walas | `VERIFIED` |

### H. Modul CBT (Computer Based Test)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/question-banks` | `auth:sanctum` | `QuestionBankController@index` | Daftar bank soal guru | `VERIFIED` |
| `POST` | `/api/v1/questions` | `auth:sanctum`, `permission:cbt.bank.write` | `QuestionController@store` | Buat soal (PG, Kompleks, Menjodohkan, Uraian) | `VERIFIED` |
| `POST` | `/api/v1/exams` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamController@store` | Buat jadwal ujian & durasi | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/questions` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamController@assignQuestions` | Distribusi paket soal ke ujian | `VERIFIED` |
| `POST` | `/api/v1/exam-rooms/{id}/regenerate-token` | `auth:sanctum` | `ExamRoomTokenController@regenerate` | Regenerasi token ruang ujian oleh pengawas | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/distribute-participants` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamParticipantCardController@distribute` | Plotting peserta ke ruang ujian | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/participants/{id}/card-pdf` | `auth:sanctum` | `ExamParticipantCardController@downloadPdf` | Cetak kartu peserta ujian ber-QR | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/start-session` | `auth:sanctum` | `ExamSessionController@start` | Mulai sesi ujian siswa via token ruang | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/session-questions` | `auth:sanctum` | `ExamSessionController@questions` | Ambil soal siswa (kunci jawaban disanitasi) | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/save-answer` | `auth:sanctum` | `ExamSessionController@saveAnswer` | Autosave jawaban siswa selama ujian | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/log-violation` | `auth:sanctum` | `ExamSessionController@logViolation` | Catat pelanggaran anti-cheat (pindah tab/blur) | `VERIFIED` |
| `POST` | `/api/v1/exam-sessions/{id}/manage` | `auth:sanctum`, `permission:cbt.proctor.action` | `ExamSessionController@manage` | Aksi pengawas (buka kunci sesi, tambah waktu) | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/finish-session` | `auth:sanctum` | `ExamSessionController@finish` | Submit dan kunci sesi ujian siswa | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/auto-grade` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamGradingController@autoGrade` | Koreksi otomatis soal objektif | `VERIFIED` |
| `POST` | `/api/v1/exams/{id}/grade-essay` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamGradingController@gradeEssay` | Input penilaian soal uraian oleh guru | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/item-analysis` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamGradingController@itemAnalysis` | Analisis butir soal (daya beda & kesukaran) | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/results` | `auth:sanctum` | `ExamGradingController@results` | Rekapitulasi nilai dan status kelulusan KKTP | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/monitor` | `auth:sanctum` | `ExamSessionController@monitor` | Monitoring live status peserta & heartbeat per ruang ujian | `VERIFIED` |
| `POST`| `/api/v1/exam-sessions/{id}/heartbeat`| `auth:sanctum` | `ExamSessionController@heartbeat` | Heartbeat deteksi anomali IP/perangkat & blur event | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/anomalies` | `auth:sanctum`, `permission:cbt.proctor.action` | `ExamSessionController@anomalies` | Daftar anomali sesi ujian untuk peninjauan pengawas | `VERIFIED` |
| `POST`| `/api/v1/exams/{id}/remedial` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamGradingController@createRemedial` | Pembuatan paket ujian remedial otomatis berbasis KKTP | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/report` | `auth:sanctum`, `permission:cbt.exam.manage` | `ExamReportController@report` | Rekapitulasi lengkap ujian, distribusi nilai & kehadiran | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/minutes` | `auth:sanctum`, `permission:cbt.proctor.action` | `ExamReportController@minutes` | Berita acara resmi pelaksanaan ujian (format cetak HTML/PDF) | `VERIFIED` |
| `GET` | `/api/v1/exams/{id}/sync/package` | Public (Token-authenticated) | `ExamSyncController@pullPackage` | Ekspor paket soal terenkripsi untuk node server lokal | `VERIFIED` |
| `POST`| `/api/v1/exams/{id}/sync/results` | Public (Token-authenticated) | `ExamSyncController@pushResults` | Sinkronisasi hasil ujian dari server lokal (idempoten) | `VERIFIED` |

### I. Modul STD (Kesiswaan, Poin & Ekskul)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/point-types` | `auth:sanctum` | `PointTypeController@index` | Master poin kedisiplinan dan prestasi | `VERIFIED` |
| `POST` | `/api/v1/student-points` | `auth:sanctum`, `permission:student.point.write` | `StudentPointController@store` | Catat pelanggaran / prestasi siswa | `VERIFIED` |
| `GET` | `/api/v1/students/{id}/points/summary` | `auth:sanctum` | `StudentPointController@studentSummary` | Ringkasan saldo poin kedisiplinan siswa | `VERIFIED` |
| `GET` | `/api/v1/extracurriculars` | `auth:sanctum` | `ExtracurricularController@index` | Daftar kegiatan ekstrakurikuler | `VERIFIED` |
| `POST` | `/api/v1/competitions` | `auth:sanctum` | `CompetitionController@store` | Pendaftaran delegasi lomba / kejuaraan | `VERIFIED` |
| `POST` | `/api/v1/achievements` | `auth:sanctum` | `AchievementController@store` | Pencatatan perolehan medali / prestasi | `VERIFIED` |
| `POST` | `/api/v1/trips` | `auth:sanctum` | `TripController@store` | Perencanaan agenda study trip / outing | `VERIFIED` |
| `POST` | `/api/v1/trips/{id}/consent` | `auth:sanctum` | `TripController@submitConsent` | Lembar persetujuan orang tua (non-financial) | `VERIFIED` |

### J. Modul BK (Bimbingan Konseling)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/counseling-cases` | `auth:sanctum`, `permission:counseling.case.read` | `CounselingCaseController@index` | Kasus konseling siswa terenkripsi | `VERIFIED` |
| `POST` | `/api/v1/counseling-cases` | `auth:sanctum`, `permission:counseling.case.write` | `CounselingCaseController@store` | Buka kasus konseling baru | `VERIFIED` |
| `POST` | `/api/v1/counseling-sessions` | `auth:sanctum`, `permission:counseling.case.write` | `CounselingSessionController@store` | Catat sesi konseling & butir tindak lanjut | `VERIFIED` |
| `GET` | `/api/v1/early-warnings` | `auth:sanctum` | `EarlyWarningController@index` | Deteksi dini siswa bermasalah (absensi/nilai) | `VERIFIED` |
| `POST` | `/api/v1/early-warnings/evaluate` | `auth:sanctum` | `EarlyWarningController@evaluate` | Jalankan evaluasi aturan peringatan dini | `VERIFIED` |
| `POST` | `/api/v1/counseling-requests` | `auth:sanctum` | `CounselingRequestController@store` | Permohonan janji temu konseling (siswa/ortu) | `VERIFIED` |
| `POST` | `/api/v1/counseling-referrals` | `auth:sanctum` | `CounselingReferralController@store` | Rujukan kasus ke profesional eksternal | `VERIFIED` |

### K. Modul SAR (Sarpras & Perpustakaan)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/tickets` | `auth:sanctum` | `TicketController@index` | Daftar tiket aduan kerusakan sarpras | `VERIFIED` |
| `POST` | `/api/v1/tickets` | `auth:sanctum` | `TicketController@store` | Laporkan kerusakan fasilitas (foto & ruang) | `VERIFIED` |
| `PATCH`| `/api/v1/tickets/{id}/status` | `auth:sanctum`, `permission:facility.asset.manage` | `TicketController@updateStatus` | Perbarui penanganan tiket & status SLA | `VERIFIED` |
| `GET` | `/api/v1/assets` | `auth:sanctum` | `AssetController@index` | Inventaris aset & fasilitas sekolah | `VERIFIED` |
| `POST` | `/api/v1/assets/{id}/audits` | `auth:sanctum`, `permission:facility.asset.manage` | `AssetAuditController@store` | Audit stok opname aset berkala | `VERIFIED` |
| `POST` | `/api/v1/bookings` | `auth:sanctum` | `BookingController@store` | Reservasi / booking ruangan atau fasilitas | `VERIFIED` |
| `POST` | `/api/v1/bookings/{id}/approve` | `auth:sanctum`, `permission:facility.booking.manage` | `BookingController@approve` | Persetujuan booking oleh penanggung jawab | `VERIFIED` |
| `POST` | `/api/v1/library/borrow` | `auth:sanctum`, `permission:library.book.manage` | `LibraryController@borrow` | Transaksi peminjaman buku perpustakaan | `VERIFIED` |
| `POST` | `/api/v1/library/return` | `auth:sanctum`, `permission:library.book.manage` | `LibraryController@returnBook` | Pengembalian buku & kalkulasi denda | `VERIFIED` |

### L. Modul HUM (Humas, MoU & Tracer Study)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/partnerships` | `auth:sanctum` | `PartnershipController@index` | Daftar kerja sama MoU kemitraan | `VERIFIED` |
| `POST` | `/api/v1/partnerships` | `auth:sanctum` | `PartnershipController@store` | Registrasi naskah MoU kemitraan baru | `VERIFIED` |
| `POST` | `/api/v1/exchange-programs` | `auth:sanctum` | `ExchangeProgramController@store` | Program pertukaran pelajar kemitraan | `VERIFIED` |
| `GET` | `/api/v1/alumni` | `auth:sanctum` | `AlumniController@index` | Direktori database alumni sekolah | `VERIFIED` |
| `POST` | `/api/v1/tracer-surveys/{id}/responses` | `auth:sanctum` | `TracerSurveyController@submitResponse` | Pengisian kuesioner tracer study alumni | `VERIFIED` |

### M. Modul RPT (Rekapitulasi & Dashboard)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/reports/{type}` | `auth:sanctum` | `ReportController@show` | Laporan agregat terpadu lintas modul | `VERIFIED` |
| `GET` | `/api/v1/reports/{type}/export` | `auth:sanctum` | `ReportController@export` | Ekspor rekapitulasi data format CSV | `VERIFIED` |
| `GET` | `/api/v1/dashboard/executive` | `auth:sanctum`, `permission:dashboard.view.executive` | `DashboardController@executive` | Dashboard eksekutif kepala sekolah (Real-time CBT + Cache 2m) | `VERIFIED` |
| `GET` | `/api/v1/dashboard/teacher` | `auth:sanctum` | `DashboardController@teacher` | Dashboard guru (jadwal KBM & jurnal tertunda) | `VERIFIED` |
| `GET` | `/api/v1/dashboard/student` | `auth:sanctum` | `DashboardController@student` | Dashboard siswa (presensi, poin & ujian) | `VERIFIED` |
| `GET` | `/api/v1/dashboard/guardian/{id}` | `auth:sanctum` | `DashboardController@guardian` | Dashboard wali murid (Anti-IDOR proteksi anak) | `VERIFIED` |

### N. Modul NTF/SYS (Notifikasi, Berkas & Sistem)
| Method | Endpoint URI | Middleware | Controller & Method | Deskripsi Bisnis | Status |
|:---:|:---|:---|:---|:---|:---:|
| `GET` | `/api/v1/notifications` | `auth:sanctum` | `NotificationController@index` | Kotak masuk notifikasi pengguna | `VERIFIED` |
| `POST` | `/api/v1/devices` | `auth:sanctum` | `NotificationController@registerDevice` | Pendaftaran token FCM perangkat mobile | `VERIFIED` |
| `POST` | `/api/v1/notifications/broadcast` | `auth:sanctum`, `permission:notification.broadcast` | `NotificationController@broadcast` | Siaran notifikasi push ke target peran | `VERIFIED` |
| `POST` | `/api/v1/notification-templates/send` | `auth:sanctum` | `MessagingController@send` | Pengiriman notifikasi WA/Email berparameter | `VERIFIED` |
| `POST` | `/api/v1/announcements` | `auth:sanctum` | `AnnouncementController@store` | Pengumuman terarah berjadwal | `VERIFIED` |
| `POST` | `/api/v1/announcements/{id}/read` | `auth:sanctum` | `AnnouncementController@markAsRead` | Pelacakan status pembaca pengumuman | `VERIFIED` |
| `GET` | `/api/v1/notification-preferences` | `auth:sanctum` | `NotificationPreferenceController@index` | Preferensi kanal notifikasi pengguna | `VERIFIED` |
| `POST` | `/api/v1/files/presign` | `auth:sanctum` | `FileController@presign` | Pembuatan presigned upload URL & token | `VERIFIED` |
| `POST` | `/api/v1/files/upload-signed/{token}` | Signed Route | `FileController@uploadSigned` | Target unggah berkas bertanda tangan | `VERIFIED` |
| `POST` | `/api/v1/files/confirm` | `auth:sanctum` | `FileController@confirm` | Konfirmasi dan pencatatan berkas ke DB | `VERIFIED` |
| `GET` | `/api/v1/files/{id}/url` | `auth:sanctum` | `FileController@url` | Signed download URL (Anti-IDOR, TTL 5 menit) | `VERIFIED` |
| `GET` | `/api/v1/files/{id}/download` | Signed Route | `FileController@downloadSigned` | Download stream berkas terverifikasi | `VERIFIED` |

---

## 2. Rangkuman Endpoint Hilang / Belum Tersedia

Berikut adalah rute yang disyaratkan dalam `04_PRD_BACKEND.md` tetapi belum tersedia di `routes/api.php`:
1. `GET /api/v1/exams/{id}/monitor` (`BE-CBT-06`): Monitoring live status peserta per ruang ujian real-time.
2. `POST /api/v1/exams/{id}/remedial` (`BE-CBT-09`): Penjadwalan ujian remedial otomatis untuk siswa di bawah KKTP.
3. `GET /api/v1/exams/{id}/report` (`BE-CBT-10`): Ekspor berita acara resmi pelaksanaan ujian format PDF.
4. `POST /api/v1/exams/sync` (`BE-CBT-11`): Sinkronisasi paket soal dan hasil ujian dengan server lokal/offline.
5. `POST /api/v1/exam-sessions/{id}/heartbeat` (`BE-CBT-12`): Deteksi anomali jeda pengerjaan, pergantian IP, dan fingerprint perangkat.
6. `GET /api/v1/exams/{id}/anomalies` (`BE-CBT-12`): Rekapitulasi deteksi anomali sesi ujian untuk audit pengawas.
