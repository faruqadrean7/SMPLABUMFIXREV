import { apiClient } from '@/lib/api/api-client';
import { executeWithMockFallback } from '@/lib/api/mock-mode';
import {
  TeacherClass,
  TeacherSubject,
  TeacherScheduleItem,
  TeacherAttendanceStudent,
  TeacherAttendanceSessionPayload,
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
  TeacherQuestionBankItem,
  StudentLeaveRequest,
  StudentPointPayload,
  CounselingReferralPayload,
  FacilityBookingItem,
  FacilityBookingPayload,
  SarprasTicketItem,
  SarprasTicketPayload,
  TeacherQuestionItem,
  TeacherQuestionPayload,
  TeacherLockGradesPayload,
} from './types';

const loadGuruMock =
  process.env.NODE_ENV !== 'production'
    ? () => import('./guru-mock-data')
    : null;

function unwrapList<T>(res: any): T[] {
  if (!res) return [];
  const payload = res.data !== undefined ? res.data : res;
  if (Array.isArray(payload)) return payload as T[];
  if (payload && Array.isArray(payload.data)) return payload.data as T[];
  return [];
}

function unwrapItem<T>(res: any, fallback: T): T {
  if (!res) return fallback;
  const payload = res.data !== undefined ? res.data : res;
  if (payload && typeof payload === 'object' && 'data' in payload && payload.data !== undefined) {
    return payload.data as T;
  }
  return (payload as T) ?? fallback;
}

