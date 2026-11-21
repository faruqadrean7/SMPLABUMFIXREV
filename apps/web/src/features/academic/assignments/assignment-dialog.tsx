'use client';

import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  AssignmentItem,
  StoreAssignmentPayload,
  UpdateAssignmentPayload,
} from '@/types/academic';
import { SchoolClassItem, SubjectItem } from '@/types/master';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  FileText,
  Upload,
  AlertTriangle,
  X,
  Layers,
  GraduationCap,
  Calendar,
  Award,
} from 'lucide-react';

export const assignmentSchema = z.object({
  class_id: z.string().uuid({ message: 'Kelas tujuan wajib dipilih' }),
  subject_id: z.string().uuid({ message: 'Mata pelajaran wajib dipilih' }),
  title: z
    .string()
    .min(3, { message: 'Judul tugas minimal 3 karakter' })
    .max(200, { message: 'Judul tugas maksimal 200 karakter' }),
  description: z
    .string()
    .max(2000, { message: 'Petunjuk tugas maksimal 2000 karakter' })
    .optional()
    .or(z.literal('')),
  due_at: z.string().min(1, { message: 'Tenggat waktu pengerjaan tugas wajib diisi' }),
  max_score: z.coerce
    .number()
    .min(1, { message: 'Nilai maksimal minimal 1' })
    .max(100, { message: 'Nilai maksimal 100' })
    .default(100),
  grade_component_id: z.string().uuid().optional().or(z.literal('')),
});

export type AssignmentDialogFormValues = z.infer<typeof assignmentSchema>;

interface AssignmentDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  assignment?: AssignmentItem | null;
  classes: SchoolClassItem[];
  subjects: SubjectItem[];
  defaultClassId?: string;
  defaultSubjectId?: string;
}

