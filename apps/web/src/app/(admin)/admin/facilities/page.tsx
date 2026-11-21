'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  Building2,
  Calendar,
  Clock,
  Plus,
  Search,
  Filter,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  RefreshCw,
  Trash2,
  Edit,
  Eye,
  Users,
  Check,
  X,
  ShieldCheck,
  Layers,
  Info,
  CalendarDays,
  CheckSquare,
  Wrench,
  Ban,
  ArrowRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { facilitiesApi } from '@/features/sarpras/facilities-api';
import {
  FacilityItem,
  BookingItem,
  BookingSummary,
  AvailabilityResult,
} from '@/features/sarpras/types';
import { useAuth } from '@/providers/auth-provider';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';

export default function FacilitiesAndBookingsPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<'fasilitas' | 'peminjaman' | 'kalender'>('peminjaman');

  // =========================================================================
  // STATE DATA
  // =========================================================================
  const [facilities, setFacilities] = useState<FacilityItem[]>([]);
  const [bookings, setBookings] = useState<BookingItem[]>([]);
  const [summary, setSummary] = useState<BookingSummary | null>(null);
  const [facilityToDelete, setFacilityToDelete] = useState<FacilityItem | null>(null);
  const [isDeletingFacility, setIsDeletingFacility] = useState(false);
  const [bookingToCancel, setBookingToCancel] = useState<string | null>(null);
  const [isCancellingBooking, setIsCancellingBooking] = useState(false);

  const [isLoadingFacilities, setIsLoadingFacilities] = useState(false);
  const [isLoadingBookings, setIsLoadingBookings] = useState(false);
  const [isLoadingSummary, setIsLoadingSummary] = useState(false);

  const [facilityError, setFacilityError] = useState<string | null>(null);
  const [bookingError, setBookingError] = useState<string | null>(null);

  // =========================================================================
  // FILTER & PENCARIAN
  // =========================================================================
  const [facilitySearch, setFacilitySearch] = useState('');
  const [facilityTypeFilter, setFacilityTypeFilter] = useState<'all' | 'ruang' | 'alat'>('all');
  const [facilityStatusFilter, setFacilityStatusFilter] = useState<'all' | 'tersedia' | 'pemeliharaan' | 'nonaktif'>('all');

  const [bookingSearch, setBookingSearch] = useState('');
  const [bookingStatusFilter, setBookingStatusFilter] = useState<string>('all');
  const [bookingDateFrom, setBookingDateFrom] = useState('');
  const [bookingDateTo, setBookingDateTo] = useState('');
  const [bookingFacilityFilter, setBookingFacilityFilter] = useState<string>('all');

  const [calendarFacilityFilter, setCalendarFacilityFilter] = useState<string>('all');

  // =========================================================================
  // MODAL FASILITAS
  // =========================================================================
  const [isFacilityModalOpen, setIsFacilityModalOpen] = useState(false);
  const [editingFacility, setEditingFacility] = useState<FacilityItem | null>(null);
  const [facilityFormName, setFacilityFormName] = useState('');
  const [facilityFormCode, setFacilityFormCode] = useState('');
  const [facilityFormType, setFacilityFormType] = useState<'ruang' | 'alat'>('ruang');
  const [facilityFormLocation, setFacilityFormLocation] = useState('');
  const [facilityFormDescription, setFacilityFormDescription] = useState('');
  const [facilityFormCapacity, setFacilityFormCapacity] = useState<number | ''>('');
  const [facilityFormRequiresApproval, setFacilityFormRequiresApproval] = useState(true);
  const [facilityFormIsActive, setFacilityFormIsActive] = useState(true);
  const [facilityFormStatus, setFacilityFormStatus] = useState<'tersedia' | 'pemeliharaan' | 'nonaktif'>('tersedia');
  const [isSubmittingFacility, setIsSubmittingFacility] = useState(false);
  const [facilityFormError, setFacilityFormError] = useState<string | null>(null);

  // =========================================================================
  // MODAL PENGAJUAN PEMINJAMAN & CEK KETERSEDIAAN
  // =========================================================================
  const [isBookingModalOpen, setIsBookingModalOpen] = useState(false);
  const [bookFacilityId, setBookFacilityId] = useState('');
  const [bookStartDate, setBookStartDate] = useState('');
  const [bookStartTime, setBookStartTime] = useState('08:00');
  const [bookEndDate, setBookEndDate] = useState('');
  const [bookEndTime, setBookEndTime] = useState('11:00');
  const [bookPurpose, setBookPurpose] = useState('');
  const [bookAttendees, setBookAttendees] = useState<number | ''>('');

  // Interactive Availability Check State
  const [isCheckingAvailability, setIsCheckingAvailability] = useState(false);
  const [availabilityResult, setAvailabilityResult] = useState<AvailabilityResult | null>(null);
  const [availabilityChecked, setAvailabilityChecked] = useState(false);

  const [isSubmittingBooking, setIsSubmittingBooking] = useState(false);
  const [bookingSubmitError, setBookingSubmitError] = useState<string | null>(null);
  const [bookingSubmitSuccess, setBookingSubmitSuccess] = useState<string | null>(null);

  // =========================================================================
  // MODAL DETAIL & KEPUTUSAN PERSETUJUAN
  // =========================================================================
  const [selectedBooking, setSelectedBooking] = useState<BookingItem | null>(null);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [decisionNotes, setDecisionNotes] = useState('');
  const [isSubmittingDecision, setIsSubmittingDecision] = useState(false);
  const [decisionError, setDecisionError] = useState<string | null>(null);

  // =========================================================================
  // LOAD DATA FUNCTIONS
  // =========================================================================
  const fetchFacilities = useCallback(async () => {
    setIsLoadingFacilities(true);
    setFacilityError(null);
    try {
      const res = await facilitiesApi.getFacilities({
        facility_type: facilityTypeFilter !== 'all' ? facilityTypeFilter : undefined,
        status: facilityStatusFilter !== 'all' ? facilityStatusFilter : undefined,
        search: facilitySearch.trim() || undefined,
        per_page: 50,
      });
      setFacilities(res.data || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat katalog fasilitas.';
      setFacilityError(msg);
    } finally {
      setIsLoadingFacilities(false);
    }
  }, [facilityTypeFilter, facilityStatusFilter, facilitySearch]);

  const fetchBookings = useCallback(async () => {
    setIsLoadingBookings(true);
    setBookingError(null);
    try {
      const res = await facilitiesApi.getBookings({
        facility_id: bookingFacilityFilter !== 'all' ? bookingFacilityFilter : undefined,
        status: bookingStatusFilter !== 'all' ? bookingStatusFilter : undefined,
        from_date: bookingDateFrom || undefined,
        to_date: bookingDateTo || undefined,
        search: bookingSearch.trim() || undefined,
        per_page: 50,
      });
      setBookings(res.data || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat daftar peminjaman.';
      setBookingError(msg);
    } finally {
      setIsLoadingBookings(false);
    }
  }, [bookingFacilityFilter, bookingStatusFilter, bookingDateFrom, bookingDateTo, bookingSearch]);

  const fetchSummary = useCallback(async () => {
    setIsLoadingSummary(true);
    try {
      const res = await facilitiesApi.getSummary();
      setSummary(res.data);
    } catch {
      // Ignored non-critical summary load error
    } finally {
      setIsLoadingSummary(false);
    }
  }, []);

  useEffect(() => {
    fetchFacilities();
    fetchBookings();
    fetchSummary();
  }, [fetchFacilities, fetchBookings, fetchSummary]);

  // =========================================================================
  // ACTIONS: CEK KETERSEDIAAN JADWAL
  // =========================================================================
  const handleCheckAvailability = async () => {
    if (!bookFacilityId || !bookStartDate || !bookStartTime || !bookEndDate || !bookEndTime) {
      setBookingSubmitError('Lengkapi fasilitas, tanggal, dan jam mulai/selesai untuk memeriksa ketersediaan.');
      return;
    }

    const startAt = `${bookStartDate} ${bookStartTime}:00`;
    const endAt = `${bookEndDate} ${bookEndTime}:00`;

    setIsCheckingAvailability(true);
    setBookingSubmitError(null);
    setAvailabilityChecked(false);

    try {
      const res = await facilitiesApi.checkAvailability({
        facility_id: bookFacilityId,
        start_at: startAt,
        end_at: endAt,
      });
      setAvailabilityResult(res.data);
      setAvailabilityChecked(true);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memeriksa ketersediaan fasilitas.';
      setBookingSubmitError(msg);
      setAvailabilityResult(null);
    } finally {
      setIsCheckingAvailability(false);
    }
  };

  // =========================================================================
  // ACTIONS: SUBMIT PEMINJAMAN BARU
  // =========================================================================
  const handleSubmitBooking = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!bookFacilityId) {
      setBookingSubmitError('Pilih fasilitas yang ingin dipinjam.');
      return;
    }
    if (!bookStartDate || !bookStartTime || !bookEndDate || !bookEndTime) {
      setBookingSubmitError('Tentukan rentang tanggal dan jam peminjaman.');
      return;
    }
    if (!bookPurpose.trim()) {
      setBookingSubmitError('Tujuan penggunaan fasilitas wajib diisi.');
      return;
    }

    const startAt = `${bookStartDate} ${bookStartTime}:00`;
    const endAt = `${bookEndDate} ${bookEndTime}:00`;

    setIsSubmittingBooking(true);
    setBookingSubmitError(null);
    setBookingSubmitSuccess(null);

    try {
      await facilitiesApi.createBooking({
        facility_id: bookFacilityId,
        start_at: startAt,
        end_at: endAt,
        purpose: bookPurpose.trim(),
        attendees_count: bookAttendees ? Number(bookAttendees) : undefined,
      });

      setBookingSubmitSuccess('Pengajuan peminjaman fasilitas berhasil dikirim!');
      fetchBookings();
      fetchSummary();
      setTimeout(() => {
        setIsBookingModalOpen(false);
        setBookingSubmitSuccess(null);
        setBookPurpose('');
        setBookAttendees('');
        setAvailabilityResult(null);
        setAvailabilityChecked(false);
      }, 1200);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Terjadi bentrok jadwal atau kegagalan server.';
      setBookingSubmitError(msg);
    } finally {
      setIsSubmittingBooking(false);
    }
  };

  // =========================================================================
  // ACTIONS: KEPUTUSAN PERSETUJUAN / PENOLAKAN
  // =========================================================================
  const handleDecision = async (status: 'disetujui' | 'ditolak' | 'selesai') => {
    if (!selectedBooking) return;

    if (status === 'ditolak' && !decisionNotes.trim()) {
      setDecisionError('Alasan atau catatan penolakan wajib diisi untuk transparansi.');
      return;
    }

    setIsSubmittingDecision(true);
    setDecisionError(null);

    try {
      await facilitiesApi.decideBooking(selectedBooking.id, {
        status,
        decision_notes: decisionNotes.trim() || undefined,
      });

      fetchBookings();
      fetchSummary();
      setIsDetailModalOpen(false);
      setSelectedBooking(null);
      setDecisionNotes('');
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memproses keputusan peminjaman.';
      setDecisionError(msg);
    } finally {
      setIsSubmittingDecision(false);
    }
  };

  // =========================================================================
  // ACTIONS: BATALKAN PEMINJAMAN
  // =========================================================================
  const handleCancelBooking = (bookingId: string) => {
    setBookingToCancel(bookingId);
  };

  const handleConfirmCancelBooking = async () => {
    if (!bookingToCancel) return;
    setIsCancellingBooking(true);
    try {
      await facilitiesApi.cancelBooking(bookingToCancel);
      toast.success('Peminjaman fasilitas berhasil dibatalkan.');
      setBookingToCancel(null);
      fetchBookings();
      fetchSummary();
      if (isDetailModalOpen) {
        setIsDetailModalOpen(false);
        setSelectedBooking(null);
      }
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Gagal membatalkan peminjaman.');
    } finally {
      setIsCancellingBooking(false);
    }
  };

  // =========================================================================
  // ACTIONS: MASTER FASILITAS FORM (TAMBAH / EDIT)
  // =========================================================================
  const openCreateFacilityModal = () => {
    setEditingFacility(null);
    setFacilityFormName('');
    setFacilityFormCode('');
    setFacilityFormType('ruang');
    setFacilityFormLocation('');
    setFacilityFormDescription('');
    setFacilityFormCapacity('');
    setFacilityFormRequiresApproval(true);
    setFacilityFormIsActive(true);
    setFacilityFormStatus('tersedia');
    setFacilityFormError(null);
    setIsFacilityModalOpen(true);
  };

  const openEditFacilityModal = (fac: FacilityItem) => {
    setEditingFacility(fac);
    setFacilityFormName(fac.name);
    setFacilityFormCode(fac.code || '');
    setFacilityFormType(fac.facility_type);
    setFacilityFormLocation(fac.location || '');
    setFacilityFormDescription(fac.description || '');
    setFacilityFormCapacity(fac.capacity || '');
    setFacilityFormRequiresApproval(fac.requires_approval);
    setFacilityFormIsActive(fac.is_active);
    setFacilityFormStatus(fac.status);
    setFacilityFormError(null);
    setIsFacilityModalOpen(true);
  };

  const handleSubmitFacility = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!facilityFormName.trim()) {
      setFacilityFormError('Nama fasilitas wajib diisi.');
      return;
    }

    setIsSubmittingFacility(true);
    setFacilityFormError(null);

    const payload = {
      name: facilityFormName.trim(),
      code: facilityFormCode.trim() || undefined,
      facility_type: facilityFormType,
      location: facilityFormLocation.trim() || undefined,
      description: facilityFormDescription.trim() || undefined,
      capacity: facilityFormCapacity ? Number(facilityFormCapacity) : undefined,
      requires_approval: facilityFormRequiresApproval,
      is_active: facilityFormIsActive,
      status: facilityFormStatus,
    };

    try {
      if (editingFacility) {
        await facilitiesApi.updateFacility(editingFacility.id, payload);
      } else {
        await facilitiesApi.createFacility(payload);
      }
      setIsFacilityModalOpen(false);
      fetchFacilities();
      fetchSummary();
    } catch (err: unknown) {
      setFacilityFormError(err instanceof Error ? err.message : 'Gagal menyimpan data fasilitas.');
    } finally {
      setIsSubmittingFacility(false);
    }
  };

  const handleDeleteFacility = (fac: FacilityItem) => {
    setFacilityToDelete(fac);
  };

  const handleConfirmDeleteFacility = async () => {
    if (!facilityToDelete) return;
    setIsDeletingFacility(true);
    try {
      await facilitiesApi.deleteFacility(facilityToDelete.id);
      toast.success(`Fasilitas "${facilityToDelete.name}" berhasil dihapus.`);
      setFacilityToDelete(null);
      fetchFacilities();
      fetchSummary();
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Gagal menghapus fasilitas.');
    } finally {
      setIsDeletingFacility(false);
    }
  };

  // Helper formatting status badge
  const renderStatusBadge = (status: string) => {
    switch (status) {
      case 'diajukan':
        return <Badge className="bg-amber-100 text-amber-800 border-amber-300">Menunggu Persetujuan</Badge>;
      case 'disetujui':
        return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">Disetujui</Badge>;
      case 'ditolak':
        return <Badge className="bg-rose-100 text-rose-800 border-rose-300">Ditolak</Badge>;
      case 'selesai':
        return <Badge className="bg-blue-100 text-blue-800 border-blue-300">Selesai Digunakan</Badge>;
      case 'dibatalkan':
        return <Badge className="bg-slate-100 text-slate-700 border-slate-300">Dibatalkan</Badge>;
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  const renderFacilityStatusBadge = (status: string, isActive: boolean) => {
    if (!isActive) {
      return <Badge className="bg-slate-100 text-slate-700 border-slate-300">Nonaktif</Badge>;
    }
    switch (status) {
      case 'tersedia':
        return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">Tersedia</Badge>;
      case 'pemeliharaan':
        return <Badge className="bg-amber-100 text-amber-800 border-amber-300">Pemeliharaan</Badge>;
      case 'nonaktif':
        return <Badge className="bg-rose-100 text-rose-800 border-rose-300">Tidak Layak</Badge>;
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  // Filtered Calendar Bookings
  const calendarBookings = bookings.filter((b) => {
    if (calendarFacilityFilter !== 'all' && b.facility_id !== calendarFacilityFilter) {
      return false;
    }
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Header Halaman */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
            <Building2 className="h-7 w-7 text-brand-blue" />
            Sarana & Prasarana: Fasilitas & Peminjaman Ruang
          </h1>
          <p className="text-sm text-slate-600">
            Katalog fasilitas sekolah, deteksi bentrok jadwal real-time, pengajuan peminjaman, dan persetujuan operasional.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            onClick={() => {
              setBookStartDate(new Date().toISOString().split('T')[0]);
              setBookEndDate(new Date().toISOString().split('T')[0]);
              setBookFacilityId(facilities.find((f) => f.is_active && f.status === 'tersedia')?.id || '');
              setIsBookingModalOpen(true);
            }}
            className="bg-brand-blue hover:bg-blue-700 text-white flex items-center gap-1.5"
          >
            <Plus className="h-4 w-4" />
            Ajukan Peminjaman
          </Button>
          <Button
            variant="outline"
            onClick={() => {
              fetchFacilities();
              fetchBookings();
              fetchSummary();
            }}
            className="flex items-center gap-1"
          >
            <RefreshCw className="h-4 w-4" />
            Muat Ulang
          </Button>
        </div>
      </div>

      {/* Ringkasan Eksekutif */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card className="border-l-4 border-l-blue-600 shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-semibold text-slate-500">
              Total Fasilitas Aktif
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-slate-900 flex items-center justify-between">
              {summary ? summary.active_facilities : facilities.filter((f) => f.is_active).length}
              <Building2 className="h-5 w-5 text-blue-600" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-xs text-slate-500">
              Dari total {summary ? summary.total_facilities : facilities.length} fasilitas terdaftar
            </p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-amber-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-semibold text-slate-500">
              Menunggu Persetujuan
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-amber-600 flex items-center justify-between">
              {summary ? summary.pending_bookings : bookings.filter((b) => b.status === 'diajukan').length}
              <Clock className="h-5 w-5 text-amber-500" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-xs text-slate-500">Permohonan perlu kurasi staf Sarpras</p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-emerald-600 shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-semibold text-slate-500">
              Peminjaman Hari Ini
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-emerald-600 flex items-center justify-between">
              {summary ? summary.today_bookings : 0}
              <CalendarDays className="h-5 w-5 text-emerald-600" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-xs text-slate-500">Fasilitas sedang/akan digunakan hari ini</p>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-indigo-600 shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-semibold text-slate-500">
              Disetujui / Selesai
            </CardDescription>
            <CardTitle className="text-2xl font-bold text-indigo-600 flex items-center justify-between">
              {(summary?.approved_bookings || 0) + (summary?.completed_bookings || 0)}
              <CheckCircle2 className="h-5 w-5 text-indigo-600" />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-xs text-slate-500">Peminjaman aktif & tuntas digunakan</p>
          </CardContent>
        </Card>
      </div>

      {/* Navigasi Tab */}
      <div className="flex border-b border-slate-200">
        <button
          onClick={() => setActiveTab('peminjaman')}
          className={`px-4 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'peminjaman'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'
          }`}
        >
          <Clock className="h-4 w-4" />
          Antrean & Riwayat Peminjaman
          {summary && summary.pending_bookings > 0 && (
            <span className="ml-1 px-1.5 py-0.5 text-xs bg-amber-500 text-white rounded-full">
              {summary.pending_bookings}
            </span>
          )}
        </button>

        <button
          onClick={() => setActiveTab('fasilitas')}
          className={`px-4 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'fasilitas'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'
          }`}
        >
          <Building2 className="h-4 w-4" />
          Katalog Fasilitas & Ruangan ({facilities.length})
        </button>

        <button
          onClick={() => setActiveTab('kalender')}
          className={`px-4 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'kalender'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'
          }`}
        >
          <CalendarDays className="h-4 w-4" />
          Kalender & Jadwal Penggunaan
        </button>
      </div>

      {/* TAB 1: DAFTAR PEMINJAMAN (BOOKINGS) */}
      {activeTab === 'peminjaman' && (
        <div className="space-y-4">
          {/* Toolbar & Filter */}
          <Card className="p-4 bg-slate-50/50">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <div className="relative">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Cari tujuan / fasilitas..."
                  value={bookingSearch}
                  onChange={(e) => setBookingSearch(e.target.value)}
                  className="pl-9 bg-white"
                />
              </div>

              <div>
                <select
                  value={bookingStatusFilter}
                  onChange={(e) => setBookingStatusFilter(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm border rounded-md bg-white border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">Semua Status</option>
                  <option value="diajukan">Menunggu Persetujuan</option>
                  <option value="disetujui">Disetujui</option>
                  <option value="ditolak">Ditolak</option>
                  <option value="selesai">Selesai</option>
                  <option value="dibatalkan">Dibatalkan</option>
                </select>
              </div>

              <div>
                <select
                  value={bookingFacilityFilter}
                  onChange={(e) => setBookingFacilityFilter(e.target.value)}
                  className="w-full h-10 px-3 py-2 text-sm border rounded-md bg-white border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">Semua Fasilitas</option>
                  {facilities.map((fac) => (
                    <option key={fac.id} value={fac.id}>
                      {fac.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <Input
                  type="date"
                  placeholder="Dari tanggal"
                  value={bookingDateFrom}
                  onChange={(e) => setBookingDateFrom(e.target.value)}
                  className="bg-white"
                />
              </div>

              <div>
                <Input
                  type="date"
                  placeholder="Sampai tanggal"
                  value={bookingDateTo}
                  onChange={(e) => setBookingDateTo(e.target.value)}
                  className="bg-white"
                />
              </div>
            </div>
          </Card>

          {/* Feedback Error / Loading */}
          {bookingError && (
            <div className="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 shrink-0" />
              <span>{bookingError}</span>
            </div>
          )}

          {isLoadingBookings ? (
            <div className="p-12 text-center text-slate-500 flex flex-col items-center justify-center gap-2">
              <RefreshCw className="h-6 w-6 animate-spin text-blue-600" />
              <p className="text-sm">Memuat antrean peminjaman fasilitas...</p>
            </div>
          ) : bookings.length === 0 ? (
            <Card className="p-12 text-center text-slate-500">
              <Clock className="h-12 w-12 mx-auto text-slate-300 mb-3" />
              <p className="text-base font-semibold text-slate-700">Belum ada pengajuan peminjaman</p>
              <p className="text-xs text-slate-500 mt-1">
                Gunakan tombol &quot;Ajukan Peminjaman&quot; untuk mengajukan peminjaman ruangan atau alat.
              </p>
            </Card>
          ) : (
            <div className="overflow-x-auto border border-slate-200 rounded-lg bg-white shadow-sm">
              <table className="w-full text-left text-sm text-slate-700">
                <thead className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase">
                  <tr>
                    <th className="px-4 py-3">Fasilitas & Lokasi</th>
                    <th className="px-4 py-3">Jadwal Penggunaan</th>
                    <th className="px-4 py-3">Tujuan & Peserta</th>
                    <th className="px-4 py-3">Pemohon</th>
                    <th className="px-4 py-3 text-center">Status</th>
                    <th className="px-4 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {bookings.map((booking) => (
                    <tr key={booking.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-900">
                        <div className="flex items-center gap-2">
                          <Building2 className="h-4 w-4 text-blue-600 shrink-0" />
                          <div>
                            <div>{booking.facility?.name || 'Fasilitas'}</div>
                            {booking.facility?.location && (
                              <div className="text-xs text-slate-400 font-normal">{booking.facility.location}</div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-xs">
                        <div className="flex items-center gap-1 font-medium text-slate-800">
                          <Calendar className="h-3.5 w-3.5 text-slate-400" />
                          {new Date(booking.start_at).toLocaleDateString('id-ID', {
                            weekday: 'short',
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                          })}
                        </div>
                        <div className="text-slate-500 mt-0.5">
                          {new Date(booking.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} -{' '}
                          {new Date(booking.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-800 line-clamp-1">{booking.purpose}</div>
                        {booking.attendees_count && (
                          <div className="text-xs text-slate-500 mt-0.5 flex items-center gap-1">
                            <Users className="h-3 w-3" /> ~{booking.attendees_count} orang
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs text-slate-600">
                        <div className="font-medium">{booking.requester?.username || 'Pengguna'}</div>
                      </td>
                      <td className="px-4 py-3 text-center">{renderStatusBadge(booking.status)}</td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                              setSelectedBooking(booking);
                              setDecisionNotes(booking.decision_notes || '');
                              setIsDetailModalOpen(true);
                            }}
                            className="text-blue-600 hover:text-blue-800 hover:bg-blue-50"
                          >
                            <Eye className="h-4 w-4 mr-1" /> Detail
                          </Button>

                          {booking.status === 'diajukan' && (
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleCancelBooking(booking.id)}
                              className="text-red-500 hover:text-red-700 hover:bg-red-50"
                            >
                              <X className="h-4 w-4" />
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: MASTER FASILITAS & RUANGAN */}
      {activeTab === 'fasilitas' && (
        <div className="space-y-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2 flex-1 max-w-lg">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Cari nama, kode, lokasi fasilitas..."
                  value={facilitySearch}
                  onChange={(e) => setFacilitySearch(e.target.value)}
                  className="pl-9 bg-white"
                />
              </div>

              <select
                value={facilityTypeFilter}
                onChange={(e) => setFacilityTypeFilter(e.target.value as 'all' | 'ruang' | 'alat')}
                className="h-10 px-3 py-2 text-sm border rounded-md bg-white border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">Semua Tipe</option>
                <option value="ruang">Ruangan</option>
                <option value="alat">Alat / Perangkat</option>
              </select>

              <select
                value={facilityStatusFilter}
                onChange={(e) => setFacilityStatusFilter(e.target.value as 'all' | 'tersedia' | 'pemeliharaan' | 'nonaktif')}
                className="h-10 px-3 py-2 text-sm border rounded-md bg-white border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">Semua Status</option>
                <option value="tersedia">Tersedia</option>
                <option value="pemeliharaan">Pemeliharaan</option>
                <option value="nonaktif">Nonaktif</option>
              </select>
            </div>

            <Button
              onClick={openCreateFacilityModal}
              className="bg-brand-blue hover:bg-blue-700 text-white flex items-center gap-1.5"
            >
              <Plus className="h-4 w-4" /> Tambah Fasilitas Baru
            </Button>
          </div>

          {facilityError && (
            <div className="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 shrink-0" />
              <span>{facilityError}</span>
            </div>
          )}

          {isLoadingFacilities ? (
            <div className="p-12 text-center text-slate-500 flex flex-col items-center justify-center gap-2">
              <RefreshCw className="h-6 w-6 animate-spin text-blue-600" />
              <p className="text-sm">Memuat katalog fasilitas...</p>
            </div>
          ) : facilities.length === 0 ? (
            <Card className="p-12 text-center text-slate-500">
              <Building2 className="h-12 w-12 mx-auto text-slate-300 mb-3" />
              <p className="text-base font-semibold text-slate-700">Belum ada data fasilitas</p>
              <p className="text-xs text-slate-500 mt-1">Tambahkan fasilitas pertama melalui tombol di atas.</p>
            </Card>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {facilities.map((fac) => (
                <Card key={fac.id} className="hover:shadow-md transition-shadow border-slate-200">
                  <CardHeader className="pb-2">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <div className="text-xs font-semibold text-blue-600 uppercase">
                          {fac.facility_type === 'ruang' ? 'Ruangan / Gedung' : 'Peralatan'}
                          {fac.code && <span className="ml-1 text-slate-400 font-normal">({fac.code})</span>}
                        </div>
                        <CardTitle className="text-lg font-bold text-slate-900 mt-1">{fac.name}</CardTitle>
                      </div>
                      {renderFacilityStatusBadge(fac.status, fac.is_active)}
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-3 pt-2 text-xs text-slate-600">
                    {fac.location && (
                      <div className="flex items-center gap-1.5 text-slate-500">
                        <Building2 className="h-3.5 w-3.5 text-slate-400" />
                        <span>Lokasi: {fac.location}</span>
                      </div>
                    )}

                    {fac.capacity && (
                      <div className="flex items-center gap-1.5 text-slate-500">
                        <Users className="h-3.5 w-3.5 text-slate-400" />
                        <span>Kapasitas: ~{fac.capacity} orang</span>
                      </div>
                    )}

                    {fac.description && <p className="text-slate-500 line-clamp-2">{fac.description}</p>}

                    <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
                      <div className="text-[11px] text-slate-400">
                        {fac.requires_approval ? 'Wajib persetujuan Sarpras' : 'Booking otomatis'}
                      </div>
                      <div className="flex items-center gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => openEditFacilityModal(fac)}
                          className="h-7 px-2 text-blue-600 hover:bg-blue-50"
                        >
                          <Edit className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDeleteFacility(fac)}
                          className="h-7 px-2 text-red-600 hover:bg-red-50"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </div>
      )}

      {/* TAB 3: KALENDER & JADWAL PENGGUNAAN */}
      {activeTab === 'kalender' && (
        <div className="space-y-4">
          <Card className="p-4 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div className="flex items-center gap-2">
              <CalendarDays className="h-5 w-5 text-blue-600" />
              <span className="text-sm font-semibold text-slate-800">Filter Jadwal Fasilitas:</span>
              <select
                value={calendarFacilityFilter}
                onChange={(e) => setCalendarFacilityFilter(e.target.value)}
                className="h-9 px-3 text-sm border rounded-md bg-white border-slate-300"
              >
                <option value="all">Semua Fasilitas</option>
                {facilities.map((fac) => (
                  <option key={fac.id} value={fac.id}>
                    {fac.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="flex items-center gap-3 text-xs text-slate-600">
              <span className="flex items-center gap-1">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> Disetujui
              </span>
              <span className="flex items-center gap-1">
                <span className="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span> Diajukan
              </span>
              <span className="flex items-center gap-1">
                <span className="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span> Selesai
              </span>
            </div>
          </Card>

          {calendarBookings.length === 0 ? (
            <Card className="p-12 text-center text-slate-500">
              <Calendar className="h-12 w-12 mx-auto text-slate-300 mb-3" />
              <p className="text-base font-semibold text-slate-700">Tidak ada jadwal peminjaman pada filter ini</p>
            </Card>
          ) : (
            <div className="space-y-3">
              {calendarBookings.map((b) => (
                <div
                  key={b.id}
                  className={`p-4 rounded-lg border flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-colors ${
                    b.status === 'disetujui'
                      ? 'bg-emerald-50/60 border-emerald-200'
                      : b.status === 'diajukan'
                      ? 'bg-amber-50/60 border-amber-200'
                      : b.status === 'selesai'
                      ? 'bg-blue-50/60 border-blue-200'
                      : 'bg-slate-50 border-slate-200'
                  }`}
                >
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-slate-900">{b.facility?.name}</span>
                      {renderStatusBadge(b.status)}
                    </div>
                    <p className="text-sm text-slate-700 font-medium">{b.purpose}</p>
                    <div className="text-xs text-slate-500 flex items-center gap-4">
                      <span className="flex items-center gap-1">
                        <Calendar className="h-3.5 w-3.5" />
                        {new Date(b.start_at).toLocaleDateString('id-ID', {
                          weekday: 'long',
                          day: 'numeric',
                          month: 'long',
                          year: 'numeric',
                        })}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5" />
                        {new Date(b.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} -{' '}
                        {new Date(b.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB
                      </span>
                    </div>
                  </div>

                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setSelectedBooking(b);
                      setDecisionNotes(b.decision_notes || '');
                      setIsDetailModalOpen(true);
                    }}
                    className="shrink-0 bg-white"
                  >
                    Lihat Detail
                  </Button>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* =========================================================================
          MODAL 1: FORM PENGAJUAN PEMINJAMAN DENGAN CEK BENTROK
         ========================================================================= */}
      {isBookingModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <h3 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                <Plus className="h-5 w-5 text-brand-blue" />
                Ajukan Peminjaman Fasilitas
              </h3>
              <button
                onClick={() => setIsBookingModalOpen(false)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {bookingSubmitError && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-xs flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                <span>{bookingSubmitError}</span>
              </div>
            )}

            {bookingSubmitSuccess && (
              <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-md text-emerald-700 text-xs flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4 shrink-0" />
                <span>{bookingSubmitSuccess}</span>
              </div>
            )}

            <form onSubmit={handleSubmitBooking} className="space-y-4 text-sm">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Pilih Fasilitas / Ruangan *
                </label>
                <select
                  value={bookFacilityId}
                  onChange={(e) => {
                    setBookFacilityId(e.target.value);
                    setAvailabilityChecked(false);
                    setAvailabilityResult(null);
                  }}
                  className="w-full h-10 px-3 border rounded-md text-sm bg-white border-slate-300"
                  required
                >
                  <option value="">-- Pilih Fasilitas --</option>
                  {facilities.map((fac) => (
                    <option
                      key={fac.id}
                      value={fac.id}
                      disabled={!fac.is_active || fac.status !== 'tersedia'}
                    >
                      {fac.name} {fac.status !== 'tersedia' ? `[${fac.status.toUpperCase()}]` : ''}
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Mulai *</label>
                  <Input
                    type="date"
                    value={bookStartDate}
                    onChange={(e) => {
                      setBookStartDate(e.target.value);
                      if (!bookEndDate) setBookEndDate(e.target.value);
                      setAvailabilityChecked(false);
                    }}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Jam Mulai *</label>
                  <Input
                    type="time"
                    value={bookStartTime}
                    onChange={(e) => {
                      setBookStartTime(e.target.value);
                      setAvailabilityChecked(false);
                    }}
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tanggal Selesai *</label>
                  <Input
                    type="date"
                    value={bookEndDate}
                    onChange={(e) => {
                      setBookEndDate(e.target.value);
                      setAvailabilityChecked(false);
                    }}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Jam Selesai *</label>
                  <Input
                    type="time"
                    value={bookEndTime}
                    onChange={(e) => {
                      setBookEndTime(e.target.value);
                      setAvailabilityChecked(false);
                    }}
                    required
                  />
                </div>
              </div>

              {/* Tombol & Panel Cek Ketersediaan Real-Time */}
              <div className="p-3 bg-slate-50 border rounded-lg space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                    <CheckSquare className="h-4 w-4 text-blue-600" />
                    Deteksi Ketersediaan Jadwal
                  </span>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleCheckAvailability}
                    disabled={isCheckingAvailability}
                    className="h-7 text-xs bg-white"
                  >
                    {isCheckingAvailability ? (
                      <RefreshCw className="h-3.5 w-3.5 animate-spin mr-1" />
                    ) : (
                      'Periksa Sekarang'
                    )}
                  </Button>
                </div>

                {availabilityChecked && availabilityResult && (
                  <div className="pt-2 text-xs">
                    {!availabilityResult.is_eligible ? (
                      <div className="p-2 bg-amber-50 border border-amber-200 rounded text-amber-800 flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
                        <span>{availabilityResult.eligibility_message}</span>
                      </div>
                    ) : availabilityResult.is_available ? (
                      <div className="p-2 bg-emerald-50 border border-emerald-200 rounded text-emerald-800 flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
                        <span>Fasilitas tersedia pada jadwal ini (tidak ada bentrok).</span>
                      </div>
                    ) : (
                      <div className="p-2 bg-red-50 border border-red-200 rounded text-red-800 space-y-1">
                        <div className="flex items-center gap-1.5 font-semibold">
                          <XCircle className="h-4 w-4 text-red-600 shrink-0" />
                          <span>Jadwal bentrok dengan peminjaman lain:</span>
                        </div>
                        {availabilityResult.conflicts.map((c) => (
                          <div key={c.id} className="text-[11px] pl-5 text-red-700">
                            • {c.purpose} ({new Date(c.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} -{' '}
                            {new Date(c.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })})
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Tujuan Penggunaan *
                </label>
                <Textarea
                  placeholder="Contoh: Rapat Koordinasi Panitia PPDB / Gladi Bersih Pentas Seni..."
                  value={bookPurpose}
                  onChange={(e) => setBookPurpose(e.target.value)}
                  rows={3}
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Jumlah Perkiraan Peserta (Opsional)
                </label>
                <Input
                  type="number"
                  min="1"
                  placeholder="Contoh: 30"
                  value={bookAttendees}
                  onChange={(e) => setBookAttendees(e.target.value === '' ? '' : Number(e.target.value))}
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsBookingModalOpen(false)}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  disabled={isSubmittingBooking || (availabilityChecked && availabilityResult?.is_available === false)}
                  className="bg-brand-blue hover:bg-blue-700 text-white"
                >
                  {isSubmittingBooking ? 'Mengirim...' : 'Kirim Pengajuan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =========================================================================
          MODAL 2: DETAIL PEMINJAMAN & KEPUTUSAN STAF SARPRAS
         ========================================================================= */}
      {isDetailModalOpen && selectedBooking && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <h3 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                  <Building2 className="h-5 w-5 text-brand-blue" />
                  Detail Peminjaman Fasilitas
                </h3>
                <div className="mt-1">{renderStatusBadge(selectedBooking.status)}</div>
              </div>
              <button
                onClick={() => setIsDetailModalOpen(false)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {decisionError && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-xs flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                <span>{decisionError}</span>
              </div>
            )}

            <div className="space-y-3 text-xs text-slate-700">
              <div className="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-lg">
                <div>
                  <span className="text-slate-400 block">Fasilitas:</span>
                  <span className="font-semibold text-slate-900">{selectedBooking.facility?.name}</span>
                </div>
                <div>
                  <span className="text-slate-400 block">Pemohon:</span>
                  <span className="font-semibold text-slate-900">{selectedBooking.requester?.username}</span>
                </div>
                <div>
                  <span className="text-slate-400 block">Waktu Mulai:</span>
                  <span className="font-medium text-slate-800">
                    {new Date(selectedBooking.start_at).toLocaleString('id-ID')}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block">Waktu Selesai:</span>
                  <span className="font-medium text-slate-800">
                    {new Date(selectedBooking.end_at).toLocaleString('id-ID')}
                  </span>
                </div>
              </div>

              <div>
                <span className="text-slate-500 font-semibold block mb-0.5">Tujuan Penggunaan:</span>
                <p className="p-2.5 bg-slate-50 rounded border border-slate-100 text-slate-800">
                  {selectedBooking.purpose}
                </p>
              </div>

              {selectedBooking.decision_notes && (
                <div>
                  <span className="text-slate-500 font-semibold block mb-0.5">Catatan Keputusan:</span>
                  <p className="p-2.5 bg-amber-50/70 rounded border border-amber-200 text-amber-900">
                    {selectedBooking.decision_notes}
                  </p>
                </div>
              )}

              {/* FORM PERSETUJUAN / KEPUTUSAN KHUSUS ADMIN & SARPRAS */}
              {selectedBooking.status === 'diajukan' && (
                <div className="p-3 bg-blue-50/50 border border-blue-200 rounded-lg space-y-3 pt-3">
                  <div className="font-semibold text-slate-800 flex items-center gap-1.5">
                    <ShieldCheck className="h-4 w-4 text-blue-600" />
                    Tindakan Persetujuan Sarpras
                  </div>

                  <div>
                    <label className="block text-slate-600 font-medium mb-1">
                      Catatan Keputusan / Instruksi Pengambilan Kunci (Wajib jika menolak):
                    </label>
                    <Textarea
                      placeholder="Contoh: Disetujui. Harap ambil kunci di ruang Sarpras H-1..."
                      value={decisionNotes}
                      onChange={(e) => setDecisionNotes(e.target.value)}
                      rows={2}
                      className="bg-white"
                    />
                  </div>

                  <div className="flex items-center justify-end gap-2 pt-2">
                    <Button
                      type="button"
                      disabled={isSubmittingDecision}
                      onClick={() => handleDecision('ditolak')}
                      className="bg-red-600 hover:bg-red-700 text-white"
                    >
                      <XCircle className="h-4 w-4 mr-1" /> Tolak Peminjaman
                    </Button>
                    <Button
                      type="button"
                      disabled={isSubmittingDecision}
                      onClick={() => handleDecision('disetujui')}
                      className="bg-emerald-600 hover:bg-emerald-700 text-white"
                    >
                      <CheckCircle2 className="h-4 w-4 mr-1" /> Setujui Peminjaman
                    </Button>
                  </div>
                </div>
              )}

              {selectedBooking.status === 'disetujui' && (
                <div className="p-3 bg-emerald-50/50 border border-emerald-200 rounded-lg flex items-center justify-between">
                  <div className="text-xs text-emerald-800">
                    Peminjaman telah disetujui. Tandai selesai jika penggunaan telah rampung.
                  </div>
                  <Button
                    size="sm"
                    disabled={isSubmittingDecision}
                    onClick={() => handleDecision('selesai')}
                    className="bg-blue-600 hover:bg-blue-700 text-white"
                  >
                    Tandai Selesai
                  </Button>
                </div>
              )}
            </div>

            <div className="flex items-center justify-between border-t pt-3">
              <div>
                {(selectedBooking.status === 'diajukan' || selectedBooking.status === 'disetujui') && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleCancelBooking(selectedBooking.id)}
                    className="text-red-600 hover:bg-red-50"
                  >
                    Batalkan Peminjaman Ini
                  </Button>
                )}
              </div>
              <Button variant="outline" onClick={() => setIsDetailModalOpen(false)}>
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* =========================================================================
          MODAL 3: TAMBAH / EDIT FASILITAS
         ========================================================================= */}
      {isFacilityModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <h3 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                <Building2 className="h-5 w-5 text-brand-blue" />
                {editingFacility ? 'Ubah Data Fasilitas' : 'Tambah Fasilitas Baru'}
              </h3>
              <button
                onClick={() => setIsFacilityModalOpen(false)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {facilityFormError && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-xs flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                <span>{facilityFormError}</span>
              </div>
            )}

            <form onSubmit={handleSubmitFacility} className="space-y-3 text-sm">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Nama Fasilitas *
                </label>
                <Input
                  placeholder="Contoh: Aula Serbaguna Graha UM"
                  value={facilityFormName}
                  onChange={(e) => setFacilityFormName(e.target.value)}
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Kode Fasilitas</label>
                  <Input
                    placeholder="Contoh: FAC-AULA-01"
                    value={facilityFormCode}
                    onChange={(e) => setFacilityFormCode(e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Tipe Fasilitas *</label>
                  <select
                    value={facilityFormType}
                    onChange={(e) => setFacilityFormType(e.target.value as 'ruang' | 'alat')}
                    className="w-full h-10 px-3 border rounded-md text-sm bg-white border-slate-300"
                  >
                    <option value="ruang">Ruangan</option>
                    <option value="alat">Alat / Perangkat</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Lokasi / Gedung</label>
                  <Input
                    placeholder="Contoh: Gedung B Lantai 2"
                    value={facilityFormLocation}
                    onChange={(e) => setFacilityFormLocation(e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Kapasitas (Orang)</label>
                  <Input
                    type="number"
                    min="1"
                    placeholder="Contoh: 150"
                    value={facilityFormCapacity}
                    onChange={(e) => setFacilityFormCapacity(e.target.value === '' ? '' : Number(e.target.value))}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Deskripsi & Kelayakan</label>
                <Textarea
                  placeholder="Fasilitas AC, soundsystem, proyektor..."
                  value={facilityFormDescription}
                  onChange={(e) => setFacilityFormDescription(e.target.value)}
                  rows={2}
                />
              </div>

              <div className="grid grid-cols-2 gap-3 pt-1">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Status Operasional</label>
                  <select
                    value={facilityFormStatus}
                    onChange={(e) => setFacilityFormStatus(e.target.value as 'tersedia' | 'pemeliharaan' | 'nonaktif')}
                    className="w-full h-10 px-3 border rounded-md text-sm bg-white border-slate-300"
                  >
                    <option value="tersedia">Tersedia (Siap Dipesan)</option>
                    <option value="pemeliharaan">Pemeliharaan (Tidak Dapat Dipesan)</option>
                    <option value="nonaktif">Nonaktif (Rusak / Ditutup)</option>
                  </select>
                </div>
                <div className="flex flex-col justify-end gap-2 pb-1">
                  <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={facilityFormRequiresApproval}
                      onChange={(e) => setFacilityFormRequiresApproval(e.target.checked)}
                      className="rounded text-blue-600 focus:ring-blue-500"
                    />
                    Wajib Persetujuan Sarpras
                  </label>
                  <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={facilityFormIsActive}
                      onChange={(e) => setFacilityFormIsActive(e.target.checked)}
                      className="rounded text-blue-600 focus:ring-blue-500"
                    />
                    Fasilitas Aktif
                  </label>
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsFacilityModalOpen(false)}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  disabled={isSubmittingFacility}
                  className="bg-brand-blue hover:bg-blue-700 text-white"
                >
                  {isSubmittingFacility ? 'Menyimpan...' : 'Simpan Fasilitas'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Fasilitas */}
      <ConfirmDialog
        isOpen={!!facilityToDelete}
        onClose={() => setFacilityToDelete(null)}
        onConfirm={handleConfirmDeleteFacility}
        title="Hapus Fasilitas"
        description="Apakah Anda yakin ingin menghapus data fasilitas ini? Fasilitas yang memiliki riwayat booking aktif tidak dapat dihapus."
        affectedItemName={facilityToDelete?.name}
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isDeletingFacility}
      />

      {/* Dialog Konfirmasi Batalkan Peminjaman */}
      <ConfirmDialog
        isOpen={!!bookingToCancel}
        onClose={() => setBookingToCancel(null)}
        onConfirm={handleConfirmCancelBooking}
        title="Batalkan Peminjaman Fasilitas"
        description="Apakah Anda yakin ingin membatalkan pengajuan peminjaman fasilitas ini?"
        confirmLabel="Ya, Batalkan"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isCancellingBooking}
      />
    </div>
  );
}
