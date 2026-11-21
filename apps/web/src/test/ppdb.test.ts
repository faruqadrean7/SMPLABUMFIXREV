import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { waveSchema } from '@/features/ppdb/wave-dialog';
import { trackSchema } from '@/features/ppdb/track-dialog';
import { requirementSchema } from '@/features/ppdb/requirement-dialog';
import { decisionOverrideSchema } from '@/features/ppdb/decision-override-dialog';
import { enrollmentSchema } from '@/features/ppdb/enrollment-dialog';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-PPDB-01 s/d ADM-PPDB-06 PPDB Module', () => {
  describe('waveSchema validation', () => {
    it('passes with valid wave input', () => {
      const valid = {
        name: 'Gelombang 1 Reguler & Prestasi',
        open_at: '2026-05-01',
        close_at: '2026-05-30',
        result_publish_at: '2026-06-05',
        status: 'aktif' as const,
      };
      const result = waveSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when close_at is before open_at', () => {
      const invalid = {
        name: 'Gelombang Error',
        open_at: '2026-05-30',
        close_at: '2026-05-01',
        result_publish_at: '2026-06-05',
        status: 'draft' as const,
      };
      const result = waveSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain(
          'Tanggal tutup pendaftaran harus setelah tanggal buka'
        );
      }
    });

    it('fails when result_publish_at is before close_at', () => {
      const invalid = {
        name: 'Gelombang Invalid Result',
        open_at: '2026-05-01',
        close_at: '2026-05-30',
        result_publish_at: '2026-05-20',
        status: 'draft' as const,
      };
      const result = waveSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain(
          'Tanggal pengumuman hasil tidak boleh sebelum tanggal tutup pendaftaran'
        );
      }
    });
  });

  describe('trackSchema validation', () => {
    it('passes with valid track input and formula weights', () => {
      const valid = {
        wave_id: '123e4567-e89b-12d3-a456-426614174000',
        code: 'JALUR-PRESTASI',
        name: 'Jalur Prestasi Akademik',
        quota: 35,
        rapor_weight: 0.6,
        test_weight: 0.4,
      };
      const result = trackSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when code contains spaces or invalid characters', () => {
      const invalid = {
        wave_id: '123e4567-e89b-12d3-a456-426614174000',
        code: 'JALUR PRESTASI SPASI',
        name: 'Jalur Prestasi',
        quota: 30,
        rapor_weight: 0.6,
        test_weight: 0.4,
      };
      const result = trackSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain(
          'Kode hanya boleh berupa huruf, angka, strip, atau garis bawah'
        );
      }
    });

    it('fails when quota is 0 or negative', () => {
      const invalid = {
        wave_id: '123e4567-e89b-12d3-a456-426614174000',
        code: 'REGULER',
        name: 'Jalur Reguler',
        quota: 0,
        rapor_weight: 0.6,
        test_weight: 0.4,
      };
      const result = trackSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('Kuota minimal 1 calon siswa');
      }
    });
  });

  describe('requirementSchema validation', () => {
    it('passes with valid requirement', () => {
      const valid = {
        track_id: '123e4567-e89b-12d3-a456-426614174000',
        document_type: 'kartu_keluarga',
        is_required: true,
        description: 'Scan KK asli',
      };
      const result = requirementSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when document_type is empty', () => {
      const invalid = {
        track_id: '123e4567-e89b-12d3-a456-426614174000',
        document_type: '',
        is_required: true,
      };
      const result = requirementSchema.safeParse(invalid);
      expect(result.success).toBe(false);
    });
  });

  describe('decisionOverrideSchema validation', () => {
    it('passes with valid decision and detailed audit reason', () => {
      const valid = {
        decision: 'diterima' as const,
        reason: 'Penetapan rekomendasi afirmasi khusus dari rapat pleno panitia.',
      };
      const result = decisionOverrideSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('fails when reason is less than 5 characters', () => {
      const invalid = {
        decision: 'diterima' as const,
        reason: 'ok',
      };
      const result = decisionOverrideSchema.safeParse(invalid);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.errors[0].message).toContain('minimal 5 karakter');
      }
    });
  });

  describe('enrollmentSchema validation', () => {
    it('passes with valid enrollment input', () => {
      const valid = {
        nis: '202601001',
        nisn: '0081234567',
        class_id: '123e4567-e89b-12d3-a456-426614174000',
        academic_year_id: '123e4567-e89b-12d3-a456-426614174001',
        entry_date: '2026-07-15',
        force_enroll: false,
        notes: 'Penerimaan siswa baru',
      };
      const result = enrollmentSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });

    it('passes with force_enroll set to true for offline registration', () => {
      const valid = {
        nis: '202601002',
        nisn: '',
        class_id: '123e4567-e89b-12d3-a456-426614174000',
        academic_year_id: '123e4567-e89b-12d3-a456-426614174001',
        entry_date: '2026-07-16',
        force_enroll: true,
        notes: 'Daftar ulang langsung/luring di sekolah',
      };
      const result = enrollmentSchema.safeParse(valid);
      expect(result.success).toBe(true);
    });
  });

  describe('ppdbApi Client Methods', () => {
    beforeEach(() => {
      vi.restoreAllMocks();
    });

    it('calls GET /api/v1/ppdb/waves for listing waves', async () => {
      const mockResponse = { data: { data: [{ id: 'w1', name: 'Gelombang 1' }] } };
      vi.spyOn(apiClient, 'get').mockResolvedValue(mockResponse);

      const result = await ppdbApi.listWaves();
      expect(apiClient.get).toHaveBeenCalledWith('/ppdb/waves', { params: undefined });
      expect(result).toEqual([{ id: 'w1', name: 'Gelombang 1' }]);
    });

    it('calls POST /api/v1/ppdb/waves for creating wave', async () => {
      const payload = {
        name: 'Gelombang Baru',
        open_at: '2026-05-01',
        close_at: '2026-05-30',
        result_publish_at: '2026-06-05',
        status: 'aktif' as const,
      };
      const mockResponse = { data: { data: { id: 'w2', ...payload } } };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.createWave(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/waves', payload);
      expect(result.id).toBe('w2');
    });

    it('calls GET /api/v1/ppdb/applications with filter parameters', async () => {
      const mockResponse = {
        data: {
          data: [{ id: 'app-1', registration_no: 'REG-2026-001', full_name: 'Budi Santoso' }],
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue(mockResponse);

      const result = await ppdbApi.listApplicants({
        page: 1,
        limit: 15,
        status: 'verified',
        search: 'Budi',
      });
      expect(apiClient.get).toHaveBeenCalledWith('/ppdb/applications', {
        params: {
          page: 1,
          limit: 15,
          wave_id: undefined,
          track_id: undefined,
          status: 'verified',
          search: 'Budi',
        },
      });
      expect(result.data[0].full_name).toBe('Budi Santoso');
    });

    it('calls PATCH /api/v1/ppdb/documents/{id}/verify for verifying document', async () => {
      const mockResponse = {
        data: { data: { id: 'doc-1', status: 'valid', reject_reason: null } },
      };
      vi.spyOn(apiClient, 'patch').mockResolvedValue(mockResponse);

      const result = await ppdbApi.verifyDocument('doc-1', { status: 'valid' });
      expect(apiClient.patch).toHaveBeenCalledWith('/ppdb/documents/doc-1/verify', {
        status: 'valid',
        reject_reason: undefined,
      });
      expect(result.status).toBe('valid');
    });

    it('calls PATCH /api/v1/ppdb/applications/{id}/verify for verifying overall application', async () => {
      const mockResponse = {
        data: { data: { id: 'app-1', status: 'verified' } },
      };
      vi.spyOn(apiClient, 'patch').mockResolvedValue(mockResponse);

      const result = await ppdbApi.verifyApplication('app-1', {
        status: 'verified',
        note: 'Dokumen lengkap',
      });
      expect(apiClient.patch).toHaveBeenCalledWith('/ppdb/applications/app-1/verify', {
        status: 'verified',
        note: 'Dokumen lengkap',
      });
      expect(result.status).toBe('verified');
    });

    it('calls POST /api/v1/ppdb/waves/{id}/rank to execute ranking', async () => {
      const mockResponse = {
        data: {
          data: {
            wave_id: 'w1',
            wave_name: 'Gelombang 1',
            tracks: [],
            message: 'Perankingan sukses',
          },
        },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.rankWave('w1');
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/waves/w1/rank', {});
      expect(result.message).toBe('Perankingan sukses');
    });

    it('calls PATCH /api/v1/ppdb/applications/{id}/decision to override ranking decision', async () => {
      const mockResponse = {
        data: { data: { id: 'app-1', decision: 'diterima', status: 'selected' } },
      };
      vi.spyOn(apiClient, 'patch').mockResolvedValue(mockResponse);

      const result = await ppdbApi.overrideDecision('app-1', {
        decision: 'diterima',
        reason: 'Rekomendasi beasiswa',
      });
      expect(apiClient.patch).toHaveBeenCalledWith('/ppdb/applications/app-1/decision', {
        decision: 'diterima',
        reason: 'Rekomendasi beasiswa',
      });
      expect(result.decision).toBe('diterima');
    });

    it('calls POST /api/v1/ppdb/applications/{id}/enroll to convert applicant into student', async () => {
      const mockResponse = {
        data: { data: { message: 'Siswa berhasil dikonversi', student_id: 'std-1' } },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.enrollApplicant('app-1', {
        nis: '202601001',
        class_id: 'cls-1',
      });
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/applications/app-1/enroll', {
        nis: '202601001',
        class_id: 'cls-1',
      });
      expect(result.student_id).toBe('std-1');
    });

    it('calls GET /api/v1/ppdb/stats to retrieve statistics summary', async () => {
      const mockResponse = {
        data: {
          data: {
            total_applicants: 120,
            submitted_applicants: 95,
            status_counts: { verified: 80 },
            decision_counts: { diterima: 60 },
            tracks: [],
          },
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue(mockResponse);

      const result = await ppdbApi.getStats();
      expect(apiClient.get).toHaveBeenCalledWith('/ppdb/stats', { params: undefined });
      expect(result.total_applicants).toBe(120);
      expect(result.decision_counts.diterima).toBe(60);
    });

    it('calls POST /api/v1/ppdb/waves/{id}/escalate with confirmed flag', async () => {
      const mockResponse = {
        data: {
          data: {
            message: 'Eskalasi cadangan berhasil diproses',
            escalated_count: 3,
            promoted_applicants: [],
          },
        },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.escalateWaitlist('w1', { track_id: 'trk-1', confirmed: true });
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/waves/w1/escalate-waitlist', {
        track_id: 'trk-1',
        confirmed: true,
      });
      expect(result.escalated_count).toBe(3);
    });

    it('calls GET /api/v1/ppdb/applications/{id}/documents/{docId}/download with responseType blob', async () => {
      const mockBlob = new Blob(['pdf-content'], { type: 'application/pdf' });
      const mockResponse = { data: mockBlob };
      vi.spyOn(apiClient, 'get').mockResolvedValue(mockResponse);

      const result = await ppdbApi.downloadDocument('app-1', 'doc-1');
      expect(apiClient.get).toHaveBeenCalledWith(
        '/ppdb/applications/app-1/documents/doc-1/download',
        { responseType: 'blob' }
      );
      expect(result).toBe(mockBlob);
    });

    it('calls GET /api/v1/public/ppdb/waves/active for public wave info', async () => {
      const mockResponse = {
        data: { data: { id: 'w-act', name: 'Gelombang 1 Reguler', status: 'aktif' } },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue(mockResponse);

      const result = await ppdbApi.getActiveWave();
      expect(apiClient.get).toHaveBeenCalledWith('/public/ppdb/waves/active');
      expect(result?.name).toBe('Gelombang 1 Reguler');
    });

    it('calls POST /api/v1/public/ppdb/results/check to check selection result online', async () => {
      const mockResponse = {
        data: {
          data: {
            registration_no: 'REG-202610-001',
            full_name: 'Ahmad Fauzan',
            decision: 'diterima',
            status: 'selected',
            instruction: 'Selamat! Anda dinyatakan DITERIMA.',
            next_step: 're_registration',
          },
        },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.checkPublicResult({
        registration_no: 'REG-202610-001',
        birth_date: '2012-05-10',
      });
      expect(apiClient.post).toHaveBeenCalledWith('/public/ppdb/results/check', {
        registration_no: 'REG-202610-001',
        birth_date: '2012-05-10',
      });
      expect(result.decision).toBe('diterima');
      expect(result.next_step).toBe('re_registration');
    });

    it('calls GET /api/v1/public/ppdb/stats to retrieve public statistics', async () => {
      const mockResponse = {
        data: {
          data: {
            total_applicants: 80,
            submitted_applicants: 65,
            status_counts: {},
            decision_counts: {},
            tracks: [],
          },
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue(mockResponse);

      const result = await ppdbApi.getPublicStats();
      expect(apiClient.get).toHaveBeenCalledWith('/public/ppdb/stats');
      expect(result.total_applicants).toBe(80);
    });

    it('calls POST /api/v1/ppdb/register to register applicant account and send OTP', async () => {
      const mockResponse = {
        data: {
          data: {
            user_id: 'usr-123',
            email: 'calon@example.com',
            phone: '081234567890',
            full_name: 'Calon Siswa Baru',
            status: 'pending',
            expires_in: 900,
            message: 'Registrasi berhasil.',
          },
        },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const payload = {
        full_name: 'Calon Siswa Baru',
        email: 'calon@example.com',
        phone: '081234567890',
        password: 'Password123!',
        password_confirmation: 'Password123!',
      };
      const result = await ppdbApi.register(payload);
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/register', payload);
      expect(result.user_id).toBe('usr-123');
      expect(result.email).toBe('calon@example.com');
    });

    it('calls POST /api/v1/ppdb/verify-otp to verify OTP code', async () => {
      const mockResponse = {
        data: {
          data: {
            accessToken: 'sample-bearer-token',
            tokenType: 'Bearer',
            user: {
              id: 'usr-123',
              username: 'calon_siswa',
              email: 'calon@example.com',
              phone: '081234567890',
              status: 'active',
              roles: ['guest'],
            },
            message: 'Verifikasi berhasil.',
          },
        },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.verifyOtp({ email: 'calon@example.com', otp: '123456' });
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/verify-otp', { email: 'calon@example.com', otp: '123456' });
      expect(result.accessToken).toBe('sample-bearer-token');
      expect(result.user.status).toBe('active');
    });

    it('calls POST /api/v1/ppdb/login to authenticate applicant with registration_no or email', async () => {
      const mockResponse = {
        data: {
          data: {
            accessToken: 'applicant-session-token',
            tokenType: 'Bearer',
            expiresIn: 900,
            user: {
              id: 'usr-123',
              username: 'calon_siswa',
              email: 'calon@example.com',
              phone: '081234567890',
              status: 'active',
              roles: ['guest'],
            },
            applicant: {
              id: 'usr-123',
              username: 'calon_siswa',
              email: 'calon@example.com',
              phone: '081234567890',
              status: 'active',
              roles: ['guest'],
              applications: [],
            },
          },
        },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.login({ identifier: 'REG-2026-0001', password: 'Password123!' });
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/login', {
        identifier: 'REG-2026-0001',
        password: 'Password123!',
      });
      expect(result.accessToken).toBe('applicant-session-token');
    });

    it('calls GET /api/v1/ppdb/me to retrieve applicant profile', async () => {
      const mockResponse = {
        data: {
          data: {
            id: 'usr-123',
            username: 'calon_siswa',
            email: 'calon@example.com',
            phone: '081234567890',
            status: 'active',
            roles: ['guest'],
            applications: [
              {
                id: 'app-1',
                registration_no: 'REG-2026-0001',
                full_name: 'Calon Siswa',
                status: 'draft',
              },
            ],
          },
        },
      };
      vi.spyOn(apiClient, 'get').mockResolvedValue(mockResponse);

      const result = await ppdbApi.getMe();
      expect(apiClient.get).toHaveBeenCalledWith('/ppdb/me');
      expect(result.applications[0].registration_no).toBe('REG-2026-0001');
    });

    it('calls PATCH /api/v1/ppdb/applications/{id}/steps/{step} to autosave form step', async () => {
      const mockResponse = {
        data: {
          data: { id: 'app-1', status: 'draft', step_data: { personal: { full_name: 'Budi' } } },
        },
      };
      vi.spyOn(apiClient, 'patch').mockResolvedValue(mockResponse);

      const result = await ppdbApi.updateStep('app-1', 'personal', { full_name: 'Budi' });
      expect(apiClient.patch).toHaveBeenCalledWith('/ppdb/applications/app-1/steps/personal', { full_name: 'Budi' });
      expect(result.id).toBe('app-1');
    });

    it('calls POST /api/v1/ppdb/applications/{id}/submit to submit application', async () => {
      const mockResponse = {
        data: {
          data: { id: 'app-1', status: 'submitted' },
        },
      };
      vi.spyOn(apiClient, 'post').mockResolvedValue(mockResponse);

      const result = await ppdbApi.submitApplication('app-1');
      expect(apiClient.post).toHaveBeenCalledWith('/ppdb/applications/app-1/submit');
      expect(result.status).toBe('submitted');
    });
  });
});
