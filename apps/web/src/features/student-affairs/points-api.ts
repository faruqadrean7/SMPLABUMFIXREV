import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  PointType,
  PointThreshold,
  StudentPointItem,
  StudentPointsSummary,
  RecordStudentPointPayload,
  RecordStudentPointResult,
} from './types';

export const pointsApi = {
  // =========================================================================
  // 1. MASTER JENIS POIN (BE-STD-01 / ADM-STD-01)
  // =========================================================================

  getPointTypes: async (params?: {
    kind?: 'pelanggaran' | 'prestasi';
    severity?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<PointType>> => {
    const res = await apiClient.get<PaginatedResponse<PointType>>('/point-types', { params });
    return res.data;
  },

  getPointType: async (id: string): Promise<ApiResponse<PointType>> => {
    const res = await apiClient.get<ApiResponse<PointType>>(`/point-types/${id}`);
    return res.data;
  },

  createPointType: async (data: {
    kind: 'pelanggaran' | 'prestasi';
    name: string;
    points: number;
    severity?: 'rendah' | 'sedang' | 'tinggi' | 'berat';
  }): Promise<ApiResponse<PointType>> => {
    const res = await apiClient.post<ApiResponse<PointType>>('/point-types', data);
    return res.data;
  },

  updatePointType: async (
    id: string,
    data: Partial<{
      kind: 'pelanggaran' | 'prestasi';
      name: string;
      points: number;
      severity: 'rendah' | 'sedang' | 'tinggi' | 'berat';
    }>
  ): Promise<ApiResponse<PointType>> => {
    const res = await apiClient.put<ApiResponse<PointType>>(`/point-types/${id}`, data);
    return res.data;
  },

  deletePointType: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/point-types/${id}`);
    return res.data;
  },

  // =========================================================================
  // 2. AMBANG BATAS SANKSI POIN (POINT THRESHOLDS)
  // =========================================================================

  getPointThresholds: async (): Promise<ApiResponse<PointThreshold[]>> => {
    const res = await apiClient.get<ApiResponse<PointThreshold[]>>('/point-thresholds');
    return res.data;
  },

  getPointThreshold: async (id: string): Promise<ApiResponse<PointThreshold>> => {
    const res = await apiClient.get<ApiResponse<PointThreshold>>(`/point-thresholds/${id}`);
    return res.data;
  },

  createPointThreshold: async (data: {
    min_points: number;
    action: string;
    notify_roles: string[];
  }): Promise<ApiResponse<PointThreshold>> => {
    const res = await apiClient.post<ApiResponse<PointThreshold>>('/point-thresholds', data);
    return res.data;
  },

  updatePointThreshold: async (
    id: string,
    data: Partial<{
      min_points: number;
      action: string;
      notify_roles: string[];
    }>
  ): Promise<ApiResponse<PointThreshold>> => {
    const res = await apiClient.put<ApiResponse<PointThreshold>>(`/point-thresholds/${id}`, data);
    return res.data;
  },

  deletePointThreshold: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/point-thresholds/${id}`);
    return res.data;
  },

  // =========================================================================
  // 3. PENCATATAN & RIWAYAT POIN SISWA
  // =========================================================================

  getStudentPoints: async (params?: {
    student_id?: string;
    class_id?: string;
    kind?: 'pelanggaran' | 'prestasi';
    search?: string;
    start_date?: string;
    end_date?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<StudentPointItem>> => {
    const res = await apiClient.get<PaginatedResponse<StudentPointItem>>('/student-points', { params });
    return res.data;
  },

  getStudentPoint: async (id: string): Promise<ApiResponse<StudentPointItem>> => {
    const res = await apiClient.get<ApiResponse<StudentPointItem>>(`/student-points/${id}`);
    return res.data;
  },

  recordStudentPoint: async (data: RecordStudentPointPayload): Promise<ApiResponse<RecordStudentPointResult>> => {
    const res = await apiClient.post<ApiResponse<RecordStudentPointResult>>('/student-points', data);
    return res.data;
  },

  deleteStudentPoint: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/student-points/${id}`);
    return res.data;
  },

  getStudentPointsSummary: async (studentId: string): Promise<ApiResponse<StudentPointsSummary>> => {
    const res = await apiClient.get<ApiResponse<StudentPointsSummary>>(`/students/${studentId}/points/summary`);
    return res.data;
  },
};
