import { describe, it, expect, vi, beforeEach } from 'vitest';
import { guruApi } from '@/features/portal/guru';
import { apiClient } from '@/lib/api/api-client';

describe('Portal Guru Gap Remediation Suite (Fitur A - E)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Fitur A: Perizinan Siswa oleh Wali Kelas', () => {
    it('fetches homeroom leave requests with filters', async () => {
      const mockLeaveData = [
        {
          id: 'lr-1',
          student_id: 'std-1',
          leave_type: 'sakit',
          start_date: '2026-10-23',
          end_date: '2026-10-24',
          reason: 'Demam tinggi',
          status: 'menunggu',
          student: {
            id: 'std-1',
            full_name: 'Budi Santoso',
            nis: '24001',
            nisn: '0099887711',
          },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockLeaveData },
      } as any);

      const result = await guruApi.getHomeroomLeaveRequests({
        class_id: 'cls-7a',
        status: 'menunggu',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/me/teacher/homeroom/leave-requests', {
        params: { class_id: 'cls-7a', status: 'menunggu' },
      });
      expect(result).toHaveLength(1);
      expect(result[0].reason).toBe('Demam tinggi');
      expect(result[0].status).toBe('menunggu');
    });

    it('decides homeroom leave request with notes', async () => {
      const mockUpdated = {
        id: 'lr-1',
        status: 'disetujui',
        decision_notes: 'Disetujui wali kelas, lekas sembuh.',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: { data: mockUpdated },
      } as any);

      const result = await guruApi.decideHomeroomLeaveRequest('lr-1', {
        decision: 'approved',
        decision_notes: 'Disetujui wali kelas, lekas sembuh.',
      });

      expect(apiClient.patch).toHaveBeenCalledWith('/me/teacher/homeroom/leave-requests/lr-1/decision', {
        decision: 'approved',
        decision_notes: 'Disetujui wali kelas, lekas sembuh.',
      });
      expect(result.status).toBe('disetujui');
    });
  });

  describe('Fitur B: Poin Karakter & Rujukan BK', () => {
    it('records student point by teacher', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          success: true,
          message: 'Poin karakter berhasil dicatat.',
          data: { id: 'pt-1', student_id: 'std-1', points: 10 },
        },
      } as any);

      const result = await guruApi.recordStudentPoint({
        student_id: 'std-1',
        point_type_id: 'type-prestasi-1',
        notes: 'Juara 1 Lomba Sains',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/me/teacher/student-points', {
        student_id: 'std-1',
        point_type_id: 'type-prestasi-1',
        notes: 'Juara 1 Lomba Sains',
      });
      expect(result.success).toBe(true);
      expect(result.message).toContain('berhasil');
    });

    it('creates counseling referral to BK', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          success: true,
          message: 'Rujukan bimbingan konseling berhasil dikirimkan.',
          data: { id: 'ref-1', student_id: 'std-1', status: 'pending' },
        },
      } as any);

      const result = await guruApi.createCounselingReferral({
        student_id: 'std-1',
        reason: 'Siswa tampak murung dan sering terlambat',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/me/teacher/counseling-referrals', {
        student_id: 'std-1',
        reason: 'Siswa tampak murung dan sering terlambat',
      });
      expect(result.success).toBe(true);
    });
  });

  describe('Fitur C: Peminjaman Fasilitas & Tiket Sarpras', () => {
    it('lists facility bookings and creates a new booking', async () => {
      const mockBookings = [
        {
          id: 'bk-1',
          facility_id: 'fac-lab',
          purpose: 'Praktikum Komputer',
          start_time: '2026-10-25 08:00:00',
          end_time: '2026-10-25 10:00:00',
          status: 'menunggu',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockBookings },
      } as any);

      const list = await guruApi.getFacilityBookings({ status: 'menunggu' });
      expect(apiClient.get).toHaveBeenCalledWith('/me/teacher/facility-bookings', {
        params: { status: 'menunggu' },
      });
      expect(list).toHaveLength(1);

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockBookings[0] },
      } as any);

      const created = await guruApi.createFacilityBooking({
        facility_id: 'fac-lab',
        purpose: 'Praktikum Komputer',
        start_time: '2026-10-25 08:00:00',
        end_time: '2026-10-25 10:00:00',
      });
      expect(created.id).toBe('bk-1');
    });

    it('creates a sarpras ticket for facility issues', async () => {
      const mockTicket = {
        id: 'tkt-1',
        ticket_number: 'TKT-20261023-001',
        category: 'it_hardware',
        priority: 'sedang' as const,
        subject: 'Monitor LCD Mati',
        description: 'Monitor nomor 12 di Lab Komputer 1 tidak menyala.',
        location: 'Lab Komputer 1',
        status: 'terbuka' as const,
        created_at: '2026-10-23T08:00:00Z',
        reporter_id: 'user-guru',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockTicket },
      } as any);

      const result = await guruApi.createSarprasTicket({
        category: 'it_hardware',
        priority: 'sedang',
        subject: 'Monitor LCD Mati',
        description: 'Monitor nomor 12 di Lab Komputer 1 tidak menyala.',
        location: 'Lab Komputer 1',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/me/teacher/tickets', {
        category: 'it_hardware',
        priority: 'sedang',
        subject: 'Monitor LCD Mati',
        description: 'Monitor nomor 12 di Lab Komputer 1 tidak menyala.',
        location: 'Lab Komputer 1',
      });
      expect(result.ticket_number).toBe('TKT-20261023-001');
    });
  });

  describe('Fitur D: Bank Soal Mandiri Guru', () => {
    it('fetches questions and creates a direct question', async () => {
      const mockQuestions = [
        {
          id: 'q-1',
          subject_id: 'sbj-mtk',
          type: 'pilihan_ganda',
          prompt: 'Berapakah 2 + 2?',
          options: [{ id: 'a', text: '4' }],
          created_at: '2026-10-23',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockQuestions },
      } as any);

      const questions = await guruApi.getTeacherQuestions({ subject_id: 'sbj-mtk' });
      expect(questions).toHaveLength(1);

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockQuestions[0] },
      } as any);

      const created = await guruApi.createDirectQuestion({
        subject_id: 'sbj-mtk',
        type: 'pilihan_ganda',
        prompt: 'Berapakah 2 + 2?',
        options: [{ id: 'a', text: '4' }],
        answer_key: 'a',
      });

      expect(created.prompt).toBe('Berapakah 2 + 2?');
    });
  });

  describe('Fitur E: Penguncian Ledger Nilai Guru', () => {
    it('locks teacher grade ledger for class and subject', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          success: true,
          message: 'Berhasil mengunci 10 data nilai siswa.',
          count: 10,
        },
      } as any);

      const result = await guruApi.lockTeacherGrades({
        class_id: 'cls-7a',
        subject_id: 'sbj-mtk',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/me/teacher/grades/lock', {
        class_id: 'cls-7a',
        subject_id: 'sbj-mtk',
      });
      expect(result.success).toBe(true);
      expect(result.count).toBe(10);
    });
  });
});
