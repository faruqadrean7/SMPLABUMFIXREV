export interface StudentProfile {
  id: string;
  name: string;
  nis: string;
  nisn: string;
  class_id: string;
  class_name: string;
  academic_year: string;
  guardian_name: string;
  guardian_phone: string;
  gender: 'L' | 'P';
  avatar_url?: string;
  homeroom_teacher: string;
  homeroom_nip: string;
  homeroom_phone: string;
  homeroom_avatar?: string;
  counselor_name?: string;
  counselor_nip?: string;
  counselor_phone?: string;
  counselor_avatar?: string;
  gate_attendance_today: {
    status: 'hadir' | 'terlambat' | 'sakit' | 'izin' | 'alpa';
    tap_time: string;
    location: string;
  };
}

export const CURRENT_STUDENT: StudentProfile = {
  id: 'std-240901',
  name: 'Ahmad Fauzan Maulana',
  nis: '240901',
  nisn: '0089123451',
  class_id: 'cls-9a',
  class_name: 'Kelas IX-A',
  academic_year: '2026/2027 Ganjil',
  guardian_name: 'H. Ir. Bambang Maulana',
  guardian_phone: '081234567890',
  gender: 'L',
  avatar_url: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=200',
  homeroom_teacher: 'Budi Santoso, S.Kom., M.T.',
  homeroom_nip: '19870512 201201 1 003',
  homeroom_phone: '081234567812',
  homeroom_avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
  counselor_name: 'Dra. Hj. Nurul Hidayati, M.Pd.',
  counselor_nip: '19760815 200501 2 004',
  counselor_phone: '081298765432',
  counselor_avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=150',
  gate_attendance_today: {
    status: 'hadir',
    tap_time: '06:48 WIB',
    location: 'Gerbang Utama Siswa',
  },
};

export interface StudentScheduleItem {
  id: string;
  day: 'Senin' | 'Selasa' | 'Rabu' | 'Kamis' | 'Jumat';
  period: string;
  time_range: string;
  subject_name: string;
  subject_code: string;
  teacher_name: string;
  teacher_avatar_url?: string;
  room_name: string;
  is_active_today?: boolean;
}

