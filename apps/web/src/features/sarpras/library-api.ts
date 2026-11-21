import { apiClient } from '@/lib/api/api-client';
import { ApiResponse, PaginatedResponse } from '@/types/api';

export type BookType = 'fisik' | 'digital';
export type LoanStatus = 'dipinjam' | 'dikembalikan';
export type EquipmentCondition = 'baik' | 'rusak_ringan' | 'rusak_berat';

export interface LibraryBookItem {
  id: string;
  school_id?: string | null;
  title: string;
  author: string;
  publisher?: string | null;
  publish_year?: number | null;
  isbn?: string | null;
  category?: string | null;
  book_type: BookType;
  stock: number;
  available_stock: number;
  cover_file_id?: string | null;
  file_id?: string | null;
  location_shelf?: string | null;
  created_at: string;
  updated_at: string;
  coverFile?: { id: string; file_url?: string } | null;
  file?: { id: string; file_url?: string; original_name?: string } | null;
  loans?: LoanItem[];
}

export interface LoanItem {
  id: string;
  book_id: string;
  borrower_id: string;
  loan_date: string;
  due_date: string;
  returned_at?: string | null;
  status: LoanStatus;
  fine_amount: number;
  fine_paid: boolean;
  notes?: string | null;
  created_at: string;
  book?: LibraryBookItem | null;
  borrower?: { id: string; username: string; email?: string } | null;
}

export interface LabEquipmentItem {
  id: string;
  school_id?: string | null;
  room_id: string;
  code?: string | null;
  name: string;
  category?: string | null;
  stock: number;
  available_stock: number;
  condition: EquipmentCondition;
  notes?: string | null;
  created_at: string;
  updated_at: string;
  room?: { id: string; name: string; code: string } | null;
  loans?: LabLoanItem[];
}

export interface LabLoanItem {
  id: string;
  equipment_id: string;
  borrower_id: string;
  loan_date: string;
  due_date: string;
  quantity: number;
  returned_at?: string | null;
  status: LoanStatus;
  purpose?: string | null;
  notes?: string | null;
  created_at: string;
  equipment?: LabEquipmentItem | null;
  borrower?: { id: string; username: string; email?: string } | null;
}

export interface LibrarySummary {
  total_books: number;
  total_stock: number;
  active_loans: number;
  overdue_loans: number;
  total_fines_unpaid: number;
  category_counts: Record<string, number>;
  book_types: { fisik: number; digital: number };
}

export interface LabSummary {
  total_equipment: number;
  total_units: number;
  active_loans: number;
  overdue_loans: number;
  conditions_breakdown: Record<EquipmentCondition, number>;
  category_counts: Record<string, number>;
}

