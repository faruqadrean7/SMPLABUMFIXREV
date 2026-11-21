import { describe, it, expect, vi, beforeEach } from 'vitest';
import { canAccessShell, hasPermission, getAccessiblePortals, getPortalRoleOptions } from '@/lib/rbac/evaluator';
import { ADMIN_NAV_SECTIONS, NavItem } from '@/lib/rbac/navigation';
import { executiveApi } from '@/features/executive';
import { apiClient } from '@/lib/api/api-client';
import { User } from '@/types/auth';

describe('FASE 4: Role Non-Portal Lewat /admin dengan RBAC Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('1. Matriks Akses Menu per Role', () => {
    it('Kepala Sekolah (principal) memiliki izin untuk melihat Dashboard Eksekutif & Antrean Persetujuan', () => {
      const principalUser: User = {
        id: 'u-kepsek',
        schoolId: 'sch-1',
        username: 'kepsek',
        email: 'kepsek@smp-labum.sch.id',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['principal'],
        permissions: ['executive.dashboard.view', 'executive.approval.manage', 'reports.view'],
        profile: { name: 'Dr. Bambang Suwarno' },
      };

      expect(hasPermission(principalUser, 'executive.dashboard.view')).toBe(true);
      expect(hasPermission(principalUser, 'executive.approval.manage')).toBe(true);
      expect(hasPermission(principalUser, 'reports.view')).toBe(true);
    });

    it('Guru BK (counselor) hanya dapat mengakses modul bimbingan konseling dan tidak dapat melihat persetujuan eksekutif', () => {
      const counselorUser: User = {
        id: 'u-bk',
        schoolId: 'sch-1',
        username: 'gurubk',
        email: 'bk@smp-labum.sch.id',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['counselor'],
        permissions: ['counseling.case.read', 'counseling.case.write', 'master.student.read'],
        profile: { name: 'Ibu Rahayu, S.Psi.' },
      };

      expect(hasPermission(counselorUser, 'counseling.case.read')).toBe(true);
      expect(hasPermission(counselorUser, 'executive.approval.manage')).toBe(false);
      expect(hasPermission(counselorUser, 'facility.asset.manage')).toBe(false);
    });

    it('Pustakawan (librarian) hanya memiliki akses ke modul perpustakaan', () => {
      const librarianUser: User = {
        id: 'u-lib',
        schoolId: 'sch-1',
        username: 'pustakawan',
        email: 'perpus@smp-labum.sch.id',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['librarian'],
        permissions: ['library.book.manage', 'facility.asset.manage'],
        profile: { name: 'Pak Joko Pustaka' },
      };

      expect(hasPermission(librarianUser, 'library.book.manage')).toBe(true);
      expect(hasPermission(librarianUser, 'counseling.case.read')).toBe(false);
      expect(hasPermission(librarianUser, 'cbt.exam.manage')).toBe(false);
    });

    it('Petugas Sarpras (facility_staff) hanya dapat mengelola aset, booking, dan tiket fasilitas', () => {
      const sarprasUser: User = {
        id: 'u-sarpras',
        schoolId: 'sch-1',
        username: 'sarpras',
        email: 'sarpras@smp-labum.sch.id',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['facility_staff'],
        permissions: ['facility.asset.manage', 'facility.booking.manage'],
        profile: { name: 'Pak Agus Sarpras' },
      };

      expect(hasPermission(sarprasUser, 'facility.asset.manage')).toBe(true);
      expect(hasPermission(sarprasUser, 'facility.booking.manage')).toBe(true);
      expect(hasPermission(sarprasUser, 'academic.grade.write')).toBe(false);
    });

    it('Kesiswaan (student_affairs) dapat mengelola kedisiplinan dan ekskul tetapi tidak dapat membuka data audit atau BK sensitif', () => {
      const studentAffairsUser: User = {
        id: 'u-kesiswaan',
        schoolId: 'sch-1',
        username: 'kesiswaan',
        email: 'kesiswaan@smp-labum.sch.id',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['student_affairs'],
        permissions: ['student.point.write', 'student.point.read', 'master.student.read'],
        profile: { name: 'Pembina OSIS & Kesiswaan' },
      };

      expect(hasPermission(studentAffairsUser, 'student.point.write')).toBe(true);
      expect(hasPermission(studentAffairsUser, 'audit.read')).toBe(false);
      expect(hasPermission(studentAffairsUser, 'counseling.case.write')).toBe(false);
    });

    it('Super Admin otomatis memiliki izin bypass untuk seluruh menu dan tindakan', () => {
      const superAdminUser: User = {
        id: 'u-sa',
        schoolId: 'sch-1',
        username: 'root',
        email: 'admin@smp-labum.sch.id',
        phone: null,
        status: 'active',
        mustChangePassword: false,
        lastLoginAt: null,
        roles: ['super_admin'],
        permissions: [],
        profile: { name: 'Root Administrator' },
      };

      expect(hasPermission(superAdminUser, 'executive.approval.manage')).toBe(true);
      expect(hasPermission(superAdminUser, 'counseling.case.read')).toBe(true);
      expect(hasPermission(superAdminUser, 'any.arbitrary.permission')).toBe(true);
    });
  });

  describe('2. Antrean Persetujuan & Validasi Alasan Penolakan', () => {
    it('mengambil daftar antrean pengajuan persetujuan (rapor, cuti, sarpras)', async () => {
      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: [
            {
              id: 'app-01',
              code: 'APR-2026-001',
              category: 'report_card',
              title: 'Penguncian Nilai Akhir Semester Gasal Kelas 9-A',
              requesterName: 'Budi Raharjo, S.Pd.',
              requesterRole: 'Wali Kelas 9-A',
              details: 'Seluruh nilai KD 3.1 - KD 3.8 dan PTS telah terinput lengkap 100%.',
              submittedAt: '2026-10-14 08:30',
              status: 'pending',
            },
          ],
        },
      });
      const approvals = await executiveApi.getApprovals();
      expect(approvals.length).toBeGreaterThan(0);
      expect(approvals[0]).toHaveProperty('code');
      expect(approvals[0]).toHaveProperty('category');
      expect(approvals[0]).toHaveProperty('requesterName');
    });

    it('dapat menyetujui pengajuan berkas resmi', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          success: true,
          message: 'Pengajuan berhasil disetujui dan diverifikasi secara resmi.',
          reviewedAt: new Date().toISOString(),
        },
      });
      const res = await executiveApi.reviewApproval('app-01', {
        action: 'approve',
        notes: 'Disetujui untuk penguncian nilai',
      });
      expect(res.success).toBe(true);
      expect(res.message).toContain('berhasil disetujui');
    });

    it('menolak aksi reject jika alasan penolakan kosong', async () => {
      await expect(
        executiveApi.reviewApproval('app-02', {
          action: 'reject',
          rejectionReason: '',
        })
      ).rejects.toThrow('Alasan penolakan wajib diisi.');
    });

    it('berhasil memproses penolakan jika alasan tertulis diisi dengan jelas', async () => {
      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          success: true,
          message: 'Pengajuan telah ditolak beserta alasan tertulis.',
          reviewedAt: new Date().toISOString(),
        },
      });
      const res = await executiveApi.reviewApproval('app-02', {
        action: 'reject',
        rejectionReason: 'Berkas rekapitulasi belum ditandatangani oleh wali kelas bersangkutan.',
      });
      expect(res.success).toBe(true);
      expect(res.message).toContain('telah ditolak');
    });
  });

  describe('3. Role Switcher Multi-Peran (Guru Pengajar yang juga Wakasek)', () => {
    it('menghasilkan opsi beralih antara Portal Guru dan Portal Pimpinan untuk guru yang juga wakasek', () => {
      const dualRoles = ['teacher', 'vice_principal'];
      const accessible = getPortalRoleOptions(dualRoles);

      const portalKeys = accessible.map((p) => p.portal);
      expect(portalKeys).toContain('kepsek');
      expect(portalKeys).toContain('guru');
      expect(portalKeys).not.toContain('siswa');
      expect(portalKeys).not.toContain('ortu');
    });

    it('hanya menampilkan 1 opsi portal untuk siswa murni sehingga role switcher tidak perlu muncul', () => {
      const studentRoles = ['student'];
      const accessible = getPortalRoleOptions(studentRoles);

      expect(accessible.length).toBe(1);
      expect(accessible[0].portal).toBe('siswa');
    });

    it('mengizinkan akses shell admin bagi seluruh peran manajemen resmi', () => {
      expect(canAccessShell(['principal'], 'admin')).toBe(true);
      expect(canAccessShell(['vice_principal'], 'admin')).toBe(true);
      expect(canAccessShell(['counselor'], 'admin')).toBe(true);
      expect(canAccessShell(['facility_staff'], 'admin')).toBe(true);
      expect(canAccessShell(['librarian'], 'admin')).toBe(true);
      expect(canAccessShell(['student_affairs'], 'admin')).toBe(true);
      expect(canAccessShell(['treasurer'], 'admin')).toBe(true);
      expect(canAccessShell(['operator_cms'], 'admin')).toBe(true);
      expect(canAccessShell(['student'], 'admin')).toBe(false);
      expect(canAccessShell(['guardian'], 'admin')).toBe(false);
    });
  });
});
