import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import JurnalPresensiPage from '@/app/(portal)/portal/guru/jurnal/page';
import { lmsApi } from '@/lib/api-client';
import * as useTeacherData from '@/lib/use-teacher-data';

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
}

function renderWithClient(ui: React.ReactElement) {
  const queryClient = createTestQueryClient();
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

const mockSchedules = [
  {
    id: 'sch-uuid-1',
    class_id: 'cls-uuid-7a',
    subject_id: 'sbj-uuid-mtk',
    day_name: 'Senin' as const,
    start_time: '07:00',
    end_time: '08:20',
    room_name: 'Ruang 7-A',
    class_name: '7-A',
    subject_name: 'Matematika',
  },
];

const mockTeacherClasses = [
  { id: 'cls-uuid-7a', name: '7-A', grade_level: 'VII', academic_year: '2026/2027 Ganjil' },
];

const mockRoster = [
  { id: 'stu-uuid-1', nis: '7A001', name: 'Aditya Pratama', gender: 'L' as const, status: 'hadir' as const, note: '' },
  { id: 'stu-uuid-2', nis: '7A002', name: 'Bella Safira', gender: 'P' as const, status: 'hadir' as const, note: '' },
  { id: 'stu-uuid-3', nis: '7A003', name: 'Citra Kirana', gender: 'P' as const, status: 'hadir' as const, note: '' },
];

const mockJournals = [
  {
    id: 'jrn-uuid-1',
    schedule_id: 'sch-uuid-1',
    teacher_id: 'tch-uuid-1',
    class_id: 'cls-uuid-7a',
    subject_id: 'sbj-uuid-mtk',
    meeting_number: 1,
    session_date: '2026-11-05',
    time_range: '07:00 - 08:20',
    room_name: 'Ruang 7-A',
    topic: 'Pengenalan Konsep Aljabar',
    method: 'Problem-Based Learning',
    notes: 'Siswa aktif berdiskusi.',
    attendance_summary: {
      total: 30,
      hadir: 28,
      sakit: 1,
      izin: 1,
      alpa: 0,
      terlambat: 0,
      attendance_rate: 93,
    },
    class: { id: 'cls-uuid-7a', name: '7-A', grade_level: 'VII', academic_year: '2026/2027 Ganjil' },
    subject: { id: 'sbj-uuid-mtk', name: 'Matematika', code: 'MTK', category: 'wajib' as const },
    created_at: '2026-11-05T08:30:00Z',
  },
  {
    id: 'jrn-uuid-2',
    schedule_id: 'sch-uuid-1',
    teacher_id: 'tch-uuid-1',
    class_id: 'cls-uuid-7a',
    subject_id: 'sbj-uuid-mtk',
    meeting_number: 2,
    session_date: '2026-11-06',
    time_range: '07:00 - 08:20',
    room_name: 'Ruang 7-A',
    topic: 'Persamaan Linier Satu Variabel',
    method: 'Diskusi Kelompok & Praktik',
    notes: 'Semua siswa hadir tepat waktu.',
    attendance_summary: {
      total: 30,
      hadir: 30,
      sakit: 0,
      izin: 0,
      alpa: 0,
      terlambat: 0,
      attendance_rate: 100,
    },
    class: { id: 'cls-uuid-7a', name: '7-A', grade_level: 'VII', academic_year: '2026/2027 Ganjil' },
    subject: { id: 'sbj-uuid-mtk', name: 'Matematika', code: 'MTK', category: 'wajib' as const },
    created_at: '2026-11-06T08:30:00Z',
  },
];

describe('FIX-GURU-01: Riwayat Jurnal Mengajar & Presensi Sesi KBM', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    vi.spyOn(lmsApi, 'getSchedules').mockResolvedValue(mockSchedules as any);
    vi.spyOn(lmsApi, 'getJournals').mockResolvedValue(mockJournals as any);
    vi.spyOn(lmsApi, 'getStudentRoster').mockResolvedValue(mockRoster as any);
    vi.spyOn(useTeacherData, 'useTeacherClasses').mockReturnValue({
      data: mockTeacherClasses,
      isLoading: false,
    } as any);
  });

  it('AC1 & AC2: Tab Riwayat menampilkan jumlah Hadir, Sakit, Izin nyata (bukan Hadir 0)', async () => {
    renderWithClient(<JurnalPresensiPage />);

    // Tunggu tab Riwayat dirender
    const riwayatTabButton = await screen.findByRole('button', { name: /Riwayat Jurnal/i });
    expect(riwayatTabButton).toBeInTheDocument();

    fireEvent.click(riwayatTabButton);

    // Verifikasi jurnal pertemuan 1 dan pertemuan 2 tampil
    await waitFor(() => {
      expect(screen.getByText('Pengenalan Konsep Aljabar')).toBeInTheDocument();
      expect(screen.getByText('Persamaan Linier Satu Variabel')).toBeInTheDocument();
    });

    // Pertemuan 1: H: 28, S: 1, I: 1, 93% (bukan H: 0!)
    expect(screen.getByText('H: 28')).toBeInTheDocument();
    expect(screen.getByText('S: 1')).toBeInTheDocument();
    expect(screen.getByText('I: 1')).toBeInTheDocument();
    expect(screen.getByText('93%')).toBeInTheDocument();

    // Pertemuan 2: H: 30, 100% (semua hadir)
    expect(screen.getByText('H: 30')).toBeInTheDocument();
    expect(screen.getByText('100%')).toBeInTheDocument();
  });

  it('AC1 & AC3: Simpan jurnal dengan presensi memanggil createJournalWithAttendance dan memperbarui riwayat', async () => {
    const createSpy = vi.spyOn(lmsApi, 'createJournalWithAttendance').mockResolvedValue({
      id: 'jrn-new',
      schedule_id: 'sch-uuid-1',
      teacher_id: 'tch-uuid-1',
      class_id: 'cls-uuid-7a',
      subject_id: 'sbj-uuid-mtk',
      meeting_number: 3,
      session_date: '2026-11-07',
      time_range: '07:00 - 08:20',
      room_name: 'Ruang 7-A',
      topic: 'Latihan Soal Aljabar Cerita',
      method: 'Problem-Based Learning',
      notes: 'Bagus',
      attendance_summary: {
        total: 3,
        hadir: 2,
        sakit: 1,
        izin: 0,
        alpa: 0,
        terlambat: 0,
        attendance_rate: 67,
      },
      created_at: new Date().toISOString(),
    });

    renderWithClient(<JurnalPresensiPage />);

    // Tunggu data siswa termuat
    await waitFor(() => {
      expect(screen.getByText('Aditya Pratama')).toBeInTheDocument();
      expect(screen.getByText('Bella Safira')).toBeInTheDocument();
      expect(screen.getByText('Citra Kirana')).toBeInTheDocument();
    });

    // Tandai Citra Kirana Sakit
    const sakitButtons = screen.getAllByRole('button', { name: 'S' });
    // Tombol Sakit untuk siswa ke-3 (index 2)
    fireEvent.click(sakitButtons[2]);

    // Simpan jurnal
    const submitButton = screen.getByRole('button', { name: /Simpan Jurnal & Presensi Sesi/i });
    expect(submitButton).toBeInTheDocument();
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(createSpy).toHaveBeenCalledTimes(1);
    });

    const callPayload = createSpy.mock.calls[0][0];
    expect(callPayload.attendances).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ student_id: 'stu-uuid-1', status: 'hadir' }),
        expect.objectContaining({ student_id: 'stu-uuid-2', status: 'hadir' }),
        expect.objectContaining({ student_id: 'stu-uuid-3', status: 'sakit' }),
      ])
    );
  });

  it('AC3: Mendukung penyimpanan saat schedule_id bukan UUID tanpa error di klien', async () => {
    // Schedule lokal tanpa UUID
    vi.spyOn(lmsApi, 'getSchedules').mockResolvedValue([
      {
        id: 'sch-offline-local-01',
        class_id: 'cls-uuid-7a',
        subject_id: 'sbj-uuid-mtk',
        day_name: 'Senin',
        start_time: '07:00',
        end_time: '08:20',
        room_name: 'Ruang 7-A',
        class_name: '7-A',
        subject_name: 'Matematika',
      },
    ] as any);

    const createSpy = vi.spyOn(lmsApi, 'createJournalWithAttendance').mockResolvedValue({
      id: 'jrn-offline-success',
      schedule_id: 'sch-offline-local-01',
      teacher_id: 'tch-uuid-1',
      class_id: 'cls-uuid-7a',
      subject_id: 'sbj-uuid-mtk',
      meeting_number: 1,
      session_date: '2026-11-07',
      time_range: '07:00 - 08:20',
      room_name: 'Ruang 7-A',
      topic: 'Sesi Tambahan Offline',
      method: 'Tatap Muka',
      attendance_summary: {
        total: 3,
        hadir: 3,
        sakit: 0,
        izin: 0,
        alpa: 0,
        terlambat: 0,
        attendance_rate: 100,
      },
      created_at: new Date().toISOString(),
    });

    renderWithClient(<JurnalPresensiPage />);

    await waitFor(() => {
      expect(screen.getByText('Aditya Pratama')).toBeInTheDocument();
    });

    const submitButton = screen.getByRole('button', { name: /Simpan Jurnal & Presensi Sesi/i });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(createSpy).toHaveBeenCalledTimes(1);
      expect(createSpy.mock.calls[0][0].schedule_id).toBe('sch-offline-local-01');
    });
  });

  it('AC4: Menampilkan empty state informatif saat belum ada jurnal', async () => {
    vi.spyOn(lmsApi, 'getJournals').mockResolvedValue([]);

    renderWithClient(<JurnalPresensiPage />);

    const riwayatTabButton = await screen.findByRole('button', { name: /Riwayat Jurnal/i });
    fireEvent.click(riwayatTabButton);

    await waitFor(() => {
      expect(screen.getByText('Belum ada riwayat jurnal mengajar')).toBeInTheDocument();
      expect(screen.getByText(/Jurnal KBM yang Anda simpan akan muncul di sini/i)).toBeInTheDocument();
    });
  });
});
