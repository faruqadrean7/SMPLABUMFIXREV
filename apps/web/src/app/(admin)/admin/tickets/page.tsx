'use client';

import React, { useEffect, useState, useCallback, useMemo } from 'react';
import {
  Wrench,
  AlertTriangle,
  Clock,
  CheckCircle2,
  Hourglass,
  Plus,
  RefreshCw,
  Search,
  Filter,
  UserCheck,
  Building2,
  Laptop,
  MessageSquare,
  ArrowRight,
  Send,
  Eye,
  Check,
  X,
  Flame,
  AlertCircle,
  FileText,
  Users,
  ChevronRight,
  TrendingUp,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  ticketsApi,
  TicketItem,
  TicketSummary,
  TechnicianItem,
  TicketPriority,
  TicketStatus,
  TicketEventItem,
} from '@/features/sarpras/tickets-api';
import { useAuth } from '@/providers/auth-provider';

import { hasPermission } from '@/lib/rbac/evaluator';

export default function HelpdeskTicketsPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<'kanban' | 'tabel' | 'teknisi'>('kanban');

  // =========================================================================
  // DATA STATE
  // =========================================================================
  const [tickets, setTickets] = useState<TicketItem[]>([]);
  const [summary, setSummary] = useState<TicketSummary | null>(null);
  const [technicians, setTechnicians] = useState<TechnicianItem[]>([]);
  const [rooms, setRooms] = useState<Array<{ id: string; name: string; code: string }>>([]);
  const [assets, setAssets] = useState<Array<{ id: string; name: string; asset_code: string }>>([]);

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // =========================================================================
  // FILTER & PENCARIAN
  // =========================================================================
  const [searchQuery, setSearchQuery] = useState('');
  const [priorityFilter, setPriorityFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [technicianFilter, setTechnicianFilter] = useState<string>('all');

  // =========================================================================
  // MODAL STATE
  // =========================================================================
  // 1. Buat Tiket
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [formTitle, setFormTitle] = useState('');
  const [formRoomId, setFormRoomId] = useState('');
  const [formAssetId, setFormAssetId] = useState('');
  const [formPriority, setFormPriority] = useState<TicketPriority>('sedang');
  const [formDescription, setFormDescription] = useState('');
  const [isSubmittingCreate, setIsSubmittingCreate] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  // 2. Disposisi Teknisi
  const [isDisposeModalOpen, setIsDisposeModalOpen] = useState(false);
  const [selectedTicketForDispose, setSelectedTicketForDispose] = useState<TicketItem | null>(null);
  const [disposeAssigneeId, setDisposeAssigneeId] = useState('');
  const [disposeNote, setDisposeNote] = useState('');
  const [isSubmittingDispose, setIsSubmittingDispose] = useState(false);
  const [disposeError, setDisposeError] = useState<string | null>(null);

  // 3. Update Status
  const [isStatusModalOpen, setIsStatusModalOpen] = useState(false);
  const [selectedTicketForStatus, setSelectedTicketForStatus] = useState<TicketItem | null>(null);
  const [targetStatus, setTargetStatus] = useState<TicketStatus>('diproses');
  const [statusNote, setStatusNote] = useState('');
  const [isSubmittingStatus, setIsSubmittingStatus] = useState(false);
  const [statusError, setStatusError] = useState<string | null>(null);

  // 4. Detail & Timeline Komentar
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedTicketForDetail, setSelectedTicketForDetail] = useState<TicketItem | null>(null);
  const [commentNote, setCommentNote] = useState('');
  const [isSubmittingComment, setIsSubmittingComment] = useState(false);
  const [commentError, setCommentError] = useState<string | null>(null);

  // =========================================================================
  // WEWENANG PENGGUNA
  // =========================================================================
  const canManageTickets = useMemo(() => {
    if (!user) return false;
    const userRoles = (user.roles || []) as string[];
    return (
      userRoles.includes('super_admin') ||
      userRoles.includes('facility_staff') ||
      userRoles.includes('admin_tu') ||
      hasPermission(user, ['facility.asset.manage', 'facility.booking.manage'])
    );
  }, [user]);

  // =========================================================================
  // FETCH DATA
  // =========================================================================
  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [ticketsRes, summaryRes] = await Promise.all([
        ticketsApi.getTickets({ per_page: 100 }),
        ticketsApi.getSummary().catch(() => null),
      ]);

      setTickets(ticketsRes.data || []);
      if (summaryRes?.data) {
        setSummary(summaryRes.data);
      }

      // Muat data pendukung jika berwenang
      if (canManageTickets) {
        ticketsApi.getTechnicians().then((res) => {
          if (res.data) setTechnicians(res.data);
        }).catch(() => {});
      }

      ticketsApi.getRooms().then((res) => {
        if (res.data) setRooms(res.data);
      }).catch(() => {});

      ticketsApi.getAssets().then((res) => {
        if (res.data) setAssets(res.data);
      }).catch(() => {});
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat data helpdesk tiket.';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, [canManageTickets]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Auto-dismiss success message
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  // =========================================================================
  // FILTERED TICKETS
  // =========================================================================
  const filteredTickets = useMemo(() => {
    return tickets.filter((t) => {
      const matchSearch =
        searchQuery === '' ||
        t.ticket_number.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (t.title && t.title.toLowerCase().includes(searchQuery.toLowerCase())) ||
        t.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (t.room && t.room.name.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (t.reporter && t.reporter.username.toLowerCase().includes(searchQuery.toLowerCase()));

      const matchPriority = priorityFilter === 'all' || t.priority === priorityFilter;
      const matchStatus = statusFilter === 'all' || t.status === statusFilter;
      const matchTechnician =
        technicianFilter === 'all' ||
        (technicianFilter === 'unassigned' && !t.assignee_id) ||
        t.assignee_id === technicianFilter;

      return matchSearch && matchPriority && matchStatus && matchTechnician;
    });
  }, [tickets, searchQuery, priorityFilter, statusFilter, technicianFilter]);

  // Kanban Columns
  const pendingTickets = useMemo(
    () => filteredTickets.filter((t) => t.status === 'pending'),
    [filteredTickets]
  );
  const inProgressTickets = useMemo(
    () => filteredTickets.filter((t) => t.status === 'diproses'),
    [filteredTickets]
  );
  const resolvedTickets = useMemo(
    () => filteredTickets.filter((t) => t.status === 'selesai'),
    [filteredTickets]
  );

  // =========================================================================
  // HANDLERS
  // =========================================================================

  // 1. Simpan Tiket Baru
  const handleCreateTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formDescription.trim()) {
      setCreateError('Deskripsi kerusakan wajib diisi.');
      return;
    }

    setIsSubmittingCreate(true);
    setCreateError(null);
    try {
      await ticketsApi.createTicket({
        title: formTitle.trim() || undefined,
        room_id: formRoomId || null,
        asset_id: formAssetId || null,
        priority: formPriority,
        description: formDescription.trim(),
      });

      setSuccessMessage('Laporan tiket kerusakan berhasil dikirimkan.');
      setIsCreateModalOpen(false);
      setFormTitle('');
      setFormRoomId('');
      setFormAssetId('');
      setFormPriority('sedang');
      setFormDescription('');
      loadData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal membuat tiket kerusakan.';
      setCreateError(msg);
    } finally {
      setIsSubmittingCreate(false);
    }
  };

  // 2. Disposisi Tiket
  const openDisposeModal = (ticket: TicketItem) => {
    setSelectedTicketForDispose(ticket);
    setDisposeAssigneeId(ticket.assignee_id || '');
    setDisposeNote('');
    setDisposeError(null);
    setIsDisposeModalOpen(true);
  };

  const handleDisposeTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicketForDispose || !disposeAssigneeId) {
      setDisposeError('Silakan pilih teknisi penanggung jawab.');
      return;
    }

    setIsSubmittingDispose(true);
    setDisposeError(null);
    try {
      await ticketsApi.disposeTicket(selectedTicketForDispose.id, {
        assignee_id: disposeAssigneeId,
        note: disposeNote.trim() || undefined,
      });

      setSuccessMessage(`Tiket ${selectedTicketForDispose.ticket_number} berhasil didisposisikan kepada teknisi.`);
      setIsDisposeModalOpen(false);
      setSelectedTicketForDispose(null);
      loadData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mendisposisikan tiket.';
      setDisposeError(msg);
    } finally {
      setIsSubmittingDispose(false);
    }
  };

  // 3. Update Status
  const openStatusModal = (ticket: TicketItem) => {
    setSelectedTicketForStatus(ticket);
    setTargetStatus(ticket.status === 'pending' ? 'diproses' : 'selesai');
    setStatusNote('');
    setStatusError(null);
    setIsStatusModalOpen(true);
  };

  const handleUpdateStatus = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicketForStatus) return;

    if (targetStatus === 'selesai' && !statusNote.trim()) {
      setStatusError('Catatan hasil perbaikan wajib diisi saat menyelesaikan tiket.');
      return;
    }

    setIsSubmittingStatus(true);
    setStatusError(null);
    try {
      await ticketsApi.updateStatus(selectedTicketForStatus.id, {
        status: targetStatus,
        note: statusNote.trim() || undefined,
      });

      setSuccessMessage(`Status tiket ${selectedTicketForStatus.ticket_number} berhasil diubah menjadi ${targetStatus}.`);
      setIsStatusModalOpen(false);
      setSelectedTicketForStatus(null);
      loadData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memperbarui status tiket.';
      setStatusError(msg);
    } finally {
      setIsSubmittingStatus(false);
    }
  };

  // 4. Detail & Komentar
  const openDetailModal = async (ticket: TicketItem) => {
    setSelectedTicketForDetail(ticket);
    setCommentNote('');
    setCommentError(null);
    setIsDetailModalOpen(true);

    try {
      const fullTicketRes = await ticketsApi.getTicket(ticket.id);
      if (fullTicketRes.data) {
        setSelectedTicketForDetail(fullTicketRes.data);
      }
    } catch {
      // Keep existing data if detail fetch fails
    }
  };

  const handleAddComment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicketForDetail || !commentNote.trim()) return;

    setIsSubmittingComment(true);
    setCommentError(null);
    try {
      const commentRes = await ticketsApi.addComment(selectedTicketForDetail.id, {
        note: commentNote.trim(),
      });

      if (commentRes.data) {
        setSelectedTicketForDetail((prev) => {
          if (!prev) return null;
          return {
            ...prev,
            events: [...(prev.events || []), commentRes.data],
          };
        });
      }
      setCommentNote('');
      setSuccessMessage('Komentar berhasil ditambahkan.');
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menambahkan komentar.';
      setCommentError(msg);
    } finally {
      setIsSubmittingComment(false);
    }
  };

  // =========================================================================
  // HELPER FORMATTERS
  // =========================================================================
  const getPriorityBadge = (priority: TicketPriority) => {
    switch (priority) {
      case 'darurat':
        return (
          <Badge className="bg-red-600 hover:bg-red-700 text-white font-semibold flex items-center gap-1 animate-pulse">
            <Flame className="w-3 h-3" /> Darurat
          </Badge>
        );
      case 'tinggi':
        return (
          <Badge className="bg-amber-500 hover:bg-amber-600 text-white font-medium flex items-center gap-1">
            <AlertTriangle className="w-3 h-3" /> Tinggi
          </Badge>
        );
      case 'sedang':
        return (
          <Badge className="bg-blue-600 hover:bg-blue-700 text-white font-medium">
            Sedang
          </Badge>
        );
      case 'rendah':
        return (
          <Badge variant="outline" className="text-gray-700 border-gray-300">
            Rendah
          </Badge>
        );
      default:
        return <Badge variant="outline">{priority}</Badge>;
    }
  };

  const getStatusBadge = (status: TicketStatus) => {
    switch (status) {
      case 'pending':
        return (
          <Badge variant="outline" className="border-amber-400 text-amber-700 bg-amber-50 font-medium flex items-center gap-1">
            <Hourglass className="w-3 h-3" /> Menunggu Disposisi
          </Badge>
        );
      case 'diproses':
        return (
          <Badge className="bg-blue-600 hover:bg-blue-700 text-white font-medium flex items-center gap-1">
            <Clock className="w-3 h-3" /> Sedang Dikerjakan
          </Badge>
        );
      case 'selesai':
        return (
          <Badge className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium flex items-center gap-1">
            <CheckCircle2 className="w-3 h-3" /> Selesai Teratasi
          </Badge>
        );
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  const isSlaBreached = (ticket: TicketItem) => {
    if (ticket.status === 'selesai') return false;
    const createdAt = new Date(ticket.created_at).getTime();
    const now = Date.now();
    const diffHours = (now - createdAt) / (1000 * 60 * 60);
    return diffHours >= 24;
  };

  const formatDate = (dateString?: string | null) => {
    if (!dateString) return '-';
    try {
      const d = new Date(dateString);
      return d.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return dateString;
    }
  };

  return (
    <div className="space-y-6 p-6">
      {/* =================================================================== */}
      {/* HEADER & TOP ACTIONS */}
      {/* =================================================================== */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-5">
        <div>
          <div className="flex items-center gap-2">
            <div className="p-2 bg-blue-100 text-blue-700 rounded-lg">
              <Wrench className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-gray-900">
                Helpdesk Perbaikan & Kerusakan Sarpras
              </h1>
              <p className="text-sm text-gray-500">
                Tiket pelaporan, papan Kanban, penugasan teknisi, dan SLA pemeliharaan sarana prasarana sekolah
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={loadData}
            disabled={isLoading}
            className="flex items-center gap-1.5"
          >
            <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
            Muat Ulang
          </Button>

          <Button
            size="sm"
            onClick={() => {
              setFormTitle('');
              setFormRoomId('');
              setFormAssetId('');
              setFormPriority('sedang');
              setFormDescription('');
              setCreateError(null);
              setIsCreateModalOpen(true);
            }}
            className="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            Lapor Kerusakan Baru
          </Button>
        </div>
      </div>

      {/* =================================================================== */}
      {/* NOTIFIKASI SUCCESS / ERROR BANNER */}
      {/* =================================================================== */}
      {successMessage && (
        <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center justify-between text-sm shadow-sm">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
            <span>{successMessage}</span>
          </div>
          <button
            onClick={() => setSuccessMessage(null)}
            className="text-emerald-600 hover:text-emerald-800"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between text-sm shadow-sm">
          <div className="flex items-center gap-2">
            <AlertCircle className="w-4 h-4 text-red-600 flex-shrink-0" />
            <span>{error}</span>
          </div>
          <Button variant="ghost" size="sm" onClick={loadData} className="text-red-700 hover:bg-red-100">
            Coba Lagi
          </Button>
        </div>
      )}

      {/* =================================================================== */}
      {/* KPI & METRIK RINGKASAN SLA */}
      {/* =================================================================== */}
      {summary && (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <Card className="shadow-xs border-gray-200">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Tiket</p>
              <p className="text-2xl font-bold text-gray-900 mt-1">{summary.total}</p>
              <p className="text-xs text-gray-400 mt-1">Keseluruhan laporan</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-amber-200 bg-amber-50/30">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-amber-700 uppercase tracking-wider">Menunggu</p>
              <p className="text-2xl font-bold text-amber-600 mt-1">{summary.pending}</p>
              <p className="text-xs text-amber-600/80 mt-1">Belum didisposisikan</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-blue-200 bg-blue-50/30">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-blue-700 uppercase tracking-wider">Diproses</p>
              <p className="text-2xl font-bold text-blue-600 mt-1">{summary.diproses}</p>
              <p className="text-xs text-blue-600/80 mt-1">Penanganan teknisi</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-emerald-200 bg-emerald-50/30">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-emerald-700 uppercase tracking-wider">Selesai</p>
              <p className="text-2xl font-bold text-emerald-600 mt-1">{summary.selesai}</p>
              <p className="text-xs text-emerald-600/80 mt-1">Perbaikan tuntas</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-red-200 bg-red-50/30">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-red-700 uppercase tracking-wider">Peringatan SLA</p>
                {summary.sla_warning > 0 && <Flame className="w-4 h-4 text-red-500 animate-bounce" />}
              </div>
              <p className="text-2xl font-bold text-red-600 mt-1">{summary.sla_warning}</p>
              <p className="text-xs text-red-600/80 mt-1">&gt; 24 jam belum selesai</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-gray-200">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Rata-rata Resolusi</p>
              <p className="text-2xl font-bold text-indigo-600 mt-1">{summary.avg_resolution_hours} <span className="text-sm font-normal text-gray-500">jam</span></p>
              <p className="text-xs text-gray-400 mt-1">Durasi perbaikan</p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* =================================================================== */}
      {/* TABS NAVIGATION & SEARCH / FILTER */}
      {/* =================================================================== */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-b pb-3">
        {/* Navigation Tabs */}
        <div className="flex items-center gap-2">
          <Button
            variant={activeTab === 'kanban' ? 'default' : 'ghost'}
            size="sm"
            onClick={() => setActiveTab('kanban')}
            className={activeTab === 'kanban' ? 'bg-blue-600 text-white' : 'text-gray-600'}
          >
            Papan Kanban ({filteredTickets.length})
          </Button>

          <Button
            variant={activeTab === 'tabel' ? 'default' : 'ghost'}
            size="sm"
            onClick={() => setActiveTab('tabel')}
            className={activeTab === 'tabel' ? 'bg-blue-600 text-white' : 'text-gray-600'}
          >
            Tabel Daftar
          </Button>

          {canManageTickets && (
            <Button
              variant={activeTab === 'teknisi' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setActiveTab('teknisi')}
              className={activeTab === 'teknisi' ? 'bg-blue-600 text-white' : 'text-gray-600'}
            >
              Evaluasi Kinerja Teknisi ({technicians.length})
            </Button>
          )}
        </div>

        {/* Search & Filter Bar */}
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative min-w-[200px]">
            <Search className="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <Input
              type="text"
              placeholder="Cari tiket, lokasi, nomor..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-8 h-9 text-xs"
            />
          </div>

          <select
            value={priorityFilter}
            onChange={(e) => setPriorityFilter(e.target.value)}
            className="h-9 px-2.5 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-500"
          >
            <option value="all">Semua Prioritas</option>
            <option value="darurat">Darurat</option>
            <option value="tinggi">Tinggi</option>
            <option value="sedang">Sedang</option>
            <option value="rendah">Rendah</option>
          </select>

          {activeTab === 'tabel' && (
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-9 px-2.5 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="all">Semua Status</option>
              <option value="pending">Menunggu Disposisi</option>
              <option value="diproses">Sedang Dikerjakan</option>
              <option value="selesai">Selesai Teratasi</option>
            </select>
          )}

          {canManageTickets && (
            <select
              value={technicianFilter}
              onChange={(e) => setTechnicianFilter(e.target.value)}
              className="h-9 px-2.5 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="all">Semua Penugasan</option>
              <option value="unassigned">Belum Didisposisikan</option>
              {technicians.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name} ({t.active_tickets_count} aktif)
                </option>
              ))}
            </select>
          )}
        </div>
      </div>

      {/* =================================================================== */}
      {/* TAB 1: PAPAN KANBAN (ADM-SAR-03) */}
      {/* =================================================================== */}
      {activeTab === 'kanban' && (
        <div>
          {isLoading ? (
            <div className="flex flex-col items-center justify-center py-20 text-gray-500">
              <RefreshCw className="w-8 h-8 animate-spin text-blue-600 mb-2" />
              <p className="text-sm">Memuat papan Kanban tiket perbaikan...</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {/* Kolom 1: Menunggu Disposisi (Pending) */}
              <div className="bg-gray-50/80 rounded-xl p-4 border border-amber-200/80 flex flex-col h-full min-h-[500px]">
                <div className="flex items-center justify-between pb-3 border-b border-amber-200">
                  <div className="flex items-center gap-2">
                    <span className="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <h2 className="font-semibold text-gray-800 text-sm">Menunggu Disposisi</h2>
                  </div>
                  <Badge variant="outline" className="bg-amber-100/60 text-amber-800 border-amber-300">
                    {pendingTickets.length}
                  </Badge>
                </div>

                <div className="mt-4 space-y-3 flex-1 overflow-y-auto">
                  {pendingTickets.length === 0 ? (
                    <div className="text-center py-12 text-gray-400 text-xs border border-dashed rounded-lg">
                      Tidak ada tiket menunggu disposisi.
                    </div>
                  ) : (
                    pendingTickets.map((ticket) => (
                      <KanbanTicketCard
                        key={ticket.id}
                        ticket={ticket}
                        isSlaBreached={isSlaBreached(ticket)}
                        canManage={canManageTickets}
                        onDispose={() => openDisposeModal(ticket)}
                        onUpdateStatus={() => openStatusModal(ticket)}
                        onViewDetail={() => openDetailModal(ticket)}
                        getPriorityBadge={getPriorityBadge}
                        formatDate={formatDate}
                      />
                    ))
                  )}
                </div>
              </div>

              {/* Kolom 2: Sedang Dikerjakan (Diproses) */}
              <div className="bg-gray-50/80 rounded-xl p-4 border border-blue-200/80 flex flex-col h-full min-h-[500px]">
                <div className="flex items-center justify-between pb-3 border-b border-blue-200">
                  <div className="flex items-center gap-2">
                    <span className="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <h2 className="font-semibold text-gray-800 text-sm">Sedang Dikerjakan</h2>
                  </div>
                  <Badge variant="outline" className="bg-blue-100/60 text-blue-800 border-blue-300">
                    {inProgressTickets.length}
                  </Badge>
                </div>

                <div className="mt-4 space-y-3 flex-1 overflow-y-auto">
                  {inProgressTickets.length === 0 ? (
                    <div className="text-center py-12 text-gray-400 text-xs border border-dashed rounded-lg">
                      Tidak ada tiket yang sedang dikerjakan.
                    </div>
                  ) : (
                    inProgressTickets.map((ticket) => (
                      <KanbanTicketCard
                        key={ticket.id}
                        ticket={ticket}
                        isSlaBreached={isSlaBreached(ticket)}
                        canManage={canManageTickets}
                        onDispose={() => openDisposeModal(ticket)}
                        onUpdateStatus={() => openStatusModal(ticket)}
                        onViewDetail={() => openDetailModal(ticket)}
                        getPriorityBadge={getPriorityBadge}
                        formatDate={formatDate}
                      />
                    ))
                  )}
                </div>
              </div>

              {/* Kolom 3: Selesai Teratasi */}
              <div className="bg-gray-50/80 rounded-xl p-4 border border-emerald-200/80 flex flex-col h-full min-h-[500px]">
                <div className="flex items-center justify-between pb-3 border-b border-emerald-200">
                  <div className="flex items-center gap-2">
                    <span className="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <h2 className="font-semibold text-gray-800 text-sm">Selesai Teratasi</h2>
                  </div>
                  <Badge variant="outline" className="bg-emerald-100/60 text-emerald-800 border-emerald-300">
                    {resolvedTickets.length}
                  </Badge>
                </div>

                <div className="mt-4 space-y-3 flex-1 overflow-y-auto">
                  {resolvedTickets.length === 0 ? (
                    <div className="text-center py-12 text-gray-400 text-xs border border-dashed rounded-lg">
                      Belum ada tiket yang berstatus selesai.
                    </div>
                  ) : (
                    resolvedTickets.map((ticket) => (
                      <KanbanTicketCard
                        key={ticket.id}
                        ticket={ticket}
                        isSlaBreached={false}
                        canManage={canManageTickets}
                        onDispose={() => openDisposeModal(ticket)}
                        onUpdateStatus={() => openStatusModal(ticket)}
                        onViewDetail={() => openDetailModal(ticket)}
                        getPriorityBadge={getPriorityBadge}
                        formatDate={formatDate}
                      />
                    ))
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* =================================================================== */}
      {/* TAB 2: TABEL DAFTAR LENGKAP */}
      {/* =================================================================== */}
      {activeTab === 'tabel' && (
        <Card className="shadow-xs border-gray-200">
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-gray-600">
                <thead className="bg-gray-50 text-gray-700 font-semibold border-b border-gray-200 uppercase tracking-wider">
                  <tr>
                    <th className="py-3 px-4">No. Tiket</th>
                    <th className="py-3 px-4">Judul & Masalah</th>
                    <th className="py-3 px-4">Prioritas</th>
                    <th className="py-3 px-4">Lokasi / Aset</th>
                    <th className="py-3 px-4">Pelapor</th>
                    <th className="py-3 px-4">Teknisi</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">Dibuat / SLA</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {filteredTickets.length === 0 ? (
                    <tr>
                      <td colSpan={9} className="py-12 text-center text-gray-400">
                        Tidak ada tiket yang sesuai dengan kriteria filter.
                      </td>
                    </tr>
                  ) : (
                    filteredTickets.map((ticket) => {
                      const breached = isSlaBreached(ticket);
                      return (
                        <tr key={ticket.id} className="hover:bg-gray-50/70 transition-colors">
                          <td className="py-3 px-4 font-mono font-semibold text-gray-900">
                            {ticket.ticket_number}
                          </td>
                          <td className="py-3 px-4 max-w-[240px]">
                            <p className="font-medium text-gray-900 line-clamp-1">{ticket.title || 'Laporan Kerusakan'}</p>
                            <p className="text-gray-500 line-clamp-1 text-[11px]">{ticket.description}</p>
                          </td>
                          <td className="py-3 px-4">{getPriorityBadge(ticket.priority)}</td>
                          <td className="py-3 px-4 text-gray-700">
                            {ticket.room && (
                              <div className="flex items-center gap-1">
                                <Building2 className="w-3 h-3 text-gray-400" />
                                <span>{ticket.room.name}</span>
                              </div>
                            )}
                            {ticket.asset && (
                              <div className="flex items-center gap-1 text-[11px] text-gray-500">
                                <Laptop className="w-3 h-3 text-gray-400" />
                                <span>{ticket.asset.name}</span>
                              </div>
                            )}
                            {!ticket.room && !ticket.asset && <span className="text-gray-400">-</span>}
                          </td>
                          <td className="py-3 px-4 text-gray-800">
                            {ticket.reporter?.username || '-'}
                          </td>
                          <td className="py-3 px-4">
                            {ticket.assignee ? (
                              <div className="flex items-center gap-1 text-blue-700 font-medium">
                                <UserCheck className="w-3 h-3" />
                                <span>{ticket.assignee.username}</span>
                              </div>
                            ) : (
                              <span className="text-gray-400 italic">Belum Ada</span>
                            )}
                          </td>
                          <td className="py-3 px-4">{getStatusBadge(ticket.status)}</td>
                          <td className="py-3 px-4">
                            <div>{formatDate(ticket.created_at)}</div>
                            {breached && (
                              <span className="inline-flex items-center gap-0.5 text-[10px] text-red-600 font-semibold">
                                <Flame className="w-3 h-3" /> SLA Lewat (&gt;24j)
                              </span>
                            )}
                          </td>
                          <td className="py-3 px-4 text-right">
                            <div className="flex items-center justify-end gap-1">
                              {canManageTickets && ticket.status === 'pending' && (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => openDisposeModal(ticket)}
                                  className="h-7 px-2 text-[11px] border-amber-300 text-amber-800 hover:bg-amber-50"
                                >
                                  Disposisi
                                </Button>
                              )}

                              {ticket.status !== 'selesai' && (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => openStatusModal(ticket)}
                                  className="h-7 px-2 text-[11px] border-blue-300 text-blue-800 hover:bg-blue-50"
                                >
                                  Update
                                </Button>
                              )}

                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => openDetailModal(ticket)}
                                className="h-7 px-2 text-[11px] text-gray-600 hover:text-gray-900"
                              >
                                <Eye className="w-3.5 h-3.5 mr-1" />
                                Detail
                              </Button>
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}

      {/* =================================================================== */}
      {/* TAB 3: EVALUASI KINERJA TEKNISI (ADM-SAR-03) */}
      {/* =================================================================== */}
      {activeTab === 'teknisi' && canManageTickets && (
        <div className="space-y-4">
          <Card className="shadow-xs border-gray-200">
            <CardHeader className="pb-3 border-b border-gray-100">
              <CardTitle className="text-base font-semibold text-gray-900 flex items-center gap-2">
                <Users className="w-5 h-5 text-blue-600" />
                Daftar Teknisi & Evaluasi Beban Penugasan
              </CardTitle>
              <CardDescription className="text-xs text-gray-500">
                Pantau kapasitas kerja teknisi sarpras untuk menjaga SLA perbaikan fasilitas sekolah.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs text-gray-600">
                  <thead className="bg-gray-50 text-gray-700 font-semibold border-b border-gray-200 uppercase tracking-wider">
                    <tr>
                      <th className="py-3 px-4">Nama Teknisi</th>
                      <th className="py-3 px-4">Email / Akun</th>
                      <th className="py-3 px-4">Peran</th>
                      <th className="py-3 px-4 text-center">Tiket Aktif (Diproses)</th>
                      <th className="py-3 px-4 text-center">Tiket Selesai</th>
                      <th className="py-3 px-4 text-center">Rata-rata Resolusi</th>
                      <th className="py-3 px-4 text-center">Status Beban</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {technicians.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="py-12 text-center text-gray-400">
                          Belum ada data teknisi terdaftar dalam sistem.
                        </td>
                      </tr>
                    ) : (
                      technicians.map((tech) => {
                        let loadBadge = (
                          <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">
                            Siap Ditugaskan
                          </Badge>
                        );
                        if (tech.active_tickets_count >= 5) {
                          loadBadge = (
                            <Badge className="bg-red-100 text-red-800 border-red-300">
                              Beban Tinggi ({tech.active_tickets_count})
                            </Badge>
                          );
                        } else if (tech.active_tickets_count >= 2) {
                          loadBadge = (
                            <Badge className="bg-amber-100 text-amber-800 border-amber-300">
                              Sedang ({tech.active_tickets_count})
                            </Badge>
                          );
                        }

                        return (
                          <tr key={tech.id} className="hover:bg-gray-50/70 transition-colors">
                            <td className="py-3 px-4 font-semibold text-gray-900">
                              {tech.name}
                            </td>
                            <td className="py-3 px-4 text-gray-600">
                              <div>{tech.email}</div>
                              <div className="text-[11px] text-gray-400">@{tech.username}</div>
                            </td>
                            <td className="py-3 px-4">
                              <Badge variant="outline" className="text-[11px]">
                                {Object.values(tech.roles)[0] || 'Teknisi'}
                              </Badge>
                            </td>
                            <td className="py-3 px-4 text-center font-bold text-blue-600">
                              {tech.active_tickets_count}
                            </td>
                            <td className="py-3 px-4 text-center font-bold text-emerald-600">
                              {tech.completed_tickets_count}
                            </td>
                            <td className="py-3 px-4 text-center text-gray-700">
                              {tech.avg_resolution_hours > 0 ? `${tech.avg_resolution_hours} jam` : '-'}
                            </td>
                            <td className="py-3 px-4 text-center">
                              {loadBadge}
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 1: BUAT TIKET KERUSAKAN BARU */}
      {/* =================================================================== */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <h2 className="font-semibold text-gray-900 text-sm flex items-center gap-2">
                <Wrench className="w-4 h-4 text-blue-600" />
                Lapor Tiket Kerusakan Sarpras
              </h2>
              <button
                onClick={() => setIsCreateModalOpen(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateTicket} className="p-4 space-y-4">
              {createError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
                  {createError}
                </div>
              )}

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Judul Ringkas Masalah / Kerusakan
                </label>
                <Input
                  type="text"
                  placeholder="Contoh: AC Ruang Guru Menetes Air, Lampu Kelas Mati"
                  value={formTitle}
                  onChange={(e) => setFormTitle(e.target.value)}
                  className="text-xs"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">
                    Ruangan / Lokasi (Opsional)
                  </label>
                  <select
                    value={formRoomId}
                    onChange={(e) => setFormRoomId(e.target.value)}
                    className="w-full h-9 px-2 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Pilih Ruangan...</option>
                    {rooms.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name} ({r.code})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">
                    Aset / Barang Terkait (Opsional)
                  </label>
                  <select
                    value={formAssetId}
                    onChange={(e) => setFormAssetId(e.target.value)}
                    className="w-full h-9 px-2 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Pilih Aset...</option>
                    {assets.map((a) => (
                      <option key={a.id} value={a.id}>
                        {a.name} ({a.asset_code})
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Tingkat Urgensi / Prioritas
                </label>
                <div className="grid grid-cols-4 gap-2">
                  {(['rendah', 'sedang', 'tinggi', 'darurat'] as TicketPriority[]).map((p) => (
                    <button
                      key={p}
                      type="button"
                      onClick={() => setFormPriority(p)}
                      className={`py-1.5 px-2 text-xs font-medium rounded-lg border capitalize text-center transition-colors ${
                        formPriority === p
                          ? p === 'darurat'
                            ? 'bg-red-600 text-white border-red-600'
                            : p === 'tinggi'
                            ? 'bg-amber-500 text-white border-amber-500'
                            : p === 'sedang'
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-gray-800 text-white border-gray-800'
                          : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'
                      }`}
                    >
                      {p}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Deskripsi Kerusakan <span className="text-red-500">*</span>
                </label>
                <Textarea
                  placeholder="Jelaskan detail kendala, gejala, dan dampak terhadap KBM..."
                  value={formDescription}
                  onChange={(e) => setFormDescription(e.target.value)}
                  rows={4}
                  className="text-xs"
                  required
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsCreateModalOpen(false)}
                  disabled={isSubmittingCreate}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingCreate}
                  className="bg-blue-600 hover:bg-blue-700 text-white"
                >
                  {isSubmittingCreate ? 'Mengirim Laporan...' : 'Kirim Tiket Kerusakan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 2: DISPOSISI TIKET KE TEKNISI */}
      {/* =================================================================== */}
      {isDisposeModalOpen && selectedTicketForDispose && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <div>
                <h2 className="font-semibold text-gray-900 text-sm">
                  Disposisi Tiket {selectedTicketForDispose.ticket_number}
                </h2>
                <p className="text-[11px] text-gray-500">{selectedTicketForDispose.title || 'Laporan Kerusakan'}</p>
              </div>
              <button
                onClick={() => setIsDisposeModalOpen(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleDisposeTicket} className="p-4 space-y-4">
              {disposeError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
                  {disposeError}
                </div>
              )}

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Pilih Teknisi Penanggung Jawab <span className="text-red-500">*</span>
                </label>
                <select
                  value={disposeAssigneeId}
                  onChange={(e) => setDisposeAssigneeId(e.target.value)}
                  className="w-full h-9 px-2.5 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  required
                >
                  <option value="">Pilih Teknisi...</option>
                  {technicians.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name} — ({t.active_tickets_count} tiket aktif saat ini)
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Catatan Instruksi Penugasan (Opsional)
                </label>
                <Textarea
                  placeholder="Contoh: Tolong cek spare part dan freon terlebih dahulu..."
                  value={disposeNote}
                  onChange={(e) => setDisposeNote(e.target.value)}
                  rows={3}
                  className="text-xs"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsDisposeModalOpen(false)}
                  disabled={isSubmittingDispose}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingDispose}
                  className="bg-amber-600 hover:bg-amber-700 text-white"
                >
                  {isSubmittingDispose ? 'Menyimpan...' : 'Tugaskan Teknisi'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 3: UPDATE STATUS TIKET */}
      {/* =================================================================== */}
      {isStatusModalOpen && selectedTicketForStatus && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <div>
                <h2 className="font-semibold text-gray-900 text-sm">
                  Update Status Tiket {selectedTicketForStatus.ticket_number}
                </h2>
                <p className="text-[11px] text-gray-500">{selectedTicketForStatus.title || 'Laporan Kerusakan'}</p>
              </div>
              <button
                onClick={() => setIsStatusModalOpen(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleUpdateStatus} className="p-4 space-y-4">
              {statusError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
                  {statusError}
                </div>
              )}

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Status Baru <span className="text-red-500">*</span>
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setTargetStatus('diproses')}
                    className={`py-2 px-3 text-xs font-medium rounded-lg border text-center transition-colors ${
                      targetStatus === 'diproses'
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'
                    }`}
                  >
                    Sedang Dikerjakan
                  </button>

                  <button
                    type="button"
                    onClick={() => setTargetStatus('selesai')}
                    className={`py-2 px-3 text-xs font-medium rounded-lg border text-center transition-colors ${
                      targetStatus === 'selesai'
                        ? 'bg-emerald-600 text-white border-emerald-600'
                        : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100'
                    }`}
                  >
                    Selesai Teratasi
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Catatan Progres / Hasil Perbaikan {targetStatus === 'selesai' && <span className="text-red-500">*</span>}
                </label>
                <Textarea
                  placeholder={
                    targetStatus === 'selesai'
                      ? 'Jelaskan tindakan perbaikan yang telah dilakukan dan komponen yang diganti...'
                      : 'Catatan progres pengerjaan atau kendala yang dihadapi...'
                  }
                  value={statusNote}
                  onChange={(e) => setStatusNote(e.target.value)}
                  rows={3}
                  className="text-xs"
                  required={targetStatus === 'selesai'}
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsStatusModalOpen(false)}
                  disabled={isSubmittingStatus}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingStatus}
                  className={
                    targetStatus === 'selesai'
                      ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                      : 'bg-blue-600 hover:bg-blue-700 text-white'
                  }
                >
                  {isSubmittingStatus ? 'Menyimpan...' : 'Simpan Status'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 4: DETAIL LENGKAP & TIMELINE RIWAYAT */}
      {/* =================================================================== */}
      {isDetailModalOpen && selectedTicketForDetail && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border">
            {/* Modal Header */}
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono font-bold text-gray-900 text-sm">
                    {selectedTicketForDetail.ticket_number}
                  </span>
                  {getPriorityBadge(selectedTicketForDetail.priority)}
                  {getStatusBadge(selectedTicketForDetail.status)}
                </div>
                <h3 className="font-semibold text-gray-800 text-base mt-1">
                  {selectedTicketForDetail.title || 'Laporan Kerusakan'}
                </h3>
              </div>
              <button
                onClick={() => setIsDetailModalOpen(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-5 overflow-y-auto space-y-6 flex-1">
              {/* Info Detail Grid */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-gray-50 p-3 rounded-lg border text-xs">
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Pelapor</p>
                  <p className="font-semibold text-gray-800 mt-0.5">
                    {selectedTicketForDetail.reporter?.username || '-'}
                  </p>
                </div>
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Teknisi</p>
                  <p className="font-semibold text-blue-700 mt-0.5">
                    {selectedTicketForDetail.assignee?.username || 'Belum Ada'}
                  </p>
                </div>
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Ruangan</p>
                  <p className="font-semibold text-gray-800 mt-0.5">
                    {selectedTicketForDetail.room?.name || '-'}
                  </p>
                </div>
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Aset Terkait</p>
                  <p className="font-semibold text-gray-800 mt-0.5">
                    {selectedTicketForDetail.asset?.name || '-'}
                  </p>
                </div>
              </div>

              {/* Deskripsi Kerusakan */}
              <div>
                <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                  Deskripsi Laporan Kerusakan
                </h4>
                <div className="p-3 bg-white border rounded-lg text-xs text-gray-800 whitespace-pre-wrap leading-relaxed">
                  {selectedTicketForDetail.description}
                </div>
              </div>

              {/* Timeline Riwayat Event */}
              <div>
                <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-3">
                  Riwayat & Progres Penanganan ({selectedTicketForDetail.events?.length || 0})
                </h4>

                <div className="space-y-3 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-gray-200">
                  {(!selectedTicketForDetail.events || selectedTicketForDetail.events.length === 0) ? (
                    <p className="text-xs text-gray-400 italic pl-8">Belum ada riwayat aktivitas.</p>
                  ) : (
                    selectedTicketForDetail.events.map((ev: TicketEventItem) => {
                      let icon = <Clock className="w-3.5 h-3.5 text-blue-600" />;
                      let bgCircle = 'bg-blue-100 border-blue-300';
                      let title = 'Progres Pengerjaan';

                      if (ev.event_type === 'lapor') {
                        icon = <Wrench className="w-3.5 h-3.5 text-amber-600" />;
                        bgCircle = 'bg-amber-100 border-amber-300';
                        title = 'Laporan Dibuat';
                      } else if (ev.event_type === 'disposisi') {
                        icon = <UserCheck className="w-3.5 h-3.5 text-indigo-600" />;
                        bgCircle = 'bg-indigo-100 border-indigo-300';
                        title = 'Didisposisikan ke Teknisi';
                      } else if (ev.event_type === 'selesai') {
                        icon = <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />;
                        bgCircle = 'bg-emerald-100 border-emerald-300';
                        title = 'Perbaikan Selesai';
                      } else if (ev.event_type === 'komentar') {
                        icon = <MessageSquare className="w-3.5 h-3.5 text-gray-600" />;
                        bgCircle = 'bg-gray-100 border-gray-300';
                        title = 'Catatan Pemantauan';
                      }

                      return (
                        <div key={ev.id} className="relative flex items-start gap-3 pl-8">
                          <div className={`absolute left-1.5 -translate-x-1/2 p-1 rounded-full border ${bgCircle}`}>
                            {icon}
                          </div>
                          <div className="bg-gray-50 border rounded-lg p-3 flex-1 text-xs">
                            <div className="flex items-center justify-between gap-2 mb-1">
                              <span className="font-semibold text-gray-800">{title}</span>
                              <span className="text-[10px] text-gray-400">{formatDate(ev.created_at)}</span>
                            </div>
                            <p className="text-gray-700 whitespace-pre-wrap">{ev.note || '-'}</p>
                            {ev.actor && (
                              <p className="text-[10px] text-gray-400 mt-1.5">
                                Oleh: <span className="font-medium text-gray-600">{ev.actor.username}</span>
                              </p>
                            )}
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>

              {/* Form Tambah Komentar / Catatan */}
              <div className="pt-4 border-t">
                <h4 className="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1.5">
                  <MessageSquare className="w-3.5 h-3.5 text-gray-500" />
                  Tambah Catatan Pemantauan
                </h4>
                {commentError && (
                  <p className="text-xs text-red-600 mb-2">{commentError}</p>
                )}
                <form onSubmit={handleAddComment} className="flex gap-2">
                  <Input
                    type="text"
                    placeholder="Tulis catatan perkembangan atau pertanyaan..."
                    value={commentNote}
                    onChange={(e) => setCommentNote(e.target.value)}
                    className="text-xs flex-1"
                    disabled={isSubmittingComment}
                  />
                  <Button
                    type="submit"
                    size="sm"
                    disabled={isSubmittingComment || !commentNote.trim()}
                    className="bg-blue-600 hover:bg-blue-700 text-white"
                  >
                    <Send className="w-3.5 h-3.5 mr-1" />
                    Kirim
                  </Button>
                </form>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="p-3 border-t bg-gray-50 flex items-center justify-between">
              <div className="flex items-center gap-2">
                {canManageTickets && selectedTicketForDetail.status === 'pending' && (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => {
                      setIsDetailModalOpen(false);
                      openDisposeModal(selectedTicketForDetail);
                    }}
                    className="border-amber-300 text-amber-800 hover:bg-amber-50"
                  >
                    Disposisi Teknisi
                  </Button>
                )}
                {selectedTicketForDetail.status !== 'selesai' && (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => {
                      setIsDetailModalOpen(false);
                      openStatusModal(selectedTicketForDetail);
                    }}
                    className="border-blue-300 text-blue-800 hover:bg-blue-50"
                  >
                    Update Status
                  </Button>
                )}
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setIsDetailModalOpen(false)}
              >
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ===========================================================================
// SUB-KOMPONEN KANBAN CARD
// ===========================================================================
function KanbanTicketCard({
  ticket,
  isSlaBreached,
  canManage,
  onDispose,
  onUpdateStatus,
  onViewDetail,
  getPriorityBadge,
  formatDate,
}: {
  ticket: TicketItem;
  isSlaBreached: boolean;
  canManage: boolean;
  onDispose: () => void;
  onUpdateStatus: () => void;
  onViewDetail: () => void;
  getPriorityBadge: (priority: TicketPriority) => React.ReactNode;
  formatDate: (dateString?: string | null) => string;
}) {
  return (
    <div className="bg-white rounded-lg p-3.5 border shadow-2xs hover:shadow-xs transition-shadow space-y-2.5">
      {/* Header: Ticket Number & Priority */}
      <div className="flex items-center justify-between gap-1">
        <span className="font-mono text-xs font-bold text-gray-900">
          {ticket.ticket_number}
        </span>
        {getPriorityBadge(ticket.priority)}
      </div>

      {/* SLA Alert if breached */}
      {isSlaBreached && (
        <div className="flex items-center gap-1 text-[11px] font-semibold text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-200">
          <Flame className="w-3 h-3 animate-pulse" />
          <span>SLA Terlewati (&gt; 24 jam)</span>
        </div>
      )}

      {/* Title & Description */}
      <div>
        <h4 className="text-xs font-semibold text-gray-900 line-clamp-1">
          {ticket.title || 'Laporan Kerusakan'}
        </h4>
        <p className="text-gray-500 text-[11px] line-clamp-2 mt-0.5 leading-relaxed">
          {ticket.description}
        </p>
      </div>

      {/* Meta: Room / Asset */}
      {(ticket.room || ticket.asset) && (
        <div className="flex flex-wrap items-center gap-1 text-[10px] text-gray-600 bg-gray-50 p-1.5 rounded">
          {ticket.room && (
            <span className="inline-flex items-center gap-1 font-medium">
              <Building2 className="w-3 h-3 text-gray-400" />
              {ticket.room.name}
            </span>
          )}
          {ticket.asset && (
            <span className="inline-flex items-center gap-1 text-gray-500">
              <Laptop className="w-3 h-3 text-gray-400" />
              {ticket.asset.name}
            </span>
          )}
        </div>
      )}

      {/* Footer: Reporter, Technician & Created At */}
      <div className="pt-2 border-t border-gray-100 flex items-center justify-between text-[10px] text-gray-500">
        <div className="flex flex-col">
          <span>Pelapor: <b className="text-gray-700">{ticket.reporter?.username || '-'}</b></span>
          {ticket.assignee ? (
            <span className="text-blue-700 font-medium">Teknisi: {ticket.assignee.username}</span>
          ) : (
            <span className="text-amber-600 italic">Belum didisposisikan</span>
          )}
        </div>
        <span className="text-gray-400">{formatDate(ticket.created_at)}</span>
      </div>

      {/* Actions */}
      <div className="pt-1 flex items-center justify-end gap-1.5">
        {canManage && ticket.status === 'pending' && (
          <Button
            variant="outline"
            size="sm"
            onClick={onDispose}
            className="h-6 px-2 text-[10px] border-amber-300 text-amber-800 hover:bg-amber-50"
          >
            Disposisi
          </Button>
        )}

        {ticket.status !== 'selesai' && (
          <Button
            variant="outline"
            size="sm"
            onClick={onUpdateStatus}
            className="h-6 px-2 text-[10px] border-blue-300 text-blue-800 hover:bg-blue-50"
          >
            Update
          </Button>
        )}

        <Button
          variant="ghost"
          size="sm"
          onClick={onViewDetail}
          className="h-6 px-2 text-[10px] text-gray-600 hover:text-gray-900"
        >
          <Eye className="w-3 h-3 mr-0.5" />
          Detail
        </Button>
      </div>
    </div>
  );
}
