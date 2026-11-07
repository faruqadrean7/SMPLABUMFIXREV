# Spesifikasi Desain: Dashboard Siswa Multi-Program Belajar (SMP Lab UM)

**ID Fitur:** `BE-PORTAL-STUDENT-02` (Backend API & Skema) & `FE-PORTAL-STUDENT-02` (Frontend Modular Dashboard)  
**Status:** Draf Disetujui  
**Target:** Portal Siswa Web (`apps/web`) & API (`apps/api`)  
**Referensi:** Analisis 4 Program Resmi `smplab-um.sch.id` (Reguler, Tahfidz, ICP, Inklusi) & `AGENTS.md`.

---

## 1. Latar Belakang & Tujuan
SMP Laboratorium Universitas Negeri Malang memiliki 4 pilar program belajar unggulan dengan karakteristik peserta didik yang berbeda:
1. **Reguler:** Kurikulum nasional/merdeka dengan penekanan ketuntasan KKTP, Proyek P5, dan bina prestasi minat bakat.
2. **Tahfidz:** Program khusus pendalaman Al-Qur'an (target hafalan juz, tasmi'/setoran halaqah, mutaba'ah yaumiyah).
3. **ICP (International Class Program):** Standar Cambridge International (fokus pada *English, Mathematics, Science*), pembiasaan bilingual, dan agenda internasional.
4. **Inklusi:** Program layanan bagi Anak Berkebutuhan Khusus (ABK) yang didampingi Guru Pendamping Khusus (GPK), dengan Program Pembelajaran Individual (PPI) dan antarmuka ramah sensori.

Tujuan dari fitur ini adalah menyediakan **Dashboard Siswa Modular** yang secara otomatis menampilkan widget, navigasi samping, dan badge program yang disesuaikan dengan tipe program siswa (`student.program_type`), tanpa menghilangkan modul inti sekolah (jadwal harian, CBT, presensi, tugas).

---

## 2. Arsitektur Data & Skema Backend

### 2.1 Perubahan Database (Migrasi)
1. **Tabel `students`:**
   * Tambah kolom `program_type`: `VARCHAR(30) DEFAULT 'reguler' NOT NULL`
   * Check constraint: `program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi')`
2. **Tabel `classes`:**
   * Tambah kolom `program_type`: `VARCHAR(30) DEFAULT 'reguler' NOT NULL`
   * Check constraint: `program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi')`
3. **Tabel Baru `student_program_profiles`:**
   * `id`: UUID (Primary Key)
   * `student_id`: UUID (Foreign Key ke `students.id`, onDelete cascade, unique)
   * `program_type`: VARCHAR(30)
   * `meta_data`: JSONB / JSON nullable (menyimpan atribut spesifik yang fleksibel)
   * `timestamps`: `created_at`, `updated_at`

### 2.2 Struktur `meta_data` per Program:
* **Tahfidz (`program_type: 'tahfidz'`):**
  ```json
  {
    "target_juz": 3,
    "current_juz": 2,
    "current_surah": "Al-Mulk: 1-30",
    "last_tasmi_date": "2026-10-28",
    "last_tasmi_grade": "Mumtaz",
    "tasmi_evaluator": "Ustadz Abdullah, S.Pd.I",
    "mutabaah_today": {
      "sholat_fardhu": 5,
      "tilawah_pages": 4,
      "dhuha": true,
      "tahajud": false
    }
  }
  ```
* **ICP (`program_type: 'icp'`):**
  ```json
  {
    "cambridge_candidate_id": "ID-MAL-2026-042",
    "checkpoint_readiness": {
      "english": { "score": 4.8, "target": 6.0, "status": "Ready" },
      "math": { "score": 5.2, "target": 6.0, "status": "Exceeding" },
      "science": { "score": 4.5, "target": 6.0, "status": "Ready" }
    },
    "vocab_of_day": {
      "word": "Photosynthesis",
      "phonetic": "/ˌfəʊtəʊˈsɪnθəsɪs/",
      "definition": "The process by which green plants synthesize nutrients from carbon dioxide and water.",
      "subject": "Biology/Science"
    },
    "upcoming_events": [
      { "title": "Cambridge Mock Checkpoint", "date": "2026-11-15", "type": "exam" },
      { "title": "English Speech & Debate Camp", "date": "2026-11-28", "type": "workshop" }
    ]
  }
  ```
