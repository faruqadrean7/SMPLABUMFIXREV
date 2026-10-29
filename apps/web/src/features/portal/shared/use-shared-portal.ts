import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { sharedPortalApi } from './shared-portal-api';
import { CreateLeaveRequestInput } from './types';

export const SHARED_PORTAL_KEYS = {
  all: ['portal-shared'] as const,
  selfAttendance: (portalType: string) => [...SHARED_PORTAL_KEYS.all, 'self-attendance', portalType] as const,
  leaveRequests: (portalType: string) => [...SHARED_PORTAL_KEYS.all, 'leave-requests', portalType] as const,
  salarySlips: () => [...SHARED_PORTAL_KEYS.all, 'salary-slips'] as const,
};

export function useSharedSelfAttendance(portalType: 'guru' | 'staf' = 'staf') {
  return useQuery({
    queryKey: SHARED_PORTAL_KEYS.selfAttendance(portalType),
    queryFn: () => sharedPortalApi.getSelfAttendance(portalType),
  });
}

export function useSharedClockIn(portalType: 'guru' | 'staf' = 'staf') {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => sharedPortalApi.recordSelfAttendance('check_in', portalType),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SHARED_PORTAL_KEYS.selfAttendance(portalType) });
    },
  });
}

export function useSharedClockOut(portalType: 'guru' | 'staf' = 'staf') {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => sharedPortalApi.recordSelfAttendance('check_out', portalType),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SHARED_PORTAL_KEYS.selfAttendance(portalType) });
    },
  });
}

export function useSharedLeaveRequests(portalType: 'guru' | 'staf' = 'staf') {
  return useQuery({
    queryKey: SHARED_PORTAL_KEYS.leaveRequests(portalType),
    queryFn: () => sharedPortalApi.getLeaveRequests(portalType),
  });
}

export function useSharedCreateLeaveRequest(portalType: 'guru' | 'staf' = 'staf') {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateLeaveRequestInput) =>
      sharedPortalApi.createLeaveRequest(payload, portalType),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: SHARED_PORTAL_KEYS.leaveRequests(portalType) });
    },
  });
}

export function useSharedSalarySlips() {
  return useQuery({
    queryKey: SHARED_PORTAL_KEYS.salarySlips(),
    queryFn: () => sharedPortalApi.getSalarySlips(),
  });
}
