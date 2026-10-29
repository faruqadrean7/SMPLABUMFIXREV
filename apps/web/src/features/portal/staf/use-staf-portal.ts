import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { stafApi } from './staf-api';
import { CreateStaffTicketInput } from './types';

export const STAF_PORTAL_KEYS = {
  all: ['portal-staf'] as const,
  dashboard: () => [...STAF_PORTAL_KEYS.all, 'dashboard'] as const,
  tickets: () => [...STAF_PORTAL_KEYS.all, 'tickets'] as const,
};

export function useStaffDashboard() {
  return useQuery({
    queryKey: STAF_PORTAL_KEYS.dashboard(),
    queryFn: () => stafApi.getDashboardSummary(),
  });
}

export function useStaffTickets() {
  return useQuery({
    queryKey: STAF_PORTAL_KEYS.tickets(),
    queryFn: () => stafApi.getMyTickets(),
  });
}

export function useCreateStaffTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateStaffTicketInput) => stafApi.createTicket(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: STAF_PORTAL_KEYS.tickets() });
      queryClient.invalidateQueries({ queryKey: STAF_PORTAL_KEYS.dashboard() });
    },
  });
}
