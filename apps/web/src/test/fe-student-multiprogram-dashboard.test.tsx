import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StudentDashboardView } from '@/features/portal/student-dashboard-view';
import * as StudentDataHooks from '@/lib/use-student-data';

vi.mock('@/lib/auth-context', () => ({
  useAuth: () => ({
    user: { full_name: 'Ahmad Siswa', roles: ['student'] },
    logout: vi.fn(),
  }),
}));

function renderWithClient(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe('FE-PORTAL-STUDENT-02B: Multi-Program Student Dashboard Widgets', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(StudentDataHooks, 'useStudentTasks').mockReturnValue({
      data: [],
      isLoading: false,
    } as any);
    vi.spyOn(StudentDataHooks, 'useSchoolAnnouncements').mockReturnValue({
      data: [],
      isLoading: false,
    } as any);
  });

  it('renders Tahfidz widget with Juz progress and tasmi details for tahfidz students', () => {
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({
      data: {
        name: 'Ahmad Hafizh',
        nis: '26102',
        nisn: '0099002102',
        className: 'VII-B Tahfidz',
        programType: 'tahfidz',
        programBadge: "Siswa Program Tahfidz Al-Qur'an",
        programProfile: {
          target_juz: 3,
          current_juz: 2,
          current_surah: 'Al-Mulk: 1-30',
          last_tasmi_grade: 'Mumtaz',
          tasmi_evaluator: 'Ustadz Abdullah, S.Pd.I',
        },
        todaySchedule: [],
        upcomingExams: [],
        attendanceRate: 98,
        totalAchievements: 2,
        photoFileId: null,
        photoUrl: null,
      },
      isLoading: false,
      isError: false,
    } as any);

    renderWithClient(<StudentDashboardView />);

    expect(screen.getByText("Siswa Program Tahfidz Al-Qur'an")).toBeInTheDocument();
    expect(screen.getByText(/2 dari 3 Juz/i)).toBeInTheDocument();
    expect(screen.getByText('Al-Mulk: 1-30')).toBeInTheDocument();
    expect(screen.getByText(/Mumtaz/i)).toBeInTheDocument();
  });

  it('renders ICP widget with Cambridge Checkpoint and Vocab of the Day for ICP students', () => {
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({
      data: {
        name: 'Valerie Cambridge',
        nis: '26103',
        nisn: '0099002103',
        className: 'VII-C ICP',
        programType: 'icp',
        programBadge: 'International Class Program (Cambridge)',
        programProfile: {
          cambridge_candidate_id: 'ID-MAL-2026-042',
          checkpoint_readiness: {
            english: { score: 4.8, status: 'Ready' },
            math: { score: 5.2, status: 'Exceeding' },
            science: { score: 4.5, status: 'Ready' },
          },
          vocab_of_day: {
            word: 'Photosynthesis',
            definition: 'Process by green plants to synthesize nutrients.',
          },
        },
        todaySchedule: [],
        upcomingExams: [],
        attendanceRate: 95,
        totalAchievements: 3,
        photoFileId: null,
        photoUrl: null,
      },
      isLoading: false,
      isError: false,
    } as any);

    renderWithClient(<StudentDashboardView />);

    expect(screen.getByText('International Class Program (Cambridge)')).toBeInTheDocument();
    expect(screen.getByText('Photosynthesis')).toBeInTheDocument();
    expect(screen.getByText(/Process by green plants/i)).toBeInTheDocument();
  });

  it('renders Inklusi widget with GPK card, PPI milestone, and mood check-in for Inklusi students', () => {
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({
      data: {
        name: 'Rian Ananda',
        nis: '26104',
        nisn: '0099002104',
        className: 'VII-D Inklusi',
        programType: 'inklusi',
        programBadge: 'Siswa Program Inklusi',
        programProfile: {
          gpk: {
            name: 'Ibu Ratna Dewi, S.Pd (GPK)',
            contact_phone: '081234567890',
            room: 'Ruang Sumber Inklusi',
          },
          ppi_milestones: [
            { id: 'm1', title: 'Menyelesaikan modul membaca terarah', is_completed: true },
          ],
        },
        todaySchedule: [],
        upcomingExams: [],
        attendanceRate: 100,
        totalAchievements: 1,
        photoFileId: null,
        photoUrl: null,
      },
      isLoading: false,
      isError: false,
    } as any);

    renderWithClient(<StudentDashboardView />);

    expect(screen.getByText('Siswa Program Inklusi')).toBeInTheDocument();
    expect(screen.getByText('Ibu Ratna Dewi, S.Pd (GPK)')).toBeInTheDocument();
    expect(screen.getByText('Menyelesaikan modul membaca terarah')).toBeInTheDocument();
    expect(screen.getByText(/Perasaan Hari Ini/i)).toBeInTheDocument();
  });

  it('renders Reguler widget with KKTP summary and P5 project tracker for Reguler students', () => {
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({
      data: {
        name: 'Bintang Pratama',
        nis: '26101',
        nisn: '0099002101',
        className: 'VII-A Reguler',
        programType: 'reguler',
        programBadge: 'Siswa Program Reguler',
        programProfile: {
          kktp_summary: {
            total_subjects: 11,
            tuntas_count: 10,
            remedial_count: 1,
            average_score: 86.4,
          },
          p5_project: {
            title: 'Pengolahan Sampah Organik Sekolah',
            theme: 'Kearifan Lokal',
            phase: 'Aksi Nyata',
            group_name: 'Kelompok Cendekia 3',
            deadline: '2026-11-20',
          },
        },
        todaySchedule: [],
        upcomingExams: [],
        attendanceRate: 96,
        totalAchievements: 4,
        photoFileId: null,
        photoUrl: null,
      },
      isLoading: false,
      isError: false,
    } as any);

    renderWithClient(<StudentDashboardView />);

    expect(screen.getByText('Siswa Program Reguler')).toBeInTheDocument();
    expect(screen.getByText(/Pengolahan Sampah Organik Sekolah/i)).toBeInTheDocument();
    expect(screen.getByText(/Kelompok Cendekia 3/i)).toBeInTheDocument();
    expect(screen.getByText(/10 dari 11 Mapel Tuntas/i)).toBeInTheDocument();
  });
});
