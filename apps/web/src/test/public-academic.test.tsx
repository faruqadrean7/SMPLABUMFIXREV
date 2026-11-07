import React from 'react';
import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AcademicPage from '@/app/(public)/akademik/page';
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

describe('Public Academic Page Tests (WEB-ACA-01)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
      identity: {
        name: 'SMP Laboratorium UM',
      },
      contact: {
        phone: '(0341) 588550',
        email: 'akademik@smp-labum.sch.id',
      },
      profile: {
        accreditation: {
          grade: 'A',
          score: 96,
        },
      },
    });
  });

  it('merender hero section akademik dengan judul, slogan, dan breadcrumb navigasi', () => {
    const { container } = renderWithClient(<AcademicPage />);

    // H1 Heading
    const h1 = screen.getByRole('heading', { level: 1 });
    expect(h1).toHaveTextContent(/Program Pendidikan & Pendekatan Akademik/i);

    // Badge
    expect(screen.getByText(/Kurikulum & Keunggulan Belajar/i)).toBeInTheDocument();

    // Breadcrumb
    const breadcrumb = screen.getByRole('navigation', { name: /Navigasi Halaman/i });
    expect(breadcrumb).toBeInTheDocument();
    expect(screen.getByText('Akademik & Kurikulum')).toBeInTheDocument();

    // Exactly 1 main landmark with correct id
    const mains = container.querySelectorAll('main');
    expect(mains).toHaveLength(1);
    expect(mains[0]).toHaveAttribute('id', 'main-content');
  });

  it('merender 4 program pendidikan unggulan terverifikasi dengan karakteristiknya', () => {
    renderWithClient(<AcademicPage />);

    expect(
      screen.getByRole('heading', { level: 2, name: /4 Program Pendidikan Unggulan/i })
    ).toBeInTheDocument();

    // 4 Programs
    expect(screen.getByRole('heading', { level: 3, name: 'Program Reguler' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'ICP (International Class Program)' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: "Program Tahfidz Qur'an" })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'Program Pendidikan Inklusi' })).toBeInTheDocument();

    // Key features check
    expect(screen.getByText(/Penerapan Kurikulum Nasional SNP/i)).toBeInTheDocument();
    expect(screen.getByText(/Integrasi silabus Cambridge Lower Secondary/i)).toBeInTheDocument();
    expect(screen.getByText(/Bimbingan tahsin dan tahfidz terstruktur/i)).toBeInTheDocument();
    expect(screen.getByText(/Penyusunan Program Pembelajaran Individual \(PPI\)/i)).toBeInTheDocument();
  });

  it('merender metodologi pendekatan inovasi pembelajaran (Lesson Study & Mastery Learning)', () => {
    renderWithClient(<AcademicPage />);

    expect(
      screen.getByRole('heading', { level: 2, name: /Inovasi Pendekatan Pembelajaran/i })
    ).toBeInTheDocument();

    expect(screen.getByText('Model Riset & Lesson Study UM')).toBeInTheDocument();
    expect(screen.getByText('Sistem Belajar Tuntas (Mastery Learning)')).toBeInTheDocument();
    expect(screen.getByText("Penguatan Karakter & Adab Ta'awun")).toBeInTheDocument();
    expect(screen.getByText('Ekosistem Ramah Anak & Inklusif')).toBeInTheDocument();
  });

  it('merender pembinaan karakter berjenjang dan rumpun minat bakat siswa', () => {
    renderWithClient(<AcademicPage />);

    expect(
      screen.getByRole('heading', { level: 2, name: /Pembinaan Karakter, Minat & Bakat/i })
    ).toBeInTheDocument();

    // Wajib Jenjang
    expect(screen.getByText('Kelas VII')).toBeInTheDocument();
    expect(screen.getByText(/Pendidikan Keterampilan Hidup \(PKH\) & Pramuka/i)).toBeInTheDocument();

    expect(screen.getByText('Kelas VIII')).toBeInTheDocument();
    expect(screen.getByText(/TaBaQu \(Tahfidz & Tata Baca Al-Qur'an Terpadu\)/i)).toBeInTheDocument();

    // Rumpun Minat & Bakat
    expect(screen.getByText('Sains, Teknologi & Riset')).toBeInTheDocument();
    expect(screen.getByText('Bahasa & Komunikasi Global')).toBeInTheDocument();
    expect(screen.getByText('Seni Budaya & Kreativitas')).toBeInTheDocument();
    expect(screen.getByText('Olahraga & Kepemimpinan')).toBeInTheDocument();
  });

  it('merender CTA pendaftaran dan konsultasi akademik dengan rute yang valid', () => {
    const { container } = renderWithClient(<AcademicPage />);

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
    const ppdbLinks = screen.getAllByRole('link', { name: /PPDB|Daftar/i });
    expect(ppdbLinks.length).toBeGreaterThan(0);
    ppdbLinks.forEach((link) => {
      expect(link).toHaveAttribute('href', '/ppdb');
    });

    const profilLink = screen.getByRole('link', { name: /Profil & Visi Sekolah/i });
    expect(profilLink).toHaveAttribute('href', '/profil');

    const konsultasiLink = screen.getByRole('link', { name: /Konsultasi Akademik/i });
    expect(konsultasiLink).toHaveAttribute('href', '/kontak');
  });

  it('tidak menampilkan klaim angka fiktif tanpa sumber (seperti "24 Pilihan Ekstrakurikuler" atau biaya pendidikan palsu)', () => {
    const { container } = renderWithClient(<AcademicPage />);
    const pageText = container.textContent || '';

    // Pastikan angka statistik usang atau klaim tanpa verifikasi tidak muncul
    expect(pageText).not.toMatch(/24 Pilihan Ekstrakurikuler/i);
    expect(pageText).not.toMatch(/Biaya SPP/i);
    expect(pageText).not.toMatch(/Uang Gedung/i);
  });

  it('tidak merender tag <header> atau <footer> internal ganda pada halaman', () => {
    const { container } = renderWithClient(<AcademicPage />);

    expect(container.querySelectorAll('header')).toHaveLength(0);
    expect(container.querySelectorAll('footer')).toHaveLength(0);
  });
});
