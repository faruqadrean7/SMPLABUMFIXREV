import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  ScheduleItem,
  ScheduleFormValues,
  ScheduleFilters,
  CheckConflictPayload,
  CheckConflictResult,
  TimeSlotItem,
  TimeSlotFormValues,
  RoomItem,
  JournalItem,
  JournalFormValues,
  JournalFilters,
  AttendanceItem,
  AttendanceRecap,
  AttendanceFilters,
  BatchSessionAttendancePayload,
  DailyGateAttendancePayload,
  LeaveRequestItem,
  LeaveRequestFilters,
  StoreLeaveRequestPayload,
  DecideLeaveRequestPayload,
  StaffAttendanceItem,
  StaffAttendanceSummary,
  StaffAttendanceFilters,
  CheckInStaffPayload,
  CheckOutStaffPayload,
  ManualStaffAttendancePayload,
  CorrectStaffAttendancePayload,
  StaffAttendanceSettings,
  TeachingMaterialItem,
  TeachingMaterialFilters,
  StoreTeachingMaterialPayload,
  CreateTeachingMaterialVersionPayload,
  GradeLevelItem,
  LearningMaterialItem,
  LearningMaterialFilters,
  StoreLearningMaterialPayload,
  UpdateLearningMaterialPayload,
  AssignmentItem,
  AssignmentFilters,
  StoreAssignmentPayload,
  UpdateAssignmentPayload,
  AssignmentSubmissionItem,
  SubmitAssignmentPayload,
  GradeSubmissionPayload,
  GradeComponentItem,
  GradePeriodItem,
  GradeItem,
  ClassGradeMatrix,
  CreateGradeComponentPayload,
  UpdateGradeComponentPayload,
  StoreGradePayload,
  BulkStoreGradePayload,
  LockPeriodPayload,
  GradeFilters,
  GradeMatrixFilters,
  CharacterNoteItem,
  ClassCharacterNotesMatrix,
  StoreCharacterNotePayload,
  BulkStoreCharacterNotePayload,
  CharacterNoteFilters,
  ClassCharacterNotesFilters,
  ProgressReportItem,
  ProgressReportSnapshot,
  ClassProgressReportsStatus,
  GenerateProgressReportPayload,
  ApproveProgressReportPayload,
  BulkApproveProgressReportPayload,
  ProgressReportFilters,
  ReportCardItem,
  ReportCardSnapshot,
  ClassReportCardsStatus,
  GenerateReportCardPayload,
  ApproveReportCardPayload,
  LockReportCardPayload,
  BulkApproveReportCardPayload,
  BulkLockReportCardPayload,
  ReportCardFilters,
  ReportDescriptionTemplateItem,
  ReportDescriptionTemplateFilters,
  StoreReportDescriptionTemplatePayload,
} from '@/types/academic';
import { SemesterItem } from '@/types/master';

