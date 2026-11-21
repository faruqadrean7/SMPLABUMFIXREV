'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import {
  PPDBWaveItem,
  PPDBTrackItem,
  CreateWavePayload,
  UpdateWavePayload,
  CreateTrackPayload,
  UpdateTrackPayload,
  CreateRequirementPayload,
} from '@/types/ppdb';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { WaveDialog, WaveFormValues } from '@/features/ppdb/wave-dialog';
import { TrackDialog, TrackFormValues } from '@/features/ppdb/track-dialog';
import { RequirementDialog, RequirementFormValues } from '@/features/ppdb/requirement-dialog';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PermissionGate } from '@/components/shared/permission-gate';
import { formatTanggalIndo } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/errors';
import {
  Layers,
  GitBranch,
  Plus,
  Edit2,
  Trash2,
  Calendar,
  FileCheck,
  FileText,
  AlertCircle,
  X,
  Check,
} from 'lucide-react';

export default function PPDBWavesPage() {
  const queryClient = useQueryClient();

  const [activeTab, setActiveTab] = useState<'waves' | 'tracks'>('waves');
  const [selectedWaveId, setSelectedWaveId] = useState<string>('');

  // Dialog states for Waves
  const [waveDialogOpen, setWaveDialogOpen] = useState(false);
  const [editingWave, setEditingWave] = useState<PPDBWaveItem | null>(null);
  const [deletingWave, setDeletingWave] = useState<PPDBWaveItem | null>(null);

  // Dialog states for Tracks
  const [trackDialogOpen, setTrackDialogOpen] = useState(false);
  const [editingTrack, setEditingTrack] = useState<PPDBTrackItem | null>(null);
  const [deletingTrack, setDeletingTrack] = useState<PPDBTrackItem | null>(null);

  // Requirement Dialog states
  const [requirementDialogOpen, setRequirementDialogOpen] = useState(false);
  const [targetTrackForReq, setTargetTrackForReq] = useState<PPDBTrackItem | null>(null);

  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null
  );

  // 1. Query Waves
  const {
    data: wavesData,
    isLoading: wavesLoading,
    isError: wavesError,
    refetch: refetchWaves,
  } = useQuery({
    queryKey: ['ppdb-waves-admin-list'],
    queryFn: () => ppdbApi.listWaves(),
  });

  const waves: PPDBWaveItem[] = useMemo(() => {
    return Array.isArray(wavesData) ? wavesData : (wavesData as any)?.data || [];
  }, [wavesData]);

  // Default wave selector
  React.useEffect(() => {
    if (waves.length > 0 && !selectedWaveId) {
      setSelectedWaveId(waves[0].id);
    }
  }, [waves, selectedWaveId]);

  // 2. Query Tracks for selected wave
  const {
    data: tracksData,
    isLoading: tracksLoading,
    refetch: refetchTracks,
  } = useQuery({
    queryKey: ['ppdb-tracks-admin-list', selectedWaveId],
    queryFn: () => ppdbApi.listTracks({ wave_id: selectedWaveId || undefined }),
    enabled: !!selectedWaveId || activeTab === 'tracks',
  });

  const tracks: PPDBTrackItem[] = Array.isArray(tracksData)
    ? tracksData
    : (tracksData as any)?.data || [];

  // Mutations: Waves
  const createWaveMutation = useMutation({
    mutationFn: (payload: CreateWavePayload) => ppdbApi.createWave(payload),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-waves-admin-list'] });
      setWaveDialogOpen(false);
      setFeedback({ type: 'success', message: `Gelombang "${data.name}" berhasil dibuat.` });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal membuat gelombang PPDB';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const updateWaveMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateWavePayload }) =>
      ppdbApi.updateWave(id, payload),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-waves-admin-list'] });
      setWaveDialogOpen(false);
      setEditingWave(null);
      setFeedback({ type: 'success', message: `Gelombang "${data.name}" berhasil diperbarui.` });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memperbarui gelombang PPDB';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const deleteWaveMutation = useMutation({
    mutationFn: (id: string) => ppdbApi.deleteWave(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-waves-admin-list'] });
      setDeletingWave(null);
      setFeedback({ type: 'success', message: 'Gelombang PPDB berhasil dihapus.' });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menghapus gelombang PPDB';
      setFeedback({ type: 'error', message: msg });
    },
  });

  // Mutations: Tracks
  const createTrackMutation = useMutation({
    mutationFn: (payload: CreateTrackPayload) => ppdbApi.createTrack(payload),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-tracks-admin-list'] });
      setTrackDialogOpen(false);
      setFeedback({ type: 'success', message: `Jalur "${data.name}" berhasil dibuat.` });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal membuat jalur PPDB';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const updateTrackMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateTrackPayload }) =>
      ppdbApi.updateTrack(id, payload),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-tracks-admin-list'] });
      setTrackDialogOpen(false);
      setEditingTrack(null);
      setFeedback({ type: 'success', message: `Jalur "${data.name}" berhasil diperbarui.` });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memperbarui jalur PPDB';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const deleteTrackMutation = useMutation({
    mutationFn: (id: string) => ppdbApi.deleteTrack(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-tracks-admin-list'] });
      setDeletingTrack(null);
      setFeedback({ type: 'success', message: 'Jalur PPDB berhasil dihapus.' });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menghapus jalur PPDB';
      setFeedback({ type: 'error', message: msg });
    },
  });

  // Mutations: Requirements
  const createReqMutation = useMutation({
    mutationFn: (payload: CreateRequirementPayload) => ppdbApi.createRequirement(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-tracks-admin-list'] });
      setRequirementDialogOpen(false);
      setTargetTrackForReq(null);
      setFeedback({ type: 'success', message: 'Syarat berkas berhasil ditambahkan ke jalur.' });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menambah syarat berkas';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const deleteReqMutation = useMutation({
    mutationFn: (id: string) => ppdbApi.deleteRequirement(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-tracks-admin-list'] });
      setFeedback({ type: 'success', message: 'Syarat berkas berhasil dihapus.' });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menghapus syarat berkas';
      setFeedback({ type: 'error', message: msg });
    },
  });

  // Table Columns: Waves
  const waveColumns: ColumnDef<PPDBWaveItem>[] = [
    {
      key: 'name',
      header: 'Nama Gelombang',
      render: (item) => (
        <div>
          <span className="font-semibold text-gray-900 block">{item.name}</span>
          <span className="text-xs text-gray-500">ID: {item.id.slice(0, 8)}...</span>
        </div>
      ),
    },
    {
      key: 'schedule',
      header: 'Jadwal Pendaftaran',
      render: (item) => (
        <div className="text-xs space-y-0.5">
          <div className="flex items-center gap-1.5 text-gray-700">
            <span className="text-gray-400">Buka:</span>
            <span>{formatTanggalIndo(item.open_at)}</span>
          </div>
          <div className="flex items-center gap-1.5 text-gray-700">
            <span className="text-gray-400">Tutup:</span>
            <span>{formatTanggalIndo(item.close_at)}</span>
          </div>
        </div>
      ),
    },
    {
      key: 'publish',
      header: 'Pengumuman Hasil',
      render: (item) => (
        <span className="text-xs text-gray-700">{formatTanggalIndo(item.result_publish_at)}</span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => {
        switch (item.status) {
          case 'aktif':
            return <Badge variant="success">AKTIF</Badge>;
          case 'ditutup':
            return <Badge variant="warning">DITUTUP</Badge>;
          case 'selesai':
            return <Badge variant="default">SELESAI</Badge>;
          default:
            return <Badge variant="outline">DRAF</Badge>;
        }
      },
    },
    {
      key: 'actions',
      header: 'Aksi',
      render: (item) => (
        <div className="flex items-center gap-1">
          <Button
            size="sm"
            variant="outline"
            onClick={() => {
              setEditingWave(item);
              setWaveDialogOpen(true);
            }}
            className="h-8 w-8 p-0"
          >
            <Edit2 className="h-3.5 w-3.5 text-gray-600" />
          </Button>
          <Button
            size="sm"
            variant="outline"
            onClick={() => setDeletingWave(item)}
            className="h-8 w-8 p-0 text-red-600 hover:bg-red-50"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </Button>
        </div>
      ),
    },
  ];

  // Table Columns: Tracks
  const trackColumns: ColumnDef<PPDBTrackItem>[] = [
    {
      key: 'code',
      header: 'Kode',
      render: (item) => (
        <span className="font-mono font-semibold text-xs text-gray-900 bg-gray-100 px-2 py-0.5 rounded">
          {item.code}
        </span>
      ),
    },
    {
      key: 'name',
      header: 'Nama Jalur',
      render: (item) => (
        <div>
          <span className="font-semibold text-gray-900 block">{item.name}</span>
          <span className="text-xs text-gray-500">
            Formula: Rapor {Math.round((item.score_formula?.rapor_weight ?? 0.6) * 100)}% + Tes{' '}
            {Math.round((item.score_formula?.test_weight ?? 0.4) * 100)}%
          </span>
        </div>
      ),
    },
    {
      key: 'quota',
      header: 'Kuota',
      render: (item) => (
        <span className="font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full text-xs">
          {item.quota} Siswa
        </span>
      ),
    },
    {
      key: 'requirements',
      header: 'Syarat Berkas',
      render: (item) => {
        const reqs = item.requirements || [];
        return (
          <div className="flex flex-wrap items-center gap-1">
            {reqs.map((r: any) => (
              <span
                key={r.id}
                className="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700"
              >
                {r.document_type}
                <button
                  type="button"
                  onClick={() => deleteReqMutation.mutate(r.id)}
                  className="text-gray-400 hover:text-red-600"
                >
                  <X className="h-3 w-3" />
                </button>
              </span>
            ))}
            <button
              type="button"
              onClick={() => {
                setTargetTrackForReq(item);
                setRequirementDialogOpen(true);
              }}
              className="inline-flex items-center gap-0.5 rounded border border-dashed border-gray-300 px-2 py-0.5 text-[11px] text-emerald-700 hover:border-emerald-500"
            >
              <Plus className="h-3 w-3" /> Syarat
            </button>
          </div>
        );
      },
    },
    {
      key: 'actions',
      header: 'Aksi',
      render: (item) => (
        <div className="flex items-center gap-1">
          <Button
            size="sm"
            variant="outline"
            onClick={() => {
              setEditingTrack(item);
              setTrackDialogOpen(true);
            }}
            className="h-8 w-8 p-0"
          >
            <Edit2 className="h-3.5 w-3.5 text-gray-600" />
          </Button>
          <Button
            size="sm"
            variant="outline"
            onClick={() => setDeletingTrack(item)}
            className="h-8 w-8 p-0 text-red-600 hover:bg-red-50"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PermissionGate permission="ppdb.wave.manage">
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Konfigurasi Gelombang & Jalur PPDB
            </h1>
            <p className="text-sm text-gray-500">
              Kelola periode gelombang pendaftaran, alokasi kuota per jalur, dan persyaratan berkas masuk
            </p>
          </div>
          <div className="flex items-center gap-2">
            {activeTab === 'waves' ? (
              <Button
                onClick={() => {
                  setEditingWave(null);
                  setWaveDialogOpen(true);
                }}
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                <Plus className="mr-1.5 h-4 w-4" /> Tambah Gelombang
              </Button>
            ) : (
              <Button
                onClick={() => {
                  setEditingTrack(null);
                  setTrackDialogOpen(true);
                }}
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                <Plus className="mr-1.5 h-4 w-4" /> Tambah Jalur
              </Button>
            )}
          </div>
        </div>

        {/* Feedback Alert */}
        {feedback && (
          <div
            className={`flex items-center justify-between rounded-lg p-3 text-sm ${
              feedback.type === 'success'
                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'bg-red-50 text-red-800 border border-red-200'
            }`}
          >
            <span>{feedback.message}</span>
            <button
              onClick={() => setFeedback(null)}
              className="text-gray-400 hover:text-gray-600"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}

        {/* Tab Navigation */}
        <div className="flex border-b border-gray-200">
          <button
            onClick={() => setActiveTab('waves')}
            className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition ${
              activeTab === 'waves'
                ? 'border-emerald-600 text-emerald-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Layers className="h-4 w-4" />
            Gelombang Pendaftaran ({waves.length})
          </button>
          <button
            onClick={() => setActiveTab('tracks')}
            className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition ${
              activeTab === 'tracks'
                ? 'border-emerald-600 text-emerald-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <GitBranch className="h-4 w-4" />
            Jalur & Kuota ({tracks.length})
          </button>
        </div>

        {/* Tab 1: Gelombang */}
        {activeTab === 'waves' && (
          <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <DataTable
              columns={waveColumns}
              data={waves}
              keyExtractor={(item) => item.id}
              isLoading={wavesLoading}
              emptyTitle="Belum ada gelombang PPDB yang dikonfigurasi."
            />
          </div>
        )}

        {/* Tab 2: Jalur & Kuota */}
        {activeTab === 'tracks' && (
          <div className="space-y-4">
            <div className="flex items-center gap-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
              <span className="text-xs font-medium text-gray-700">Filter Gelombang:</span>
              <select
                value={selectedWaveId}
                onChange={(e) => setSelectedWaveId(e.target.value)}
                className="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-800"
              >
                {waves.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name} ({w.status.toUpperCase()})
                  </option>
                ))}
              </select>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
              <DataTable
                columns={trackColumns}
                data={tracks}
                keyExtractor={(item) => item.id}
                isLoading={tracksLoading}
                emptyTitle="Belum ada jalur pendaftaran untuk gelombang ini."
              />
            </div>
          </div>
        )}

        {/* Dialogs */}
        <WaveDialog
          isOpen={waveDialogOpen}
          onClose={() => setWaveDialogOpen(false)}
          onSubmit={async (values: WaveFormValues) => {
            if (editingWave) {
              await updateWaveMutation.mutateAsync({ id: editingWave.id, payload: values });
            } else {
              await createWaveMutation.mutateAsync(values);
            }
          }}
          initialData={editingWave}
          isLoading={createWaveMutation.isPending || updateWaveMutation.isPending}
        />

        <TrackDialog
          isOpen={trackDialogOpen}
          onClose={() => setTrackDialogOpen(false)}
          onSubmit={async (values: TrackFormValues) => {
            const payload: CreateTrackPayload = {
              wave_id: values.wave_id,
              code: values.code,
              name: values.name,
              quota: values.quota,
              score_formula: {
                rapor_weight: values.rapor_weight,
                test_weight: values.test_weight,
              },
            };
            if (editingTrack) {
              await updateTrackMutation.mutateAsync({ id: editingTrack.id, payload });
            } else {
              await createTrackMutation.mutateAsync(payload);
            }
          }}
          waves={waves}
          defaultWaveId={selectedWaveId}
          initialData={editingTrack}
          isLoading={createTrackMutation.isPending || updateTrackMutation.isPending}
        />

        <RequirementDialog
          isOpen={requirementDialogOpen}
          onClose={() => setRequirementDialogOpen(false)}
          onSubmit={async (values: RequirementFormValues) => {
            await createReqMutation.mutateAsync(values);
          }}
          track={targetTrackForReq}
          isLoading={createReqMutation.isPending}
        />

        {/* Confirm Delete Wave */}
        <ConfirmDialog
          isOpen={!!deletingWave}
          title="Hapus Gelombang PPDB"
          description={`Apakah Anda yakin ingin menghapus gelombang "${deletingWave?.name}"? Tindakan ini tidak dapat dibatalkan.`}
          confirmLabel="Hapus Gelombang"
          variant="danger"
          onClose={() => setDeletingWave(null)}
          onConfirm={() => {
            if (deletingWave) deleteWaveMutation.mutate(deletingWave.id);
          }}
          isLoading={deleteWaveMutation.isPending}
        />

        {/* Confirm Delete Track */}
        <ConfirmDialog
          isOpen={!!deletingTrack}
          title="Hapus Jalur PPDB"
          description={`Apakah Anda yakin ingin menghapus jalur "${deletingTrack?.name}"? Tindakan ini tidak dapat dibatalkan.`}
          confirmLabel="Hapus Jalur"
          variant="danger"
          onClose={() => setDeletingTrack(null)}
          onConfirm={() => {
            if (deletingTrack) deleteTrackMutation.mutate(deletingTrack.id);
          }}
          isLoading={deleteTrackMutation.isPending}
        />
      </div>
    </PermissionGate>
  );
}