export interface ListBooksParams {
  category?: string;
  book_type?: BookType;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface CreateBookPayload {
  title: string;
  author: string;
  publisher?: string | null;
  publish_year?: number | null;
  isbn?: string | null;
  category?: string | null;
  book_type?: BookType;
  stock?: number;
  cover_file_id?: string | null;
  file_id?: string | null;
  location_shelf?: string | null;
}

export interface UpdateBookPayload {
  title?: string;
  author?: string;
  publisher?: string | null;
  publish_year?: number | null;
  isbn?: string | null;
  category?: string | null;
  book_type?: BookType;
  stock?: number;
  cover_file_id?: string | null;
  file_id?: string | null;
  location_shelf?: string | null;
}

export interface ListLoansParams {
  borrower_id?: string;
  book_id?: string;
  status?: string;
  page?: number;
  per_page?: number;
}

export interface CreateLoanPayload {
  book_id: string;
  borrower_id?: string;
  loan_date?: string;
  due_date: string;
  notes?: string | null;
}

export interface ReturnLoanPayload {
  returned_at?: string;
  fine_paid?: boolean;
  notes?: string | null;
}

export interface ListEquipmentParams {
  room_id?: string;
  category?: string;
  condition?: EquipmentCondition;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface CreateEquipmentPayload {
  room_id: string;
  code?: string | null;
  name: string;
  category?: string | null;
  stock?: number;
  condition?: EquipmentCondition;
  notes?: string | null;
}

export interface UpdateEquipmentPayload {
  room_id?: string;
  code?: string | null;
  name?: string;
  category?: string | null;
  stock?: number;
  condition?: EquipmentCondition;
  notes?: string | null;
}

export interface ListLabLoansParams {
  borrower_id?: string;
  equipment_id?: string;
  status?: string;
  page?: number;
  per_page?: number;
}

export interface CreateLabLoanPayload {
  equipment_id: string;
  borrower_id?: string;
  quantity?: number;
  loan_date?: string;
  due_date: string;
  purpose?: string | null;
  notes?: string | null;
}

export interface ReturnLabLoanPayload {
  returned_at?: string;
  notes?: string | null;
}

export const libraryApi = {
  // =========================================================================
  // 1. PERPUSTAKAAN (BE-SAR-04 / ADM-SAR-04)
  // =========================================================================
  getBooks: async (params?: ListBooksParams): Promise<PaginatedResponse<LibraryBookItem>> => {
    const res = await apiClient.get<PaginatedResponse<LibraryBookItem>>('/library/books', { params });
    return res.data;
  },

  getBook: async (id: string): Promise<ApiResponse<LibraryBookItem>> => {
    const res = await apiClient.get<ApiResponse<LibraryBookItem>>(`/library/books/${id}`);
    return res.data;
  },

  createBook: async (data: CreateBookPayload): Promise<ApiResponse<LibraryBookItem>> => {
    const res = await apiClient.post<ApiResponse<LibraryBookItem>>('/library/books', data);
    return res.data;
  },

  updateBook: async (id: string, data: UpdateBookPayload): Promise<ApiResponse<LibraryBookItem>> => {
    const res = await apiClient.put<ApiResponse<LibraryBookItem>>(`/library/books/${id}`, data);
    return res.data;
  },

  deleteBook: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/library/books/${id}`);
    return res.data;
  },

  getLibrarySummary: async (): Promise<ApiResponse<LibrarySummary>> => {
    const res = await apiClient.get<ApiResponse<LibrarySummary>>('/library/summary');
    return res.data;
  },

  getLoans: async (params?: ListLoansParams): Promise<PaginatedResponse<LoanItem>> => {
    const res = await apiClient.get<PaginatedResponse<LoanItem>>('/library/loans', { params });
    return res.data;
  },

  getLoan: async (id: string): Promise<ApiResponse<LoanItem>> => {
    const res = await apiClient.get<ApiResponse<LoanItem>>(`/library/loans/${id}`);
    return res.data;
  },

  createLoan: async (data: CreateLoanPayload): Promise<ApiResponse<LoanItem>> => {
    const res = await apiClient.post<ApiResponse<LoanItem>>('/library/loans', data);
    return res.data;
  },

  returnLoan: async (id: string, data?: ReturnLoanPayload): Promise<ApiResponse<LoanItem>> => {
    const res = await apiClient.post<ApiResponse<LoanItem>>(`/library/loans/${id}/return`, data || {});
    return res.data;
  },

  // =========================================================================
  // 2. ALAT LABORATORIUM (BE-SAR-04 / ADM-SAR-04)
  // =========================================================================
  getEquipmentList: async (params?: ListEquipmentParams): Promise<PaginatedResponse<LabEquipmentItem>> => {
    const res = await apiClient.get<PaginatedResponse<LabEquipmentItem>>('/lab-equipment', { params });
    return res.data;
  },

  getEquipment: async (id: string): Promise<ApiResponse<LabEquipmentItem>> => {
    const res = await apiClient.get<ApiResponse<LabEquipmentItem>>(`/lab-equipment/${id}`);
    return res.data;
  },

  createEquipment: async (data: CreateEquipmentPayload): Promise<ApiResponse<LabEquipmentItem>> => {
    const res = await apiClient.post<ApiResponse<LabEquipmentItem>>('/lab-equipment', data);
    return res.data;
  },

  updateEquipment: async (id: string, data: UpdateEquipmentPayload): Promise<ApiResponse<LabEquipmentItem>> => {
    const res = await apiClient.put<ApiResponse<LabEquipmentItem>>(`/lab-equipment/${id}`, data);
    return res.data;
  },

  deleteEquipment: async (id: string): Promise<ApiResponse<{ message: string }>> => {
    const res = await apiClient.delete<ApiResponse<{ message: string }>>(`/lab-equipment/${id}`);
    return res.data;
  },

  getLabSummary: async (): Promise<ApiResponse<LabSummary>> => {
    const res = await apiClient.get<ApiResponse<LabSummary>>('/lab-equipment/summary');
    return res.data;
  },

  getLabLoans: async (params?: ListLabLoansParams): Promise<PaginatedResponse<LabLoanItem>> => {
    const res = await apiClient.get<PaginatedResponse<LabLoanItem>>('/lab-loans', { params });
    return res.data;
  },

  getLabLoan: async (id: string): Promise<ApiResponse<LabLoanItem>> => {
    const res = await apiClient.get<ApiResponse<LabLoanItem>>(`/lab-loans/${id}`);
    return res.data;
  },

  createLabLoan: async (data: CreateLabLoanPayload): Promise<ApiResponse<LabLoanItem>> => {
    const res = await apiClient.post<ApiResponse<LabLoanItem>>('/lab-loans', data);
    return res.data;
  },

  returnLabLoan: async (id: string, data?: ReturnLabLoanPayload): Promise<ApiResponse<LabLoanItem>> => {
    const res = await apiClient.post<ApiResponse<LabLoanItem>>(`/lab-loans/${id}/return`, data || {});
    return res.data;
  },
};
