'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  HeartHandshake,
  ShieldAlert,
  Plus,
  Search,
  Filter,
  Eye,
  Edit,
  Clock,
  Calendar,
  User,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  X,
  FileText,
  Lock,
  ArrowRight,
  UserCheck,
  Send,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { counselingApi } from '@/features/counseling/counseling-api';
import { studentsApi } from '@/features/master/students-api';
import {
  CounselingCaseItem,
  CounselingSessionItem,
  CounselingReferralItem,
  CounselingCategory,
  CounselingCaseStatus,
  CounselingReferralStatus,
} from '@/features/counseling/types';
import { EarlyWarningManager } from '@/features/counseling/components/EarlyWarningManager';
import { CounselingRequestManager } from '@/features/counseling/components/CounselingRequestManager';
import { StudentItem } from '@/types/master';

const CATEGORIES: CounselingCategory[] = ['Pribadi', 'Sosial', 'Belajar', 'Karir', 'Lainnya'];
const STATUSES: { value: CounselingCaseStatus; label: string }[] = [
  { value: 'aktif', label: 'Aktif / Dalam Pembinaan' },
  { value: 'selesai', label: 'Selesai / Tuntas' },
  { value: 'dirujuk', label: 'Dirujuk ke Pihak Luar / Spesialis' },
];

