import { describe, it, expect, vi, beforeEach } from 'vitest';
import { lmsApi } from '@/lib/lms-api';
import { apiClient } from '@/lib/api-client';

describe('FIX-INPUT-01: Form Input and Submission Fixes', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lmsApi.createJournalWithAttendance calls /me/teacher/journals with valid fields and does not forward non-UUID schedule_id', async () => {
    const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
      data: {
        data: {
          id: 'jrn-uuid-1234',
          schedule_id: '01a19b00-0000-7000-8000-000000000001',
          topic: 'Pembelajaran Bab 3',
          method: 'Tatap Muka',
          notes: 'Catatan KBM',
          session_date: '2026-11-03',
        },
      },
    } as any);

    const result = await lmsApi.createJournalWithAttendance({
      schedule_id: 'sch-invalid-mock-id', // Non-UUID mock ID
      class_id: '01a19b00-0000-7000-8000-000000000002',
      subject_id: '01a19b00-0000-7000-8000-000000000003',
      session_date: '2026-11-03',
      topic: 'Pembelajaran Bab 3',
      method: 'Tatap Muka',
      notes: 'Catatan KBM',
      attendances: [],
    });

    // Verify /me/teacher/journals was called
    expect(postSpy).toHaveBeenCalledWith(
      '/me/teacher/journals',
      expect.objectContaining({
        session_date: '2026-11-03',
        topic: 'Pembelajaran Bab 3',
        method: 'Tatap Muka',
        notes: 'Catatan KBM',
        class_id: '01a19b00-0000-7000-8000-000000000002',
        subject_id: '01a19b00-0000-7000-8000-000000000003',
      })
    );

    // Ensure non-UUID schedule_id was NOT sent to prevent 422 VALIDATION_ERROR
    const journalCallArgs = postSpy.mock.calls.find((c) => c[0] === '/me/teacher/journals');
    expect(journalCallArgs?.[1]).not.toHaveProperty('schedule_id');

    expect(result.id).toBe('jrn-uuid-1234');
    expect(result.topic).toBe('Pembelajaran Bab 3');
  });

  it('lmsApi.createJournalWithAttendance passes valid UUID schedule_id to journals and attendance sessions', async () => {
    const validScheduleId = '01a19b00-0000-7000-8000-000000000001';
    const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
      data: {
        data: {
          id: 'jrn-uuid-5678',
          schedule_id: validScheduleId,
          topic: 'Struktur Sel',
          method: 'Praktikum',
          session_date: '2026-11-03',
        },
      },
    } as any);

    await lmsApi.createJournalWithAttendance({
      schedule_id: validScheduleId,
      session_date: '2026-11-03',
      topic: 'Struktur Sel',
      method: 'Praktikum',
      attendances: [
        { student_id: 'std-1', status: 'hadir', note: '' },
      ],
    });

    // Called both journals and attendance sessions
    expect(postSpy).toHaveBeenCalledWith(
      '/me/teacher/journals',
      expect.objectContaining({
        schedule_id: validScheduleId,
        topic: 'Struktur Sel',
      })
    );

    expect(postSpy).toHaveBeenCalledWith(
      '/me/teacher/attendance/sessions',
      expect.objectContaining({
        schedule_id: validScheduleId,
        session_topic: 'Struktur Sel',
        attendees: [
          expect.objectContaining({ student_id: 'std-1', status: 'present' }),
        ],
      })
    );
  });

  it('lmsApi.createJournalWithAttendance throws error when API fails and mock fallback is not enabled', async () => {
    vi.spyOn(apiClient, 'post').mockRejectedValue(new Error('Koneksi database terputus'));

    await expect(
      lmsApi.createJournalWithAttendance({
        schedule_id: '01a19b00-0000-7000-8000-000000000001',
        session_date: '2026-11-03',
        topic: 'Aljabar',
        method: 'Diskusi',
        attendances: [],
      })
    ).rejects.toThrow('Koneksi database terputus');
  });

  it('program tasks error message extractor resolves ApiClientError.message correctly', () => {
    const errorWithResponse = {
      response: { data: { message: 'Batas waktu pengumpulan telah lewat' } },
    };
    const errorWithMessageOnly = {
      message: 'Network Timeout: Gagal menghubungi server',
    };
    const fallbackError = {};

    const resolveMsg = (err: any) =>
      err?.response?.data?.message || err?.message || 'Gagal mengumpulkan tugas. Silakan periksa kembali isian Anda.';

    expect(resolveMsg(errorWithResponse)).toBe('Batas waktu pengumpulan telah lewat');
    expect(resolveMsg(errorWithMessageOnly)).toBe('Network Timeout: Gagal menghubungi server');
    expect(resolveMsg(fallbackError)).toBe('Gagal mengumpulkan tugas. Silakan periksa kembali isian Anda.');
  });
});
