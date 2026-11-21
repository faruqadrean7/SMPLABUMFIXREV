'use client';

import React, { useState } from 'react';
import { UserManagementItem, UserStatus } from '@/types/user-management';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { X, AlertTriangle, ShieldCheck, ShieldAlert } from 'lucide-react';

export interface UserStatusDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (status: UserStatus, reason?: string) => Promise<void>;
  user: UserManagementItem | null;
  isLoading?: boolean;
}

export function UserStatusDialog({
  isOpen,
  onClose,
  onSubmit,
  user,
  isLoading = false,
}: UserStatusDialogProps) {
  const [selectedStatus, setSelectedStatus] = useState<UserStatus>('active');
  const [reason, setReason] = useState('');

  if (!isOpen || !user) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onSubmit(selectedStatus, reason || undefined);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in">
      <div className="relative w-full max-w-md rounded-xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
        {/* Header Modal */}
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
              <ShieldAlert className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">Ubah Status Akun</h2>
              <p className="text-xs text-slate-500">
                Pengguna: <span className="font-semibold text-slate-800">{user.username}</span>
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={isLoading}
            className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Status Baru
            </label>
            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value as UserStatus)}
              className="w-full h-10 px-3 rounded-lg border border-slate-300 text-xs bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-blue"
            >
              <option value="active">Aktif (Dapat Login & Beraktivitas)</option>
              <option value="inactive">Nonaktif (Akses Masuk Dinonaktifkan)</option>
              <option value="suspended">Ditangguhkan (Suspended / Ditolak Sistem)</option>
            </select>
          </div>

          {selectedStatus === 'suspended' && (
            <div className="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800">
              <AlertTriangle className="h-4 w-4 shrink-0 text-red-600 mt-0.5" />
              <div>
                <span className="font-semibold">Perhatian Keamanan:</span> Penangguhan akun akan segera
                mencabut seluruh sesi login dan refresh token aktif pengguna ini di semua perangkat.
              </div>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Alasan Perubahan Status (Opsional)
            </label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Tuliskan catatan alasan tindakan ini untuk audit trail..."
              rows={3}
              className="w-full p-2.5 rounded-lg border border-slate-300 text-xs bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-blue"
            />
          </div>

          <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <Button type="button" variant="outline" onClick={onClose} disabled={isLoading} className="text-xs">
              Batal
            </Button>
            <Button
              type="submit"
              disabled={isLoading}
              className="bg-brand-blue hover:bg-brand-dark text-white text-xs font-semibold px-4"
            >
              {isLoading ? 'Memproses...' : 'Simpan Status'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
