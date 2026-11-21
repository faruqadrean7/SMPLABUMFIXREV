import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SafetyCounselingCard } from '@/features/portal/safety-counseling-card';

// SafetyCounselingCard memakai next/link; mock next/navigation agar aman di jsdom.
vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useSearchParams: () => ({ get: vi.fn().mockReturnValue(null) }),
}));

describe('FE-REPORT-02: SafetyCounselingCard (entry point portal ke /lapor)', () => {
  it('Portal Siswa: menampilkan narasi empati ramah siswa dan link lapor', () => {
    render(<SafetyCounselingCard role="student" />);

    expect(screen.getByText('Ruang Aman Siswa')).toBeInTheDocument();
    expect(
      screen.getByText(/Merasa Tidak Nyaman atau Mengalami Perundungan \(Bullying\)\?/i)
    ).toBeInTheDocument();

    const reportLink = screen.getByRole('link', { name: /Lapor Aman ke BK/i });
    expect(reportLink).toHaveAttribute('href', '/lapor');

    const trackLink = screen.getByRole('link', { name: /Lacak Tiket/i });
    expect(trackLink).toHaveAttribute('href', '/lapor/lacak');
  });

  it('Portal Ortu: menampilkan narasi wali murid dan link lapor', () => {
    render(<SafetyCounselingCard role="guardian" />);

    expect(screen.getByText('Kanal Perlindungan Ananda')).toBeInTheDocument();
    expect(
      screen.getByText(/Mendapati Masalah Perundungan atau Kekhawatiran pada Ananda\?/i)
    ).toBeInTheDocument();

    const reportLink = screen.getByRole('link', { name: /Sampaikan Pengaduan/i });
    expect(reportLink).toHaveAttribute('href', '/lapor');
  });
});