export const MOCK_STUDENT_SCHEDULES: StudentScheduleItem[] = [
  // Senin (Hari Ini)
  { 
    id: 'sch-s-01', 
    day: 'Senin', 
    period: 'JP 1-2', 
    time_range: '07:30 - 09:00 WIB', 
    subject_name: 'Informatika & Pemrograman Web', 
    subject_code: 'INF-09', 
    teacher_name: 'Budi Santoso, S.Kom., M.T.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    room_name: 'Lab Komputer 1', 
    is_active_today: true 
  },
  { 
    id: 'sch-s-02', 
    day: 'Senin', 
    period: 'JP 3-4', 
    time_range: '09:15 - 10:45 WIB', 
    subject_name: 'Matematika Terapan', 
    subject_code: 'MAT-09', 
    teacher_name: 'Dra. Siti Rahayu, M.Pd.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
    room_name: 'Ruang Kelas IX-A', 
    is_active_today: true 
  },
  { 
    id: 'sch-s-03', 
    day: 'Senin', 
    period: 'JP 5-6', 
    time_range: '11:00 - 12:30 WIB', 
    subject_name: 'Bahasa Indonesia', 
    subject_code: 'BIN-09', 
    teacher_name: 'Agus Subekti, S.Pd.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=150',
    room_name: 'Ruang Kelas IX-A' 
  },
  
  // Selasa
  { 
    id: 'sch-s-04', 
    day: 'Selasa', 
    period: 'JP 1-2', 
    time_range: '07:30 - 09:00 WIB', 
    subject_name: 'Ilmu Pengetahuan Alam (IPA)', 
    subject_code: 'IPA-09', 
    teacher_name: 'Dr. Hendra Wijaya, M.Si.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=150',
    room_name: 'Lab Sains Terpadu' 
  },
  { 
    id: 'sch-s-05', 
    day: 'Selasa', 
    period: 'JP 3-4', 
    time_range: '09:15 - 10:45 WIB', 
    subject_name: 'Bahasa Inggris', 
    subject_code: 'ENG-09', 
    teacher_name: 'Nurul Hidayati, M.Ed.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=150',
    room_name: 'Lab Bahasa' 
  },
  { 
    id: 'sch-s-06', 
    day: 'Selasa', 
    period: 'JP 5-6', 
    time_range: '11:00 - 12:30 WIB', 
    subject_name: 'Pendidikan Pancasila & PKn', 
    subject_code: 'PKN-09', 
    teacher_name: 'Wahyu Hidayat, S.Pd.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150',
    room_name: 'Ruang Kelas IX-A' 
  },

  // Rabu
  { 
    id: 'sch-s-07', 
    day: 'Rabu', 
    period: 'JP 1-2', 
    time_range: '07:30 - 09:00 WIB', 
    subject_name: 'Praktikum Desain Grafis & Multimedia', 
    subject_code: 'TIK-09', 
    teacher_name: 'Budi Santoso, S.Kom., M.T.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    room_name: 'Lab Multimedia' 
  },
  { 
    id: 'sch-s-08', 
    day: 'Rabu', 
    period: 'JP 3-4', 
    time_range: '09:15 - 10:45 WIB', 
    subject_name: 'Pendidikan Jasmani (PJOK)', 
    subject_code: 'PJK-09', 
    teacher_name: 'Rudi Hartono, S.Pd.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150',
    room_name: 'Lapangan Olahraga Utama' 
  },
  { 
    id: 'sch-s-09', 
    day: 'Rabu', 
    period: 'JP 5-6', 
    time_range: '11:00 - 12:30 WIB', 
    subject_name: 'Ilmu Pengetahuan Sosial (IPS)', 
    subject_code: 'IPS-09', 
    teacher_name: 'Dewi Lestari, S.Pd.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
    room_name: 'Ruang Kelas IX-A' 
  },

  // Kamis
  { 
    id: 'sch-s-10', 
    day: 'Kamis', 
    period: 'JP 1-2', 
    time_range: '07:30 - 09:00 WIB', 
    subject_name: 'Pendidikan Agama & Budi Pekerti', 
    subject_code: 'PAB-09', 
    teacher_name: 'Ust. M. Ridwan, M.Ag.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    room_name: 'Ruang Kelas IX-A' 
  },
  { 
    id: 'sch-s-11', 
    day: 'Kamis', 
    period: 'JP 3-4', 
    time_range: '09:15 - 10:45 WIB', 
    subject_name: 'Projek Penguatan Profil Pancasila (P5)', 
    subject_code: 'P5-09', 
    teacher_name: 'Tim Fasilitator P5', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=150',
    room_name: 'Aula Serbaguna' 
  },
  { 
    id: 'sch-s-11b', 
    day: 'Kamis', 
    period: 'JP 5-6', 
    time_range: '11:00 - 12:30 WIB', 
    subject_name: 'Bahasa Jawa & Muatan Lokal', 
    subject_code: 'MLK-09', 
    teacher_name: 'Dra. Siti Rahayu, M.Pd.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
    room_name: 'Ruang Kelas IX-A' 
  },

  // Jumat
  { 
    id: 'sch-s-12', 
    day: 'Jumat', 
    period: 'JP 1-2', 
    time_range: '07:30 - 08:50 WIB', 
    subject_name: 'Seni Budaya & Prakarya', 
    subject_code: 'SBP-09', 
    teacher_name: 'Yuni Astuti, S.Sn.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
    room_name: 'Studio Seni' 
  },
  { 
    id: 'sch-s-13', 
    day: 'Jumat', 
    period: 'JP 3', 
    time_range: '09:05 - 10:15 WIB', 
    subject_name: 'Literasi Digital & Bimbingan Walas', 
    subject_code: 'WLS-09', 
    teacher_name: 'Budi Santoso, S.Kom., M.T.', 
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    room_name: 'Ruang Kelas IX-A' 
  },
];

export interface StudentCBTExamItem {
  id: string;
  title: string;
  subject_name: string;
  subject_code: string;
  teacher_name: string;
  teacher_avatar_url?: string;
  date_str: string;
  time_range: string;
  duration_minutes: number;
  total_questions: number;
  kktp: number;
  room_name: string;
  token_required: string;
  status: 'aktif' | 'terjadwal' | 'selesai';
  score?: number | null;
  is_passed?: boolean;
}