export const academicApi = {
  // =========================================================================
  // SCHEDULES (BE-ACD-01 / ADM-ACD-01)
  // =========================================================================

  /**
   * Mengambil daftar jadwal pelajaran dengan berbagai filter (kelas, guru, hari, semester)
   * GET /api/v1/schedules
   */
  async listSchedules(filters?: ScheduleFilters): Promise<ScheduleItem[]> {
    const res = await apiClient.get<ApiResponse<ScheduleItem[]> | PaginatedResponse<ScheduleItem>>(
      '/schedules',
      {
        params: {
          ...filters,
          all: filters?.all ?? true, // default all untuk rendering matrix timetable
        },
      }
    );

    // Menangani baik paginated response ({ data: [...], meta: {...} }) maupun standard ({ data: [...] })
    const payload = res.data;
    if (Array.isArray(payload)) {
      return payload;
    }
    if (payload && Array.isArray((payload as ApiResponse<ScheduleItem[]>).data)) {
      return (payload as ApiResponse<ScheduleItem[]>).data;
    }
    if (payload && 'data' in payload && Array.isArray((payload as any).data)) {
      return (payload as any).data;
    }
    return [];
  },

  /**
   * Mengambil detail jadwal pelajaran tunggal
   * GET /api/v1/schedules/{id}
   */
  async getSchedule(id: string): Promise<ScheduleItem> {
    const res = await apiClient.get<ApiResponse<ScheduleItem>>(`/schedules/${id}`);
    return res.data.data;
  },

  /**
   * Memeriksa bentrok jadwal (kelas, guru, ruangan) secara real-time
   * POST /api/v1/schedules/conflicts/check
   */
  async checkConflicts(payload: CheckConflictPayload): Promise<CheckConflictResult> {
    const res = await apiClient.post<ApiResponse<CheckConflictResult>>(
      '/schedules/conflicts/check',
      payload
    );
    return res.data.data;
  },

  /**
   * Membuat jadwal pelajaran baru
   * POST /api/v1/schedules
   */
  async createSchedule(payload: ScheduleFormValues): Promise<ScheduleItem> {
    const res = await apiClient.post<ApiResponse<ScheduleItem>>('/schedules', payload);
    return res.data.data;
  },

  /**
   * Memperbarui jadwal pelajaran
   * PUT /api/v1/schedules/{id}
   */
  async updateSchedule(id: string, payload: Partial<ScheduleFormValues>): Promise<ScheduleItem> {
    const res = await apiClient.put<ApiResponse<ScheduleItem>>(`/schedules/${id}`, payload);
    return res.data.data;
  },

  /**
   * Menghapus jadwal pelajaran (selama belum ada jurnal mengajar)
   * DELETE /api/v1/schedules/{id}
   */
  async deleteSchedule(id: string): Promise<void> {
    await apiClient.delete(`/schedules/${id}`);
  },

  // =========================================================================
  // TIME SLOTS (Jam Pelajaran)
  // =========================================================================

  /**
   * Mengambil daftar slot jam pelajaran terurut
   * GET /api/v1/time-slots
   */
  async listTimeSlots(dayType?: string): Promise<TimeSlotItem[]> {
    const res = await apiClient.get<ApiResponse<TimeSlotItem[]>>('/time-slots', {
      params: dayType ? { day_type: dayType } : undefined,
    });
    return res.data.data;
  },

  /**
   * Menambahkan slot jam pelajaran baru
   * POST /api/v1/time-slots
   */
  async createTimeSlot(payload: TimeSlotFormValues): Promise<TimeSlotItem> {
    const res = await apiClient.post<ApiResponse<TimeSlotItem>>('/time-slots', payload);
    return res.data.data;
  },

  /**
   * Menghapus slot jam pelajaran
   * DELETE /api/v1/time-slots/{id}
   */
  async deleteTimeSlot(id: string): Promise<void> {
    await apiClient.delete(`/time-slots/${id}`);
  },

  // =========================================================================
  // MASTER DATA LOOKUPS (Semesters, Rooms)
  // =========================================================================

  /**
   * Mengambil daftar semester aktif/terdaftar
   * GET /api/v1/semesters
   */
  async listSemesters(academicYearId?: string): Promise<SemesterItem[]> {
    const res = await apiClient.get<ApiResponse<SemesterItem[]>>('/semesters', {
      params: academicYearId ? { academic_year_id: academicYearId } : undefined,
    });
    return res.data.data;
  },

  /**
   * Mengambil daftar ruangan sekolah (opsional untuk slot jadwal)
   * GET /api/v1/rooms
   */
  async listRooms(): Promise<RoomItem[]> {
    try {
      const res = await apiClient.get<ApiResponse<RoomItem[]> | PaginatedResponse<RoomItem>>(
        '/rooms'
      );
      const payload = res.data;
      if (Array.isArray(payload)) return payload;
      if (payload && Array.isArray((payload as ApiResponse<RoomItem[]>).data)) {
        return (payload as ApiResponse<RoomItem[]>).data;
      }
      return [];
    } catch {
      // Jika role guru tidak memiliki permission master.room.manage, kembalikan array kosong dengan aman
      return [];
    }
  },

  // =========================================================================
  // JOURNALS (BE-ACD-02 / ADM-ACD-02: Jurnal Mengajar Guru)
  // =========================================================================

  /**
   * Mengambil daftar jurnal mengajar dengan filter (tanggal, jadwal, kelas, guru)
   * GET /api/v1/journals
   */
  async listJournals(filters?: JournalFilters): Promise<PaginatedResponse<JournalItem>> {
    const res = await apiClient.get<PaginatedResponse<JournalItem>>('/journals', {
      params: filters,
    });
    return res.data;
  },

  /**
   * Mengambil detail jurnal mengajar lengkap dengan jadwal dan presensi
   * GET /api/v1/journals/{id}
   */
  async getJournal(id: string): Promise<JournalItem> {
    const res = await apiClient.get<ApiResponse<JournalItem>>(`/journals/${id}`);
    return res.data.data;
  },

  /**
   * Membuat catatan jurnal mengajar baru
   * POST /api/v1/journals
   */
  async createJournal(payload: JournalFormValues): Promise<JournalItem> {
    const res = await apiClient.post<ApiResponse<JournalItem>>('/journals', payload);
    return res.data.data;
  },

  /**
   * Memperbarui catatan jurnal mengajar (dibatasi N hari, default 3 hari)
   * PUT /api/v1/journals/{id}
   */
  async updateJournal(
    id: string,
    payload: Partial<JournalFormValues>
  ): Promise<JournalItem> {
    const res = await apiClient.put<ApiResponse<JournalItem>>(`/journals/${id}`, payload);
    return res.data.data;
  },

  /**
   * Menghapus catatan jurnal mengajar
   * DELETE /api/v1/journals/{id}
   */
  async deleteJournal(id: string): Promise<void> {
    await apiClient.delete(`/journals/${id}`);
  },

  // =========================================================================
  // ATTENDANCES (BE-ACD-03 / ADM-ACD-03: Presensi KBM & Sesi Kelas)
  // =========================================================================

  /**
   * Mengambil riwayat catatan presensi siswa
   * GET /api/v1/attendances
   */
  async listAttendances(filters?: AttendanceFilters): Promise<PaginatedResponse<AttendanceItem>> {
    const res = await apiClient.get<PaginatedResponse<AttendanceItem>>('/attendances', {
      params: filters,
    });
    return res.data;
  },

  /**
   * Mengambil statistik rekapitulasi kehadiran
   * GET /api/v1/attendances/recap
   */
  async getAttendanceRecap(filters?: {
    class_id?: string;
    student_id?: string;
    journal_id?: string;
    start_date?: string;
    end_date?: string;
  }): Promise<AttendanceRecap> {
    const res = await apiClient.get<ApiResponse<AttendanceRecap>>('/attendances/recap', {
      params: filters,
    });
    return res.data.data;
  },

  /**
   * Batch submit presensi sesi kelas dari jurnal mengajar
   * POST /api/v1/attendances
   */
  async recordSessionAttendance(payload: BatchSessionAttendancePayload): Promise<{
    journal_id: string;
    session_date: string;
    total_recorded: number;
    attendances: AttendanceItem[];
  }> {
    const res = await apiClient.post<
      ApiResponse<{
        journal_id: string;
        session_date: string;
        total_recorded: number;
        attendances: AttendanceItem[];
      }>
    >('/attendances', payload);
    return res.data.data;
  },

  /**
   * Mencatat presensi gerbang harian
   * POST /api/v1/attendances/daily
   */
  async recordDailyAttendance(payload: DailyGateAttendancePayload): Promise<AttendanceItem> {
    const res = await apiClient.post<ApiResponse<AttendanceItem>>('/attendances/daily', payload);
    return res.data.data;
  },

  // =========================================================================
  // LEAVE REQUESTS (BE-ACD-04 / ADM-ACD-04: Izin & Sakit Siswa)
  // =========================================================================

  /**
   * Mengambil daftar permohonan izin/sakit dengan filter
   * GET /api/v1/leave-requests
   */
  async listLeaveRequests(filters?: LeaveRequestFilters): Promise<PaginatedResponse<LeaveRequestItem>> {
    const res = await apiClient.get<PaginatedResponse<LeaveRequestItem>>('/leave-requests', {
      params: filters,
    });
    return res.data;
  },

  /**
   * Mengambil detail permohonan izin/sakit siswa
   * GET /api/v1/leave-requests/{id}
   */
  async getLeaveRequest(id: string): Promise<LeaveRequestItem> {
    const res = await apiClient.get<ApiResponse<LeaveRequestItem>>(`/leave-requests/${id}`);
    return res.data.data;
  },

  /**
   * Mengajukan permohonan izin/sakit siswa (Wali Murid / Siswa)
   * POST /api/v1/leave-requests
   */
  async createLeaveRequest(payload: StoreLeaveRequestPayload): Promise<LeaveRequestItem> {
    if (payload.attachment_file) {
      const formData = new FormData();
      formData.append('student_id', payload.student_id);
      formData.append('leave_type', payload.leave_type);
      formData.append('start_date', payload.start_date);
      formData.append('end_date', payload.end_date);
      formData.append('reason', payload.reason);
      formData.append('attachment_file', payload.attachment_file);

      const res = await apiClient.post<ApiResponse<LeaveRequestItem>>('/leave-requests', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return res.data.data;
    }

    const res = await apiClient.post<ApiResponse<LeaveRequestItem>>('/leave-requests', {
      student_id: payload.student_id,
      leave_type: payload.leave_type,
      start_date: payload.start_date,
      end_date: payload.end_date,
      reason: payload.reason,
      attachment_file_id: payload.attachment_file_id,
    });
    return res.data.data;
  },

  /**
   * Memutuskan status permohonan izin siswa (Wali Kelas / Admin)
   * PATCH /api/v1/leave-requests/{id}/decision
   */
  async decideLeaveRequest(id: string, payload: DecideLeaveRequestPayload): Promise<LeaveRequestItem> {
    const res = await apiClient.patch<ApiResponse<LeaveRequestItem>>(
      `/leave-requests/${id}/decision`,
      payload
    );
    return res.data.data;
  },

  /**
   * Mengambil URL berkas lampiran (signed URL jika privat)
   * GET /api/v1/files/{id}/url
   */
  async getFileUrl(id: string): Promise<{ url: string; expires_at?: string }> {
    const res = await apiClient.get<ApiResponse<{ url: string; expires_at?: string }>>(
      `/files/${id}/url`
    );
    return res.data.data;
  },

  // =========================================================================
  // STAFF ATTENDANCES (BE-ACD-05 / ADM-ACD-05: Presensi Guru & Staf)
  // =========================================================================

  /**
   * Cek status presensi hari ini untuk pegawai yang login
   * GET /api/v1/staff-attendances/today
   */
  async getStaffToday(): Promise<StaffAttendanceItem | null> {
    const res = await apiClient.get<ApiResponse<StaffAttendanceItem | null>>(
      '/staff-attendances/today'
    );
    return res.data.data;
  },

  /**
   * Check-in presensi staf mandiri (GPS & foto selfie opsional)
   * POST /api/v1/staff-attendances/check-in
   */
  async checkInStaff(payload: CheckInStaffPayload): Promise<StaffAttendanceItem> {
    if (payload.photo_file) {
      const formData = new FormData();
      if (payload.lat !== undefined) formData.append('lat', String(payload.lat));
      if (payload.lng !== undefined) formData.append('lng', String(payload.lng));
      if (payload.notes) formData.append('notes', payload.notes);
      formData.append('photo_file', payload.photo_file);

      const res = await apiClient.post<ApiResponse<StaffAttendanceItem>>(
        '/staff-attendances/check-in',
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.post<ApiResponse<StaffAttendanceItem>>(
      '/staff-attendances/check-in',
      payload
    );
    return res.data.data;
  },

  /**
   * Check-out presensi staf mandiri
   * POST /api/v1/staff-attendances/check-out
   */
  async checkOutStaff(payload: CheckOutStaffPayload): Promise<StaffAttendanceItem> {
    const res = await apiClient.post<ApiResponse<StaffAttendanceItem>>(
      '/staff-attendances/check-out',
      payload
    );
    return res.data.data;
  },

  /**
   * Riwayat presensi staf dengan filter dan paginasi
   * GET /api/v1/staff-attendances
   */
  async listStaffAttendances(
    filters?: StaffAttendanceFilters
  ): Promise<PaginatedResponse<StaffAttendanceItem>> {
    const res = await apiClient.get<PaginatedResponse<StaffAttendanceItem>>(
      '/staff-attendances',
      { params: filters }
    );
    return res.data;
  },

  /**
   * Rekapitulasi kehadiran staf untuk dashboard & periode
   * GET /api/v1/staff-attendances/summary
   */
  async getStaffAttendanceSummary(filters?: {
    user_id?: string;
    month?: string;
    date_from?: string;
    date_to?: string;
  }): Promise<StaffAttendanceSummary> {
    const res = await apiClient.get<ApiResponse<StaffAttendanceSummary>>(
      '/staff-attendances/summary',
      { params: filters }
    );
    return res.data.data;
  },

  /**
   * Manual record presensi staf oleh Admin
   * POST /api/v1/staff-attendances
   */
  async manualRecordStaffAttendance(
    payload: ManualStaffAttendancePayload
  ): Promise<StaffAttendanceItem> {
    const res = await apiClient.post<ApiResponse<StaffAttendanceItem>>(
      '/staff-attendances',
      payload
    );
    return res.data.data;
  },

  /**
   * Koreksi data presensi staf oleh Admin berwenang
   * PATCH /api/v1/staff-attendances/{id}
   */
  async correctStaffAttendance(
    id: string,
    payload: CorrectStaffAttendancePayload
  ): Promise<StaffAttendanceItem> {
    const res = await apiClient.patch<ApiResponse<StaffAttendanceItem>>(
      `/staff-attendances/${id}`,
      payload
    );
    return res.data.data;
  },

  /**
   * Mengambil konfigurasi GPS radius dan jam batas keterlambatan
   * GET /api/v1/staff-attendances/settings
   */
  async getStaffAttendanceSettings(): Promise<StaffAttendanceSettings> {
    const res = await apiClient.get<ApiResponse<StaffAttendanceSettings>>(
      '/staff-attendances/settings'
    );
    return res.data.data;
  },

  /**
   * Memperbarui konfigurasi GPS radius dan jam batas keterlambatan
   * PUT /api/v1/staff-attendances/settings
   */
  async updateStaffAttendanceSettings(
    payload: StaffAttendanceSettings
  ): Promise<StaffAttendanceSettings> {
    const res = await apiClient.put<ApiResponse<StaffAttendanceSettings>>(
      '/staff-attendances/settings',
      payload
    );
    return res.data.data;
  },

  // =========================================================================
  // TEACHING MATERIALS (BE-ACD-06 / ADM-ACD-06: Bank Modul Ajar)
  // =========================================================================

  /**
   * Mengambil daftar perangkat ajar (modul, ATP, silabus, P5) berpaginasi
   * GET /api/v1/teaching-materials
   */
  async listTeachingMaterials(
    filters?: TeachingMaterialFilters
  ): Promise<PaginatedResponse<TeachingMaterialItem>> {
    const res = await apiClient.get<PaginatedResponse<TeachingMaterialItem>>(
      '/teaching-materials',
      { params: filters }
    );
    return res.data;
  },

  /**
   * Mengambil detail perangkat ajar lengkap beserta riwayat versi
   * GET /api/v1/teaching-materials/{id}
   */
  async getTeachingMaterial(id: string): Promise<TeachingMaterialItem> {
    const res = await apiClient.get<ApiResponse<TeachingMaterialItem>>(
      `/teaching-materials/${id}`
    );
    return res.data.data;
  },

  /**
   * Mengunggah perangkat ajar baru
   * POST /api/v1/teaching-materials
   */
  async createTeachingMaterial(
    payload: StoreTeachingMaterialPayload
  ): Promise<TeachingMaterialItem> {
    if (payload.file) {
      const formData = new FormData();
      formData.append('title', payload.title);
      if (payload.description) formData.append('description', payload.description);
      formData.append('material_type', payload.material_type);
      if (payload.grade_level_id) formData.append('grade_level_id', payload.grade_level_id);
      if (payload.subject_id) formData.append('subject_id', payload.subject_id);
      if (payload.version) formData.append('version', String(payload.version));
      formData.append('file', payload.file);

      const res = await apiClient.post<ApiResponse<TeachingMaterialItem>>(
        '/teaching-materials',
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.post<ApiResponse<TeachingMaterialItem>>(
      '/teaching-materials',
      payload
    );
    return res.data.data;
  },

  /**
   * Mengunggah revisi versi baru dari perangkat ajar
   * POST /api/v1/teaching-materials/{id}/versions
   */
  async createTeachingMaterialVersion(
    id: string,
    payload: CreateTeachingMaterialVersionPayload
  ): Promise<TeachingMaterialItem> {
    if (payload.file) {
      const formData = new FormData();
      if (payload.title) formData.append('title', payload.title);
      if (payload.description) formData.append('description', payload.description);
      if (payload.version) formData.append('version', String(payload.version));
      formData.append('file', payload.file);

      const res = await apiClient.post<ApiResponse<TeachingMaterialItem>>(
        `/teaching-materials/${id}/versions`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.post<ApiResponse<TeachingMaterialItem>>(
      `/teaching-materials/${id}/versions`,
      payload
    );
    return res.data.data;
  },

  /**
   * Menghapus perangkat ajar (hanya pemilik atau admin)
   * DELETE /api/v1/teaching-materials/{id}
   */
  async deleteTeachingMaterial(id: string): Promise<void> {
    await apiClient.delete(`/teaching-materials/${id}`);
  },

  /**
   * Mengambil daftar tingkat kelas (Grade Levels: 7, 8, 9)
   * GET /api/v1/grade-levels
   */
  async getGradeLevels(): Promise<GradeLevelItem[]> {
    const res = await apiClient.get<ApiResponse<GradeLevelItem[]>>('/grade-levels');
    return res.data.data;
  },

  // =========================================================================
  // LMS RINGAN: TUGAS & MATERI (BE-ACD-07 / ADM-ACD-07)
  // =========================================================================

  /**
   * Mengambil daftar materi pembelajaran berpaginasi
   * GET /api/v1/materials
   */
  async listMaterials(
    filters?: LearningMaterialFilters
  ): Promise<PaginatedResponse<LearningMaterialItem>> {
    const res = await apiClient.get<PaginatedResponse<LearningMaterialItem>>(
      '/materials',
      { params: filters }
    );
    return res.data;
  },

  /**
   * Mengambil detail materi pembelajaran
   * GET /api/v1/materials/{id}
   */
  async getMaterial(id: string): Promise<LearningMaterialItem> {
    const res = await apiClient.get<ApiResponse<LearningMaterialItem>>(`/materials/${id}`);
    return res.data.data;
  },

  /**
   * Mengunggah materi pembelajaran baru
   * POST /api/v1/materials
   */
  async createMaterial(
    payload: StoreLearningMaterialPayload
  ): Promise<LearningMaterialItem> {
    if (payload.file) {
      const formData = new FormData();
      formData.append('class_id', payload.class_id);
      formData.append('subject_id', payload.subject_id);
      formData.append('title', payload.title);
      if (payload.description) formData.append('description', payload.description);
      formData.append('file', payload.file);

      const res = await apiClient.post<ApiResponse<LearningMaterialItem>>(
        '/materials',
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.post<ApiResponse<LearningMaterialItem>>('/materials', payload);
    return res.data.data;
  },

  /**
   * Memperbarui materi pembelajaran
   * PUT /api/v1/materials/{id}
   */
  async updateMaterial(
    id: string,
    payload: UpdateLearningMaterialPayload
  ): Promise<LearningMaterialItem> {
    if (payload.file) {
      const formData = new FormData();
      if (payload.title) formData.append('title', payload.title);
      if (payload.description !== undefined) formData.append('description', payload.description || '');
      formData.append('file', payload.file);

      const res = await apiClient.post<ApiResponse<LearningMaterialItem>>(
        `/materials/${id}?_method=PUT`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.put<ApiResponse<LearningMaterialItem>>(`/materials/${id}`, payload);
    return res.data.data;
  },

  /**
   * Menghapus materi pembelajaran
   * DELETE /api/v1/materials/{id}
   */
  async deleteMaterial(id: string): Promise<void> {
    await apiClient.delete(`/materials/${id}`);
  },

  /**
   * Mengambil daftar tugas kelas berpaginasi
   * GET /api/v1/assignments
   */
  async listAssignments(
    filters?: AssignmentFilters
  ): Promise<PaginatedResponse<AssignmentItem>> {
    const res = await apiClient.get<PaginatedResponse<AssignmentItem>>(
      '/assignments',
      { params: filters }
    );
    return res.data;
  },

  /**
   * Mengambil detail tugas kelas
   * GET /api/v1/assignments/{id}
   */
  async getAssignment(id: string): Promise<AssignmentItem> {
    const res = await apiClient.get<ApiResponse<AssignmentItem>>(`/assignments/${id}`);
    return res.data.data;
  },

  /**
   * Membuat tugas kelas baru
   * POST /api/v1/assignments
   */
  async createAssignment(
    payload: StoreAssignmentPayload
  ): Promise<AssignmentItem> {
    if (payload.file) {
      const formData = new FormData();
      formData.append('class_id', payload.class_id);
      formData.append('subject_id', payload.subject_id);
      formData.append('title', payload.title);
      if (payload.description) formData.append('description', payload.description);
      formData.append('due_at', payload.due_at);
      if (payload.max_score !== undefined) formData.append('max_score', String(payload.max_score));
      if (payload.grade_component_id) formData.append('grade_component_id', payload.grade_component_id);
      formData.append('file', payload.file);

      const res = await apiClient.post<ApiResponse<AssignmentItem>>(
        '/assignments',
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.post<ApiResponse<AssignmentItem>>('/assignments', payload);
    return res.data.data;
  },

  /**
   * Memperbarui tugas kelas
   * PUT /api/v1/assignments/{id}
   */
  async updateAssignment(
    id: string,
    payload: UpdateAssignmentPayload
  ): Promise<AssignmentItem> {
    if (payload.file) {
      const formData = new FormData();
      if (payload.title) formData.append('title', payload.title);
      if (payload.description !== undefined) formData.append('description', payload.description || '');
      if (payload.due_at) formData.append('due_at', payload.due_at);
      if (payload.max_score !== undefined) formData.append('max_score', String(payload.max_score));
      if (payload.grade_component_id) formData.append('grade_component_id', payload.grade_component_id);
      formData.append('file', payload.file);

      const res = await apiClient.post<ApiResponse<AssignmentItem>>(
        `/assignments/${id}?_method=PUT`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.put<ApiResponse<AssignmentItem>>(`/assignments/${id}`, payload);
    return res.data.data;
  },

  /**
   * Menghapus tugas kelas
   * DELETE /api/v1/assignments/{id}
   */
  async deleteAssignment(id: string): Promise<void> {
    await apiClient.delete(`/assignments/${id}`);
  },

  /**
   * Mengambil daftar hasil pengumpulan tugas (submission)
   * GET /api/v1/assignments/{id}/submissions
   */
  async listSubmissions(assignmentId: string): Promise<AssignmentSubmissionItem[]> {
    const res = await apiClient.get<ApiResponse<AssignmentSubmissionItem[]>>(
      `/assignments/${assignmentId}/submissions`
    );
    return res.data.data;
  },

  /**
   * Siswa mengumpulkan tugas
   * POST /api/v1/assignments/{id}/submissions
   */
  async submitAssignment(
    assignmentId: string,
    payload: SubmitAssignmentPayload
  ): Promise<AssignmentSubmissionItem> {
    if (payload.file) {
      const formData = new FormData();
      if (payload.notes) formData.append('notes', payload.notes);
      formData.append('file', payload.file);

      const res = await apiClient.post<ApiResponse<AssignmentSubmissionItem>>(
        `/assignments/${assignmentId}/submissions`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return res.data.data;
    }

    const res = await apiClient.post<ApiResponse<AssignmentSubmissionItem>>(
      `/assignments/${assignmentId}/submissions`,
      payload
    );
    return res.data.data;
  },

  /**
   * Guru memberikan nilai dan umpan balik tugas
   * PATCH /api/v1/assignments/{assignmentId}/submissions/{submissionId}/grade
   */
  async gradeSubmission(
    assignmentId: string,
    submissionId: string,
    payload: GradeSubmissionPayload
  ): Promise<AssignmentSubmissionItem> {
    const res = await apiClient.patch<ApiResponse<AssignmentSubmissionItem>>(
      `/assignments/${assignmentId}/submissions/${submissionId}/grade`,
      payload
    );
    return res.data.data;
  },

  // =========================================================================
  // ADM-GRD-01 / BE-GRD-01: Buku Nilai & Komponen Penilaian Bulanan
  // =========================================================================

  /**
   * Mengambil daftar komponen penilaian
   * GET /api/v1/grade-components
   */
  async listGradeComponents(filters?: {
    subject_id?: string;
    semester_id?: string;
    class_id?: string | null;
  }): Promise<GradeComponentItem[]> {
    const res = await apiClient.get<ApiResponse<GradeComponentItem[]>>('/grade-components', {
      params: filters,
    });
    return res.data.data;
  },

  /**
   * Membuat komponen penilaian baru
   * POST /api/v1/grade-components
   */
  async createGradeComponent(payload: CreateGradeComponentPayload): Promise<GradeComponentItem> {
    const res = await apiClient.post<ApiResponse<GradeComponentItem>>('/grade-components', payload);
    return res.data.data;
  },

  /**
   * Memperbarui komponen penilaian
   * PUT /api/v1/grade-components/{id}
   */
  async updateGradeComponent(
    id: string,
    payload: UpdateGradeComponentPayload
  ): Promise<GradeComponentItem> {
    const res = await apiClient.put<ApiResponse<GradeComponentItem>>(
      `/grade-components/${id}`,
      payload
    );
    return res.data.data;
  },

  /**
   * Menghapus komponen penilaian
   * DELETE /api/v1/grade-components/{id}
   */
  async deleteGradeComponent(id: string): Promise<void> {
    await apiClient.delete(`/grade-components/${id}`);
  },

  /**
   * Mengambil daftar periode penilaian bulanan
   * GET /api/v1/grade-periods
   */
  async listGradePeriods(filters?: { semester_id?: string }): Promise<GradePeriodItem[]> {
    const res = await apiClient.get<ApiResponse<GradePeriodItem[]>>('/grade-periods', {
      params: filters,
    });
    return res.data.data;
  },

  /**
   * Mengunci atau membuka periode penilaian bulanan
   * PATCH /api/v1/grade-periods/{id}/lock
   */
  async lockGradePeriod(id: string, payload: LockPeriodPayload): Promise<GradePeriodItem> {
    const res = await apiClient.patch<ApiResponse<GradePeriodItem>>(
      `/grade-periods/${id}/lock`,
      payload
    );
    return res.data.data;
  },

  /**
   * Mengambil daftar nilai dengan filter dan pagination
   * GET /api/v1/grades
   */
  async listGrades(filters?: GradeFilters): Promise<PaginatedResponse<GradeItem>> {
    const res = await apiClient.get<PaginatedResponse<GradeItem>>('/grades', {
      params: filters,
    });
    return res.data;
  },

  /**
   * Mengambil grid spreadsheet nilai satu kelas (Siswa x Komponen)
   * GET /api/v1/grades/matrix
   */
  async getClassGradeMatrix(filters: GradeMatrixFilters): Promise<ClassGradeMatrix> {
    const res = await apiClient.get<ApiResponse<ClassGradeMatrix>>('/grades/matrix', {
      params: filters,
    });
    return res.data.data;
  },

  /**
   * Menginput / memperbarui nilai satuan siswa
   * POST /api/v1/grades
   */
  async storeGrade(payload: StoreGradePayload): Promise<GradeItem> {
    const res = await apiClient.post<ApiResponse<GradeItem>>('/grades', payload);
    return res.data.data;
  },

  /**
   * Menginput nilai massal / batch spreadsheet matrix
   * POST /api/v1/grades/batch
   */
  async bulkStoreGrades(payload: BulkStoreGradePayload): Promise<{
    count: number;
    component_id?: string;
    period_id: string;
    items: GradeItem[];
  }> {
    const res = await apiClient.post<
      ApiResponse<{
        count: number;
        component_id?: string;
        period_id: string;
        items: GradeItem[];
      }>
    >('/grades/batch', payload);
    return res.data.data;
  },

  /**
   * Menghapus nilai siswa
   * DELETE /api/v1/grades/{id}
   */
  async deleteGrade(id: string): Promise<void> {
    await apiClient.delete(`/grades/${id}`);
  },

  // =========================================================================
  // ADM-GRD-02 / BE-GRD-02: Catatan Karakter Bulanan Siswa
  // =========================================================================

  /**
   * Mengambil daftar catatan karakter dengan filter & pagination
   * GET /api/v1/character-notes
   */
  async listCharacterNotes(
    filters?: CharacterNoteFilters
  ): Promise<PaginatedResponse<CharacterNoteItem>> {
    const res = await apiClient.get<PaginatedResponse<CharacterNoteItem>>('/character-notes', {
      params: filters,
    });
    return res.data;
  },

  /**
   * Mengambil tampilan massal catatan karakter rombel kelas
   * GET /api/v1/character-notes/matrix
   */
  async getClassCharacterNotes(
    filters: ClassCharacterNotesFilters
  ): Promise<ClassCharacterNotesMatrix> {
    const res = await apiClient.get<ApiResponse<ClassCharacterNotesMatrix>>(
      '/character-notes/matrix',
      {
        params: filters,
      }
    );
    return res.data.data;
  },

  /**
   * Mengambil detail catatan karakter
   * GET /api/v1/character-notes/{id}
   */
  async getCharacterNote(id: string): Promise<CharacterNoteItem> {
    const res = await apiClient.get<ApiResponse<CharacterNoteItem>>(`/character-notes/${id}`);
    return res.data.data;
  },

  /**
   * Menyimpan / memperbarui catatan karakter satuan
   * POST /api/v1/character-notes
   */
  async storeCharacterNote(payload: StoreCharacterNotePayload): Promise<CharacterNoteItem> {
    const res = await apiClient.post<ApiResponse<CharacterNoteItem>>('/character-notes', payload);
    return res.data.data;
  },

  /**
   * Menyimpan catatan karakter massal satu rombel kelas
   * POST /api/v1/character-notes/batch
   */
  async bulkStoreCharacterNotes(payload: BulkStoreCharacterNotePayload): Promise<{
    count: number;
    period_id: string;
    items: CharacterNoteItem[];
  }> {
    const res = await apiClient.post<
      ApiResponse<{
        count: number;
        period_id: string;
        items: CharacterNoteItem[];
      }>
    >('/character-notes/batch', payload);
    return res.data.data;
  },

  /**
   * Menghapus catatan karakter siswa
   * DELETE /api/v1/character-notes/{id}
   */
  async deleteCharacterNote(id: string): Promise<void> {
    await apiClient.delete(`/character-notes/${id}`);
  },

  // =========================================================================
  // ADM-GRD-03 / BE-GRD-03: E-Progress Report Bulanan
  // =========================================================================

  /**
   * Mengambil daftar e-progress report bulanan berpaginasi
   * GET /api/v1/progress-reports
   */
  async listProgressReports(
    filters?: ProgressReportFilters
  ): Promise<PaginatedResponse<ProgressReportItem>> {
    const res = await apiClient.get<PaginatedResponse<ProgressReportItem>>('/progress-reports', {
      params: filters,
    });
    return res.data;
  },

  /**
   * Mengambil detail e-progress report
   * GET /api/v1/progress-reports/{id}
   */
  async getProgressReport(id: string): Promise<ProgressReportItem> {
    const res = await apiClient.get<ApiResponse<ProgressReportItem>>(`/progress-reports/${id}`);
    return res.data.data;
  },

  /**
   * Mengambil status kesiapan & rekap rapor satu rombel kelas
   * GET /api/v1/progress-reports/class-status
   */
  async getClassProgressReportsStatus(
    classId: string,
    periodId: string
  ): Promise<ClassProgressReportsStatus> {
    const res = await apiClient.get<ApiResponse<ClassProgressReportsStatus>>(
      '/progress-reports/class-status',
      {
        params: { class_id: classId, period_id: periodId },
      }
    );
    return res.data.data;
  },

  /**
   * Pratinjau snapshot rapor siswa sebelum disimpan ke DB
   * GET /api/v1/progress-reports/preview
   */
  async previewProgressReport(
    studentId: string,
    periodId: string
  ): Promise<ProgressReportSnapshot> {
    const res = await apiClient.get<ApiResponse<ProgressReportSnapshot>>(
      '/progress-reports/preview',
      {
        params: { student_id: studentId, period_id: periodId },
      }
    );
    return res.data.data;
  },

  /**
   * Generate snapshot e-progress report bulanan (satuan / massal)
   * POST /api/v1/progress-reports/generate
   */
  async generateProgressReports(
    payload: GenerateProgressReportPayload
  ): Promise<ProgressReportItem | ProgressReportItem[]> {
    const res = await apiClient.post<ApiResponse<ProgressReportItem | ProgressReportItem[]>>(
      '/progress-reports/generate',
      payload
    );
    return res.data.data;
  },

  /**
   * Pengesahan / ACC Kepala Sekolah atas rapor satuan
   * PATCH /api/v1/progress-reports/{id}/approve
   */
  async approveProgressReport(
    id: string,
    payload: ApproveProgressReportPayload
  ): Promise<ProgressReportItem> {
    const res = await apiClient.patch<ApiResponse<ProgressReportItem>>(
      `/progress-reports/${id}/approve`,
      payload
    );
    return res.data.data;
  },

  /**
   * Pengesahan / persetujuan massal Kepala Sekolah
   * POST /api/v1/progress-reports/bulk-approve
   */
  async bulkApproveProgressReports(
    payload: BulkApproveProgressReportPayload
  ): Promise<{ count: number; status: string; items: ProgressReportItem[] }> {
    const res = await apiClient.post<
      ApiResponse<{ count: number; status: string; items: ProgressReportItem[] }>
    >('/progress-reports/bulk-approve', payload);
    return res.data.data;
  },

  /**
   * Mendapatkan file binary PDF rapor
   * GET /api/v1/progress-reports/{id}/pdf
   */
  async downloadProgressReportPdf(id: string): Promise<Blob> {
    const res = await apiClient.get(`/progress-reports/${id}/pdf`, {
      responseType: 'blob',
    });
    return res.data;
  },

  // =========================================================================
  // ADM-GRD-04 / BE-GRD-04: E-Rapor Semester & Template Deskripsi Capaian
  // =========================================================================

  /**
   * Mengambil daftar template deskripsi capaian per mapel
   * GET /api/v1/report-description-templates
   */
  async listReportDescriptionTemplates(
    filters?: ReportDescriptionTemplateFilters
  ): Promise<PaginatedResponse<ReportDescriptionTemplateItem>> {
    const res = await apiClient.get<PaginatedResponse<ReportDescriptionTemplateItem>>(
      '/report-description-templates',
      {
        params: filters,
      }
    );
    return res.data;
  },

  /**
   * Menyimpan / memperbarui template deskripsi capaian
   * POST /api/v1/report-description-templates
   */
  async storeReportDescriptionTemplate(
    payload: StoreReportDescriptionTemplatePayload
  ): Promise<ReportDescriptionTemplateItem> {
    const res = await apiClient.post<ApiResponse<ReportDescriptionTemplateItem>>(
      '/report-description-templates',
      payload
    );
    return res.data.data;
  },

  /**
   * Menghapus template deskripsi capaian
   * DELETE /api/v1/report-description-templates/{id}
   */
  async deleteReportDescriptionTemplate(id: string): Promise<void> {
    await apiClient.delete(`/report-description-templates/${id}`);
  },

  /**
   * Mengambil daftar e-rapor semester berpaginasi
   * GET /api/v1/report-cards
   */
  async listReportCards(
    filters?: ReportCardFilters
  ): Promise<PaginatedResponse<ReportCardItem>> {
    const res = await apiClient.get<PaginatedResponse<ReportCardItem>>('/report-cards', {
      params: filters,
    });
    return res.data;
  },

  /**
   * Mengambil detail e-rapor semester
   * GET /api/v1/report-cards/{id}
   */
  async getReportCard(id: string): Promise<ReportCardItem> {
    const res = await apiClient.get<ApiResponse<ReportCardItem>>(`/report-cards/${id}`);
    return res.data.data;
  },

  /**
   * Mengambil status kesiapan & rekap e-rapor semester satu rombel kelas
   * GET /api/v1/report-cards/class-status
   */
  async getClassReportCardsStatus(
    classId: string,
    semesterId: string
  ): Promise<ClassReportCardsStatus> {
    const res = await apiClient.get<ApiResponse<ClassReportCardsStatus>>(
      '/report-cards/class-status',
      {
        params: { class_id: classId, semester_id: semesterId },
      }
    );
    return res.data.data;
  },

  /**
   * Pratinjau snapshot rapor semester siswa sebelum disimpan ke DB
   * GET /api/v1/report-cards/preview
   */
  async previewReportCard(
    studentId: string,
    semesterId: string,
    homeroomNote?: string
  ): Promise<ReportCardSnapshot> {
    const res = await apiClient.get<ApiResponse<ReportCardSnapshot>>(
      '/report-cards/preview',
      {
        params: { student_id: studentId, semester_id: semesterId, homeroom_note: homeroomNote },
      }
    );
    return res.data.data;
  },

  /**
   * Generate snapshot e-rapor semester (satuan / massal rombel)
   * POST /api/v1/report-cards/generate
   */
  async generateReportCards(
    payload: GenerateReportCardPayload
  ): Promise<ReportCardItem | ReportCardItem[]> {
    const res = await apiClient.post<ApiResponse<ReportCardItem | ReportCardItem[]>>(
      '/report-cards/generate',
      payload
    );
    return res.data.data;
  },

  /**
   * Kunci / buka kunci e-rapor semester oleh Admin Kurikulum
   * PATCH /api/v1/report-cards/{id}/lock
   */
  async lockReportCard(
    id: string,
    payload: LockReportCardPayload
  ): Promise<ReportCardItem> {
    const res = await apiClient.patch<ApiResponse<ReportCardItem>>(
      `/report-cards/${id}/lock`,
      payload
    );
    return res.data.data;
  },

  /**
   * Pengesahan / ACC Kepala Sekolah atas rapor semester satuan
   * PATCH /api/v1/report-cards/{id}/approve
   */
  async approveReportCard(
    id: string,
    payload: ApproveReportCardPayload
  ): Promise<ReportCardItem> {
    const res = await apiClient.patch<ApiResponse<ReportCardItem>>(
      `/report-cards/${id}/approve`,
      payload
    );
    return res.data.data;
  },

  /**
   * Pengesahan / penerbitan massal E-Rapor semester oleh Kepala Sekolah
   * POST /api/v1/report-cards/bulk-approve
   */
  async bulkApproveReportCards(
    payload: BulkApproveReportCardPayload
  ): Promise<{ count: number; status: string; items: ReportCardItem[] }> {
    const res = await apiClient.post<
      ApiResponse<{ count: number; status: string; items: ReportCardItem[] }>
    >('/report-cards/bulk-approve', payload);
    return res.data.data;
  },

  /**
   * Penguncian / pembukaan kunci massal E-Rapor semester oleh Admin Kurikulum
   * POST /api/v1/report-cards/bulk-lock
   */
  async bulkLockReportCards(
    payload: BulkLockReportCardPayload
  ): Promise<{ count: number; is_locked: boolean; items: ReportCardItem[] }> {
    const res = await apiClient.post<
      ApiResponse<{ count: number; is_locked: boolean; items: ReportCardItem[] }>
    >('/report-cards/bulk-lock', payload);
    return res.data.data;
  },

  /**
   * Mendapatkan file binary PDF e-rapor semester
   * GET /api/v1/report-cards/{id}/pdf
   */
  async downloadReportCardPdf(id: string): Promise<Blob> {
    const res = await apiClient.get(`/report-cards/${id}/pdf`, {
      responseType: 'blob',
    });
    return res.data;
  },
};
