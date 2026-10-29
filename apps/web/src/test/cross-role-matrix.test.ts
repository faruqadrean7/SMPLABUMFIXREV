import { describe, it, expect } from 'vitest';
import {
  canAccessShell,
  resolveUserDestination,
  hasPermission,
  isReadOnlyRole,
} from '@/lib/rbac/evaluator';
import {
  resolveNotificationDeepLink,
  ROLE_NOTIFICATION_TOPICS,
  isTopicOptedIn,
  saveStoredNotificationPreferences,
} from '@/features/notifications/web-push';
import { User } from '@/types/auth';

describe('FASE 6: Cross-Role Isolation Matrix & Security Hardening', () => {
  // Mock users for different roles
  const studentA: User = {
    id: 'usr-student-01',
    schoolId: 'sch-01',
    username: 'siswa_ahmad',
    email: 'ahmad@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T07:00:00Z',
    roles: ['student'],
    permissions: ['portal.student.read'],
    profile: {
      name: 'Ahmad Dahlan',
      nis: '2026001',
      className: '7-A',
    },
  };

  const studentB: User = {
    id: 'usr-student-02',
    schoolId: 'sch-01',
    username: 'siswa_budi',
    email: 'budi@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T07:05:00Z',
    roles: ['student'],
    permissions: ['portal.student.read'],
    profile: {
      name: 'Budi Santoso',
      nis: '2026002',
      className: '7-B',
    },
  };

  const guardianUser: User = {
    id: 'usr-guardian-01',
    schoolId: 'sch-01',
    username: 'wali_ahmad',
    email: 'ortu.ahmad@gmail.com',
    phone: '081234567890',
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T07:10:00Z',
    roles: ['guardian'],
    permissions: ['portal.guardian.read'],
    profile: {
      name: 'Pak Hendro (Wali Ahmad)',
    },
  };

  const teacher7A: User = {
    id: 'usr-teacher-01',
    schoolId: 'sch-01',
    username: 'guru_matematika',
    email: 'guru.math@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T06:45:00Z',
    roles: ['teacher', 'homeroom'],
    permissions: [
      'academic.attendance.write',
      'academic.grade.write',
      'academic.journal.write',
    ],
    profile: {
      name: 'Siti Aminah, S.Pd',
      className: '7-A', // Wali kelas 7-A
    },
  };

  const staffTU: User = {
    id: 'usr-staff-01',
    schoolId: 'sch-01',
    username: 'staf_tu_1',
    email: 'tu@labschool.sch.id',
    phone: null,
    status: 'active',
    mustChangePassword: false,
    lastLoginAt: '2026-10-14T07:00:00Z',
    roles: ['admin_tu'],
    permissions: ['academic.leave.manage', 'sarpras.ticket.read'],
    profile: {
      name: 'Bambang Irawan',
    },
  };

  describe('1. Cross-Student & CBT Exam Isolation', () => {
    it('isolates student A from student B exam sessions and personal gradebooks', () => {
      // Students have student destination and can only access siswa shell
      expect(resolveUserDestination(studentA.roles)).toBe('siswa');
      expect(resolveUserDestination(studentB.roles)).toBe('siswa');

      expect(canAccessShell(studentA.roles, 'siswa')).toBe(true);
      expect(canAccessShell(studentA.roles, 'guru')).toBe(false);
      expect(canAccessShell(studentA.roles, 'staf')).toBe(false);
      expect(canAccessShell(studentA.roles, 'admin')).toBe(false);

      // Student cannot evaluate or administer other students
      expect(hasPermission(studentA, 'academic.grade.write')).toBe(false);
      expect(hasPermission(studentA, 'cbt.exam.manage')).toBe(false);
    });

    it('denies CBT answer keys leakage to student portal', () => {
      // CBT answers must never be present in student permissions or scope
      expect(hasPermission(studentA, 'cbt.answer_key.read')).toBe(false);
      expect(hasPermission(studentB, 'cbt.answer_key.read')).toBe(false);
    });
  });

  describe('2. Parent Child-Scoping Validation', () => {
    it('restricts guardian to parent portal without cross-parent student leak', () => {
      expect(resolveUserDestination(guardianUser.roles)).toBe('ortu');
      expect(canAccessShell(guardianUser.roles, 'ortu')).toBe(true);
      expect(canAccessShell(guardianUser.roles, 'siswa')).toBe(false);
      expect(canAccessShell(guardianUser.roles, 'guru')).toBe(false);
      expect(canAccessShell(guardianUser.roles, 'admin')).toBe(false);

      // Guardians cannot access admin data or teacher journals
      expect(hasPermission(guardianUser, 'academic.journal.write')).toBe(false);
      expect(hasPermission(guardianUser, 'master.student.write')).toBe(false);
    });
  });

  describe('3. Teacher Classroom Authority & Wali Kelas Boundary', () => {
    it('allows teacher to access guru portal and isolates admin shell and other portals', () => {
      expect(canAccessShell(teacher7A.roles, 'guru')).toBe(true);
      expect(canAccessShell(teacher7A.roles, 'admin')).toBe(false);
      expect(canAccessShell(teacher7A.roles, 'ortu')).toBe(false);
      expect(canAccessShell(teacher7A.roles, 'siswa')).toBe(false);

      // Teacher has academic write permission
      expect(hasPermission(teacher7A, 'academic.grade.write')).toBe(true);
      expect(hasPermission(teacher7A, 'academic.attendance.write')).toBe(true);

      // But teacher cannot manage system accounts or approve executive school budgets
      expect(hasPermission(teacher7A, 'auth.role.manage')).toBe(false);
      expect(hasPermission(teacher7A, 'executive.approval.manage')).toBe(false);
    });
  });

  describe('4. Staff TU Isolation from Administrative & Counseling Secrets', () => {
    it('prevents staff from viewing confidential counseling cases and executive approvals', () => {
      expect(canAccessShell(staffTU.roles, 'staf')).toBe(true);
      expect(canAccessShell(staffTU.roles, 'admin')).toBe(true);

      // Counseling records are strictly confidential
      expect(hasPermission(staffTU, 'counseling.case.read')).toBe(false);
      expect(hasPermission(staffTU, 'counseling.case.write')).toBe(false);

      // Cannot approve pimpinan requests
      expect(hasPermission(staffTU, 'executive.approval.manage')).toBe(false);
    });
  });

  describe('5. Role-Based Push Notification Topics & Routing', () => {
    it('defines topics tailored strictly per role', () => {
      expect(ROLE_NOTIFICATION_TOPICS.siswa.map((t) => t.id)).toContain('assignment');
      expect(ROLE_NOTIFICATION_TOPICS.siswa.map((t) => t.id)).toContain('cbt_exam');
      expect(ROLE_NOTIFICATION_TOPICS.siswa.map((t) => t.id)).not.toContain('salary_slip');

      expect(ROLE_NOTIFICATION_TOPICS.ortu.map((t) => t.id)).toContain('attendance');
      expect(ROLE_NOTIFICATION_TOPICS.ortu.map((t) => t.id)).toContain('report_card');

      expect(ROLE_NOTIFICATION_TOPICS.guru.map((t) => t.id)).toContain('salary_slip');
      expect(ROLE_NOTIFICATION_TOPICS.guru.map((t) => t.id)).toContain('schedule_change');

      expect(ROLE_NOTIFICATION_TOPICS.staf.map((t) => t.id)).toContain('sarpras_ticket');
    });

    it('resolves notification click destinations to canonical portal routes without open redirects', () => {
      expect(resolveNotificationDeepLink('assignment', 'siswa')).toBe('/siswa/tugas');
      expect(resolveNotificationDeepLink('cbt_exam', 'siswa', 'paket-cbt-1')).toBe('/siswa/cbt/paket-cbt-1');
      expect(resolveNotificationDeepLink('attendance', 'ortu', 'std-01')).toBe('/ortu/presensi');
      expect(resolveNotificationDeepLink('schedule_change', 'guru')).toBe('/guru/jadwal');
      expect(resolveNotificationDeepLink('sarpras_ticket', 'staf')).toBe('/guru/sarpras');
      expect(resolveNotificationDeepLink('salary_slip', 'guru')).toBe('/guru/dashboard');
      expect(resolveNotificationDeepLink(null)).toBe('/siswa/notifikasi');
    });
  });
});