export const MOCK_STUDENT_EXAMS: StudentCBTExamItem[] = [
  {
    id: 'exam-cbt-01',
    title: 'Sumatif Tengah Semester Gasal: Informatika & Logika Web',
    subject_name: 'Informatika',
    subject_code: 'INF-09',
    teacher_name: 'Budi Santoso, S.Kom., M.T.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    date_str: 'Hari Ini, 14 Oktober 2026',
    time_range: '08:00 - 09:30 WIB',
    duration_minutes: 90,
    total_questions: 30,
    kktp: 75,
    room_name: 'Lab Komputer 1 (Workstation 14)',
    token_required: 'LAB-9X7B',
    status: 'aktif',
    score: null,
  },
  {
    id: 'exam-cbt-02',
    title: 'Penilaian Sumatif Bab 2: Sistem Persamaan Linear Dua Variabel',
    subject_name: 'Matematika Terapan',
    subject_code: 'MAT-09',
    teacher_name: 'Dra. Siti Rahayu, M.Pd.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
    date_str: 'Senin, 19 Oktober 2026',
    time_range: '09:15 - 10:45 WIB',
    duration_minutes: 90,
    total_questions: 25,
    kktp: 75,
    room_name: 'Lab Komputer 2',
    token_required: 'MAT-4R8K',
    status: 'terjadwal',
    score: null,
  },
  {
    id: 'exam-cbt-03',
    title: 'Kuis Diagnostik Bab 1: Struktur Semantic HTML5 & CSS Box Model',
    subject_name: 'Informatika',
    subject_code: 'INF-09',
    teacher_name: 'Budi Santoso, S.Kom., M.T.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    date_str: '28 September 2026',
    time_range: '08:00 - 08:45 WIB',
    duration_minutes: 45,
    total_questions: 20,
    kktp: 75,
    room_name: 'Lab Komputer 1',
    token_required: 'INF-1A2B',
    status: 'selesai',
    score: 92,
    is_passed: true,
  },
  {
    id: 'exam-cbt-04',
    title: 'Ulangan Harian 1: Ekosistem & Keanekaragaman Hayati',
    subject_name: 'Ilmu Pengetahuan Alam (IPA)',
    subject_code: 'IPA-09',
    teacher_name: 'Dr. Hendra Wijaya, M.Si.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=150',
    date_str: '15 September 2026',
    time_range: '07:30 - 08:30 WIB',
    duration_minutes: 60,
    total_questions: 30,
    kktp: 75,
    room_name: 'Lab Komputer 1',
    token_required: 'IPA-99PQ',
    status: 'selesai',
    score: 86,
    is_passed: true,
  },
];

export interface CBTQuestionDetail {
  id: string;
  number: number;
  type: 'pg' | 'pg_kompleks' | 'benar_salah' | 'menjodohkan' | 'isian' | 'uraian';
  category: 'LOTS' | 'MOTS' | 'HOTS';
  points: number;
  prompt: string;
  formula?: string;
  has_audio?: boolean;
  audio_title?: string;
  image_url?: string;
  options?: { key: string; label: string }[];
  statements?: { text: string; correct_val?: boolean }[];
  matching_pairs?: { premise: string; target: string; options: string[] }[];
}

