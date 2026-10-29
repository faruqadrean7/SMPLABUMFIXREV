'use client';

import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  Award, 
  ShieldCheck, 
  AlertTriangle, 
  PlusCircle, 
  MinusCircle, 
  Clock, 
  CheckCircle2, 
  Users, 
  Calendar,
  ArrowRight,
  RotateCw,
  Compass,
  Trophy
} from 'lucide-react';
import { portalApi } from '@/features/portal/portal-api';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';

export default function StudentPoinPage() {
  const [activeTab, setActiveTab] = useState<'poin' | 'ekskul'>('poin');
  const [filterKind, setFilterKind] = useState<'semua' | 'prestasi' | 'pelanggaran'>('semua');

  const { data: pointsData, isLoading, isError, refetch } = useQuery({
    queryKey: ['student-points'],
    queryFn: () => portalApi.getStudentPoints(),
  });

  const logs = Array.isArray(pointsData?.logs) ? pointsData.logs : [];
  const totalPrestasi = pointsData?.totalPrestasi ?? 0;
  const totalPelanggaran = pointsData?.totalPelanggaran ?? 0;
  const balancePoints = pointsData?.balancePoints ?? (totalPrestasi - totalPelanggaran);

  const filteredLogs = filterKind === 'semua'
    ? logs
    : logs.filter((p) => p.kind === filterKind);

  const extracurriculars = [
    {
      id: 'ekskul-01',
      name: 'Coding & Robotika Club',
      coach: 'Budi Santoso, S.Kom., M.T.',
      schedule: 'Setiap Rabu, 15:00 - 17:00 WIB',
      room: 'Lab Komputer 1',
      role: 'Anggota Inti',
      attendance_rate: '100%',
    },
    {
      id: 'ekskul-02',
      name: 'Palang Merah Remaja (PMR Madya)',
      coach: 'Dra. Siti Rahayu, M.Pd.',
      schedule: 'Setiap Sabtu, 08:00 - 10:00 WIB',
      room: 'Ruang UKS & Lapangan',
      role: 'Anggota Reguler',
      attendance_rate: '94%',
    },
  ];

  return (
    <div className="space-y-6 text-slate-900">
      {/* Header Halaman */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
            <Award className="h-6 w-6 text-brand-blue" />
            Poin Kedisiplinan & Ekstrakurikuler
          </h1>
          <p className="text-xs text-slate-600 mt-1">
            Monitoring saldo poin pembinaan karakter, rekaman prestasi siswa, serta keikutsertaan kegiatan ekskul.
          </p>
        </div>

        {/* Tab Selector */}
        <div className="flex items-center gap-2 bg-slate-100 p-1 rounded-lg border border-slate-200 self-start md:self-auto">
          <button
            onClick={() => setActiveTab('poin')}
            className={`flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
              activeTab === 'poin' 
                ? 'bg-white text-brand-blue shadow-xs' 
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <ShieldCheck className="h-3.5 w-3.5" />
            Buku Poin Karakter
          </button>
          <button
            onClick={() => setActiveTab('ekskul')}
            className={`flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
              activeTab === 'ekskul' 
                ? 'bg-white text-brand-blue shadow-xs' 
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Compass className="h-3.5 w-3.5" />
            Ekstrakurikuler ({extracurriculars.length})
          </button>
        </div>
      </div>

      {activeTab === 'poin' && (
        <div className="space-y-6">
          {/* Ringkasan Skor Poin */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card className="border-blue-200 bg-blue-50/50 shadow-xs">
              <CardContent className="p-4 flex items-center justify-between">
                <div>
                  <div className="text-xs font-medium text-slate-600">Saldo Poin Bersih</div>
                  <div className={`text-2xl font-black mt-1 ${balancePoints >= 0 ? 'text-brand-blue' : 'text-status-danger'}`}>
                    {balancePoints >= 0 ? `+${balancePoints}` : balancePoints}
                  </div>
                  <div className="text-[10px] text-slate-500 mt-0.5">Status: Baik / Tertib</div>
                </div>
                <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-brand-blue">
                  <ShieldCheck className="h-5 w-5" />
                </div>
              </CardContent>
            </Card>

            <Card className="border-emerald-200 bg-emerald-50/50 shadow-xs">
              <CardContent className="p-4 flex items-center justify-between">
                <div>
                  <div className="text-xs font-medium text-slate-600">Total Poin Prestasi</div>
                  <div className="text-2xl font-black text-emerald-700 mt-1">
                    +{totalPrestasi}
                  </div>
                  <div className="text-[10px] text-emerald-600 mt-0.5">Penghargaan & Apresiasi</div>
                </div>
                <div className="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                  <Trophy className="h-5 w-5" />
                </div>
              </CardContent>
            </Card>

            <Card className="border-red-200 bg-red-50/50 shadow-xs">
              <CardContent className="p-4 flex items-center justify-between">
                <div>
                  <div className="text-xs font-medium text-slate-600">Poin Pelanggaran</div>
                  <div className="text-2xl font-black text-status-danger mt-1">
                    -{totalPelanggaran}
                  </div>
                  <div className="text-[10px] text-red-600 mt-0.5">Teguran & Pembinaan</div>
                </div>
                <div className="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center text-status-danger">
                  <AlertTriangle className="h-5 w-5" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Filter Bar */}
          <div className="flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs">
            <div className="flex items-center gap-2 text-xs font-medium text-slate-700">
              <span>Filter Catatan:</span>
              <div className="flex gap-1.5">
                {(['semua', 'prestasi', 'pelanggaran'] as const).map((kd) => (
                  <button
                    key={kd}
                    onClick={() => setFilterKind(kd)}
                    className={`px-3 py-1 rounded text-xs font-medium transition-colors capitalize ${
                      filterKind === kd
                        ? 'bg-brand-blue text-white'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    }`}
                  >
                    {kd}
                  </button>
                ))}
              </div>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={() => refetch()}
              className="text-xs h-8 gap-1.5"
            >
              <RotateCw className="h-3.5 w-3.5" />
              Segarkan
            </Button>
          </div>

          {/* Loading State */}
          {isLoading && (
            <div className="py-12 bg-white rounded-lg border border-slate-200 flex justify-center">
              <LoadingSpinner label="Memuat rekam poin karakter..." />
            </div>
          )}

          {/* Error State */}
          {isError && (
            <Card className="border-red-200 bg-red-50/50">
              <CardContent className="p-6 text-center">
                <AlertTriangle className="h-8 w-8 text-status-danger mx-auto mb-2" />
                <h3 className="font-semibold text-slate-900">Gagal Memuat Data Poin</h3>
                <p className="text-xs text-slate-600 mt-1 mb-4">Kendala server kesiswaan.</p>
                <Button size="sm" onClick={() => refetch()}>Coba Lagi</Button>
              </CardContent>
            </Card>
          )}

          {/* Riwayat Poin */}
          {!isLoading && !isError && filteredLogs.length === 0 && (
            <div className="bg-white rounded-lg border border-slate-200 p-8">
              <EmptyState
                icon={<ShieldCheck className="h-6 w-6" />}
                title="Buku Catatan Bersih"
                description={
                  filterKind === 'semua'
                    ? 'Belum ada catatan poin kedisiplinan atau prestasi yang tercatat pada semester ini.'
                    : `Tidak ada catatan dengan kategori "${filterKind}".`
                }
              />
            </div>
          )}

          {!isLoading && !isError && filteredLogs.length > 0 && (
            <div className="space-y-3">
              {filteredLogs.map((log) => {
                const isPositive = log.kind === 'prestasi';
                return (
                  <Card key={log.id} className="border-slate-200 bg-white hover:border-blue-200 transition-all shadow-xs">
                    <CardContent className="p-4 flex items-center justify-between gap-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <Badge className={isPositive ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}>
                            {isPositive ? 'Prestasi' : 'Pelanggaran'}
                          </Badge>
                          <span className="text-[11px] text-slate-500">{log.date}</span>
                        </div>
                        <h4 className="font-bold text-sm text-slate-900">{log.title}</h4>
                        <p className="text-xs text-slate-600">{log.notes || log.category}</p>
                        <div className="text-[10px] text-slate-400">Dicatat oleh: {log.reporterName}</div>
                      </div>

                      <div className={`text-xl font-black px-3 py-1.5 rounded-lg ${
                        isPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-status-danger'
                      }`}>
                        {isPositive ? `+${log.points}` : `-${log.points}`}
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          )}
        </div>
      )}

      {activeTab === 'ekskul' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {extracurriculars.map((ex) => (
              <Card key={ex.id} className="border-slate-200 bg-white shadow-xs">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    <Badge variant="outline" className="bg-blue-50 text-brand-blue border-blue-200 text-xs">
                      {ex.role}
                    </Badge>
                    <Badge className="bg-emerald-100 text-emerald-800 text-[11px]">
                      Kehadiran: {ex.attendance_rate}
                    </Badge>
                  </div>
                  <CardTitle className="text-base font-bold text-slate-900 mt-2">{ex.name}</CardTitle>
                  <CardDescription className="text-xs text-slate-500">Pembina: {ex.coach}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-2 text-xs text-slate-600 pt-0">
                  <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-slate-400" />
                    <span>{ex.schedule}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Clock className="h-4 w-4 text-slate-400" />
                    <span>Tempat: {ex.room}</span>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
