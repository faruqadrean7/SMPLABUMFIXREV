import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import CmsAdminPage from '@/app/(admin)/admin/cms/page';
import { cmsApi } from '@/features/cms/cms-api';
import { SiteProfile } from '@/features/cms/types';

vi.mock('@/features/storage/storage-api', () => ({
  storageApi: {
    uploadFileDirect: vi.fn(),
    getFile: vi.fn(),
  },
}));

describe('FE-CMS-ADMIN-01: Editor Social Media & Program Unggulan di Tab Profil CMS', () => {
  const mockInitialSiteData: SiteProfile = {
    identity: {
      name: 'SMP Laboratorium UM',
      tagline: 'Mencerdaskan & Berkarakter',
      logo_url: 'https://example.com/logo.png',
      favicon_url: 'https://example.com/favicon.ico',
    },
    contact: {
      phone: '0341-551122',
      email: 'info@labum.sch.id',
      whatsapp: '081234567890',
    },
    address: {
      street: 'Jl. Simpang Bogor No. 19',
      city: 'Malang',
      postal_code: '65145',
      maps_embed_url: '',
    },
    social_media: {
      instagram: '@smplabum',
      youtube: 'smplaboratoriumum',
      facebook: 'SMP Lab UM Official',
      twitter: '@labum_sch',
      tiktok: '@smplabum_tiktok',
    },
    profile: {
      vision: 'Menjadi sekolah terdepan dalam inovasi pembelajaran.',
      mission: ['Membina keimanan', 'Mengembangkan riset'],
      principal_greeting: {
        principal_name: 'Dr. Hj. Rahmawati, M.Pd',
        greeting_text: 'Selamat datang di SMP Laboratorium UM.',
      },
      accreditation: {
        grade: 'A',
        score: 96,
        certificate_no: '123/BAN-SM/2024',
      },
      featured_programs: [
        {
          title: 'International Cambridge Class',
          description: 'Kurikulum standar global Cambridge Secondary.',
          icon: 'Cambridge',
        },
        {
          title: 'Tahfidz Al-Qur\'an Berkelanjutan',
          description: 'Bimbingan intensif hafalan 3 juz.',
          icon: 'Tahfidz',
        },
      ],
    },
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(cmsApi, 'getSiteSettings').mockResolvedValue({
      data: mockInitialSiteData,
    } as any);
    vi.spyOn(cmsApi, 'updateSiteSettings').mockResolvedValue({
      data: mockInitialSiteData,
    } as any);
  });

  it('FE-CMS-ADMIN-01 (AC1): merender 5 field input Media Sosial dan seksi Program Unggulan di tab profil', async () => {
    render(<CmsAdminPage />);

    expect(await screen.findByDisplayValue('SMP Laboratorium UM')).toBeInTheDocument();

    // Check 5 Social Media inputs
    expect(screen.getByDisplayValue('@smplabum')).toBeInTheDocument();
    expect(screen.getByDisplayValue('smplaboratoriumum')).toBeInTheDocument();
    expect(screen.getByDisplayValue('SMP Lab UM Official')).toBeInTheDocument();
    expect(screen.getByDisplayValue('@labum_sch')).toBeInTheDocument();
    expect(screen.getByDisplayValue('@smplabum_tiktok')).toBeInTheDocument();

    // Check Program Unggulan section & existing programs
    expect(screen.getByText('Program Unggulan Sekolah')).toBeInTheDocument();
    expect(screen.getByDisplayValue('International Cambridge Class')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Tahfidz Al-Qur\'an Berkelanjutan')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Cambridge')).toBeInTheDocument();
  });

  it('FE-CMS-ADMIN-01 (AC1): dapat menambah dan menghapus baris Program Unggulan secara dinamis', async () => {
    render(<CmsAdminPage />);

    expect(await screen.findByDisplayValue('International Cambridge Class')).toBeInTheDocument();

    // Click "Tambah Program"
    const addButtons = screen.getAllByRole('button', { name: /Tambah Program/i });
    fireEvent.click(addButtons[0]);

    // Check that Program #3 appears
    expect(screen.getByText('Program #3')).toBeInTheDocument();

    // Fill new program
    const titleInputs = screen.getAllByPlaceholderText(/Contoh: Kelas Internasional/i);
    expect(titleInputs.length).toBe(3);
    fireEvent.change(titleInputs[2], { target: { value: 'Kelas Robotika & AI' } });
    expect(screen.getByDisplayValue('Kelas Robotika & AI')).toBeInTheDocument();

    // Delete the first program
    const deleteButtons = screen.getAllByRole('button', { name: /Hapus/i });
    fireEvent.click(deleteButtons[0]);

    // International Cambridge Class should now be deleted
    expect(screen.queryByDisplayValue('International Cambridge Class')).not.toBeInTheDocument();
    expect(screen.getByDisplayValue('Tahfidz Al-Qur\'an Berkelanjutan')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Kelas Robotika & AI')).toBeInTheDocument();
  });

  it('FE-CMS-ADMIN-01 (AC2 & AC4): menyimpan perubahan Media Sosial dan Program Unggulan ke cmsApi.updateSiteSettings', async () => {
    render(<CmsAdminPage />);

    expect(await screen.findByDisplayValue('@smplabum')).toBeInTheDocument();

    // Change Instagram handle
    const igInput = screen.getByDisplayValue('@smplabum');
    fireEvent.change(igInput, { target: { value: 'https://instagram.com/smp_labum_new' } });

    // Click Simpan Perubahan Profil
    const saveButton = screen.getByRole('button', { name: /Simpan Perubahan Profil/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(cmsApi.updateSiteSettings).toHaveBeenCalledTimes(1);
    });

    const calledPayload = vi.mocked(cmsApi.updateSiteSettings).mock.calls[0][0];
    expect(calledPayload.social_media?.instagram).toBe('https://instagram.com/smp_labum_new');
    expect(calledPayload.social_media?.youtube).toBe('smplaboratoriumum');
    expect(calledPayload.profile?.featured_programs).toHaveLength(2);
    expect(calledPayload.profile?.featured_programs?.[0].title).toBe('International Cambridge Class');
  });

  it('FE-CMS-ADMIN-01 (AC3): memfilter baris program yang judulnya kosong sebelum disimpan', async () => {
    render(<CmsAdminPage />);

    expect(await screen.findByDisplayValue('International Cambridge Class')).toBeInTheDocument();

    // Add 2 new program rows
    const addButtons = screen.getAllByRole('button', { name: /Tambah Program/i });
    fireEvent.click(addButtons[0]);
    fireEvent.click(addButtons[0]);

    // Row 3: leave title empty, fill description
    const descInputs = screen.getAllByPlaceholderText(/Penjelasan keunggulan program/i);
    fireEvent.change(descInputs[2], { target: { value: 'Deskripsi tanpa judul program' } });

    // Row 4: fill title with only spaces "   "
    const titleInputs = screen.getAllByPlaceholderText(/Contoh: Kelas Internasional/i);
    fireEvent.change(titleInputs[3], { target: { value: '   ' } });

    // Submit form
    const saveButton = screen.getByRole('button', { name: /Simpan Perubahan Profil/i });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(cmsApi.updateSiteSettings).toHaveBeenCalledTimes(1);
    });

    const calledPayload = vi.mocked(cmsApi.updateSiteSettings).mock.calls[0][0];
    // Only the original 2 valid programs should be sent (empty and whitespace rows are excluded)
    expect(calledPayload.profile?.featured_programs).toHaveLength(2);
    expect(calledPayload.profile?.featured_programs?.map((p) => p.title)).toEqual([
      'International Cambridge Class',
      'Tahfidz Al-Qur\'an Berkelanjutan',
    ]);
  });

  it('FE-CMS-ADMIN-01 (AC5): menangani data kosong dengan pesan empty-state yang informatif', async () => {
    vi.spyOn(cmsApi, 'getSiteSettings').mockResolvedValue({
      data: {
        identity: { name: 'SMP Lab Tanpa Konten' },
        profile: {
          featured_programs: [],
        },
      },
    } as any);

    render(<CmsAdminPage />);

    expect(await screen.findByDisplayValue('SMP Lab Tanpa Konten')).toBeInTheDocument();
    expect(screen.getByText('Belum ada program unggulan yang ditambahkan.')).toBeInTheDocument();
    expect(
      screen.getByText(/Bila dikosongkan, website publik akan menampilkan program bawaan/i)
    ).toBeInTheDocument();

    // Can add first program from empty state button
    const addFirstBtn = screen.getByRole('button', { name: /Tambah Program Pertama/i });
    fireEvent.click(addFirstBtn);
    expect(screen.getByText('Program #1')).toBeInTheDocument();
  });
});
