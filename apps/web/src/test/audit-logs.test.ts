import { describe, it, expect, vi, beforeEach } from 'vitest';
import { auditLogsApi } from '@/features/settings/audit-logs-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-SET-03 Audit Logs API Client', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('list requests /audit-logs with query params', async () => {
    const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: {
        data: [
          {
            id: 'log-1',
            actor_id: 'user-1',
            action: 'role.created',
            entity: 'Role',
            entity_id: 'role-123',
            ip: '127.0.0.1',
            user_agent: 'Mozilla/5.0',
            old_values: null,
            new_values: { name: 'Koordinator' },
            created_at: '2026-03-01T10:00:00Z',
            actor: { id: 'user-1', username: 'admin', email: 'admin@labschool.sch.id' },
          },
        ],
        meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
      },
    } as any);

    const res = await auditLogsApi.list({
      page: 1,
      limit: 15,
      q: 'role',
      action: 'role.created',
    });

    expect(getSpy).toHaveBeenCalledWith('/audit-logs', {
      params: {
        page: 1,
        limit: 15,
        q: 'role',
        action: 'role.created',
        actor_id: undefined,
        entity: undefined,
        entity_id: undefined,
        start_date: undefined,
        end_date: undefined,
      },
    });
    expect(res.data).toHaveLength(1);
    expect(res.data[0].action).toBe('role.created');
    expect(res.data[0].actor?.username).toBe('admin');
  });

  it('getDetail requests /audit-logs/:id', async () => {
    const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: {
        data: {
          id: 'log-1',
          action: 'user.roles_assigned',
          entity: 'User',
          entity_id: 'user-2',
          old_values: { roles: ['teacher'] },
          new_values: { roles: ['teacher', 'homeroom'] },
          created_at: '2026-03-01T11:00:00Z',
        },
      },
    } as any);

    const res = await auditLogsApi.getDetail('log-1');
    expect(getSpy).toHaveBeenCalledWith('/audit-logs/log-1');
    expect(res.action).toBe('user.roles_assigned');
    expect(res.old_values).toBeDefined();
    expect(res.new_values).toBeDefined();
  });

  describe('GAP-06 UI Telemetry Logger (Non-Authoritative)', () => {
    it('sends UI telemetry with ui. prefix to /audit-logs', async () => {
      const { uiTelemetryLogger } = await import('@/lib/audit/audit-logger');
      const postSpy = vi.spyOn(apiClient, 'post').mockResolvedValue({
        data: { data: { message: 'ok' } },
      } as any);

      const success = await uiTelemetryLogger.logButtonClick('export_grades', {
        format: 'csv',
      });

      expect(success).toBe(true);
      expect(postSpy).toHaveBeenCalledWith(
        '/audit-logs',
        expect.objectContaining({
          action: 'ui.button_click',
          entity: 'Button',
          new_values: expect.objectContaining({ button: 'export_grades', format: 'csv' }),
        })
      );
    });

    it('handles network failure gracefully without throwing', async () => {
      const { uiTelemetryLogger } = await import('@/lib/audit/audit-logger');
      vi.spyOn(apiClient, 'post').mockRejectedValue(new Error('Network error'));

      const success = await uiTelemetryLogger.logModalOpen('grade_edit_modal');
      expect(success).toBe(false);
    });
  });
});

