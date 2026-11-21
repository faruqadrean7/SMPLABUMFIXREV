'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  AlertCircle,
  RefreshCw,
  Download,
  Filter,
  DollarSign,
  Users,
  FileText,
  Clock,
  Layers,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import { financeApi, formatRupiah } from '@/features/finance/finance-api';
import { classesApi } from '@/features/master/classes-api';
import { SchoolClassItem } from '@/types/master';
import { ArrearsSummary } from '@/types/finance';

export default function ArrearsReportPage() {
  const [report, setReport] = useState<ArrearsSummary | null>(null);
  const [classes, setClasses] = useState<SchoolClassItem[]>([]);
  const [classFilter, setClassFilter] = useState<string>('all');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [arrearsData, classesRes] = await Promise.all([
        financeApi.getArrearsReport({
          class_id: classFilter !== 'all' ? classFilter : undefined,
        }),
        classesApi.list({ limit: 100 }).catch(() => ({ data: [] })),
      ]);
      setReport(arrearsData);
      setClasses(classesRes.data || []);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat rekap tunggakan.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, [classFilter]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleExportCsv = () => {
    const url = financeApi.exportCsvUrl('arrears', {
      class_id: classFilter !== 'all' ? classFilter : undefined,
    });
    window.open(url, '_blank');
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <AlertCircle className="h-6 w-6 text-rose-600" />
            Rekap Tunggakan Piutang Siswa
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Pantau total piutang biaya pendidikan yang belum terlunasi berdasarkan kelas dan jenjang tingkatan.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={loadData} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={handleExportCsv}
            className="gap-2 border-slate-300 text-slate-700 hover:bg-slate-50"
          >
            <Download className="h-4 w-4" />
            Ekspor CSV
          </Button>
        </div>
      </div>

      {/* Filter Rombel */}
      <Card>
        <CardContent className="p-4 flex items-center gap-3">
          <Filter className="h-4 w-4 text-slate-500" />
          <div className="flex-1 max-w-xs">
            <select
              value={classFilter}
              onChange={(e) => setClassFilter(e.target.value)}
              className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
            >
              <option value="all">Semua Rombongan Belajar (Rombel)</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>
        </CardContent>
      </Card>

      {/* Konten Laporan */}
      {isLoading ? (
        <Card className="p-12">
          <LoadingSpinner label="Menghitung rekap tunggakan..." size="lg" />
        </Card>
      ) : error ? (
        <ErrorState
          title="Gagal Memuat Rekap Tunggakan"
          message={error}
          onRetry={loadData}
        />
      ) : !report ? (
        <Card className="p-8">
          <EmptyState title="Tidak Ada Data" description="Belum ada data tunggakan untuk filter ini." />
        </Card>
      ) : (
        <>
          {/* Summary Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Card className="border-rose-100 bg-rose-50/30">
              <CardContent className="p-5 flex items-center gap-4">
                <div className="p-3 bg-rose-100 rounded-xl text-rose-700">
                  <DollarSign className="h-6 w-6" />
                </div>
                <div>
                  <div className="text-xs font-semibold text-rose-700 uppercase">Total Piutang Tertunggak</div>
                  <div className="text-2xl font-bold text-rose-900 mt-1">
                    {formatRupiah(report.total_arrears)}
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center gap-4">
                <div className="p-3 bg-amber-100 rounded-xl text-amber-700">
                  <FileText className="h-6 w-6" />
                </div>
                <div>
                  <div className="text-xs font-semibold text-slate-500 uppercase">Jumlah Faktur Belum Lunas</div>
                  <div className="text-2xl font-bold text-slate-900 mt-1">
                    {report.total_invoices_unpaid} Faktur
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center gap-4">
                <div className="p-3 bg-blue-100 rounded-xl text-blue-700">
                  <Users className="h-6 w-6" />
                </div>
                <div>
                  <div className="text-xs font-semibold text-slate-500 uppercase">Siswa Memiliki Tunggakan</div>
                  <div className="text-2xl font-bold text-slate-900 mt-1">
                    {report.students_in_arrears_count} Siswa
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Breakdown per Tingkat */}
          {report.by_grade && report.by_grade.length > 0 && (
            <Card>
              <CardHeader className="pb-3 border-b border-slate-100">
                <CardTitle className="text-base font-semibold text-slate-900 flex items-center gap-2">
                  <Layers className="h-5 w-5 text-slate-600" />
                  Rincian Tunggakan per Tingkat Kelas
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  {report.by_grade.map((bg) => (
                    <div
                      key={bg.grade}
                      className="p-3 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-between"
                    >
                      <div>
                        <div className="font-semibold text-slate-800">Kelas {bg.grade}</div>
                        <div className="text-xs text-slate-500">{bg.count} faktur tertunda</div>
                      </div>
                      <div className="text-right font-bold text-rose-600">
                        {formatRupiah(bg.arrears)}
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Tabel Detail Tagihan Tertunggak */}
          <Card>
            <CardHeader className="pb-3 border-b border-slate-100">
              <CardTitle className="text-base font-semibold text-slate-900">
                Daftar Faktur Tertunggak ({report.items?.length || 0} entri)
              </CardTitle>
            </CardHeader>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase font-semibold text-slate-600">
                  <tr>
                    <th className="px-4 py-3">No. Faktur</th>
                    <th className="px-4 py-3">Siswa</th>
                    <th className="px-4 py-3">Rombel</th>
                    <th className="px-4 py-3">Periode</th>
                    <th className="px-4 py-3">Jatuh Tempo</th>
                    <th className="px-4 py-3 text-right">Total Tagihan</th>
                    <th className="px-4 py-3 text-right">Sudah Bayar</th>
                    <th className="px-4 py-3 text-right">Sisa Tunggakan</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {report.items?.map((inv) => (
                    <tr key={inv.id} className="hover:bg-slate-50/50">
                      <td className="px-4 py-3 font-mono font-medium text-slate-800">{inv.invoice_number}</td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-900">{inv.student?.full_name}</div>
                        <div className="text-xs text-slate-500">NISN: {inv.student?.nisn}</div>
                      </td>
                      <td className="px-4 py-3 text-slate-700">{inv.student?.current_class?.name || '-'}</td>
                      <td className="px-4 py-3 text-slate-600">{inv.period || '-'}</td>
                      <td className="px-4 py-3 text-rose-600 font-medium">{inv.due_date}</td>
                      <td className="px-4 py-3 text-right text-slate-700 font-medium">
                        {formatRupiah(inv.total_amount)}
                      </td>
                      <td className="px-4 py-3 text-right text-emerald-600">
                        {formatRupiah(inv.paid_amount)}
                      </td>
                      <td className="px-4 py-3 text-right font-bold text-rose-600">
                        {formatRupiah(inv.remaining_amount)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </>
      )}
    </div>
  );
}
