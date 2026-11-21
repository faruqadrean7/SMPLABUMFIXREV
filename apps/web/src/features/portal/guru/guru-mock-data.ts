import {
  TeacherClass,
  TeacherSubject,
  TeacherScheduleItem,
  TeacherAttendanceStudent,
  TeacherJournalItem,
  TeacherGradeComponent,
  TeacherStudentGrade,
  TeacherMaterialItem,
  TeacherAssignmentItem,
  TeacherAssignmentSubmission,
  TeacherExamItem,
  TeacherLeaveRequest,
  TeacherSalarySlipItem,
  TeacherSelfAttendance,
  TeacherHomeroomData,
} from './types';

export const MOCK_TEACHER_CLASSES: TeacherClass[] = [
  { id: 'cls-7a', name: '7-A Unggulan', gradeLevel: 7, studentCount: 32 },
  { id: 'cls-7b', name: '7-B Reguler', gradeLevel: 7, studentCount: 30 },
  { id: 'cls-8a', name: '8-A Billingual', gradeLevel: 8, studentCount: 28 },
];

export const MOCK_TEACHER_SUBJECTS: TeacherSubject[] = [
  { id: 'sub-mat', name: 'Matematika', code: 'MAT-SMP' },
];

export const MOCK_TEACHER_SCHEDULES: TeacherScheduleItem[] = [
  {
    id: 'sch-t-1',
    dayOfWeek: 1,
    dayName: 'Senin',
    startTime: '08:00',
    endTime: '09:20',
    classId: 'cls-7a',
    className: '7-A Unggulan',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    roomName: 'Ruang 7-A',
    isAttendanceRecorded: true,
  },
  {
    id: 'sch-t-2',
    dayOfWeek: 1,
    dayName: 'Senin',
    startTime: '09:40',
    endTime: '11:00',
    classId: 'cls-7b',
    className: '7-B Reguler',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    roomName: 'Ruang 7-B',
    isAttendanceRecorded: false,
  },
  {
    id: 'sch-t-3',
    dayOfWeek: 3,
    dayName: 'Rabu',
    startTime: '07:00',
    endTime: '08:20',
    classId: 'cls-7a',
    className: '7-A Unggulan',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    roomName: 'Ruang 7-A',
    isAttendanceRecorded: false,
  },
  {
    id: 'sch-t-4',
    dayOfWeek: 3,
    dayName: 'Rabu',
    startTime: '08:40',
    endTime: '10:00',
    classId: 'cls-8a',
    className: '8-A Billingual',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    roomName: 'Lab Matematika',
    isAttendanceRecorded: false,
  },
  {
    id: 'sch-t-5',
    dayOfWeek: 5,
    dayName: 'Jumat',
    startTime: '08:00',
    endTime: '09:20',
    classId: 'cls-7b',
    className: '7-B Reguler',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    roomName: 'Ruang 7-B',
    isAttendanceRecorded: false,
  },
];

export const MOCK_CLASS_STUDENTS: Record<string, TeacherAttendanceStudent[]> = {
  'cls-7a': [
    { studentId: 'std-1', studentName: 'Ahmad Fauzi', nisn: '0081234567', nis: '24001', status: 'present' },
    { studentId: 'std-2', studentName: 'Aisyah Putri Rahma', nisn: '0081234568', nis: '24002', status: 'present' },
    { studentId: 'std-3', studentName: 'Bagas Aditya Pratama', nisn: '0081234569', nis: '24003', status: 'sick', notes: 'Surat dokter terlampir' },
    { studentId: 'std-4', studentName: 'Citra Kirana Dewi', nisn: '0081234570', nis: '24004', status: 'present' },
    { studentId: 'std-5', studentName: 'Dimas Wahyu Nugroho', nisn: '0081234571', nis: '24005', status: 'permission', notes: 'Izin kejuaraan silat' },
  ],
  'cls-7b': [
    { studentId: 'std-6', studentName: 'Eka Prasetya', nisn: '0081234572', nis: '24006', status: 'present' },
    { studentId: 'std-7', studentName: 'Fajar Ramadhan', nisn: '0081234573', nis: '24007', status: 'present' },
    { studentId: 'std-8', studentName: 'Gita Safitri', nisn: '0081234574', nis: '24008', status: 'present' },
  ],
  'cls-8a': [
    { studentId: 'std-9', studentName: 'Hafiz Al-Ghifari', nisn: '0071234575', nis: '23001', status: 'present' },
    { studentId: 'std-10', studentName: 'Indah Permata Sari', nisn: '0071234576', nis: '23002', status: 'present' },
  ],
};

