'use client';

import React from 'react';
import Link from 'next/link';
import { CalendarCheck, Award, CheckSquare, MonitorPlay, Clock, ArrowRight, BookOpen, ChevronRight } from 'lucide-react';
import { useAuth } from '@/lib/auth-context';
import { useStudentDashboard, useStudentTasks, useSchoolAnnouncements } from '@/lib/use-student-data';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { Labi } from './mascot/labi';
import { StudentLoading, StudentError } from './mascot/student-states';
import { StatTile } from './mascot/student-ui';
import { TahfidzWidget } from './student-programs/tahfidz-widget';
import { IcpWidget } from './student-programs/icp-widget';
import { InklusiWidget } from './student-programs/inklusi-widget';
import { RegulerWidget } from './student-programs/reguler-widget';
import { SafetyCounselingCard } from './safety-counseling-card';

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

  if (dash.isLoading) return <StudentLoading label="Menyiapkan portal kamu..." />;
  if (dash.isError) return <StudentError message="Gagal memuat dashboard siswa. Coba lagi ya." onRetry={() => dash.refetch()} />;

  // Friendly contextual greeting built from existing counts (no new logic/data).
  const greeting = activeTasks.length > 0 || upcomingExams.length > 0
    ? `Hari ini ada ${activeTasks.length} tugas & ${upcomingExams.length} ujian. Semangat ya! 💪`
    : 'Tidak ada tugas mendesak hari ini. Tetap semangat belajar! 🌟';

  return (
    <div className="flex flex-col gap-3.5 text-slate-900">
      {/* Playful hero greeting with Labi */}
      <div className="siswa-hero siswa-card relative flex items-center gap-4 overflow-hidden p-5 text-white shadow-md">
        <Labi expression="wave" size={76} className="shrink-0" />
        <div className="min-w-0 flex-1">
          <h1 className="truncate text-xl font-extrabold tracking-tight">Halo, {name.split(' ')[0]}! 🌞</h1>
          <p className="mt-1 text-sm font-medium text-white/90">{className} &middot; NIS {data?.nis || '-'}</p>
          <div className="mt-2 inline-block rounded-2xl border border-white/30 bg-white/20 px-3 py-1.5 text-sm font-semibold backdrop-blur">
            {greeting}
          </div>
        </div>
        {data?.programBadge && (
          <div className="ml-auto hidden shrink-0 self-start sm:block">
            <span className="rounded-full border border-white/40 bg-white/20 px-3 py-1 text-xs font-bold text-white">
              {data.programBadge}
            </span>
          </div>
        )}
      </div>

      <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <Link href="/siswa/jadwal"><StatTile icon={CalendarCheck} label="Kehadiran" value={(data?.attendanceRate ?? 0) + '%'} tone="mint" /></Link>
        <Link href="/siswa/tugas"><StatTile icon={CheckSquare} label="Tugas aktif" value={activeTasks.length} tone="coral" /></Link>
        <Link href="/siswa/cbt"><StatTile icon={MonitorPlay} label="Ujian mendatang" value={upcomingExams.length} tone="sun" /></Link>
        <Link href="/siswa/poin"><StatTile icon={Award} label="Poin prestasi" value={data?.totalAchievements ?? 0} tone="grape" /></Link>
      </div>

      {/* Widget Khusus Program Belajar Siswa */}
      {data?.programType === 'tahfidz' && <TahfidzWidget profile={data.programProfile} />}
      {data?.programType === 'icp' && <IcpWidget profile={data.programProfile} />}
      {data?.programType === 'inklusi' && <InklusiWidget profile={data.programProfile} />}
      {(!data?.programType || data?.programType === 'reguler') && <RegulerWidget profile={data?.programProfile} />}

      {/* RUANG AMAN & LAPOR PERUNDUNGAN (FE-REPORT-02) */}
      <SafetyCounselingCard role="student" />

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
