'use client';

import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  LearningMaterialItem,
  StoreLearningMaterialPayload,
  UpdateLearningMaterialPayload,
} from '@/types/academic';
import { SchoolClassItem, SubjectItem } from '@/types/master';
import { academicApi } from '../academic-api';
import { ApiClientError } from '@/lib/api/errors';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  BookOpen,
  FileText,
  Upload,
  AlertTriangle,
  X,
  Layers,
  GraduationCap,
} from 'lucide-react';

export const materialSchema = z.object({
  class_id: z.string().uuid({ message: 'Kelas tujuan wajib dipilih' }),
  subject_id: z.string().uuid({ message: 'Mata pelajaran wajib dipilih' }),
  title: z
    .string()
    .min(3, { message: 'Judul materi minimal 3 karakter' })
    .max(200, { message: 'Judul materi maksimal 200 karakter' }),
  description: z
    .string()
    .max(2000, { message: 'Deskripsi materi maksimal 2000 karakter' })
    .optional()
    .or(z.literal('')),
});

export type MaterialDialogFormValues = z.infer<typeof materialSchema>;

interface MaterialDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  material?: LearningMaterialItem | null;
  classes: SchoolClassItem[];
  subjects: SubjectItem[];
  defaultClassId?: string;
  defaultSubjectId?: string;
}

export function MaterialDialog({
  isOpen,
  onClose,
  onSuccess,
  material,
  classes,
  subjects,
  defaultClassId,
  defaultSubjectId,
}: MaterialDialogProps) {
  const isEditing = !!material;
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<MaterialDialogFormValues>({
    resolver: zodResolver(materialSchema),
    defaultValues: {
      class_id: defaultClassId || '',
      subject_id: defaultSubjectId || '',
      title: '',
      description: '',
    },
  });

  useEffect(() => {
    if (material) {
      reset({
        class_id: material.class_id,
        subject_id: material.subject_id,
        title: material.title,
        description: material.description || '',
      });
      setSelectedFile(null);
    } else {
      reset({
        class_id: defaultClassId || '',
        subject_id: defaultSubjectId || '',
        title: '',
        description: '',
      });
      setSelectedFile(null);
    }
    setErrorMessage(null);
  }, [material, defaultClassId, defaultSubjectId, reset, isOpen]);

  if (!isOpen) return null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      if (file.size > 52428800) {
        // 50MB
        setErrorMessage('Ukuran berkas materi melebihi batas maksimal 50 MB.');
        return;
      }
      setSelectedFile(file);
      setErrorMessage(null);
    }
  };

  const onSubmit = async (values: MaterialDialogFormValues) => {
    try {
      setErrorMessage(null);

      if (isEditing && material) {
        const updatePayload: UpdateLearningMaterialPayload = {
          title: values.title,
          description: values.description || undefined,
          file: selectedFile || undefined,
        };
        await academicApi.updateMaterial(material.id, updatePayload);
      } else {
        const createPayload: StoreLearningMaterialPayload = {
          class_id: values.class_id,
          subject_id: values.subject_id,
          title: values.title,
          description: values.description || undefined,
          file: selectedFile || undefined,
        };
        await academicApi.createMaterial(createPayload);
      }

      onSuccess();
      onClose();
    } catch (err: unknown) {
      if (err instanceof ApiClientError) {
        setErrorMessage(err.message);
      } else if (err instanceof Error) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage('Terjadi kesalahan saat menyimpan materi pembelajaran.');
      }
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-xl rounded-xl border border-gray-200 bg-white shadow-xl animate-in fade-in-0 zoom-in-95">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div className="flex items-center gap-2.5">
            <div className="rounded-lg bg-emerald-100 p-2 text-emerald-700">
              <BookOpen className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-gray-900">
                {isEditing ? 'Ubah Materi Pembelajaran' : 'Unggah Materi Pembelajaran Baru'}
              </h2>
              <p className="text-xs text-gray-500">
                {isEditing
                  ? 'Perbarui judul, ringkasan, atau berkas acuan materi'
                  : 'Bagikan bahan ajar digital, modul, atau slide presentasi kepada siswa'}
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

          {/* Target Class & Subject */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Kelas / Rombel <span className="text-rose-500">*</span>
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
              Judul Materi Pembelajaran <span className="text-rose-500">*</span>
            </label>
            <Input
              type="text"
              placeholder="Contoh: Bab 2 - Sistem Persamaan Linear Dua Variabel"
              {...register('title')}
              className="text-xs"
            />
            {errors.title && (
              <p className="mt-1 text-[11px] text-rose-600">{errors.title.message}</p>
            )}
          </div>

          {/* Description */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Deskripsi & Petunjuk Pembelajaran
            </label>
            <textarea
              rows={3}
              placeholder="Jelaskan ringkasan materi, capaian pembelajaran, atau petunjuk bagi siswa..."
              {...register('description')}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden"
            />
            {errors.description && (
              <p className="mt-1 text-[11px] text-rose-600">{errors.description.message}</p>
            )}
          </div>

          {/* File Upload */}
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">
              Berkas Lampiran Digital (PDF, DOCX, PPTX, XLSX, ZIP, MP4 max 50MB)
            </label>
            <div className="mt-1 flex justify-center rounded-lg border border-dashed border-gray-300 px-6 py-4 hover:border-emerald-400 transition bg-gray-50/50">
              <div className="text-center">
                <Upload className="mx-auto h-8 w-8 text-gray-400" />
                <div className="mt-2 flex text-xs leading-6 text-gray-600 justify-center">
                  <label
                    htmlFor="material-file"
                    className="relative cursor-pointer rounded-md font-semibold text-emerald-600 focus-within:outline-hidden hover:text-emerald-500"
                  >
                    <span>Pilih Berkas</span>
                    <input
                      id="material-file"
                      type="file"
                      onChange={handleFileChange}
                      className="sr-only"
                      accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.mp4"
                    />
                  </label>
                  <p className="pl-1">atau tarik dan lepas</p>
                </div>
                <p className="text-[11px] text-gray-500 mt-0.5">
                  {selectedFile
                    ? `Terpilih: ${selectedFile.name} (${(selectedFile.size / (1024 * 1024)).toFixed(2)} MB)`
                    : material?.file
                    ? `Berkas tersimpan: ${material.file.object_key.split('/').pop() || 'Dokumen Materi'}`
                    : 'PDF, Word, PowerPoint, Excel, Video MP4 hingga 50 MB'}
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
              {isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Terbitkan Materi'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
