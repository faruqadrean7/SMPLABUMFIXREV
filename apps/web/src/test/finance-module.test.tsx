import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import {
  formatRupiah,
  validateProofFile,
  financeApi,
} from '@/features/finance/finance-api';
import { ADMIN_NAV_SECTIONS, getAdminRoutePermission } from '@/lib/rbac/navigation';
import FeeTypesPage from '@/app/(admin)/admin/finance/fee-types/page';
import PaymentProviderSettingsPage from '@/app/(admin)/admin/finance/settings/page';
import InvoicesAdminPage from '@/app/(admin)/admin/finance/invoices/page';
import PaymentsAdminPage from '@/app/(admin)/admin/finance/payments/page';
import ArrearsReportPage from '@/app/(admin)/admin/finance/arrears/page';
import FinanceReportsPage from '@/app/(admin)/admin/finance/reports/page';
import StudentInvoicesPage from '@/app/(portal)/portal/siswa/tagihan/page';
import { ParentFinanceTab } from '@/features/finance/components/parent-finance-tab';
import { StudentHeader } from '@/components/shell/portal/student-header';
import {
  Invoice,
  Payment,
  OfficialReceipt,
  PaymentProviderSetting,
  FeeType,
} from '@/types/finance';

// Mock Next.js navigation
vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => '/admin/finance',
  useParams: () => ({ id: 'child-123' }),
}));

// Mock Auth Provider
vi.mock('@/providers/auth-provider', () => ({
  useAuth: vi.fn(() => ({
    user: {
      id: 'usr-admin-1',
      username: 'bendahara',
      roles: ['bendahara', 'super_admin'],
      permissions: [
        'finance.fee.manage',
        'finance.invoice.manage',
        'finance.payment.verify',
        'finance.report.view',
      ],
      profile: { name: 'Ibu Bendahara' },
    },
    logout: vi.fn(),
  })),
}));

