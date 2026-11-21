# Spesifikasi Kebutuhan API Portal Self-Service (`/me/*`)
**Sistem Manajemen Sekolah Terpadu SISTLABUM (SMP Laboratorium UM)**

Dokumen ini memetakan seluruh kebutuhan endpoint RESTful API *self-service* yang aman untuk konsumsi Portal Personal (`/portal/siswa`, `/portal/ortu`, `/portal/guru`, `/portal/staf`). Sesuai standar keamanan `AGENTS.md` dan `09_SECURITY_PRIVACY.md`, seluruh endpoint portal wajib dibatasi pada level backend (*server-side enforcement*) berdasarkan identitas token pengguna (`auth()->user()`). **Frontend portal dilarang menggunakan endpoint administratif internal.**

---

## 1. Prinsip Keamanan & Desain API

1. **Deny-by-Default & Self-Service**:
   - Endpoint berawalan `/api/v1/me/*` secara otomatis mengambil identitas pengguna dari token JWT/Bearer yang valid.
   - Tidak menerima parameter ID pengguna (`user_id`, `student_id`, `teacher_id`) di query atau body untuk data milik diri sendiri guna mencegah eskalasi IDOR (*Insecure Direct Object Reference*).
2. **Validasi Relasi Wali-Anak di Server**:
   - Untuk endpoint `/api/v1/me/children/{child_id}/*`, server wajib memverifikasi bahwa `child_id` terdaftar dalam tabel relasi perwalian aktif `guardians` / `student_guardians` di bawah akun wali yang sedang terautentikasi.
   - Jika `child_id` tidak valid atau milik orang tua lain, server wajib mengembalikan status `403 Forbidden` dengan pesan: *"Anda tidak memiliki hak akses perwalian sah terhadap siswa ini."*
3. **Data Sanitization & Minimal Privilege**:
   - Siswa & Ortu dilarang menerima kunci jawaban, bobot internal yang belum dipublikasikan, atau catatan konseling BK rahasia melalui endpoint ini.
4. **Idempotency & Audit**:
   - Setiap mutasi (misal pengajuan permohonan konseling mandiri atau izin) mendukung header `Idempotency-Key` dan dicatat ke `audit_logs`.

---

## 2. Endpoint Portal Orang Tua (`/me/children/...`)

### 2.1. `GET /api/v1/me/children`
- **Tujuan**: Mengambil daftar seluruh anak yang berada di bawah perwalian wali yang login.
- **Guard**: `auth:sanctum`, role `guardian` / `super_admin`.
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": "123e4567-e89b-12d3-a456-426614174001",
      "name": "Ahmad Fauzi",
      "nisn": "0081234567",
      "nis": "24001",
      "class_id": "cls-7a",
      "class_name": "7-A Unggulan",
      "academic_year": "2024/2025",
      "gender": "male",
      "avatar_url": null,
      "status": "active"
    }
  ]
}
```

### 2.2. `GET /api/v1/me/children/{child_id}`
- **Tujuan**: Detail profil dan ringkasan akademik siswa anak kandung/perwalian.
- **Otorisasi**: Server memvalidasi `WHERE guardian_id = auth()->id() AND student_id = :child_id`.
- **Response**: `200 OK` (atau `403 Forbidden` jika bukan anaknya).

### 2.3. `GET /api/v1/me/children/{child_id}/attendance`
- **Tujuan**: Rekapitulasi dan riwayat presensi harian anak.
- **Query Params**: `semester_id` (opsional), `month` (opsional, format: `YYYY-MM`).
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "summary": {
      "present_days": 78,
      "sick_days": 2,
      "leave_days": 1,
      "unexcused_days": 0,
      "total_effective_days": 81,
      "attendance_percentage": 96.3
    },
    "history": [
      {
        "date": "2026-10-14",
        "status": "present",
        "check_in_time": "06:45:00",
        "notes": null
      }
    ]
  }
}
```

### 2.4. `GET /api/v1/me/children/{child_id}/grades`
- **Tujuan**: Rekap buku nilai mata pelajaran anak (nilai harian, STS, SAS).
- **Query Params**: `semester_id`, `academic_year_id`.
- **Response**: `200 OK` dengan daftar mapel, nilai akhir, predikat huruf, dan status ketuntasan (KKTP/KKM).

### 2.5. `GET /api/v1/me/children/{child_id}/report-cards`
- **Tujuan**: Mengambil e-rapor semester resmi yang sudah diverifikasi dan dikunci oleh wali kelas & kepala sekolah.
- **Response**: `200 OK` (nilai, capaian kompetensi, catatan wali kelas, unduh PDF rapor bertanda tangan elektronik).

### 2.6. `GET /api/v1/me/children/{child_id}/character-notes`
- **Tujuan**: Rekap catatan adab dan penilaian karakter profil pelajar Pancasila anak.
- **Response**: `200 OK` dengan aspek karakter (Beriman, Gotong Royong, Mandiri, Bernalar Kritis, Kreatif) dan catatan wali kelas.

