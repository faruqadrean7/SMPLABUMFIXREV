import { describe, it, expect, vi, beforeEach } from 'vitest';
import { executiveApi } from '@/features/executive/executive-api';
import { apiClient } from '@/lib/api/api-client';

describe('ADM-DASH-02: Antrean & Eksekusi Persetujuan Eksekutif', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('ADM-DASH-02: fetches pending approvals queue from GET /approvals/pending', async () => {
    const mockApprovals = [
      {
        id: 'leave-uuid-01',
        code: 'APP-LEAVE-01',
        category: 'leave_request',
        title: 'Permohonan Izin: Ahmad Siswa',
        requesterName: 'Ahmad Siswa',
        requesterRole: 'Siswa',
        details: 'Sakit flu (2026-10-15)',
        submittedAt: '2026-10-15T07:00:00Z',
        status: 'pending',
      },
      {
        id: 'booking-uuid-02',
        code: 'APP-SARPRAS-02',
        category: 'facility_purchase',
        title: 'Peminjaman Fasilitas: Lab Komputer 1',
        requesterName: 'Budi Guru',
        requesterRole: 'Guru',
        details: 'Simulasi CBT',
        submittedAt: '2026-10-15T08:00:00Z',
        status: 'pending',
      },
    ];

    vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
      data: {
        success: true,
        data: mockApprovals,
      },
    } as any);

    const result = await executiveApi.getApprovals();

    expect(apiClient.get).toHaveBeenCalledWith('/approvals/pending');
    expect(result).toHaveLength(2);
    expect(result[0].category).toBe('leave_request');
    expect(result[1].category).toBe('facility_purchase');
  });

  it('ADM-DASH-02: submits approve decision to POST /approvals/{id}/review', async () => {
    vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Permohonan izin berhasil disetujui.',
        reviewedAt: '2026-10-15T08:30:00.000Z',
      },
    } as any);

    const result = await executiveApi.reviewApproval('leave-uuid-01', {
      action: 'approve',
      notes: 'Disetujui oleh Kepala Sekolah',
    });

    expect(apiClient.post).toHaveBeenCalledWith('/approvals/leave-uuid-01/review', {
      action: 'approve',
      notes: 'Disetujui oleh Kepala Sekolah',
    });
    expect(result.success).toBe(true);
    expect(result.reviewedAt).toBe('2026-10-15T08:30:00.000Z');
  });

  it('ADM-DASH-02: submits reject decision with notes to POST /approvals/{id}/review', async () => {
    vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Permohonan izin berhasil ditolak.',
        reviewedAt: '2026-10-15T08:35:00.000Z',
      },
    } as any);

    const result = await executiveApi.reviewApproval('leave-uuid-01', {
      action: 'reject',
      rejectionReason: 'Jadwal terlalu padat, mohon ajukan minggu depan.',
    });

    expect(apiClient.post).toHaveBeenCalledWith('/approvals/leave-uuid-01/review', {
      action: 'reject',
      notes: 'Jadwal terlalu padat, mohon ajukan minggu depan.',
    });
    expect(result.success).toBe(true);
  });

  it('ADM-DASH-02: client validation fails if reject is submitted without rejectionReason/notes', async () => {
    await expect(
      executiveApi.reviewApproval('leave-uuid-01', {
        action: 'reject',
        rejectionReason: '   ',
      })
    ).rejects.toThrow('Alasan penolakan wajib diisi.');

    expect(apiClient.post).not.toHaveBeenCalled();
  });
});
