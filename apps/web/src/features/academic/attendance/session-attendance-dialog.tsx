'use client';

import React, { useEffect, useState, useMemo } from 'react';
import {
  JournalItem,
  AttendanceStatus,
  ATTENDANCE_STATUS_LABELS,
  ATTENDANCE_STATUS_COLORS,
} from '@/types/academic';
import { ClassStudentItem } from '@/types/master';
import { academicApi } from '../academic-api';
import { classesApi } from '@/features/master/classes-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  UserCheck,
  Calendar,
  Layers,
  BookOpen,
  User,
  Clock,
  AlertTriangle,
  CheckCircle2,
  X,
  RotateCcw,
  CheckCheck,
  Info,
} from 'lucide-react';

interface SessionAttendanceDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  journal: JournalItem | null;
  isAdmin?: boolean;
}

interface StudentAttendanceRow {
  student_id: string;
  nis: string;
  full_name: string;
  gender?: string;
  status: AttendanceStatus;
  note: string;
}

export function SessionAttendanceDialog({
  isOpen,
  onClose,
  onSuccess,
  journal,
  isAdmin = false,
}: SessionAttendanceDialogProps) {
  const [rows, setRows] = useState<StudentAttendanceRow[]>([]);
  const [isLoadingStudents, setIsLoadingStudents] = useState<boolean>(false);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Cek masa kedaluwarsa pengubahan presensi (maksimal 3 hari untuk guru non-admin)
  const isExpired = useMemo(() => {
    if (!journal || isAdmin) return false;
    const sessionDateStr = typeof journal.session_date === 'string'
      ? journal.session_date.slice(0, 10)
      : '';
    if (!sessionDateStr) return false;
    const sessionDate = new Date(sessionDateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    sessionDate.setHours(0, 0, 0, 0);
    const diffTime = today.getTime() - sessionDate.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    return diffDays > 3;
  }, [journal, isAdmin]);

  // Muat daftar siswa rombel & sinkronisasi data presensi yang sudah ada
  useEffect(() => {
    if (!isOpen || !journal) {
      setRows([]);
      setErrorMessage(null);
      return;
    }

    const classId = journal.schedule?.class_id || (journal.schedule as any)?.class?.id;
    if (!classId) return;

    let isMounted = true;
    setIsLoadingStudents(true);
    setErrorMessage(null);

    classesApi
      .listStudents(classId)
      .then((students: ClassStudentItem[]) => {
        if (!isMounted) return;

        // Peta presensi yang tersimpan sebelumnya di jurnal
        const existingAttendanceMap = new Map<string, { status: AttendanceStatus; note: string }>();
        if (journal.attendances && Array.isArray(journal.attendances)) {
          journal.attendances.forEach((att) => {
            existingAttendanceMap.set(att.student_id, {
              status: att.status as AttendanceStatus,
              note: att.note || '',
            });
          });
        }

        const initialRows: StudentAttendanceRow[] = students.map((s) => {
          const existing = existingAttendanceMap.get(s.id);
          return {
            student_id: s.id,
            nis: s.nis,
            full_name: s.full_name,
            gender: s.gender,
            status: existing ? existing.status : 'hadir',
            note: existing ? existing.note : '',
          };
        });

        setRows(initialRows);
      })
      .catch((err) => {
        if (!isMounted) return;
        setErrorMessage(
          err instanceof ApiClientError
            ? err.message
            : 'Gagal mengambil daftar siswa rombel kelas ini.'
        );
      })
      .finally(() => {
        if (isMounted) setIsLoadingStudents(false);
      });

    return () => {
      isMounted = false;
    };
  }, [isOpen, journal]);

  // Statistik rekap realtime di form
  const stats = useMemo(() => {
    let hadir = 0;
    let izin = 0;
    let sakit = 0;
    let alpa = 0;
    let terlambat = 0;

    rows.forEach((r) => {
      if (r.status === 'hadir') hadir++;
      else if (r.status === 'izin') izin++;
      else if (r.status === 'sakit') sakit++;
      else if (r.status === 'alpa') alpa++;
      else if (r.status === 'terlambat') terlambat++;
    });

    const total = rows.length;
    const rate = total > 0 ? Math.round(((hadir + terlambat) / total) * 100) : 100;

    return { total, hadir, izin, sakit, alpa, terlambat, rate };
  }, [rows]);

  // Tandai semua hadir
  const handleMarkAllPresent = () => {
    setRows((prev) => prev.map((r) => ({ ...r, status: 'hadir' })));
  };

  // Ubah status satu siswa
  const handleStatusChange = (studentId: string, status: AttendanceStatus) => {
    setRows((prev) =>
      prev.map((r) => (r.student_id === studentId ? { ...r, status } : r))
    );
  };

  // Ubah catatan satu siswa
  const handleNoteChange = (studentId: string, note: string) => {
    setRows((prev) =>
      prev.map((r) => (r.student_id === studentId ? { ...r, note } : r))
    );
  };

  // Simpan presensi massal
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!journal) return;

    if (isExpired && !isAdmin) {
      setErrorMessage('Batas waktu pengisian/pengubahan presensi telah berakhir (3 hari).');
      return;
    }

    if (rows.length === 0) {
      setErrorMessage('Tidak ada data siswa untuk disimpan.');
      return;
    }

    setIsSubmitting(true);
    setErrorMessage(null);

    try {
      await academicApi.recordSessionAttendance({
        journal_id: journal.id,
        attendances: rows.map((r) => ({
          student_id: r.student_id,
          status: r.status,
          note: r.note.trim() ? r.note.trim() : null,
        })),
      });

      onSuccess();
      onClose();
    } catch (err: any) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message || 'Gagal menyimpan presensi siswa.');
      } else {
        setErrorMessage('Terjadi kesalahan jaringan saat menyimpan presensi.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen || !journal) return null;

  const className =
    journal.schedule?.class?.name || (journal.schedule as any)?.class_room?.name || 'Kelas';
  const subjectName = journal.schedule?.subject?.name || 'Mata Pelajaran';
  const teacherName =
    journal.teacher?.full_name || journal.schedule?.teacher?.full_name || 'Guru Pengajar';
  const timeSlotName = journal.schedule?.timeSlot
    ? `${journal.schedule.timeSlot.name} (${journal.schedule.timeSlot.start_time.slice(0, 5)} - ${journal.schedule.timeSlot.end_time.slice(0, 5)})`
    : 'Waktu Sesi';
  const sessionDateFormatted = typeof journal.session_date === 'string'
    ? journal.session_date.slice(0, 10)
    : '';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-150">
      <div
        className="w-full max-w-4xl bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh] animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
        aria-labelledby="attendance-dialog-title"
      >
        {/* Header Modal */}
        <div className="px-6 py-4 bg-gradient-to-r from-indigo-700 via-indigo-800 to-indigo-900 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-white/10 rounded-lg">
              <UserCheck className="w-5 h-5 text-indigo-100" />
            </div>
            <div>
              <h2 id="attendance-dialog-title" className="text-lg font-bold">
                Presensi Sesi KBM
              </h2>
              <p className="text-xs text-indigo-200">
                Catat kehadiran siswa pada sesi tatap muka kelas
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
        <div className="px-6 py-3.5 bg-slate-50 border-b border-slate-200 shrink-0">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div className="flex items-center gap-2 text-slate-700">
              <Layers className="w-4 h-4 text-slate-400 shrink-0" />
              <div>
                <span className="text-[10px] text-slate-400 block font-medium uppercase tracking-wider">
                  Kelas / Rombel
                </span>
                <span className="font-semibold text-slate-900">{className}</span>
              </div>
            </div>
            <div className="flex items-center gap-2 text-slate-700">
              <BookOpen className="w-4 h-4 text-slate-400 shrink-0" />
              <div>
                <span className="text-[10px] text-slate-400 block font-medium uppercase tracking-wider">
                  Mata Pelajaran
                </span>
                <span className="font-semibold text-slate-900">{subjectName}</span>
              </div>
            </div>
            <div className="flex items-center gap-2 text-slate-700">
              <Calendar className="w-4 h-4 text-slate-400 shrink-0" />
              <div>
                <span className="text-[10px] text-slate-400 block font-medium uppercase tracking-wider">
                  Tanggal & Jam
                </span>
                <span className="font-semibold text-slate-900">
                  {sessionDateFormatted} • {timeSlotName}
                </span>
              </div>
            </div>
            <div className="flex items-center gap-2 text-slate-700">
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

          {journal.topic && (
            <div className="mt-2.5 pt-2 border-t border-slate-200/60 flex items-center gap-2 text-xs text-slate-600">
              <span className="font-medium text-slate-500 shrink-0">Materi KBM:</span>
              <span className="font-medium text-slate-800 truncate">{journal.topic}</span>
            </div>
          )}
        </div>

        {/* Peringatan Batas Waktu */}
        {isExpired && (
          <div className="px-6 py-2.5 bg-amber-50 border-b border-amber-200 text-amber-800 text-xs flex items-center gap-2 shrink-0">
            <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
            <span>
              <strong>Perhatian:</strong> Batas waktu 3 hari kalender untuk pengisian/pengeditan
              presensi sesi ini telah berakhir. Formulir dikunci dalam mode lihat (read-only).
            </span>
          </div>
        )}

        {/* Error Alert */}
        {errorMessage && (
          <div className="mx-6 mt-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-xs flex items-center gap-2 shrink-0">
            <AlertTriangle className="w-4 h-4 text-rose-500 shrink-0" />
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Bar Rekap Realtime & Aksi Cepat */}
        <div className="px-6 py-3 bg-white border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 shrink-0">
          <div className="flex items-center gap-2 text-xs">
            <span className="text-slate-500 font-medium">Rekap Sesi:</span>
            <span className="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-medium">
              Total {stats.total}
            </span>
            <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-medium">
              Hadir {stats.hadir}
            </span>
            <span className="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-medium">
              Izin {stats.izin}
            </span>
            <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium">
              Sakit {stats.sakit}
            </span>
            <span className="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 font-medium">
              Alpa {stats.alpa}
            </span>
            {stats.terlambat > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 font-medium">
                Terlambat {stats.terlambat}
              </span>
            )}
            <span className="ml-2 font-semibold text-slate-700">
              Tingkat Kehadiran: {stats.rate}%
            </span>
          </div>

          {!isExpired && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleMarkAllPresent}
              disabled={isLoadingStudents || isSubmitting || rows.length === 0}
              className="text-xs h-8 text-emerald-700 border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800"
            >
              <CheckCheck className="w-3.5 h-3.5 mr-1 text-emerald-600" />
              Tandai Semua Hadir
            </Button>
          )}
        </div>

        {/* Tabel Daftar Siswa Presensi */}
        <div className="flex-1 overflow-y-auto px-6 py-4">
          {isLoadingStudents ? (
            <div className="py-16 text-center text-slate-500 text-sm">
              <div className="w-8 h-8 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
              Memuat data siswa rombel {className}...
            </div>
          ) : rows.length === 0 ? (
            <div className="py-16 text-center text-slate-400 text-sm">
              <Info className="w-8 h-8 text-slate-300 mx-auto mb-2" />
              Tidak ada siswa aktif yang terdaftar di kelas ini.
            </div>
          ) : (
            <form id="session-attendance-form" onSubmit={handleSubmit}>
              <div className="border border-slate-200 rounded-lg overflow-hidden">
                <table className="w-full text-left text-xs border-collapse">
                  <thead className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold sticky top-0">
                    <tr>
                      <th className="py-2.5 px-3 w-12 text-center">No</th>
                      <th className="py-2.5 px-3 w-28">NIS</th>
                      <th className="py-2.5 px-3 min-w-[180px]">Nama Siswa</th>
                      <th className="py-2.5 px-3 w-[260px] text-center">Status Kehadiran</th>
                      <th className="py-2.5 px-3 min-w-[180px]">Catatan / Keterangan</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {rows.map((row, idx) => (
                      <tr
                        key={row.student_id}
                        className="hover:bg-slate-50/70 transition-colors"
                      >
                        <td className="py-2.5 px-3 text-center text-slate-400 font-medium">
                          {idx + 1}
                        </td>
                        <td className="py-2.5 px-3 font-mono text-slate-600">{row.nis}</td>
                        <td className="py-2.5 px-3">
                          <span className="font-semibold text-slate-900 block">
                            {row.full_name}
                          </span>
                          {row.gender && (
                            <span className="text-[10px] text-slate-400">
                              {row.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                            </span>
                          )}
                        </td>
                        <td className="py-2.5 px-3">
                          <div className="flex items-center justify-center gap-1">
                            {(['hadir', 'sakit', 'izin', 'alpa', 'terlambat'] as AttendanceStatus[]).map(
                              (statusKey) => {
                                const isSelected = row.status === statusKey;
                                const colorConfig = ATTENDANCE_STATUS_COLORS[statusKey];

                                return (
                                  <button
                                    key={statusKey}
                                    type="button"
                                    disabled={isExpired}
                                    onClick={() => handleStatusChange(row.student_id, statusKey)}
                                    className={`px-2 py-1 rounded text-[11px] font-semibold border transition-all ${
                                      isSelected
                                        ? `${colorConfig.bg} shadow-sm font-bold ring-1 ring-current`
                                        : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                                    } ${isExpired ? 'opacity-70 cursor-not-allowed' : ''}`}
                                    title={ATTENDANCE_STATUS_LABELS[statusKey]}
                                  >
                                    {ATTENDANCE_STATUS_LABELS[statusKey]}
                                  </button>
                                );
                              }
                            )}
                          </div>
                        </td>
                        <td className="py-2.5 px-3">
                          <Input
                            type="text"
                            placeholder="Catatan..."
                            value={row.note}
                            disabled={isExpired}
                            onChange={(e) => handleNoteChange(row.student_id, e.target.value)}
                            className="h-8 text-xs bg-slate-50/50"
                            maxLength={255}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </form>
          )}
        </div>

        {/* Footer Modal */}
        <div className="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
          <div className="text-xs text-slate-500">
            {rows.length > 0 && (
              <span>
                Menampilkan <strong>{rows.length}</strong> siswa kelas {className}
              </span>
            )}
          </div>
          <div className="flex items-center gap-2">
            <Button type="button" variant="ghost" size="sm" onClick={onClose} disabled={isSubmitting}>
              {isExpired ? 'Tutup' : 'Batal'}
            </Button>
            {!isExpired && (
              <Button
                type="submit"
                form="session-attendance-form"
                size="sm"
                disabled={isSubmitting || isLoadingStudents || rows.length === 0}
                className="bg-indigo-600 hover:bg-indigo-700 text-white min-w-[120px]"
              >
                {isSubmitting ? (
                  <span className="flex items-center gap-2">
                    <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    Menyimpan...
                  </span>
                ) : (
                  <span className="flex items-center gap-1.5">
                    <CheckCircle2 className="w-4 h-4" />
                    Simpan Presensi
                  </span>
                )}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
