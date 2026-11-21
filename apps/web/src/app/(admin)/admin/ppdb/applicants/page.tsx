'use client';

import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { ApplicantItem, PPDBWaveItem, PPDBTrackItem } from '@/types/ppdb';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { ApplicantVerificationDialog } from '@/features/ppdb/applicant-verification-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PermissionGate } from '@/components/shared/permission-gate';
import { formatTanggalIndo } from '@/lib/utils';
import {
  FileCheck2,
  Search,
  Filter,
  CheckCircle2,
  Eye,
  RefreshCw,
  School,
  Clock,
  Layers,
} from 'lucide-react';

export default function PPDBApplicantsPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [waveFilter, setWaveFilter] = useState('');
  const [trackFilter, setTrackFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Selected applicant for split verification dialog
  const [verifyingApplicantId, setVerifyingApplicantId] = useState<string | null>(null);

  // 1. Fetch Waves for filter dropdown
  const { data: wavesData } = useQuery({
    queryKey: ['ppdb-waves-lookup-for-applicants'],
    queryFn: () => ppdbApi.listWaves(),
  });

  const waves: PPDBWaveItem[] = Array.isArray(wavesData)
    ? wavesData
    : (wavesData as any)?.data || [];

  // 2. Fetch Tracks for filter dropdown
  const { data: tracksData } = useQuery({
    queryKey: ['ppdb-tracks-lookup-for-applicants', waveFilter],
    queryFn: () => ppdbApi.listTracks({ wave_id: waveFilter || undefined }),
  });

  const tracks: PPDBTrackItem[] = Array.isArray(tracksData)
    ? tracksData
    : (tracksData as any)?.data || [];

  // 3. Fetch Applicants
  const {
    data: applicantsData,
    isLoading,
    isError,
    refetch,
  } = useQuery({
    queryKey: ['ppdb-applicants-list', page, search, waveFilter, trackFilter, statusFilter],
    queryFn: () =>
      ppdbApi.listApplicants({
        page,
        limit: 15,
        search: search || undefined,
        wave_id: waveFilter || undefined,
        track_id: trackFilter || undefined,
        status: statusFilter || undefined,
      }),
  });

  const applicants = applicantsData?.data || [];
  const meta = applicantsData?.meta;

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'verified':
        return <Badge variant="success">TERVERIFIKASI</Badge>;
      case 'revision':
        return <Badge variant="warning">PERLU REVISI</Badge>;
      case 'submitted':
        return <Badge variant="info">MENUNGGU VERIFIKASI</Badge>;
      case 'selected':
        return <Badge variant="success">DITERIMA</Badge>;
      case 'waitlist':
        return <Badge variant="warning">CADANGAN</Badge>;
      case 'rejected':
        return <Badge variant="danger">DITOLAK</Badge>;
      case 'enrolled':
        return <Badge variant="default">TERDAFTAR ULANG</Badge>;
      case 'draft':
        return <Badge variant="outline">DRAF</Badge>;
      default:
        return <Badge variant="outline">{status.toUpperCase()}</Badge>;
    }
  };

  const columns: ColumnDef<ApplicantItem>[] = [
    {
      key: 'reg_no',
      header: 'No. Registrasi',
      render: (item) => (
        <span className="font-mono text-xs font-bold text-gray-800 bg-gray-100 px-2.5 py-1 rounded">
          {item.registration_no}
        </span>
      ),
    },
    {
      key: 'name',
      header: 'Nama Calon Siswa',
      render: (item) => (
        <div>
          <span className="font-semibold text-gray-900 block">{item.full_name}</span>
          <span className="text-xs text-gray-500">
            {item.gender === 'L' ? 'Laki-laki' : 'Perempuan'} • NISN: {item.nisn || '-'}
          </span>
        </div>
      ),
    },
    {
      key: 'track',
      header: 'Gelombang & Jalur',
      render: (item) => (
        <div className="text-xs">
          <span className="font-medium text-emerald-800 block">{item.track?.name || 'Jalur'}</span>
          <span className="text-gray-400">{item.wave?.name || 'Gelombang'}</span>
        </div>
      ),
    },
    {
      key: 'school',
      header: 'Asal Sekolah',
      render: (item) => (
        <span className="text-xs text-gray-700">{item.school_origin || '-'}</span>
      ),
    },
    {
      key: 'date',
      header: 'Tgl Pengajuan',
      render: (item) => (
        <span className="text-xs text-gray-600">
          {item.created_at ? formatTanggalIndo(item.created_at) : '-'}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status Berkas',
      render: (item) => getStatusBadge(item.status),
    },
    {
      key: 'actions',
      header: 'Aksi',
      render: (item) => (
        <Button
          size="sm"
          onClick={() => setVerifyingApplicantId(item.id)}
          className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-8 px-3"
        >
          <FileCheck2 className="mr-1.5 h-3.5 w-3.5" />
          Periksa Berkas
        </Button>
      ),
    },
  ];

  return (
    <PermissionGate permission="ppdb.applicant.verify">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Verifikasi Berkas Calon Siswa
            </h1>
            <p className="text-sm text-gray-500">
              Pemeriksaan berkas pendaftaran, verifikasi dokumen persyaratan, dan evaluasi status pendaftar
            </p>
          </div>
          <Button
            variant="outline"
            onClick={() => refetch()}
            className="flex items-center gap-1.5 text-xs text-gray-600"
          >
            <RefreshCw className="h-3.5 w-3.5" /> Segarkan Data
          </Button>
        </div>

        {/* Filter Bar */}
        <div className="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-4">
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">Pencarian</label>
            <div className="relative">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Cari nama atau No. Reg..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
                className="pl-9 text-xs"
              />
            </div>
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">Gelombang</label>
            <select
              value={waveFilter}
              onChange={(e) => {
                setWaveFilter(e.target.value);
                setTrackFilter('');
                setPage(1);
              }}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Semua Gelombang</option>
              {waves.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name} ({w.status.toUpperCase()})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">Jalur Pendaftaran</label>
            <select
              value={trackFilter}
              onChange={(e) => {
                setTrackFilter(e.target.value);
                setPage(1);
              }}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Semua Jalur</option>
              {tracks.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name} (Kuota: {t.quota})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">Status Pendaftaran</label>
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Semua Status</option>
              <option value="submitted">Menunggu Verifikasi (Submitted)</option>
              <option value="verified">Terverifikasi (Verified)</option>
              <option value="revision">Perlu Revisi (Revision)</option>
              <option value="selected">Diterima (Selected)</option>
              <option value="waitlist">Cadangan (Waitlist)</option>
              <option value="rejected">Ditolak (Rejected)</option>
              <option value="enrolled">Terdaftar Ulang (Enrolled)</option>
              <option value="draft">Draf (Draft)</option>
            </select>
          </div>
        </div>

        {/* Applicants Table */}
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          <DataTable
            columns={columns}
            data={applicants}
            keyExtractor={(item) => item.id}
            isLoading={isLoading}
            emptyTitle="Tidak ada calon siswa yang sesuai dengan kriteria filter."
          />

          {/* Pagination */}
          {meta && meta.totalPages > 1 && (
            <div className="flex items-center justify-between border-t border-gray-200 bg-gray-50/50 px-4 py-3 text-xs text-gray-500">
              <div>
                Halaman <span className="font-semibold text-gray-700">{meta.page}</span> dari{' '}
                <span className="font-semibold text-gray-700">{meta.totalPages}</span> ({meta.total}{' '}
                pendaftar)
              </div>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="text-xs h-7"
                >
                  Sebelumnya
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setPage((p) => Math.min(meta.totalPages, p + 1))}
                  disabled={page >= meta.totalPages}
                  className="text-xs h-7"
                >
                  Selanjutnya
                </Button>
              </div>
            </div>
          )}
        </div>

        {/* Split Verification Dialog */}
        <ApplicantVerificationDialog
          isOpen={!!verifyingApplicantId}
          applicantId={verifyingApplicantId}
          onClose={() => setVerifyingApplicantId(null)}
          onSuccess={() => {
            refetch();
          }}
        />
      </div>
    </PermissionGate>
  );
}
