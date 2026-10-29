'use client';

import { useQuery } from '@tanstack/react-query';
import { apiClient } from './api-client';
import { ApiResponse } from '@/types/auth';

export type InvoiceStatus = 'belum_bayar' | 'sebagian' | 'lunas' | 'dibatalkan';

export interface InvoiceFeeType {
  id: string;
  code: string;
  name: string;
  category?: string;
}

export interface InvoiceItem {
  id: string;
  description: string;
  amount: number;
  discount: number;
  fee_type?: InvoiceFeeType | null;
  feeType?: InvoiceFeeType | null;
}

export interface InvoicePayment {
  id: string;
  amount: number;
  channel?: string | null;
  external_reference?: string | null;
  payment_date?: string | null;
  status: 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak' | 'void';
  receipt_number?: string | null;
  verified_at?: string | null;
  verified_by?: { id?: string; name?: string } | null;
  verifiedBy?: { id?: string; name?: string } | null;
}

export interface Invoice {
  id: string;
  invoice_number: string;
  student_id: string;
  period?: string | null;
  va_number?: string | null;
  due_date: string;
  total_amount: number;
  paid_amount: number;
  remaining_amount?: number;
  status: InvoiceStatus;
  notes?: string | null;
  items?: InvoiceItem[];
  payments?: InvoicePayment[];
}

export const INVOICE_STATUS_LABEL: Record<InvoiceStatus, string> = {
  belum_bayar: 'Belum Bayar',
  sebagian: 'Dibayar Sebagian',
  lunas: 'Lunas',
  dibatalkan: 'Dibatalkan',
};

export function formatRupiah(n: number | null | undefined): string {
  const v = typeof n === 'number' ? n : 0;
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(v);
}

export function invoiceRemaining(inv: Invoice): number {
  if (typeof inv.remaining_amount === 'number') return inv.remaining_amount;
  return Math.max(0, (inv.total_amount || 0) - (inv.paid_amount || 0));
}

export function latestReceiptNumber(inv: Invoice): string | null {
  const verified = (inv.payments || []).filter((p) => p.status === 'terverifikasi' && p.receipt_number);
  return verified.length ? verified[verified.length - 1].receipt_number ?? null : null;
}

/** GET /me/children/{childId}/invoices — tagihan anak perwalian (portal ortu). */
export function useChildInvoices(childId: string | null | undefined) {
  return useQuery({
    queryKey: ['child-invoices', childId],
    enabled: !!childId,
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<Invoice[]>>(`/me/children/${childId}/invoices`);
      return res.data.data;
    },
  });
}

/** GET /me/invoices — tagihan siswa sendiri (portal siswa). */
export function useMyInvoices() {
  return useQuery({
    queryKey: ['my-invoices'],
    queryFn: async () => {
      const res = await apiClient.get<ApiResponse<Invoice[]>>('/me/invoices');
      return res.data.data;
    },
  });
}
