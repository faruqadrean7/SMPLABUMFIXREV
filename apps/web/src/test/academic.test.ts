import { describe, it, expect, vi, beforeEach } from 'vitest';
import { scheduleSchema, timeSlotSchema } from '@/features/academic/schedules';
import { journalSchema } from '@/features/academic/journals';
import { academicApi } from '@/features/academic/academic-api';
import { apiClient } from '@/lib/api/api-client';
import { materialSchema } from '@/features/academic/teaching-materials';
import { assignmentSchema } from '@/features/academic/assignments';
import {
  ATTENDANCE_STATUS_LABELS,
  ATTENDANCE_STATUS_COLORS,
  AttendanceStatus,
  LEAVE_TYPE_LABELS,
  LEAVE_STATUS_LABELS,
  LEAVE_STATUS_COLORS,
  STAFF_ATTENDANCE_STATUS_LABELS,
  STAFF_ATTENDANCE_STATUS_COLORS,
  StaffAttendanceStatus,
  TEACHING_MATERIAL_TYPE_LABELS,
  TEACHING_MATERIAL_TYPE_COLORS,
  TeachingMaterialType,
  SUBMISSION_STATUS_LABELS,
  SUBMISSION_STATUS_COLORS,
  SUBMISSION_TIMING_LABELS,
  SUBMISSION_TIMING_COLORS,
} from '@/types/academic';

describe('ADM-ACD-01 Academic Schedule Module', () => {
  describe('scheduleSchema validation', () => {
    it('passes with valid schedule inputs', () => {
      const valid = {
        semester_id: '123e4567-e89b-12d3-a456-426614174000',
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        teacher_id: '123e4567-e89b-12d3-a456-426614174003',
        room_id: '123e4567-e89b-12d3-a456-426614174004',
        time_slot_id: '123e4567-e89b-12d3-a456-426614174005',
        day_of_week: 1,
      };
      const result = scheduleSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('passes without room_id (optional regular classroom)', () => {
      const validWithoutRoom = {
        semester_id: '123e4567-e89b-12d3-a456-426614174000',
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        teacher_id: '123e4567-e89b-12d3-a456-426614174003',
        room_id: '',
        time_slot_id: '123e4567-e89b-12d3-a456-426614174005',
        day_of_week: 3,
      };
      const result = scheduleSchema.safeParse(validWithoutRoom);
      expect(result.success).toBe(true);
    });

    it('fails when semester_id is not a valid uuid', () => {
      const invalid = {
        semester_id: 'invalid-id',
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        teacher_id: '123e4567-e89b-12d3-a456-426614174003',
        time_slot_id: '123e4567-e89b-12d3-a456-426614174005',
        day_of_week: 1,
      };
      const result = scheduleSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Semester wajib dipilih');
      }
    });

    it('fails when day_of_week is outside 1..7', () => {
      const invalid = {
        semester_id: '123e4567-e89b-12d3-a456-426614174000',
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        teacher_id: '123e4567-e89b-12d3-a456-426614174003',
        time_slot_id: '123e4567-e89b-12d3-a456-426614174005',
        day_of_week: 8,
      };
      const result = scheduleSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Hari tidak valid');
      }
    });
  });

  describe('timeSlotSchema validation', () => {
    it('passes with valid time slot data', () => {
      const valid = {
        name: 'Jam ke-1',
        start_time: '07:00',
        end_time: '07:45',
        day_type: 'reguler',
      };
      const result = timeSlotSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when end_time is earlier than start_time', () => {
      const invalid = {
        name: 'Jam Invalid',
        start_time: '08:00',
        end_time: '07:15',
        day_type: 'reguler',
      };
      const result = timeSlotSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain(
          'Waktu selesai harus setelah waktu mulai'
        );
      }
    });

    it('fails when time format is not HH:mm', () => {
      const invalid = {
        name: 'Jam Invalid Format',
        start_time: '7:0',
        end_time: '08:00',
        day_type: 'reguler',
      };
      const result = timeSlotSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Format waktu mulai harus HH:mm');
      }
    });
  });

  describe('academicApi Client Methods', () => {
    beforeEach(() => {
      vi.restoreAllMocks();
    });

    it('calls GET /schedules with filter parameters', async () => {
      const mockSchedules = [
        {
          id: 'sch-1',
          semester_id: 'sem-1',
          class_id: 'cls-1',
          subject_id: 'sub-1',
          teacher_id: 'tch-1',
          time_slot_id: 'ts-1',
          day_of_week: 1,
        },
      ];
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockSchedules } });

      const result = await academicApi.listSchedules({ class_id: 'cls-1', day_of_week: 1 });
      expect(apiClient.get).toHaveBeenCalledWith('/schedules', {
        params: { class_id: 'cls-1', day_of_week: 1, all: true },
      });
      expect(result).toEqual(mockSchedules);
    });

    it('calls POST /schedules/conflicts/check for real-time conflict detection', async () => {
      const payload = {
        semester_id: 'sem-1',
        class_id: 'cls-1',
        teacher_id: 'tch-1',
        room_id: null,
        time_slot_id: 'ts-1',
        day_of_week: 1 as const,
      };
      const mockResult = {
        has_conflict: true,
        conflicts: [
          {
            type: 'TEACHER_CONFLICT' as const,
            message: 'Guru sudah terjadwal mengajar di kelas lain',
          },
        ],
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockResult } });

      const result = await academicApi.checkConflicts(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/schedules/conflicts/check', payload);
      expect(result.has_conflict).toBe(true);
      expect(result.conflicts[0].type).toBe('TEACHER_CONFLICT');
    });

    it('calls POST /schedules to create a new schedule', async () => {
      const payload = {
        semester_id: 'sem-1',
        class_id: 'cls-1',
        subject_id: 'sub-1',
        teacher_id: 'tch-1',
        time_slot_id: 'ts-1',
        day_of_week: 1 as const,
      };
      const mockCreated = { id: 'sch-new', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const result = await academicApi.createSchedule(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/schedules', payload);
      expect(result.id).toBe('sch-new');
    });

    it('calls PUT /schedules/{id} to update existing schedule', async () => {
      const updateData = { subject_id: 'sub-2' };
      const mockUpdated = { id: 'sch-1', subject_id: 'sub-2' };
      vi.spyOn(apiClient, 'put').mockResolvedValue({ data: { data: mockUpdated } });

      const result = await academicApi.updateSchedule('sch-1', updateData);
      expect(apiClient.put).toHaveBeenCalledWith('/schedules/sch-1', updateData);
      expect(result.subject_id).toBe('sub-2');
    });

    it('calls DELETE /schedules/{id} to remove schedule', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: { success: true } });

      await academicApi.deleteSchedule('sch-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/schedules/sch-1');
    });

    it('calls GET /time-slots to retrieve time slots', async () => {
      const mockSlots = [
        { id: 'ts-1', name: 'Jam ke-1', start_time: '07:00', end_time: '07:45', day_type: 'reguler' },
      ];
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockSlots } });

      const result = await academicApi.listTimeSlots();
      expect(apiClient.get).toHaveBeenCalledWith('/time-slots', { params: undefined });
      expect(result).toEqual(mockSlots);
    });

    it('calls POST /time-slots to create a new time slot', async () => {
      const payload = {
        name: 'Jam ke-2',
        start_time: '07:45',
        end_time: '08:30',
        day_type: 'reguler',
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: { id: 'ts-2', ...payload } } });

      const result = await academicApi.createTimeSlot(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/time-slots', payload);
      expect(result.name).toBe('Jam ke-2');
    });
  });
});

