import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { TeacherDetailDialog } from '@/features/master/teacher-detail-dialog';
import { TeacherItem } from '@/types/master';

vi.mock('@/features/master/teachers-api', () => ({
  teachersApi: {
    listSalarySlips: vi.fn().mockResolvedValue([
      {
        id: 'slip-101',
        teacher_id: 'tch-1',
        slip_number: 'PAY-2026/10/001',
        period_month: 10,
        period_year: 2026,
        period_formatted: 'Oktober 2026',
        basic_salary: 4500000,
        allowances: 1500000,
        deductions: 200000,
        net_salary: 5800000,
        status: 'published',
        notes: 'Bank Jatim Syariah',
        file_id: 'file-1',
      },
    ]),
    deleteSalarySlip: vi.fn().mockResolvedValue({}),
  },
}));

describe('TeacherDetailDialog Component (Rekap Jam Mengajar & Slip Gaji)', () => {
  const mockTeacher: TeacherItem = {
    id: 'tch-1',
    full_name: 'Budi Santoso, S.Kom., M.T.',
    nip: '198503152009041001',
    nuptk: '1234567890123456',
    position: 'Guru Informatika',
    status: 'tetap',
    user: {
      id: 'usr-1',
      username: 'budi_santoso',
      email: 'budi@labschool.sch.id',
    },
    homeroomClasses: [
      { id: 'cls-1', name: 'X-MIPA-1', academic_year_id: 'ay-1' },
    ],
    teachingAssignments: [
      {
        id: 'ta-1',
        subject: { id: 'sub-1', name: 'Informatika', code: 'INF-07' },
        class: { id: 'cls-1', name: 'VII-A' },
        hours_per_week: 14,
      },
      {
        id: 'ta-2',
        subject: { id: 'sub-1', name: 'Informatika', code: 'INF-07' },
        class: { id: 'cls-2', name: 'VII-B' },
        hours_per_week: 12,
      },
    ],
  };

  it('renders teacher profile, JJM total hours (26 JP) and certification status', async () => {
    await React.act(async () => {
      render(
        <TeacherDetailDialog
          isOpen={true}
          onClose={vi.fn()}
          teacher={mockTeacher}
        />
      );
    });

    expect(screen.getByText('Budi Santoso, S.Kom., M.T.')).toBeInTheDocument();
    expect(screen.getByText('198503152009041001')).toBeInTheDocument();
    expect(screen.getByText('budi_santoso')).toBeInTheDocument();

    // JJM rekapitulasi check (14 + 12 = 26 JP)
    expect(screen.getByText('26 JP')).toBeInTheDocument();
    expect(screen.getByText('Memenuhi Beban (≥ 24 JP)')).toBeInTheDocument();
    expect(screen.getByText('Rincian Beban Mata Pelajaran (2 Kelas)')).toBeInTheDocument();
  });

  it('switches to Slip Gaji & Payroll tab and displays salary slips', async () => {
    await React.act(async () => {
      render(
        <TeacherDetailDialog
          isOpen={true}
          onClose={vi.fn()}
          teacher={mockTeacher}
        />
      );
    });

    // Switch to Slip Gaji tab
    const salaryTabBtn = screen.getByText('Slip Gaji & Payroll');
    await React.act(async () => {
      fireEvent.click(salaryTabBtn);
    });

    // Verify upload button
    expect(screen.getByText('Upload Slip Gaji')).toBeInTheDocument();

    // Verify salary slip loaded
    const slipHeading = await screen.findByText('Oktober 2026');
    expect(slipHeading).toBeInTheDocument();
    expect(screen.getByText('PAY-2026/10/001')).toBeInTheDocument();
    expect(screen.getByText('Rp 5.800.000')).toBeInTheDocument();
    expect(screen.getByText('Unduh Slip (PDF)')).toBeInTheDocument();
  });
});
