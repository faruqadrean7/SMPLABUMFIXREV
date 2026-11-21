'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  TrendingUp,
  RefreshCw,
  Download,
  Calendar,
  DollarSign,
  CreditCard,
  PieChart,
  BarChart3,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import { financeApi, formatRupiah } from '@/features/finance/finance-api';
import { IncomeReportSummary } from '@/types/finance';

export default function FinanceReportsPage() {
  const todayStr = new Date().toISOString().slice(0, 10);
  const firstOfMonthStr = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    .toISOString()
    .slice(0, 10);

  const [startDate, setStartDate] = useState(firstOfMonthStr);
  const [endDate, setEndDate] = useState(todayStr);

  const [report, setReport] = useState<IncomeReportSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await financeApi.getIncomeReport({
        start_date: startDate,
        end_date: endDate,
      });
      setReport(data);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat laporan pemasukan.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, [startDate, endDate]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleExportCsv = () => {
    const url = financeApi.exportCsvUrl('income', {
      start_date: startDate,
      end_date: endDate,
    });
    window.open(url, '_blank');
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <TrendingUp className="h-6 w-6 text-brand-blue" />
            Laporan Realisasi Pemasukan Keuangan
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Rekapitulasi dana masuk dari pembayaran siswa terverifikasi berdasarkan metode dan kategori pos biaya.
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

      {/* Filter Tanggal */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col sm:flex-row items-center gap-3">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <span className="text-xs font-semibold text-slate-600">Dari:</span>
              <Input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="w-full sm:w-44"
              />
            </div>
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <span className="text-xs font-semibold text-slate-600">Sampai:</span>
              <Input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="w-full sm:w-44"
              />
            </div>
            <Button size="sm" onClick={loadData} className="bg-brand-blue hover:bg-brand-blue/90">
              Terapkan Periode
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Konten Laporan */}
      {isLoading ? (
        <Card className="p-12">
          <LoadingSpinner label="Mengagregasi data pemasukan keuangan..." size="lg" />
        </Card>
      ) : error ? (
        <ErrorState
          title="Gagal Memuat Laporan"
          message={error}
          onRetry={loadData}
        />
      ) : !report ? (
        <Card className="p-8">
          <EmptyState title="Tidak Ada Data" description="Tidak ada transaksi pada rentang tanggal ini." />
        </Card>
      ) : (
        <>
          {/* Summary Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Card className="bg-emerald-50/40 border-emerald-100">
              <CardContent className="p-5 flex items-center gap-4">
                <div className="p-3 bg-emerald-100 rounded-xl text-emerald-700">
                  <DollarSign className="h-6 w-6" />
                </div>
                <div>
                  <div className="text-xs font-semibold text-emerald-800 uppercase">
                    Total Pemasukan Bersih Terverifikasi
                  </div>
                  <div className="text-3xl font-bold text-emerald-950 mt-1">
                    {formatRupiah(report.total_income)}
                  </div>
                  <div className="text-xs text-slate-500 mt-1">
                    Periode: {report.period_start} s.d. {report.period_end}
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-5 flex items-center gap-4">
                <div className="p-3 bg-blue-100 rounded-xl text-blue-700">
                  <CreditCard className="h-6 w-6" />
                </div>
                <div>
                  <div className="text-xs font-semibold text-slate-500 uppercase">
                    Total Transaksi Pembayaran
                  </div>
                  <div className="text-3xl font-bold text-slate-900 mt-1">
                    {report.payment_count} Transaksi
                  </div>
                  <div className="text-xs text-slate-500 mt-1">Kuitansi sah tercatat</div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Breakdown: Metode & Kategori */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* By Payment Method */}
            <Card>
              <CardHeader className="pb-3 border-b border-slate-100">
                <CardTitle className="text-base font-semibold text-slate-900 flex items-center gap-2">
                  <PieChart className="h-5 w-5 text-slate-600" />
                  Pemasukan Berdasarkan Metode Bayar
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 space-y-3">
                {Object.entries(report.by_method || {}).map(([method, amount]) => (
                  <div
                    key={method}
                    className="p-3 rounded-lg border border-slate-100 bg-slate-50 flex items-center justify-between"
                  >
                    <span className="font-semibold uppercase text-xs text-slate-700">{method}</span>
                    <span className="font-bold text-slate-900">{formatRupiah(amount)}</span>
                  </div>
                ))}
              </CardContent>
            </Card>

            {/* By Fee Category */}
            <Card>
              <CardHeader className="pb-3 border-b border-slate-100">
                <CardTitle className="text-base font-semibold text-slate-900 flex items-center gap-2">
                  <BarChart3 className="h-5 w-5 text-slate-600" />
                  Pemasukan Berdasarkan Kategori Pos
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 space-y-3">
                {Object.entries(report.by_category || {}).map(([cat, amount]) => (
                  <div
                    key={cat}
                    className="p-3 rounded-lg border border-slate-100 bg-slate-50 flex items-center justify-between"
                  >
                    <span className="font-semibold uppercase text-xs text-slate-700">{cat}</span>
                    <span className="font-bold text-slate-900">{formatRupiah(amount)}</span>
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>

          {/* Daily Trend Table */}
          <Card>
            <CardHeader className="pb-3 border-b border-slate-100">
              <CardTitle className="text-base font-semibold text-slate-900">
                Arus Pemasukan Harian
              </CardTitle>
            </CardHeader>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase font-semibold text-slate-600">
                  <tr>
                    <th className="px-4 py-3">Tanggal</th>
                    <th className="px-4 py-3 text-center">Jumlah Transaksi</th>
                    <th className="px-4 py-3 text-right">Total Pemasukan Harian</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {report.daily && report.daily.length > 0 ? (
                    report.daily.map((d) => (
                      <tr key={d.date} className="hover:bg-slate-50/50">
                        <td className="px-4 py-3 font-medium text-slate-900">{d.date}</td>
                        <td className="px-4 py-3 text-center text-slate-700">{d.count} transaksi</td>
                        <td className="px-4 py-3 text-right font-bold text-emerald-700">
                          {formatRupiah(d.total)}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={3} className="px-4 py-6 text-center text-slate-400">
                        Tidak ada transaksi pada rentang tanggal ini.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </Card>
        </>
      )}
    </div>
  );
}
