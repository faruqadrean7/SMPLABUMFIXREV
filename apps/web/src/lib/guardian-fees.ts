import type { ChildInvoice } from './use-guardian-data';
import type { SchoolFeeItem, SchoolFeeStatus } from './mock-guardian-data';

/**
 * FE-ORTU-BIAYA-01: Pemetaan tagihan & biaya sekolah untuk portal wali murid.
 *
 * Prinsip kejujuran data (AGENTS.md §6.2 / §9):
 * - HANYA menampilkan data tagihan dan pembayaran yang nyata dari backend `GET /me/children/{child}/invoices`.
 * - DILARANG mengarang nominal (mis. 'Rp 450.000'), nomor kwitansi ('KW-TU/...'), tanggal, atau nama kasir ('Siti Rohmah').
 * - Status tagihan dipetakan secara jujur (Lunas, Belum Dibayar, Menunggu Verifikasi, Jatuh Tempo, Dibayar Sebagian, Dibatalkan).
 * - Tanpa data invoice → array kosong [] (bukan data fiktif).
 */

const MONTH_NAMES = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
];

/**
 * Format angka rupiah secara deterministik tanpa variasi ICU/locale mesin.
 * 450000 → 'Rp 450.000'
 */
export function formatRupiah(amount?: number | null): string {
  const val = typeof amount === 'number' && Number.isFinite(amount) ? Math.round(amount) : 0;
  const isNegative = val < 0;
  const formatted = Math.abs(val)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return isNegative ? `-Rp ${formatted}` : `Rp ${formatted}`;
}

/**
 * Format tanggal Indonesia dari string ISO / YYYY-MM-DD.
 * '2026-10-05' → '05 Oktober 2026'
 */
export function formatIndonesianDate(rawDate?: string | null): string {
  if (!rawDate) return '';
  const str = String(rawDate).trim();
  const datePart = str.split('T')[0].split(' ')[0];
  const parts = datePart.split('-');
  if (parts.length === 3) {
    const year = parts[0];
    const monthIdx = parseInt(parts[1], 10) - 1;
    const day = parts[2].padStart(2, '0');
    if (monthIdx >= 0 && monthIdx < 12) {
      return `${day} ${MONTH_NAMES[monthIdx]} ${year}`;
    }
  }
  const d = new Date(str);
  if (!isNaN(d.getTime())) {
    const day = String(d.getDate()).padStart(2, '0');
    const month = MONTH_NAMES[d.getMonth()];
    const year = d.getFullYear();
    return `${day} ${month} ${year}`;
  }
  return str;
}

/**
 * Format label periode tagihan secara ramah pengguna.
 * '2026-10' → 'Bulan Oktober 2026'
 */
export function formatPeriodLabel(period?: string | null, dueDate?: string | null): string {
  if (period) {
    const match = period.match(/^(\d{4})-(\d{1,2})$/);
    if (match) {
      const year = match[1];
      const monthIdx = parseInt(match[2], 10) - 1;
      if (monthIdx >= 0 && monthIdx < 12) {
        return `Bulan ${MONTH_NAMES[monthIdx]} ${year}`;
      }
    }
    return period;
  }
  if (dueDate) {
    return `Jatuh tempo: ${formatIndonesianDate(dueDate)}`;
  }
  return 'Tagihan Sekolah';
}

/**
 * Nama item tagihan dari rincian invoice items atau catatan.
 */
export function resolveItemName(invoice: ChildInvoice): string {
  if (invoice.items && Array.isArray(invoice.items) && invoice.items.length > 0) {
    const titles = invoice.items
      .map((it) => it.feeType?.name || it.fee_type?.name || it.description)
      .filter((t): t is string => Boolean(t && t.trim()));
    if (titles.length > 0) {
      return titles.join(', ');
    }
  }
  if (invoice.notes && invoice.notes.trim()) {
    return invoice.notes.trim();
  }
  return invoice.invoice_number ? `Tagihan #${invoice.invoice_number}` : 'Biaya Pendidikan';
}

/**
 * Pemetaan status tagihan jujur dari status backend + status pembayaran + due_date.
 */
export function resolveFeeStatus(
  invoice: ChildInvoice,
  referenceDate = new Date()
): SchoolFeeStatus {
  const rawStatus = (invoice.status || '').toLowerCase().trim();

  if (rawStatus === 'lunas') {
    return 'Lunas';
  }

  if (rawStatus === 'dibatalkan') {
    return 'Dibatalkan';
  }

  // Jika ada pembayaran yang masih menunggu verifikasi bendahara TU
  const payments = invoice.payments || [];
  const hasPendingVerification = payments.some(
    (p) => (p.status || '').toLowerCase().trim() === 'menunggu_verifikasi'
  );
  if (hasPendingVerification) {
    return 'Menunggu Verifikasi';
  }

  if (rawStatus === 'sebagian') {
    return 'Dibayar Sebagian';
  }

  // Cek apakah tanggal jatuh tempo telah terlewati
  if (invoice.due_date) {
    const dueDateStr = invoice.due_date.split('T')[0].split(' ')[0];
    const refDateStr = referenceDate.toISOString().split('T')[0];
    if (dueDateStr < refDateStr) {
      return 'Jatuh Tempo';
    }
  }

  return 'Belum Dibayar';
}

