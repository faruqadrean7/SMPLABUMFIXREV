'use client';

import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { GuardianFormValues, GuardianItem } from '@/types/master';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, Users, Save, KeyRound, Eye, EyeOff, ShieldCheck } from 'lucide-react';

export const guardianSchema = z.object({
  full_name: z.string().min(1, 'Nama lengkap wali wajib diisi').max(200, 'Maksimal 200 karakter'),
  phone: z.string().min(1, 'Nomor telepon/HP wajib diisi').max(30, 'Maksimal 30 karakter'),
  occupation: z.string().max(100, 'Maksimal 100 karakter').optional().or(z.literal('')),
  address: z.string().optional().or(z.literal('')),
  create_user_account: z.boolean().optional(),
  username: z.string().optional(),
  email: z.string().optional(),
  password: z.string().optional(),
});

export interface GuardianDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: GuardianFormValues) => Promise<void>;
  initialData?: GuardianItem | null;
  isLoading?: boolean;
}

export function GuardianDialog({
  isOpen,
  onClose,
  onSubmit,
  initialData,
  isLoading = false,
}: GuardianDialogProps) {
  const isEditing = !!initialData;

  const [createUserAccount, setCreateUserAccount] = React.useState<boolean>(!initialData);
  const [showPassword, setShowPassword] = React.useState<boolean>(false);

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<GuardianFormValues>({
    resolver: zodResolver(guardianSchema),
    defaultValues: {
      full_name: '',
      phone: '',
      occupation: '',
      address: '',
      create_user_account: true,
      username: '',
      email: '',
      password: 'Password123!',
    },
  });

  const fullNameVal = watch('full_name');

  useEffect(() => {
    if (initialData) {
      setCreateUserAccount(false);
      reset({
        full_name: initialData.full_name || '',
        phone: initialData.phone || '',
        occupation: initialData.occupation || '',
        address: initialData.address || '',
        create_user_account: false,
        username: initialData.user?.username || '',
        email: initialData.user?.email || '',
        password: '',
      });
    } else {
      setCreateUserAccount(true);
      reset({
        full_name: '',
        phone: '',
        occupation: '',
        address: '',
        create_user_account: true,
        username: '',
        email: '',
        password: 'Password123!',
      });
    }
  }, [initialData, reset, isOpen]);

  // Auto-suggest username when typing name on creation
  useEffect(() => {
    if (!initialData && fullNameVal) {
      const clean = fullNameVal
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '')
        .slice(0, 15);
      if (clean) {
        setValue('username', `ortu_${clean}`, { shouldValidate: true });
      }
    }
  }, [fullNameVal, initialData, setValue]);

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-md rounded-xl bg-white p-6 shadow-elevated border border-slate-200">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-brand-blue">
              {isEditing ? <Save className="h-5 w-5" /> : <Users className="h-5 w-5" />}
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">
                {isEditing ? 'Perbarui Data Orang Tua / Wali' : 'Tambah Orang Tua / Wali'}
              </h2>
              <p className="text-xs text-slate-500">Informasi kontak dan domisili wali murid</p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Nama Lengkap Wali <span className="text-status-danger">*</span>
            </label>
            <Input
              {...register('full_name')}
              placeholder="Nama lengkap sesuai KTP"
              className="h-9 text-xs"
              disabled={isLoading || isSubmitting}
            />
            {errors.full_name && (
              <p className="mt-1 text-[11px] text-status-danger">{errors.full_name.message}</p>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                No. Telepon / WhatsApp <span className="text-status-danger">*</span>
              </label>
              <Input
                {...register('phone')}
                placeholder="0812xxxxxxxx"
                className="h-9 text-xs font-mono"
                disabled={isLoading || isSubmitting}
              />
              {errors.phone && <p className="mt-1 text-[11px] text-status-danger">{errors.phone.message}</p>}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Pekerjaan / Profesi
              </label>
              <Input
                {...register('occupation')}
                placeholder="PNS / Swasta / Wiraswasta"
                className="h-9 text-xs"
                disabled={isLoading || isSubmitting}
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Alamat Domisili
            </label>
            <Input
              {...register('address')}
              placeholder="Alamat tempat tinggal lengkap"
              className="h-9 text-xs"
              disabled={isLoading || isSubmitting}
            />
          </div>

          {/* Opsi Pembuatan / Pengaturan Akun Login Portal */}
          <div className="pt-2 border-t border-slate-100">
            <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-3.5 space-y-3">
              <label className="flex items-center gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={createUserAccount}
                  onChange={(e) => {
                    setCreateUserAccount(e.target.checked);
                    setValue('create_user_account', e.target.checked);
                  }}
                  className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4"
                />
                <div className="flex items-center gap-1.5 font-semibold text-xs text-slate-900">
                  <KeyRound className="h-3.5 w-3.5 text-blue-600" />
                  <span>{isEditing ? 'Perbarui Akun Login Portal' : 'Aktifkan Akun Login Portal Wali Murid'}</span>
                </div>
              </label>

              {createUserAccount && (
                <div className="space-y-3 pt-1 pl-6">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-[11px] font-semibold text-slate-700 mb-1">
                        Username Login <span className="text-status-danger">*</span>
                      </label>
                      <Input
                        {...register('username')}
                        placeholder="ortu_nama"
                        className="h-8 text-xs font-mono bg-white"
                        disabled={isLoading || isSubmitting}
                      />
                      <p className="text-[10px] text-slate-400 mt-0.5">Digunakan wali saat login di /masuk</p>
                    </div>

                    <div>
                      <label className="block text-[11px] font-semibold text-slate-700 mb-1">
                        Kata Sandi / Password <span className="text-status-danger">*</span>
                      </label>
                      <div className="relative">
                        <Input
                          {...register('password')}
                          type={showPassword ? 'text' : 'password'}
                          placeholder="Password123!"
                          className="h-8 text-xs font-mono bg-white pr-8"
                          disabled={isLoading || isSubmitting}
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword(!showPassword)}
                          className="absolute right-2 top-2 text-slate-400 hover:text-slate-600"
                        >
                          {showPassword ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                        </button>
                      </div>
                      <p className="text-[10px] text-slate-400 mt-0.5">Default standar: Password123!</p>
                    </div>
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-slate-700 mb-1">
                      Email Wali Murid (Opsional)
                    </label>
                    <Input
                      {...register('email')}
                      type="email"
                      placeholder="wali@gmail.com"
                      className="h-8 text-xs bg-white"
                      disabled={isLoading || isSubmitting}
                    />
                  </div>
                </div>
              )}
            </div>
          </div>

          <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={isLoading || isSubmitting}
            >
              Batal
            </Button>
            <Button
              type="submit"
              variant="default"
              size="sm"
              isLoading={isLoading || isSubmitting}
              disabled={isLoading || isSubmitting}
            >
              {isEditing ? 'Simpan Perubahan' : 'Tambah Wali'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