* **Inklusi (`program_type: 'inklusi'`):**
  ```json
  {
    "gpk": {
      "id": "gpk-uuid",
      "name": "Ibu Ratna Dewi, S.Pd (GPK)",
      "contact_phone": "081234567890",
      "room": "Ruang Sumber / Inklusi Lab UM"
    },
    "ppi_milestones": [
      { "id": "m1", "title": "Menyelesaikan modul membaca teks deskriptif", "is_completed": true },
      { "id": "m2", "title": "Latihan fokus & motorik halus terarah (15 menit)", "is_completed": false },
      { "id": "m3", "title": "Komunikasi aktif dalam sesi kelompok kecil", "is_completed": false }
    ],
    "daily_mood": {
      "mood": "happy",
      "timestamp": "2026-10-31T07:30:00Z",
      "note": "Semangat mengikuti pelajaran"
    }
  }
  ```
* **Reguler (`program_type: 'reguler'`):**
  ```json
  {
    "kktp_summary": {
      "total_subjects": 11,
      "tuntas_count": 10,
      "remedial_count": 1,
      "average_score": 86.4
    },
    "p5_project": {
      "title": "Gaya Hidup Berkelanjutan: Pengolahan Sampah Organik Sekolah",
      "theme": "Kearifan Lokal & Lingkungan",
      "phase": "Aksi Nyata & Pembuatan Produk",
      "group_name": "Kelompok Cendekia 3",
      "deadline": "2026-11-20"
    },
    "ekskul": [
      { "name": "Robotika & Coding", "day": "Kamis", "time": "15:00 - 17:00" },
      { "name": "Bina Prestasi OSN IPA", "day": "Sabtu", "time": "08:00 - 10:00" }
    ]
  }
  ```

---

## 3. Kontrak Endpoint API

### 3.1 Perubahan Response `GET /api/v1/me/student-dashboard`
Payload JSON diperkaya dengan atribut:
* `programType`: `'reguler' | 'tahfidz' | 'icp' | 'inklusi'`
* `programBadge`: Label judul resmi (misal: "Siswa Program Tahfidz Al-Qur'an")
* `programProfile`: Objek metadata program yang bersangkutan

### 3.2 Endpoint Interaksi Cepat Program:
* `POST /api/v1/me/program/mood` (Inklusi Daily Mood Check-in)
  * Payload: `{ "mood": "happy" | "calm" | "tired" | "anxious", "note": string | null }`
* `POST /api/v1/me/program/mutabaah` (Tahfidz Daily Mutaba'ah Check-in)
  * Payload: `{ "sholat_fardhu": int, "tilawah_pages": int, "dhuha": bool, "tahajud": bool }`

---

## 4. Rancangan Frontend (`apps/web`)

### 4.1 Header Profil Siswa
* Menampilkan badge program dengan warna khas:
  * Tahfidz: Hijau emerald (`bg-emerald-100 text-emerald-800 border-emerald-300`)
  * ICP: Biru royal (`bg-blue-100 text-blue-900 border-blue-300`)
  * Inklusi: Lavender/Teal lembut (`bg-purple-100 text-purple-900 border-purple-300`)
  * Reguler: Slate/Sekolah (`bg-slate-100 text-slate-800 border-slate-300`)

### 4.2 Navigasi Samping (`layout.tsx`) Dinamis
Link sidebar diperluas secara otomatis sesuai `program_type`:
* **Tahfidz:** Tambahan menu item `Hafalan & Tasmi'` (`/siswa/hafalan`)
* **ICP:** Tambahan menu item `Cambridge & Portofolio` (`/siswa/cambridge`)
* **Inklusi:** Tambahan menu item `Target Mandiri & PPI` (`/siswa/ppi`)
* **Reguler:** Tambahan menu item `Proyek P5 & Ekskul` (`/siswa/p5`)

### 4.3 Widget Khusus Program pada Beranda (`student-dashboard-view.tsx`)
* `<TahfidzProgramSection />`: Progress bar juz, target surat, status evaluasi ustadz, dan tombol mutaba'ah.
* `<IcpProgramSection />`: 3 Progress bar mata pelajaran Cambridge (*Math, Science, English*), Vocab of the Day card, dan event internasional.
* `<InklusiProgramSection />`: Desain sensory-friendly, checklist milestone PPI, kartu GPK pendamping, dan 4 tombol mood check-in.
* `<RegulerProgramSection />`: Radar KKTP, kartu progres proyek P5, dan daftar ekskul aktif.
