import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import ContactPage from '@/app/(public)/kontak/page';
import {
  VERIFIED_CONTACT_DETAILS,
  OFFICE_HOURS,
  DIRECT_CHANNELS,
} from '@/features/public-site/content/contact';
import { publicApi } from '@/features/public-site/public-api';

// Mock navigator.clipboard
const writeTextMock = vi.fn().mockResolvedValue(undefined);
Object.assign(navigator, {
  clipboard: {
    writeText: writeTextMock,
  },
});

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

describe('WEB-CON-01 & INT-02: Public Contact Page (/kontak)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders hero section, heading, and breadcrumbs correctly', () => {
    renderWithClient(<ContactPage />);

    // 1. Single landmark <main id="main-content">
    const mainLandmark = screen.getByRole('main');
    expect(mainLandmark).toHaveAttribute('id', 'main-content');

    // 2. Heading H1
    const heading1 = screen.getByRole('heading', { level: 1, name: /Hubungi & Kunjungi Kami/i });
    expect(heading1).toBeInTheDocument();

    // 3. Breadcrumb navigation
    const breadcrumbNav = screen.getByRole('navigation', { name: /Navigasi Halaman/i });
    expect(breadcrumbNav).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Beranda/i })).toHaveAttribute('href', '/');
    expect(screen.getByText('Kontak')).toBeInTheDocument();
  });

  it('renders all verified official contact information', () => {
    renderWithClient(<ContactPage />);

    // Phone
    expect(screen.getAllByText(VERIFIED_CONTACT_DETAILS.phone.display).length).toBeGreaterThan(0);

    // WhatsApp
    expect(screen.getAllByText(VERIFIED_CONTACT_DETAILS.whatsapp.display).length).toBeGreaterThan(0);

    // Email
    expect(screen.getAllByText(VERIFIED_CONTACT_DETAILS.email.display).length).toBeGreaterThan(0);

    // Address
    expect(screen.getAllByText(VERIFIED_CONTACT_DETAILS.address.full).length).toBeGreaterThan(0);
    expect(screen.getByText(VERIFIED_CONTACT_DETAILS.address.campusArea)).toBeInTheDocument();

    // Office hours
    for (const item of OFFICE_HOURS) {
      expect(screen.getByText(item.days)).toBeInTheDocument();
      expect(screen.getByText(item.hours)).toBeInTheDocument();
    }
  });

  it('provides semantic and valid link protocols for telephone, WhatsApp, and email', () => {
    renderWithClient(<ContactPage />);

    // Phone link protocol
    const phoneLinks = screen.getAllByRole('link', { name: /telepon/i });
    const phoneActionLink = phoneLinks.find((l) => l.getAttribute('href')?.startsWith('tel:'));
    expect(phoneActionLink).toBeDefined();
    expect(phoneActionLink).toHaveAttribute('href', VERIFIED_CONTACT_DETAILS.phone.tel);

    // WhatsApp link protocol
    const waLinks = screen.getAllByRole('link', { name: /whatsapp/i });
    const waActionLink = waLinks.find((l) => l.getAttribute('href')?.includes('wa.me'));
    expect(waActionLink).toBeDefined();
    expect(waActionLink).toHaveAttribute('href', VERIFIED_CONTACT_DETAILS.whatsapp.url);
    expect(waActionLink).toHaveAttribute('target', '_blank');
    expect(waActionLink).toHaveAttribute('rel', expect.stringContaining('noopener'));

    // Email link protocol
    const mailLinks = screen.getAllByRole('link', { name: /email/i });
    const mailActionLink = mailLinks.find((l) => l.getAttribute('href')?.startsWith('mailto:'));
    expect(mailActionLink).toBeDefined();
    expect(mailActionLink).toHaveAttribute('href', VERIFIED_CONTACT_DETAILS.email.mailto);
  });

  it('allows user to copy contact information via accessible CopyContactButton', async () => {
    renderWithClient(<ContactPage />);

    // Find copy buttons
    const copyButtons = screen.getAllByRole('button', { name: /Salin/i });
    expect(copyButtons.length).toBeGreaterThanOrEqual(4); // 3 channels + 1 address

    // Click first copy button
    fireEvent.click(copyButtons[0]);

    expect(writeTextMock).toHaveBeenCalledTimes(1);

    // Verify visual update
    await waitFor(() => {
      expect(screen.getAllByText('Tersalin').length).toBeGreaterThan(0);
    });
  });

  it('renders accessible Google Maps iframe with valid title, lazy loading, and external route link', () => {
    renderWithClient(<ContactPage />);

    const iframe = screen.getByTitle('Peta Lokasi Kampus SMP Laboratorium UM');
    expect(iframe).toBeInTheDocument();
    expect(iframe).toHaveAttribute('src', VERIFIED_CONTACT_DETAILS.address.mapsEmbedUrl);
    expect(iframe).toHaveAttribute('loading', 'lazy');

    // External map navigation link
    const mapNavLinks = screen.getAllByRole('link', { name: /Google Maps/i });
    expect(mapNavLinks.length).toBeGreaterThan(0);
    for (const link of mapNavLinks) {
      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', expect.stringContaining('noopener'));
      expect(link.getAttribute('href')).toContain('google.com/maps');
    }
  });

  it('contains zero "#" broken links and only points to valid internal or external destinations', () => {
    renderWithClient(<ContactPage />);

    const allLinks = screen.getAllByRole('link');
    for (const link of allLinks) {
      const href = link.getAttribute('href');
      expect(href).not.toBe('#');
      expect(href).not.toBe('');
      expect(href).toBeTruthy();
    }
  });

  it('does NOT contain deceptive fake submission form and instead provides direct consultation notice', () => {
    renderWithClient(<ContactPage />);

    // Ensure there is no form pretending to submit messages without backend
    const forms = document.querySelectorAll('form');
    expect(forms.length).toBe(0);

    // Verify consultation guide is present
    expect(screen.getByText(/Panduan Layanan & Konsultasi/i)).toBeInTheDocument();
    expect(screen.getByText(/Pertanyaan PPDB & Biaya Pendidikan/i)).toBeInTheDocument();
  });

  it('renders Call to Action banners linking to valid internal routes (/ppdb, /profil)', () => {
    renderWithClient(<ContactPage />);

    const ppdbCta = screen.getByRole('link', { name: /Informasi PPDB Online/i });
    expect(ppdbCta).toHaveAttribute('href', '/ppdb');

    const profilCta = screen.getByRole('link', { name: /Lihat Profil Sekolah/i });
    expect(profilCta).toHaveAttribute('href', '/profil');
  });

  // =========================================================================
  // INT-02: DYNAMIC SITE SETTINGS TESTS
  // =========================================================================

  it('INT-02: merender kontak dinamis dari API pengaturan situs', async () => {
    vi.spyOn(publicApi, 'getPublicSite').mockResolvedValue({
      identity: {
        name: 'SMP Laboratorium Kampus 2',
      },
      contact: {
        phone: '(0341) 776655',
        whatsapp: '081377665544',
        email: 'humas@labum-pusat.sch.id',
      },
      address: {
        street: 'Jl. Veteran No. 8',
        city: 'Kota Malang',
        postal_code: '65145',
        maps_embed_url: 'https://maps.google.com/embed?pb=test-maps',
      },
    });

    renderWithClient(<ContactPage />);

    // Nilai kontak terintegrasi
    await waitFor(() => {
      expect(screen.getAllByText('(0341) 776655').length).toBeGreaterThanOrEqual(2);
    });
    expect(screen.getAllByText('081377665544').length).toBeGreaterThanOrEqual(2);
    expect(screen.getAllByText('humas@labum-pusat.sch.id').length).toBeGreaterThanOrEqual(2);

    // Alamat dinamis
    expect(screen.getByText('Jl. Veteran No. 8, Kota Malang 65145')).toBeInTheDocument();

    // Link protokol
    const phoneLinks = screen.getAllByRole('link', { name: /telepon/i });
    const dynamicPhoneLink = phoneLinks.find((l) => l.getAttribute('href')?.startsWith('tel:'));
    expect(dynamicPhoneLink).toHaveAttribute('href', 'tel:+62341776655');

    const waLinks = screen.getAllByRole('link', { name: /whatsapp/i });
    const dynamicWaLink = waLinks.find((l) => l.getAttribute('href')?.includes('wa.me'));
    expect(dynamicWaLink).toHaveAttribute('href', 'https://wa.me/6281377665544');

    const mailLinks = screen.getAllByRole('link', { name: /surel|email/i });
    const dynamicMailLink = mailLinks.find((l) => l.getAttribute('href')?.startsWith('mailto:'));
    expect(dynamicMailLink).toHaveAttribute('href', 'mailto:humas@labum-pusat.sch.id');

    // Embed map dinamis
    const iframe = screen.getByTitle(/Peta Lokasi Kampus/i);
    expect(iframe).toHaveAttribute('src', 'https://maps.google.com/embed?pb=test-maps');
  });

  it('INT-02: tetap menampilkan fallback saat API gagal (network error)', async () => {
    vi.spyOn(publicApi, 'getPublicSite').mockRejectedValue(new Error('Network failure'));

    renderWithClient(<ContactPage />);

    await waitFor(() => {
      expect(screen.getAllByText(VERIFIED_CONTACT_DETAILS.phone.display).length).toBeGreaterThan(0);
    });
    expect(screen.getAllByText(VERIFIED_CONTACT_DETAILS.whatsapp.display).length).toBeGreaterThan(0);
    expect(screen.getAllByText(VERIFIED_CONTACT_DETAILS.email.display).length).toBeGreaterThan(0);
  });
});
