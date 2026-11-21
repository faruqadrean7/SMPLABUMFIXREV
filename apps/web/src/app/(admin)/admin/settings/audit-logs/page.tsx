'use client';

import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { auditLogsApi } from '@/features/settings/audit-logs-api';
import { AuditLogItem, AuditLogDetail } from '@/types/audit-log';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { AuditLogDetailModal } from '@/features/settings/audit-log-detail-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PermissionGate } from '@/components/shared/permission-gate';
import { formatTanggalIndo } from '@/lib/utils';
import { FileClock, Eye, User, Globe, ShieldAlert } from 'lucide-react';

export default function AuditLogsPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [selectedLog, setSelectedLog] = useState<AuditLogDetail | null>(null);

  // 1. Query Audit Logs
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['audit-logs', page, search],
    queryFn: () => auditLogsApi.list({ page, limit: 15, q: search }),
  });

  const getActionBadge = (action: string) => {
    let style = 'bg-slate-100 text-slate-700 border-slate-300';
    if (action.includes('created') || action.includes('create')) {
      style = 'bg-emerald-50 text-emerald-700 border-emerald-200';
    } else if (action.includes('updated') || action.includes('update') || action.includes('assigned')) {
      style = 'bg-blue-50 text-blue-700 border-blue-200';
    } else if (action.includes('deleted') || action.includes('delete') || action.includes('revoked')) {
      style = 'bg-rose-50 text-rose-700 border-rose-200';
    } else if (action.includes('login') || action.includes('auth')) {
      style = 'bg-amber-50 text-amber-700 border-amber-200';
    }
    return (
      <Badge variant="outline" className={`font-mono text-[11px] ${style}`}>
        {action}
      </Badge>
    );
  };

  const columns: ColumnDef<AuditLogItem>[] = [
    {
      key: 'created_at',
      header: 'Waktu Peristiwa',
      render: (item: AuditLogItem) => (
        <div className="space-y-0.5">
          <div className="font-semibold text-slate-800 text-xs">
            {formatTanggalIndo(item.created_at)}
          </div>
          <div className="font-mono text-[10px] text-slate-400">ID: {item.id}</div>
        </div>
      ),
    },
    {
      key: 'actor',
      header: 'Aktor (Pengguna)',
      render: (item: AuditLogItem) => (
        <div className="flex items-center gap-2">
          <div className="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-600 text-xs shrink-0">
            <User className="h-3.5 w-3.5" />
          </div>
          <div>
            <div className="font-semibold text-slate-900 text-xs">
              {item.actor?.username || item.actor_id || 'Sistem'}
            </div>
            {item.actor?.email && (
              <div className="text-[10px] text-slate-400 truncate max-w-[150px]">
                {item.actor.email}
              </div>
            )}
          </div>
        </div>
      ),
    },
    {
      key: 'action',
      header: 'Aksi Keamanan',
      render: (item: AuditLogItem) => getActionBadge(item.action),
    },
    {
      key: 'entity',
      header: 'Entitas Sasaran',
      render: (item: AuditLogItem) => (
        <div className="text-xs">
          <span className="font-medium text-slate-800">{item.entity || '-'}</span>
          {item.entity_id && (
            <span className="font-mono text-[10px] text-slate-400 block truncate max-w-[140px]">
              #{item.entity_id}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'ip',
      header: 'Alamat IP',
      render: (item: AuditLogItem) => (
        <div className="flex items-center gap-1 font-mono text-[11px] text-slate-600">
          <Globe className="h-3 w-3 text-slate-400" />
          <span>{item.ip || 'N/A'}</span>
        </div>
      ),
    },
    {
      key: 'actions',
      header: 'Aksi',
      align: 'right',
      render: (item: AuditLogItem) => (
        <div className="text-right">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => setSelectedLog(item)}
            className="h-8 px-2.5 text-xs text-brand-blue hover:bg-blue-50"
          >
            <Eye className="h-3.5 w-3.5 mr-1" /> Inspeksi
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PermissionGate permission="audit.read" fallback={null}>
      <div className="space-y-6">
        {/* Header Halaman */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <FileClock className="h-6 w-6 text-brand-blue" />
              <h1 className="text-xl font-bold tracking-tight text-slate-900">
                Audit Log Sistem Imutabel
              </h1>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Catatan jejak aktivitas keamanan, perubahan hak akses, dan transaksi data yang tidak dapat diubah (append-only).
            </p>
          </div>
        </div>

        {/* Security Note */}
        <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-3.5 text-xs text-slate-600 shadow-sm">
          <ShieldAlert className="h-4 w-4 shrink-0 text-brand-blue mt-0.5" />
          <div>
            <span className="font-semibold text-slate-800">Integritas Log Forensik:</span> Seluruh operasi penting
            termasuk alokasi peran pengguna, pembuatan master data, login, dan aksi administratif terekam otomatis
            bersama IP origin dan diff payload.
          </div>
        </div>

        {/* Tabel Data Audit Logs */}
        <DataTable<AuditLogItem>
          columns={columns}
          data={data?.data || []}
          keyExtractor={(item) => item.id}
          isLoading={isLoading}
          error={isError ? (error instanceof Error ? error.message : 'Gagal memuat log audit') : null}
          onRetry={() => refetch()}
          searchable={true}
          searchValue={search}
          onSearchChange={(q) => {
            setSearch(q);
            setPage(1);
          }}
          searchPlaceholder="Cari aksi, entitas, username, atau IP..."
          pagination={
            data?.meta
              ? {
                  page: data.meta.page,
                  limit: data.meta.limit,
                  total: data.meta.total,
                  totalPages: data.meta.totalPages,
                }
              : undefined
          }
          onPageChange={(p) => setPage(p)}
          emptyTitle="Belum Ada Log Audit"
          emptyDescription="Belum ada catatan aktivitas keamanan yang terekam pada sistem."
        />

        {/* Modal Inspeksi Detail Log */}
        <AuditLogDetailModal
          isOpen={!!selectedLog}
          onClose={() => setSelectedLog(null)}
          data={selectedLog}
        />
      </div>
    </PermissionGate>
  );
}
