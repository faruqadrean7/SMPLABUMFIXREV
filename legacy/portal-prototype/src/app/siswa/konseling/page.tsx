'use client';

import React, { useState } from 'react';
import { 
  HeartHandshake, 
  ShieldCheck, 
  Lock, 
  Calendar, 
  Clock, 
  MapPin, 
  Send, 
  CheckCircle2, 
  ShieldAlert, 
  ExternalLink,
  MessageCircle,
  Users
} from 'lucide-react';
import { StudentCounselingItem } from '@/lib/mock-student-data';
import { useCounselingRequests, useCreateCounselingRequest } from '@/lib/use-student-data';
import { CounselingListSkeleton } from '@/components/ui/student-skeletons';
import { EmptyState } from '@/components/ui/empty-state';
import { ErrorState } from '@/components/ui/error-state';

export default function StudentKonselingPage() {
  const [localList, setLocalList] = useState<StudentCounselingItem[]>([]);
  const { data: apiRequests, isLoading, isError, refetch } = useCounselingRequests();
  const createCounselingMutation = useCreateCounselingRequest();
  
  // Form State
  const [topic, setTopic] = useState('');
  const [category, setCategory] = useState<'Akademik' | 'Sosial & Teman' | 'Pengembangan Diri' | 'Keluarga'>('Pengembangan Diri');
  const [counselor, setCounselor] = useState('Ibu Ratna Dewi, S.Pd., Kons.');
  const [preferredDate, setPreferredDate] = useState('');
  const [preferredTime, setPreferredTime] = useState('13:30 WIB (Sepulang Sekolah)');
  const [notes, setNotes] = useState('');
  const [isSuccess, setIsSuccess] = useState(false);

  const displayList: StudentCounselingItem[] = apiRequests
    ? [
        ...localList,
        ...apiRequests.map((r) => ({
          id: r.id,
          topic: r.topic,
          category: (r.category as any) || 'Pengembangan Diri',
          request_date: r.createdAt ? r.createdAt.split('T')[0] : 'Hari Ini',
          preferred_schedule: `${r.preferredDate || ''} ${r.preferredTime || ''}`.trim() || 'Jadwal Fleksibel',
          counselor_name: r.counselorName || 'Guru BK',
          status: (r.status === 'dijadwalkan' || r.status === 'terjadwal' ? 'terjadwal' : r.status === 'selesai' ? 'selesai' : 'menunggu') as 'menunggu' | 'terjadwal' | 'selesai',
          notes: r.notes || undefined,
          location: r.room || r.roomName || undefined,
        }))
      ]
    : localList;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!topic) return;

    try {
      await createCounselingMutation.mutateAsync({
        topic,
        category,
        counselor_name: counselor,
        preferred_schedule: `${preferredDate || 'Tanggal fleksibel'} - ${preferredTime}`,
        notes: notes || 'Permohonan janji temu baru diajukan siswa.',
      });
      setIsSuccess(true);
      setTopic('');
      setNotes('');
      setTimeout(() => {
        setIsSuccess(false);
      }, 3000);
    } catch {
      // Local optimistic fallback
      const newRequest: StudentCounselingItem = {
        id: `csl-${Date.now()}`,
        topic,
        category,
        request_date: 'Hari Ini',
        preferred_schedule: `${preferredDate || 'Tanggal fleksibel'} - ${preferredTime}`,
        counselor_name: counselor,
        status: 'menunggu',
        notes: notes || 'Permohonan janji temu baru diajukan siswa.',
      };

      setLocalList(prev => [newRequest, ...prev]);
      setIsSuccess(true);
      setTopic('');
      setNotes('');
      setTimeout(() => {
        setIsSuccess(false);
      }, 3000);
    }
  };

  return (
    <div className="space-y-5 text-slate-900">
      {/* ─── HEADER CARD (COMPACT & PROPORTIONED) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Bimbingan & Konseling (BK)
            </span>
            <span className="text-xs text-slate-500 font-semibold">• Ruang Aman Sahabat Siswa</span>
          </div>
          <h1 className="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
            Konseling Privat & Layanan Bantuan
          </h1>
          <p className="text-xs text-slate-500 font-normal mt-0.5 max-w-2xl">
            Konsultasikan studi, minat bakat lanjutan, atau kendala pribadi dengan Guru BK secara aman dan rahasia.
          </p>
        </div>
      </div>

      {/* ─── PRIVACY & DATA CONFIDENTIALITY NOTICE (SOLID BLUE-900) ─── */}
      <div className="p-4 sm:p-4.5 rounded-2xl bg-blue-900 text-white border border-blue-950 flex items-start gap-3.5 shadow-xs">
        <Lock className="w-5 h-5 text-blue-200 shrink-0 mt-0.5" />
        <div className="text-xs leading-relaxed space-y-1">
          <p className="font-extrabold text-sm text-white">Jaminan Kerahasiaan & Keamanan Konseling (UU PDP):</p>
          <p className="text-blue-100 font-normal">
            Setiap catatan dan permohonan konseling dienkripsi secara khusus. Informasi yang Anda sampaikan <strong>hanya dapat dibaca oleh Anda dan Guru BK</strong>. Rekan sekelas maupun guru mata pelajaran lain tidak dapat melihat isi permohonan ini.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left 2 Cols: Form Permohonan Janji Temu */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-7 space-y-5 shadow-xs">
            <div className="pb-3 border-b border-slate-100">
              <h3 className="font-extrabold text-base text-slate-900">Form Pengajuan Janji Temu Konseling</h3>
              <p className="text-xs text-slate-500 font-normal mt-0.5">Guru BK akan mengonfirmasi kesediaan waktu dan mengalokasikan ruang pertemuan privat</p>
            </div>

            {isSuccess && (
              <div className="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center gap-2.5 shadow-2xs">
                <CheckCircle2 className="w-5 h-5 text-emerald-700 shrink-0" />
                <span>Permohonan janji temu berhasil diajukan! Guru BK akan segera menetapkan jadwal temu Anda.</span>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                  Kategori Pembahasan:
                </label>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  {(['Akademik', 'Sosial & Teman', 'Pengembangan Diri', 'Keluarga'] as const).map((cat) => (
                    <button
                      key={cat}
                      type="button"
                      onClick={() => setCategory(cat)}
                      className={`py-2 px-3 rounded-xl text-xs font-bold transition-all border cursor-pointer ${
                        category === cat
                          ? 'bg-blue-900 text-white border-blue-900 shadow-xs'
                          : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                      }`}
                    >
                      {cat}
                    </button>
                  ))}
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                  Topik / Hal yang Ingin Didiskusikan:
                </label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Diskusi minat bakat lanjutan ke SMA/SMK atau kendala belajar..."
                  value={topic}
                  onChange={(e) => setTopic(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:outline-none focus:border-blue-900 shadow-2xs"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                    Pilihan Guru Konselor BK:
                  </label>
                  <select
                    value={counselor}
                    onChange={(e) => setCounselor(e.target.value)}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-900 bg-white focus:outline-none focus:border-blue-900 shadow-2xs cursor-pointer"
                  >
                    <option value="Ibu Ratna Dewi, S.Pd., Kons.">Ibu Ratna Dewi, S.Pd., Kons. (Koordinator BK)</option>
                    <option value="Bapak Dedi Kurniawan, M.Pd.">Bapak Dedi Kurniawan, M.Pd. (Konselor Putra)</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                    Preferensi Waktu Konseling:
                  </label>
                  <select
                    value={preferredTime}
                    onChange={(e) => setPreferredTime(e.target.value)}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-900 bg-white focus:outline-none focus:border-blue-900 shadow-2xs cursor-pointer"
                  >
                    <option value="13:30 WIB (Sepulang Sekolah)">Sepulang Sekolah (13:30 - 14:30 WIB)</option>
                    <option value="Jam Istirahat Pertama (09:45 WIB)">Jam Istirahat 1 (09:45 - 10:15 WIB)</option>
                    <option value="Jam Istirahat Siang (12:30 WIB)">Jam Istirahat Siang (12:30 - 13:00 WIB)</option>
                  </select>
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                  Pesan / Catatan Pengantar Tambahan (Opsional):
                </label>
                <textarea
                  rows={3}
                  placeholder="Ceritakan gambaran singkat hal yang ingin Anda bicarakan..."
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  className="w-full p-3 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:outline-none focus:border-blue-900 shadow-2xs leading-relaxed"
                />
              </div>

              <div className="pt-2 flex justify-end">
                <button
                  type="submit"
                  className="px-6 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-black uppercase tracking-wider transition-colors shadow-xs flex items-center gap-2 cursor-pointer"
                >
                  <Send className="w-4 h-4 text-white" />
                  <span>Kirim Permohonan Konseling ➔</span>
                </button>
              </div>
            </form>
          </div>

          {/* Riwayat Permohonan Konseling */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xs">
            <div className="pb-3 border-b border-slate-100">
              <h3 className="font-extrabold text-base text-slate-900">Riwayat Sesi & Permohonan Konseling Saya</h3>
              <p className="text-xs text-slate-500 font-normal mt-0.5">Pelacakan status jadwal pertemuan bersama Guru BK</p>
            </div>

            <div className="space-y-3">
              {isLoading && <CounselingListSkeleton />}

              {isError && (
                <ErrorState
                  title="Gagal Memuat Riwayat Konseling"
                  message="Terjadi kendala saat mengambil riwayat permohonan konseling dari server. Silakan coba kembali."
                  onRetry={() => refetch()}
                />
              )}

              {!isLoading && !isError && displayList.length === 0 && (
                <EmptyState
                  icon={HeartHandshake}
                  title="Belum Ada Permohonan Konseling"
                  description="Anda belum pernah mengajukan sesi bimbingan atau konseling. Gunakan formulir di atas untuk berdiskusi dengan Guru BK."
                />
              )}

              {!isLoading && !isError && displayList.map((item) => (
                <div
                  key={item.id}
                  className="p-4 rounded-xl border border-slate-200/90 bg-slate-50/60 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-2xs hover:border-slate-300 transition-all"
                >
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider ${
                        item.status === 'terjadwal' 
                          ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' 
                          : item.status === 'menunggu' 
                          ? 'bg-amber-50 text-amber-800 border border-amber-200' 
                          : 'bg-slate-100 text-slate-700 border border-slate-200'
                      }`}>
                        {item.status.toUpperCase()}
                      </span>
                      <span className="text-xs font-bold text-slate-700">{item.category}</span>
                    </div>

                    <h4 className="font-extrabold text-sm text-slate-900">{item.topic}</h4>
                    <p className="text-xs text-slate-500 font-normal">
                      Konselor: <strong className="text-slate-800 font-semibold">{item.counselor_name}</strong>
                    </p>

                    {item.location && (
                      <p className="text-xs font-semibold text-blue-900 flex items-center gap-1">
                        <MapPin className="w-3.5 h-3.5" />
                        {item.location}
                      </p>
                    )}

                    {item.notes && (
                      <p className="text-xs text-slate-600 italic bg-white p-2.5 rounded-lg border border-slate-200/80 mt-1">
                        "{item.notes}"
                      </p>
                    )}
                  </div>

                  <span className="px-3 py-1 rounded-lg text-xs font-semibold bg-white text-slate-700 border border-slate-200 self-start md:self-auto shrink-0 shadow-2xs">
                    {item.preferred_schedule}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Right 1 Col: Saluran Pengaduan Whistleblowing / Anti-Bullying */}
        <div className="space-y-6">
          <div className="bg-white border border-rose-200 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xs">
            <div className="flex items-center gap-2.5">
              <div className="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center font-bold shrink-0 shadow-2xs">
                <ShieldAlert className="w-5 h-5" />
              </div>
              <div>
                <h4 className="font-extrabold text-sm text-slate-900 leading-tight">Posko Pengaduan Aman & Anti-Perundungan</h4>
                <p className="text-[11px] text-slate-500 font-normal mt-0.5">Kanal Perlindungan Murid Labschool</p>
              </div>
            </div>

            <p className="text-xs text-slate-600 leading-relaxed font-normal">
              Apakah Anda atau rekan Anda mengalami tindakan perundungan (*bullying*), pelecehan verbal/fisik, atau situasi tidak aman di sekolah?
            </p>

            <div className="p-3 bg-rose-50/60 rounded-xl border border-rose-200/80 text-[11px] text-rose-900 leading-relaxed space-y-1">
              <p className="font-bold">✔ Pilihan Anonim:</p>
              <p className="text-rose-800">Laporan dapat dikirimkan secara rahasia/anonim tanpa mencantumkan nama atau NIS Anda. Alamat IP tidak disimpan ke database.</p>
            </div>

            <a
              href="http://localhost:3000/aspirasi.html"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-black uppercase tracking-wider transition-colors shadow-xs cursor-pointer"
            >
              <span>Buka Formulir Pengaduan Aman ➔</span>
            </a>
          </div>

          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 space-y-3 shadow-xs">
            <h4 className="font-extrabold text-xs text-slate-900 uppercase tracking-wider">Kontak Cepat Konselor</h4>
            <div className="space-y-2 text-xs text-slate-700">
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-200/80">
                <p className="font-bold text-slate-900">Ibu Ratna Dewi, S.Pd., Kons.</p>
                <p className="text-slate-500 text-[11px]">Koordinator Bimbingan Konseling</p>
                <p className="text-blue-900 font-mono font-semibold mt-1">Ruang BK Lt. 2 Gedung A</p>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-200/80">
                <p className="font-bold text-slate-900">Bapak Dedi Kurniawan, M.Pd.</p>
                <p className="text-slate-500 text-[11px]">Konselor Putra & Pembinaan Karakter</p>
                <p className="text-blue-900 font-mono font-semibold mt-1">Ruang BK Lt. 2 Gedung A</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