export const MOCK_TEACHER_JOURNALS: TeacherJournalItem[] = [
  {
    id: 'jrn-1',
    scheduleId: 'sch-t-1',
    classId: 'cls-7a',
    className: '7-A Unggulan',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    date: '2026-10-12',
    timeSlot: '08:00 - 09:20',
    lessonTopic: 'Operasi Bentuk Aljabar (Penjumlahan dan Pengurangan)',
    competenceIndicator: 'Siswa mampu menyederhanakan suku-suku sejenis pada ekspresi aljabar linier.',
    studentAbsentees: ['Bagas Aditya Pratama (Sakit)'],
    learningObstacles: 'Beberapa siswa masih keliru saat mengoperasikan variabel dengan koefisien negatif.',
    isLocked: true,
    createdAt: '2026-10-12T09:30:00Z',
  },
  {
    id: 'jrn-2',
    scheduleId: 'sch-t-3',
    classId: 'cls-7a',
    className: '7-A Unggulan',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    date: '2026-10-14',
    timeSlot: '07:00 - 08:20',
    lessonTopic: 'Perkalian dan Pembagian Pecahan Aljabar',
    competenceIndicator: 'Siswa mampu mengalikan dua bentuk aljabar menggunakan sifat distributif.',
    studentAbsentees: [],
    learningObstacles: 'KBM berjalan lancar dengan metode problem-based learning.',
    isLocked: false,
    createdAt: '2026-10-14T08:30:00Z',
  },
];

export const MOCK_GRADE_COMPONENTS: TeacherGradeComponent[] = [
  { id: 'comp-1', name: 'Ulangan Harian 1 (Aljabar)', code: 'UH1', weight: 20, classId: 'cls-7a', subjectId: 'sub-mat' },
  { id: 'comp-2', name: 'Tugas Proyek Pemodelan', code: 'TUGAS', weight: 30, classId: 'cls-7a', subjectId: 'sub-mat' },
  { id: 'comp-3', name: 'Penilaian Tengah Semester', code: 'PTS', weight: 50, classId: 'cls-7a', subjectId: 'sub-mat' },
];

export const MOCK_STUDENT_GRADES: Record<string, TeacherStudentGrade[]> = {
  'cls-7a': [
    {
      studentId: 'std-1',
      studentName: 'Ahmad Fauzi',
      nisn: '0081234567',
      scores: { 'comp-1': 88, 'comp-2': 92, 'comp-3': 90 },
      finalScore: 90.2,
      letterGrade: 'A',
      isPassed: true,
    },
    {
      studentId: 'std-2',
      studentName: 'Aisyah Putri Rahma',
      nisn: '0081234568',
      scores: { 'comp-1': 84, 'comp-2': 86, 'comp-3': 85 },
      finalScore: 85.1,
      letterGrade: 'A-',
      isPassed: true,
    },
    {
      studentId: 'std-3',
      studentName: 'Bagas Aditya Pratama',
      nisn: '0081234569',
      scores: { 'comp-1': 70, 'comp-2': 75, 'comp-3': 72 },
      finalScore: 72.5,
      letterGrade: 'C+',
      isPassed: false,
    },
    {
      studentId: 'std-4',
      studentName: 'Citra Kirana Dewi',
      nisn: '0081234570',
      scores: { 'comp-1': 92, 'comp-2': 95, 'comp-3': 94 },
      finalScore: 93.9,
      letterGrade: 'A+',
      isPassed: true,
    },
    {
      studentId: 'std-5',
      studentName: 'Dimas Wahyu Nugroho',
      nisn: '0081234571',
      scores: { 'comp-1': 80, 'comp-2': 82, 'comp-3': 80 },
      finalScore: 80.6,
      letterGrade: 'B',
      isPassed: true,
    },
  ],
};

export const MOCK_TEACHER_MATERIALS: TeacherMaterialItem[] = [
  {
    id: 'mat-1',
    title: 'Modul Ajar Bab 2: Aljabar Linier dan Polinomial',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    classIds: ['cls-7a', 'cls-7b'],
    classNames: ['7-A Unggulan', '7-B Reguler'],
    fileUrl: '/documents/modul-aljabar-kelas7.pdf',
    description: 'Panduan belajar mandiri materi aljabar semester gasal disertai latihan soal kontekstual.',
    createdAt: '2026-10-01',
    downloadsCount: 54,
  },
  {
    id: 'mat-2',
    title: 'Slide Presentasi: Faktorisasi dan Suku Sejenis',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    classIds: ['cls-7a'],
    classNames: ['7-A Unggulan'],
    fileUrl: '/documents/faktorisasi-aljabar.pptx',
    description: 'Bahan tayang presentasi interaktif di kelas.',
    createdAt: '2026-10-08',
    downloadsCount: 31,
  },
];

export const MOCK_TEACHER_ASSIGNMENTS: TeacherAssignmentItem[] = [
  {
    id: 'asg-1',
    title: 'Lembar Kerja 3: Pemodelan Soal Cerita Aljabar',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    classId: 'cls-7a',
    className: '7-A Unggulan',
    dueDate: '2026-10-18 23:59',
    totalStudents: 32,
    submittedCount: 28,
    gradedCount: 20,
    description: 'Selesaikan 5 permasalahan nyata dengan membuat model matematika suku aljabar.',
    createdAt: '2026-10-10',
  },
  {
    id: 'asg-2',
    title: 'Latihan Mandiri 4: Pembagian Polinomial',
    subjectId: 'sub-mat',
    subjectName: 'Matematika',
    classId: 'cls-7b',
    className: '7-B Reguler',
    dueDate: '2026-10-22 23:59',
    totalStudents: 30,
    submittedCount: 15,
    gradedCount: 0,
    description: 'Latihan mandiri persiapan ulangan harian bab 2.',
    createdAt: '2026-10-13',
  },
];

