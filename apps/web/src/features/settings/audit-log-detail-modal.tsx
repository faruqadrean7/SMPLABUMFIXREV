'use client';

import React from 'react';
import { AuditLogDetail } from '@/types/audit-log';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatTanggalIndo } from '@/lib/utils';
import { X, FileClock, User, Globe, Laptop, ArrowRightLeft } from 'lucide-react';

export interface AuditLogDetailModalProps {
  isOpen: boolean;
  onClose: () => void;
  data: AuditLogDetail | null;
}

export function AuditLogDetailModal({ isOpen, onClose, data }: AuditLogDetailModalProps) {
  if (!isOpen || !data) return null;

  const getActionBadgeVariant = (action: string) => {
    if (action.includes('created') || action.includes('create')) {
      return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    if (action.includes('updated') || action.includes('update') || action.includes('assigned')) {
      return 'bg-blue-50 text-blue-700 border-blue-200';
    }
    if (action.includes('deleted') || action.includes('delete') || action.includes('revoked')) {
      return 'bg-rose-50 text-rose-700 border-rose-200';
    }
    if (action.includes('login') || action.includes('auth')) {
      return 'bg-amber-50 text-amber-700 border-amber-200';
    }
    return 'bg-slate-50 text-slate-700 border-slate-200';
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in">
      <div className="relative w-full max-w-3xl rounded-xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        {/* Header Modal */}
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
              <FileClock className="h-5 w-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base font-bold text-slate-900">Detail Audit Keamanan</h2>
                <Badge variant="outline" className={`font-mono text-[11px] ${getActionBadgeVariant(data.action)}`}>
                  {data.action}
                </Badge>
              </div>
              <p className="text-xs text-slate-500">ID Rekaman: <span className="font-mono">{data.id}</span></p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* Metadata Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 rounded-lg bg-slate-50/70 p-4 border border-slate-200 text-xs">
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-slate-500">
                <User className="h-3.5 w-3.5 text-slate-400" />
                <span className="font-semibold text-slate-700">Aktor / Pengguna:</span>
                <span className="font-medium text-slate-900">
                  {data.actor?.username || data.actor_id || 'Sistem Otomatis'}
                </span>
              </div>
              {data.actor?.email && (
                <div className="text-[11px] text-slate-500 pl-5.5">
                  Email: {data.actor.email}
                </div>
              )}
              <div className="flex items-center gap-2 text-slate-500">
                <Globe className="h-3.5 w-3.5 text-slate-400" />
                <span className="font-semibold text-slate-700">Alamat IP:</span>
                <span className="font-mono text-slate-900">{data.ip || 'N/A'}</span>
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center gap-2 text-slate-500">
                <ArrowRightLeft className="h-3.5 w-3.5 text-slate-400" />
                <span className="font-semibold text-slate-700">Entitas Sasaran:</span>
                <span className="font-medium text-slate-900">
                  {data.entity || 'N/A'} {data.entity_id ? `(#${data.entity_id})` : ''}
                </span>
              </div>
              <div className="flex items-center gap-2 text-slate-500">
                <FileClock className="h-3.5 w-3.5 text-slate-400" />
                <span className="font-semibold text-slate-700">Waktu Kejadian:</span>
                <span className="text-slate-900 font-medium">{formatTanggalIndo(data.created_at)}</span>
              </div>
              <div className="flex items-start gap-2 text-slate-500">
                <Laptop className="h-3.5 w-3.5 text-slate-400 shrink-0 mt-0.5" />
                <span className="font-semibold text-slate-700 shrink-0">User Agent:</span>
                <span className="font-mono text-[11px] text-slate-600 truncate" title={data.user_agent || ''}>
                  {data.user_agent || 'N/A'}
                </span>
              </div>
            </div>
          </div>

          {/* Diff Viewer: Old Values vs New Values */}
          <div className="space-y-3">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">
              Perubahan Data (State Difference)
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Nilai Sebelum */}
              <div className="rounded-lg border border-slate-200 overflow-hidden">
                <div className="bg-slate-100/80 px-3 py-2 border-b border-slate-200 text-xs font-semibold text-slate-700 flex items-center justify-between">
                  <span>Nilai Sebelum (Old Values)</span>
                  <Badge variant="outline" className="text-[10px] bg-white">
                    {data.old_values ? 'Tersedia' : 'Kosong'}
                  </Badge>
                </div>
                <div className="p-3 bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto max-h-[220px]">
                  {data.old_values ? (
                    <pre>{JSON.stringify(data.old_values, null, 2)}</pre>
                  ) : (
                    <span className="text-slate-500 italic">Tidak ada data sebelumnya (pembuatan baru)</span>
                  )}
                </div>
              </div>

              {/* Nilai Sesudah */}
              <div className="rounded-lg border border-slate-200 overflow-hidden">
                <div className="bg-slate-100/80 px-3 py-2 border-b border-slate-200 text-xs font-semibold text-slate-700 flex items-center justify-between">
                  <span>Nilai Sesudah (New Values)</span>
                  <Badge variant="outline" className="text-[10px] bg-white">
                    {data.new_values ? 'Tersedia' : 'Kosong'}
                  </Badge>
                </div>
                <div className="p-3 bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto max-h-[220px]">
                  {data.new_values ? (
                    <pre>{JSON.stringify(data.new_values, null, 2)}</pre>
                  ) : (
                    <span className="text-slate-500 italic">Tidak ada nilai sesudah (penghapusan data)</span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Footer Modal */}
        <div className="flex items-center justify-end border-t border-slate-100 bg-slate-50/50 px-6 py-3.5">
          <Button variant="outline" onClick={onClose} className="text-xs">
            Tutup
          </Button>
        </div>
      </div>
    </div>
  );
}
