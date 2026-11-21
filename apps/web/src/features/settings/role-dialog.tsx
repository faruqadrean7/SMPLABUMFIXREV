'use client';

import React, { useEffect, useState, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { RoleDetail, PermissionItem, CreateRolePayload, UpdateRolePayload } from '@/types/rbac';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { X, Shield, Key, Search, CheckSquare, Square, AlertTriangle } from 'lucide-react';

export const roleSchema = z.object({
  code: z
    .string()
    .min(2, 'Kode role minimal 2 karakter')
    .max(50, 'Maksimal 50 karakter')
    .regex(/^[a-z0-9_-]+$/, 'Kode hanya boleh menggunakan huruf kecil, angka, garis bawah (_), atau strip (-)'),
  name: z.string().min(3, 'Nama role minimal 3 karakter').max(100, 'Maksimal 100 karakter'),
  permission_ids: z.array(z.string()).default([]),
});

export type RoleFormValues = z.infer<typeof roleSchema>;

export interface RoleDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: CreateRolePayload | UpdateRolePayload) => Promise<void>;
  initialData?: RoleDetail | null;
  permissions: PermissionItem[];
  isLoading?: boolean;
}

export function RoleDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  permissions,
  isLoading = false,
}: RoleDialogProps) {
  const isEditing = !!initialData;
  const isSystemRole = initialData?.is_system ?? false;

  const [permSearch, setPermSearch] = useState('');

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<RoleFormValues>({
    resolver: zodResolver(roleSchema),
    defaultValues: {
      code: '',
      name: '',
      permission_ids: [],
    },
  });

  const selectedPermIds = watch('permission_ids') || [];

  useEffect(() => {
    if (initialData) {
      reset({
        code: initialData.code,
        name: initialData.name,
        permission_ids: initialData.permissions ? initialData.permissions.map((p) => p.id) : [],
      });
    } else {
      reset({
        code: '',
        name: '',
        permission_ids: [],
      });
    }
    setPermSearch('');
  }, [initialData, reset, isOpen]);

  // Kelompokkan izin berdasarkan prefix modul (contoh: master.student.read -> Master)
  const groupedPermissions = useMemo(() => {
    const map: Record<string, PermissionItem[]> = {};

    const filtered = permissions.filter((p) => {
      if (!permSearch) return true;
      const q = permSearch.toLowerCase();
      return p.code.toLowerCase().includes(q) || p.description.toLowerCase().includes(q);
    });

    filtered.forEach((p) => {
      const prefix = p.code.split('.')[0] || 'lainnya';
      const label = prefix.toUpperCase();
      if (!map[label]) map[label] = [];
      map[label].push(p);
    });

    return map;
  }, [permissions, permSearch]);

  const togglePermission = (id: string) => {
    const current = new Set(selectedPermIds);
    if (current.has(id)) {
      current.delete(id);
    } else {
      current.add(id);
    }
    setValue('permission_ids', Array.from(current));
  };

  const toggleModuleAll = (modulePerms: PermissionItem[]) => {
    const current = new Set(selectedPermIds);
    const allModuleSelected = modulePerms.every((p) => current.has(p.id));

    if (allModuleSelected) {
      modulePerms.forEach((p) => current.delete(p.id));
    } else {
      modulePerms.forEach((p) => current.add(p.id));
    }
    setValue('permission_ids', Array.from(current));
  };

  if (!isOpen) return null;

  const handleFormSubmit = async (values: RoleFormValues) => {
    if (isEditing) {
      await onSubmit({
        name: values.name,
        permission_ids: values.permission_ids,
      });
    } else {
      await onSubmit({
        code: values.code,
        name: values.name,
        permission_ids: values.permission_ids,
      });
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in">
      <div className="relative w-full max-w-3xl rounded-xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        {/* Header Modal */}
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-brand-blue">
              <Shield className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">
                {isEditing ? 'Ubah Hak Akses Role' : 'Tambah Role Baru'}
              </h2>
              <p className="text-xs text-slate-500">
                {isEditing
                  ? `Mengatur konfigurasi izin role: ${initialData?.name}`
                  : 'Definisikan role pengguna baru dan pilih hak akses yang sesuai'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={isSubmitting || isLoading}
            className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content Form */}
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-6 space-y-6">
            {isSystemRole && (
              <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900">
                <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
                <div>
                  <span className="font-semibold">Role Bawaan Sistem:</span> Kode role sistem tidak dapat diubah
                  atau dihapus demi menjaga kestabilan otentikasi. Anda dapat menyesuaikan nama tampilan dan izin
                  terkait.
                </div>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Kode Role <span className="text-red-500">*</span>
                </label>
                <Input
                  {...register('code')}
                  disabled={isEditing}
                  placeholder="contoh: kurikulum_staff"
                  className={errors.code ? 'border-red-400 focus:ring-red-400' : ''}
                />
                {errors.code ? (
                  <p className="text-[11px] text-red-600 mt-1">{errors.code.message}</p>
                ) : (
                  <p className="text-[11px] text-slate-400 mt-1">
                    Hanya huruf kecil, angka, dan garis bawah. Tidak bisa diubah setelah dibuat.
                  </p>
                )}
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Nama Tampilan Role <span className="text-red-500">*</span>
                </label>
                <Input
                  {...register('name')}
                  placeholder="contoh: Tim Pengembang Kurikulum"
                  className={errors.name ? 'border-red-400 focus:ring-red-400' : ''}
                />
                {errors.name && <p className="text-[11px] text-red-600 mt-1">{errors.name.message}</p>}
              </div>
            </div>

            {/* Matriks Hak Akses (Permissions) */}
            <div className="border-t border-slate-200 pt-5">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                    <Key className="h-3.5 w-3.5 text-brand-blue" /> Matriks Hak Akses (Permissions)
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Terpilih: <span className="font-semibold text-brand-blue">{selectedPermIds.length}</span> dari{' '}
                    {permissions.length} izin
                  </p>
                </div>
                <div className="relative w-full sm:w-64">
                  <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                  <Input
                    value={permSearch}
                    onChange={(e) => setPermSearch(e.target.value)}
                    placeholder="Cari hak akses..."
                    className="pl-8 text-xs h-8"
                  />
                </div>
              </div>

              <div className="space-y-4 max-h-[350px] overflow-y-auto pr-1">
                {Object.keys(groupedPermissions).length === 0 ? (
                  <div className="py-8 text-center text-xs text-slate-400">
                    Tidak ditemukan hak akses yang cocok dengan kata kunci &quot;{permSearch}&quot;
                  </div>
                ) : (
                  Object.entries(groupedPermissions).map(([moduleName, perms]) => {
                    const allSelected = perms.every((p) => selectedPermIds.includes(p.id));
                    const someSelected = perms.some((p) => selectedPermIds.includes(p.id)) && !allSelected;

                    return (
                      <div key={moduleName} className="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                        <div className="flex items-center justify-between pb-2 mb-2 border-b border-slate-200">
                          <div className="flex items-center gap-2">
                            <Badge variant="outline" className="font-mono text-[10px] bg-white">
                              {moduleName}
                            </Badge>
                            <span className="text-xs font-medium text-slate-600">
                              ({perms.filter((p) => selectedPermIds.includes(p.id)).length}/{perms.length})
                            </span>
                          </div>
                          <button
                            type="button"
                            onClick={() => toggleModuleAll(perms)}
                            className="text-[11px] font-medium text-brand-blue hover:underline flex items-center gap-1"
                          >
                            {allSelected ? (
                              <>
                                <CheckSquare className="h-3.5 w-3.5" /> Batal Pilih
                              </>
                            ) : (
                              <>
                                <Square className="h-3.5 w-3.5" /> Pilih Semua
                              </>
                            )}
                          </button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                          {perms.map((perm) => {
                            const isChecked = selectedPermIds.includes(perm.id);
                            return (
                              <label
                                key={perm.id}
                                className={`flex items-start gap-2.5 p-2 rounded-md border text-xs cursor-pointer transition-colors ${
                                  isChecked
                                    ? 'bg-blue-50/70 border-blue-200 text-slate-900'
                                    : 'bg-white border-slate-200/80 hover:bg-slate-50 text-slate-700'
                                }`}
                              >
                                <input
                                  type="checkbox"
                                  className="mt-0.5 rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                                  checked={isChecked}
                                  onChange={() => togglePermission(perm.id)}
                                />
                                <div className="flex-1">
                                  <div className="font-mono text-[11px] font-semibold text-slate-800">
                                    {perm.code}
                                  </div>
                                  <div className="text-[11px] text-slate-500 line-clamp-2">
                                    {perm.description}
                                  </div>
                                </div>
                              </label>
                            );
                          })}
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </div>
          </div>

          {/* Footer Actions */}
          <div className="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-6 py-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={isSubmitting || isLoading}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={isSubmitting || isLoading}
              className="bg-brand-blue hover:bg-brand-dark text-white text-xs font-semibold px-5"
            >
              {isSubmitting || isLoading ? 'Menyimpan...' : isEditing ? 'Perbarui Role' : 'Buat Role'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
