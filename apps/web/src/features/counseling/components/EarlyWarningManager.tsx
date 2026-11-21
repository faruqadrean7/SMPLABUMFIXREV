'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Filter,
  RefreshCw,
  Search,
  ShieldAlert,
  User,
  X,
  Play,
  HeartHandshake,
  TrendingDown,
  Calendar,
  AlertOctagon,
  FileText,
  Sliders,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { earlyWarningApi } from '../early-warning-api';
import {
  EarlyWarningItem,
  EarlyWarningSummary,
  EarlyWarningSeverity,
  EarlyWarningStatus,
  EarlyWarningRuleCode,
} from '../early-warning-types';

interface EarlyWarningManagerProps {
  onOpenNewCase?: (studentId: string) => void;
}

export const EarlyWarningManager: React.FC<EarlyWarningManagerProps> = ({ onOpenNewCase }) => {
  const [warnings, setWarnings] = useState<EarlyWarningItem[]>([]);
  const [summary, setSummary] = useState<EarlyWarningSummary | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isSummaryLoading, setIsSummaryLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Filters
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [severityFilter, setSeverityFilter] = useState<string>('all');
  const [ruleFilter, setRuleFilter] = useState<string>('all');

  // Modals
  const [detailWarning, setDetailWarning] = useState<EarlyWarningItem | null>(null);
  const [handlingWarning, setHandlingWarning] = useState<EarlyWarningItem | null>(null);
  const [handleStatus, setHandleStatus] = useState<'in_progress' | 'resolved' | 'dismissed'>('in_progress');
  const [handleNotes, setHandleNotes] = useState<string>('');
  const [isHandlingSubmitting, setIsHandlingSubmitting] = useState<boolean>(false);

  // Evaluate Modal
  const [isEvalModalOpen, setIsEvalModalOpen] = useState<boolean>(false);
  const [evalAttThreshold, setEvalAttThreshold] = useState<number>(75);
  const [evalMinAbsent, setEvalMinAbsent] = useState<number>(3);
  const [evalGradeDrop, setEvalGradeDrop] = useState<number>(15);
  const [evalViolationPoints, setEvalViolationPoints] = useState<number>(20);
  const [isEvaluating, setIsEvaluating] = useState<boolean>(false);

  const fetchSummary = useCallback(async () => {
    setIsSummaryLoading(true);
    try {
      const res = await earlyWarningApi.getSummary();
      setSummary(res.data);
    } catch (err: unknown) {
      console.error('Gagal memuat ringkasan peringatan dini:', err);
    } finally {
      setIsSummaryLoading(false);
    }
  }, []);

  const fetchWarnings = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await earlyWarningApi.getWarnings({
        search: searchQuery.trim() || undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        severity: severityFilter === 'all' ? undefined : severityFilter,
        rule_code: ruleFilter === 'all' ? undefined : ruleFilter,
        per_page: 50,
      });
      setWarnings(res.data || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat daftar deteksi dini siswa.';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, [searchQuery, statusFilter, severityFilter, ruleFilter]);

  useEffect(() => {
    fetchSummary();
  }, [fetchSummary]);

  useEffect(() => {
    fetchWarnings();
  }, [fetchWarnings]);

  const handleRunEvaluation = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsEvaluating(true);
    setError(null);
    try {
      const res = await earlyWarningApi.evaluateRules({
        attendance_threshold_percent: evalAttThreshold,
        min_absent_days: evalMinAbsent,
        grade_drop_threshold: evalGradeDrop,
        violation_points_threshold: evalViolationPoints,
      });
      setSuccessMessage(res.data.message || 'Evaluasi deteksi dini berhasil dijalankan.');
      setIsEvalModalOpen(false);
      fetchSummary();
      fetchWarnings();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menjalankan evaluasi deteksi dini.';
      setError(msg);
    } finally {
      setIsEvaluating(false);
    }
  };

  const handleOpenHandleModal = (warning: EarlyWarningItem) => {
    setHandlingWarning(warning);
    setHandleStatus(warning.status === 'open' || warning.status === 'aktif' ? 'in_progress' : (warning.status as any));
    setHandleNotes(warning.data?.handling_notes || '');
  };

  const handleSubmitHandleWarning = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!handlingWarning) return;

    setIsHandlingSubmitting(true);
    setError(null);
    try {
      await earlyWarningApi.handleWarning(handlingWarning.id, {
        status: handleStatus,
        notes: handleNotes.trim() || undefined,
      });

      setSuccessMessage(`Peringatan dini berhasil diperbarui ke status: ${formatStatusLabel(handleStatus)}`);
      setHandlingWarning(null);
      fetchSummary();
      fetchWarnings();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menindaklanjuti peringatan dini.';
      setError(msg);
    } finally {
      setIsHandlingSubmitting(false);
    }
  };

  const formatRuleName = (ruleCode: EarlyWarningRuleCode) => {
    switch (ruleCode) {
      case 'ATTENDANCE_LOW':
      case 'ATT_LOW':
        return 'Presensi Kritis (Kehadiran / Alpa)';
      case 'GRADE_DROP':
        return 'Penurunan Nilai Akademik';
      case 'VIOLATION_POINTS':
        return 'Akumulasi Poin Pelanggaran';
      default:
        return ruleCode;
    }
  };

  const formatStatusLabel = (status: EarlyWarningStatus) => {
    switch (status) {
      case 'open':
      case 'aktif':
        return 'Terbuka';
      case 'in_progress':
        return 'Dalam Penanganan';
      case 'resolved':
        return 'Selesai';
      case 'dismissed':
        return 'Diabaikan';
      default:
        return status;
    }
  };

  const renderSeverityBadge = (severity: EarlyWarningSeverity) => {
    if (severity === 'tinggi') {
      return (
        <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 gap-1 font-semibold text-[11px]">
          <AlertOctagon className="h-3 w-3" /> Tinggi
        </Badge>
      );
    }
    return (
      <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 gap-1 font-semibold text-[11px]">
        <AlertTriangle className="h-3 w-3" /> Sedang
      </Badge>
    );
  };

  const renderStatusBadge = (status: EarlyWarningStatus) => {
    switch (status) {
      case 'open':
      case 'aktif':
        return (
          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 gap-1 text-[11px]">
            <Clock className="h-3 w-3" /> Terbuka
          </Badge>
        );
      case 'in_progress':
        return (
          <Badge variant="outline" className="bg-purple-50 text-purple-700 border-purple-200 gap-1 text-[11px]">
            <RefreshCw className="h-3 w-3" /> Dalam Penanganan
          </Badge>
        );
      case 'resolved':
        return (
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 gap-1 text-[11px]">
            <CheckCircle2 className="h-3 w-3" /> Selesai
          </Badge>
        );
      case 'dismissed':
        return (
          <Badge variant="outline" className="bg-slate-50 text-slate-600 border-slate-200 gap-1 text-[11px]">
            <X className="h-3 w-3" /> Diabaikan
          </Badge>
        );
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  return (
    <div className="space-y-6">
      {/* Alert Notifikasi */}
      {error && (
        <div className="rounded-md bg-red-50 p-3 text-xs text-red-700 border border-red-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-red-500 shrink-0" />
            <span>{error}</span>
          </div>
          <button onClick={() => setError(null)} className="text-red-500 hover:text-red-800">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {successMessage && (
        <div className="rounded-md bg-emerald-50 p-3 text-xs text-emerald-800 border border-emerald-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0" />
            <span>{successMessage}</span>
          </div>
          <button onClick={() => setSuccessMessage(null)} className="text-emerald-500 hover:text-emerald-800">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Total Perlu Perhatian</CardTitle>
            <ShieldAlert className="h-4 w-4 text-indigo-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">
              {isSummaryLoading ? '...' : summary?.total_active ?? 0}
            </div>
            <p className="text-[11px] text-slate-500 mt-1">Siswa membutuhkan pendampingan</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-rose-600">Risiko Tinggi</CardTitle>
            <AlertOctagon className="h-4 w-4 text-rose-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-rose-600">
              {isSummaryLoading ? '...' : summary?.high_severity_count ?? 0}
            </div>
            <p className="text-[11px] text-slate-500 mt-1">Prioritas penanganan segera</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-blue-600">Status Terbuka (Open)</CardTitle>
            <Clock className="h-4 w-4 text-blue-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">
              {isSummaryLoading ? '...' : summary?.open_count ?? 0}
            </div>
            <p className="text-[11px] text-slate-500 mt-1">Menunggu respon tindak lanjut</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-purple-600">Dalam Penanganan</CardTitle>
            <RefreshCw className="h-4 w-4 text-purple-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-purple-600">
              {isSummaryLoading ? '...' : summary?.in_progress_count ?? 0}
            </div>
            <p className="text-[11px] text-slate-500 mt-1">Sedang dibina konselor/walas</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-emerald-600">Selesai Ditangani</CardTitle>
            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">
              {isSummaryLoading ? '...' : summary?.resolved_count ?? 0}
            </div>
            <p className="text-[11px] text-slate-500 mt-1">Masalah berhasil terselesaikan</p>
          </CardContent>
        </Card>
      </div>

      {/* Action Bar & Filters */}
      <div className="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
        <div className="flex flex-wrap items-center gap-2 flex-1">
          <div className="relative w-full sm:w-60">
            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
            <Input
              placeholder="Cari siswa atau NIS..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-8 text-xs h-9 bg-white"
            />
          </div>

          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
          >
            <option value="all">Semua Status</option>
            <option value="open">Terbuka (Open)</option>
            <option value="in_progress">Dalam Penanganan</option>
            <option value="resolved">Selesai Ditangani</option>
            <option value="dismissed">Diabaikan</option>
          </select>

          <select
            value={severityFilter}
            onChange={(e) => setSeverityFilter(e.target.value)}
            className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
          >
            <option value="all">Semua Tingkat Keparahan</option>
            <option value="tinggi">Tinggi (Kritis)</option>
            <option value="sedang">Sedang</option>
          </select>

          <select
            value={ruleFilter}
            onChange={(e) => setRuleFilter(e.target.value)}
            className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
          >
            <option value="all">Semua Jenis Aturan</option>
            <option value="ATTENDANCE_LOW">Presensi Kritis</option>
            <option value="GRADE_DROP">Penurunan Nilai</option>
            <option value="VIOLATION_POINTS">Poin Pelanggaran</option>
          </select>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              fetchSummary();
              fetchWarnings();
            }}
            className="text-xs h-9 bg-white"
            title="Muat ulang data"
          >
            <RefreshCw className="h-3.5 w-3.5" />
          </Button>

          <Button
            size="sm"
            onClick={() => setIsEvalModalOpen(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white gap-1.5 text-xs h-9"
          >
            <Play className="h-3.5 w-3.5" /> Jalankan Evaluasi Aturan
          </Button>
        </div>
      </div>

      {/* Warnings Table */}
      {isLoading ? (
        <div className="text-center py-12 text-xs text-slate-500 bg-white rounded-lg border border-slate-200">
          <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
          Memuat data deteksi dini siswa...
        </div>
      ) : warnings.length === 0 ? (
        <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
          <ShieldAlert className="h-10 w-10 text-emerald-400 mx-auto mb-2" />
          <h3 className="text-sm font-semibold text-slate-800">Tidak Ada Peringatan Dini Aktif</h3>
          <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">
            Seluruh siswa berada dalam rentang indikator presensi, akademik, dan kedisiplinan yang sehat. Anda dapat menjalankan evaluasi berkala untuk pemantauan rutin.
          </p>
          <Button
            size="sm"
            onClick={() => setIsEvalModalOpen(true)}
            className="mt-3 bg-indigo-600 hover:bg-indigo-700 text-white gap-1 text-xs"
          >
            <Play className="h-3.5 w-3.5" /> Jalankan Evaluasi Sekarang
          </Button>
        </div>
      ) : (
        <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="bg-slate-50 text-slate-600 border-b border-slate-200">
                <tr>
                  <th className="px-4 py-3 font-semibold">Siswa & Kelas</th>
                  <th className="px-4 py-3 font-semibold">Indikator Deteksi</th>
                  <th className="px-4 py-3 font-semibold text-center">Tingkat Risiko</th>
                  <th className="px-4 py-3 font-semibold text-center">Status</th>
                  <th className="px-4 py-3 font-semibold">Ringkasan Bukti / Data</th>
                  <th className="px-4 py-3 font-semibold">Penangan</th>
                  <th className="px-4 py-3 font-semibold text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {warnings.map((w) => {
                  const className = w.student?.classes?.[0]?.name || 'Tidak ada kelas';
                  return (
                    <tr key={w.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-900">
                        <div>{w.student?.full_name || 'Nama Tidak Diketahui'}</div>
                        <div className="text-[11px] text-slate-500 mt-0.5">
                          NIS: {w.student?.nis || '-'} • {className}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="font-medium text-slate-800">{formatRuleName(w.rule_code)}</div>
                        <div className="text-[11px] text-slate-500 font-mono mt-0.5">{w.rule_code}</div>
                      </td>
                      <td className="px-4 py-3 text-center">{renderSeverityBadge(w.severity)}</td>
                      <td className="px-4 py-3 text-center">{renderStatusBadge(w.status)}</td>
                      <td className="px-4 py-3 text-slate-600 max-w-xs">
                        {w.rule_code.includes('ATT') && (
                          <div className="text-[11px]">
                            Alpa: <strong className="text-rose-600">{w.data?.alpa_count ?? 0} hari</strong>
                            {w.data?.attendance_percent !== undefined && ` (${w.data.attendance_percent}%)`}
                          </div>
                        )}
                        {w.rule_code === 'GRADE_DROP' && (
                          <div className="text-[11px]">
                            Penurunan: <strong className="text-rose-600">-{w.data?.drop_points ?? 0} poin</strong>
                            <span className="text-slate-400"> (Rata-rata: {w.data?.previous_average} → {w.data?.recent_average})</span>
                          </div>
                        )}
                        {w.rule_code === 'VIOLATION_POINTS' && (
                          <div className="text-[11px]">
                            Akumulasi Poin: <strong className="text-rose-600">{w.data?.total_points ?? 0} poin</strong>
                            <span className="text-slate-400"> (Batas ambang: {w.data?.threshold ?? 20})</span>
                          </div>
                        )}
                        {w.data?.handling_notes && (
                          <div className="text-[10px] text-slate-500 truncate mt-1 italic">
                            Catatan: {w.data.handling_notes}
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3 text-slate-600 text-[11px]">
                        {w.handler?.name || w.handler?.username || <span className="text-slate-400">- Belum ada -</span>}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDetailWarning(w)}
                            className="h-7 px-2 text-[11px] text-slate-600 hover:text-indigo-600"
                            title="Lihat detail indikator"
                          >
                            <FileText className="h-3.5 w-3.5 mr-1" /> Detail
                          </Button>

                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenHandleModal(w)}
                            className="h-7 px-2 text-[11px] text-indigo-600 border-indigo-200 hover:bg-indigo-50"
                          >
                            Tindak Lanjuti
                          </Button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* MODAL 1: DETAIL PERINGATAN DINI */}
      {detailWarning && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <ShieldAlert className="h-4 w-4 text-indigo-600" />
                <h3 className="font-semibold text-slate-800 text-sm">Detail Peringatan Dini Siswa</h3>
              </div>
              <button onClick={() => setDetailWarning(null)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-4 text-xs">
              <div className="bg-slate-50 p-3 rounded-md border border-slate-200 space-y-1.5">
                <div className="text-slate-500">Informasi Siswa:</div>
                <div className="text-sm font-semibold text-slate-900">{detailWarning.student?.full_name}</div>
                <div className="text-slate-600">
                  NIS: {detailWarning.student?.nis || '-'} • Kelas: {detailWarning.student?.classes?.[0]?.name || '-'}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="border border-slate-200 rounded p-2.5">
                  <span className="text-slate-500 block mb-1">Tingkat Risiko:</span>
                  {renderSeverityBadge(detailWarning.severity)}
                </div>
                <div className="border border-slate-200 rounded p-2.5">
                  <span className="text-slate-500 block mb-1">Status:</span>
                  {renderStatusBadge(detailWarning.status)}
                </div>
              </div>

              <div className="border border-slate-200 rounded-md p-3.5 space-y-2 bg-indigo-50/30">
                <div className="font-semibold text-indigo-900 flex items-center gap-1.5">
                  <FileText className="h-4 w-4 text-indigo-600" />
                  Bukti & Indikator: {formatRuleName(detailWarning.rule_code)}
                </div>
                <p className="text-slate-700 leading-relaxed">
                  {detailWarning.data?.rule || 'Terdeteksi anomali pada rekam perkembangan siswa.'}
                </p>

                <div className="mt-2 pt-2 border-t border-indigo-100 text-[11px] space-y-1 text-slate-600">
                  {detailWarning.rule_code.includes('ATT') && (
                    <>
                      <div>• Total Alpa: <strong className="text-rose-600">{detailWarning.data?.alpa_count} hari</strong></div>
                      <div>• Izin / Sakit: {detailWarning.data?.izin_count ?? 0} izin, {detailWarning.data?.sakit_count ?? 0} sakit</div>
                      {detailWarning.data?.attendance_percent !== undefined && (
                        <div>• Persentase Kehadiran: <strong>{detailWarning.data.attendance_percent}%</strong> (Ambang minimum: {detailWarning.data?.threshold_percent}%)</div>
                      )}
                    </>
                  )}
                  {detailWarning.rule_code === 'GRADE_DROP' && (
                    <>
                      <div>• Rata-rata Sebelumnya: {detailWarning.data?.previous_average}</div>
                      <div>• Rata-rata Terkini: {detailWarning.data?.recent_average}</div>
                      <div>• Penurunan: <strong className="text-rose-600">{detailWarning.data?.drop_points} poin</strong> (Ambang: {detailWarning.data?.threshold} poin)</div>
                    </>
                  )}
                  {detailWarning.rule_code === 'VIOLATION_POINTS' && (
                    <>
                      <div>• Total Poin Pelanggaran: <strong className="text-rose-600">{detailWarning.data?.total_points} poin</strong></div>
                      <div>• Batas Ambang Peringatan: {detailWarning.data?.threshold} poin</div>
                    </>
                  )}
                </div>
              </div>

              {detailWarning.data?.handling_notes && (
                <div className="bg-amber-50/60 border border-amber-200 rounded-md p-3 text-[11px] space-y-1">
                  <div className="font-semibold text-amber-900">Catatan Penanganan:</div>
                  <div className="text-amber-800">{detailWarning.data.handling_notes}</div>
                  {detailWarning.data.handled_at && (
                    <div className="text-[10px] text-amber-600 mt-1">
                      Waktu tindak lanjut: {new Date(detailWarning.data.handled_at).toLocaleString('id-ID')}
                    </div>
                  )}
                </div>
              )}
            </div>

            <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-between items-center">
              {onOpenNewCase && detailWarning.student_id ? (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    const studentId = detailWarning.student_id;
                    setDetailWarning(null);
                    onOpenNewCase(studentId);
                  }}
                  className="text-xs gap-1.5 border-indigo-200 text-indigo-700 hover:bg-indigo-50"
                >
                  <HeartHandshake className="h-3.5 w-3.5" /> Buka Kasus Konseling
                </Button>
              ) : <div />}

              <Button size="sm" onClick={() => setDetailWarning(null)} className="text-xs">
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: TINDAK LANJUTI ALERT */}
      {handlingWarning && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <RefreshCw className="h-4 w-4 text-indigo-600" />
                <h3 className="font-semibold text-slate-800 text-sm">Tindak Lanjuti Peringatan Siswa</h3>
              </div>
              <button onClick={() => setHandlingWarning(null)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSubmitHandleWarning}>
              <div className="p-5 space-y-4 text-xs">
                <div>
                  <span className="text-slate-500 block">Siswa:</span>
                  <span className="font-semibold text-slate-900 text-sm">
                    {handlingWarning.student?.full_name} ({handlingWarning.student?.classes?.[0]?.name || '-'})
                  </span>
                </div>

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Perbarui Status Penanganan *</label>
                  <select
                    value={handleStatus}
                    onChange={(e) => setHandleStatus(e.target.value as any)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-800"
                    required
                  >
                    <option value="in_progress">Dalam Penanganan (Sedang Dibimbing/Dipanggil)</option>
                    <option value="resolved">Selesai Ditangani (Masalah Teratasi)</option>
                    <option value="dismissed">Diabaikan (Klarifikasi Khusus / False Positive)</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="font-semibold text-slate-700">Catatan Tindak Lanjut & Rencana Pembinaan</label>
                  <Textarea
                    placeholder="Contoh: Telah dikonfirmasi kepada wali kelas dan dijadwalkan pertemuan konseling bimbingan belajar."
                    value={handleNotes}
                    onChange={(e) => setHandleNotes(e.target.value)}
                    rows={4}
                    className="text-xs resize-none"
                  />
                  <p className="text-[11px] text-slate-400">
                    Gunakan kalimat netral dan mendukung perkembangan potensi siswa tanpa stigma negatif.
                  </p>
                </div>
              </div>

              <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setHandlingWarning(null)}
                  disabled={isHandlingSubmitting}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isHandlingSubmitting}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5"
                >
                  {isHandlingSubmitting ? (
                    <>
                      <RefreshCw className="h-3.5 w-3.5 animate-spin" /> Menyimpan...
                    </>
                  ) : (
                    'Simpan Tindak Lanjut'
                  )}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 3: EVALUASI ATURAN MANUAL DENGAN AMBANG BATAS */}
      {isEvalModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <Sliders className="h-4 w-4 text-indigo-600" />
                <h3 className="font-semibold text-slate-800 text-sm">Jalankan Evaluasi Deteksi Dini</h3>
              </div>
              <button onClick={() => setIsEvalModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleRunEvaluation}>
              <div className="p-5 space-y-3.5 text-xs">
                <p className="text-slate-600 leading-relaxed text-[11px]">
                  Sistem akan memindai seluruh data kehadiran 30 hari terakhir, riwayat nilai ujian, dan catatan poin disiplin siswa aktif. Anda dapat menyesuaikan ambang batas berikut:
                </p>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Batas Minimum Kehadiran (%)</label>
                  <Input
                    type="number"
                    min="1"
                    max="100"
                    value={evalAttThreshold}
                    onChange={(e) => setEvalAttThreshold(parseFloat(e.target.value) || 0)}
                    className="text-xs h-8"
                  />
                  <span className="text-[10px] text-slate-400">Standar sekolah: 75%</span>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Minimum Hari Alpa (Hari)</label>
                  <Input
                    type="number"
                    min="1"
                    max="30"
                    value={evalMinAbsent}
                    onChange={(e) => setEvalMinAbsent(parseInt(e.target.value, 10) || 0)}
                    className="text-xs h-8"
                  />
                  <span className="text-[10px] text-slate-400">Standar deteksi: 3 hari alpa dalam 30 hari</span>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Batas Penurunan Rata-rata Nilai (Poin)</label>
                  <Input
                    type="number"
                    min="1"
                    max="100"
                    value={evalGradeDrop}
                    onChange={(e) => setEvalGradeDrop(parseFloat(e.target.value) || 0)}
                    className="text-xs h-8"
                  />
                  <span className="text-[10px] text-slate-400">Standar: penurunan ≥ 15 poin antara dua periode</span>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Batas Akumulasi Poin Pelanggaran</label>
                  <Input
                    type="number"
                    min="1"
                    max="500"
                    value={evalViolationPoints}
                    onChange={(e) => setEvalViolationPoints(parseInt(e.target.value, 10) || 0)}
                    className="text-xs h-8"
                  />
                  <span className="text-[10px] text-slate-400">Standar: akumulasi ≥ 20 poin pelanggaran</span>
                </div>
              </div>

              <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsEvalModalOpen(false)}
                  disabled={isEvaluating}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isEvaluating}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5"
                >
                  {isEvaluating ? (
                    <>
                      <RefreshCw className="h-3.5 w-3.5 animate-spin" /> Mengevaluasi Data...
                    </>
                  ) : (
                    <>
                      <Play className="h-3.5 w-3.5" /> Mulai Evaluasi
                    </>
                  )}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
