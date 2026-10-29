'use client';

import React, { useEffect, useState, useRef } from 'react';
import Link from 'next/link';
import {
  Activity,
  RefreshCw,
  ArrowLeft,
  Users,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Lock,
  Unlock,
  PlusCircle,
  Square,
  RotateCcw,
  Key,
  Wifi,
  WifiOff,
  Search,
  Eye,
  ShieldAlert,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cbtApi } from '@/features/cbt/cbt-api';
import { ExamItem, ExamMonitorSessionItem } from '@/types/cbt';

export default function LiveCbtMonitorPage() {
  const [exams, setExams] = useState<ExamItem[]>([]);
  const [selectedExamId, setSelectedExamId] = useState<string>('');
  const [monitorData, setMonitorData] = useState<{
    total_participants: number;
    active_count: number;
    submitted_count: number;
    blocked_count: number;
    sessions: ExamMonitorSessionItem[];
  } | null>(null);

  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(false);
  const [autoRefresh, setAutoRefresh] = useState<boolean>(true);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [feedbackMsg, setFeedbackMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Proctor Action Modal
  const [activeSession, setActiveSession] = useState<ExamMonitorSessionItem | null>(null);
  const [actionType, setActionType] = useState<'unlock' | 'force_submit' | 'extra_time' | 'reset' | null>(null);
  const [extraMinutes, setExtraMinutes] = useState<number>(15);
  const [actionReason, setActionReason] = useState<string>('');
  const [isSubmittingAction, setIsSubmittingAction] = useState<boolean>(false);

  const autoRefreshIntervalRef = useRef<NodeJS.Timeout | null>(null);

  const fetchActiveExams = async () => {
    setIsLoading(true);
    try {
      const res = await cbtApi.getExams({ per_page: 50 });
      const loaded = res.data || [];
      setExams(loaded);
      if (loaded.length > 0 && !selectedExamId) {
        // Pilih ujian yang aktif atau pertama
        const active = loaded.find((e) => e.status === 'active' || e.status === 'berlangsung') || loaded[0];
        setSelectedExamId(active.id);
      }
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memuat daftar ujian.' });
    } finally {
      setIsLoading(false);
    }
  };

  const fetchMonitor = async (examId: string, background = false) => {
    if (!examId) return;
    if (!background) setIsRefreshing(true);
    try {
      const res = await cbtApi.getExamMonitor(examId);
      setMonitorData(res.data);
    } catch (err: any) {
      if (!background) {
        setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal memuat live monitoring.' });
      }
    } finally {
      if (!background) setIsRefreshing(false);
    }
  };

  useEffect(() => {
    fetchActiveExams();
  }, []);

  useEffect(() => {
    if (selectedExamId) {
      fetchMonitor(selectedExamId);
    }
  }, [selectedExamId]);

  // Setup auto-refresh polling every 5 seconds
  useEffect(() => {
    if (autoRefresh && selectedExamId) {
      autoRefreshIntervalRef.current = setInterval(() => {
        fetchMonitor(selectedExamId, true);
      }, 5000);
    }
    return () => {
      if (autoRefreshIntervalRef.current) {
        clearInterval(autoRefreshIntervalRef.current);
      }
    };
  }, [autoRefresh, selectedExamId]);

  const handleProctorAction = async () => {
    if (!activeSession || !actionType) return;

    setIsSubmittingAction(true);
    try {
      await cbtApi.manageSession(
        activeSession.session_id,
        actionType,
        actionType === 'extra_time' ? extraMinutes : undefined,
        actionReason || undefined
      );
      setFeedbackMsg({
        type: 'success',
        text: `Aksi proctor (${actionType}) berhasil diterapkan untuk siswa ${activeSession.student_name}.`,
      });
      setActiveSession(null);
      setActionType(null);
      fetchMonitor(selectedExamId);
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err?.message || 'Gagal menjalankan aksi proctor.' });
    } finally {
      setIsSubmittingAction(false);
    }
  };

  const selectedExam = exams.find((e) => e.id === selectedExamId);
  const activeRoom = selectedExam?.rooms && selectedExam.rooms.length > 0 ? selectedExam.rooms[0] : null;

  const filteredSessions = (monitorData?.sessions || []).filter((s) => {
    if (!searchQuery) return true;
    const q = searchQuery.toLowerCase();
    return s.student_name.toLowerCase().includes(q) || s.nis.toLowerCase().includes(q);
  });

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <Link href="/admin/cbt" className="text-slate-500 hover:text-slate-800 text-xs flex items-center gap-1">
              <ArrowLeft className="h-3 w-3" /> Kembali ke CBT Hub
            </Link>
          </div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900">
            Live Monitor Lab Ujian CBT (ADM-CBT-04) 🖥️
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Pengawasan real-time layar komputer siswa di laboratorium: pantau pengerjaan, sisa waktu, dan deteksi anomali perpindahan tab.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <label className="flex items-center gap-1.5 text-xs text-slate-600 bg-white border border-slate-200 px-3 py-1.5 rounded-lg cursor-pointer">
            <input
              type="checkbox"
              checked={autoRefresh}
              onChange={(e) => setAutoRefresh(e.target.checked)}
              className="rounded text-blue-600"
            />
            <span>Auto Refresh (5s)</span>
          </label>

          <Button
            size="sm"
            variant="outline"
            onClick={() => fetchMonitor(selectedExamId)}
            disabled={isRefreshing}
            className="text-xs gap-1.5"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
        </div>
      </div>

      {feedbackMsg && (
        <div
          className={`p-3 rounded-lg text-xs flex items-center justify-between ${
            feedbackMsg.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
              : 'bg-rose-50 text-rose-800 border border-rose-200'
          }`}
        >
          <span>{feedbackMsg.text}</span>
          <button onClick={() => setFeedbackMsg(null)} className="text-slate-400 hover:text-slate-600">
            ✕
          </button>
        </div>
      )}

      {/* Selector & Room Status Card */}
      <div className="p-4 rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex flex-col sm:flex-row sm:items-center gap-3">
          <div>
            <label className="text-[11px] font-semibold text-slate-500 block">Pilih Ujian Aktif</label>
            <select
              value={selectedExamId}
              onChange={(e) => setSelectedExamId(e.target.value)}
              className="text-xs rounded-lg border border-slate-300 p-2 font-semibold text-slate-900 bg-white mt-0.5"
            >
              {exams.map((ex) => (
                <option key={ex.id} value={ex.id}>
                  {ex.title} ({ex.status})
                </option>
              ))}
            </select>
          </div>

          {activeRoom && (
            <div className="pl-0 sm:pl-3 sm:border-l border-slate-200">
              <label className="text-[11px] font-semibold text-slate-500 block">Token Ruang Lab</label>
              <div className="flex items-center gap-2 mt-0.5">
                <span className="font-mono font-bold text-sm text-blue-700 bg-blue-50 px-2.5 py-1 rounded border border-blue-200">
                  {activeRoom.token}
                </span>
                <span className="text-[11px] text-slate-500">
                  {activeRoom.room?.name || 'Lab Komputer 1'}
                </span>
              </div>
            </div>
          )}
        </div>

        <div className="relative w-full md:w-64">
          <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
          <Input
            placeholder="Cari siswa atau NIS..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-8 text-xs h-8"
          />
        </div>
      </div>

      {/* Stats Summary */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <Card className="border-l-4 border-l-blue-500">
          <CardContent className="p-4">
            <div className="text-[11px] font-semibold text-slate-500 uppercase">Total Peserta</div>
            <div className="text-2xl font-bold text-slate-900 mt-1">
              {monitorData?.total_participants ?? 0} Siswa
            </div>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-emerald-500">
          <CardContent className="p-4">
            <div className="text-[11px] font-semibold text-slate-500 uppercase">Sedang Mengerjakan</div>
            <div className="text-2xl font-bold text-emerald-600 mt-1">
              {monitorData?.active_count ?? 0} Sesi
            </div>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-indigo-500">
          <CardContent className="p-4">
            <div className="text-[11px] font-semibold text-slate-500 uppercase">Telah Dikumpulkan</div>
            <div className="text-2xl font-bold text-indigo-600 mt-1">
              {monitorData?.submitted_count ?? 0} Selesai
            </div>
          </CardContent>
        </Card>

        <Card className="border-l-4 border-l-rose-500">
          <CardContent className="p-4">
            <div className="text-[11px] font-semibold text-slate-500 uppercase">Terkunci / Pelanggaran</div>
            <div className="text-2xl font-bold text-rose-600 mt-1">
              {monitorData?.blocked_count ?? 0} Anomali
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Workstation Cards Grid */}
      <div className="space-y-3">
        <div className="flex items-center justify-between text-xs text-slate-600">
          <span className="font-semibold">Grid Meja Komputer Lab ({filteredSessions.length} Terpantau)</span>
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1">
              <span className="h-2 w-2 rounded-full bg-emerald-500"></span> Online
            </span>
            <span className="flex items-center gap-1">
              <span className="h-2 w-2 rounded-full bg-slate-300"></span> Offline
            </span>
          </div>
        </div>

        {filteredSessions.length === 0 ? (
          <div className="p-12 text-center text-xs text-slate-500 bg-white rounded-xl border border-slate-200">
            Belum ada aktivitas peserta pada ujian ini. Pastikan siswa telah memasukkan token ujian pada workspace CBT.
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {filteredSessions.map((session, idx) => {
              const isWorking = session.status === 'in_progress';
              const isFinished = session.status === 'submitted' || session.status === 'force_submitted';
              const isBlocked = session.status === 'blocked';

              const mins = Math.floor(session.remaining_seconds / 60);
              const secs = session.remaining_seconds % 60;
              const timeDisplay = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;

              return (
                <div
                  key={session.session_id}
                  className={`p-4 rounded-xl border bg-white shadow-sm transition-all flex flex-col justify-between ${
                    isBlocked
                      ? 'border-rose-400 bg-rose-50/20 ring-1 ring-rose-400'
                      : isWorking
                      ? 'border-emerald-300'
                      : 'border-slate-200'
                  }`}
                >
                  <div className="space-y-2.5">
                    {/* Header Card: Seat & Online Status */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-slate-400" />
                        <span className="font-bold text-xs text-slate-700">Meja #{idx + 1}</span>
                      </div>
                      <div className="flex items-center gap-1.5">
                        {session.is_online ? (
                          <Badge variant="outline" className="text-[10px] bg-emerald-50 text-emerald-700 border-emerald-200 gap-1 py-0">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /> Live
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="text-[10px] bg-slate-50 text-slate-400 py-0">
                            Offline
                          </Badge>
                        )}
                      </div>
                    </div>

                    {/* Student Name */}
                    <div>
                      <div className="font-bold text-xs text-slate-900 truncate" title={session.student_name}>
                        {session.student_name}
                      </div>
                      <div className="text-[10px] text-slate-500">NIS: {session.nis}</div>
                    </div>

                    {/* Progress Bar */}
                    <div className="space-y-1">
                      <div className="flex items-center justify-between text-[10px] text-slate-600">
                        <span>
                          Terjawab: <b>{session.answered_count}</b> / {session.total_questions}
                        </span>
                        <span>{session.progress_percent}%</span>
                      </div>
                      <div className="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                        <div
                          className="bg-blue-600 h-1.5 rounded-full transition-all"
                          style={{ width: `${session.progress_percent}%` }}
                        />
                      </div>
                    </div>

                    {/* Time & Violations */}
                    <div className="flex items-center justify-between text-[11px] pt-1 border-t border-slate-100">
                      <div className="flex items-center gap-1 text-slate-600">
                        <Clock className="h-3 w-3" />
                        <span className="font-mono font-medium">{timeDisplay}</span>
                      </div>
                      {session.blur_count > 0 && (
                        <span className="flex items-center gap-1 text-[10px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded">
                          <AlertTriangle className="h-3 w-3" />
                          Tab Blur: {session.blur_count}x
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Proctor Action Buttons */}
                  <div className="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between gap-1">
                    {isBlocked ? (
                      <Button
                        size="sm"
                        onClick={() => {
                          setActiveSession(session);
                          setActionType('unlock');
                        }}
                        className="w-full h-7 text-[11px] bg-emerald-600 hover:bg-emerald-500 text-white gap-1"
                      >
                        <Unlock className="h-3 w-3" /> Buka Kunci
                      </Button>
                    ) : (
                      <>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            setActiveSession(session);
                            setActionType('extra_time');
                          }}
                          className="h-7 text-[10px] text-blue-700 hover:bg-blue-50 px-1.5"
                          title="Tambah waktu pengerjaan"
                        >
                          + Waktu
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            setActiveSession(session);
                            setActionType('force_submit');
                          }}
                          className="h-7 text-[10px] text-amber-700 hover:bg-amber-50 px-1.5"
                          title="Paksa kirim jawaban"
                        >
                          Paksa Submit
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            setActiveSession(session);
                            setActionType('reset');
                          }}
                          className="h-7 text-[10px] text-rose-700 hover:bg-rose-50 px-1.5"
                          title="Reset sesi pengerjaan"
                        >
                          Reset
                        </Button>
                      </>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Proctor Action Confirmation Modal */}
      {activeSession && actionType && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <h3 className="font-bold text-slate-900 text-sm">
                {actionType === 'unlock'
                  ? 'Buka Kunci Sesi Siswa'
                  : actionType === 'extra_time'
                  ? 'Tambah Waktu Ujian'
                  : actionType === 'force_submit'
                  ? 'Paksa Kumpulkan Jawaban'
                  : 'Reset Sesi Ujian'}
              </h3>
              <button
                onClick={() => {
                  setActiveSession(null);
                  setActionType(null);
                }}
                className="text-slate-400 hover:text-slate-600"
              >
                ✕
              </button>
            </div>

            <div className="text-xs text-slate-600 space-y-2">
              <p>
                Konfirmasi aksi pengawas untuk siswa: <b>{activeSession.student_name}</b> (NIS: {activeSession.nis}).
              </p>

              {actionType === 'extra_time' && (
                <div>
                  <label className="text-[11px] font-semibold text-slate-700">Tambahan Waktu (Menit):</label>
                  <Input
                    type="number"
                    min="1"
                    max="60"
                    value={extraMinutes}
                    onChange={(e) => setExtraMinutes(parseInt(e.target.value, 10) || 15)}
                    className="text-xs mt-1"
                  />
                </div>
              )}

              <div>
                <label className="text-[11px] font-semibold text-slate-700">Alasan / Catatan Proctor:</label>
                <Input
                  value={actionReason}
                  onChange={(e) => setActionReason(e.target.value)}
                  placeholder="Contoh: Gangguan jaringan atau izin pengawas"
                  className="text-xs mt-1"
                />
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t">
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setActiveSession(null);
                  setActionType(null);
                }}
                className="text-xs"
              >
                Batal
              </Button>
              <Button
                size="sm"
                disabled={isSubmittingAction}
                onClick={handleProctorAction}
                className="bg-blue-600 hover:bg-blue-500 text-white text-xs"
              >
                {isSubmittingAction ? 'Menerapkan...' : 'Terapkan Aksi'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
