import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import TeacherAttendanceSessionPage from '@/app/(portal)/portal/guru/presensi/page';
import * as portalGuruFeatures from '@/features/portal/guru';

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

const mockSubjects = [
  { id: 'sbj-uuid-mtk', name: 'Matematika', code: 'MTK', category: 'wajib' },
];

const mockClasses = [
  { id: 'cls-uuid-7a', name: 'Kelas 7-A', grade_level: 'VII', academic_year: '2026/2027 Ganjil' },
];

const mockSchedules = [
  {
    id: 'sch-uuid-1',
    subjectId: 'sbj-uuid-mtk',
    classId: 'cls-uuid-7a',
    subjectName: 'Matematika',
    className: 'Kelas 7-A',
  },
];

const mockStudents = [
  {
    studentId: 'stu-uuid-1',
    studentName: 'Ahmad Fauzi',
    nisn: '0012345678',
    nis: '7A001',
    status: 'present' as const,
    notes: '',
  },
  {
    studentId: 'stu-uuid-2',
    studentName: 'Budi Santoso',
    nisn: '0012345679',
    nis: '7A002',
    status: 'sick' as const,
    notes: 'Surat dokter terlampir',
  },
  {
    studentId: 'stu-uuid-3',
    studentName: 'Cindy Claudia',
    nisn: '0012345680',
    nis: '7A003',
    status: 'permission' as const,
    notes: '',
  },
  {
    studentId: 'stu-uuid-4',
    studentName: 'Doni Pratama',
    nisn: '0012345681',
    nis: '7A004',
    status: 'unexcused' as const,
    notes: '',
  },
];

describe('FIX-GURU-02: Kontras Lembar Presensi Siswa Sesi KBM', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    vi.spyOn(portalGuruFeatures, 'useTeacherAssignmentsSummary').mockReturnValue({
      subjects: mockSubjects as any,
      getClassesForSubject: () => mockClasses as any,
      schedules: mockSchedules as any,
      isLoading: false,
    } as any);

    vi.spyOn(portalGuruFeatures, 'useAttendanceStudents').mockReturnValue({
      data: mockStudents as any,
      isLoading: false,
    } as any);
  });

  it('AC1: seluruh tombol status aktif menggunakan kelas latar gelap dengan teks putih berkontras tinggi (>= 4.5:1 WCAG AA)', async () => {
    renderWithClient(<TeacherAttendanceSessionPage />);

    await waitFor(() => {
      expect(screen.getByText('Ahmad Fauzi')).toBeInTheDocument();
      expect(screen.getByText('Budi Santoso')).toBeInTheDocument();
    });

    // 1. Hadir aktif (Ahmad Fauzi): harus menggunakan bg-emerald-700 text-white (rasio ~5.67:1)
    const presentBtn = screen.getAllByRole('button', { name: 'Hadir' })[0];
    expect(presentBtn.className).toContain('bg-emerald-700');
    expect(presentBtn.className).toContain('text-white');

    // 2. Sakit aktif (Budi Santoso): HARUS menggunakan bg-amber-700 text-white (rasio ~4.71:1) dan BUKAN bg-amber-500 (rasio 2.1:1)!
    const sickBtn = screen.getAllByRole('button', { name: 'Sakit' })[1];
    expect(sickBtn.className).toContain('bg-amber-700');
    expect(sickBtn.className).toContain('text-white');
    expect(sickBtn.className).not.toContain('bg-amber-500');

    // 3. Izin aktif (Cindy Claudia): harus menggunakan bg-blue-700 text-white (rasio ~6.54:1)
    const permissionBtn = screen.getAllByRole('button', { name: 'Izin' })[2];
    expect(permissionBtn.className).toContain('bg-blue-700');
    expect(permissionBtn.className).toContain('text-white');

    // 4. Alpa aktif (Doni Pratama): harus menggunakan bg-rose-700 text-white (rasio ~6.16:1)
    const unexcusedBtn = screen.getAllByRole('button', { name: 'Alpa' })[3];
    expect(unexcusedBtn.className).toContain('bg-rose-700');
    expect(unexcusedBtn.className).toContain('text-white');
  });

  it('AC1 & AC2: tombol inaktif menggunakan teks gelap (text-slate-700) di atas latar terang', async () => {
    renderWithClient(<TeacherAttendanceSessionPage />);

    await waitFor(() => {
      expect(screen.getByText('Ahmad Fauzi')).toBeInTheDocument();
    });

    // Tombol Sakit pada baris Ahmad Fauzi (karena Ahmad berstatus Hadir) adalah inaktif
    const inactiveSickBtn = screen.getAllByRole('button', { name: 'Sakit' })[0];
    expect(inactiveSickBtn.className).toContain('text-slate-700');
    expect(inactiveSickBtn.className).not.toContain('text-white');
  });

  it('AC2: badge ringkasan statistik menggunakan token warna gelap yang memenuhi rasio kontras WCAG AA', async () => {
    renderWithClient(<TeacherAttendanceSessionPage />);

    await waitFor(() => {
      expect(screen.getByText(/Hadir: 1/)).toBeInTheDocument();
      expect(screen.getByText(/Sakit: 1/)).toBeInTheDocument();
      expect(screen.getByText(/Izin: 1/)).toBeInTheDocument();
      expect(screen.getByText(/Alpa: 1/)).toBeInTheDocument();
    });

    const hadirBadge = screen.getByText(/Hadir: 1/);
    expect(hadirBadge.className).toContain('text-emerald-800');
    expect(hadirBadge.className).toContain('bg-emerald-50');

    const sakitBadge = screen.getByText(/Sakit: 1/);
    expect(sakitBadge.className).toContain('text-amber-800');
    expect(sakitBadge.className).toContain('bg-amber-50');

    const izinBadge = screen.getByText(/Izin: 1/);
    expect(izinBadge.className).toContain('text-blue-800');
    expect(izinBadge.className).toContain('bg-blue-50');

    const alpaBadge = screen.getByText(/Alpa: 1/);
    expect(alpaBadge.className).toContain('text-rose-800');
    expect(alpaBadge.className).toContain('bg-rose-50');
  });

  it('AC3: tidak ada regresi pada interaktivitas tombol status dan fitur Set Semua Hadir', async () => {
    renderWithClient(<TeacherAttendanceSessionPage />);

    await waitFor(() => {
      expect(screen.getByText('Ahmad Fauzi')).toBeInTheDocument();
    });

    // Ubah status Ahmad Fauzi menjadi Sakit
    const ahmadSickBtn = screen.getAllByRole('button', { name: 'Sakit' })[0];
    fireEvent.click(ahmadSickBtn);

    // Ahmad Fauzi sekarang berstatus Sakit dan tombolnya aktif berkelas bg-amber-700 text-white
    expect(ahmadSickBtn.className).toContain('bg-amber-700');
    expect(ahmadSickBtn.className).toContain('text-white');

    // Klik tombol Set Semua Hadir
    const setAllPresentBtn = screen.getByRole('button', { name: /Set Semua Hadir/i });
    fireEvent.click(setAllPresentBtn);

    // Semua baris sekarang aktif Hadir
    const allPresentBtns = screen.getAllByRole('button', { name: 'Hadir' });
    allPresentBtns.forEach((btn) => {
      expect(btn.className).toContain('bg-emerald-700');
      expect(btn.className).toContain('text-white');
    });
  });
});
