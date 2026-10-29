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
  // ─── MODEL 1: PILIHAN GANDA (PG) SEDERHANA (5 BUTIR) ───
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
    type: 'pg',
    category: 'LOTS',
    points: 3,
    prompt: 'Bentuk paling sederhana dari operasi aljabar 4(2x - 3) + 5x adalah ...',
    options: [
      { key: 'A', label: '13x - 12' },
      { key: 'B', label: '13x + 12' },
      { key: 'C', label: '8x - 12' },
      { key: 'D', label: '8x + 12' },
    ],
  },
  {
    id: 'q-03',
    number: 3,
    type: 'pg',
    category: 'HOTS',
    prompt: 'Perhatikan formula kompleksitas algoritma berikut. Jika algoritma pencarian biner (Binary Search) diimplementasikan pada larik terurut sepanjang n elemen, tentukan kompleksitas waktu kasus terburuknya:',
    formula: 'T(n) = O(\\log_2 n) \\quad \\text{vs} \\quad T(n) = O(n^2)',
    points: 4,
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
    type: 'pg',
    category: 'MOTS',
    points: 3,
    prompt: 'Jika nilai a = 4 dan b = -2, maka nilai dari 3a - 2b adalah ...',
    options: [
      { key: 'A', label: '8' },
      { key: 'B', label: '12' },
      { key: 'C', label: '16' },
      { key: 'D', label: '20' },
    ],
  },
  {
    id: 'q-05',
    number: 5,
    type: 'pg',
    category: 'LOTS',
    points: 3,
    prompt: 'Satuan Sistem Internasional (SI) yang baku untuk menyatakan besaran suhu mutlak adalah ...',
    options: [
      { key: 'A', label: 'Kelvin (K)' },
      { key: 'B', label: 'Derajat Celcius (°C)' },
      { key: 'C', label: 'Derajat Fahrenheit (°F)' },
      { key: 'D', label: 'Derajat Reamur (°R)' },
    ],
  },

  // ─── MODEL 2: PILIHAN GANDA KOMPLEKS (5 BUTIR) ───
  {
    id: 'q-06',
    number: 6,
    type: 'pg_kompleks',
    category: 'MOTS',
    points: 4,
    prompt: 'Pilihlah DUA properti CSS Flexbox yang digunakan untuk mengatur perataan item anak secara vertikal dan horizontal di dalam flex container:',
    options: [
      { key: 'A', label: 'justify-content (mengatur distribusi di sepanjang main axis)' },
      { key: 'B', label: 'font-weight (mengatur ketebalan font teks)' },
      { key: 'C', label: 'align-items (mengatur perataan di sepanjang cross axis)' },
      { key: 'D', label: 'border-radius (mengatur kelengkungan sudut elemen)' },
    ],
  },
  {
    id: 'q-07',
    number: 7,
    type: 'pg_kompleks',
    category: 'MOTS',
    points: 4,
    prompt: 'Diberikan himpunan S = {bilangan asli dari 1 hingga 20}. Pilihlah semua pernyataan yang bernilai benar berikut ini:',
    options: [
      { key: 'A', label: 'Angka 2 adalah satu-satunya bilangan prima yang bernilai genap di dalam himpunan S' },
      { key: 'B', label: 'Terdapat tepat 8 bilangan prima di antara 1 dan 20' },
      { key: 'C', label: 'Semua bilangan ganjil di dalam S selalu merupakan bilangan prima' },
      { key: 'D', label: 'Angka 9 dan 15 bukan merupakan bilangan prima (merupakan bilangan komposit)' },
    ],
  },
  {
    id: 'q-08',
    number: 8,
    type: 'pg_kompleks',
    category: 'MOTS',
    points: 4,
    prompt: 'Manakah di antara karakteristik berikut yang merupakan ciri-ciri algoritma komputasi yang baik? (Pilih semua yang benar):',
    options: [
      { key: 'A', label: 'Definiteness (setiap instruksi terdefinisi jelas, tepat, dan tidak ambigu)' },
      { key: 'B', label: 'Finiteness (proses harus berhenti setelah sejumlah langkah terhingga dieksekusi)' },
      { key: 'C', label: 'Effectiveness (langkah-langkah instruksi cukup sederhana untuk dapat diselesaikan)' },
      { key: 'D', label: 'Infinite Execution (eksekusi program harus berjalan terus tanpa akhir selamanya)' },
    ],
  },
  {
    id: 'q-09',
    number: 9,
    type: 'pg_kompleks',
    category: 'LOTS',
    points: 4,
    prompt: 'Pilihlah besaran-besaran fisika berikut yang termasuk ke dalam 7 Besaran Pokok Sistem Internasional (SI):',
    options: [
      { key: 'A', label: 'Panjang (meter)' },
      { key: 'B', label: 'Massa (kilogram)' },
      { key: 'C', label: 'Waktu (sekon)' },
      { key: 'D', label: 'Kecepatan (meter/sekon)' },
    ],
  },
  {
    id: 'q-10',
    number: 10,
    type: 'pg_kompleks',
    category: 'MOTS',
    points: 4,
    prompt: 'Pilihlah tindakan-tindakan yang mencerminkan praktik keamanan siber (cyber hygiene) yang baik untuk menjaga akun portal sekolah:',
    options: [
      { key: 'A', label: 'Mengaktifkan Autentikasi Dua Faktor (2FA/OTP)' },
      { key: 'B', label: 'Menggunakan kata sandi kuat gabungan huruf kapital, angka, dan simbol unik' },
      { key: 'C', label: 'Menuliskan password di secarik kertas yang ditempel di layar komputer umum lab' },
      { key: 'D', label: 'Selalu logout akun setelah selesai menggunakan workstation laboratorium' },
    ],
  },

  // ─── MODEL 3: PILIHAN GANDA KOMPLEKS KATEGORI / BENAR-SALAH (5 BUTIR) ───
  {
    id: 'q-11',
    number: 11,
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
    id: 'q-12',
    number: 12,
    type: 'benar_salah',
    category: 'MOTS',
    points: 4,
    prompt: 'Analisis nilai kebenaran dari pernyataan konsep aljabar dan aritmatika sosial berikut ini:',
    statements: [
      { text: 'Sebuah jaket seharga Rp200.000 mendapat diskon 25%, maka harga yang harus dibayar adalah Rp150.000.' },
      { text: 'Bentuk aljabar (x + 3)(x - 3) jika dijabarkan menghasilkan x^2 - 9.' },
      { text: 'Nilai penyelesaian dari persamaan 3x - 5 = 10 adalah x = 4.' },
    ],
  },
  {
    id: 'q-13',
    number: 13,
    type: 'benar_salah',
    category: 'LOTS',
    points: 4,
    prompt: 'Tentukan kebenaran pernyataan mengenai klasifikasi materi dan perubahan zat berikut:',
    statements: [
      { text: 'Tumbuhan hijau bertindak sebagai produsen primer pada ekosistem karena mampu berfotosintesis.' },
      { text: 'Peristiwa pembakaran kertas menjadi abu dan gas karbon dioksida merupakan contoh perubahan fisika.' },
      { text: 'Massa jenis suatu zat murni didefinisikan sebagai perbandingan massa terhadap volume zat tersebut.' },
    ],
  },
  {
    id: 'q-14',
    number: 14,
    type: 'benar_salah',
    category: 'MOTS',
    points: 4,
    prompt: 'Tentukan kebenaran pernyataan terkait pilar Berpikir Komputasional (Computational Thinking):',
    statements: [
      { text: 'Dekomposisi adalah teknik memecah permasalahan kompleks menjadi sub-masalah yang lebih kecil.' },
      { text: 'Abstraksi melibatkan pencarian detail terkecil yang tidak relevan untuk mempersulit pemecahan masalah.' },
      { text: 'Pengenalan pola membantu mengenali kesamaan di antara masalah-masalah berbeda untuk menerapkan solusi serupa.' },
    ],
  },
  {
    id: 'q-15',
    number: 15,
    type: 'benar_salah',
    category: 'LOTS',
    points: 4,
    prompt: 'Tentukan kebenaran pernyataan mengenai sistem peredaran darah manusia berikut:',
    statements: [
      { text: 'Jantung manusia memiliki empat ruangan: dua serambi (atrium) dan dua bilik (ventrikel).' },
      { text: 'Pembuluh darah vena selalu mengalirkan darah yang kaya akan oksigen langsung dari paru-paru ke seluruh tubuh.' },
      { text: 'Sel darah merah (eritrosit) mengandung hemoglobin yang berfungsi mengikat oksigen.' },
    ],
  },

  // ─── MODEL 4: MENJODOHKAN (MATCHING PAIR - BUKAN DROPDOWN) (5 BUTIR) ───
  {
    id: 'q-16',
    number: 16,
    type: 'menjodohkan',
    category: 'MOTS',
    points: 5,
    prompt: 'Hubungkan komponen arsitektur web modern di Kolom Kiri dengan peran dan fungsi utamanya di Kolom Kanan:',
    matching_pairs: [
      { premise: 'HTML5 Semantic', target: 'Menyusun struktur hierarki dan arti konten dokumen web', options: ['Menyusun struktur hierarki dan arti konten dokumen web', 'Mengatur estetika tata letak dan animasi visual', 'Menjalankan logika interaktivitas dan manipulasi DOM', 'Menyimpan data persisten secara relasional'] },
      { premise: 'CSS3 Tailwind', target: 'Mengatur estetika tata letak dan animasi visual', options: ['Menyusun struktur hierarki dan arti konten dokumen web', 'Mengatur estetika tata letak dan animasi visual', 'Menjalankan logika interaktivitas dan manipulasi DOM', 'Menyimpan data persisten secara relasional'] },
      { premise: 'JavaScript ES6', target: 'Menjalankan logika interaktivitas dan manipulasi DOM', options: ['Menyusun struktur hierarki dan arti konten dokumen web', 'Mengatur estetika tata letak dan animasi visual', 'Menjalankan logika interaktivitas dan manipulasi DOM', 'Menyimpan data persisten secara relasional'] },
    ],
  },
  {
    id: 'q-17',
    number: 17,
    type: 'menjodohkan',
    category: 'MOTS',
    points: 5,
    prompt: 'Pasangkan simbol diagram alir (flowchart) di Kolom Kiri dengan fungsi standarnya di Kolom Kanan:',
    matching_pairs: [
      { premise: 'Oval / Terminator', target: 'Titik awal (Start) atau akhir (End) dari suatu alur program', options: ['Titik awal (Start) atau akhir (End) dari suatu alur program', 'Operasi pengolahan atau kalkulasi data internal', 'Kondisi percabangan logika keputusan (Decision)', 'Operasi masukan (Input) atau keluaran (Output)'] },
      { premise: 'Persegi Panjang', target: 'Operasi pengolahan atau kalkulasi data internal', options: ['Titik awal (Start) atau akhir (End) dari suatu alur program', 'Operasi pengolahan atau kalkulasi data internal', 'Kondisi percabangan logika keputusan (Decision)', 'Operasi masukan (Input) atau keluaran (Output)'] },
      { premise: 'Belah Ketupat (Diamond)', target: 'Kondisi percabangan logika keputusan (Decision)', options: ['Titik awal (Start) atau akhir (End) dari suatu alur program', 'Operasi pengolahan atau kalkulasi data internal', 'Kondisi percabangan logika keputusan (Decision)', 'Operasi masukan (Input) atau keluaran (Output)'] },
      { premise: 'Jajar Genjang', target: 'Operasi masukan (Input) atau keluaran (Output)', options: ['Titik awal (Start) atau akhir (End) dari suatu alur program', 'Operasi pengolahan atau kalkulasi data internal', 'Kondisi percabangan logika keputusan (Decision)', 'Operasi masukan (Input) atau keluaran (Output)'] },
    ],
  },
  {
    id: 'q-18',
    number: 18,
    type: 'menjodohkan',
    category: 'MOTS',
    points: 5,
    prompt: 'Pasangkan alat ukur ilmiah di Kolom Kiri dengan besaran fisik yang diukurnya secara tepat di Kolom Kanan:',
    matching_pairs: [
      { premise: 'Jangka Sorong', target: 'Ketebalan dan diameter dalam suatu tabung silinder', options: ['Ketebalan dan diameter dalam suatu tabung silinder', 'Massa zat padat dengan tingkat presisi miligram', 'Interval waktu tempuh suatu kejadian gerak', 'Derajat panas dingin temperatur suatu fluida'] },
      { premise: 'Neraca Ohaus', target: 'Massa zat padat dengan tingkat presisi miligram', options: ['Ketebalan dan diameter dalam suatu tabung silinder', 'Massa zat padat dengan tingkat presisi miligram', 'Interval waktu tempuh suatu kejadian gerak', 'Derajat panas dingin temperatur suatu fluida'] },
      { premise: 'Stopwatch Digital', target: 'Interval waktu tempuh suatu kejadian gerak', options: ['Ketebalan dan diameter dalam suatu tabung silinder', 'Massa zat padat dengan tingkat presisi miligram', 'Interval waktu tempuh suatu kejadian gerak', 'Derajat panas dingin temperatur suatu fluida'] },
      { premise: 'Termometer Lab', target: 'Derajat panas dingin temperatur suatu fluida', options: ['Ketebalan dan diameter dalam suatu tabung silinder', 'Massa zat padat dengan tingkat presisi miligram', 'Interval waktu tempuh suatu kejadian gerak', 'Derajat panas dingin temperatur suatu fluida'] },
    ],
  },
  {
    id: 'q-19',
    number: 19,
    type: 'menjodohkan',
    category: 'LOTS',
    points: 5,
    prompt: 'Pasangkan komponen bentuk aljabar 5x - 7 di Kolom Kiri dengan istilah matematiknya di Kolom Kanan:',
    matching_pairs: [
      { premise: 'Huruf x', target: 'Variabel (peubah lambang bilangan)', options: ['Variabel (peubah lambang bilangan)', 'Koefisien (faktor pengali dari variabel)', 'Konstanta (bilangan tetap)', 'Derajat pangkat variabel'] },
      { premise: 'Angka 5', target: 'Koefisien (faktor pengali dari variabel)', options: ['Variabel (peubah lambang bilangan)', 'Koefisien (faktor pengali dari variabel)', 'Konstanta (bilangan tetap)', 'Derajat pangkat variabel'] },
      { premise: 'Angka -7', target: 'Konstanta (bilangan tetap)', options: ['Variabel (peubah lambang bilangan)', 'Koefisien (faktor pengali dari variabel)', 'Konstanta (bilangan tetap)', 'Derajat pangkat variabel'] },
    ],
  },
  {
    id: 'q-20',
    number: 20,
    type: 'menjodohkan',
    category: 'MOTS',
    points: 5,
    prompt: 'Pasangkan persamaan linear di Kolom Kiri dengan nilai penyelesaian x yang tepat di Kolom Kanan:',
    matching_pairs: [
      { premise: 'Persamaan: 2x = 10', target: 'Nilai x = 5', options: ['Nilai x = 5', 'Nilai x = 3', 'Nilai x = 2', 'Nilai x = 7'] },
      { premise: 'Persamaan: 3x + 1 = 10', target: 'Nilai x = 3', options: ['Nilai x = 5', 'Nilai x = 3', 'Nilai x = 2', 'Nilai x = 7'] },
      { premise: 'Persamaan: 4x - 8 = 0', target: 'Nilai x = 2', options: ['Nilai x = 5', 'Nilai x = 3', 'Nilai x = 2', 'Nilai x = 7'] },
    ],
  },

  // ─── MODEL 5: ISIAN SINGKAT (5 BUTIR) ───
  {
    id: 'q-21',
    number: 21,
    type: 'isian',
    category: 'LOTS',
    points: 3,
    prompt: 'Tentukan nilai penyelesaian x dari persamaan linear satu variabel: 5x - 14 = 2x + 16 (Ketikkan angka saja):',
  },
  {
    id: 'q-22',
    number: 22,
    type: 'isian',
    category: 'MOTS',
    points: 3,
    prompt: 'Sebutkan nama metode CSS Grid yang digunakan untuk membagi ruang container menjadi unit proporsional relatif (contoh: 1___):',
  },
  {
    id: 'q-23',
    number: 23,
    type: 'isian',
    category: 'LOTS',
    points: 3,
    prompt: 'Zat pigmen hijau daun yang berfungsi menangkap energi foton matahari saat reaksi fotosintesis disebut ...',
  },
  {
    id: 'q-24',
    number: 24,
    type: 'isian',
    category: 'MOTS',
    points: 3,
    prompt: 'Nomor port standar terenkripsi yang digunakan oleh protokol web aman HTTPS adalah port bernomor ...',
  },
  {
    id: 'q-25',
    number: 25,
    type: 'isian',
    category: 'LOTS',
    points: 3,
    prompt: 'Hasil perhitungan perpangkatan dari 2^4 - 3^2 adalah ... (Ketikkan angka hasil akhir):',
  },

  // ─── MODEL 6: URAIAN / ESAI HOTS (5 BUTIR) ───
  {
    id: 'q-26',
    number: 26,
    type: 'uraian',
    category: 'HOTS',
    points: 8,
    prompt: 'Jelaskan konsep "Mobile-First Design" dalam pengembangan antarmuka web modern! Mengapa pendekatan ini lebih disarankan daripada "Desktop-First" saat membangun aplikasi yang diakses oleh siswa dan guru?',
  },
  {
    id: 'q-27',
    number: 27,
    type: 'uraian',
    category: 'HOTS',
    points: 8,
    prompt: 'Jelaskan mengapa Autentikasi Dua Faktor (2FA) jauh lebih aman dibandingkan hanya mengandalkan kata sandi tunggal dalam melindungi akun portal sekolah! Analisislah skenario jika terjadi serangan phishing.',
  },
  {
    id: 'q-28',
    number: 28,
    type: 'uraian',
    category: 'HOTS',
    points: 8,
    prompt: 'Jelaskan dampak ekologis yang akan terjadi pada suatu ekosistem persawahan apabila populasi ular sawah diburu hingga punah oleh manusia! Hubungkan dengan populasi hama tikus dan ketahanan pangan.',
  },
  {
    id: 'q-29',
    number: 29,
    type: 'uraian',
    category: 'HOTS',
    points: 8,
    prompt: 'Koperasi sekolah meminjam modal Rp10.000.000 dengan sistem bunga tunggal 12% per tahun selama 10 bulan. Uraikan langkah-langkah perhitungan untuk menentukan total pengembalian dan angsuran tiap bulannya!',
  },
  {
    id: 'q-30',
    number: 30,
    type: 'uraian',
    category: 'HOTS',
    points: 8,
    prompt: 'Jelaskan konsep strategi pencadangan data "3-2-1 Backup Strategy" dan bagaimana implementasinya untuk melindungi arsip nilai rapor dan data peserta didik dari ancaman ransomware!',
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