describe('ADM-ACD-02 Teaching Journal Module', () => {
  describe('journalSchema validation', () => {
    it('passes with valid journal input', () => {
      const valid = {
        schedule_id: '123e4567-e89b-12d3-a456-426614174000',
        session_date: '2026-08-10',
        topic: 'SPLTV dan Pembahasan Soal Cerita',
        method: 'Problem Based Learning & Diskusi Kelompok',
        notes: 'Siswa aktif bertanya dan mengerjakan latihan.',
      };
      const result = journalSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('passes without notes (optional field)', () => {
      const validWithoutNotes = {
        schedule_id: '123e4567-e89b-12d3-a456-426614174000',
        session_date: '2026-08-10',
        topic: 'Pengantar Termodinamika',
        method: 'Eksperimen Lab & Praktik',
        notes: '',
      };
      const result = journalSchema.safeParse(validWithoutNotes);
      expect(result.success).toBe(true);
    });

    it('fails when schedule_id is not a valid uuid', () => {
      const invalid = {
        schedule_id: 'invalid-id',
        session_date: '2026-08-10',
        topic: 'Topik Valid',
        method: 'Metode Valid',
      };
      const result = journalSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Sesi jadwal pelajaran wajib dipilih');
      }
    });

    it('fails when session_date format is not YYYY-MM-DD', () => {
      const invalid = {
        schedule_id: '123e4567-e89b-12d3-a456-426614174000',
        session_date: '10/08/2026',
        topic: 'Topik Valid',
        method: 'Metode Valid',
      };
      const result = journalSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Format tanggal harus YYYY-MM-DD');
      }
    });

    it('fails when topic is shorter than 2 characters', () => {
      const invalid = {
        schedule_id: '123e4567-e89b-12d3-a456-426614174000',
        session_date: '2026-08-10',
        topic: 'A',
        method: 'Diskusi Kelompok',
      };
      const result = journalSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Materi / topik pembelajaran minimal 2 karakter');
      }
    });

    it('fails when method is shorter than 2 characters', () => {
      const invalid = {
        schedule_id: '123e4567-e89b-12d3-a456-426614174000',
        session_date: '2026-08-10',
        topic: 'Aljabar Linier',
        method: 'M',
      };
      const result = journalSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Metode pembelajaran minimal 2 karakter');
      }
    });
  });

  describe('academicApi Journal Methods', () => {
    beforeEach(() => {
      vi.restoreAllMocks();
    });

    it('calls GET /journals with filter parameters', async () => {
      const mockResponse = {
        data: [
          {
            id: 'jrn-1',
            schedule_id: 'sch-1',
            session_date: '2026-08-10',
            topic: 'Trigonometri',
            method: 'Ceramah & Tanya Jawab',
          },
        ],
        meta: { page: 1, limit: 20, total: 1, totalPages: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockResponse });

      const result = await academicApi.listJournals({ class_id: 'cls-1', start_date: '2026-08-01' });
      expect(apiClient.get).toHaveBeenCalledWith('/journals', {
        params: { class_id: 'cls-1', start_date: '2026-08-01' },
      });
      expect(result.data).toHaveLength(1);
      expect(result.data[0].topic).toBe('Trigonometri');
    });

    it('calls GET /journals/{id} to retrieve detail with attendances', async () => {
      const mockDetail = {
        id: 'jrn-1',
        schedule_id: 'sch-1',
        session_date: '2026-08-10',
        topic: 'Trigonometri',
        method: 'Ceramah & Tanya Jawab',
        attendances: [{ id: 'att-1', student_id: 'std-1', status: 'hadir' as const }],
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockDetail } });

      const result = await academicApi.getJournal('jrn-1');
      expect(apiClient.get).toHaveBeenCalledWith('/journals/jrn-1');
      expect(result.id).toBe('jrn-1');
      expect(result.attendances).toHaveLength(1);
    });

    it('calls POST /journals to create new teaching journal', async () => {
      const payload = {
        schedule_id: 'sch-1',
        session_date: '2026-08-10',
        topic: 'Persamaan Kuadrat',
        method: 'Problem Based Learning',
        notes: 'Siswa tuntas memahami rumus kuadratik.',
      };
      const mockCreated = { id: 'jrn-new', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const result = await academicApi.createJournal(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/journals', payload);
      expect(result.id).toBe('jrn-new');
      expect(result.topic).toBe('Persamaan Kuadrat');
    });

    it('calls PUT /journals/{id} to update existing journal', async () => {
      const updateData = {
        topic: 'Persamaan Kuadrat Lanjutan',
        method: 'Diskusi Kelompok & Praktik',
      };
      const mockUpdated = { id: 'jrn-1', ...updateData };
      vi.spyOn(apiClient, 'put').mockResolvedValue({ data: { data: mockUpdated } });

      const result = await academicApi.updateJournal('jrn-1', updateData);
      expect(apiClient.put).toHaveBeenCalledWith('/journals/jrn-1', updateData);
      expect(result.topic).toBe('Persamaan Kuadrat Lanjutan');
    });

    it('calls DELETE /journals/{id} to delete journal', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: { success: true } });

      await academicApi.deleteJournal('jrn-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/journals/jrn-1');
    });
  });
});

