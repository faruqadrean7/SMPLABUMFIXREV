'use client';

import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import {
  PPDBWaveItem,
  PPDBTrackItem,
  PPDBRankingItem,
  RankApplicantsPayload,
  OverrideDecisionPayload,
  EnrollApplicantPayload,
} from '@/types/ppdb';
import { DataTable, ColumnDef } from '@/components/shared/data-table';
import { DecisionOverrideDialog, DecisionOverrideFormValues } from '@/features/ppdb/decision-override-dialog';
import { EnrollmentDialog } from '@/features/ppdb/enrollment-dialog';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PermissionGate } from '@/components/shared/permission-gate';
import { formatTanggalIndo } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/errors';
import {
  TrendingUp,
  Play,
  Share2,
  UserCheck,
  SlidersHorizontal,
  RefreshCw,
  AlertCircle,
  CheckCircle,
  HelpCircle,
  Trophy,
  ArrowUpRight,
  X,
} from 'lucide-react';

export default function PPDBSelectionPage() {
  const queryClient = useQueryClient();

  const [selectedWaveId, setSelectedWaveId] = useState<string>('');
  const [selectedTrackId, setSelectedTrackId] = useState<string>('');
  const [decisionFilter, setDecisionFilter] = useState<string>('');

  // Modals
  const [confirmRankOpen, setConfirmRankOpen] = useState(false);
  const [confirmPublishOpen, setConfirmPublishOpen] = useState(false);
  const [confirmEscalateOpen, setConfirmEscalateOpen] = useState(false);

  // Dialog states
  const [overrideApplicant, setOverrideApplicant] = useState<PPDBRankingItem | null>(null);
  const [enrollApplicant, setEnrollApplicant] = useState<PPDBRankingItem | null>(null);

  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null
  );

  // 1. Fetch Waves
  const { data: wavesData } = useQuery({
    queryKey: ['ppdb-waves-lookup-for-selection'],
    queryFn: () => ppdbApi.listWaves(),
  });

  const waves: PPDBWaveItem[] = useMemo(() => {
    return Array.isArray(wavesData) ? wavesData : (wavesData as any)?.data || [];
  }, [wavesData]);

  React.useEffect(() => {
    if (waves.length > 0 && !selectedWaveId) {
      setSelectedWaveId(waves[0].id);
    }
  }, [waves, selectedWaveId]);

  // 2. Fetch Tracks for selected wave
  const { data: tracksData } = useQuery({
    queryKey: ['ppdb-tracks-lookup-for-selection', selectedWaveId],
    queryFn: () => ppdbApi.listTracks({ wave_id: selectedWaveId || undefined }),
    enabled: !!selectedWaveId,
  });

  const tracks: PPDBTrackItem[] = Array.isArray(tracksData)
    ? tracksData
    : (tracksData as any)?.data || [];

  // 3. Fetch Rankings for wave
  const {
    data: rankingsData,
    isLoading: rankingsLoading,
    refetch: refetchRankings,
  } = useQuery({
    queryKey: ['ppdb-rankings', selectedWaveId, selectedTrackId, decisionFilter],
    queryFn: () =>
      ppdbApi.getRankings(selectedWaveId, {
        track_id: selectedTrackId || undefined,
        decision: decisionFilter || undefined,
      }),
    enabled: !!selectedWaveId,
  });

  const rankings = rankingsData?.applicants || [];
  const selectedWave = waves.find((w) => w.id === selectedWaveId);

  // Mutations
  const rankMutation = useMutation({
    mutationFn: (payload?: RankApplicantsPayload) =>
      ppdbApi.rankWave(selectedWaveId, payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-rankings'] });
      setConfirmRankOpen(false);
      setFeedback({
        type: 'success',
        message: 'Kalkulasi perangkingan seleksi berhasil dieksekusi berdasarkan kuota jalur!',
      });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal mengeksekusi perankingan';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const publishMutation = useMutation({
    mutationFn: () => ppdbApi.publishResults(selectedWaveId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-waves-lookup-for-selection'] });
      queryClient.invalidateQueries({ queryKey: ['ppdb-rankings'] });
      setConfirmPublishOpen(false);
      setFeedback({
        type: 'success',
        message: 'Hasil seleksi PPDB berhasil dipublikasikan dan notifikasi telah dikirimkan ke calon siswa!',
      });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal mempublikasikan hasil seleksi';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const escalateMutation = useMutation({
    mutationFn: () => ppdbApi.escalateWaitlist(selectedWaveId, { track_id: selectedTrackId || undefined, confirmed: true }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-rankings'] });
      setConfirmEscalateOpen(false);
      setFeedback({
        type: 'success',
        message: 'Eskalasi cadangan (waitlist promotion) berhasil diproses.',
      });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal memproses eskalasi cadangan';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const overrideMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: OverrideDecisionPayload }) =>
      ppdbApi.overrideDecision(id, payload),
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-rankings'] });
      setOverrideApplicant(null);
      setFeedback({
        type: 'success',
        message: `Keputusan untuk ${updated.full_name} berhasil diubah menjadi: ${updated.decision?.toUpperCase()}`,
      });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal menyesuaikan keputusan';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const enrollMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: EnrollApplicantPayload }) =>
      ppdbApi.enrollApplicant(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-rankings'] });
      setEnrollApplicant(null);
      setFeedback({
        type: 'success',
        message: 'Pendaftar berhasil dikonversi menjadi Siswa Aktif dan rombel kelas telah dialokasikan!',
      });
    },
    onError: (err: unknown) => {
      const msg = err instanceof ApiClientError ? err.message : 'Gagal melakukan konversi siswa';
      setFeedback({ type: 'error', message: msg });
    },
  });

  const columns: ColumnDef<PPDBRankingItem>[] = [
    {
      key: 'rank',
      header: 'Peringkat',
      render: (item) => (
        <div className="flex items-center gap-1.5 font-bold">
          {item.rank === 1 && <Trophy className="h-4 w-4 text-amber-500" />}
          <span
            className={`font-mono text-xs px-2 py-0.5 rounded ${
              item.rank && item.rank <= 3
                ? 'bg-amber-100 text-amber-900 font-bold'
                : 'bg-gray-100 text-gray-700'
            }`}
          >
            #{item.rank ?? '-'}
          </span>
        </div>
      ),
    },
    {
      key: 'reg_no',
      header: 'No. Registrasi',
      render: (item) => (
        <span className="font-mono text-xs text-gray-700 bg-gray-50 px-2 py-0.5 rounded border border-gray-100">
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
          <span className="text-xs text-gray-500">{item.track_name || '-'}</span>
        </div>
      ),
    },
    {
      key: 'score',
      header: 'Skor Seleksi',
      render: (item) => (
        <span className="font-mono font-bold text-xs text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded">
          {item.score !== null && item.score !== undefined ? Number(item.score).toFixed(2) : '-'}
        </span>
      ),
    },
    {
      key: 'decision',
      header: 'Keputusan',
      render: (item) => {
        switch (item.decision) {
          case 'diterima':
            return <Badge variant="success">DITERIMA</Badge>;
          case 'cadangan':
            return <Badge variant="warning">CADANGAN</Badge>;
          case 'ditolak':
            return <Badge variant="danger">TIDAK DITERIMA</Badge>;
          default:
            return <Badge variant="outline">BELUM DITETAPKAN</Badge>;
        }
      },
    },
    {
      key: 'status',
      header: 'Status Siswa',
      render: (item) => {
        if (item.status === 'enrolled') {
          return <Badge variant="default">SISWA AKTIF</Badge>;
        }
        return <span className="text-xs text-gray-500 uppercase">{item.status}</span>;
      },
    },
    {
      key: 'actions',
      header: 'Aksi Panitia',
      render: (item) => (
        <div className="flex items-center gap-1.5">
          {item.status !== 'enrolled' ? (
            <Button
              size="sm"
              variant="outline"
              onClick={() => setOverrideApplicant(item)}
              className="text-xs h-7 px-2.5 text-amber-700 border-amber-300 hover:bg-amber-50"
              title="Sesuaikan Keputusan Ranking"
            >
              <SlidersHorizontal className="mr-1 h-3 w-3" />
              Override
            </Button>
          ) : (
            <span className="text-xs text-gray-400 italic px-1">Siswa Terdaftar</span>
          )}

          {item.decision === 'diterima' && item.status !== 'enrolled' && (
            <Button
              size="sm"
              onClick={() => setEnrollApplicant(item)}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-7 px-2.5"
              title="Konversi ke Siswa Aktif Master Data"
            >
              <UserCheck className="mr-1 h-3 w-3" />
              Masuk Siswa
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <PermissionGate permission="ppdb.selection.run">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Seleksi & Perankingan PPDB
            </h1>
            <p className="text-sm text-gray-500">
              Kalkulasi pemeringkatan otomatis berdasarkan kuota, override keputusan panitia, dan konversi ke siswa aktif
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button
              onClick={() => setConfirmRankOpen(true)}
              disabled={!selectedWaveId || rankMutation.isPending}
              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9"
            >
              <Play className="mr-1.5 h-4 w-4" />
              {rankMutation.isPending ? 'Menghitung...' : 'Jalankan Ranking'}
            </Button>

            <Button
              onClick={() => setConfirmPublishOpen(true)}
              disabled={!selectedWaveId || publishMutation.isPending}
              variant="outline"
              className="border-emerald-300 text-emerald-700 hover:bg-emerald-50 text-xs h-9"
            >
              <Share2 className="mr-1.5 h-4 w-4" />
              Publikasikan Hasil
            </Button>
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

        {/* Filter Bar */}
        <div className="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-4">
          <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">Gelombang PPDB</label>
            <select
              value={selectedWaveId}
              onChange={(e) => {
                setSelectedWaveId(e.target.value);
                setSelectedTrackId('');
              }}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
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
              value={selectedTrackId}
              onChange={(e) => setSelectedTrackId(e.target.value)}
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
            <label className="mb-1 block text-xs font-medium text-gray-600">Status Keputusan</label>
            <select
              value={decisionFilter}
              onChange={(e) => setDecisionFilter(e.target.value)}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="">Semua Keputusan</option>
              <option value="diterima">Diterima</option>
              <option value="cadangan">Cadangan (Waitlist)</option>
              <option value="ditolak">Tidak Diterima</option>
            </select>
          </div>

          <div className="flex items-end">
            <Button
              variant="outline"
              onClick={() => setConfirmEscalateOpen(true)}
              className="w-full text-xs h-9 text-gray-700 hover:bg-gray-50 border-gray-300"
            >
              <ArrowUpRight className="mr-1 h-3.5 w-3.5" />
              Eskalasi Cadangan
            </Button>
          </div>
        </div>

        {/* Rankings Table */}
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          <DataTable
            columns={columns}
            data={rankings}
            keyExtractor={(item) => item.id}
            isLoading={rankingsLoading}
            emptyTitle="Belum ada data perankingan untuk gelombang ini. Klik 'Jalankan Ranking' untuk memproses."
          />
        </div>

        {/* Dialogs */}
        <DecisionOverrideDialog
          isOpen={!!overrideApplicant}
          applicant={overrideApplicant}
          onClose={() => setOverrideApplicant(null)}
          onSubmit={async (values: DecisionOverrideFormValues) => {
            if (overrideApplicant) {
              await overrideMutation.mutateAsync({
                id: overrideApplicant.id,
                payload: values,
              });
            }
          }}
          isLoading={overrideMutation.isPending}
        />

        <EnrollmentDialog
          isOpen={!!enrollApplicant}
          applicant={enrollApplicant}
          onClose={() => setEnrollApplicant(null)}
          onSubmit={async (values: EnrollApplicantPayload) => {
            if (enrollApplicant) {
              await enrollMutation.mutateAsync({
                id: enrollApplicant.id,
                payload: values,
              });
            }
          }}
          isLoading={enrollMutation.isPending}
        />

        {/* Confirm Run Ranking */}
        <ConfirmDialog
          isOpen={confirmRankOpen}
          title="Eksekusi Perankingan Seleksi"
          description={`Apakah Anda yakin ingin menjalankan algoritma perankingan pada ${selectedWave?.name}? Skor pendaftar yang telah diverifikasi akan diurutkan dan dialokasikan ke kuota (Diterima / Cadangan / Ditolak).`}
          confirmLabel="Jalankan Perankingan"
          variant="primary"
          onClose={() => setConfirmRankOpen(false)}
          onConfirm={() => rankMutation.mutate()}
          isLoading={rankMutation.isPending}
        />

        {/* Confirm Publish Results */}
        <ConfirmDialog
          isOpen={confirmPublishOpen}
          title="Publikasikan Pengumuman Seleksi"
          description="Apakah Anda yakin ingin merilis pengumuman hasil seleksi gelombang ini? Calon siswa akan dapat melihat status kelulusan di portal publik dan notifikasi sistem akan dikirimkan."
          confirmLabel="Publikasikan Sekarang"
          variant="primary"
          onClose={() => setConfirmPublishOpen(false)}
          onConfirm={() => publishMutation.mutate()}
          isLoading={publishMutation.isPending}
        />

        {/* Confirm Escalate Waitlist */}
        <ConfirmDialog
          isOpen={confirmEscalateOpen}
          title="Eskalasi Cadangan ke Diterima"
          description="Apakah Anda ingin mempromosikan kandidat cadangan berperingkat teratas untuk mengisi kuota pendaftar utama yang tidak melakukan daftar ulang sebelum batas waktu?"
          confirmLabel="Proses Eskalasi"
          variant="primary"
          onClose={() => setConfirmEscalateOpen(false)}
          onConfirm={() => escalateMutation.mutate()}
          isLoading={escalateMutation.isPending}
        />
      </div>
    </PermissionGate>
  );
}
