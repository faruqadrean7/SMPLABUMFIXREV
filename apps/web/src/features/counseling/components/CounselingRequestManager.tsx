'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  Calendar,
  Clock,
  Filter,
  RefreshCw,
  Search,
  User,
  X,
  CheckCircle2,
  AlertTriangle,
  FileText,
  HeartHandshake,
  UserCheck,
  Send,
  Plus,
  CalendarCheck,
  Ban,
  ArrowRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { counselingApi } from '../counseling-api';
import {
  CounselingRequestItem,
  CounselingRequestStatus,
  CounselingRequesterRole,
  CounselingCategory,
} from '../types';
import { StudentItem } from '@/types/master';
import { studentsApi } from '@/features/master/students-api';

interface CounselingRequestManagerProps {
  onCaseCreated?: () => void;
}

export const CounselingRequestManager: React.FC<CounselingRequestManagerProps> = ({ onCaseCreated }) => {
  const [requests, setRequests] = useState<CounselingRequestItem[]>([]);
  const [students, setStudents] = useState<StudentItem[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Filters
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Modals
  const [isNewRequestModalOpen, setIsNewRequestModalOpen] = useState<boolean>(false);
  const [detailRequest, setDetailRequest] = useState<CounselingRequestItem | null>(null);
  const [schedulingRequest, setSchedulingRequest] = useState<CounselingRequestItem | null>(null);
  const [rejectingRequest, setRejectingRequest] = useState<CounselingRequestItem | null>(null);
  const [completingRequest, setCompletingRequest] = useState<CounselingRequestItem | null>(null);

  // Forms State
  // 1. New Request
  const [formStudentId, setFormStudentId] = useState<string>('');
  const [formTopic, setFormTopic] = useState<string>('');
  const [formPrefDate, setFormPrefDate] = useState<string>('');
  const [formPrefTime, setFormPrefTime] = useState<string>('');
  const [formNotes, setFormNotes] = useState<string>('');
  const [isSubmittingNew, setIsSubmittingNew] = useState<boolean>(false);

  // 2. Schedule
  const [scheduleDateTime, setScheduleDateTime] = useState<string>('');
  const [scheduleNotes, setScheduleNotes] = useState<string>('');
  const [isSubmittingSchedule, setIsSubmittingSchedule] = useState<boolean>(false);

  // 3. Reject
  const [rejectReason, setRejectReason] = useState<string>('');
  const [isSubmittingReject, setIsSubmittingReject] = useState<boolean>(false);

  // 4. Complete
  const [completeNotes, setCompleteNotes] = useState<string>('');
  const [createCase, setCreateCase] = useState<boolean>(true);
  const [caseCategory, setCaseCategory] = useState<CounselingCategory>('Pribadi');
  const [caseSummary, setCaseSummary] = useState<string>('');
  const [sessionNotes, setSessionNotes] = useState<string>('');
  const [followUp, setFollowUp] = useState<string>('');
  const [isSubmittingComplete, setIsSubmittingComplete] = useState<boolean>(false);

  const fetchRequests = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [reqsRes, studentsRes] = await Promise.all([
        counselingApi.getRequests({
          status: statusFilter === 'all' ? undefined : (statusFilter as CounselingRequestStatus),
          per_page: 50,
        }),
        studentsApi.list({ limit: 100 }),
      ]);

      let items = reqsRes.data || [];
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        items = items.filter(
          (r) =>
            r.student?.full_name?.toLowerCase().includes(q) ||
            r.student?.nis?.toLowerCase().includes(q) ||
            r.topic?.toLowerCase().includes(q)
        );
      }
      setRequests(items);
      setStudents(studentsRes.data || []);
      if (studentsRes.data && studentsRes.data.length > 0 && !formStudentId) {
        setFormStudentId(studentsRes.data[0].id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat daftar permintaan konseling';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, [statusFilter, searchQuery, formStudentId]);

  useEffect(() => {
    fetchRequests();
  }, [fetchRequests]);

  // Submit Handlers
  const handleCreateRequest = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formStudentId || !formTopic.trim()) {
      setError('Siswa dan topik konseling wajib diisi.');
      return;
    }

    setIsSubmittingNew(true);
    setError(null);
    try {
      await counselingApi.createRequest({
        student_id: formStudentId,
        topic: formTopic.trim(),
        preferred_date: formPrefDate || undefined,
        preferred_time: formPrefTime || undefined,
        notes: formNotes.trim() || undefined,
      });

      setSuccessMessage('Permintaan konseling berhasil dibuat.');
      setIsNewRequestModalOpen(false);
      setFormTopic('');
      setFormNotes('');
      fetchRequests();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal membuat permintaan konseling.';
      setError(msg);
    } finally {
      setIsSubmittingNew(false);
    }
  };

  const handleOpenScheduleModal = (req: CounselingRequestItem) => {
    setSchedulingRequest(req);
    const defaultDate = req.preferred_date ? `${req.preferred_date}T09:00` : new Date().toISOString().slice(0, 16);
    setScheduleDateTime(defaultDate);
    setScheduleNotes(req.notes || '');
  };

  const handleSubmitSchedule = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!schedulingRequest || !scheduleDateTime) return;

    setIsSubmittingSchedule(true);
    setError(null);
    try {
      await counselingApi.scheduleRequest(schedulingRequest.id, {
        scheduled_at: scheduleDateTime,
        notes: scheduleNotes.trim() || undefined,
      });

      setSuccessMessage('Permintaan konseling berhasil dijadwalkan.');
      setSchedulingRequest(null);
      fetchRequests();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menjadwalkan konseling.';
      setError(msg);
    } finally {
      setIsSubmittingSchedule(false);
    }
  };

  const handleOpenRejectModal = (req: CounselingRequestItem) => {
    setRejectingRequest(req);
    setRejectReason('');
  };

  const handleSubmitReject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rejectingRequest || !rejectReason.trim()) {
      setError('Alasan penolakan wajib diisi.');
      return;
    }

    setIsSubmittingReject(true);
    setError(null);
    try {
      await counselingApi.rejectRequest(rejectingRequest.id, {
        rejection_reason: rejectReason.trim(),
      });

      setSuccessMessage('Permintaan konseling berhasil ditolak dengan catatan.');
      setRejectingRequest(null);
      fetchRequests();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menolak permintaan konseling.';
      setError(msg);
    } finally {
      setIsSubmittingReject(false);
    }
  };

  const handleOpenCompleteModal = (req: CounselingRequestItem) => {
    setCompletingRequest(req);
    setCompleteNotes('Sesi tatap muka konseling telah terlaksana.');
    setCreateCase(true);
    setCaseCategory('Pribadi');
    setCaseSummary(`Tindak lanjut permintaan konseling: ${req.topic}`);
    setSessionNotes('');
    setFollowUp('');
  };

  const handleSubmitComplete = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!completingRequest) return;

    setIsSubmittingComplete(true);
    setError(null);
    try {
      await counselingApi.completeRequest(completingRequest.id, {
        notes: completeNotes.trim() || undefined,
        create_case: createCase,
        case_category: createCase ? caseCategory : undefined,
        case_summary: createCase ? caseSummary.trim() : undefined,
        session_notes: createCase ? sessionNotes.trim() : undefined,
        follow_up: createCase ? followUp.trim() : undefined,
      });

      setSuccessMessage('Permintaan konseling berhasil diselesaikan' + (createCase ? ' dan dicatat ke Kasus BK resmi.' : '.'));
      setCompletingRequest(null);
      fetchRequests();
      if (createCase && onCaseCreated) {
        onCaseCreated();
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyelesaikan permintaan konseling.';
      setError(msg);
    } finally {
      setIsSubmittingComplete(false);
    }
  };

  // Badges & Labels
  const renderStatusBadge = (status: CounselingRequestStatus) => {
    switch (status) {
      case 'pending':
        return (
          <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 gap-1 text-[11px]">
            <Clock className="h-3 w-3" /> Menunggu Jadwal
          </Badge>
        );
      case 'scheduled':
        return (
          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 gap-1 text-[11px]">
            <CalendarCheck className="h-3 w-3" /> Terjadwal
          </Badge>
        );
      case 'completed':
        return (
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 gap-1 text-[11px]">
            <CheckCircle2 className="h-3 w-3" /> Selesai
          </Badge>
        );
      case 'rejected':
        return (
          <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 gap-1 text-[11px]">
            <Ban className="h-3 w-3" /> Ditolak
          </Badge>
        );
      case 'cancelled':
        return (
          <Badge variant="outline" className="bg-slate-50 text-slate-600 border-slate-200 gap-1 text-[11px]">
            <X className="h-3 w-3" /> Dibatalkan
          </Badge>
        );
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  const renderRequesterRole = (role: CounselingRequesterRole) => {
    switch (role) {
      case 'student':
        return (
          <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
            Siswa Sendiri
          </span>
        );
      case 'guardian':
        return (
          <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-teal-200">
            Wali Murid / Ortu
          </span>
        );
      case 'counselor':
        return (
          <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-purple-700 bg-purple-50 px-2 py-0.5 rounded border border-purple-200">
            Konselor BK
          </span>
        );
      default:
        return <span className="text-[11px] text-slate-500">{role}</span>;
    }
  };

  const pendingCount = requests.filter((r) => r.status === 'pending').length;
  const scheduledCount = requests.filter((r) => r.status === 'scheduled').length;
  const completedCount = requests.filter((r) => r.status === 'completed').length;

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
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Total Permintaan</CardTitle>
            <HeartHandshake className="h-4 w-4 text-indigo-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">{requests.length}</div>
            <p className="text-[11px] text-slate-500 mt-1">Pengajuan siswa & orang tua</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-amber-600">Menunggu Jadwal</CardTitle>
            <Clock className="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-amber-600">{pendingCount}</div>
            <p className="text-[11px] text-slate-500 mt-1">Perlu dijadwalkan oleh BK</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-blue-600">Konseling Terjadwal</CardTitle>
            <CalendarCheck className="h-4 w-4 text-blue-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">{scheduledCount}</div>
            <p className="text-[11px] text-slate-500 mt-1">Menanti pelaksanaan sesi</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-emerald-600">Selesai Terlaksana</CardTitle>
            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">{completedCount}</div>
            <p className="text-[11px] text-slate-500 mt-1">Telah dibina / ditindaklanjuti</p>
          </CardContent>
        </Card>
      </div>

      {/* Filter Toolbar */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
        <div className="flex flex-wrap items-center gap-2 flex-1">
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
            <Input
              placeholder="Cari nama siswa, NIS, atau topik..."
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
            <option value="pending">Menunggu Jadwal (Pending)</option>
            <option value="scheduled">Terjadwal</option>
            <option value="completed">Selesai</option>
            <option value="rejected">Ditolak</option>
            <option value="cancelled">Dibatalkan</option>
          </select>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <Button
            variant="outline"
            size="sm"
            onClick={fetchRequests}
            className="text-xs h-9 bg-white"
            title="Muat ulang data"
          >
            <RefreshCw className="h-3.5 w-3.5" />
          </Button>

          <Button
            size="sm"
            onClick={() => setIsNewRequestModalOpen(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white gap-1.5 text-xs h-9"
          >
            <Plus className="h-3.5 w-3.5" /> Catat Permintaan Konseling
          </Button>
        </div>
      </div>

      {/* Requests Table */}
      {isLoading ? (
        <div className="text-center py-12 text-xs text-slate-500 bg-white rounded-lg border border-slate-200">
          <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
          Memuat data permintaan konseling...
        </div>
      ) : requests.length === 0 ? (
        <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
          <HeartHandshake className="h-10 w-10 text-slate-300 mx-auto mb-2" />
          <h3 className="text-sm font-semibold text-slate-800">Tidak Ada Permintaan Konseling</h3>
          <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">
            Siswa dan orang tua dapat mengajukan janji temu konseling secara mandiri melalui portal siswa atau portal orang tua.
          </p>
        </div>
      ) : (
        <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="bg-slate-50 text-slate-600 border-b border-slate-200">
                <tr>
                  <th className="px-4 py-3 font-semibold">Siswa & Kelas</th>
                  <th className="px-4 py-3 font-semibold">Pemohon</th>
                  <th className="px-4 py-3 font-semibold">Topik Bimbingan</th>
                  <th className="px-4 py-3 font-semibold">Preferensi Waktu</th>
                  <th className="px-4 py-3 font-semibold text-center">Status</th>
                  <th className="px-4 py-3 font-semibold">Jadwal / Konselor</th>
                  <th className="px-4 py-3 font-semibold text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {requests.map((r) => {
                  const className = r.student?.classes?.[0]?.name || 'Tanpa kelas';
                  return (
                    <tr key={r.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-900">
                        <div>{r.student?.full_name || 'Nama Siswa'}</div>
                        <div className="text-[11px] text-slate-500 mt-0.5">
                          NIS: {r.student?.nis || '-'} • {className}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div>{renderRequesterRole(r.requester_role)}</div>
                        <div className="text-[11px] text-slate-500 mt-0.5">{r.requester?.name || r.requester?.username}</div>
                      </td>
                      <td className="px-4 py-3 text-slate-800 max-w-xs">
                        <div className="font-semibold line-clamp-1">{r.topic}</div>
                        {r.notes && <div className="text-[11px] text-slate-500 italic line-clamp-1 mt-0.5">{r.notes}</div>}
                      </td>
                      <td className="px-4 py-3 text-slate-600 text-[11px]">
                        {r.preferred_date ? (
                          <div>
                            <span className="font-medium text-slate-800">{new Date(r.preferred_date).toLocaleDateString('id-ID')}</span>
                            {r.preferred_time && <div className="text-slate-500">{r.preferred_time}</div>}
                          </div>
                        ) : (
                          <span className="text-slate-400">Fleksibel</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-center">{renderStatusBadge(r.status)}</td>
                      <td className="px-4 py-3 text-[11px] text-slate-600">
                        {r.scheduled_at ? (
                          <div>
                            <div className="font-semibold text-indigo-700">{new Date(r.scheduled_at).toLocaleString('id-ID')}</div>
                            <div className="text-slate-500 mt-0.5">Konselor: {r.counselor?.name || r.counselor?.username || 'BK'}</div>
                          </div>
                        ) : (
                          <span className="text-slate-400">- Belum dijadwalkan -</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDetailRequest(r)}
                            className="h-7 px-2 text-[11px] text-slate-600 hover:text-indigo-600"
                          >
                            <FileText className="h-3.5 w-3.5 mr-1" /> Detail
                          </Button>

                          {r.status === 'pending' && (
                            <>
                              <Button
                                size="sm"
                                onClick={() => handleOpenScheduleModal(r)}
                                className="h-7 px-2 text-[11px] bg-indigo-600 hover:bg-indigo-700 text-white gap-1"
                              >
                                <CalendarCheck className="h-3 w-3" /> Jadwalkan
                              </Button>

                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleOpenRejectModal(r)}
                                className="h-7 px-2 text-[11px] text-rose-600 border-rose-200 hover:bg-rose-50"
                              >
                                Tolak
                              </Button>
                            </>
                          )}

                          {r.status === 'scheduled' && (
                            <Button
                              size="sm"
                              onClick={() => handleOpenCompleteModal(r)}
                              className="h-7 px-2 text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white gap-1"
                            >
                              <CheckCircle2 className="h-3 w-3" /> Selesaikan & Kasus
                            </Button>
                          )}
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

      {/* MODAL 1: CATAT PERMINTAAN KONSELING BARU */}
      {isNewRequestModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <HeartHandshake className="h-4 w-4 text-indigo-600" />
                <h3 className="font-semibold text-slate-800 text-sm">Catat Permintaan Konseling</h3>
              </div>
              <button onClick={() => setIsNewRequestModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleCreateRequest}>
              <div className="p-5 space-y-3.5 text-xs">
                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Pilih Siswa *</label>
                  <select
                    value={formStudentId}
                    onChange={(e) => setFormStudentId(e.target.value)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-800"
                    required
                  >
                    {students.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.full_name} ({s.nis || '-'})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Topik Konseling *</label>
                  <Input
                    placeholder="Contoh: Diskusi rencana karir atau kendala konsentrasi belajar"
                    value={formTopic}
                    onChange={(e) => setFormTopic(e.target.value)}
                    className="text-xs h-9"
                    required
                  />
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div className="space-y-1">
                    <label className="font-semibold text-slate-700">Preferensi Tanggal</label>
                    <Input
                      type="date"
                      value={formPrefDate}
                      onChange={(e) => setFormPrefDate(e.target.value)}
                      className="text-xs h-9"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="font-semibold text-slate-700">Preferensi Waktu</label>
                    <Input
                      placeholder="09:00 - 10:00"
                      value={formPrefTime}
                      onChange={(e) => setFormPrefTime(e.target.value)}
                      className="text-xs h-9"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Catatan Tambahan (Opsional)</label>
                  <Textarea
                    placeholder="Catatan latar belakang atau kebutuhan sesi..."
                    value={formNotes}
                    onChange={(e) => setFormNotes(e.target.value)}
                    rows={3}
                    className="text-xs resize-none"
                  />
                </div>
              </div>

              <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsNewRequestModalOpen(false)}
                  disabled={isSubmittingNew}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingNew}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingNew ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : 'Simpan Permintaan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 2: JADWALKAN KONSELING */}
      {schedulingRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <CalendarCheck className="h-4 w-4 text-indigo-600" />
                <h3 className="font-semibold text-slate-800 text-sm">Jadwalkan Sesi Konseling</h3>
              </div>
              <button onClick={() => setSchedulingRequest(null)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSubmitSchedule}>
              <div className="p-5 space-y-3.5 text-xs">
                <div>
                  <span className="text-slate-500 block">Siswa:</span>
                  <span className="font-semibold text-slate-900 text-sm">
                    {schedulingRequest.student?.full_name} ({schedulingRequest.student?.classes?.[0]?.name || '-'})
                  </span>
                  <div className="text-slate-600 mt-1">Topik: {schedulingRequest.topic}</div>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Pilih Waktu & Tanggal Konseling *</label>
                  <Input
                    type="datetime-local"
                    value={scheduleDateTime}
                    onChange={(e) => setScheduleDateTime(e.target.value)}
                    className="text-xs h-9"
                    required
                  />
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Catatan Ruang / Panduan Sesi</label>
                  <Textarea
                    placeholder="Contoh: Bertempat di Ruang Bimbingan Konseling 1, siapkan buku catatan pribadi."
                    value={scheduleNotes}
                    onChange={(e) => setScheduleNotes(e.target.value)}
                    rows={3}
                    className="text-xs resize-none"
                  />
                </div>
              </div>

              <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setSchedulingRequest(null)}
                  disabled={isSubmittingSchedule}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingSchedule}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingSchedule ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : 'Konfirmasi Jadwal'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 3: TOLAK PERMINTAAN */}
      {rejectingRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2 text-rose-700">
                <Ban className="h-4 w-4" />
                <h3 className="font-semibold text-slate-800 text-sm">Tolak Permintaan Konseling</h3>
              </div>
              <button onClick={() => setRejectingRequest(null)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSubmitReject}>
              <div className="p-5 space-y-3.5 text-xs">
                <div>
                  <span className="text-slate-500 block">Siswa:</span>
                  <span className="font-semibold text-slate-900">
                    {rejectingRequest.student?.full_name} ({rejectingRequest.student?.classes?.[0]?.name || '-'})
                  </span>
                  <div className="text-slate-600 mt-1">Topik: {rejectingRequest.topic}</div>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Alasan Penolakan / Arahan Tindak Lanjut *</label>
                  <Textarea
                    placeholder="Tuliskan alasan penolakan secara santun dan edukatif (contoh: Diarahkan untuk konsultasi langsung kepada wali kelas)."
                    value={rejectReason}
                    onChange={(e) => setRejectReason(e.target.value)}
                    rows={3}
                    className="text-xs resize-none"
                    required
                  />
                </div>
              </div>

              <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setRejectingRequest(null)}
                  disabled={isSubmittingReject}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingReject}
                  className="bg-rose-600 hover:bg-rose-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingReject ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : 'Tolak Permintaan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 4: SELESAIKAN & CATAT KASUS BK RESMI */}
      {completingRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                <h3 className="font-semibold text-slate-800 text-sm">Selesaikan Permintaan & Catat Kasus BK</h3>
              </div>
              <button onClick={() => setCompletingRequest(null)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSubmitComplete}>
              <div className="p-5 space-y-3.5 text-xs">
                <div>
                  <span className="text-slate-500 block">Siswa:</span>
                  <span className="font-semibold text-slate-900 text-sm">
                    {completingRequest.student?.full_name} ({completingRequest.student?.classes?.[0]?.name || '-'})
                  </span>
                </div>

                <div className="space-y-1">
                  <label className="font-semibold text-slate-700">Catatan Pelaksanaan Sesi</label>
                  <Textarea
                    placeholder="Catatan pelaksanaan sesi konseling tatap muka..."
                    value={completeNotes}
                    onChange={(e) => setCompleteNotes(e.target.value)}
                    rows={2}
                    className="text-xs resize-none"
                  />
                </div>

                <div className="bg-indigo-50/50 border border-indigo-100 rounded-md p-3 space-y-2.5">
                  <div className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      id="createCaseCheckbox"
                      checked={createCase}
                      onChange={(e) => setCreateCase(e.target.checked)}
                      className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                    />
                    <label htmlFor="createCaseCheckbox" className="font-semibold text-indigo-950 cursor-pointer">
                      Integrasikan ke Kasus BK Resmi (Buku Kasus Privat)
                    </label>
                  </div>

                  {createCase && (
                    <div className="space-y-2 pt-2 border-t border-indigo-100">
                      <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                          <label className="font-semibold text-slate-700">Kategori Kasus</label>
                          <select
                            value={caseCategory}
                            onChange={(e) => setCaseCategory(e.target.value as any)}
                            className="w-full text-xs h-8 px-2 rounded border border-slate-200 bg-white"
                          >
                            <option value="Pribadi">Pribadi</option>
                            <option value="Sosial">Sosial</option>
                            <option value="Belajar">Belajar</option>
                            <option value="Karir">Karir</option>
                            <option value="Lainnya">Lainnya</option>
                          </select>
                        </div>
                        <div className="space-y-1">
                          <label className="font-semibold text-slate-700">Rencana Tindak Lanjut</label>
                          <Input
                            placeholder="Contoh: Evaluasi 2 minggu ke depan"
                            value={followUp}
                            onChange={(e) => setFollowUp(e.target.value)}
                            className="text-xs h-8"
                          />
                        </div>
                      </div>

                      <div className="space-y-1">
                        <label className="font-semibold text-slate-700">Ringkasan Kasus (Terenkripsi At-Rest)</label>
                        <Textarea
                          value={caseSummary}
                          onChange={(e) => setCaseSummary(e.target.value)}
                          rows={2}
                          className="text-xs resize-none"
                          required={createCase}
                        />
                      </div>

                      <div className="space-y-1">
                        <label className="font-semibold text-slate-700">Catatan Sesi Pertama</label>
                        <Textarea
                          placeholder="Hasil konseling dan poin kesepakatan bersama siswa..."
                          value={sessionNotes}
                          onChange={(e) => setSessionNotes(e.target.value)}
                          rows={2}
                          className="text-xs resize-none"
                        />
                      </div>
                    </div>
                  )}
                </div>
              </div>

              <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setCompletingRequest(null)}
                  disabled={isSubmittingComplete}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingComplete}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingComplete ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : 'Selesaikan Permintaan'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 5: DETAIL PERMINTAAN */}
      {detailRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in-50">
            <div className="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <FileText className="h-4 w-4 text-indigo-600" />
                <h3 className="font-semibold text-slate-800 text-sm">Detail Permintaan Konseling</h3>
              </div>
              <button onClick={() => setDetailRequest(null)} className="text-slate-400 hover:text-slate-600">
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-3.5 text-xs">
              <div className="bg-slate-50 p-3 rounded border border-slate-200 space-y-1">
                <div className="text-slate-500">Identitas Siswa:</div>
                <div className="text-sm font-semibold text-slate-900">{detailRequest.student?.full_name}</div>
                <div className="text-slate-600">
                  NIS: {detailRequest.student?.nis || '-'} • Kelas: {detailRequest.student?.classes?.[0]?.name || '-'}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div className="border border-slate-200 rounded p-2">
                  <span className="text-slate-500 block mb-1">Diajukan Oleh:</span>
                  <div>{renderRequesterRole(detailRequest.requester_role)}</div>
                </div>
                <div className="border border-slate-200 rounded p-2">
                  <span className="text-slate-500 block mb-1">Status:</span>
                  <div>{renderStatusBadge(detailRequest.status)}</div>
                </div>
              </div>

              <div className="border border-slate-200 rounded p-3 space-y-1">
                <div className="font-semibold text-slate-800">Topik Konseling:</div>
                <div className="text-slate-700 leading-relaxed">{detailRequest.topic}</div>
                {detailRequest.notes && (
                  <div className="text-slate-500 italic mt-1 pt-1 border-t border-slate-100">
                    Catatan: {detailRequest.notes}
                  </div>
                )}
              </div>

              {detailRequest.rejection_reason && (
                <div className="bg-rose-50 border border-rose-200 rounded p-3 text-rose-800 space-y-1">
                  <div className="font-semibold text-rose-900">Alasan Penolakan:</div>
                  <div>{detailRequest.rejection_reason}</div>
                </div>
              )}

              {detailRequest.scheduled_at && (
                <div className="bg-indigo-50 border border-indigo-200 rounded p-3 text-indigo-900 space-y-1">
                  <div className="font-semibold">Jadwal Sesi Konseling:</div>
                  <div>{new Date(detailRequest.scheduled_at).toLocaleString('id-ID')}</div>
                  {detailRequest.counselor && (
                    <div className="text-[11px] text-indigo-700 mt-1">
                      Konselor Penanggung Jawab: {detailRequest.counselor.name || detailRequest.counselor.username}
                    </div>
                  )}
                </div>
              )}
            </div>

            <div className="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
              <Button size="sm" onClick={() => setDetailRequest(null)} className="text-xs">
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
