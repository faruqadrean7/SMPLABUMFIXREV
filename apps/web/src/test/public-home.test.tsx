import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import HomePage from '@/app/(public)/page';
import { publicApi } from '@/features/public-site/public-api';
import { HERO_CONTENT, PROFILE_SUMMARY } from '@/features/public-site/content/homepage';
import { SCHOOL_CONTACT_INFO } from '@/features/public-site/content/navigation';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

// Mock Next Navigation
vi.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
}));

// Mock Next Image
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

describe('Public HomePage Tests (WEB-HOME-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('merender hero section dengan identitas resmi sekolah dan slogan', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/(membina|mewujudkan) generasi/i);
    expect(screen.getAllByText(/p2lp universitas negeri malang/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/terakreditasi a \(unggul\)/i)).toBeInTheDocument();
  });

  it('merender tombol aksi utama PPDB menuju /ppdb dan sekunder ke /profil', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    const ppdbBtn = screen.getByRole('link', { name: /pendaftaran (ppdb|spmb) 2026\/2027/i });
    expect(ppdbBtn).toHaveAttribute('href', '/ppdb');

    const profilBtn = screen.getByRole('link', { name: /kenali (profil sekolah|sekolah kami)/i });
    expect(profilBtn).toHaveAttribute('href', '/profil');
  });

  it('merender kartu pimpinan kepala sekolah dengan kutipan sambutan resmi terverifikasi', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    expect(screen.getByText('Bapak Tamyis Suliantoro, M.Pd.')).toBeInTheDocument();
    expect(screen.getByText('Kepala Sekolah SMP Laboratorium')).toBeInTheDocument();
    expect(screen.getByText(/pendidikan bukan sekadar capaian angka akademis/i)).toBeInTheDocument();

    const readMoreLink = screen.getByRole('link', { name: /baca sambutan & profil lengkap/i });
    expect(readMoreLink).toHaveAttribute('href', '/profil');
  });

  it('merender 4 program akademik unggulan dengan tautan yang sesuai', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    expect(screen.getByRole('heading', { level: 3, name: /program reguler/i })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: /icp \(international class program\)/i })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: /program tahfidz/i })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: /program inklusi/i })).toBeInTheDocument();
  });

  it('merender pilar pendekatan model secara objektif tanpa mengarang statistik', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    expect(screen.getByText(/binaan pakar pendidikan um/i)).toBeInTheDocument();
    expect(screen.getByText(/penguatan karakter wajib/i)).toBeInTheDocument();
    expect(screen.getByText(/pengembangan minat & bakat/i)).toBeInTheDocument();
    expect(screen.getByText(/fasilitas belajar representatif/i)).toBeInTheDocument();
  });

  it('menangani state data kosong pada seksi publikasi berita', async () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    await waitFor(() => {
      expect(screen.getByText(/belum ada publikasi berita terbaru yang diterbitkan/i)).toBeInTheDocument();
    });
  });

  it('menangani state error pada seksi publikasi berita dengan tombol coba lagi', async () => {
    const errorSpy = vi.spyOn(publicApi, 'getLatestPosts').mockRejectedValue(new Error('Network error'));
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    await waitFor(() => {
      expect(screen.getByText(/tidak dapat memuat publikasi berita saat ini/i)).toBeInTheDocument();
    });

    const retryBtn = screen.getByRole('button', { name: /coba lagi/i });
    expect(retryBtn).toBeInTheDocument();
  });

  it('merender daftar berita nyata ketika data API tersedia', async () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([
      {
        id: 'post-1',
        title: 'Workshop Penguatan Riset Lesson Study Guru SMP Lab UM',
        slug: 'workshop-lesson-study',
        content: '<p>Kegiatan penguatan kurikulum berbasis riset pembelajaran.</p>',
        status: 'terbit',
        visibility: 'publik',
        published_at: '2026-10-10T08:00:00Z',
        category: {
          id: 'cat-1',
          name: 'Akademik',
          slug: 'akademik',
        },
      },
    ]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    await waitFor(() => {
      expect(screen.getByText('Workshop Penguatan Riset Lesson Study Guru SMP Lab UM')).toBeInTheDocument();
      expect(screen.getByText('Akademik')).toBeInTheDocument();
    });
  });

  it('merender banner CTA PPDB dengan tautan daftar akun dan informasi', async () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue({
      id: 'wave-1',
      name: 'Gelombang 1 Reguler & Prestasi',
      code: 'GEL-1',
      academic_year_id: 'ay-2026',
      start_date: '2026-01-05',
      end_date: '2026-03-17',
      is_active: true,
      description: 'Gelombang pertama pendaftaran peserta didik baru',
    } as any);

    renderWithClient(<HomePage />);

    expect(screen.getByRole('heading', { level: 2, name: /raih masa depan gemilang bersama smp laboratorium um/i })).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText(/gelombang aktif saat ini: Gelombang 1 Reguler & Prestasi\./i)).toBeInTheDocument();
    });

    const registerCta = screen.getByRole('link', { name: /daftar akun calon siswa/i });
    expect(registerCta).toHaveAttribute('href', '/ppdb/daftar');

    const infoCta = screen.getByRole('link', { name: /pelajari jalur & jadwal spmb/i });
    expect(infoCta).toHaveAttribute('href', '/ppdb');
  });

  it('merender seksi bantuan kontak dengan nomor telepon dan WhatsApp resmi terverifikasi', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    renderWithClient(<HomePage />);

    const telLink = screen.getByRole('link', { name: /\(0341\) 588550/i });
    expect(telLink).toHaveAttribute('href', 'tel:+62341588550');

    const waLink = screen.getByRole('link', { name: /whatsapp: 0851-2608-8473/i });
    expect(waLink).toHaveAttribute('href', 'https://wa.me/6285126088473');
  });

  it('tidak memiliki tautan placeholder "#" kosong sama sekali di beranda', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    const { container } = renderWithClient(<HomePage />);
    const fakeLinks = container.querySelectorAll('a[href="#"]');
    expect(fakeLinks.length).toBe(0);
  });

  it('merender tepat 1 tag <main> dan tidak merender tag <header> atau <footer> internal ganda', () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);

    const { container } = renderWithClient(<HomePage />);

    const mains = container.querySelectorAll('main');
    expect(mains.length).toBe(1);
    expect(mains[0]).toHaveAttribute('id', 'main-content');

    const headers = container.querySelectorAll('header');
    expect(headers.length).toBe(0);

    const footers = container.querySelectorAll('footer');
    expect(footers.length).toBe(0);
  });

  it('INT-02: merender identitas, sambutan, dan kontak dinamis pada homepage tanpa merusak berita dan PPDB', async () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([
      {
        id: 'post-1',
        title: 'Prestasi Juara 1 Robotika Nasional',
        slug: 'prestasi-juara-1-robotika',
        content: 'Siswa berhasil meraih medali emas tingkat nasional.',
        published_at: '2026-10-15T08:00:00Z',
        category: { id: 'c-1', name: 'Prestasi', slug: 'prestasi' },
      } as any,
    ]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue({
      id: 'wave-1',
      name: 'Gelombang 1 Reguler & Prestasi',
      status: 'active',
      start_date: '2026-01-01',
      end_date: '2026-03-31',
    } as any);
    vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
      identity: {
        name: 'SMP Laboratorium Unggulan UM',
        tagline: 'Cerdas Bernalar, Tangguh Berakhlak',
      },
      profile: {
        principal_greeting: {
          principal_name: 'Dr. Hj. Siti Nurjanah, M.Pd.',
          greeting_text: 'Selamat datang di gerbang percontohan riset pembelajaran modern.',
        },
        accreditation: {
          grade: 'A+',
        },
      },
      contact: {
        phone: '(0341) 887766',
        whatsapp: '081288776655',
      },
    });

    renderWithClient(<HomePage />);

    // Slogan dinamis
    expect(await screen.findByText('Cerdas Bernalar, Tangguh Berakhlak')).toBeInTheDocument();

    // Akreditasi dinamis
    expect(screen.getByText('Terakreditasi A+ (Unggul)')).toBeInTheDocument();

    // Sambutan pimpinan dinamis
    expect(screen.getByText('Dr. Hj. Siti Nurjanah, M.Pd.')).toBeInTheDocument();
    expect(screen.getByText(/Selamat datang di gerbang percontohan riset pembelajaran modern/i)).toBeInTheDocument();

    // Berita dinamis tetap bekerja
    expect(screen.getByText('Prestasi Juara 1 Robotika Nasional')).toBeInTheDocument();

    // Gelombang PPDB aktif tetap bekerja
    expect(screen.getByText(/Gelombang 1 Reguler & Prestasi/i)).toBeInTheDocument();

    // Kontak footer dinamis
    const telLink = screen.getByRole('link', { name: /\(0341\) 887766/i });
    expect(telLink).toHaveAttribute('href', 'tel:+62341887766');

    const waLink = screen.getByRole('link', { name: /whatsapp: 081288776655/i });
    expect(waLink).toHaveAttribute('href', 'https://wa.me/6281288776655');
  });

  it('INT-02: tetap merender homepage dengan fallback aman saat API site settings gagal', async () => {
    vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
    vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);
    vi.spyOn(publicApi, 'getPublicSite').mockRejectedValue(new Error('Backend error'));

    renderWithClient(<HomePage />);

    // Fallback tetap ter-render
    expect(await screen.findByText(HERO_CONTENT.tagline)).toBeInTheDocument();
    expect(screen.getByText(PROFILE_SUMMARY.principal.name)).toBeInTheDocument();
    expect(screen.getByText(SCHOOL_CONTACT_INFO.phone.display)).toBeInTheDocument();
  });
});
