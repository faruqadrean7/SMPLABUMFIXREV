import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  PROGRESS_REPORT_STATUS_LABELS,
  PROGRESS_REPORT_STATUS_COLORS,
  ProgressReportStatus,
  ProgressReportItem,
} from '@/types/academic';
import { academicApi } from '@/features/academic/academic-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-GRD-03 / BE-GRD-03: E-Progress Report & Rapor Bulanan SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Status Constants & Lifecycle', () => {
    it('defines correct labels and colors for all 4 progress report lifecycle statuses', () => {
      const statuses: ProgressReportStatus[] = [
        'draft',
        'menunggu_acc',
        'disetujui',
        'terbit',
      ];

      statuses.forEach((st) => {
        expect(PROGRESS_REPORT_STATUS_LABELS[st]).toBeDefined();
        expect(PROGRESS_REPORT_STATUS_COLORS[st]).toBeDefined();
      });

      expect(PROGRESS_REPORT_STATUS_LABELS.draft).toBe('Draf');
      expect(PROGRESS_REPORT_STATUS_LABELS.menunggu_acc).toBe('Menunggu ACC');
      expect(PROGRESS_REPORT_STATUS_LABELS.disetujui).toBe('Disetujui Kepsek');
      expect(PROGRESS_REPORT_STATUS_LABELS.terbit).toBe('Terbit Resmi');
    });
  });

  describe('academicApi Progress Report Endpoints', () => {
    it('calls GET /progress-reports with filter parameters', async () => {
      const mockList = {
        data: [{ id: 'pr-1', student_id: 'stu-1', status: 'terbit' }],
        meta: { total: 1, current_page: 1, last_page: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockList });

      const res = await academicApi.listProgressReports({
        period_id: 'per-1',
        class_id: 'cls-1',
        status: 'terbit',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/progress-reports', {
        params: { period_id: 'per-1', class_id: 'cls-1', status: 'terbit' },
      });
      expect(res).toEqual(mockList);
    });

    it('calls GET /progress-reports/{id} to get report details', async () => {
      const mockReport: Partial<ProgressReportItem> = {
        id: 'pr-123',
        student_id: 'stu-1',
        period_id: 'per-1',
        status: 'disetujui',
        verify_token: 'token-abc-123',
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockReport } });

      const res = await academicApi.getProgressReport('pr-123');

      expect(apiClient.get).toHaveBeenCalledWith('/progress-reports/pr-123');
      expect(res.id).toBe('pr-123');
      expect(res.status).toBe('disetujui');
    });

    it('calls GET /progress-reports/class-status to get class roster readiness and summary KPIs', async () => {
      const mockClassStatus = {
        class: { id: 'cls-1', name: '7-A' },
        period: { id: 'per-1', month: 8, is_locked: false },
        students: [
          {
            student: { id: 'stu-1', full_name: 'Ahmad Fikri', nis: '001', nisn: '001', gender: 'L' },
            report: { id: 'pr-1', status: 'menunggu_acc', verify_token: 'tok-1' },
            data_readiness: {
              has_grades: true,
              graded_subjects_count: 5,
              has_character_note: true,
              character_note_complete: true,
              attendance_days_count: 20,
              is_ready_to_generate: true,
            },
          },
        ],
        summary: {
          total_students: 1,
          generated_count: 1,
          draft_count: 0,
          menunggu_acc_count: 1,
          disetujui_count: 0,
          terbit_count: 0,
          belum_dibuat_count: 0,
          ready_to_generate_count: 1,
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockClassStatus } });

      const res = await academicApi.getClassProgressReportsStatus('cls-1', 'per-1');

      expect(apiClient.get).toHaveBeenCalledWith('/progress-reports/class-status', {
        params: { class_id: 'cls-1', period_id: 'per-1' },
      });
      expect(res).toEqual(mockClassStatus);
      expect(res.summary.menunggu_acc_count).toBe(1);
    });

    it('calls GET /progress-reports/preview to generate on-the-fly preview snapshot without persisting', async () => {
      const mockSnapshot = {
        student: { id: 'stu-1', full_name: 'Ahmad Fikri' },
        period: { id: 'per-1', month: 8 },
        grades: [{ subject_id: 'sub-1', subject_name: 'Matematika', final_score: 88 }],
        attendance: { hadir: 20, izin: 1, sakit: 0, alpa: 0, total_recorded: 21, percentage: 95.24 },
        character: { scale_data: { mandiri: 'sangat_baik' }, homeroom_note: 'Bagus' },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockSnapshot } });

      const res = await academicApi.previewProgressReport('stu-1', 'per-1');

      expect(apiClient.get).toHaveBeenCalledWith('/progress-reports/preview', {
        params: { student_id: 'stu-1', period_id: 'per-1' },
      });
      expect(res.student.full_name).toBe('Ahmad Fikri');
      expect(res.grades[0].final_score).toBe(88);
    });

    it('calls POST /progress-reports/generate to compile and create snapshot report', async () => {
      const payload = {
        class_id: 'cls-1',
        period_id: 'per-1',
        status: 'menunggu_acc' as const,
      };
      const mockCreated = [{ id: 'pr-1', student_id: 'stu-1', status: 'menunggu_acc' }];
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockCreated } });

      const res = await academicApi.generateProgressReports(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/progress-reports/generate', payload);
      expect(res).toEqual(mockCreated);
    });

    it('calls PATCH /progress-reports/{id}/approve to approve or change status of single report', async () => {
      const payload = {
        status: 'disetujui' as const,
        note: 'Disetujui oleh Kepala Sekolah',
      };
      const mockApproved = { id: 'pr-1', status: 'disetujui' };
      vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { data: mockApproved } });

      const res = await academicApi.approveProgressReport('pr-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/progress-reports/pr-1/approve', payload);
      expect(res.status).toBe('disetujui');
    });

    it('calls POST /progress-reports/bulk-approve for mass approval or publishing', async () => {
      const payload = {
        class_id: 'cls-1',
        period_id: 'per-1',
        status: 'terbit' as const,
      };
      const mockBulkResult = { count: 32, status: 'terbit', items: [] };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockBulkResult } });

      const res = await academicApi.bulkApproveProgressReports(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/progress-reports/bulk-approve', payload);
      expect(res.count).toBe(32);
      expect(res.status).toBe('terbit');
    });

    it('calls GET /progress-reports/{id}/pdf to download PDF stream as blob', async () => {
      const mockBlob = new Blob(['%PDF-1.4'], { type: 'application/pdf' });
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockBlob });

      const res = await academicApi.downloadProgressReportPdf('pr-1');

      expect(apiClient.get).toHaveBeenCalledWith('/progress-reports/pr-1/pdf', {
        responseType: 'blob',
      });
      expect(res).toBe(mockBlob);
    });
  });
});