### 2.7. `GET /api/v1/me/children/{child_id}/progress-reports`
- **Tujuan**: Catatan kemajuan akademik dan perkembangan perilaku anak per periode.

---

## 3. Endpoint Portal Siswa (`/me/...`)

### 3.1. `GET /api/v1/me/student-dashboard`
- **Tujuan**: Ringkasan beranda siswa dalam satu panggilan efisien.
- **Guard**: `auth:sanctum`, role `student`.
- **Response**: `200 OK` (profil siswa, jadwal pelajaran hari ini, tugas yang mendekati tenggat, ujian CBT yang akan datang, persentase kehadiran).

### 3.2. `GET /api/v1/me/schedule`
- **Tujuan**: Jadwal pelajaran mingguan siswa aktif berdasarkan kelas tempat ia terdaftar.
- **Response**: `200 OK` (daftar sesi Senin - Jumat, jam mulai, jam selesai, mata pelajaran, nama guru, ruang kelas).

### 3.3. `GET /api/v1/me/grades`
- **Tujuan**: Rekap capaian nilai pembelajaran dan e-rapor siswa.

### 3.4. `GET /api/v1/me/exams`
- **Tujuan**: Daftar sesi ujian Computer-Based Testing (CBT) yang ditugaskan kepada siswa.
- **Response**: `200 OK` (ID sesi, judul ujian, mata pelajaran, durasi waktu, status: `scheduled`, `ongoing`, `completed`).

### 3.5. `POST /api/v1/me/counseling-requests`
- **Tujuan**: Pengajuan jadwal konseling mandiri siswa ke guru BK secara aman & privat.

---

## 4. Endpoint Notifikasi Personal (`/me/notifications`)

### 4.1. `GET /api/v1/me/notifications`
- **Tujuan**: Inbox notifikasi personal tunggal untuk semua role portal.
- **Query Params**: `page`, `per_page`, `unread_only` (boolean).
- **Response**: `200 OK` dengan status `is_read`, kategori, judul, ringkasan, dan tautan aksi (`action_url`).

### 4.2. `POST /api/v1/me/notifications/read-all`
- **Tujuan**: Menandai seluruh notifikasi milik pengguna sebagai terbaca.

### 4.3. `PATCH /api/v1/me/notifications/{id}/read`
- **Tujuan**: Menandai satu notifikasi tertentu sebagai terbaca.

---

## 5. Endpoint Portal Guru (`/me/teacher/...` & `/salary-slips`)

### 5.1. `GET /api/v1/me/teacher/dashboard`
- **Tujuan**: Mengambil ringkasan hari ini untuk guru (jadwal hari ini, kelas belum diabsen, tugas yang belum dinilai, pengumuman).
- **Otorisasi**: Otomatis dibatasi ke guru aktif `auth()->user()->teacher->id`.

### 5.2. `GET /api/v1/me/teacher/classes`
- **Tujuan**: Mengambil hanya daftar kelas dan mata pelajaran yang resmi ditugaskan ke guru yang login.
- **Keamanan**: Mencegah IDOR sehingga guru tidak dapat melihat/mengubah data kelas lain.

### 5.3. `GET /api/v1/me/teacher/schedules`
- **Tujuan**: Jadwal mengajar mingguan guru aktif.

### 5.4. `GET /api/v1/me/teacher/attendance/sessions` & `POST /api/v1/me/teacher/attendance/sessions`
- **Tujuan**: Input presensi kehadiran siswa per sesi jam mengajar.

### 5.5. `GET /api/v1/me/teacher/journals` & `POST /api/v1/me/teacher/journals`
- **Tujuan**: Catatan jurnal mengajar harian guru.

### 5.6. `GET /api/v1/me/teacher/grades` & `POST /api/v1/me/teacher/grades/bulk`
- **Tujuan**: Buku nilai siswa per mapel/kelas yang diampu dan input nilai massal.

### 5.7. `GET /api/v1/me/teacher/materials` & `POST /api/v1/me/teacher/materials`
- **Tujuan**: Unggah modul dan materi pembelajaran.

### 5.8. `GET /api/v1/me/teacher/assignments` & `POST /api/v1/me/teacher/assignments`
- **Tujuan**: Buat tugas KBM, cek pengumpulan, dan berikan nilai.

### 5.9. `GET /api/v1/salary-slips` & `GET /api/v1/salary-slips/{id}/download`
- **Tujuan**: Riwayat slip gaji mandiri guru dan unduh PDF resmi.
- **Status Backend**: ✅ Tersedia (`TeacherController::mySalarySlips` & `downloadMySalarySlip`).

### 5.10. `GET /api/v1/me/teacher/homeroom/*` (Kondisional Wali Kelas)
- **Tujuan**: Rekapitulasi kelas binaan, presensi kelas, catatan karakter profil pelajar Pancasila, laporan perkembangan, dan e-rapor kelas.
- **Otorisasi**: Wajib diverifikasi di server `teacher->is_homeroom == true` dan kelas sesuai penugasan `homeroom_class_id`.