export const CBT_ACTIVE_EXAM_QUESTIONS: CBTQuestionDetail[] = [
  {
    id: 'q-01',
    number: 1,
    type: 'pg',
    category: 'LOTS',
    points: 3,
    prompt: 'Manakah tag HTML5 semantik yang paling tepat untuk mendefinisikan navigasi menu utama suatu situs web sekolah?',
    options: [
      { key: 'A', label: '<div class="navigation">' },
      { key: 'B', label: '<nav>' },
      { key: 'C', label: '<menu-bar>' },
      { key: 'D', label: '<header-nav>' },
    ],
  },
  {
    id: 'q-02',
    number: 2,
    type: 'pg_kompleks',
    category: 'MOTS',
    points: 4,
    prompt: 'Pilihlah DUA properti CSS Flexbox yang digunakan untuk mengatur posisi item anak secara vertikal dan horizontal di dalam flex container:',
    options: [
      { key: 'A', label: 'justify-content (mengatur distribusi di sepanjang main axis)' },
      { key: 'B', label: 'font-weight (mengatur ketebalan font teks)' },
      { key: 'C', label: 'align-items (mengatur posisi di sepanjang cross axis)' },
      { key: 'D', label: 'border-radius (mengatur lengkungan sudut elemen)' },
    ],
  },
  {
    id: 'q-03',
    number: 3,
    type: 'pg',
    category: 'HOTS',
    points: 4,
    prompt: 'Perhatikan formula kompleksitas algoritma berikut. Jika algoritma pencarian biner (Binary Search) diimplementasikan pada larik terurut sepanjang n elemen, tentukan kompleksitas waktu kasus terburuknya:',
    formula: 'T(n) = O(\\log_2 n) \\quad \\text{vs} \\quad T(n) = O(n^2)',
    options: [
      { key: 'A', label: 'O(1) - Waktu konstan' },
      { key: 'B', label: 'O(log n) - Waktu logaritmik' },
      { key: 'C', label: 'O(n) - Waktu linear' },
      { key: 'D', label: 'O(n log n) - Waktu linearitmik' },
    ],
  },
  {
    id: 'q-04',
    number: 4,
    type: 'benar_salah',
    category: 'MOTS',
    points: 4,
    prompt: 'Tentukan kebenaran dari pernyataan-pernyataan mengenai arsitektur jaringan komputer lokal (LAN) berikut ini:',
    statements: [
      { text: 'Protokol DHCP berfungsi memberikan konfigurasi alamat IP dinamis secara otomatis kepada workstation klien.' },
      { text: 'Switch jaringan beroperasi pada Layer 1 (Physical) sedangkan Hub beroperasi pada Layer 2 (Data Link).' },
      { text: 'Kabel UTP kategori Cat 6 mendukung transmisi data berkecepatan hingga 10 Gbps pada jarak terbatas.' },
    ],
  },
  {
    id: 'q-05',
    number: 5,
    type: 'menjodohkan',
    category: 'MOTS',
    points: 5,
    prompt: 'Pasangkan komponen arsitektur web modern berikut dengan fungsi utamanya yang paling tepat:',
    matching_pairs: [
      { premise: 'HTML5 Semantic', target: 'Menyusun struktur hierarki dan arti konten dokumen web', options: ['Menyusun struktur hierarki dan arti konten dokumen web', 'Mengatur estetika tata letak dan animasi visual', 'Menjalankan logika interaktivitas dan manipulasi DOM', 'Menyimpan data persisten secara relasional'] },
      { premise: 'CSS3 Tailwind', target: 'Mengatur estetika tata letak dan animasi visual', options: ['Menyusun struktur hierarki dan arti konten dokumen web', 'Mengatur estetika tata letak dan animasi visual', 'Menjalankan logika interaktivitas dan manipulasi DOM', 'Menyimpan data persisten secara relasional'] },
      { premise: 'JavaScript ES6', target: 'Menjalankan logika interaktivitas dan manipulasi DOM', options: ['Menyusun struktur hierarki dan arti konten dokumen web', 'Mengatur estetika tata letak dan animasi visual', 'Menjalankan logika interaktivitas dan manipulasi DOM', 'Menyimpan data persisten secara relasional'] },
    ],
  },
  {
    id: 'q-06',
    number: 6,
    type: 'isian',
    category: 'MOTS',
    points: 3,
    prompt: 'Sebutkan nama metode CSS Grid yang digunakan untuk membagi ruang container menjadi unit proporsional relatif (contoh: 1___):',
  },
  {
    id: 'q-07',
    number: 7,
    type: 'uraian',
    category: 'HOTS',
    points: 8,
    prompt: 'Jelaskan konsep "Mobile-First Design" dalam pengembangan antarmuka web modern! Mengapa pendekatan ini lebih disarankan daripada "Desktop-First" saat membangun aplikasi yang diakses oleh siswa dan guru?',
  },
];

export interface StudentTaskItem {
  id: string;
  title: string;
  subject_name: string;
  teacher_name: string;
  teacher_avatar_url?: string;
  due_date_str: string;
  days_left: number;
  max_score: number;
  status: 'aktif' | 'dikumpulkan' | 'dinilai';
  submission?: {
    file_name?: string;
    submitted_at: string;
    score?: number;
    kktp_passed?: boolean;
    feedback?: string;
    drive_link?: string;
  };
}

