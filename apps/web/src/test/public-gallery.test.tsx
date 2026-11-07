import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import GalleryPage from '@/app/(public)/galeri/page';
import { publicApi } from '@/features/public-site/public-api';

// Mock navigation
vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => '/galeri',
}));

// Mock publicApi
vi.mock('@/features/public-site/public-api', () => ({
  publicApi: {
    getPublicAlbums: vi.fn(),
    getPublicAlbumDetail: vi.fn(),
  },
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
  const testClient = createTestQueryClient();
  return render(<QueryClientProvider client={testClient}>{ui}</QueryClientProvider>);
}

describe('Public Gallery Page Tests (WEB-GAL-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('merender hero section galeri dengan judul, breadcrumb, dan form pencarian', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 9, total: 0, totalPages: 1 },
    });

    const { container } = renderWithClient(<GalleryPage />);

    // H1 Heading
    const h1 = screen.getByRole('heading', { level: 1 });
    // Judul hero dipisah <br/>, jadi cek per potongan agar tahan perubahan whitespace.
    expect(h1).toHaveTextContent(/Galeri Kegiatan/i);
    expect(h1).toHaveTextContent(/Aktivitas Sekolah/i);

    // Breadcrumb
    const breadcrumb = screen.getByRole('navigation', { name: /Navigasi Halaman/i });
    expect(breadcrumb).toBeInTheDocument();
    expect(screen.getByText('Galeri Dokumentasi')).toBeInTheDocument();

    // Search input
    expect(screen.getByPlaceholderText(/Cari album kegiatan/i)).toBeInTheDocument();

    // Exactly 1 main landmark
    const mains = container.querySelectorAll('main');
    expect(mains).toHaveLength(1);
    expect(mains[0]).toHaveAttribute('id', 'main-content');
  });

  it('menangani loading skeleton state saat album galeri sedang dimuat', () => {
    vi.mocked(publicApi.getPublicAlbums).mockReturnValue(new Promise(() => {}));

    renderWithClient(<GalleryPage />);

    expect(screen.getByLabelText(/Memuat album galeri/i)).toBeInTheDocument();
  });

  it('menangani error state pada daftar album dengan tombol coba lagi', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockRejectedValue(new Error('Koneksi terputus'));

    renderWithClient(<GalleryPage />);

    await waitFor(() => {
      expect(screen.getByText(/Gagal Memuat Album Galeri/i)).toBeInTheDocument();
    });

    expect(screen.getByRole('button', { name: /Coba Lagi/i })).toBeInTheDocument();
  });

  it('menangani empty state ketika API mengembalikan 0 album', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 9, total: 0, totalPages: 1 },
    });

    renderWithClient(<GalleryPage />);

    await waitFor(() => {
      expect(screen.getByText(/Belum Ada Album Dokumentasi/i)).toBeInTheDocument();
    });
  });

  it('merender daftar album dari API dengan tanggal, badge jumlah foto, dan tombol buka album', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [
        {
          id: 'album-1',
          title: 'Dokumentasi Wisuda Angkatan 2026',
          items_count: 12,
          created_at: '2026-06-20T08:00:00Z',
          cover: { id: 'file-1', url: 'https://example.com/cover.jpg' },
        },
      ],
      meta: { page: 1, limit: 9, total: 1, totalPages: 1 },
    });

    renderWithClient(<GalleryPage />);

    await waitFor(() => {
      expect(screen.getByText('Dokumentasi Wisuda Angkatan 2026')).toBeInTheDocument();
    });

    expect(screen.getByText(/12 Foto/i)).toBeInTheDocument();
    expect(screen.getByText(/20 Juni 2026/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Buka Album/i })).toBeInTheDocument();
  });

  it('menangani album dengan cover kosong atau metadata opsional tanpa error', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [
        {
          id: 'album-no-cover',
          title: 'Album Kegiatan Literasi Digital',
          items_count: 0,
          created_at: '2026-07-05T08:00:00Z',
          cover: null,
        },
      ],
      meta: { page: 1, limit: 9, total: 1, totalPages: 1 },
    });

    renderWithClient(<GalleryPage />);

    await waitFor(() => {
      expect(screen.getByText('Album Kegiatan Literasi Digital')).toBeInTheDocument();
    });

    expect(screen.getByText(/0 Foto/i)).toBeInTheDocument();
    expect(screen.getByText('Dokumentasi Sekolah')).toBeInTheDocument();
  });

  it('membuka modal detail album dan menutupnya menggunakan tombol atau tombol Escape', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [
        {
          id: 'album-interactive',
          title: 'Pekan Sains dan Riset Pembelajaran',
          items_count: 2,
          created_at: '2026-08-10T08:00:00Z',
          cover: null,
        },
      ],
      meta: { page: 1, limit: 9, total: 1, totalPages: 1 },
    });

    vi.mocked(publicApi.getPublicAlbumDetail).mockResolvedValue({
      id: 'album-interactive',
      title: 'Pekan Sains dan Riset Pembelajaran',
      items_count: 2,
      items: [
        {
          id: 'media-1',
          album_id: 'album-interactive',
          media_type: 'image',
          caption: 'Presentasi Proyek Robotika Siswa',
          file: { id: 'f-1', url: 'https://example.com/robot.jpg' },
        },
      ],
    });

    renderWithClient(<GalleryPage />);

    await waitFor(() => {
      expect(screen.getByText('Pekan Sains dan Riset Pembelajaran')).toBeInTheDocument();
    });

    // Open modal
    const openBtn = screen.getByRole('button', { name: /Buka Album/i });
    fireEvent.click(openBtn);

    // Modal dialog appears
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
    expect(screen.getByText('Detail Album Dokumentasi')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText('Presentasi Proyek Robotika Siswa')).toBeInTheDocument();
    });

    // Close modal via Escape
    fireEvent.keyDown(window, { key: 'Escape', code: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('merender kontrol paginasi saat totalPages > 1 dan menangani navigasi', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [
        {
          id: 'album-p1',
          title: 'Album Halaman Pertama',
          items_count: 5,
          created_at: '2026-08-01T08:00:00Z',
          cover: null,
        },
      ],
      meta: { page: 1, limit: 9, total: 18, totalPages: 2 },
    });

    renderWithClient(<GalleryPage />);

    await waitFor(() => {
      expect(screen.getByText('Album Halaman Pertama')).toBeInTheDocument();
    });

    const paginationNav = screen.getByRole('navigation', { name: /Paginasi Album Galeri/i });
    expect(paginationNav).toBeInTheDocument();
    expect(screen.getByText(/Total 18 album/i)).toBeInTheDocument();

    const nextBtn = screen.getByRole('button', { name: /Berikutnya/i });
    expect(nextBtn).toBeEnabled();

    fireEvent.click(nextBtn);

    await waitFor(() => {
      expect(publicApi.getPublicAlbums).toHaveBeenCalledWith(
        expect.objectContaining({ page: 2 })
      );
    });
  });

  it('merender seksi video dokumenter resmi terverifikasi dengan iframe dan link YouTube', () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 9, total: 0, totalPages: 1 },
    });

    renderWithClient(<GalleryPage />);

    expect(screen.getByText(/Video Profil & Suasana Belajar/i)).toBeInTheDocument();

    const iframe = screen.getByTitle(/Video Profil Resmi SMP Laboratorium UM/i);
    expect(iframe).toBeInTheDocument();
    expect(iframe).toHaveAttribute('src', 'https://www.youtube-nocookie.com/embed/BxQztnwi_74');

    const ytLink = screen.getByRole('link', { name: /Buka di YouTube/i });
    expect(ytLink).toHaveAttribute('href', 'https://www.youtube.com/watch?v=BxQztnwi_74');
  });

  it('tidak memiliki tautan placeholder "#" dan semua tautan memiliki rute valid', async () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 9, total: 0, totalPages: 1 },
    });

    const { container } = renderWithClient(<GalleryPage />);

    const allLinks = container.querySelectorAll('a');
    expect(allLinks.length).toBeGreaterThan(0);

    allLinks.forEach((link) => {
      const href = link.getAttribute('href');
      expect(href).not.toBeNull();
      expect(href).not.toBe('#');
      expect(href).not.toBe('');
      expect(
        href?.startsWith('/') || href?.startsWith('https://')
      ).toBe(true);
    });
  });

  it('tidak merender tag <header> atau <footer> internal ganda pada halaman', () => {
    vi.mocked(publicApi.getPublicAlbums).mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 9, total: 0, totalPages: 1 },
    });

    const { container } = renderWithClient(<GalleryPage />);

    expect(container.querySelectorAll('header')).toHaveLength(0);
    expect(container.querySelectorAll('footer')).toHaveLength(0);
  });
});