describe('GAP-FIN-01 (REVISI BATCH 3B): Modul Keuangan & Slip Pembayaran Pihak Ketiga', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('1. Currency & Client-Side Proof File Validation', () => {
    it('formats integer rupiah without float/decimal according to Indonesian locale', () => {
      const formatted500k = formatRupiah(500000);
      expect(formatted500k).toMatch(/Rp\s?500\.000/);

      const formatted0 = formatRupiah(0);
      expect(formatted0).toMatch(/Rp\s?0/);

      const formattedMillion = formatRupiah(2500000);
      expect(formattedMillion).toMatch(/Rp\s?2\.500\.000/);
    });

    it('validates proof file: accepts JPG, PNG, and PDF under 2MB', () => {
      const validJpg = new File(['dummy-content'], 'bukti.jpg', { type: 'image/jpeg' });
      expect(validateProofFile(validJpg)).toEqual({ valid: true });

      const validPng = new File(['dummy-content'], 'struk.png', { type: 'image/png' });
      expect(validateProofFile(validPng)).toEqual({ valid: true });

      const validPdf = new File(['dummy-content'], 'bukti.pdf', { type: 'application/pdf' });
      expect(validateProofFile(validPdf)).toEqual({ valid: true });
    });

    it('validates proof file: rejects unsupported file types and files larger than 2MB', () => {
      const invalidTxt = new File(['hello'], 'struk.txt', { type: 'text/plain' });
      const txtResult = validateProofFile(invalidTxt);
      expect(txtResult.valid).toBe(false);
      expect(txtResult.error).toContain('Format file tidak didukung');

      // Create dummy oversized file (> 2MB)
      const oversizedBlob = new Blob([new Uint8Array(2.5 * 1024 * 1024)]);
      const oversizedFile = new File([oversizedBlob], 'huge.jpg', { type: 'image/jpeg' });
      const sizeResult = validateProofFile(oversizedFile);
      expect(sizeResult.valid).toBe(false);
      expect(sizeResult.error).toContain('melebihi batas maksimal 2MB');
    });
  });

  describe('2. Navigation & RBAC Permission Mapping', () => {
    it('registers Keuangan & Pembayaran in ADMIN_NAV_SECTIONS with proper permissions including settings', () => {
      const financeSection = ADMIN_NAV_SECTIONS.find((s) => s.title === 'Keuangan & Pembayaran');
      expect(financeSection).toBeDefined();

      const mainItem = financeSection!.items.find((i) => i.href === '/admin/finance');
      expect(mainItem).toBeDefined();
      expect(mainItem!.children).toHaveLength(6);

      const subTitles = mainItem!.children!.map((c) => c.title);
      expect(subTitles).toContain('Jenis Biaya & Tarif');
      expect(subTitles).toContain('Tagihan Siswa');
      expect(subTitles).toContain('Verifikasi Pembayaran');
      expect(subTitles).toContain('Rekap Tunggakan');
      expect(subTitles).toContain('Laporan Keuangan');
      expect(subTitles).toContain('Cara Bayar & Rekening');
    });

    it('resolves correct permissions via getAdminRoutePermission', () => {
      expect(getAdminRoutePermission('/admin/finance/fee-types')).toBe('finance.fee.manage');
      expect(getAdminRoutePermission('/admin/finance/invoices')).toBe('finance.invoice.manage');
      expect(getAdminRoutePermission('/admin/finance/payments')).toBe('finance.payment.verify');
      expect(getAdminRoutePermission('/admin/finance/arrears')).toBe('finance.report.view');
      expect(getAdminRoutePermission('/admin/finance/reports')).toBe('finance.report.view');
      expect(getAdminRoutePermission('/admin/finance/settings')).toBe('finance.fee.manage');
    });

    it('includes Tagihan in StudentHeader navbar linking to /portal/siswa/tagihan', () => {
      render(<StudentHeader />);
      const tagihanLink = screen.getByRole('link', { name: /Tagihan/i });
      expect(tagihanLink).toBeInTheDocument();
      expect(tagihanLink).toHaveAttribute('href', '/portal/siswa/tagihan');
    });
  });

  describe('3. Student Portal Invoices (Read-Only & VA Display)', () => {
    it('renders student bills read-only with VA number, payment instructions, and official receipt without upload button', async () => {
      const mockProviders: PaymentProviderSetting[] = [
        {
          id: 'prov-1',
          name: 'Bank Jatim Virtual Account',
          instructions: 'Transfer ke nomor Virtual Account via ATM / Mobile Banking.',
          info_url: 'https://bankjatim.co.id/cara-bayar-va',
          is_active: true,
        },
      ];

      const mockInvoices: Invoice[] = [
        {
          id: 'inv-student-1',
          invoice_number: 'INV/2026/10/0001',
          student_id: 'std-1',
          academic_year_id: 'ay-1',
          period: '2026-10',
          due_date: '2026-10-20',
          va_number: '9880123456789012',
          total_amount: 500000,
          paid_amount: 500000,
          remaining_amount: 0,
          status: 'lunas',
          items: [
            {
              id: 'it-1',
              invoice_id: 'inv-student-1',
              fee_type_id: 'ft-1',
              description: 'SPP Bulan Oktober 2026',
              amount: 500000,
              discount: 0,
            },
          ],
          payments: [
            {
              id: 'pay-1',
              invoice_id: 'inv-student-1',
              amount: 500000,
              channel: 'Bank Jatim VA',
              external_reference: 'BJ-TX-888999',
              payment_date: '2026-10-15',
              status: 'terverifikasi',
              receipt_number: 'KWT/2026/10/0001',
            },
          ],
        },
      ];

      const mockReceipt: OfficialReceipt = {
        receipt_number: 'KWT/2026/10/0001',
        payment_date: '2026-10-15',
        student_name: 'Ahmad Siswa',
        student_nisn: '1234567890',
        class_name: '7-A',
        amount: 500000,
        terbilang: 'Lima Ratus Ribu Rupiah',
        channel: 'Bank Jatim VA',
        external_reference: 'BJ-TX-888999',
        invoice_number: 'INV/2026/10/0001',
        verifier_name: 'Bendahara Sekolah',
        verified_at: '2026-10-15 10:00:00',
        school_info: {
          name: 'SMP Laboratorium UM',
          address: 'Jl. Simpang Bogor T-7 Malang',
          phone: '(0341) 551312',
        },
      };

      vi.spyOn(financeApi, 'getMyInvoices').mockResolvedValue(mockInvoices);
      vi.spyOn(financeApi, 'getPortalPaymentProviders').mockResolvedValue(mockProviders);
      vi.spyOn(financeApi, 'getPortalPaymentReceipt').mockResolvedValue(mockReceipt);

      render(<StudentInvoicesPage />);

      // Wait for loading to finish and invoices to render
      await waitFor(() => {
        expect(screen.getByText('INV/2026/10/0001')).toBeInTheDocument();
        expect(screen.getByText('9880123456789012')).toBeInTheDocument();
        expect(screen.getByText('SPP Bulan Oktober 2026')).toBeInTheDocument();
        expect(screen.getByText('Bank Jatim Virtual Account')).toBeInTheDocument();
        expect(screen.getByText(/BJ-TX-888999/)).toBeInTheDocument();
      });

      // Ensure no slip upload button exists on student page (strictly read-only)
      expect(screen.queryByRole('button', { name: /Unggah Slip/i })).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: /Bayar/i })).not.toBeInTheDocument();

      // Click "Kuitansi" button to view official receipt
      const receiptBtn = screen.getByRole('button', { name: /Kuitansi/i });
      fireEvent.click(receiptBtn);

      await waitFor(() => {
        expect(screen.getByText('Kuitansi Pembayaran Sah')).toBeInTheDocument();
        expect(screen.getByText(/Lima Ratus Ribu Rupiah/i)).toBeInTheDocument();
        expect(screen.getByText('Ahmad Siswa')).toBeInTheDocument();
      });
    });
  });

  describe('4. Parent Portal Finance Tab (Anti-IDOR & Third-Party Slip Upload Flow)', () => {
    it('renders provider instructions, VA number, unpaid bills, and allows uploading payment slip', async () => {
      const mockProviders: PaymentProviderSetting[] = [
        {
          id: 'prov-1',
          name: 'Bank Jatim Virtual Account',
          instructions: 'Transfer ke VA Siswa 9880123456789012.',
          info_url: 'https://bankjatim.co.id',
          is_active: true,
        },
      ];

      const mockChildInvoices: Invoice[] = [
        {
          id: 'inv-child-1',
          invoice_number: 'INV/2026/10/0088',
          student_id: 'child-123',
          academic_year_id: 'ay-1',
          period: '2026-10',
          due_date: '2026-10-25',
          va_number: '9880123456789012',
          total_amount: 500000,
          paid_amount: 200000,
          remaining_amount: 300000,
          status: 'sebagian',
          items: [
            {
              id: 'it-1',
              invoice_id: 'inv-child-1',
              fee_type_id: 'ft-1',
              description: 'SPP Bulan Oktober 2026',
              amount: 500000,
              discount: 0,
            },
          ],
          payments: [
            {
              id: 'p-prev',
              invoice_id: 'inv-child-1',
              amount: 200000,
              channel: 'Bank Jatim VA',
              external_reference: 'BJ-TX-111222',
              payment_date: '2026-10-10',
              status: 'terverifikasi',
              receipt_number: 'KWT/2026/10/0045',
            },
          ],
        },
      ];

      vi.spyOn(financeApi, 'getPortalPaymentProviders').mockResolvedValue(mockProviders);
      vi.spyOn(financeApi, 'getChildInvoices').mockResolvedValue(mockChildInvoices);
      const submitPaymentSpy = vi.spyOn(financeApi, 'submitPayment').mockResolvedValue({
        id: 'new-pay-1',
        invoice_id: 'inv-child-1',
        amount: 300000,
        channel: 'Bank Jatim VA',
        external_reference: 'BJ-TX-999000',
        payment_date: '2026-10-16',
        status: 'menunggu_verifikasi',
      } as Payment);

      render(<ParentFinanceTab childId="child-123" />);

      // Verify provider info, VA number, and invoice rendered
      await waitFor(() => {
        expect(screen.getByText('Bank Jatim Virtual Account')).toBeInTheDocument();
        expect(screen.getByText('9880123456789012')).toBeInTheDocument();
        expect(screen.getByText('INV/2026/10/0088')).toBeInTheDocument();
      });

      // Click "Unggah Slip Pembayaran"
      const uploadSlipBtn = screen.getByRole('button', { name: /Unggah Slip Pembayaran/i });
      fireEvent.click(uploadSlipBtn);

      // Verify modal opened
      expect(screen.getByRole('heading', { name: 'Unggah Slip Pembayaran' })).toBeInTheDocument();

      // Fill in external reference
      const refInput = screen.getByPlaceholderText(/Nomor referensi \/ nomor transaksi/i);
      fireEvent.change(refInput, { target: { value: 'BJ-TX-999000' } });

      // Attach proof file
      const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
      const testFile = new File(['dummy-img'], 'struk_transfer.png', { type: 'image/png' });
      fireEvent.change(fileInput, { target: { files: [testFile] } });

      // Click "Kirim Slip Pembayaran"
      const submitBtn = screen.getByRole('button', { name: /Kirim Slip Pembayaran/i });
      fireEvent.submit(submitBtn.closest('form')!);

      await waitFor(() => {
        expect(submitPaymentSpy).toHaveBeenCalledWith(
          'inv-child-1',
          expect.objectContaining({
            amount: 300000,
            channel: 'Bank Jatim Virtual Account',
            external_reference: 'BJ-TX-999000',
            proof_file: testFile,
          })
        );
      });
    });

    it('displays rejection reason and allows re-uploading slip when previous slip was rejected', async () => {
      const mockChildInvoices: Invoice[] = [
        {
          id: 'inv-child-rejected',
          invoice_number: 'INV/2026/10/0099',
          student_id: 'child-123',
          academic_year_id: 'ay-1',
          period: '2026-10',
          due_date: '2026-10-25',
          total_amount: 500000,
          paid_amount: 0,
          remaining_amount: 500000,
          status: 'belum_bayar',
          items: [],
          payments: [
            {
              id: 'p-rejected',
              invoice_id: 'inv-child-rejected',
              amount: 500000,
              channel: 'Bank BCA Transfer',
              external_reference: 'BCA-ERR-001',
              payment_date: '2026-10-12',
              status: 'ditolak',
              rejection_reason: 'Foto struk buram dan tanggal transfer tidak terbaca.',
            },
          ],
        },
      ];

      vi.spyOn(financeApi, 'getPortalPaymentProviders').mockResolvedValue([]);
      vi.spyOn(financeApi, 'getChildInvoices').mockResolvedValue(mockChildInvoices);

      render(<ParentFinanceTab childId="child-123" />);

      await waitFor(() => {
        expect(screen.getByText('Foto struk buram dan tanggal transfer tidak terbaca.')).toBeInTheDocument();
        expect(screen.getByText('Ditolak')).toBeInTheDocument();
        // Since slip was rejected, the button "Unggah Slip Pembayaran" is still available for re-uploading
        expect(screen.getByRole('button', { name: /Unggah Slip Pembayaran/i })).toBeInTheDocument();
      });
    });
  });

  describe('5. Admin Finance Pages (Fee Types, Settings, Invoices, Payments, Reports)', () => {
    it('Admin FeeTypesPage: renders list of fee types and opens create modal', async () => {
      const mockFeeTypes: FeeType[] = [
        {
          id: 'ft-1',
          code: 'SPP-SMP',
          name: 'SPP Bulanan SMP',
          category: 'spp',
          default_amount: 500000,
          is_recurring: true,
          is_active: true,
        },
      ];

      vi.spyOn(financeApi, 'getFeeTypes').mockResolvedValue(mockFeeTypes);

      render(<FeeTypesPage />);

      await waitFor(() => {
        expect(screen.getByText('SPP-SMP')).toBeInTheDocument();
        expect(screen.getByText('SPP Bulanan SMP')).toBeInTheDocument();
      });

      // Open Create Modal
      const addBtn = screen.getByRole('button', { name: /Tambah Biaya/i });
      fireEvent.click(addBtn);

      expect(screen.getByText('Tambah Jenis Biaya Baru')).toBeInTheDocument();
    });

    it('Admin PaymentProviderSettingsPage: renders list of providers and opens create modal', async () => {
      const mockProviders: PaymentProviderSetting[] = [
        {
          id: 'prov-setting-1',
          name: 'Bank Jatim VA',
          instructions: 'Instruksi pembayaran lewat Virtual Account.',
          info_url: 'https://bankjatim.co.id',
          is_active: true,
        },
      ];

      vi.spyOn(financeApi, 'getPaymentProviders').mockResolvedValue(mockProviders);

      render(<PaymentProviderSettingsPage />);

      await waitFor(() => {
        expect(screen.getByText('Bank Jatim VA')).toBeInTheDocument();
        expect(screen.getByText('Instruksi pembayaran lewat Virtual Account.')).toBeInTheDocument();
      });

      // Open Create Modal
      const addBtn = screen.getByRole('button', { name: /Tambah Penyedia/i });
      fireEvent.click(addBtn);

      expect(screen.getByText('Tambah Penyedia Pembayaran Pihak Ketiga')).toBeInTheDocument();
    });

    it('Admin PaymentsPage: renders slip verification queue, supports search by external_reference, and allows Terima/Tolak slip', async () => {
      const mockPayments: Payment[] = [
        {
          id: 'pay-pending-1',
          invoice_id: 'inv-1',
          amount: 500000,
          channel: 'Bank Jatim VA',
          external_reference: 'REF-AUTO-9988',
          payment_date: '2026-10-18',
          status: 'menunggu_verifikasi',
          proof_file_url: 'http://localhost/storage/proof.jpg',
          invoice: {
            id: 'inv-1',
            invoice_number: 'INV/2026/10/0100',
            student_id: 'std-1',
            student: {
              id: 'std-1',
              full_name: 'Budi Santoso',
              nisn: '9876543210',
            },
            academic_year_id: 'ay-1',
            due_date: '2026-10-25',
            total_amount: 500000,
            paid_amount: 0,
            remaining_amount: 500000,
            status: 'belum_bayar',
          },
        },
      ];

      vi.spyOn(financeApi, 'getPayments').mockResolvedValue({
        data: mockPayments,
        meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
      });

      window.confirm = vi.fn().mockReturnValue(true);
      const verifySpy = vi.spyOn(financeApi, 'verifyPayment').mockResolvedValue({
        ...mockPayments[0],
        status: 'terverifikasi',
        receipt_number: 'KWT/2026/10/0099',
      });

      render(<PaymentsAdminPage />);

      await waitFor(() => {
        expect(screen.getByText('Budi Santoso')).toBeInTheDocument();
        expect(screen.getByText('REF-AUTO-9988')).toBeInTheDocument();
        expect(screen.getByText('Bank Jatim VA')).toBeInTheDocument();
      });

      // Verify "Terima Slip" action
      const verifyBtn = screen.getByRole('button', { name: /Terima Slip/i });
      fireEvent.click(verifyBtn);

      expect(verifySpy).toHaveBeenCalledWith('pay-pending-1');
    });

    it('Admin ArrearsReportPage: renders arrears summary cards and breakdown', async () => {
      vi.spyOn(financeApi, 'getArrearsReport').mockResolvedValue({
        total_arrears: 1500000,
        total_invoices_unpaid: 3,
        students_in_arrears_count: 2,
        by_grade: [
          { grade: '7', arrears: 1000000, count: 2 },
          { grade: '8', arrears: 500000, count: 1 },
        ],
        items: [],
      });

      render(<ArrearsReportPage />);

      await waitFor(() => {
        expect(screen.getByText('Total Piutang Tertunggak')).toBeInTheDocument();
        expect(screen.getByText('3 Faktur')).toBeInTheDocument();
        expect(screen.getByText('2 Siswa')).toBeInTheDocument();
      });
    });

    it('Admin FinanceReportsPage: renders total income summary and daily stream from verified slips', async () => {
      vi.spyOn(financeApi, 'getIncomeReport').mockResolvedValue({
        period_start: '2026-10-01',
        period_end: '2026-10-26',
        total_income: 12500000,
        payment_count: 25,
        by_method: { 'Bank Jatim VA': 10000000, 'BCA Transfer': 2500000 },
        by_category: { spp: 10000000, gedung: 2500000 },
        daily: [{ date: '2026-10-15', total: 5000000, count: 10 }],
      });

      render(<FinanceReportsPage />);

      await waitFor(() => {
        expect(screen.getByText('Total Pemasukan Bersih Terverifikasi')).toBeInTheDocument();
        expect(screen.getByText('25 Transaksi')).toBeInTheDocument();
      });
    });
  });
});
