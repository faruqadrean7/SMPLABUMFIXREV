export type FeeCategory = 'spp' | 'gedung' | 'seragam' | 'kegiatan' | 'lainnya';

export interface FeeType {
  id: string;
  code: string;
  name: string;
  category: FeeCategory;
  default_amount: number;
  is_recurring: boolean;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface PaymentProviderSetting {
  id: string;
  name: string;
  instructions: string;
  info_url?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export type InvoiceStatus = 'belum_bayar' | 'sebagian' | 'lunas' | 'dibatalkan';

export interface InvoiceItem {
  id: string;
  invoice_id: string;
  fee_type_id: string;
  fee_type?: FeeType;
  description: string;
  amount: number;
  discount: number;
  net_amount?: number;
  created_at?: string;
}

export type PaymentStatus = 'menunggu_verifikasi' | 'terverifikasi' | 'ditolak' | 'void';

export interface Payment {
  id: string;
  invoice_id: string;
  amount: number;
  channel: string;
  external_reference: string;
  payment_date: string;
  proof_file_url?: string | null;
  status: PaymentStatus;
  receipt_number?: string | null;
  rejection_reason?: string | null;
  submitted_by?: string | null;
  verified_by?: string | null;
  verified_at?: string | null;
  created_at?: string;
  submitter?: {
    id: string;
    name: string;
    username?: string;
  } | null;
  verifier?: {
    id: string;
    name: string;
    username?: string;
  } | null;
  invoice?: Invoice;
}

export interface Invoice {
  id: string;
  invoice_number: string;
  student_id: string;
  student?: {
    id: string;
    full_name: string;
    nisn: string;
    nis?: string;
    current_class?: {
      id?: string;
      name: string;
    };
  };
  academic_year_id: string;
  period?: string | null;
  va_number?: string | null;
  due_date: string;
  total_amount: number;
  paid_amount: number;
  remaining_amount: number;
  status: InvoiceStatus;
  notes?: string | null;
  items?: InvoiceItem[];
  payments?: Payment[];
  created_at?: string;
  updated_at?: string;
}

export interface OfficialReceipt {
  receipt_number: string;
  payment_date: string;
  student_name: string;
  student_nisn: string;
  class_name: string;
  amount: number;
  terbilang: string;
  payment_method?: string;
  channel?: string;
  external_reference?: string | null;
  invoice_number: string;
  period?: string | null;
  verifier_name: string;
  verified_at: string;
  school_info: {
    name: string;
    address: string;
    phone: string;
  };
}

export interface ArrearsSummary {
  total_arrears: number;
  total_invoices_unpaid: number;
  students_in_arrears_count: number;
  by_grade: Array<{
    grade: string;
    arrears: number;
    count: number;
  }>;
  items: Invoice[];
}

export interface IncomeReportSummary {
  period_start: string;
  period_end: string;
  total_income: number;
  payment_count: number;
  by_method: Record<string, number>;
  by_category: Record<string, number>;
  daily: Array<{
    date: string;
    total: number;
    count: number;
  }>;
}
