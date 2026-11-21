'use client';

import React, { useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  CharacterNoteItem,
  CHARACTER_ASPECTS,
  CHARACTER_RATING_LABELS,
  CHARACTER_RATING_COLORS,
  CharacterRating,
  GradePeriodItem,
} from '@/types/academic';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import {
  Award,
  BookOpen,
  CheckCircle2,
  Lock,
  X,
  AlertTriangle,
  Loader2,
  Sparkles,
  User,
} from 'lucide-react';

export const characterNoteSchema = z.object({
  student_id: z.string().min(1, { message: 'Siswa wajib dipilih' }),
  period_id: z.string().min(1, { message: 'Periode penilaian wajib dipilih' }),
  scale_data: z.record(z.string()).refine((data) => Object.keys(data).length >= 1, {
    message: 'Minimal satu aspek karakter harus dinilai',
  }),
  homeroom_note: z
    .string()
    .max(2000, { message: 'Catatan wali kelas maksimal 2000 karakter' })
    .optional()
    .nullable(),
});

export type CharacterNoteFormValues = z.infer<typeof characterNoteSchema>;

interface CharacterNoteDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  student: {
    id: string;
    nisn: string;
    nis: string;
    name?: string;
    full_name?: string;
    gender: string;
  } | null;
  period: GradePeriodItem | null;
  existingNote?: CharacterNoteItem | null;
  isLocked?: boolean;
}

const TEMPLATE_SUGGESTIONS = [
  'Ananda menunjukkan kedisiplinan dan adab islami yang sangat baik, serta aktif bekerja sama dalam kegiatan kelompok kelas.',
  'Perkembangan kemandirian dan nalar kritis ananda sangat membanggakan. Terus pertahankan motivasi belajar dan empati terhadap teman.',
  'Ananda memiliki potensi kepemimpinan yang baik. Diharapkan dapat lebih meningkatkan fokus dan ketepatan waktu pengumpulan tugas.',
  'Sikap santun dan kejujuran ananda sangat patut dicontoh. Perlu sedikit dorongan agar lebih percaya diri mengemukakan pendapat di kelas.',
];

