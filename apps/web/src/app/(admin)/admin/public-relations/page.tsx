'use client';

import React, { useEffect, useState, useCallback, useMemo } from 'react';
import {
  Users,
  BookOpenCheck,
  ShieldAlert,
  Handshake,
  GraduationCap,
  Plus,
  RefreshCw,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  AlertTriangle,
  FileCheck,
  Check,
  X,
  Eye,
  LogOut,
  Send,
  Building,
  Phone,
  FileText,
  Calendar,
  Lock,
  ExternalLink,
  AlertCircle,
  HelpCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { publicRelationsApi } from '@/features/public-relations/public-relations-api';
import {
  GuestItem,
  GuestSignatureDetail,
  RegisterGuestPayload,
  ComplaintItem,
  ComplaintEvent,
  UpdateComplaintStatusPayload,
  PartnershipItem,
  CreatePartnershipPayload,
  AlumniItem,
} from '@/features/public-relations/types';
import { useAuth } from '@/providers/auth-provider';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';

export default function PublicRelationsAdminPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<'guests' | 'complaints' | 'partnerships' | 'alumni'>('guests');

  // =========================================================================
  // COMMON STATE
  // =========================================================================
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [prConfirm, setPrConfirm] = useState<{
    isOpen: boolean;
    title: string;
    description: string;
    affectedItemName?: string;
    confirmLabel?: string;
    variant?: 'danger' | 'primary' | 'warning';
    onConfirm: () => Promise<void>;
    isLoading?: boolean;
  }>({
    isOpen: false,
    title: '',
    description: '',
    onConfirm: async () => {},
    isLoading: false,
  });

  // =========================================================================
  // TAB 1: GUESTS STATE
  // =========================================================================
  const [guests, setGuests] = useState<GuestItem[]>([]);
  const [guestSearch, setGuestSearch] = useState('');
  const [guestStatusFilter, setGuestStatusFilter] = useState<'all' | 'checked_in' | 'checked_out'>('all');
  const [isRegisterGuestModalOpen, setIsRegisterGuestModalOpen] = useState(false);
  const [guestForm, setGuestForm] = useState<RegisterGuestPayload>({
    full_name: '',
    institution: '',
    phone: '',
    purpose: '',
  });
  const [isSubmittingGuest, setIsSubmittingGuest] = useState(false);
  const [selectedGuestForSignature, setSelectedGuestForSignature] = useState<GuestSignatureDetail | null>(null);
  const [isSignatureModalOpen, setIsSignatureModalOpen] = useState(false);
  const [isLoadingSignature, setIsLoadingSignature] = useState(false);

  // =========================================================================
  // TAB 2: COMPLAINTS STATE
  // =========================================================================
  const [complaints, setComplaints] = useState<ComplaintItem[]>([]);
  const [complaintSearch, setComplaintSearch] = useState('');
  const [complaintCategoryFilter, setComplaintCategoryFilter] = useState('all');
  const [complaintStatusFilter, setComplaintStatusFilter] = useState('all');
  const [selectedComplaint, setSelectedComplaint] = useState<ComplaintItem | null>(null);
  const [isComplaintModalOpen, setIsComplaintModalOpen] = useState(false);
  const [statusUpdateForm, setStatusUpdateForm] = useState<UpdateComplaintStatusPayload>({
    status: 'ditinjau',
    response_text: '',
  });
  const [isSubmittingComplaintStatus, setIsSubmittingComplaintStatus] = useState(false);

  // =========================================================================
  // TAB 3: PARTNERSHIPS STATE
  // =========================================================================
  const [partnerships, setPartnerships] = useState<PartnershipItem[]>([]);
  const [partnershipSearch, setPartnershipSearch] = useState('');
  const [partnershipStatusFilter, setPartnershipStatusFilter] = useState('all');
  const [isPartnershipModalOpen, setIsPartnershipModalOpen] = useState(false);
  const [editingPartnershipId, setEditingPartnershipId] = useState<string | null>(null);
  const [partnershipForm, setPartnershipForm] = useState<CreatePartnershipPayload>({
    partner_name: '',
    mou_number: '',
    partner_type: 'instansi',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    scope: '',
    valid_from: new Date().toISOString().split('T')[0],
    valid_until: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    notes: '',
  });
  const [isSubmittingPartnership, setIsSubmittingPartnership] = useState(false);

  // =========================================================================
  // TAB 4: ALUMNI STATE
  // =========================================================================
  const [alumniList, setAlumniList] = useState<AlumniItem[]>([]);
  const [alumniSearch, setAlumniSearch] = useState('');

  // Auto-dismiss alert
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 5000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  // =========================================================================
  // FETCH DATA
  // =========================================================================
  const fetchGuests = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const res = await publicRelationsApi.getGuests({
        q: guestSearch || undefined,
        status: guestStatusFilter,
        per_page: 50,
      });
      setGuests(res.data || []);
    } catch (err: any) {
      setError(err?.response?.data?.error?.message || err?.message || 'Gagal memuat data buku tamu');
    } finally {
      setIsLoading(false);
    }
  }, [guestSearch, guestStatusFilter]);

  const fetchComplaints = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const res = await publicRelationsApi.getComplaints({
        q: complaintSearch || undefined,
        category: complaintCategoryFilter !== 'all' ? complaintCategoryFilter : undefined,
        status: complaintStatusFilter !== 'all' ? complaintStatusFilter : undefined,
        per_page: 50,
      });
      setComplaints(res.data || []);
    } catch (err: any) {
      setError(err?.response?.data?.error?.message || err?.message || 'Gagal memuat data aduan & aspirasi');
    } finally {
      setIsLoading(false);
    }
  }, [complaintSearch, complaintCategoryFilter, complaintStatusFilter]);

  const fetchPartnerships = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const res = await publicRelationsApi.getPartnerships({
        search: partnershipSearch || undefined,
        status: partnershipStatusFilter !== 'all' ? partnershipStatusFilter : undefined,
        per_page: 50,
      });
      setPartnerships(res.data || []);
    } catch (err: any) {
      setError(err?.response?.data?.error?.message || err?.message || 'Gagal memuat data kemitraan & MoU');
    } finally {
      setIsLoading(false);
    }
  }, [partnershipSearch, partnershipStatusFilter]);

  const fetchAlumni = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const res = await publicRelationsApi.getAlumni({
        search: alumniSearch || undefined,
        per_page: 50,
      });
      setAlumniList(res.data || []);
    } catch (err: any) {
      setError(err?.response?.data?.error?.message || err?.message || 'Gagal memuat direktori alumni');
    } finally {
      setIsLoading(false);
    }
  }, [alumniSearch]);

  // Load data when active tab changes
  useEffect(() => {
    if (activeTab === 'guests') fetchGuests();
    else if (activeTab === 'complaints') fetchComplaints();
    else if (activeTab === 'partnerships') fetchPartnerships();
    else if (activeTab === 'alumni') fetchAlumni();
  }, [activeTab, fetchGuests, fetchComplaints, fetchPartnerships, fetchAlumni]);

  // =========================================================================
  // GUEST ACTIONS
  // =========================================================================
  const handleRegisterGuestSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!guestForm.full_name || !guestForm.phone || !guestForm.purpose) {
      toast.warning('Nama lengkap, nomor telepon, dan keperluan wajib diisi.');
      return;
    }

    try {
      setIsSubmittingGuest(true);
      await publicRelationsApi.registerGuestPublic(guestForm);
      setSuccessMessage(`Kunjungan tamu ${guestForm.full_name} berhasil didaftarkan.`);
      toast.success(`Kunjungan tamu ${guestForm.full_name} berhasil didaftarkan.`);
      setIsRegisterGuestModalOpen(false);
      setGuestForm({ full_name: '', institution: '', phone: '', purpose: '' });
      fetchGuests();
    } catch (err: any) {
      toast.error(err?.response?.data?.error?.message || err?.message || 'Gagal mendaftarkan tamu');
    } finally {
      setIsSubmittingGuest(false);
    }
  };

  const handleCheckoutGuest = (guest: GuestItem) => {
    setPrConfirm({
      isOpen: true,
      title: 'Konfirmasi Checkout Tamu',
      description: `Apakah Anda yakin ingin mencatat kepulangan/checkout untuk tamu ini?`,
      affectedItemName: guest.full_name,
      confirmLabel: 'Ya, Checkout',
      variant: 'primary',
      onConfirm: async () => {
        try {
          await publicRelationsApi.checkoutGuest(guest.id);
          setSuccessMessage(`Tamu ${guest.full_name} berhasil dicatat checkout.`);
          toast.success(`Tamu ${guest.full_name} berhasil dicatat checkout.`);
          fetchGuests();
        } catch (err: any) {
          toast.error(err?.response?.data?.error?.message || err?.message || 'Gagal mencatat checkout tamu');
        }
      },
    });
  };

  const handleViewSignature = async (guest: GuestItem) => {
    try {
      setIsLoadingSignature(true);
      const res = await publicRelationsApi.getGuestSignature(guest.id);
      setSelectedGuestForSignature(res.data);
      setIsSignatureModalOpen(true);
    } catch (err: any) {
      toast.error(err?.response?.data?.error?.message || err?.message || 'Tanda tangan digital belum tersedia atau akses ditolak');
    } finally {
      setIsLoadingSignature(false);
    }
  };

  // =========================================================================
  // COMPLAINT ACTIONS
  // =========================================================================
  const handleOpenComplaintDetail = (item: ComplaintItem) => {
    setSelectedComplaint(item);
    setStatusUpdateForm({
      status: item.status === 'baru' ? 'ditinjau' : (item.status as any),
      response_text: '',
      assigned_to: item.assigned_to || undefined,
    });
    setIsComplaintModalOpen(true);
  };

  const handleUpdateComplaintStatusSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedComplaint) return;
    if (!statusUpdateForm.response_text.trim()) {
      toast.warning('Catatan tindak lanjut atau alasan perubahan status wajib diisi.');
      return;
    }

    try {
      setIsSubmittingComplaintStatus(true);
      const res = await publicRelationsApi.updateComplaintStatus(selectedComplaint.id, statusUpdateForm);
      setSuccessMessage(`Status pengaduan ${selectedComplaint.ticket_code} berhasil diperbarui.`);
      toast.success(`Status pengaduan ${selectedComplaint.ticket_code} berhasil diperbarui.`);
      setSelectedComplaint(res.data);
      setIsComplaintModalOpen(false);
      fetchComplaints();
    } catch (err: any) {
      toast.error(err?.response?.data?.error?.message || err?.message || 'Gagal memperbarui status pengaduan');
    } finally {
      setIsSubmittingComplaintStatus(false);
    }
  };

  // =========================================================================
  // PARTNERSHIP ACTIONS
  // =========================================================================
  const handleOpenPartnershipModal = (item?: PartnershipItem) => {
    if (item) {
      setEditingPartnershipId(item.id);
      setPartnershipForm({
        partner_name: item.partner_name,
        mou_number: item.mou_number || '',
        partner_type: item.partner_type,
        contact_person: item.contact_person || '',
        phone: item.phone || '',
        email: item.email || '',
        address: item.address || '',
        scope: item.scope,
        valid_from: item.valid_from,
        valid_until: item.valid_until,
        notes: item.notes || '',
      });
    } else {
      setEditingPartnershipId(null);
      setPartnershipForm({
        partner_name: '',
        mou_number: '',
        partner_type: 'instansi',
        contact_person: '',
        phone: '',
        email: '',
        address: '',
        scope: '',
        valid_from: new Date().toISOString().split('T')[0],
        valid_until: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        notes: '',
      });
    }
    setIsPartnershipModalOpen(true);
  };

  const handlePartnershipSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!partnershipForm.partner_name || !partnershipForm.scope || !partnershipForm.valid_from || !partnershipForm.valid_until) {
      toast.warning('Nama mitra, ruang lingkup, dan rentang tanggal MoU wajib diisi.');
      return;
    }

    try {
      setIsSubmittingPartnership(true);
      if (editingPartnershipId) {
        await publicRelationsApi.updatePartnership(editingPartnershipId, partnershipForm);
        setSuccessMessage(`Kemitraan ${partnershipForm.partner_name} berhasil diperbarui.`);
        toast.success(`Kemitraan ${partnershipForm.partner_name} berhasil diperbarui.`);
      } else {
        await publicRelationsApi.createPartnership(partnershipForm);
        setSuccessMessage(`Kemitraan ${partnershipForm.partner_name} berhasil ditambahkan.`);
        toast.success(`Kemitraan ${partnershipForm.partner_name} berhasil ditambahkan.`);
      }
      setIsPartnershipModalOpen(false);
      fetchPartnerships();
    } catch (err: any) {
      toast.error(err?.response?.data?.error?.message || err?.message || 'Gagal menyimpan data kemitraan');
    } finally {
      setIsSubmittingPartnership(false);
    }
  };

  const handleDeletePartnership = (item: PartnershipItem) => {
    setPrConfirm({
      isOpen: true,
      title: 'Hapus Kemitraan',
      description: `Apakah Anda yakin ingin menghapus data kemitraan ini?`,
      affectedItemName: item.partner_name,
      confirmLabel: 'Ya, Hapus',
      variant: 'danger',
      onConfirm: async () => {
        try {
          await publicRelationsApi.deletePartnership(item.id);
          setSuccessMessage(`Kemitraan dengan ${item.partner_name} berhasil dihapus.`);
          toast.success(`Kemitraan dengan ${item.partner_name} berhasil dihapus.`);
          fetchPartnerships();
        } catch (err: any) {
          toast.error(err?.response?.data?.error?.message || err?.message || 'Gagal menghapus kemitraan');
        }
      },
    });
  };

  // =========================================================================
  // COMPUTED STATS
  // =========================================================================
  const guestStats = useMemo(() => {
    const total = guests.length;
    const active = guests.filter((g) => !g.check_out).length;
    const completed = guests.filter((g) => !!g.check_out).length;
    const withSignature = guests.filter((g) => !!g.signature_file_id).length;
    return { total, active, completed, withSignature };
  }, [guests]);

  const complaintStats = useMemo(() => {
    const total = complaints.length;
    const baru = complaints.filter((c) => c.status === 'baru').length;
    const ditinjau = complaints.filter((c) => c.status === 'ditinjau').length;
    const ditindaklanjuti = complaints.filter((c) => c.status === 'ditindaklanjuti').length;
    const selesai = complaints.filter((c) => c.status === 'selesai').length;
    return { total, baru, ditinjau, ditindaklanjuti, selesai };
  }, [complaints]);

  const partnershipStats = useMemo(() => {
    const total = partnerships.length;
    const aktif = partnerships.filter((p) => p.status === 'aktif').length;
    const kedaluwarsa = partnerships.filter((p) => p.status === 'kedaluwarsa').length;
    return { total, aktif, kedaluwarsa };
  }, [partnerships]);

  return (
    <div className="space-y-6">
      {/* HEADER SECTION */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">
            Humas, Buku Tamu & Pengaduan
          </h1>
          <p className="text-sm text-slate-500">
            Kelola buku tamu digital, tanda tangan integritas, pengaduan anonim (whistleblowing), dan dokumen kemitraan MoU SMP Laboratorium UM.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {activeTab === 'guests' && (
            <Button
              onClick={() => setIsRegisterGuestModalOpen(true)}
              className="bg-indigo-600 hover:bg-indigo-700 text-white"
            >
              <Plus className="w-4 h-4 mr-2" />
              Catat Tamu Baru
            </Button>
          )}
          {activeTab === 'partnerships' && (
            <Button
              onClick={() => handleOpenPartnershipModal()}
              className="bg-indigo-600 hover:bg-indigo-700 text-white"
            >
              <Plus className="w-4 h-4 mr-2" />
              Tambah Kemitraan
            </Button>
          )}
          <Button
            variant="outline"
            onClick={() => {
              if (activeTab === 'guests') fetchGuests();
              else if (activeTab === 'complaints') fetchComplaints();
              else if (activeTab === 'partnerships') fetchPartnerships();
              else if (activeTab === 'alumni') fetchAlumni();
            }}
            disabled={isLoading}
          >
            <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? 'animate-spin' : ''}`} />
            Muat Ulang
          </Button>
        </div>
      </div>

      {/* ALERT BANNERS */}
      {successMessage && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-3 text-emerald-800 text-sm">
          <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-lg flex items-center justify-between gap-3 text-red-800 text-sm">
          <div className="flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0" />
            <span>{error}</span>
          </div>
          <Button
            size="sm"
            variant="outline"
            className="border-red-300 text-red-700 hover:bg-red-100"
            onClick={() => {
              if (activeTab === 'guests') fetchGuests();
              else if (activeTab === 'complaints') fetchComplaints();
              else if (activeTab === 'partnerships') fetchPartnerships();
              else if (activeTab === 'alumni') fetchAlumni();
            }}
          >
            Coba Lagi
          </Button>
        </div>
      )}

      {/* NAVIGATION TABS */}
      <div className="flex border-b border-slate-200 space-x-2">
        <button
          onClick={() => setActiveTab('guests')}
          className={`pb-3 px-4 font-medium text-sm flex items-center gap-2 border-b-2 transition-colors ${
            activeTab === 'guests'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
          }`}
        >
          <BookOpenCheck className="w-4 h-4" />
          Buku Tamu Digital
          <Badge variant="default" className="ml-1 text-xs">{guestStats.total}</Badge>
        </button>
        <button
          onClick={() => setActiveTab('complaints')}
          className={`pb-3 px-4 font-medium text-sm flex items-center gap-2 border-b-2 transition-colors ${
            activeTab === 'complaints'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
          }`}
        >
          <ShieldAlert className="w-4 h-4" />
          Aspirasi & Whistleblowing
          {complaintStats.baru > 0 && (
            <Badge className="ml-1 bg-amber-500 text-white text-xs">{complaintStats.baru} Baru</Badge>
          )}
        </button>
        <button
          onClick={() => setActiveTab('partnerships')}
          className={`pb-3 px-4 font-medium text-sm flex items-center gap-2 border-b-2 transition-colors ${
            activeTab === 'partnerships'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
          }`}
        >
          <Handshake className="w-4 h-4" />
          Kemitraan & Dokumen MoU
          <Badge variant="default" className="ml-1 text-xs">{partnershipStats.total}</Badge>
        </button>
      </div>

      {/* ===================================================================== */}
      {/* TAB 1: BUKU TAMU DIGITAL CONTENT */}
      {/* ===================================================================== */}
      {activeTab === 'guests' && (
        <div className="space-y-6">
          {/* STAT CARDS */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
              <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500 uppercase">Total Kunjungan</p>
                    <p className="text-2xl font-bold text-slate-900 mt-1">{guestStats.total}</p>
                  </div>
                  <Users className="w-8 h-8 text-indigo-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500 uppercase">Sedang Berkunjung</p>
                    <p className="text-2xl font-bold text-amber-600 mt-1">{guestStats.active}</p>
                  </div>
                  <Clock className="w-8 h-8 text-amber-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500 uppercase">Sudah Checkout</p>
                    <p className="text-2xl font-bold text-emerald-600 mt-1">{guestStats.completed}</p>
                  </div>
                  <CheckCircle2 className="w-8 h-8 text-emerald-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500 uppercase">Tanda Tangan Integritas</p>
                    <p className="text-2xl font-bold text-blue-600 mt-1">{guestStats.withSignature}</p>
                  </div>
                  <FileCheck className="w-8 h-8 text-blue-500" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* FILTERS */}
          <Card>
            <CardContent className="pt-5">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="sm:col-span-2 relative">
                  <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                  <Input
                    placeholder="Cari nama tamu, instansi, no HP, atau keperluan..."
                    value={guestSearch}
                    onChange={(e) => setGuestSearch(e.target.value)}
                    className="pl-9"
                  />
                </div>
                <div>
                  <select
                    className="w-full h-10 px-3 py-2 text-sm border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={guestStatusFilter}
                    onChange={(e) => setGuestStatusFilter(e.target.value as any)}
                  >
                    <option value="all">Semua Status Kunjungan</option>
                    <option value="checked_in">Sedang Berkunjung (Belum Checkout)</option>
                    <option value="checked_out">Selesai (Sudah Checkout)</option>
                  </select>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* TABLE */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">Daftar Kunjungan Buku Tamu</CardTitle>
              <CardDescription>Catatan kedatangan tamu dan tanda tangan integritas digital.</CardDescription>
            </CardHeader>
            <CardContent>
              {isLoading ? (
                <div className="py-12 flex flex-col items-center justify-center text-slate-400">
                  <RefreshCw className="w-8 h-8 animate-spin mb-2" />
                  <p className="text-sm">Memuat data buku tamu...</p>
                </div>
              ) : guests.length === 0 ? (
                <div className="py-12 text-center text-slate-500">
                  <BookOpenCheck className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                  <p className="text-base font-medium">Belum ada catatan kunjungan tamu</p>
                  <p className="text-xs text-slate-400 mt-1">Gunakan tombol &quot;Catat Tamu Baru&quot; untuk mencatat tamu resepsionis.</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600 border-y border-slate-200 font-medium">
                      <tr>
                        <th className="py-3 px-4">Nama & Instansi</th>
                        <th className="py-3 px-4">Kontak (HP)</th>
                        <th className="py-3 px-4">Keperluan & Tujuan</th>
                        <th className="py-3 px-4">Waktu Kedatangan</th>
                        <th className="py-3 px-4">Tanda Tangan</th>
                        <th className="py-3 px-4">Status</th>
                        <th className="py-3 px-4 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {guests.map((g) => {
                        const isCheckedOut = !!g.check_out;
                        return (
                          <tr key={g.id} className="hover:bg-slate-50 transition-colors">
                            <td className="py-3 px-4">
                              <p className="font-medium text-slate-900">{g.full_name}</p>
                              {g.institution && (
                                <p className="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                  <Building className="w-3 h-3 text-slate-400" />
                                  {g.institution}
                                </p>
                              )}
                            </td>
                            <td className="py-3 px-4 text-slate-600">
                              <span className="font-mono text-xs">{g.phone}</span>
                            </td>
                            <td className="py-3 px-4">
                              <p className="text-slate-800 line-clamp-1">{g.purpose}</p>
                              {g.target_user && (
                                <p className="text-xs text-indigo-600 mt-0.5">
                                  Bertemu: {g.target_user.username}
                                </p>
                              )}
                            </td>
                            <td className="py-3 px-4 text-slate-600 whitespace-nowrap">
                              <p className="text-xs font-medium">
                                Masuk: {new Date(g.check_in).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })}
                              </p>
                              {isCheckedOut && (
                                <p className="text-xs text-slate-400">
                                  Keluar: {new Date(g.check_out!).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })}
                                </p>
                              )}
                            </td>
                            <td className="py-3 px-4 whitespace-nowrap">
                              {g.signature_file_id ? (
                                <button
                                  onClick={() => handleViewSignature(g)}
                                  className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-md text-xs font-medium border border-emerald-200 transition-colors"
                                >
                                  <FileCheck className="w-3.5 h-3.5" />
                                  Terverifikasi SHA-256
                                </button>
                              ) : (
                                <span className="text-xs text-slate-400 italic">Belum ada</span>
                              )}
                            </td>
                            <td className="py-3 px-4 whitespace-nowrap">
                              {isCheckedOut ? (
                                <Badge variant="default" className="bg-slate-100 text-slate-700">
                                  Checkout
                                </Badge>
                              ) : (
                                <Badge className="bg-amber-100 text-amber-800 border-amber-200">
                                  Sedang Berkunjung
                                </Badge>
                              )}
                            </td>
                            <td className="py-3 px-4 text-right whitespace-nowrap">
                              {!isCheckedOut && (
                                <Button
                                  size="sm"
                                  variant="outline"
                                  onClick={() => handleCheckoutGuest(g)}
                                  className="text-xs text-red-600 border-red-200 hover:bg-red-50"
                                >
                                  <LogOut className="w-3.5 h-3.5 mr-1" />
                                  Checkout
                                </Button>
                              )}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* ===================================================================== */}
      {/* TAB 2: ASPIRASI & WHISTLEBLOWING CONTENT */}
      {/* ===================================================================== */}
      {activeTab === 'complaints' && (
        <div className="space-y-6">
          {/* STAT CARDS */}
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <Card>
              <CardContent className="pt-4 pb-3">
                <p className="text-xs text-slate-500 uppercase font-medium">Total Aduan</p>
                <p className="text-xl font-bold text-slate-900 mt-1">{complaintStats.total}</p>
              </CardContent>
            </Card>
            <Card className="border-amber-200 bg-amber-50/30">
              <CardContent className="pt-4 pb-3">
                <p className="text-xs text-amber-700 uppercase font-medium">Baru</p>
                <p className="text-xl font-bold text-amber-600 mt-1">{complaintStats.baru}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3">
                <p className="text-xs text-blue-600 uppercase font-medium">Ditinjau</p>
                <p className="text-xl font-bold text-blue-600 mt-1">{complaintStats.ditinjau}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3">
                <p className="text-xs text-indigo-600 uppercase font-medium">Ditindaklanjuti</p>
                <p className="text-xl font-bold text-indigo-600 mt-1">{complaintStats.ditindaklanjuti}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3">
                <p className="text-xs text-emerald-600 uppercase font-medium">Selesai</p>
                <p className="text-xl font-bold text-emerald-600 mt-1">{complaintStats.selesai}</p>
              </CardContent>
            </Card>
          </div>

          {/* FILTERS */}
          <Card>
            <CardContent className="pt-5">
              <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div className="sm:col-span-2 relative">
                  <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                  <Input
                    placeholder="Cari kode tiket (ASP-...) atau isi laporan..."
                    value={complaintSearch}
                    onChange={(e) => setComplaintSearch(e.target.value)}
                    className="pl-9"
                  />
                </div>
                <div>
                  <select
                    className="w-full h-10 px-3 py-2 text-sm border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={complaintCategoryFilter}
                    onChange={(e) => setComplaintCategoryFilter(e.target.value)}
                  >
                    <option value="all">Semua Kategori</option>
                    <option value="fasilitas">Fasilitas & Sarpras</option>
                    <option value="akademik">Akademik & Pembelajaran</option>
                    <option value="pelayanan">Pelayanan & Administrasi</option>
                    <option value="bullying">Perundungan (Bullying)</option>
                    <option value="pungli">Pungutan Liar (Pungli)</option>
                    <option value="lainnya">Lainnya</option>
                  </select>
                </div>
                <div>
                  <select
                    className="w-full h-10 px-3 py-2 text-sm border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={complaintStatusFilter}
                    onChange={(e) => setComplaintStatusFilter(e.target.value)}
                  >
                    <option value="all">Semua Status</option>
                    <option value="baru">Baru</option>
                    <option value="ditinjau">Ditinjau</option>
                    <option value="ditindaklanjuti">Ditindaklanjuti</option>
                    <option value="selesai">Selesai</option>
                    <option value="ditolak">Ditolak</option>
                  </select>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* TABLE */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">Daftar Aspirasi & Pengaduan</CardTitle>
              <CardDescription>
                Kanal aspirasi aman dengan perlindungan whistleblower dan rekam jejak timeline penanganan.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoading ? (
                <div className="py-12 flex flex-col items-center justify-center text-slate-400">
                  <RefreshCw className="w-8 h-8 animate-spin mb-2" />
                  <p className="text-sm">Memuat data pengaduan...</p>
                </div>
              ) : complaints.length === 0 ? (
                <div className="py-12 text-center text-slate-500">
                  <ShieldAlert className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                  <p className="text-base font-medium">Belum ada pengaduan atau aspirasi</p>
                  <p className="text-xs text-slate-400 mt-1">Laporan dari portal publik atau formulir aduan akan muncul di sini.</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600 border-y border-slate-200 font-medium">
                      <tr>
                        <th className="py-3 px-4">Kode Tiket</th>
                        <th className="py-3 px-4">Kategori</th>
                        <th className="py-3 px-4">Isi Laporan</th>
                        <th className="py-3 px-4">Pelapor</th>
                        <th className="py-3 px-4">Status</th>
                        <th className="py-3 px-4">Tanggal Masuk</th>
                        <th className="py-3 px-4 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {complaints.map((c) => {
                        const statusColors: Record<string, string> = {
                          baru: 'bg-amber-100 text-amber-800 border-amber-200',
                          ditinjau: 'bg-blue-100 text-blue-800 border-blue-200',
                          ditindaklanjuti: 'bg-indigo-100 text-indigo-800 border-indigo-200',
                          selesai: 'bg-emerald-100 text-emerald-800 border-emerald-200',
                          ditolak: 'bg-red-100 text-red-800 border-red-200',
                        };

                        return (
                          <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                            <td className="py-3 px-4 font-mono font-semibold text-indigo-600 whitespace-nowrap">
                              {c.ticket_code}
                            </td>
                            <td className="py-3 px-4">
                              <Badge variant="outline" className="capitalize text-xs font-normal">
                                {c.category}
                              </Badge>
                            </td>
                            <td className="py-3 px-4 max-w-xs">
                              <p className="line-clamp-2 text-slate-800 text-xs leading-relaxed">{c.content}</p>
                            </td>
                            <td className="py-3 px-4 whitespace-nowrap">
                              {c.is_anonymous ? (
                                <Badge variant="default" className="bg-slate-100 text-slate-600 flex items-center gap-1 w-fit">
                                  <Lock className="w-3 h-3 text-slate-400" />
                                  Anonim
                                </Badge>
                              ) : c.reporter ? (
                                <div>
                                  <p className="text-xs font-medium text-slate-900">{c.reporter.username}</p>
                                  <p className="text-[11px] text-slate-400">{c.reporter.email}</p>
                                </div>
                              ) : (
                                <span className="text-xs text-slate-400 italic">Identitas Dilindungi</span>
                              )}
                            </td>
                            <td className="py-3 px-4 whitespace-nowrap">
                              <Badge className={statusColors[c.status] || 'bg-slate-100 text-slate-800'}>
                                {c.status}
                              </Badge>
                            </td>
                            <td className="py-3 px-4 text-xs text-slate-500 whitespace-nowrap">
                              {new Date(c.created_at).toLocaleDateString('id-ID', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                              })}
                            </td>
                            <td className="py-3 px-4 text-right whitespace-nowrap">
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleOpenComplaintDetail(c)}
                                className="text-xs text-indigo-600 hover:bg-indigo-50"
                              >
                                <Eye className="w-3.5 h-3.5 mr-1" />
                                Tindak Lanjut
                              </Button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* ===================================================================== */}
      {/* TAB 3: KEMITRAAN & DOKUMEN MOU CONTENT */}
      {/* ===================================================================== */}
      {activeTab === 'partnerships' && (
        <div className="space-y-6">
          {/* STAT CARDS */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card>
              <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500 uppercase">Total Kemitraan Lembaga</p>
                    <p className="text-2xl font-bold text-slate-900 mt-1">{partnershipStats.total}</p>
                  </div>
                  <Handshake className="w-8 h-8 text-indigo-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500 uppercase">MoU Aktif Berjalan</p>
                    <p className="text-2xl font-bold text-emerald-600 mt-1">{partnershipStats.aktif}</p>
                  </div>
                  <CheckCircle2 className="w-8 h-8 text-emerald-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500 uppercase">MoU Kedaluwarsa</p>
                    <p className="text-2xl font-bold text-red-600 mt-1">{partnershipStats.kedaluwarsa}</p>
                  </div>
                  <AlertTriangle className="w-8 h-8 text-red-500" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* FILTERS */}
          <Card>
            <CardContent className="pt-5">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="sm:col-span-2 relative">
                  <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                  <Input
                    placeholder="Cari nama mitra, nomor MoU, kontak person, atau ruang lingkup..."
                    value={partnershipSearch}
                    onChange={(e) => setPartnershipSearch(e.target.value)}
                    className="pl-9"
                  />
                </div>
                <div>
                  <select
                    className="w-full h-10 px-3 py-2 text-sm border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={partnershipStatusFilter}
                    onChange={(e) => setPartnershipStatusFilter(e.target.value)}
                  >
                    <option value="all">Semua Status MoU</option>
                    <option value="aktif">Aktif</option>
                    <option value="kedaluwarsa">Kedaluwarsa</option>
                    <option value="dihentikan">Dihentikan</option>
                  </select>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* TABLE */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">Daftar Dokumen Kemitraan & MoU</CardTitle>
              <CardDescription>
                Pencatatan kerja sama resmi dengan perguruan tinggi/UM, dinas pendidikan, puskesmas, dan lembaga mitra sekolah.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoading ? (
                <div className="py-12 flex flex-col items-center justify-center text-slate-400">
                  <RefreshCw className="w-8 h-8 animate-spin mb-2" />
                  <p className="text-sm">Memuat data kemitraan...</p>
                </div>
              ) : partnerships.length === 0 ? (
                <div className="py-12 text-center text-slate-500">
                  <Handshake className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                  <p className="text-base font-medium">Belum ada kemitraan yang terdaftar</p>
                  <p className="text-xs text-slate-400 mt-1">Gunakan tombol &quot;Tambah Kemitraan&quot; untuk menambahkan dokumen kerja sama baru.</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600 border-y border-slate-200 font-medium">
                      <tr>
                        <th className="py-3 px-4">Mitra & Nomor MoU</th>
                        <th className="py-3 px-4">Tipe Mitra</th>
                        <th className="py-3 px-4">Ruang Lingkup (Scope)</th>
                        <th className="py-3 px-4">Masa Berlaku</th>
                        <th className="py-3 px-4">Status</th>
                        <th className="py-3 px-4 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {partnerships.map((p) => {
                        const isExpired = p.status === 'kedaluwarsa';
                        return (
                          <tr key={p.id} className="hover:bg-slate-50 transition-colors">
                            <td className="py-3 px-4">
                              <p className="font-semibold text-slate-900">{p.partner_name}</p>
                              <p className="text-xs font-mono text-slate-500 mt-0.5">
                                {p.mou_number || 'Tanpa Nomor Dokumen'}
                              </p>
                            </td>
                            <td className="py-3 px-4">
                              <Badge variant="outline" className="capitalize text-xs font-normal">
                                {p.partner_type}
                              </Badge>
                            </td>
                            <td className="py-3 px-4 max-w-xs">
                              <p className="text-xs text-slate-700 line-clamp-2 leading-relaxed">{p.scope}</p>
                            </td>
                            <td className="py-3 px-4 whitespace-nowrap text-xs text-slate-600">
                              <p>Dari: {p.valid_from}</p>
                              <p>Sampai: {p.valid_until}</p>
                            </td>
                            <td className="py-3 px-4 whitespace-nowrap">
                              {p.status === 'aktif' ? (
                                <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200">
                                  Aktif
                                </Badge>
                              ) : isExpired ? (
                                <Badge className="bg-red-100 text-red-800 border-red-200">
                                  Kedaluwarsa
                                </Badge>
                              ) : (
                                <Badge variant="default">
                                  {p.status}
                                </Badge>
                              )}
                            </td>
                            <td className="py-3 px-4 text-right whitespace-nowrap space-x-1">
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleOpenPartnershipModal(p)}
                                className="text-xs text-indigo-600 hover:bg-indigo-50"
                              >
                                Edit
                              </Button>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleDeletePartnership(p)}
                                className="text-xs text-red-600 hover:bg-red-50 border-red-200"
                              >
                                Hapus
                              </Button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* ===================================================================== */}
      {/* TAB 4: ALUMNI & TRACER CONTENT */}
      {/* ===================================================================== */}
      {activeTab === 'alumni' && (
        <div className="space-y-6">
          <Card>
            <CardContent className="pt-5">
              <div className="relative">
                <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                <Input
                  placeholder="Cari nama alumni atau nama institusi/kampus/perusahaan..."
                  value={alumniSearch}
                  onChange={(e) => setAlumniSearch(e.target.value)}
                  className="pl-9"
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">Direktori Alumni Terbatas & Tracer Study</CardTitle>
              <CardDescription>
                Pangkalan data lulusan dan penelusuran rekam jejak studi lanjut serta karier dunia kerja.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoading ? (
                <div className="py-12 flex flex-col items-center justify-center text-slate-400">
                  <RefreshCw className="w-8 h-8 animate-spin mb-2" />
                  <p className="text-sm">Memuat direktori alumni...</p>
                </div>
              ) : alumniList.length === 0 ? (
                <div className="py-12 text-center text-slate-500">
                  <GraduationCap className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                  <p className="text-base font-medium">Belum ada data alumni terdaftar</p>
                  <p className="text-xs text-slate-400 mt-1">Data alumni dari tracer study atau sistem kelulusan akan ditampilkan di sini.</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600 border-y border-slate-200 font-medium">
                      <tr>
                        <th className="py-3 px-4">Nama Lengkap</th>
                        <th className="py-3 px-4">Tahun Lulus</th>
                        <th className="py-3 px-4">Aktivitas Saat Ini</th>
                        <th className="py-3 px-4">Institusi / Perusahaan</th>
                        <th className="py-3 px-4">Status Publikasi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {alumniList.map((a) => (
                        <tr key={a.id} className="hover:bg-slate-50 transition-colors">
                          <td className="py-3 px-4 font-medium text-slate-900">{a.full_name}</td>
                          <td className="py-3 px-4 text-slate-600 font-mono text-xs">{a.graduation_year}</td>
                          <td className="py-3 px-4 capitalize">
                            <Badge variant="outline" className="text-xs">{a.current_activity.replace('_', ' ')}</Badge>
                          </td>
                          <td className="py-3 px-4 text-slate-700">{a.institution_name || '-'}</td>
                          <td className="py-3 px-4">
                            {a.is_public ? (
                              <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200">Publik</Badge>
                            ) : (
                              <Badge variant="default">Terbatas</Badge>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* ===================================================================== */}
      {/* MODAL 1: REGISTRASI TAMU BARU */}
      {/* ===================================================================== */}
      {isRegisterGuestModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div className="p-5 border-b border-slate-200 flex items-center justify-between">
              <h3 className="font-semibold text-slate-900 text-base">Registrasi Kunjungan Tamu</h3>
              <button
                onClick={() => setIsRegisterGuestModalOpen(false)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleRegisterGuestSubmit} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-700 mb-1">Nama Lengkap Tamu *</label>
                <Input
                  required
                  placeholder="Contoh: Drs. Bambang Sutrisno"
                  value={guestForm.full_name}
                  onChange={(e) => setGuestForm({ ...guestForm, full_name: e.target.value })}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Instansi / Perusahaan</label>
                  <Input
                    placeholder="Contoh: Dinas Pendidikan"
                    value={guestForm.institution}
                    onChange={(e) => setGuestForm({ ...guestForm, institution: e.target.value })}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Nomor Telepon / WA *</label>
                  <Input
                    required
                    placeholder="Contoh: 081234567890"
                    value={guestForm.phone}
                    onChange={(e) => setGuestForm({ ...guestForm, phone: e.target.value })}
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-700 mb-1">Keperluan Kunjungan *</label>
                <Textarea
                  required
                  rows={3}
                  placeholder="Jelaskan maksud dan tujuan kedatangan..."
                  value={guestForm.purpose}
                  onChange={(e) => setGuestForm({ ...guestForm, purpose: e.target.value })}
                />
              </div>
              <div className="pt-3 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsRegisterGuestModalOpen(false)}
                  disabled={isSubmittingGuest}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  className="bg-indigo-600 hover:bg-indigo-700 text-white"
                  disabled={isSubmittingGuest}
                >
                  {isSubmittingGuest ? 'Menyimpan...' : 'Simpan Kunjungan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ===================================================================== */}
      {/* MODAL 2: DETAIL TANDA TANGAN DIGITAL (BE-GUEST-03) */}
      {/* ===================================================================== */}
      {isSignatureModalOpen && selectedGuestForSignature && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div className="p-5 border-b border-slate-200 flex items-center justify-between">
              <h3 className="font-semibold text-slate-900 text-base flex items-center gap-2">
                <FileCheck className="w-5 h-5 text-emerald-600" />
                Tanda Tangan Integritas Digital
              </h3>
              <button
                onClick={() => setIsSignatureModalOpen(false)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-5 space-y-4">
              <div>
                <p className="text-xs text-slate-500">Nama Tamu</p>
                <p className="font-semibold text-slate-900">{selectedGuestForSignature.full_name}</p>
              </div>
              <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-2.5">
                <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                <div>
                  <p className="text-xs font-semibold text-emerald-900">Integritas Berkas Terverifikasi</p>
                  <p className="text-[11px] text-emerald-700">Checksum berkas valid sesuai rekaman hash saat penandatanganan.</p>
                </div>
              </div>
              <div>
                <p className="text-xs text-slate-500 mb-1">SHA-256 Checksum</p>
                <div className="p-2.5 bg-slate-100 rounded text-[11px] font-mono break-all text-slate-700">
                  {selectedGuestForSignature.signature_hash}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4 text-xs text-slate-600">
                <div>
                  <span className="text-slate-400">Format:</span> {selectedGuestForSignature.mime_type}
                </div>
                <div>
                  <span className="text-slate-400">Ukuran:</span> {selectedGuestForSignature.size_bytes} bytes
                </div>
              </div>
            </div>
            <div className="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
              <Button variant="outline" onClick={() => setIsSignatureModalOpen(false)}>
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ===================================================================== */}
      {/* MODAL 3: TINDAK LANJUT PENGADUAN (BE-GUEST-02) */}
      {/* ===================================================================== */}
      {isComplaintModalOpen && selectedComplaint && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-150">
            <div className="p-5 border-b border-slate-200 flex items-center justify-between">
              <div>
                <h3 className="font-semibold text-slate-900 text-base flex items-center gap-2">
                  <ShieldAlert className="w-5 h-5 text-indigo-600" />
                  Detail & Tindak Lanjut Pengaduan
                </h3>
                <p className="text-xs font-mono text-indigo-600 mt-0.5">{selectedComplaint.ticket_code}</p>
              </div>
              <button
                onClick={() => setIsComplaintModalOpen(false)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-5 space-y-6">
              {/* DETAIL CARD */}
              <div className="p-4 bg-slate-50 rounded-lg space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <Badge variant="outline" className="capitalize text-xs font-semibold">
                    Kategori: {selectedComplaint.category}
                  </Badge>
                  {selectedComplaint.is_anonymous ? (
                    <Badge variant="default" className="bg-slate-200 text-slate-700 flex items-center gap-1">
                      <Lock className="w-3 h-3" />
                      Whistleblower Anonim (Identitas Dirahasiakan)
                    </Badge>
                  ) : selectedComplaint.reporter ? (
                    <Badge variant="outline" className="text-xs">
                      Pelapor: {selectedComplaint.reporter.username} ({selectedComplaint.reporter.email})
                    </Badge>
                  ) : (
                    <Badge variant="outline" className="text-xs">
                      Pelapor: Identitas Dilindungi
                    </Badge>
                  )}
                </div>
                <div>
                  <p className="text-xs font-medium text-slate-500 mb-1">Isi Aspirasi / Pengaduan:</p>
                  <p className="text-sm text-slate-800 whitespace-pre-wrap bg-white p-3 rounded border border-slate-200">
                    {selectedComplaint.content}
                  </p>
                </div>
              </div>

              {/* TIMELINE */}
              <div>
                <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">
                  Riwayat & Kronologi Penanganan
                </h4>
                {selectedComplaint.events && selectedComplaint.events.length > 0 ? (
                  <div className="space-y-3 border-l-2 border-slate-200 pl-4 ml-2">
                    {selectedComplaint.events.map((ev, idx) => (
                      <div key={idx} className="relative">
                        <div className="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-indigo-500 ring-4 ring-white" />
                        <div className="flex items-center justify-between text-xs text-slate-500">
                          <span className="font-semibold text-slate-700 capitalize">
                            {ev.event_type.replace('_', ' ')}
                          </span>
                          <span>{new Date(ev.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })}</span>
                        </div>
                        <p className="text-xs text-slate-800 mt-1">{ev.response_text}</p>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-xs text-slate-400 italic">Belum ada riwayat perkembangan.</p>
                )}
              </div>

              {/* FORM UPDATE STATUS */}
              <form onSubmit={handleUpdateComplaintStatusSubmit} className="pt-4 border-t border-slate-200 space-y-4">
                <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Perbarui Status & Tindak Lanjut Sekolah
                </h4>
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Ubah Status</label>
                  <select
                    className="w-full h-10 px-3 py-2 text-sm border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={statusUpdateForm.status}
                    onChange={(e) => setStatusUpdateForm({ ...statusUpdateForm, status: e.target.value as any })}
                  >
                    <option value="ditinjau">Ditinjau (Sedang diverifikasi tim sekolah)</option>
                    <option value="ditindaklanjuti">Ditindaklanjuti (Dalam proses investigasi/tindakan)</option>
                    <option value="selesai">Selesai (Penanganan tuntas)</option>
                    <option value="ditolak">Ditolak (Tidak memenuhi syarat / fiktif)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">
                    Tanggapan / Catatan Perkembangan *
                  </label>
                  <Textarea
                    required
                    rows={3}
                    placeholder="Tuliskan catatan tindak lanjut yang dapat dilihat pelapor via kode tiket pelacak..."
                    value={statusUpdateForm.response_text}
                    onChange={(e) => setStatusUpdateForm({ ...statusUpdateForm, response_text: e.target.value })}
                  />
                </div>
                <div className="flex justify-end gap-2 pt-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsComplaintModalOpen(false)}
                    disabled={isSubmittingComplaintStatus}
                  >
                    Batal
                  </Button>
                  <Button
                    type="submit"
                    className="bg-indigo-600 hover:bg-indigo-700 text-white"
                    disabled={isSubmittingComplaintStatus}
                  >
                    {isSubmittingComplaintStatus ? 'Memproses...' : 'Simpan Tindak Lanjut'}
                  </Button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* ===================================================================== */}
      {/* MODAL 4: TAMBAH / EDIT KEMITRAAN & MOU (BE-HUM-01) */}
      {/* ===================================================================== */}
      {isPartnershipModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-xl w-full max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-150">
            <div className="p-5 border-b border-slate-200 flex items-center justify-between">
              <h3 className="font-semibold text-slate-900 text-base">
                {editingPartnershipId ? 'Edit Data Kemitraan' : 'Tambah Kemitraan & Dokumen MoU'}
              </h3>
              <button
                onClick={() => setIsPartnershipModalOpen(false)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handlePartnershipSubmit} className="p-5 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Nama Lembaga Mitra *</label>
                  <Input
                    required
                    placeholder="Contoh: PT Telkom Indonesia"
                    value={partnershipForm.partner_name}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, partner_name: e.target.value })}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Tipe Mitra</label>
                  <select
                    className="w-full h-10 px-3 py-2 text-sm border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value={partnershipForm.partner_type}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, partner_type: e.target.value })}
                  >
                    <option value="industri">Perusahaan Mitra / Sponsor Pendidikan</option>
                    <option value="universitas">Perguruan Tinggi / Universitas</option>
                    <option value="instansi">Instansi Pemerintah / Dinas</option>
                    <option value="komunitas">Komunitas / LSM</option>
                    <option value="lainnya">Lainnya</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Nomor Dokumen MoU</label>
                  <Input
                    placeholder="Contoh: 045/MOU-SMP/X/2026"
                    value={partnershipForm.mou_number}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, mou_number: e.target.value })}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Kontak Person (PIC)</label>
                  <Input
                    placeholder="Nama narahubung"
                    value={partnershipForm.contact_person}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, contact_person: e.target.value })}
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">No Telepon Narahubung</label>
                  <Input
                    placeholder="08xxxxxxxxxx"
                    value={partnershipForm.phone}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, phone: e.target.value })}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Email Mitra</label>
                  <Input
                    type="email"
                    placeholder="kerjasama@mitra.com"
                    value={partnershipForm.email}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, email: e.target.value })}
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-700 mb-1">Ruang Lingkup Kerja Sama *</label>
                <Textarea
                  required
                  rows={2}
                  placeholder="Contoh: Praktik kerja lapangan siswa, guru tamu, uji kompetensi, dan penyaluran lulusan..."
                  value={partnershipForm.scope}
                  onChange={(e) => setPartnershipForm({ ...partnershipForm, scope: e.target.value })}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Mulai Berlaku *</label>
                  <Input
                    required
                    type="date"
                    value={partnershipForm.valid_from}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, valid_from: e.target.value })}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Berakhir Berlaku *</label>
                  <Input
                    required
                    type="date"
                    value={partnershipForm.valid_until}
                    onChange={(e) => setPartnershipForm({ ...partnershipForm, valid_until: e.target.value })}
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-700 mb-1">Catatan Tambahan</label>
                <Textarea
                  rows={2}
                  placeholder="Catatan klausul khusus atau perjanjian tambahan..."
                  value={partnershipForm.notes}
                  onChange={(e) => setPartnershipForm({ ...partnershipForm, notes: e.target.value })}
                />
              </div>
              <div className="pt-3 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsPartnershipModalOpen(false)}
                  disabled={isSubmittingPartnership}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  className="bg-indigo-600 hover:bg-indigo-700 text-white"
                  disabled={isSubmittingPartnership}
                >
                  {isSubmittingPartnership ? 'Menyimpan...' : 'Simpan Kemitraan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hubungan Masyarakat */}
      <ConfirmDialog
        isOpen={prConfirm.isOpen}
        onClose={() => setPrConfirm((prev) => ({ ...prev, isOpen: false }))}
        onConfirm={async () => {
          setPrConfirm((prev) => ({ ...prev, isLoading: true }));
          try {
            await prConfirm.onConfirm();
            setPrConfirm((prev) => ({ ...prev, isOpen: false, isLoading: false }));
          } catch {
            setPrConfirm((prev) => ({ ...prev, isLoading: false }));
          }
        }}
        title={prConfirm.title}
        description={prConfirm.description}
        affectedItemName={prConfirm.affectedItemName}
        confirmLabel={prConfirm.confirmLabel || 'Konfirmasi'}
        cancelLabel="Batal"
        variant={prConfirm.variant || 'primary'}
        isLoading={prConfirm.isLoading}
      />
    </div>
  );
}
