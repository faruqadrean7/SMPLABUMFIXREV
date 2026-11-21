import { describe, it, expect, vi, beforeEach } from 'vitest';
import { extracurricularApi } from '@/features/student-affairs/extracurricular-api';
import { apiClient } from '@/lib/api/api-client';
import {
  ExtracurricularItem,
  ExtracurricularMemberItem,
  ExtracurricularJournalItem,
  ActivityDocumentItem,
  CreateExtracurricularPayload,
  UpdateExtracurricularPayload,
  RegisterMemberPayload,
  UpdateMemberPayload,
  CreateJournalPayload,
  CreateActivityDocumentPayload,
} from '@/features/student-affairs/extracurricular-types';

describe('ADM-STD-02 / BE-STD-02: Ekstrakurikuler & Keanggotaan Siswa SISTLABUM', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. MASTER EKSTRAKURIKULER
  // =========================================================================
  describe('1. Master Ekstrakurikuler (BE-STD-02 / ADM-STD-02)', () => {
    it('mengambil daftar ekstrakurikuler dengan filter kategori, pencarian, dan status aktif', async () => {
      const mockEkskuls: ExtracurricularItem[] = [
        {
          id: 'ekskul-1',
          name: 'Futsal Prestasi',
          category: 'Olahraga',
          description: 'Ekskul pembinaan futsal turnamen',
          quota: 25,
          schedule_day: 'Jumat',
          schedule_time: '15:30 - 17:30',
          is_active: true,
          active_members_count: 20,
          available_quota: 5,
          coach: { id: 'teacher-1', full_name: 'Agus Hendra, S.Pd', nip: '198501012010011005' },
        },
        {
          id: 'ekskul-2',
          name: 'Robotik & AI Club',
          category: 'Teknologi & Robotik',
          quota: 15,
          is_active: true,
          active_members_count: 10,
          available_quota: 5,
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockEkskuls,
          meta: { page: 1, limit: 10, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await extracurricularApi.getExtracurriculars({
        category: 'Olahraga',
        search: 'Futsal',
        is_active: true,
        page: 1,
        per_page: 10,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/extracurriculars', {
        params: {
          category: 'Olahraga',
          search: 'Futsal',
          is_active: true,
          page: 1,
          per_page: 10,
        },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].name).toBe('Futsal Prestasi');
      expect(res.data[0].available_quota).toBe(5);
    });

    it('mengambil detail satu ekstrakurikuler berdasarkan ID', async () => {
      const mockItem: ExtracurricularItem = {
        id: 'ekskul-1',
        name: 'Tari Saman Tradisional',
        category: 'Seni & Budaya',
        quota: 30,
        is_active: true,
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockItem },
      } as any);

      const res = await extracurricularApi.getExtracurricular('ekskul-1');
      expect(apiClient.get).toHaveBeenCalledWith('/extracurriculars/ekskul-1');
      expect(res.data.id).toBe('ekskul-1');
      expect(res.data.name).toBe('Tari Saman Tradisional');
    });

    it('membuat kegiatan ekstrakurikuler baru dengan kuota dan jadwal', async () => {
      const payload: CreateExtracurricularPayload = {
        name: 'Klub Debat Bahasa Inggris',
        category: 'Bahasa & Sastra',
        description: 'Persiapan lomba National English Debate',
        coach_id: 'teacher-2',
        quota: 20,
        schedule_day: 'Sabtu',
        schedule_time: '09:00 - 11:30',
        is_active: true,
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'ekskul-new',
            ...payload,
            active_members_count: 0,
            available_quota: 20,
          },
        },
      } as any);

      const res = await extracurricularApi.createExtracurricular(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/extracurriculars', payload);
      expect(res.data.id).toBe('ekskul-new');
      expect(res.data.quota).toBe(20);
    });

    it('memperbarui data ekstrakurikuler (kuota, jadwal, status aktif)', async () => {
      const updatePayload: UpdateExtracurricularPayload = {
        quota: 35,
        schedule_time: '16:00 - 18:00',
        is_active: true,
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'ekskul-1',
            name: 'Futsal Prestasi',
            quota: 35,
            schedule_time: '16:00 - 18:00',
            is_active: true,
          },
        },
      } as any);

      const res = await extracurricularApi.updateExtracurricular('ekskul-1', updatePayload);
      expect(apiClient.put).toHaveBeenCalledWith('/extracurriculars/ekskul-1', updatePayload);
      expect(res.data.quota).toBe(35);
    });

    it('menghapus ekstrakurikuler yang tidak memiliki anggota aktif', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Ekstrakurikuler berhasil dihapus.' } },
      } as any);

      const res = await extracurricularApi.deleteExtracurricular('ekskul-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/extracurriculars/ekskul-1');
      expect(res.data.message).toBe('Ekstrakurikuler berhasil dihapus.');
    });
  });

  // =========================================================================
  // 2. KEANGGOTAAN & PENILAIAN RAPOR
  // =========================================================================
  describe('2. Keanggotaan Siswa & Integrasi Nilai Rapor Semester', () => {
    it('mengambil daftar anggota berdasarkan ID ekstrakurikuler dengan filter role dan pencarian', async () => {
      const mockMembers: ExtracurricularMemberItem[] = [
        {
          id: 'mem-1',
          extracurricular_id: 'ekskul-1',
          student_id: 'std-1',
          role: 'ketua',
          status: 'aktif',
          final_grade: 'A',
          joined_at: '2026-08-01',
          student: {
            id: 'std-1',
            full_name: 'Doni Saputra',
            nis: '20261005',
            classes: [{ id: 'cls-1', name: 'X-A' }],
          },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockMembers,
          meta: { page: 1, limit: 20, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await extracurricularApi.getMembers({
        extracurricular_id: 'ekskul-1',
        role: 'ketua',
        search: 'Doni',
        page: 1,
        per_page: 20,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/extracurricular-members', {
        params: {
          extracurricular_id: 'ekskul-1',
          role: 'ketua',
          search: 'Doni',
          page: 1,
          per_page: 20,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].role).toBe('ketua');
      expect(res.data[0].final_grade).toBe('A');
      expect(res.data[0].student?.classes?.[0].name).toBe('X-A');
    });

    it('mengambil daftar keanggotaan berdasarkan ID siswa untuk portal dan rapor', async () => {
      const mockMembers: ExtracurricularMemberItem[] = [
        {
          id: 'mem-1',
          extracurricular_id: 'ekskul-1',
          student_id: 'std-1',
          role: 'anggota',
          status: 'aktif',
          final_grade: 'A',
          extracurricular: {
            id: 'ekskul-1',
            name: 'Futsal Prestasi',
            quota: 25,
            is_active: true,
          },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockMembers,
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await extracurricularApi.getMembers({
        student_id: 'std-1',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/extracurricular-members', {
        params: {
          student_id: 'std-1',
        },
      });
      expect(res.data[0].extracurricular?.name).toBe('Futsal Prestasi');
    });

    it('mendaftarkan siswa ke ekstrakurikuler dengan jabatan awal', async () => {
      const payload: RegisterMemberPayload = {
        extracurricular_id: 'ekskul-1',
        student_id: 'std-2',
        role: 'anggota',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'mem-2',
            ...payload,
            status: 'aktif',
            joined_at: '2026-10-13',
          },
        },
      } as any);

      const res = await extracurricularApi.registerMember(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/extracurricular-members', payload);
      expect(res.data.id).toBe('mem-2');
      expect(res.data.status).toBe('aktif');
    });

    it('memperbarui predikat nilai rapor semester dan jabatan anggota', async () => {
      const updatePayload: UpdateMemberPayload = {
        role: 'wakil_ketua',
        final_grade: 'A',
        status: 'aktif',
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'mem-1',
            role: 'wakil_ketua',
            final_grade: 'A',
            status: 'aktif',
          },
        },
      } as any);

      const res = await extracurricularApi.updateMember('mem-1', updatePayload);
      expect(apiClient.put).toHaveBeenCalledWith('/extracurricular-members/mem-1', updatePayload);
      expect(res.data.final_grade).toBe('A');
      expect(res.data.role).toBe('wakil_ketua');
    });

    it('mengeluarkan anggota dari ekstrakurikuler', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Anggota berhasil dikeluarkan dari ekstrakurikuler.' } },
      } as any);

      const res = await extracurricularApi.removeMember('mem-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/extracurricular-members/mem-1');
      expect(res.data.message).toContain('berhasil dikeluarkan');
    });
  });

  // =========================================================================
  // 3. JURNAL KEGIATAN & PRESENSI
  // =========================================================================
  describe('3. Jurnal Pertemuan & Presensi Pelatih/Pembina', () => {
    it('mengambil daftar jurnal pertemuan ekstrakurikuler', async () => {
      const mockJournals: ExtracurricularJournalItem[] = [
        {
          id: 'jrn-1',
          extracurricular_id: 'ekskul-1',
          session_date: '2026-10-09',
          start_time: '15:30',
          end_time: '17:30',
          activity: 'Latihan fisik sprint dan taktik serangan balik',
          notes: 'Kondisi lapangan basah',
          attendance: [
            { student_id: 'std-1', status: 'hadir' },
            { student_id: 'std-2', status: 'izin' },
          ],
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockJournals,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await extracurricularApi.getJournals({
        extracurricular_id: 'ekskul-1',
        page: 1,
        per_page: 15,
      });

      expect(apiClient.get).toHaveBeenCalledWith('/extracurricular-journals', {
        params: {
          extracurricular_id: 'ekskul-1',
          page: 1,
          per_page: 15,
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].activity).toContain('Latihan fisik sprint');
      expect(res.data[0].attendance).toHaveLength(2);
    });

    it('membuat catatan jurnal pertemuan baru dengan absensi kehadiran', async () => {
      const payload: CreateJournalPayload = {
        extracurricular_id: 'ekskul-1',
        session_date: '2026-10-13',
        start_time: '15:30',
        end_time: '17:00',
        activity: 'Latihan simulasi pertandingan mini cup',
        attendance: [
          { student_id: 'std-1', status: 'hadir' },
          { student_id: 'std-2', status: 'hadir' },
        ],
        notes: 'Semua anggota hadir tepat waktu',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'jrn-new',
            ...payload,
          },
        },
      } as any);

      const res = await extracurricularApi.createJournal(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/extracurricular-journals', payload);
      expect(res.data.id).toBe('jrn-new');
      expect(res.data.activity).toContain('simulasi pertandingan');
    });

    it('menghapus jurnal kegiatan', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Jurnal kegiatan berhasil dihapus.' } },
      } as any);

      const res = await extracurricularApi.deleteJournal('jrn-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/extracurricular-journals/jrn-1');
      expect(res.data.message).toBe('Jurnal kegiatan berhasil dihapus.');
    });
  });

  // =========================================================================
  // 4. REPOSITORI DOKUMEN KEGIATAN (PROPOSAL & LPJ)
  // =========================================================================
  describe('4. Repositori Dokumen Kegiatan & LPJ', () => {
    it('mengambil daftar dokumen kegiatan dengan filter doc_type', async () => {
      const mockDocs: ActivityDocumentItem[] = [
        {
          id: 'doc-1',
          extracurricular_id: 'ekskul-1',
          file_id: 'file-uuid-1',
          doc_type: 'proposal',
          title: 'Proposal Turnamen Futsal Cup 2026',
          description: 'Pengajuan anggaran seragam dan bola',
          uploaded_by: 'user-coach-1',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockDocs,
          meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await extracurricularApi.getDocuments({
        extracurricular_id: 'ekskul-1',
        doc_type: 'proposal',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/activity-documents', {
        params: {
          extracurricular_id: 'ekskul-1',
          doc_type: 'proposal',
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].doc_type).toBe('proposal');
      expect(res.data[0].title).toContain('Proposal');
    });

    it('mengunggah / mencatat dokumen proposal baru', async () => {
      const payload: CreateActivityDocumentPayload = {
        extracurricular_id: 'ekskul-1',
        file_id: 'file-uuid-2',
        doc_type: 'proposal',
        title: 'Pengajuan Sarana Latihan Semester Ganjil',
        description: 'Pembelian cone dan rompi latihan',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: {
          data: {
            id: 'doc-new',
            ...payload,
          },
        },
      } as any);

      const res = await extracurricularApi.createDocument(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/activity-documents', payload);
      expect(res.data.id).toBe('doc-new');
      expect(res.data.title).toContain('Pengajuan Sarana');
    });

    it('menghapus dokumen kegiatan', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Dokumen kegiatan berhasil dihapus.' } },
      } as any);

      const res = await extracurricularApi.deleteDocument('doc-1');
      expect(apiClient.delete).toHaveBeenCalledWith('/activity-documents/doc-1');
      expect(res.data.message).toBe('Dokumen kegiatan berhasil dihapus.');
    });
  });
});
