'use client';

import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { JournalItem, DAY_OF_WEEK_LABELS } from '@/types/academic';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  BookMarked,
  Calendar,
  Clock,
  Layers,
  BookOpen,
  User,
  Building2,
  FileText,
  Users,
  CheckCircle,
  X,
  Edit2,
  Info,
} from 'lucide-react';

interface JournalDetailDialogProps {
  journalId: string | null;
  isOpen: boolean;
  onClose: () => void;
  onEdit?: (journal: JournalItem) => void;
  canEdit?: boolean;
}

export function JournalDetailDialog({
  journalId,
  isOpen,
  onClose,
  onEdit,
  canEdit = false,
}: JournalDetailDialogProps) {
  const { data: journal, isLoading, error } = useQuery<JournalItem>({
    queryKey: ['academic-journal-detail', journalId],
    queryFn: () => academicApi.getJournal(journalId!),
    enabled: isOpen && !!journalId,
  });

  if (!isOpen || !journalId) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-2xl rounded-xl bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
              <BookMarked className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                Detail Jurnal Mengajar KBM
              </h2>
              <p className="text-xs text-gray-500">
                Dokumentasi resmi sesi pertemuan pembelajaran dan kehadiran siswa
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Content Body */}
        <div className="px-6 py-5 max-h-[80vh] overflow-y-auto space-y-4">
          {isLoading ? (
            <div className="p-8 text-center text-xs text-gray-500">
              Memuat detail data jurnal mengajar...
            </div>
          ) : error || !journal ? (
            <div className="rounded-lg bg-red-50 p-4 text-xs text-red-700">
              Gagal memuat detail jurnal mengajar.
            </div>
          ) : (
            <>
              {/* Card Ringkasan Sesi KBM */}
              <div className="rounded-xl border border-gray-200 bg-slate-50/70 p-4 space-y-2.5">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-2.5">
                  <div className="flex items-center gap-2 font-semibold text-gray-900 text-sm">
                    <BookOpen className="h-4 w-4 text-emerald-600" />
                    <span>{journal.schedule?.subject?.name || 'Mata Pelajaran'}</span>
                    <span className="font-mono text-xs text-gray-500">
                      [{journal.schedule?.subject?.code || '-'}]
                    </span>
                  </div>
                  <span className="inline-flex items-center gap-1 rounded bg-white px-2.5 py-1 text-xs font-semibold text-emerald-800 border border-gray-200 shadow-xs">
                    <Layers className="h-3.5 w-3.5 text-emerald-600" />
                    Kelas {journal.schedule?.class?.name || '-'}
                  </span>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-gray-600">
                  <div className="flex items-center gap-1.5">
                    <Calendar className="h-3.5 w-3.5 text-gray-400 shrink-0" />
                    <span>
                      {new Date(journal.session_date).toLocaleDateString('id-ID', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                      })}
                    </span>
                  </div>

                  <div className="flex items-center gap-1.5">
                    <Clock className="h-3.5 w-3.5 text-gray-400 shrink-0" />
                    <span>
                      {journal.schedule?.timeSlot?.name || 'Jam'}{' '}
                      ({journal.schedule?.timeSlot?.start_time} - {journal.schedule?.timeSlot?.end_time})
                    </span>
                  </div>

                  <div className="flex items-center gap-1.5">
                    <User className="h-3.5 w-3.5 text-gray-400 shrink-0" />
                    <span className="truncate">
                      {journal.teacher?.full_name || 'Guru'}
                    </span>
                  </div>

                  {journal.schedule?.room && (
                    <div className="flex items-center gap-1.5">
                      <Building2 className="h-3.5 w-3.5 text-gray-400 shrink-0" />
                      <span>{journal.schedule.room.name}</span>
                    </div>
                  )}
                </div>
              </div>

              {/* Konten Jurnal KBM */}
              <div className="space-y-3 pt-1">
                {/* Materi / Topik */}
                <div className="rounded-lg border border-gray-100 bg-white p-3.5 shadow-xs">
                  <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">
                    Materi / Topik Pembelajaran
                  </span>
                  <p className="text-sm font-semibold text-gray-900">{journal.topic}</p>
                </div>

                {/* Metode Pembelajaran */}
                <div className="rounded-lg border border-gray-100 bg-white p-3.5 shadow-xs">
                  <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">
                    Metode Pembelajaran
                  </span>
                  <p className="text-xs text-gray-800">{journal.method}</p>
                </div>

                {/* Catatan KBM */}
                <div className="rounded-lg border border-gray-100 bg-white p-3.5 shadow-xs">
                  <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">
                    Catatan KBM & Evaluasi Sesi
                  </span>
                  {journal.notes ? (
                    <p className="text-xs text-gray-700 whitespace-pre-wrap leading-relaxed">
                      {journal.notes}
                    </p>
                  ) : (
                    <span className="text-xs text-gray-400 italic">Tidak ada catatan tambahan.</span>
                  )}
                </div>
              </div>

              {/* Rekap Presensi Siswa Terkait Sesi */}
              <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs space-y-2">
                <div className="flex items-center justify-between border-b border-gray-100 pb-2">
                  <span className="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                    <Users className="h-4 w-4 text-emerald-600" />
                    Kehadiran Siswa Sesi Ini ({journal.attendances?.length || journal.attendances_count || 0} Siswa)
                  </span>
                  {journal.attendances && journal.attendances.length > 0 && (
                    <span className="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200">
                      Presensi Telah Dicatat
                    </span>
                  )}
                </div>

                {journal.attendances && journal.attendances.length > 0 ? (
                  <div className="max-h-48 overflow-y-auto divide-y divide-gray-100 text-xs">
                    {journal.attendances.map((att) => (
                      <div key={att.id} className="py-2 flex items-center justify-between">
                        <div>
                          <div className="font-medium text-gray-900">
                            {att.student?.full_name || 'Siswa'}
                          </div>
                          <div className="text-[10px] text-gray-400 font-mono">
                            NIS: {att.student?.nis || '-'}
                          </div>
                        </div>
                        <span
                          className={`rounded px-2 py-0.5 text-[11px] font-semibold uppercase ${
                            att.status === 'hadir'
                              ? 'bg-emerald-100 text-emerald-800'
                              : att.status === 'izin'
                              ? 'bg-blue-100 text-blue-800'
                              : att.status === 'sakit'
                              ? 'bg-amber-100 text-amber-800'
                              : att.status === 'terlambat'
                              ? 'bg-purple-100 text-purple-800'
                              : 'bg-red-100 text-red-800'
                          }`}
                        >
                          {att.status}
                        </span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-xs text-gray-500 italic py-2">
                    Presensi siswa untuk jurnal sesi ini belum dicatat (dapat diisi via modul Presensi KBM).
                  </p>
                )}
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between border-t border-gray-100 px-6 py-4 bg-slate-50/50">
          <div className="text-[11px] text-gray-400">
            {journal?.created_at && (
              <span>Dibuat: {new Date(journal.created_at).toLocaleString('id-ID')}</span>
            )}
          </div>
          <div className="flex items-center gap-2">
            {canEdit && journal && onEdit && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  onClose();
                  onEdit(journal);
                }}
                className="text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50"
              >
                <Edit2 className="mr-1.5 h-3.5 w-3.5" />
                Ubah Jurnal
              </Button>
            )}
            <Button variant="outline" size="sm" onClick={onClose} className="text-xs">
              Tutup
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
