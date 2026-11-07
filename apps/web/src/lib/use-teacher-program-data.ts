'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from './api-client';
import { ApiResponse } from '@/types/auth';

export interface TeacherProgramMaterialItem {
  id: string;
  program_type: 'tahfidz' | 'icp' | 'inklusi' | 'reguler';
  title: string;
  description?: string | null;
  material_type: string;
  external_url?: string | null;
  file_id?: string | null;
  class_id?: string | null;
  target_scope?: string;
  created_at: string;
  creator?: { id: string; username: string } | null;
  file?: { id: string; url?: string; original_name?: string } | null;
  schoolClass?: { id: string; name: string; program_type: string } | null;
  assignedStudent?: { id: string; full_name: string; nis: string } | null;
}

export interface TeacherProgramTargetItem {
  id: string;
  student_id: string;
  program_type: 'tahfidz' | 'icp' | 'inklusi' | 'reguler';
  title: string;
  domain?: string | null;
  target_value?: string | null;
  current_value?: string | null;
  period?: string | null;
  status: 'aktif' | 'tercapai' | 'batal' | string;
  created_at: string;
  student?: { id: string; full_name: string; nis: string; class_id?: string } | null;
  setter?: { id: string; username: string } | null;
}

export interface TeacherProgramTaskItem {
  id: string;
  program_type: 'tahfidz' | 'icp' | 'inklusi' | 'reguler';
  title: string;
  instructions?: string | null;
  due_at?: string | null;
  file_id?: string | null;
  class_id?: string | null;
  target_scope?: string;
  created_at: string;
  submissions_count?: number;
  creator?: { id: string; username: string } | null;
  file?: { id: string; url?: string; original_name?: string } | null;
  schoolClass?: { id: string; name: string; program_type: string } | null;
}

export interface TeacherTaskSubmissionItem {
  id: string;
  task_id: string;
  student_id: string;
  file_id?: string | null;
  link?: string | null;
  notes?: string | null;
  status: 'dikumpulkan' | 'dinilai' | 'perlu_revisi' | string;
  score?: number | null;
  feedback?: string | null;
  submitted_at: string;
  graded_at?: string | null;
  student?: { id: string; full_name: string; nis: string } | null;
  file?: { id: string; url?: string; original_name?: string } | null;
  grader?: { id: string; username: string } | null;
}

// =========================================================================
// QUERIES
// =========================================================================

export function useTeacherProgramMaterials(params?: {
  program_type?: string;
  class_id?: string;
  material_type?: string;
  q?: string;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ['teacher-program-materials', params],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<TeacherProgramMaterialItem[]>>('/program/materials', {
        params,
      });
      return res.data;
    },
    staleTime: 1000 * 60 * 3,
  });
}

export function useTeacherProgramTargets(params?: {
  program_type?: string;
  student_id?: string;
  domain?: string;
  status?: string;
  q?: string;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ['teacher-program-targets', params],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<TeacherProgramTargetItem[]>>('/program/targets', {
        params,
      });
      return res.data;
    },
    staleTime: 1000 * 60 * 3,
  });
}

export function useTeacherProgramTasks(params?: {
  program_type?: string;
  class_id?: string;
  q?: string;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ['teacher-program-tasks', params],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<TeacherProgramTaskItem[]>>('/program/tasks', {
        params,
      });
      return res.data;
    },
    staleTime: 1000 * 60 * 3,
  });
}

export function useTeacherTaskSubmissions(taskId?: string | null, params?: { status?: string }) {
  return useQuery({
    queryKey: ['teacher-task-submissions', taskId, params],
    queryFn: async () => {
      if (!taskId) return { data: [] };
      const res = await apiClient.get<ApiResponse<TeacherTaskSubmissionItem[]>>(
        `/program/tasks/${taskId}/submissions`,
        { params }
      );
      return res.data;
    },
    enabled: Boolean(taskId),
    staleTime: 1000 * 60 * 2,
  });
}

// =========================================================================
// MUTATIONS
// =========================================================================

