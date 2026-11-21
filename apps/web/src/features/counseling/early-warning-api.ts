import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  EarlyWarningItem,
  EarlyWarningSummary,
  ListEarlyWarningsParams,
  EvaluateEarlyWarningsPayload,
  HandleEarlyWarningPayload,
} from './early-warning-types';

export const earlyWarningApi = {
  /**
   * Ambil daftar alert deteksi dini dengan filter dan paginasi (BE-BK-02 / ADM-BK-02)
   */
  getWarnings: async (params?: ListEarlyWarningsParams): Promise<PaginatedResponse<EarlyWarningItem>> => {
    const res = await apiClient.get<PaginatedResponse<EarlyWarningItem>>('/early-warnings', { params });
    return res.data;
  },

  /**
   * Ambil ringkasan statistik metrik deteksi dini untuk metrics cards
   */
  getSummary: async (): Promise<ApiResponse<EarlyWarningSummary>> => {
    const res = await apiClient.get<ApiResponse<EarlyWarningSummary>>('/early-warnings/summary');
    return res.data;
  },

  /**
   * Ambil detail alert deteksi dini spesifik
   */
  getWarning: async (id: string): Promise<ApiResponse<EarlyWarningItem>> => {
    const res = await apiClient.get<ApiResponse<EarlyWarningItem>>(`/early-warnings/${id}`);
    return res.data;
  },

  /**
   * Jalankan evaluasi aturan deteksi dini secara otomatis
   */
  evaluateRules: async (
    payload?: EvaluateEarlyWarningsPayload
  ): Promise<
    ApiResponse<{
      students_evaluated: number;
      created_alerts: number;
      updated_alerts: number;
      message: string;
    }>
  > => {
    const res = await apiClient.post<
      ApiResponse<{
        students_evaluated: number;
        created_alerts: number;
        updated_alerts: number;
        message: string;
      }>
    >('/early-warnings/evaluate', payload || {});
    return res.data;
  },

  /**
   * Tangani atau perbarui status alert deteksi dini (in_progress, resolved, dismissed)
   */
  handleWarning: async (
    id: string,
    payload: HandleEarlyWarningPayload
  ): Promise<ApiResponse<EarlyWarningItem>> => {
    const res = await apiClient.patch<ApiResponse<EarlyWarningItem>>(`/early-warnings/${id}/handle`, payload);
    return res.data;
  },
};
