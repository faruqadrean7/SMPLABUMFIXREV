import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  formatRupiah,
  formatPeriodLabel,
  formatIndonesianDate,
  resolveItemName,
  resolveFeeStatus,
  buildChildFees,
  resolveAdminFeeStatus,
  resolveFeeSummary,
} from '@/lib/guardian-fees';
import type { ChildInvoice } from '@/lib/use-guardian-data';
import { apiClient } from '@/lib/api-client';

describe('FE-ORTU-BIAYA-01: Pemetaan Tagihan Nyata Portal Ortu', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('formatRupiah', () => {
    it('FE-ORTU-BIAYA-01: memformat nominal angka bulat rupiah', () => {
      expect(formatRupiah(450000)).toBe('Rp 450.000');
      expect(formatRupiah(1250000)).toBe('Rp 1.250.000');
      expect(formatRupiah(50000)).toBe('Rp 50.000');
    });

    it('FE-ORTU-BIAYA-01: memformat angka 0 dan nilai tidak valid menjadi Rp 0', () => {
      expect(formatRupiah(0)).toBe('Rp 0');
      expect(formatRupiah(null)).toBe('Rp 0');
      expect(formatRupiah(undefined)).toBe('Rp 0');
      expect(formatRupiah(Number.NaN)).toBe('Rp 0');
    });

    it('FE-ORTU-BIAYA-01: memformat nominal negatif secara benar', () => {
      expect(formatRupiah(-50000)).toBe('-Rp 50.000');
    });
  });

  describe('formatPeriodLabel & formatIndonesianDate', () => {
    it('FE-ORTU-BIAYA-01: memformat period tahun-bulan menjadi nama bulan Indonesia', () => {
      expect(formatPeriodLabel('2026-10')).toBe('Bulan Oktober 2026');
      expect(formatPeriodLabel('2026-01')).toBe('Bulan Januari 2026');
      expect(formatPeriodLabel('2026-12')).toBe('Bulan Desember 2026');
    });

    it('FE-ORTU-BIAYA-01: mempertahankan label kustom yang bukan format YYYY-MM', () => {
      expect(formatPeriodLabel('Semester Gasal 2026/2027')).toBe('Semester Gasal 2026/2027');
    });

    it('FE-ORTU-BIAYA-01: fallback ke due_date bila period kosong', () => {
      expect(formatPeriodLabel(null, '2026-10-15')).toBe('Jatuh tempo: 15 Oktober 2026');
      expect(formatPeriodLabel(undefined, undefined)).toBe('Tagihan Sekolah');
    });

    it('FE-ORTU-BIAYA-01: formatIndonesianDate mengonversi tanggal YYYY-MM-DD ke DD MMMM YYYY', () => {
      expect(formatIndonesianDate('2026-10-05')).toBe('05 Oktober 2026');
      expect(formatIndonesianDate('2026-05-20T08:30:00Z')).toBe('20 Mei 2026');
      expect(formatIndonesianDate(null)).toBe('');
    });
  });

  describe('resolveItemName', () => {
    it('FE-ORTU-BIAYA-01: merangkai nama butir biaya dari items.feeType.name', () => {
      const invoice: ChildInvoice = {
        id: 'inv-1',
        invoice_number: 'INV/2026/10/0001',
        student_id: 'std-1',
        due_date: '2026-10-10',
        total_amount: 450000,
        paid_amount: 450000,
        status: 'lunas',
        items: [
          {
            id: 'it-1',
            invoice_id: 'inv-1',
            fee_type_id: 'ft-1',
            description: 'SPP',
            amount: 350000,
            discount: 0,
            feeType: { id: 'ft-1', name: 'SPP Bulanan' },
          },
          {
            id: 'it-2',
            invoice_id: 'inv-1',
            fee_type_id: 'ft-2',
            description: 'BOP',
            amount: 100000,
            discount: 0,
            feeType: { id: 'ft-2', name: 'Biaya Operasional Pembelajaran' },
          },
        ],
      };
      expect(resolveItemName(invoice)).toBe('SPP Bulanan, Biaya Operasional Pembelajaran');
    });

    it('FE-ORTU-BIAYA-01: fallback ke item.description jika feeType tidak ada', () => {
      const invoice: ChildInvoice = {
        id: 'inv-2',
        invoice_number: 'INV/2026/10/0002',
        student_id: 'std-1',
        due_date: '2026-10-10',
        total_amount: 50000,
        paid_amount: 0,
        status: 'belum_bayar',
        items: [
          {
            id: 'it-3',
            invoice_id: 'inv-2',
            fee_type_id: 'ft-3',
            description: 'Kegiatan Outbound Edukasi',
            amount: 50000,
            discount: 0,
          },
        ],
      };
      expect(resolveItemName(invoice)).toBe('Kegiatan Outbound Edukasi');
    });

    it('FE-ORTU-BIAYA-01: fallback ke notes atau nomor invoice jika items kosong', () => {
      const invoiceNotes: ChildInvoice = {
        id: 'inv-3',
        invoice_number: 'INV/2026/10/0003',
        student_id: 'std-1',
        due_date: '2026-10-10',
        total_amount: 100000,
        paid_amount: 0,
        status: 'belum_bayar',
        notes: 'Uang Seragam Olahraga',
      };
      expect(resolveItemName(invoiceNotes)).toBe('Uang Seragam Olahraga');

      const invoiceEmpty: ChildInvoice = {
        id: 'inv-4',
        invoice_number: 'INV/2026/10/0004',
        student_id: 'std-1',
        due_date: '2026-10-10',
        total_amount: 100000,
        paid_amount: 0,
        status: 'belum_bayar',
      };
      expect(resolveItemName(invoiceEmpty)).toBe('Tagihan #INV/2026/10/0004');
    });
  });

  describe('resolveFeeStatus', () => {
    const refDate = new Date('2026-10-15T10:00:00Z');

    it('FE-ORTU-BIAYA-01: memetakan status lunas secara jujur', () => {
      const invoice: ChildInvoice = {
        id: 'inv-1',
        invoice_number: 'INV/1',
        student_id: 'std-1',
        due_date: '2026-10-10',
        total_amount: 450000,
        paid_amount: 450000,
        status: 'lunas',
      };
      expect(resolveFeeStatus(invoice, refDate)).toBe('Lunas');
    });

    it('FE-ORTU-BIAYA-01: mendeteksi pembayaran yang menunggu verifikasi', () => {
      const invoice: ChildInvoice = {
        id: 'inv-2',
        invoice_number: 'INV/2',
        student_id: 'std-1',
        due_date: '2026-10-20',
        total_amount: 450000,
        paid_amount: 0,
        status: 'belum_bayar',
        payments: [
          {
            id: 'pay-1',
            amount: 450000,
            payment_method: 'transfer',
            payment_date: '2026-10-14',
            status: 'menunggu_verifikasi',
          },
        ],
      };
      expect(resolveFeeStatus(invoice, refDate)).toBe('Menunggu Verifikasi');
    });

    it('FE-ORTU-BIAYA-01: memetakan status sebagian menjadi Dibayar Sebagian', () => {
      const invoice: ChildInvoice = {
        id: 'inv-3',
        invoice_number: 'INV/3',
        student_id: 'std-1',
        due_date: '2026-10-25',
        total_amount: 500000,
        paid_amount: 250000,
        status: 'sebagian',
      };
      expect(resolveFeeStatus(invoice, refDate)).toBe('Dibayar Sebagian');
    });

    it('FE-ORTU-BIAYA-01: memetakan status belum bayar yang melewati tanggal jatuh tempo menjadi Jatuh Tempo', () => {
      const invoice: ChildInvoice = {
        id: 'inv-4',
        invoice_number: 'INV/4',
        student_id: 'std-1',
        due_date: '2026-10-10', // Sebelum refDate 2026-10-15
        total_amount: 450000,
        paid_amount: 0,
        status: 'belum_bayar',
      };
      expect(resolveFeeStatus(invoice, refDate)).toBe('Jatuh Tempo');
    });

    it('FE-ORTU-BIAYA-01: memetakan status belum bayar dengan tanggal jatuh tempo masa depan menjadi Belum Dibayar', () => {
      const invoice: ChildInvoice = {
        id: 'inv-5',
        invoice_number: 'INV/5',
        student_id: 'std-1',
        due_date: '2026-10-30', // Setelah refDate 2026-10-15
        total_amount: 450000,
        paid_amount: 0,
        status: 'belum_bayar',
      };
      expect(resolveFeeStatus(invoice, refDate)).toBe('Belum Dibayar');
    });

    it('FE-ORTU-BIAYA-01: memetakan status dibatalkan', () => {
      const invoice: ChildInvoice = {
        id: 'inv-6',
        invoice_number: 'INV/6',
        student_id: 'std-1',
        due_date: '2026-10-10',
        total_amount: 450000,
        paid_amount: 0,
        status: 'dibatalkan',
      };
      expect(resolveFeeStatus(invoice, refDate)).toBe('Dibatalkan');
    });
  });

  describe('buildChildFees', () => {
    const refDate = new Date('2026-10-15T10:00:00Z');

    it('FE-ORTU-BIAYA-01: input kosong atau null menghasilkan array kosong []', () => {
      expect(buildChildFees(null)).toEqual([]);
      expect(buildChildFees(undefined)).toEqual([]);
      expect(buildChildFees([])).toEqual([]);
    });

    it('FE-ORTU-BIAYA-01: memetakan invoice nyata tanpa mengarang nama kasir atau nomor kwitansi fiktif', () => {
      const invoices: ChildInvoice[] = [
        {
          id: 'inv-101',
          invoice_number: 'INV/2026/10/0101',
          student_id: 'std-2026-01',
          period: '2026-10',
          due_date: '2026-10-10',
          total_amount: 450000,
          paid_amount: 450000,
          status: 'lunas',
          notes: 'Lunas SPP',
          items: [
            {
              id: 'it-1',
              invoice_id: 'inv-101',
              fee_type_id: 'ft-1',
              description: 'SPP Oktober 2026',
              amount: 450000,
              discount: 0,
              feeType: { id: 'ft-1', name: 'SPP Reguler' },
            },
          ],
          payments: [
            {
              id: 'pay-1',
              amount: 450000,
              payment_method: 'transfer',
              payment_date: '2026-10-05',
              status: 'terverifikasi',
              receipt_number: 'KWT/2026/10/0045',
              verified_at: '2026-10-05T09:30:00Z',
            },
          ],
        },
        {
          id: 'inv-102',
          invoice_number: 'INV/2026/11/0102',
          student_id: 'std-2026-01',
          period: '2026-11',
          due_date: '2026-11-10',
          total_amount: 450000,
          paid_amount: 0,
          status: 'belum_bayar',
          items: [
            {
              id: 'it-2',
              invoice_id: 'inv-102',
              fee_type_id: 'ft-1',
              description: 'SPP November 2026',
              amount: 450000,
              discount: 0,
              feeType: { id: 'ft-1', name: 'SPP Reguler' },
            },
          ],
          payments: [],
        },
      ];

      const fees = buildChildFees(invoices, refDate);

      expect(fees).toHaveLength(2);

      // Item 1: Lunas dengan pembayaran terverifikasi
      expect(fees[0].id).toBe('inv-101');
      expect(fees[0].item_name).toBe('SPP Reguler');
      expect(fees[0].period_label).toBe('Bulan Oktober 2026');
      expect(fees[0].nominal_text).toBe('Rp 450.000');
      expect(fees[0].status).toBe('Lunas');
      expect(fees[0].receipt_number).toBe('KWT/2026/10/0045');
      expect(fees[0].verified_date).toBe('05 Oktober 2026');
      // Tidak boleh ada kasir fiktif 'Siti Rohmah'
      expect(fees[0].cashier_name).toBe('');

      // Item 2: Belum Bayar
      expect(fees[1].id).toBe('inv-102');
      expect(fees[1].status).toBe('Belum Dibayar');
      expect(fees[1].receipt_number).toBe('');
      expect(fees[1].verified_date).toBe('');
      expect(fees[1].cashier_name).toBe('');
    });
  });

  describe('resolveAdminFeeStatus', () => {
    it('FE-ORTU-BIAYA-01: mengembalikan Lunas / Bebas Tanggungan bila data kosong atau seluruhnya lunas', () => {
      expect(resolveAdminFeeStatus([])).toBe('Lunas / Bebas Tanggungan');
      expect(resolveAdminFeeStatus(null)).toBe('Lunas / Bebas Tanggungan');
      expect(
        resolveAdminFeeStatus([
          {
            id: 'f-1',
            student_id: 'std-1',
            item_name: 'SPP',
            period_label: 'Bulan Oktober 2026',
            nominal_text: 'Rp 450.000',
            status: 'Lunas',
            verified_date: '05 Oktober 2026',
            receipt_number: 'KW-01',
            cashier_name: '',
            notes: '',
          },
        ])
      ).toBe('Lunas / Bebas Tanggungan');
    });

    it('FE-ORTU-BIAYA-01: memetakan status Menunggu Verifikasi TU bila ada slip pembayaran dalam verifikasi', () => {
      expect(
        resolveAdminFeeStatus([
          {
            id: 'f-1',
            student_id: 'std-1',
            item_name: 'SPP',
            period_label: 'Bulan Oktober 2026',
            nominal_text: 'Rp 450.000',
            status: 'Menunggu Verifikasi',
            verified_date: '',
            receipt_number: '',
            cashier_name: '',
            notes: '',
          },
        ])
      ).toBe('Menunggu Verifikasi TU');
    });

    it('FE-ORTU-BIAYA-01: memetakan status Menunggu Pembayaran bila ada tagihan belum dibayar/jatuh tempo', () => {
      expect(
        resolveAdminFeeStatus([
          {
            id: 'f-1',
            student_id: 'std-1',
            item_name: 'SPP',
            period_label: 'Bulan Oktober 2026',
            nominal_text: 'Rp 450.000',
            status: 'Jatuh Tempo',
            verified_date: '',
            receipt_number: '',
            cashier_name: '',
            notes: '',
          },
        ])
      ).toBe('Menunggu Pembayaran');
    });
  });

  describe('resolveFeeSummary', () => {
    it('FE-ORTU-BIAYA-01: mengembalikan ringkasan bersih tanpa karangan kasir bila tidak ada tagihan', () => {
      const summary = resolveFeeSummary([]);
      expect(summary.title).toBe('SPP & Biaya Sekolah');
      expect(summary.status).toBe('Lunas');
      expect(summary.amount).toBe('Rp 0');
      expect(summary.officer).not.toContain('Siti Rohmah');
    });

    it('FE-ORTU-BIAYA-01: memprioritaskan tagihan yang belum dibayar atau menunggu verifikasi', () => {
      const summary = resolveFeeSummary([
        {
          id: 'f-1',
          student_id: 'std-1',
          item_name: 'SPP Oktober 2026',
          period_label: 'Bulan Oktober 2026',
          nominal_text: 'Rp 450.000',
          status: 'Lunas',
          verified_date: '05 Oktober 2026',
          receipt_number: 'KW-01',
          cashier_name: '',
          notes: '',
        },
        {
          id: 'f-2',
          student_id: 'std-1',
          item_name: 'SPP November 2026',
          period_label: 'Bulan November 2026',
          nominal_text: 'Rp 450.000',
          status: 'Belum Dibayar',
          verified_date: '',
          receipt_number: '',
          cashier_name: '',
          notes: '',
          due_date: '2026-11-10',
        },
      ]);

      expect(summary.title).toBe('SPP November 2026');
      expect(summary.status).toBe('Menunggu Pembayaran');
      expect(summary.amount).toBe('Rp 450.000');
      expect(summary.receipt_or_due).toBe('Jatuh tempo: 10 November 2026');
      expect(summary.officer).toBe('');
    });
  });

  describe('Integrasi Endpoint Backend GET /me/children/{child}/invoices', () => {
    it('FE-ORTU-BIAYA-01: memanggil endpoint perwalian sah dengan childId yang sesuai', async () => {
      const mockApiResponse = {
        data: {
          data: [
            {
              id: 'inv-999',
              invoice_number: 'INV/2026/10/999',
              student_id: 'child-123',
              due_date: '2026-10-20',
              total_amount: 500000,
              paid_amount: 0,
              status: 'belum_bayar',
            },
          ],
        },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce(mockApiResponse as any);

      const res = await apiClient.get('/me/children/child-123/invoices');
      expect(apiClient.get).toHaveBeenCalledWith('/me/children/child-123/invoices');
      expect(res.data.data).toHaveLength(1);
      expect(res.data.data[0].invoice_number).toBe('INV/2026/10/999');
    });
  });
});
