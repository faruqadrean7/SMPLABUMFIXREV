import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  isReadOnlyRole,
  anonymizeStudentName,
  canPerformWriteOperation,
  resolveUserDestination,
  canAccessShell,
  hasPermission,
  READ_ONLY_ROLES,
} from '@/lib/rbac/evaluator';
import { tokenStorage } from '@/lib/auth/token-storage';
import { apiClient } from '@/lib/api/api-client';
import { ApiClientError } from '@/lib/api/errors';
import { User } from '@/types/auth';

describe('FASE 5: Read-Only Roles (Committee & Supervisor)', () => {
  const committeeUser: User = {
    id: 'usr-committee-01',
    schoolId: 'sch-01',
    username: 'komite_sekolah',
    email: 'komite@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T10:00:00Z',
    roles: ['committee'],
    permissions: [
      'executive.dashboard.view',
      'reports.view',
      'academic.attendance.read',
      'academic.grade.read',
      'ppdb.applicant.read',
    ],
    profile: {
      name: 'Drs. H. Mulyadi (Ketua Komite)',
      fullName: 'Drs. H. Mulyadi',
    },
  };

  const supervisorUser: User = {
    id: 'usr-supervisor-01',
    schoolId: 'sch-01',
    username: 'pengawas_dinas',
    email: 'pengawas@dinas.malang.go.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T11:00:00Z',
    roles: ['supervisor'],
    permissions: [
      'executive.dashboard.view',
      'reports.view',
      'academic.attendance.read',
      'academic.grade.read',
    ],
    profile: {
      name: 'Dr. Dra. Endang Sri M.Pd (Pengawas Pembina)',
      fullName: 'Dr. Dra. Endang Sri M.Pd',
    },
  };

  const principalUser: User = {
    id: 'usr-principal-01',
    schoolId: 'sch-01',
    username: 'kepala_sekolah',
    email: 'kepsek@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T08:00:00Z',
    roles: ['principal'],
    permissions: [
      'executive.dashboard.view',
      'executive.approval.manage',
      'reports.view',
      'academic.grade.write',
    ],
    profile: {
      name: 'Dr. Budi Santoso, M.Pd',
      fullName: 'Dr. Budi Santoso, M.Pd',
    },
  };

  afterEach(() => {
    tokenStorage.clearAll();
  });

  describe('1. Role Classification & Evaluator Helpers', () => {
    it('defines READ_ONLY_ROLES with committee and supervisor', () => {
      expect(READ_ONLY_ROLES).toContain('committee');
      expect(READ_ONLY_ROLES).toContain('supervisor');
      expect(READ_ONLY_ROLES).toHaveLength(2);
    });

    it('accurately identifies pure read-only roles with isReadOnlyRole', () => {
      expect(isReadOnlyRole(['committee'])).toBe(true);
      expect(isReadOnlyRole(['supervisor'])).toBe(true);
      expect(isReadOnlyRole(['committee', 'supervisor'])).toBe(true);

      // Roles with write/operational administrative authorities must not be marked as read-only
      expect(isReadOnlyRole(['principal'])).toBe(false);
      expect(isReadOnlyRole(['super_admin'])).toBe(false);
      expect(isReadOnlyRole(['teacher'])).toBe(false);
      expect(isReadOnlyRole(['admin_tu'])).toBe(false);
      expect(isReadOnlyRole(['treasurer'])).toBe(false);

      // Mixed roles: if user also has a write role, isReadOnlyRole returns false
      expect(isReadOnlyRole(['committee', 'teacher'])).toBe(false);
      expect(isReadOnlyRole(['supervisor', 'super_admin'])).toBe(false);

      // Empty / invalid roles
      expect(isReadOnlyRole([])).toBe(false);
      expect(isReadOnlyRole(null)).toBe(false);
      expect(isReadOnlyRole(undefined)).toBe(false);
    });

    it('evaluates canPerformWriteOperation accurately', () => {
      expect(canPerformWriteOperation(committeeUser)).toBe(false);
      expect(canPerformWriteOperation(supervisorUser)).toBe(false);
      expect(canPerformWriteOperation(principalUser)).toBe(true);
      expect(canPerformWriteOperation(null)).toBe(false);
      expect(canPerformWriteOperation(undefined)).toBe(false);
    });
  });

  describe('2. Student Data Privacy & Anonymization (UU PDP)', () => {
    it('anonymizes personal names to protect student privacy for supervisory roles', () => {
      // Single word name
      expect(anonymizeStudentName('Ahmad')).toBe('A***');

      // Two words name
      expect(anonymizeStudentName('Budi Santoso')).toBe('B*** S.');

      // Three words name
      expect(anonymizeStudentName('Dewi Sartika Putri')).toBe('D*** S. P.');

      // Edge cases: empty, null, whitespace
      expect(anonymizeStudentName('')).toBe('Siswa ***');
      expect(anonymizeStudentName('   ')).toBe('Siswa ***');
      expect(anonymizeStudentName(undefined)).toBe('Siswa ***');
    });
  });

  describe('3. Shell Navigation & Destination Routing', () => {
    it('does not route committee and supervisor to admin shell automatically (routes safely to portal hub)', () => {
      expect(resolveUserDestination(['committee'])).toBe('portal');
      expect(resolveUserDestination(['supervisor'])).toBe('portal');
      expect(resolveUserDestination(['committee'])).not.toBe('admin');
      expect(resolveUserDestination(['supervisor'])).not.toBe('admin');
    });

    it('denies automatic access to admin shell and personal portals for mitra roles', () => {
      expect(canAccessShell(['committee'], 'admin')).toBe(false);
      expect(canAccessShell(['supervisor'], 'admin')).toBe(false);

      expect(canAccessShell(['committee'], 'siswa')).toBe(false);
      expect(canAccessShell(['committee'], 'guru')).toBe(false);
      expect(canAccessShell(['committee'], 'staf')).toBe(false);
      expect(canAccessShell(['committee'], 'ortu')).toBe(false);

      expect(canAccessShell(['supervisor'], 'siswa')).toBe(false);
      expect(canAccessShell(['supervisor'], 'guru')).toBe(false);
      expect(canAccessShell(['supervisor'], 'staf')).toBe(false);
      expect(canAccessShell(['supervisor'], 'ortu')).toBe(false);
    });
  });

  describe('4. RBAC Permission Restrictions', () => {
    it('allows report and dashboard reading permissions for committee and supervisor', () => {
      expect(hasPermission(committeeUser, 'reports.view')).toBe(true);
      expect(hasPermission(committeeUser, 'executive.dashboard.view')).toBe(true);
      expect(hasPermission(supervisorUser, 'reports.view')).toBe(true);
      expect(hasPermission(supervisorUser, 'executive.dashboard.view')).toBe(true);
    });

    it('strictly denies write and approval permissions for committee and supervisor', () => {
      // Executive approval management
      expect(hasPermission(committeeUser, 'executive.approval.manage')).toBe(false);
      expect(hasPermission(supervisorUser, 'executive.approval.manage')).toBe(false);
      expect(hasPermission(principalUser, 'executive.approval.manage')).toBe(true);

      // Academic mutations
      expect(hasPermission(committeeUser, 'academic.grade.write')).toBe(false);
      expect(hasPermission(committeeUser, 'academic.attendance.write')).toBe(false);
      expect(hasPermission(supervisorUser, 'academic.grade.write')).toBe(false);
      expect(hasPermission(supervisorUser, 'academic.attendance.write')).toBe(false);

      // Confidential counseling cases
      expect(hasPermission(committeeUser, 'counseling.case.read')).toBe(false);
      expect(hasPermission(supervisorUser, 'counseling.case.read')).toBe(false);
    });
  });

  describe('5. API Client Write Mutation Guarding (403 Forbidden)', () => {
    beforeEach(() => {
      tokenStorage.setTokens('mock-access-token-ro');
    });

    it('blocks POST requests for committee user with 403 ApiClientError', async () => {
      tokenStorage.setUser(committeeUser);

      await expect(
        apiClient.post('/approvals/apr-123/review', { action: 'approve' })
      ).rejects.toThrow(ApiClientError);

      try {
        await apiClient.post('/academic/grades', { student_id: '1', score: 90 });
      } catch (err: any) {
        expect(err).toBeInstanceOf(ApiClientError);
        expect(err.statusCode).toBe(403);
        expect(err.code).toBe('FORBIDDEN');
        expect(err.message).toContain('Akses ditolak: Akun dengan peran baca-saja');
      }
    });

    it('blocks PUT and DELETE requests for supervisor user with 403 ApiClientError', async () => {
      tokenStorage.setUser(supervisorUser);

      try {
        await apiClient.put('/master/students/std-1', { name: 'Baru' });
      } catch (err: any) {
        expect(err).toBeInstanceOf(ApiClientError);
        expect(err.statusCode).toBe(403);
      }

      try {
        await apiClient.delete('/sarpras/tickets/tkt-1');
      } catch (err: any) {
        expect(err).toBeInstanceOf(ApiClientError);
        expect(err.statusCode).toBe(403);
      }
    });

    it('allows auth endpoints (e.g. /auth/logout) even for read-only user', async () => {
      tokenStorage.setUser(committeeUser);
      const originalAdapter = apiClient.defaults.adapter;
      apiClient.defaults.adapter = async () => ({
        data: { success: true },
        status: 200,
        statusText: 'OK',
        headers: {},
        config: {} as any,
      });

      await expect(
        apiClient.post('/auth/logout', { refreshToken: 'mock-ref' })
      ).resolves.toBeDefined();

      apiClient.defaults.adapter = originalAdapter;
    });

    it('allows write requests to pass through interceptor for principal or admin user', async () => {
      tokenStorage.setUser(principalUser);
      const originalAdapter = apiClient.defaults.adapter;
      apiClient.defaults.adapter = async () => ({
        data: { success: true },
        status: 200,
        statusText: 'OK',
        headers: {},
        config: {} as any,
      });

      await expect(
        apiClient.post('/academic/attendance', { class_id: '7a' })
      ).resolves.toBeDefined();

      apiClient.defaults.adapter = originalAdapter;
    });
  });
});
