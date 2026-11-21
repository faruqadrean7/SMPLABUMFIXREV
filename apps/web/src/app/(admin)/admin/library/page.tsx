'use client';

import React, { useEffect, useState, useCallback, useMemo } from 'react';
import {
  BookOpen,
  FlaskConical,
  Search,
  Filter,
  Plus,
  RefreshCw,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Eye,
  Edit,
  Trash2,
  Calendar,
  Clock,
  BookMarked,
  Layers,
  FileText,
  ArrowRight,
  ShieldCheck,
  Check,
  X,
  AlertCircle,
  Building2,
  Bookmark,
  Coins,
  FileUp,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  libraryApi,
  LibraryBookItem,
  LoanItem,
  LabEquipmentItem,
  LabLoanItem,
  LibrarySummary,
  LabSummary,
  BookType,
  LoanStatus,
  EquipmentCondition,
  CreateBookPayload,
  CreateEquipmentPayload,
  CreateLoanPayload,
  CreateLabLoanPayload,
} from '@/features/sarpras/library-api';
import { apiClient } from '@/lib/api/api-client';
import { useAuth } from '@/providers/auth-provider';

export default function LibraryAndLabPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<'buku' | 'pinjam_buku' | 'alat_lab' | 'pinjam_lab' | 'sirkulasi_cepat'>('buku');

  // =========================================================================
  // DATA STATES
  // =========================================================================
  const [books, setBooks] = useState<LibraryBookItem[]>([]);
  const [bookLoans, setBookLoans] = useState<LoanItem[]>([]);
  const [equipmentList, setEquipmentList] = useState<LabEquipmentItem[]>([]);
  const [labLoans, setLabLoans] = useState<LabLoanItem[]>([]);
  const [libSummary, setLibSummary] = useState<LibrarySummary | null>(null);
  const [labSummary, setLabSummary] = useState<LabSummary | null>(null);
  const [rooms, setRooms] = useState<Array<{ id: string; name: string; code: string }>>([]);

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // =========================================================================
  // FILTERS
  // =========================================================================
  const [bookSearch, setBookSearch] = useState('');
  const [bookCategoryFilter, setBookCategoryFilter] = useState('all');
  const [bookTypeFilter, setBookTypeFilter] = useState('all');

  const [bookLoanSearch, setBookLoanSearch] = useState('');
  const [bookLoanStatusFilter, setBookLoanStatusFilter] = useState('all');

  const [equipmentSearch, setEquipmentSearch] = useState('');
  const [equipmentCategoryFilter, setEquipmentCategoryFilter] = useState('all');
  const [equipmentConditionFilter, setEquipmentConditionFilter] = useState('all');

  const [labLoanStatusFilter, setLabLoanStatusFilter] = useState('all');

  // =========================================================================
  // FAST CIRCULATION SCANNER STATE
  // =========================================================================
  const [scanCode, setScanCode] = useState('');
  const [scannedResult, setScannedResult] = useState<{
    type: 'buku' | 'alat';
    book?: LibraryBookItem;
    equipment?: LabEquipmentItem;
  } | null>(null);
  const [isScanning, setIsScanning] = useState(false);
  const [scanError, setScanError] = useState<string | null>(null);

  // =========================================================================
  // MODAL STATES
  // =========================================================================
  // Modal Buku
  const [isBookModalOpen, setIsBookModalOpen] = useState(false);
  const [editingBook, setEditingBook] = useState<LibraryBookItem | null>(null);
  const [formBookTitle, setFormBookTitle] = useState('');
  const [formBookAuthor, setFormBookAuthor] = useState('');
  const [formBookPublisher, setFormBookPublisher] = useState('');
  const [formBookPublishYear, setFormBookPublishYear] = useState('');
  const [formBookIsbn, setFormBookIsbn] = useState('');
  const [formBookCategory, setFormBookCategory] = useState('');
  const [formBookType, setFormBookType] = useState<BookType>('fisik');
  const [formBookStock, setFormBookStock] = useState('1');
  const [formBookLocation, setFormBookLocation] = useState('');

  // Modal Pinjam Buku
  const [isLoanModalOpen, setIsLoanModalOpen] = useState(false);
  const [targetBook, setTargetBook] = useState<LibraryBookItem | null>(null);
  const [formBorrowerId, setFormBorrowerId] = useState('');
  const [formLoanDueDate, setFormLoanDueDate] = useState('');
  const [formLoanNotes, setFormLoanNotes] = useState('');

  // Modal Pengembalian Buku & Denda
  const [isReturnBookModalOpen, setIsReturnBookModalOpen] = useState(false);
  const [targetReturnLoan, setTargetReturnLoan] = useState<LoanItem | null>(null);
  const [formReturnDate, setFormReturnDate] = useState('');
  const [formFinePaid, setFormFinePaid] = useState(false);
  const [formReturnNotes, setFormReturnNotes] = useState('');

  // Modal Alat Lab
  const [isEquipmentModalOpen, setIsEquipmentModalOpen] = useState(false);
  const [editingEquipment, setEditingEquipment] = useState<LabEquipmentItem | null>(null);
  const [formEquipName, setFormEquipName] = useState('');
  const [formEquipCode, setFormEquipCode] = useState('');
  const [formEquipRoomId, setFormEquipRoomId] = useState('');
  const [formEquipCategory, setFormEquipCategory] = useState('');
  const [formEquipStock, setFormEquipStock] = useState('1');
  const [formEquipCondition, setFormEquipCondition] = useState<EquipmentCondition>('baik');
  const [formEquipNotes, setFormEquipNotes] = useState('');

  // Modal Pinjam Alat Lab
  const [isLabLoanModalOpen, setIsLabLoanModalOpen] = useState(false);
  const [targetEquipment, setTargetEquipment] = useState<LabEquipmentItem | null>(null);
  const [formLabBorrowerId, setFormLabBorrowerId] = useState('');
  const [formLabLoanQuantity, setFormLabLoanQuantity] = useState('1');
  const [formLabLoanDueDate, setFormLabLoanDueDate] = useState('');
  const [formLabLoanPurpose, setFormLabLoanPurpose] = useState('');
  const [formLabLoanNotes, setFormLabLoanNotes] = useState('');

  // Modal Detail
  const [detailModalItem, setDetailModalItem] = useState<{
    type: 'buku' | 'alat';
    data: LibraryBookItem | LabEquipmentItem;
  } | null>(null);

  // Modal Konfirmasi Hapus
  const [deleteConfirmation, setDeleteConfirmation] = useState<{
    type: 'buku' | 'alat';
    id: string;
    title: string;
  } | null>(null);

  // =========================================================================
  // DATA FETCHING
  // =========================================================================
  const fetchData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [bukuRes, pinjamBukuRes, equipRes, pinjamLabRes, libSumRes, labSumRes, roomsRes] = await Promise.all([
        libraryApi.getBooks({ per_page: 100 }),
        libraryApi.getLoans({ per_page: 100 }),
        libraryApi.getEquipmentList({ per_page: 100 }),
        libraryApi.getLabLoans({ per_page: 100 }),
        libraryApi.getLibrarySummary().catch(() => ({ data: null })),
        libraryApi.getLabSummary().catch(() => ({ data: null })),
        apiClient.get<any>('/rooms').catch(() => ({ data: { data: [] } })),
      ]);

      setBooks(bukuRes.data || []);
      setBookLoans(pinjamBukuRes.data || []);
      setEquipmentList(equipRes.data || []);
      setLabLoans(pinjamLabRes.data || []);
      if (libSumRes.data) setLibSummary(libSumRes.data);
      if (labSumRes.data) setLabSummary(labSumRes.data);

      const roomData = Array.isArray(roomsRes.data) ? roomsRes.data : roomsRes.data?.data || [];
      setRooms(roomData);
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal memuat data perpustakaan & alat laboratorium.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // =========================================================================
  // HANDLERS BUKU
  // =========================================================================
  const handleOpenBookModal = (book?: LibraryBookItem) => {
    if (book) {
      setEditingBook(book);
      setFormBookTitle(book.title);
      setFormBookAuthor(book.author);
      setFormBookPublisher(book.publisher || '');
      setFormBookPublishYear(book.publish_year ? String(book.publish_year) : '');
      setFormBookIsbn(book.isbn || '');
      setFormBookCategory(book.category || '');
      setFormBookType(book.book_type);
      setFormBookStock(String(book.stock));
      setFormBookLocation(book.location_shelf || '');
    } else {
      setEditingBook(null);
      setFormBookTitle('');
      setFormBookAuthor('');
      setFormBookPublisher('');
      setFormBookPublishYear('');
      setFormBookIsbn('');
      setFormBookCategory('');
      setFormBookType('fisik');
      setFormBookStock('1');
      setFormBookLocation('');
    }
    setIsBookModalOpen(true);
  };

  const handleSaveBook = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formBookTitle.trim() || !formBookAuthor.trim()) {
      setError('Judul dan pengarang buku wajib diisi.');
      return;
    }

    try {
      const payload: CreateBookPayload = {
        title: formBookTitle.trim(),
        author: formBookAuthor.trim(),
        publisher: formBookPublisher.trim() || null,
        publish_year: formBookPublishYear ? parseInt(formBookPublishYear, 10) : null,
        isbn: formBookIsbn.trim() || null,
        category: formBookCategory.trim() || null,
        book_type: formBookType,
        stock: parseInt(formBookStock, 10) || 1,
        location_shelf: formBookLocation.trim() || null,
      };

      if (editingBook) {
        await libraryApi.updateBook(editingBook.id, payload);
        setSuccessMessage(`Buku "${payload.title}" berhasil diperbarui.`);
      } else {
        await libraryApi.createBook(payload);
        setSuccessMessage(`Buku "${payload.title}" berhasil ditambahkan ke katalog.`);
      }

      setIsBookModalOpen(false);
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal menyimpan buku.');
    }
  };

  const handleDeleteBook = async (id: string) => {
    try {
      await libraryApi.deleteBook(id);
      setSuccessMessage('Buku berhasil dihapus dari katalog.');
      setDeleteConfirmation(null);
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal menghapus buku.');
    }
  };

  // =========================================================================
  // HANDLERS PINJAM BUKU
  // =========================================================================
  const handleOpenLoanModal = (book: LibraryBookItem) => {
    setTargetBook(book);
    setFormBorrowerId('');
    // Default jatuh tempo 7 hari ke depan
    const defaultDue = new Date();
    defaultDue.setDate(defaultDue.getDate() + 7);
    setFormLoanDueDate(defaultDue.toISOString().split('T')[0]);
    setFormLoanNotes('');
    setIsLoanModalOpen(true);
  };

  const handleSaveLoan = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!targetBook) return;
    if (!formLoanDueDate) {
      setError('Tanggal jatuh tempo wajib ditentukan.');
      return;
    }

    try {
      const payload: CreateLoanPayload = {
        book_id: targetBook.id,
        borrower_id: formBorrowerId.trim() || undefined,
        due_date: formLoanDueDate,
        notes: formLoanNotes.trim() || null,
      };

      await libraryApi.createLoan(payload);
      setSuccessMessage(`Peminjaman buku "${targetBook.title}" berhasil dicatat.`);
      setIsLoanModalOpen(false);
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal mencatat peminjaman buku.');
    }
  };

  const handleOpenReturnBookModal = (loan: LoanItem) => {
    setTargetReturnLoan(loan);
    setFormReturnDate(new Date().toISOString().split('T')[0]);
    setFormFinePaid(true);
    setFormReturnNotes(loan.notes || '');
    setIsReturnBookModalOpen(true);
  };

  const handleProcessReturnBook = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!targetReturnLoan) return;

    try {
      await libraryApi.returnLoan(targetReturnLoan.id, {
        returned_at: formReturnDate,
        fine_paid: formFinePaid,
        notes: formReturnNotes.trim() || undefined,
      });

      setSuccessMessage('Pengembalian buku berhasil diproses dan dicatat.');
      setIsReturnBookModalOpen(false);
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal memproses pengembalian buku.');
    }
  };

  // =========================================================================
  // HANDLERS ALAT LAB
  // =========================================================================
  const handleOpenEquipmentModal = (equip?: LabEquipmentItem) => {
    if (equip) {
      setEditingEquipment(equip);
      setFormEquipName(equip.name);
      setFormEquipCode(equip.code || '');
      setFormEquipRoomId(equip.room_id);
      setFormEquipCategory(equip.category || '');
      setFormEquipStock(String(equip.stock));
      setFormEquipCondition(equip.condition);
      setFormEquipNotes(equip.notes || '');
    } else {
      setEditingEquipment(null);
      setFormEquipName('');
      setFormEquipCode('');
      setFormEquipRoomId(rooms[0]?.id || '');
      setFormEquipCategory('');
      setFormEquipStock('1');
      setFormEquipCondition('baik');
      setFormEquipNotes('');
    }
    setIsEquipmentModalOpen(true);
  };

  const handleSaveEquipment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formEquipName.trim() || !formEquipRoomId) {
      setError('Nama alat dan ruangan laboratorium wajib dipilih.');
      return;
    }

    try {
      const payload: CreateEquipmentPayload = {
        name: formEquipName.trim(),
        code: formEquipCode.trim() || null,
        room_id: formEquipRoomId,
        category: formEquipCategory.trim() || null,
        stock: parseInt(formEquipStock, 10) || 1,
        condition: formEquipCondition,
        notes: formEquipNotes.trim() || null,
      };

      if (editingEquipment) {
        await libraryApi.updateEquipment(editingEquipment.id, payload);
        setSuccessMessage(`Alat laboratorium "${payload.name}" berhasil diperbarui.`);
      } else {
        await libraryApi.createEquipment(payload);
        setSuccessMessage(`Alat laboratorium "${payload.name}" berhasil ditambahkan.`);
      }

      setIsEquipmentModalOpen(false);
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal menyimpan alat lab.');
    }
  };

  const handleDeleteEquipment = async (id: string) => {
    try {
      await libraryApi.deleteEquipment(id);
      setSuccessMessage('Alat laboratorium berhasil dihapus.');
      setDeleteConfirmation(null);
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal menghapus alat lab.');
    }
  };

  // =========================================================================
  // HANDLERS PINJAM ALAT LAB
  // =========================================================================
  const handleOpenLabLoanModal = (equip: LabEquipmentItem) => {
    setTargetEquipment(equip);
    setFormLabBorrowerId('');
    setFormLabLoanQuantity('1');
    const defaultDue = new Date();
    defaultDue.setDate(defaultDue.getDate() + 3);
    setFormLabLoanDueDate(defaultDue.toISOString().split('T')[0]);
    setFormLabLoanPurpose('');
    setFormLabLoanNotes('');
    setIsLabLoanModalOpen(true);
  };

  const handleSaveLabLoan = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!targetEquipment) return;
    if (!formLabLoanDueDate) {
      setError('Tanggal jatuh tempo pengembalian alat wajib diisi.');
      return;
    }

    try {
      const payload: CreateLabLoanPayload = {
        equipment_id: targetEquipment.id,
        borrower_id: formLabBorrowerId.trim() || undefined,
        quantity: parseInt(formLabLoanQuantity, 10) || 1,
        due_date: formLabLoanDueDate,
        purpose: formLabLoanPurpose.trim() || null,
        notes: formLabLoanNotes.trim() || null,
      };

      await libraryApi.createLabLoan(payload);
      setSuccessMessage(`Peminjaman alat "${targetEquipment.name}" berhasil dicatat.`);
      setIsLabLoanModalOpen(false);
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal mencatat peminjaman alat lab.');
    }
  };

  const handleReturnLabLoan = async (loanId: string) => {
    try {
      await libraryApi.returnLabLoan(loanId, {
        returned_at: new Date().toISOString().split('T')[0],
      });
      setSuccessMessage('Alat laboratorium berhasil ditandai telah dikembalikan.');
      fetchData();
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Gagal memproses pengembalian alat lab.');
    }
  };

  // =========================================================================
  // FAST SCANNER LOOKUP
  // =========================================================================
  const handleScanLookup = (code: string) => {
    setScanError(null);
    setScannedResult(null);
    const cleanCode = code.trim().toLowerCase();
    if (!cleanCode) return;

    setIsScanning(true);

    // Cari di buku berdasarkan ISBN atau judul
    const foundBook = books.find(
      (b) =>
        (b.isbn && b.isbn.toLowerCase().includes(cleanCode)) ||
        b.title.toLowerCase().includes(cleanCode)
    );

    if (foundBook) {
      setScannedResult({ type: 'buku', book: foundBook });
      setIsScanning(false);
      return;
    }

    // Cari di alat lab berdasarkan kode alat atau nama
    const foundEquip = equipmentList.find(
      (e) =>
        (e.code && e.code.toLowerCase().includes(cleanCode)) ||
        e.name.toLowerCase().includes(cleanCode)
    );

    if (foundEquip) {
      setScannedResult({ type: 'alat', equipment: foundEquip });
      setIsScanning(false);
      return;
    }

    setIsScanning(false);
    setScanError(`Barang dengan kode/nama "${code}" tidak ditemukan dalam sistem inventaris.`);
  };

  // =========================================================================
  // FILTERED LISTS
  // =========================================================================
  const filteredBooks = useMemo(() => {
    return books.filter((b) => {
      const matchSearch =
        !bookSearch ||
        b.title.toLowerCase().includes(bookSearch.toLowerCase()) ||
        b.author.toLowerCase().includes(bookSearch.toLowerCase()) ||
        (b.isbn && b.isbn.toLowerCase().includes(bookSearch.toLowerCase()));

      const matchCategory = bookCategoryFilter === 'all' || b.category === bookCategoryFilter;
      const matchType = bookTypeFilter === 'all' || b.book_type === bookTypeFilter;

      return matchSearch && matchCategory && matchType;
    });
  }, [books, bookSearch, bookCategoryFilter, bookTypeFilter]);

  const filteredBookLoans = useMemo(() => {
    return bookLoans.filter((l) => {
      const matchSearch =
        !bookLoanSearch ||
        (l.book && l.book.title.toLowerCase().includes(bookLoanSearch.toLowerCase())) ||
        (l.borrower && l.borrower.username.toLowerCase().includes(bookLoanSearch.toLowerCase()));

      const matchStatus = bookLoanStatusFilter === 'all' || l.status === bookLoanStatusFilter;

      return matchSearch && matchStatus;
    });
  }, [bookLoans, bookLoanSearch, bookLoanStatusFilter]);

  const filteredEquipment = useMemo(() => {
    return equipmentList.filter((e) => {
      const matchSearch =
        !equipmentSearch ||
        e.name.toLowerCase().includes(equipmentSearch.toLowerCase()) ||
        (e.code && e.code.toLowerCase().includes(equipmentSearch.toLowerCase()));

      const matchCategory = equipmentCategoryFilter === 'all' || e.category === equipmentCategoryFilter;
      const matchCondition = equipmentConditionFilter === 'all' || e.condition === equipmentConditionFilter;

      return matchSearch && matchCategory && matchCondition;
    });
  }, [equipmentList, equipmentSearch, equipmentCategoryFilter, equipmentConditionFilter]);

  const filteredLabLoans = useMemo(() => {
    return labLoans.filter((l) => {
      return labLoanStatusFilter === 'all' || l.status === labLoanStatusFilter;
    });
  }, [labLoans, labLoanStatusFilter]);

  // Kategori unik untuk dropdown filter
  const bookCategories = useMemo(() => {
    const cats = new Set<string>();
    books.forEach((b) => {
      if (b.category) cats.add(b.category);
    });
    return Array.from(cats);
  }, [books]);

  const equipCategories = useMemo(() => {
    const cats = new Set<string>();
    equipmentList.forEach((e) => {
      if (e.category) cats.add(e.category);
    });
    return Array.from(cats);
  }, [equipmentList]);

  return (
    <div className="space-y-6 p-6">
      {/* HEADER SECTION */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b pb-5">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold tracking-tight text-gray-900">
              Perpustakaan & Inventaris Alat Lab
            </h1>
          </div>
          <p className="text-sm text-gray-500 mt-1">
            Manajemen sirkulasi katalog buku pustaka, inventaris peralatan laboratorium, pencatatan pinjam-kembali, dan denda otomatis.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={fetchData} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>

          {activeTab === 'buku' && (
            <Button size="sm" onClick={() => handleOpenBookModal()} className="gap-2 bg-indigo-600 hover:bg-indigo-700">
              <Plus className="h-4 w-4" />
              Tambah Buku
            </Button>
          )}

          {activeTab === 'alat_lab' && (
            <Button size="sm" onClick={() => handleOpenEquipmentModal()} className="gap-2 bg-teal-600 hover:bg-teal-700">
              <Plus className="h-4 w-4" />
              Tambah Alat Lab
            </Button>
          )}
        </div>
      </div>

      {/* ALERT NOTIFIKASI */}
      {error && (
        <div className="rounded-md bg-red-50 p-4 border border-red-200 flex items-start gap-3">
          <AlertCircle className="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
          <div className="flex-1">
            <h3 className="text-sm font-medium text-red-800">Terjadi Kesalahan</h3>
            <p className="text-sm text-red-700 mt-1">{error}</p>
          </div>
          <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {successMessage && (
        <div className="rounded-md bg-green-50 p-4 border border-green-200 flex items-start gap-3">
          <CheckCircle2 className="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5" />
          <div className="flex-1">
            <p className="text-sm text-green-800 font-medium">{successMessage}</p>
          </div>
          <button onClick={() => setSuccessMessage(null)} className="text-green-400 hover:text-green-600">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* KPI SUMMARY CARDS */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-l-4 border-l-indigo-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-gray-500 flex items-center justify-between">
              Total Buku Perpustakaan
              <BookOpen className="h-4 w-4 text-indigo-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-gray-900">{libSummary ? libSummary.total_books : books.length}</div>
            <p className="text-xs text-gray-500 mt-1">
              {libSummary ? `${libSummary.total_stock} Total Eksemplar Fisik` : 'Katalog judul terdaftar'}
            </p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-blue-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-gray-500 flex items-center justify-between">
              Sirkulasi Buku Aktif
              <Bookmark className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">
              {libSummary ? libSummary.active_loans : bookLoans.filter((l) => l.status === 'dipinjam').length}
            </div>
            <p className="text-xs text-gray-500 mt-1">
              {libSummary && libSummary.overdue_loans > 0 ? (
                <span className="text-red-600 font-medium">{libSummary.overdue_loans} buku lewat jatuh tempo!</span>
              ) : (
                'Tidak ada keterlambatan kritis'
              )}
            </p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-teal-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-gray-500 flex items-center justify-between">
              Peralatan Laboratorium
              <FlaskConical className="h-4 w-4 text-teal-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-gray-900">
              {labSummary ? labSummary.total_equipment : equipmentList.length}
            </div>
            <p className="text-xs text-gray-500 mt-1">
              {labSummary ? `${labSummary.total_units} Unit Fisik Tersebar di Lab` : 'Inventaris alat sains'}
            </p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-amber-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-gray-500 flex items-center justify-between">
              Alat Lab Dipinjam
              <Layers className="h-4 w-4 text-amber-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-amber-600">
              {labSummary ? labSummary.active_loans : labLoans.filter((l) => l.status === 'dipinjam').length}
            </div>
            <p className="text-xs text-gray-500 mt-1">
              {labSummary && labSummary.conditions_breakdown?.rusak_ringan > 0
                ? `${labSummary.conditions_breakdown.rusak_ringan} alat perlu servis`
                : 'Status alat terkendali'}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* TABS NAVIGATION */}
      <div className="border-b border-gray-200">
        <nav className="flex space-x-6 overflow-x-auto pb-1" aria-label="Tabs">
          <button
            onClick={() => setActiveTab('buku')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 whitespace-nowrap ${
              activeTab === 'buku'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <BookOpen className="h-4 w-4" />
            Katalog Buku ({books.length})
          </button>

          <button
            onClick={() => setActiveTab('pinjam_buku')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 whitespace-nowrap ${
              activeTab === 'pinjam_buku'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <Bookmark className="h-4 w-4" />
            Peminjaman Buku ({bookLoans.length})
          </button>

          <button
            onClick={() => setActiveTab('alat_lab')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 whitespace-nowrap ${
              activeTab === 'alat_lab'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <FlaskConical className="h-4 w-4" />
            Inventaris Alat Lab ({equipmentList.length})
          </button>

          <button
            onClick={() => setActiveTab('pinjam_lab')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 whitespace-nowrap ${
              activeTab === 'pinjam_lab'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <Layers className="h-4 w-4" />
            Peminjaman Alat Lab ({labLoans.length})
          </button>

          <button
            onClick={() => setActiveTab('sirkulasi_cepat')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 whitespace-nowrap ${
              activeTab === 'sirkulasi_cepat'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <Search className="h-4 w-4" />
            Sirkulasi Cepat / Scan Barcode
          </button>
        </nav>
      </div>

      {/* =====================================================================
          TAB 1: KATALOG BUKU
         ===================================================================== */}
      {activeTab === 'buku' && (
        <div className="space-y-4">
          {/* SEARCH & FILTERS */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3 bg-gray-50 p-4 rounded-lg border">
            <div className="relative md:col-span-2">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Cari judul buku, pengarang, ISBN..."
                value={bookSearch}
                onChange={(e) => setBookSearch(e.target.value)}
                className="pl-9"
              />
            </div>

            <div>
              <select
                value={bookCategoryFilter}
                onChange={(e) => setBookCategoryFilter(e.target.value)}
                className="w-full text-sm border-gray-300 rounded-md shadow-sm h-10 px-3 border bg-white focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="all">Semua Kategori</option>
                {bookCategories.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <select
                value={bookTypeFilter}
                onChange={(e) => setBookTypeFilter(e.target.value)}
                className="w-full text-sm border-gray-300 rounded-md shadow-sm h-10 px-3 border bg-white focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="all">Semua Tipe Buku</option>
                <option value="fisik">Buku Fisik</option>
                <option value="digital">E-Book (Digital)</option>
              </select>
            </div>
          </div>

          {/* TABLE BUKU */}
          <div className="bg-white border rounded-lg overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-gray-50 text-gray-700 border-b">
                  <tr>
                    <th className="py-3 px-4 font-semibold">Judul Buku & Pengarang</th>
                    <th className="py-3 px-4 font-semibold">ISBN / Kategori</th>
                    <th className="py-3 px-4 font-semibold">Tipe & Rak</th>
                    <th className="py-3 px-4 font-semibold text-center">Stok / Tersedia</th>
                    <th className="py-3 px-4 font-semibold text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {filteredBooks.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-8 text-center text-gray-500">
                        Tidak ada buku yang sesuai dengan filter pencarian.
                      </td>
                    </tr>
                  ) : (
                    filteredBooks.map((book) => (
                      <tr key={book.id} className="hover:bg-gray-50 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-medium text-gray-900">{book.title}</div>
                          <div className="text-xs text-gray-500">{book.author} {book.publisher ? `• ${book.publisher}` : ''}</div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="text-xs font-mono text-gray-700">{book.isbn || '-'}</div>
                          {book.category && (
                            <Badge variant="outline" className="text-[10px] mt-1 bg-gray-50">
                              {book.category}
                            </Badge>
                          )}
                        </td>
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-1.5">
                            <Badge
                              variant="outline"
                              className={
                                book.book_type === 'digital'
                                  ? 'bg-blue-50 text-blue-700 border-blue-200'
                                  : 'bg-emerald-50 text-emerald-700 border-emerald-200'
                              }
                            >
                              {book.book_type === 'digital' ? 'E-Book' : 'Fisik'}
                            </Badge>
                            {book.location_shelf && (
                              <span className="text-xs text-gray-500 font-mono">[{book.location_shelf}]</span>
                            )}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center">
                          {book.book_type === 'digital' ? (
                            <Badge className="bg-blue-100 text-blue-800 border-0">Digital Akses</Badge>
                          ) : (
                            <div className="inline-flex items-center gap-1">
                              <span className={`font-bold ${book.available_stock > 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                {book.available_stock}
                              </span>
                              <span className="text-gray-400 text-xs">/ {book.stock}</span>
                            </div>
                          )}
                        </td>
                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            {book.book_type === 'fisik' && book.available_stock > 0 && (
                              <Button
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs text-indigo-600 border-indigo-200 hover:bg-indigo-50"
                                onClick={() => handleOpenLoanModal(book)}
                              >
                                Pinjamkan
                              </Button>
                            )}
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0 text-gray-500 hover:text-gray-800"
                              onClick={() => setDetailModalItem({ type: 'buku', data: book })}
                            >
                              <Eye className="h-4 w-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0 text-gray-500 hover:text-indigo-600"
                              onClick={() => handleOpenBookModal(book)}
                            >
                              <Edit className="h-4 w-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0 text-gray-500 hover:text-red-600"
                              onClick={() =>
                                setDeleteConfirmation({
                                  type: 'buku',
                                  id: book.id,
                                  title: book.title,
                                })
                              }
                            >
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 2: PEMINJAMAN BUKU
         ===================================================================== */}
      {activeTab === 'pinjam_buku' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3 bg-gray-50 p-4 rounded-lg border">
            <div className="relative md:col-span-2">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Cari peminjaman buku, nama siswa, atau judul..."
                value={bookLoanSearch}
                onChange={(e) => setBookLoanSearch(e.target.value)}
                className="pl-9"
              />
            </div>

            <div>
              <select
                value={bookLoanStatusFilter}
                onChange={(e) => setBookLoanStatusFilter(e.target.value)}
                className="w-full text-sm border-gray-300 rounded-md shadow-sm h-10 px-3 border bg-white focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="all">Semua Status Peminjaman</option>
                <option value="dipinjam">Sedang Dipinjam</option>
                <option value="dikembalikan">Telah Dikembalikan</option>
              </select>
            </div>
          </div>

          <div className="bg-white border rounded-lg overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-gray-50 text-gray-700 border-b">
                  <tr>
                    <th className="py-3 px-4 font-semibold">Judul Buku</th>
                    <th className="py-3 px-4 font-semibold">Peminjam</th>
                    <th className="py-3 px-4 font-semibold">Tgl Pinjam & Jatuh Tempo</th>
                    <th className="py-3 px-4 font-semibold">Status & Denda</th>
                    <th className="py-3 px-4 font-semibold text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {filteredBookLoans.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-8 text-center text-gray-500">
                        Tidak ada transaksi peminjaman buku yang tercatat.
                      </td>
                    </tr>
                  ) : (
                    filteredBookLoans.map((loan) => {
                      const isOverdue =
                        loan.status === 'dipinjam' && new Date(loan.due_date) < new Date();

                      return (
                        <tr key={loan.id} className="hover:bg-gray-50 transition-colors">
                          <td className="py-3 px-4">
                            <div className="font-medium text-gray-900">{loan.book?.title || 'Buku Tidak Terdaftar'}</div>
                            <div className="text-xs text-gray-500">{loan.book?.author || '-'}</div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="font-medium text-gray-800">{loan.borrower?.username || 'Siswa/Guru'}</div>
                            <div className="text-xs text-gray-500">{loan.borrower?.email || '-'}</div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="text-xs text-gray-700">Pinjam: {loan.loan_date}</div>
                            <div className={`text-xs font-medium ${isOverdue ? 'text-red-600 font-bold' : 'text-gray-500'}`}>
                              Jatuh tempo: {loan.due_date} {isOverdue && '(Lewat Jatuh Tempo)'}
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex items-center gap-2">
                              <Badge
                                variant="outline"
                                className={
                                  loan.status === 'dikembalikan'
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : isOverdue
                                    ? 'bg-red-50 text-red-700 border-red-200'
                                    : 'bg-blue-50 text-blue-700 border-blue-200'
                                }
                              >
                                {loan.status === 'dikembalikan' ? 'Dikembalikan' : isOverdue ? 'Terlambat' : 'Dipinjam'}
                              </Badge>

                              {loan.fine_amount > 0 && (
                                <Badge
                                  variant="outline"
                                  className={loan.fine_paid ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-800 font-mono'}
                                >
                                  Denda: Rp {loan.fine_amount.toLocaleString('id-ID')} {loan.fine_paid ? '(Lunas)' : '(Belum Lunas)'}
                                </Badge>
                              )}
                            </div>
                          </td>
                          <td className="py-3 px-4 text-right">
                            {loan.status === 'dipinjam' && (
                              <Button
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50"
                                onClick={() => handleOpenReturnBookModal(loan)}
                              >
                                Proses Kembali
                              </Button>
                            )}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 3: INVENTARIS ALAT LAB
         ===================================================================== */}
      {activeTab === 'alat_lab' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3 bg-gray-50 p-4 rounded-lg border">
            <div className="relative md:col-span-2">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Cari nama alat laboratorium, kode alat..."
                value={equipmentSearch}
                onChange={(e) => setEquipmentSearch(e.target.value)}
                className="pl-9"
              />
            </div>

            <div>
              <select
                value={equipmentCategoryFilter}
                onChange={(e) => setEquipmentCategoryFilter(e.target.value)}
                className="w-full text-sm border-gray-300 rounded-md shadow-sm h-10 px-3 border bg-white focus:ring-teal-500 focus:border-teal-500"
              >
                <option value="all">Semua Kategori Alat</option>
                {equipCategories.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <select
                value={equipmentConditionFilter}
                onChange={(e) => setEquipmentConditionFilter(e.target.value)}
                className="w-full text-sm border-gray-300 rounded-md shadow-sm h-10 px-3 border bg-white focus:ring-teal-500 focus:border-teal-500"
              >
                <option value="all">Semua Kondisi Fisik</option>
                <option value="baik">Kondisi Baik</option>
                <option value="rusak_ringan">Rusak Ringan</option>
                <option value="rusak_berat">Rusak Berat</option>
              </select>
            </div>
          </div>

          <div className="bg-white border rounded-lg overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-gray-50 text-gray-700 border-b">
                  <tr>
                    <th className="py-3 px-4 font-semibold">Kode & Nama Alat Lab</th>
                    <th className="py-3 px-4 font-semibold">Ruangan Laboratorium</th>
                    <th className="py-3 px-4 font-semibold">Kategori & Kondisi</th>
                    <th className="py-3 px-4 font-semibold text-center">Stok / Siap Pakai</th>
                    <th className="py-3 px-4 font-semibold text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {filteredEquipment.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-8 text-center text-gray-500">
                        Tidak ada alat laboratorium yang cocok dengan filter.
                      </td>
                    </tr>
                  ) : (
                    filteredEquipment.map((equip) => (
                      <tr key={equip.id} className="hover:bg-gray-50 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-medium text-gray-900">{equip.name}</div>
                          <div className="text-xs font-mono text-gray-500">{equip.code || 'Tanpa Kode Barcode'}</div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="text-xs text-gray-800 font-medium">{equip.room?.name || 'Ruang Lab'}</div>
                          <div className="text-[10px] text-gray-500 font-mono">{equip.room?.code || '-'}</div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-1.5">
                            <Badge
                              variant="outline"
                              className={
                                equip.condition === 'baik'
                                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                  : equip.condition === 'rusak_ringan'
                                  ? 'bg-amber-50 text-amber-700 border-amber-200'
                                  : 'bg-red-50 text-red-700 border-red-200'
                              }
                            >
                              {equip.condition === 'baik'
                                ? 'Baik'
                                : equip.condition === 'rusak_ringan'
                                ? 'Rusak Ringan'
                                : 'Rusak Berat'}
                            </Badge>
                            {equip.category && (
                              <Badge variant="outline" className="text-[10px] bg-gray-50">
                                {equip.category}
                              </Badge>
                            )}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <div className="inline-flex items-center gap-1">
                            <span className={`font-bold ${equip.available_stock > 0 ? 'text-teal-600' : 'text-red-600'}`}>
                              {equip.available_stock}
                            </span>
                            <span className="text-gray-400 text-xs">/ {equip.stock} Unit</span>
                          </div>
                        </td>
                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            {equip.available_stock > 0 && (
                              <Button
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs text-teal-700 border-teal-200 hover:bg-teal-50"
                                onClick={() => handleOpenLabLoanModal(equip)}
                              >
                                Pinjamkan
                              </Button>
                            )}
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0 text-gray-500 hover:text-gray-800"
                              onClick={() => setDetailModalItem({ type: 'alat', data: equip })}
                            >
                              <Eye className="h-4 w-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0 text-gray-500 hover:text-teal-600"
                              onClick={() => handleOpenEquipmentModal(equip)}
                            >
                              <Edit className="h-4 w-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-8 w-8 p-0 text-gray-500 hover:text-red-600"
                              onClick={() =>
                                setDeleteConfirmation({
                                  type: 'alat',
                                  id: equip.id,
                                  title: equip.name,
                                })
                              }
                            >
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 4: PEMINJAMAN ALAT LAB
         ===================================================================== */}
      {activeTab === 'pinjam_lab' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center bg-gray-50 p-4 rounded-lg border">
            <div className="text-sm font-medium text-gray-700">Daftar Peminjaman Alat untuk Praktikum & Penelitian</div>
            <div>
              <select
                value={labLoanStatusFilter}
                onChange={(e) => setLabLoanStatusFilter(e.target.value)}
                className="text-sm border-gray-300 rounded-md shadow-sm h-10 px-3 border bg-white focus:ring-teal-500 focus:border-teal-500"
              >
                <option value="all">Semua Status</option>
                <option value="dipinjam">Sedang Dipinjam</option>
                <option value="dikembalikan">Telah Dikembalikan</option>
              </select>
            </div>
          </div>

          <div className="bg-white border rounded-lg overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-gray-50 text-gray-700 border-b">
                  <tr>
                    <th className="py-3 px-4 font-semibold">Nama Alat Lab</th>
                    <th className="py-3 px-4 font-semibold">Peminjam & Keperluan</th>
                    <th className="py-3 px-4 font-semibold">Jumlah & Jatuh Tempo</th>
                    <th className="py-3 px-4 font-semibold">Status</th>
                    <th className="py-3 px-4 font-semibold text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {filteredLabLoans.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-8 text-center text-gray-500">
                        Tidak ada transaksi peminjaman alat lab yang tercatat.
                      </td>
                    </tr>
                  ) : (
                    filteredLabLoans.map((loan) => (
                      <tr key={loan.id} className="hover:bg-gray-50 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-medium text-gray-900">{loan.equipment?.name || 'Alat Lab'}</div>
                          <div className="text-xs text-gray-500">{loan.equipment?.room?.name || '-'}</div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="font-medium text-gray-800">{loan.borrower?.username || 'Siswa/Guru'}</div>
                          <div className="text-xs text-gray-500">{loan.purpose ? `Tujuan: ${loan.purpose}` : '-'}</div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="text-xs font-semibold text-teal-700">{loan.quantity} Unit</div>
                          <div className="text-xs text-gray-500">Batas: {loan.due_date}</div>
                        </td>
                        <td className="py-3 px-4">
                          <Badge
                            variant="outline"
                            className={
                              loan.status === 'dikembalikan'
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200'
                            }
                          >
                            {loan.status === 'dikembalikan' ? 'Dikembalikan' : 'Dipinjam'}
                          </Badge>
                        </td>
                        <td className="py-3 px-4 text-right">
                          {loan.status === 'dipinjam' && (
                            <Button
                              size="sm"
                              variant="outline"
                              className="h-8 text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50"
                              onClick={() => handleReturnLabLoan(loan.id)}
                            >
                              Tandai Kembali
                            </Button>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 5: SIRKULASI CEPAT / SCANNER
         ===================================================================== */}
      {activeTab === 'sirkulasi_cepat' && (
        <div className="max-w-2xl mx-auto space-y-6 py-6">
          <Card className="border-indigo-100 shadow-md">
            <CardHeader className="bg-indigo-50/50 border-b pb-4">
              <CardTitle className="text-base font-bold text-indigo-900 flex items-center gap-2">
                <Search className="h-5 w-5 text-indigo-600" />
                Pemindaian Cepat Barcode / ISBN / Kode Alat
              </CardTitle>
              <CardDescription className="text-xs text-gray-600">
                Arahkan scanner hardware atau ketik ISBN buku / kode inventaris alat lab untuk pencarian cepat status ketersediaan.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6 space-y-4">
              <div className="flex gap-2">
                <Input
                  placeholder="Ketik ISBN atau scan barcode alat di sini..."
                  value={scanCode}
                  onChange={(e) => setScanCode(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      handleScanLookup(scanCode);
                    }
                  }}
                  className="font-mono text-base"
                />
                <Button
                  onClick={() => handleScanLookup(scanCode)}
                  disabled={isScanning || !scanCode.trim()}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white"
                >
                  Cari Barang
                </Button>
              </div>

              {scanError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700 flex items-center gap-2">
                  <AlertCircle className="h-4 w-4 flex-shrink-0 text-red-500" />
                  {scanError}
                </div>
              )}

              {scannedResult && scannedResult.type === 'buku' && scannedResult.book && (
                <div className="p-4 border rounded-lg bg-indigo-50/30 space-y-3">
                  <div className="flex justify-between items-start">
                    <div>
                      <Badge className="bg-indigo-600 mb-1">Buku Perpustakaan</Badge>
                      <h3 className="font-bold text-gray-900 text-base">{scannedResult.book.title}</h3>
                      <p className="text-xs text-gray-600">{scannedResult.book.author}</p>
                    </div>
                    <Badge variant="outline" className="font-mono">
                      ISBN: {scannedResult.book.isbn || '-'}
                    </Badge>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-xs pt-2 border-t">
                    <div>
                      <span className="text-gray-500">Stok Tersedia:</span>{' '}
                      <span className="font-bold text-emerald-600">
                        {scannedResult.book.available_stock} dari {scannedResult.book.stock}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-500">Lokasi Rak:</span>{' '}
                      <span className="font-bold">{scannedResult.book.location_shelf || '-'}</span>
                    </div>
                  </div>

                  {scannedResult.book.book_type === 'fisik' && scannedResult.book.available_stock > 0 && (
                    <Button
                      size="sm"
                      onClick={() => handleOpenLoanModal(scannedResult.book!)}
                      className="w-full bg-indigo-600 hover:bg-indigo-700 text-white mt-2"
                    >
                      Pinjamkan Buku Ini
                    </Button>
                  )}
                </div>
              )}

              {scannedResult && scannedResult.type === 'alat' && scannedResult.equipment && (
                <div className="p-4 border rounded-lg bg-teal-50/30 space-y-3">
                  <div className="flex justify-between items-start">
                    <div>
                      <Badge className="bg-teal-600 mb-1">Alat Laboratorium</Badge>
                      <h3 className="font-bold text-gray-900 text-base">{scannedResult.equipment.name}</h3>
                      <p className="text-xs text-gray-600">Ruangan: {scannedResult.equipment.room?.name || '-'}</p>
                    </div>
                    <Badge variant="outline" className="font-mono">
                      Kode: {scannedResult.equipment.code || '-'}
                    </Badge>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-xs pt-2 border-t">
                    <div>
                      <span className="text-gray-500">Siap Pakai:</span>{' '}
                      <span className="font-bold text-teal-600">
                        {scannedResult.equipment.available_stock} dari {scannedResult.equipment.stock} Unit
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-500">Kondisi:</span>{' '}
                      <span className="font-bold capitalize">{scannedResult.equipment.condition}</span>
                    </div>
                  </div>

                  {scannedResult.equipment.available_stock > 0 && (
                    <Button
                      size="sm"
                      onClick={() => handleOpenLabLoanModal(scannedResult.equipment!)}
                      className="w-full bg-teal-600 hover:bg-teal-700 text-white mt-2"
                    >
                      Pinjamkan Alat Lab Ini
                    </Button>
                  )}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* =====================================================================
          MODAL 1: TAMBAH / EDIT BUKU
         ===================================================================== */}
      {isBookModalOpen && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 space-y-4">
            <div className="flex justify-between items-center border-b pb-3">
              <h2 className="text-lg font-bold text-gray-900">
                {editingBook ? 'Edit Informasi Buku' : 'Tambah Buku Baru'}
              </h2>
              <button onClick={() => setIsBookModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSaveBook} className="space-y-4 text-sm">
              <div>
                <label className="block font-medium text-gray-700 mb-1">Judul Buku *</label>
                <Input
                  required
                  value={formBookTitle}
                  onChange={(e) => setFormBookTitle(e.target.value)}
                  placeholder="Contoh: Fisika Dasar Edisi 7"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Pengarang / Penulis *</label>
                  <Input
                    required
                    value={formBookAuthor}
                    onChange={(e) => setFormBookAuthor(e.target.value)}
                    placeholder="Contoh: Halliday & Resnick"
                  />
                </div>
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Penerbit</label>
                  <Input
                    value={formBookPublisher}
                    onChange={(e) => setFormBookPublisher(e.target.value)}
                    placeholder="Contoh: Erlangga"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Nomor ISBN</label>
                  <Input
                    value={formBookIsbn}
                    onChange={(e) => setFormBookIsbn(e.target.value)}
                    placeholder="978-602-01-xxxx-x"
                  />
                </div>
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Tahun Terbit</label>
                  <Input
                    type="number"
                    value={formBookPublishYear}
                    onChange={(e) => setFormBookPublishYear(e.target.value)}
                    placeholder="2023"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Kategori</label>
                  <Input
                    value={formBookCategory}
                    onChange={(e) => setFormBookCategory(e.target.value)}
                    placeholder="Sains, Sastra, Agama..."
                  />
                </div>
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Tipe Buku</label>
                  <select
                    value={formBookType}
                    onChange={(e) => setFormBookType(e.target.value as BookType)}
                    className="w-full h-10 px-3 border rounded-md"
                  >
                    <option value="fisik">Buku Fisik</option>
                    <option value="digital">E-Book (Digital)</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Jumlah Stok Fisik</label>
                  <Input
                    type="number"
                    min="1"
                    value={formBookStock}
                    onChange={(e) => setFormBookStock(e.target.value)}
                  />
                </div>
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Lokasi Rak Pustaka</label>
                  <Input
                    value={formBookLocation}
                    onChange={(e) => setFormBookLocation(e.target.value)}
                    placeholder="Rak A-02"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" onClick={() => setIsBookModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
                  Simpan Buku
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL 2: PINJAM BUKU
         ===================================================================== */}
      {isLoanModalOpen && targetBook && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
            <div className="flex justify-between items-center border-b pb-3">
              <h2 className="text-lg font-bold text-gray-900">Peminjaman Buku</h2>
              <button onClick={() => setIsLoanModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-3 bg-indigo-50 border border-indigo-100 rounded-md text-sm">
              <div className="font-bold text-indigo-900">{targetBook.title}</div>
              <div className="text-xs text-indigo-700">Tersedia: {targetBook.available_stock} eksemplar</div>
            </div>

            <form onSubmit={handleSaveLoan} className="space-y-4 text-sm">
              <div>
                <label className="block font-medium text-gray-700 mb-1">ID Peminjam (Siswa/Guru)</label>
                <Input
                  value={formBorrowerId}
                  onChange={(e) => setFormBorrowerId(e.target.value)}
                  placeholder="Kosongkan jika meminjam untuk akun aktif saat ini"
                />
              </div>

              <div>
                <label className="block font-medium text-gray-700 mb-1">Tanggal Jatuh Tempo Pengembalian *</label>
                <Input
                  required
                  type="date"
                  value={formLoanDueDate}
                  onChange={(e) => setFormLoanDueDate(e.target.value)}
                />
              </div>

              <div>
                <label className="block font-medium text-gray-700 mb-1">Catatan Tambahan</label>
                <Textarea
                  rows={2}
                  value={formLoanNotes}
                  onChange={(e) => setFormLoanNotes(e.target.value)}
                  placeholder="Kondisi buku saat dipinjam..."
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" onClick={() => setIsLoanModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
                  Konfirmasi Peminjaman
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL 3: PENGEMBALIAN BUKU & DENDA
         ===================================================================== */}
      {isReturnBookModalOpen && targetReturnLoan && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
            <div className="flex justify-between items-center border-b pb-3">
              <h2 className="text-lg font-bold text-gray-900">Proses Pengembalian Buku</h2>
              <button onClick={() => setIsReturnBookModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-3 bg-gray-50 border rounded-md text-sm space-y-1">
              <div className="font-bold text-gray-900">{targetReturnLoan.book?.title}</div>
              <div className="text-xs text-gray-500">Peminjam: {targetReturnLoan.borrower?.username}</div>
              <div className="text-xs text-gray-500">Batas Waktu: {targetReturnLoan.due_date}</div>
            </div>

            <form onSubmit={handleProcessReturnBook} className="space-y-4 text-sm">
              <div>
                <label className="block font-medium text-gray-700 mb-1">Tanggal Dikembalikan</label>
                <Input
                  type="date"
                  value={formReturnDate}
                  onChange={(e) => setFormReturnDate(e.target.value)}
                />
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="finePaidCheck"
                  checked={formFinePaid}
                  onChange={(e) => setFormFinePaid(e.target.checked)}
                  className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                />
                <label htmlFor="finePaidCheck" className="text-sm font-medium text-gray-700">
                  Tandai denda keterlambatan (jika ada) telah lunas terbayar
                </label>
              </div>

              <div>
                <label className="block font-medium text-gray-700 mb-1">Catatan Kondisi Buku Kembali</label>
                <Textarea
                  rows={2}
                  value={formReturnNotes}
                  onChange={(e) => setFormReturnNotes(e.target.value)}
                  placeholder="Kondisi fisik buku lengkap, halaman tidak robek..."
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" onClick={() => setIsReturnBookModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" className="bg-emerald-600 hover:bg-emerald-700 text-white">
                  Konfirmasi Pengembalian
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL 4: TAMBAH / EDIT ALAT LAB
         ===================================================================== */}
      {isEquipmentModalOpen && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
            <div className="flex justify-between items-center border-b pb-3">
              <h2 className="text-lg font-bold text-gray-900">
                {editingEquipment ? 'Edit Alat Laboratorium' : 'Tambah Alat Lab Baru'}
              </h2>
              <button onClick={() => setIsEquipmentModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSaveEquipment} className="space-y-4 text-sm">
              <div>
                <label className="block font-medium text-gray-700 mb-1">Nama Alat Laboratorium *</label>
                <Input
                  required
                  value={formEquipName}
                  onChange={(e) => setFormEquipName(e.target.value)}
                  placeholder="Contoh: Mikroskop Binokuler Olympus"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Kode / Barcode Alat</label>
                  <Input
                    value={formEquipCode}
                    onChange={(e) => setFormEquipCode(e.target.value)}
                    placeholder="LAB-BIO-001"
                  />
                </div>
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Ruangan Lab *</label>
                  <select
                    required
                    value={formEquipRoomId}
                    onChange={(e) => setFormEquipRoomId(e.target.value)}
                    className="w-full h-10 px-3 border rounded-md text-sm"
                  >
                    <option value="">Pilih Ruangan</option>
                    {rooms.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name} ({r.code})
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Kategori Alat</label>
                  <Input
                    value={formEquipCategory}
                    onChange={(e) => setFormEquipCategory(e.target.value)}
                    placeholder="Optik, Gelas Kimia, Pengukuran..."
                  />
                </div>
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Kondisi Fisik</label>
                  <select
                    value={formEquipCondition}
                    onChange={(e) => setFormEquipCondition(e.target.value as EquipmentCondition)}
                    className="w-full h-10 px-3 border rounded-md text-sm"
                  >
                    <option value="baik">Baik & Layak Pakai</option>
                    <option value="rusak_ringan">Rusak Ringan</option>
                    <option value="rusak_berat">Rusak Berat</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-medium text-gray-700 mb-1">Jumlah Unit Fisik</label>
                <Input
                  type="number"
                  min="1"
                  value={formEquipStock}
                  onChange={(e) => setFormEquipStock(e.target.value)}
                />
              </div>

              <div>
                <label className="block font-medium text-gray-700 mb-1">Catatan / Spesifikasi Teknis</label>
                <Textarea
                  rows={2}
                  value={formEquipNotes}
                  onChange={(e) => setFormEquipNotes(e.target.value)}
                  placeholder="Instruksi penanganan, nomor kalibrasi..."
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" onClick={() => setIsEquipmentModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" className="bg-teal-600 hover:bg-teal-700 text-white">
                  Simpan Alat Lab
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL 5: PINJAM ALAT LAB
         ===================================================================== */}
      {isLabLoanModalOpen && targetEquipment && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
            <div className="flex justify-between items-center border-b pb-3">
              <h2 className="text-lg font-bold text-gray-900">Peminjaman Alat Laboratorium</h2>
              <button onClick={() => setIsLabLoanModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-3 bg-teal-50 border border-teal-100 rounded-md text-sm">
              <div className="font-bold text-teal-900">{targetEquipment.name}</div>
              <div className="text-xs text-teal-700">Tersedia siap pakai: {targetEquipment.available_stock} unit</div>
            </div>

            <form onSubmit={handleSaveLabLoan} className="space-y-4 text-sm">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Jumlah Unit *</label>
                  <Input
                    required
                    type="number"
                    min="1"
                    max={targetEquipment.available_stock}
                    value={formLabLoanQuantity}
                    onChange={(e) => setFormLabLoanQuantity(e.target.value)}
                  />
                </div>
                <div>
                  <label className="block font-medium text-gray-700 mb-1">Tanggal Jatuh Tempo *</label>
                  <Input
                    required
                    type="date"
                    value={formLabLoanDueDate}
                    onChange={(e) => setFormLabLoanDueDate(e.target.value)}
                  />
                </div>
              </div>

              <div>
                <label className="block font-medium text-gray-700 mb-1">Keperluan / Praktikum</label>
                <Input
                  value={formLabLoanPurpose}
                  onChange={(e) => setFormLabLoanPurpose(e.target.value)}
                  placeholder="Praktikum Kimia Organik Kelas XI-IPA 2"
                />
              </div>

              <div>
                <label className="block font-medium text-gray-700 mb-1">Catatan Peminjam</label>
                <Textarea
                  rows={2}
                  value={formLabLoanNotes}
                  onChange={(e) => setFormLabLoanNotes(e.target.value)}
                  placeholder="Petugas lab penanggung jawab..."
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button type="button" variant="outline" onClick={() => setIsLabLoanModalOpen(false)}>
                  Batal
                </Button>
                <Button type="submit" className="bg-teal-600 hover:bg-teal-700 text-white">
                  Konfirmasi Pinjam Alat
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL DETAIL
         ===================================================================== */}
      {detailModalItem && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
            <div className="flex justify-between items-center border-b pb-3">
              <h2 className="text-lg font-bold text-gray-900">
                Detail {detailModalItem.type === 'buku' ? 'Buku Perpustakaan' : 'Alat Laboratorium'}
              </h2>
              <button onClick={() => setDetailModalItem(null)} className="text-gray-400 hover:text-gray-600">
                <X className="h-5 w-5" />
              </button>
            </div>

            {detailModalItem.type === 'buku' && (
              <div className="space-y-3 text-sm">
                <div>
                  <span className="text-xs text-gray-500">Judul Lengkap:</span>
                  <div className="font-bold text-base text-gray-900">
                    {(detailModalItem.data as LibraryBookItem).title}
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2 text-xs">
                  <div>
                    <span className="text-gray-500">Pengarang:</span>
                    <div className="font-medium">{(detailModalItem.data as LibraryBookItem).author}</div>
                  </div>
                  <div>
                    <span className="text-gray-500">Penerbit & Tahun:</span>
                    <div className="font-medium">
                      {(detailModalItem.data as LibraryBookItem).publisher || '-'} (
                      {(detailModalItem.data as LibraryBookItem).publish_year || '-'})
                    </div>
                  </div>
                  <div>
                    <span className="text-gray-500">Nomor ISBN:</span>
                    <div className="font-mono">{(detailModalItem.data as LibraryBookItem).isbn || '-'}</div>
                  </div>
                  <div>
                    <span className="text-gray-500">Lokasi Rak:</span>
                    <div className="font-mono">
                      {(detailModalItem.data as LibraryBookItem).location_shelf || '-'}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {detailModalItem.type === 'alat' && (
              <div className="space-y-3 text-sm">
                <div>
                  <span className="text-xs text-gray-500">Nama Peralatan:</span>
                  <div className="font-bold text-base text-gray-900">
                    {(detailModalItem.data as LabEquipmentItem).name}
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2 text-xs">
                  <div>
                    <span className="text-gray-500">Kode Inventaris:</span>
                    <div className="font-mono">{(detailModalItem.data as LabEquipmentItem).code || '-'}</div>
                  </div>
                  <div>
                    <span className="text-gray-500">Ruangan Laboratorium:</span>
                    <div className="font-medium">
                      {(detailModalItem.data as LabEquipmentItem).room?.name || '-'}
                    </div>
                  </div>
                  <div>
                    <span className="text-gray-500">Kondisi:</span>
                    <div className="font-medium capitalize">
                      {(detailModalItem.data as LabEquipmentItem).condition}
                    </div>
                  </div>
                  <div>
                    <span className="text-gray-500">Stok Siap Pakai:</span>
                    <div className="font-bold text-teal-600">
                      {(detailModalItem.data as LabEquipmentItem).available_stock} dari{' '}
                      {(detailModalItem.data as LabEquipmentItem).stock} Unit
                    </div>
                  </div>
                </div>
              </div>
            )}

            <div className="flex justify-end pt-3 border-t">
              <Button variant="outline" onClick={() => setDetailModalItem(null)}>
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL KONFIRMASI HAPUS
         ===================================================================== */}
      {deleteConfirmation && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-sm w-full p-6 space-y-4">
            <div className="flex items-center gap-3 text-red-600">
              <AlertTriangle className="h-6 w-6" />
              <h3 className="font-bold text-gray-900">Konfirmasi Hapus</h3>
            </div>
            <p className="text-sm text-gray-600">
              Apakah Anda yakin ingin menghapus {deleteConfirmation.type === 'buku' ? 'buku' : 'alat lab'}{' '}
              <strong className="text-gray-900">&quot;{deleteConfirmation.title}&quot;</strong>? Tindakan ini tidak dapat
              dibatalkan.
            </p>
            <div className="flex justify-end gap-2 pt-2 border-t">
              <Button variant="outline" size="sm" onClick={() => setDeleteConfirmation(null)}>
                Batal
              </Button>
              <Button
                variant="danger"
                size="sm"
                onClick={() => {
                  if (deleteConfirmation.type === 'buku') {
                    handleDeleteBook(deleteConfirmation.id);
                  } else {
                    handleDeleteEquipment(deleteConfirmation.id);
                  }
                }}
              >
                Hapus Permanen
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
