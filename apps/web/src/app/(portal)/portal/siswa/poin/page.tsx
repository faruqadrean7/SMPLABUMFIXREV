'use client';

import React, { useState } from 'react';
import { 
  Award, 
  ShieldCheck, 
  PlusCircle, 
  MinusCircle, 
  Clock, 
  Users, 
  Calendar,
  ArrowRight,
  CheckCircle2,
  Inbox
} from 'lucide-react';
import { StudentPointLogItem } from '@/lib/mock-student-data';
import { useStudentDashboard } from '@/lib/use-student-data';
import { EmptyState } from '@/components/ui/empty-state';

export default function StudentPoinPage() {
  const [activeTab, setActiveTab] = useState<'poin' | 'ekskul'>('poin');
  const [filterKind, setFilterKind] = useState<'semua' | 'prestasi' | 'pelanggaran'>('semua');

  const { data: studentDash } = useStudentDashboard();
  const achievementsCount = studentDash?.totalAchievements ?? 3;

  const achievementItems: StudentPointLogItem[] = [
    {
      id: 'ach-01',
      kind: 'prestasi' as const,
      type_name: 'Juara 1 Lomba Coding & Web Development Tingkat Kota',
      category: 'Prestasi Akademik & Teknologi',
      date_str: '12 Okt 2026',
      points: 50,
      note: 'Juara 1 kategori SMP se-Kota Malang, surat tugas resmi sekolah.',
      recorder_name: 'Budi Santoso, S.Kom., M.T.',
    },
    {
      id: 'ach-02',
      kind: 'prestasi' as const,
      type_name: 'Medali Perak Olimpiade Sains Nasional (OSN) Bidang IPA',
      category: 'Prestasi Sains & Riset',
      date_str: '28 Sep 2026',
      points: 75,
      note: 'Diverifikasi oleh tim kurikulum dan kesiswaan SMP Labschool UM.',
      recorder_name: 'Ratna Dewi, S.Pd.',
    },
    {
      id: 'ach-03',
      kind: 'prestasi' as const,
      type_name: 'Apresiasi Pemimpin Upacara Hari Kemerdekaan RI',
      category: 'Kedisiplinan & Kepemimpinan',
      date_str: '17 Agu 2026',
      points: 25,
      note: 'Petugas inti upacara bendera HUT RI ke-81 di kampus Labschool.',
      recorder_name: 'Tim Kesiswaan',
    },
  ].slice(0, achievementsCount);

  const totalPrestasi = achievementItems.reduce((acc, curr) => acc + curr.points, 0);
  const totalPelanggaran = 0;
  const balancePoints = totalPrestasi - totalPelanggaran;

  const filteredLogs = filterKind === 'pelanggaran'
    ? []
    : achievementItems;

  const myExtracurriculars = [
    {
      id: 'ekskul-01',
      name: 'Coding & Robotika Club (Robotics Team)',
      coach: 'Budi Santoso, S.Kom., M.T.',
      schedule: 'Setiap Rabu, 15:00 - 17:00 WIB',
      room: 'Lab Komputer 1 & Lab Robotika',
      role: 'Anggota Inti (Programmer)',
      attendance_rate: '100%',
      badgeColor: 'bg-blue-50 text-blue-900 border-blue-200',
    },
    {
      id: 'ekskul-02',
      name: 'Palang Merah Remaja (PMR Madya)',
      coach: 'Dra. Siti Rahayu, M.Pd.',
      schedule: 'Setiap Sabtu, 08:00 - 10:00 WIB',
      room: 'Ruang UKS & Lapangan Utama',
      role: 'Anggota Reguler',
      attendance_rate: '92.3%',
      badgeColor: 'bg-rose-50 text-rose-800 border-rose-200',
    },
  ];

  const availableEkskul = [
    { name: 'Futsal & Sepak Bola', coach: 'Rudi Hartono, S.Pd.', quota: 'Sisa 4 Kuota', schedule: 'Selasa & Jumat' },
    { name: 'Paduan Suara & Remaja', coach: 'Yuni Astuti, S.Sn.', quota: 'Sisa 8 Kuota', schedule: 'Kamis Sore' },
    { name: 'Klub Bahasa Inggris (EDC)', coach: 'Nurul Hidayati, M.Ed.', quota: 'Sisa 6 Kuota', schedule: 'Senin Sore' },
    { name: 'Jurnalistik & Fotografi', coach: 'Agus Subekti, S.Pd.', quota: 'Sisa 2 Kuota', schedule: 'Sabtu Pagi' },
  ];

  return (
    <div className="space-y-5 text-slate-900">
      {/* ─── HEADER CARD (COMPACT & PROPORTIONED) ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Kesiswaan & Karakter
            </span>
            <span className="text-xs text-slate-500 font-semibold">• T.A. 2026/2027 Ganjil</span>
          </div>
          <h1 className="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
            Buku Poin Pembiasaan & Ekstrakurikuler
          </h1>
          <p className="text-xs text-slate-500 font-normal mt-0.5 max-w-2xl">
            Pantau catatan poin kedisiplinan dan apresiasi prestasi, serta jadwal kegiatan ekstrakurikuler sekolah.
          </p>
        </div>

        {/* Tab Switcher */}
        <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 self-start md:self-auto shadow-2xs shrink-0">
          <button
            onClick={() => setActiveTab('poin')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'poin'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Buku Poin Siswa
          </button>
          <button
            onClick={() => setActiveTab('ekskul')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'ekskul'
                ? 'bg-blue-900 text-white shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Ekstrakurikuler ({myExtracurriculars.length})
          </button>
        </div>
      </div>

      {/* ─── TAB 1: BUKU POIN SISWA ─── */}
      {activeTab === 'poin' && (
        <div className="space-y-4">
          {/* 3 Metric Cards Saldo (Clean without border-l-4) */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
            <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-4.5 shadow-xs flex items-center justify-between gap-3">
              <div>
                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                  Total Poin Prestasi
                </span>
                <div className="text-2xl sm:text-3xl font-black text-slate-900 font-mono mt-0.5">
                  +{totalPrestasi}
                </div>
                <p className="text-[11px] text-slate-500 font-medium mt-0.5">{achievementsCount} Penghargaan Resmi</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center shrink-0 shadow-2xs">
                <PlusCircle className="w-5 h-5" />
              </div>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-4.5 shadow-xs flex items-center justify-between gap-3">
              <div>
                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                  Total Poin Pelanggaran
                </span>
                <div className="text-2xl sm:text-3xl font-black text-slate-900 font-mono mt-0.5">
                  -{totalPelanggaran}
                </div>
                <p className="text-[11px] text-slate-500 font-medium mt-0.5">Catatan Perilaku Bersih</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center shrink-0 shadow-2xs">
                <MinusCircle className="w-5 h-5" />
              </div>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-4.5 shadow-xs flex items-center justify-between gap-3">
              <div>
                <span className="text-[10px] font-bold text-blue-900 uppercase tracking-wider block">
                  Saldo Bersih Poin
                </span>
                <div className="text-2xl sm:text-3xl font-black text-slate-900 font-mono mt-0.5">
                  +{balancePoints} <span className="text-xs font-bold text-slate-500 font-sans">Poin</span>
                </div>
                <p className="text-[11px] text-slate-500 font-medium mt-0.5">Kategori: Sangat Baik (SAB)</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0 shadow-2xs">
                <Award className="w-5 h-5" />
              </div>
            </div>
          </div>

          {/* Indikator Ambang Batas Disiplin Sekolah */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs space-y-3">
            <div className="flex items-center justify-between">
              <h4 className="font-extrabold text-xs text-slate-900 uppercase tracking-wider">
                Ambang Batas Kedisiplinan Siswa SMP Labschool UM
              </h4>
              <span className="text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 rounded-lg shadow-2xs">
                Status Anda: AMAN
              </span>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
              <div className="p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/80">
                <span className="font-bold text-slate-800 block text-xs">Ambang 1: -25 Poin</span>
                <p className="text-slate-500 text-[11px] mt-0.5">Teguran tertulis & pembinaan wali kelas.</p>
                <span className="text-[10px] font-bold text-emerald-700 mt-1.5 block">✔ Pelanggaran Anda: 0 poin (Teladan)</span>
              </div>

              <div className="p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/80">
                <span className="font-bold text-slate-800 block text-xs">Ambang 2: -50 Poin</span>
                <p className="text-slate-500 text-[11px] mt-0.5">Surat Peringatan 1 & pemanggilan orang tua.</p>
                <span className="text-[10px] font-bold text-emerald-700 mt-1.5 block">✔ Belum pernah tercapai</span>
              </div>

              <div className="p-3.5 bg-slate-50/70 rounded-xl border border-slate-200/80">
                <span className="font-bold text-slate-800 block text-xs">Ambang 3: -75 Poin</span>
                <p className="text-slate-500 text-[11px] mt-0.5">Skorsing sementara & konferensi kasus BK.</p>
                <span className="text-[10px] font-bold text-emerald-700 mt-1.5 block">✔ Belum pernah tercapai</span>
              </div>
            </div>
          </div>

          {/* Tabel Catatan Riwayat Poin */}
          <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs space-y-0">
            <div className="p-4 bg-slate-50/70 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h3 className="font-extrabold text-xs sm:text-sm text-slate-900">Riwayat Pencatatan Poin Siswa</h3>
                <p className="text-[11px] text-slate-500 font-normal mt-0.5">Dicatat oleh guru piket, wali kelas, dan pembina kesiswaan</p>
              </div>

              <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200">
                <button
                  onClick={() => setFilterKind('semua')}
                  className={`px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                    filterKind === 'semua' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Semua
                </button>
                <button
                  onClick={() => setFilterKind('prestasi')}
                  className={`px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                    filterKind === 'prestasi' ? 'bg-emerald-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Prestasi
                </button>
                <button
                  onClick={() => setFilterKind('pelanggaran')}
                  className={`px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                    filterKind === 'pelanggaran' ? 'bg-rose-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Pelanggaran
                </button>
              </div>
            </div>

            <div className="divide-y divide-slate-100">
              {filteredLogs.length === 0 ? (
                <EmptyState
                  icon={Award}
                  title="Belum Ada Catatan Poin pada Kategori Ini"
                  description="Tidak ada rekam catatan pelanggaran maupun prestasi pada filter yang Anda pilih."
                />
              ) : (
                filteredLogs.map((log) => (
                  <div key={log.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/80 transition-colors">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                          log.kind === 'prestasi' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'
                        }`}>
                          {log.kind === 'prestasi' ? '+ Prestasi' : '- Pelanggaran'}
                        </span>
                        <span className="text-xs text-slate-400 font-medium">{log.date_str}</span>
                        <span className="text-slate-300">•</span>
                        <span className="text-xs text-slate-500 font-medium">{log.category}</span>
                      </div>

                      <h4 className="font-extrabold text-sm text-slate-900">{log.type_name}</h4>
                      <p className="text-xs text-slate-500 font-normal italic">"{log.note}"</p>
                      <p className="text-[11px] text-slate-400">Pencatat: {log.recorder_name}</p>
                    </div>

                    <span className={`text-lg font-black font-mono px-3 py-1 rounded-xl shadow-2xs self-start sm:self-auto ${
                      log.kind === 'prestasi' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'
                    }`}>
                      {log.points > 0 ? `+${log.points}` : log.points} Poin
                    </span>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* ─── TAB 2: EKSTRAKURIKULER & OSIS ─── */}
      {activeTab === 'ekskul' && (
        <div className="space-y-6">
          {/* Ekskul yang Diikuti */}
          <div className="space-y-3">
            <h3 className="font-extrabold text-xs text-slate-900 uppercase tracking-wider">
              Ekstrakurikuler Aktif yang Diikuti
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {myExtracurriculars.map((ekskul) => (
                <div key={ekskul.id} className="bg-white border border-slate-200/90 rounded-2xl p-5 space-y-3 shadow-xs">
                  <div className="flex items-start justify-between">
                    <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider ${ekskul.badgeColor}`}>
                      {ekskul.role}
                    </span>
                    <span className="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                      Kehadiran: {ekskul.attendance_rate}
                    </span>
                  </div>

                  <div>
                    <h4 className="font-extrabold text-base text-slate-900">{ekskul.name}</h4>
                    <p className="text-xs text-slate-500 font-normal mt-0.5">Pembina: {ekskul.coach}</p>
                  </div>

                  <div className="p-3 bg-slate-50/70 rounded-xl border border-slate-200/80 text-xs space-y-1 text-slate-600">
                    <p className="flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5 text-blue-900" />
                      <strong>Jadwal:</strong> {ekskul.schedule}
                    </p>
                    <p className="flex items-center gap-1.5">
                      <Users className="w-3.5 h-3.5 text-blue-900" />
                      <strong>Lokasi:</strong> {ekskul.room}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Ekskul Lainnya Tersedia */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xs">
            <div className="pb-3 border-b border-slate-100">
              <h3 className="font-extrabold text-base text-slate-900">Katalog Ekstrakurikuler Pilihan Lainnya</h3>
              <p className="text-xs text-slate-500 font-normal mt-0.5">Pendaftaran gelombang tambahan dibuka hingga akhir bulan</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {availableEkskul.map((eks, idx) => (
                <div key={idx} className="p-3.5 rounded-xl border border-slate-200/90 bg-slate-50/60 flex items-center justify-between gap-3 shadow-2xs">
                  <div>
                    <h4 className="font-bold text-sm text-slate-900">{eks.name}</h4>
                    <p className="text-xs text-slate-500 mt-0.5">Pembina: {eks.coach} • {eks.schedule}</p>
                    <span className="text-[10px] font-bold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded mt-1.5 inline-block">
                      {eks.quota}
                    </span>
                  </div>

                  <button
                    onClick={() => alert(`Mengajukan pendaftaran ekstrakurikuler: ${eks.name}`)}
                    className="px-3.5 py-1.5 rounded-xl bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-colors cursor-pointer shadow-xs shrink-0"
                  >
                    Daftar
                  </button>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
