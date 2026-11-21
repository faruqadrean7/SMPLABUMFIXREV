import { describe, it, expect, vi, beforeEach } from 'vitest';
import { lmsApi } from '@/lib/lms-api';
import { apiClient } from '@/lib/api-client';
import fs from 'fs';
import path from 'path';

describe('FIX-MOCK-CLEANUP-01: Clean API contracts and mock file elimination (BUG 3 & BUG 4)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('AC1: mock data files in apps/web/src/lib have been removed', () => {
    const libDir = path.resolve(__dirname, '../lib');
    const forbiddenFiles = [
      'mock-lms-data.ts',
      'mock-guardian-data.ts',
      'mock-student-data.ts',
    ];

    forbiddenFiles.forEach((fileName) => {
      const fullPath = path.join(libDir, fileName);
      expect(fs.existsSync(fullPath)).toBe(false);
    });
  });

  it('AC1: pure type definition files exist in apps/web/src/types', () => {
    const typesDir = path.resolve(__dirname, '../types');
    expect(fs.existsSync(path.join(typesDir, 'guardian.ts'))).toBe(true);
    expect(fs.existsSync(path.join(typesDir, 'student.ts'))).toBe(true);
  });

  it('AC2: lmsApi.getMaterials propagates HTTP errors and does not swallow with mock data', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue({
      response: { status: 403, data: { message: 'Akses ditolak: Anda bukan guru pengampu.' } },
    });

    await expect(lmsApi.getMaterials()).rejects.toMatchObject({
      response: { status: 403 },
    });
  });

  it('AC2: lmsApi.getAssignments propagates HTTP errors cleanly', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue({
      response: { status: 500, data: { message: 'Database connection failed' } },
    });

    await expect(lmsApi.getAssignments()).rejects.toMatchObject({
      response: { status: 500 },
    });
  });

  it('AC2: lmsApi.getAssignmentDetail throws error when task is not found (404)', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: { data: null },
    } as any);

    await expect(lmsApi.getAssignmentDetail('non-existent-id')).rejects.toThrow('Tugas tidak ditemukan.');
  });

  it('AC2: lmsApi returns real data correctly on successful response', async () => {
    const mockMaterial = {
      id: 'mat-real-01',
      title: 'Modul Struktur Atom',
      description: 'Materi IPA Kelas 8',
      class_id: 'cls-8a',
      subject_id: 'sbj-ipa',
      category: 'modul_ajar',
      created_at: '2026-11-03T10:00:00Z',
    };

    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: { data: [mockMaterial] },
    } as any);

    const result = await lmsApi.getMaterials('cls-8a', 'sbj-ipa');
    expect(result).toHaveLength(1);
    expect(result[0].id).toBe('mat-real-01');
    expect(result[0].title).toBe('Modul Struktur Atom');
  });

  it('AC2: lmsApi does not expose useMockFallback or setMockFallback toggles', () => {
    expect((lmsApi as any).useMockFallback).toBeUndefined();
    expect((lmsApi as any).setMockFallback).toBeUndefined();
  });
});
