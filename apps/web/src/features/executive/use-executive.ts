import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { executiveApi } from './executive-api';
import { ReviewApprovalPayload } from './types';

export const EXECUTIVE_QUERY_KEYS = {
  all: ['executive'] as const,
  overview: () => [...EXECUTIVE_QUERY_KEYS.all, 'overview'] as const,
  approvals: () => [...EXECUTIVE_QUERY_KEYS.all, 'approvals'] as const,
};

export function useExecutiveOverview() {
  return useQuery({
    queryKey: EXECUTIVE_QUERY_KEYS.overview(),
    queryFn: () => executiveApi.getOverviewMetrics(),
  });
}

export function useApprovals() {
  return useQuery({
    queryKey: EXECUTIVE_QUERY_KEYS.approvals(),
    queryFn: () => executiveApi.getApprovals(),
  });
}

export function useReviewApproval() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: ReviewApprovalPayload }) =>
      executiveApi.reviewApproval(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: EXECUTIVE_QUERY_KEYS.approvals() });
      queryClient.invalidateQueries({ queryKey: EXECUTIVE_QUERY_KEYS.overview() });
    },
  });
}