export const MOCK_STUDENT_TASKS: StudentTaskItem[] = [
  {
    id: 'task-01',
    title: 'Tugas Mandiri 03: Desain Web Portofolio Siswa Responsif',
    subject_name: 'Informatika & Pemrograman Web',
    teacher_name: 'Budi Santoso, S.Kom., M.T.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    due_date_str: '24 Oktober 2026, 23:59 WIB',
    days_left: 10,
    max_score: 100,
    status: 'dinilai',
    submission: {
      file_name: 'Laporan_Tugas3_Ahmad_Fauzan_IXA.pdf',
      submitted_at: '21 Oktober 2026, 14:32 WIB',
      score: 95,
      kktp_passed: true,
      feedback: 'Struktur kode HTML sangat rapi dan semantik. Penggunaan CSS Grid pada galeri karya sangat presisi. Pertahankan prestasimu, Fauzan!',
      drive_link: 'https://github.com/fauzan-student/portofolio-ix-a',
    },
  },
  {
    id: 'task-02',
    title: 'Lembar Kerja 04: Perancangan Skema ERD Perpustakaan Sekolah',
    subject_name: 'Informatika',
    teacher_name: 'Budi Santoso, S.Kom., M.T.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
    due_date_str: '30 Oktober 2026, 23:59 WIB',
    days_left: 16,
    max_score: 100,
    status: 'aktif',
  },
  {
    id: 'task-03',
    title: 'Latihan Soal Matematika: SPLDV dengan Metode Eliminasi & Substitusi',
    subject_name: 'Matematika Terapan',
    teacher_name: 'Dra. Siti Rahayu, M.Pd.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
    due_date_str: '16 Oktober 2026, 17:00 WIB',
    days_left: 2,
    max_score: 100,
    status: 'aktif',
  },
  {
    id: 'task-04',
    title: 'Essay Refleksi: Etika dan Dampak Positif Perkembangan Teknologi Informasi',
    subject_name: 'Bahasa Indonesia',
    teacher_name: 'Agus Subekti, S.Pd.',
    teacher_avatar_url: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=150',
    due_date_str: '12 Oktober 2026, 20:00 WIB',
    days_left: 0,
    max_score: 100,
    status: 'dikumpulkan',
    submission: {
      file_name: 'Esai_Refleksi_Teknologi_Ahmad_Fauzan.pdf',
      submitted_at: '11 Oktober 2026, 19:15 WIB',
    },
  },
];

export interface StudentSubjectGrade {
  subject_code: string;
  subject_name: string;
  teacher_name: string;
  kktp: number;
  f1: number;
  f2: number;
  f3: number;
  slm: number;
  sts: number;
  final_score: number;
  status: 'Tuntas' | 'Perlu Bimbingan';
}

export const MOCK_STUDENT_GRADES: StudentSubjectGrade[] = [
  { subject_code: 'INF-09', subject_name: 'Informatika & Pemrograman Web', teacher_name: 'Budi Santoso, S.Kom., M.T.', kktp: 75, f1: 94, f2: 95, f3: 92, slm: 90, sts: 96, final_score: 93.5, status: 'Tuntas' },
  { subject_code: 'MAT-09', subject_name: 'Matematika Terapan', teacher_name: 'Dra. Siti Rahayu, M.Pd.', kktp: 75, f1: 88, f2: 85, f3: 84, slm: 86, sts: 88, final_score: 86.4, status: 'Tuntas' },
  { subject_code: 'IPA-09', subject_name: 'Ilmu Pengetahuan Alam', teacher_name: 'Dr. Hendra Wijaya, M.Si.', kktp: 75, f1: 85, f2: 88, f3: 86, slm: 87, sts: 90, final_score: 87.5, status: 'Tuntas' },
  { subject_code: 'ENG-09', subject_name: 'Bahasa Inggris', teacher_name: 'Nurul Hidayati, M.Ed.', kktp: 75, f1: 90, f2: 92, f3: 88, slm: 89, sts: 91, final_score: 90.1, status: 'Tuntas' },
  { subject_code: 'BIN-09', subject_name: 'Bahasa Indonesia', teacher_name: 'Agus Subekti, S.Pd.', kktp: 75, f1: 86, f2: 88, f3: 85, slm: 87, sts: 89, final_score: 87.2, status: 'Tuntas' },
  { subject_code: 'PKN-09', subject_name: 'Pendidikan Pancasila & PKn', teacher_name: 'Wahyu Hidayat, S.Pd.', kktp: 75, f1: 92, f2: 90, f3: 91, slm: 88, sts: 92, final_score: 90.7, status: 'Tuntas' },
  { subject_code: 'PAB-09', subject_name: 'Pendidikan Agama Islam', teacher_name: 'Ust. M. Ridwan, M.Ag.', kktp: 75, f1: 95, f2: 94, f3: 96, slm: 92, sts: 95, final_score: 94.3, status: 'Tuntas' },
];

export interface StudentPointLogItem {
  id: string;
  date_str: string;
  kind: 'prestasi' | 'pelanggaran';
  type_name: string;
  points: number;
  category: string;
  recorder_name: string;
  note: string;
}

