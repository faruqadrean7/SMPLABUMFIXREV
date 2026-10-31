'use client';

import React from 'react';
import Link from 'next/link';
import { CalendarCheck, Award, CheckSquare, MonitorPlay, Clock, ArrowRight, BookOpen, ChevronRight } from 'lucide-react';
import { useAuth } from '@/lib/auth-context';
import { useStudentDashboard, useStudentTasks, useSchoolAnnouncements } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { EmptyState } from '@/components/shared/empty-state';
import { TahfidzWidget } from './student-programs/tahfidz-widget';
import { IcpWidget } from './student-programs/icp-widget';
import { InklusiWidget } from './student-programs/inklusi-widget';
import { RegulerWidget } from './student-programs/reguler-widget';

function formatDate(value?: string): string {
  if (!value) return '';
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function StudentDashboardView() {
  const { user } = useAuth();
  const dash = useStudentDashboard();
  const tasksQuery = useStudentTasks();
  const announcementsQuery = useSchoolAnnouncements();

  const data = dash.data;
  const tasks = tasksQuery.data ?? [];
  const announcements = announcementsQuery.data ?? [];

  const name = data?.name || user?.full_name || user?.name || 'Siswa';
  const className = data?.className || '-';
  const todaySchedule = data?.todaySchedule ?? [];
  const upcomingExams = data?.upcomingExams ?? [];
  const activeTasks = tasks.filter((t) => t.status === 'aktif');
  const initials = name.split(' ').filter(Boolean).map((n: string) => n[0]).slice(0, 2).join('').toUpperCase() || 'SW';

  if (dash.isLoading) return <div className="py-20"><LoadingSpinner label="Memuat dashboard..." size="lg" /></div>;
  if (dash.isError) return <div className="py-16"><ErrorState message="Gagal memuat dashboard siswa. Coba lagi." onRetry={() => dash.refetch()} /></div>;

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex items-center justify-between gap-3.5">
        <div className="flex items-center gap-3.5 min-w-0">
          <div className="w-12 h-12 rounded-xl bg-blue-900 text-white font-extrabold flex items-center justify-center text-base shrink-0">{initials}</div>
          <div className="min-w-0">
            <h1 className="text-lg font-extrabold tracking-tight truncate">{name}</h1>
            <p className="text-xs text-slate-600 mt-0.5">{className} &middot; NIS {data?.nis || '-'}</p>
          </div>
        </div>
        {data?.programBadge && (
          <div className="shrink-0 hidden sm:block">
            <span
              className={`px-3 py-1 rounded-full text-xs font-bold border ${
                data.programType === 'tahfidz'
                  ? 'bg-emerald-50 text-emerald-900 border-emerald-300'
                  : data.programType === 'icp'
                  ? 'bg-blue-50 text-blue-900 border-blue-300'
                  : data.programType === 'inklusi'
                  ? 'bg-purple-50 text-purple-900 border-purple-300'
                  : 'bg-slate-100 text-slate-800 border-slate-300'
              }`}
            >
              {data.programBadge}
            </span>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <MetricCard icon={<CalendarCheck className="w-3.5 h-3.5" />} label="Kehadiran" value={(data?.attendanceRate ?? 0) + '%'} sub="Semester berjalan" href="/siswa/jadwal" cta="Lihat jadwal" />
        <MetricCard icon={<CheckSquare className="w-3.5 h-3.5" />} label="Tugas Aktif" value={String(activeTasks.length)} sub="Belum dikumpulkan" href="/siswa/tugas" cta="Kerjakan tugas" />
        <MetricCard icon={<MonitorPlay className="w-3.5 h-3.5" />} label="Ujian Mendatang" value={String(upcomingExams.length)} sub="Terjadwal" href="/siswa/cbt" cta="Lihat ujian" />
        <MetricCard icon={<Award className="w-3.5 h-3.5" />} label="Prestasi" value={String(data?.totalAchievements ?? 0)} sub="Poin prestasi" href="/siswa/poin" cta="Lihat poin" />
      </div>

      {/* Widget Khusus Program Belajar Siswa */}
      {data?.programType === 'tahfidz' && <TahfidzWidget profile={data.programProfile} />}
      {data?.programType === 'icp' && <IcpWidget profile={data.programProfile} />}
      {data?.programType === 'inklusi' && <InklusiWidget profile={data.programProfile} />}
      {(!data?.programType || data?.programType === 'reguler') && <RegulerWidget profile={data?.programProfile} />}

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
        <div className="lg:col-span-7 flex flex-col gap-3">
          <SectionCard icon={<Clock className="w-4 h-4" />} title="Jadwal Hari Ini" subtitle={`${todaySchedule.length} sesi`}>
            {todaySchedule.length === 0 ? (
              <EmptyState title="Tidak ada jadwal hari ini" description="Jadwal pelajaran untuk hari ini belum ada atau libur." />
            ) : (
              <div className="space-y-2">
                {todaySchedule.map((s) => (
                  <div key={s.id} className="p-3 rounded-xl border border-slate-200 bg-slate-50/50">
                    <div className="flex flex-wrap items-center justify-between gap-1.5">
                      <span className="font-mono text-xs font-bold">{s.startTime} - {s.endTime} WIB</span>
                      <span className="text-xs text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">{s.roomName}</span>
                    </div>
                    <h4 className="mt-1.5 text-sm font-bold">{s.subjectName}</h4>
                    <p className="text-[11px] text-slate-500">{s.teacherName}</p>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>

          <SectionCard icon={<CheckSquare className="w-4 h-4" />} title="Tugas Perlu Dikerjakan" subtitle="Belum dikumpulkan" action={<Link href="/siswa/tugas" className="text-xs font-bold text-blue-900 hover:underline inline-flex items-center gap-1">Semua tugas <ChevronRight className="w-3.5 h-3.5" /></Link>}>
            {tasksQuery.isLoading ? (
              <LoadingSpinner label="Memuat tugas..." size="sm" />
            ) : activeTasks.length === 0 ? (
              <EmptyState title="Tidak ada tugas aktif" description="Semua tugas sudah dikumpulkan. Kerja bagus!" />
            ) : (
              <div className="space-y-2">
                {activeTasks.slice(0, 5).map((t) => (
                  <div key={t.id} className="p-3 rounded-xl border border-slate-200 bg-white flex items-center justify-between gap-2">
                    <div className="min-w-0">
                      <p className="text-sm font-bold truncate">{t.title}</p>
                      <p className="text-[11px] text-slate-500 truncate">{t.subjectName} &middot; {t.teacherName}</p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      <span className="text-[11px] text-amber-700 font-semibold">{formatDate(t.dueDate)} {t.dueTime}</span>
                      <Link href="/siswa/tugas" className="px-2.5 py-1 rounded-lg bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold inline-flex items-center gap-1">Kumpul <ArrowRight className="w-3 h-3" /></Link>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>

        <div className="lg:col-span-5 flex flex-col gap-3">
          <SectionCard icon={<MonitorPlay className="w-4 h-4" />} title="Ujian Mendatang" subtitle="Jadwal CBT">
            {upcomingExams.length === 0 ? (
              <EmptyState title="Belum ada ujian" description="Belum ada ujian yang dijadwalkan." />
            ) : (
              <div className="space-y-2">
                {upcomingExams.map((e) => (
                  <div key={e.id} className="p-2.5 rounded-xl border border-slate-200 bg-white border-l-4 border-l-blue-900">
                    <h4 className="text-xs font-bold truncate">{e.title}</h4>
                    <p className="text-[11px] text-slate-500 mt-0.5">{e.subjectName} &middot; {e.durationMinutes} menit &middot; {e.totalQuestions} soal</p>
                    <p className="text-[11px] text-slate-500 font-mono mt-0.5">{formatDate(e.startTime)}</p>
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

function MetricCard({ icon, label, value, sub, href, cta }: { icon: React.ReactNode; label: string; value: string; sub: string; href: string; cta: string; }) {
  return (
    <div className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-xs flex flex-col justify-between">
      <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
        <span className="text-xs font-bold text-slate-700 uppercase tracking-wider truncate">{label}</span>
        <div className="w-7 h-7 rounded-lg bg-blue-50 text-blue-900 border border-blue-200 flex items-center justify-center shrink-0">{icon}</div>
      </div>
      <div className="my-1.5">
        <span className="text-3xl font-extrabold tracking-tight">{value}</span>
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