export const MOCK_ASSIGNMENT_SUBMISSIONS: Record<string, TeacherAssignmentSubmission[]> = {
  'asg-1': [
    {
      id: 'subm-1',
      studentId: 'std-1',
      studentName: 'Ahmad Fauzi',
      nisn: '0081234567',
      submittedAt: '2026-10-14 10:15',
      fileUrl: '/uploads/tugas-ahmad-fauzi.pdf',
      score: 95,
      feedback: 'Penyelesaian langkah sangat runut dan tepat.',
      status: 'graded',
    },
    {
      id: 'subm-2',
      studentId: 'std-2',
      studentName: 'Aisyah Putri Rahma',
      nisn: '0081234568',
      submittedAt: '2026-10-14 14:20',
      fileUrl: '/uploads/tugas-aisyah.pdf',
      score: undefined,
      status: 'pending',
    },
  ],
};

export const MOCK_TEACHER_EXAMS: TeacherExamItem[] = [
  {
    id: 'ex-101',
    title: 'Penilaian Harian 2: Aljabar Linier',
    subjectName: 'Matematika',
    classNames: ['7-A Unggulan', '7-B Reguler'],
    durationMinutes: 60,
    totalQuestions: 25,
    startTime: '2026-10-16 08:00',
    endTime: '2026-10-16 10:00',
    isProctor: false,
    status: 'scheduled',
  },
  {
    id: 'ex-102',
    title: 'Penilaian Tengah Semester (PTS) Gasal - Sesi 1',
    subjectName: 'Matematika & IPA',
    classNames: ['7-A Unggulan'],
    durationMinutes: 90,
    totalQuestions: 40,
    startTime: '2026-10-20 07:30',
    endTime: '2026-10-20 09:30',
    isProctor: true, // Guru bertindak sebagai pengawas
    status: 'scheduled',
  },
];

export const MOCK_TEACHER_LEAVE: TeacherLeaveRequest[] = [
  {
    id: 'lv-1',
    leaveType: 'sick',
    startDate: '2026-09-15',
    endDate: '2026-09-16',
    daysCount: 2,
    reason: 'Sakit flu dan demam tinggi',
    status: 'approved',
    reviewNote: 'Disetujui oleh Kepala Sekolah. Semoga lekas sembuh.',
    createdAt: '2026-09-15T07:00:00Z',
  },
  {
    id: 'lv-2',
    leaveType: 'duty',
    startDate: '2026-10-25',
    endDate: '2026-10-26',
    daysCount: 2,
    reason: 'Pelatihan Kurikulum Merdeka di Balai Guru Penggerak Jawa Timur',
    status: 'pending',
    createdAt: '2026-10-13T10:00:00Z',
  },
];

export const MOCK_TEACHER_SALARY_SLIPS: TeacherSalarySlipItem[] = [
  {
    id: 'slip-2026-10',
    slipNumber: 'SLIP/2026/10/GUR-001',
    periodMonth: 'Oktober 2026',
    basicSalary: 4500000,
    teachingAllowance: 1200000, // 24 jam mengajar
    functionalAllowance: 750000,
    deduction: 150000, // BPJS / koperasi
    totalNetSalary: 6300000,
    paymentDate: '2026-10-01',
    isPaid: true,
  },
  {
    id: 'slip-2026-09',
    slipNumber: 'SLIP/2026/09/GUR-001',
    periodMonth: 'September 2026',
    basicSalary: 4500000,
    teachingAllowance: 1200000,
    functionalAllowance: 750000,
    deduction: 150000,
    totalNetSalary: 6300000,
    paymentDate: '2026-09-01',
    isPaid: true,
  },
];

export const MOCK_SELF_ATTENDANCE: TeacherSelfAttendance = {
  todayDate: '2026-10-14',
  checkInTime: '06:40:15',
  checkOutTime: undefined,
  status: 'present',
  workHoursThisMonth: 128,
  targetWorkHours: 160,
  attendancePercentage: 100,
};

export const MOCK_HOMEROOM_DATA: TeacherHomeroomData = {
  classId: 'cls-7a',
  className: '7-A Unggulan',
  totalStudents: 32,
  academicYear: '2024/2025',
  semester: 'Ganjil',
  attendanceRecap: {
    averageRate: 97.4,
    sickDays: 4,
    leaveDays: 2,
    unexcusedDays: 0,
  },
  characterRecapCount: 32,
  isReportCardLocked: false,
  students: [
    { id: 'std-1', name: 'Ahmad Fauzi', nisn: '0081234567', nis: '24001', attendanceRate: 96.3, averageScore: 90.2, rank: 1 },
    { id: 'std-2', name: 'Aisyah Putri Rahma', nisn: '0081234568', nis: '24002', attendanceRate: 98.7, averageScore: 89.5, rank: 2 },
    { id: 'std-3', name: 'Citra Kirana Dewi', nisn: '0081234570', nis: '24004', attendanceRate: 100, averageScore: 88.0, rank: 3 },
  ],
};
