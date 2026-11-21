import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import NewsIndexPage from '@/app/(public)/berita/page';
import NewsDetailPage from '@/app/(public)/berita/[slug]/page';
import { publicApi } from '@/features/public-site/public-api';

// Mock navigation
vi.mock('next/navigation', () => ({
  useParams: () => ({ slug: 'olimpiade-sains-2026' }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => '/berita',
}));

// Mock publicApi
vi.mock('@/features/public-site/public-api', () => ({
  publicApi: {
    getPublicPosts: vi.fn(),
    getPublicPostBySlug: vi.fn(),
    getCategories: vi.fn(),
    getLatestPosts: vi.fn(),
    getActiveWave: vi.fn(),
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

describe('Public News Page Tests (WEB-NEWS-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('merender hero section berita dengan judul, breadcrumb, dan form pencarian', async () => {
    vi.mocked(publicApi.getCategories).mockResolvedValue([
      { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
      { id: 'cat-2', name: 'Akademik', slug: 'akademik' },
    ]);
    vi.mocked(publicApi.getPublicPosts).mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 6, total: 0, totalPages: 1 },
    });

    const { container } = renderWithClient(<NewsIndexPage />);

    // H1 Heading
    const h1 = screen.getByRole('heading', { level: 1 });
    // Judul hero dipisah <br/>, jadi cek per potongan agar tahan perubahan whitespace.
    expect(h1).toHaveTextContent(/Warta & Pengumuman/i);
    expect(h1).toHaveTextContent(/Sekolah/i);

    // Breadcrumb
    const breadcrumb = screen.getByRole('navigation', { name: /Navigasi Halaman/i });
    expect(breadcrumb).toBeInTheDocument();
    expect(screen.getByText('Berita & Pengumuman')).toBeInTheDocument();

    // Search input
    expect(screen.getByPlaceholderText(/Cari berita atau agenda/i)).toBeInTheDocument();

    // Exactly 1 main landmark
    const mains = container.querySelectorAll('main');
    expect(mains).toHaveLength(1);
    expect(mains[0]).toHaveAttribute('id', 'main-content');
  });

  it('menangani loading skeleton state saat data berita sedang dimuat', () => {
    vi.mocked(publicApi.getCategories).mockReturnValue(new Promise(() => {}));
    vi.mocked(publicApi.getPublicPosts).mockReturnValue(new Promise(() => {}));

    renderWithClient(<NewsIndexPage />);

    expect(screen.getByLabelText(/Memuat warta berita/i)).toBeInTheDocument();
  });

  it('menangani error state pada daftar berita dengan tombol coba lagi', async () => {
    vi.mocked(publicApi.getCategories).mockResolvedValue([]);
    vi.mocked(publicApi.getPublicPosts).mockRejectedValue(new Error('Koneksi terputus'));

    renderWithClient(<NewsIndexPage />);

    await waitFor(() => {
      expect(screen.getByText(/Gagal Memuat Publikasi Berita/i)).toBeInTheDocument();
    });

    expect(screen.getByRole('button', { name: /Coba Lagi/i })).toBeInTheDocument();
  });

  it('menangani empty state ketika API mengembalikan 0 berita', async () => {
    vi.mocked(publicApi.getCategories).mockResolvedValue([]);
    vi.mocked(publicApi.getPublicPosts).mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 6, total: 0, totalPages: 1 },
    });

    renderWithClient(<NewsIndexPage />);

    await waitFor(() => {
      expect(screen.getByText(/Belum Ada Berita yang Sesuai/i)).toBeInTheDocument();
    });
  });

  it('merender daftar berita nyata dari API dengan tanggal, kategori, dan tautan detail', async () => {
    vi.mocked(publicApi.getCategories).mockResolvedValue([
      { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
    ]);
    vi.mocked(publicApi.getPublicPosts).mockResolvedValue({
      data: [
        {
          id: 'post-1',
          title: 'Tim Siswa SMP Laboratorium Raih Emas OSN',
          slug: 'tim-siswa-smp-lab-raih-emas-osn',
          content: 'Tim olimpiade sains berhasil menorehkan prestasi gemilang.',
          published_at: '2026-09-18T08:00:00Z',
          status: 'terbit',
          visibility: 'publik',
          category: { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
        },
      ],
      meta: { page: 1, limit: 6, total: 1, totalPages: 1 },
    });

    renderWithClient(<NewsIndexPage />);

    await waitFor(() => {
      expect(screen.getByText('Tim Siswa SMP Laboratorium Raih Emas OSN')).toBeInTheDocument();
    });

    const prestasiElements = screen.getAllByText('Prestasi');
    expect(prestasiElements.length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText(/18 September 2026/i)).toBeInTheDocument();

    // Tautan detail
    const detailLink = screen.getByRole('link', { name: /Tim Siswa SMP Laboratorium Raih Emas OSN/i });
    expect(detailLink).toHaveAttribute('href', '/berita/tim-siswa-smp-lab-raih-emas-osn');
  });

  it('merender kontrol paginasi saat totalPages > 1 dan menangani navigasi halaman', async () => {
    vi.mocked(publicApi.getCategories).mockResolvedValue([]);
    vi.mocked(publicApi.getPublicPosts).mockResolvedValue({
      data: [
        {
          id: 'post-1',
          title: 'Warta Halaman Satu',
          slug: 'warta-halaman-satu',
          content: 'Konten warta pertama.',
          published_at: '2026-09-10T08:00:00Z',
          status: 'terbit',
          visibility: 'publik',
        },
      ],
      meta: { page: 1, limit: 6, total: 12, totalPages: 2 },
    });

    renderWithClient(<NewsIndexPage />);

    await waitFor(() => {
      expect(screen.getByText('Warta Halaman Satu')).toBeInTheDocument();
    });

    const paginationNav = screen.getByRole('navigation', { name: /Paginasi Halaman Berita/i });
    expect(paginationNav).toBeInTheDocument();
    expect(screen.getByText(/Total 12 warta/i)).toBeInTheDocument();
    const nextBtn = screen.getByRole('button', { name: /Berikutnya/i });
    expect(nextBtn).toBeEnabled();

    // Click next
    fireEvent.click(nextBtn);

    await waitFor(() => {
      expect(publicApi.getPublicPosts).toHaveBeenCalledWith(
        expect.objectContaining({ page: 2 })
      );
    });
  });

  it('tidak memiliki tautan placeholder "#" dan semua tautan memiliki tujuan rute valid', async () => {
    vi.mocked(publicApi.getCategories).mockResolvedValue([]);
    vi.mocked(publicApi.getPublicPosts).mockResolvedValue({
      data: [
        {
          id: 'post-1',
          title: 'Pengumuman Penting Libur Semester',
          slug: 'pengumuman-penting-libur-semester',
          content: 'Berikut adalah jadwal libur.',
          published_at: '2026-09-12T08:00:00Z',
          status: 'terbit',
          visibility: 'publik',
        },
      ],
      meta: { page: 1, limit: 6, total: 1, totalPages: 1 },
    });

    const { container } = renderWithClient(<NewsIndexPage />);

    await waitFor(() => {
      expect(screen.getByText('Pengumuman Penting Libur Semester')).toBeInTheDocument();
    });

    const allLinks = container.querySelectorAll('a');
    expect(allLinks.length).toBeGreaterThan(0);

    allLinks.forEach((link) => {
      const href = link.getAttribute('href');
      expect(href).not.toBeNull();
      expect(href).not.toBe('#');
      expect(href).not.toBe('');
      expect(href?.startsWith('/')).toBe(true);
    });
  });

  it('merender halaman detail berita dengan konten artikel lengkap dan breadcrumb', async () => {
    vi.mocked(publicApi.getPublicPostBySlug).mockResolvedValue({
      id: 'post-detail-1',
      title: 'Judul Warta Prestasi Gemilang',
      slug: 'olimpiade-sains-2026',
      content: 'Paragraf pertama berita resmi sekolah.\n\nParagraf kedua tentang capaian.',
      published_at: '2026-09-15T08:00:00Z',
      status: 'terbit',
      visibility: 'publik',
      category: { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
    });

    renderWithClient(<NewsDetailPage />);

    await waitFor(() => {
      expect(
        screen.getByRole('heading', { level: 1, name: 'Judul Warta Prestasi Gemilang' })
      ).toBeInTheDocument();
    });

    expect(screen.getByText('Paragraf pertama berita resmi sekolah.')).toBeInTheDocument();
    expect(screen.getByText('Paragraf kedua tentang capaian.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Kembali ke Daftar Berita/i })).toHaveAttribute(
      'href',
      '/berita'
    );
  });

  it('menangani error 404 pada detail berita dengan pesan informatif', async () => {
    vi.mocked(publicApi.getPublicPostBySlug).mockRejectedValue(new Error('Post not found'));

    renderWithClient(<NewsDetailPage />);

    await waitFor(() => {
      expect(screen.getByText(/Berita Tidak Ditemukan/i)).toBeInTheDocument();
    });

    expect(screen.getByRole('link', { name: /Daftar Berita Lainnya/i })).toHaveAttribute(
      'href',
      '/berita'
    );
  });

  it('INT-05: Merender gambar sampul (cover banner) pada halaman detail berita saat data cover tersedia', async () => {
    vi.mocked(publicApi.getPublicPostBySlug).mockResolvedValue({
      id: 'post-detail-cover',
      title: 'Judul Warta Prestasi Berkover',
      slug: 'olimpiade-sains-2026',
      content: 'Paragraf berita dengan cover image.',
      published_at: '2026-09-15T08:00:00Z',
      status: 'terbit',
      visibility: 'publik',
      cover_file_id: 'file-cov-1',
      cover_file: {
        id: 'file-cov-1',
        url: 'https://cdn.example.com/osn-cover.jpg',
      },
      category: { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
    });

    renderWithClient(<NewsDetailPage />);

    await waitFor(() => {
      const coverImg = screen.getByAltText(/Sampul warta Judul Warta Prestasi Berkover/i);
      expect(coverImg).toBeInTheDocument();
      expect(coverImg.getAttribute('src')).toBe('https://cdn.example.com/osn-cover.jpg');
    });
  });

  it('INT-05: Merender gambar sampul pada kartu daftar berita di indeks berita', async () => {
    vi.mocked(publicApi.getCategories).mockResolvedValue([]);
    vi.mocked(publicApi.getPublicPosts).mockResolvedValue({
      data: [
        {
          id: 'post-card-cover',
          title: 'Warta Kartu Berkover',
          slug: 'warta-kartu-berkover',
          content: 'Konten warta kartu dengan gambar sampul.',
          published_at: '2026-09-10T08:00:00Z',
          status: 'terbit',
          visibility: 'publik',
          cover_file_id: 'file-cov-2',
          cover_file: {
            id: 'file-cov-2',
            url: 'https://cdn.example.com/card-cover.jpg',
          },
        },
      ],
      meta: { page: 1, limit: 6, total: 1, totalPages: 1 },
    });

    renderWithClient(<NewsIndexPage />);

    await waitFor(() => {
      const cardCoverImg = screen.getByAltText(/Sampul warta Warta Kartu Berkover/i);
      expect(cardCoverImg).toBeInTheDocument();
      expect(cardCoverImg.getAttribute('src')).toBe('https://cdn.example.com/card-cover.jpg');
    });
  });
});
