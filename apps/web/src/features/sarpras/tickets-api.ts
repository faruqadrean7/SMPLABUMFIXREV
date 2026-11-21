import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';

export type TicketPriority = 'rendah' | 'sedang' | 'tinggi' | 'darurat';
export type TicketStatus = 'pending' | 'diproses' | 'selesai';
export type TicketEventType = 'lapor' | 'disposisi' | 'proses' | 'selesai' | 'komentar';

export interface TicketEventItem {
  id: string;
  ticket_id: string;
  actor_id: string;
  event_type: TicketEventType;
  note?: string | null;
  file_id?: string | null;
  created_at: string;
  actor?: {
    id: string;
    username: string;
    email?: string;
  } | null;
  file?: {
    id: string;
    file_name?: string;
    file_url?: string;
  } | null;
}

export interface TicketItem {
  id: string;
  school_id?: string | null;
  ticket_number: string;
  title?: string | null;
  reporter_id: string;
  room_id?: string | null;
  asset_id?: string | null;
  assignee_id?: string | null;
  description: string;
  priority: TicketPriority;
  status: TicketStatus;
  photo_file_id?: string | null;
  resolved_at?: string | null;
  created_at: string;
  updated_at: string;
  reporter?: {
    id: string;
    username: string;
  } | null;
  assignee?: {
    id: string;
    username: string;
  } | null;
  room?: {
    id: string;
    code: string;
    name: string;
    room_type?: string;
  } | null;
  asset?: {
    id: string;
    asset_code: string;
    name: string;
    category?: string;
  } | null;
  photoFile?: {
    id: string;
    file_name?: string;
    file_url?: string;
  } | null;
  events?: TicketEventItem[];
}

export interface TicketSummary {
  total: number;
  pending: number;
  diproses: number;
  selesai: number;
  darurat: number;
  sla_warning: number;
  avg_resolution_hours: number;
}

export interface TechnicianItem {
  id: string;
  name: string;
  username: string;
  email: string;
  roles: Record<string, string>;
  active_tickets_count: number;
  completed_tickets_count: number;
  avg_resolution_hours: number;
}

export interface ListTicketParams {
  status?: string;
  priority?: string;
  room_id?: string;
  asset_id?: string;
  assignee_id?: string;
  reporter_id?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface CreateTicketPayload {
  title?: string;
  room_id?: string | null;
  asset_id?: string | null;
  description: string;
  priority?: TicketPriority;
  photo_file_id?: string | null;
}

export interface DisposeTicketPayload {
  assignee_id: string;
  note?: string;
}

export interface UpdateTicketStatusPayload {
  status: TicketStatus;
  note?: string;
  file_id?: string | null;
}

export interface AddTicketCommentPayload {
  note: string;
  file_id?: string | null;
}

export const ticketsApi = {
  // =========================================================================
  // HELPDESK PERBAIKAN SARPRAS (BE-SAR-03 / ADM-SAR-03)
  // =========================================================================

  getTickets: async (params?: ListTicketParams): Promise<PaginatedResponse<TicketItem>> => {
    const res = await apiClient.get<PaginatedResponse<TicketItem>>('/tickets', { params });
    return res.data;
  },

  getTicket: async (id: string): Promise<ApiResponse<TicketItem>> => {
    const res = await apiClient.get<ApiResponse<TicketItem>>(`/tickets/${id}`);
    return res.data;
  },

  createTicket: async (data: CreateTicketPayload): Promise<ApiResponse<TicketItem>> => {
    const res = await apiClient.post<ApiResponse<TicketItem>>('/tickets', data);
    return res.data;
  },

  disposeTicket: async (id: string, data: DisposeTicketPayload): Promise<ApiResponse<TicketItem>> => {
    const res = await apiClient.post<ApiResponse<TicketItem>>(`/tickets/${id}/dispose`, data);
    return res.data;
  },

  updateStatus: async (id: string, data: UpdateTicketStatusPayload): Promise<ApiResponse<TicketItem>> => {
    const res = await apiClient.patch<ApiResponse<TicketItem>>(`/tickets/${id}/status`, data);
    return res.data;
  },

  addComment: async (id: string, data: AddTicketCommentPayload): Promise<ApiResponse<TicketEventItem>> => {
    const res = await apiClient.post<ApiResponse<TicketEventItem>>(`/tickets/${id}/comments`, data);
    return res.data;
  },

  getSummary: async (): Promise<ApiResponse<TicketSummary>> => {
    const res = await apiClient.get<ApiResponse<TicketSummary>>('/tickets/summary');
    return res.data;
  },

  getTechnicians: async (): Promise<ApiResponse<TechnicianItem[]>> => {
    const res = await apiClient.get<ApiResponse<TechnicianItem[]>>('/tickets/technicians');
    return res.data;
  },

  getRooms: async (): Promise<PaginatedResponse<{ id: string; name: string; code: string; room_type?: string }>> => {
    const res = await apiClient.get<PaginatedResponse<{ id: string; name: string; code: string; room_type?: string }>>('/rooms', { params: { limit: 100 } });
    return res.data;
  },

  getAssets: async (): Promise<PaginatedResponse<{ id: string; name: string; asset_code: string; category?: string }>> => {
    const res = await apiClient.get<PaginatedResponse<{ id: string; name: string; asset_code: string; category?: string }>>('/assets', { params: { per_page: 100 } });
    return res.data;
  },
};
