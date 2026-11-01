import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  ExamItem,
  QuestionBankItem,
  QuestionItem,
  ExamRoomItem,
  ExamMonitorSessionItem,
  ExamResultItem,
  ItemAnalysisItem,
  StudentSessionData,
  StudentSessionQuestionItem,
} from '@/types/cbt';

export const cbtApi = {
  // =========================================================================
  // 1. Bank Soal (BE-CBT-01 / ADM-CBT-01)
  // =========================================================================
  getQuestionBanks: async (params?: {
    subject_id?: string;
    grade_level?: number | string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<QuestionBankItem>> => {
    const res = await apiClient.get<PaginatedResponse<QuestionBankItem>>('/question-banks', { params });
    return res.data;
  },

  getQuestionBank: async (id: string): Promise<ApiResponse<QuestionBankItem>> => {
    const res = await apiClient.get<ApiResponse<QuestionBankItem>>(`/question-banks/${id}`);
    return res.data;
  },

  createQuestionBank: async (data: {
    subject_id: string;
    title: string;
    code: string;
    grade_level?: number | string;
  }): Promise<ApiResponse<QuestionBankItem>> => {
    const res = await apiClient.post<ApiResponse<QuestionBankItem>>('/question-banks', data);
    return res.data;
  },

  updateQuestionBank: async (
    id: string,
    data: Partial<{
      subject_id: string;
      title: string;
      code: string;
      grade_level?: number | string;
    }>
  ): Promise<ApiResponse<QuestionBankItem>> => {
    const res = await apiClient.put<ApiResponse<QuestionBankItem>>(`/question-banks/${id}`, data);
    return res.data;
  },

  deleteQuestionBank: async (id: string): Promise<ApiResponse<null>> => {
    const res = await apiClient.delete<ApiResponse<null>>(`/question-banks/${id}`);
    return res.data;
  },

  // =========================================================================
  // 2. Butir Soal (BE-CBT-01 / ADM-CBT-01)
  // =========================================================================
  getQuestions: async (params?: {
    bank_id?: string;
    question_type?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<QuestionItem>> => {
    const res = await apiClient.get<PaginatedResponse<QuestionItem>>('/questions', { params });
    return res.data;
  },

  getQuestion: async (id: string): Promise<ApiResponse<QuestionItem>> => {
    const res = await apiClient.get<ApiResponse<QuestionItem>>(`/questions/${id}`);
    return res.data;
  },

  createQuestion: async (data: {
    bank_id: string;
    question_type: string;
    stem: string;
    media_url?: string;
    weight?: number;
    options?: Array<{
      label: string;
      text: string;
      is_correct?: boolean;
      match_key?: string | null;
    }>;
  }): Promise<ApiResponse<QuestionItem>> => {
    const res = await apiClient.post<ApiResponse<QuestionItem>>('/questions', data);
    return res.data;
  },

  updateQuestion: async (
    id: string,
    data: {
      question_type?: string;
      stem?: string;
      media_url?: string;
      weight?: number;
      options?: Array<{
        id?: string;
        label: string;
        text: string;
        is_correct?: boolean;
        match_key?: string | null;
      }>;
    }
  ): Promise<ApiResponse<QuestionItem>> => {
    const res = await apiClient.put<ApiResponse<QuestionItem>>(`/questions/${id}`, data);
    return res.data;
  },

  deleteQuestion: async (id: string): Promise<ApiResponse<null>> => {
    const res = await apiClient.delete<ApiResponse<null>>(`/questions/${id}`);
    return res.data;
  },

  // =========================================================================
  // 3. Manajemen Ujian (BE-CBT-02, BE-CBT-03 / ADM-CBT-02, ADM-CBT-03)
  // =========================================================================
  getExams: async (params?: {
    subject_id?: string;
    exam_type?: string;
    status?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<ExamItem>> => {
    const res = await apiClient.get<PaginatedResponse<ExamItem>>('/exams', { params });
    return res.data;
  },

  getExam: async (id: string): Promise<ApiResponse<ExamItem>> => {
    const res = await apiClient.get<ApiResponse<ExamItem>>(`/exams/${id}`);
    return res.data;
  },

  createExam: async (data: {
    subject_id: string;
    title: string;
    exam_type: string;
    duration_min: number;
    start_at: string;
    end_at: string;
    passing_grade?: number;
    shuffle_questions?: boolean;
    shuffle_options?: boolean;
    package_mode?: string;
    show_result_mode?: string;
  }): Promise<ApiResponse<ExamItem>> => {
    const res = await apiClient.post<ApiResponse<ExamItem>>('/exams', data);
    return res.data;
  },

  updateExam: async (id: string, data: Partial<ExamItem>): Promise<ApiResponse<ExamItem>> => {
    const res = await apiClient.put<ApiResponse<ExamItem>>(`/exams/${id}`, data);
    return res.data;
  },

  deleteExam: async (id: string): Promise<ApiResponse<null>> => {
    const res = await apiClient.delete<ApiResponse<null>>(`/exams/${id}`);
    return res.data;
  },

  updateExamStatus: async (id: string, status: string): Promise<ApiResponse<ExamItem>> => {
    const res = await apiClient.patch<ApiResponse<ExamItem>>(`/exams/${id}/status`, { status });
    return res.data;
  },

  assignExamQuestions: async (
    id: string,
    payload: {
      question_ids: string[];
      weight_overrides?: Record<string, number>;
    }
  ): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exams/${id}/questions`, payload);
    return res.data;
  },

  assignExamRooms: async (
    id: string,
    payload: {
      rooms: Array<{
        room_id: string;
        proctor_name?: string;
      }>;
    }
  ): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exams/${id}/rooms`, payload);
    return res.data;
  },

  enrollExamParticipants: async (
    id: string,
    payload: {
      student_ids?: string[];
      class_ids?: string[];
    }
  ): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exams/${id}/participants`, payload);
    return res.data;
  },

  regenerateExamRoomToken: async (roomId: string): Promise<ApiResponse<ExamRoomItem>> => {
    const res = await apiClient.post<ApiResponse<ExamRoomItem>>(`/exam-rooms/${roomId}/regenerate-token`);
    return res.data;
  },

  // =========================================================================
  // 4. Monitoring Real-Time & Proctor Actions (BE-CBT-06, BE-CBT-04, BE-CBT-12)
  // =========================================================================
  getExamMonitor: async (
    id: string,
    params?: { room_id?: string; status?: string }
  ): Promise<
    ApiResponse<{
      exam_id: string;
      total_participants: number;
      active_count: number;
      submitted_count: number;
      blocked_count: number;
      sessions: ExamMonitorSessionItem[];
    }>
  > => {
    const res = await apiClient.get<
      ApiResponse<{
        exam_id: string;
        total_participants: number;
        active_count: number;
        submitted_count: number;
        blocked_count: number;
        sessions: ExamMonitorSessionItem[];
      }>
    >(`/exams/${id}/monitor`, { params });
    return res.data;
  },

  manageSession: async (
    sessionId: string,
    action: 'unlock' | 'force_submit' | 'extra_time' | 'reset',
    extraMinutes?: number,
    reason?: string
  ): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exam-sessions/${sessionId}/manage`, {
      action,
      extra_minutes: extraMinutes,
      reason,
    });
    return res.data;
  },

  // =========================================================================
  // 5. Penilaian & Integrasi Buku Nilai (BE-CBT-05, BE-CBT-09 / ADM-CBT-05, ADM-CBT-06, ADM-CBT-07)
  // =========================================================================
  runAutoGrade: async (id: string, sessionId?: string): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exams/${id}/auto-grade`, {
      session_id: sessionId,
    });
    return res.data;
  },

  getExamResults: async (id: string, filters?: { status?: string }): Promise<ApiResponse<ExamResultItem[]>> => {
    const res = await apiClient.get<ApiResponse<ExamResultItem[]>>(`/exams/${id}/results`, { params: filters });
    return res.data;
  },

  getItemAnalysis: async (id: string): Promise<ApiResponse<ItemAnalysisItem[]>> => {
    const res = await apiClient.get<ApiResponse<ItemAnalysisItem[]>>(`/exams/${id}/item-analysis`);
    return res.data;
  },

  createRemedialExam: async (
    id: string,
    payload: {
      title?: string;
      passing_grade?: number;
      max_remedial_score?: number;
      target_student_ids?: string[];
      start_at: string;
      end_at: string;
      duration_min?: number;
    }
  ): Promise<ApiResponse<ExamItem>> => {
    const res = await apiClient.post<ApiResponse<ExamItem>>(`/exams/${id}/remedial`, payload);
    return res.data;
  },

  publishToGradebook: async (
    id: string,
    payload: {
      component_id: string;
      period_id: string;
      overwrite_locked?: boolean;
    }
  ): Promise<ApiResponse<{ grades_synced: number; skipped_locked: number; message: string }>> => {
    const res = await apiClient.post<ApiResponse<{ grades_synced: number; skipped_locked: number; message: string }>>(
      `/exams/${id}/publish-gradebook`,
      payload
    );
    return res.data;
  },

  // =========================================================================
  // 6. Siswa / Client Workspace Player (BE-CBT-04 / SIS-CBT-01)
  // =========================================================================
  startStudentSession: async (
    id: string,
    payload: { token: string; device_fingerprint?: string }
  ): Promise<ApiResponse<StudentSessionData>> => {
    const res = await apiClient.post<ApiResponse<StudentSessionData>>(`/exams/${id}/start-session`, payload);
    return res.data;
  },

  getStudentSessionQuestions: async (id: string): Promise<ApiResponse<StudentSessionQuestionItem[]>> => {
    const res = await apiClient.get<ApiResponse<StudentSessionQuestionItem[]>>(`/exams/${id}/session-questions`);
    return res.data;
  },

  saveStudentAnswer: async (
    id: string,
    payload: {
      question_id: string;
      answer: any;
      is_flagged?: boolean;
    }
  ): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exams/${id}/save-answer`, payload);
    return res.data;
  },

  logStudentViolation: async (
    id: string,
    payload: {
      violation_type: string;
      details?: any;
    }
  ): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exams/${id}/log-violation`, payload);
    return res.data;
  },

  sendStudentHeartbeat: async (
    sessionId: string,
    payload?: { client_timestamp?: string }
  ): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exam-sessions/${sessionId}/heartbeat`, payload || {});
    return res.data;
  },

  finishStudentSession: async (id: string): Promise<ApiResponse<any>> => {
    const res = await apiClient.post<ApiResponse<any>>(`/exams/${id}/finish-session`);
    return res.data;
  },

  getStudentExamReview: async (id: string): Promise<ApiResponse<any>> => {
    const res = await apiClient.get<ApiResponse<any>>(`/exams/${id}/review`);
    return res.data;
  },
};
