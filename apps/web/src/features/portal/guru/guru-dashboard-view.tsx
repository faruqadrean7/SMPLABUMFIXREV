'use client';

import React from 'react';
import Link from 'next/link';
import {
  Users,
  Calendar,
  CheckSquare,
  CalendarCheck,
  Clock,
  ArrowRight,
  FileSpreadsheet,
  UserCheck,
  BookOpen,
  ChevronRight,
} from 'lucide-react';
import { useAuth } from '@/lib/auth-context';
import { useTeacherDashboard, useTeacherClasses, useTeacherSchedules } from '@/lib/use-teacher-data';
import { useSchoolAnnouncements } from '@/lib/use-student-data';
import { useTeacherAssignments, useHomeroomLeaveRequests } from '@/features/portal/guru/use-guru-portal';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';

function todayIso(): number {
  const d = new Date().getDay(); // 0=Minggu
  return ((d + 6) % 7) + 1; // 1=Senin ... 7=Minggu (ISO, sesuai backend)
}

function formatDate(value?: string): string {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function GuruDashboardView() {
  const { user } = useAuth();
  const dash = useTeacherDashboard();
  const classesQuery = useTeacherClasses();
  const schedulesQuery = useTeacherSchedules();
  const announcementsQuery = useSchoolAnnouncements();
  const assignmentsQuery = useTeacherAssignments();

  const roles = (user?.roles || []).map((r: any) => String(r).toLowerCase());
  const isHomeroom = roles.includes('homeroom');
  const isCounselor = roles.includes('counselor');
  const leavesQuery = useHomeroomLeaveRequests({ status: 'menunggu', per_page: 5 }, isHomeroom);

  const dashboard = dash.data;
  const classes = classesQuery.data ?? [];
  const schedules = schedulesQuery.data ?? [];
  const announcements = announcementsQuery.data ?? [];
  const assignments = assignmentsQuery.data ?? [];
  const pendingLeaves = leavesQuery.data ?? [];

  const teacherName = dashboard?.teacherName || user?.full_name || user?.name || 'Guru';
  const nip = dashboard?.nip || user?.nip || '-';
  const roleLabel = isHomeroom ? 'Wali Kelas' : isCounselor ? 'Guru BK' : 'Guru Pengampu';
  const initials =
    teacherName.split(' ').filter(Boolean).map((n: string) => n[0]).slice(0, 2).join('').toUpperCase() || 'GR';

  const totalStudents = classes.reduce((acc, c) => acc + (c.studentCount || 0), 0);
  const today = todayIso();
  const todaySchedules = [...schedules]
    .filter((s) => s.dayOfWeek === today)
    .sort((a, b) => (a.startTime || '').localeCompare(b.startTime || ''));
  const todayCount = dashboard?.todaySchedulesCount ?? todaySchedules.length;
  const pendingGrading = assignments.reduce(
    (n, a) => n + Math.max(0, (a.submittedCount || 0) - (a.gradedCount || 0)),
    0,
  );
  const gradeQueue = assignments.filter((a) => (a.submittedCount || 0) > (a.gradedCount || 0)).slice(0, 6);
  const pendingJournals = dashboard?.pendingJournalsCount ?? 0;

  if (dash.isLoading) {
    return (
      <div className="py-20">
        <LoadingSpinner label="Memuat dashboard guru..." size="lg" />
      </div>
    );
  }

  if (dash.isError) {
    return (
      <div className="py-16">
        <ErrorState
          message="Gagal memuat data dashboard guru. Periksa koneksi lalu coba lagi."
          onRetry={() => dash.refetch()}
        />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      {/* Hero identitas guru */}
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-3.5 min-w-0">
            <div className="w-12 h-12 rounded-xl bg-blue-900 text-white font-extrabold flex items-center justify-center text-base shrink-0">
              {initials}
            </div>
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-lg font-extrabold tracking-tight truncate">{teacherName}</h1>
                <span className="px-2 py-0.5 rounded-lg text-xs font-bold bg-blue-900 text-white">{roleLabel}</span>
                <span className="px-2 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 border border-slate-300 text-slate-700 font-mono">
                  NIP: {nip}
                </span>
              </div>
              <p className="mt-1 text-xs text-slate-600">
                {todayCount} sesi mengajar hari ini &middot; {classes.length} rombel diampu
              </p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2 shrink-0">
            <Link href="/guru/jurnal" className="px-3.5 py-2 rounded-xl bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold flex items-center gap-1.5">
              <CalendarCheck className="w-4 h-4" /> Jurnal &amp; Presensi
            </Link>
            <Link href="/guru/nilai" className="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold flex items-center gap-1.5">
              <FileSpreadsheet className="w-4 h-4" /> Buku Nilai
            </Link>
            <Link href="/guru/tugas" className="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 text-xs font-bold flex items-center gap-1.5">
              <CheckSquare className="w-4 h-4" /> Tugas
            </Link>
          </div>
        </div>
      </div>

      {/* Metrik ringkas (live) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <MetricCard icon={<Users className="w-3.5 h-3.5" />} label="Rombel & Siswa" value={totalStudents} unit="Siswa" sub={classes.length + ' rombel diampu'} href="/guru/siswa" cta="Daftar siswa" />
        <MetricCard icon={<Calendar className="w-3.5 h-3.5" />} label="Jadwal Hari Ini" value={todayCount} unit="Sesi" sub="Lihat jadwal mingguan" href="/guru/jadwal" cta="Buka jadwal" />
        <MetricCard icon={<CheckSquare className="w-3.5 h-3.5" />} label="Tugas Perlu Dinilai" value={pendingGrading} unit="Berkas" sub={assignments.length + ' tugas aktif'} href="/guru/tugas" cta="Periksa tugas" />
        <MetricCard icon={<CalendarCheck className="w-3.5 h-3.5" />} label="Jurnal Belum Diisi" value={pendingJournals} unit="Sesi" sub="Lengkapi administrasi KBM" href="/guru/jurnal" cta="Isi jurnal" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        {/* Kolom kiri: jadwal hari ini + antrean penilaian */}
        <div className="lg:col-span-7 flex flex-col gap-3">
          <SectionCard icon={<Clock className="w-4 h-4" />} title="Jadwal Tatap Muka Hari Ini" subtitle={`${todaySchedules.length} sesi terjadwal`}>
            {todaySchedules.length === 0 ? (
              <EmptyState title="Tidak ada jadwal hari ini" description="Jadwal mengajar untuk hari ini belum ada atau libur." />
            ) : (
              <div className="space-y-2">
                {todaySchedules.map((s) => (
                  <div key={s.id} className="p-3 rounded-xl border border-slate-200 bg-slate-50/50">
                    <div className="flex flex-wrap items-center justify-between gap-1.5">
                      <span className="font-mono text-xs font-bold">{s.startTime} - {s.endTime} WIB</span>
                      <span className="text-xs text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">{s.roomName || 'Ruang KBM'}</span>
                    </div>
                    <h4 className="mt-1.5 text-sm font-bold">{s.subjectName} <span className="font-medium text-slate-500">({s.className})</span></h4>
                    <div className="mt-1 flex items-center justify-between">
                      <span className={'text-xs font-semibold ' + (s.isAttendanceRecorded ? 'text-emerald-700' : 'text-amber-700')}>
                        {s.isAttendanceRecorded ? 'Presensi terisi' : 'Presensi belum difinalkan'}
                      </span>
                      <Link href="/guru/jurnal" className="text-xs font-bold text-emerald-800 hover:underline inline-flex items-center gap-1">
                        Input jurnal <ArrowRight className="w-3 h-3" />
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>

          <SectionCard
            icon={<CheckSquare className="w-4 h-4" />}
            title="Antrean Penilaian Tugas"
            subtitle="Tugas dengan pengumpulan yang belum dinilai"
            action={<Link href="/guru/tugas" className="text-xs font-bold text-blue-900 hover:text-blue-950 inline-flex items-center gap-1">Semua tugas <ChevronRight className="w-3.5 h-3.5" /></Link>}
          >
            {assignmentsQuery.isLoading ? (
              <LoadingSpinner label="Memuat tugas..." size="sm" />
            ) : gradeQueue.length === 0 ? (
              <EmptyState title="Tidak ada antrean penilaian" description="Semua pengumpulan tugas sudah dinilai." />
            ) : (
              <div className="space-y-2">
                {gradeQueue.map((a) => (
                  <div key={a.id} className="p-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-colors flex items-center justify-between gap-2">
                    <div className="min-w-0">
                      <p className="text-sm font-bold truncate">{a.title}</p>
                      <p className="text-xs text-slate-500 truncate">{a.subjectName} &middot; {a.className}</p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      <span className="px-2.5 py-0.5 rounded-md text-xs font-bold bg-amber-600 text-white">
                        {Math.max(0, (a.submittedCount || 0) - (a.gradedCount || 0))} perlu dinilai
                      </span>
                      <Link href={`/guru/tugas/${a.id}/penilaian`} className="px-2.5 py-1 rounded-lg bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold inline-flex items-center gap-1">
                        Nilai <ArrowRight className="w-3 h-3" />
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>

        {/* Kolom kanan: perizinan (walas) + pengumuman */}
        <div className="lg:col-span-5 flex flex-col gap-3">
          {isHomeroom && (
            <SectionCard icon={<UserCheck className="w-4 h-4" />} title="Perizinan Siswa Binaan" subtitle="Menunggu persetujuan wali kelas" action={<Link href="/guru/perizinan" className="text-xs font-bold text-blue-900 hover:underline">Kelola</Link>}>
              {leavesQuery.isLoading ? (
                <LoadingSpinner label="Memuat perizinan..." size="sm" />
              ) : pendingLeaves.length === 0 ? (
                <EmptyState title="Tidak ada pengajuan izin" description="Belum ada perizinan siswa yang menunggu persetujuan." />
              ) : (
                <div className="space-y-2">
                  {pendingLeaves.map((leave) => (
                    <div key={leave.id} className="p-2.5 rounded-xl border border-slate-200 bg-white">
                      <div className="flex items-center justify-between">
                        <h4 className="text-xs font-bold truncate">{leave.studentName || (leave as any).student?.full_name || 'Siswa'}</h4>
                        <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-600 text-white uppercase">{leave.leave_type}</span>
                      </div>
                      <p className="text-[11px] text-slate-500 mt-0.5">{formatDate(leave.start_date)} &ndash; {formatDate(leave.end_date)}</p>
                      <p className="text-[11px] text-slate-600 mt-0.5 truncate">{leave.reason}</p>
                    </div>
                  ))}
                </div>
              )}
            </SectionCard>
          )}

          <SectionCard icon={<BookOpen className="w-4 h-4" />} title="Pengumuman & Agenda" subtitle="Informasi dari sekolah">
            {announcementsQuery.isLoading ? (
              <LoadingSpinner label="Memuat pengumuman..." size="sm" />
            ) : announcements.length === 0 ? (
              <EmptyState title="Belum ada pengumuman" description="Belum ada pengumuman terbaru dari sekolah." />
            ) : (
              <div className="space-y-2">
                {announcements.map((ann) => (
                  <div key={ann.id} className="p-2.5 rounded-xl border border-slate-200 bg-white border-l-4 border-l-blue-900">
                    <div className="flex items-center justify-between">
                      <span className="text-[11px] font-semibold text-slate-500 font-mono">{formatDate(ann.publish_at || ann.published_at || ann.created_at)}</span>
                      {ann.priority && <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-800 text-white uppercase">{ann.priority}</span>}
                    </div>
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

function MetricCard({ icon, label, value, unit, sub, href, cta }: { icon: React.ReactNode; label: string; value: number; unit: string; sub: string; href: string; cta: string; }) {
  return (
    <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs flex flex-col justify-between">
      <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
        <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">{label}</span>
        <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">{icon}</div>
      </div>
      <div className="my-1.5">
        <div className="flex items-baseline gap-1">
          <span className="text-3xl font-extrabold tracking-tight">{value}</span>
          <span className="text-sm font-bold text-slate-500">{unit}</span>
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