describe('ADM-ACD-03 Attendance Module', () => {
  describe('Attendance status definitions and colors', () => {
    it('defines all required attendance statuses', () => {
      const statuses: AttendanceStatus[] = ['hadir', 'izin', 'sakit', 'alpa', 'terlambat'];
      statuses.forEach((status) => {
        expect(ATTENDANCE_STATUS_LABELS[status]).toBeDefined();
        expect(ATTENDANCE_STATUS_COLORS[status]).toBeDefined();
        expect(ATTENDANCE_STATUS_COLORS[status].bg).toBeDefined();
      });
      expect(ATTENDANCE_STATUS_LABELS.hadir).toBe('Hadir');
      expect(ATTENDANCE_STATUS_LABELS.sakit).toBe('Sakit');
      expect(ATTENDANCE_STATUS_LABELS.izin).toBe('Izin');
      expect(ATTENDANCE_STATUS_LABELS.alpa).toBe('Alpa');
      expect(ATTENDANCE_STATUS_LABELS.terlambat).toBe('Terlambat');
    });
  });

  describe('academicApi Attendance methods', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('calls GET /attendances with filters and pagination', async () => {
      const mockResult = {
        data: [
          {
            id: 'att-1',
            student_id: 'std-1',
            att_date: '2026-08-10',
            status: 'hadir',
            source: 'kelas',
          },
        ],
        meta: { current_page: 1, total: 1, per_page: 20 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockResult });

      const filters = { class_id: 'cls-1', status: 'hadir' as const, per_page: 20 };
      const result = await academicApi.listAttendances(filters);

      expect(apiClient.get).toHaveBeenCalledWith('/attendances', { params: filters });
      expect(result.data).toHaveLength(1);
      expect(result.data[0].status).toBe('hadir');
    });

    it('calls GET /attendances/recap to fetch attendance summary', async () => {
      const mockRecap = {
        total_sessions: 10,
        summary: { hadir: 8, izin: 1, sakit: 1, alpa: 0, terlambat: 0 },
        attendance_rate: 80,
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockRecap } });

      const result = await academicApi.getAttendanceRecap({ class_id: 'cls-1' });

      expect(apiClient.get).toHaveBeenCalledWith('/attendances/recap', {
        params: { class_id: 'cls-1' },
      });
      expect(result.total_sessions).toBe(10);
      expect(result.attendance_rate).toBe(80);
    });

    it('calls POST /attendances to batch record session attendance', async () => {
      const payload = {
        journal_id: 'jrn-1',
        attendances: [
          { student_id: 'std-1', status: 'hadir' as const },
          { student_id: 'std-2', status: 'terlambat' as const, note: 'Ban bocor' },
        ],
      };
      const mockResponse = {
        journal_id: 'jrn-1',
        session_date: '2026-08-10',
        total_recorded: 2,
        attendances: [],
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockResponse } });

      const result = await academicApi.recordSessionAttendance(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/attendances', payload);
      expect(result.total_recorded).toBe(2);
    });

    it('calls POST /attendances/daily to record gate attendance', async () => {
      const payload = {
        student_id: 'std-1',
        att_date: '2026-08-10',
        status: 'hadir' as const,
        note: 'Scan RFID',
      };
      const mockRecord = { id: 'att-gate-1', ...payload, source: 'gerbang' };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockRecord } });

      const result = await academicApi.recordDailyAttendance(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/attendances/daily', payload);
      expect(result.id).toBe('att-gate-1');
    });
  });

  describe('ADM-ACD-04 Permohonan Izin & Sakit Siswa (Leave Requests)', () => {
    beforeEach(() => {
      vi.restoreAllMocks();
    });

    it('has valid status and type labels mapping', () => {
      expect(LEAVE_TYPE_LABELS.sakit).toBe('Sakit');
      expect(LEAVE_TYPE_LABELS.izin).toBe('Izin');
      expect(LEAVE_TYPE_LABELS.dispen).toBe('Dispensasi');

      expect(LEAVE_STATUS_LABELS.menunggu).toBe('Menunggu Verifikasi');
      expect(LEAVE_STATUS_LABELS.disetujui).toBe('Disetujui');
      expect(LEAVE_STATUS_LABELS.ditolak).toBe('Ditolak');

      expect(LEAVE_STATUS_COLORS.menunggu.badge).toContain('amber');
      expect(LEAVE_STATUS_COLORS.disetujui.badge).toContain('emerald');
      expect(LEAVE_STATUS_COLORS.ditolak.badge).toContain('rose');
    });

    it('calls GET /leave-requests with query filters', async () => {
      const mockData = {
        data: [
          {
            id: 'lr-1',
            student_id: 'std-1',
            leave_type: 'sakit' as const,
            start_date: '2026-08-10',
            end_date: '2026-08-12',
            reason: 'Sakit cacar',
            status: 'menunggu' as const,
          },
        ],
        meta: { total: 1, current_page: 1, last_page: 1, per_page: 15 },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockData });

      const filters = {
        status: 'menunggu' as const,
        class_id: 'cls-1',
        leave_type: 'sakit' as const,
        q: 'Aditya',
      };

      const result = await academicApi.listLeaveRequests(filters);

      expect(apiClient.get).toHaveBeenCalledWith('/leave-requests', {
        params: filters,
      });
      expect(result.data.length).toBe(1);
      expect(result.data[0].id).toBe('lr-1');
    });

    it('calls GET /leave-requests/{id} to get leave request detail', async () => {
      const mockDetail = {
        id: 'lr-detail-1',
        student_id: 'std-1',
        leave_type: 'izin' as const,
        start_date: '2026-08-15',
        end_date: '2026-08-15',
        reason: 'Acara keluarga di luar kota',
        status: 'disetujui' as const,
      };

      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockDetail } });

      const result = await academicApi.getLeaveRequest('lr-detail-1');

      expect(apiClient.get).toHaveBeenCalledWith('/leave-requests/lr-detail-1');
      expect(result.id).toBe('lr-detail-1');
      expect(result.status).toBe('disetujui');
    });

    it('calls POST /leave-requests with JSON when no file attachment', async () => {
      const payload = {
        student_id: 'std-1',
        leave_type: 'izin' as const,
        start_date: '2026-08-20',
        end_date: '2026-08-21',
        reason: 'Khitanan adik kandung',
      };

      const mockCreated = { id: 'lr-created-1', ...payload, status: 'menunggu' as const };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const result = await academicApi.createLeaveRequest(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/leave-requests', {
        student_id: payload.student_id,
        leave_type: payload.leave_type,
        start_date: payload.start_date,
        end_date: payload.end_date,
        reason: payload.reason,
        attachment_file_id: undefined,
      });
      expect(result.id).toBe('lr-created-1');
    });

    it('calls POST /leave-requests with FormData when file attachment provided', async () => {
      const fakeFile = new File(['dummy content'], 'surat_dokter.pdf', {
        type: 'application/pdf',
      });

      const payload = {
        student_id: 'std-1',
        leave_type: 'sakit' as const,
        start_date: '2026-08-20',
        end_date: '2026-08-22',
        reason: 'Demam tinggi dirawat',
        attachment_file: fakeFile,
      };

      const mockCreated = { id: 'lr-with-file', ...payload, status: 'menunggu' as const };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const result = await academicApi.createLeaveRequest(payload);

      expect(apiClient.post).toHaveBeenCalledWith(
        '/leave-requests',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      expect(result.id).toBe('lr-with-file');
    });

    it('calls PATCH /leave-requests/{id}/decision with approval decision and notes', async () => {
      const payload = {
        status: 'disetujui' as const,
        decision_notes: 'Disetujui wali kelas, lekas sembuh.',
      };

      const mockUpdated = {
        id: 'lr-decide-1',
        status: 'disetujui',
        decision_notes: payload.decision_notes,
      };
      vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { data: mockUpdated } });

      const result = await academicApi.decideLeaveRequest('lr-decide-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith(
        '/leave-requests/lr-decide-1/decision',
        payload
      );
      expect(result.status).toBe('disetujui');
    });

    it('calls GET /files/{id}/url to retrieve signed attachment link', async () => {
      const mockUrlResponse = { url: 'https://storage.school.sch.id/signed/file123.pdf' };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockUrlResponse } });

      const result = await academicApi.getFileUrl('file-uuid-123');

      expect(apiClient.get).toHaveBeenCalledWith('/files/file-uuid-123/url');
      expect(result.url).toBe('https://storage.school.sch.id/signed/file123.pdf');
    });
  });

  describe('ADM-ACD-05 Staff Attendance (Presensi PTK) Module', () => {
    it('provides valid Indonesian labels for all staff attendance statuses', () => {
      expect(STAFF_ATTENDANCE_STATUS_LABELS.hadir).toBe('Hadir');
      expect(STAFF_ATTENDANCE_STATUS_LABELS.terlambat).toBe('Terlambat');
      expect(STAFF_ATTENDANCE_STATUS_LABELS.izin).toBe('Izin');
      expect(STAFF_ATTENDANCE_STATUS_LABELS.sakit).toBe('Sakit');
      expect(STAFF_ATTENDANCE_STATUS_LABELS.alfa).toBe('Alfa');
    });

    it('provides badge style definitions for all staff attendance statuses', () => {
      const statuses: StaffAttendanceStatus[] = ['hadir', 'terlambat', 'izin', 'sakit', 'alfa'];
      statuses.forEach((st) => {
        expect(STAFF_ATTENDANCE_STATUS_COLORS[st]).toBeDefined();
        expect(STAFF_ATTENDANCE_STATUS_COLORS[st].badge).toBeDefined();
        expect(STAFF_ATTENDANCE_STATUS_COLORS[st].badge.length).toBeGreaterThan(0);
      });
    });

    it('calls GET /staff-attendances/today to get today self record', async () => {
      const mockRecord = {
        id: 'att-today-1',
        user_id: 'user-1',
        att_date: '2026-10-13',
        status: 'hadir' as const,
        check_in: '2026-10-13T07:05:00Z',
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockRecord } });

      const res = await academicApi.getStaffToday();
      expect(apiClient.get).toHaveBeenCalledWith('/staff-attendances/today');
      expect(res?.id).toBe('att-today-1');
      expect(res?.status).toBe('hadir');
    });

    it('calls POST /staff-attendances/check-in without photo as JSON', async () => {
      const mockResponse = {
        id: 'att-checkin-1',
        status: 'hadir',
        check_in: '2026-10-13 07:00:00',
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockResponse } });

      const res = await academicApi.checkInStaff({
        lat: -7.9666,
        lng: 112.6186,
        notes: 'Piket pagi',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/staff-attendances/check-in', {
        lat: -7.9666,
        lng: 112.6186,
        photo_file_id: undefined,
        notes: 'Piket pagi',
      });
      expect(res.id).toBe('att-checkin-1');
    });

    it('calls POST /staff-attendances/check-in with photo as multipart/form-data', async () => {
      const fakePhoto = new File(['photo bytes'], 'selfie.jpg', { type: 'image/jpeg' });
      const mockResponse = { id: 'att-checkin-photo', status: 'hadir' };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockResponse } });

      const res = await academicApi.checkInStaff({
        lat: -7.9666,
        lng: 112.6186,
        photo_file: fakePhoto,
      });

      expect(apiClient.post).toHaveBeenCalledWith(
        '/staff-attendances/check-in',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      expect(res.id).toBe('att-checkin-photo');
    });

    it('calls POST /staff-attendances/check-out with coordinates and notes', async () => {
      const mockResponse = {
        id: 'att-checkout-1',
        status: 'hadir',
        check_out: '2026-10-13 15:30:00',
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockResponse } });

      const res = await academicApi.checkOutStaff({
        lat: -7.9666,
        lng: 112.6186,
        notes: 'Selesai tugas',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/staff-attendances/check-out', {
        lat: -7.9666,
        lng: 112.6186,
        notes: 'Selesai tugas',
      });
      expect(res.id).toBe('att-checkout-1');
    });

    it('calls GET /staff-attendances with filter parameters', async () => {
      const mockPaginated = {
        data: [{ id: 'item-1', status: 'hadir' }],
        pagination: { page: 1, limit: 15, total: 1, totalPages: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockPaginated });

      const res = await academicApi.listStaffAttendances({
        q: 'budi',
        status: 'hadir',
        date_from: '2026-10-01',
        date_to: '2026-10-31',
        page: 1,
        per_page: 15,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/staff-attendances', {
        params: {
          user_id: undefined,
          date_from: '2026-10-01',
          date_to: '2026-10-31',
          status: 'hadir',
          q: 'budi',
          per_page: 15,
          page: 1,
        },
      });
      expect(res.data.length).toBe(1);
    });

    it('calls GET /staff-attendances/summary with date range', async () => {
      const mockSummary = {
        total_recorded_days: 20,
        summary: { hadir: 18, terlambat: 2, izin: 0, sakit: 0, alfa: 0 },
        total_present: 20,
        attendance_percentage: 100,
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockSummary } });

      const res = await academicApi.getStaffAttendanceSummary({
        date_from: '2026-10-01',
        date_to: '2026-10-31',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/staff-attendances/summary', {
        params: {
          user_id: undefined,
          date_from: '2026-10-01',
          date_to: '2026-10-31',
        },
      });
      expect(res.attendance_percentage).toBe(100);
      expect(res.summary.hadir).toBe(18);
    });

    it('calls POST /staff-attendances for manual entry by admin', async () => {
      const payload = {
        user_id: 'user-guru-1',
        att_date: '2026-10-13',
        status: 'hadir' as const,
        check_in: '2026-10-13 07:00:00',
        check_out: '2026-10-13 15:30:00',
        notes: 'Entri manual dinas luar',
      };
      const mockCreated = { id: 'manual-1', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.manualRecordStaffAttendance(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/staff-attendances', payload);
      expect(res.id).toBe('manual-1');
    });

    it('calls PATCH /staff-attendances/{id} for correction with reason', async () => {
      const payload = {
        status: 'hadir' as const,
        check_in: '2026-10-13 07:10:00',
        reason: 'Revisi salah input status oleh staf TU',
      };
      const mockUpdated = { id: 'att-1', status: 'hadir' };
      vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { data: mockUpdated } });

      const res = await academicApi.correctStaffAttendance('att-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/staff-attendances/att-1', payload);
      expect(res.id).toBe('att-1');
    });

    it('calls GET & PUT /staff-attendances/settings for geofencing configuration', async () => {
      const mockSettings = {
        latitude: -7.9666,
        longitude: 112.6186,
        radius_meters: 100,
        late_time: '07:15:00',
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockSettings } });
      vi.spyOn(apiClient, 'put').mockResolvedValue({ data: { data: mockSettings } });

      const getRes = await academicApi.getStaffAttendanceSettings();
      expect(apiClient.get).toHaveBeenCalledWith('/staff-attendances/settings');
      expect(getRes.radius_meters).toBe(100);

      const updateRes = await academicApi.updateStaffAttendanceSettings(mockSettings);
      expect(apiClient.put).toHaveBeenCalledWith('/staff-attendances/settings', mockSettings);
      expect(updateRes.late_time).toBe('07:15:00');
    });
  });

  describe('ADM-ACD-06 Bank Modul Ajar & Perangkat Pembelajaran Module', () => {
    it('provides valid Indonesian labels for all teaching material types', () => {
      expect(TEACHING_MATERIAL_TYPE_LABELS.modul).toBe('Modul Ajar');
      expect(TEACHING_MATERIAL_TYPE_LABELS.ATP).toBe('Alur Tujuan Pembelajaran (ATP)');
      expect(TEACHING_MATERIAL_TYPE_LABELS.silabus).toBe('Silabus / Perangkat');
      expect(TEACHING_MATERIAL_TYPE_LABELS.P5).toBe('Modul Proyek P5');
    });

    it('provides badge style definitions for all teaching material types', () => {
      const types: TeachingMaterialType[] = ['modul', 'ATP', 'silabus', 'P5'];
      types.forEach((t) => {
        expect(TEACHING_MATERIAL_TYPE_COLORS[t]).toBeDefined();
        expect(TEACHING_MATERIAL_TYPE_COLORS[t].badge).toBeDefined();
        expect(TEACHING_MATERIAL_TYPE_COLORS[t].bg).toBeDefined();
        expect(TEACHING_MATERIAL_TYPE_COLORS[t].text).toBeDefined();
      });
    });

    it('calls GET /teaching-materials with filter parameters', async () => {
      const mockPaginated = {
        data: [
          {
            id: 'mat-1',
            title: 'Modul Ajar Informatika Bab 1',
            material_type: 'modul' as const,
            version: 1,
          },
        ],
        meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockPaginated });

      const res = await academicApi.listTeachingMaterials({
        search: 'Informatika',
        material_type: 'modul',
        grade_level_id: 'gl-1',
        subject_id: 'sub-1',
        page: 1,
        per_page: 15,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/teaching-materials', {
        params: {
          search: 'Informatika',
          material_type: 'modul',
          grade_level_id: 'gl-1',
          subject_id: 'sub-1',
          page: 1,
          per_page: 15,
        },
      });
      expect(res.data.length).toBe(1);
      expect(res.data[0].title).toBe('Modul Ajar Informatika Bab 1');
    });

    it('calls GET /teaching-materials/{id} to get detail and version history', async () => {
      const mockDetail = {
        id: 'mat-1',
        title: 'Modul Ajar Informatika Bab 1',
        material_type: 'modul' as const,
        version: 2,
        versions: [
          { id: 'mat-1', version: 1, title: 'v1 draft' },
          { id: 'mat-v2', version: 2, title: 'v2 final' },
        ],
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockDetail } });

      const res = await academicApi.getTeachingMaterial('mat-1');

      expect(apiClient.get).toHaveBeenCalledWith('/teaching-materials/mat-1');
      expect(res.id).toBe('mat-1');
      expect(res.version).toBe(2);
      expect(res.versions?.length).toBe(2);
    });

    it('calls POST /teaching-materials with multipart/form-data when file uploaded', async () => {
      const fakeFile = new File(['dummy pdf content'], 'modul_informatika.pdf', {
        type: 'application/pdf',
      });

      const payload = {
        title: 'Modul Baru dengan Berkas',
        description: 'Petunjuk KBM',
        material_type: 'modul' as const,
        grade_level_id: 'gl-7',
        subject_id: 'sub-info',
        file: fakeFile,
      };

      const mockCreated = { id: 'created-mat-1', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.createTeachingMaterial(payload);

      expect(apiClient.post).toHaveBeenCalledWith(
        '/teaching-materials',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      expect(res.id).toBe('created-mat-1');
    });

    it('calls POST /teaching-materials with JSON when file_id provided without raw file', async () => {
      const payload = {
        title: 'Modul dengan file_id',
        material_type: 'ATP' as const,
        file_id: 'file-uuid-abc',
      };

      const mockCreated = { id: 'created-mat-json', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.createTeachingMaterial(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/teaching-materials', payload);
      expect(res.id).toBe('created-mat-json');
    });

    it('calls POST /teaching-materials/{id}/versions with multipart/form-data for new revision', async () => {
      const fakeRevisionFile = new File(['revision content'], 'revisi_v2.pdf', {
        type: 'application/pdf',
      });

      const payload = {
        title: 'Modul Ajar Revisi v2',
        description: 'Penambahan rubrik penilaian sumatif',
        file: fakeRevisionFile,
        version: 2,
      };

      const mockVersion = { id: 'ver-2', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockVersion } });

      const res = await academicApi.createTeachingMaterialVersion('mat-root-1', payload);

      expect(apiClient.post).toHaveBeenCalledWith(
        '/teaching-materials/mat-root-1/versions',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      expect(res.id).toBe('ver-2');
    });

    it('calls DELETE /teaching-materials/{id} to remove teaching material', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: { success: true } });

      await academicApi.deleteTeachingMaterial('mat-delete-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/teaching-materials/mat-delete-1');
    });

    it('calls GET /grade-levels to retrieve grade levels for filtering and options', async () => {
      const mockGradeLevels = [
        { id: 'gl-7', name: 'Kelas 7', level: 7 },
        { id: 'gl-8', name: 'Kelas 8', level: 8 },
        { id: 'gl-9', name: 'Kelas 9', level: 9 },
      ];
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockGradeLevels } });

      const res = await academicApi.getGradeLevels();

      expect(apiClient.get).toHaveBeenCalledWith('/grade-levels');
      expect(res.length).toBe(3);
      expect(res[0].name).toBe('Kelas 7');
    });
  });
});

