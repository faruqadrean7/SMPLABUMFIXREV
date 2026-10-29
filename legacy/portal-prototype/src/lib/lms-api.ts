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
import { 
  MOCK_ASSIGNMENTS, 
  MOCK_MATERIALS, 
  MOCK_SUBMISSIONS, 
  MOCK_SCHEDULES, 
  MOCK_JOURNALS, 
  MOCK_STUDENTS_ROSTER, 
  MOCK_GRADE_COMPONENTS_DETAILED, 
  MOCK_GRADEBOOK_PERIOD, 
  MOCK_STUDENT_GRADES, 
  MOCK_CBT_QUESTIONS, 
  MOCK_CBT_EXAMS,
  MOCK_CLASSES,
  MOCK_SUBJECTS,
  MOCK_ROSTERS_BY_CLASS,
  MOCK_GRADES_BY_CLASS,
  CURRENT_TEACHER
} from './mock-lms-data';

let useMockFallback = false;

export const setMockFallback = (enabled: boolean) => {
  useMockFallback = enabled;
};

export const lmsApi = {
  // ─── 1. Modul & Perangkat Ajar (BE-ACD-06) ───
  async getMaterials(classId?: string, subjectId?: string): Promise<LearningMaterial[]> {
    try {
      const response = await apiClient.get('/me/teacher/materials', {
        params: { class_id: classId, subject_id: subjectId },
      });
      if (response.data?.data && Array.isArray(response.data.data)) {
        return response.data.data;
      }
      return response.data?.data || [];
    } catch (err) {
      if (useMockFallback) {
        let results = [...MOCK_MATERIALS];
        if (classId) results = results.filter((m) => m.class_id === classId);
        if (subjectId) results = results.filter((m) => m.subject_id === subjectId);
        return results;
      }
      throw err;
    }
  },

  async createMaterial(formData: FormData): Promise<LearningMaterial> {
    try {
      const response = await apiClient.post('/me/teacher/materials', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return response.data?.data;
    } catch (err) {
      if (useMockFallback) {
        const title = (formData.get('title') as string) || 'Modul Ajar Baru';
        const description = (formData.get('description') as string) || '';
        const classId = (formData.get('class_id') as string) || 'cls-9a';
        const subjectId = (formData.get('subject_id') as string) || 'sbj-inf';
        const file = formData.get('file') as File | null;

        const newMat: LearningMaterial = {
          id: `mat-${Date.now()}`,
          class_id: classId,
          subject_id: subjectId,
          teacher_id: 'tch-001',
          title,
          description,
          category: 'modul_ajar',
          version: 'v1.0',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
          class: MOCK_CLASSES.find((c) => c.id === classId) || MOCK_CLASSES[0],
          subject: MOCK_SUBJECTS.find((s) => s.id === subjectId) || MOCK_SUBJECTS[0],
          teacher: CURRENT_TEACHER,
          file: file
            ? {
                id: `fil-${Date.now()}`,
                object_key: `materials/${file.name}`,
                filename: file.name,
                mime_type: file.type || 'application/pdf',
                size_bytes: file.size,
              }
            : undefined,
        };
        MOCK_MATERIALS.unshift(newMat);
        return newMat;
      }
      throw err;
    }
  },

  // ─── 2. Tugas KBM & Pengumpulan (BE-ACD-07) ───
  async getAssignments(classId?: string, subjectId?: string): Promise<Assignment[]> {
    try {
      const response = await apiClient.get('/me/teacher/assignments', {
        params: { class_id: classId, subject_id: subjectId },
      });
      if (response.data?.data && Array.isArray(response.data.data)) {
        return response.data.data;
      }
      return response.data?.data || [];
    } catch (err) {
      if (useMockFallback) {
        let results = [...MOCK_ASSIGNMENTS];
        if (classId) results = results.filter((a) => a.class_id === classId);
        if (subjectId) results = results.filter((a) => a.subject_id === subjectId);
        return results;
      }
      throw err;
    }
  },

  async getAssignmentDetail(id: string): Promise<Assignment> {
    try {
      const response = await apiClient.get(`/me/teacher/assignments/${id}`);
      if (response.data?.data) {
        return response.data.data;
      }
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      const found = MOCK_ASSIGNMENTS.find((a) => a.id === id);
      if (found) return found;
      return MOCK_ASSIGNMENTS[0];
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
    try {
      const response = await apiClient.post('/me/teacher/assignments', payload);
      return response.data?.data;
    } catch (err) {
      if (useMockFallback) {
        const clsId = payload.classId || payload.class_id || 'cls-9a';
        const sbjId = payload.subjectId || payload.subject_id || 'sbj-inf';
        const newAssignment: Assignment = {
          id: `asg-${Date.now()}`,
          class_id: clsId,
          subject_id: sbjId,
          teacher_id: 'tch-001',
          title: payload.title,
          description: payload.description || '',
          due_at: payload.dueDate || payload.due_at || new Date().toISOString(),
          max_score: payload.maxScore || payload.max_score || 100,
          grade_component_name: payload.gradeComponentName || payload.grade_component_name || 'Formatif',
          is_locked: Boolean(payload.lockAfterDue || payload.is_locked),
          allow_late: payload.allowLate !== undefined ? payload.allowLate : true,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
          submissions_count: 0,
          total_students: 32,
          reviewed_count: 0,
          class: MOCK_CLASSES.find((c) => c.id === clsId) || MOCK_CLASSES[0],
          subject: MOCK_SUBJECTS.find((s) => s.id === sbjId) || MOCK_SUBJECTS[0],
          teacher: CURRENT_TEACHER,
        };
        MOCK_ASSIGNMENTS.unshift(newAssignment);
        return newAssignment;
      }
      throw err;
    }
  },

  async getSubmissions(assignmentId: string): Promise<AssignmentSubmission[]> {
    try {
      const response = await apiClient.get(`/me/teacher/assignments/${assignmentId}/submissions`);
      if (response.data?.data && Array.isArray(response.data.data)) {
        return response.data.data;
      }
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return MOCK_SUBMISSIONS[assignmentId] || MOCK_SUBMISSIONS['asg-001'] || [];
    }
    return [];
  },

  async gradeSubmission(
    assignmentId: string,
    submissionId: string,
    payload: { score: number; feedback?: string }
  ): Promise<AssignmentSubmission> {
    try {
      const response = await apiClient.post(`/me/teacher/submissions/${submissionId}/grade`, payload);
      return response.data?.data;
    } catch (err) {
      if (useMockFallback) {
        const list = MOCK_SUBMISSIONS[assignmentId] || MOCK_SUBMISSIONS['asg-001'] || [];
        const item = list.find((s) => s.id === submissionId);
        if (item) {
          item.score = payload.score;
          item.feedback = payload.feedback || '';
          item.graded_at = new Date().toISOString();
          return item;
        }
      }
      throw err;
    }
  },

  // ─── 3. Jadwal & Jurnal Mengajar (BE-ACD-01, BE-ACD-02) ───
  async getSchedules(): Promise<TeachingSchedule[]> {
    try {
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
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return MOCK_SCHEDULES;
    }
    return [];
  },

  async getJournals(classId?: string, subjectId?: string): Promise<TeachingJournal[]> {
    try {
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
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return MOCK_JOURNALS;
    }
    return [];
  },

  async getStudentRoster(classId?: string): Promise<StudentRosterItem[]> {
    if (!classId) return [];
    try {
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
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return (classId && MOCK_ROSTERS_BY_CLASS[classId]) || MOCK_ROSTERS_BY_CLASS['cls-7a'] || MOCK_STUDENTS_ROSTER;
    }
    return [];
  },

  async createJournalWithAttendance(payload: {
    schedule_id: string;
    session_date: string;
    topic: string;
    method: string;
    notes?: string;
    attendances: { student_id: string; status: AttendanceStatus; note?: string }[];
  }): Promise<TeachingJournal> {
    try {
      await apiClient.post('/me/teacher/attendance/sessions', {
        schedule_id: payload.schedule_id,
        session_date: payload.session_date,
        session_topic: payload.topic,
        attendees: payload.attendances.map((a) => ({
          student_id: a.student_id,
          status: a.status === 'hadir' ? 'present' : a.status,
          notes: a.note,
        })),
      });
    } catch {
      // Fallback gracefully
    }

    const hadirCount = payload.attendances.filter((a) => a.status === 'hadir').length;
    const sakitCount = payload.attendances.filter((a) => a.status === 'sakit').length;
    const izinCount = payload.attendances.filter((a) => a.status === 'izin').length;
    const alpaCount = payload.attendances.filter((a) => a.status === 'alpa').length;
    const terlambatCount = payload.attendances.filter((a) => a.status === 'terlambat').length;
    const total = payload.attendances.length || 32;

    const newJournal: TeachingJournal = {
      id: `jrn-${Date.now()}`,
      schedule_id: payload.schedule_id,
      teacher_id: 'tch-001',
      class_id: 'cls-9a',
      subject_id: 'sbj-inf',
      meeting_number: MOCK_JOURNALS.length + 1,
      session_date: payload.session_date,
      time_range: '07:30 - 09:00 WIB',
      room_name: 'Lab Komputer 1',
      topic: payload.topic,
      method: payload.method,
      notes: payload.notes,
      attendance_summary: {
        total,
        hadir: hadirCount,
        sakit: sakitCount,
        izin: izinCount,
        alpa: alpaCount,
        terlambat: terlambatCount,
        attendance_rate: Math.round(((hadirCount + terlambatCount) / (total || 1)) * 100),
      },
      class: MOCK_CLASSES[0],
      subject: MOCK_SUBJECTS[0],
      created_at: new Date().toISOString(),
    };
    MOCK_JOURNALS.unshift(newJournal);
    return newJournal;
  },

  // ─── 4. Buku Nilai Ledger KKTP (BE-GRD-01) ───
  async getGradeComponents(classId?: string, subjectId?: string): Promise<GradeComponent[]> {
    try {
      const response = await apiClient.get('/me/teacher/grade-components', {
        params: { class_id: classId, subject_id: subjectId },
      });
      if (response.data?.data && Array.isArray(response.data.data)) {
        return response.data.data;
      }
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return MOCK_GRADE_COMPONENTS_DETAILED;
    }
    return [];
  },

  async getGradebook(classId: string, subjectId: string): Promise<{
    period: GradebookPeriod;
    components: GradeComponent[];
    grades: StudentGradeRow[];
  }> {
    try {
      const response = await apiClient.get('/me/teacher/grades', {
        params: { class_id: classId, subject_id: subjectId },
      });
      if (response.data?.data?.grades) {
        return response.data.data;
      }
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return {
        period: {
          ...MOCK_GRADEBOOK_PERIOD,
          class_id: classId,
          subject_id: subjectId,
        },
        components: MOCK_GRADE_COMPONENTS_DETAILED,
        grades: (classId && MOCK_GRADES_BY_CLASS[classId]) || MOCK_GRADES_BY_CLASS['cls-7a'] || MOCK_STUDENT_GRADES,
      };
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
    try {
      const response = await apiClient.post('/me/teacher/grades/bulk', payload);
      return response.data;
    } catch (err) {
      if (useMockFallback) {
        payload.grades.forEach((g) => {
          const row = MOCK_STUDENT_GRADES.find((s) => s.student_id === g.student_id);
          if (row) {
            row.scores = { ...row.scores, ...g.scores };
            const scoreValues = Object.values(row.scores).filter((v): v is number => v !== null);
            if (scoreValues.length > 0) {
              const sum = scoreValues.reduce((a, b) => a + b, 0);
              row.final_score = Math.round((sum / scoreValues.length) * 10) / 10;
              row.is_tuntas = row.final_score >= 75;
            }
          }
        });
        return { success: true, updated_count: payload.grades.length };
      }
      throw err;
    }
  },

  async lockGradePeriod(periodId: string, lock: boolean): Promise<GradebookPeriod> {
    try {
      const response = await apiClient.patch(`/grade-periods/${periodId}/lock`, { is_locked: lock });
      return response.data?.data;
    } catch (err) {
      if (useMockFallback) {
        MOCK_GRADEBOOK_PERIOD.is_locked = lock;
        MOCK_GRADEBOOK_PERIOD.locked_at = lock ? new Date().toISOString() : null;
        MOCK_GRADEBOOK_PERIOD.locked_by = lock ? CURRENT_TEACHER.name : null;
        return { ...MOCK_GRADEBOOK_PERIOD };
      }
      throw err;
    }
  },

  // ─── 5. Bank Soal & Ujian CBT (BE-CBT-01, BE-CBT-02) ───
  async getCBTQuestions(subjectId?: string): Promise<CBTQuestion[]> {
    try {
      const response = await apiClient.get('/questions', { params: { subject_id: subjectId } });
      if (response.data?.data && Array.isArray(response.data.data)) {
        return response.data.data;
      }
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return MOCK_CBT_QUESTIONS;
    }
    return [];
  },

  async getCBTExams(classId?: string, subjectId?: string): Promise<CBTExam[]> {
    try {
      const response = await apiClient.get('/me/teacher/exams', {
        params: { class_id: classId, subject_id: subjectId },
      });
      if (response.data?.data && Array.isArray(response.data.data)) {
        return response.data.data;
      }
    } catch {
      // Fallback
    }
    if (useMockFallback) {
      return MOCK_CBT_EXAMS;
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
