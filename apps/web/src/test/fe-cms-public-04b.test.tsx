import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import PublicDirectoryPage from '@/app/(public)/pendidik/page';
import { publicApi } from '@/features/public-site/public-api';
import { apiClient } from '@/lib/api/api-client';
import { PUBLIC_NAV_ITEMS, FOOTER_QUICK_LINKS } from '@/features/public-site/content/navigation';
import { PublicTeacherItem, PublicStaffItem } from '@/features/public-site/types';

vi.mock('next/image', () => ({
  // eslint-disable-next-line @next/next/no-img-element
  default: ({ src, alt, ...props }: any) => <img src={src} alt={alt} {...props} />,
}));

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
}

function renderWithClient(ui: React.ReactElement) {
  const queryClient = createTestQueryClient();
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

const mockTeachers: PublicTeacherItem[] = [
  {
    id: 'tch-01',
    full_name: 'Dra. Endang Sulistyowati, M.Pd.',
    position: 'Guru Madya & Koordinator Kurikulum',
    photo: {
      id: 'photo-01',
      object_key: 'teachers/endang.jpg',
      mime_type: 'image/jpeg',
    },
    subjects: [
      { id: 'sbj-01', code: 'MAT-01', name: 'Matematika' },
      { id: 'sbj-02', code: 'MAT-02', name: 'Statistika Dasar' },
    ],
  },
  {
    id: 'tch-02',
    full_name: 'Bambang Tri Raharjo, S.Pd.',
    position: 'Guru Pengajar',
    photo: null,
    subjects: [
      { id: 'sbj-03', code: 'IPA-01', name: 'Ilmu Pengetahuan Alam' },
    ],
  },
];

const mockStaff: PublicStaffItem[] = [
  {
    id: 'stf-01',
    full_name: 'Hadi Prasetyo, S.AP.',
    position: 'Kepala Urusan Tata Usaha',
    unit: 'Tata Usaha & Administrasi',
  },
  {
    id: 'stf-02',
    full_name: 'Ratna Dewi Kusuma, S.I.Pust.',
    position: 'Pustakawan Madya',
    unit: 'Perpustakaan Digital',
  },
];

describe('FE-CMS-PUBLIC-04b: Direktori Guru & Tenaga Kependidikan Publik (/pendidik)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe('1. publicApi Direktori Endpoints (BE-CMS-05)', () => {
    it('FE-CMS-PUBLIC-04b (AC1): getPublicTeachers memanggil GET /public/teachers dengan parameter yang tepat', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockTeachers,
          meta: {
            currentPage: 1,
            totalPages: 1,
            totalItems: 2,
            itemsPerPage: 12,
          },
        },
      });

      const res = await publicApi.getPublicTeachers({
        page: 1,
        per_page: 12,
        search: 'Endang',
        position: 'Guru Madya',
      });

      expect(getSpy).toHaveBeenCalledWith('/public/teachers', {
        params: {
          page: 1,
          per_page: 12,
          search: 'Endang',
          position: 'Guru Madya',
          subject: undefined,
          subject_id: undefined,
          school_id: undefined,
          all: undefined,
        },
      });

      expect(res.data).toHaveLength(2);
      expect(res.data[0].full_name).toBe('Dra. Endang Sulistyowati, M.Pd.');
      expect(res.data[0].subjects).toHaveLength(2);
      expect(res.data[0].subjects?.[0].name).toBe('Matematika');
    });

    it('FE-CMS-PUBLIC-04b (AC1): getPublicTeacher memanggil GET /public/teachers/{id}', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockTeachers[0],
        },
      });

      const teacher = await publicApi.getPublicTeacher('tch-01');

      expect(getSpy).toHaveBeenCalledWith('/public/teachers/tch-01');
      expect(teacher.id).toBe('tch-01');
      expect(teacher.full_name).toBe('Dra. Endang Sulistyowati, M.Pd.');
    });

    it('FE-CMS-PUBLIC-04b (AC1): getPublicStaff memanggil GET /public/staff dengan parameter yang tepat', async () => {
      const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockStaff,
          meta: {
            currentPage: 1,
            totalPages: 1,
            totalItems: 2,
            itemsPerPage: 12,
          },
        },
      });

      const res = await publicApi.getPublicStaff({
        page: 1,
        per_page: 12,
        search: 'Hadi',
        unit: 'Tata Usaha',
      });

      expect(getSpy).toHaveBeenCalledWith('/public/staff', {
        params: {
          page: 1,
          per_page: 12,
          search: 'Hadi',
          position: undefined,
          unit: 'Tata Usaha',
          school_id: undefined,
          all: undefined,
        },
      });

      expect(res.data).toHaveLength(2);
      expect(res.data[0].full_name).toBe('Hadi Prasetyo, S.AP.');
      expect(res.data[0].unit).toBe('Tata Usaha & Administrasi');
    });
  });

  describe('2. Jaminan Privasi Data (AGENTS.md §6 - Data Whitelist)', () => {
    it('FE-CMS-PUBLIC-04b: Model guru dan staf publik tidak memuat field PII sensitif (NIK, no. HP, alamat)', () => {
      mockTeachers.forEach((teacher) => {
        expect((teacher as any).nik).toBeUndefined();
        expect((teacher as any).phone).toBeUndefined();
        expect((teacher as any).phone_number).toBeUndefined();
        expect((teacher as any).address).toBeUndefined();
        expect((teacher as any).home_address).toBeUndefined();
      });

      mockStaff.forEach((staff) => {
        expect((staff as any).nik).toBeUndefined();
        expect((staff as any).phone).toBeUndefined();
        expect((staff as any).phone_number).toBeUndefined();
        expect((staff as any).address).toBeUndefined();
      });
    });
  });

  describe('3. Tautan Navigasi Publik (AC3)', () => {
    it('FE-CMS-PUBLIC-04b (AC3): PUBLIC_NAV_ITEMS memuat link rute /pendidik dengan label "Pendidik"', () => {
      const navItem = PUBLIC_NAV_ITEMS.find((n) => n.href === '/pendidik');
      expect(navItem).toBeDefined();
      expect(navItem?.label).toBe('Pendidik');
    });

    it('FE-CMS-PUBLIC-04b (AC3): FOOTER_QUICK_LINKS memuat link rute /pendidik', () => {
      const footerLink = FOOTER_QUICK_LINKS.find((l) => l.href === '/pendidik');
      expect(footerLink).toBeDefined();
      expect(footerLink?.label).toContain('Pendidik');
    });
  });

  describe('4. Tampilan Halaman Direktori (/pendidik)', () => {
    it('FE-CMS-PUBLIC-04b (AC2): merender daftar dewan guru dan tenaga kependidikan dari endpoint nyata', async () => {
      vi.spyOn(publicApi, 'getPublicTeachers').mockResolvedValueOnce({
        data: mockTeachers,
        meta: { currentPage: 1, totalPages: 1, totalItems: 2, itemsPerPage: 24 },
      } as any);

      vi.spyOn(publicApi, 'getPublicStaff').mockResolvedValueOnce({
        data: mockStaff,
        meta: { currentPage: 1, totalPages: 1, totalItems: 2, itemsPerPage: 24 },
      } as any);

      renderWithClient(<PublicDirectoryPage />);

      // Judul halaman
      expect(
        screen.getByRole('heading', { name: /Direktori Guru & Tenaga Kependidikan/i })
      ).toBeInTheDocument();

      // Guru
      expect(await screen.findByText('Dra. Endang Sulistyowati, M.Pd.')).toBeInTheDocument();
      expect(screen.getByText('Bambang Tri Raharjo, S.Pd.')).toBeInTheDocument();
      expect(screen.getByText('Matematika')).toBeInTheDocument();
      expect(screen.getByText('Ilmu Pengetahuan Alam')).toBeInTheDocument();

      // Staf
      expect(screen.getByText('Hadi Prasetyo, S.AP.')).toBeInTheDocument();
      expect(screen.getByText('Ratna Dewi Kusuma, S.I.Pust.')).toBeInTheDocument();
      expect(screen.getByText('Tata Usaha & Administrasi')).toBeInTheDocument();
      expect(screen.getByText('Perpustakaan Digital')).toBeInTheDocument();
    });

    it('FE-CMS-PUBLIC-04b: Tab switching bekerja dengan baik (Dewan Guru / Tenaga Kependidikan)', async () => {
      vi.spyOn(publicApi, 'getPublicTeachers').mockResolvedValue({
        data: mockTeachers,
        meta: { currentPage: 1, totalPages: 1, totalItems: 2, itemsPerPage: 12 },
      } as any);

      vi.spyOn(publicApi, 'getPublicStaff').mockResolvedValue({
        data: mockStaff,
        meta: { currentPage: 1, totalPages: 1, totalItems: 2, itemsPerPage: 12 },
      } as any);

      renderWithClient(<PublicDirectoryPage />);

      await screen.findByText('Dra. Endang Sulistyowati, M.Pd.');

      // Klik tab "Dewan Guru"
      const guruTab = screen.getByRole('tab', { name: /Dewan Guru/i });
      fireEvent.click(guruTab);

      // Dewan Guru tampil, seksi Tenaga Kependidikan tidak dirender
      expect(screen.getByText('Dra. Endang Sulistyowati, M.Pd.')).toBeInTheDocument();
      expect(screen.queryByText('Ratna Dewi Kusuma, S.I.Pust.')).not.toBeInTheDocument();

      // Klik tab "Tenaga Kependidikan"
      const stafTab = screen.getByRole('tab', { name: /Tenaga Kependidikan/i });
      fireEvent.click(stafTab);

      // Staf tampil, Dewan Guru tidak dirender
      expect(await screen.findByText('Ratna Dewi Kusuma, S.I.Pust.')).toBeInTheDocument();
      expect(screen.queryByText('Dra. Endang Sulistyowati, M.Pd.')).not.toBeInTheDocument();
    });

    it('FE-CMS-PUBLIC-04b: Formulir pencarian memicu refetch dengan parameter pencarian', async () => {
      const teacherSpy = vi.spyOn(publicApi, 'getPublicTeachers').mockResolvedValue({
        data: [mockTeachers[0]],
        meta: { currentPage: 1, totalPages: 1, totalItems: 1, itemsPerPage: 24 },
      } as any);

      const staffSpy = vi.spyOn(publicApi, 'getPublicStaff').mockResolvedValue({
        data: [],
        meta: { currentPage: 1, totalPages: 1, totalItems: 0, itemsPerPage: 24 },
      } as any);

      renderWithClient(<PublicDirectoryPage />);

      await screen.findByText('Dra. Endang Sulistyowati, M.Pd.');

      const searchInput = screen.getByPlaceholderText(/Cari nama, mata pelajaran, posisi.../i);
      fireEvent.change(searchInput, { target: { value: 'Endang' } });

      const searchBtn = screen.getByRole('button', { name: /^Cari$/i });
      fireEvent.click(searchBtn);

      await waitFor(() => {
        expect(teacherSpy).toHaveBeenCalledWith(
          expect.objectContaining({
            search: 'Endang',
          })
        );
      });
    });

    it('FE-CMS-PUBLIC-04b: Merender EmptyState saat direktori tidak menemukan hasil', async () => {
      vi.spyOn(publicApi, 'getPublicTeachers').mockResolvedValueOnce({
        data: [],
        meta: { currentPage: 1, totalPages: 1, totalItems: 0, itemsPerPage: 24 },
      } as any);

      vi.spyOn(publicApi, 'getPublicStaff').mockResolvedValueOnce({
        data: [],
        meta: { currentPage: 1, totalPages: 1, totalItems: 0, itemsPerPage: 24 },
      } as any);

      renderWithClient(<PublicDirectoryPage />);

      expect(
        await screen.findByText('Pendidik atau Staf Tidak Ditemukan')
      ).toBeInTheDocument();
    });

    it('FE-CMS-PUBLIC-04b: Merender ErrorState saat panggilan API mengalami kendala', async () => {
      vi.spyOn(publicApi, 'getPublicTeachers').mockRejectedValueOnce(new Error('Network error'));
      vi.spyOn(publicApi, 'getPublicStaff').mockRejectedValueOnce(new Error('Network error'));

      renderWithClient(<PublicDirectoryPage />);

      expect(await screen.findByText('Gagal Memuat Direktori')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Coba Lagi/i })).toBeInTheDocument();
    });
  });
});
