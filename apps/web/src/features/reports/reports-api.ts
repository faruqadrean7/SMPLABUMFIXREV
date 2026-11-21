import { apiClient } from '@/lib/api/api-client';
import { ApiResponse } from '@/types/api';
import {
  ExecutiveDashboardData,
  ReportType,
  ReportFilterParams,
  ReportResponse,
} from './types';

export const reportsApi = {
  // =========================================================================
  // 1. DASHBOARD EKSEKUTIF & ROLE (BE-RPT-02 / ADM-DASH-01 / ADM-DASH-02)
  // =========================================================================

  getExecutiveDashboard: async (): Promise<ApiResponse<ExecutiveDashboardData>> => {
    const res = await apiClient.get<ApiResponse<ExecutiveDashboardData>>('/dashboard/executive');
    return res.data;
  },

  getTeacherDashboard: async (): Promise<ApiResponse<Record<string, unknown>>> => {
    const res = await apiClient.get<ApiResponse<Record<string, unknown>>>('/dashboard/teacher');
    return res.data;
  },

  getStudentDashboard: async (): Promise<ApiResponse<Record<string, unknown>>> => {
    const res = await apiClient.get<ApiResponse<Record<string, unknown>>>('/dashboard/student');
    return res.data;
  },

  getGuardianDashboard: async (studentId?: string): Promise<ApiResponse<Record<string, unknown>>> => {
    const url = studentId ? `/dashboard/guardian/${studentId}` : '/dashboard/guardian';
    const res = await apiClient.get<ApiResponse<Record<string, unknown>>>(url);
    return res.data;
  },

  // =========================================================================
  // 2. PUSAT LAPORAN REKAPITULASI (BE-RPT-01 / ADM-RPT-01)
  // =========================================================================

  getReportData: async <T = Record<string, unknown>>(
    type: ReportType,
    params?: ReportFilterParams
  ): Promise<ApiResponse<ReportResponse<T>>> => {
    const res = await apiClient.get<ApiResponse<ReportResponse<T>>>(`/reports/${type}`, {
      params,
    });
    return res.data;
  },

  exportReportCsv: async (
    type: ReportType,
    params?: ReportFilterParams
  ): Promise<Blob> => {
    const res = await apiClient.get(`/reports/${type}/export`, {
      params: { ...params, format: 'csv' },
      responseType: 'blob',
    });
    return res.data as Blob;
  },

  /**
   * Helper untuk mengunduh berkas Blob CSV langsung di browser.
   */
  triggerBlobDownload: (blob: Blob, filename: string): void => {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.parentNode?.removeChild(link);
    window.URL.revokeObjectURL(url);
  },
};
