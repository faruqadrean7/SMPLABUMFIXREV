'use client';

import React, { useState } from 'react';
import {
  Award,
  ShieldCheck,
  PlusCircle,
  MinusCircle,
  Clock,
  Users,
  CheckCircle2,
  Loader2,
  AlertCircle,
  Activity,
  Layers,
} from 'lucide-react';
import {
  useStudentPoints,
  useStudentExtracurriculars,
  useRegisterExtracurricular,
} from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { StudentError } from '@/features/portal/mascot/student-states';
import { EmptyState } from '@/components/shared/empty-state';

export default function StudentPoinPage() {
  const [activeTab, setActiveTab] = useState<'poin' | 'ekskul'>('poin');
  const [filterKind, setFilterKind] = useState<'semua' | 'prestasi' | 'pelanggaran'>('semua');
  const [registeringId, setRegisteringId] = useState<string | null>(null);
  const [feedbackMessage, setFeedbackMessage] = useState<{ text: string; type: 'success' | 'error' } | null>(null);

  const {
    data: pointsData,
    isLoading: isPointsLoading,
    isError: isPointsError,
    refetch: refetchPoints,
  } = useStudentPoints();

  const {
    data: ekskulData,
    isLoading: isEkskulLoading,
    isError: isEkskulError,
    refetch: refetchEkskul,
  } = useStudentExtracurriculars();

  const registerMutation = useRegisterExtracurricular();

  const totalPrestasi = pointsData?.totalPrestasi ?? 0;
  const totalPelanggaran = pointsData?.totalPelanggaran ?? 0;
  const balancePoints = pointsData?.balancePoints ?? 0;
  const statusDisiplin = pointsData?.statusDisiplin ?? 'AMAN';
  const allPointItems = pointsData?.items ?? [];

  const filteredLogs = filterKind === 'semua'
    ? allPointItems
    : allPointItems.filter((item) => item.kind === filterKind);

  const myExtracurriculars = ekskulData?.myExtracurriculars ?? [];
  const availableEkskul = ekskulData?.availableExtracurriculars ?? [];

  const handleRegisterEkskul = async (id: string, name: string) => {
    setRegisteringId(id);
    setFeedbackMessage(null);
    try {
      await registerMutation.mutateAsync(id);
      setFeedbackMessage({
        text: `Berhasil mendaftar ke ekstrakurikuler ${name}!`,
        type: 'success',
      });
      refetchEkskul();
    } catch (err: any) {
      setFeedbackMessage({
        text: err?.response?.data?.message || err?.message || 'Gagal mendaftar ekstrakurikuler.',
        type: 'error',
      });
    } finally {
      setRegisteringId(null);
    }
  };

  return (
    <div className="space-y-6 text-slate-900">
      {/* Header */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <span className="p-2 rounded-xl bg-blue-50 text-blue-900 border border-blue-200/80">
              <Award className="w-5 h-5" />
            </span>
            <h1 className="text-xl font-extrabold tracking-tight text-slate-900">
              Buku Poin Karakter &amp; Ekstrakurikuler
            </h1>
          </div>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pantau catatan apresiasi prestasi, ketertiban siswa, serta aktivitas ekstrakurikuler aktif.
          </p>
        </div>

        {/* Tab Selector */}
        <div className="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-bold self-start sm:self-auto">
          <button
            type="button"
            onClick={() => setActiveTab('poin')}
            className={`flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg transition-all cursor-pointer ${
              activeTab === 'poin'
                ? 'bg-white text-blue-900 shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Activity className="w-3.5 h-3.5" />
            <span>Poin &amp; Karakter</span>
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('ekskul')}
            className={`flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg transition-all cursor-pointer ${
              activeTab === 'ekskul'
                ? 'bg-white text-blue-900 shadow-xs'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Layers className="w-3.5 h-3.5" />
            <span>Ekstrakurikuler</span>
          </button>
        </div>
      </div>

      {/* ─── TAB 1: POIN KARAKTER & PRESTASI ─── */}
      {activeTab === 'poin' && (
        <div className="space-y-6">
          {/* Summary Cards */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-slate-500">Total Prestasi</span>
                <span className="p-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <PlusCircle className="w-4 h-4" />
                </span>
              </div>
              <p className="text-2xl sm:text-3xl font-extrabold text-emerald-800 mt-2">
                +{totalPrestasi}
              </p>
              <p className="text-[11px] text-slate-500 mt-1">Poin penghargaan &amp; lomba</p>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-slate-500">Total Pelanggaran</span>
                <span className="p-1.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                  <MinusCircle className="w-4 h-4" />
                </span>
              </div>
              <p className="text-2xl sm:text-3xl font-extrabold text-rose-700 mt-2">
                -{totalPelanggaran}
              </p>
              <p className="text-[11px] text-slate-500 mt-1">Poin ketertiban &amp; tata tertib</p>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-slate-500">Saldo Poin</span>
                <span className="p-1.5 rounded-lg bg-blue-50 text-blue-900 border border-blue-200">
                  <Award className="w-4 h-4" />
                </span>
              </div>
              <p className={`text-2xl sm:text-3xl font-extrabold mt-2 ${balancePoints >= 0 ? 'text-blue-900' : 'text-rose-700'}`}>
                {balancePoints > 0 ? `+${balancePoints}` : balancePoints}
              </p>
              <p className="text-[11px] text-slate-500 mt-1">Netto pembiasaan karakter</p>
            </div>

            <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-slate-500">Status Pembinaan</span>
                <span className="p-1.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                  <ShieldCheck className="w-4 h-4" />
                </span>
              </div>
              <p className="text-base sm:text-lg font-extrabold text-indigo-950 mt-3 truncate">
                {statusDisiplin}
              </p>
              <p className="text-[11px] text-slate-500 mt-1">
                {statusDisiplin === 'AMAN' ? 'Tidak ada surat peringatan' : 'Perlu konsultasi BK'}
              </p>
            </div>
          </div>

          {/* Log Poin List */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
              <div>
                <h3 className="font-extrabold text-base text-slate-900">Riwayat Catatan Karakter</h3>
                <p className="text-xs text-slate-500 mt-0.5">Diverifikasi oleh tim kesiswaan, guru BK, dan wali kelas</p>
              </div>

              {/* Filter chips */}
              <div className="flex items-center gap-1.5">
                {(['semua', 'prestasi', 'pelanggaran'] as const).map((kind) => (
                  <button
                    key={kind}
                    type="button"
                    onClick={() => setFilterKind(kind)}
                    className={`px-3 py-1 rounded-lg text-xs font-bold capitalize transition-colors cursor-pointer ${
                      filterKind === kind
                        ? 'bg-slate-900 text-white'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    }`}
                  >
                    {kind}
                  </button>
                ))}
              </div>
            </div>

            {isPointsLoading ? (
              <div className="py-12">
                <LoadingSpinner label="Memuat riwayat poin..." size="md" />
              </div>
            ) : isPointsError ? (
              <StudentError
                message="Gagal memuat catatan poin karakter."
                onRetry={() => refetchPoints()}
              />
            ) : filteredLogs.length === 0 ? (
              <EmptyState
                title="Belum ada catatan poin"
                description={
                  filterKind === 'semua'
                    ? 'Belum ada catatan prestasi atau pelanggaran yang tercatat untuk Anda.'
                    : `Belum ada riwayat kategori ${filterKind}.`
                }
              />
            ) : (
              <div className="divide-y divide-slate-100">
                {filteredLogs.map((item) => {
                  const isPrestasi = item.kind === 'prestasi';
                  return (
                    <div key={item.id} className="py-3.5 flex items-start justify-between gap-4">
                      <div className="flex items-start gap-3">
                        <span
                          className={`mt-0.5 p-1.5 rounded-lg shrink-0 ${
                            isPrestasi
                              ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                              : 'bg-rose-50 text-rose-700 border border-rose-200'
                          }`}
                        >
                          {isPrestasi ? (
                            <PlusCircle className="w-4 h-4" />
                          ) : (
                            <MinusCircle className="w-4 h-4" />
                          )}
                        </span>
                        <div>
                          <p className="text-sm font-bold text-slate-900">{item.title}</p>
                          <p className="text-xs text-slate-500 mt-0.5">{item.note}</p>
                          <div className="flex flex-wrap items-center gap-2 mt-1.5 text-[11px] text-slate-400">
                            <span className="font-semibold text-slate-600">{item.category}</span>
                            <span>•</span>
                            <span>{item.point_date}</span>
                            <span>•</span>
                            <span>Pencatat: {item.recorder_name}</span>
                          </div>
                        </div>
                      </div>
                      <span
                        className={`text-sm font-extrabold shrink-0 px-2.5 py-1 rounded-lg ${
                          isPrestasi
                            ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                            : 'bg-rose-50 text-rose-800 border border-rose-200'
                        }`}
                      >
                        {isPrestasi ? `+${item.points}` : `-${item.points}`}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      )}

      {/* ─── TAB 2: EKSTRAKURIKULER ─── */}
      {activeTab === 'ekskul' && (
        <div className="space-y-6">
          {/* Feedback banner */}
          {feedbackMessage && (
            <div
              className={`p-3.5 rounded-xl text-xs font-semibold flex items-center gap-2 border shadow-2xs ${
                feedbackMessage.type === 'success'
                  ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                  : 'bg-rose-50 text-rose-800 border-rose-200'
              }`}
            >
              {feedbackMessage.type === 'success' ? (
                <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-700" />
              ) : (
                <AlertCircle className="w-4 h-4 shrink-0 text-rose-700" />
              )}
              <span>{feedbackMessage.text}</span>
            </div>
          )}

          {/* Ekskul yang Diikuti */}
          <div className="space-y-3">
            <h3 className="font-extrabold text-xs text-slate-900 uppercase tracking-wider">
              Ekstrakurikuler Aktif yang Diikuti
            </h3>

            {isEkskulLoading ? (
              <div className="py-12 bg-white border border-slate-200/90 rounded-2xl">
                <LoadingSpinner label="Memuat kegiatan ekstrakurikuler..." size="md" />
              </div>
            ) : isEkskulError ? (
              <StudentError
                message="Gagal memuat data ekstrakurikuler."
                onRetry={() => refetchEkskul()}
              />
            ) : myExtracurriculars.length === 0 ? (
              <div className="bg-white border border-slate-200/90 rounded-2xl p-4">
                <EmptyState
                  title="Belum terdaftar di ekstrakurikuler"
                  description="Pilih salah satu kegiatan ekstrakurikuler di katalog bawah untuk bergabung."
                />
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {myExtracurriculars.map((ekskul) => (
                  <div
                    key={ekskul.id}
                    className="bg-white border border-slate-200/90 rounded-2xl p-5 space-y-3 shadow-xs"
                  >
                    <div className="flex items-start justify-between">
                      <span
                        className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider ${ekskul.badgeColor}`}
                      >
                        {ekskul.role}
                      </span>
                      <span className="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        Kehadiran: {ekskul.attendance_rate}
                      </span>
                    </div>

                    <div>
                      <h4 className="font-extrabold text-base text-slate-900">{ekskul.name}</h4>
                      <p className="text-xs text-slate-500 font-normal mt-0.5">
                        Pembina: {ekskul.coach}
                      </p>
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
            )}
          </div>

          {/* Ekskul Lainnya Tersedia */}
          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xs">
            <div className="pb-3 border-b border-slate-100">
              <h3 className="font-extrabold text-base text-slate-900">
                Katalog Ekstrakurikuler Pilihan Lainnya
              </h3>
              <p className="text-xs text-slate-500 font-normal mt-0.5">
                Pilih kegiatan ekstrakurikuler yang sesuai dengan minat dan bakat Anda
              </p>
            </div>

            {isEkskulLoading ? (
              <div className="py-8">
                <LoadingSpinner label="Memuat katalog ekstrakurikuler..." size="sm" />
              </div>
            ) : availableEkskul.length === 0 ? (
              <EmptyState
                title="Semua ekstrakurikuler telah diikuti atau kuota penuh"
                description="Tidak ada pilihan kegiatan ekstrakurikuler baru yang dapat didaftarkan saat ini."
              />
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {availableEkskul.map((eks) => {
                  const isCurrentRegistering = registeringId === eks.id;
                  return (
                    <div
                      key={eks.id}
                      className="p-3.5 rounded-xl border border-slate-200/90 bg-slate-50/60 flex items-center justify-between gap-3 shadow-2xs"
                    >
                      <div>
                        <h4 className="font-bold text-sm text-slate-900">{eks.name}</h4>
                        <p className="text-xs text-slate-500 mt-0.5">
                          Pembina: {eks.coach} • {eks.schedule}
                        </p>
                        <span className="text-[10px] font-bold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded mt-1.5 inline-block">
                          {eks.quota}
                        </span>
                      </div>

                      <button
                        type="button"
                        disabled={isCurrentRegistering}
                        onClick={() => handleRegisterEkskul(eks.id, eks.name)}
                        className="px-3.5 py-1.5 rounded-xl bg-blue-900 hover:bg-blue-950 disabled:bg-blue-900/50 text-white text-xs font-bold transition-colors cursor-pointer shadow-xs shrink-0 flex items-center gap-1.5"
                      >
                        {isCurrentRegistering ? (
                          <>
                            <Loader2 className="w-3.5 h-3.5 animate-spin" />
                            <span>Mendaftar...</span>
                          </>
                        ) : (
                          <span>Daftar</span>
                        )}
                      </button>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
