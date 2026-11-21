import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  libraryApi,
  LibraryBookItem,
  LoanItem,
  LabEquipmentItem,
  LabLoanItem,
  LibrarySummary,
  LabSummary,
  CreateBookPayload,
  UpdateBookPayload,
  CreateLoanPayload,
  CreateEquipmentPayload,
  UpdateEquipmentPayload,
  CreateLabLoanPayload,
} from '@/features/sarpras/library-api';
import { apiClient } from '@/lib/api/api-client';

describe('BE-SAR-04 / ADM-SAR-04: Perpustakaan & Inventaris Alat Laboratorium', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. KATALOG BUKU PERPUSTAKAAN & FILTER
  // =========================================================================
  describe('1. Katalog Buku Perpustakaan', () => {
    it('mengambil daftar buku perpustakaan dengan filter kategori, jenis, dan kata kunci', async () => {
      const mockBooks: LibraryBookItem[] = [
        {
          id: 'b-1',
          school_id: 'school-1',
          title: 'Fisika Modern',
          author: 'Kenneth Krane',
          publisher: 'UI Press',
          publish_year: 2020,
          isbn: '978-979-456-111-2',
          category: 'Sains',
          book_type: 'fisik',
          stock: 4,
          available_stock: 3,
          location_shelf: 'Rak B-02',
          created_at: '2026-01-10T08:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
        },
        {
          id: 'b-2',
          school_id: 'school-1',
          title: 'Algoritma Pemrograman Digital',
          author: 'Rinaldi Munir',
          category: 'Komputer',
          book_type: 'digital',
          stock: 1,
          available_stock: 999,
          created_at: '2026-02-15T08:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockBooks,
          meta: { page: 1, limit: 15, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await libraryApi.getBooks({
        category: 'Sains',
        book_type: 'fisik',
        search: 'Krane',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/library/books', {
        params: {
          category: 'Sains',
          book_type: 'fisik',
          search: 'Krane',
        },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].title).toBe('Fisika Modern');
      expect(res.data[0].available_stock).toBe(3);
    });

    it('mengambil detail satu buku beserta riwayat peminjamannya', async () => {
      const mockDetail: LibraryBookItem = {
        id: 'b-1',
        title: 'Fisika Modern',
        author: 'Kenneth Krane',
        book_type: 'fisik',
        stock: 4,
        available_stock: 3,
        created_at: '2026-01-10T08:00:00Z',
        updated_at: '2026-10-14T08:00:00Z',
        loans: [
          {
            id: 'loan-1',
            book_id: 'b-1',
            borrower_id: 'u-siswa-1',
            loan_date: '2026-10-10',
            due_date: '2026-10-17',
            status: 'dipinjam',
            fine_amount: 0,
            fine_paid: false,
            created_at: '2026-10-10T08:00:00Z',
            borrower: { id: 'u-siswa-1', username: 'budi_siswa' },
          },
        ],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockDetail },
      } as any);

      const res = await libraryApi.getBook('b-1');

      expect(apiClient.get).toHaveBeenCalledWith('/library/books/b-1');
      expect(res.data.id).toBe('b-1');
      expect(res.data.loans).toHaveLength(1);
    });

    it('menambahkan buku baru ke katalog perpustakaan', async () => {
      const payload: CreateBookPayload = {
        title: 'Biologi Sel Edisi Revisi',
        author: 'Campbell',
        category: 'Biologi',
        book_type: 'fisik',
        stock: 5,
        isbn: '978-602-1234-99',
        location_shelf: 'Rak C-01',
      };

      const mockCreated: LibraryBookItem = {
        id: 'b-new-1',
        ...payload,
        book_type: payload.book_type || 'fisik',
        stock: payload.stock || 1,
        available_stock: payload.stock || 1,
        created_at: '2026-10-14T08:00:00Z',
        updated_at: '2026-10-14T08:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockCreated },
      } as any);

      const res = await libraryApi.createBook(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/library/books', payload);
      expect(res.data.id).toBe('b-new-1');
      expect(res.data.title).toBe('Biologi Sel Edisi Revisi');
    });

    it('memperbarui informasi buku', async () => {
      const payload: UpdateBookPayload = {
        stock: 8,
        location_shelf: 'Rak C-05',
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'b-1',
            title: 'Biologi Sel Edisi Revisi',
            author: 'Campbell',
            stock: 8,
            location_shelf: 'Rak C-05',
          },
        },
      } as any);

      const res = await libraryApi.updateBook('b-1', payload);

      expect(apiClient.put).toHaveBeenCalledWith('/library/books/b-1', payload);
      expect(res.data.stock).toBe(8);
      expect(res.data.location_shelf).toBe('Rak C-05');
    });

    it('menghapus buku dari katalog', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Buku berhasil dihapus.' } },
      } as any);

      const res = await libraryApi.deleteBook('b-del-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/library/books/b-del-1');
      expect(res.data.message).toBe('Buku berhasil dihapus.');
    });

    it('mengambil ringkasan metrik dashboard perpustakaan', async () => {
      const mockSummary: LibrarySummary = {
        total_books: 120,
        total_stock: 450,
        active_loans: 32,
        overdue_loans: 4,
        total_fines_unpaid: 15000,
        category_counts: { Sains: 45, Sastra: 30, Komputer: 25 },
        book_types: { fisik: 100, digital: 20 },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSummary },
      } as any);

      const res = await libraryApi.getLibrarySummary();

      expect(apiClient.get).toHaveBeenCalledWith('/library/summary');
      expect(res.data.total_books).toBe(120);
      expect(res.data.active_loans).toBe(32);
      expect(res.data.overdue_loans).toBe(4);
    });
  });

  // =========================================================================
  // 2. TRANSAKSI SIRKULASI PEMINJAMAN BUKU & DENDA
  // =========================================================================
  describe('2. Transaksi Peminjaman Buku', () => {
    it('mengambil daftar transaksi peminjaman buku dengan filter status', async () => {
      const mockLoans: LoanItem[] = [
        {
          id: 'loan-1',
          book_id: 'b-1',
          borrower_id: 'u-1',
          loan_date: '2026-10-01',
          due_date: '2026-10-08',
          status: 'dipinjam',
          fine_amount: 0,
          fine_paid: false,
          created_at: '2026-10-01T08:00:00Z',
          book: {
            id: 'b-1',
            title: 'Fisika Dasar',
            author: 'Halliday',
            book_type: 'fisik',
            stock: 3,
            available_stock: 2,
            created_at: '2026-01-01T00:00:00Z',
            updated_at: '2026-10-01T00:00:00Z',
          },
          borrower: { id: 'u-1', username: 'siswa_dewi' },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockLoans,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await libraryApi.getLoans({ status: 'dipinjam' });

      expect(apiClient.get).toHaveBeenCalledWith('/library/loans', {
        params: { status: 'dipinjam' },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].book?.title).toBe('Fisika Dasar');
    });

    it('membuat transaksi peminjaman buku baru', async () => {
      const payload: CreateLoanPayload = {
        book_id: 'b-1',
        borrower_id: 'u-1',
        due_date: '2026-10-21',
        notes: 'Peminjaman untuk tugas kelompok',
      };

      const mockLoan: LoanItem = {
        id: 'loan-new-1',
        book_id: payload.book_id,
        borrower_id: payload.borrower_id!,
        loan_date: '2026-10-14',
        due_date: payload.due_date,
        status: 'dipinjam',
        fine_amount: 0,
        fine_paid: false,
        notes: payload.notes,
        created_at: '2026-10-14T08:30:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockLoan },
      } as any);

      const res = await libraryApi.createLoan(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/library/loans', payload);
      expect(res.data.id).toBe('loan-new-1');
      expect(res.data.status).toBe('dipinjam');
    });

    it('memproses pengembalian buku beserta pelunasan denda', async () => {
      const mockReturned: LoanItem = {
        id: 'loan-1',
        book_id: 'b-1',
        borrower_id: 'u-1',
        loan_date: '2026-10-01',
        due_date: '2026-10-08',
        returned_at: '2026-10-14',
        status: 'dikembalikan',
        fine_amount: 6000,
        fine_paid: true,
        notes: 'Keterlambatan 6 hari, denda dilunasi',
        created_at: '2026-10-01T08:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockReturned },
      } as any);

      const res = await libraryApi.returnLoan('loan-1', {
        returned_at: '2026-10-14',
        fine_paid: true,
        notes: 'Keterlambatan 6 hari, denda dilunasi',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/library/loans/loan-1/return', {
        returned_at: '2026-10-14',
        fine_paid: true,
        notes: 'Keterlambatan 6 hari, denda dilunasi',
      });
      expect(res.data.status).toBe('dikembalikan');
      expect(res.data.fine_amount).toBe(6000);
      expect(res.data.fine_paid).toBe(true);
    });
  });

  // =========================================================================
  // 3. INVENTARIS ALAT LABORATORIUM & SIRKULASI
  // =========================================================================
  describe('3. Inventaris Alat Laboratorium & Sirkulasi', () => {
    it('mengambil daftar inventaris alat laboratorium dengan filter ruangan dan kondisi', async () => {
      const mockEquipment: LabEquipmentItem[] = [
        {
          id: 'equip-1',
          school_id: 'school-1',
          room_id: 'room-lab-fis',
          code: 'LAB-FIS-001',
          name: 'Neraca Ohaus 311g',
          category: 'Pengukuran',
          stock: 4,
          available_stock: 3,
          condition: 'baik',
          notes: 'Terpasang kalibrasi tahunan',
          created_at: '2026-01-01T00:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
          room: { id: 'room-lab-fis', name: 'Laboratorium Fisika', code: 'LAB-FIS' },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockEquipment,
          meta: { page: 1, limit: 15, total: 1, totalPages: 1 },
        },
      } as any);

      const res = await libraryApi.getEquipmentList({
        room_id: 'room-lab-fis',
        condition: 'baik',
        category: 'Pengukuran',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/lab-equipment', {
        params: {
          room_id: 'room-lab-fis',
          condition: 'baik',
          category: 'Pengukuran',
        },
      });
      expect(res.data).toHaveLength(1);
      expect(res.data[0].name).toBe('Neraca Ohaus 311g');
      expect(res.data[0].condition).toBe('baik');
    });

    it('menambahkan alat laboratorium baru', async () => {
      const payload: CreateEquipmentPayload = {
        name: 'Osiloskop Digital 100MHz',
        code: 'LAB-EL-009',
        room_id: 'room-lab-el',
        category: 'Elektronika',
        stock: 2,
        condition: 'baik',
        notes: 'Disimpan di lemari A2',
      };

      const mockCreated: LabEquipmentItem = {
        id: 'equip-new-1',
        ...payload,
        stock: payload.stock || 1,
        condition: payload.condition || 'baik',
        available_stock: payload.stock || 1,
        created_at: '2026-10-14T08:00:00Z',
        updated_at: '2026-10-14T08:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockCreated },
      } as any);

      const res = await libraryApi.createEquipment(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/lab-equipment', payload);
      expect(res.data.id).toBe('equip-new-1');
      expect(res.data.name).toBe('Osiloskop Digital 100MHz');
    });

    it('memperbarui status kondisi alat laboratorium', async () => {
      const payload: UpdateEquipmentPayload = {
        condition: 'rusak_ringan',
        notes: 'Probe channel 1 putus',
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'equip-1',
            name: 'Osiloskop Digital',
            condition: 'rusak_ringan',
            notes: 'Probe channel 1 putus',
          },
        },
      } as any);

      const res = await libraryApi.updateEquipment('equip-1', payload);

      expect(apiClient.put).toHaveBeenCalledWith('/lab-equipment/equip-1', payload);
      expect(res.data.condition).toBe('rusak_ringan');
    });

    it('menghapus alat laboratorium', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Alat laboratorium berhasil dihapus.' } },
      } as any);

      const res = await libraryApi.deleteEquipment('equip-del-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/lab-equipment/equip-del-1');
      expect(res.data.message).toBe('Alat laboratorium berhasil dihapus.');
    });

    it('mengambil ringkasan metrik inventaris alat lab', async () => {
      const mockSummary: LabSummary = {
        total_equipment: 65,
        total_units: 240,
        active_loans: 12,
        overdue_loans: 1,
        conditions_breakdown: { baik: 58, rusak_ringan: 5, rusak_berat: 2 },
        category_counts: { Optik: 20, Pengukuran: 25, Kimia: 20 },
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSummary },
      } as any);

      const res = await libraryApi.getLabSummary();

      expect(apiClient.get).toHaveBeenCalledWith('/lab-equipment/summary');
      expect(res.data.total_equipment).toBe(65);
      expect(res.data.conditions_breakdown.baik).toBe(58);
    });

    it('membuat peminjaman alat lab untuk kegiatan praktikum', async () => {
      const payload: CreateLabLoanPayload = {
        equipment_id: 'equip-1',
        quantity: 2,
        due_date: '2026-10-16',
        purpose: 'Praktikum Hukum Ohm Kelas X-B',
      };

      const mockLoan: LabLoanItem = {
        id: 'lab-loan-1',
        equipment_id: payload.equipment_id,
        borrower_id: 'u-guru-1',
        quantity: 2,
        loan_date: '2026-10-14',
        due_date: payload.due_date,
        status: 'dipinjam',
        purpose: payload.purpose,
        created_at: '2026-10-14T08:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockLoan },
      } as any);

      const res = await libraryApi.createLabLoan(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/lab-loans', payload);
      expect(res.data.id).toBe('lab-loan-1');
      expect(res.data.quantity).toBe(2);
      expect(res.data.status).toBe('dipinjam');
    });

    it('memproses pengembalian alat laboratorium', async () => {
      const mockReturned: LabLoanItem = {
        id: 'lab-loan-1',
        equipment_id: 'equip-1',
        borrower_id: 'u-guru-1',
        quantity: 2,
        loan_date: '2026-10-14',
        due_date: '2026-10-16',
        returned_at: '2026-10-16',
        status: 'dikembalikan',
        created_at: '2026-10-14T08:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockReturned },
      } as any);

      const res = await libraryApi.returnLabLoan('lab-loan-1', {
        returned_at: '2026-10-16',
      });

      expect(apiClient.post).toHaveBeenCalledWith('/lab-loans/lab-loan-1/return', {
        returned_at: '2026-10-16',
      });
      expect(res.data.status).toBe('dikembalikan');
    });
  });
});
