import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';
import {
  FeeType,
  PaymentProviderSetting,
  Invoice,
  Payment,
  OfficialReceipt,
  ArrearsSummary,
  IncomeReportSummary,
} from '@/types/finance';

/**
 * Format nominal rupiah bulat standar Indonesia (id-ID)
 */
export function formatRupiah(amount: number): string {
  if (isNaN(amount) || amount === null || amount === undefined) {
    return 'Rp 0';
  }
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

/**
 * Validasi berkas bukti transfer / slip di sisi klien
 * Syarat: Tipe berkas jpg, jpeg, png, pdf; Ukuran maksimum 2MB (2 * 1024 * 1024 bytes)
 */
export function validateProofFile(file: File): { valid: boolean; error?: string } {
  const MAX_SIZE = 2 * 1024 * 1024; // 2 MB
  const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'image/webp'];

  if (!ALLOWED_TYPES.includes(file.type)) {
    return {
      valid: false,
      error: 'Format file tidak didukung. Harap unggah berkas JPG, PNG, atau PDF.',
    };
  }

  if (file.size > MAX_SIZE) {
    return {
      valid: false,
      error: 'Ukuran file melebihi batas maksimal 2MB.',
    };
  }

  return { valid: true };
}

export const financeApi = {
  // =========================================================================
  // 1. MASTER JENIS BIAYA (Admin)
  // =========================================================================
  getFeeTypes: async (params?: { category?: string; is_active?: boolean }): Promise<FeeType[]> => {
    const res = await apiClient.get<ApiResponse<FeeType[]>>('/finance/fee-types', { params });
    return res.data.data;
  },

  createFeeType: async (payload: {
    code: string;
    name: string;
    category: string;
    default_amount: number;
    is_recurring: boolean;
    is_active?: boolean;
  }): Promise<FeeType> => {
    const res = await apiClient.post<ApiResponse<FeeType>>('/finance/fee-types', payload);
    return res.data.data;
  },

  updateFeeType: async (
    id: string,
    payload: Partial<{
      code: string;
      name: string;
      category: string;
      default_amount: number;
      is_recurring: boolean;
      is_active: boolean;
    }>
  ): Promise<FeeType> => {
    const res = await apiClient.put<ApiResponse<FeeType>>(`/finance/fee-types/${id}`, payload);
    return res.data.data;
  },

  deleteFeeType: async (id: string): Promise<void> => {
    await apiClient.delete(`/finance/fee-types/${id}`);
  },

  // =========================================================================
  // 2. PENGATURAN PENYEDIA PEMBAYARAN PIHAK KETIGA (Admin & Portal)
  // =========================================================================
  getPaymentProviders: async (params?: { is_active?: boolean }): Promise<PaymentProviderSetting[]> => {
    const res = await apiClient.get<ApiResponse<PaymentProviderSetting[]>>('/finance/settings/providers', { params });
    return res.data.data;
  },

  createPaymentProvider: async (payload: {
    name: string;
    instructions: string;
    info_url?: string;
    is_active?: boolean;
  }): Promise<PaymentProviderSetting> => {
    const res = await apiClient.post<ApiResponse<PaymentProviderSetting>>('/finance/settings/providers', payload);
    return res.data.data;
  },

  updatePaymentProvider: async (
    id: string,
    payload: Partial<{
      name: string;
      instructions: string;
      info_url?: string;
      is_active: boolean;
    }>
  ): Promise<PaymentProviderSetting> => {
    const res = await apiClient.put<ApiResponse<PaymentProviderSetting>>(`/finance/settings/providers/${id}`, payload);
    return res.data.data;
  },

  deletePaymentProvider: async (id: string): Promise<void> => {
    await apiClient.delete(`/finance/settings/providers/${id}`);
  },

  getPortalPaymentProviders: async (): Promise<PaymentProviderSetting[]> => {
    const res = await apiClient.get<ApiResponse<PaymentProviderSetting[]>>('/me/payment-providers');
    return res.data.data;
  },

  // =========================================================================
  // 3. TAGIHAN / INVOICES (Admin)
  // =========================================================================
  getInvoices: async (params?: {
    class_id?: string;
    status?: string;
    period?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<Invoice>> => {
    const res = await apiClient.get<PaginatedResponse<Invoice>>('/finance/invoices', { params });
    return res.data;
  },

  getInvoice: async (id: string): Promise<Invoice> => {
    const res = await apiClient.get<ApiResponse<Invoice>>(`/finance/invoices/${id}`);
    return res.data.data;
  },

  createInvoice: async (payload: {
    student_id: string;
    academic_year_id: string;
    period?: string;
    va_number?: string;
    due_date: string;
    notes?: string;
    items: Array<{
      fee_type_id: string;
      description: string;
      amount: number;
      discount?: number;
    }>;
  }): Promise<Invoice> => {
    const res = await apiClient.post<ApiResponse<Invoice>>('/finance/invoices', payload);
    return res.data.data;
  },

  generateBulkInvoices: async (payload: {
    academic_year_id: string;
    fee_type_id: string;
    period?: string;
    due_date: string;
    class_id?: string;
  }): Promise<{ created_count: number; skipped_count: number }> => {
    const res = await apiClient.post<ApiResponse<{ created_count: number; skipped_count: number }>>(
      '/finance/invoices/generate-bulk',
      payload
    );
    return res.data.data;
  },

  cancelInvoice: async (id: string, notes?: string): Promise<Invoice> => {
    const res = await apiClient.post<ApiResponse<Invoice>>(`/finance/invoices/${id}/cancel`, { notes });
    return res.data.data;
  },

  // =========================================================================
  // 4. PEMBAYARAN & VERIFIKASI SLIP (Admin)
  // =========================================================================
  getPayments: async (params?: {
    status?: string;
    channel?: string;
    external_reference?: string;
    class_id?: string;
    date?: string;
    search?: string;
    invoice_id?: string;
    page?: number;
  }): Promise<PaginatedResponse<Payment>> => {
    const res = await apiClient.get<PaginatedResponse<Payment>>('/finance/payments', { params });
    return res.data;
  },

  verifyPayment: async (paymentId: string, notes?: string): Promise<Payment> => {
    const res = await apiClient.post<ApiResponse<Payment>>(`/finance/payments/${paymentId}/verify`, { notes });
    return res.data.data;
  },

  rejectPayment: async (paymentId: string, reason: string): Promise<Payment> => {
    const res = await apiClient.post<ApiResponse<Payment>>(`/finance/payments/${paymentId}/reject`, {
      reason,
    });
    return res.data.data;
  },

  voidPayment: async (paymentId: string, voidReason: string): Promise<Payment> => {
    const res = await apiClient.post<ApiResponse<Payment>>(`/finance/payments/${paymentId}/void`, {
      reason: voidReason,
    });
    return res.data.data;
  },

  getPaymentReceipt: async (paymentId: string): Promise<OfficialReceipt> => {
    const res = await apiClient.get<ApiResponse<OfficialReceipt>>(`/finance/payments/${paymentId}/receipt`);
    return res.data.data;
  },

  // =========================================================================
  // 5. REKAP LAPORAN (Admin)
  // =========================================================================
  getArrearsReport: async (params?: { class_id?: string }): Promise<ArrearsSummary> => {
    const res = await apiClient.get<ApiResponse<ArrearsSummary>>('/finance/reports/arrears', { params });
    return res.data.data;
  },

  getIncomeReport: async (params?: { start_date?: string; end_date?: string }): Promise<IncomeReportSummary> => {
    const res = await apiClient.get<ApiResponse<IncomeReportSummary>>('/finance/reports/income', { params });
    return res.data.data;
  },

  exportCsvUrl: (type: 'arrears' | 'income', params?: { start_date?: string; end_date?: string; class_id?: string }) => {
    const baseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api/v1';
    const query = new URLSearchParams({ type, ...(params as Record<string, string>) });
    return `${baseUrl}/finance/reports/export-csv?${query.toString()}`;
  },

  // =========================================================================
  // 6. PORTAL ENDPOINTS (Siswa & Ortu)
  // =========================================================================
  getMyInvoices: async (): Promise<Invoice[]> => {
    const res = await apiClient.get<ApiResponse<Invoice[]>>('/me/invoices');
    return res.data.data;
  },

  getChildInvoices: async (childId: string): Promise<Invoice[]> => {
    const res = await apiClient.get<ApiResponse<Invoice[]>>(`/me/children/${childId}/invoices`);
    return res.data.data;
  },

  getPortalInvoice: async (id: string): Promise<Invoice> => {
    const res = await apiClient.get<ApiResponse<Invoice>>(`/me/invoices/${id}`);
    return res.data.data;
  },

  submitPayment: async (
    invoiceId: string,
    payload: {
      amount: number;
      channel: string;
      external_reference: string;
      payment_date: string;
      proof_file: File;
    }
  ): Promise<Payment> => {
    const formData = new FormData();
    formData.append('amount', String(payload.amount));
    formData.append('channel', payload.channel);
    formData.append('external_reference', payload.external_reference);
    formData.append('payment_date', payload.payment_date);
    formData.append('proof_file', payload.proof_file);

    const res = await apiClient.post<ApiResponse<Payment>>(
      `/me/invoices/${invoiceId}/payments`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );
    return res.data.data;
  },

  getPortalPaymentReceipt: async (paymentId: string): Promise<OfficialReceipt> => {
    const res = await apiClient.get<ApiResponse<OfficialReceipt>>(`/me/payments/${paymentId}/receipt`);
    return res.data.data;
  },
};
