import React from 'react';
import { render, screen, within } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import ProfilePage from '@/app/(public)/profil/page';
import { publicApi } from '@/features/public-site/public-api';

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

describe('Public School Profile Page Tests (WEB-PRF-01 & INT-02)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: tanpa data CMS → halaman memakai konten statis (hindari fetch nyata ke API saat uji).
    vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({} as any);
  });

  it('merender hero section dengan judul profil, slogan, dan breadcrumb navigasi', () => {
    const { container } = renderWithClient(<ProfilePage />);

    // H1 Heading (judul hero: "Profil Sekolah" + sr-only nama sekolah)
    const h1 = screen.getByRole('heading', { level: 1 });
    expect(h1).toHaveTextContent(/Profil Sekolah/i);

    // Slogan badge
    expect(screen.getByText(/Beradab, Kreatif, Berprestasi/i)).toBeInTheDocument();

    // Breadcrumb
    const breadcrumb = screen.getByRole('navigation', { name: /Navigasi Halaman/i });
    expect(breadcrumb).toBeInTheDocument();
    expect(within(breadcrumb).getByText('Profil Sekolah')).toBeInTheDocument();

    // Exactly 1 main landmark with correct id
    const mains = container.querySelectorAll('main');
    expect(mains).toHaveLength(1);
    expect(mains[0]).toHaveAttribute('id', 'main-content');
  });

  it('merender sambutan kepala sekolah dengan atribusi dan kutipan resmi terverifikasi', () => {
    renderWithClient(<ProfilePage />);

    expect(
      screen.getByRole('heading', { level: 2, name: /Mendidik Karakter, Menumbuhkan Potensi, Merajut Prestasi/i })
    ).toBeInTheDocument();

    const principalNames = screen.getAllByText('Bapak Tamyis Suliantoro, M.Pd.');
    expect(principalNames.length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Kepala Sekolah SMP Laboratorium UM')).toBeInTheDocument();

    // Quote resmi
    expect(
      screen.getByText(/Pendidikan bukan sekadar capaian angka akademis, melainkan perjalanan menumbuhkan kemandirian budi pekerti/i)
    ).toBeInTheDocument();
  });

  it('merender informasi pokok institusi dengan kontak resmi terverifikasi', () => {
    renderWithClient(<ProfilePage />);

    expect(screen.getByText('Terakreditasi “A” (Unggul)')).toBeInTheDocument();
    expect(screen.getAllByText('P2LP Universitas Negeri Malang').length).toBeGreaterThan(0);
    expect(screen.getByText('(0341) 588550')).toBeInTheDocument();
    expect(screen.getByText('0851-2608-8473')).toBeInTheDocument();
    expect(screen.getByText('info@smplab-um.sch.id')).toBeInTheDocument();

    // Valid contact links
    const telLink = screen.getByRole('link', { name: '(0341) 588550' });
    expect(telLink).toHaveAttribute('href', 'tel:+62341588550');

    const waLink = screen.getByRole('link', { name: '0851-2608-8473' });
    expect(waLink).toHaveAttribute('href', 'https://wa.me/6285126088473');

    const emailLink = screen.getByRole('link', { name: 'info@smplab-um.sch.id' });
    expect(emailLink).toHaveAttribute('href', 'mailto:info@smplab-um.sch.id');
  });

  it('merender visi resmi dan 7 butir misi sekolah', () => {
    renderWithClient(<ProfilePage />);

    // Visi
    expect(
      screen.getByText(/Unggul dalam Iman, Prestasi, Kemandirian, Sosial dan Budi Pekerti serta Berbudaya Lingkungan/i)
    ).toBeInTheDocument();

    // Misi list (ordered list)
    const missionList = screen.getByRole('list', { name: /Daftar Misi Sekolah/i });
    expect(missionList).toBeInTheDocument();

    const missionItems = missionList.querySelectorAll('li');
    expect(missionItems).toHaveLength(7);

    // Misi 1 & 7
    expect(missionItems[0]).toHaveTextContent(/Melaksanakan pembelajaran universal dan bimbingan secara efektif/i);
    expect(missionItems[6]).toHaveTextContent(/Meningkatkan kepedulian warga sekolah terhadap pelestarian lingkungan melalui program 3R/i);
  });

  it('merender 4 poin peran strategis sebagai sekolah laboratorium model UM', () => {
    renderWithClient(<ProfilePage />);

    expect(
      screen.getByRole('heading', { level: 2, name: /Peran Strategis Bersama Universitas Negeri Malang/i })
    ).toBeInTheDocument();

    expect(screen.getByText('Laboratorium Riset & Model Pembelajaran')).toBeInTheDocument();
    expect(screen.getByText('Pembinaan Berkelanjutan dari Pakar UM')).toBeInTheDocument();
    expect(screen.getByText('Integrasi Kurikulum Holistik Berjenjang')).toBeInTheDocument();
    expect(screen.getByText('Layanan Pendidikan Inklusi Ramah Anak')).toBeInTheDocument();
  });

  it('merender linimasa transformasi sejarah sekolah dari 1990 hingga era Cambridge/digital', () => {
    renderWithClient(<ProfilePage />);

    expect(
      screen.getByRole('heading', { level: 2, name: /Sejarah Perkembangan Sekolah/i })
    ).toBeInTheDocument();

    expect(screen.getAllByText('1990').length).toBeGreaterThan(0);
    expect(screen.getByText('Pendirian SMP IKIP Malang')).toBeInTheDocument();

    expect(screen.getByText('1996')).toBeInTheDocument();
    expect(screen.getByText('Transformasi Yayasan Pendidikan IKIP Malang')).toBeInTheDocument();

    expect(screen.getByText('2005 / 2006')).toBeInTheDocument();
    expect(screen.getByText('SMP Laboratorium UM & Inovasi Sistem Modul')).toBeInTheDocument();

    expect(screen.getByText('2009')).toBeInTheDocument();
    expect(screen.getByText('Peleburan ke dalam BPLP / P2LP UM')).toBeInTheDocument();

    expect(screen.getByText('2010 / 2011')).toBeInTheDocument();
    expect(screen.getByText('Perintisan Layanan Inklusi & Kecakapan Hidup')).toBeInTheDocument();

    expect(screen.getByText('2016 – Sekarang')).toBeInTheDocument();
    expect(screen.getByText('Integrasi Cambridge ICP & Ekosistem Digital')).toBeInTheDocument();
  });

  it('merender CTA dengan rute tujuan yang valid dan tanpa placeholder link "#"', () => {
    const { container } = renderWithClient(<ProfilePage />);

    // Check all links
    const allLinks = container.querySelectorAll('a');
    expect(allLinks.length).toBeGreaterThan(0);

    allLinks.forEach((link) => {
      const href = link.getAttribute('href');
      expect(href).not.toBeNull();
      expect(href).not.toBe('#');
      expect(href).not.toBe('');
      expect(
        href?.startsWith('/') ||
        href?.startsWith('tel:') ||
        href?.startsWith('mailto:') ||
        href?.startsWith('https://')
      ).toBe(true);
    });

    // Check key action links
    const ppdbLinks = screen.getAllByRole('link', { name: /PPDB|Pendaftaran/i });
    expect(ppdbLinks.length).toBeGreaterThan(0);
    ppdbLinks.forEach((link) => {
      expect(link).toHaveAttribute('href', '/ppdb');
    });

    const kontakLinks = screen.getAllByRole('link', { name: /Hubungi Kami/i });
    expect(kontakLinks.length).toBeGreaterThan(0);
    kontakLinks.forEach((link) => expect(link).toHaveAttribute('href', '/kontak'));

    const akademikLink = screen.getByRole('link', { name: /Program & Kurikulum/i });
    expect(akademikLink).toHaveAttribute('href', '/akademik');
  });

  it('tidak menampilkan statistik angka fiktif yang tidak terverifikasi (739 siswa, 38 guru)', () => {
    const { container } = renderWithClient(<ProfilePage />);
    const pageText = container.textContent || '';

    // Pastikan angka statistik usang dari prototipe tidak diklaim secara fiktif
    expect(pageText).not.toMatch(/739 Siswa Aktif/i);
    expect(pageText).not.toMatch(/38 Guru Profesional/i);
    expect(pageText).not.toMatch(/12 Tenaga Kependidikan/i);
  });

  it('tidak merender tag <header> atau <footer> internal ganda pada halaman', () => {
    const { container } = renderWithClient(<ProfilePage />);

    expect(container.querySelectorAll('header')).toHaveLength(0);
    expect(container.querySelectorAll('footer')).toHaveLength(0);
  });

  // =========================================================================
  // INT-02: DYNAMIC SITE SETTINGS TESTS
  // =========================================================================

  it('INT-02: merender data dinamis dari API pengaturan situs saat tersedia', async () => {
    vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
      identity: {
        name: 'SMP Laboratorium Prestasi',
        tagline: 'Slogan Baru Dinamis 2026',
      },
      contact: {
        phone: '(0341) 998877',
        whatsapp: '081299887766',
        email: 'kontak@labum-baru.sch.id',
      },
      profile: {
        vision: 'Visi Baru yang Terintegrasi dari CMS Admin',
        mission: ['Misi Pertama CMS', 'Misi Kedua CMS'],
        principal_greeting: {
          principal_name: 'Dr. Ahmad Subagyo, M.Ed.',
          greeting_text: 'Sambutan dinamis kepala sekolah yang diperbarui dari admin.',
        },
        accreditation: {
          grade: 'A+',
        },
      },
    });

    renderWithClient(<ProfilePage />);

    // Slogan baru
    expect(await screen.findByText('Slogan Baru Dinamis 2026')).toBeInTheDocument();

    // Nama sekolah baru
    expect(screen.getAllByText('SMP Laboratorium Prestasi').length).toBeGreaterThan(0);

    // Sambutan pimpinan baru
    expect(screen.getAllByText('Dr. Ahmad Subagyo, M.Ed.').length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText(/Sambutan dinamis kepala sekolah yang diperbarui dari admin/i)).toBeInTheDocument();

    // Akreditasi dinamis
    expect(screen.getByText('Terakreditasi "A+" (Unggul)')).toBeInTheDocument();

    // Kontak dinamis
    expect(screen.getByText('(0341) 998877')).toBeInTheDocument();
    expect(screen.getByText('081299887766')).toBeInTheDocument();
    expect(screen.getByText('kontak@labum-baru.sch.id')).toBeInTheDocument();

    // Valid dynamic link attributes
    expect(screen.getByRole('link', { name: '(0341) 998877' })).toHaveAttribute('href', 'tel:+62341998877');
    expect(screen.getByRole('link', { name: '081299887766' })).toHaveAttribute('href', 'https://wa.me/6281299887766');
    expect(screen.getByRole('link', { name: 'kontak@labum-baru.sch.id' })).toHaveAttribute('href', 'mailto:kontak@labum-baru.sch.id');

    // Visi dinamis
    expect(screen.getByText(/Visi Baru yang Terintegrasi dari CMS Admin/i)).toBeInTheDocument();

    // Misi dinamis
    expect(screen.getByText('Misi Pertama CMS')).toBeInTheDocument();
    expect(screen.getByText('Misi Kedua CMS')).toBeInTheDocument();
  });

  it('INT-02: tetap berfungsi dengan aman saat API mengalami network error', async () => {
    vi.spyOn(publicApi, 'getPublicSite').mockRejectedValue(new Error('Network error 500'));

    renderWithClient(<ProfilePage />);

    // Fallback tetap ter-render tanpa unhandled rejection / blank screen
    const heroHeading = await screen.findByRole('heading', { level: 1 });
    expect(heroHeading).toHaveTextContent(/Profil Sekolah/i);
    expect(screen.getByText('Terakreditasi "A" (Unggul)')).toBeInTheDocument();
    expect(screen.getByText('(0341) 588550')).toBeInTheDocument();
  });
});
