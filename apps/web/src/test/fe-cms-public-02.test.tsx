import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import HomePage from '@/app/(public)/page';
import ContactPage from '@/app/(public)/kontak/page';
import { publicApi } from '@/features/public-site/public-api';
import { SiteProfile } from '@/features/cms/types';

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

describe('FE-CMS-PUBLIC-02: Beranda & Kontak Baca Site-Settings (Dynamic CMS SiteProfile)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe('1. Beranda (HomePage) Dynamic Profile Integration', () => {
    it('FE-CMS-PUBLIC-02 (AC1): merender sambutan kepala sekolah dinamis dari siteData.profile.principal_greeting', async () => {
      vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);
      vi.spyOn(publicApi, 'getLatestEvents').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
        identity: {
          name: 'SMP Laboratorium Inovatif',
          tagline: 'Maju Bersama Riset',
        },
        profile: {
          principal_greeting: {
            principal_name: 'Prof. Dr. H. Ahmad Fauzi, M.Ed.',
            greeting_text: 'Membimbing generasi muda berkarakter unggul dengan pembelajaran riset abad 21.',
            principal_photo_url: 'https://cdn.example.com/photos/principal-fauzi.jpg',
          },
        },
      });

      renderWithClient(<HomePage />);

      expect(await screen.findByText('Prof. Dr. H. Ahmad Fauzi, M.Ed.')).toBeInTheDocument();
      expect(
        screen.getByText(/Membimbing generasi muda berkarakter unggul dengan pembelajaran riset abad 21/i)
      ).toBeInTheDocument();

      const principalImg = screen.getByAltText('Prof. Dr. H. Ahmad Fauzi, M.Ed.');
      expect(principalImg).toHaveAttribute('src', 'https://cdn.example.com/photos/principal-fauzi.jpg');
    });

    it('FE-CMS-PUBLIC-02 (AC1): merender visi dan misi sekolah dinamis dari siteData.profile.vision & mission', async () => {
      vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);
      vi.spyOn(publicApi, 'getLatestEvents').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
        profile: {
          vision: 'Menjadi pusat riset pembelajaran terdepan di Jawa Timur berbasis karakter mulia.',
          mission: [
            'Mengembangkan kurikulum integratif berstandar global.',
            'Mewujudkan iklim sekolah ramah anak dan inklusif.',
            'Meningkatkan kompetensi riset dan teknologi bagi civitas.',
          ],
        },
      });

      renderWithClient(<HomePage />);

      expect(
        await screen.findByText('Menjadi pusat riset pembelajaran terdepan di Jawa Timur berbasis karakter mulia.')
      ).toBeInTheDocument();
      expect(screen.getByText('Mengembangkan kurikulum integratif berstandar global.')).toBeInTheDocument();
      expect(screen.getByText('Mewujudkan iklim sekolah ramah anak dan inklusif.')).toBeInTheDocument();
      expect(screen.getByText('Meningkatkan kompetensi riset dan teknologi bagi civitas.')).toBeInTheDocument();
    });

    it('FE-CMS-PUBLIC-02 (AC1): merender program keunggulan sekolah dinamis dari siteData.profile.featured_programs', async () => {
      vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);
      vi.spyOn(publicApi, 'getLatestEvents').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
        profile: {
          featured_programs: [
            {
              title: 'Laboratorium Robotika & AI Cilik',
              description: 'Pembelajaran kecerdasan artifisial dan robotik terapan sejak jenjang awal.',
            },
            {
              title: 'Program Riset Lingkungan Hidup',
              description: 'Eksplorasi biologi dan ekologi kampus terpadu Universitas Negeri Malang.',
            },
          ],
        },
      });

      renderWithClient(<HomePage />);

      expect(await screen.findByText('Laboratorium Robotika & AI Cilik')).toBeInTheDocument();
      expect(
        screen.getByText('Pembelajaran kecerdasan artifisial dan robotik terapan sejak jenjang awal.')
      ).toBeInTheDocument();
      expect(screen.getByText('Program Riset Lingkungan Hidup')).toBeInTheDocument();
      expect(
        screen.getByText('Eksplorasi biologi dan ekologi kampus terpadu Universitas Negeri Malang.')
      ).toBeInTheDocument();
    });

    it('FE-CMS-PUBLIC-02 (AC2): kontak bantuan beranda menggunakan data nomor telepon & wa dari site-settings', async () => {
      vi.spyOn(publicApi, 'getLatestPosts').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getActiveWave').mockResolvedValue(null);
      vi.spyOn(publicApi, 'getLatestEvents').mockResolvedValue([]);
      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
        contact: {
          phone: '(0341) 998877',
          whatsapp: '081234567899',
        },
      });

      renderWithClient(<HomePage />);

      const phoneLink = await screen.findByRole('link', { name: /\(0341\) 998877/i });
      expect(phoneLink).toHaveAttribute('href', 'tel:+62341998877');

      const waLink = screen.getByRole('link', { name: /whatsapp: 081234567899/i });
      expect(waLink).toHaveAttribute('href', 'https://wa.me/6281234567899');
    });
  });

  describe('2. Halaman Kontak (/kontak) Dynamic Integration', () => {
    it('FE-CMS-PUBLIC-02 (AC1): merender informasi kontak, alamat, dan embed maps dinamis dari CMS', async () => {
      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
        identity: {
          name: 'SMP Laboratorium Kampus Utama',
        },
        contact: {
          phone: '(0341) 123456',
          whatsapp: '081987654321',
          email: 'sekretariat@labum-inovasi.sch.id',
        },
        address: {
          street: 'Jl. Surabaya No. 10',
          city: 'Kota Malang',
          postal_code: '65115',
          maps_embed_url: 'https://maps.google.com/embed?pb=custom-embed-url',
        },
      });

      renderWithClient(<ContactPage />);

      await waitFor(() => {
        expect(screen.getAllByText('(0341) 123456').length).toBeGreaterThanOrEqual(2);
      });
      expect(screen.getAllByText('081987654321').length).toBeGreaterThanOrEqual(2);
      expect(screen.getAllByText('sekretariat@labum-inovasi.sch.id').length).toBeGreaterThanOrEqual(2);

      expect(screen.getByText('Jl. Surabaya No. 10, Kota Malang 65115')).toBeInTheDocument();

      const iframe = screen.getByTitle(/Peta Lokasi Kampus/i);
      expect(iframe).toHaveAttribute('src', 'https://maps.google.com/embed?pb=custom-embed-url');
    });

    it('FE-CMS-PUBLIC-02 (AC1): merender kanal media sosial jika dikonfigurasi di CMS', async () => {
      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
        social_media: {
          instagram: '@smplabum_official',
          youtube: 'smplabum_tv',
          facebook: 'smplaboratorium.um',
        },
      });

      renderWithClient(<ContactPage />);

      expect(await screen.findByText('Kanal Media Sosial Resmi')).toBeInTheDocument();
      expect(screen.getByText('@smplabum_official')).toBeInTheDocument();
      expect(screen.getByText('smplabum_tv')).toBeInTheDocument();
      expect(screen.getByText('smplaboratorium.um')).toBeInTheDocument();

      const igLink = screen.getByRole('link', { name: /Instagram: @smplabum_official/i });
      expect(igLink).toHaveAttribute('href', 'https://instagram.com/smplabum_official');
    });

    it('FE-CMS-PUBLIC-02 (AC3): menyembunyikan seksi media sosial jika field kosong di CMS', async () => {
      vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
        social_media: undefined,
      });

      renderWithClient(<ContactPage />);

      await waitFor(() => {
        expect(screen.getByText(/Kanal Layanan Cepat/i)).toBeInTheDocument();
      });

      expect(screen.queryByText('Kanal Media Sosial Resmi')).not.toBeInTheDocument();
    });
  });
});