export default function CounselingAdminPage() {
  const [activeTab, setActiveTab] = useState<'kasus' | 'rujukan' | 'deteksi_dini' | 'permintaan'>('kasus');

  // Master Data
  const [cases, setCases] = useState<CounselingCaseItem[]>([]);
  const [referrals, setReferrals] = useState<CounselingReferralItem[]>([]);
  const [students, setStudents] = useState<StudentItem[]>([]);

  // Filter State
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [referralStatusFilter, setReferralStatusFilter] = useState<string>('all');

  // Loading & Error State
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isReferralsLoading, setIsReferralsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Modals State
  const [isNewCaseModalOpen, setIsNewCaseModalOpen] = useState<boolean>(false);
  const [isNewReferralModalOpen, setIsNewReferralModalOpen] = useState<boolean>(false);
  const [isSessionModalOpen, setIsSessionModalOpen] = useState<boolean>(false);
  const [detailCase, setDetailCase] = useState<CounselingCaseItem | null>(null);
  const [editingCase, setEditingCase] = useState<CounselingCaseItem | null>(null);

  // Form State: Buka Kasus Baru
  const [formStudentId, setFormStudentId] = useState<string>('');
  const [formCategory, setFormCategory] = useState<CounselingCategory>('Pribadi');
  const [formSummary, setFormSummary] = useState<string>('');
  const [formStatus, setFormStatus] = useState<CounselingCaseStatus>('aktif');
  const [isSubmittingCase, setIsSubmittingCase] = useState<boolean>(false);

  // Form State: Sesi Konseling
  const [sessionDate, setSessionDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [sessionNotes, setSessionNotes] = useState<string>('');
  const [sessionFollowUp, setSessionFollowUp] = useState<string>('');
  const [isSubmittingSession, setIsSubmittingSession] = useState<boolean>(false);

  // Form State: Rujukan Baru
  const [referralStudentId, setReferralStudentId] = useState<string>('');
  const [referralReason, setReferralReason] = useState<string>('');
  const [isSubmittingReferral, setIsSubmittingReferral] = useState<boolean>(false);

  // 1. Fetch Cases & Students
  const fetchCases = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [casesRes, studentsRes] = await Promise.all([
        counselingApi.getCases({
          category: selectedCategory === 'all' ? undefined : (selectedCategory as CounselingCategory),
          status: selectedStatus === 'all' ? undefined : (selectedStatus as CounselingCaseStatus),
          search: searchQuery || undefined,
          per_page: 50,
        }),
        studentsApi.list({ limit: 100 }),
      ]);

      setCases(casesRes.data || []);
      setStudents(studentsRes.data || []);
      if (studentsRes.data && studentsRes.data.length > 0 && !formStudentId) {
        setFormStudentId(studentsRes.data[0].id);
        setReferralStudentId(studentsRes.data[0].id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat data kasus konseling';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  };

  // 2. Fetch Referrals
  const fetchReferrals = async () => {
    setIsReferralsLoading(true);
    try {
      const res = await counselingApi.getReferrals({
        status: referralStatusFilter === 'all' ? undefined : (referralStatusFilter as CounselingReferralStatus),
        per_page: 50,
      });
      setReferrals(res.data || []);
    } catch (err: unknown) {
      console.error('Error fetching referrals:', err);
    } finally {
      setIsReferralsLoading(false);
    }
  };

  useEffect(() => {
    fetchCases();
  }, [selectedCategory, selectedStatus]);

  useEffect(() => {
    if (activeTab === 'rujukan') {
      fetchReferrals();
    }
  }, [activeTab, referralStatusFilter]);

  // Handlers: Kasus
  const handleOpenNewCase = (presetStudentId?: string) => {
    setFormStudentId(presetStudentId || (students.length > 0 ? students[0].id : ''));
    setFormCategory('Pribadi');
    setFormSummary('');
    setFormStatus('aktif');
    setIsNewCaseModalOpen(true);
  };

  const handleSaveNewCase = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formStudentId || !formSummary.trim()) {
      setError('Siswa dan ringkasan kasus wajib diisi.');
      return;
    }

    setIsSubmittingCase(true);
    setError(null);
    try {
      await counselingApi.createCase({
        student_id: formStudentId,
        category: formCategory,
        summary: formSummary.trim(),
        status: formStatus,
      });

      setSuccessMessage('Kasus bimbingan konseling berhasil dibuat dan dienkripsi at-rest.');
      setIsNewCaseModalOpen(false);
      fetchCases();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal membuka kasus konseling';
      setError(msg);
    } finally {
      setIsSubmittingCase(false);
    }
  };

  const handleOpenDetailCase = async (caseItem: CounselingCaseItem) => {
    try {
      // Ambil detail lengkap (beserta sesi dan pencatatan audit log baca)
      const res = await counselingApi.getCase(caseItem.id);
      setDetailCase(res.data);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal membuka detail kasus privat';
      setError(msg);
    }
  };

  const handleSaveUpdateCase = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingCase) return;

    setIsSubmittingCase(true);
    setError(null);
    try {
      await counselingApi.updateCase(editingCase.id, {
        category: editingCase.category,
        status: editingCase.status,
      });

      setSuccessMessage('Status kasus bimbingan konseling berhasil diperbarui.');
      setEditingCase(null);
      fetchCases();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memperbarui kasus';
      setError(msg);
    } finally {
      setIsSubmittingCase(false);
    }
  };

  // Handlers: Sesi
  const handleOpenAddSession = () => {
    setSessionDate(new Date().toISOString().split('T')[0]);
    setSessionNotes('');
    setSessionFollowUp('');
    setIsSessionModalOpen(true);
  };

  const handleSaveSession = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!detailCase || !sessionNotes.trim()) {
      setError('Catatan sesi konseling wajib diisi.');
      return;
    }

    setIsSubmittingSession(true);
    setError(null);
    try {
      await counselingApi.createSession(detailCase.id, {
        session_date: sessionDate,
        notes: sessionNotes.trim(),
        follow_up: sessionFollowUp.trim() || undefined,
      });

      setSuccessMessage('Sesi konseling baru berhasil dicatat dan diamankan.');
      setIsSessionModalOpen(false);
      // Refresh case detail
      const updatedCase = await counselingApi.getCase(detailCase.id);
      setDetailCase(updatedCase.data);
      fetchCases();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mencatat sesi konseling';
      setError(msg);
    } finally {
      setIsSubmittingSession(false);
    }
  };

  // Handlers: Rujukan
  const handleSaveReferral = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!referralStudentId || !referralReason.trim()) {
      setError('Siswa dan alasan rujukan wajib diisi.');
      return;
    }

    setIsSubmittingReferral(true);
    setError(null);
    try {
      await counselingApi.createReferral({
        student_id: referralStudentId,
        reason: referralReason.trim(),
      });

      setSuccessMessage('Rujukan kasus siswa berhasil dikirimkan ke Guru BK.');
      setIsNewReferralModalOpen(false);
      setReferralReason('');
      fetchReferrals();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal membuat rujukan kasus';
      setError(msg);
    } finally {
      setIsSubmittingReferral(false);
    }
  };

  const handleUpdateReferralStatus = async (id: string, status: CounselingReferralStatus) => {
    try {
      await counselingApi.updateReferralStatus(id, { status });
      setSuccessMessage(`Status rujukan diubah menjadi "${status}".`);
      fetchReferrals();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memperbarui status rujukan';
      setError(msg);
    }
  };

  // Metrics
  const activeCasesCount = cases.filter((c) => c.status === 'aktif').length;
  const completedCasesCount = cases.filter((c) => c.status === 'selesai').length;
  const totalSessionsCount = cases.reduce((acc, curr) => acc + (curr.sessions_count || 0), 0);
  const pendingReferralsCount = referrals.filter((r) => r.status === 'pending').length;

  return (
    <div className="space-y-6 p-6">
      {/* Header & Navigasi */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
            <HeartHandshake className="h-6 w-6 text-indigo-600" />
            Bimbingan & Konseling Siswa
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            Pengelolaan catatan kasus privat, sesi konseling terjadwal, pembinaan siswa, dan rujukan dari guru mata pelajaran/wali kelas.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={fetchCases}
            disabled={isLoading}
            className="border-slate-200 text-slate-700 gap-1.5"
          >
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>

          <Button
            size="sm"
            onClick={() => handleOpenNewCase()}
            className="bg-indigo-600 hover:bg-indigo-700 text-white gap-1.5"
          >
            <Plus className="h-4 w-4" />
            Buka Kasus Konseling Baru
          </Button>
        </div>
      </div>

      {/* BANNER KEAMANAN PRIVASI & AUDIT LOG WAJIB */}
      <div className="rounded-lg bg-amber-50 border border-amber-200 p-4 text-amber-900 shadow-sm flex items-start gap-3">
        <ShieldAlert className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
        <div className="text-xs">
          <div className="font-bold flex items-center gap-1.5">
            DATA SANGAT RAHASIA & TERENKRIPSI AT-REST (AES-256)
            <Badge variant="outline" className="text-[10px] bg-amber-100/50 border-amber-300 text-amber-800">
              Hak Akses Ketat
            </Badge>
          </div>
          <p className="text-amber-800 mt-1 leading-relaxed">
            Sesuai Kode Etik Bimbingan Konseling dan UU Perlindungan Data Pribadi, seluruh isi ringkasan kasus dan catatan sesi bimbingan disimpan dalam bentuk terenkripsi.
            <strong> Setiap pembukaan berkas atau sesi konseling secara otomatis dicatat dalam Audit Log Keamanan</strong> bersama identitas pengguna dan waktu akses.
          </p>
        </div>
      </div>

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

      {/* Metrics Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Kasus Konseling Aktif</CardTitle>
            <HeartHandshake className="h-4 w-4 text-indigo-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">{activeCasesCount}</div>
            <p className="text-[11px] text-slate-500 mt-1">Siswa dalam proses bimbingan berjalan</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Kasus Selesai / Tuntas</CardTitle>
            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">{completedCasesCount}</div>
            <p className="text-[11px] text-slate-500 mt-1">Pembinaan telah selesai diselesaikan</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Sesi Bimbingan Terlaksana</CardTitle>
            <Calendar className="h-4 w-4 text-blue-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">{totalSessionsCount}</div>
            <p className="text-[11px] text-slate-500 mt-1">Akumulasi pertemuan sesi tatap muka</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Rujukan Guru Pending</CardTitle>
            <Send className="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-amber-600">{pendingReferralsCount}</div>
            <p className="text-[11px] text-slate-500 mt-1">Menunggu penanganan konselor BK</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs Menu */}
      <div className="border-b border-slate-200">
        <div className="flex gap-4">
          <button
            onClick={() => setActiveTab('kasus')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'kasus'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <HeartHandshake className="h-4 w-4" />
            Rekam Kasus Konseling ({cases.length})
          </button>

          <button
            onClick={() => setActiveTab('rujukan')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'rujukan'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <Send className="h-4 w-4" />
            Rujukan dari Guru & Wali Kelas ({referrals.length})
          </button>

          <button
            onClick={() => setActiveTab('deteksi_dini')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'deteksi_dini'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <ShieldAlert className="h-4 w-4" />
            Deteksi Dini & Peringatan Siswa
          </button>

          <button
            onClick={() => setActiveTab('permintaan')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'permintaan'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <UserCheck className="h-4 w-4" />
            Permintaan Konseling Siswa & Wali
          </button>
        </div>
      </div>

      {/* TAB 1: REKAM KASUS KONSELING */}
      {activeTab === 'kasus' && (
        <div className="space-y-4">
          {/* Filters Bar */}
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                <Input
                  placeholder="Cari siswa atau NIS..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && fetchCases()}
                  className="pl-8 text-xs h-9 bg-white"
                />
              </div>

              <select
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Kategori</option>
                {CATEGORIES.map((cat) => (
                  <option key={cat} value={cat}>
                    {cat}
                  </option>
                ))}
              </select>

              <select
                value={selectedStatus}
                onChange={(e) => setSelectedStatus(e.target.value)}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="selesai">Selesai</option>
                <option value="dirujuk">Dirujuk</option>
              </select>

              <Button variant="outline" size="sm" onClick={fetchCases} className="text-xs h-9 bg-white">
                Terapkan
              </Button>
            </div>
          </div>

          {/* Cases Table */}
          {isLoading ? (
            <div className="text-center py-12 text-xs text-slate-500">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat data kasus konseling...
            </div>
          ) : cases.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
              <HeartHandshake className="h-10 w-10 text-slate-300 mx-auto mb-2" />
              <h3 className="text-sm font-semibold text-slate-800">Belum Ada Kasus Konseling</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Buka kasus bimbingan konseling baru untuk mencatat sesi pembinaan dan perkembangan psikososial siswa.
              </p>
              <Button size="sm" onClick={() => handleOpenNewCase()} className="mt-3 bg-indigo-600 hover:bg-indigo-700 text-white gap-1 text-xs">
                <Plus className="h-3.5 w-3.5" /> Buka Kasus Baru
              </Button>
            </div>
          ) : (
            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-sm">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-3 px-4">Nama Siswa</th>
                    <th className="py-3 px-4">NIS / Rombel</th>
                    <th className="py-3 px-4">Kategori Masalah</th>
                    <th className="py-3 px-4">Konselor Penanggung Jawab</th>
                    <th className="py-3 px-4 text-center">Jumlah Sesi</th>
                    <th className="py-3 px-4 text-center">Status Kasus</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {cases.map((c) => {
                    const student = c.student;
                    const className = student?.classes && student.classes.length > 0 ? student.classes[0].name : '-';

                    return (
                      <tr key={c.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-semibold text-slate-900">{student?.full_name || 'Siswa'}</div>
                          <div className="text-[10px] text-slate-400">ID: {c.id.substring(0, 8)}...</div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="font-mono text-slate-700">{student?.nis || '-'}</div>
                          <Badge variant="outline" className="text-[10px] mt-0.5 border-slate-200 text-slate-600">
                            Kelas: {className}
                          </Badge>
                        </td>
                        <td className="py-3 px-4">
                          <Badge
                            className={`text-[10px] ${
                              c.category === 'Pribadi'
                                ? 'bg-purple-100 text-purple-800'
                                : c.category === 'Sosial'
                                ? 'bg-blue-100 text-blue-800'
                                : c.category === 'Belajar'
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-emerald-100 text-emerald-800'
                            }`}
                          >
                            {c.category}
                          </Badge>
                        </td>
                        <td className="py-3 px-4">
                          <div className="font-medium text-slate-800">
                            {c.counselor?.teacher?.full_name || c.counselor?.name || 'Guru BK'}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <Badge variant="outline" className="text-xs font-semibold px-2 py-0.5 border-slate-200">
                            {c.sessions_count || 0} Pertemuan
                          </Badge>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <Badge
                            className={`text-[10px] ${
                              c.status === 'aktif'
                                ? 'bg-blue-100 text-blue-800'
                                : c.status === 'selesai'
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-red-100 text-red-800'
                            }`}
                          >
                            {c.status}
                          </Badge>
                        </td>
                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleOpenDetailCase(c)}
                              className="text-xs h-7 px-2 border-slate-200 gap-1 text-indigo-700 hover:bg-indigo-50"
                              title="Lihat Detail & Catat Sesi"
                            >
                              <Eye className="h-3 w-3" />
                              Detail Sesi
                            </Button>

                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => setEditingCase(c)}
                              className="text-slate-600 hover:text-slate-900 h-7 w-7 p-0"
                              title="Ubah Status Kasus"
                            >
                              <Edit className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: RUJUKAN DARI GURU */}
      {activeTab === 'rujukan' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex items-center gap-2">
              <label className="text-xs font-semibold text-slate-700">Filter Status:</label>
              <select
                value={referralStatusFilter}
                onChange={(e) => setReferralStatusFilter(e.target.value)}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Status Rujukan</option>
                <option value="pending">Menunggu / Pending</option>
                <option value="diterima">Diterima Konselor</option>
                <option value="ditolak">Ditolak</option>
                <option value="selesai">Selesai</option>
              </select>
            </div>

            <Button
              size="sm"
              onClick={() => setIsNewReferralModalOpen(true)}
              className="bg-indigo-600 hover:bg-indigo-700 text-white gap-1.5 text-xs"
            >
              <Send className="h-3.5 w-3.5" />
              Buat Rujukan Siswa Baru
            </Button>
          </div>

          {isReferralsLoading ? (
            <div className="text-center py-12 text-xs text-slate-500">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat daftar rujukan guru...
            </div>
          ) : referrals.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
              <Send className="h-10 w-10 text-slate-300 mx-auto mb-2" />
              <h3 className="text-sm font-semibold text-slate-800">Tidak Ada Rujukan</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Guru mata pelajaran atau wali kelas dapat mengirimkan rujukan siswa yang membutuhkan perhatian bimbingan konseling di sini.
              </p>
            </div>
          ) : (
            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-sm">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-3 px-4">Tanggal Rujukan</th>
                    <th className="py-3 px-4">Siswa</th>
                    <th className="py-3 px-4">Guru Perujuk</th>
                    <th className="py-3 px-4">Alasan & Gejala yang Terlihat</th>
                    <th className="py-3 px-4 text-center">Status</th>
                    <th className="py-3 px-4 text-right">Tindak Lanjut BK</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {referrals.map((r) => (
                    <tr key={r.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-3 px-4 text-slate-600">
                        {r.created_at ? new Date(r.created_at).toLocaleDateString('id-ID') : '-'}
                      </td>
                      <td className="py-3 px-4">
                        <div className="font-semibold text-slate-900">{r.student?.full_name}</div>
                        <div className="text-[10px] text-slate-500">NIS: {r.student?.nis || '-'}</div>
                      </td>
                      <td className="py-3 px-4 font-medium text-slate-800">
                        {r.referrer?.teacher?.full_name || r.referrer?.name || 'Guru'}
                      </td>
                      <td className="py-3 px-4 max-w-xs text-slate-700">
                        <p className="line-clamp-2">{r.reason}</p>
                      </td>
                      <td className="py-3 px-4 text-center">
                        <Badge
                          className={`text-[10px] uppercase ${
                            r.status === 'pending'
                              ? 'bg-amber-100 text-amber-800'
                              : r.status === 'diterima'
                              ? 'bg-blue-100 text-blue-800'
                              : r.status === 'selesai'
                              ? 'bg-emerald-100 text-emerald-800'
                              : 'bg-red-100 text-red-800'
                          }`}
                        >
                          {r.status}
                        </Badge>
                      </td>
                      <td className="py-3 px-4 text-right">
                        {r.status === 'pending' ? (
                          <div className="flex items-center justify-end gap-1">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => {
                                handleUpdateReferralStatus(r.id, 'diterima');
                                handleOpenNewCase(r.student_id);
                              }}
                              className="text-xs h-7 px-2 border-emerald-300 text-emerald-700 hover:bg-emerald-50 gap-1"
                            >
                              <UserCheck className="h-3 w-3" />
                              Terima & Buka Kasus
                            </Button>

                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleUpdateReferralStatus(r.id, 'ditolak')}
                              className="text-xs h-7 px-2 text-red-600 hover:bg-red-50"
                            >
                              Tolak
                            </Button>
                          </div>
                        ) : (
                          <span className="text-slate-400 text-[11px] italic">Sudah Diproses</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 3: DETEKSI DINI SISWA (EARLY WARNING SYSTEM - ADM-BK-02) */}
      {activeTab === 'deteksi_dini' && (
        <EarlyWarningManager onOpenNewCase={(studentId) => handleOpenNewCase(studentId)} />
      )}

      {/* TAB 4: PERMINTAAN KONSELING SISWA & WALI (ADM-BK-03) */}
      {activeTab === 'permintaan' && (
        <CounselingRequestManager
          onCaseCreated={() => {
            fetchCases();
            setActiveTab('kasus');
          }}
        />
      )}

      {/* ========================================================================= */}
      {/* MODAL 1: BUKA KASUS KONSELING BARU */}
      {/* ========================================================================= */}
      {isNewCaseModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <HeartHandshake className="h-4 w-4 text-indigo-600" />
                Buka Rekam Kasus Konseling Baru
              </h3>
              <button onClick={() => setIsNewCaseModalOpen(false)} className="text-slate-400 hover:text-slate-600 rounded p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveNewCase} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Pilih Siswa yang Membutuhkan Bimbingan *</label>
                <select
                  required
                  value={formStudentId}
                  onChange={(e) => setFormStudentId(e.target.value)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  {students.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.full_name} ({s.nis || 'No NIS'})
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Kategori Masalah *</label>
                  <select
                    value={formCategory}
                    onChange={(e) => setFormCategory(e.target.value as CounselingCategory)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    {CATEGORIES.map((cat) => (
                      <option key={cat} value={cat}>
                        {cat}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Status Awal</label>
                  <select
                    value={formStatus}
                    onChange={(e) => setFormStatus(e.target.value as CounselingCaseStatus)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    {STATUSES.map((st) => (
                      <option key={st.value} value={st.value}>
                        {st.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1 flex items-center justify-between">
                  <span>Ringkasan Kasus / Pengantar Bimbingan *</span>
                  <span className="text-[10px] text-amber-700 flex items-center gap-1">
                    <Lock className="h-3 w-3" /> Dienkripsi Otomatis (AES-256)
                  </span>
                </label>
                <Textarea
                  required
                  placeholder="Uraikan gambaran umum masalah yang dialami siswa secara rahasia dan objektif..."
                  value={formSummary}
                  onChange={(e) => setFormSummary(e.target.value)}
                  className="text-xs h-24 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsNewCaseModalOpen(false)} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isSubmittingCase} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5">
                  {isSubmittingCase && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Buka & Amankan Kasus
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 2: DETAIL KASUS & RIWAYAT SESI KONSELING */}
      {/* ========================================================================= */}
      {detailCase && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl border overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] flex flex-col">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50 shrink-0">
              <div className="flex items-center gap-2">
                <HeartHandshake className="h-5 w-5 text-indigo-600" />
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">
                    Detail Kasus Konseling — {detailCase.student?.full_name}
                  </h3>
                  <div className="text-[11px] text-slate-500">
                    NIS: {detailCase.student?.nis || '-'} | Kategori: <strong>{detailCase.category}</strong>
                  </div>
                </div>
              </div>
              <button onClick={() => setDetailCase(null)} className="text-slate-400 hover:text-slate-600 rounded p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-4 text-xs overflow-y-auto">
              {/* Ringkasan Kasus Terenkripsi */}
              <div className="bg-slate-50 p-3.5 rounded-lg border border-slate-200">
                <div className="flex items-center justify-between mb-1.5 text-[11px] font-semibold text-slate-700">
                  <span>Ringkasan Kasus Konseling</span>
                  <Badge variant="outline" className="text-[10px] bg-emerald-50 text-emerald-800 border-emerald-300 gap-1">
                    <Lock className="h-3 w-3" /> Dekripsi Aman Aktif
                  </Badge>
                </div>
                <p className="text-slate-800 whitespace-pre-wrap leading-relaxed">{detailCase.summary_enc}</p>
              </div>

              {/* Daftar Sesi Pertemuan */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <h4 className="font-bold text-slate-800 flex items-center gap-1.5">
                    <Calendar className="h-4 w-4 text-indigo-600" />
                    Riwayat Sesi Pertemuan ({detailCase.sessions?.length || 0})
                  </h4>
                  <Button
                    size="sm"
                    onClick={handleOpenAddSession}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs h-7 px-2.5 gap-1"
                  >
                    <Plus className="h-3.5 w-3.5" /> Catat Sesi Baru
                  </Button>
                </div>

                {(!detailCase.sessions || detailCase.sessions.length === 0) ? (
                  <div className="text-center py-6 text-slate-400 italic bg-slate-50/50 rounded border border-dashed border-slate-200">
                    Belum ada sesi pertemuan yang dicatat untuk kasus ini.
                  </div>
                ) : (
                  <div className="space-y-3">
                    {detailCase.sessions.map((s, idx) => (
                      <Card key={s.id} className="border-slate-200 shadow-none bg-slate-50/50">
                        <CardHeader className="py-2.5 px-3 flex flex-row items-center justify-between border-b border-slate-100">
                          <span className="font-bold text-slate-800 text-[11px]">
                            Pertemuan #{idx + 1} — {new Date(s.session_date).toLocaleDateString('id-ID', {
                              weekday: 'long',
                              day: 'numeric',
                              month: 'long',
                              year: 'numeric',
                            })}
                          </span>
                        </CardHeader>
                        <CardContent className="py-2.5 px-3 space-y-1.5 text-xs">
                          <div>
                            <div className="font-semibold text-slate-700 text-[11px]">Catatan Sesi Privat:</div>
                            <p className="text-slate-800 whitespace-pre-wrap">{s.notes_enc}</p>
                          </div>
                          {s.follow_up && (
                            <div className="pt-1 text-[11px] text-indigo-700 border-t border-slate-100 font-medium">
                              Rekomendasi Tindak Lanjut: {s.follow_up}
                            </div>
                          )}
                        </CardContent>
                      </Card>
                    ))}
                  </div>
                )}
              </div>
            </div>

            <div className="px-5 py-3 border-t bg-slate-50 flex justify-end shrink-0">
              <Button type="button" variant="outline" size="sm" onClick={() => setDetailCase(null)} className="text-xs">
                Tutup Berkas
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 3: TAMBAH SESI KONSELING BARU */}
      {/* ========================================================================= */}
      {isSessionModalOpen && (
        <div className="fixed inset-0 z-[60] bg-black/60 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Calendar className="h-4 w-4 text-indigo-600" />
                Catat Sesi Konseling Baru
              </h3>
              <button onClick={() => setIsSessionModalOpen(false)} className="text-slate-400 hover:text-slate-600 rounded p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveSession} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Tanggal Sesi Pertemuan *</label>
                <Input
                  type="date"
                  required
                  value={sessionDate}
                  onChange={(e) => setSessionDate(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1 flex items-center justify-between">
                  <span>Catatan Sesi Konseling Privat *</span>
                  <span className="text-[10px] text-amber-700 flex items-center gap-1">
                    <Lock className="h-3 w-3" /> Terenkripsi
                  </span>
                </label>
                <Textarea
                  required
                  placeholder="Catat dinamika konseling, respon siswa, pokok masalah yang diungkapkan..."
                  value={sessionNotes}
                  onChange={(e) => setSessionNotes(e.target.value)}
                  className="text-xs h-28 resize-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Rekomendasi Tindak Lanjut (Opsional)</label>
                <Input
                  placeholder="Misal: Pemanggilan orang tua, konsultasi wali kelas, sesi ke-2 minggu depan..."
                  value={sessionFollowUp}
                  onChange={(e) => setSessionFollowUp(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsSessionModalOpen(false)} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isSubmittingSession} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5">
                  {isSubmittingSession && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Simpan Sesi Konseling
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 4: UBAH STATUS KASUS */}
      {/* ========================================================================= */}
      {editingCase && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-sm border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Edit className="h-4 w-4 text-indigo-600" />
                Ubah Status Kasus
              </h3>
              <button onClick={() => setEditingCase(null)} className="text-slate-400 hover:text-slate-600 rounded p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveUpdateCase} className="p-5 space-y-3.5 text-xs">
              <div>
                <div className="text-[11px] text-slate-500 mb-1">Siswa:</div>
                <div className="font-bold text-slate-800">{editingCase.student?.full_name}</div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Status Kasus *</label>
                <select
                  value={editingCase.status}
                  onChange={(e) =>
                    setEditingCase({
                      ...editingCase,
                      status: e.target.value as CounselingCaseStatus,
                    })
                  }
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  {STATUSES.map((st) => (
                    <option key={st.value} value={st.value}>
                      {st.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setEditingCase(null)} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isSubmittingCase} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs">
                  Simpan Status
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 5: BUAT RUJUKAN SISWA BARU */}
      {/* ========================================================================= */}
      {isNewReferralModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Send className="h-4 w-4 text-indigo-600" />
                Kirim Rujukan Siswa ke Guru BK
              </h3>
              <button onClick={() => setIsNewReferralModalOpen(false)} className="text-slate-400 hover:text-slate-600 rounded p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveReferral} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Pilih Siswa yang Dirujuk *</label>
                <select
                  required
                  value={referralStudentId}
                  onChange={(e) => setReferralStudentId(e.target.value)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  {students.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.full_name} ({s.nis || 'No NIS'})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Alasan & Indikasi Masalah *</label>
                <Textarea
                  required
                  placeholder="Uraikan alasan rujukan (misal: penurunan drastis motivasi belajar, perubahan perilaku, sering melamun)..."
                  value={referralReason}
                  onChange={(e) => setReferralReason(e.target.value)}
                  className="text-xs h-24 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button type="button" variant="outline" size="sm" onClick={() => setIsNewReferralModalOpen(false)} className="text-xs">
                  Batal
                </Button>
                <Button type="submit" size="sm" disabled={isSubmittingReferral} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5">
                  {isSubmittingReferral && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Kirimkan Rujukan
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
