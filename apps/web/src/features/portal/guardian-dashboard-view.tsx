'use client';

import React from 'react';
import Link from 'next/link';
import {
  CalendarCheck,
  FileSpreadsheet,
  Wallet,
  Award,
  ArrowRight,
  BookOpen,
  ChevronRight,
  GraduationCap,
} from 'lucide-react';
import { useGuardian } from '@/lib/guardian-context';
import { useSchoolAnnouncements } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';

function formatDate(value?: string): string {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function GuardianDashboardView() {
  const {
    guardian,
    activeChild,
    activeChildId,
    setActiveChildId,
    activeGrades,
    activeAttendance,
    activeFees,
    activePoints,
    isLoading,
    isError,
    refetchAll,
  } = useGuardian();
  const announcementsQuery = useSchoolAnnouncements();
  const announcements = announcementsQuery.data ?? [];

  const children = guardian?.children ?? [];

  const gradedList = (activeGrades ?? []).filter((g) => typeof g.final_score === 'number' && g.final_score > 0);
  const avgFinal = gradedList.length
    ? Math.round(gradedList.reduce((a, g) => a + (g.final_score || 0), 0) / gradedList.length)
    : null;

  const attendanceDays = (activeAttendance ?? []).filter((d) => d.status !== 'libur');
  const hadirCount = attendanceDays.filter((d) => d.status === 'hadir' || d.status === 'terlambat').length;
  const attendancePct = attendanceDays.length ? Math.round((hadirCount / attendanceDays.length) * 100) : null;

  const outstandingFees = (activeFees ?? []).filter(
    (f) => f.status !== 'Lunas / Bebas Tanggungan',
  );
  const prestasiCount = (activePoints ?? []).filter((p) => p.kind === 'prestasi').length;
  const pelanggaranCount = (activePoints ?? []).filter((p) => p.kind === 'pelanggaran').length;

  if (isLoading) {
    return (
      <div className="py-20">
        <LoadingSpinner label="Memuat data putra/putri Anda..." size="lg" />
      </div>
    );
  }

  if (isError) {
    return (
      <div className="py-16">
        <ErrorState message="Gagal memuat data wali murid. Periksa koneksi lalu coba lagi." onRetry={() => refetchAll()} />
      </div>
    );
  }

  if (children.length === 0) {
    return (
      <div className="py-16">
        <EmptyState
          title="Belum ada data putra/putri"
          description="Akun wali murid Anda belum tertaut dengan data siswa. Silakan hubungi admin tata usaha sekolah."
        />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      {/* Hero + pemilih anak */}
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs text-slate-500 font-medium">Selamat datang,</p>
            <h1 className="text-lg font-extrabold tracking-tight truncate">{guardian?.name || 'Wali Murid'}</h1>
            <p className="text-xs text-slate-600 mt-0.5">
              Memantau: <strong>{activeChild?.name}</strong> &middot; {activeChild?.class_name} &middot; {activeChild?.grade_level}
            </p>
          </div>
          {children.length > 1 && (
            <div className="flex flex-wrap gap-1.5 shrink-0">
              {children.map((c) => (
                <button
                  key={c.id}
                  type="button"
                  onClick={() => setActiveChildId(c.id)}
                  className={
                    'px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ' +
                    (c.id === activeChildId
                      ? 'bg-blue-900 text-white border-blue-900'
                      : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100')
                  }
                >
                  {c.name}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Metrik ringkas anak aktif (live) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <MetricCard icon={<FileSpreadsheet className="w-3.5 h-3.5" />} label="Rata-rata Nilai" value={avgFinal !== null ? String(avgFinal) : '-'} unit="/ 100" sub={gradedList.length + ' mapel dinilai'} href={`/ortu/anak/${activeChildId}`} cta="Lihat rapor" />
        <MetricCard icon={<CalendarCheck className="w-3.5 h-3.5" />} label="Kehadiran" value={attendancePct !== null ? attendancePct + '%' : '-'} unit="" sub={hadirCount + ' dari ' + attendanceDays.length + ' hari'} href="/ortu/presensi" cta="Detail presensi" />
        <MetricCard icon={<Wallet className="w-3.5 h-3.5" />} label="Tagihan" value={String(outstandingFees.length)} unit="item" sub={outstandingFees.length === 0 ? 'Bebas tanggungan' : 'Perlu diselesaikan'} href="/ortu/biaya" cta="Lihat tagihan" />
        <MetricCard icon={<Award className="w-3.5 h-3.5" />} label="Poin Sikap" value={String(prestasiCount)} unit="prestasi" sub={pelanggaranCount + ' pelanggaran'} href="/ortu/poin" cta="Lihat poin" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* Kiri: anak + nilai terbaru */}
        <div className="lg:col-span-7 flex flex-col gap-3">
          <SectionCard icon={<GraduationCap className="w-4 h-4" />} title="Profil Akademik Anak" subtitle={activeChild?.homeroom_teacher ? ('Wali kelas: ' + activeChild.homeroom_teacher) : undefined} action={<Link href={`/ortu/anak/${activeChildId}`} className="text-xs font-bold text-blue-900 hover:underline inline-flex items-center gap-1">Detail lengkap <ChevronRight className="w-3.5 h-3.5" /></Link>}>
            <div className="grid grid-cols-2 gap-2 text-xs">
              <Info label="NISN" value={activeChild?.nisn || '-'} />
              <Info label="NIS" value={activeChild?.nis || '-'} />
              <Info label="Kelas" value={activeChild?.class_name || '-'} />
              <Info label="Fase" value={activeChild?.curriculum_phase || '-'} />
            </div>
          </SectionCard>

          <SectionCard icon={<FileSpreadsheet className="w-4 h-4" />} title="Ringkasan Nilai Mata Pelajaran" subtitle="Nilai akhir per mapel semester berjalan" action={<Link href={`/ortu/anak/${activeChildId}`} className="text-xs font-bold text-blue-900 hover:underline">Semua nilai</Link>}>
            {gradedList.length === 0 ? (
              <EmptyState title="Nilai belum tersedia" description="Guru belum menginput nilai untuk periode ini." />
            ) : (
              <div className="space-y-1.5">
                {gradedList.slice(0, 6).map((g) => (
                  <div key={g.subject_code} className="flex items-center justify-between p-2.5 rounded-lg border border-slate-200 bg-white">
                    <div className="min-w-0">
                      <p className="text-sm font-semibold truncate">{g.subject_name}</p>
                      <p className="text-[11px] text-slate-500 truncate">{g.teacher_name}</p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      <span className="text-sm font-bold">{g.final_score}</span>
                      <span className={'px-2 py-0.5 rounded-md text-[10px] font-bold ' + (g.status === 'Tuntas' ? 'bg-emerald-700 text-white' : 'bg-amber-600 text-white')}>{g.predicate}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>

        {/* Kanan: tagihan + pengumuman */}
        <div className="lg:col-span-5 flex flex-col gap-3">
          <SectionCard icon={<Wallet className="w-4 h-4" />} title="Status Tagihan" subtitle="Administrasi keuangan sekolah" action={<Link href="/ortu/biaya" className="text-xs font-bold text-blue-900 hover:underline">Kelola</Link>}>
            {outstandingFees.length === 0 ? (
              <EmptyState title="Bebas tanggungan" description="Tidak ada tagihan yang perlu diselesaikan saat ini." />
            ) : (
              <div className="space-y-2">
                {outstandingFees.slice(0, 4).map((f) => (
                  <div key={f.id} className="p-2.5 rounded-xl border border-slate-200 bg-white border-l-4 border-l-amber-600">
                    <div className="flex items-center justify-between">
                      <h4 className="text-xs font-bold truncate">{f.item_name}</h4>
                      <span className="text-xs font-bold text-slate-900 font-mono shrink-0">{f.nominal_text}</span>
                    </div>
                    <p className="text-[11px] text-slate-500 mt-0.5">{f.period_label} &middot; {f.status}</p>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>

          <SectionCard icon={<BookOpen className="w-4 h-4" />} title="Pengumuman Sekolah" subtitle="Informasi terbaru">
            {announcementsQuery.isLoading ? (
              <LoadingSpinner label="Memuat pengumuman..." size="sm" />
            ) : announcements.length === 0 ? (
              <EmptyState title="Belum ada pengumuman" description="Belum ada pengumuman terbaru dari sekolah." />
            ) : (
              <div className="space-y-2">
                {announcements.map((ann) => (
                  <div key={ann.id} className="p-2.5 rounded-xl border border-slate-200 bg-white border-l-4 border-l-blue-900">
                    <span className="text-[11px] font-semibold text-slate-500 font-mono">{formatDate(ann.publish_at || ann.published_at || ann.created_at)}</span>
                    <h4 className="text-xs font-bold mt-0.5 leading-snug">{ann.title}</h4>
                    <p className="text-xs text-slate-600 mt-0.5 line-clamp-2">{ann.content || ann.body}</p>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>
      </div>
    </div>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
      <span className="text-[10px] font-bold text-slate-500 uppercase">{label}</span>
      <p className="font-semibold text-slate-900 mt-0.5 truncate">{value}</p>
    </div>
  );
}

function MetricCard({ icon, label, value, unit, sub, href, cta }: { icon: React.ReactNode; label: string; value: string; unit: string; sub: string; href: string; cta: string; }) {
  return (
    <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs flex flex-col justify-between">
      <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
        <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">{label}</span>
        <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">{icon}</div>
      </div>
      <div className="my-1.5">
        <div className="flex items-baseline gap-1">
          <span className="text-3xl font-extrabold tracking-tight">{value}</span>
          {unit && <span className="text-sm font-bold text-slate-500">{unit}</span>}
        </div>
        <p className="text-xs text-slate-500 font-medium mt-0.5 truncate">{sub}</p>
      </div>
      <div className="pt-2 border-t border-slate-100">
        <Link href={href} className="text-xs font-bold text-slate-700 hover:text-blue-900 flex items-center justify-between group">
          <span>{cta}</span>
          <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-900 group-hover:translate-x-0.5 transition-all" />
        </Link>
      </div>
    </div>
  );
}

function SectionCard({ icon, title, subtitle, action, children }: { icon: React.ReactNode; title: string; subtitle?: string; action?: React.ReactNode; children: React.ReactNode; }) {
  return (
    <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs">
      <div className="flex items-center justify-between pb-2.5 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center border border-slate-200 shrink-0">{icon}</div>
          <div>
            <h2 className="text-sm font-bold tracking-tight">{title}</h2>
            {subtitle && <p className="text-xs text-slate-500">{subtitle}</p>}
          </div>
        </div>
        {action}
      </div>
      <div className="mt-2.5">{children}</div>
    </div>
  );
}