---

## 6. Status Implementasi Saat Ini

| Endpoint | Status Backend | Adapter Frontend |
|---|---|---|
| `GET /api/v1/me` | ✅ Tersedia | Terhubung langsung via `authProvider` |
| `GET /api/v1/salary-slips` | ✅ Terimplementasi (GAP-08) | Terhubung langsung via API Client |
| `GET /api/v1/salary-slips/{id}/download` | ✅ Terimplementasi (GAP-08) | Terhubung langsung via API Client |
| `GET /api/v1/me/children` | ✅ Terimplementasi (GAP-08) | Terhubung via `portalApi` (`MeChildController@index`) |
| `GET /api/v1/me/children/{id}/*` | ✅ Terimplementasi (GAP-08) | Terhubung via `portalApi` (`GuardianChildPolicy` Anti-IDOR 403) |
| `GET /api/v1/me/student-dashboard` | ✅ Terimplementasi (GAP-08) | Terhubung via `portalApi` (`MeStudentController@dashboard`) |
| `GET /api/v1/me/schedule` | ✅ Terimplementasi (GAP-08) | Terhubung via `portalApi` (`MeStudentController@schedule`) |
| `GET /api/v1/me/grades` | ✅ Terimplementasi (GAP-08) | Terhubung via `portalApi` (`MeStudentController@grades`) |
| `GET /api/v1/me/teacher/*` | ✅ Terimplementasi (GAP-08) | Terhubung via `guruApi` (`MeTeacherController` Anti-IDOR 403) |
| `GET /api/v1/me/staff/*` | ✅ Terimplementasi (GAP-08) | Terhubung via `stafApi` / `sharedPortalApi` (`MeStaffController`) |
| `GET /api/v1/me/notifications` | ✅ Tersedia (`NotificationController`) | Terhubung langsung dengan API Notifikasi |
| `GET /api/v1/me/notification-preferences` | ✅ Terimplementasi (GAP-11) | Terhubung via `notificationsApi.getPreferences` |
| `PUT /api/v1/me/notification-preferences` | ✅ Terimplementasi (GAP-11) | Terhubung via `notificationsApi.updatePreferences` |
| `POST /api/v1/cbt/sessions/{id}/start` | ✅ Terimplementasi (GAP-02) | Terhubung via `cbtApi.startExam` (server-timing & offset) |
| `POST /api/v1/cbt/sessions/{id}/submit` | ✅ Terimplementasi (GAP-02) | Terhubung via `cbtApi.submitExam` (idempoten & server-close) |
| `POST /api/v1/cbt/sessions/{id}/violations` | ✅ Terimplementasi (GAP-07) | Terhubung via `cbtApi.reportViolation` (inkremen atomik server) |
| `POST /api/v1/audit-logs` | ✅ Terimplementasi (GAP-06) | Terhubung via `uiTelemetryLogger` (menolak aksi bisnis 422) |

---

## 7. Spesifikasi Kebutuhan Endpoint Mandiri Staf / Tenaga Kependidikan (`/me/staff/*`)

Staf Tata Usaha, petugas Sarana-Prasarana, dan Pustakawan mengakses modul kerja mandiri lewat `/portal/staf`:

### 7.1. `GET /api/v1/me/staff/dashboard`
- **Tujuan**: Ringkasan beranda staf (presensi hari ini, jumlah izin pending, tiket sarpras aktif, dan pengumuman internal).
- **Otorisasi**: Pengguna login dengan role `admin_tu`, `facility_staff`, `librarian`, atau `super_admin`.

### 7.2. `GET /api/v1/me/staff/self-attendance` & `POST /api/v1/me/staff/self-attendance`
- **Tujuan**: Presensi kerja mandiri staf (clock in jam datang, clock out jam pulang, dan akumulasi jam kerja bulanan vs target 160 jam).
- **Metode**: `POST` menerima `{ action: 'check_in' | 'check_out' }`.

### 7.3. `GET /api/v1/me/staff/leave-requests` & `POST /api/v1/me/staff/leave-requests`
- **Tujuan**: Pengajuan dan pelacakan izin sakit, cuti tahunan, dan tugas kedinasan luar kantor bagi staf.
- **Payload POST**: `{ leaveType, startDate, endDate, reason, attachmentUrl? }`.

### 7.4. `GET /api/v1/salary-slips` & `GET /api/v1/salary-slips/{id}/download`
- **Tujuan**: Riwayat slip gaji staf dan unduh slip gaji resmi dalam format PDF.
- **Status Backend**: ✅ Tersedia (`TeacherController::mySalarySlips` / payroll controller).

### 7.5. `GET /api/v1/me/staff/tickets` & `POST /api/v1/tickets`
- **Tujuan**: Pelaporan aduan kerusakan fasilitas sekolah (AC, proyektor, kelistrikan, sanitasi) dan pemantauan status tindak lanjut oleh tim sarpras.
- **Payload POST**: `{ title, category, location, description, priority }`.
- **Status Backend**: ✅ Controller `/tickets` tersedia.

