import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { SkipLink, PublicHeader, PublicFooter } from '@/components/shell/public';
import { PublicAuthNav } from '@/components/shell/public/PublicAuthNav';
import * as authProvider from '@/providers/auth-provider';

// Mock Next Navigation
vi.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
    prefetch: vi.fn(),
  }),
}));

// Mock Next Image to simple img tag
vi.mock('next/image', () => ({
  // eslint-disable-next-line @next/next/no-img-element
  default: ({ src, alt, priority, ...props }: any) => <img src={src} alt={alt} {...props} />,
}));

describe('Public Shell & Accessibility Tests (Langkah 1)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    document.body.style.overflow = '';
    vi.spyOn(authProvider, 'useAuth').mockReturnValue({
      user: null,
      isAuthenticated: false,
      isLoading: false,
      activeRole: null,
      login: vi.fn(),
      logout: vi.fn(),
      checkAuth: vi.fn(),
      setActiveRole: vi.fn(),
    });
  });

  afterEach(() => {
    document.body.style.overflow = '';
  });

  describe('SkipLink Component', () => {
    it('merender tautan lompat ke konten utama dengan target #main-content', () => {
      render(<SkipLink />);
      const link = screen.getByRole('link', { name: /lewati ke konten utama/i });
      expect(link).toBeInTheDocument();
      expect(link).toHaveAttribute('href', '#main-content');
      expect(link.className).toContain('sr-only');
      expect(link.className).toContain('focus:not-sr-only');
    });
  });

  describe('PublicHeader Component (Desktop & Mobile Drawer)', () => {
    it('merender logo resmi dan identitas SMP Laboratorium UM', () => {
      render(<PublicHeader />);
      const logo = screen.getAllByAltText('Logo SMP Laboratorium')[0];
      expect(logo).toBeInTheDocument();
      expect(logo).toHaveAttribute('src', '/images/logo.png');
      expect(screen.getAllByText('SMP Laboratorium')[0]).toBeInTheDocument();
      expect(screen.getByText('P2LP Universitas Negeri Malang')).toBeInTheDocument();
    });

    it('merender seluruh 6 menu navigasi publik dengan tautan URL yang benar', () => {
      render(<PublicHeader />);
      const nav = screen.getByRole('navigation', { name: /navigasi utama/i });
      expect(nav).toBeInTheDocument();

      const expectedLinks = [
        { name: 'Beranda', href: '/' },
        { name: 'Profil', href: '/profil' },
        { name: 'Akademik', href: '/akademik' },
        { name: 'Berita', href: '/berita' },
        { name: 'Galeri', href: '/galeri' },
        { name: 'Kontak', href: '/kontak' },
      ];

      expectedLinks.forEach(({ name, href }) => {
        const link = within(nav).getByRole('link', { name });
        expect(link).toHaveAttribute('href', href);
      });
    });

    it('merender tombol aksi cepat PPDB Online menuju /ppdb', () => {
      render(<PublicHeader />);
      const cta = screen.getAllByRole('link', { name: /ppdb online/i })[0];
      expect(cta).toHaveAttribute('href', '/ppdb');
    });

    it('memiliki tombol hamburger mobile dengan atribut aksesibel yang benar', () => {
      render(<PublicHeader />);
      const btn = screen.getByRole('button', { name: /buka menu navigasi/i });
      expect(btn).toHaveAttribute('aria-expanded', 'false');
      expect(btn).toHaveAttribute('aria-controls', 'mobile-nav-drawer');
    });

    it('memastikan drawer tidak ada di DOM saat tertutup sehingga tidak ada tautan yang dapat difokuskan atau diekspos ke screen reader', () => {
      render(<PublicHeader />);
      // Saat tertutup, dialog tidak boleh ada di dokumen sama sekali
      expect(screen.queryByRole('dialog', { name: /menu navigasi mobile/i })).not.toBeInTheDocument();
      expect(screen.queryByTestId('mobile-backdrop')).not.toBeInTheDocument();
    });

    it('membuka drawer modal, mengunci scroll body, dan mengelola fokus saat tombol menu ditekan', async () => {
      render(<PublicHeader />);
      const menuBtn = screen.getByRole('button', { name: /buka menu navigasi/i });

      fireEvent.click(menuBtn);

      // Status expanded berubah
      expect(menuBtn).toHaveAttribute('aria-expanded', 'true');

      // Dialog drawer muncul
      const drawer = screen.getByRole('dialog', { name: /menu navigasi mobile/i });
      expect(drawer).toBeInTheDocument();
      expect(drawer).toHaveAttribute('aria-modal', 'true');

      // Scroll body dikunci
      expect(document.body.style.overflow).toBe('hidden');

      // Backdrop muncul
      const backdrop = screen.getByTestId('mobile-backdrop');
      expect(backdrop).toBeInTheDocument();
    });

    it('menutup drawer modal dan memulihkan scroll body saat tombol Escape ditekan', async () => {
      render(<PublicHeader />);
      const menuBtn = screen.getByRole('button', { name: /buka menu navigasi/i });

      fireEvent.click(menuBtn);
      expect(document.body.style.overflow).toBe('hidden');

      // Tekan tombol Escape
      fireEvent.keyDown(window, { key: 'Escape' });

      await waitFor(() => {
        expect(menuBtn).toHaveAttribute('aria-expanded', 'false');
        expect(document.body.style.overflow).toBe('');
        expect(screen.queryByRole('dialog', { name: /menu navigasi mobile/i })).not.toBeInTheDocument();
      });
    });

    it('menutup drawer modal saat backdrop diklik', async () => {
      render(<PublicHeader />);
      const menuBtn = screen.getByRole('button', { name: /buka menu navigasi/i });

      fireEvent.click(menuBtn);
      const backdrop = screen.getByTestId('mobile-backdrop');

      fireEvent.click(backdrop);

      await waitFor(() => {
        expect(menuBtn).toHaveAttribute('aria-expanded', 'false');
        expect(document.body.style.overflow).toBe('');
        expect(screen.queryByRole('dialog', { name: /menu navigasi mobile/i })).not.toBeInTheDocument();
      });
    });

    it('menutup drawer modal saat tombol close X ditekan', async () => {
      render(<PublicHeader />);
      const menuBtn = screen.getByRole('button', { name: /buka menu navigasi/i });

      fireEvent.click(menuBtn);
      const closeBtn = screen.getByRole('button', { name: /tutup panel navigasi/i });

      fireEvent.click(closeBtn);

      await waitFor(() => {
        expect(menuBtn).toHaveAttribute('aria-expanded', 'false');
        expect(document.body.style.overflow).toBe('');
        expect(screen.queryByRole('dialog', { name: /menu navigasi mobile/i })).not.toBeInTheDocument();
      });
    });
  });

  describe('PublicAuthNav Component', () => {
    it('merender "Masuk Portal" menuju /masuk ketika pengguna belum terautentikasi', () => {
      vi.spyOn(authProvider, 'useAuth').mockReturnValue({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        activeRole: null,
        login: vi.fn(),
        logout: vi.fn(),
        checkAuth: vi.fn(),
        setActiveRole: vi.fn(),
      });

      render(<PublicAuthNav variant="desktop" />);
      const link = screen.getByRole('link', { name: /(masuk portal|portal akademik)/i });
      expect(link).toHaveAttribute('href', '/masuk');
    });

    it('merender "Masuk Portal" / "Portal Akademik" sebagai fallback saat status autentikasi masih memuat (loading)', () => {
      vi.spyOn(authProvider, 'useAuth').mockReturnValue({
        user: null,
        isAuthenticated: false,
        isLoading: true,
        activeRole: null,
        login: vi.fn(),
        logout: vi.fn(),
        checkAuth: vi.fn(),
        setActiveRole: vi.fn(),
      });

      render(<PublicAuthNav variant="desktop" />);
      const link = screen.getByRole('link', { name: /(masuk portal|portal akademik)/i });
      expect(link).toHaveAttribute('href', '/masuk');
    });

    it('merender "Buka Portal Saya" menuju destinasi portal ketika pengguna sudah terautentikasi', () => {
      vi.spyOn(authProvider, 'useAuth').mockReturnValue({
        user: {
          id: 'user-student-1',
          name: 'Ahmad Siswa',
          email: 'siswa@smplab-um.sch.id',
          roles: ['student'],
          permissions: [],
        } as any,
        isAuthenticated: true,
        isLoading: false,
        activeRole: 'student',
        login: vi.fn(),
        logout: vi.fn(),
        checkAuth: vi.fn(),
        setActiveRole: vi.fn(),
      });

      render(<PublicAuthNav variant="desktop" />);
      const link = screen.getByRole('link', { name: /buka portal saya/i });
      expect(link).toHaveAttribute('href', '/portal/siswa');
    });
  });

  describe('PublicFooter Component', () => {
    it('merender informasi resmi alamat kampus yang terverifikasi', () => {
      render(<PublicFooter />);
      expect(
        screen.getByText(/Jl\. Simpang Bogor No\. T-7, Sumbersari, Kec\. Lowokwaru, Kota Malang 65145/i)
      ).toBeInTheDocument();
    });

    it('merender nomor telepon resmi dengan skema tautan tel:', () => {
      render(<PublicFooter />);
      const telLink = screen.getByRole('link', { name: /telepon: \(0341\) 588550/i });
      expect(telLink).toHaveAttribute('href', 'tel:+62341588550');
    });

    it('merender WhatsApp resmi dengan tautan wa.me terverifikasi', () => {
      render(<PublicFooter />);
      const waLink = screen.getByRole('link', { name: /whatsapp: 0851-2608-8473/i });
      expect(waLink).toHaveAttribute('href', 'https://wa.me/6285126088473');
      expect(waLink).toHaveAttribute('target', '_blank');
      expect(waLink).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('merender email resmi dengan skema tautan mailto:', () => {
      render(<PublicFooter />);
      const mailLink = screen.getByRole('link', { name: /info@smplab-um\.sch\.id/i });
      expect(mailLink).toHaveAttribute('href', 'mailto:info@smplab-um.sch.id');
    });

    it('tidak memiliki tautan kosong "#" palsu sama sekali', () => {
      const { container } = render(<PublicFooter />);
      const fakeLinks = container.querySelectorAll('a[href="#"]');
      expect(fakeLinks.length).toBe(0);
    });

    it('tidak merender iframe peta di dalam footer', () => {
      const { container } = render(<PublicFooter />);
      const iframes = container.querySelectorAll('iframe');
      expect(iframes.length).toBe(0);
    });

    it('merender teks hak cipta resmi terverifikasi', () => {
      render(<PublicFooter />);
      expect(
        screen.getByText(/© 2026 SMP Laboratorium\. Seluruh Hak Cipta Dilindungi\./i)
      ).toBeInTheDocument();
    });
  });
});
