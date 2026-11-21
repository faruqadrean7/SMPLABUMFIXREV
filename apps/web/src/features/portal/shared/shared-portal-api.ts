import { apiClient } from '@/lib/api/api-client';
import { executeWithMockFallback } from '@/lib/api/mock-mode';
import {
  SharedSelfAttendance,
  SharedLeaveRequest,
  SharedSalarySlipItem,
  CreateLeaveRequestInput,
} from './types';

interface BackendSalarySlip {
  id: string;
  slip_number?: string;
  period_month?: string;
  period?: string;
  basic_salary?: number;
  functional_allowance?: number;
  operational_allowance?: number;
  teaching_allowance?: number;
  deduction?: number;
  total_net_salary?: number;
  net_salary?: number;
  payment_date?: string;
  created_at?: string;
  is_paid?: boolean;
}

const loadSharedMock =
  process.env.NODE_ENV !== 'production'
    ? () => import('./shared-portal-mock-data')
    : null;

function unwrapList<T>(res: any): T[] {
  if (!res) return [];
  const payload = res.data !== undefined ? res.data : res;
  if (Array.isArray(payload)) return payload as T[];
  if (payload && Array.isArray(payload.data)) return payload.data as T[];
  return [];
}

function unwrapItem<T>(res: any, fallback: T): T {
  if (!res) return fallback;
  const payload = res.data !== undefined ? res.data : res;
  if (payload && typeof payload === 'object' && 'data' in payload && payload.data !== undefined) {
    return payload.data as T;
  }
  return (payload as T) ?? fallback;
}

const resolvePortalPath = (portalType: 'guru' | 'staf'): string => {
  return portalType === 'guru' ? 'teacher' : 'staff';
};

export const sharedPortalApi = {
  // === PRESENSI MANDIRI (GURU / STAF) ===
  async getSelfAttendance(portalType: 'guru' | 'staf' = 'staf'): Promise<SharedSelfAttendance> {
    const prefix = resolvePortalPath(portalType);
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<SharedSelfAttendance>(`/me/${prefix}/self-attendance`);
        return unwrapItem<SharedSelfAttendance>(res, {} as SharedSelfAttendance);
      },
      loadSharedMock ? () => loadSharedMock().then((m) => m.MOCK_SHARED_SELF_ATTENDANCE) : undefined
    );
  },

  async recordSelfAttendance(
    action: 'check_in' | 'check_out',
    portalType: 'guru' | 'staf' = 'staf'
  ): Promise<{ success: boolean; message: string; timestamp: string }> {
    const prefix = resolvePortalPath(portalType);
    const now = new Date().toTimeString().split(' ')[0];
    await apiClient.post(`/me/${prefix}/self-attendance`, { action });
    return {
      success: true,
      message: `Presensi kerja (${action === 'check_in' ? 'Masuk' : 'Pulang'}) berhasil dicatat.`,
      timestamp: now,
    };
  },

  // === IZIN & CUTI (GURU / STAF) ===
  async getLeaveRequests(portalType: 'guru' | 'staf' = 'staf'): Promise<SharedLeaveRequest[]> {
    const prefix = resolvePortalPath(portalType);
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<SharedLeaveRequest[]>(`/me/${prefix}/leave-requests`);
        return unwrapList<SharedLeaveRequest>(res);
      },
      loadSharedMock ? () => loadSharedMock().then((m) => m.MOCK_SHARED_LEAVE_REQUESTS) : undefined
    );
  },

  async createLeaveRequest(
    payload: CreateLeaveRequestInput,
    portalType: 'guru' | 'staf' = 'staf'
  ): Promise<{ success: boolean; message: string }> {
    const prefix = resolvePortalPath(portalType);
    await apiClient.post(`/me/${prefix}/leave-requests`, payload);
    return { success: true, message: 'Permohonan izin berhasil diajukan.' };
  },

  // === SLIP GAJI (GURU / STAF - BACKEND RESMI) ===
  async getSalarySlips(): Promise<SharedSalarySlipItem[]> {
    return executeWithMockFallback(
      async () => {
        const res = await apiClient.get<{ data: BackendSalarySlip[] }>('/salary-slips');
        if (res.data?.data && Array.isArray(res.data.data)) {
          return res.data.data.map((item) => ({
            id: item.id,
            slipNumber: item.slip_number || `SLIP-${item.id}`,
            periodMonth: item.period_month || item.period || 'Periode Berjalan',
            basicSalary: Number(item.basic_salary || 0),
            functionalAllowance: Number(item.functional_allowance || 0),
            operationalAllowance: Number(item.operational_allowance || item.teaching_allowance || 0),
            teachingAllowance: Number(item.teaching_allowance || 0),
            deduction: Number(item.deduction || 0),
            totalNetSalary: Number(item.total_net_salary || item.net_salary || 0),
            paymentDate: item.payment_date || item.created_at || new Date().toISOString().split('T')[0],
            isPaid: Boolean(item.is_paid ?? true),
            downloadUrl: `/api/v1/salary-slips/${item.id}/download`,
          }));
        }
        return [];
      },
      loadSharedMock ? () => loadSharedMock().then((m) => m.MOCK_SHARED_SALARY_SLIPS) : undefined
    );
  },
};
