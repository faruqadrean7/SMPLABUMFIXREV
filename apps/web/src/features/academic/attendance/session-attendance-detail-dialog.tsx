'use client';

import React, { useMemo } from 'react';
import {
  JournalItem,
  AttendanceStatus,
  ATTENDANCE_STATUS_LABELS,
  ATTENDANCE_STATUS_COLORS,
} from '@/types/academic';
import { Button } from '@/components/ui/button';
import {
  UserCheck,
  Calendar,
  Layers,
  BookOpen,
  User,
  Clock,
  Edit,
  X,
  FileText,
  Info,
} from 'lucide-react';

interface SessionAttendanceDetailDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onEdit?: () => void;
  journal: JournalItem | null;
  canEdit?: boolean;
}

export function SessionAttendanceDetailDialog({
  isOpen,
  onClose,
  onEdit,
  journal,
  canEdit = true,
}: SessionAttendanceDetailDialogProps) {
  const attendances = useMemo(() => {
    return journal?.attendances || [];
  }, [journal]);

  const stats = useMemo(() => {
    let hadir = 0;
    let izin = 0;
    let sakit = 0;
    let alpa = 0;
    let terlambat = 0;

    attendances.forEach((a) => {
      if (a.status === 'hadir') hadir++;
      else if (a.status === 'izin') izin++;
      else if (a.status === 'sakit') sakit++;
      else if (a.status === 'alpa') alpa++;
      else if (a.status === 'terlambat') terlambat++;
    });

    const total = attendances.length;
    const rate = total > 0 ? Math.round(((hadir + terlambat) / total) * 100) : 0;

    return { total, hadir, izin, sakit, alpa, terlambat, rate };
  }, [attendances]);

  if (!isOpen || !journal) return null;

  const className =
    journal.schedule?.class?.name || (journal.schedule as any)?.class_room?.name || 'Kelas';
  const subjectName = journal.schedule?.subject?.name || 'Mata Pelajaran';
  const teacherName =
    journal.teacher?.full_name || journal.schedule?.teacher?.full_name || 'Guru Pengajar';
  const timeSlotName = journal.schedule?.timeSlot
    ? `${journal.schedule.timeSlot.name} (${journal.schedule.timeSlot.start_time.slice(0, 5)} - ${journal.schedule.timeSlot.end_time.slice(0, 5)})`
    : 'Waktu Sesi';
  const sessionDateFormatted =
    typeof journal.session_date === 'string' ? journal.session_date.slice(0, 10) : '';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-150">
      <div
        className="w-full max-w-4xl bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
        aria-labelledby="attendance-detail-title"
      >
        {/* Header Modal */}
        <div className="px-6 py-4 bg-gradient-to-r from-slate-800 to-slate-900 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-white/10 rounded-lg">
              <UserCheck className="w-5 h-5 text-indigo-300" />
            </div>
            <div>
              <h2 id="attendance-detail-title" className="text-lg font-bold">
                Detail Presensi Sesi KBM
              </h2>
              <p className="text-xs text-slate-300">
                Rekapitulasi kehadiran siswa pada pertemuan KBM
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
            title="Tutup dialog"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Informasi Meta Sesi KBM */}
        <div className="px-6 py-4 bg-slate-50 border-b border-slate-200 shrink-0">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div className="flex items-center gap-2.5 text-slate-700">
              <Layers className="w-4 h-4 text-slate-400 shrink-0" />
              <div>
                <span className="text-[10px] text-slate-400 block font-medium uppercase tracking-wider">
                  Kelas / Rombel
                </span>
                <span className="font-semibold text-slate-900">{className}</span>
              </div>
            </div>
            <div className="flex items-center gap-2.5 text-slate-700">
              <BookOpen className="w-4 h-4 text-slate-400 shrink-0" />
              <div>
                <span className="text-[10px] text-slate-400 block font-medium uppercase tracking-wider">
                  Mata Pelajaran
                </span>
                <span className="font-semibold text-slate-900">{subjectName}</span>
              </div>
            </div>
            <div className="flex items-center gap-2.5 text-slate-700">
              <Calendar className="w-4 h-4 text-slate-400 shrink-0" />
              <div>
                <span className="text-[10px] text-slate-400 block font-medium uppercase tracking-wider">
                  Tanggal & Waktu
                </span>
                <span className="font-semibold text-slate-900">
                  {sessionDateFormatted} • {timeSlotName}
                </span>
              </div>
            </div>
            <div className="flex items-center gap-2.5 text-slate-700">
              <User className="w-4 h-4 text-slate-400 shrink-0" />
              <div>
                <span className="text-[10px] text-slate-400 block font-medium uppercase tracking-wider">
                  Guru Pengajar
                </span>
                <span className="font-semibold text-slate-900 truncate max-w-[140px] block">
                  {teacherName}
                </span>
              </div>
            </div>
          </div>

          <div className="mt-3 pt-3 border-t border-slate-200/70 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
            <div>
              <span className="text-slate-400 font-medium">Topik / Materi: </span>
              <span className="font-semibold text-slate-800">{journal.topic}</span>
            </div>
            {journal.method && (
              <div>
                <span className="text-slate-400 font-medium">Metode KBM: </span>
                <span className="text-slate-700">{journal.method}</span>
              </div>
            )}
          </div>
        </div>

        {/* Ringkasan Kartu Statistik */}
        <div className="px-6 py-3.5 bg-white border-b border-slate-100 shrink-0">
          <div className="grid grid-cols-2 sm:grid-cols-6 gap-2.5 text-center">
            <div className="p-2 bg-slate-50 border border-slate-200 rounded-lg">
              <span className="text-[10px] font-semibold text-slate-400 block uppercase">
                Total Siswa
              </span>
              <span className="text-base font-bold text-slate-800">{stats.total}</span>
            </div>
            <div className="p-2 bg-emerald-50 border border-emerald-200 rounded-lg">
              <span className="text-[10px] font-semibold text-emerald-600 block uppercase">
                Hadir
              </span>
              <span className="text-base font-bold text-emerald-700">{stats.hadir}</span>
            </div>
            <div className="p-2 bg-blue-50 border border-blue-200 rounded-lg">
              <span className="text-[10px] font-semibold text-blue-600 block uppercase">Izin</span>
              <span className="text-base font-bold text-blue-700">{stats.izin}</span>
            </div>
            <div className="p-2 bg-amber-50 border border-amber-200 rounded-lg">
              <span className="text-[10px] font-semibold text-amber-600 block uppercase">
                Sakit
              </span>
              <span className="text-base font-bold text-amber-700">{stats.sakit}</span>
            </div>
            <div className="p-2 bg-rose-50 border border-rose-200 rounded-lg">
              <span className="text-[10px] font-semibold text-rose-600 block uppercase">Alpa</span>
              <span className="text-base font-bold text-rose-700">{stats.alpa}</span>
            </div>
            <div className="p-2 bg-indigo-50 border border-indigo-200 rounded-lg">
              <span className="text-[10px] font-semibold text-indigo-600 block uppercase">
                Kehadiran
              </span>
              <span className="text-base font-bold text-indigo-700">{stats.rate}%</span>
            </div>
          </div>
        </div>

        {/* Daftar Kehadiran Siswa */}
        <div className="flex-1 overflow-y-auto px-6 py-4">
          {attendances.length === 0 ? (
            <div className="py-16 text-center text-slate-400 text-sm">
              <Info className="w-8 h-8 text-slate-300 mx-auto mb-2" />
              Belum ada catatan presensi yang diinput untuk sesi ini.
            </div>
          ) : (
            <div className="border border-slate-200 rounded-lg overflow-hidden">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold sticky top-0">
                  <tr>
                    <th className="py-2.5 px-3 w-12 text-center">No</th>
                    <th className="py-2.5 px-3 w-28">NIS</th>
                    <th className="py-2.5 px-3 min-w-[180px]">Nama Siswa</th>
                    <th className="py-2.5 px-3 w-28 text-center">Status</th>
                    <th className="py-2.5 px-3 min-w-[180px]">Catatan / Keterangan</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {attendances.map((att, idx) => {
                    const statusKey = att.status as AttendanceStatus;
                    const colorConfig =
                      ATTENDANCE_STATUS_COLORS[statusKey] || ATTENDANCE_STATUS_COLORS.hadir;

                    return (
                      <tr key={att.id} className="hover:bg-slate-50/70 transition-colors">
                        <td className="py-2.5 px-3 text-center text-slate-400 font-medium">
                          {idx + 1}
                        </td>
                        <td className="py-2.5 px-3 font-mono text-slate-600">
                          {att.student?.nis || '-'}
                        </td>
                        <td className="py-2.5 px-3 font-semibold text-slate-900">
                          {att.student?.full_name || 'Siswa'}
                        </td>
                        <td className="py-2.5 px-3 text-center">
                          <span
                            className={`inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${colorConfig.bg}`}
                          >
                            {ATTENDANCE_STATUS_LABELS[statusKey] || att.status}
                          </span>
                        </td>
                        <td className="py-2.5 px-3 text-slate-600">
                          {att.note || <span className="text-slate-300 italic">-</span>}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Footer Modal */}
        <div className="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
          <div className="text-xs text-slate-500">
            {attendances.length > 0 && (
              <span>
                Total <strong>{attendances.length}</strong> catatan presensi tersimpan
              </span>
            )}
          </div>
          <div className="flex items-center gap-2">
            <Button type="button" variant="ghost" size="sm" onClick={onClose}>
              Tutup
            </Button>
            {canEdit && onEdit && (
              <Button
                type="button"
                size="sm"
                onClick={() => {
                  onClose();
                  onEdit();
                }}
                className="bg-indigo-600 hover:bg-indigo-700 text-white"
              >
                <Edit className="w-3.5 h-3.5 mr-1.5" />
                Ubah Presensi
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
