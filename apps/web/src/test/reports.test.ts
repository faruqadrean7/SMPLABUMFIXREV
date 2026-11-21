import { describe, it, expect, vi, beforeEach } from 'vitest';
import { reportsApi } from '@/features/reports/reports-api';
import { apiClient } from '@/lib/api/api-client';
import { ADMIN_NAV_SECTIONS } from '@/lib/rbac/navigation';
import {
  ExecutiveDashboardData,
  ReportResponse,
} from '@/features/reports/types';

describe('BE-RPT-01 / BE-RPT-02 / ADM-DASH-01 / ADM-DASH-02 / ADM-RPT-01: Laporan & Dashboard Eksekutif', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. DASHBOARD EKSEKUTIF (BE-RPT-02 / ADM-DASH-01 / ADM-DASH-02)
  // =========================================================================
  describe('1. Dashboard Eksekutif Kepala Sekolah & Pimpinan', () => {
    it('mengambil metrik agregat eksekutif lengkap termasuk CBT Live real-time dan ringkasan master', async () => {
      const mockExecutiveData: ExecutiveDashboardData = {
        cbt_live: {
          active_students_count: 72,
          ongoing_exams_count: 2,
          active_rooms_count: 2,
        },
        attendance_today: {
          date: '2026-10-14',
          students: {
            total_recorded: 720,
            hadir: 698,
            izin: 12,
            sakit: 8,
            alpa: 2,
            terlambat: 10,
            rate: 96.94,
          },
          staff: {
            total_recorded: 50,
            hadir: 48,
            terlambat: 2,
            izin: 0,
            rate: 96.0,
          },
        },
        syllabus_completion: {
          total_schedules: 120,
          active_with_journals: 108,
          completion_rate: 90.0,
        },
        teacher_grading_completion: {
          total_components: 80,
          completed_components: 68,
          completion_rate: 85.0,
        },
        grades_by_grade_level: [
          {
            grade_level_id: 'lvl-10',
            level_name: 'Kelas 10',
            sort_order: 10,
            average_score: 82.5,
            grades_count: 240,
          },
          {
            grade_level_id: 'lvl-11',
            level_name: 'Kelas 11',
            sort_order: 11,
            average_score: 84.1,
            grades_count: 235,
          },
        ],
        student_affairs_follow_up: {
          active_counseling_cases: 3,
          recent_violations_count: 5,
        },
        infrastructure_status: {
          critical_tickets_count: 1,
          heavily_damaged_assets: 2,
        },
        master_summary: {
          total_students: 720,
          total_teachers: 45,
          total_classes: 24,
          pending_verifications: {
            achievements: 4,
            facility_bookings: 2,
            total: 6,
          },
        },
        cached_at: '2026-10-14T08:00:00+07:00',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockExecutiveData,
        },
      } as any);

      const res = await reportsApi.getExecutiveDashboard();

      expect(apiClient.get).toHaveBeenCalledWith('/dashboard/executive');
      expect(res.data.cbt_live.active_students_count).toBe(72);
      expect(res.data.cbt_live.ongoing_exams_count).toBe(2);
      expect(res.data.attendance_today.students.rate).toBe(96.94);
      expect(res.data.syllabus_completion.completion_rate).toBe(90.0);
      expect(res.data.teacher_grading_completion.completion_rate).toBe(85.0);
      expect(res.data.grades_by_grade_level).toHaveLength(2);
      expect(res.data.infrastructure_status.heavily_damaged_assets).toBe(2);
      expect(res.data.master_summary?.total_students).toBe(720);
      expect(res.data.master_summary?.pending_verifications.total).toBe(6);
    });

    it('mendukung pemanggilan endpoint dashboard peran khusus (guru, siswa, wali murid)', async () => {
      vi.spyOn(apiClient, 'get')
        .mockResolvedValueOnce({ data: { data: { teacher: { id: 'tch-1' } } } } as any)
        .mockResolvedValueOnce({ data: { data: { student: { id: 'std-1' } } } } as any)
        .mockResolvedValueOnce({ data: { data: { guardian: { id: 'grd-1' } } } } as any);

      const teacherRes = await reportsApi.getTeacherDashboard();
      const studentRes = await reportsApi.getStudentDashboard();
      const guardianRes = await reportsApi.getGuardianDashboard('std-1');

      expect(apiClient.get).toHaveBeenCalledWith('/dashboard/teacher');
      expect(apiClient.get).toHaveBeenCalledWith('/dashboard/student');
      expect(apiClient.get).toHaveBeenCalledWith('/dashboard/guardian/std-1');
      expect(teacherRes.data).toBeDefined();
      expect(studentRes.data).toBeDefined();
      expect(guardianRes.data).toBeDefined();
    });
  });

  // =========================================================================
  // 2. PUSAT LAPORAN REKAPITULASI (BE-RPT-01 / ADM-RPT-01)
  // =========================================================================
  describe('2. Pusat Laporan & Rekapitulasi (ADM-RPT-01)', () => {
    it('mengambil data laporan rekapitulasi presensi siswa dengan filter periode', async () => {
      const mockReport: ReportResponse = {
        type: 'attendance',
        summary: {
          total_records: 150,
          hadir_count: 140,
          izin_count: 6,
          sakit_count: 3,
          alpa_count: 1,
          attendance_rate: 93.33,
        },
        data: [
          {
            id: 'att-1',
            att_date: '2026-10-14',
            student_name: 'Ahmad Siswa',
            status: 'hadir',
            source: 'kelas',
          },
        ],
        meta: {
          current_page: 1,
          per_page: 15,
          total: 150,
          last_page: 10,
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockReport,
        },
      } as any);

      const res = await reportsApi.getReportData('attendance', {
        start_date: '2026-10-01',
        end_date: '2026-10-14',
        status: 'hadir',
        page: 1,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/reports/attendance', {
        params: {
          start_date: '2026-10-01',
          end_date: '2026-10-14',
          status: 'hadir',
          page: 1,
        },
      });

      expect(res.data.type).toBe('attendance');
      expect(res.data.summary.total_records).toBe(150);
      expect(res.data.summary.attendance_rate).toBe(93.33);
      expect(res.data.data).toHaveLength(1);
    });

    it('mengambil data laporan rekapitulasi nilai akademik beserta ringkasan KKM', async () => {
      const mockReport: ReportResponse = {
        type: 'grades',
        summary: {
          total_grades: 240,
          average_score: 83.4,
          highest_score: 98,
          lowest_score: 65,
          passing_count: 220,
          failing_count: 20,
        },
        data: [],
        meta: { current_page: 1, per_page: 15, total: 240, last_page: 16 },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockReport },
      } as any);

      const res = await reportsApi.getReportData('grades', { class_id: 'cls-10a' });

      expect(apiClient.get).toHaveBeenCalledWith('/reports/grades', {
        params: { class_id: 'cls-10a' },
      });
      expect(res.data.summary.average_score).toBe(83.4);
      expect(res.data.summary.passing_count).toBe(220);
    });

    it('mengambil data laporan rekapitulasi PPDB dan status pendaftar', async () => {
      const mockReport: ReportResponse = {
        type: 'ppdb',
        summary: {
          total_applicants: 350,
          by_status: { verified: 280, accepted: 216, submitted: 50, rejected: 20 },
          average_score: 81.25,
        },
        data: [],
        meta: { current_page: 1, per_page: 15, total: 350, last_page: 24 },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockReport },
      } as any);

      const res = await reportsApi.getReportData('ppdb');
      expect(apiClient.get).toHaveBeenCalledWith('/reports/ppdb', { params: undefined });
      expect(res.data.summary.total_applicants).toBe(350);
    });

    it('mengambil data laporan rekapitulasi poin kesiswaan, tiket sarpras, dan peminjaman buku', async () => {
      vi.spyOn(apiClient, 'get')
        .mockResolvedValueOnce({
          data: {
            data: {
              type: 'student_points',
              summary: { total_events: 40, total_prestasi_points: 350, total_pelanggaran_points: 60 },
              data: [],
              meta: { current_page: 1, per_page: 15, total: 40, last_page: 3 },
            },
          },
        } as any)
        .mockResolvedValueOnce({
          data: {
            data: {
              type: 'tickets',
              summary: { total_tickets: 18, pending_count: 2, diproses_count: 4, selesai_count: 12 },
              data: [],
              meta: { current_page: 1, per_page: 15, total: 18, last_page: 2 },
            },
          },
        } as any)
        .mockResolvedValueOnce({
          data: {
            data: {
              type: 'loans',
              summary: { total_loans: 85, active_loans: 22, returned_loans: 63, overdue_loans: 4, total_fines: 25000 },
              data: [],
              meta: { current_page: 1, per_page: 15, total: 85, last_page: 6 },
            },
          },
        } as any);

      const pointsRes = await reportsApi.getReportData('student_points');
      const ticketsRes = await reportsApi.getReportData('tickets');
      const loansRes = await reportsApi.getReportData('loans');

      expect(pointsRes.data.summary.total_prestasi_points).toBe(350);
      expect(ticketsRes.data.summary.selesai_count).toBe(12);
      expect(loansRes.data.summary.overdue_loans).toBe(4);
    });

    it('menjalankan ekspor berkas CSV dengan format response blob', async () => {
      const mockBlob = new Blob(['Tanggal,Nama Siswa,Status\n2026-10-14,Ahmad,hadir'], {
        type: 'text/csv',
      });

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: mockBlob,
      } as any);

      const blob = await reportsApi.exportReportCsv('attendance', {
        start_date: '2026-10-01',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/reports/attendance/export', {
        params: { start_date: '2026-10-01', format: 'csv' },
        responseType: 'blob',
      });

      expect(blob).toBeInstanceOf(Blob);
    });
  });

  // =========================================================================
  // 3. NAVIGASI RBAC RESMI
  // =========================================================================
  describe('3. Konfigurasi Navigasi RBAC untuk Pusat Laporan', () => {
    it('memastikan Pusat Laporan Terpadu terdaftar dalam ADMIN_NAV_SECTIONS', () => {
      const allItems = ADMIN_NAV_SECTIONS.flatMap((s) => s.items);
      const reportsItem = allItems.find((item) => item.href === '/admin/reports');

      expect(reportsItem).toBeDefined();
      expect(reportsItem?.title).toBe('Pusat Laporan Terpadu');
      expect(reportsItem?.icon).toBe('BarChart3');
    });
  });
});