export const guruApi = {
  // === JADWAL & KELAS BINAAN ===
  async getTeacherClasses(): Promise<TeacherClass[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherClass[]>('/me/teacher/classes');
        return unwrapList<TeacherClass>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_CLASSES) : undefined
    );
  },

  async getTeacherSchedules(): Promise<TeacherScheduleItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherScheduleItem[]>('/me/teacher/schedules');
        return unwrapList<TeacherScheduleItem>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_SCHEDULES) : undefined
    );
  },

  // === PRESENSI KBM ===
  async getAttendanceStudents(classId: string): Promise<TeacherAttendanceStudent[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherAttendanceStudent[]>(`/me/teacher/classes/${classId}/students`);
        return unwrapList<TeacherAttendanceStudent>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_CLASS_STUDENTS[classId] || m.MOCK_CLASS_STUDENTS['cls-7a']) : undefined
    );
  },

  async recordAttendanceSession(payload: TeacherAttendanceSessionPayload): Promise<{ success: boolean; message: string }> {
    const body = {
      class_id: payload.classId,
      classId: payload.classId,
      subject_id: payload.subjectId,
      subjectId: payload.subjectId,
      session_date: payload.sessionDate,
      sessionDate: payload.sessionDate,
      session_topic: payload.sessionTopic,
      sessionTopic: payload.sessionTopic,
      schedule_id: payload.scheduleId,
      scheduleId: payload.scheduleId,
      attendees: payload.attendees.map((a) => ({
        student_id: a.studentId,
        studentId: a.studentId,
        status: a.status,
        notes: a.notes,
      })),
    };
    const res = await apiClient.post<{ message: string }>('/me/teacher/attendance/sessions', body);
    const data = unwrapItem<{ message?: string }>(res, {});
    return { success: true, message: data.message || 'Presensi KBM berhasil disimpan.' };
  },

  // === JURNAL MENGAJAR ===
  async getJournals(): Promise<TeacherJournalItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherJournalItem[]>('/me/teacher/journals');
        return unwrapList<TeacherJournalItem>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_JOURNALS) : undefined
    );
  },

  async createJournal(payload: Partial<TeacherJournalItem>): Promise<TeacherJournalItem> {
    const body = {
      schedule_id: payload.scheduleId,
      scheduleId: payload.scheduleId,
      class_id: payload.classId,
      classId: payload.classId,
      subject_id: payload.subjectId,
      subjectId: payload.subjectId,
      session_date: payload.date,
      date: payload.date,
      topic: payload.lessonTopic,
      lesson_topic: payload.lessonTopic,
      lessonTopic: payload.lessonTopic,
      competence_indicator: payload.competenceIndicator,
      competenceIndicator: payload.competenceIndicator,
      notes: payload.competenceIndicator,
      learning_obstacles: payload.learningObstacles,
      learningObstacles: payload.learningObstacles,
      time_slot: payload.timeSlot,
      timeSlot: payload.timeSlot,
    };
    const res = await apiClient.post<TeacherJournalItem>('/me/teacher/journals', body);
    return unwrapItem<TeacherJournalItem>(res, res.data);
  },

  // === BUKU NILAI ===
  async getGradeComponents(classId: string, subjectId: string): Promise<TeacherGradeComponent[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherGradeComponent[]>('/me/teacher/grade-components', {
          params: { class_id: classId, subject_id: subjectId },
        });
        return unwrapList<TeacherGradeComponent>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_GRADE_COMPONENTS) : undefined
    );
  },

  async getStudentGrades(classId: string, subjectId: string): Promise<TeacherStudentGrade[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherStudentGrade[]>('/me/teacher/grades', {
          params: { class_id: classId, subject_id: subjectId },
        });
        return unwrapList<TeacherStudentGrade>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_STUDENT_GRADES[classId] || m.MOCK_STUDENT_GRADES['cls-7a']) : undefined
    );
  },

  async updateBulkGrades(
    classId: string,
    subjectId: string,
    grades: TeacherStudentGrade[]
  ): Promise<{ success: boolean; message: string }> {
    const res = await apiClient.post<{ message: string }>('/me/teacher/grades/bulk', {
      class_id: classId,
      classId,
      subject_id: subjectId,
      subjectId,
      grades: grades.map((g) => ({
        student_id: g.studentId,
        studentId: g.studentId,
        scores: g.scores,
      })),
    });
    const data = unwrapItem<{ message?: string }>(res, {});
    return { success: true, message: data.message || 'Buku nilai berhasil disimpan.' };
  },

  // === MATERI & MODUL AJAR ===
  async getMaterials(): Promise<TeacherMaterialItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherMaterialItem[]>('/me/teacher/materials');
        return unwrapList<TeacherMaterialItem>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_MATERIALS) : undefined
    );
  },

  async createMaterial(payload: Partial<TeacherMaterialItem>): Promise<TeacherMaterialItem> {
    const body = {
      title: payload.title,
      subject_id: payload.subjectId,
      subjectId: payload.subjectId,
      description: payload.description,
      file_id: (payload as Record<string, unknown>).fileId,
      fileId: (payload as Record<string, unknown>).fileId,
    };
    const res = await apiClient.post<TeacherMaterialItem>('/me/teacher/materials', body);
    return unwrapItem<TeacherMaterialItem>(res, res.data);
  },

  // === TUGAS KBM ===
  async getAssignments(): Promise<TeacherAssignmentItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherAssignmentItem[]>('/me/teacher/assignments');
        return unwrapList<TeacherAssignmentItem>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_ASSIGNMENTS) : undefined
    );
  },

  async createAssignment(payload: Partial<TeacherAssignmentItem>): Promise<TeacherAssignmentItem> {
    const body = {
      title: payload.title,
      class_id: payload.classId,
      classId: payload.classId,
      subject_id: payload.subjectId,
      subjectId: payload.subjectId,
      description: payload.description,
      due_at: payload.dueDate,
      dueDate: payload.dueDate,
    };
    const res = await apiClient.post<TeacherAssignmentItem>('/me/teacher/assignments', body);
    return unwrapItem<TeacherAssignmentItem>(res, res.data);
  },

  async getAssignmentSubmissions(assignmentId: string): Promise<TeacherAssignmentSubmission[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherAssignmentSubmission[]>(`/me/teacher/assignments/${assignmentId}/submissions`);
        return unwrapList<TeacherAssignmentSubmission>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_ASSIGNMENT_SUBMISSIONS[assignmentId] || m.MOCK_ASSIGNMENT_SUBMISSIONS['asg-1']) : undefined
    );
  },

  async gradeSubmission(
    submissionId: string,
    score: number,
    feedback?: string
  ): Promise<{ success: boolean; message: string }> {
    await apiClient.post(`/me/teacher/submissions/${submissionId}/grade`, { score, feedback });
    return { success: true, message: 'Nilai dan feedback berhasil diberikan.' };
  },

  // === UJIAN CBT & PENGAWAS ===
  async getTeacherExams(): Promise<TeacherExamItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherExamItem[]>('/me/teacher/exams');
        return unwrapList<TeacherExamItem>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_EXAMS) : undefined
    );
  },

  // === BANK SOAL & PEMBUATAN UJIAN GURU MANDIRI ===
  async getQuestionBanks(): Promise<TeacherQuestionBankItem[]> {
    const res = await apiClient.get<TeacherQuestionBankItem[]>('/me/teacher/question-banks');
    return unwrapList<TeacherQuestionBankItem>(res);
  },

  async createQuestionBank(payload: { name: string; subject_id: string; grade_level_id?: string }): Promise<TeacherQuestionBankItem> {
    const res = await apiClient.post<TeacherQuestionBankItem>('/me/teacher/question-banks', payload);
    return unwrapItem<TeacherQuestionBankItem>(res, res.data);
  },

  async getQuestionBankDetail(bankId: string): Promise<TeacherQuestionBankItem> {
    const res = await apiClient.get<TeacherQuestionBankItem>(`/me/teacher/question-banks/${bankId}`);
    return unwrapItem<TeacherQuestionBankItem>(res, res.data);
  },

  async createQuestion(bankId: string, payload: any): Promise<any> {
    const res = await apiClient.post(`/me/teacher/question-banks/${bankId}/questions`, payload);
    return unwrapItem(res, res.data);
  },

  async createExam(payload: any): Promise<any> {
    const res = await apiClient.post('/me/teacher/exams', payload);
    return unwrapItem(res, res.data);
  },

  // === IZIN & CUTI GURU ===
  async getLeaveRequests(): Promise<TeacherLeaveRequest[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherLeaveRequest[]>('/me/teacher/leave-requests');
        return unwrapList<TeacherLeaveRequest>(res);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_LEAVE) : undefined
    );
  },

  async createLeaveRequest(payload: {
    leaveType: 'sick' | 'annual' | 'family' | 'duty';
    startDate: string;
    endDate: string;
    reason: string;
    attachmentUrl?: string;
  }): Promise<{ success: boolean; message: string }> {
    await apiClient.post('/me/teacher/leave-requests', payload);
    return { success: true, message: 'Permohonan izin berhasil diajukan.' };
  },

  // === SLIP GAJI MANDIRI (TERHUBUNG KE BACKEND RESMI) ===
  async getSalarySlips(): Promise<TeacherSalarySlipItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<{ data: any[] }>('/salary-slips');
        const rawList = unwrapList<any>(res);
        return rawList.map((item) => ({
          id: item.id,
          slipNumber: item.slip_number || `SLIP-${item.id}`,
          periodMonth: item.period_month || item.period || 'Periode Berjalan',
          basicSalary: Number(item.basic_salary || 0),
          teachingAllowance: Number(item.teaching_allowance || 0),
          functionalAllowance: Number(item.functional_allowance || 0),
          deduction: Number(item.deduction || 0),
          totalNetSalary: Number(item.total_net_salary || item.net_salary || 0),
          paymentDate: item.payment_date || item.created_at,
          isPaid: Boolean(item.is_paid ?? true),
          downloadUrl: `/api/v1/salary-slips/${item.id}/download`,
        }));
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_TEACHER_SALARY_SLIPS) : undefined
    );
  },

  // === PRESENSI MANDIRI GURU ===
  async getSelfAttendance(): Promise<TeacherSelfAttendance> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherSelfAttendance>('/me/teacher/self-attendance');
        return unwrapItem<TeacherSelfAttendance>(res, {} as TeacherSelfAttendance);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_SELF_ATTENDANCE) : undefined
    );
  },

  async recordSelfAttendance(action: 'check_in' | 'check_out'): Promise<{ success: boolean; message: string; timestamp: string }> {
    const now = new Date().toTimeString().split(' ')[0];
    await apiClient.post('/me/teacher/self-attendance', { action });
    return { success: true, message: `Berhasil melakukan ${action === 'check_in' ? 'Check-in' : 'Check-out'}.`, timestamp: now };
  },

  // === WALI KELAS (KONDISIONAL) ===
  async getHomeroomData(): Promise<TeacherHomeroomData> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<TeacherHomeroomData>('/me/teacher/homeroom');
        return unwrapItem<TeacherHomeroomData>(res, {} as TeacherHomeroomData);
      },
      loadGuruMock ? () => loadGuruMock().then((m) => m.MOCK_HOMEROOM_DATA) : undefined
    );
  },

  // === FITUR A: PERIZINAN SISWA OLEH WALI KELAS ===
  async getHomeroomLeaveRequests(params?: {
    class_id?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
    q?: string;
    page?: number;
    per_page?: number;
  }): Promise<StudentLeaveRequest[]> {
    const res = await apiClient.get<StudentLeaveRequest[]>('/me/teacher/homeroom/leave-requests', { params });
    return unwrapList<StudentLeaveRequest>(res);
  },

  async decideHomeroomLeaveRequest(
    id: string,
    payload: { decision: 'approved' | 'rejected'; decision_notes?: string }
  ): Promise<StudentLeaveRequest> {
    const res = await apiClient.patch<StudentLeaveRequest>(`/me/teacher/homeroom/leave-requests/${id}/decision`, payload);
    return unwrapItem<StudentLeaveRequest>(res, res.data);
  },

  // === FITUR B: POIN KARAKTER & RUJUKAN BK ===
  async recordStudentPoint(payload: StudentPointPayload): Promise<{ success: boolean; message: string; data?: any }> {
    const res = await apiClient.post<{ message: string; data: any }>('/me/teacher/student-points', payload);
    return {
      success: true,
      message: res.data?.message || 'Poin karakter siswa berhasil dicatat.',
      data: res.data?.data,
    };
  },

  async createCounselingReferral(payload: CounselingReferralPayload): Promise<{ success: boolean; message: string; data?: any }> {
    const res = await apiClient.post<{ message: string; data: any }>('/me/teacher/counseling-referrals', payload);
    return {
      success: true,
      message: res.data?.message || 'Rujukan bimbingan konseling berhasil dikirimkan.',
      data: res.data?.data,
    };
  },

  // === FITUR C: PEMINJAMAN FASILITAS & TIKET SARPRAS ===
  async getFacilityBookings(params?: {
    status?: string;
    start_date?: string;
    end_date?: string;
    page?: number;
    per_page?: number;
  }): Promise<FacilityBookingItem[]> {
    const res = await apiClient.get<FacilityBookingItem[]>('/me/teacher/facility-bookings', { params });
    return unwrapList<FacilityBookingItem>(res);
  },

  async createFacilityBooking(payload: FacilityBookingPayload): Promise<FacilityBookingItem> {
    const res = await apiClient.post<FacilityBookingItem>('/me/teacher/facility-bookings', payload);
    return unwrapItem<FacilityBookingItem>(res, res.data);
  },

  async createSarprasTicket(payload: SarprasTicketPayload): Promise<SarprasTicketItem> {
    const res = await apiClient.post<SarprasTicketItem>('/me/teacher/tickets', payload);
    return unwrapItem<SarprasTicketItem>(res, res.data);
  },

  // === FITUR D: BANK SOAL MANDIRI GURU ===
  async getTeacherQuestions(params?: {
    subject_id?: string;
    bank_id?: string;
    type?: string;
    difficulty?: string;
    q?: string;
    page?: number;
    per_page?: number;
  }): Promise<TeacherQuestionItem[]> {
    const res = await apiClient.get<TeacherQuestionItem[]>('/me/teacher/questions', { params });
    return unwrapList<TeacherQuestionItem>(res);
  },

  async createDirectQuestion(payload: TeacherQuestionPayload): Promise<TeacherQuestionItem> {
    const res = await apiClient.post<TeacherQuestionItem>('/me/teacher/questions', payload);
    return unwrapItem<TeacherQuestionItem>(res, res.data);
  },

  // === FITUR E: PENGUNCIAN LEDGER NILAI GURU ===
  async lockTeacherGrades(payload: TeacherLockGradesPayload): Promise<{ success: boolean; message: string; count?: number }> {
    const res = await apiClient.post<{ message: string; count?: number }>('/me/teacher/grades/lock', payload);
    return {
      success: true,
      message: res.data?.message || 'Buku nilai guru berhasil dikunci.',
      count: res.data?.count,
    };
  },
};
