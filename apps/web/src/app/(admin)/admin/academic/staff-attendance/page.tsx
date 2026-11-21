'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { academicApi } from '@/features/academic/academic-api';
import {
  StaffAttendanceSelfDialog,
  StaffAttendanceManualDialog,
  StaffAttendanceCorrectDialog,
  StaffAttendanceSettingsDialog,
} from '@/features/academic/staff-attendance';
import {
  StaffAttendanceItem,
  StaffAttendanceStatus,
  STAFF_ATTENDANCE_STATUS_LABELS,
  STAFF_ATTENDANCE_STATUS_COLORS,
} from '@/types/academic';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { useAuth } from '@/providers/auth-provider';
import { hasPermission } from '@/lib/rbac/evaluator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  UserCheck,
  Calendar,
  Clock,
  MapPin,
  Camera,
  Edit3,
  PlusCircle,
  Settings,
  RotateCw,
  Search,
  Filter,
  CheckCircle2,
  AlertTriangle,
  Users,
  ShieldCheck,
  Percent,
  FileText,
  User,
} from 'lucide-react';

export default function StaffAttendancePage() {
  const queryClient = useQueryClient();
  const { user } = useAuth();

  // Evaluasi Hak Akses Admin (TU / Kurikulum / Super Admin / Kepsek)
  const isAdmin = useMemo(() => {
    if (!user) return false;
    const roles = Array.isArray(user.roles) ? user.roles : [];
    return (
      roles.includes('super_admin') ||
      roles.includes('admin_tu') ||
      roles.includes('principal') ||
      hasPermission(user, 'master.teacher.manage')
    );
  }, [user]);

  // Tab State: 'semua' | 'saya' | 'rekap'
  const [activeTab, setActiveTab] = useState<'semua' | 'saya' | 'rekap'>('semua');

  // Filter States
  const [search, setSearch] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [dateFrom, setDateFrom] = useState<string>('');
  const [dateTo, setDateTo] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const perPage = 15;

  // Dialog States
  const [selfDialogOpen, setSelfDialogOpen] = useState<boolean>(false);
  const [manualDialogOpen, setManualDialogOpen] = useState<boolean>(false);
  const [correctDialogOpen, setCorrectDialogOpen] = useState<boolean>(false);
  const [settingsDialogOpen, setSettingsDialogOpen] = useState<boolean>(false);
  const [selectedRecord, setSelectedRecord] = useState<StaffAttendanceItem | null>(null);

  // Fetch Summary Statistics
  const {
    data: summaryData,
    isLoading: loadingSummary,
    refetch: refetchSummary,
  } = useQuery({
    queryKey: ['staff-attendance-summary', activeTab, dateFrom, dateTo, user?.id],
    queryFn: () =>
      academicApi.getStaffAttendanceSummary({
        user_id: activeTab === 'saya' ? user?.id : undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      }),
  });

  // Fetch Attendance List
  const {
    data: listData,
    isLoading: loadingList,
    error: listError,
    refetch: refetchList,
  } = useQuery({
    queryKey: [
      'staff-attendances-list',
      activeTab,
      search,
      statusFilter,
      dateFrom,
      dateTo,
      page,
      user?.id,
    ],
    queryFn: () =>
      academicApi.listStaffAttendances({
        user_id: activeTab === 'saya' ? user?.id : undefined,
        q: search.trim() || undefined,
        status: (statusFilter as StaffAttendanceStatus) || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        page,
        per_page: perPage,
      }),
  });

  const handleRefreshAll = () => {
    refetchSummary();
    refetchList();
  };

  const handleOpenCorrect = (record: StaffAttendanceItem) => {
    setSelectedRecord(record);
    setCorrectDialogOpen(true);
  };

  const handleResetFilters = () => {
    setSearch('');
    setStatusFilter('');
    setDateFrom('');
    setDateTo('');
    setPage(1);
  };

  // Helper formatting jam
  const formatTime = (isoString?: string | null) => {
    if (!isoString) return '-';
    try {
      const d = new Date(isoString);
      return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    } catch {
      return isoString;
    }
  };

  // Helper formatting tanggal
  const formatDateIndo = (dateStr?: string | null) => {
    if (!dateStr) return '-';
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  // Table Columns Definition
  const columns: ColumnDef<StaffAttendanceItem>[] = useMemo(() => {
    return [
      {
        key: 'employee',
        header: 'Guru / Tenaga Kependidikan',
        render: (item) => {
          const teacher = item.user?.teacher;
          const staff = item.user?.staff;
          const name = teacher?.full_name || staff?.full_name || item.user?.username || 'Pegawai';
          const nip = teacher?.nip || staff?.nip;
          const subtitle = nip
            ? `NIP: ${nip}`
            : staff?.position || item.user?.email || '-';

          return (
            <div className="flex items-center gap-2.5">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold shrink-0">
                {name.charAt(0).toUpperCase()}
              </div>
              <div className="min-w-0">
                <span className="font-semibold text-gray-900 block truncate text-xs">
                  {name}
                </span>
                <span className="text-[11px] text-gray-500 block truncate">{subtitle}</span>
              </div>
            </div>
          );
        },
      },
      {
        key: 'date_time',
        header: 'Tanggal & Waktu',
        render: (item) => (
          <div>
            <span className="font-medium text-gray-900 block text-xs">
              {formatDateIndo(item.att_date)}
            </span>
            <div className="flex items-center gap-2 text-[11px] text-gray-500 mt-0.5">
              <span>Masuk: <strong className="text-gray-700">{formatTime(item.check_in)}</strong></span>
              <span>•</span>
              <span>Pulang: <strong className="text-gray-700">{formatTime(item.check_out)}</strong></span>
            </div>
          </div>
        ),
      },
      {
        key: 'status',
        header: 'Status',
        render: (item) => {
          const color = STAFF_ATTENDANCE_STATUS_COLORS[item.status] || {
            badge: 'bg-gray-100 text-gray-700 border-gray-200',
          };
          const label = STAFF_ATTENDANCE_STATUS_LABELS[item.status] || item.status;
          return (
            <span
              className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${color.badge}`}
            >
              {label}
            </span>
          );
        },
      },
      {
        key: 'verification',
        header: 'Verifikasi & Catatan',
        render: (item) => {
          const hasGps = item.lat !== null && item.lat !== undefined && item.lng !== null && item.lng !== undefined;
          const hasPhoto = !!item.photo_file_id;

          return (
            <div className="space-y-1 text-xs">
              <div className="flex items-center gap-2">
                {hasGps ? (
                  <span
                    className="inline-flex items-center gap-1 text-[11px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100"
                    title={`Lat: ${item.lat}, Lng: ${item.lng}`}
                  >
                    <MapPin className="h-3 w-3" />
                    GPS Terkunci
                  </span>
                ) : (
                  <span className="text-[11px] text-gray-400">Tanpa GPS</span>
                )}

                {hasPhoto && (
                  <span
                    className="inline-flex items-center gap-1 text-[11px] text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-100"
                    title="Swafoto kehadiran terlampir"
                  >
                    <Camera className="h-3 w-3" />
                    Swafoto
                  </span>
                )}
              </div>

              {item.notes && (
                <p className="text-[11px] text-gray-600 line-clamp-1 italic">
                  &ldquo;{item.notes}&rdquo;
                </p>
              )}
            </div>
          );
        },
      },
      {
        key: 'actions',
        header: 'Aksi',
        align: 'right',
        render: (item) => {
          return (
            <div className="flex items-center justify-end gap-1.5">
              {isAdmin && (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => handleOpenCorrect(item)}
                  className="h-7 px-2 text-xs text-amber-700 border-amber-200 hover:bg-amber-50 gap-1"
                  title="Koreksi presensi pegawai"
                >
                  <Edit3 className="h-3.5 w-3.5" />
                  <span>Koreksi</span>
                </Button>
              )}
            </div>
          );
        },
      },
    ];
  }, [isAdmin]);

  const summary = summaryData?.summary;
  const attendanceRate = summaryData?.attendance_percentage ?? 0;
  const totalDays = summaryData?.total_recorded_days ?? 0;

  return (
    <div className="space-y-6">
      {/* Top Banner / Header */}
      <div className="rounded-xl border border-emerald-100 bg-gradient-to-r from-emerald-500/10 via-teal-500/5 to-transparent p-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-3">
            <div className="rounded-xl bg-emerald-600 p-2.5 text-white shadow-sm">
              <UserCheck className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-xl font-bold tracking-tight text-gray-900">
                Presensi Guru & Tenaga Kependidikan (PTK)
              </h1>
              <p className="text-xs text-gray-600 mt-0.5">
                Pencatatan presensi mandiri geofencing, pemantauan ketepatan waktu, dan rekapitulasi kehadiran Labschool UM
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {/* Tombol Presensi Mandiri */}
            <Button
              onClick={() => setSelfDialogOpen(true)}
              size="sm"
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5 shadow-sm"
            >
              <UserCheck className="h-4 w-4" />
              <span>Presensi Saya Hari Ini</span>
            </Button>

            {/* Tombol Entri Manual (Admin TU) */}
            {isAdmin && (
              <Button
                onClick={() => setManualDialogOpen(true)}
                size="sm"
                variant="outline"
                className="text-xs gap-1.5 border-purple-200 text-purple-700 hover:bg-purple-50"
              >
                <PlusCircle className="h-4 w-4" />
                <span>Entri Manual</span>
              </Button>
            )}

            {/* Tombol Pengaturan GPS (Admin) */}
            {isAdmin && (
              <Button
                onClick={() => setSettingsDialogOpen(true)}
                size="sm"
                variant="outline"
                className="text-xs gap-1.5 border-gray-200 text-gray-700 hover:bg-gray-100"
                title="Pengaturan Geofencing & Jam Masuk"
              >
                <Settings className="h-4 w-4" />
                <span>Pengaturan</span>
              </Button>
            )}

            {/* Tombol Refresh */}
            <Button
              onClick={handleRefreshAll}
              size="sm"
              variant="outline"
              className="text-xs border-gray-200 text-gray-700 hover:bg-gray-100"
              title="Perbarui data"
            >
              <RotateCw className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      {/* KPI Summary Cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-gray-500">
            <span className="text-xs font-medium">Total Rekor</span>
            <Users className="h-4 w-4 text-gray-400" />
          </div>
          <div className="mt-2 text-2xl font-bold text-gray-900">
            {loadingSummary ? '...' : totalDays}
          </div>
          <span className="text-[11px] text-gray-500">Hari presensi</span>
        </div>

        <div className="rounded-xl border border-emerald-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-emerald-600">
            <span className="text-xs font-medium">Tepat Waktu</span>
            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-emerald-700">
            {loadingSummary ? '...' : summary?.hadir ?? 0}
          </div>
          <span className="text-[11px] text-emerald-600">Sebelum batas jam</span>
        </div>

        <div className="rounded-xl border border-amber-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-amber-600">
            <span className="text-xs font-medium">Terlambat</span>
            <Clock className="h-4 w-4 text-amber-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-amber-700">
            {loadingSummary ? '...' : summary?.terlambat ?? 0}
          </div>
          <span className="text-[11px] text-amber-600">Lewat batas jam</span>
        </div>

        <div className="rounded-xl border border-blue-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-blue-600">
            <span className="text-xs font-medium">Izin</span>
            <FileText className="h-4 w-4 text-blue-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-blue-700">
            {loadingSummary ? '...' : summary?.izin ?? 0}
          </div>
          <span className="text-[11px] text-blue-600">Dispensasi resmi</span>
        </div>

        <div className="rounded-xl border border-purple-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-purple-600">
            <span className="text-xs font-medium">Sakit</span>
            <AlertTriangle className="h-4 w-4 text-purple-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-purple-700">
            {loadingSummary ? '...' : summary?.sakit ?? 0}
          </div>
          <span className="text-[11px] text-purple-600">Keterangan medis</span>
        </div>

        <div className="rounded-xl border border-indigo-100 bg-white p-4 shadow-xs">
          <div className="flex items-center justify-between text-indigo-600">
            <span className="text-xs font-medium">Tingkat Hadir</span>
            <Percent className="h-4 w-4 text-indigo-500" />
          </div>
          <div className="mt-2 text-2xl font-bold text-indigo-700">
            {loadingSummary ? '...' : `${attendanceRate}%`}
          </div>
          <span className="text-[11px] text-indigo-600">Hadir + Terlambat</span>
        </div>
      </div>

      {/* Main Tabs Navigation */}
      <div className="border-b border-gray-200">
        <nav className="flex space-x-6" aria-label="Tabs">
          <button
            onClick={() => {
              setActiveTab('semua');
              setPage(1);
            }}
            className={`flex items-center gap-2 border-b-2 py-3 px-1 text-xs font-semibold transition ${
              activeTab === 'semua'
                ? 'border-emerald-600 text-emerald-600'
                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
            }`}
          >
            <Users className="h-4 w-4" />
            <span>Semua Presensi PTK</span>
          </button>

          <button
            onClick={() => {
              setActiveTab('saya');
              setPage(1);
            }}
            className={`flex items-center gap-2 border-b-2 py-3 px-1 text-xs font-semibold transition ${
              activeTab === 'saya'
                ? 'border-emerald-600 text-emerald-600'
                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
            }`}
          >
            <User className="h-4 w-4" />
            <span>Presensi Saya</span>
          </button>

          <button
            onClick={() => setActiveTab('rekap')}
            className={`flex items-center gap-2 border-b-2 py-3 px-1 text-xs font-semibold transition ${
              activeTab === 'rekap'
                ? 'border-emerald-600 text-emerald-600'
                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
            }`}
          >
            <Percent className="h-4 w-4" />
            <span>Rekapitulasi Kehadiran</span>
          </button>
        </nav>
      </div>

      {/* Tab: Rekapitulasi */}
      {activeTab === 'rekap' ? (
        <div className="rounded-xl border border-gray-200 bg-white p-6 space-y-6">
          <div className="flex items-center justify-between border-b pb-4">
            <div>
              <h2 className="text-base font-bold text-gray-900">
                Ringkasan Rekapitulasi Kehadiran PTK
              </h2>
              <p className="text-xs text-gray-500">
                Statistik akumulasi hari kerja, tingkat ketepatan waktu, dan ketidakhadiran
              </p>
            </div>
            <div className="flex items-center gap-3">
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs"
              />
              <span className="text-xs text-gray-500">s/d</span>
              <input
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-4 space-y-3">
              <h3 className="text-xs font-bold text-gray-800">Distribusi Kategori Kehadiran</h3>
              <div className="space-y-2 text-xs">
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Hadir Tepat Waktu</span>
                  <span className="font-bold text-emerald-700">{summary?.hadir ?? 0}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Terlambat</span>
                  <span className="font-bold text-amber-700">{summary?.terlambat ?? 0}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Izin</span>
                  <span className="font-bold text-blue-700">{summary?.izin ?? 0}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Sakit</span>
                  <span className="font-bold text-purple-700">{summary?.sakit ?? 0}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Alfa (Tanpa Keterangan)</span>
                  <span className="font-bold text-rose-700">{summary?.alfa ?? 0}</span>
                </div>
              </div>
            </div>

            <div className="rounded-xl border border-emerald-100 bg-emerald-50/30 p-4 flex flex-col justify-between">
              <div>
                <h3 className="text-xs font-bold text-emerald-900">Persentase Tingkat Kehadiran</h3>
                <p className="text-[11px] text-gray-600 mt-1">
                  Dihitung dari total hari hadir (tepat waktu + terlambat) dibagi total hari pencatatan.
                </p>
              </div>
              <div className="mt-4 flex items-baseline gap-2">
                <span className="text-4xl font-extrabold text-emerald-700">
                  {attendanceRate}%
                </span>
                <span className="text-xs text-gray-500">
                  ({(summary?.hadir ?? 0) + (summary?.terlambat ?? 0)} dari {totalDays} rekor)
                </span>
              </div>
            </div>
          </div>
        </div>
      ) : (
        /* Tab: List (Semua / Saya) */
        <div className="space-y-4">
          {/* Toolbar Filter */}
          <div className="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-2">
              {/* Search */}
              <div className="relative min-w-[220px]">
                <Search className="absolute left-3 top-2.5 h-3.5 w-3.5 text-gray-400" />
                <Input
                  type="text"
                  placeholder="Cari nama, NIP, email..."
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    setPage(1);
                  }}
                  className="pl-8 text-xs h-8"
                />
              </div>

              {/* Status Filter */}
              <select
                value={statusFilter}
                onChange={(e) => {
                  setStatusFilter(e.target.value);
                  setPage(1);
                }}
                className="h-8 rounded-lg border border-gray-300 px-2.5 text-xs text-gray-700 focus:border-emerald-500 focus:outline-none"
              >
                <option value="">Semua Status</option>
                <option value="hadir">Hadir</option>
                <option value="terlambat">Terlambat</option>
                <option value="izin">Izin</option>
                <option value="sakit">Sakit</option>
                <option value="alfa">Alfa</option>
              </select>

              {/* Date From */}
              <div className="flex items-center gap-1.5">
                <span className="text-xs text-gray-500">Dari:</span>
                <Input
                  type="date"
                  value={dateFrom}
                  onChange={(e) => {
                    setDateFrom(e.target.value);
                    setPage(1);
                  }}
                  className="h-8 text-xs w-36"
                />
              </div>

              {/* Date To */}
              <div className="flex items-center gap-1.5">
                <span className="text-xs text-gray-500">s/d:</span>
                <Input
                  type="date"
                  value={dateTo}
                  onChange={(e) => {
                    setDateTo(e.target.value);
                    setPage(1);
                  }}
                  className="h-8 text-xs w-36"
                />
              </div>

              {(search || statusFilter || dateFrom || dateTo) && (
                <Button
                  onClick={handleResetFilters}
                  size="sm"
                  variant="ghost"
                  className="h-8 text-xs text-gray-500 hover:text-gray-700"
                >
                  Reset
                </Button>
              )}
            </div>
          </div>

          {/* DataTable */}
          <DataTable<StaffAttendanceItem>
            columns={columns}
            data={listData?.data || []}
            keyExtractor={(item) => item.id}
            isLoading={loadingList}
            error={listError ? 'Gagal memuat daftar riwayat presensi PTK.' : null}
            onRetry={refetchList}
            pagination={listData?.meta}
            onPageChange={(p) => setPage(p)}
            emptyTitle="Belum ada catatan presensi"
            emptyDescription="Tidak ditemukan rekor kehadiran yang sesuai dengan kriteria filter."
          />
        </div>
      )}

      {/* Dialogs */}
      <StaffAttendanceSelfDialog
        isOpen={selfDialogOpen}
        onClose={() => setSelfDialogOpen(false)}
        onSuccess={() => {
          handleRefreshAll();
        }}
      />

      <StaffAttendanceManualDialog
        isOpen={manualDialogOpen}
        onClose={() => setManualDialogOpen(false)}
        onSuccess={() => {
          handleRefreshAll();
        }}
      />

      <StaffAttendanceCorrectDialog
        isOpen={correctDialogOpen}
        onClose={() => {
          setCorrectDialogOpen(false);
          setSelectedRecord(null);
        }}
        onSuccess={() => {
          handleRefreshAll();
        }}
        attendance={selectedRecord}
      />

      <StaffAttendanceSettingsDialog
        isOpen={settingsDialogOpen}
        onClose={() => setSettingsDialogOpen(false)}
        onSuccess={() => {
          handleRefreshAll();
        }}
      />
    </div>
  );
}