export const MOCK_STUDENT_POINTS_LOG: StudentPointLogItem[] = [
  { id: 'pt-01', date_str: '08 Oktober 2026', kind: 'prestasi', type_name: 'Juara 1 Lomba OSN Informatika Tingkat Kota', points: 30, category: 'Prestasi Akademik', recorder_name: 'Budi Santoso, S.Kom., M.T.', note: 'Mewakili sekolah ke tingkat Provinsi Jawa Timur' },
  { id: 'pt-02', date_str: '22 September 2026', kind: 'prestasi', type_name: 'Juara 2 Lomba Desain Web Nasional SMA/SMP', points: 25, category: 'Prestasi Talenta & TIK', recorder_name: 'Budi Santoso, S.Kom., M.T.', note: 'Aplikasi edukasi literasi digital' },
  { id: 'pt-03', date_str: '15 September 2026', kind: 'pelanggaran', type_name: 'Keterlambatan Hadir Gerbang Sekolah (>10 menit)', points: -10, category: 'Kedisiplinan Waktu', recorder_name: 'Petugas Piket Gerbang', note: 'Terjebak macet hujan deras di perempatan Dieng' },
];

export interface StudentCounselingItem {
  id: string;
  topic: string;
  category: 'Akademik' | 'Sosial & Teman' | 'Pengembangan Diri' | 'Keluarga';
  request_date: string;
  preferred_schedule: string;
  counselor_name: string;
  counselor_avatar_url?: string;
  status: 'menunggu' | 'terjadwal' | 'selesai';
  location?: string;
  notes?: string;
}

export const MOCK_STUDENT_COUNSELING: StudentCounselingItem[] = [
  {
    id: 'csl-01',
    topic: 'Konsultasi Penjurusan Lanjutan SMA / Peminatan Kurikulum Merdeka',
    category: 'Pengembangan Diri',
    request_date: '10 Oktober 2026',
    preferred_schedule: 'Jumat, 16 Oktober 2026 - 13:30 WIB',
    counselor_name: 'Ibu Ratna Dewi, S.Pd., Kons.',
    counselor_avatar_url: 'https://images.unsplash.com/photo-1580894732444-8ecded7900cd?auto=format&fit=crop&q=80&w=150',
    status: 'terjadwal',
    location: 'Ruang Bimbingan & Konseling (Lantai 2 Gedung A)',
    notes: 'Membahas hasil asesmen minat bakat psikologi dan rekomendasi peminatan sains teknologi.',
  },
  {
    id: 'csl-02',
    topic: 'Manajemen Waktu Pelatnas Lomba Olimpiade & Tugas Harian Kelas',
    category: 'Akademik',
    request_date: '12 September 2026',
    preferred_schedule: 'Kamis, 17 September 2026',
    counselor_name: 'Ibu Ratna Dewi, S.Pd., Kons.',
    counselor_avatar_url: 'https://images.unsplash.com/photo-1580894732444-8ecded7900cd?auto=format&fit=crop&q=80&w=150',
    status: 'selesai',
    location: 'Ruang BK',
    notes: 'Telah diberikan rekomendasi dispensasi praktikum terjadwal yang dikoordinasikan dengan wali kelas.',
  },
];

export interface StudentAnnouncementItem {
  id: string;
  title: string;
  date: string;
  category: string;
  content: string;
  author: string;
  author_avatar_url?: string;
}

export const MOCK_STUDENT_ANNOUNCEMENTS: StudentAnnouncementItem[] = [
  {
    id: 'ann-s-01',
    title: 'Pelaksanaan Sumatif Tengah Semester (STS) Gasal di Lab Komputer',
    date: '14 Oktober 2026',
    category: 'Asesmen CBT',
    content: 'Seluruh peserta wajib hadir 10 menit sebelum jam 08:00 WIB dan menitipkan tas/ponsel di loker lab.',
    author: 'Budi Santoso, S.Kom., M.T.',
    author_avatar_url: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=150',
  },
  {
    id: 'ann-s-02',
    title: 'Pendaftaran Ekstrakurikuler Pilihan & Gelar Karya P5 "Rekayasa Teknologi"',
    date: '12 Oktober 2026',
    category: 'Kesiswaan',
    content: 'Pengumpulan proposal prototipe karya teknologi kelompok dibuka hingga 25 Oktober 2026.',
    author: 'Dra. Siti Rahayu, M.Pd.',
    author_avatar_url: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150',
  },
];
