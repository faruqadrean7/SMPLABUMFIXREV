'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import { 
  HeartHandshake, 
  Calendar, 
  Clock, 
  MapPin, 
  CheckCircle2, 
  Send, 
  Plus, 
  Phone, 
  MessageSquare, 
  ShieldCheck, 
  User, 
  X, 
  AlertCircle,
  ExternalLink,
  BookOpen
} from 'lucide-react';
import { toast } from 'sonner';
import { useGuardian } from '@/lib/guardian-context';
import { GuardianCounselingItem } from '@/lib/mock-guardian-data';
import { useGuardianCounselingRequests, useCreateGuardianCounselingRequest } from '@/lib/use-guardian-data';

export default function GuardianKonselingPage() {
  const { activeChild } = useGuardian();
  const { data: rawAppointments = [], isLoading: isCslLoading } = useGuardianCounselingRequests();
  const createCounselingMutation = useCreateGuardianCounselingRequest();

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [targetRole, setTargetRole] = useState<'Wali Kelas' | 'Guru BK'>('Wali Kelas');
  const [topic, setTopic] = useState('');
  const [requestedDate, setRequestedDate] = useState('');
  const [notes, setNotes] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Filter and map appointments for active child
  const childAppointments = React.useMemo<GuardianCounselingItem[]>(() => {
    return rawAppointments
      .filter(a => !a.student_id || a.student_id === activeChild.id)
      .map((item) => ({
        id: item.id,
        student_id: activeChild.id,
        student_name: activeChild.name,
        topic: item.topic,
        target_role: item.counselorName?.includes('BK') ? 'Guru BK' : 'Wali Kelas',
        target_name: item.counselorName || activeChild.homeroom_teacher,
        requested_date: item.scheduledTime || item.preferredSchedule || 'Jadwal Ditentukan',
        status: (item.status === 'dijadwalkan' || item.status === 'terjadwal' 
          ? 'terjadwal' 
          : item.status === 'selesai' 
          ? 'selesai' 
          : 'menunggu') as 'menunggu' | 'terjadwal' | 'selesai',
        location: item.room || item.roomName || 'Ruang Konseling Labschool',
        notes: item.notes || 'Permohonan konsultasi perkembangan siswa.',
      }));
  }, [rawAppointments, activeChild]);

  // Dynamic Homeroom Teacher Photo
  const getHomeroomPhoto = () => {
    return 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=256';
  };

  const bkPhoto = 'https://images.unsplash.com/photo-1580894732444-8ecded7900cd?auto=format&fit=crop&q=80&w=256';

  const handleCreateAppointment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!topic || !requestedDate || !notes) {
      toast.error('Mohon lengkapi topik, tanggal, dan catatan permohonan konsultasi');
      return;
    }

    setIsSubmitting(true);
    try {
      await createCounselingMutation.mutateAsync({
        student_id: activeChild.id,
        topic,
        category: targetRole === 'Wali Kelas' ? 'akademik' : 'sosial',
        reason: notes,
        preferred_schedule: requestedDate,
        counselor_name: targetRole === 'Wali Kelas' ? activeChild.homeroom_teacher : 'Guru BK Labschool UM',
      });

      setIsSubmitting(false);
      setIsModalOpen(false);
      setTopic('');
      setRequestedDate('');
      setNotes('');

      toast.success('Permohonan janji temu berhasil diajukan kepada sekolah');
    } catch (err: any) {
      setIsSubmitting(false);
      toast.error(err?.response?.data?.message || 'Gagal mengajukan janji temu');
    }
  };

  return (
    <div className="flex flex-col gap-3.5 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden">
      {/* ─── 1. HEADER BAR (DESKTOP MAXIMIZED & WELL-PROPORTIONED) ─── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">
              Janji Temu Konseling ({activeChild.name.split(' ')[0]})
            </h1>
            <span className="px-2.5 py-0.5 rounded-lg bg-teal-800 text-white text-xs font-bold shadow-2xs">
              Kelas {activeChild.class_name.replace('Kelas ', '')}
            </span>
            <span className="px-2.5 py-0.5 rounded-lg bg-slate-100 border border-slate-300 text-slate-700 text-xs font-mono font-semibold">
              NIS: {activeChild.nis}
            </span>
            <span className="px-2.5 py-0.5 rounded-lg bg-emerald-800 text-white text-xs font-bold shadow-2xs">
              Layanan Terbuka
            </span>
          </div>
          <p className="text-xs text-slate-600 mt-1 font-normal">
            Fasilitas dialog tatap muka berkala bersama Wali Kelas dan Guru Bimbingan Konseling (BK) Labschool UM.
          </p>
        </div>

        <button
          onClick={() => setIsModalOpen(true)}
          className="px-3.5 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold transition-all shadow-sm flex items-center gap-2 shrink-0 self-start sm:self-auto hover:shadow"
        >
          <Plus className="w-4 h-4 text-white" />
          <span>Ajukan Janji Temu Baru</span>
        </button>
      </div>

      {/* ─── 2. PROFIL PENDIDIK & KONSELOR SISWA (2 DIRECT HERO CARDS) ─── */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 shrink-0">
        {/* Card 1: Homeroom Teacher */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center gap-3.5 min-w-0">
            <div className="w-12 h-12 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 shrink-0 relative shadow-2xs">
              <Image
                src={getHomeroomPhoto()}
                alt={activeChild.homeroom_teacher}
                fill
                sizes="48px"
                className="object-cover"
                unoptimized
              />
            </div>
            <div className="min-w-0">
              <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-800 text-white uppercase">
                Wali Kelas {activeChild.class_name}
              </span>
              <h3 className="text-sm font-bold text-slate-900 truncate mt-1">
                {activeChild.homeroom_teacher}
              </h3>
              <p className="text-xs text-slate-500 font-medium">
                Kontak: {activeChild.homeroom_phone} • Ruang Guru Gedung Utama
              </p>
            </div>
          </div>

          <a
            href={`https://wa.me/${activeChild.homeroom_phone.replace(/[^0-9]/g, '')}`}
            target="_blank"
            rel="noreferrer"
            className="px-3 py-2 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold shrink-0 transition-colors flex items-center gap-1.5 shadow-2xs ml-3"
          >
            <Phone className="w-3.5 h-3.5" />
            <span>WhatsApp</span>
          </a>
        </div>

        {/* Card 2: Guidance Counselor (BK) */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-3.5 shadow-xs flex items-center justify-between hover:border-slate-300 transition-all">
          <div className="flex items-center gap-3.5 min-w-0">
            <div className="w-12 h-12 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 shrink-0 relative shadow-2xs">
              <Image
                src={bkPhoto}
                alt="Ibu Ratna Dewi"
                fill
                sizes="48px"
                className="object-cover"
                unoptimized
              />
            </div>
            <div className="min-w-0">
              <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-teal-800 text-white uppercase">
                Koordinator BK Labschool
              </span>
              <h3 className="text-sm font-bold text-slate-900 truncate mt-1">
                Ibu Ratna Dewi, S.Pd., Kons.
              </h3>
              <p className="text-xs text-slate-500 font-medium">
                Layanan: 07.30 - 15.00 WIB • Ruang Konseling BK Lantai 2
              </p>
            </div>
          </div>

          <a
            href="https://wa.me/6281334567895"
            target="_blank"
            rel="noreferrer"
            className="px-3 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold shrink-0 transition-colors flex items-center gap-1.5 shadow-2xs ml-3"
          >
            <Phone className="w-3.5 h-3.5" />
            <span>WhatsApp BK</span>
          </a>
        </div>
      </div>

      {/* ─── 3. DUA KOLOM: DAFTAR TIKET KONSULTASI & PANDUAN KONSELING (MAXIMIZED DESKTOP) ─── */}
      <div className="xl:flex-1 xl:min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* KOLOM KIRI: DAFTAR AGENDA JANJI TEMU (7 COLS) */}
        <div className="lg:col-span-7 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-97.5">
          {/* Header Bar */}
          <div className="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                  Daftar Agenda Konsultasi ({activeChild.name.split(' ')[0]})
                </h2>
                <span className="px-2.5 py-0.5 rounded-full bg-slate-800 text-white text-xs font-bold">
                  {childAppointments.length} Agenda
                </span>
              </div>
              <p className="text-xs text-slate-500 font-normal mt-0.5">
                Riwayat permohonan diskusi tatap muka orang tua bersama guru pembina.
              </p>
            </div>

            <button
              onClick={() => setIsModalOpen(true)}
              className="px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-900 border border-teal-200 text-xs font-bold transition-colors flex items-center gap-1 shrink-0"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Tambah Jadwal</span>
            </button>
          </div>

          {/* List of Appointment Tickets (Internal Scroll) */}
          <div className="flex-1 min-h-0 overflow-y-auto pr-1 my-2 space-y-2.5">
            {childAppointments.length === 0 ? (
              <div className="p-8 text-center bg-slate-50 rounded-xl border border-slate-200/80 text-slate-500">
                <HeartHandshake className="w-8 h-8 mx-auto text-slate-400 mb-2" />
                <p className="text-xs font-bold text-slate-800">Belum ada agenda janji temu konseling</p>
                <p className="text-xs text-slate-500 mt-0.5">Gunakan tombol di atas untuk mengajukan jadwal konsultasi tatap muka.</p>
              </div>
            ) : (
              childAppointments.map((item, idx) => {
                const isBK = item.target_role === 'Guru BK';

                return (
                  <div 
                    key={item.id}
                    className={`bg-white rounded-xl p-3.5 border border-slate-200/90 shadow-xs hover:shadow-sm transition-all border-l-4 ${
                      isBK ? 'border-l-teal-600' : 'border-l-emerald-600'
                    }`}
                  >
                    {/* Header Row: Role Badge, Index #, and Status Badge */}
                    <div className="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
                      <div className="flex items-center gap-2">
                        <span className={`px-2 py-0.5 rounded text-xs font-bold ${
                          isBK ? 'bg-teal-800 text-white' : 'bg-emerald-800 text-white'
                        }`}>
                          {item.target_role}
                        </span>
                        <span className="text-[11px] font-bold text-slate-400 font-mono">
                          Agenda #{idx + 1}
                        </span>
                      </div>
                      <span className={`px-2.5 py-0.5 rounded-md text-xs font-bold ${
                        item.status === 'terjadwal' ? 'bg-blue-800 text-white' :
                        item.status === 'selesai' ? 'bg-emerald-800 text-white' : 'bg-amber-600 text-white'
                      }`}>
                        {item.status === 'terjadwal' ? 'Jadwal Disetujui' :
                         item.status === 'selesai' ? 'Selesai Dilaksanakan' : 'Menunggu Konfirmasi'}
                      </span>
                    </div>

                    {/* Topic */}
                    <h3 className="text-sm font-bold text-slate-900">
                      {item.topic}
                    </h3>

                    {/* Metadata Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-700 mt-2 pt-1 border-t border-slate-100">
                      <div className="flex items-center gap-2">
                        <User className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span className="truncate">Narasumber: <strong className="text-slate-900 font-semibold">{item.target_name.split('(')[0]}</strong></span>
                      </div>
                      <div className="flex items-center gap-2">
                        <Calendar className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Waktu: <strong className="text-slate-900 font-semibold">{item.requested_date}</strong></span>
                      </div>
                      <div className="flex items-center gap-2 sm:col-span-2">
                        <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>Lokasi: <strong className="text-slate-900 font-semibold">{item.location}</strong></span>
                      </div>
                    </div>

                    {/* Notes Box */}
                    {item.notes && (
                      <div className="mt-2.5 p-2.5 bg-slate-50 rounded-lg border border-slate-200/80 text-xs text-slate-600">
                        <span className="font-semibold text-slate-800 block text-[11px] mb-0.5">Catatan Permohonan Ortu:</span>
                        <p className="text-[11px] leading-relaxed text-slate-600">{item.notes}</p>
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>
        </div>

        {/* KOLOM KANAN: PANDUAN ETIKA & TOPIK KONSELING (5 COLS - NO DEAD VOID) */}
        <div className="lg:col-span-5 bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-col h-full min-h-97.5 justify-between">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <div>
              <h2 className="text-sm font-bold text-slate-900 tracking-tight">
                Pedoman & Kerahasiaan Konseling
              </h2>
              <p className="text-xs text-slate-500 font-normal">
                Prinsip perlindungan privasi anak dan etika bimbingan
              </p>
            </div>
            <span className="px-2.5 py-1 rounded-full bg-slate-800 text-white text-xs font-bold shadow-2xs">
              Kode Etik BK
            </span>
          </div>

          {/* List of 2 Guidance Cards */}
          <div className="flex-1 min-h-0 overflow-y-auto pr-1 my-1.5 space-y-2.5">
            {/* Card 1: Kerahasiaan Konseling */}
            <div className="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:shadow-sm transition-all border-l-4 border-l-blue-600">
              <div className="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded text-xs font-bold bg-blue-800 text-white">
                    Privasi Terjaga
                  </span>
                  <span className="text-[11px] font-bold text-slate-400 font-mono">
                    Etika #1
                  </span>
                </div>
                <span className="text-[11px] text-slate-500 font-medium">
                  UU PDP & Kode Etik
                </span>
              </div>

              <div className="space-y-0.5">
                <div className="flex items-center gap-1.5">
                  <ShieldCheck className="w-3.5 h-3.5 text-blue-800 shrink-0" />
                  <p className="text-xs font-bold text-slate-900">
                    Kerahasiaan Data & Riwayat Diskusi
                  </p>
                </div>
                <p className="text-xs text-slate-600 font-normal leading-relaxed pl-5">
                  Seluruh isi pembicaraan dan catatan kasus konseling anak dilindungi kerahasiaannya secara ketat, hanya dapat diakses oleh Orang Tua, Wali Kelas, dan Konselor BK terkait.
                </p>
              </div>

              <div className="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span className="text-[11px] text-slate-500">
                  Aman & Terenkripsi
                </span>
                <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-800 text-white">
                  SOP Labschool UM
                </span>
              </div>
            </div>

            {/* Card 2: Rumpun Layanan Konsultasi */}
            <div className="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:shadow-sm transition-all border-l-4 border-l-teal-600">
              <div className="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
                <div className="flex items-center gap-2">
                  <span className="px-2 py-0.5 rounded text-xs font-bold bg-teal-800 text-white">
                    Topik Pembahasan
                  </span>
                  <span className="text-[11px] font-bold text-slate-400 font-mono">
                    Layanan #2
                  </span>
                </div>
                <span className="text-[11px] text-slate-500 font-medium">
                  3 Rumpun Utama
                </span>
              </div>

              <div className="space-y-0.5">
                <div className="flex items-center gap-1.5">
                  <BookOpen className="w-3.5 h-3.5 text-teal-800 shrink-0" />
                  <p className="text-xs font-bold text-slate-900">
                    Fokus Bimbingan Akademik & Minat Bakat
                  </p>
                </div>
                <p className="text-xs text-slate-600 font-normal leading-relaxed pl-5">
                  • Perkembangan belajar & capaian ketuntasan KKTP.<br />
                  • Peminatan jurusan, karir, dan olimpiade siswa.<br />
                  • Adaptasi sosial & pembinaan karakter di sekolah.
                </p>
              </div>

              <div className="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span className="text-[11px] text-slate-500">
                  Konsultasi Terbuka
                </span>
                <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-teal-800 text-white">
                  Rekomendasi Walas
                </span>
              </div>
            </div>
          </div>

          {/* Solid Information Card & Fast Communication */}
          <div className="pt-2 border-t border-slate-100 shrink-0 space-y-2">
            {/* Info Jam Layanan */}
            <div className="p-2.5 bg-slate-50 rounded-xl border border-slate-200/90 text-xs text-slate-700 space-y-0.5">
              <div className="flex items-center gap-1.5 font-bold text-slate-900 text-xs">
                <Clock className="w-3.5 h-3.5 text-teal-800" />
                <span>Jadwal Ruang Konseling Tatap Muka:</span>
              </div>
              <p className="text-slate-600 leading-normal text-[11px] font-normal">
                Sesi tatap muka diselenggarakan pada hari kerja (Senin–Jumat) pukul 13.00 – 15.30 WIB di Ruang BK Lantai 2 Labschool UM.
              </p>
            </div>

            {/* Quick Action Trigger */}
            <button
              onClick={() => setIsModalOpen(true)}
              className="w-full py-2.5 px-4 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-2 hover:shadow"
            >
              <HeartHandshake className="w-4 h-4 text-white" />
              <span>Ajukan Janji Temu Konseling Sekarang</span>
            </button>
          </div>
        </div>
      </div>

      {/* ─── MODAL PENGAJUAN JANJI TEMU BARU ─── */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="px-6 py-4 bg-teal-800 text-white flex items-center justify-between">
              <div className="flex items-center gap-2">
                <HeartHandshake className="w-5 h-5 text-teal-200" />
                <div>
                  <h3 className="text-sm font-bold text-white">
                    Permohonan Janji Temu Konseling
                  </h3>
                  <p className="text-xs text-teal-100 font-normal">
                    Siswa: {activeChild.name} ({activeChild.class_name})
                  </p>
                </div>
              </div>
              <button 
                onClick={() => setIsModalOpen(false)}
                className="p-1.5 rounded-lg text-teal-100 hover:text-white hover:bg-teal-700 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleCreateAppointment} className="p-6 space-y-4">
              {/* Target Konseling */}
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                  Tujuan Konsultasi *
                </label>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setTargetRole('Wali Kelas')}
                    className={`p-3 rounded-xl border text-left text-xs transition-colors ${
                      targetRole === 'Wali Kelas'
                        ? 'border-emerald-800 bg-emerald-50 text-slate-900 font-semibold shadow-xs'
                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                    }`}
                  >
                    <span className="block font-bold">Wali Kelas</span>
                    <span className="text-xs font-normal text-slate-500">
                      {activeChild.homeroom_teacher.split(',')[0]}
                    </span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setTargetRole('Guru BK')}
                    className={`p-3 rounded-xl border text-left text-xs transition-colors ${
                      targetRole === 'Guru BK'
                        ? 'border-teal-800 bg-teal-50 text-slate-900 font-semibold shadow-xs'
                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                    }`}
                  >
                    <span className="block font-bold">Guru BK</span>
                    <span className="text-xs font-normal text-slate-500">
                      Bimbingan & Konseling
                    </span>
                  </button>
                </div>
              </div>

              {/* Topik Diskusi */}
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Topik Konsultasi *
                </label>
                <input
                  type="text"
                  required
                  value={topic}
                  onChange={(e) => setTopic(e.target.value)}
                  placeholder="Contoh: Diskusi Penjurusan SMA / Minat Bakat Olimpiade..."
                  className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-700"
                />
              </div>

              {/* Usulan Waktu */}
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Usulan Hari & Jam Pertemuan *
                </label>
                <input
                  type="text"
                  required
                  value={requestedDate}
                  onChange={(e) => setRequestedDate(e.target.value)}
                  placeholder="Contoh: Jumat, 23 Oktober 2026 - 13:30 WIB"
                  className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-700"
                />
              </div>

              {/* Catatan / Harapan Diskusi */}
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Harapan Diskusi / Catatan Tambahan *
                </label>
                <textarea
                  required
                  rows={3}
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder="Tuliskan hal-hal yang ingin didiskusikan secara mendalam mengenai ananda..."
                  className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-700"
                />
              </div>

              {/* Tombol Aksi */}
              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="px-5 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold transition-colors shadow-xs flex items-center gap-1.5 disabled:opacity-50"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>{isSubmitting ? 'Mengirim...' : 'Kirim Permohonan Janji Temu'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
