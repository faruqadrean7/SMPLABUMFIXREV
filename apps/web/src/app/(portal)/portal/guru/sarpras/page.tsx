'use client';

import React, { useState } from 'react';
import { 
  Building2, 
  Wrench, 
  Calendar, 
  Clock, 
  CheckCircle2, 
  AlertTriangle, 
  Plus, 
  Send, 
  Search, 
  Laptop, 
  Wifi, 
  Monitor, 
  X,
  ShieldCheck,
  Check,
  AlertCircle
} from 'lucide-react';
import { toast } from 'sonner';
import { lmsApi } from '@/lib/api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

interface BookingRecord {
  id: string;
  facility: string;
  date: string;
  timeSlot: string;
  purpose: string;
  participantCount: number;
  status: 'disetujui' | 'menunggu' | 'ditolak' | 'selesai';
  notes: string;
}

interface TicketRecord {
  id: string;
  ticketNumber: string;
  location: string;
  assetCode: string;
  category: string;
  priority: 'normal' | 'tinggi';
  description: string;
  date: string;
  status: 'menunggu' | 'diproses' | 'selesai';
  technician?: string;
  technicianAvatar?: string;
  resolutionNotes?: string;
}

export default function SarprasGuruPage() {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'booking' | 'tiket'>('booking');

  const { data: apiBookings = [] } = useQuery({
    queryKey: ['facility-bookings'],
    queryFn: () => lmsApi.getFacilityBookings(),
    staleTime: 1000 * 60 * 2,
  });

  const bookingMutation = useMutation({
    mutationFn: (payload: any) => lmsApi.createFacilityBooking(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['facility-bookings'] });
    },
  });

  const ticketMutation = useMutation({
    mutationFn: (payload: any) => lmsApi.createTicket(payload),
  });

  // Booking Form State
  const [isBookingModalOpen, setIsBookingModalOpen] = useState<boolean>(false);
  const [bookingFacility, setBookingFacility] = useState<string>('Lab Komputer 1 (Lantai 2)');
  const [bookingDate, setBookingDate] = useState<string>('2026-10-16');
  const [bookingTimeSlot, setBookingTimeSlot] = useState<string>('07:30 - 09:45 (Jam Ke 1-3)');
  const [bookingPurpose, setBookingPurpose] = useState<string>('Praktikum Evaluasi Pemrograman Web Kelas IX-A');
  const [bookingCount, setBookingCount] = useState<number>(32);
  const [bookingNotes, setBookingNotes] = useState<string>('Mohon pastikan 32 unit PC terhubung jaringan lokal CBT');

  // Ticket Form State
  const [isTicketModalOpen, setIsTicketModalOpen] = useState<boolean>(false);
  const [ticketLocation, setTicketLocation] = useState<string>('Lab Komputer 1 (Lantai 2)');
  const [ticketCategory, setTicketCategory] = useState<string>('Perangkat Komputer PC');
  const [ticketAssetCode, setTicketAssetCode] = useState<string>('PC-LAB1-08');
  const [ticketPriority, setTicketPriority] = useState<'normal' | 'tinggi'>('tinggi');
  const [ticketDescription, setTicketDescription] = useState<string>('PC Meja 08 mengalami blue screen berulang saat booting ke sistem ujian browser.');

  // Mock Bookings
  const [bookings, setBookings] = useState<BookingRecord[]>([
    {
      id: 'bk-1',
      facility: 'Lab Komputer 1 (Lantai 2)',
      date: '2026-10-14',
      timeSlot: '07:30 - 09:45 (Jam Ke 1-3)',
      purpose: 'KBM Tatap Muka Informatika Kelas IX-A',
      participantCount: 32,
      status: 'disetujui',
      notes: 'Disetujui oleh Koordinator Sarpras (Pak Hendra)'
    },
    {
      id: 'bk-2',
      facility: 'Lab Multimedia (Lantai 3)',
      date: '2026-10-14',
      timeSlot: '10:00 - 12:15 (Jam Ke 4-6)',
      purpose: 'Praktikum Desain Antarmuka Kelas IX-B',
      participantCount: 30,
      status: 'disetujui',
      notes: 'Kunci lab dapat diambil di ruang staf IT'
    },
    {
      id: 'bk-3',
      facility: 'Aula Graha Saraswati (Lantai 1)',
      date: '2026-10-20',
      timeSlot: '08:00 - 11:30 (Jam Ke 2-5)',
      purpose: 'Simulasi Geladi Bersih CBT Mandiri Angkatan IX',
      participantCount: 94,
      status: 'menunggu',
      notes: 'Dalam antrean telaah ketersediaan fasilitas'
    }
  ]);

  // Mock Tickets
  const [tickets, setTickets] = useState<TicketRecord[]>([
    {
      id: 'tk-1',
      ticketNumber: 'TKT-2026-0891',
      location: 'Lab Komputer 1 (Lantai 2)',
      assetCode: 'PC-LAB1-08',
      category: 'Perangkat Komputer PC',
      priority: 'tinggi',
      description: 'PC Meja 08 mengalami blue screen berulang saat booting ke sistem ujian browser.',
      date: '2026-10-14',
      status: 'diproses',
      technician: 'Doni Prasetyo (Teknisi Lab)',
      technicianAvatar: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&q=80&w=150',
      resolutionNotes: 'Sedang dilakukan pengecekan modul RAM dan penggantian thermal paste.'
    },
    {
      id: 'tk-2',
      ticketNumber: 'TKT-2026-0742',
      location: 'Ruang Kelas IX-A',
      assetCode: 'PRJ-EPSON-03',
      category: 'Proyektor & Layar',
      priority: 'normal',
      description: 'Lampu proyektor berkedip redup dan kabel HDMI meja guru longgar.',
      date: '2026-10-10',
      status: 'selesai',
      technician: 'Agus Santoso (Staf Sarpras)',
      technicianAvatar: 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&q=80&w=150',
      resolutionNotes: 'Kabel HDMI diganti baru dan filter udara proyektor telah dibersihkan.'
    },
    {
      id: 'tk-3',
      ticketNumber: 'TKT-2026-0618',
      location: 'Ruang Server & Jaringan (Lantai 2)',
      assetCode: 'SW-CISCO-24P',
      category: 'Jaringan & Akses Internet',
      priority: 'tinggi',
      description: 'Port switch ke AP koridor barat mengalami flapping packet loss saat jam istirahat.',
      date: '2026-10-12',
      status: 'diproses',
      technician: 'Rendra Pratama (Network Engineer)',
      technicianAvatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=150',
      resolutionNotes: 'Sfp transceiver port 12 dibersihkan dan firmware switch dijadwalkan upgrade malam ini.'
    }
  ]);

  const handleCreateBooking = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await bookingMutation.mutateAsync({
        facility_id: '00000000-0000-0000-0000-000000000001',
        start_at: `${bookingDate}T08:00:00`,
        end_at: `${bookingDate}T10:00:00`,
        purpose: bookingPurpose,
        attendees_count: Number(bookingCount),
      });
      toast.success(`Pengajuan peminjaman ${bookingFacility} berhasil diajukan!`);
    } catch {
      const newBooking: BookingRecord = {
        id: `bk-${Date.now()}`,
        facility: bookingFacility,
        date: bookingDate,
        timeSlot: bookingTimeSlot,
        purpose: bookingPurpose,
        participantCount: Number(bookingCount),
        status: 'menunggu',
        notes: bookingNotes || 'Menunggu verifikasi tim Sarpras'
      };

      setBookings([newBooking, ...bookings]);
      toast.success(`Pengajuan peminjaman ${bookingFacility} dicatat.`);
    }
    setIsBookingModalOpen(false);
  };

  const handleCreateTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await ticketMutation.mutateAsync({
        title: `Laporan Kerusakan ${ticketAssetCode}`,
        category: ticketCategory,
        priority: ticketPriority,
        description: ticketDescription,
        location: ticketLocation,
      });
      toast.success(`Tiket pelaporan kerusakan ${ticketAssetCode} berhasil diterbitkan!`);
    } catch {
      const newTicket: TicketRecord = {
        id: `tk-${Date.now()}`,
        ticketNumber: `TKT-2026-0${Math.floor(100 + Math.random() * 900)}`,
        location: ticketLocation,
        assetCode: ticketAssetCode,
        category: ticketCategory,
        priority: ticketPriority,
        description: ticketDescription,
        date: new Date().toISOString().slice(0, 10),
        status: 'menunggu'
      };

      setTickets([newTicket, ...tickets]);
      toast.success(`Tiket pelaporan kerusakan ${ticketAssetCode} dicatat.`);
    }
    setIsTicketModalOpen(false);
  };

  return (
    <div className="flex flex-col gap-3 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── 0. BANNER STATUS PROTOTIPE ─── */}
      <div className="bg-amber-50 border border-amber-200 text-amber-900 text-xs px-3.5 py-2 rounded-xl flex items-center justify-between gap-2 shadow-2xs">
        <div className="flex items-center gap-2">
          <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
          <span>
            <strong>Aplikasi Prototipe (apps/portal)</strong>: Modul ini terhubung ke API LMS (<code>/me/teacher/facility-bookings</code> & <code>/tickets</code>). Aplikasi resmi kanonis portal sekolah adalah <code>apps/web</code>.
          </span>
        </div>
        <span className="text-[10px] bg-amber-200/80 text-amber-900 font-bold px-2 py-0.5 rounded shrink-0">
          {apiBookings.length > 0 ? `${apiBookings.length} Data Live API` : 'Mode Demo'}
        </span>
      </div>

      {/* ─── HEADER ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3 sm:p-3.5 shadow-xs shrink-0 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              PORTAL GURU
            </span>
            <span className="text-xs text-slate-500 font-semibold">• Sarana & Prasarana</span>
          </div>
          <h1 className="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
            Peminjaman Ruang Lab & Helpdesk Fasilitas
          </h1>
          <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
            Reservasi peminjaman lab komputer & fasilitas KBM, serta laporkan kendala teknis peralatan/proyektor.
          </p>
        </div>

        {/* Tab Switcher & Action */}
        <div className="flex flex-wrap items-center gap-2 shrink-0">
          <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200">
            <button
              type="button"
              onClick={() => setActiveTab('booking')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'booking' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Building2 className="w-3.5 h-3.5" />
              <span>Pinjam Lab ({bookings.length})</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('tiket')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'tiket' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Wrench className="w-3.5 h-3.5" />
              <span>Lapor Kerusakan ({tickets.length})</span>
            </button>
          </div>

          {activeTab === 'booking' ? (
            <button
              type="button"
              onClick={() => setIsBookingModalOpen(true)}
              className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Reservasi Lab Baru</span>
            </button>
          ) : (
            <button
              type="button"
              onClick={() => setIsTicketModalOpen(true)}
              className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-rose-700 hover:bg-rose-800 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Buka Tiket Laporan</span>
            </button>
          )}
        </div>
      </div>

      {/* ─── TAB 1: PEMINJAMAN RUANG & FASILITAS (BE-SAR-01) ─── */}
      {activeTab === 'booking' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          {/* Status Ketersediaan Ruangan Hari Ini (Grid) */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-emerald-800">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-slate-700">Lab Komputer 1</span>
                <span className="text-[10px] font-black px-2 py-0.5 rounded bg-emerald-800 text-white">
                  TERJADWAL
                </span>
              </div>
              <p className="text-xl font-black text-slate-900 mt-2">Kapasitas: 36 PC</p>
              <p className="text-[11px] text-slate-500 mt-1">Lantai 2 • Terpakai KBM IX-A</p>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-blue-900">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-slate-700">Lab Multimedia</span>
                <span className="text-[10px] font-black px-2 py-0.5 rounded bg-blue-900 text-white">
                  TERJADWAL
                </span>
              </div>
              <p className="text-xl font-black text-slate-900 mt-2">Kapasitas: 32 PC</p>
              <p className="text-[11px] text-slate-500 mt-1">Lantai 3 • Terpakai KBM IX-B</p>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-teal-800">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-slate-700">Lab Bahasa Digital</span>
                <span className="text-[10px] font-black px-2 py-0.5 rounded bg-teal-800 text-white">
                  TERSEDIA
                </span>
              </div>
              <p className="text-xl font-black text-slate-900 mt-2">Kapasitas: 32 PC</p>
              <p className="text-[11px] text-emerald-800 font-bold mt-1">Siap Digunakan KBM</p>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-purple-900">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-slate-700">Aula Graha Saraswati</span>
                <span className="text-[10px] font-black px-2 py-0.5 rounded bg-purple-900 text-white">
                  TERSEDIA
                </span>
              </div>
              <p className="text-xl font-black text-slate-900 mt-2">Kapasitas: 250 Kursi</p>
              <p className="text-[11px] text-emerald-800 font-bold mt-1">Koneksi Proyektor &amp; Audio</p>
            </div>
          </div>

          {/* Bookings Table */}
          <div className="bg-white border border-slate-200/90 rounded-xl overflow-hidden shadow-xs">
            <div className="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between">
              <div>
                <h3 className="font-extrabold text-sm text-slate-900">Riwayat Pengajuan Peminjaman Fasilitas Anda</h3>
                <p className="text-xs text-slate-500">Seluruh pengajuan dipantau langsung oleh penanggung jawab Sarpras sekolah</p>
              </div>
              <span className="text-xs font-bold bg-blue-900 text-white px-3 py-1 rounded-md shadow-xs">
                {bookings.length} Catatan
              </span>
            </div>

            <div className="overflow-x-auto max-h-160 overflow-y-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-200">
                  <tr className="text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-3 px-3 w-12 text-center">No</th>
                    <th className="py-3 px-3 min-w-48">Fasilitas / Ruangan</th>
                    <th className="py-3 px-3 w-32">Tanggal</th>
                    <th className="py-3 px-3 w-44">Sesi Jam</th>
                    <th className="py-3 px-3 min-w-56">Keperluan KBM</th>
                    <th className="py-3 px-2 w-20 text-center">Peserta</th>
                    <th className="py-3 px-3 w-32 text-center">Status</th>
                    <th className="py-3 px-3 min-w-52">Catatan Sarpras</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200/80">
                  {bookings.map((b, idx) => (
                    <tr key={b.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-2.5 px-3 text-center font-bold text-slate-500">{idx + 1}</td>
                      <td className="py-2.5 px-3 font-bold text-slate-900">{b.facility}</td>
                      <td className="py-2.5 px-3 font-mono text-slate-700">{b.date}</td>
                      <td className="py-2.5 px-3 font-medium text-slate-700">{b.timeSlot}</td>
                      <td className="py-2.5 px-3 text-slate-800 font-medium">{b.purpose}</td>
                      <td className="py-2.5 px-2 text-center font-bold text-slate-800">{b.participantCount} Siswa</td>
                      <td className="py-2.5 px-3 text-center">
                        {b.status === 'disetujui' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-800 text-white shadow-xs">
                            DISETUJUI
                          </span>
                        )}
                        {b.status === 'menunggu' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                            MENUNGGU
                          </span>
                        )}
                        {b.status === 'selesai' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-slate-700 text-white shadow-xs">
                            SELESAI
                          </span>
                        )}
                      </td>
                      <td className="py-2.5 px-3 text-slate-600 italic text-[11px]">{b.notes}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ─── TAB 2: HELPDESK KERUSAKAN FASILITAS (BE-SAR-03) ─── */}
      {activeTab === 'tiket' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          {/* KPI Tiket */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3 shrink-0">
            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-blue-900">
              <span className="text-[11px] font-bold text-slate-500 uppercase">Total Laporan Kerusakan</span>
              <span className="text-2xl font-black text-slate-900 block mt-1">{tickets.length} Tiket</span>
              <span className="text-[10px] text-slate-500 mt-0.5 block">Akumulasi Semester Ganjil</span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-amber-600">
              <span className="text-[11px] font-bold text-slate-500 uppercase">Dalam Perbaikan Teknisi</span>
              <span className="text-2xl font-black text-amber-700 block mt-1">
                {tickets.filter(t => t.status === 'diproses').length} Perangkat
              </span>
              <span className="text-[10px] text-amber-800 font-bold mt-0.5 block">Diprioritaskan untuk KBM</span>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-xl p-4 shadow-xs border-l-4 border-l-emerald-800">
              <span className="text-[11px] font-bold text-slate-500 uppercase">Selesai Ditangani</span>
              <span className="text-2xl font-black text-emerald-800 block mt-1">
                {tickets.filter(t => t.status === 'selesai').length} Tiket
              </span>
              <span className="text-[10px] text-emerald-800 font-bold mt-0.5 block">Fasilitas Kembali Normal</span>
            </div>
          </div>

          {/* Tickets Table */}
          <div className="bg-white border border-slate-200/90 rounded-xl overflow-hidden shadow-xs">
            <div className="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between">
              <div>
                <h3 className="font-extrabold text-sm text-slate-900">Daftar Tiket Kendala Fasilitas &amp; Komputer</h3>
                <p className="text-xs text-slate-500">Status penanganan live oleh staf teknisi Sarpras sekolah</p>
              </div>
              <span className="text-xs font-bold bg-rose-700 text-white px-3 py-1 rounded-md shadow-xs">
                {tickets.filter(t => t.status !== 'selesai').length} Menunggu / Diproses
              </span>
            </div>

            <div className="overflow-x-auto max-h-160 overflow-y-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-200">
                  <tr className="text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-3 px-3 w-12 text-center">No</th>
                    <th className="py-3 px-3 w-28">No. Tiket</th>
                    <th className="py-3 px-3 w-36">Kode Aset</th>
                    <th className="py-3 px-3 min-w-44">Lokasi Ruangan</th>
                    <th className="py-3 px-2 w-24 text-center">Urgensi</th>
                    <th className="py-3 px-3 min-w-64">Deskripsi Kerusakan</th>
                    <th className="py-3 px-3 w-32 text-center">Status</th>
                    <th className="py-3 px-3 min-w-56">Catatan Tindakan Teknisi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200/80">
                  {tickets.map((t, idx) => (
                    <tr key={t.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-2.5 px-3 text-center font-bold text-slate-500">{idx + 1}</td>
                      <td className="py-2.5 px-3 font-mono font-bold text-blue-900">{t.ticketNumber}</td>
                      <td className="py-2.5 px-3 font-mono font-semibold text-slate-800">{t.assetCode}</td>
                      <td className="py-2.5 px-3 font-medium text-slate-800">{t.location}</td>
                      <td className="py-2.5 px-2 text-center">
                        {t.priority === 'tinggi' ? (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-rose-700 text-white shadow-xs">
                            DARURAT
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-slate-700 text-white shadow-xs">
                            NORMAL
                          </span>
                        )}
                      </td>
                      <td className="py-2.5 px-3 text-slate-800 font-medium">{t.description}</td>
                      <td className="py-2.5 px-3 text-center">
                        {t.status === 'menunggu' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                            MENUNGGU
                          </span>
                        )}
                        {t.status === 'diproses' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-blue-900 text-white shadow-xs">
                            DIPROSES
                          </span>
                        )}
                        {t.status === 'selesai' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-800 text-white shadow-xs">
                            SELESAI
                          </span>
                        )}
                      </td>
                      <td className="py-2.5 px-3 text-slate-600 text-[11px]">
                        {t.technician ? (
                          <div className="flex items-center gap-2 mb-1">
                            {t.technicianAvatar ? (
                              <img
                                src={t.technicianAvatar}
                                alt={t.technician}
                                className="w-6 h-6 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                              />
                            ) : (
                              <div className="w-6 h-6 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-[10px] shrink-0">
                                {t.technician.charAt(0)}
                              </div>
                            )}
                            <strong className="text-slate-800 font-bold">{t.technician}</strong>
                          </div>
                        ) : (
                          <span className="text-slate-400 italic block mb-1">Menunggu penugasan teknisi</span>
                        )}
                        <span className="text-slate-600 block pl-8 text-[10.5px] leading-relaxed">
                          {t.resolutionNotes || 'Belum ada catatan progres teknisi.'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ─── MODAL 1: FORM RESERVASI RUANG LAB (BE-SAR-01) ─── */}
      {isBookingModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 border border-slate-300">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div>
                <h3 className="font-extrabold text-base text-slate-900">Reservasi Laboratorium / Ruangan KBM</h3>
                <p className="text-xs text-slate-500">Ajukan permohonan ke Koordinator Sarpras Labschool UM</p>
              </div>
              <button
                type="button"
                onClick={() => setIsBookingModalOpen(false)}
                className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateBooking} className="space-y-4 text-xs">
              <div>
                <label className="font-bold text-slate-800 block mb-1">Pilih Fasilitas / Ruangan:</label>
                <select
                  value={bookingFacility}
                  onChange={(e) => setBookingFacility(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-900"
                >
                  <option value="Lab Komputer 1 (Lantai 2)">Lab Komputer 1 (Lantai 2 - 36 PC)</option>
                  <option value="Lab Multimedia (Lantai 3)">Lab Multimedia (Lantai 3 - 32 PC)</option>
                  <option value="Lab Bahasa Digital (Lantai 2)">Lab Bahasa Digital (Lantai 2 - 32 PC)</option>
                  <option value="Aula Graha Saraswati (Lantai 1)">Aula Graha Saraswati (Lantai 1 - 250 Kursi)</option>
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="font-bold text-slate-800 block mb-1">Tanggal Penggunaan:</label>
                  <input
                    type="date"
                    required
                    value={bookingDate}
                    onChange={(e) => setBookingDate(e.target.value)}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold focus:outline-none focus:border-blue-900"
                  />
                </div>
                <div>
                  <label className="font-bold text-slate-800 block mb-1">Jumlah Siswa:</label>
                  <input
                    type="number"
                    min="1"
                    max="100"
                    value={bookingCount}
                    onChange={(e) => setBookingCount(Number(e.target.value))}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold focus:outline-none focus:border-blue-900"
                  />
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-800 block mb-1">Sesi Waktu / Jam Pembelajaran:</label>
                <select
                  value={bookingTimeSlot}
                  onChange={(e) => setBookingTimeSlot(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-900 focus:outline-none focus:border-blue-900"
                >
                  <option value="07:30 - 09:45 (Jam Ke 1-3)">07:30 - 09:45 (Jam Ke 1-3)</option>
                  <option value="10:00 - 12:15 (Jam Ke 4-6)">10:00 - 12:15 (Jam Ke 4-6)</option>
                  <option value="13:00 - 15:15 (Jam Ke 7-9)">13:00 - 15:15 (Jam Ke 7-9)</option>
                </select>
              </div>

              <div>
                <label className="font-bold text-slate-800 block mb-1">Keperluan Pembelajaran / Agenda:</label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Praktikum Pemrograman Web & Evaluasi Sumatif"
                  value={bookingPurpose}
                  onChange={(e) => setBookingPurpose(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:outline-none focus:border-blue-900"
                />
              </div>

              <div>
                <label className="font-bold text-slate-800 block mb-1">Kebutuhan Teknis Tambahan (Opsional):</label>
                <textarea
                  rows={2}
                  placeholder="Contoh: Butuh proyektor aktif dan jaringan LAN terhubung server CBT..."
                  value={bookingNotes}
                  onChange={(e) => setBookingNotes(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:outline-none focus:border-blue-900"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                <button
                  type="button"
                  onClick={() => setIsBookingModalOpen(false)}
                  className="px-3.5 py-2 text-xs font-bold border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-black bg-blue-900 text-white rounded-lg hover:bg-blue-950 shadow-xs cursor-pointer"
                >
                  Kirim Reservasi Lab
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ─── MODAL 2: FORM BUKA TIKET LAPOR KERUSAKAN (BE-SAR-03) ─── */}
      {isTicketModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 border border-slate-300">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div>
                <h3 className="font-extrabold text-base text-slate-900">Buka Tiket Kerusakan Fasilitas / Lab</h3>
                <p className="text-xs text-slate-500">Laporan diteruskan langsung ke tim Teknisi Sarpras</p>
              </div>
              <button
                type="button"
                onClick={() => setIsTicketModalOpen(false)}
                className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateTicket} className="space-y-4 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="font-bold text-slate-800 block mb-1">Lokasi Ruangan:</label>
                  <select
                    value={ticketLocation}
                    onChange={(e) => setTicketLocation(e.target.value)}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-900"
                  >
                    <option value="Lab Komputer 1 (Lantai 2)">Lab Komputer 1</option>
                    <option value="Lab Multimedia (Lantai 3)">Lab Multimedia</option>
                    <option value="Ruang Kelas IX-A">Ruang Kelas IX-A</option>
                    <option value="Ruang Kelas IX-B">Ruang Kelas IX-B</option>
                    <option value="Aula Graha Saraswati">Aula Graha Saraswati</option>
                  </select>
                </div>

                <div>
                  <label className="font-bold text-slate-800 block mb-1">Kategori Perangkat:</label>
                  <select
                    value={ticketCategory}
                    onChange={(e) => setTicketCategory(e.target.value)}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-900 focus:outline-none focus:border-blue-900"
                  >
                    <option value="Perangkat Komputer PC">Perangkat PC / Client</option>
                    <option value="Proyektor & Layar">Proyektor &amp; Layar</option>
                    <option value="Jaringan & WiFi">Jaringan &amp; WiFi</option>
                    <option value="AC & Kelistrikan">AC &amp; Kelistrikan</option>
                    <option value="Meja & Kursi">Meja / Kursi Siswa</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="font-bold text-slate-800 block mb-1">Kode / Nomor Aset:</label>
                  <input
                    type="text"
                    required
                    placeholder="Contoh: PC-LAB1-08"
                    value={ticketAssetCode}
                    onChange={(e) => setTicketAssetCode(e.target.value)}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-mono font-bold focus:outline-none focus:border-blue-900"
                  />
                </div>

                <div>
                  <label className="font-bold text-slate-800 block mb-1">Tingkat Urgensi:</label>
                  <select
                    value={ticketPriority}
                    onChange={(e) => setTicketPriority(e.target.value as 'normal' | 'tinggi')}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-black text-slate-900 focus:outline-none focus:border-blue-900"
                  >
                    <option value="normal">Normal (Tidak Menghentikan KBM)</option>
                    <option value="tinggi">Darurat (Menghentikan KBM/Ujian)</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-800 block mb-1">Deskripsi Kerusakan &amp; Gejala:</label>
                <textarea
                  rows={3}
                  required
                  placeholder="Jelaskan secara rinci kendala teknis yang dialami..."
                  value={ticketDescription}
                  onChange={(e) => setTicketDescription(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:outline-none focus:border-blue-900"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                <button
                  type="button"
                  onClick={() => setIsTicketModalOpen(false)}
                  className="px-3.5 py-2 text-xs font-bold border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-black bg-rose-700 text-white rounded-lg hover:bg-rose-800 shadow-xs cursor-pointer"
                >
                  Terbitkan Tiket Kerusakan
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
