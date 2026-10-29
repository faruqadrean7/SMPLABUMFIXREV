export interface TeacherClass {
  id: string;
  name: string;
  gradeLevel: number;
  studentCount: number;
}

export interface TeacherSubject {
  id: string;
  name: string;
  code: string;
}

export interface TeacherScheduleItem {
  id: string;
  dayOfWeek: number;
  dayName: string;
  startTime: string;
  endTime: string;
  classId: string;
  className: string;
  subjectId: string;
  subjectName: string;
  roomName: string;
  isAttendanceRecorded: boolean;
}

export interface TeacherAttendanceStudent {
  studentId: string;
  studentName: string;
  nisn: string;
  nis?: string;
  status: 'present' | 'sick' | 'permission' | 'unexcused';
  notes?: string;
}

export interface TeacherAttendanceSessionPayload {
  scheduleId?: string;
  classId: string;
  subjectId: string;
  sessionDate: string;
  sessionTopic?: string;
  attendees: Array<{
    studentId: string;
    status: 'present' | 'sick' | 'permission' | 'unexcused';
    notes?: string;
  }>;
}

export interface TeacherJournalItem {
  id: string;
  scheduleId?: string;
  classId: string;
  className: string;
  subjectId: string;
  subjectName: string;
  date: string;
  timeSlot: string;
  lessonTopic: string;
  competenceIndicator: string;
  studentAbsentees: string[];
  learningObstacles?: string;
  isLocked: boolean;
  createdAt: string;
}

export interface TeacherGradeComponent {
  id: string;
  name: string;
  code: string;
  weight: number;
  classId?: string;
  subjectId: string;
}

export interface TeacherStudentGrade {
  studentId: string;
  studentName: string;
  nisn: string;
  scores: Record<string, number>; // misal: { 'comp-uh1': 85, 'comp-tugas': 90, 'comp-pts': 88 }
  finalScore: number;
  letterGrade: string;
  isPassed: boolean;
}

export interface TeacherMaterialItem {
  id: string;
  title: string;
  subjectId: string;
  subjectName: string;
  classIds: string[];
  classNames: string[];
  fileUrl?: string;
  externalLink?: string;
  description: string;
  createdAt: string;
  downloadsCount: number;
}

export interface TeacherAssignmentItem {
  id: string;
  title: string;
  subjectId: string;
  subjectName: string;
  classId: string;
  className: string;
  dueDate: string;
  totalStudents: number;
  submittedCount: number;
  gradedCount: number;
  description: string;
  createdAt: string;
}

export interface TeacherAssignmentSubmission {
  id: string;
  studentId: string;
  studentName: string;
  nisn: string;
  submittedAt: string;
  fileUrl?: string;
  notes?: string;
  score?: number;
  feedback?: string;
  status: 'pending' | 'graded' | 'late';
}

export interface TeacherExamItem {
  id: string;
  title: string;
  subjectName: string;
  classNames: string[];
  durationMinutes: number;
  totalQuestions: number;
  startTime: string;
  endTime: string;
  isProctor: boolean;
  status: 'scheduled' | 'ongoing' | 'completed';
}

export interface TeacherLeaveRequest {
  id: string;
  leaveType: 'sick' | 'annual' | 'family' | 'duty';
  startDate: string;
  endDate: string;
  daysCount: number;
  reason: string;
  attachmentUrl?: string;
  status: 'pending' | 'approved' | 'rejected';
  reviewNote?: string;
  createdAt: string;
}

export interface TeacherSalarySlipItem {
  id: string;
  slipNumber: string;
  periodMonth: string; // "Oktober 2026"
  basicSalary: number;
  teachingAllowance: number;
  functionalAllowance: number;
  deduction: number;
  totalNetSalary: number;
  paymentDate: string;
  isPaid: boolean;
  downloadUrl?: string;
}

export interface TeacherSelfAttendance {
  todayDate: string;
  checkInTime?: string;
  checkOutTime?: string;
  status: 'present' | 'late' | 'not_checked_in';
  workHoursThisMonth: number;
  targetWorkHours: number;
  attendancePercentage: number;
}