export function CharacterNoteDialog({
  isOpen,
  onClose,
  onSuccess,
  student,
  period,
  existingNote,
  isLocked = false,
}: CharacterNoteDialogProps) {
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const {
    control,
    handleSubmit,
    reset,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<CharacterNoteFormValues>({
    resolver: zodResolver(characterNoteSchema),
    defaultValues: {
      student_id: '',
      period_id: '',
      scale_data: {},
      homeroom_note: '',
    },
  });

  const currentScaleData = watch('scale_data') || {};
  const currentNote = watch('homeroom_note') || '';

  useEffect(() => {
    if (student && period) {
      const initialScale: Record<string, string> = existingNote?.scale_data
        ? { ...existingNote.scale_data }
        : {
            beriman_bertakwa: 'baik',
            berkebinekaan_global: 'baik',
            gotong_royong: 'baik',
            mandiri: 'baik',
            bernalar_kritis: 'baik',
            kreatif: 'baik',
            kedisiplinan: 'baik',
            kejujuran: 'baik',
          };

      reset({
        student_id: student.id,
        period_id: period.id,
        scale_data: initialScale,
        homeroom_note: existingNote?.homeroom_note || '',
      });
      setErrorMessage(null);
    }
  }, [student, period, existingNote, reset, isOpen]);

  if (!isOpen || !student || !period) return null;

  const onSubmit = async (values: CharacterNoteFormValues) => {
    if (isLocked) return;
    setErrorMessage(null);

    try {
      await academicApi.storeCharacterNote({
        student_id: values.student_id,
        period_id: values.period_id,
        scale_data: values.scale_data,
        homeroom_note: values.homeroom_note || null,
      });

      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat menyimpan catatan karakter siswa.');
      }
    }
  };

  const handleApplyTemplate = (tmpl: string) => {
    if (isLocked) return;
    const prev = currentNote.trim();
    if (prev.length > 0) {
      setValue('homeroom_note', `${prev}\n${tmpl}`, { shouldDirty: true });
    } else {
      setValue('homeroom_note', tmpl, { shouldDirty: true });
    }
  };

  const studentDisplayName = student.full_name || student.name || 'Siswa';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-3xl rounded-xl bg-white shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[92vh] flex flex-col">
        {/* Modal Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-slate-50/70 shrink-0">
          <div className="flex items-center gap-3">
            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
              <Award className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900 flex items-center gap-2">
                <span>Catatan Karakter: {studentDisplayName}</span>
                <span className="text-xs font-normal text-gray-500 font-mono">
                  ({student.nisn || student.nis})
                </span>
              </h2>
              <p className="text-xs text-gray-500">
                {period.month_name || `Bulan ke-${period.month}`} —{' '}
                {period.semester?.name || 'Semester Aktif'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            type="button"
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Scrollable Form Body */}
        <form onSubmit={handleSubmit(onSubmit)} className="overflow-y-auto p-6 space-y-6 flex-1">
          {isLocked && (
            <div className="flex items-start gap-2.5 rounded-lg bg-amber-50 p-3.5 text-xs text-amber-900 border border-amber-200">
              <Lock className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold">Periode Penilaian Dikunci</p>
                <p className="text-amber-800 text-[11px] mt-0.5">
                  Catatan karakter pada bulan ini tidak dapat diubah karena periode penilaian telah
                  resmi dikunci oleh administrator.
                </p>
              </div>
            </div>
          )}

          {errorMessage && (
            <div className="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-700 border border-red-200">
              <AlertTriangle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          {/* Section 1: Dimensi Profil Pelajar & Pembiasaan */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <div>
                <h3 className="text-sm font-semibold text-gray-900">
                  1. Skala Karakter & Pembiasaan Profil Pelajar
                </h3>
                <p className="text-xs text-gray-500">
                  Pilih tingkat capaian pembiasaan karakter untuk 8 dimensi terstandar
                </p>
              </div>
              <div className="flex items-center gap-2 text-[11px] text-gray-500">
                <span className="flex items-center gap-1">
                  <span className="h-2 w-2 rounded-full bg-emerald-500" /> SB = Sangat Baik
                </span>
                <span className="flex items-center gap-1">
                  <span className="h-2 w-2 rounded-full bg-blue-500" /> B = Baik
                </span>
                <span className="flex items-center gap-1">
                  <span className="h-2 w-2 rounded-full bg-amber-500" /> C = Cukup
                </span>
                <span className="flex items-center gap-1">
                  <span className="h-2 w-2 rounded-full bg-rose-500" /> PB = Perlu Bimbingan
                </span>
              </div>
            </div>

            <div className="space-y-3">
              {CHARACTER_ASPECTS.map((aspect) => {
                const currentRating = (currentScaleData[aspect.key] as CharacterRating) || 'baik';

                return (
                  <div
                    key={aspect.key}
                    className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 rounded-xl border border-gray-200 bg-white p-3 hover:border-emerald-200 transition"
                  >
                    <div className="sm:max-w-md">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-xs text-gray-900">{aspect.name}</span>
                        <span
                          className={`rounded px-1.5 py-0.2 text-[9px] uppercase font-bold tracking-wider ${
                            aspect.category === 'spiritual'
                              ? 'bg-purple-50 text-purple-700'
                              : aspect.category === 'sosial'
                              ? 'bg-blue-50 text-blue-700'
                              : 'bg-emerald-50 text-emerald-700'
                          }`}
                        >
                          {aspect.category}
                        </span>
                      </div>
                      <p className="text-[11px] text-gray-500 mt-0.5">{aspect.description}</p>
                    </div>

                    <div className="flex items-center gap-1.5 shrink-0">
                      {(
                        ['sangat_baik', 'baik', 'cukup', 'perlu_bimbingan'] as CharacterRating[]
                      ).map((rKey) => {
                        const isSelected = currentRating === rKey;
                        const ratingColor = CHARACTER_RATING_COLORS[rKey];

                        return (
                          <button
                            key={rKey}
                            type="button"
                            disabled={isLocked}
                            onClick={() => {
                              setValue(`scale_data.${aspect.key}`, rKey, {
                                shouldDirty: true,
                              });
                            }}
                            className={`px-2.5 py-1 text-xs font-semibold rounded-lg border transition ${
                              isSelected
                                ? `${ratingColor.badge} font-bold shadow-xs ring-1 ring-emerald-500/30`
                                : 'border-gray-200 bg-slate-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                            } disabled:opacity-50`}
                          >
                            {rKey === 'sangat_baik'
                              ? 'SB'
                              : rKey === 'baik'
                              ? 'B'
                              : rKey === 'cukup'
                              ? 'C'
                              : 'PB'}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Section 2: Catatan Naratif Wali Kelas */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <div>
                <h3 className="text-sm font-semibold text-gray-900">
                  2. Catatan Deskriptif Wali Kelas
                </h3>
                <p className="text-xs text-gray-500">
                  Ulasan deskriptif perkembangan kepribadian siswa yang akan dicetak pada E-Progress
                  Report
                </p>
              </div>
              <span className="text-[11px] text-gray-400 font-mono">
                {currentNote.length}/2000 Karakter
              </span>
            </div>

            {/* Template Suggestions */}
            <div className="mb-2">
              <div className="flex items-center gap-1.5 text-xs text-gray-600 mb-1.5 font-medium">
                <Sparkles className="h-3.5 w-3.5 text-amber-500" />
                <span>Rekomendasi Narasi Cepat (Klik untuk menyisipkan):</span>
              </div>
              <div className="flex flex-wrap gap-1.5">
                {TEMPLATE_SUGGESTIONS.map((tmpl, idx) => (
                  <button
                    key={idx}
                    type="button"
                    disabled={isLocked}
                    onClick={() => handleApplyTemplate(tmpl)}
                    className="text-[11px] text-left rounded-md border border-slate-200 bg-slate-50/80 px-2.5 py-1 text-slate-700 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-800 transition line-clamp-1 max-w-sm disabled:opacity-50"
                    title={tmpl}
                  >
                    + {tmpl}
                  </button>
                ))}
              </div>
            </div>

            <Controller
              name="homeroom_note"
              control={control}
              render={({ field }) => (
                <Textarea
                  {...field}
                  value={field.value || ''}
                  disabled={isLocked}
                  rows={4}
                  placeholder="Tuliskan catatan apresiasi, motivasi, atau aspek yang perlu ditingkatkan oleh ananda selama satu bulan ini..."
                  className="text-xs leading-relaxed"
                />
              )}
            />
            {errors.homeroom_note && (
              <p className="text-xs text-red-600 mt-1">{errors.homeroom_note.message}</p>
            )}
          </div>

          {/* Modal Footer */}
          <div className="flex items-center justify-between pt-4 border-t border-gray-100 shrink-0">
            <div className="text-xs text-gray-500">
              {existingNote?.author?.name && (
                <span>Terakhir diubah oleh: {existingNote.author.name}</span>
              )}
            </div>

            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={onClose}
                disabled={isSubmitting}
                className="text-xs"
              >
                Batal
              </Button>
              <Button
                type="submit"
                disabled={isSubmitting || isLocked}
                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
              >
                {isSubmitting ? (
                  <>
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    Menyimpan...
                  </>
                ) : (
                  <>
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    Simpan Catatan Karakter
                  </>
                )}
              </Button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
