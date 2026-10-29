export interface PortalChildSummary {
  id: string;
  name: string;
  nisn: string;
  nis?: string;
  className: string;
  academicYear?: string;
  gender?: 'male' | 'female' | string;
  avatarUrl?: string | null;
  status?: string;
}

export interface ChildAttendanceRecord {
  date: string;
  status: 'present' | 'sick' | 'permission' | 'unexcused';
  checkInTime?: string;
  notes?: string;
}

export interface ChildAttendanceSummary {
  presentDays: number;
  sickDays: number;
  leaveDays: number;
  unexcusedDays: number;
  totalDays: number;
  percentage: number;
  history: ChildAttendanceRecord[];
}

export interface ChildGradeSubject {
  subjectId: string;
  subjectName: string;
  subjectCode: string;
  teacherName: string;
  dailyAverage: number;
  midExam: number;
  finalExam: number;
  finalScore: number;
  letterGrade: string;
  passingGrade: number;
  isPassed: boolean;
  competencyDesc?: string;
}

export interface ChildReportCardSummary {
  periodId: string;
  periodName: string;
  academicYear: string;
  semester: string;
  totalScore: number;
  averageScore: number;
  classRank?: number;
  totalStudents?: number;
  homeroomNote?: string;
  grades: ChildGradeSubject[];
}

export interface ChildCharacterNoteSummary {
  periodId: string;
  periodName: string;
  academicYear: string;
  ratings: {
    beriman_bertakwa: 'sangat_baik' | 'baik' | 'cukup' | 'perlu_bimbingan';
    gotong_royong: 'sangat_baik' | 'baik' | 'cukup' | 'perlu_bimbingan';
    mandiri: 'sangat_baik' | 'baik' | 'cukup' | 'perlu_bimbingan';
    bernalar_kritis: 'sangat_baik' | 'baik' | 'cukup' | 'perlu_bimbingan';
    kreatif: 'sangat_baik' | 'baik' | 'cukup' | 'perlu_bimbingan';
  };
  homeroomNote: string;
  updatedAt: string;
}

export interface ChildProgressReportItem {
  id: string;
  title: string;
  periodName: string;
  category: string;
  narrative: string;
  teacherName: string;
  date: string;
}

export interface StudentScheduleItem {
  id: string;
  dayOfWeek: number;
  dayName: string;
  startTime: string;
  endTime: string;
  subjectName: string;
  teacherName: string;
  roomName: string;
}

export interface StudentExamItem {
  id: string;
  title: string;
  subjectName: string;
  durationMinutes: number;
  totalQuestions: number;
  startTime: string;
  endTime: string;
  status: 'scheduled' | 'ongoing' | 'completed' | 'expired';
}

export interface StudentDashboardSummary {
  name: string;
  nis: string;
  nisn: string;
  className: string;
  todaySchedule: StudentScheduleItem[];
  upcomingExams: StudentExamItem[];
  attendanceRate: number;
  totalAchievements: number;
}

export interface StudentTaskItem {
  id: string;
  title: string;
  subjectName: string;
  dueDate: string;
  dueTime?: string;
  status: 'aktif' | 'dikumpulkan' | 'dinilai';
  description: string;
  teacherName: string;
  maxScore: number;
  score?: number;
  teacherFeedback?: string;
  submission?: {
    id?: string;
    file_name: string;
    submitted_at: string;
    drive_link?: string;
    notes?: string;
  };
}

export interface StudentCounselingItem {
  id: string;
  topic: string;
  category: 'Akademik' | 'Sosial & Teman' | 'Pengembangan Diri' | 'Keluarga' | string;
  requestDate: string;
  preferredSchedule: string;
  counselorName: string;
  status: 'menunggu' | 'dijadwalkan' | 'selesai' | 'ditolak';
  notes?: string;
  scheduledTime?: string;
  roomName?: string;
}

export interface StudentPointLogItem {
  id: string;
  date: string;
  category: string;
  title: string;
  kind: 'prestasi' | 'pelanggaran';
  points: number;
  reporterName: string;
  notes?: string;
}

export interface StudentPointsResult {
  totalPrestasi: number;
  totalPelanggaran: number;
  balancePoints: number;
  logs: StudentPointLogItem[];
  pagination?: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
}

