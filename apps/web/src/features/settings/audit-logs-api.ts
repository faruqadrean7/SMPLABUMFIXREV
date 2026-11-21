import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import { AuditLogItem, AuditLogDetail, AuditLogQueryParams } from '@/types/audit-log';

export const auditLogsApi = {
  /**
   * BE-AUTH-05: Ambil daftar log audit berpaginasi dengan berbagai filter
   * GET /api/v1/audit-logs
   */
  async list(params?: AuditLogQueryParams): Promise<PaginatedResponse<AuditLogItem>> {
    const res = await apiClient.get<PaginatedResponse<AuditLogItem>>('/audit-logs', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 20,
        q: params?.q || undefined,
        actor_id: params?.actor_id || undefined,
        action: params?.action || undefined,
        entity: params?.entity || undefined,
        entity_id: params?.entity_id || undefined,
        start_date: params?.start_date || undefined,
        end_date: params?.end_date || undefined,
      },
    });
    return res.data;
  },

  /**
   * BE-AUTH-05: Ambil detail rekaman log audit berdasarkan ID
   * GET /api/v1/audit-logs/{id}
   */
  async getDetail(id: string): Promise<AuditLogDetail> {
    const res = await apiClient.get<ApiResponse<AuditLogDetail>>(`/audit-logs/${id}`);
    return res.data.data;
  },
};