/**
 * Memetakan koleksi invoice anak dari backend ke array SchoolFeeItem siap tampil.
 * Mengembalikan array kosong bila data tidak ada.
 */
export function buildChildFees(
  invoices?: ChildInvoice[] | null,
  referenceDate?: Date
): SchoolFeeItem[] {
  if (!invoices || !Array.isArray(invoices) || invoices.length === 0) {
    return [];
  }

  return invoices.map((inv) => {
    const status = resolveFeeStatus(inv, referenceDate);
    const itemName = resolveItemName(inv);
    const periodLabel = formatPeriodLabel(inv.period, inv.due_date);
    const nominalText = formatRupiah(inv.total_amount);

    // Cari pembayaran yang telah terverifikasi untuk nomor kwitansi & tanggal verifikasi
    const verifiedPayment = (inv.payments || []).find(
      (p) => (p.status || '').toLowerCase().trim() === 'terverifikasi'
    );

    const receiptNumber = verifiedPayment?.receipt_number || '';
    const verifiedDate = verifiedPayment
      ? formatIndonesianDate(verifiedPayment.verified_at || verifiedPayment.payment_date)
      : '';

    return {
      id: inv.id,
      student_id: inv.student_id,
      item_name: itemName,
      period_label: periodLabel,
      nominal_text: nominalText,
      status,
      verified_date: verifiedDate,
      receipt_number: receiptNumber,
      cashier_name: '', // Tidak mengarang nama kasir
      notes: inv.notes || '',
      total_amount: inv.total_amount,
      paid_amount: inv.paid_amount,
      remaining_amount:
        inv.remaining_amount !== undefined
          ? inv.remaining_amount
          : Math.max(0, (inv.total_amount || 0) - (inv.paid_amount || 0)),
      due_date: inv.due_date,
      raw_status: inv.status,
    };
  });
}

/**
 * Menghitung status ringkas biaya siswa untuk badge navigasi portal wali.
 * Dipetakan dari daftar tagihan aktif nyata.
 */
export function resolveAdminFeeStatus(
  fees?: SchoolFeeItem[] | null
): 'Lunas / Bebas Tanggungan' | 'Menunggu Pembayaran' | 'Menunggu Verifikasi TU' {
  if (!fees || !Array.isArray(fees) || fees.length === 0) {
    return 'Lunas / Bebas Tanggungan';
  }

  const hasPending = fees.some((f) => f.status === 'Menunggu Verifikasi');
  if (hasPending) {
    return 'Menunggu Verifikasi TU';
  }

  const hasUnpaid = fees.some(
    (f) =>
      f.status === 'Belum Dibayar' ||
      f.status === 'Jatuh Tempo' ||
      f.status === 'Dibayar Sebagian'
  );
  if (hasUnpaid) {
    return 'Menunggu Pembayaran';
  }

  return 'Lunas / Bebas Tanggungan';
}

/**
 * Membangun ringkasan biaya tunggal untuk kartu profil anak tanpa karangan kasir/kwitansi.
 */
export function resolveFeeSummary(
  fees?: SchoolFeeItem[] | null
): {
  title: string;
  period: string;
  amount: string;
  status: 'Lunas' | 'Menunggu Pembayaran' | 'Verifikasi TU';
  detail_note: string;
  receipt_or_due: string;
  officer: string;
} {
  if (!fees || !Array.isArray(fees) || fees.length === 0) {
    return {
      title: 'SPP & Biaya Sekolah',
      period: '-',
      amount: 'Rp 0',
      status: 'Lunas',
      detail_note: 'Bebas Tanggungan SPP',
      receipt_or_due: '-',
      officer: '-',
    };
  }

  // Prioritas tampil: tagihan bermasalah/pending/unpaid pertama, atau tagihan terakhir
  const targetFee =
    fees.find((f) => f.status === 'Menunggu Verifikasi') ||
    fees.find(
      (f) =>
        f.status === 'Jatuh Tempo' ||
        f.status === 'Belum Dibayar' ||
        f.status === 'Dibayar Sebagian'
    ) ||
    fees[0];

  let summaryStatus: 'Lunas' | 'Menunggu Pembayaran' | 'Verifikasi TU' = 'Lunas';
  if (targetFee.status === 'Menunggu Verifikasi') {
    summaryStatus = 'Verifikasi TU';
  } else if (
    targetFee.status === 'Belum Dibayar' ||
    targetFee.status === 'Jatuh Tempo' ||
    targetFee.status === 'Dibayar Sebagian'
  ) {
    summaryStatus = 'Menunggu Pembayaran';
  }

  const receiptOrDue = targetFee.receipt_number
    ? targetFee.receipt_number
    : targetFee.due_date
    ? `Jatuh tempo: ${formatIndonesianDate(targetFee.due_date)}`
    : '-';

  return {
    title: targetFee.item_name,
    period: targetFee.period_label,
    amount: targetFee.nominal_text,
    status: summaryStatus,
    detail_note:
      targetFee.notes || (targetFee.status === 'Lunas' ? 'Lunas' : 'Belum selesai'),
    receipt_or_due: receiptOrDue,
    officer: '', // Tidak mengarang nama kasir
  };
}