describe('ADM-ACD-07 Lightweight LMS (Tugas & Materi Pembelajaran)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe('materialSchema validation', () => {
    it('passes with valid learning material payload', () => {
      const valid = {
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        title: 'Bab 1 Bilangan Bulat dan Pecahan',
        description: 'Ringkasan materi operasi hitung bilangan',
      };
      const result = materialSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when class_id is missing or not a uuid', () => {
      const invalid = {
        class_id: 'invalid-id',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        title: 'Judul Valid',
      };
      const result = materialSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Kelas tujuan wajib dipilih');
      }
    });

    it('fails when title is shorter than 3 characters', () => {
      const invalid = {
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        title: 'No',
      };
      const result = materialSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Judul materi minimal 3 karakter');
      }
    });
  });

  describe('assignmentSchema validation', () => {
    it('passes with valid assignment payload', () => {
      const valid = {
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        title: 'Tugas Mandiri 1: Aljabar',
        description: 'Kerjakan soal 1 sampai 10 di lembar jawaban',
        due_at: '2026-10-20T23:59',
        max_score: 100,
        grade_component_id: '123e4567-e89b-12d3-a456-426614174099',
      };
      const result = assignmentSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when due_at is empty', () => {
      const invalid = {
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        title: 'Tugas Tanpa Deadline',
        due_at: '',
      };
      const result = assignmentSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Tenggat waktu pengerjaan tugas wajib diisi');
      }
    });

    it('fails when max_score is greater than 100', () => {
      const invalid = {
        class_id: '123e4567-e89b-12d3-a456-426614174001',
        subject_id: '123e4567-e89b-12d3-a456-426614174002',
        title: 'Tugas Nilai Berlebih',
        due_at: '2026-10-20T23:59',
        max_score: 150,
      };
      const result = assignmentSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Nilai maksimal 100');
      }
    });
  });

  describe('LMS Status Constants & Helpers', () => {
    it('defines valid submission status labels and styling', () => {
      expect(SUBMISSION_STATUS_LABELS.menunggu_penilaian).toBe('Menunggu Penilaian');
      expect(SUBMISSION_STATUS_LABELS.dinilai).toBe('Sudah Dinilai');

      expect(SUBMISSION_STATUS_COLORS.menunggu_penilaian.badge).toContain('amber');
      expect(SUBMISSION_STATUS_COLORS.dinilai.badge).toContain('emerald');
    });

    it('defines valid submission timing labels and styling', () => {
      expect(SUBMISSION_TIMING_LABELS.tepat_waktu).toBe('Tepat Waktu');
      expect(SUBMISSION_TIMING_LABELS.terlambat).toBe('Terlambat');

      expect(SUBMISSION_TIMING_COLORS.tepat_waktu.badge).toContain('blue');
      expect(SUBMISSION_TIMING_COLORS.terlambat.badge).toContain('rose');
    });
  });

  describe('academicApi LMS Methods', () => {
    it('calls GET /materials with filter parameters', async () => {
      const mockMaterials = {
        data: [{ id: 'mat-1', title: 'Materi Bab 1' }],
        pagination: { page: 1, limit: 15, total: 1, totalPages: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockMaterials });

      const res = await academicApi.listMaterials({ class_id: 'cls-1', subject_id: 'sub-1' });

      expect(apiClient.get).toHaveBeenCalledWith('/materials', {
        params: { class_id: 'cls-1', subject_id: 'sub-1' },
      });
      expect(res.data.length).toBe(1);
      expect(res.data[0].title).toBe('Materi Bab 1');
    });

    it('calls GET /materials/{id} to get material detail', async () => {
      const mockDetail = { id: 'mat-1', title: 'Materi Detail' };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockDetail } });

      const res = await academicApi.getMaterial('mat-1');

      expect(apiClient.get).toHaveBeenCalledWith('/materials/mat-1');
      expect(res.id).toBe('mat-1');
    });

    it('calls POST /materials with FormData when file provided', async () => {
      const fakeFile = new File(['material bytes'], 'materi.pdf', { type: 'application/pdf' });
      const payload = {
        class_id: 'cls-1',
        subject_id: 'sub-1',
        title: 'Materi PDF',
        file: fakeFile,
      };
      const mockCreated = { id: 'mat-created-1', title: 'Materi PDF' };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.createMaterial(payload);

      expect(apiClient.post).toHaveBeenCalledWith(
        '/materials',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      expect(res.id).toBe('mat-created-1');
    });

    it('calls POST /materials with JSON when no raw file provided', async () => {
      const payload = {
        class_id: 'cls-1',
        subject_id: 'sub-1',
        title: 'Materi Teks Tanpa Berkas',
        description: 'Ringkasan catatan',
      };
      const mockCreated = { id: 'mat-created-json', ...payload };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.createMaterial(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/materials', payload);
      expect(res.id).toBe('mat-created-json');
    });

    it('calls PUT /materials/{id} to update material', async () => {
      const payload = { title: 'Materi Diperbarui' };
      const mockUpdated = { id: 'mat-1', title: 'Materi Diperbarui' };
      vi.spyOn(apiClient, 'put').mockResolvedValue({ data: { data: mockUpdated } });

      const res = await academicApi.updateMaterial('mat-1', payload);

      expect(apiClient.put).toHaveBeenCalledWith('/materials/mat-1', payload);
      expect(res.title).toBe('Materi Diperbarui');
    });

    it('calls DELETE /materials/{id} to remove material', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: { success: true } });

      await academicApi.deleteMaterial('mat-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/materials/mat-1');
    });

    it('calls GET /assignments with filter parameters', async () => {
      const mockAssignments = {
        data: [{ id: 'asg-1', title: 'Tugas Aljabar 1' }],
        pagination: { page: 1, limit: 15, total: 1, totalPages: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockAssignments });

      const res = await academicApi.listAssignments({ class_id: 'cls-1' });

      expect(apiClient.get).toHaveBeenCalledWith('/assignments', {
        params: { class_id: 'cls-1' },
      });
      expect(res.data.length).toBe(1);
    });

    it('calls GET /assignments/{id} to retrieve assignment detail', async () => {
      const mockDetail = { id: 'asg-1', title: 'Tugas Detail', max_score: 100 };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockDetail } });

      const res = await academicApi.getAssignment('asg-1');

      expect(apiClient.get).toHaveBeenCalledWith('/assignments/asg-1');
      expect(res.id).toBe('asg-1');
    });

    it('calls POST /assignments with FormData when file provided', async () => {
      const fakeFile = new File(['assignment sheet'], 'soal.pdf', { type: 'application/pdf' });
      const payload = {
        class_id: 'cls-1',
        subject_id: 'sub-1',
        title: 'Tugas Lembar Kerja',
        due_at: '2026-10-25 23:59:00',
        file: fakeFile,
      };
      const mockCreated = { id: 'asg-created-1', title: 'Tugas Lembar Kerja' };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.createAssignment(payload);

      expect(apiClient.post).toHaveBeenCalledWith(
        '/assignments',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      expect(res.id).toBe('asg-created-1');
    });

    it('calls PUT /assignments/{id} to update assignment', async () => {
      const payload = { title: 'Tugas Diperbarui', max_score: 95 };
      const mockUpdated = { id: 'asg-1', title: 'Tugas Diperbarui', max_score: 95 };
      vi.spyOn(apiClient, 'put').mockResolvedValue({ data: { data: mockUpdated } });

      const res = await academicApi.updateAssignment('asg-1', payload);

      expect(apiClient.put).toHaveBeenCalledWith('/assignments/asg-1', payload);
      expect(res.title).toBe('Tugas Diperbarui');
    });

    it('calls DELETE /assignments/{id} to remove assignment', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: { success: true } });

      await academicApi.deleteAssignment('asg-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/assignments/asg-1');
    });

    it('calls GET /assignments/{id}/submissions to list student submissions', async () => {
      const mockSubmissions = [
        { id: 'sub-1', student_id: 'std-1', score: 90, status: 'dinilai' as const },
      ];
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockSubmissions } });

      const res = await academicApi.listSubmissions('asg-1');

      expect(apiClient.get).toHaveBeenCalledWith('/assignments/asg-1/submissions');
      expect(res.length).toBe(1);
      expect(res[0].id).toBe('sub-1');
    });

    it('calls POST /assignments/{id}/submissions for student submission upload', async () => {
      const fakeAnswer = new File(['answer sheet'], 'jawaban.pdf', { type: 'application/pdf' });
      const payload = { file: fakeAnswer, notes: 'Jawaban saya' };
      const mockResponse = { id: 'sub-created-1', notes: 'Jawaban saya' };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockResponse } });

      const res = await academicApi.submitAssignment('asg-1', payload);

      expect(apiClient.post).toHaveBeenCalledWith(
        '/assignments/asg-1/submissions',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      expect(res.id).toBe('sub-created-1');
    });

    it('calls PATCH /assignments/{id}/submissions/{subId}/grade for teacher grading', async () => {
      const payload = { score: 92.5, feedback: 'Bagus sekali!' };
      const mockResponse = { id: 'sub-1', score: 92.5, feedback: 'Bagus sekali!' };
      vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { data: mockResponse } });

      const res = await academicApi.gradeSubmission('asg-1', 'sub-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith(
        '/assignments/asg-1/submissions/sub-1/grade',
        payload
      );
      expect(res.score).toBe(92.5);
      expect(res.feedback).toBe('Bagus sekali!');
    });
  });
});




