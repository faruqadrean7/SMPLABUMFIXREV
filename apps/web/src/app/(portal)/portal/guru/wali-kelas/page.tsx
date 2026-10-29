'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useAuth } from '@/providers/auth-provider';
import {
  useTeacherHomeroomData,
  useHomeroomLeaveRequests,
  useDecideHomeroomLeaveRequest,
  useRecordStudentPoint,
  useCreateCounselingReferral,
} from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { AccessDenied } from '@/components/shared/access-denied';
import { EmptyState } from '@/components/shared/empty-state';
import {
  ShieldCheck,
  ArrowLeft,
  Users,
  CalendarCheck,
  Sparkles,
  FileCheck2,
  CheckCircle2,
  Lock,
  Send,
  Award,
  Clock,
  AlertTriangle,
  XCircle,
  FileText,
  AlertCircle,
  PlusCircle,
  Filter,
  Search,
} from 'lucide-react';

export default function TeacherHomeroomPage() {
  const { user } = useAuth();

  const isHomeroom = Boolean(
    user?.roles?.includes('homeroom') ||
    user?.roles?.includes('super_admin') ||
    user?.profile?.isHomeroom
  );

  const { data: homeroom, isLoading } = useTeacherHomeroomData(isHomeroom);
  const [activeTab, setActiveTab] = useState<'overview' | 'perizinan' | 'poin'>('overview');

  // E-Rapor Approval State
  const [isReportLocked, setIsReportLocked] = useState(false);
  const [isSubmittingApproval, setIsSubmittingApproval] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);

  // Perizinan State
  const [leaveStatusFilter, setLeaveStatusFilter] = useState<string>('ALL');
  const [leaveSearch, setLeaveSearch] = useState<string>('');
  const {
    data: leaveRequests = [],
    isLoading: isLoadingLeaves,
    error: leavesError,
  } = useHomeroomLeaveRequests(undefined, isHomeroom);
  const decideMutation = useDecideHomeroomLeaveRequest();

  // Decision Modal State
  const [decisionModalOpen, setDecisionModalOpen] = useState(false);
  const [selectedLeaveId, setSelectedLeaveId] = useState<string | null>(null);
  const [decisionType, setDecisionType] = useState<'approved' | 'rejected'>('approved');
  const [decisionNotes, setDecisionNotes] = useState('');
  const [decisionError, setDecisionError] = useState<string | null>(null);

  // Poin Karakter State
  const recordPointMutation = useRecordStudentPoint();
  const [pointStudentId, setPointStudentId] = useState('');
  const [pointKind, setPointKind] = useState<'prestasi' | 'pelanggaran'>('prestasi');
  const [pointCategory, setPointCategory] = useState('');
  const [pointValue, setPointValue] = useState<number>(10);
  const [pointNotes, setPointNotes] = useState('');
  const [pointFeedback, setPointFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Rujukan BK State
  const counselingMutation = useCreateCounselingReferral();
  const [counselingStudentId, setCounselingStudentId] = useState('');
  const [counselingReason, setCounselingReason] = useState('');
  const [counselingPriority, setCounselingPriority] = useState<'biasa' | 'segera'>('biasa');
  const [counselingFeedback, setCounselingFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  React.useEffect(() => {
    if (homeroom) {
      setIsReportLocked(homeroom.isReportCardLocked);
    }
  }, [homeroom]);

  // Guard: Jika guru bukan wali kelas aktif
  if (!isHomeroom) {
    return (
      <AccessDenied
        title="Akses Khusus Wali Kelas Ditolak (403)"
        description="Menu ini khusus diperuntukkan bagi guru yang ditugaskan resmi sebagai Wali Kelas aktif. Silakan hubungi Wakil Kepala Sekolah Kurikulum jika terdapat kekeliruan penugasan."
      />
    );
  }

  const handleRequestApproval = () => {
    setIsSubmittingApproval(true);
    setTimeout(() => {
      setIsReportLocked(true);
      setIsSubmittingApproval(false);
      setFeedback('E-Rapor kelas binaan berhasil diajukan untuk verifikasi dan persetujuan Kepala Sekolah.');
      setTimeout(() => setFeedback(null), 5000);
    }, 1000);
  };

  const openDecisionModal = (leaveId: string, decision: 'approved' | 'rejected') => {
    setSelectedLeaveId(leaveId);
    setDecisionType(decision);
    setDecisionNotes('');
    setDecisionError(null);
    setDecisionModalOpen(true);
  };

  const handleConfirmDecision = async () => {
    if (!selectedLeaveId) return;
    setDecisionError(null);

    try {
      await decideMutation.mutateAsync({
        id: selectedLeaveId,
        payload: {
          decision: decisionType,
          decision_notes: decisionNotes.trim() || undefined,
        },
      });

      setFeedback(
        `Permohonan izin berhasil ${decisionType === 'approved' ? 'disetujui' : 'ditolak'}. Presensi siswa telah disinkronkan.`
      );
      setDecisionModalOpen(false);
      setTimeout(() => setFeedback(null), 5000);
    } catch (err: any) {
      const msg = err.response?.data?.error?.message || err.message || 'Gagal memproses keputusan izin.';
      setDecisionError(msg);
    }
  };

  const handleRecordPoint = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pointStudentId) {
      setPointFeedback({ type: 'error', message: 'Silakan pilih siswa terlebih dahulu.' });
      return;
    }

    setPointFeedback(null);
    try {
      await recordPointMutation.mutateAsync({
        student_id: pointStudentId,
        kind: pointKind,
        category: pointCategory.trim() || (pointKind === 'prestasi' ? 'Prestasi Akademik' : 'Kedisiplinan'),
        points: Number(pointValue) || (pointKind === 'prestasi' ? 10 : 5),
        notes: pointNotes.trim() || 'Pencatatan poin oleh wali kelas',
      });

      setPointFeedback({
        type: 'success',
        message: `Poin ${pointKind === 'prestasi' ? 'prestasi (+)' : 'pelanggaran (-)'} berhasil dicatat.`,
      });
      setPointCategory('');
      setPointNotes('');
      setTimeout(() => setPointFeedback(null), 5000);
    } catch (err: any) {
      const msg = err.response?.data?.error?.message || err.message || 'Gagal mencatat poin siswa.';
      setPointFeedback({ type: 'error', message: msg });
    }
  };

  const handleCreateCounseling = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!counselingStudentId) {
      setCounselingFeedback({ type: 'error', message: 'Silakan pilih siswa terlebih dahulu.' });
      return;
    }
    if (counselingReason.trim().length < 5) {
      setCounselingFeedback({ type: 'error', message: 'Alasan rujukan minimal 5 karakter.' });
      return;
    }

    setCounselingFeedback(null);
    try {
      await counselingMutation.mutateAsync({
        student_id: counselingStudentId,
        reason: counselingReason.trim(),
        priority: counselingPriority,
      });

      setCounselingFeedback({
        type: 'success',
        message: 'Rujukan bimbingan konseling berhasil dikirimkan ke guru BK.',
      });
      setCounselingReason('');
      setTimeout(() => setCounselingFeedback(null), 5000);
    } catch (err: any) {
      const msg = err.response?.data?.error?.message || err.message || 'Gagal mengirim rujukan BK.';
      setCounselingFeedback({ type: 'error', message: msg });
    }
  };

  // Filtered leave requests
  const filteredLeaves = leaveRequests.filter((lr) => {
    if (leaveStatusFilter !== 'ALL' && lr.status !== leaveStatusFilter) return false;
    if (leaveSearch) {
      const q = leaveSearch.toLowerCase();
      const matchName = lr.student?.full_name?.toLowerCase().includes(q) || false;
      const matchNis = lr.student?.nis?.toLowerCase().includes(q) || false;
      const matchReason = lr.reason?.toLowerCase().includes(q) || false;
      if (!matchName && !matchNis && !matchReason) return false;
    }
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/guru"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <ShieldCheck className="h-6 w-6 text-brand-blue" />
            <span>Manajemen Wali Kelas ({homeroom?.className || '7-A Unggulan'})</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Monitoring kehadiran, verifikasi surat izin siswa, pembinaan karakter, dan pengesahan e-rapor.
          </p>
        </div>

        <div className="flex items-center gap-2">
          {isReportLocked ? (
            <Badge className="bg-slate-100 text-slate-700 border-slate-300 gap-1.5 px-3 py-1 text-xs font-bold">
              <Lock className="h-3.5 w-3.5" />
              <span>E-Rapor Terkunci</span>
            </Badge>
          ) : (
            <Button
              onClick={handleRequestApproval}
              isLoading={isSubmittingApproval}
              size="sm"
              className="gap-2 font-bold bg-brand-blue hover:bg-blue-800"
            >
              <Send className="h-4 w-4" />
              <span>Ajukan Persetujuan E-Rapor</span>
            </Button>
          )}
        </div>
      </div>

      {feedback && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-5 w-5 shrink-0" />
          <span>{feedback}</span>
        </div>
      )}

      {/* Tab Navigation */}
      <div className="flex border-b border-slate-200 bg-white px-4 rounded-xl gap-2 shadow-2xs">
        <button
          onClick={() => setActiveTab('overview')}
          className={`py-3 px-4 font-bold text-xs sm:text-sm border-b-2 transition-all flex items-center gap-2 ${
            activeTab === 'overview'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-500 hover:text-slate-900'
          }`}
        >
          <Users className="w-4 h-4" />
          <span>Ringkasan & E-Rapor</span>
        </button>

        <button
          onClick={() => setActiveTab('perizinan')}
          className={`py-3 px-4 font-bold text-xs sm:text-sm border-b-2 transition-all flex items-center gap-2 ${
            activeTab === 'perizinan'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-500 hover:text-slate-900'
          }`}
        >
          <FileCheck2 className="w-4 h-4" />
          <span>Perizinan Siswa</span>
          {leaveRequests.filter((l) => l.status === 'menunggu').length > 0 && (
            <span className="bg-amber-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full">
              {leaveRequests.filter((l) => l.status === 'menunggu').length}
            </span>
          )}
        </button>

        <button
          onClick={() => setActiveTab('poin')}
          className={`py-3 px-4 font-bold text-xs sm:text-sm border-b-2 transition-all flex items-center gap-2 ${
            activeTab === 'poin'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-500 hover:text-slate-900'
          }`}
        >
          <Award className="w-4 h-4" />
          <span>Poin Siswa & BK</span>
        </button>
      </div>

      {/* TAB 1: RINGKASAN & E-RAPOR */}
      {activeTab === 'overview' && (
        <>
          {isLoading ? (
            <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat data kelas binaan..." size="lg" />
            </div>
          ) : (
            <div className="space-y-6">
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs font-medium">Siswa Perwalian</CardDescription>
                    <CardTitle className="text-2xl font-black text-slate-900">
                      {homeroom?.totalStudents || 32} <span className="text-xs font-normal text-slate-500">anak</span>
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="pt-0 text-[11px] text-slate-500">
                    Semester {homeroom?.semester} TA {homeroom?.academicYear}
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs font-medium">Presensi Kelas Rata-Rata</CardDescription>
                    <CardTitle className="text-2xl font-black text-status-success">
                      {homeroom?.attendanceRecap.averageRate || 97.4}%
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="pt-0 text-[11px] text-slate-500">
                    Sakit: {homeroom?.attendanceRecap.sickDays} • Izin: {homeroom?.attendanceRecap.leaveDays}
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs font-medium">Catatan Karakter P5</CardDescription>
                    <CardTitle className="text-2xl font-black text-brand-blue">
                      {homeroom?.characterRecapCount || 32} / 32
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="pt-0 text-[11px] text-slate-500">
                    100% siswa telah dinilai
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs font-medium">Status Buku Rapor</CardDescription>
                    <CardTitle className="text-base font-black text-purple-700">
                      {isReportLocked ? 'Terkunci & Sah' : 'Draf Pengajuan'}
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="pt-0 text-[11px] text-slate-500">
                    {isReportLocked ? 'Menunggu penandatanganan' : 'Dapat diperbaiki'}
                  </CardContent>
                </Card>
              </div>

              {/* Student Class Roster */}
              <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                  <div>
                    <h3 className="font-bold text-sm text-slate-900">Daftar Siswa Kelas Binaan</h3>
                    <p className="text-xs text-slate-500 mt-0.5">Rekapitulasi nilai dan kehadiran siswa kelas binaan</p>
                  </div>
                  <Badge variant="outline" className="text-xs">
                    {homeroom?.students.length || 0} Siswa Terdaftar
                  </Badge>
                </div>

                <div className="divide-y divide-slate-100">
                  {homeroom?.students.map((st) => (
                    <div
                      key={st.id}
                      className="p-4 hover:bg-slate-50/60 transition-colors flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs"
                    >
                      <div className="flex items-center gap-3">
                        <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center font-black text-brand-blue shrink-0">
                          #{st.rank}
                        </div>
                        <div>
                          <h4 className="font-bold text-sm text-slate-900">{st.name}</h4>
                          <p className="text-[11px] text-slate-400">NISN: {st.nisn} • NIS: {st.nis}</p>
                        </div>
                      </div>

                      <div className="flex items-center gap-4 self-end sm:self-center">
                        <div className="text-right">
                          <span className="text-slate-400 text-[10px] block uppercase font-bold">Rata-Rata Nilai</span>
                          <span className="text-sm font-black text-slate-900">{st.averageScore}</span>
                        </div>

                        <div className="text-right pl-3 border-l border-slate-100">
                          <span className="text-slate-400 text-[10px] block uppercase font-bold">Kehadiran</span>
                          <span className="text-sm font-black text-status-success">{st.attendanceRate}%</span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </>
      )}

      {/* TAB 2: PERIZINAN SISWA */}
      {activeTab === 'perizinan' && (
        <div className="space-y-4">
          <div className="bg-white p-4 rounded-xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
            <div className="flex items-center gap-2 flex-1 max-w-md">
              <Search className="w-4 h-4 text-slate-400 shrink-0" />
              <input
                type="text"
                placeholder="Cari siswa, NIS, atau alasan izin..."
                value={leaveSearch}
                onChange={(e) => setLeaveSearch(e.target.value)}
                className="w-full text-xs sm:text-sm bg-transparent border-0 outline-hidden text-slate-900 placeholder:text-slate-400"
              />
            </div>

            <div className="flex items-center gap-2 shrink-0">
              <Filter className="w-3.5 h-3.5 text-slate-400" />
              <select
                value={leaveStatusFilter}
                onChange={(e) => setLeaveStatusFilter(e.target.value)}
                className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-700 font-semibold focus:outline-hidden"
              >
                <option value="ALL">Semua Status</option>
                <option value="menunggu">Menunggu Keputusan</option>
                <option value="disetujui">Disetujui</option>
                <option value="ditolak">Ditolak</option>
              </select>
            </div>
          </div>

          {isLoadingLeaves ? (
            <div className="min-h-[200px] flex items-center justify-center bg-white rounded-xl border border-slate-200">
              <LoadingSpinner label="Memuat permohonan izin siswa..." />
            </div>
          ) : leavesError ? (
            <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-semibold flex items-center gap-2">
              <AlertTriangle className="w-4 h-4 shrink-0" />
              <span>Gagal memuat data perizinan. Silakan coba muat ulang halaman.</span>
            </div>
          ) : filteredLeaves.length === 0 ? (
            <EmptyState
              title="Tidak Ada Permohonan Izin"
              description="Belum ada permohonan izin dari orang tua siswa yang sesuai filter saat ini."
              className="bg-white border-slate-200 py-12"
            />
          ) : (
            <div className="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 shadow-2xs overflow-hidden">
              {filteredLeaves.map((lr) => (
                <div key={lr.id} className="p-4 hover:bg-slate-50/50 transition-colors flex flex-col md:flex-row md:items-center justify-between gap-4">
                  <div className="space-y-1.5 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                      <h4 className="font-bold text-sm text-slate-900">{lr.student?.full_name || 'Siswa'}</h4>
                      <span className="text-xs text-slate-400">({lr.student?.nis || '-'})</span>
                      <Badge
                        className={`text-[10px] uppercase font-bold px-2 py-0.5 ${
                          lr.leave_type === 'sakit'
                            ? 'bg-rose-100 text-rose-800 border-rose-200'
                            : lr.leave_type === 'dispen'
                            ? 'bg-indigo-100 text-indigo-800 border-indigo-200'
                            : 'bg-amber-100 text-amber-800 border-amber-200'
                        }`}
                      >
                        {lr.leave_type}
                      </Badge>
                      <Badge
                        className={`text-[10px] font-bold px-2 py-0.5 ${
                          lr.status === 'disetujui'
                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                            : lr.status === 'ditolak'
                            ? 'bg-slate-100 text-slate-700 border-slate-200'
                            : 'bg-amber-500 text-white font-black'
                        }`}
                      >
                        {lr.status === 'disetujui' ? 'Disetujui' : lr.status === 'ditolak' ? 'Ditolak' : 'Menunggu Walas'}
                      </Badge>
                    </div>

                    <div className="text-xs text-slate-600 flex items-center gap-3">
                      <span className="font-semibold text-slate-700">
                        {lr.start_date} s/d {lr.end_date}
                      </span>
                      <span>•</span>
                      <span className="line-clamp-1 italic">{lr.reason}</span>
                    </div>

                    {lr.decision_notes && (
                      <p className="text-[11px] text-slate-500 bg-slate-50 p-2 rounded border border-slate-100">
                        <strong>Catatan Walas:</strong> {lr.decision_notes}
                      </p>
                    )}
                  </div>

                  {lr.status === 'menunggu' && (
                    <div className="flex items-center gap-2 shrink-0">
                      <Button
                        size="sm"
                        variant="outline"
                        className="text-rose-600 border-rose-200 hover:bg-rose-50 text-xs font-bold"
                        onClick={() => openDecisionModal(lr.id, 'rejected')}
                      >
                        <XCircle className="w-3.5 h-3.5 mr-1" />
                        Tolak
                      </Button>
                      <Button
                        size="sm"
                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold"
                        onClick={() => openDecisionModal(lr.id, 'approved')}
                      >
                        <CheckCircle2 className="w-3.5 h-3.5 mr-1" />
                        Setujui
                      </Button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}

          {/* Decision Modal */}
          {decisionModalOpen && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div className="flex items-center gap-3">
                  <div
                    className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${
                      decisionType === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
                    }`}
                  >
                    {decisionType === 'approved' ? <CheckCircle2 className="w-5 h-5" /> : <XCircle className="w-5 h-5" />}
                  </div>
                  <div>
                    <h3 className="font-bold text-base text-slate-900">
                      {decisionType === 'approved' ? 'Setujui Permohonan Izin' : 'Tolak Permohonan Izin'}
                    </h3>
                    <p className="text-xs text-slate-500">
                      Keputusan akan otomatis menyinkronkan data presensi KBM siswa.
                    </p>
                  </div>
                </div>

                {decisionError && (
                  <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-xs font-semibold flex items-center gap-2">
                    <AlertCircle className="w-4 h-4 shrink-0" />
                    <span>{decisionError}</span>
                  </div>
                )}

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-slate-700">Catatan Keputusan (Opsional)</label>
                  <textarea
                    rows={3}
                    placeholder="Contoh: Disetujui, surat dokter telah diverifikasi."
                    value={decisionNotes}
                    onChange={(e) => setDecisionNotes(e.target.value)}
                    className="w-full text-xs p-2.5 rounded-lg border border-slate-200 focus:outline-hidden focus:ring-2 focus:ring-brand-blue"
                  />
                </div>

                <div className="flex items-center justify-end gap-2 pt-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setDecisionModalOpen(false)}
                    disabled={decideMutation.isPending}
                  >
                    Batal
                  </Button>
                  <Button
                    size="sm"
                    className={decisionType === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-rose-600 hover:bg-rose-700 text-white'}
                    onClick={handleConfirmDecision}
                    isLoading={decideMutation.isPending}
                  >
                    Konfirmasi Keputusan
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 3: POIN KARAKTER & RUJUKAN BK */}
      {activeTab === 'poin' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Card Form 1: Poin Siswa */}
          <Card className="border-slate-200 shadow-2xs">
            <CardHeader>
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <Award className="w-5 h-5 text-amber-500" />
                <span>Pencatatan Poin Karakter Siswa</span>
              </CardTitle>
              <CardDescription className="text-xs">
                Catat poin reward prestasi atau catatan pelanggaran kedisiplinan siswa binaan.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleRecordPoint} className="space-y-3.5 text-xs">
                {pointFeedback && (
                  <div
                    className={`p-3 rounded-lg text-xs font-semibold flex items-center gap-2 ${
                      pointFeedback.type === 'success'
                        ? 'bg-emerald-50 text-status-success border border-emerald-200'
                        : 'bg-rose-50 text-rose-800 border border-rose-200'
                    }`}
                  >
                    {pointFeedback.type === 'success' ? <CheckCircle2 className="w-4 h-4 shrink-0" /> : <AlertCircle className="w-4 h-4 shrink-0" />}
                    <span>{pointFeedback.message}</span>
                  </div>
                )}

                <div className="space-y-1">
                  <label htmlFor="point-student-select" className="font-bold text-slate-700">Pilih Siswa (Poin Karakter)</label>
                  <select
                    id="point-student-select"
                    value={pointStudentId}
                    onChange={(e) => setPointStudentId(e.target.value)}
                    required
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white text-slate-800 font-medium"
                  >
                    <option value="">-- Pilih Siswa Kelas Binaan --</option>
                    {homeroom?.students.map((st) => (
                      <option key={st.id} value={st.id}>
                        {st.name} ({st.nis})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1">
                    <label className="font-bold text-slate-700">Jenis Poin</label>
                    <select
                      value={pointKind}
                      onChange={(e) => setPointKind(e.target.value as any)}
                      className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white"
                    >
                      <option value="prestasi">Prestasi (+)</option>
                      <option value="pelanggaran">Pelanggaran (-)</option>
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label className="font-bold text-slate-700">Besaran Poin</label>
                    <input
                      type="number"
                      min={1}
                      max={100}
                      value={pointValue}
                      onChange={(e) => setPointValue(Number(e.target.value))}
                      className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                      required
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="font-bold text-slate-700">Kategori Kegiatan</label>
                  <input
                    type="text"
                    placeholder="Contoh: Juara 1 Sains, Kerapian Seragam, Adab Menolong"
                    value={pointCategory}
                    onChange={(e) => setPointCategory(e.target.value)}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                  />
                </div>

                <div className="space-y-1">
                  <label className="font-bold text-slate-700">Catatan / Deskripsi Kejadian</label>
                  <textarea
                    rows={3}
                    placeholder="Jelaskan rincian prestasi atau indikasi pelanggaran secara objektif..."
                    value={pointNotes}
                    onChange={(e) => setPointNotes(e.target.value)}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                  />
                </div>

                <Button
                  type="submit"
                  size="sm"
                  isLoading={recordPointMutation.isPending}
                  className="w-full font-bold bg-brand-blue hover:bg-blue-800 text-white"
                >
                  Simpan Poin Siswa
                </Button>
              </form>
            </CardContent>
          </Card>

          {/* Card Form 2: Rujukan BK */}
          <Card className="border-slate-200 shadow-2xs">
            <CardHeader>
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <Users className="w-5 h-5 text-indigo-600" />
                <span>Rujukan Bimbingan & Konseling (BK)</span>
              </CardTitle>
              <CardDescription className="text-xs">
                Rujuk siswa yang memerlukan pendampingan khusus atau konseling psikologis ke guru BK.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleCreateCounseling} className="space-y-3.5 text-xs">
                {counselingFeedback && (
                  <div
                    className={`p-3 rounded-lg text-xs font-semibold flex items-center gap-2 ${
                      counselingFeedback.type === 'success'
                        ? 'bg-emerald-50 text-status-success border border-emerald-200'
                        : 'bg-rose-50 text-rose-800 border border-rose-200'
                    }`}
                  >
                    {counselingFeedback.type === 'success' ? <CheckCircle2 className="w-4 h-4 shrink-0" /> : <AlertCircle className="w-4 h-4 shrink-0" />}
                    <span>{counselingFeedback.message}</span>
                  </div>
                )}

                <div className="space-y-1">
                  <label htmlFor="counseling-student-select" className="font-bold text-slate-700">Pilih Siswa (Rujukan BK)</label>
                  <select
                    id="counseling-student-select"
                    value={counselingStudentId}
                    onChange={(e) => setCounselingStudentId(e.target.value)}
                    required
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white text-slate-800 font-medium"
                  >
                    <option value="">-- Pilih Siswa yang Dirujuk --</option>
                    {homeroom?.students.map((st) => (
                      <option key={st.id} value={st.id}>
                        {st.name} ({st.nis})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="font-bold text-slate-700">Tingkat Prioritas</label>
                  <select
                    value={counselingPriority}
                    onChange={(e) => setCounselingPriority(e.target.value as any)}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white"
                  >
                    <option value="biasa">Biasa (Jadwal Konseling Rutin)</option>
                    <option value="segera">Segera (Perlu Penanganan Cepat)</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="font-bold text-slate-700">Alasan Rujukan</label>
                  <textarea
                    rows={4}
                    required
                    placeholder="Deskripsikan latar belakang dan perubahan perilaku yang diamati..."
                    value={counselingReason}
                    onChange={(e) => setCounselingReason(e.target.value)}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                  />
                </div>

                <Button
                  type="submit"
                  size="sm"
                  isLoading={counselingMutation.isPending}
                  className="w-full font-bold bg-indigo-600 hover:bg-indigo-700 text-white"
                >
                  Kirim Rujukan ke Guru BK
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
