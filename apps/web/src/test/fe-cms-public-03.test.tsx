import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AcademicPage from '@/app/(public)/akademik/page';
import { publicApi } from '@/features/public-site/public-api';
import type { SiteProfile } from '@/features/cms/types';

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

describe('FE-CMS-PUBLIC-03: Halaman Akademik Membaca Konten CMS (SiteProfile)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('1. Integrasi Dinamis Pita Akreditasi & Kredensial (AC1)', () => {
    it('FE-CMS-PUBLIC-03 (AC1): merender skor dan nomor akreditasi dinamis dari CMS', async () => {
      const mockSiteData: SiteProfile = {
        profile: {
          accreditation: {
            grade: 'A',
            score: 98,
            certificate_no: 'SK-BANSM/2026/099',
          },
        },
      };

      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue(mockSiteData);

      renderWithClient(<AcademicPage />);

      expect(await screen.findByText('Akreditasi A (Nilai 98)')).toBeInTheDocument();
      expect(screen.getByText('BAN-S/M • SK-BANSM/2026/099')).toBeInTheDocument();
    });

    it('FE-CMS-PUBLIC-03 (AC1): fallback nilai akreditasi resmi bila field nilai kosong di CMS', async () => {
      const mockSiteData: SiteProfile = {
        profile: {
          accreditation: {
            grade: 'A',
          },
        },
      };

      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue(mockSiteData);

      renderWithClient(<AcademicPage />);

      expect(await screen.findByText('Akreditasi A (Nilai 96)')).toBeInTheDocument();
      expect(screen.getByText('BAN-S/M')).toBeInTheDocument();
    });
  });

  describe('2. Integrasi 4 Program Unggulan Dinamis vs Fallback Baku (AC1 & AC2)', () => {
    it('FE-CMS-PUBLIC-03 (AC1): merender program unggulan dinamis dari CMS bila dikonfigurasi', async () => {
      const mockSiteData: SiteProfile = {
        profile: {
          featured_programs: [
            {
              title: 'Kelas Robotika & AI Mandiri',
              description: 'Program akselerasi komputasi cerdas dan kecerdasan artifisial generasi muda.',
              icon: 'STEM Inovasi',
            },
            {
              title: 'Cambridge Global Leadership',
              description: 'Kurikulum dwibahasa internasional dengan penutur asli.',
              icon: 'Internasional',
            },
          ],
        },
      };

      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue(mockSiteData);

      renderWithClient(<AcademicPage />);

      expect(await screen.findByText('Kelas Robotika & AI Mandiri')).toBeInTheDocument();
      expect(screen.getByText('Program akselerasi komputasi cerdas dan kecerdasan artifisial generasi muda.')).toBeInTheDocument();
      expect(screen.getByText('Cambridge Global Leadership')).toBeInTheDocument();
      expect(screen.getByText('Kurikulum dwibahasa internasional dengan penutur asli.')).toBeInTheDocument();
    });

    it('FE-CMS-PUBLIC-03 (AC2): fallback ke 4 program resmi sekolah terkonfirmasi saat featured_programs kosong', async () => {
      const mockSiteData: SiteProfile = {
        profile: {
          featured_programs: [],
        },
      };

      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue(mockSiteData);

      renderWithClient(<AcademicPage />);

      // Memastikan 4 program resmi sekolah tetap tampil
      expect(await screen.findByRole('heading', { level: 3, name: 'Program Reguler' })).toBeInTheDocument();
      expect(screen.getByRole('heading', { level: 3, name: 'ICP (International Class Program)' })).toBeInTheDocument();
      expect(screen.getByRole('heading', { level: 3, name: "Program Tahfidz Qur'an" })).toBeInTheDocument();
      expect(screen.getByRole('heading', { level: 3, name: 'Program Pendidikan Inklusi' })).toBeInTheDocument();

      // Karakteristik kunci terkonfirmasi tetap ada
      expect(screen.getByText(/Penerapan Kurikulum Nasional SNP/i)).toBeInTheDocument();
      expect(screen.getByText(/Integrasi silabus Cambridge Lower Secondary/i)).toBeInTheDocument();
      expect(screen.getByText(/Bimbingan tahsin dan tahfidz terstruktur/i)).toBeInTheDocument();
      expect(screen.getByText(/Penyusunan Program Pembelajaran Individual \(PPI\)/i)).toBeInTheDocument();
    });
  });

  describe('3. Integrasi Kontak & Konsultasi CTA (AC1)', () => {
    it('FE-CMS-PUBLIC-03 (AC1): merender nomor hotline dan tautan WhatsApp dinamis pada CTA', async () => {
      const mockSiteData: SiteProfile = {
        contact: {
          phone: '(0341) 778899',
          whatsapp: '081234567890',
          email: 'konsultasi@smp-labum.sch.id',
        },
      };

      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue(mockSiteData);

      renderWithClient(<AcademicPage />);

      expect(await screen.findByText('(0341) 778899')).toBeInTheDocument();
      expect(screen.getByText('konsultasi@smp-labum.sch.id')).toBeInTheDocument();

      const waLink = screen.getByRole('link', { name: /Konsultasi via WA/i });
      expect(waLink).toHaveAttribute('href', 'https://wa.me/081234567890');
    });
  });

  describe('4. Kurikulum Permanen Tetap Statis Sesuai Keputusan PRD (AC2)', () => {
    it('FE-CMS-PUBLIC-03 (AC2): metodologi Lesson Study UM dan tabel Karakter Berjenjang VII–IX tetap utuh', async () => {
      const mockSiteData: SiteProfile = {
        identity: { name: 'SMP Laboratorium UM' },
      };

      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue(mockSiteData);

      renderWithClient(<AcademicPage />);

      // Pendekatan Pembelajaran Lesson Study UM
      expect(await screen.findByText('Model Riset & Lesson Study UM')).toBeInTheDocument();
      expect(screen.getByText('Sistem Belajar Tuntas (Mastery Learning)')).toBeInTheDocument();

      // Karakter Berjenjang
      expect(screen.getByText('Kelas VII')).toBeInTheDocument();
      expect(screen.getByText(/Pendidikan Keterampilan Hidup \(PKH\) & Pramuka/i)).toBeInTheDocument();
      expect(screen.getByText('Kelas VIII')).toBeInTheDocument();
      expect(screen.getByText(/TaBaQu \(Tahfidz & Tata Baca Al-Qur'an Terpadu\)/i)).toBeInTheDocument();
    });
  });
});
