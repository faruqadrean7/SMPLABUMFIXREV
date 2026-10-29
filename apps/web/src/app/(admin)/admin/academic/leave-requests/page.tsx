'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { academicApi } from '@/features/academic/academic-api';
import { classesApi } from '@/features/master/classes-api';
import { LeaveRequestDecisionDialog, LeaveRequestDialog } from '@/features/academic/leave-requests';
import {
  LeaveRequestItem,
  LeaveType,
  LeaveStatus,
  LEAVE_TYPE_LABELS,
  LEAVE_TYPE_COLORS,
  LEAVE_STATUS_LABELS,
  LEAVE_STATUS_COLORS,
} from '@/types/academic';
import { SchoolClassItem } from '@/types/master';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { toast } from '@/components/ui/toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  FileCheck2,
  Calendar,
  Clock,
  CheckCircle2,
  XCircle,
  Plus,
  Search,
  Filter,
  RotateCw,
  ExternalLink,
  Paperclip,
  GraduationCap,
  ListFilter,
  HelpCircle,
  ShieldCheck,
  Check,
  AlertCircle,
} from 'lucide-react';

export default function LeaveRequestsPage() {
  const queryClient = useQueryClient();

  // Tab State: 'menunggu' | 'disetujui' | 'ditolak' | 'semua'
  const [activeTab, setActiveTab] = useState<'menunggu' | 'disetujui' | 'ditolak' | 'semua'>('menunggu');

  // Filter States
  const [search, setSearch] = useState<string>('');
  const [classId, setClassId] = useState<string>('');
  const [leaveType, setLeaveType] = useState<string>('');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const perPage = 15;

  // Dialog States
  const [submitDialogOpen, setSubmitDialogOpen] = useState<boolean>(false);
  const [decisionDialogOpen, setDecisionDialogOpen] = useState<boolean>(false);
  const [selectedLeave, setSelectedLeave] = useState<LeaveRequestItem | null>(null);

  // Success Feedback Toast
  const [feedbackMessage, setFeedbackMessage] = useState<string | null>(null);

  // 1. Fetch Classes for filter
  const { data: classes = [] } = useQuery<SchoolClassItem[]>({
    queryKey: ['academic-classes'],
    queryFn: () => classesApi.list({ limit: 100 }).then((res) => res.data),
  });

  // 2. Fetch Leave Requests
  const statusFilter: LeaveStatus | undefined =
    activeTab === 'semua' ? undefined : (activeTab as LeaveStatus);

  const {
    data: leaveData,
    isLoading,
    refetch,
    isFetching,
  } = useQuery({
    queryKey: [
      'academic-leave-requests',
      {
        status: statusFilter,
        class_id: classId || undefined,
        leave_type: (leaveType as LeaveType) || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        q: search || undefined,
        page,
        per_page: perPage,
      },
    ],
    queryFn: () =>
      academicApi.listLeaveRequests({
        status: statusFilter,
        class_id: classId || undefined,
        leave_type: (leaveType as LeaveType) || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        q: search.trim() || undefined,
        page,
        per_page: perPage,
      }),
  });

  // 3. Fetch Overview Stats (counts for summary cards)
  const { data: allLeavesStats } = useQuery({
    queryKey: ['academic-leave-requests-stats', classId],
    queryFn: () =>
      academicApi.listLeaveRequests({
        class_id: classId || undefined,
        per_page: 100,
      }),
  });

  const stats = useMemo(() => {
    const items = allLeavesStats?.data || [];
    let menunggu = 0;
    let disetujui = 0;
    let ditolak = 0;

    items.forEach((item) => {
      if (item.status === 'menunggu') menunggu++;
      else if (item.status === 'disetujui') disetujui++;
      else if (item.status === 'ditolak') ditolak++;
    });

    return {
      total: items.length,
      menunggu,
      disetujui,
      ditolak,
    };
  }, [allLeavesStats]);

  const items = leaveData?.data || [];
  const meta = leaveData?.meta;

  const showFeedback = (msg: string) => {
    setFeedbackMessage(msg);
    setTimeout(() => setFeedbackMessage(null), 5000);
  };

  const handleOpenAttachment = async (fileId: string) => {
    try {
      const res = await academicApi.getFileUrl(fileId);
      if (res.url) {
        window.open(res.url, '_blank', 'noopener,noreferrer');
      }
    } catch {
      toast.error('Gagal memuat tautan berkas lampiran atau berkas telah kedaluwarsa.');
    }
  };

  // Definisi Kolom DataTable
  const columns: ColumnDef<LeaveRequestItem>[] = useMemo(
    () => [
      {
        key: 'student',
        header: 'Siswa & Rombel',
        render: (item: LeaveRequestItem) => {
          const className =
            item.student?.classes?.[0]?.name ||
            (item.student as any)?.class_name ||
            '-';
          return (
            <div className="flex items-center gap-2.5 min-w-[180px]">
              <div className="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs shrink-0">
                {item.student?.full_name?.charAt(0) || 'S'}
              </div>
              <div className="min-w-0">
                <p className="font-semibold text-slate-800 text-xs truncate">
                  {item.student?.full_name || 'Nama Siswa'}
                </p>
                <div className="flex items-center gap-1.5 text-[11px] text-slate-500 mt-0.5">
                  <span>NIS: {item.student?.nis || '-'}</span>
                  <span>•</span>
                  <span className="inline-flex items-center gap-0.5 text-slate-600 font-medium">
                    <GraduationCap className="w-3 h-3 text-slate-400" />
                    {className}
                  </span>
                </div>
              </div>
            </div>
          );
        },
      },
      {
        key: 'leave_type',
        header: 'Jenis Izin',
        render: (item: LeaveRequestItem) => {
          const badgeClass =
            LEAVE_TYPE_COLORS[item.leave_type] || 'bg-slate-100 text-slate-800';
          return (
            <span
              className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${badgeClass}`}
            >
              {LEAVE_TYPE_LABELS[item.leave_type] || item.leave_type}
            </span>
          );
        },
      },
      {
        key: 'start_date',
        header: 'Rentang Waktu',
        render: (item: LeaveRequestItem) => {
          let durationDays = 1;
          try {
            const start = new Date(item.start_date);
            const end = new Date(item.end_date);
            const diffTime = Math.abs(end.getTime() - start.getTime());
            durationDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
          } catch {
            durationDays = 1;
          }

          return (
            <div className="text-xs">
              <div className="font-medium text-slate-800">
                {item.start_date}
                {item.start_date !== item.end_date && ` s.d. ${item.end_date}`}
              </div>
              <span className="inline-block px-1.5 py-0.5 mt-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-medium">
                {durationDays} hari
              </span>
            </div>
          );
        },
      },
      {
        key: 'reason',
        header: 'Alasan & Keterangan',
        render: (item: LeaveRequestItem) => (
          <div className="max-w-xs text-xs text-slate-700 leading-relaxed truncate" title={item.reason}>
            {item.reason}
          </div>
        ),
      },
      {
        key: 'attachment',
        header: 'Lampiran',
        render: (item: LeaveRequestItem) => {
          if (!item.attachment_file_id) {
            return <span className="text-xs text-slate-400 italic">-</span>;
          }
          return (
            <button
              onClick={() => handleOpenAttachment(item.attachment_file_id!)}
              className="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded text-xs font-medium transition-colors"
              title="Buka lampiran berkas surat"
            >
              <Paperclip className="w-3.5 h-3.5" />
              <span>Surat</span>
              <ExternalLink className="w-3 h-3 text-indigo-400" />
            </button>
          );
        },
      },
      {
        key: 'status',
        header: 'Status',
        render: (item: LeaveRequestItem) => {
          const color = LEAVE_STATUS_COLORS[item.status];
          return (
            <span
              className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                color?.badge || 'bg-slate-100 text-slate-800'
              }`}
            >
              {item.status === 'disetujui' && <CheckCircle2 className="w-3 h-3" />}
              {item.status === 'ditolak' && <XCircle className="w-3 h-3" />}
              {item.status === 'menunggu' && <Clock className="w-3 h-3" />}
              {LEAVE_STATUS_LABELS[item.status] || item.status}
            </span>
          );
        },
      },
      {
        key: 'decision_notes',
        header: 'Catatan Keputusan',
        render: (item: LeaveRequestItem) => {
          if (!item.decided_by && !item.decision_notes) {
            return <span className="text-xs text-slate-400">-</span>;
          }
          return (
            <div className="text-xs text-slate-600 max-w-[180px]">
              {item.decision_notes ? (
                <p className="truncate italic text-slate-700" title={item.decision_notes}>
                  &ldquo;{item.decision_notes}&rdquo;
                </p>
              ) : null}
              {item.decisionMaker?.username ? (
                <span className="text-[10px] text-slate-400 block mt-0.5">
                  Oleh: {item.decisionMaker.username}
                </span>
              ) : null}
            </div>
          );
        },
      },
      {
        key: 'actions',
        header: 'Aksi',
        render: (item: LeaveRequestItem) => (
          <div className="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setSelectedLeave(item);
                setDecisionDialogOpen(true);
              }}
              className={
                item.status === 'menunggu'
                  ? 'h-8 px-2.5 text-xs bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100 font-semibold'
                  : 'h-8 px-2.5 text-xs text-slate-600 hover:bg-slate-100'
              }
            >
              <ShieldCheck className="w-3.5 h-3.5 mr-1" />
              {item.status === 'menunggu' ? 'Tinjau & Putuskan' : 'Revisi / Detail'}
            </Button>
          </div>
        ),
      },
    ],
    []
  );

  return (
    <div className="space-y-6 pb-12">
      {/* Toast Feedback */}
      {feedbackMessage && (
        <div className="fixed top-4 right-4 z-50 p-4 bg-emerald-600 text-white rounded-xl shadow-xl flex items-center gap-2 text-sm font-medium animate-in slide-in-from-top-2">
          <Check className="w-4 h-4 shrink-0" />
          <span>{feedbackMessage}</span>
        </div>
      )}

      {/* Header Halaman */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <div className="p-2 bg-indigo-600 text-white rounded-lg">
              <FileCheck2 className="w-5 h-5" />
            </div>
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">
              Permohonan Izin & Sakit Siswa
            </h1>
          </div>
          <p className="text-sm text-slate-500">
            Verifikasi pengajuan dispensasi, izin, dan surat sakit dari wali murid atau siswa oleh wali kelas.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            onClick={() => setSubmitDialogOpen(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs h-9 shadow-sm"
          >
            <Plus className="w-4 h-4 mr-1.5" />
            Ajukan Izin Baru
          </Button>

          <Button
            variant="outline"
            size="sm"
            onClick={() => refetch()}
            disabled={isFetching}
            className="h-9 px-3 text-slate-600 hover:bg-slate-50"
            title="Muat ulang data"
          >
            <RotateCw className={`w-4 h-4 ${isFetching ? 'animate-spin' : ''}`} />
          </Button>
        </div>
      </div>

      {/* Stat Cards Overview */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Menunggu */}
        <div className="p-4 bg-white border border-amber-200 rounded-xl shadow-xs relative overflow-hidden">
          <div className="absolute top-0 right-0 w-16 h-16 bg-amber-50 rounded-bl-full pointer-events-none -mr-4 -mt-4" />
          <div className="flex items-center gap-2 text-xs font-semibold text-amber-700 uppercase tracking-wider mb-1">
            <Clock className="w-4 h-4 text-amber-600" />
            <span>Menunggu Verifikasi</span>
          </div>
          <div className="text-2xl font-bold text-slate-900">{stats.menunggu}</div>
          <p className="text-[11px] text-slate-500 mt-1">Antrean siap diputuskan</p>
        </div>

        {/* Disetujui */}
        <div className="p-4 bg-white border border-emerald-200 rounded-xl shadow-xs relative overflow-hidden">
          <div className="absolute top-0 right-0 w-16 h-16 bg-emerald-50 rounded-bl-full pointer-events-none -mr-4 -mt-4" />
          <div className="flex items-center gap-2 text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">
            <CheckCircle2 className="w-4 h-4 text-emerald-600" />
            <span>Disetujui</span>
          </div>
          <div className="text-2xl font-bold text-slate-900">{stats.disetujui}</div>
          <p className="text-[11px] text-slate-500 mt-1">Sinkron ke presensi gerbang</p>
        </div>

        {/* Ditolak */}
        <div className="p-4 bg-white border border-rose-200 rounded-xl shadow-xs relative overflow-hidden">
          <div className="absolute top-0 right-0 w-16 h-16 bg-rose-50 rounded-bl-full pointer-events-none -mr-4 -mt-4" />
          <div className="flex items-center gap-2 text-xs font-semibold text-rose-700 uppercase tracking-wider mb-1">
            <XCircle className="w-4 h-4 text-rose-600" />
            <span>Ditolak</span>
          </div>
          <div className="text-2xl font-bold text-slate-900">{stats.ditolak}</div>
          <p className="text-[11px] text-slate-500 mt-1">Dengan catatan alasan</p>
        </div>

        {/* Total Pengajuan */}
        <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-xs relative overflow-hidden">
          <div className="absolute top-0 right-0 w-16 h-16 bg-slate-50 rounded-bl-full pointer-events-none -mr-4 -mt-4" />
          <div className="flex items-center gap-2 text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
            <ListFilter className="w-4 h-4 text-slate-500" />
            <span>Total Permohonan</span>
          </div>
          <div className="text-2xl font-bold text-slate-900">{stats.total}</div>
          <p className="text-[11px] text-slate-500 mt-1">Semua status permohonan</p>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="border-b border-slate-200 flex items-center gap-6 text-sm font-semibold">
        <button
          type="button"
          onClick={() => {
            setActiveTab('menunggu');
            setPage(1);
          }}
          className={`pb-3 relative transition-colors ${
            activeTab === 'menunggu'
              ? 'text-indigo-600 border-b-2 border-indigo-600'
              : 'text-slate-500 hover:text-slate-800'
          }`}
        >
          <span>Antrean Menunggu</span>
          {stats.menunggu > 0 && (
            <span className="ml-2 px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded-full">
              {stats.menunggu}
            </span>
          )}
        </button>

        <button
          type="button"
          onClick={() => {
            setActiveTab('disetujui');
            setPage(1);
          }}
          className={`pb-3 relative transition-colors ${
            activeTab === 'disetujui'
              ? 'text-indigo-600 border-b-2 border-indigo-600'
              : 'text-slate-500 hover:text-slate-800'
          }`}
        >
          <span>Riwayat Disetujui</span>
        </button>

        <button
          type="button"
          onClick={() => {
            setActiveTab('ditolak');
            setPage(1);
          }}
          className={`pb-3 relative transition-colors ${
            activeTab === 'ditolak'
              ? 'text-indigo-600 border-b-2 border-indigo-600'
              : 'text-slate-500 hover:text-slate-800'
          }`}
        >
          <span>Riwayat Ditolak</span>
        </button>

        <button
          type="button"
          onClick={() => {
            setActiveTab('semua');
            setPage(1);
          }}
          className={`pb-3 relative transition-colors ${
            activeTab === 'semua'
              ? 'text-indigo-600 border-b-2 border-indigo-600'
              : 'text-slate-500 hover:text-slate-800'
          }`}
        >
          <span>Semua Permohonan</span>
        </button>
      </div>

      {/* Filter Toolbar */}
      <div className="bg-slate-50 p-3.5 rounded-xl border border-slate-200 flex flex-wrap items-center gap-3">
        {/* Search Input */}
        <div className="relative flex-1 min-w-[200px] max-w-xs">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Cari siswa atau NIS..."
            className="pl-9 h-9 text-xs bg-white border-slate-300"
          />
        </div>

        {/* Filter Rombel */}
        <select
          value={classId}
          onChange={(e) => {
            setClassId(e.target.value);
            setPage(1);
          }}
          className="h-9 px-2.5 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-slate-700 min-w-[140px]"
        >
          <option value="">Semua Rombel</option>
          {classes.map((cls) => (
            <option key={cls.id} value={cls.id}>
              {cls.name}
            </option>
          ))}
        </select>

        {/* Filter Jenis Izin */}
        <select
          value={leaveType}
          onChange={(e) => {
            setLeaveType(e.target.value);
            setPage(1);
          }}
          className="h-9 px-2.5 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 text-slate-700 min-w-[130px]"
        >
          <option value="">Semua Jenis Izin</option>
          <option value="sakit">Sakit</option>
          <option value="izin">Izin</option>
          <option value="dispen">Dispensasi</option>
        </select>

        {/* Filter Tanggal */}
        <div className="flex items-center gap-1.5 text-xs text-slate-600">
          <input
            type="date"
            value={startDate}
            onChange={(e) => {
              setStartDate(e.target.value);
              setPage(1);
            }}
            className="h-9 px-2 text-xs bg-white border border-slate-300 rounded-lg"
            title="Mulai tanggal"
          />
          <span>s.d.</span>
          <input
            type="date"
            value={endDate}
            onChange={(e) => {
              setEndDate(e.target.value);
              setPage(1);
            }}
            className="h-9 px-2 text-xs bg-white border border-slate-300 rounded-lg"
            title="Selesai tanggal"
          />
        </div>

        {/* Tombol Reset */}
        {(search || classId || leaveType || startDate || endDate) && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSearch('');
              setClassId('');
              setLeaveType('');
              setStartDate('');
              setEndDate('');
              setPage(1);
            }}
            className="h-9 px-2.5 text-xs text-slate-500 hover:text-slate-800"
          >
            Reset
          </Button>
        )}
      </div>

      {/* Data Table */}
      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
        <DataTable<LeaveRequestItem>
          columns={columns}
          data={items}
          keyExtractor={(item) => item.id}
          isLoading={isLoading}
          emptyTitle={
            activeTab === 'menunggu'
              ? 'Tidak ada permohonan izin yang menunggu verifikasi saat ini.'
              : 'Tidak ditemukan riwayat permohonan izin siswa.'
          }
          emptyDescription="Data pengajuan izin dan surat sakit siswa akan ditampilkan pada tabel ini."
        />

        {/* Paginasi Sederhana */}
        {meta && meta.totalPages > 1 && (
          <div className="px-4 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-600">
            <span>
              Menampilkan {items.length} dari {meta.total} data (Halaman {meta.page} dari{' '}
              {meta.totalPages})
            </span>
            <div className="flex items-center gap-1.5">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="h-8 px-2.5 text-xs"
              >
                Sebelumnya
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= meta.totalPages}
                onClick={() => setPage((p) => p + 1)}
                className="h-8 px-2.5 text-xs"
              >
                Berikutnya
              </Button>
            </div>
          </div>
        )}
      </div>

      {/* Dialog Ajukan Permohonan Izin */}
      <LeaveRequestDialog
        isOpen={submitDialogOpen}
        onClose={() => setSubmitDialogOpen(false)}
        onSuccess={() => {
          queryClient.invalidateQueries({ queryKey: ['academic-leave-requests'] });
          queryClient.invalidateQueries({ queryKey: ['academic-leave-requests-stats'] });
          showFeedback('Permohonan izin siswa berhasil dikirim.');
        }}
      />

      {/* Dialog Keputusan / Review Walas */}
      <LeaveRequestDecisionDialog
        isOpen={decisionDialogOpen}
        onClose={() => {
          setDecisionDialogOpen(false);
          setSelectedLeave(null);
        }}
        leave={selectedLeave}
        onSuccess={() => {
          queryClient.invalidateQueries({ queryKey: ['academic-leave-requests'] });
          queryClient.invalidateQueries({ queryKey: ['academic-leave-requests-stats'] });
          queryClient.invalidateQueries({ queryKey: ['academic-attendances'] });
          showFeedback('Keputusan permohonan izin berhasil disimpan.');
        }}
      />
    </div>
  );
}
