'use client';

import React from 'react';
import {
  ScheduleItem,
  TimeSlotItem,
  DayOfWeek,
  DAY_OF_WEEK_LABELS,
} from '@/types/academic';
import { Button } from '@/components/ui/button';
import {
  Clock,
  Plus,
  Edit2,
  Trash2,
  BookOpen,
  User,
  Building2,
  Calendar,
} from 'lucide-react';

interface ScheduleTimetableGridProps {
  schedules: ScheduleItem[];
  timeSlots: TimeSlotItem[];
  selectedSemesterName?: string;
  viewTitle: string; // misal: "Kelas X-A" atau "Budi Santoso, M.Pd."
  onAddScheduleSlot: (dayOfWeek: DayOfWeek, timeSlotId: string) => void;
  onEditSchedule: (schedule: ScheduleItem) => void;
  onDeleteSchedule: (schedule: ScheduleItem) => void;
  canManage: boolean;
}

const ACTIVE_DAYS: DayOfWeek[] = [1, 2, 3, 4, 5, 6]; // Senin s.d. Sabtu

export function ScheduleTimetableGrid({
  schedules,
  timeSlots,
  selectedSemesterName,
  viewTitle,
  onAddScheduleSlot,
  onEditSchedule,
  onDeleteSchedule,
  canManage,
}: ScheduleTimetableGridProps) {
  // Sort time slots by start_time
  const sortedSlots = [...timeSlots].sort((a, b) => a.start_time.localeCompare(b.start_time));

  // Build a lookup map: `${day_of_week}_${time_slot_id}` -> ScheduleItem
  const scheduleMap = new Map<string, ScheduleItem>();
  schedules.forEach((sch) => {
    const key = `${sch.day_of_week}_${sch.time_slot_id}`;
    scheduleMap.set(key, sch);
  });

  return (
    <div className="space-y-4">
      {/* Printable Header - Visible when printed */}
      <div className="hidden print:block mb-6 border-b pb-4 text-center">
        <h1 className="text-xl font-bold text-gray-900">JADWAL PELAJARAN KBM</h1>
        <p className="text-sm font-semibold text-gray-700">{viewTitle}</p>
        <p className="text-xs text-gray-500">
          Semester: {selectedSemesterName || 'Aktif'} • Dicetak pada: {new Date().toLocaleDateString('id-ID')}
        </p>
      </div>

      {sortedSlots.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center">
          <Clock className="mx-auto h-10 w-10 text-gray-300" />
          <h3 className="mt-3 text-sm font-medium text-gray-900">Belum Ada Jam Pelajaran (Time Slots)</h3>
          <p className="mt-1 text-xs text-gray-500">
            Tambahkan slot jam pelajaran terlebih dahulu untuk menyusun matriks jadwal KBM.
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
          <table className="w-full border-collapse text-left text-xs">
            <thead>
              <tr className="border-b border-gray-200 bg-slate-50/80">
                <th className="w-28 px-3 py-3 font-semibold text-gray-700 border-r border-gray-200 text-center">
                  Jam / Waktu
                </th>
                {ACTIVE_DAYS.map((day) => (
                  <th
                    key={day}
                    className="min-w-[140px] px-3 py-3 font-semibold text-gray-800 text-center border-r border-gray-200 last:border-r-0"
                  >
                    <span className="font-bold">{DAY_OF_WEEK_LABELS[day]}</span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {sortedSlots.map((slot) => (
                <tr key={slot.id} className="hover:bg-slate-50/30 transition-colors">
                  {/* Time slot column */}
                  <td className="px-3 py-2.5 bg-slate-50/50 border-r border-gray-200 text-center align-top">
                    <div className="font-semibold text-gray-900 text-xs">{slot.name}</div>
                    <div className="text-[11px] font-mono text-gray-500 mt-0.5">
                      {slot.start_time} - {slot.end_time}
                    </div>
                    {slot.day_type !== 'reguler' && (
                      <span className="mt-1 inline-block rounded bg-amber-100 px-1 py-0.2 text-[9px] font-medium text-amber-800">
                        {slot.day_type}
                      </span>
                    )}
                  </td>

                  {/* Day columns */}
                  {ACTIVE_DAYS.map((day) => {
                    const key = `${day}_${slot.id}`;
                    const item = scheduleMap.get(key);

                    return (
                      <td
                        key={day}
                        className="px-2 py-2 border-r border-gray-200 last:border-r-0 align-top h-24"
                      >
                        {item ? (
                          <div className="group relative flex h-full flex-col justify-between rounded-lg border border-emerald-100 bg-emerald-50/40 p-2.5 transition hover:border-emerald-300 hover:bg-emerald-50 hover:shadow-xs">
                            <div>
                              {/* Subject */}
                              <div className="font-semibold text-emerald-950 text-xs flex items-center gap-1">
                                <BookOpen className="h-3 w-3 text-emerald-600 shrink-0" />
                                <span className="line-clamp-1">{item.subject?.name || 'Mata Pelajaran'}</span>
                              </div>

                              {/* Teacher or Class */}
                              <div className="mt-1 text-[11px] text-gray-600 flex items-center gap-1">
                                <User className="h-2.5 w-2.5 text-gray-400 shrink-0" />
                                <span className="line-clamp-1">
                                  {item.teacher?.full_name || 'Guru Pengajar'}
                                </span>
                              </div>

                              {/* Class name if not already in title */}
                              {item.class && (
                                <div className="mt-0.5 text-[10px] text-gray-500 font-mono">
                                  Kelas: {item.class.name}
                                </div>
                              )}

                              {/* Room */}
                              {item.room && (
                                <div className="mt-1">
                                  <span className="inline-flex items-center gap-0.5 rounded bg-white px-1.5 py-0.5 text-[10px] font-medium text-gray-600 border border-gray-200">
                                    <Building2 className="h-2.5 w-2.5 text-gray-500" />
                                    {item.room.name}
                                  </span>
                                </div>
                              )}
                            </div>

                            {/* Action Buttons (Hidden when printing) */}
                            {canManage && (
                              <div className="mt-2 flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity print:hidden">
                                <button
                                  type="button"
                                  onClick={() => onEditSchedule(item)}
                                  className="rounded p-1 text-gray-500 hover:bg-white hover:text-emerald-700 shadow-xs"
                                  title="Edit Jadwal"
                                >
                                  <Edit2 className="h-3 w-3" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => onDeleteSchedule(item)}
                                  className="rounded p-1 text-gray-500 hover:bg-white hover:text-red-600 shadow-xs"
                                  title="Hapus Jadwal"
                                >
                                  <Trash2 className="h-3 w-3" />
                                </button>
                              </div>
                            )}
                          </div>
                        ) : (
                          /* Empty slot */
                          canManage && (
                            <button
                              type="button"
                              onClick={() => onAddScheduleSlot(day, slot.id)}
                              className="group flex h-full w-full items-center justify-center rounded-lg border border-dashed border-gray-200 bg-white p-2 text-gray-300 transition hover:border-emerald-400 hover:bg-emerald-50/30 hover:text-emerald-600 print:hidden"
                              title={`Tambah jadwal ${DAY_OF_WEEK_LABELS[day]} - ${slot.name}`}
                            >
                              <Plus className="h-4 w-4 opacity-40 group-hover:opacity-100 transition" />
                            </button>
                          )
                        )}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
