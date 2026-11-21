import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';

export type AssetCondition = 'baik' | 'rusak_ringan' | 'rusak_berat';
export type AssetAuditStatus = 'draf' | 'proses' | 'selesai';

export interface AssetItem {
  id: string;
  school_id?: string | null;
  room_id?: string | null;
  asset_code: string;
  name: string;
  category: string;
  brand?: string | null;
  model?: string | null;
  serial_number?: string | null;
  asset_condition: AssetCondition;
  acquired_at?: string | null;
  notes?: string | null;
  created_at: string;
  updated_at: string;
  room?: {
    id: string;
    name: string;
    code: string;
    room_type?: string;
  } | null;
  auditItems?: Array<{
    id: string;
    audit_id: string;
    found_condition: AssetCondition;
    is_present: boolean;
    notes?: string | null;
    audit?: {
      id: string;
      title: string;
      audit_date: string;
      status: string;
      auditor?: { id: string; username: string } | null;
    } | null;
  }>;
  tickets?: Array<{
    id: string;
    ticket_number: string;
    title?: string | null;
    description: string;
    priority: string;
    status: string;
    created_at: string;
    reporter?: { id: string; username: string } | null;
    assignee?: { id: string; username: string } | null;
  }>;
}

export interface AssetSummary {
  total_assets: number;
  condition_baik: number;
  condition_rusak_ringan: number;
  condition_rusak_berat: number;
  category_counts: Record<string, number>;
  total_audits: number;
  completed_audits: number;
}

export interface AssetQrData {
  asset: AssetItem;
  qr_code: string;
  qr_data_string: string;
  qr_payload: {
    system: string;
    module: string;
    asset_id: string;
    code: string;
    name: string;
    room: string;
    category: string;
  };
  label_title: string;
  school_id?: string | null;
  printed_at: string;
}

export interface AssetAuditItem {
  id: string;
  audit_id: string;
  asset_id: string;
  found_condition: AssetCondition;
  is_present: boolean;
  notes?: string | null;
  asset?: AssetItem | null;
}

export interface AssetAuditSession {
  id: string;
  school_id?: string | null;
  room_id?: string | null;
  title: string;
  audit_date: string;
  auditor_id: string;
  status: AssetAuditStatus;
  notes?: string | null;
  created_at: string;
  updated_at: string;
  auditor?: {
    id: string;
    username: string;
  } | null;
  room?: {
    id: string;
    name: string;
    code: string;
  } | null;
  items?: AssetAuditItem[];
  items_count?: number;
}

export interface ListAssetParams {
  room_id?: string;
  category?: string;
  asset_condition?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface CreateAssetPayload {
  asset_code: string;
  name: string;
  category: string;
  room_id?: string | null;
  brand?: string | null;
  model?: string | null;
  serial_number?: string | null;
  asset_condition?: AssetCondition;
  acquired_at?: string | null;
  notes?: string | null;
}

export interface UpdateAssetPayload {
  asset_code?: string;
  name?: string;
  category?: string;
  room_id?: string | null;
  brand?: string | null;
  model?: string | null;
  serial_number?: string | null;
  asset_condition?: AssetCondition;
  acquired_at?: string | null;
  notes?: string | null;
}

export interface CreateAssetAuditPayload {
  title: string;
  audit_date: string;
  room_id?: string | null;
  status?: AssetAuditStatus;
  notes?: string | null;
  items?: Array<{
    asset_id: string;
    is_present: boolean;
    found_condition: AssetCondition;
    notes?: string | null;
  }>;
}

export interface UpdateAssetAuditPayload {
  title?: string;
  audit_date?: string;
  room_id?: string | null;
  status?: AssetAuditStatus;
  notes?: string | null;
  items?: Array<{
    asset_id: string;
    is_present: boolean;
    found_condition: AssetCondition;
    notes?: string | null;
  }>;
}

export const assetsApi = {
  // =========================================================================
  // 1. MASTER ASET & LOOKUP QR (BE-SAR-02 / ADM-SAR-02)
  // =========================================================================

  getAssets: async (params?: ListAssetParams): Promise<PaginatedResponse<AssetItem>> => {
    const res = await apiClient.get<PaginatedResponse<AssetItem>>('/assets', { params });
    return res.data;
  },

  getAsset: async (id: string): Promise<ApiResponse<AssetItem>> => {
    const res = await apiClient.get<ApiResponse<AssetItem>>(`/assets/${id}`);
    return res.data;
  },

  getAssetByCode: async (code: string): Promise<ApiResponse<AssetItem>> => {
    const res = await apiClient.get<ApiResponse<AssetItem>>(`/assets/by-code/${encodeURIComponent(code)}`);
    return res.data;
  },

  getAssetQr: async (id: string): Promise<ApiResponse<AssetQrData>> => {
    const res = await apiClient.get<ApiResponse<AssetQrData>>(`/assets/${id}/qr`);
    return res.data;
  },

  getSummary: async (): Promise<ApiResponse<AssetSummary>> => {
    const res = await apiClient.get<ApiResponse<AssetSummary>>('/assets/summary');
    return res.data;
  },

  createAsset: async (data: CreateAssetPayload): Promise<ApiResponse<AssetItem>> => {
    const res = await apiClient.post<ApiResponse<AssetItem>>('/assets', data);
    return res.data;
  },

  updateAsset: async (id: string, data: UpdateAssetPayload): Promise<ApiResponse<AssetItem>> => {
    const res = await apiClient.put<ApiResponse<AssetItem>>(`/assets/${id}`, data);
    return res.data;
  },

  deleteAsset: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/assets/${id}`);
    return res.data;
  },

  // =========================================================================
  // 2. SESI STOK OPNAME ASET (BE-SAR-02 / ADM-SAR-02)
  // =========================================================================

  getAudits: async (params?: {
    status?: string;
    room_id?: string;
    from_date?: string;
    to_date?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<AssetAuditSession>> => {
    const res = await apiClient.get<PaginatedResponse<AssetAuditSession>>('/asset-audits', { params });
    return res.data;
  },

  getAudit: async (id: string): Promise<ApiResponse<AssetAuditSession>> => {
    const res = await apiClient.get<ApiResponse<AssetAuditSession>>(`/asset-audits/${id}`);
    return res.data;
  },

  createAudit: async (data: CreateAssetAuditPayload): Promise<ApiResponse<AssetAuditSession>> => {
    const res = await apiClient.post<ApiResponse<AssetAuditSession>>('/asset-audits', data);
    return res.data;
  },

  updateAudit: async (id: string, data: UpdateAssetAuditPayload): Promise<ApiResponse<AssetAuditSession>> => {
    const res = await apiClient.patch<ApiResponse<AssetAuditSession>>(`/asset-audits/${id}`, data);
    return res.data;
  },
};
