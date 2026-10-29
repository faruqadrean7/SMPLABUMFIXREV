import { apiClient } from '@/lib/api/api-client';
import { executeWithMockFallback } from '@/lib/api/mock-mode';
import { ExecutiveKpiMetrics, ApprovalItem, ReviewApprovalPayload } from './types';

const loadExecutiveMock =
  process.env.NODE_ENV !== 'production'
    ? () => import('./executive-mock-data')
    : null;

export const executiveApi = {
  // === OVERVIEW METRICS DASHBOARD EKSEKUTIF ===
  async getOverviewMetrics(): Promise<ExecutiveKpiMetrics> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<{ data: any }>('/dashboard/executive');
        const raw = (res.data as any)?.data || res.data;
        if (!raw) {
          throw new Error('Data eksekutif kosong');
        }

        const gradeLevels = Array.isArray(raw.grades_by_grade_level)
          ? raw.grades_by_grade_level.map((g: any) => ({
              level: g.level_name || `Tingkat ${g.sort_order || ''}`,
              averageScore: Number(g.average_score || 0),
              totalStudents: Number(g.grades_count || 0),
            }))
          : Array.isArray(raw.gradeLevelAverages)
          ? raw.gradeLevelAverages
          : [];

        return {
          studentAttendanceRate: Number(raw.attendance_today?.students?.rate ?? raw.studentAttendanceRate ?? 0),
          teacherAttendanceRate: Number(raw.attendance_today?.staff?.rate ?? raw.teacherAttendanceRate ?? 0),
          totalStudents: Number(raw.master_summary?.total_students ?? raw.totalStudents ?? 0),
          totalTeachers: Number(raw.master_summary?.total_teachers ?? raw.totalTeachers ?? 0),
          activeEarlyWarningsCount: Number(raw.student_affairs_follow_up?.active_counseling_cases ?? raw.activeEarlyWarningsCount ?? 0),
          openTicketsCount: Number(raw.infrastructure_status?.critical_tickets_count ?? raw.openTicketsCount ?? 0),
          ppdbEnrolledCount: Number(raw.master_summary?.pending_verifications?.total ?? raw.ppdbEnrolledCount ?? 0),
          ppdbTargetQuota: Number(raw.ppdbTargetQuota ?? 120),
          pendingApprovalsCount: Number(raw.master_summary?.pending_verifications?.total ?? raw.pendingApprovalsCount ?? 0),
          gradeLevelAverages: gradeLevels,
          recentIncidents: Array.isArray(raw.recentIncidents) ? raw.recentIncidents : [],
        };
      },
      loadExecutiveMock ? () => loadExecutiveMock().then((m) => m.MOCK_EXECUTIVE_METRICS) : undefined
    );
  },

  // === ANTREAN PERSETUJUAN KEPALA SEKOLAH / WAKASEK ===
  async getApprovals(): Promise<ApprovalItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<{ data: ApprovalItem[] }>('/approvals/pending');
        if (res.data?.data && Array.isArray(res.data.data)) {
          return res.data.data;
        }
        return [];
      },
      loadExecutiveMock ? () => loadExecutiveMock().then((m) => m.MOCK_APPROVAL_ITEMS) : undefined
    );
  },

  async reviewApproval(
    id: string,
    payload: ReviewApprovalPayload
  ): Promise<{ success: boolean; message: string; reviewedAt: string }> {
    if (payload.action === 'reject' && !payload.rejectionReason?.trim()) {
      throw new Error('Alasan penolakan wajib diisi.');
    }

    const now = new Date().toISOString();
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.post<{ success: boolean; message: string; reviewedAt: string }>(
          `/approvals/${id}/review`,
          {
            action: payload.action,
            notes: payload.notes || payload.rejectionReason,
          }
        );
        return res.data;
      },
      () =>
        Promise.resolve({
          success: true,
          message:
            payload.action === 'approve'
              ? 'Pengajuan berhasil disetujui dan diverifikasi secara resmi.'
              : 'Pengajuan telah ditolak beserta alasan tertulis.',
          reviewedAt: now,
        })
    );
  },
};

