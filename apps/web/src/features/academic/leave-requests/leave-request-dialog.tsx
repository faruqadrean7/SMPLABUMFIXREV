'use client';

import React, { useState, useEffect } from 'react';
import { LeaveType, LEAVE_TYPE_LABELS } from '@/types/academic';
import { SchoolClassItem, ClassStudentItem } from '@/types/master';
import { classesApi } from '@/features/master/classes-api';
import { academicApi } from '../academic-api';
import { Button } from '@/components/ui/button';
import {
  X,
  PlusCircle,
  Calendar,
  User,
  GraduationCap,
  Paperclip,
  AlertCircle,
  FileText,
  Loader2,
} from 'lucide-react';

interface LeaveRequestDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export function LeaveRequestDialog({
  isOpen,
  onClose,
  onSuccess,
}: LeaveRequestDialogProps) {
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [selectedClassId, setSelectedClassId] = useState<string>('');
  const [students, setStudents] = useState<ClassStudentItem[]>([]);
  const [loadingStudents, setLoadingStudents] = useState(false);

  const [studentId, setStudentId] = useState<string>('');
  const [leaveType, setLeaveType] = useState<LeaveType>('sakit');

  const todayStr = new Date().toISOString().slice(0, 10);
  const [startDate, setStartDate] = useState<string>(todayStr);
  const [endDate, setEndDate] = useState<string>(todayStr);
  const [reason, setReason] = useState<string>('');
  const [attachmentFile, setAttachmentFile] = useState<File | null>(null);

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Load class list on mount / open
  useEffect(() => {
    if (isOpen) {
      classesApi
        .list({ limit: 100 })
        .then((res) => {
          const list = res.data || [];
          setClasses(list);
          setSelectedClassId((prev) => prev || list[0]?.id || '');
        })
        .catch(() => {});
    }
  }, [isOpen]);

  // Load students when selectedClassId changes
  useEffect(() => {
    if (selectedClassId) {
      setLoadingStudents(true);
      classesApi
        .listStudents(selectedClassId)
        .then((items) => {
          setStudents(items || []);
          if (items && items.length > 0) {
            setStudentId(items[0].id);
          } else {
            setStudentId('');
          }
        })
        .catch(() => {
          setStudents([]);
        })
        .finally(() => {
          setLoadingStudents(false);
        });
    }
  }, [selectedClassId]);

  if (!isOpen) return null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) {
      setAttachmentFile(null);
      return;
    }

    // Validasi ukuran berkas (maks 5 MB)
    if (file.size > 5 * 1024 * 1024) {
      setError('Ukuran berkas lampiran maksimal 5 MB.');
      setAttachmentFile(null);
      e.target.value = '';
      return;
    }

    // Validasi tipe berkas
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
      setError('Format berkas lampiran harus berupa JPG, PNG, atau PDF.');
      setAttachmentFile(null);
      e.target.value = '';
      return;
    }

    setError(null);
    setAttachmentFile(file);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!studentId) {
      setError('Siswa wajib dipilih.');
      return;
    }

    if (!startDate || !endDate) {
      setError('Rentang tanggal izin wajib diisi.');
      return;
    }

    if (new Date(endDate) < new Date(startDate)) {
      setError('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
      return;
    }

    if (!reason.trim() || reason.trim().length < 3) {
      setError('Alasan permohonan izin minimal 3 karakter.');
      return;
    }

    setSubmitting(true);

    try {
      await academicApi.createLeaveRequest({
        student_id: studentId,
        leave_type: leaveType,
        start_date: startDate,
        end_date: endDate,
        reason: reason.trim(),
        attachment_file: attachmentFile || undefined,
      });

      onSuccess();
      onClose();
    } catch (err: any) {
      const message =
        err?.response?.data?.message ||
        err?.response?.data?.error?.message ||
        'Gagal mengajukan permohonan izin. Silakan periksa kembali formulir Anda.';
      setError(message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-150">
      <div
        className="w-full max-w-xl bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
        aria-labelledby="leave-request-dialog-title"
      >
        {/* Header Modal */}
        <div className="px-6 py-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-indigo-500/20 border border-indigo-400/30 rounded-lg">
              <PlusCircle className="w-5 h-5 text-indigo-300" />
            </div>
            <div>
              <h2 id="leave-request-dialog-title" className="text-lg font-bold">
                Ajukan Permohonan Izin / Sakit
              </h2>
              <p className="text-xs text-indigo-200">
                Pencatatan surat izin/sakit siswa untuk verifikasi wali kelas
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
            aria-label="Tutup modal"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-4">
          {error && (
            <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-sm flex items-start gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          {/* Rombel & Pemilihan Siswa */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label
                htmlFor="select-class"
                className="block text-xs font-semibold text-slate-700 mb-1"
              >
                Kelas / Rombel:
              </label>
              <select
                id="select-class"
                value={selectedClassId}
                onChange={(e) => setSelectedClassId(e.target.value)}
                className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 bg-white"
              >
                {classes.map((cls) => (
                  <option key={cls.id} value={cls.id}>
                    {cls.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="select-student"
                className="block text-xs font-semibold text-slate-700 mb-1"
              >
                Pilih Siswa:
              </label>
              {loadingStudents ? (
                <div className="flex items-center gap-2 h-10 px-3 border border-slate-200 rounded-lg text-xs text-slate-500">
                  <Loader2 className="w-3.5 h-3.5 animate-spin text-indigo-600" />
                  <span>Memuat siswa...</span>
                </div>
              ) : (
                <select
                  id="select-student"
                  value={studentId}
                  onChange={(e) => setStudentId(e.target.value)}
                  className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 bg-white"
                >
                  {students.length === 0 ? (
                    <option value="">Tidak ada siswa di rombel ini</option>
                  ) : (
                    students.map((st) => (
                      <option key={st.id} value={st.id}>
                        {st.full_name} ({st.nis || '-'})
                      </option>
                    ))
                  )}
                </select>
              )}
            </div>
          </div>

          {/* Jenis Izin */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Jenis Permohonan:
            </label>
            <div className="grid grid-cols-3 gap-2">
              {(['sakit', 'izin', 'dispen'] as LeaveType[]).map((type) => (
                <button
                  type="button"
                  key={type}
                  onClick={() => setLeaveType(type)}
                  className={`py-2 px-3 rounded-lg border text-xs font-semibold text-center transition-all ${
                    leaveType === type
                      ? 'bg-indigo-50 border-indigo-500 text-indigo-700 ring-2 ring-indigo-500/20'
                      : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  {LEAVE_TYPE_LABELS[type]}
                </button>
              ))}
            </div>
          </div>

          {/* Rentang Tanggal */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label
                htmlFor="start-date"
                className="block text-xs font-semibold text-slate-700 mb-1"
              >
                Mulai Tanggal:
              </label>
              <input
                id="start-date"
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                required
              />
            </div>

            <div>
              <label
                htmlFor="end-date"
                className="block text-xs font-semibold text-slate-700 mb-1"
              >
                Sampai Tanggal:
              </label>
              <input
                id="end-date"
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                required
              />
            </div>
          </div>

          {/* Alasan */}
          <div>
            <label
              htmlFor="reason"
              className="block text-xs font-semibold text-slate-700 mb-1"
            >
              Alasan Permohonan Izin:
            </label>
            <textarea
              id="reason"
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Jelaskan keterangan sakit, keperluan acara keluarga, atau kegiatan dispensasi lomba..."
              className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              required
            />
          </div>

          {/* Berkas Lampiran Surat Dokter / Izin */}
          <div>
            <label
              htmlFor="attachment"
              className="block text-xs font-semibold text-slate-700 mb-1"
            >
              Unggah Surat Dokter / Surat Izin (Opsional, Maks 5 MB):
            </label>
            <input
              id="attachment"
              type="file"
              accept=".jpg,.jpeg,.png,.pdf"
              onChange={handleFileChange}
              className="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer border border-slate-200 rounded-lg p-1"
            />
            {attachmentFile && (
              <p className="text-xs text-emerald-600 mt-1 flex items-center gap-1 font-medium">
                <Paperclip className="w-3.5 h-3.5" />
                Berkas terpilih: {attachmentFile.name} (
                {(attachmentFile.size / 1024).toFixed(1)} KB)
              </p>
            )}
          </div>

          {/* Footer Action */}
          <div className="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={submitting}
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={submitting || !studentId}
              className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
            >
              {submitting ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Mengirimkan...
                </>
              ) : (
                'Kirim Permohonan'
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
