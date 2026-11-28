import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import * as StudentDataHooks from '@/lib/use-student-data';
import * as GuardianContext from '@/lib/guardian-context';
import SiswaNilaiPage from '@/app/(portal)/portal/siswa/nilai/page';
import OrtuNilaiPage from '@/app/(portal)/portal/ortu/nilai/page';
import { ReportTemplateDialog } from '@/features/academic/report-cards/report-template-dialog';
import { ReportCardModal } from '@/features/academic/report-cards/report-card-modal';
import { ProgressReportModal } from '@/features/academic/progress-reports/progress-report-modal';
import { academicApi } from '@/features/academic/academic-api';
import { subjectsApi } from '@/features/master/subjects-api';

vi.mock('next/link', () => ({
  default: ({ children, href, ...props }: any) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

vi.mock('@/components/ui/toast', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  },
}));

describe('P1-17: Portal Rapor UI, 4-Level Kurikulum Merdeka Template, & Print Hygiene', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.URL.createObjectURL = vi.fn(() => 'blob:mock-pdf-url');
    window.URL.revokeObjectURL = vi.fn();
  });

  it('P1-17-siswa: SiswaNilaiPage menampilkan tab E-Progress Report & E-Rapor Semester serta unduh PDF', async () => {
    vi.spyOn(StudentDataHooks, 'useStudentGrades').mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as any);

    const listReportCardsSpy = vi.spyOn(academicApi, 'listReportCards').mockResolvedValue({
      data: [
        {
          id: 'rc-1',
          student_id: 'stu-1',
          semester_id: 'sem-1',
          verify_token: 'TOKEN-SEM-001',
          status: 'terbit',
          updated_at: '2026-12-20T08:00:00Z',
          data_snapshot: {
            student: { name: 'Nadia Wardhani', nis: '2026001', nisn: '00991', class_name: 'VIII-A' },
            semester: { name: 'Semester Ganjil 2026/2027', academic_year: '2026/2027' },
            subjects: [],
            attendance: { hadir: 95, sakit: 1, izin: 0, alpha: 0 },
            extracurriculars: [],
            average_score: 89.5,
          },
        } as any,
      ],
      meta: { total: 1, page: 1, per_page: 20, total_pages: 1 },
    } as any);

    const downloadPdfSpy = vi
      .spyOn(academicApi, 'downloadReportCardPdf')
      .mockResolvedValue(new Blob(['%PDF-1.7'], { type: 'application/pdf' }));

    render(<SiswaNilaiPage />);

    const semesterTab = screen.getByRole('button', { name: /E-Rapor Semester/i });
    expect(screen.getByRole('button', { name: /E-Progress Report/i })).toBeTruthy();

    fireEvent.click(semesterTab);

    await waitFor(() => {
      expect(listReportCardsSpy).toHaveBeenCalledTimes(1);
      expect(screen.getByText(/Semester Ganjil 2026\/2027/i)).toBeTruthy();
      expect(screen.getByText(/VIII-A/)).toBeTruthy();
    });

    const downloadBtn = screen.getByRole('button', { name: /Unduh PDF E-Rapor/i });
    fireEvent.click(downloadBtn);

    await waitFor(() => {
      expect(downloadPdfSpy).toHaveBeenCalledWith('rc-1');
    });
  });

  it('P1-17-ortu: OrtuNilaiPage menampilkan tab E-Progress Report dengan student_id anak aktif', async () => {
    vi.spyOn(GuardianContext, 'useGuardian').mockReturnValue({
      guardian: { children: [{ id: 'child-99', name: 'Raka Putra', class_name: 'VII-B' }] },
      activeChild: { id: 'child-99', name: 'Raka Putra', class_name: 'VII-B' },
      activeChildId: 'child-99',
      setActiveChildId: vi.fn(),
      activeGrades: [],
      isLoading: false,
      isError: false,
      refetchAll: vi.fn(),
    } as any);

    const listProgressSpy = vi.spyOn(academicApi, 'listProgressReports').mockResolvedValue({
      data: [
        {
          id: 'pr-99',
          student_id: 'child-99',
          period_id: 'per-1',
          verify_token: 'TOKEN-PR-099',
          status: 'terbit',
          updated_at: '2026-10-15T08:00:00Z',
          data_snapshot: {
            student: { name: 'Raka Putra', nis: '2026099', class_name: 'VII-B' },
            period: { month: 10, semester_name: 'Ganjil 2026/2027', academic_year: '2026/2027' },
            grades: [],
            attendance: { hadir: 20, sakit: 0, izin: 0, alpha: 0 },
            character_notes: [],
          },
        } as any,
      ],
      meta: { total: 1, page: 1, per_page: 20, total_pages: 1 },
    } as any);

    render(<OrtuNilaiPage />);

    const progressTab = screen.getByRole('button', { name: /E-Progress Report/i });
    fireEvent.click(progressTab);

    await waitFor(() => {
      expect(listProgressSpy).toHaveBeenCalledWith({ student_id: 'child-99', per_page: 20 });
      expect(screen.getByText(/E-Progress Report Bulan 10/i)).toBeTruthy();
      expect(screen.getByRole('link', { name: /Verifikasi QR/i }).getAttribute('href')).toBe(
        '/verifikasi/TOKEN-PR-099'
      );
    });
  });

  it('P1-17-template: ReportTemplateDialog mendukung 4 level Kurikulum Merdeka, KKTP, placeholder, tanpa &ge;', async () => {
    vi.spyOn(academicApi, 'listReportDescriptionTemplates').mockResolvedValue({
      data: [],
      meta: { total: 0, page: 1, per_page: 50, total_pages: 0 },
    } as any);
    vi.spyOn(subjectsApi, 'list').mockResolvedValue({
      data: [{ id: 'sub-1', code: 'MTK', name: 'Matematika', subject_group: 'Kelompok A', kktp: 78 }],
    } as any);
    const storeSpy = vi
      .spyOn(academicApi, 'storeReportDescriptionTemplate')
      .mockResolvedValue({} as any);

    const { container } = render(<ReportTemplateDialog isOpen onClose={vi.fn()} />);

    await waitFor(() => {
      expect(screen.getByText(/Template Deskripsi Capaian Pembelajaran \(KKTP\)/i)).toBeTruthy();
    });

    // Pastikan tidak ada entity literal '&ge;' di teks ter-render
    expect(container.textContent).not.toContain('&ge;');

    // Klik chip placeholder {tp_tertinggi} & isi KKTP
    const chip = screen.getByRole('button', { name: '{tp_tertinggi}' });
    fireEvent.click(chip);

    const kktpInput = screen.getByLabelText('Ambang KKTP') as HTMLInputElement;
    fireEvent.change(kktpInput, { target: { value: '82' } });

    const submitBtn = screen.getByRole('button', { name: /Simpan Template/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(storeSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          subject_id: 'sub-1',
          level: 'sangat_baik',
          kktp_threshold: 82,
          template_text: '{tp_tertinggi}',
        })
      );
    });
  });

  it('P1-17-hygiene: ReportCardModal & ProgressReportModal bebas nama kepsek fiktif hardcoded dan memakai print:hidden', () => {
    const progressRender = render(
      <ProgressReportModal
        isOpen
        onClose={vi.fn()}
        report={{
          id: 'pr-1',
          student_id: 'stu-1',
          period_id: 'per-1',
          verify_token: 'VERIFY-PR-1',
          status: 'draft',
          data_snapshot: {
            school_name: 'SMP Lab Nusantara',
            student: { name: 'Nadia', nis: '123', class_name: 'VII-A' },
            period: { label: 'Oktober 2026', month: 10 },
            grades: [],
            attendance: { hadir: 20, sakit: 0, izin: 0, alpha: 0 },
            character_notes: [],
          },
        } as any}
      />
    );

    expect(progressRender.container.textContent).not.toContain('Drs. H. Mulyani, M.Pd.');
    expect(progressRender.container.innerHTML).toContain('print:hidden');

    const cardRender = render(
      <ReportCardModal
        isOpen
        onClose={vi.fn()}
        report={{
          id: 'rc-1',
          student_id: 'stu-1',
          semester_id: 'sem-1',
          verify_token: 'VERIFY-RC-1',
          status: 'draft',
          data_snapshot: {
            school_name: 'SMP Lab Nusantara',
            student: { name: 'Nadia', nis: '123', nisn: '001', class_name: 'VII-A' },
            semester: { name: 'Ganjil 2026/2027', academic_year: '2026/2027' },
            subjects: [
              {
                subject_id: 's1',
                subject_code: 'MTK',
                subject_name: 'Matematika',
                subject_group: 'A',
                final_score: 86,
                level: 'tuntas',
                predicate_level: 'baik',
                predicate_label: 'Baik',
                kktp: 78,
                competency_description: 'Menguasai materi dengan baik.',
              },
            ],
            attendance: { hadir: 95, sakit: 1, izin: 0, alpha: 0 },
            extracurriculars: [],
            average_score: 86,
          },
        } as any}
      />
    );

    expect(cardRender.container.textContent).not.toContain('Kepala Sekolah Labschool UM');
    expect(cardRender.container.textContent).toContain('KKTP 78');
    expect(cardRender.container.innerHTML).toContain('print:hidden');
  });
});
