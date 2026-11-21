import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  PPDBWaveItem,
  PPDBTrackItem,
  PPDBRequirementItem,
  ApplicantItem,
  ApplicantDetail,
  ApplicantQueryParams,
  CreateWavePayload,
  UpdateWavePayload,
  CreateTrackPayload,
  UpdateTrackPayload,
  CreateRequirementPayload,
  VerifyDocumentPayload,
  VerifyApplicationPayload,
  RankApplicantsPayload,
  OverrideDecisionPayload,
  EnrollApplicantPayload,
  PPDBWaveRankingsResponse,
  PPDBRankExecutionResult,
  ApplicantDocumentItem,
  PPDBStatsResponse,
  CheckPPDBResultPayload,
  PPDBPublicResult,
  RegisterApplicantPayload,
  RegisterApplicantResponse,
  VerifyOtpPayload,
  VerifyOtpResponse,
  PPDBLoginPayload,
  PPDBLoginResponse,
  ApplicantProfileResponse,
} from '@/types/ppdb';

export const ppdbApi = {
  // =========================================================================
  // BE-PPDB-01 / ADM-PPDB-01: Gelombang & Jalur
  // =========================================================================

  /**
   * Mengambil daftar gelombang PPDB (Admin)
   * GET /api/v1/ppdb/waves
   */
  async listWaves(params?: { page?: number; limit?: number; school_id?: string }): Promise<PaginatedResponse<PPDBWaveItem> | PPDBWaveItem[]> {
    const res = await apiClient.get<PaginatedResponse<PPDBWaveItem> | ApiResponse<PPDBWaveItem[]>>('/ppdb/waves', {
      params,
    });
    return (res.data as any).data ?? res.data;
  },

  /**
   * Mengambil detail gelombang PPDB tunggal
   * GET /api/v1/ppdb/waves/{id}
   */
  async getWave(id: string): Promise<PPDBWaveItem> {
    const res = await apiClient.get<ApiResponse<PPDBWaveItem>>(`/ppdb/waves/${id}`);
    return res.data.data;
  },

  /**
   * Membuat gelombang PPDB baru
   * POST /api/v1/ppdb/waves
   */
  async createWave(data: CreateWavePayload): Promise<PPDBWaveItem> {
    const res = await apiClient.post<ApiResponse<PPDBWaveItem>>('/ppdb/waves', data);
    return res.data.data;
  },

  /**
   * Memperbarui gelombang PPDB
   * PUT /api/v1/ppdb/waves/{id}
   */
  async updateWave(id: string, data: UpdateWavePayload): Promise<PPDBWaveItem> {
    const res = await apiClient.put<ApiResponse<PPDBWaveItem>>(`/ppdb/waves/${id}`, data);
    return res.data.data;
  },

  /**
   * Menghapus gelombang PPDB
   * DELETE /api/v1/ppdb/waves/{id}
   */
  async deleteWave(id: string): Promise<{ message: string }> {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/ppdb/waves/${id}`);
    return res.data.data;
  },

  /**
   * Mengambil daftar jalur PPDB
   * GET /api/v1/ppdb/tracks
   */
  async listTracks(params?: { wave_id?: string; limit?: number }): Promise<PaginatedResponse<PPDBTrackItem> | PPDBTrackItem[]> {
    const res = await apiClient.get<PaginatedResponse<PPDBTrackItem> | ApiResponse<PPDBTrackItem[]>>('/ppdb/tracks', {
      params,
    });
    return (res.data as any).data ?? res.data;
  },

  /**
   * Membuat jalur PPDB baru
   * POST /api/v1/ppdb/tracks
   */
  async createTrack(data: CreateTrackPayload): Promise<PPDBTrackItem> {
    const res = await apiClient.post<ApiResponse<PPDBTrackItem>>('/ppdb/tracks', data);
    return res.data.data;
  },

  /**
   * Memperbarui jalur PPDB
   * PUT /api/v1/ppdb/tracks/{id}
   */
  async updateTrack(id: string, data: UpdateTrackPayload): Promise<PPDBTrackItem> {
    const res = await apiClient.put<ApiResponse<PPDBTrackItem>>(`/ppdb/tracks/${id}`, data);
    return res.data.data;
  },

  /**
   * Menghapus jalur PPDB
   * DELETE /api/v1/ppdb/tracks/{id}
   */
  async deleteTrack(id: string): Promise<{ message: string }> {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/ppdb/tracks/${id}`);
    return res.data.data;
  },

  /**
   * Mengambil daftar syarat berkas dokumen
   * GET /api/v1/ppdb/requirements
   */
  async listRequirements(params?: { track_id?: string }): Promise<PPDBRequirementItem[]> {
    const res = await apiClient.get<ApiResponse<PPDBRequirementItem[]>>('/ppdb/requirements', {
      params,
    });
    return res.data.data;
  },

  /**
   * Menambah syarat berkas dokumen baru untuk jalur
   * POST /api/v1/ppdb/requirements
   */
  async createRequirement(data: CreateRequirementPayload): Promise<PPDBRequirementItem> {
    const res = await apiClient.post<ApiResponse<PPDBRequirementItem>>('/ppdb/requirements', data);
    return res.data.data;
  },

  /**
   * Menghapus syarat berkas dokumen
   * DELETE /api/v1/ppdb/requirements/{id}
   */
  async deleteRequirement(id: string): Promise<{ message: string }> {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/ppdb/requirements/${id}`);
    return res.data.data;
  },

  // =========================================================================
  // BE-PPDB-03 & BE-PPDB-05 / ADM-PPDB-02 & ADM-PPDB-03: Calon Siswa & Verifikasi
  // =========================================================================

  /**
   * Mengambil daftar pendaftaran calon siswa (Admin)
   * GET /api/v1/ppdb/applications
   */
  async listApplicants(params?: ApplicantQueryParams): Promise<PaginatedResponse<ApplicantItem>> {
    const res = await apiClient.get<PaginatedResponse<ApplicantItem>>('/ppdb/applications', {
      params: {
        page: params?.page || 1,
        limit: params?.limit || 15,
        wave_id: params?.wave_id || undefined,
        track_id: params?.track_id || undefined,
        status: params?.status || undefined,
        search: params?.search || undefined,
      },
    });
    return res.data;
  },

  /**
   * Mengambil detail pendaftaran calon siswa lengkap beserta berkas & histori
   * GET /api/v1/ppdb/applications/{id}
   */
  async getApplicant(id: string): Promise<ApplicantDetail> {
    const res = await apiClient.get<ApiResponse<ApplicantDetail>>(`/ppdb/applications/${id}`);
    return res.data.data;
  },

  /**
   * Verifikasi berkas dokumen individual (valid atau ditolak dengan alasan)
   * PATCH /api/v1/ppdb/documents/{id}/verify
   */
  async verifyDocument(documentId: string, data: VerifyDocumentPayload): Promise<ApplicantDocumentItem> {
    const res = await apiClient.patch<ApiResponse<ApplicantDocumentItem>>(
      `/ppdb/documents/${documentId}/verify`,
      data
    );
    return res.data.data;
  },

  /**
   * Verifikasi status aplikasi calon siswa keseluruhan (verified / revision)
   * PATCH /api/v1/ppdb/applications/{id}/verify
   */
  async verifyApplication(applicantId: string, data: VerifyApplicationPayload): Promise<ApplicantDetail> {
    const res = await apiClient.patch<ApiResponse<ApplicantDetail>>(
      `/ppdb/applications/${applicantId}/verify`,
      data
    );
    return res.data.data;
  },

  // =========================================================================
  // BE-PPDB-06 / ADM-PPDB-04: Seleksi & Perankingan
  // =========================================================================

  /**
   * Menjalankan algoritma perangkingan dan alokasi kuota gelombang
   * POST /api/v1/ppdb/waves/{id}/rank
   */
  async rankWave(waveId: string, data?: RankApplicantsPayload): Promise<PPDBRankExecutionResult> {
    const res = await apiClient.post<ApiResponse<PPDBRankExecutionResult>>(
      `/ppdb/waves/${waveId}/rank`,
      data || {}
    );
    return res.data.data;
  },

  /**
   * Mengambil daftar peringkat seleksi calon siswa per gelombang
   * GET /api/v1/ppdb/waves/{id}/rankings
   */
  async getRankings(
    waveId: string,
    params?: { track_id?: string; decision?: string }
  ): Promise<PPDBWaveRankingsResponse> {
    const res = await apiClient.get<ApiResponse<PPDBWaveRankingsResponse>>(
      `/ppdb/waves/${waveId}/rankings`,
      { params }
    );
    return res.data.data;
  },

  /**
   * Melakukan penyesuaian (override) keputusan seleksi manual disertai alasan wajib
   * PATCH /api/v1/ppdb/applications/{id}/decision
   */
  async overrideDecision(applicantId: string, data: OverrideDecisionPayload): Promise<ApplicantDetail> {
    const res = await apiClient.patch<ApiResponse<ApplicantDetail>>(
      `/ppdb/applications/${applicantId}/decision`,
      data
    );
    return res.data.data;
  },

  /**
   * Mempublikasikan pengumuman hasil seleksi gelombang PPDB ke publik dan notifikasi
   * POST /api/v1/ppdb/waves/{id}/publish-results
   */
  async publishResults(waveId: string): Promise<Record<string, unknown>> {
    const res = await apiClient.post<ApiResponse<Record<string, unknown>>>(
      `/ppdb/waves/${waveId}/publish-results`
    );
    return res.data.data;
  },

  /**
   * Mengunduh berkas fisik dokumen pendaftaran secara terotentikasi (Blob)
   * GET /api/v1/ppdb/applications/{id}/documents/{documentId}/download
   */
  async downloadDocument(applicantId: string, documentId: string): Promise<Blob> {
    const res = await apiClient.get<Blob>(
      `/ppdb/applications/${applicantId}/documents/${documentId}/download`,
      {
        responseType: 'blob',
      }
    );
    return res.data;
  },

  /**
   * Mempromosikan calon cadangan (waitlist) setelah batas waktu pendaftaran ulang
   * POST /api/v1/ppdb/waves/{id}/escalate-waitlist
   */
  async escalateWaitlist(
    waveId: string,
    data?: { track_id?: string; quota?: number; confirmed?: boolean; force?: boolean }
  ): Promise<Record<string, unknown>> {
    const res = await apiClient.post<ApiResponse<Record<string, unknown>>>(
      `/ppdb/waves/${waveId}/escalate-waitlist`,
      data || {}
    );
    return res.data.data;
  },

  // =========================================================================
  // BE-PPDB-08 / ADM-PPDB-05: Daftar Ulang & Konversi ke Siswa Aktif
  // =========================================================================

  /**
   * Konversi pendaftar yang diterima/daftar ulang menjadi siswa aktif di Master Data
   * POST /api/v1/ppdb/applications/{id}/enroll
   */
  async enrollApplicant(applicantId: string, data: EnrollApplicantPayload): Promise<Record<string, unknown>> {
    const res = await apiClient.post<ApiResponse<Record<string, unknown>>>(
      `/ppdb/applications/${applicantId}/enroll`,
      data
    );
    return res.data.data;
  },

  // =========================================================================
  // BE-PPDB-09 / ADM-PPDB-06: Statistik & Bukti Pendaftaran
  // =========================================================================

  /**
   * Mengambil statistik komprehensif PPDB (total pendaftar, breakdown status, keterisian kuota)
   * GET /api/v1/ppdb/stats
   */
  async getStats(params?: { wave_id?: string; start_date?: string; end_date?: string }): Promise<PPDBStatsResponse> {
    const res = await apiClient.get<ApiResponse<PPDBStatsResponse>>('/ppdb/stats', {
      params,
    });
    return res.data.data;
  },

  // =========================================================================
  // PUBLIC SPMB / PPDB ONLINE (Calon Siswa & Wali Murid)
  // =========================================================================

  /**
   * Mengambil data gelombang PPDB yang sedang aktif saat ini
   * GET /api/v1/public/ppdb/waves/active
   */
  async getActiveWave(): Promise<PPDBWaveItem | null> {
    const res = await apiClient.get<ApiResponse<PPDBWaveItem | null>>('/public/ppdb/waves/active');
    return res.data.data;
  },

  /**
   * Mengambil daftar seluruh gelombang PPDB publik
   * GET /api/v1/public/ppdb/waves
   */
  async listPublicWaves(): Promise<PPDBWaveItem[]> {
    const res = await apiClient.get<ApiResponse<PPDBWaveItem[]>>('/public/ppdb/waves');
    return Array.isArray(res.data.data) ? res.data.data : [];
  },

  /**
   * Mengambil daftar jalur PPDB publik (beserta kuota dan persyaratan)
   * GET /api/v1/public/ppdb/tracks
   */
  async listPublicTracks(waveId?: string): Promise<PPDBTrackItem[]> {
    const res = await apiClient.get<ApiResponse<PPDBTrackItem[]>>('/public/ppdb/tracks', {
      params: { wave_id: waveId || undefined },
    });
    return Array.isArray(res.data.data) ? res.data.data : [];
  },

  /**
   * Cek pengumuman hasil seleksi PPDB secara publik dengan nomor pendaftaran & tanggal lahir
   * POST /api/v1/public/ppdb/results/check
   */
  async checkPublicResult(payload: CheckPPDBResultPayload): Promise<PPDBPublicResult> {
    const res = await apiClient.post<ApiResponse<PPDBPublicResult>>('/public/ppdb/results/check', payload);
    return res.data.data;
  },

  /**
   * Mengambil statistik publik PPDB
   * GET /api/v1/public/ppdb/stats
   */
  async getPublicStats(): Promise<PPDBStatsResponse> {
    const res = await apiClient.get<ApiResponse<PPDBStatsResponse>>('/public/ppdb/stats');
    return res.data.data;
  },

  // =========================================================================
  // APPLICANT ACCOUNT, REGISTRATION & DASHBOARD (WEB-PPDB-02 / WEB-PPDB-03)
  // =========================================================================

  /**
   * Registrasi akun calon siswa baru & kirim OTP
   * POST /api/v1/ppdb/register
   */
  async register(payload: RegisterApplicantPayload): Promise<RegisterApplicantResponse> {
    const res = await apiClient.post<ApiResponse<RegisterApplicantResponse>>('/ppdb/register', payload);
    return res.data.data;
  },

  /**
   * Verifikasi OTP untuk aktivasi akun calon siswa
   * POST /api/v1/ppdb/verify-otp
   */
  async verifyOtp(payload: VerifyOtpPayload): Promise<VerifyOtpResponse> {
    const res = await apiClient.post<ApiResponse<VerifyOtpResponse>>('/ppdb/verify-otp', payload);
    return res.data.data;
  },

  /**
   * Kirim ulang kode OTP aktivasi pendaftar
   * POST /api/v1/ppdb/resend-otp
   */
  async resendOtp(email: string): Promise<{ message: string; otp?: string }> {
    const res = await apiClient.post<ApiResponse<{ message: string; otp?: string }>>('/ppdb/resend-otp', { email });
    return res.data.data;
  },

  /**
   * Pengecekan ketersediaan NIK calon siswa
   * POST /api/v1/ppdb/check-nik
   */
  async checkNik(nik: string, waveId?: string): Promise<{ is_available: boolean; has_active_registration: boolean; existing_registration_no?: string | null }> {
    const res = await apiClient.post<ApiResponse<{ is_available: boolean; has_active_registration: boolean; existing_registration_no?: string | null }>>('/ppdb/check-nik', { nik, wave_id: waveId });
    return res.data.data;
  },

  /**
   * Masuk ke portal akun pendaftar (Email / No. HP / No. Registrasi + Password)
   * POST /api/v1/ppdb/login
   */
  async login(payload: PPDBLoginPayload): Promise<PPDBLoginResponse> {
    const res = await apiClient.post<ApiResponse<PPDBLoginResponse>>('/ppdb/login', payload);
    return res.data.data;
  },

  /**
   * Mengambil profil dan data pendaftaran calon siswa yang sedang login
   * GET /api/v1/ppdb/me
   */
  async getMe(): Promise<ApplicantProfileResponse> {
    const res = await apiClient.get<ApiResponse<ApplicantProfileResponse>>('/ppdb/me');
    return res.data.data;
  },

  /**
   * Memulai pendaftaran baru / draf pendaftaran calon siswa
   * POST /api/v1/ppdb/applications
   */
  async createApplication(data: { wave_id: string; track_id: string; full_name: string; nik: string; birth_date: string }): Promise<any> {
    const res = await apiClient.post<ApiResponse<any>>('/ppdb/applications', data);
    return res.data.data;
  },

  /**
   * Simpan otomatis (autosave) langkah formulir bertahap pendaftar
   * PATCH /api/v1/ppdb/applications/{id}/steps/{step}
   */
  async updateStep(applicationId: string, step: string | number, data: Record<string, unknown>): Promise<any> {
    const res = await apiClient.patch<ApiResponse<any>>(`/ppdb/applications/${applicationId}/steps/${step}`, data);
    return res.data.data;
  },

  /**
   * Submit final pendaftaran calon siswa (mengunci formulir)
   * POST /api/v1/ppdb/applications/{id}/submit
   */
  async submitApplication(applicationId: string): Promise<any> {
    const res = await apiClient.post<ApiResponse<any>>(`/ppdb/applications/${applicationId}/submit`);
    return res.data.data;
  },

  /**
   * Unggah dokumen berkas pendaftar (KK, Akta, Rapor, Foto)
   * POST /api/v1/ppdb/applications/{id}/documents
   */
  async uploadDocument(applicationId: string, formData: FormData): Promise<any> {
    const res = await apiClient.post<ApiResponse<any>>(`/ppdb/applications/${applicationId}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data.data;
  },

  /**
   * Hapus berkas pendaftar
   * DELETE /api/v1/ppdb/applications/{id}/documents/{documentId}
   */
  async deleteDocument(applicationId: string, documentId: string): Promise<{ message: string }> {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/ppdb/applications/${applicationId}/documents/${documentId}`);
    return res.data.data;
  },

  /**
   * Konfirmasi pendaftaran ulang oleh calon siswa yang dinyatakan diterima (BE-PPDB-08)
   * POST /api/v1/ppdb/applications/{id}/re-register
   */
  async reRegister(applicationId: string, data?: { notes?: string }): Promise<any> {
    const res = await apiClient.post<ApiResponse<any>>(`/ppdb/applications/${applicationId}/re-register`, data || {});
    return res.data.data;
  },

  /**
   * Mengambil data bukti resmi pendaftaran calon siswa (BE-PPDB-09)
   * GET /api/v1/ppdb/applications/{id}/receipt
   */
  async getReceiptData(applicationId: string): Promise<any> {
    const res = await apiClient.get<ApiResponse<any>>(`/ppdb/applications/${applicationId}/receipt`);
    return res.data.data;
  },

  /**
   * Mengunduh dokumen PDF resmi bukti tanda pendaftaran (BE-PPDB-09)
   * GET /api/v1/ppdb/applications/{id}/receipt?format=pdf
   */
  async downloadReceiptPdf(applicationId: string): Promise<Blob> {
    const res = await apiClient.get(`/ppdb/applications/${applicationId}/receipt?format=pdf`, {
      responseType: 'blob',
      headers: { Accept: 'application/pdf' },
    });
    return res.data;
  },
};