export function useCreateProgramMaterial() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: {
      program_type: string;
      title: string;
      material_type: string;
      description?: string;
      external_url?: string;
      class_id?: string;
      target_scope?: string;
    }) => {
      const res = await apiClient.post<ApiResponse<TeacherProgramMaterialItem>>(
        '/program/materials',
        payload
      );
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-materials'] });
      queryClient.invalidateQueries({ queryKey: ['program-materials'] });
    },
  });
}

export function useUpdateProgramMaterial() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id: string;
      payload: Partial<{
        title: string;
        material_type: string;
        description?: string;
        external_url?: string;
        class_id?: string;
      }>;
    }) => {
      const res = await apiClient.patch<ApiResponse<TeacherProgramMaterialItem>>(
        `/program/materials/${id}`,
        payload
      );
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-materials'] });
      queryClient.invalidateQueries({ queryKey: ['program-materials'] });
    },
  });
}

export function useDeleteProgramMaterial() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      const res = await apiClient.delete<ApiResponse<any>>(`/program/materials/${id}`);
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-materials'] });
      queryClient.invalidateQueries({ queryKey: ['program-materials'] });
    },
  });
}

export function useCreateProgramTarget() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: {
      student_id: string;
      program_type: string;
      title: string;
      domain?: string;
      target_value?: string;
      current_value?: string;
      period?: string;
      status?: string;
    }) => {
      const res = await apiClient.post<ApiResponse<TeacherProgramTargetItem>>(
        '/program/targets',
        payload
      );
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-targets'] });
      queryClient.invalidateQueries({ queryKey: ['program-targets'] });
    },
  });
}

export function useUpdateProgramTarget() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id: string;
      payload: Partial<{
        title: string;
        domain?: string;
        target_value?: string;
        current_value?: string;
        period?: string;
        status?: string;
      }>;
    }) => {
      const res = await apiClient.patch<ApiResponse<TeacherProgramTargetItem>>(
        `/program/targets/${id}`,
        payload
      );
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-targets'] });
      queryClient.invalidateQueries({ queryKey: ['program-targets'] });
    },
  });
}

export function useDeleteProgramTarget() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      const res = await apiClient.delete<ApiResponse<any>>(`/program/targets/${id}`);
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-targets'] });
      queryClient.invalidateQueries({ queryKey: ['program-targets'] });
    },
  });
}

export function useCreateProgramTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: {
      program_type: string;
      title: string;
      instructions?: string;
      due_at?: string;
      class_id?: string;
      target_scope?: string;
    }) => {
      const res = await apiClient.post<ApiResponse<TeacherProgramTaskItem>>('/program/tasks', payload);
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-tasks'] });
      queryClient.invalidateQueries({ queryKey: ['program-tasks'] });
    },
  });
}

export function useUpdateProgramTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id: string;
      payload: Partial<{
        title: string;
        instructions?: string;
        due_at?: string;
        class_id?: string;
      }>;
    }) => {
      const res = await apiClient.patch<ApiResponse<TeacherProgramTaskItem>>(
        `/program/tasks/${id}`,
        payload
      );
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-tasks'] });
      queryClient.invalidateQueries({ queryKey: ['program-tasks'] });
    },
  });
}

export function useDeleteProgramTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      const res = await apiClient.delete<ApiResponse<any>>(`/program/tasks/${id}`);
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-program-tasks'] });
      queryClient.invalidateQueries({ queryKey: ['program-tasks'] });
    },
  });
}

export function useGradeProgramSubmission() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      submissionId,
      payload,
    }: {
      submissionId: string;
      payload: {
        score: number;
        feedback?: string;
        status?: 'dinilai' | 'perlu_revisi';
      };
    }) => {
      const res = await apiClient.patch<ApiResponse<TeacherTaskSubmissionItem>>(
        `/program/submissions/${submissionId}`,
        payload
      );
      return res.data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['teacher-task-submissions'] });
      queryClient.invalidateQueries({ queryKey: ['teacher-program-tasks'] });
      queryClient.invalidateQueries({ queryKey: ['program-tasks'] });
    },
  });
}
