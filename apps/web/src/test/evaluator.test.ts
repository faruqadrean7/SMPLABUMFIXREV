import { describe, it, expect } from 'vitest';
import {
  resolveUserDestination,
  getDestinationPath,
  getAccessiblePortals,
  canAccessShell,
  hasPermission,
  sanitizeRoles,
} from '@/lib/rbac/evaluator';
import { User } from '@/types/auth';

describe('RBAC Evaluator & Route Protection', () => {
  describe('sanitizeRoles defensive normalization', () => {
    it('handles null, undefined, numbers, strings, and objects gracefully', () => {
      expect(sanitizeRoles(null)).toEqual([]);
      expect(sanitizeRoles(undefined)).toEqual([]);
      expect(sanitizeRoles('admin' as any)).toEqual([]);
      expect(sanitizeRoles(123 as any)).toEqual([]);
      expect(sanitizeRoles({ role: 'admin' } as any)).toEqual([]);
    });

    it('filters out empty or whitespace-only role strings and resolves aliases', () => {
      expect(sanitizeRoles(['super_admin', '', '   ', 'teacher', null, undefined])).toEqual(['super_admin', 'teacher']);
      expect(sanitizeRoles(['admin', '', '   ', 'teacher', null, undefined])).toEqual(['admin', 'admin_tu', 'teacher']);
    });
  });

  describe('Destination and shell resolution', () => {
    it('resolves student role to /portal/siswa', () => {
      expect(resolveUserDestination(['student'])).toBe('siswa');
      expect(getDestinationPath('siswa')).toBe('/portal/siswa');
    });

    it('resolves guardian role to /portal/ortu', () => {
      expect(resolveUserDestination(['guardian'])).toBe('ortu');
      expect(getDestinationPath('ortu')).toBe('/portal/ortu');
    });

    it('resolves teacher, admin_tu, and principal roles to their legitimate portals', () => {
      expect(resolveUserDestination(['teacher'])).toBe('guru');
      expect(resolveUserDestination(['admin_tu'])).toBe('staf');
      expect(getDestinationPath('staf')).toBe('/portal/staf');
      expect(resolveUserDestination(['principal'])).toBe('kepsek');
      expect(getDestinationPath('kepsek')).toBe('/portal/kepsek');
      expect(resolveUserDestination(['vice_principal'])).toBe('kepsek');
      expect(resolveUserDestination(['super_admin'])).toBe('admin');
      expect(getDestinationPath('admin')).toBe('/admin');
    });

    it('handles malformed or empty roles safely without granting admin access', () => {
      expect(resolveUserDestination(null)).not.toBe('admin');
      expect(resolveUserDestination(undefined)).not.toBe('admin');
      expect(resolveUserDestination([])).not.toBe('admin');
    });

    it('detects accessible portals for multi-role user (e.g. teacher + guardian)', () => {
      const portals = getAccessiblePortals(['teacher', 'guardian']);
      expect(portals).toHaveLength(2);
      expect(portals.map((p) => p.portal)).toContain('guru');
      expect(portals.map((p) => p.portal)).toContain('ortu');
    });

    it('returns empty portals array on null or invalid role input', () => {
      expect(getAccessiblePortals(null)).toEqual([]);
      expect(getAccessiblePortals(undefined)).toEqual([]);
    });
  });

  describe('Shell access and permission gate', () => {
    it('correctly isolates navigation shells with canAccessShell', () => {
      // Admin shell
      expect(canAccessShell(['admin_tu'], 'admin')).toBe(true);
      expect(canAccessShell(['teacher'], 'admin')).toBe(false);
      expect(canAccessShell(['teacher'], 'guru')).toBe(true);
      expect(canAccessShell(['student'], 'admin')).toBe(false);
      expect(canAccessShell(['guardian'], 'admin')).toBe(false);

      // Student shell
      expect(canAccessShell(['student'], 'siswa')).toBe(true);
      expect(canAccessShell(['guardian'], 'siswa')).toBe(false);

      // Parent shell
      expect(canAccessShell(['guardian'], 'ortu')).toBe(true);
      expect(canAccessShell(['student'], 'ortu')).toBe(false);

      // Super Admin has universal access
      expect(canAccessShell(['super_admin'], 'admin')).toBe(true);
      expect(canAccessShell(['super_admin'], 'siswa')).toBe(true);
      expect(canAccessShell(['super_admin'], 'ortu')).toBe(true);

      // Defensive handling
      expect(canAccessShell(null, 'admin')).toBe(false);
      expect(canAccessShell(undefined, 'admin')).toBe(false);
    });

    it('evaluates permissions accurately with hasPermission', () => {
      const teacherUser: User = {
        id: 'usr-1',
        schoolId: 'sch-1',
        username: 'guru_matematika',
        email: 'guru@labschool.sch.id',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['teacher'],
        permissions: ['academic.journal.write', 'academic.grade.write'],
        profile: { fullName: 'Budi Santoso' },
      };

      expect(hasPermission(teacherUser, 'academic.journal.write')).toBe(true);
      expect(hasPermission(teacherUser, 'master.academic_year.manage')).toBe(false);
      expect(hasPermission(teacherUser, ['master.academic_year.manage', 'academic.grade.write'])).toBe(true);

      const superAdminUser: User = {
        ...teacherUser,
        roles: ['super_admin'],
        permissions: [],
      };
      expect(hasPermission(superAdminUser, 'any.unassigned.permission')).toBe(true);

      // Null user handling
      expect(hasPermission(null, 'academic.journal.write')).toBe(false);
      expect(hasPermission(undefined, 'academic.journal.write')).toBe(false);
      expect(hasPermission(teacherUser, undefined)).toBe(true);
    });
  });
});
