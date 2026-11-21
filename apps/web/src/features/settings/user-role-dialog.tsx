'use client';

import React, { useEffect, useState } from 'react';
import { RoleItem, AssignUserRolesPayload } from '@/types/rbac';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { X, ShieldCheck, UserCheck } from 'lucide-react';

export interface UserRoleDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: AssignUserRolesPayload) => Promise<void>;
  userId: string;
  username: string;
  currentRoleCodes: string[];
  allRoles: RoleItem[];
  isLoading?: boolean;
}

export function UserRoleDialog({
  isOpen,
  onClose,
  onSubmit,
  username,
  currentRoleCodes,
  allRoles,
  isLoading = false,
}: UserRoleDialogProps) {
  const [selectedRoleIds, setSelectedRoleIds] = useState<string[]>([]);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (isOpen) {
      // Petakan currentRoleCodes ke ID role
      const initialIds = allRoles
        .filter((r) => currentRoleCodes.includes(r.code))
        .map((r) => r.id);
      setSelectedRoleIds(initialIds);
    }
  }, [isOpen, currentRoleCodes, allRoles]);

  if (!isOpen) return null;

  const toggleRole = (roleId: string) => {
    setSelectedRoleIds((prev) =>
      prev.includes(roleId) ? prev.filter((id) => id !== roleId) : [...prev, roleId]
    );
  };

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const payload: AssignUserRolesPayload = {
        roles: selectedRoleIds.map((roleId) => ({
          role_id: roleId,
          scope_class_id: null,
        })),
      };
      await onSubmit(payload);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in">
      <div className="relative w-full max-w-lg rounded-xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[85vh]">
        {/* Header Modal */}
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-brand-blue">
              <UserCheck className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">Tetapkan Peran Pengguna</h2>
              <p className="text-xs text-slate-500">
                Pengguna: <span className="font-semibold text-slate-800">{username}</span>
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            disabled={submitting || isLoading}
            className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content Body */}
        <form onSubmit={handleFormSubmit} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-6 space-y-4">
            <p className="text-xs text-slate-600">
              Pilih satu atau beberapa peran untuk akun ini. Hak akses akan digabungkan secara otomatis dari
              seluruh peran terpilih.
            </p>

            <div className="space-y-2 max-h-[300px] overflow-y-auto pr-1">
              {allRoles.map((role) => {
                const isChecked = selectedRoleIds.includes(role.id);
                return (
                  <label
                    key={role.id}
                    className={`flex items-start gap-3 p-3 rounded-lg border text-xs cursor-pointer transition-colors ${
                      isChecked
                        ? 'bg-blue-50/80 border-blue-300 text-slate-900 shadow-sm'
                        : 'bg-white border-slate-200 hover:bg-slate-50 text-slate-700'
                    }`}
                  >
                    <input
                      type="checkbox"
                      className="mt-0.5 rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                      checked={isChecked}
                      onChange={() => toggleRole(role.id)}
                    />
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-slate-900">{role.name}</span>
                        {role.is_system ? (
                          <Badge variant="outline" className="text-[10px] bg-slate-100 text-slate-600">
                            Sistem
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="text-[10px] bg-emerald-50 text-emerald-700 border-emerald-200">
                            Kustom
                          </Badge>
                        )}
                      </div>
                      <div className="font-mono text-[11px] text-slate-500 mt-0.5">Kode: {role.code}</div>
                    </div>
                  </label>
                );
              })}
            </div>
          </div>

          {/* Footer Actions */}
          <div className="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-6 py-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={submitting || isLoading}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={submitting || isLoading}
              className="bg-brand-blue hover:bg-brand-dark text-white text-xs font-semibold px-5"
            >
              {submitting || isLoading ? 'Menyimpan...' : 'Simpan Peran'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
