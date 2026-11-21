import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  REPORT_CARD_STATUS_LABELS,
  REPORT_CARD_STATUS_COLORS,
  ReportCardStatus,
  ReportCardItem,
  ReportDescriptionTemplateItem,
} from '@/types/academic';
import { academicApi } from '@/features/academic/academic-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-GRD-04 / BE-GRD-04: E-Rapor Semester & Template Deskripsi Capaian Pembelajaran SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Status Constants & Lifecycle', () => {
    it('defines correct labels and colors for all 4 semester report card lifecycle statuses', () => {
      const statuses: ReportCardStatus[] = [
        'draft',
        'terkunci',
        'disetujui',
        'terbit',
      ];

      statuses.forEach((st) => {
        expect(REPORT_CARD_STATUS_LABELS[st]).toBeDefined();
        expect(REPORT_CARD_STATUS_COLORS[st]).toBeDefined();
      });

      expect(REPORT_CARD_STATUS_LABELS.draft).toBe('Draf');
      expect(REPORT_CARD_STATUS_LABELS.terkunci).toBe('Terkunci');
      expect(REPORT_CARD_STATUS_LABELS.disetujui).toBe('Disahkan Kepsek');
      expect(REPORT_CARD_STATUS_LABELS.terbit).toBe('Terbit Resmi');
    });
  });

  describe('academicApi Report Description Templates Endpoints', () => {
    it('calls GET /report-description-templates with filters', async () => {
      const mockList = {
        data: [
          {
            id: 'tpl-1',
            subject_id: 'sub-mat',
            level: 'tuntas',
            template_text: 'Sangat menguasai konsep kalkulus dan trigonometri dasar.',
          },
        ],
        meta: { total: 1, current_page: 1, last_page: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockList });

      const res = await academicApi.listReportDescriptionTemplates({
        subject_id: 'sub-mat',
        level: 'tuntas',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/report-description-templates', {
        params: { subject_id: 'sub-mat', level: 'tuntas' },
      });
      expect(res).toEqual(mockList);
    });

    it('calls POST /report-description-templates to store or update template', async () => {
      const mockSaved: Partial<ReportDescriptionTemplateItem> = {
        id: 'tpl-1',
        subject_id: 'sub-mat',
        level: 'tuntas',
        template_text: 'Teks deskripsi baru',
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockSaved } });

      const res = await academicApi.storeReportDescriptionTemplate({
        subject_id: 'sub-mat',
        level: 'tuntas',
        template_text: 'Teks deskripsi baru',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/report-description-templates', {
        subject_id: 'sub-mat',
        level: 'tuntas',
        template_text: 'Teks deskripsi baru',
      });
      expect(res).toEqual(mockSaved);
    });

    it('calls DELETE /report-description-templates/{id} to delete template', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValue({ data: { message: 'Deleted' } });

      await academicApi.deleteReportDescriptionTemplate('tpl-123');

      expect(apiClient.delete).toHaveBeenCalledWith('/report-description-templates/tpl-123');
    });
  });

  describe('academicApi Report Card Endpoints', () => {
    it('calls GET /report-cards with filter parameters', async () => {
      const mockList = {
        data: [{ id: 'rc-1', student_id: 'stu-1', status: 'terbit' }],
        meta: { total: 1, current_page: 1, last_page: 1 },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockList });

      const res = await academicApi.listReportCards({
        semester_id: 'sem-1',
        class_id: 'cls-1',
        status: 'terbit',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/report-cards', {
        params: { semester_id: 'sem-1', class_id: 'cls-1', status: 'terbit' },
      });
      expect(res).toEqual(mockList);
    });

    it('calls GET /report-cards/{id} to get semester report details', async () => {
      const mockReport: Partial<ReportCardItem> = {
        id: 'rc-101',
        student_id: 'stu-1',
        semester_id: 'sem-1',
        status: 'disetujui',
        verify_token: 'token-sem-xyz',
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockReport } });

      const res = await academicApi.getReportCard('rc-101');

      expect(apiClient.get).toHaveBeenCalledWith('/report-cards/rc-101');
      expect(res.id).toBe('rc-101');
      expect(res.status).toBe('disetujui');
    });

    it('calls GET /report-cards/class-status to get class roster readiness and summary KPIs', async () => {
      const mockClassStatus = {
        class: { id: 'cls-1', name: 'X-MIPA-1' },
        semester: { id: 'sem-1', name: 'Semester Ganjil 2026/2027', is_active: true },
        students: [
          {
            student: { id: 'stu-1', full_name: 'Muhammad Danang', nis: '202600101', nisn: '0098765501', gender: 'L' },
            report: { id: 'rc-1', status: 'draft', verify_token: 'tok-danang' },
            data_readiness: {
              has_grades: true,
              graded_subjects_count: 8,
              has_attendance: true,
              attendance_days_count: 55,
              has_character_note: true,
              has_extracurricular: true,
              is_ready_to_generate: true,
            },
          },
        ],
        summary: {
          total_students: 1,
          generated_count: 1,
          draft_count: 1,
          terkunci_count: 0,
          disetujui_count: 0,
          terbit_count: 0,
          belum_dibuat_count: 0,
          ready_to_generate_count: 1,
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockClassStatus } });

      const res = await academicApi.getClassReportCardsStatus('cls-1', 'sem-1');

      expect(apiClient.get).toHaveBeenCalledWith('/report-cards/class-status', {
        params: { class_id: 'cls-1', semester_id: 'sem-1' },
      });
      expect(res).toEqual(mockClassStatus);
      expect(res.summary.draft_count).toBe(1);
    });

    it('calls GET /report-cards/preview to generate on-the-fly preview snapshot without persisting', async () => {
      const mockSnapshot = {
        student: { id: 'stu-1', full_name: 'Muhammad Danang' },
        semester: { id: 'sem-1', name: 'Semester Ganjil' },
        subjects: [
          {
            subject_id: 'sub-mat',
            subject_code: 'MAT-10',
            subject_name: 'Matematika Peminatan',
            final_score: 88,
            level: 'tuntas' as const,
            competency_description: 'Sangat menguasai materi.',
          },
        ],
        attendance: { hadir: 50, izin: 1, sakit: 2, alpa: 0, total_recorded: 53, percentage: 94.34 },
        extracurriculars: [{ name: 'PMR', predicate: 'A' }],
        homeroom_note: 'Pertahankan prestasi belajar.',
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: mockSnapshot } });

      const res = await academicApi.previewReportCard('stu-1', 'sem-1', 'Pertahankan prestasi belajar.');

      expect(apiClient.get).toHaveBeenCalledWith('/report-cards/preview', {
        params: {
          student_id: 'stu-1',
          semester_id: 'sem-1',
          homeroom_note: 'Pertahankan prestasi belajar.',
        },
      });
      expect(res.subjects[0].level).toBe('tuntas');
      expect(res.subjects[0].final_score).toBe(88);
    });

    it('calls POST /report-cards/generate to generate report cards for an entire class', async () => {
      const mockGenerated = [
        { id: 'rc-1', student_id: 'stu-1', status: 'draft' },
        { id: 'rc-2', student_id: 'stu-2', status: 'draft' },
      ];
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockGenerated } });

      const res = await academicApi.generateReportCards({
        class_id: 'cls-1',
        semester_id: 'sem-1',
        status: 'draft',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/report-cards/generate', {
        class_id: 'cls-1',
        semester_id: 'sem-1',
        status: 'draft',
      });
      expect(res).toEqual(mockGenerated);
    });

    it('calls PATCH /report-cards/{id}/lock to lock/unlock report card by curriculum admin', async () => {
      const mockLocked: Partial<ReportCardItem> = {
        id: 'rc-1',
        status: 'terkunci',
        locked_at: '2026-10-13T10:00:00Z',
      };
      vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { data: mockLocked } });

      const res = await academicApi.lockReportCard('rc-1', {
        is_locked: true,
        reason: 'Penutupan nilai semester oleh kurikulum',
      });

      expect(apiClient.patch).toHaveBeenCalledWith('/report-cards/rc-1/lock', {
        is_locked: true,
        reason: 'Penutupan nilai semester oleh kurikulum',
      });
      expect(res.status).toBe('terkunci');
    });

    it('calls PATCH /report-cards/{id}/approve to approve/publish single report card by principal', async () => {
      const mockApproved: Partial<ReportCardItem> = {
        id: 'rc-1',
        status: 'disetujui',
        approved_by: 'usr-kepsek',
      };
      vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { data: mockApproved } });

      const res = await academicApi.approveReportCard('rc-1', {
        status: 'disetujui',
        note: 'Dokumen terverifikasi sah.',
      });

      expect(apiClient.patch).toHaveBeenCalledWith('/report-cards/rc-1/approve', {
        status: 'disetujui',
        note: 'Dokumen terverifikasi sah.',
      });
      expect(res.status).toBe('disetujui');
    });

    it('calls POST /report-cards/bulk-approve to batch approve reports for a class', async () => {
      const mockBulk = {
        count: 2,
        status: 'disetujui',
        items: [{ id: 'rc-1' }, { id: 'rc-2' }],
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockBulk } });

      const res = await academicApi.bulkApproveReportCards({
        class_id: 'cls-1',
        semester_id: 'sem-1',
        status: 'disetujui',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/report-cards/bulk-approve', {
        class_id: 'cls-1',
        semester_id: 'sem-1',
        status: 'disetujui',
      });
      expect(res.count).toBe(2);
      expect(res.status).toBe('disetujui');
    });

    it('calls POST /report-cards/bulk-lock to batch lock/unlock reports for a class', async () => {
      const mockBulkLock = {
        count: 2,
        is_locked: true,
        items: [{ id: 'rc-1' }, { id: 'rc-2' }],
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: mockBulkLock } });

      const res = await academicApi.bulkLockReportCards({
        class_id: 'cls-1',
        semester_id: 'sem-1',
        is_locked: true,
        reason: 'Batas akhir revisi nilai telah ditutup',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/report-cards/bulk-lock', {
        class_id: 'cls-1',
        semester_id: 'sem-1',
        is_locked: true,
        reason: 'Batas akhir revisi nilai telah ditutup',
      });
      expect(res.count).toBe(2);
      expect(res.is_locked).toBe(true);
    });

    it('calls GET /report-cards/{id}/pdf to download binary PDF', async () => {
      const mockBlob = new Blob(['%PDF-1.4 mock content'], { type: 'application/pdf' });
      vi.spyOn(apiClient, 'get').mockResolvedValue({ data: mockBlob });

      const res = await academicApi.downloadReportCardPdf('rc-1');

      expect(apiClient.get).toHaveBeenCalledWith('/report-cards/rc-1/pdf', {
        responseType: 'blob',
      });
      expect(res).toEqual(mockBlob);
    });
  });
});
