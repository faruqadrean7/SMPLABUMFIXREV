'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { UserManagementItem, CreateUserPayload, UpdateUserPayload, UserStatus } from '@/types/user-management';
import { RoleItem } from '@/types/rbac';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { X, UserPlus, UserCog, Key, Shield } from 'lucide-react';

export const userFormSchema = z.object({
  username: z
    .string()
    .min(3, 'Username minimal 3 karakter')
    .max(50, 'Maksimal 50 karakter')
    .regex(/^[a-zA-Z0-9._-]+$/, 'Hanya boleh memuat huruf, angka, titik, strip, atau garis bawah'),
  email: z
    .string()
    .email('Format email tidak valid')
    .or(z.literal(''))
    .optional(),
  phone: z.string().max(30, 'Maksimal 30 karakter').optional(),
  password: z
    .string()
    .min(8, 'Password minimal 8 karakter')
    .or(z.literal(''))
    .optional(),
  status: z.enum(['active', 'inactive', 'suspended'] as const).default('active'),
  roles: z.array(z.string()).default([]),
});

export type UserFormValues = z.infer<typeof userFormSchema>;

export interface UserDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: CreateUserPayload | UpdateUserPayload) => Promise<void>;
  initialData?: UserManagementItem | null;
  availableRoles: RoleItem[];
  isLoading?: boolean;
}

export function UserDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  availableRoles,
  isLoading = false,
}: UserDialogProps) {
  const isEditing = !!initialData;

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<UserFormValues>({
    resolver: zodResolver(userFormSchema),
    defaultValues: {
      username: '',
      email: '',
      phone: '',
      password: '',
      status: 'active',
      roles: [],
    },
  });

  const selectedRoles = watch('roles') || [];

  useEffect(() => {
    if (initialData) {
      reset({
        username: initialData.username,
        email: initialData.email || '',
        phone: initialData.phone || '',
        password: '',
        status: (initialData.status as UserStatus) || 'active',
        roles: initialData.roles ? initialData.roles.map((r) => r.code) : [],
      });
    } else {
      reset({
        username: '',
        email: '',
        phone: '',
        password: '',
        status: 'active',
        roles: [],
      });
    }
  }, [initialData, reset, isOpen]);

  if (!isOpen) return null;

  const toggleRole = (code: string) => {
    const current = new Set(selectedRoles);
    if (current.has(code)) {
      current.delete(code);
    } else {
      current.add(code);
    }
    setValue('roles', Array.from(current));
  };

  const handleFormSubmit = async (values: UserFormValues) => {
    if (isEditing) {
      const payload: UpdateUserPayload = {
        username: values.username,
        email: values.email || undefined,
        phone: values.phone || undefined,
        status: values.status,
        roles: values.roles,
      };
      await onSubmit(payload);
    } else {
      const payload: CreateUserPayload = {
        username: values.username,
        email: values.email || undefined,
        phone: values.phone || undefined,
        password: values.password || undefined,
        status: values.status,
        roles: values.roles,
      };
      await onSubmit(payload);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in">
      <div className="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        {/* Header Modal */}
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-brand-blue">
              {isEditing ? <UserCog className="h-5 w-5" /> : <UserPlus className="h-5 w-5" />}
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">
                {isEditing ? 'Ubah Akun Pengguna' : 'Tambah Akun Pengguna Baru'}
              </h2>
              <p className="text-xs text-slate-500">
                {isEditing
                  ? `Memperbarui identitas dan akses akun: ${initialData?.username}`
                  : 'Daftarkan akun pengguna sistem dan tentukan peran wewenangnya'}
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

        {/* Content Body */}
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-6 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Username <span className="text-red-500">*</span>
                </label>
                <Input
                  {...register('username')}
                  placeholder="contoh: budi_santoso"
                  className={errors.username ? 'border-red-400 focus:ring-red-400' : ''}
                />
                {errors.username && <p className="text-[11px] text-red-600 mt-1">{errors.username.message}</p>}
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Email (Opsional)
                </label>
                <Input
                  type="email"
                  {...register('email')}
                  placeholder="contoh: budi@labschool.sch.id"
                  className={errors.email ? 'border-red-400 focus:ring-red-400' : ''}
                />
                {errors.email && <p className="text-[11px] text-red-600 mt-1">{errors.email.message}</p>}
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Nomor Telepon / WhatsApp
                </label>
                <Input
                  {...register('phone')}
                  placeholder="contoh: 081234567890"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Status Akun
                </label>
                <select
                  {...register('status')}
                  className="w-full h-10 px-3 rounded-lg border border-slate-300 text-xs bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                >
                  <option value="active">Aktif</option>
                  <option value="inactive">Nonaktif</option>
                  <option value="suspended">Ditangguhkan (Suspended)</option>
                </select>
              </div>
            </div>

            {!isEditing && (
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Password Awal (Opsional)
                </label>
                <Input
                  type="password"
                  {...register('password')}
                  placeholder="Kosongkan untuk membuat password acak otomatis"
                  className={errors.password ? 'border-red-400 focus:ring-red-400' : ''}
                />
                {errors.password ? (
                  <p className="text-[11px] text-red-600 mt-1">{errors.password.message}</p>
                ) : (
                  <p className="text-[11px] text-slate-400 mt-1">
                    Jika dikosongkan, sistem akan meng-generate password sementara acak 12 karakter aman.
                  </p>
                )}
              </div>
            )}

            {/* Peran Pengguna (Roles Assignment) */}
            <div className="border-t border-slate-200 pt-4">
              <label className="block text-xs font-semibold text-slate-700 mb-2 flex items-center gap-1.5">
                <Shield className="h-3.5 w-3.5 text-brand-blue" /> Peran Akun (Multi-Role)
              </label>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[180px] overflow-y-auto pr-1">
                {availableRoles.map((role) => {
                  const isChecked = selectedRoles.includes(role.code);
                  return (
                    <label
                      key={role.id}
                      className={`flex items-center gap-2.5 p-2 rounded-md border text-xs cursor-pointer transition-colors ${
                        isChecked
                          ? 'bg-blue-50 border-blue-200 text-slate-900 font-medium'
                          : 'bg-white border-slate-200 hover:bg-slate-50 text-slate-600'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={isChecked}
                        onChange={() => toggleRole(role.code)}
                        className="rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                      />
                      <span className="flex-1 truncate">{role.name}</span>
                      <Badge variant="outline" className="text-[10px] bg-white font-mono shrink-0">
                        {role.code}
                      </Badge>
                    </label>
                  );
                })}
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
              {isSubmitting || isLoading ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Buat Akun'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
