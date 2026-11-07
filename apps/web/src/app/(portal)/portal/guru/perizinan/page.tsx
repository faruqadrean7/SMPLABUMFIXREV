'use client';

import React, { useState } from 'react';
import { 
  FileCheck2, 
  Clock, 
  CheckCircle2, 
  XCircle, 
  Search, 
  Filter, 
  FileText, 
  Eye, 
  AlertCircle, 
  Calendar,
  UserCheck,
  Award,
  HeartPulse,
  Send,
  Building2,
  ExternalLink,
  Download,
  Phone,
  User,
  ChevronRight
} from 'lucide-react';
import { toast } from 'sonner';
import { lmsApi } from '@/lib/api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

interface LeaveRequestItem {
  id: string;
  studentId: string;
  studentName: string;
  nis: string;
  className: string;
  type: 'sakit' | 'izin' | 'dispensasi';
  startDate: string;
  endDate: string;
  reason: string;
  attachmentFileId?: string;
  attachmentName?: string;
  submittedAt: string;
  status: 'menunggu' | 'disetujui' | 'ditolak';
  parentName: string;
  decisionNote?: string;
  avatar_url?: string;
}

export default function PerizinanSiswaPage() {
  const queryClient = useQueryClient();
  const [classFilter, setClassFilter] = useState<string>('ALL');
  const [filterType, setFilterType] = useState<string>('ALL');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedId, setSelectedId] = useState<string>('');
  const [openingFileId, setOpeningFileId] = useState<string | null>(null);

  const { data: apiRequests = [], isLoading, isError } = useQuery({
    queryKey: ['homeroom-leave-requests'],
    queryFn: async () => {
      const res = await lmsApi.getHomeroomLeaveRequests();
      return (res?.data ?? []) as any[];
    },
    staleTime: 1000 * 60 * 2,
  });

  const decideMutation = useMutation({
    mutationFn: ({ id, decision }: { id: string; decision: 'approved' | 'rejected' }) =>
      lmsApi.decideHomeroomLeaveRequest(id, { decision }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['homeroom-leave-requests'] });
    },
  });

  // Normalisasi record API LeaveRequest menjadi bentuk tampilan (sumber data tunggal dari backend).
  const requests: LeaveRequestItem[] = React.useMemo(
    () =>
      (apiRequests as any[]).map((r) => {
        const student = r.student ?? {};
        const rawClasses = student.classes ?? student.class ?? [];
        const className = Array.isArray(rawClasses)
          ? (rawClasses[0]?.name ?? '-')
          : (rawClasses?.name ?? '-');
        const requester = r.requester ?? r.requested_by_user ?? {};
        const attachment = r.attachment_file ?? r.attachmentFile ?? null;
        const rawType = (r.leave_type ?? r.type ?? 'izin') as string;
        const type = (['sakit', 'izin', 'dispensasi'].includes(rawType) ? rawType : 'izin') as LeaveRequestItem['type'];
        const classLabel = `${className}`.startsWith('Kelas') ? `${className}` : `Kelas ${className}`;
        return {
          id: r.id,
          studentId: r.student_id ?? student.id ?? '',
          studentName: student.full_name ?? student.name ?? 'Siswa',
          nis: student.nis ?? '',
          className: classLabel,
          type,
          startDate: `${r.start_date ?? ''}`.slice(0, 10),
          endDate: `${r.end_date ?? r.start_date ?? ''}`.slice(0, 10),
          reason: r.reason ?? '',
          attachmentFileId: r.attachment_file_id ?? attachment?.id ?? undefined,
          attachmentName:
            attachment?.original_name ?? attachment?.name ?? (r.attachment_file_id ? 'Lampiran dokumen' : undefined),
          submittedAt: `${r.created_at ?? ''}`.replace('T', ' ').slice(0, 16),
          status: (r.status ?? 'menunggu') as LeaveRequestItem['status'],
          parentName: requester.full_name ?? requester.name ?? 'Orang tua/wali',
          decisionNote: r.decision_notes ?? undefined,
          avatar_url: student.avatar_url ?? undefined,
        } as LeaveRequestItem;
      }),
    [apiRequests],
  );


  const handleDecision = async (id: string, newStatus: 'disetujui' | 'ditolak') => {
    try {
      await decideMutation.mutateAsync({
        id,
        decision: newStatus === 'disetujui' ? 'approved' : 'rejected',
      });
      toast.success(
        newStatus === 'disetujui'
          ? 'Permohonan izin disetujui! Status presensi KBM siswa otomatis tersinkronisasi.'
          : 'Permohonan izin ditolak.'
      );
    } catch (err: any) {
      toast.error(
        'Gagal memperbarui keputusan izin: ' +
          (err?.response?.data?.error?.message ||
            err?.response?.data?.message ||
            err?.message ||
            'Terjadi kesalahan sistem.')
      );
    }
  };

  // FIX-GURU-07: buka lampiran berkas nyata via resolusi file_id ke tautan ber-otorisasi backend.
  const handleViewAttachment = async (fileId?: string) => {
    if (!fileId) return;
    setOpeningFileId(fileId);
    try {
      const url = await lmsApi.getFileUrl(fileId);
      if (url) {
        window.open(url, '_blank', 'noopener,noreferrer');
      } else {
        toast.error('Tautan berkas tidak tersedia.');
      }
    } catch (err: any) {
      toast.error(
        'Gagal membuka berkas: ' +
          (err?.response?.data?.error?.message || err?.message || 'Berkas tidak dapat diakses.')
      );
    } finally {
      setOpeningFileId(null);
    }
  };

  const filteredRequests = requests.filter(req => {
    if (filterType !== 'ALL' && req.status !== filterType) return false;
    if (classFilter !== 'ALL' && !req.className.toLowerCase().includes(classFilter.toLowerCase())) return false;
    if (searchQuery && !req.studentName.toLowerCase().includes(searchQuery.toLowerCase()) && !req.nis.includes(searchQuery)) return false;
    return true;
  });

  const pendingCount = requests.filter(r => r.status === 'menunggu').length;
  const approvedCount = requests.filter(r => r.status === 'disetujui').length;
  const rejectedCount = requests.filter(r => r.status === 'ditolak').length;

  const activeItem = requests.find(r => r.id === selectedId) || filteredRequests[0] || requests[0];

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── 0. BANNER STATUS PROTOTIPE ─── */}
      <div className="bg-amber-50 border border-amber-200 text-amber-900 text-xs px-3.5 py-2 rounded-xl flex items-center justify-between gap-2 shadow-2xs">
        <div className="flex items-center gap-2">
          <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
          <span>
            <strong>Aplikasi Prototipe (apps/portal)</strong>: Modul ini terhubung ke API LMS (<code>/me/teacher/homeroom/leave-requests</code>). Aplikasi resmi kanonis portal sekolah adalah <code>apps/web</code>.
          </span>
        </div>
        <span className="text-[10px] bg-amber-200/80 text-amber-900 font-bold px-2 py-0.5 rounded shrink-0">
          {apiRequests.length > 0 ? `${apiRequests.length} Data Live API` : 'Mode Demo'}
        </span>
      </div>

      {/* ─── 1. HEADER ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 sm:p-4 shadow-xs shrink-0">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <span className="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
                Modul Perizinan
              </span>
              <span className="text-xs text-slate-500 font-semibold">• Kelas: {classFilter === 'ALL' ? 'Semua Kelas (Prioritas VII-A)' : classFilter}</span>
            </div>
            <h1 className="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">
              Verifikasi Surat Izin & Sakit Siswa
            </h1>
            <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
              Tinjau permohonan ketidakhadiran dari orang tua murid, periksa surat keterangan dokter, dan sinkronkan keputusan ke Presensi KBM.
            </p>
          </div>

          {/* Quick Status Pill */}
          <div className="flex items-center gap-2 bg-amber-600 text-white px-3 py-1.5 rounded-xl shadow-xs shrink-0 font-bold text-xs self-start md:self-auto">
            <Clock className="w-4 h-4 text-amber-200" />
            <span>{pendingCount} Permohonan Menunggu</span>
          </div>
        </div>
      </div>

      {/* ─── 2. EMPAT METRIK REKAP PERIZINAN ─── */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-3 shrink-0">
        <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs">
          <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Total Pengajuan</span>
          <div className="text-xl sm:text-2xl font-extrabold text-slate-900 mt-0.5">
            {requests.length} <span className="text-xs font-bold text-slate-500">Surat</span>
          </div>
          <p className="text-[11px] text-slate-500 mt-0.5 font-medium">Semester Ganjil 2026/2027</p>
        </div>

        <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs border-l-4 border-l-amber-600">
          <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Menunggu Konfirmasi</span>
          <div className="text-xl sm:text-2xl font-extrabold text-amber-700 mt-0.5">
            {pendingCount} <span className="text-xs font-bold text-slate-500">Berkas</span>
          </div>
          <p className="text-[11px] text-amber-800 mt-0.5 font-semibold">Perlu keputusan Walas</p>
        </div>

        <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs border-l-4 border-l-emerald-800">
          <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Telah Disetujui</span>
          <div className="text-xl sm:text-2xl font-extrabold text-emerald-800 mt-0.5">
            {approvedCount} <span className="text-xs font-bold text-slate-500">Surat</span>
          </div>
          <p className="text-[11px] text-emerald-800 mt-0.5 font-semibold">Tersinkron ke Jurnal KBM</p>
        </div>

        <div className="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs border-l-4 border-l-rose-700">
          <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Ditolak / Dibatalkan</span>
          <div className="text-xl sm:text-2xl font-extrabold text-slate-900 mt-0.5">
            {rejectedCount} <span className="text-xs font-bold text-slate-500">Surat</span>
          </div>
          <p className="text-[11px] text-slate-500 mt-0.5 font-medium">Bukti tidak valid</p>
        </div>
      </div>

      {/* ─── 3. MASTER-DETAIL VIEWPORT SPLIT ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 xl:grid-cols-12 gap-3.5 overflow-hidden">
        {/* KOLOM KIRI: DAFTAR PERMOHONAN (5 COLS) */}
        <div className="xl:col-span-5 flex flex-col min-h-0 bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
          {/* Header & Filter Bar */}
          <div className="p-3 border-b border-slate-200/90 bg-slate-50/70 space-y-2 shrink-0">
            <div className="flex items-center gap-2">
              <div className="flex items-center gap-1 bg-white border border-slate-300 rounded-lg px-2 py-1 shrink-0">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Kelas:</span>
                <select
                  aria-label="Filter Kelas Perizinan"
                  id="select-filter-kelas-perizinan"
                  value={classFilter}
                  onChange={(e) => setClassFilter(e.target.value)}
                  className="bg-transparent text-xs font-black text-slate-900 focus:outline-none cursor-pointer"
                >
                  <option value="ALL">Semua Kelas</option>
                  <option value="VII-A">Kelas VII-A (Walas)</option>
                  <option value="VII-B">Kelas VII-B</option>
                  <option value="VIII-A">Kelas VIII-A</option>
                  <option value="IX-A">Kelas IX-A</option>
                </select>
              </div>

              <div className="relative flex-1">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  placeholder="Cari nama atau NIS siswa..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-blue-900 font-medium"
                />
              </div>
            </div>
            <div className="flex items-center gap-1 overflow-x-auto pb-0.5">
              <button
                type="button"
                onClick={() => setFilterType('ALL')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer whitespace-nowrap ${
                  filterType === 'ALL' ? 'bg-blue-900 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                }`}
              >
                Semua ({requests.length})
              </button>
              <button
                type="button"
                onClick={() => setFilterType('menunggu')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer whitespace-nowrap ${
                  filterType === 'menunggu' ? 'bg-amber-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                }`}
              >
                Menunggu ({pendingCount})
              </button>
              <button
                type="button"
                onClick={() => setFilterType('disetujui')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer whitespace-nowrap ${
                  filterType === 'disetujui' ? 'bg-emerald-800 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                }`}
              >
                Disetujui ({approvedCount})
              </button>
              <button
                type="button"
                onClick={() => setFilterType('ditolak')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer whitespace-nowrap ${
                  filterType === 'ditolak' ? 'bg-rose-700 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
                }`}
              >
                Ditolak ({rejectedCount})
              </button>
            </div>
          </div>

          {/* List Scroll Area */}
          <div className="flex-1 min-h-0 overflow-y-auto p-2.5 space-y-2">
            {isLoading ? (
              <div className="p-8 text-center text-slate-500">
                <Clock className="w-8 h-8 mx-auto text-slate-300 mb-2 animate-pulse" />
                <p className="text-xs font-bold text-slate-700">Memuat permohonan izin...</p>
              </div>
            ) : isError ? (
              <div className="p-8 text-center text-rose-600">
                <AlertCircle className="w-8 h-8 mx-auto text-rose-300 mb-2" />
                <p className="text-xs font-bold">Gagal memuat data perizinan</p>
                <p className="text-[11px] text-slate-400 mt-0.5">Periksa koneksi atau coba muat ulang halaman.</p>
              </div>
            ) : filteredRequests.length === 0 ? (
              <div className="p-8 text-center text-slate-500">
                <UserCheck className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                <p className="text-xs font-bold text-slate-700">Tidak ada permohonan ditemukan</p>
                <p className="text-[11px] text-slate-400 mt-0.5">Belum ada pengajuan izin, atau sesuaikan kata kunci / filter status.</p>
              </div>
            ) : (
              filteredRequests.map((item) => {
                const isSelected = activeItem?.id === item.id;
                const isPending = item.status === 'menunggu';
                const isApproved = item.status === 'disetujui';

                const TypeIcon = 
                  item.type === 'sakit' ? HeartPulse : 
                  item.type === 'dispensasi' ? Award : FileText;

                return (
                  <div
                    key={item.id}
                    onClick={() => setSelectedId(item.id)}
                    className={`p-3 rounded-xl border transition-all cursor-pointer ${
                      isSelected
                        ? 'border-blue-900 bg-blue-50/50 shadow-xs ring-1 ring-blue-900'
                        : 'border-slate-200/80 bg-white hover:bg-slate-50/80 hover:border-slate-300'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2.5">
                        {item.avatar_url ? (
                          <img
                            src={item.avatar_url}
                            alt={item.studentName}
                            className="w-9 h-9 rounded-xl object-cover border border-slate-200 shrink-0"
                          />
                        ) : (
                          <div className="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0">
                            <User className="w-4 h-4 text-slate-600" />
                          </div>
                        )}
                        <div>
                          <div className="flex items-center gap-1.5">
                            <h4 className="text-xs font-bold text-slate-900 line-clamp-1">{item.studentName}</h4>
                            <span className="font-mono text-[10px] text-slate-500">({item.nis})</span>
                          </div>
                          <p className="text-[11px] text-slate-500 mt-0.5">
                            {item.startDate === item.endDate ? item.startDate : `${item.startDate} s/d ${item.endDate}`}
                          </p>
                        </div>
                      </div>

                      {/* Status Tag */}
                      <div className="shrink-0">
                        {isPending && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-600 text-white shadow-2xs">
                            Menunggu
                          </span>
                        )}
                        {isApproved && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-800 text-white shadow-2xs">
                            Disetujui
                          </span>
                        )}
                        {item.status === 'ditolak' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-700 text-white shadow-2xs">
                            Ditolak
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                      <span className={`font-bold flex items-center gap-1 ${
                        item.type === 'sakit' ? 'text-amber-800' : item.type === 'dispensasi' ? 'text-blue-900' : 'text-emerald-800'
                      }`}>
                        <TypeIcon className="w-3.5 h-3.5" />
                        <span className="uppercase text-[10px]">{item.type}</span>
                      </span>
                      <span className="text-slate-400 line-clamp-1 text-[11px] max-w-45 text-right">
                        Oleh: {item.parentName.split(' ')[0]}
                      </span>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>

        {/* KOLOM KANAN: DETAIL PERMOHONAN AKTIF (7 COLS) */}
        <div className="xl:col-span-7 flex flex-col min-h-0 bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
          {activeItem ? (
            <>
              {/* Header Panel Kanan */}
              <div className="p-3.5 sm:p-4 border-b border-slate-200/90 bg-slate-50/70 shrink-0">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div className="flex items-center gap-3">
                    {activeItem.avatar_url ? (
                      <img
                        src={activeItem.avatar_url}
                        alt={activeItem.studentName}
                        className="w-12 h-12 rounded-xl object-cover border border-slate-200 shadow-2xs shrink-0"
                      />
                    ) : (
                      <div className="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0">
                        <User className="w-6 h-6 text-slate-600" />
                      </div>
                    )}
                    <div>
                      <div className="flex items-center gap-2">
                        <h2 className="text-sm sm:text-base font-extrabold text-slate-900 tracking-tight">
                          {activeItem.studentName}
                        </h2>
                        <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-900 text-white">
                          {activeItem.className}
                        </span>
                      </div>
                      <p className="text-xs text-slate-600 mt-0.5">
                        NIS: <span className="font-mono font-bold text-slate-800">{activeItem.nis}</span> • Diajukan: {activeItem.submittedAt}
                      </p>
                    </div>
                  </div>

                  {/* Status Pill Header */}
                  <div>
                    {activeItem.status === 'menunggu' && (
                      <span className="px-3 py-1 rounded-lg text-xs font-bold bg-amber-600 text-white shadow-2xs flex items-center gap-1.5">
                        <Clock className="w-3.5 h-3.5 text-amber-200" />
                        <span>Menunggu Verifikasi</span>
                      </span>
                    )}
                    {activeItem.status === 'disetujui' && (
                      <span className="px-3 py-1 rounded-lg text-xs font-bold bg-emerald-800 text-white shadow-2xs flex items-center gap-1.5">
                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-200" />
                        <span>Disetujui</span>
                      </span>
                    )}
                    {activeItem.status === 'ditolak' && (
                      <span className="px-3 py-1 rounded-lg text-xs font-bold bg-rose-700 text-white shadow-2xs flex items-center gap-1.5">
                        <XCircle className="w-3.5 h-3.5 text-rose-200" />
                        <span>Ditolak</span>
                      </span>
                    )}
                  </div>
                </div>
              </div>

              {/* Body Detail Scrollable */}
              <div className="flex-1 min-h-0 overflow-y-auto p-4 space-y-3.5">
                {/* Ringkasan Parameter */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                  <div className="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Kategori Izin</span>
                    <span className="font-bold text-slate-900 text-xs uppercase flex items-center gap-1.5">
                      {activeItem.type === 'sakit' && <HeartPulse className="w-4 h-4 text-amber-600" />}
                      {activeItem.type === 'dispensasi' && <Award className="w-4 h-4 text-blue-900" />}
                      {activeItem.type === 'izin' && <FileText className="w-4 h-4 text-emerald-800" />}
                      {activeItem.type}
                    </span>
                  </div>

                  <div className="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Rentang Tanggal</span>
                    <span className="font-bold text-slate-900 font-mono text-xs block">
                      {activeItem.startDate === activeItem.endDate ? activeItem.startDate : `${activeItem.startDate} s/d ${activeItem.endDate}`}
                    </span>
                  </div>

                  <div className="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Pemohon / Kontak</span>
                    <span className="font-bold text-slate-900 text-xs block truncate">
                      {activeItem.parentName}
                    </span>
                  </div>
                </div>

                {/* Alasan & Keterangan */}
                <div className="p-3.5 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1.5">
                  <span className="text-[10px] font-bold text-slate-500 uppercase block">Alasan Permohonan Ketidakhadiran</span>
                  <p className="text-xs text-slate-800 font-medium leading-relaxed bg-white p-3 rounded-lg border border-slate-200">
                    "{activeItem.reason}"
                  </p>
                </div>

                {/* Berkas Lampiran Surat / Bukti */}
                <div className="p-3.5 bg-slate-50 border border-slate-200/80 rounded-xl space-y-2">
                  <span className="text-[10px] font-bold text-slate-500 uppercase block">Lampiran Dokumen Resmi</span>
                  {activeItem.attachmentFileId ? (
                    <div className="flex items-center justify-between p-3 bg-white rounded-lg border border-slate-200">
                      <div className="flex items-center gap-2.5">
                        <div className="w-9 h-9 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center shrink-0">
                          <FileText className="w-5 h-5 text-blue-900" />
                        </div>
                        <div>
                          <p className="text-xs font-bold text-slate-900 line-clamp-1">{activeItem.attachmentName}</p>
                          <span className="text-[10px] text-slate-500 font-medium">Dokumen Surat Keterangan / Tugas Resmi</span>
                        </div>
                      </div>
                      <button
                        type="button"
                        onClick={() => handleViewAttachment(activeItem.attachmentFileId)}
                        disabled={openingFileId === activeItem.attachmentFileId}
                        className="px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 transition-colors flex items-center gap-1.5 shrink-0 cursor-pointer shadow-2xs disabled:opacity-50 disabled:cursor-wait"
                      >
                        <Eye className="w-3.5 h-3.5" />
                        <span>{openingFileId === activeItem.attachmentFileId ? 'Membuka...' : 'Lihat Berkas'}</span>
                      </button>
                    </div>
                  ) : (
                    <p className="text-xs text-slate-500 italic bg-white p-3 rounded-lg border border-slate-200">
                      Tidak ada lampiran berkas yang diunggah oleh orang tua.
                    </p>
                  )}
                </div>

                {/* Riwayat Keputusan Walas jika sudah diproses */}
                {activeItem.decisionNote && (
                  <div className="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-1">
                    <span className="text-[10px] font-bold text-slate-500 uppercase block">Catatan Keputusan Walas:</span>
                    <p className="font-semibold text-slate-800">{activeItem.decisionNote}</p>
                  </div>
                )}
              </div>

              {/* Action Bar / Decision Footer */}
              <div className="p-3.5 border-t border-slate-200/90 bg-slate-50/70 flex flex-wrap items-center justify-between gap-2 shrink-0">
                <button
                  type="button"
                  onClick={() => toast.info(`Menghubungi orang tua siswa: ${activeItem.parentName} via WhatsApp`)}
                  className="px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-300 text-slate-700 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer"
                >
                  <Phone className="w-3.5 h-3.5 text-slate-600" />
                  <span>Hubungi Wali Siswa</span>
                </button>

                {activeItem.status === 'menunggu' ? (
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => handleDecision(activeItem.id, 'ditolak')}
                      className="px-3.5 py-1.5 rounded-xl text-xs font-bold border border-rose-300 text-rose-700 hover:bg-rose-50 cursor-pointer transition-colors"
                    >
                      Tolak
                    </button>
                    <button
                      type="button"
                      onClick={() => handleDecision(activeItem.id, 'disetujui')}
                      className="px-4 py-1.5 rounded-xl text-xs font-bold bg-emerald-800 hover:bg-emerald-900 text-white flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors"
                    >
                      <CheckCircle2 className="w-4 h-4 text-white" />
                      <span>Setujui & Sinkron ke KBM</span>
                    </button>
                  </div>
                ) : (
                  <div className="text-xs font-bold text-slate-600 flex items-center gap-1.5">
                    <CheckCircle2 className="w-4 h-4 text-emerald-800" />
                    <span>Status telah diperbarui</span>
                  </div>
                )}
              </div>
            </>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400">
              <FileCheck2 className="w-12 h-12 text-slate-300 mb-2" />
              <p className="text-sm font-bold text-slate-700">Pilih salah satu permohonan</p>
              <p className="text-xs text-slate-500 mt-0.5">Klik pada daftar di sebelah kiri untuk melihat rincian.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
