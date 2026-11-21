'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { rbacApi } from '@/features/settings/rbac-api';
import { RoleItem, RoleDetail, CreateRolePayload, UpdateRolePayload } from '@/types/rbac';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { RoleDialog } from '@/features/settings/role-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PermissionGate } from '@/components/shared/permission-gate';
import { ApiClientError } from '@/lib/api/errors';
import { Shield, Plus, Edit2, Trash2, Key, Users, AlertCircle, Info } from 'lucide-react';

export default function RolesPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');

  // Dialog State
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingRole, setEditingRole] = useState<RoleDetail | null>(null);
  const [deletingRole, setDeletingRole] = useState<RoleItem | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  // 1. Query Master Permissions
  const { data: permissionsData } = useQuery({
    queryKey: ['master-permissions'],
    queryFn: () => rbacApi.listPermissions(),
  });

  // 2. Query Roles
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['roles', page, search],
    queryFn: () => rbacApi.listRoles({ page, limit: 15, q: search }),
  });

  // 3. Mutasi Tambah Role
  const createMutation = useMutation({
    mutationFn: (values: CreateRolePayload) => rbacApi.createRole(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] });
      setDialogOpen(false);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal membuat role baru';
      setActionError(msg);
    },
  });

  // 4. Mutasi Ubah Role
  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: string; values: UpdateRolePayload }) =>
      rbacApi.updateRole(id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] });
      setDialogOpen(false);
      setEditingRole(null);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memperbarui konfigurasi role';
      setActionError(msg);
    },
  });

  // 5. Mutasi Hapus Role
  const deleteMutation = useMutation({
    mutationFn: (id: string) => rbacApi.deleteRole(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['roles'] });
      setDeletingRole(null);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menghapus role';
      setActionError(msg);
    },
  });

  const handleOpenEdit = async (role: RoleItem) => {
    try {
      setActionError(null);
      const detail = await rbacApi.getRole(role.id);
      setEditingRole(detail);
      setDialogOpen(true);
    } catch (err: unknown) {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memuat detail konfigurasi role';
      setActionError(msg);
    }
  };

  const handleDialogSubmit = async (payload: CreateRolePayload | UpdateRolePayload) => {
    if (editingRole) {
      await updateMutation.mutateAsync({ id: editingRole.id, values: payload as UpdateRolePayload });
    } else {
      await createMutation.mutateAsync(payload as CreateRolePayload);
    }
  };

  const columns: ColumnDef<RoleItem>[] = [
    {
      key: 'name',
      header: 'Nama Role',
      render: (item: RoleItem) => (
        <div className="flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-brand-blue font-bold text-xs shrink-0">
            <Shield className="h-4 w-4" />
          </div>
          <div>
            <div className="font-semibold text-slate-900">{item.name}</div>
            <div className="font-mono text-[11px] text-slate-400">ID: {item.id}</div>
          </div>
        </div>
      ),
    },
    {
      key: 'code',
      header: 'Kode Role',
      render: (item: RoleItem) => (
        <span className="font-mono text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
          {item.code}
        </span>
      ),
    },
    {
      key: 'type',
      header: 'Tipe Role',
      render: (item: RoleItem) =>
        item.is_system ? (
          <Badge variant="outline" className="text-xs bg-slate-100 text-slate-700 border-slate-300">
            Sistem (Bawaan)
          </Badge>
        ) : (
          <Badge variant="outline" className="text-xs bg-emerald-50 text-emerald-700 border-emerald-300">
            Kustom (Sekolah)
          </Badge>
        ),
    },
    {
      key: 'permissions_count',
      header: 'Hak Akses',
      render: (item: RoleItem) => (
        <div className="flex items-center gap-1.5 text-xs text-slate-600">
          <Key className="h-3.5 w-3.5 text-amber-500" />
          <span className="font-semibold text-slate-900">{item.permissions_count ?? 0}</span> izin
        </div>
      ),
    },
    {
      key: 'users_count',
      header: 'Pengguna',
      render: (item: RoleItem) => (
        <div className="flex items-center gap-1.5 text-xs text-slate-600">
          <Users className="h-3.5 w-3.5 text-blue-500" />
          <span className="font-semibold text-slate-900">{item.users_count ?? 0}</span> akun
        </div>
      ),
    },
    {
      key: 'actions',
      header: 'Aksi',
      align: 'right',
      render: (item: RoleItem) => (
        <div className="flex items-center justify-end gap-1.5">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => handleOpenEdit(item)}
            className="h-8 px-2 text-slate-600 hover:text-brand-blue hover:bg-blue-50"
            title="Ubah Konfigurasi Hak Akses"
          >
            <Edit2 className="h-3.5 w-3.5 mr-1" /> Edit
          </Button>

          {!item.is_system && (
            <Button
              size="sm"
              variant="ghost"
              onClick={() => setDeletingRole(item)}
              className="h-8 px-2 text-slate-400 hover:text-red-600 hover:bg-red-50"
              title="Hapus Role Kustom"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <PermissionGate permission="auth.role.manage" fallback={null}>
      <div className="space-y-6">
        {/* Header Halaman */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <Shield className="h-6 w-6 text-brand-blue" />
              <h1 className="text-xl font-bold tracking-tight text-slate-900">
                Matriks Role & Hak Akses (RBAC)
              </h1>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Kelola peran pengguna dan izin akses granular untuk seluruh modul sistem portal sekolah.
            </p>
          </div>

          <Button
            onClick={() => {
              setEditingRole(null);
              setDialogOpen(true);
            }}
            className="bg-brand-blue hover:bg-brand-dark text-white text-xs font-semibold gap-1.5 shrink-0"
          >
            <Plus className="h-4 w-4" /> Tambah Role Kustom
          </Button>
        </div>

        {/* Global Action Error Alert */}
        {actionError && (
          <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3.5 text-xs text-red-700">
            <AlertCircle className="h-4 w-4 shrink-0 text-red-500" />
            <div className="flex-1 font-medium">{actionError}</div>
            <button
              onClick={() => setActionError(null)}
              className="font-bold hover:underline text-red-900 ml-2"
            >
              Tutup
            </button>
          </div>
        )}

        {/* Info Box */}
        <div className="flex items-start gap-3 rounded-lg border border-blue-100 bg-blue-50/60 p-3.5 text-xs text-blue-900">
          <Info className="h-4 w-4 shrink-0 text-blue-600 mt-0.5" />
          <div className="space-y-1">
            <span className="font-semibold">Prinsip Keamanan RBAC Deny-by-Default:</span> Setiap akun hanya
            memiliki wewenang sesuai izin yang terasosiasi pada peran aktifnya. Role sistem dilindungi dari
            penghapusan untuk menjamin integritas alur kerja operasional.
          </div>
        </div>

        {/* Tabel Data Role */}
        <DataTable<RoleItem>
          columns={columns}
          data={data?.data || []}
          keyExtractor={(item) => item.id}
          isLoading={isLoading}
          error={isError ? (error instanceof Error ? error.message : 'Gagal memuat daftar role') : null}
          onRetry={() => refetch()}
          searchable={true}
          searchValue={search}
          onSearchChange={(q) => {
            setSearch(q);
            setPage(1);
          }}
          searchPlaceholder="Cari nama atau kode role..."
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
          emptyTitle="Belum Ada Role"
          emptyDescription="Belum ada data peran pengguna yang tersimpan di sistem."
        />

        {/* Dialog Form Tambah / Edit Role */}
        <RoleDialog
          isOpen={dialogOpen}
          onClose={() => {
            setDialogOpen(false);
            setEditingRole(null);
          }}
          onSubmit={handleDialogSubmit}
          initialData={editingRole}
          permissions={permissionsData || []}
          isLoading={createMutation.isPending || updateMutation.isPending}
        />

        {/* Dialog Konfirmasi Hapus Role Kustom */}
        <ConfirmDialog
          isOpen={!!deletingRole}
          onClose={() => setDeletingRole(null)}
          onConfirm={() => {
            if (deletingRole) {
              deleteMutation.mutate(deletingRole.id);
            }
          }}
          title="Hapus Role Kustom"
          description={`Apakah Anda yakin ingin menghapus role "${deletingRole?.name}" (${deletingRole?.code})? Aksi ini akan menghapus asosiasi izin role ini.`}
          confirmLabel="Hapus Role"
          variant="danger"
          isLoading={deleteMutation.isPending}
        />
      </div>
    </PermissionGate>
  );
}
