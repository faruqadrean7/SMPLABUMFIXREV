'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import { usersApi } from '@/features/settings/users-api';
import { rbacApi } from '@/features/settings/rbac-api';
import {
  UserManagementItem,
  CreateUserPayload,
  UpdateUserPayload,
  UserStatus,
} from '@/types/user-management';
import { AssignUserRolesPayload } from '@/types/rbac';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { UserRoleDialog } from '@/features/settings/user-role-dialog';
import { UserDialog } from '@/features/settings/user-dialog';
import { UserStatusDialog } from '@/features/settings/user-status-dialog';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PermissionGate } from '@/components/shared/permission-gate';
import { formatTanggalIndo } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/errors';
import {
  Users,
  UserPlus,
  UserCheck,
  Key,
  Shield,
  Edit2,
  Lock,
  Power,
  AlertCircle,
  Copy,
  Check,
} from 'lucide-react';

export default function UsersManagementPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Dialog State
  const [userDialogOpen, setUserDialogOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<UserManagementItem | null>(null);

  const [roleDialogUser, setRoleDialogUser] = useState<UserManagementItem | null>(null);
  const [statusDialogUser, setStatusDialogUser] = useState<UserManagementItem | null>(null);
  const [resetPasswordUser, setResetPasswordUser] = useState<UserManagementItem | null>(null);

  // Result Dialog State (untuk menampilkan temporary password aman)
  const [tempPasswordResult, setTempPasswordResult] = useState<{
    username: string;
    temporaryPassword?: string | null;
  } | null>(null);
  const [copied, setCopied] = useState(false);

  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  // 1. Query Roles (for filters and role assignment)
  const { data: rolesData } = useQuery({
    queryKey: ['all-roles-lookup'],
    queryFn: () => rbacApi.listRoles({ limit: 100 }),
  });

  // 2. Query Users
  const {
    data,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery({
    queryKey: ['users-list', page, search, roleFilter, statusFilter],
    queryFn: () =>
      usersApi.list({
        page,
        limit: 15,
        q: search,
        role: roleFilter || undefined,
        status: statusFilter || undefined,
      }),
  });

  // 3. Mutasi Buat Pengguna
  const createMutation = useMutation({
    mutationFn: (payload: CreateUserPayload) => usersApi.create(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['users-list'] });
      setUserDialogOpen(false);
      setActionSuccess(`Akun "${res.user.username}" berhasil dibuat.`);
      setActionError(null);
      if (res.temporary_password) {
        setTempPasswordResult({
          username: res.user.username,
          temporaryPassword: res.temporary_password,
        });
      }
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal membuat akun pengguna';
      setActionError(msg);
    },
  });

  // 4. Mutasi Ubah Pengguna
  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateUserPayload }) =>
      usersApi.update(id, payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['users-list'] });
      setUserDialogOpen(false);
      setEditingUser(null);
      setActionSuccess(`Data akun "${res.username}" berhasil diperbarui.`);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memperbarui data pengguna';
      setActionError(msg);
    },
  });

  // 5. Mutasi Tetapkan Role Pengguna
  const assignRolesMutation = useMutation({
    mutationFn: ({ userId, payload }: { userId: string; payload: AssignUserRolesPayload }) =>
      rbacApi.assignUserRoles(userId, payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['users-list'] });
      setRoleDialogUser(null);
      setActionSuccess(res.message || 'Peran pengguna berhasil diperbarui.');
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memperbarui peran pengguna';
      setActionError(msg);
    },
  });

  // 6. Mutasi Ubah Status Akun
  const changeStatusMutation = useMutation({
    mutationFn: ({ userId, status, reason }: { userId: string; status: UserStatus; reason?: string }) =>
      usersApi.changeStatus(userId, status, reason),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['users-list'] });
      setStatusDialogUser(null);
      setActionSuccess(`Status akun "${res.username}" diubah menjadi ${res.status}.`);
      setActionError(null);
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal mengubah status akun';
      setActionError(msg);
    },
  });

  // 7. Mutasi Reset Password
  const resetPasswordMutation = useMutation({
    mutationFn: (userId: string) =>
      usersApi.resetPassword(userId, { must_change_password: true }),
    onSuccess: (res) => {
      const username = resetPasswordUser?.username || 'pengguna';
      setResetPasswordUser(null);
      queryClient.invalidateQueries({ queryKey: ['users-list'] });
      setActionSuccess(res.message || 'Password berhasil di-reset.');
      setActionError(null);
      if (res.temporary_password) {
        setTempPasswordResult({
          username,
          temporaryPassword: res.temporary_password,
        });
      }
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal me-reset password akun';
      setActionError(msg);
    },
  });

  const handleOpenEdit = (user: UserManagementItem) => {
    setEditingUser(user);
    setUserDialogOpen(true);
  };

  const handleUserDialogSubmit = async (payload: CreateUserPayload | UpdateUserPayload) => {
    if (editingUser) {
      await updateMutation.mutateAsync({ id: editingUser.id, payload: payload as UpdateUserPayload });
    } else {
      await createMutation.mutateAsync(payload as CreateUserPayload);
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return (
          <Badge variant="outline" className="text-xs bg-emerald-50 text-emerald-700 border-emerald-300">
            Aktif
          </Badge>
        );
      case 'suspended':
        return (
          <Badge variant="outline" className="text-xs bg-red-50 text-red-700 border-red-300">
            Ditangguhkan
          </Badge>
        );
      case 'inactive':
      default:
        return (
          <Badge variant="outline" className="text-xs bg-slate-100 text-slate-600 border-slate-300">
            Nonaktif
          </Badge>
        );
    }
  };

  const columns: ColumnDef<UserManagementItem>[] = [
    {
      key: 'username',
      header: 'Pengguna',
      render: (item: UserManagementItem) => (
        <div className="flex items-center gap-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-brand-blue font-bold text-xs shrink-0">
            {item.username.charAt(0).toUpperCase()}
          </div>
          <div>
            <div className="font-semibold text-slate-900">{item.username}</div>
            <div className="text-[11px] text-slate-400">
              {item.email || item.phone || 'Tanpa kontak terdaftar'}
            </div>
          </div>
        </div>
      ),
    },
    {
      key: 'roles',
      header: 'Peran (Roles)',
      render: (item: UserManagementItem) => (
        <div className="flex flex-wrap gap-1 max-w-[220px]">
          {item.roles && item.roles.length > 0 ? (
            item.roles.map((r) => (
              <Badge key={r.id || r.code} variant="outline" className="text-[10px] bg-slate-50 font-mono">
                {r.name || r.code}
              </Badge>
            ))
          ) : (
            <span className="text-xs text-slate-400 italic">Tanpa peran</span>
          )}
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status Akun',
      render: (item: UserManagementItem) => getStatusBadge(item.status),
    },
    {
      key: 'last_login',
      header: 'Login Terakhir',
      render: (item: UserManagementItem) => (
        <div className="text-xs text-slate-600">
          {item.last_login_at ? formatTanggalIndo(item.last_login_at) : 'Belum pernah login'}
        </div>
      ),
    },
    {
      key: 'actions',
      header: 'Aksi',
      align: 'right',
      render: (item: UserManagementItem) => (
        <div className="flex items-center justify-end gap-1">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => handleOpenEdit(item)}
            className="h-8 px-2 text-xs text-slate-600 hover:text-brand-blue hover:bg-blue-50"
            title="Edit Profil Akun"
          >
            <Edit2 className="h-3.5 w-3.5 mr-1" /> Edit
          </Button>

          <Button
            size="sm"
            variant="ghost"
            onClick={() => setRoleDialogUser(item)}
            className="h-8 px-2 text-xs text-brand-blue hover:bg-blue-50"
            title="Atur Peran Akun"
          >
            <Key className="h-3.5 w-3.5 mr-1" /> Peran
          </Button>

          <Button
            size="sm"
            variant="ghost"
            onClick={() => setStatusDialogUser(item)}
            className="h-8 px-2 text-xs text-slate-600 hover:text-slate-900 hover:bg-slate-100"
            title="Ubah Status Akun"
          >
            <Power className="h-3.5 w-3.5 mr-1" /> Status
          </Button>

          <Button
            size="sm"
            variant="ghost"
            onClick={() => setResetPasswordUser(item)}
            className="h-8 px-2 text-xs text-amber-600 hover:bg-amber-50"
            title="Reset Password Akun"
          >
            <Lock className="h-3.5 w-3.5 mr-1" /> Reset
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PermissionGate permission="auth.user.read" fallback={null}>
      <div className="space-y-6">
        {/* Header Halaman */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <Users className="h-6 w-6 text-brand-blue" />
              <h1 className="text-xl font-bold tracking-tight text-slate-900">
                Manajemen Akun Pengguna
              </h1>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Daftar seluruh akun pengguna sistem, pengaturan peran ganda (multi-role), dan kontrol keamanan akun.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Link href="/admin/settings/roles">
              <Button variant="outline" className="text-xs gap-1.5 border-slate-300">
                <Shield className="h-3.5 w-3.5 text-brand-blue" /> Kelola Matriks Role
              </Button>
            </Link>

            <Button
              onClick={() => {
                setEditingUser(null);
                setUserDialogOpen(true);
              }}
              className="bg-brand-blue hover:bg-brand-dark text-white text-xs font-semibold gap-1.5"
            >
              <UserPlus className="h-4 w-4" /> Tambah Pengguna
            </Button>
          </div>
        </div>

        {/* Global Feedback Banner */}
        {actionSuccess && (
          <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3.5 text-xs text-emerald-800">
            <UserCheck className="h-4 w-4 shrink-0 text-emerald-600" />
            <div className="flex-1 font-medium">{actionSuccess}</div>
            <button
              onClick={() => setActionSuccess(null)}
              className="font-bold hover:underline text-emerald-900 ml-2"
            >
              Tutup
            </button>
          </div>
        )}

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

        {/* Temporary Password Notification Modal */}
        {tempPasswordResult && (
          <div className="rounded-xl border border-blue-200 bg-blue-50/80 p-5 space-y-3">
            <div className="flex items-start justify-between">
              <div>
                <h3 className="text-sm font-bold text-blue-900">
                  Password Sementara Dihasilkan untuk {tempPasswordResult.username}
                </h3>
                <p className="text-xs text-blue-800 mt-0.5">
                  Salin password ini dan serahkan kepada pengguna. Pengguna wajib mengubah password pada saat login pertama kali.
                </p>
              </div>
              <button
                onClick={() => setTempPasswordResult(null)}
                className="text-xs font-semibold text-blue-700 hover:underline"
              >
                Tutup
              </button>
            </div>
            <div className="flex items-center gap-2 max-w-md">
              <input
                readOnly
                value={tempPasswordResult.temporaryPassword || ''}
                className="font-mono text-sm px-3 py-2 bg-white rounded-lg border border-blue-300 w-full text-slate-900"
              />
              <Button
                size="sm"
                variant="outline"
                onClick={() => copyToClipboard(tempPasswordResult.temporaryPassword || '')}
                className="gap-1.5 shrink-0 text-xs"
              >
                {copied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
                {copied ? 'Tersalin' : 'Salin'}
              </Button>
            </div>
          </div>
        )}

        {/* Filter Controls */}
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
          <div className="flex-1">
            <input
              type="text"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              placeholder="Cari username, email, atau nomor HP..."
              className="w-full h-9 px-3 rounded-lg border border-slate-300 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-brand-blue"
            />
          </div>

          <div className="flex items-center gap-2">
            <select
              value={roleFilter}
              onChange={(e) => {
                setRoleFilter(e.target.value);
                setPage(1);
              }}
              className="h-9 px-2.5 rounded-lg border border-slate-300 text-xs bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-blue"
            >
              <option value="">Semua Peran</option>
              {rolesData?.data.map((r) => (
                <option key={r.code} value={r.code}>
                  {r.name}
                </option>
              ))}
            </select>

            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="h-9 px-2.5 rounded-lg border border-slate-300 text-xs bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-blue"
            >
              <option value="">Semua Status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
              <option value="suspended">Ditangguhkan</option>
            </select>
          </div>
        </div>

        {/* Tabel Data Users */}
        <DataTable<UserManagementItem>
          columns={columns}
          data={data?.data || []}
          keyExtractor={(item) => item.id}
          isLoading={isLoading}
          error={isError ? (error instanceof Error ? error.message : 'Gagal memuat pengguna') : null}
          onRetry={() => refetch()}
          searchable={false}
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
          emptyTitle="Belum Ada Akun Pengguna"
          emptyDescription="Tidak ditemukan akun pengguna yang cocok dengan kriteria pencarian."
        />

        {/* Dialog Tambah / Edit Pengguna (ADM-USR-02) */}
        <UserDialog
          isOpen={userDialogOpen}
          onClose={() => {
            setUserDialogOpen(false);
            setEditingUser(null);
          }}
          onSubmit={handleUserDialogSubmit}
          initialData={editingUser}
          availableRoles={rolesData?.data || []}
          isLoading={createMutation.isPending || updateMutation.isPending}
        />

        {/* Dialog Ubah Status Akun (ADM-USR-04) */}
        <UserStatusDialog
          isOpen={!!statusDialogUser}
          onClose={() => setStatusDialogUser(null)}
          onSubmit={async (status, reason) => {
            if (statusDialogUser) {
              await changeStatusMutation.mutateAsync({
                userId: statusDialogUser.id,
                status,
                reason,
              });
            }
          }}
          user={statusDialogUser}
          isLoading={changeStatusMutation.isPending}
        />

        {/* Dialog Penetapan Role Pengguna (ADM-USR-03) */}
        {roleDialogUser && (
          <UserRoleDialog
            isOpen={!!roleDialogUser}
            onClose={() => setRoleDialogUser(null)}
            onSubmit={async (payload) => {
              await assignRolesMutation.mutateAsync({
                userId: roleDialogUser.id,
                payload,
              });
            }}
            userId={roleDialogUser.id}
            username={roleDialogUser.username}
            currentRoleCodes={roleDialogUser.roles?.map((r) => r.code) || []}
            allRoles={rolesData?.data || []}
            isLoading={assignRolesMutation.isPending}
          />
        )}

        {/* Dialog Konfirmasi Reset Password (ADM-USR-04) */}
        <ConfirmDialog
          isOpen={!!resetPasswordUser}
          onClose={() => setResetPasswordUser(null)}
          onConfirm={() => {
            if (resetPasswordUser) {
              resetPasswordMutation.mutate(resetPasswordUser.id);
            }
          }}
          title="Reset Password Akun"
          description={`Apakah Anda yakin ingin me-reset password akun "${resetPasswordUser?.username}"? Seluruh sesi aktif akan dicabut dan pengguna akan diminta mengubah password pada saat login berikutnya.`}
          confirmLabel="Reset Password"
          variant="warning"
          isLoading={resetPasswordMutation.isPending}
        />
      </div>
    </PermissionGate>
  );
}