export function AssignmentDialog({
  isOpen,
  onClose,
  onSuccess,
  assignment,
  classes,
  subjects,
  defaultClassId,
  defaultSubjectId,
}: AssignmentDialogProps) {
  const isEditing = !!assignment;
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  // Default due date: 3 days ahead at 23:59
  const defaultDue = new Date(Date.now() + 3 * 24 * 60 * 60 * 1000);
  defaultDue.setHours(23, 59, 0, 0);
  const defaultDueStr = defaultDue.toISOString().slice(0, 16);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<AssignmentDialogFormValues>({
    resolver: zodResolver(assignmentSchema),
    defaultValues: {
      class_id: defaultClassId || '',
      subject_id: defaultSubjectId || '',
      title: '',
      description: '',
      due_at: defaultDueStr,
      max_score: 100,
      grade_component_id: '',
    },
  });

  useEffect(() => {
    if (assignment) {
      const formattedDue = assignment.due_at
        ? new Date(assignment.due_at).toISOString().slice(0, 16)
        : defaultDueStr;

      reset({
        class_id: assignment.class_id,
        subject_id: assignment.subject_id,
        title: assignment.title,
        description: assignment.description || '',
        due_at: formattedDue,
        max_score: Number(assignment.max_score) || 100,
        grade_component_id: assignment.grade_component_id || '',
      });
      setSelectedFile(null);
    } else {
      reset({
        class_id: defaultClassId || '',
        subject_id: defaultSubjectId || '',
        title: '',
        description: '',
        due_at: defaultDueStr,
        max_score: 100,
        grade_component_id: '',
      });
      setSelectedFile(null);
    }
    setErrorMessage(null);
  }, [assignment, defaultClassId, defaultSubjectId, reset, isOpen, defaultDueStr]);

  if (!isOpen) return null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      if (file.size > 26214400) {
        // 25MB
        setErrorMessage('Ukuran berkas acuan tugas melebihi batas maksimal 25 MB.');
        return;
      }
      setSelectedFile(file);
      setErrorMessage(null);
    }
  };

  const onSubmit = async (values: AssignmentDialogFormValues) => {
    try {
      setErrorMessage(null);

      // Ensure due_at has full date string format (YYYY-MM-DD HH:mm:ss)
      const dueAtFormatted = values.due_at.length === 16 ? `${values.due_at}:00` : values.due_at;

      if (isEditing && assignment) {
        const updatePayload: UpdateAssignmentPayload = {
          title: values.title,
          description: values.description || undefined,
          due_at: dueAtFormatted,
          max_score: values.max_score,
          grade_component_id: values.grade_component_id || undefined,
          file: selectedFile || undefined,
        };
        await academicApi.updateAssignment(assignment.id, updatePayload);
      } else {
        const createPayload: StoreAssignmentPayload = {
          class_id: values.class_id,
          subject_id: values.subject_id,
          title: values.title,
          description: values.description || undefined,
          due_at: dueAtFormatted,
          max_score: values.max_score,
          grade_component_id: values.grade_component_id || undefined,
          file: selectedFile || undefined,
        };
        await academicApi.createAssignment(createPayload);
      }

      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else if (err instanceof Error) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat menyimpan tugas kelas.');
      }
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl animate-in fade-in-0 zoom-in-95">
        {/* Header */}
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-100 p-2 text-emerald-700">
              <FileText className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                {isEditing ? 'Ubah Tugas Kelas' : 'Terbitkan Tugas Kelas Baru'}
              </h2>
              <p className="text-xs text-gray-500">
                {isEditing
                  ? 'Perbarui judul, tenggat pengerjaan, atau lembar soal tugas'
                  : 'Terbitkan instruksi tugas, batas pengumpulan, dan berkas acuan untuk siswa'}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 px-6 py-5">
          {errorMessage && (
            <div className="flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
              <span>{errorMessage}</span>
            </div>
          )}

          {/* Class & Subject */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Kelas Tujuan <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <select
                  disabled={isEditing}
                  {...register('class_id')}
                  className="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden disabled:bg-gray-100 disabled:text-gray-500"
                >
                  <option value="">Pilih Kelas...</option>
                  {classes.map((cls) => (
                    <option key={cls.id} value={cls.id}>
                      {cls.name}
                    </option>
                  ))}
                </select>
                <Layers className="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-gray-400" />
              </div>
              {errors.class_id && (
                <p className="mt-1 text-[11px] text-rose-600">{errors.class_id.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Mata Pelajaran <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <select
                  disabled={isEditing}
                  {...register('subject_id')}
                  className="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden disabled:bg-gray-100 disabled:text-gray-500"
                >
                  <option value="">Pilih Mata Pelajaran...</option>
                  {subjects.map((sbj) => (
                    <option key={sbj.id} value={sbj.id}>
                      {sbj.name} ({sbj.code})
                    </option>
                  ))}
                </select>
                <GraduationCap className="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-gray-400" />
              </div>
              {errors.subject_id && (
                <p className="mt-1 text-[11px] text-rose-600">{errors.subject_id.message}</p>
              )}
            </div>
          </div>

          {/* Title */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Judul Tugas <span className="text-rose-500">*</span>
            </label>
            <Input
              type="text"
              placeholder="Contoh: Latihan 3 - Penyelesaian Soal Cerita SPLDV"
              {...register('title')}
              className="text-xs"
            />
            {errors.title && (
              <p className="mt-1 text-[11px] text-rose-600">{errors.title.message}</p>
            )}
          </div>

          {/* Due date & Max score */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Batas Pengumpulan (Deadline) <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <Input
                  type="datetime-local"
                  {...register('due_at')}
                  className="text-xs pr-8"
                />
                <Calendar className="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-gray-400" />
              </div>
              {errors.due_at && (
                <p className="mt-1 text-[11px] text-rose-600">{errors.due_at.message}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Nilai Maksimal <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <Input
                  type="number"
                  min={1}
                  max={100}
                  step={1}
                  placeholder="100"
                  {...register('max_score')}
                  className="text-xs pr-8"
                />
                <Award className="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-gray-400" />
              </div>
              {errors.max_score && (
                <p className="mt-1 text-[11px] text-rose-600">{errors.max_score.message}</p>
              )}
            </div>
          </div>

          {/* Description / Instructions */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Petunjuk Pengerjaan Tugas
            </label>
            <textarea
              rows={3}
              placeholder="Tuliskan petunjuk teknis, format pengumpulan, nomor soal yang dikerjakan..."
              {...register('description')}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden"
            />
            {errors.description && (
              <p className="mt-1 text-[11px] text-rose-600">{errors.description.message}</p>
            )}
          </div>

          {/* File Upload (Lembar Tugas / Acuan) */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Lembar Soal / Lampiran Tugas (PDF, DOCX, ZIP max 25MB)
            </label>
            <div className="mt-1 flex justify-center rounded-lg border border-dashed border-gray-300 px-6 py-4 hover:border-emerald-400 transition bg-gray-50/50">
              <div className="text-center">
                <Upload className="mx-auto h-8 w-8 text-gray-400" />
                <div className="mt-2 flex text-xs leading-6 text-gray-600 justify-center">
                  <label
                    htmlFor="assignment-file"
                    className="relative cursor-pointer rounded-md font-semibold text-emerald-600 focus-within:outline-hidden hover:text-emerald-500"
                  >
                    <span>Pilih Berkas Soal</span>
                    <input
                      id="assignment-file"
                      type="file"
                      onChange={handleFileChange}
                      className="sr-only"
                      accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip"
                    />
                  </label>
                  <p className="pl-1">atau seret ke sini</p>
                </div>
                <p className="text-[11px] text-gray-500 mt-0.5">
                  {selectedFile
                    ? `Terpilih: ${selectedFile.name} (${(selectedFile.size / (1024 * 1024)).toFixed(2)} MB)`
                    : assignment?.file
                    ? `Berkas tersimpan: ${assignment.file.object_key.split('/').pop() || 'Lembar Soal'}`
                    : 'PDF, Word, PPT, Excel, ZIP hingga 25 MB'}
                </p>
              </div>
            </div>
          </div>

          {/* Footer Buttons */}
          <div className="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={isSubmitting}
            >
              Batal
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={isSubmitting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Terbitkan Tugas'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
