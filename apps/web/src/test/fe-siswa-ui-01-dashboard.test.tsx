import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StudentDashboardView } from '@/features/portal/student-dashboard-view';
import * as StudentDataHooks from '@/lib/use-student-data';

vi.mock('@/lib/auth-context', () => ({
  useAuth: () => ({ user: { full_name: 'Raka Pratama', roles: ['student'] }, logout: vi.fn() }),
}));

function renderWithClient(ui: React.ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

const baseDash = {
  name: 'Raka Pratama', nis: '26001', nisn: '0012', className: 'VII-A',
  programType: 'reguler', todaySchedule: [], upcomingExams: [],
  attendanceRate: 96, totalAchievements: 3, photoFileId: null, photoUrl: null,
};

describe('FE-SISWA-UI-01: playful student dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(StudentDataHooks, 'useSchoolAnnouncements').mockReturnValue({ data: [], isLoading: false } as any);
  });

  it('FE-SISWA-UI-01-dashboard: greets the student by first name with a mascot', () => {
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({ data: baseDash, isLoading: false, isError: false } as any);
    vi.spyOn(StudentDataHooks, 'useStudentTasks').mockReturnValue({ data: [], isLoading: false } as any);
    const { getByText, container } = renderWithClient(<StudentDashboardView />);
    expect(getByText(/Halo, Raka!/)).toBeTruthy();
    expect(container.querySelector('svg')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-dashboard: renders stat tiles from existing data (active tasks + attendance)', () => {
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({ data: baseDash, isLoading: false, isError: false } as any);
    vi.spyOn(StudentDataHooks, 'useStudentTasks').mockReturnValue({
      data: [
        { id: '1', title: 'Tugas A', subjectName: 'MTK', teacherName: 'Bu A', status: 'aktif', dueDate: '2026-11-25', dueTime: '08:00' },
        { id: '2', title: 'Tugas B', subjectName: 'IPA', teacherName: 'Pak B', status: 'aktif', dueDate: '2026-11-26', dueTime: '09:00' },
      ],
      isLoading: false,
    } as any);
    const { getByText } = renderWithClient(<StudentDashboardView />);
    expect(getByText('2')).toBeTruthy();
    expect(getByText('96%')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-dashboard: shows StudentError with retry on dashboard error', () => {
    const refetch = vi.fn();
    vi.spyOn(StudentDataHooks, 'useStudentDashboard').mockReturnValue({ data: undefined, isLoading: false, isError: true, refetch } as any);
    vi.spyOn(StudentDataHooks, 'useStudentTasks').mockReturnValue({ data: [], isLoading: false } as any);
    const { getByText } = renderWithClient(<StudentDashboardView />);
    expect(getByText('Coba lagi')).toBeTruthy();
  });
});
