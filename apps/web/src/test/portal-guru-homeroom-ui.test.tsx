import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import TeacherHomeroomPage from '@/app/(portal)/portal/guru/wali-kelas/page';
import TeacherFacilitiesPage from '@/app/(portal)/portal/guru/fasilitas/page';
import { guruApi } from '@/features/portal/guru';

// Mock auth provider
vi.mock('@/providers/auth-provider', () => ({
  useAuth: vi.fn(() => ({
    user: {
      id: 'usr-walas-1',
      name: 'Wali Kelas 7-A',
      roles: ['teacher', 'homeroom'],
      profile: { isHomeroom: true },
    },
  })),
}));

describe('Portal Guru Homeroom & Facilities UI Integration (P1-01)', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    vi.clearAllMocks();
    queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
        mutations: { retry: false },
      },
    });
  });

  const renderWithQuery = (ui: React.ReactElement) => {
    return render(
      <QueryClientProvider client={queryClient}>
        {ui}
      </QueryClientProvider>
    );
  };

  describe('TeacherHomeroomPage', () => {
    beforeEach(() => {
      vi.spyOn(guruApi, 'getHomeroomData').mockResolvedValue({
        classId: 'cls-7a',
        className: '7-A Unggulan',
        academicYear: '2026/2027',
        semester: 'Ganjil',
        totalStudents: 32,
        attendanceRecap: {
          presentRate: 97.5,
          averageRate: 97.5,
          sickDays: 4,
          leaveDays: 2,
          unexcusedDays: 0,
        },
        characterRecapCount: 32,
        isReportCardLocked: false,
        students: [
          {
            id: 'std-1',
            nisn: '0012345678',
            nis: '24001',
            name: 'Ahmad Dahlan',
            averageScore: 88.5,
            attendanceRate: 98,
            characterScore: 'Sangat Baik',
            rank: 1,
          },
          {
            id: 'std-2',
            nisn: '0012345679',
            nis: '24002',
            name: 'Bunga Citra',
            averageScore: 85.0,
            attendanceRate: 96,
            characterScore: 'Baik',
            rank: 2,
          },
        ],
      } as any);

      vi.spyOn(guruApi, 'getHomeroomLeaveRequests').mockResolvedValue([
        {
          id: 'lr-1',
          student_id: 'std-1',
          leave_type: 'sakit',
          start_date: '2026-10-24',
          end_date: '2026-10-25',
          reason: 'Demam tinggi',
          status: 'menunggu',
          student: {
            id: 'std-1',
            full_name: 'Ahmad Dahlan',
            nis: '24001',
            nisn: '0012345678',
          },
        },
      ]);
    });

    it('renders tabs: Ringkasan, Perizinan, and Poin Siswa', async () => {
      renderWithQuery(<TeacherHomeroomPage />);

      expect(screen.getByText(/Manajemen Wali Kelas/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Ringkasan & E-Rapor/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Perizinan Siswa/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Poin Siswa & BK/i })).toBeInTheDocument();
    });

    it('navigates to Perizinan tab and displays leave requests list with action buttons', async () => {
      renderWithQuery(<TeacherHomeroomPage />);

      const perizinanTab = screen.getByRole('button', { name: /Perizinan Siswa/i });
      fireEvent.click(perizinanTab);

      await waitFor(() => {
        expect(screen.getByText('Ahmad Dahlan')).toBeInTheDocument();
        expect(screen.getByText(/Demam tinggi/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Setujui/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Tolak/i })).toBeInTheDocument();
      });
    });

    it('opens modal on Setujui click and submits decision via API', async () => {
      const decideSpy = vi.spyOn(guruApi, 'decideHomeroomLeaveRequest').mockResolvedValue({
        id: 'lr-1',
        status: 'disetujui',
      } as any);

      renderWithQuery(<TeacherHomeroomPage />);

      // Switch to Perizinan
      fireEvent.click(screen.getByRole('button', { name: /Perizinan Siswa/i }));

      await waitFor(() => {
        expect(screen.getByRole('button', { name: /Setujui/i })).toBeInTheDocument();
      });

      // Click Setujui
      fireEvent.click(screen.getByRole('button', { name: /Setujui/i }));

      // Expect decision modal to open
      expect(screen.getByText('Setujui Permohonan Izin')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Konfirmasi Keputusan/i })).toBeInTheDocument();

      // Click Konfirmasi
      fireEvent.click(screen.getByRole('button', { name: /Konfirmasi Keputusan/i }));

      await waitFor(() => {
        expect(decideSpy).toHaveBeenCalledWith('lr-1', {
          decision: 'approved',
          decision_notes: undefined,
        });
      });
    });

    it('navigates to Poin Siswa & BK tab and records student point', async () => {
      const recordPointSpy = vi.spyOn(guruApi, 'recordStudentPoint').mockResolvedValue({
        success: true,
        message: 'Poin prestasi berhasil dicatat.',
      });

      renderWithQuery(<TeacherHomeroomPage />);

      // Switch to Poin tab
      fireEvent.click(screen.getByRole('button', { name: /Poin Siswa & BK/i }));

      await waitFor(() => {
        expect(screen.getByText(/Pencatatan Poin Karakter Siswa/i)).toBeInTheDocument();
        expect(screen.getByText(/Rujukan Bimbingan & Konseling/i)).toBeInTheDocument();
        expect(screen.getAllByText('Ahmad Dahlan (24001)').length).toBeGreaterThanOrEqual(1);
      });

      // Select student
      const studentSelect = screen.getByLabelText(/Pilih Siswa \(Poin Karakter\)/i);
      fireEvent.change(studentSelect, { target: { value: 'std-1' } });

      // Click submit
      const submitBtn = screen.getByRole('button', { name: /Simpan Poin Siswa/i });
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(recordPointSpy).toHaveBeenCalledWith(
          expect.objectContaining({
            student_id: 'std-1',
            kind: 'prestasi',
          })
        );
      });
    });
  });

  describe('TeacherFacilitiesPage', () => {
    beforeEach(() => {
      vi.spyOn(guruApi, 'getFacilityBookings').mockResolvedValue([
        {
          id: 'bk-1',
          facility: 'Lab Komputer 1',
          facility_id: 'fac-1',
          start_time: '2026-10-25T08:00:00Z',
          end_time: '2026-10-25T10:00:00Z',
          date: '2026-10-25',
          purpose: 'Praktikum Informatika Kelas 7-A',
          status: 'disetujui',
          created_at: '2026-10-24T00:00:00Z',
        },
      ]);
    });

    it('renders bookings list and submission forms', async () => {
      renderWithQuery(<TeacherFacilitiesPage />);

      expect(screen.getByText(/Peminjaman Fasilitas & Tiket Sarpras/i)).toBeInTheDocument();
      expect(screen.getByText(/Form Reservasi Fasilitas/i)).toBeInTheDocument();

      await waitFor(() => {
        expect(screen.getByText('Lab Komputer 1')).toBeInTheDocument();
        expect(screen.getByText(/"Praktikum Informatika Kelas 7-A"/i)).toBeInTheDocument();
      });
    });

    it('submits a new facility booking via API', async () => {
      const createBookingSpy = vi.spyOn(guruApi, 'createFacilityBooking').mockResolvedValue({
        id: 'bk-2',
        facility_id: '00000000-0000-0000-0000-000000000001',
        start_time: '2026-10-26T08:00:00',
        end_time: '2026-10-26T10:00:00',
        purpose: 'Simulasi CBT',
        status: 'menunggu',
        created_at: '2026-10-24T00:00:00Z',
      });

      renderWithQuery(<TeacherFacilitiesPage />);

      // Fill in booking form
      const dateInput = screen.getByLabelText(/Tanggal Kegiatan/i);
      fireEvent.change(dateInput, { target: { value: '2026-10-26' } });

      const purposeInput = screen.getByLabelText(/Tujuan Peminjaman/i);
      fireEvent.change(purposeInput, { target: { value: 'Simulasi CBT' } });

      const submitBtn = screen.getByRole('button', { name: /Ajukan Peminjaman/i });
      fireEvent.click(submitBtn);

      await waitFor(() => {
        expect(createBookingSpy).toHaveBeenCalledWith(
          expect.objectContaining({
            purpose: 'Simulasi CBT',
            start_at: '2026-10-26T08:00:00',
            end_at: '2026-10-26T10:00:00',
          })
        );
      });
    });
  });
});