export interface TeacherHomeroomData {
  classId: string;
  className: string;
  totalStudents: number;
  academicYear: string;
  semester: string;
  attendanceRecap: {
    averageRate: number;
    sickDays: number;
    leaveDays: number;
    unexcusedDays: number;
  };
  characterRecapCount: number;
  isReportCardLocked: boolean;
  students: Array<{
    id: string;
    name: string;
    nisn: string;
    nis: string;
    attendanceRate: number;
    averageScore: number;
    rank: number;
  }>;
}

export interface TeacherQuestionBankItem {
  id: string;
  name: string;
  subject_id: string;
  grade_level_id?: string;
  questions_count?: number;
  subject?: { id: string; name: string; code: string };
  gradeLevel?: { id: string; name: string };
  questions?: Array<{
    id: string;
    question_type: string;
    body: { text?: string; [key: string]: any };
    cognitive_level?: string;
    difficulty?: number;
    weight?: number;
    options?: any[];
  }>;
}

// === GAP REMEDIATION: FITUR A - E TYPES ===

export interface StudentLeaveRequest {
  id: string;
  studentId?: string;
  student_id: string;
  studentName?: string;
  leaveType?: 'sakit' | 'izin' | 'dispen';
  leave_type: 'sakit' | 'izin' | 'dispen';
  startDate?: string;
  start_date: string;
  endDate?: string;
  end_date: string;
  reason: string;
  status: 'menunggu' | 'disetujui' | 'ditolak';
  decisionNotes?: string;
  decision_notes?: string;
  decidedBy?: string;
  student?: {
    id: string;
    full_name: string;
    nis: string;
    nisn: string;
    classes?: Array<{ id: string; name: string }>;
  };
}

export interface StudentPointPayload {
  student_id: string;
  point_type_id?: string;
  kind?: 'prestasi' | 'pelanggaran';
  category?: string;
  points?: number;
  date?: string;
  notes?: string;
  idempotency_key?: string;
}

export interface CounselingReferralPayload {
  student_id: string;
  reason: string;
  priority?: 'biasa' | 'segera' | 'normal' | 'tinggi';
}

export interface FacilityBookingItem {
  id: string;
  facility_id: string;
  facility_name?: string;
  facility?: { id: string; name: string; code?: string; type?: string } | string;
  start_at?: string;
  end_at?: string;
  start_time?: string;
  end_time?: string;
  date?: string;
  time_slot?: string;
  purpose: string;
  status: 'menunggu' | 'disetujui' | 'ditolak' | 'selesai' | 'dibatalkan' | 'approved' | 'rejected';
  attendees_count?: number;
  participant_count?: number;
  decision_notes?: string;
  notes?: string;
  requested_by?: string;
  created_at: string;
}

export interface FacilityBookingPayload {
  facility_id: string;
  start_at?: string;
  end_at?: string;
  start_time?: string;
  end_time?: string;
  purpose: string;
  attendees_count?: number;
  participant_count?: number;
}

export interface SarprasTicketItem {
  id: string;
  ticket_number: string;
  reporter_id: string;
  category: string;
  priority: 'rendah' | 'sedang' | 'tinggi' | 'darurat';
  subject?: string;
  title?: string;
  description: string;
  location?: string;
  status: 'terbuka' | 'diproses' | 'selesai' | 'dibatalkan' | 'pending';
  created_at: string;
}

export interface SarprasTicketPayload {
  title?: string;
  subject?: string;
  category: string;
  priority: 'rendah' | 'sedang' | 'tinggi' | 'darurat';
  description: string;
  location?: string;
}

export interface TeacherQuestionItem {
  id: string;
  bank_id?: string;
  subject_id: string;
  type: string;
  prompt: string;
  options?: any;
  weight?: number;
  difficulty?: string;
  created_at: string;
}

export interface TeacherQuestionPayload {
  subject_id: string;
  bank_id?: string;
  type: string;
  prompt: string;
  options?: any;
  answer_key?: any;
  weight?: number;
  difficulty?: string;
}

export interface TeacherLockGradesPayload {
  class_id: string;
  subject_id: string;
  academic_year_id?: string;
  semester_id?: string;
}

