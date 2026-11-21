import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  CompetitionItem,
  CompetitionEntryItem,
  AchievementItem,
  CompetitionSummary,
  CreateCompetitionPayload,
  UpdateCompetitionPayload,
  RegisterCompetitionEntryPayload,
  UpdateCompetitionEntryPayload,
  CreateAchievementPayload,
  UpdateAchievementPayload,
  VerifyAchievementPayload,
} from './types';

export const competitionsApi = {
  // =========================================================================
  // 1. KOMPETISI / LOMBA (BE-STD-03 / ADM-STD-03)
  // =========================================================================

  getCompetitions: async (params?: {
    level?: string;
    category?: string;
    status?: string;
    search?: string;
    from_date?: string;
    to_date?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<CompetitionItem>> => {
    const res = await apiClient.get<PaginatedResponse<CompetitionItem>>('/competitions', { params });
    return res.data;
  },

  getCompetition: async (id: string): Promise<ApiResponse<CompetitionItem>> => {
    const res = await apiClient.get<ApiResponse<CompetitionItem>>(`/competitions/${id}`);
    return res.data;
  },

  createCompetition: async (data: CreateCompetitionPayload): Promise<ApiResponse<CompetitionItem>> => {
    const res = await apiClient.post<ApiResponse<CompetitionItem>>('/competitions', data);
    return res.data;
  },

  updateCompetition: async (id: string, data: UpdateCompetitionPayload): Promise<ApiResponse<CompetitionItem>> => {
    const res = await apiClient.put<ApiResponse<CompetitionItem>>(`/competitions/${id}`, data);
    return res.data;
  },

  deleteCompetition: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/competitions/${id}`);
    return res.data;
  },

  getSummary: async (): Promise<ApiResponse<CompetitionSummary>> => {
    const res = await apiClient.get<ApiResponse<CompetitionSummary>>('/competitions/summary');
    return res.data;
  },

  // =========================================================================
  // 2. DELEGASI PESERTA KOMPETISI (ENTRIES)
  // =========================================================================

  getEntries: async (competitionId: string): Promise<PaginatedResponse<CompetitionEntryItem>> => {
    const res = await apiClient.get<PaginatedResponse<CompetitionEntryItem>>(`/competitions/${competitionId}/entries`);
    return res.data;
  },

  registerEntry: async (
    competitionId: string,
    data: RegisterCompetitionEntryPayload
  ): Promise<ApiResponse<CompetitionEntryItem>> => {
    const res = await apiClient.post<ApiResponse<CompetitionEntryItem>>(`/competitions/${competitionId}/entries`, data);
    return res.data;
  },

  updateEntry: async (
    id: string,
    data: UpdateCompetitionEntryPayload
  ): Promise<ApiResponse<CompetitionEntryItem>> => {
    const res = await apiClient.put<ApiResponse<CompetitionEntryItem>>(`/competition-entries/${id}`, data);
    return res.data;
  },

  deleteEntry: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/competition-entries/${id}`);
    return res.data;
  },

  // =========================================================================
  // 3. PRESTASI & GALERI PIALA (ACHIEVEMENTS)
  // =========================================================================

  getAchievements: async (params?: {
    student_id?: string;
    competition_id?: string;
    rank_level?: string;
    verification_status?: string;
    search?: string;
    only_my?: boolean;
    from_date?: string;
    to_date?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<AchievementItem>> => {
    const res = await apiClient.get<PaginatedResponse<AchievementItem>>('/achievements', { params });
    return res.data;
  },

  getAchievement: async (id: string): Promise<ApiResponse<AchievementItem>> => {
    const res = await apiClient.get<ApiResponse<AchievementItem>>(`/achievements/${id}`);
    return res.data;
  },

  createAchievement: async (data: CreateAchievementPayload): Promise<ApiResponse<AchievementItem>> => {
    const res = await apiClient.post<ApiResponse<AchievementItem>>('/achievements', data);
    return res.data;
  },

  updateAchievement: async (id: string, data: UpdateAchievementPayload): Promise<ApiResponse<AchievementItem>> => {
    const res = await apiClient.put<ApiResponse<AchievementItem>>(`/achievements/${id}`, data);
    return res.data;
  },

  deleteAchievement: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/achievements/${id}`);
    return res.data;
  },

  verifyAchievement: async (
    id: string,
    data: VerifyAchievementPayload
  ): Promise<ApiResponse<AchievementItem>> => {
    const res = await apiClient.patch<ApiResponse<AchievementItem>>(`/achievements/${id}/verify`, data);
    return res.data;
  },
};
