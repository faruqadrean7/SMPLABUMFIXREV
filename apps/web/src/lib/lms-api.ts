import { apiClient } from './api-client';
import { 
  Assignment, 
  AssignmentSubmission, 
  LearningMaterial, 
  TeachingJournal, 
  TeachingSchedule, 
  StudentRosterItem, 
  GradeComponent, 
  StudentGradeRow, 
  GradebookPeriod, 
  CBTQuestion, 
  CBTExam,
  AttendanceStatus 
} from '@/types/lms';

export const lmsApi = {
  // ─── 1. Modul & Perangkat Ajar (BE-ACD-06) ───
  async getMaterials(classId?: string, subjectId?: string): Promise<LearningMaterial[]> {
    const response = await apiClient.get('/me/teacher/materials', {
      params: { class_id: classId, subject_id: subjectId },
    });
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }
    return response.data?.data || [];
  },

  async createMaterial(formData: FormData): Promise<LearningMaterial> {
    const response = await apiClient.post('/me/teacher/materials', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data?.data;
  },

  // ─── 2. Tugas KBM & Pengumpulan (BE-ACD-07) ───
  async getAssignments(classId?: string, subjectId?: string): Promise<Assignment[]> {
    const response = await apiClient.get('/me/teacher/assignments', {
      params: { class_id: classId, subject_id: subjectId },
    });
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }
    return response.data?.data || [];
  },

  async getAssignmentDetail(id: string): Promise<Assignment> {
    const response = await apiClient.get(`/me/teacher/assignments/${id}`);
    if (response.data?.data) {
      return response.data.data;
    }
    throw new Error('Tugas tidak ditemukan.');
  },

  async createAssignment(payload: {
    title: string;
    class_id?: string;
    classId?: string;
    subject_id?: string;
    subjectId?: string;
    due_at?: string;
    dueDate?: string;
    description?: string;
    max_score?: number;
    maxScore?: number;
    grade_component_name?: string;
    gradeComponentName?: string;
    allow_late?: boolean;
    allowLate?: boolean;
    is_locked?: boolean;
    lockAfterDue?: boolean;
    file?: File | null;
  }): Promise<Assignment> {
    const response = await apiClient.post('/me/teacher/assignments', payload);
    return response.data?.data;
  },

  async getSubmissions(assignmentId: string): Promise<AssignmentSubmission[]> {
    const response = await apiClient.get(`/me/teacher/assignments/${assignmentId}/submissions`);
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }
    return [];
  },

  async gradeSubmission(
    assignmentId: string,
    submissionId: string,
    payload: { score: number; feedback?: string }
  ): Promise<AssignmentSubmission> {
    const response = await apiClient.post(`/me/teacher/submissions/${submissionId}/grade`, payload);
    return response.data?.data;
  },

  // ─── 3. Jadwal & Jurnal Mengajar (BE-ACD-01, BE-ACD-02) ───
  async getSchedules(): Promise<TeachingSchedule[]> {
    const response = await apiClient.get('/me/teacher/schedules');
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data.map((s: any) => ({
        id: s.id,
        class_id: s.classId || s.class_id,
        subject_id: s.subjectId || s.subject_id,
        day_name: s.dayName || 'Senin',
        start_time: s.startTime || '07:30',
        end_time: s.endTime || '09:00',
        room_name: s.roomName || 'Lab Komputer',
        class_name: s.className || 'Kelas',
        subject_name: s.subjectName || 'Mata Pelajaran',
      }));
    }
    return [];
  },

  async getJournals(classId?: string, subjectId?: string): Promise<TeachingJournal[]> {
    const response = await apiClient.get('/me/teacher/journals', {
      params: { class_id: classId, subject_id: subjectId },
    });
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data.map((j: any) => ({
        id: j.id,
        schedule_id: j.scheduleId || j.schedule_id || '',
        teacher_id: j.teacher_id || '',
        class_id: j.classId || j.class_id || '',
        subject_id: j.subjectId || j.subject_id || '',
        meeting_number: j.meeting_number || 1,
        session_date: j.date || j.session_date || '',
        time_range: j.timeSlot || j.time_range || '',
        room_name: j.roomName || j.room_name || 'Ruang Kelas',
        topic: j.lessonTopic || j.topic || 'Pembelajaran Tatap Muka',
        method: j.method || 'Ceramah & Diskusi',
        notes: j.competenceIndicator || j.notes || '',
        attendance_summary: j.attendance_summary || {
          total: 0,
          hadir: 0,
          sakit: 0,
          izin: 0,
          alpa: 0,
          terlambat: 0,
          attendance_rate: 100,
        },
        class: j.class || { id: j.classId || '', name: j.className || 'Kelas', grade_level: 'VII', academic_year: '2026/2027 Ganjil' },
        subject: j.subject || { id: j.subjectId || '', name: j.subjectName || 'Mata Pelajaran', code: 'MP', category: 'wajib' },
        created_at: j.createdAt || j.created_at || new Date().toISOString(),
      }));
    }
    return [];
  },

  async getStudentRoster(classId?: string): Promise<StudentRosterItem[]> {
    if (!classId) return [];
    const response = await apiClient.get(`/me/teacher/classes/${classId}/students`);
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data.map((s: any) => ({
        id: s.studentId || s.id,
        nis: s.nis || '',
        name: s.studentName || s.name || s.full_name,
        gender: s.gender || 'L',
        status: (s.status === 'present' ? 'hadir' : s.status) || 'hadir',
        note: s.notes || s.note || '',
        avatar_url: s.avatar_url || null,
      }));
    }
    return [];
  },

  async createJournalWithAttendance(payload: {
    schedule_id?: string;
    class_id?: string;
    subject_id?: string;
    session_date: string;
    topic: string;
    method: string;
    notes?: string;
    attendances: { student_id: string; status: AttendanceStatus; note?: string }[];
  }): Promise<TeachingJournal> {
    const isUuid = (val?: string) =>
      Boolean(val && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(val));

    // FIX-GURU-01: jurnal + presensi dikirim dalam satu panggilan agar tersimpan dalam satu
    // transaksi di backend dan terbaca di Riwayat (tanpa duplikasi jurnal / gating UUID klien).
    const journalPayload: Record<string, any> = {
      session_date: payload.session_date,
      topic: payload.topic,
      method: payload.method,
      notes: payload.notes || '',
      attendances: payload.attendances.map((a) => ({
        student_id: a.student_id,
        status: a.status,
        note: a.note,
      })),
    };

    if (isUuid(payload.schedule_id)) {
      journalPayload.schedule_id = payload.schedule_id;
    }
    if (isUuid(payload.class_id)) {
      journalPayload.class_id = payload.class_id;
    }
    if (isUuid(payload.subject_id)) {
      journalPayload.subject_id = payload.subject_id;
    }

    const journalRes = await apiClient.post<any>('/me/teacher/journals', journalPayload);
    const createdJournal = journalRes.data?.data || journalRes.data;

    const hadirCount = payload.attendances.filter((a) => a.status === 'hadir').length;
    const sakitCount = payload.attendances.filter((a) => a.status === 'sakit').length;
    const izinCount = payload.attendances.filter((a) => a.status === 'izin').length;
    const alpaCount = payload.attendances.filter((a) => a.status === 'alpa').length;
    const terlambatCount = payload.attendances.filter((a) => a.status === 'terlambat').length;
    const total = payload.attendances.length || 0;

    return {
      id: createdJournal?.id || `jrn-${Date.now()}`,
      schedule_id: createdJournal?.schedule_id || payload.schedule_id || '',
      teacher_id: createdJournal?.teacher_id || '',
      class_id: createdJournal?.class_id || payload.class_id || '',
      subject_id: createdJournal?.subject_id || payload.subject_id || '',
      meeting_number: createdJournal?.meeting_number || 1,
      session_date: createdJournal?.session_date || payload.session_date,
      time_range: createdJournal?.time_range || '07:30 - 09:00 WIB',
      room_name: createdJournal?.room_name || 'Ruang Kelas',
      topic: createdJournal?.topic || payload.topic,
      method: createdJournal?.method || payload.method,
      notes: createdJournal?.notes || payload.notes,
      attendance_summary: {
        total,
        hadir: hadirCount,
        sakit: sakitCount,
        izin: izinCount,
        alpa: alpaCount,
        terlambat: terlambatCount,
        attendance_rate: total > 0 ? Math.round(((hadirCount + terlambatCount) / total) * 100) : 0,
      },
      class: createdJournal?.class || { id: payload.class_id || '', name: 'Kelas', grade_level: 'VII', academic_year: '2026/2027 Ganjil' },
      subject: createdJournal?.subject || { id: payload.subject_id || '', name: 'Mata Pelajaran', code: 'MP', category: 'wajib' },
      created_at: createdJournal?.created_at || new Date().toISOString(),
    };
  },

  // ─── 4. Buku Nilai Ledger KKTP (BE-GRD-01) ───
  async getGradeComponents(classId?: string, subjectId?: string): Promise<GradeComponent[]> {
    const response = await apiClient.get('/me/teacher/grade-components', {
      params: { class_id: classId, subject_id: subjectId },
    });
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }
    return [];
  },

  async getGradebook(classId: string, subjectId: string): Promise<{
    period: GradebookPeriod;
    components: GradeComponent[];
    grades: StudentGradeRow[];
  }> {
    const response = await apiClient.get('/me/teacher/grades', {
      params: { class_id: classId, subject_id: subjectId },
    });
    if (response.data?.data?.grades) {
      return response.data.data;
    }
    return {
      period: { id: 'prd-01', name: 'Semester Gasal 2026/2027', is_locked: false, class_id: classId, subject_id: subjectId },
      components: [],
      grades: [],
    };
  },

  async saveBulkGrades(payload: {
    class_id: string;
    subject_id: string;
    grades: { student_id: string; scores: Record<string, number | null> }[];
  }): Promise<{ success: boolean; updated_count: number }> {
    const response = await apiClient.post('/me/teacher/grades/bulk', payload);
    return response.data;
  },

  async lockGradePeriod(periodId: string, lock: boolean): Promise<GradebookPeriod> {
    const response = await apiClient.patch(`/grade-periods/${periodId}/lock`, { is_locked: lock });
    return response.data?.data;
  },

  // ─── 5. Bank Soal & Ujian CBT (BE-CBT-01, BE-CBT-02) ───
  async getCBTQuestions(subjectId?: string): Promise<CBTQuestion[]> {
    const response = await apiClient.get('/questions', { params: { subject_id: subjectId } });
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }
    return [];
  },

  async getCBTExams(classId?: string, subjectId?: string): Promise<CBTExam[]> {
    const response = await apiClient.get('/me/teacher/exams', {
      params: { class_id: classId, subject_id: subjectId },
    });
    if (response.data?.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }
    return [];
  },

  // ─── 6. Gap Remediation: Fitur A - E (Wali Kelas, BK, Sarpras, Bank Soal, Lock) ───
  async getHomeroomLeaveRequests(params?: {
    class_id?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
    q?: string;
    page?: number;
    per_page?: number;
  }) {
    const res = await apiClient.get('/me/teacher/homeroom/leave-requests', { params });
    return res.data;
  },

  async decideHomeroomLeaveRequest(id: string, payload: { decision: 'approved' | 'rejected'; decision_notes?: string }) {
    const res = await apiClient.patch(`/me/teacher/homeroom/leave-requests/${id}/decision`, payload);
    return res.data;
  },

  /**
   * Resolusi tautan berkas (lampiran perizinan, dsb.) berbasis file_id.
   * Otorisasi kepemilikan diperiksa di backend (FileStorageService::getFileUrl).
   */
  async getFileUrl(fileId: string, ttl = 300): Promise<string | null> {
    const res = await apiClient.get(`/files/${fileId}/url`, { params: { ttl } });
    const data = res.data?.data ?? res.data;
    return data?.url ?? null;
  },

  async recordStudentPoint(payload: {
    student_id: string;
    point_type_id?: string;
    date?: string;
    notes?: string;
    category?: string;
    points?: number;
    type?: string;
    kind?: string;
  }) {
    const res = await apiClient.post('/me/teacher/student-points', payload);
    return res.data;
  },

  async createCounselingReferral(payload: {
    student_id: string;
    reason: string;
    priority?: string;
  }) {
    const res = await apiClient.post('/me/teacher/counseling-referrals', payload);
    return res.data;
  },

  async getFacilities(params?: { per_page?: number; q?: string }) {
    const res = await apiClient.get('/facilities', { params: { per_page: 100, ...params } });
    return res.data;
  },

  async getFacilityBookings(params?: {
    status?: string;
    start_date?: string;
    end_date?: string;
    page?: number;
    per_page?: number;
  }) {
    const res = await apiClient.get('/me/teacher/facility-bookings', { params });
    return res.data;
  },

  async createFacilityBooking(payload: {
    facility_id: string;
    start_at?: string;
    end_at?: string;
    start_time?: string;
    end_time?: string;
    purpose: string;
    attendees_count?: number;
    participant_count?: number;
  }) {
    const body = {
      facility_id: payload.facility_id,
      start_at: payload.start_at || payload.start_time,
      end_at: payload.end_at || payload.end_time,
      purpose: payload.purpose,
      attendees_count: payload.attendees_count || payload.participant_count || 1,
    };
    const res = await apiClient.post('/me/teacher/facility-bookings', body);
    return res.data;
  },

  async createTicket(payload: {
    title?: string;
    category?: string;
    priority?: string;
    subject?: string;
    description: string;
    location?: string;
    room_id?: string;
    asset_id?: string;
  }) {
    const body = {
      title: payload.title || payload.subject || 'Laporan Sarpras',
      category: payload.category,
      priority: payload.priority || 'sedang',
      description: payload.description,
      location: payload.location,
      room_id: payload.room_id,
      asset_id: payload.asset_id,
    };
    const res = await apiClient.post('/me/teacher/tickets', body);
    return res.data;
  },

  async getQuestions(params?: {
    subject_id?: string;
    bank_id?: string;
    type?: string;
    difficulty?: string;
    q?: string;
    page?: number;
    per_page?: number;
  }) {
    const res = await apiClient.get('/me/teacher/questions', { params });
    return res.data;
  },

  async createDirectQuestion(payload: {
    subject_id?: string;
    bank_id?: string;
    question_type?: string;
    type?: string;
    prompt?: string;
    question_text?: string;
    body?: any;
    options?: any;
    answer_key?: any;
    weight?: number;
    points?: number;
    difficulty?: any;
  }) {
    const body = {
      subject_id: payload.subject_id,
      bank_id: payload.bank_id,
      question_type: payload.question_type || payload.type || 'pg',
      question_text: payload.question_text || payload.prompt,
      body: payload.body || (payload.prompt ? { text: payload.prompt } : undefined),
      options: payload.options,
      weight: payload.weight || payload.points || 1.0,
      difficulty: payload.difficulty,
    };
    const res = await apiClient.post('/me/teacher/questions', body);
    return res.data;
  },

  async lockTeacherGrades(payload: {
    class_id: string;
    subject_id: string;
    period_id?: string;
    academic_year_id?: string;
    semester_id?: string;
  }) {
    const res = await apiClient.post('/me/teacher/grades/lock', payload);
    return res.data;
  },
};

export default lmsApi;
