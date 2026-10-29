'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useStudentSchedule } from '@/features/portal';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import {
  Calendar,
  Clock,
  BookOpen,
  User,
  MapPin,
  ArrowLeft,
  CalendarDays,
  Sparkles,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const DAYS = [
  { id: 1, name: 'Senin' },
  { id: 2, name: 'Selasa' },
  { id: 3, name: 'Rabu' },
  { id: 4, name: 'Kamis' },
  { id: 5, name: 'Jumat' },
];

export default function StudentSchedulePage() {
  const { data: schedules = [], isLoading } = useStudentSchedule();
  const [selectedDay, setSelectedDay] = useState<number>(() => {
    const today = new Date().getDay();
    return today >= 1 && today <= 5 ? today : 1;
  });

  const safeSchedules = Array.isArray(schedules) ? schedules : [];
  const filteredSchedules = safeSchedules.filter((s) => s.dayOfWeek === selectedDay);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/siswa"
              className="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <CalendarDays className="h-6 w-6 text-brand-blue" />
            <span>Jadwal Pelajaran Mingguan</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Agenda Kegiatan Belajar Mengajar (KBM) Semester Gasal TA 2024/2025.
          </p>
        </div>

        <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-brand-blue rounded-xl text-xs font-semibold">
          <Sparkles className="h-4 w-4" />
          <span>Kurikulum Merdeka</span>
        </div>
      </div>

      {/* Day Selector Pills */}
      <div className="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
        {DAYS.map((d) => {
          const isSelected = selectedDay === d.id;
          const count = safeSchedules.filter((s) => s.dayOfWeek === d.id).length;
          return (
            <button
              key={d.id}
              onClick={() => setSelectedDay(d.id)}
              className={cn(
                'flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue whitespace-nowrap shadow-xs',
                isSelected
                  ? 'bg-brand-blue text-white shadow-md'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              )}
            >
              <span>{d.name}</span>
              <span
                className={cn(
                  'px-1.5 py-0.5 rounded-full text-[10px] font-black',
                  isSelected ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'
                )}
              >
                {count} sesi
              </span>
            </button>
          );
        })}
      </div>

      {/* Schedule Content */}
      {isLoading ? (
        <div className="min-h-[300px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat jadwal pelajaran..." size="lg" />
        </div>
      ) : filteredSchedules.length === 0 ? (
        <Card className="border-slate-200 py-12 text-center">
          <CardContent>
            <Calendar className="h-12 w-12 text-slate-300 mx-auto mb-3" />
            <h3 className="text-base font-bold text-slate-700">Tidak Ada Jadwal Belajar</h3>
            <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
              Hari ini tidak ada sesi tatap muka terjadwal atau merupakan hari libur kegiatan belajar.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredSchedules.map((item, idx) => (
            <Card
              key={item.id}
              className="border-slate-200 hover:border-brand-blue hover:shadow-md transition-all duration-200 overflow-hidden"
            >
              <div className="h-1.5 bg-brand-blue w-full" />
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between text-xs font-semibold text-slate-500 mb-1">
                  <span className="flex items-center gap-1.5">
                    <Clock className="h-3.5 w-3.5 text-brand-blue" />
                    <span>
                      {item.startTime} - {item.endTime}
                    </span>
                  </span>
                  <Badge variant="outline" className="text-[10px] font-bold">
                    Sesi #{idx + 1}
                  </Badge>
                </div>
                <CardTitle className="text-base font-black text-slate-900 flex items-center gap-2">
                  <BookOpen className="h-4 w-4 text-brand-blue shrink-0" />
                  <span>{item.subjectName}</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-xs text-slate-600 pt-0 border-t border-slate-100 mt-2">
                <div className="flex items-center gap-2 pt-3">
                  <User className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                  <span className="font-medium text-slate-700">{item.teacherName}</span>
                </div>
                <div className="flex items-center gap-2">
                  <MapPin className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                  <span className="text-slate-500">{item.roomName}</span>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
