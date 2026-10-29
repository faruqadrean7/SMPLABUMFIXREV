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
  attachmentUrl?: string;
  attachmentName?: string;
  submittedAt: string;
  status: 'menunggu' | 'disetujui' | 'ditolak';
  parentName: string;
  decisionNote?: string;
  avatar_url?: string;
}

export default function PerizinanSiswaPage() {
  const [classFilter, setClassFilter] = useState<string>('ALL');
  const [filterType, setFilterType] = useState<string>('ALL');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedId, setSelectedId] = useState<string>('lr-101');

  const [requests, setRequests] = useState<LeaveRequestItem[]>([
    {
      id: 'lr-101',
      studentId: 'std-260705',
      studentName: 'Dimas Anggara Saputra',
      avatar_url: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=150',
      nis: '260705',
      className: 'Kelas VII-A',
      type: 'sakit',
      startDate: '2026-10-21',
      endDate: '2026-10-22',
      reason: 'Demam tinggi dan flu batuk, istirahat di rumah sesuai anjuran dokter.',
      attachmentUrl: '#',
      attachmentName: 'Surat_Keterangan_Sakit_Klinik_UM.pdf',
      submittedAt: '2026-10-21 06:30 WIB',
      status: 'menunggu',
      parentName: 'Bpk. Anggara Saputra (Ayah)',
    },
    {
      id: 'lr-102',
      studentId: 'std-260707',
      studentName: 'Gita Amanda Sari',
      avatar_url: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&q=80&w=150',
      nis: '260707',
      className: 'Kelas VII-A',
      type: 'dispensasi',
      startDate: '2026-10-22',
      endDate: '2026-10-23',
      reason: 'Mewakili sekolah pada Kejuaraan Renang Pelajar Tingkat Kota Malang.',
      attachmentUrl: '#',
      attachmentName: 'Surat_Dispensasi_KONI_Malang.pdf',
      submittedAt: '2026-10-20 15:40 WIB',
      status: 'menunggu',
      parentName: 'Ibu Amanda (Ibu)',
    },
    {
      id: 'lr-001',
      studentId: 'std-240907',
      studentName: 'Dimas Anggara Putra',
      avatar_url: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=150',
      nis: '240907',
      className: 'Kelas IX-A',
      type: 'sakit',
      startDate: '2026-10-14',
      endDate: '2026-10-15',
      reason: 'Demam tinggi dan radang tenggorokan. Istirahat dokter 2 hari.',
      attachmentUrl: '#',
      attachmentName: 'Surat_Dokter_Klinik_UM_Dimas.pdf',
      submittedAt: '2026-10-14 06:45 WIB',
      status: 'menunggu',
      parentName: 'Bambang Sudarmono (Ayah)',
    },
    {
      id: 'lr-002',
      studentId: 'std-240914',
      studentName: 'Karina Safitri',
      avatar_url: 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&q=80&w=150',
      nis: '240914',
      className: 'Kelas IX-A',
      type: 'dispensasi',
      startDate: '2026-10-14',
      endDate: '2026-10-14',
      reason: 'Mewakili sekolah pada babak penyisihan Olimpiade Sains Nasional (OSN) Bidang Informatika Tingkat Kota Malang.',
      attachmentUrl: '#',
      attachmentName: 'Surat_Tugas_OSN_Informatika_Karina.pdf',
      submittedAt: '2026-10-13 14:10 WIB',
      status: 'menunggu',
      parentName: 'H. Suryanto (Ayah)',
    },
    {
      id: 'lr-003',
      studentId: 'std-240922',
      studentName: 'Salma Nuraini',
      avatar_url: 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&q=80&w=150',
      nis: '240922',
      className: 'Kelas IX-A',
      type: 'izin',
      startDate: '2026-10-09',
      endDate: '2026-10-09',
      reason: 'Menghadiri prosesi akad nikah kakak kandung di luar kota.',
      attachmentUrl: '#',
      attachmentName: 'Surat_Izin_Keluarga_Salma.pdf',
      submittedAt: '2026-10-08 19:20 WIB',
      status: 'disetujui',
      parentName: 'Ibu Ratna (Ibu)',
      decisionNote: 'Disetujui. Tugas praktikum mandiri telah diserahkan ke guru pengampu.'
    },
    {
      id: 'lr-004',
      studentId: 'std-240903',
      studentName: 'Rizky Pratama Wijaya',
      avatar_url: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150',
      nis: '240903',
      className: 'Kelas IX-A',
      type: 'sakit',
      startDate: '2026-10-05',
      endDate: '2026-10-06',
      reason: 'Pemeriksaan rawat jalan dan observasi gejala tifus di RS Lavalette.',
      attachmentUrl: '#',
      attachmentName: 'Surat_Keterangan_RS_Lavalette.pdf',
      submittedAt: '2026-10-05 07:15 WIB',
      status: 'disetujui',
      parentName: 'Ir. Hendro Wijaya (Ayah)',
      decisionNote: 'Disetujui. Siswa dipersilakan mengikuti KBM daring asinkron.'
    },
    {
      id: 'lr-005',
      studentId: 'std-240902',
      studentName: 'Nabila Putri Andini',
      avatar_url: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150',
      nis: '240902',
      className: 'Kelas IX-A',
      type: 'dispensasi',
      startDate: '2026-09-28',
      endDate: '2026-09-29',
      reason: 'Lomba Debat Bahasa Inggris English Proficiency antar SMP Labschool Se-Jawa Timur.',
      attachmentUrl: '#',
      attachmentName: 'Surat_Tugas_Debat_Jatim.pdf',
      submittedAt: '2026-09-27 11:30 WIB',
      status: 'disetujui',
      parentName: 'Dra. Endang Sulastri (Ibu)',
      decisionNote: 'Disetujui resmi sebagai delegasi sekolah.'
    }
  ]);

  const handleDecision = (id: string, newStatus: 'disetujui' | 'ditolak') => {
    setRequests(prev => prev.map(item => {
      if (item.id === id) {
        return {
          ...item,
          status: newStatus,
          decisionNote: newStatus === 'disetujui' ? 'Disetujui oleh Wali Kelas (Drs. Budi Santoso)' : 'Ditolak: lampiran bukti kurang lengkap'
        };
      }
      return item;
    }));

    toast.success(
      newStatus === 'disetujui'
        ? 'Permohonan izin disetujui! Status presensi KBM siswa otomatis tersinkronisasi.'
        : 'Permohonan izin ditolak.'
    );
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
            {filteredRequests.length === 0 ? (
              <div className="p-8 text-center text-slate-500">
                <UserCheck className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                <p className="text-xs font-bold text-slate-700">Tidak ada permohonan ditemukan</p>
                <p className="text-[11px] text-slate-400 mt-0.5">Sesuaikan kata kunci pencarian atau filter status.</p>
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
                  {activeItem.attachmentName ? (
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
                        onClick={() => toast.success(`Membuka berkas dokumen: ${activeItem.attachmentName}`)}
                        className="px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 transition-colors flex items-center gap-1.5 shrink-0 cursor-pointer shadow-2xs"
                      >
                        <Eye className="w-3.5 h-3.5" />
                        <span>Lihat Berkas</span>
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
