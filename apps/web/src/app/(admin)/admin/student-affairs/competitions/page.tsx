'use client';

import React, { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import {
  Trophy,
  Award,
  Medal,
  Calendar,
  Users,
  CheckCircle2,
  AlertTriangle,
  X,
  Search,
  Filter,
  Plus,
  RefreshCw,
  Eye,
  Edit,
  Trash2,
  FileCheck,
  ShieldCheck,
  FileText,
  Clock,
  Sparkles,
  ExternalLink,
  ChevronRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { competitionsApi } from '@/features/student-affairs/competitions-api';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import {
  CompetitionItem,
  CompetitionEntryItem,
  AchievementItem,
  CompetitionSummary,
  CompetitionLevel,
  CompetitionStatus,
  CompetitionEntryStatus,
} from '@/features/student-affairs/types';
import { studentsApi } from '@/features/master/students-api';
import { teachersApi } from '@/features/master/teachers-api';
import { StudentItem, TeacherItem } from '@/types/master';

const LEVELS: CompetitionLevel[] = [
  'Sekolah',
  'Kecamatan',
  'Kota',
  'Kabupaten',
  'Provinsi',
  'Nasional',
  'Internasional',
];

const CATEGORIES = [
  'Sains & Matematika',
  'Teknologi & Robotik',
  'Olahraga & Bela Diri',
  'Seni & Musik',
  'Bahasa & Sastra',
  'Keagamaan',
  'Lainnya',
];

export default function CompetitionsAndAchievementsPage() {
  const [activeTab, setActiveTab] = useState<'lomba' | 'prestasi' | 'verifikasi'>('lomba');

  // Master & Entity States
  const [competitions, setCompetitions] = useState<CompetitionItem[]>([]);
  const [achievements, setAchievements] = useState<AchievementItem[]>([]);
  const [summary, setSummary] = useState<CompetitionSummary | null>(null);
  const [students, setStudents] = useState<StudentItem[]>([]);
  const [teachers, setTeachers] = useState<TeacherItem[]>([]);

  // Loading & Feedback States
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<{
    isOpen: boolean;
    title: string;
    description: string;
    affectedItemName?: string;
    onConfirm: () => Promise<void>;
    isLoading?: boolean;
  }>({
    isOpen: false,
    title: '',
    description: '',
    onConfirm: async () => {},
    isLoading: false,
  });

  // Filters State
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedLevel, setSelectedLevel] = useState<string>('all');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [achievementSearch, setAchievementSearch] = useState<string>('');
  const [verificationFilter, setVerificationFilter] = useState<string>('all');

  // Modals States
  const [isNewCompModalOpen, setIsNewCompModalOpen] = useState<boolean>(false);
  const [editingComp, setEditingComp] = useState<CompetitionItem | null>(null);
  const [isEntriesModalOpen, setIsEntriesModalOpen] = useState<boolean>(false);
  const [selectedCompForEntries, setSelectedCompForEntries] = useState<CompetitionItem | null>(null);
  const [entries, setEntries] = useState<CompetitionEntryItem[]>([]);
  const [isNewEntryModalOpen, setIsNewEntryModalOpen] = useState<boolean>(false);
  const [isNewAchievementModalOpen, setIsNewAchievementModalOpen] = useState<boolean>(false);
  const [detailAchievement, setDetailAchievement] = useState<AchievementItem | null>(null);
  const [verifyingAchievement, setVerifyingAchievement] = useState<AchievementItem | null>(null);
  const [rejectionReason, setRejectionReason] = useState<string>('');

  // Form State: Tambah/Edit Kompetisi
  const [compName, setCompName] = useState<string>('');
  const [compOrganizer, setCompOrganizer] = useState<string>('');
  const [compLevel, setCompLevel] = useState<CompetitionLevel>('Kota');
  const [compCategory, setCompCategory] = useState<string>('Sains & Matematika');
  const [compHeldAt, setCompHeldAt] = useState<string>(new Date().toISOString().split('T')[0]);
  const [compDeadline, setCompDeadline] = useState<string>('');
  const [compStatus, setCompStatus] = useState<CompetitionStatus>('pendaftaran');
  const [compDescription, setCompDescription] = useState<string>('');
  const [isSubmittingComp, setIsSubmittingComp] = useState<boolean>(false);

  // Form State: Daftarkan Delegasi Siswa
  const [entryStudentId, setEntryStudentId] = useState<string>('');
  const [entryCoachId, setEntryCoachId] = useState<string>('');
  const [entryStatus, setEntryStatus] = useState<CompetitionEntryStatus>('terdaftar');
  const [entryNotes, setEntryNotes] = useState<string>('');
  const [isSubmittingEntry, setIsSubmittingEntry] = useState<boolean>(false);

  // Form State: Catat Prestasi Baru
  const [achStudentId, setAchStudentId] = useState<string>('');
  const [achCompetitionId, setAchCompetitionId] = useState<string>('');
  const [achTitle, setAchTitle] = useState<string>('');
  const [achRankLevel, setAchRankLevel] = useState<string>('Juara 1');
  const [achAchievedAt, setAchAchievedAt] = useState<string>(new Date().toISOString().split('T')[0]);
  const [achPointsAwarded, setAchPointsAwarded] = useState<number>(30);
  const [achNotes, setAchNotes] = useState<string>('');
  const [isSubmittingAch, setIsSubmittingAch] = useState<boolean>(false);

  // 1. Fetch Data
  const fetchData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [compRes, achRes, sumRes, stdRes, tchRes] = await Promise.all([
        competitionsApi.getCompetitions({
          level: selectedLevel === 'all' ? undefined : selectedLevel,
          status: selectedStatus === 'all' ? undefined : selectedStatus,
          category: selectedCategory === 'all' ? undefined : selectedCategory,
          search: searchQuery || undefined,
          per_page: 50,
        }),
        competitionsApi.getAchievements({
          verification_status: verificationFilter === 'all' ? undefined : verificationFilter,
          search: achievementSearch || undefined,
          per_page: 50,
        }),
        competitionsApi.getSummary(),
        studentsApi.list({ limit: 100, status: 'aktif' }),
        teachersApi.list({ limit: 100 }),
      ]);

      setCompetitions(compRes.data || []);
      setAchievements(achRes.data || []);
      setSummary(sumRes.data || null);
      setStudents(stdRes.data || []);
      setTeachers(tchRes.data || []);

      if (stdRes.data?.length && !entryStudentId) {
        setEntryStudentId(stdRes.data[0].id);
        setAchStudentId(stdRes.data[0].id);
      }
      if (tchRes.data?.length && !entryCoachId) {
        setEntryCoachId(tchRes.data[0].id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat data lomba & prestasi';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, [
    selectedLevel,
    selectedStatus,
    selectedCategory,
    searchQuery,
    verificationFilter,
    achievementSearch,
    entryStudentId,
    entryCoachId,
  ]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Handlers: Kompetisi
  const handleOpenNewCompModal = () => {
    setEditingComp(null);
    setCompName('');
    setCompOrganizer('');
    setCompLevel('Kota');
    setCompCategory('Sains & Matematika');
    setCompHeldAt(new Date().toISOString().split('T')[0]);
    setCompDeadline('');
    setCompStatus('pendaftaran');
    setCompDescription('');
    setIsNewCompModalOpen(true);
  };

  const handleOpenEditCompModal = (comp: CompetitionItem) => {
    setEditingComp(comp);
    setCompName(comp.name);
    setCompOrganizer(comp.organizer || '');
    setCompLevel(comp.level as CompetitionLevel);
    setCompCategory(comp.category || 'Sains & Matematika');
    setCompHeldAt(comp.held_at);
    setCompDeadline(comp.registration_deadline || '');
    setCompStatus(comp.status as CompetitionStatus);
    setCompDescription(comp.description || '');
    setIsNewCompModalOpen(true);
  };

  const handleSaveCompetition = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!compName.trim()) {
      setError('Nama kompetisi wajib diisi.');
      return;
    }

    setIsSubmittingComp(true);
    setError(null);
    try {
      const payload = {
        name: compName.trim(),
        organizer: compOrganizer.trim() || undefined,
        level: compLevel,
        category: compCategory,
        held_at: compHeldAt,
        registration_deadline: compDeadline || undefined,
        status: compStatus,
        description: compDescription.trim() || undefined,
      };

      if (editingComp) {
        await competitionsApi.updateCompetition(editingComp.id, payload);
        setSuccessMessage('Data kompetisi berhasil diperbarui.');
      } else {
        await competitionsApi.createCompetition(payload);
        setSuccessMessage('Kompetisi baru berhasil didaftarkan ke katalog sekolah.');
      }

      setIsNewCompModalOpen(false);
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan data kompetisi';
      setError(msg);
    } finally {
      setIsSubmittingComp(false);
    }
  };

  const handleDeleteCompetition = (id: string, name: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Kompetisi',
      description: 'Apakah Anda yakin ingin menghapus kompetisi ini beserta data delegasinya?',
      affectedItemName: name,
      onConfirm: async () => {
        try {
          await competitionsApi.deleteCompetition(id);
          setSuccessMessage(`Kompetisi "${name}" berhasil dihapus.`);
          toast.success(`Kompetisi "${name}" berhasil dihapus.`);
          fetchData();
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal menghapus kompetisi';
          setError(msg);
          toast.error(msg);
        }
      },
    });
  };

  // Handlers: Delegasi
  const handleOpenEntriesModal = async (comp: CompetitionItem) => {
    setSelectedCompForEntries(comp);
    setIsEntriesModalOpen(true);
    try {
      const res = await competitionsApi.getEntries(comp.id);
      setEntries(res.data || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat daftar delegasi';
      setError(msg);
    }
  };

  const handleSaveEntry = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedCompForEntries || !entryStudentId) {
      setError('Pilih siswa yang akan didaftarkan.');
      return;
    }

    setIsSubmittingEntry(true);
    setError(null);
    try {
      await competitionsApi.registerEntry(selectedCompForEntries.id, {
        student_id: entryStudentId,
        coach_teacher_id: entryCoachId || undefined,
        status: entryStatus,
        notes: entryNotes.trim() || undefined,
      });

      setSuccessMessage('Delegasi siswa berhasil didaftarkan pada kompetisi.');
      setIsNewEntryModalOpen(false);
      setEntryNotes('');
      // Refresh entries
      const res = await competitionsApi.getEntries(selectedCompForEntries.id);
      setEntries(res.data || []);
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mendaftarkan delegasi siswa';
      setError(msg);
    } finally {
      setIsSubmittingEntry(false);
    }
  };

  const handleDeleteEntry = (id: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Delegasi Siswa',
      description: 'Apakah Anda yakin ingin menghapus siswa ini dari daftar delegasi kompetisi?',
      onConfirm: async () => {
        try {
          await competitionsApi.deleteEntry(id);
          setSuccessMessage('Delegasi siswa berhasil dihapus.');
          toast.success('Delegasi siswa berhasil dihapus.');
          if (selectedCompForEntries) {
            const res = await competitionsApi.getEntries(selectedCompForEntries.id);
            setEntries(res.data || []);
          }
          fetchData();
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal menghapus delegasi';
          setError(msg);
          toast.error(msg);
        }
      },
    });
  };

  // Handlers: Prestasi
  const handleOpenNewAchievementModal = () => {
    setAchTitle('');
    setAchCompetitionId('');
    setAchRankLevel('Juara 1');
    setAchAchievedAt(new Date().toISOString().split('T')[0]);
    setAchPointsAwarded(30);
    setAchNotes('');
    setIsNewAchievementModalOpen(true);
  };

  const handleSaveAchievement = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!achStudentId || !achTitle.trim()) {
      setError('Siswa dan judul prestasi wajib diisi.');
      return;
    }

    setIsSubmittingAch(true);
    setError(null);
    try {
      await competitionsApi.createAchievement({
        student_id: achStudentId,
        competition_id: achCompetitionId || undefined,
        title: achTitle.trim(),
        rank_level: achRankLevel,
        achieved_at: achAchievedAt,
        points_awarded: Number(achPointsAwarded) || 25,
        notes: achNotes.trim() || undefined,
      });

      setSuccessMessage('Prestasi siswa berhasil dicatat dan poin apresiasi diterbitkan.');
      setIsNewAchievementModalOpen(false);
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mencatat prestasi siswa';
      setError(msg);
    } finally {
      setIsSubmittingAch(false);
    }
  };

  const handleDeleteAchievement = (id: string, title: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Prestasi Siswa',
      description: `Apakah Anda yakin ingin menghapus data prestasi "${title}"? Poin apresiasi terkait akan disesuaikan.`,
      affectedItemName: title,
      onConfirm: async () => {
        try {
          await competitionsApi.deleteAchievement(id);
          setSuccessMessage(`Prestasi "${title}" berhasil dihapus.`);
          toast.success(`Prestasi "${title}" berhasil dihapus.`);
          fetchData();
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal menghapus prestasi';
          setError(msg);
          toast.error(msg);
        }
      },
    });
  };

  // Handlers: Verifikasi Prestasi
  const handleConfirmVerify = async (status: 'verified' | 'rejected') => {
    if (!verifyingAchievement) return;
    if (status === 'rejected' && !rejectionReason.trim()) {
      setError('Catatan alasan penolakan wajib diisi bila menolak berkas.');
      return;
    }

    try {
      await competitionsApi.verifyAchievement(verifyingAchievement.id, {
        status,
        rejection_reason: status === 'rejected' ? rejectionReason.trim() : undefined,
      });

      setSuccessMessage(
        status === 'verified'
          ? 'Bukti prestasi berhasil diverifikasi dan poin penghargaan disahkan.'
          : 'Pengajuan prestasi ditolak.'
      );
      setVerifyingAchievement(null);
      setRejectionReason('');
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memverifikasi prestasi';
      setError(msg);
    }
  };

  // Badges Helper
  const getLevelBadge = (level: string) => {
    switch (level) {
      case 'Internasional':
        return <Badge className="bg-purple-100 text-purple-800 border-purple-300">🌍 Internasional</Badge>;
      case 'Nasional':
        return <Badge className="bg-blue-100 text-blue-800 border-blue-300">🇮🇩 Nasional</Badge>;
      case 'Provinsi':
        return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">🏛️ Provinsi</Badge>;
      case 'Kota':
      case 'Kabupaten':
        return <Badge className="bg-amber-100 text-amber-800 border-amber-300">🏙️ Kota/Kab</Badge>;
      default:
        return <Badge className="bg-slate-100 text-slate-700 border-slate-300">🏫 Sekolah</Badge>;
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'pendaftaran':
        return <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">Buka Pendaftaran</Badge>;
      case 'berlangsung':
        return <Badge className="bg-blue-100 text-blue-800 border-blue-300">Sedang Berlangsung</Badge>;
      case 'selesai':
        return <Badge className="bg-slate-100 text-slate-700 border-slate-300">Selesai</Badge>;
      case 'dibatalkan':
        return <Badge className="bg-red-100 text-red-800 border-red-300">Dibatalkan</Badge>;
      default:
        return <Badge className="bg-amber-100 text-amber-800 border-amber-300">Direncanakan</Badge>;
    }
  };

  const getRankBadge = (rank: string) => {
    const lower = rank.toLowerCase();
    if (lower.includes('1') || lower.includes('emas')) {
      return <Badge className="bg-amber-100 text-amber-900 border-amber-400 font-bold">🥇 {rank}</Badge>;
    }
    if (lower.includes('2') || lower.includes('perak')) {
      return <Badge className="bg-slate-200 text-slate-800 border-slate-400 font-bold">🥈 {rank}</Badge>;
    }
    if (lower.includes('3') || lower.includes('perunggu')) {
      return <Badge className="bg-amber-800/10 text-amber-800 border-amber-700/30 font-bold">🥉 {rank}</Badge>;
    }
    return <Badge className="bg-indigo-100 text-indigo-800 border-indigo-300">🎖️ {rank}</Badge>;
  };

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="rounded-xl bg-gradient-to-r from-slate-900 via-amber-950 to-indigo-950 p-6 text-white shadow-elevated">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <Badge className="bg-amber-500/20 text-amber-300 border border-amber-400/30">
                ADM-STD-03 • P1 SSOT
              </Badge>
              <span className="text-xs text-amber-200">•</span>
              <span className="text-xs text-amber-200">Kesiswaan Labschool UM</span>
            </div>
            <h1 className="text-2xl font-bold tracking-tight">Lomba, Delegasi & Prestasi Siswa 🏆</h1>
            <p className="mt-1 text-sm text-slate-300 max-w-2xl">
              Pusat pengelolaan katalog kompetisi, penugasan delegasi siswa kontingen sekolah, pencatatan hasil kejuaraan & medali,
              verifikasi sertifikat bukti prestasi, serta otomatisasi penerbitan poin apresiasi karakter.
            </p>
          </div>

          <div className="flex items-center gap-2.5">
            <Button
              variant="outline"
              size="sm"
              onClick={fetchData}
              disabled={isLoading}
              className="bg-white/10 hover:bg-white/20 text-white border-white/20 text-xs"
            >
              <RefreshCw className={`h-4 w-4 mr-1.5 ${isLoading ? 'animate-spin' : ''}`} />
              Segarkan
            </Button>
            <Button
              size="sm"
              onClick={handleOpenNewAchievementModal}
              className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs gap-1.5 shadow-sm"
            >
              <Award className="h-4 w-4" />
              Catat Prestasi Baru
            </Button>
            <Button
              size="sm"
              onClick={handleOpenNewCompModal}
              className="bg-amber-600 hover:bg-amber-500 text-white text-xs gap-1.5 shadow-sm"
            >
              <Plus className="h-4 w-4" />
              Tambah Lomba Baru
            </Button>
          </div>
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
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Total Kompetisi Terdaftar</CardTitle>
            <Trophy className="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">{summary?.total_competitions ?? competitions.length}</div>
            <p className="text-[11px] text-slate-500 mt-1">
              <strong>{summary?.active_competitions ?? 0}</strong> lomba aktif / pendaftaran dibuka
            </p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Delegasi Siswa Dikirim</CardTitle>
            <Users className="h-4 w-4 text-blue-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">{summary?.total_entries ?? 0} Peserta</div>
            <p className="text-[11px] text-slate-500 mt-1">Siswa delegasi kontingen lomba sekolah</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Prestasi Terverifikasi</CardTitle>
            <Award className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">{summary?.verified_achievements ?? achievements.length}</div>
            <p className="text-[11px] text-slate-500 mt-1">
              {summary?.pending_verification ? (
                <span className="text-amber-600 font-semibold">{summary.pending_verification} menunggu verifikasi</span>
              ) : (
                'Semua data prestasi tuntas diverifikasi'
              )}
            </p>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Galeri Medali Kejuaraan</CardTitle>
            <Medal className="h-4 w-4 text-purple-500" />
          </CardHeader>
          <CardContent>
            <div className="flex items-baseline gap-2">
              <span className="text-lg font-bold text-amber-600">🥇 {summary?.medals.emas ?? 0}</span>
              <span className="text-lg font-bold text-slate-600">🥈 {summary?.medals.perak ?? 0}</span>
              <span className="text-lg font-bold text-amber-800">🥉 {summary?.medals.perunggu ?? 0}</span>
            </div>
            <p className="text-[11px] text-slate-500 mt-1">
              +{summary?.medals.finalis ?? 0} peringkat finalis & juara harapan
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs Menu Navigation */}
      <div className="border-b border-slate-200">
        <div className="flex gap-4">
          <button
            onClick={() => setActiveTab('lomba')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'lomba'
                ? 'border-amber-600 text-amber-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <Trophy className="h-4 w-4" />
            Katalog Lomba & Pendaftaran Delegasi ({competitions.length})
          </button>

          <button
            onClick={() => setActiveTab('prestasi')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'prestasi'
                ? 'border-amber-600 text-amber-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <Award className="h-4 w-4" />
            Galeri Prestasi & Piala Siswa ({achievements.length})
          </button>

          <button
            onClick={() => setActiveTab('verifikasi')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'verifikasi'
                ? 'border-amber-600 text-amber-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <FileCheck className="h-4 w-4" />
            Verifikasi Sertifikat & Dokumen Bukti
            {summary?.pending_verification ? (
              <span className="ml-1 px-1.5 py-0.2 rounded-full text-[10px] bg-amber-500 text-white font-bold">
                {summary.pending_verification}
              </span>
            ) : null}
          </button>
        </div>
      </div>

      {/* ========================================================================= */}
      {/* TAB 1: KATALOG LOMBA & DELEGASI SISWA */}
      {/* ========================================================================= */}
      {activeTab === 'lomba' && (
        <div className="space-y-4">
          {/* Filters Bar */}
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                <Input
                  placeholder="Cari nama lomba atau penyelenggara..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && fetchData()}
                  className="pl-8 text-xs h-9 bg-white"
                />
              </div>

              <select
                value={selectedLevel}
                onChange={(e) => setSelectedLevel(e.target.value)}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Tingkat</option>
                {LEVELS.map((lvl) => (
                  <option key={lvl} value={lvl}>
                    {lvl}
                  </option>
                ))}
              </select>

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
                <option value="pendaftaran">Buka Pendaftaran</option>
                <option value="berlangsung">Sedang Berlangsung</option>
                <option value="selesai">Selesai</option>
                <option value="direncanakan">Direncanakan</option>
              </select>

              <Button variant="outline" size="sm" onClick={fetchData} className="text-xs h-9 bg-white">
                Terapkan
              </Button>
            </div>
          </div>

          {/* Table Kompetisi */}
          {isLoading ? (
            <div className="text-center py-12 text-xs text-slate-500 bg-white rounded-lg border">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat katalog kompetisi & lomba...
            </div>
          ) : competitions.length === 0 ? (
            <div className="text-center py-12 text-xs text-slate-500 bg-white rounded-lg border">
              <Trophy className="h-8 w-8 text-slate-300 mx-auto mb-2" />
              Belum ada data kompetisi yang terdaftar pada filter ini.
            </div>
          ) : (
            <div className="overflow-x-auto bg-white rounded-lg border border-slate-200 shadow-sm">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-3 px-4">Nama Kompetisi / Lomba</th>
                    <th className="py-3 px-4">Penyelenggara & Kategori</th>
                    <th className="py-3 px-4">Tingkat</th>
                    <th className="py-3 px-4">Tanggal Pelaksanaan</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4 text-center">Delegasi</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {competitions.map((comp) => (
                    <tr key={comp.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="py-3 px-4 font-medium text-slate-900 max-w-xs">
                        <div className="font-semibold">{comp.name}</div>
                        {comp.description && (
                          <div className="text-[11px] text-slate-500 line-clamp-1">{comp.description}</div>
                        )}
                      </td>
                      <td className="py-3 px-4 text-slate-700">
                        <div>{comp.organizer || '-'}</div>
                        <div className="text-[11px] text-slate-400">{comp.category || 'Umum'}</div>
                      </td>
                      <td className="py-3 px-4 whitespace-nowrap">{getLevelBadge(comp.level)}</td>
                      <td className="py-3 px-4 whitespace-nowrap text-slate-600">
                        <div className="flex items-center gap-1.5">
                          <Calendar className="h-3.5 w-3.5 text-slate-400" />
                          <span>{comp.held_at}</span>
                        </div>
                        {comp.registration_deadline && (
                          <div className="text-[11px] text-slate-400 mt-0.5">
                            Batas: {comp.registration_deadline}
                          </div>
                        )}
                      </td>
                      <td className="py-3 px-4 whitespace-nowrap">{getStatusBadge(comp.status)}</td>
                      <td className="py-3 px-4 text-center whitespace-nowrap">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleOpenEntriesModal(comp)}
                          className="text-xs h-7 px-2 text-indigo-600 hover:bg-indigo-50 font-semibold"
                        >
                          <Users className="h-3.5 w-3.5 mr-1" />
                          {comp.entries?.length ?? 0} Siswa
                        </Button>
                      </td>
                      <td className="py-3 px-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenEntriesModal(comp)}
                            className="text-xs h-7 px-2 border-indigo-200 text-indigo-700 hover:bg-indigo-50"
                          >
                            Kelola Delegasi
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleOpenEditCompModal(comp)}
                            className="text-xs h-7 px-2 text-slate-600 hover:bg-slate-100"
                          >
                            <Edit className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleDeleteCompetition(comp.id, comp.name)}
                            className="text-xs h-7 px-2 text-red-600 hover:bg-red-50"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 2: GALERI PRESTASI & PIALA SISWA */}
      {/* ========================================================================= */}
      {activeTab === 'prestasi' && (
        <div className="space-y-4">
          {/* Filters Bar */}
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                <Input
                  placeholder="Cari prestasi, judul, atau nama siswa..."
                  value={achievementSearch}
                  onChange={(e) => setAchievementSearch(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && fetchData()}
                  className="pl-8 text-xs h-9 bg-white"
                />
              </div>

              <select
                value={verificationFilter}
                onChange={(e) => setVerificationFilter(e.target.value)}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Status Verifikasi</option>
                <option value="verified">Terverifikasi</option>
                <option value="pending">Menunggu Verifikasi</option>
                <option value="rejected">Ditolak</option>
              </select>

              <Button variant="outline" size="sm" onClick={fetchData} className="text-xs h-9 bg-white">
                Terapkan
              </Button>
            </div>
          </div>

          {/* Table Prestasi */}
          {isLoading ? (
            <div className="text-center py-12 text-xs text-slate-500 bg-white rounded-lg border">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat galeri prestasi siswa...
            </div>
          ) : achievements.length === 0 ? (
            <div className="text-center py-12 text-xs text-slate-500 bg-white rounded-lg border">
              <Award className="h-8 w-8 text-slate-300 mx-auto mb-2" />
              Belum ada riwayat prestasi siswa yang tercatat.
            </div>
          ) : (
            <div className="overflow-x-auto bg-white rounded-lg border border-slate-200 shadow-sm">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-3 px-4">Nama Siswa</th>
                    <th className="py-3 px-4">Prestasi & Kejuaraan</th>
                    <th className="py-3 px-4">Peringkat / Capaian</th>
                    <th className="py-3 px-4">Tanggal Raihan</th>
                    <th className="py-3 px-4 text-center">Poin Apresiasi</th>
                    <th className="py-3 px-4">Verifikasi</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {achievements.map((ach) => (
                    <tr key={ach.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="py-3 px-4 font-medium text-slate-900 whitespace-nowrap">
                        <div className="font-semibold">{ach.student?.full_name || 'Siswa'}</div>
                        <div className="text-[11px] text-slate-400">
                          NIS: {ach.student?.nis || '-'} • {ach.student?.classes?.[0]?.name || 'Kelas'}
                        </div>
                      </td>
                      <td className="py-3 px-4 text-slate-700 max-w-xs">
                        <div className="font-medium text-slate-900">{ach.title}</div>
                        {ach.competition && (
                          <div className="text-[11px] text-slate-500 line-clamp-1">
                            Lomba: {ach.competition.name} ({ach.competition.level})
                          </div>
                        )}
                      </td>
                      <td className="py-3 px-4 whitespace-nowrap">{getRankBadge(ach.rank_level)}</td>
                      <td className="py-3 px-4 whitespace-nowrap text-slate-600 font-mono">
                        {ach.achieved_at}
                      </td>
                      <td className="py-3 px-4 text-center whitespace-nowrap">
                        <Badge variant="outline" className="bg-emerald-50 text-emerald-800 border-emerald-300 font-mono">
                          +{ach.points_awarded} Poin
                        </Badge>
                      </td>
                      <td className="py-3 px-4 whitespace-nowrap">
                        {ach.verification_status === 'verified' ? (
                          <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 flex items-center gap-1 w-fit">
                            <CheckCircle2 className="h-3 w-3" /> Terverifikasi
                          </Badge>
                        ) : ach.verification_status === 'rejected' ? (
                          <Badge className="bg-red-100 text-red-800 border-red-300 flex items-center gap-1 w-fit">
                            <X className="h-3 w-3" /> Ditolak
                          </Badge>
                        ) : (
                          <Badge className="bg-amber-100 text-amber-800 border-amber-300 flex items-center gap-1 w-fit">
                            <Clock className="h-3 w-3" /> Menunggu
                          </Badge>
                        )}
                      </td>
                      <td className="py-3 px-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDetailAchievement(ach)}
                            className="text-xs h-7 px-2 text-indigo-600 hover:bg-indigo-50"
                          >
                            <Eye className="h-3.5 w-3.5" />
                          </Button>
                          {ach.verification_status === 'pending' && (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => {
                                setVerifyingAchievement(ach);
                                setRejectionReason('');
                              }}
                              className="text-xs h-7 px-2 border-emerald-300 text-emerald-700 hover:bg-emerald-50"
                            >
                              Verifikasi
                            </Button>
                          )}
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleDeleteAchievement(ach.id, ach.title)}
                            className="text-xs h-7 px-2 text-red-600 hover:bg-red-50"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 3: VERIFIKASI BUKTI DOKUMEN & SERTIFIKAT */}
      {/* ========================================================================= */}
      {activeTab === 'verifikasi' && (
        <div className="space-y-4">
          <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 flex items-start gap-3">
            <FileCheck className="h-5 w-5 text-indigo-600 shrink-0 mt-0.5" />
            <div className="text-xs text-slate-700">
              <h4 className="font-bold text-slate-900">Alur Verifikasi Sertifikat & Validasi Poin Apresiasi</h4>
              <p className="mt-1 leading-relaxed">
                Staf Kesiswaan berwenang memeriksa keabsahan bukti sertifikat atau foto piala/medali sebelum poin prestasi
                resmi diakumulasikan ke Buku Nilai Sikap dan E-Rapor Semester. Pengesahan akan otomatis menerbitkan poin
                pada modul kedisiplinan siswa.
              </p>
            </div>
          </div>

          {/* Pending Verifications */}
          <div className="overflow-x-auto bg-white rounded-lg border border-slate-200 shadow-sm">
            <table className="w-full text-xs text-left">
              <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                <tr>
                  <th className="py-3 px-4">Tanggal Raihan</th>
                  <th className="py-3 px-4">Siswa</th>
                  <th className="py-3 px-4">Judul Prestasi</th>
                  <th className="py-3 px-4">Peringkat</th>
                  <th className="py-3 px-4 text-center">Status Saat Ini</th>
                  <th className="py-3 px-4 text-right">Tindakan Verifikasi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {achievements.filter((a) => a.verification_status !== 'verified').length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-center py-8 text-xs text-slate-500">
                      Tidak ada permohonan verifikasi prestasi yang tertunda. Semua berkas telah disahkan.
                    </td>
                  </tr>
                ) : (
                  achievements
                    .filter((a) => a.verification_status !== 'verified')
                    .map((ach) => (
                      <tr key={ach.id} className="hover:bg-slate-50/70 transition-colors">
                        <td className="py-3 px-4 font-mono text-slate-600 whitespace-nowrap">
                          {ach.achieved_at}
                        </td>
                        <td className="py-3 px-4 font-medium text-slate-900 whitespace-nowrap">
                          <div>{ach.student?.full_name}</div>
                          <div className="text-[11px] text-slate-400">NIS: {ach.student?.nis || '-'}</div>
                        </td>
                        <td className="py-3 px-4 text-slate-700 font-medium">
                          {ach.title}
                        </td>
                        <td className="py-3 px-4 whitespace-nowrap">{getRankBadge(ach.rank_level)}</td>
                        <td className="py-3 px-4 text-center whitespace-nowrap">
                          {ach.verification_status === 'rejected' ? (
                            <Badge className="bg-red-100 text-red-800 border-red-300">Ditolak</Badge>
                          ) : (
                            <Badge className="bg-amber-100 text-amber-800 border-amber-300">Menunggu</Badge>
                          )}
                        </td>
                        <td className="py-3 px-4 text-right whitespace-nowrap">
                          <Button
                            size="sm"
                            onClick={() => {
                              setVerifyingAchievement(ach);
                              setRejectionReason(ach.rejection_reason || '');
                            }}
                            className="text-xs h-7 px-3 bg-indigo-600 hover:bg-indigo-700 text-white"
                          >
                            Periksa & Verifikasi
                          </Button>
                        </td>
                      </tr>
                    ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 1: TAMBAH / EDIT KOMPETISI LOMBA */}
      {/* ========================================================================= */}
      {isNewCompModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Trophy className="h-4 w-4 text-amber-600" />
                {editingComp ? 'Perbarui Data Kompetisi' : 'Daftarkan Kompetisi Baru'}
              </h3>
              <button
                onClick={() => setIsNewCompModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveCompetition} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Nama Kompetisi / Lomba *</label>
                <Input
                  required
                  placeholder="Misal: Olimpiade Sains Nasional (OSN) 2026"
                  value={compName}
                  onChange={(e) => setCompName(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Penyelenggara</label>
                  <Input
                    placeholder="Misal: Kemendikbudristek / Puspresnas"
                    value={compOrganizer}
                    onChange={(e) => setCompOrganizer(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Tingkat *</label>
                  <select
                    value={compLevel}
                    onChange={(e) => setCompLevel(e.target.value as CompetitionLevel)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    {LEVELS.map((lvl) => (
                      <option key={lvl} value={lvl}>
                        {lvl}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Kategori Bidang</label>
                  <select
                    value={compCategory}
                    onChange={(e) => setCompCategory(e.target.value)}
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
                  <label className="block font-medium text-slate-700 mb-1">Status Lomba</label>
                  <select
                    value={compStatus}
                    onChange={(e) => setCompStatus(e.target.value as CompetitionStatus)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    <option value="direncanakan">Direncanakan</option>
                    <option value="pendaftaran">Buka Pendaftaran</option>
                    <option value="berlangsung">Sedang Berlangsung</option>
                    <option value="selesai">Selesai</option>
                    <option value="dibatalkan">Dibatalkan</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Tanggal Pelaksanaan *</label>
                  <Input
                    type="date"
                    required
                    value={compHeldAt}
                    onChange={(e) => setCompHeldAt(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Batas Pendaftaran</label>
                  <Input
                    type="date"
                    value={compDeadline}
                    onChange={(e) => setCompDeadline(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Deskripsi & Ketentuan</label>
                <Textarea
                  placeholder="Informasi persyaratan berkas, juknis, atau keterangan tambahan lomba..."
                  value={compDescription}
                  onChange={(e) => setCompDescription(e.target.value)}
                  className="text-xs h-20 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsNewCompModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingComp}
                  className="bg-amber-600 hover:bg-amber-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingComp && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  {editingComp ? 'Simpan Perubahan' : 'Daftarkan Lomba'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 2: KELOLA DELEGASI PESERTA KOMPETISI */}
      {/* ========================================================================= */}
      {isEntriesModalOpen && selectedCompForEntries && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl border overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] flex flex-col">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50 shrink-0">
              <div>
                <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                  <Users className="h-4 w-4 text-blue-600" />
                  Delegasi Peserta — {selectedCompForEntries.name}
                </h3>
                <div className="text-[11px] text-slate-500 mt-0.5">
                  Tingkat {selectedCompForEntries.level} • {selectedCompForEntries.held_at}
                </div>
              </div>
              <button
                onClick={() => setIsEntriesModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 overflow-y-auto space-y-4 text-xs">
              <div className="flex items-center justify-between">
                <div className="font-semibold text-slate-800">
                  Daftar Kontingen Sekolah ({entries.length} Siswa)
                </div>
                <Button
                  size="sm"
                  onClick={() => setIsNewEntryModalOpen(true)}
                  className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5 h-8"
                >
                  <Plus className="h-3.5 w-3.5" />
                  Daftarkan Siswa
                </Button>
              </div>

              {entries.length === 0 ? (
                <div className="text-center py-8 text-xs text-slate-500 border rounded-lg">
                  Belum ada siswa yang didaftarkan sebagai delegasi pada kompetisi ini.
                </div>
              ) : (
                <div className="overflow-x-auto border rounded-lg">
                  <table className="w-full text-xs text-left">
                    <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                      <tr>
                        <th className="py-2.5 px-3">Siswa Delegasi</th>
                        <th className="py-2.5 px-3">Guru Pembimbing</th>
                        <th className="py-2.5 px-3">Status</th>
                        <th className="py-2.5 px-3">Hasil Lomba</th>
                        <th className="py-2.5 px-3 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {entries.map((entry) => (
                        <tr key={entry.id} className="hover:bg-slate-50/70">
                          <td className="py-2.5 px-3 font-medium text-slate-900 whitespace-nowrap">
                            <div>{entry.student?.full_name || 'Siswa'}</div>
                            <div className="text-[10px] text-slate-400">
                              NIS: {entry.student?.nis || '-'} • {entry.student?.classes?.[0]?.name || 'Kelas'}
                            </div>
                          </td>
                          <td className="py-2.5 px-3 text-slate-700 whitespace-nowrap">
                            {entry.coach?.full_name || '-'}
                          </td>
                          <td className="py-2.5 px-3 whitespace-nowrap">
                            <Badge variant="outline" className="text-[10px]">
                              {entry.status}
                            </Badge>
                          </td>
                          <td className="py-2.5 px-3 whitespace-nowrap">
                            {entry.result ? getRankBadge(entry.result) : <span className="text-slate-400 italic">Belum Tuntas</span>}
                          </td>
                          <td className="py-2.5 px-3 text-right whitespace-nowrap">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleDeleteEntry(entry.id)}
                              className="text-xs h-6 px-2 text-red-600 hover:bg-red-50"
                            >
                              <Trash2 className="h-3 w-3" />
                            </Button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <div className="px-5 py-3 border-t bg-slate-50 flex justify-end shrink-0">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setIsEntriesModalOpen(false)}
                className="text-xs"
              >
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 2.1: DAFTARKAN SISWA KE DELEGASI LOMBA */}
      {/* ========================================================================= */}
      {isNewEntryModalOpen && selectedCompForEntries && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Plus className="h-4 w-4 text-blue-600" />
                Daftarkan Delegasi Siswa
              </h3>
              <button
                onClick={() => setIsNewEntryModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveEntry} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Pilih Siswa Aktif *</label>
                <select
                  required
                  value={entryStudentId}
                  onChange={(e) => setEntryStudentId(e.target.value)}
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
                <label className="block font-medium text-slate-700 mb-1">Guru Pembimbing / Pendamping</label>
                <select
                  value={entryCoachId}
                  onChange={(e) => setEntryCoachId(e.target.value)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  <option value="">-- Tanpa Guru Pembimbing --</option>
                  {teachers.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.full_name} ({t.nip || 'Guru'})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Status Keikutsertaan</label>
                <select
                  value={entryStatus}
                  onChange={(e) => setEntryStatus(e.target.value as CompetitionEntryStatus)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  <option value="terdaftar">Terdaftar</option>
                  <option value="dikonfirmasi">Dikonfirmasi Lolos Berkas</option>
                  <option value="hadir">Hadir di Lokasi Lomba</option>
                  <option value="selesai">Selesai</option>
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Catatan Delegasi</label>
                <Textarea
                  placeholder="Kategori nomor lomba yang diikuti, persiapan siswa, dsb..."
                  value={entryNotes}
                  onChange={(e) => setEntryNotes(e.target.value)}
                  className="text-xs h-16 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsNewEntryModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingEntry}
                  className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingEntry && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Daftarkan Siswa
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 3: CATAT PRESTASI SISWA BARU */}
      {/* ========================================================================= */}
      {isNewAchievementModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Award className="h-4 w-4 text-emerald-600" />
                Catat Prestasi & Penghargaan Siswa
              </h3>
              <button
                onClick={() => setIsNewAchievementModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveAchievement} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Siswa Berprestasi *</label>
                <select
                  required
                  value={achStudentId}
                  onChange={(e) => setAchStudentId(e.target.value)}
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
                <label className="block font-medium text-slate-700 mb-1">Kompetisi Terkait (Opsional)</label>
                <select
                  value={achCompetitionId}
                  onChange={(e) => setAchCompetitionId(e.target.value)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  <option value="">-- Di luar kompetisi sekolah / Mandiri --</option>
                  {competitions.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} ({c.level})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Judul / Uraian Prestasi *</label>
                <Input
                  required
                  placeholder="Misal: Juara 1 Lomba Cipta Puisi Nasional 2026"
                  value={achTitle}
                  onChange={(e) => setAchTitle(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Peringkat / Tingkatan Juara *</label>
                  <select
                    value={achRankLevel}
                    onChange={(e) => setAchRankLevel(e.target.value)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    <option value="Juara 1">Juara 1</option>
                    <option value="Juara 2">Juara 2</option>
                    <option value="Juara 3">Juara 3</option>
                    <option value="Harapan 1">Harapan 1</option>
                    <option value="Harapan 2">Harapan 2</option>
                    <option value="Medali Emas">Medali Emas</option>
                    <option value="Medali Perak">Medali Perak</option>
                    <option value="Medali Perunggu">Medali Perunggu</option>
                    <option value="Finalis">Finalis</option>
                    <option value="Peserta Terbaik">Peserta Terbaik</option>
                  </select>
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Poin Apresiasi Diberikan *</label>
                  <Input
                    type="number"
                    min={0}
                    max={100}
                    value={achPointsAwarded}
                    onChange={(e) => setAchPointsAwarded(Number(e.target.value))}
                    className="text-xs h-9"
                  />
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Tanggal Raihan / Sertifikat *</label>
                <Input
                  type="date"
                  required
                  value={achAchievedAt}
                  onChange={(e) => setAchAchievedAt(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Catatan Tambahan</label>
                <Textarea
                  placeholder="Nomor sertifikat, penyelenggara, atau rincian pembinaan..."
                  value={achNotes}
                  onChange={(e) => setAchNotes(e.target.value)}
                  className="text-xs h-16 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsNewAchievementModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingAch}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingAch && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Catat & Sahkan Poin
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 4: DETAIL PRESTASI & TROFI */}
      {/* ========================================================================= */}
      {detailAchievement && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Trophy className="h-4 w-4 text-amber-600" />
                Detail Prestasi Siswa
              </h3>
              <button
                onClick={() => setDetailAchievement(null)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-4 text-xs">
              <div className="flex items-center justify-between pb-3 border-b">
                <div>
                  <div className="text-slate-500 text-[11px]">Siswa</div>
                  <div className="text-sm font-bold text-slate-900">
                    {detailAchievement.student?.full_name}
                  </div>
                  <div className="text-[11px] text-slate-500">
                    NIS: {detailAchievement.student?.nis || '-'} • {detailAchievement.student?.classes?.[0]?.name || 'Kelas'}
                  </div>
                </div>
                <div>{getRankBadge(detailAchievement.rank_level)}</div>
              </div>

              <div className="space-y-2">
                <div>
                  <div className="text-slate-500 text-[11px]">Judul Prestasi</div>
                  <div className="font-semibold text-slate-900 text-xs">{detailAchievement.title}</div>
                </div>

                {detailAchievement.competition && (
                  <div>
                    <div className="text-slate-500 text-[11px]">Kompetisi Terkait</div>
                    <div className="text-slate-800">
                      {detailAchievement.competition.name} ({detailAchievement.competition.level})
                    </div>
                  </div>
                )}

                <div className="grid grid-cols-2 gap-2 pt-1">
                  <div>
                    <div className="text-slate-500 text-[11px]">Tanggal Raihan</div>
                    <div className="font-mono text-slate-800">{detailAchievement.achieved_at}</div>
                  </div>
                  <div>
                    <div className="text-slate-500 text-[11px]">Poin Apresiasi</div>
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-800 border-emerald-300 font-mono">
                      +{detailAchievement.points_awarded} Poin Karakter
                    </Badge>
                  </div>
                </div>

                <div className="pt-2 border-t">
                  <div className="text-slate-500 text-[11px]">Status Verifikasi Dokumen</div>
                  <div className="flex items-center gap-2 mt-1">
                    {detailAchievement.verification_status === 'verified' ? (
                      <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">
                        Terverifikasi oleh {detailAchievement.verifiedBy?.name || 'Staf Kesiswaan'}
                      </Badge>
                    ) : detailAchievement.verification_status === 'rejected' ? (
                      <Badge className="bg-red-100 text-red-800 border-red-300">Ditolak</Badge>
                    ) : (
                      <Badge className="bg-amber-100 text-amber-800 border-amber-300">Menunggu Verifikasi</Badge>
                    )}
                  </div>
                  {detailAchievement.rejection_reason && (
                    <div className="mt-1.5 p-2 bg-red-50 text-red-700 rounded border border-red-200 text-[11px]">
                      Alasan Penolakan: {detailAchievement.rejection_reason}
                    </div>
                  )}
                </div>

                {detailAchievement.notes && (
                  <div className="pt-2 border-t">
                    <div className="text-slate-500 text-[11px]">Catatan</div>
                    <div className="text-slate-700 bg-slate-50 p-2.5 rounded border border-slate-100">
                      {detailAchievement.notes}
                    </div>
                  </div>
                )}
              </div>
            </div>

            <div className="px-5 py-3 border-t bg-slate-50 flex justify-end">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDetailAchievement(null)}
                className="text-xs"
              >
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 5: VERIFIKASI DOKUMEN & SERTIFIKAT PRESTASI */}
      {/* ========================================================================= */}
      {verifyingAchievement && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 text-indigo-600" />
                Verifikasi Bukti Dokumen Prestasi
              </h3>
              <button
                onClick={() => setVerifyingAchievement(null)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-5 space-y-3.5 text-xs">
              <div className="p-3 bg-slate-50 border rounded-lg space-y-1">
                <div className="font-semibold text-slate-900 text-sm">
                  {verifyingAchievement.student?.full_name}
                </div>
                <div className="text-slate-600 font-medium">{verifyingAchievement.title}</div>
                <div className="text-slate-500 text-[11px]">
                  Capaian: <strong>{verifyingAchievement.rank_level}</strong> • Tanggal: {verifyingAchievement.achieved_at}
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">
                  Catatan Penolakan (Hanya wajib diisi bila ditolak)
                </label>
                <Textarea
                  placeholder="Misal: Nomor sertifikat tidak valid atau berkas tidak terbaca..."
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                  className="text-xs h-20 resize-none"
                />
              </div>

              <div className="p-3 bg-amber-50 border border-amber-200 rounded-md text-amber-900 text-[11px]">
                <strong>Ketentuan Pengesahan:</strong> Penyetujuan prestasi akan otomatis menerbitkan poin apresiasi
                sebesar <strong>+{verifyingAchievement.points_awarded} poin</strong> pada buku nilai kedisiplinan siswa.
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setVerifyingAchievement(null)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="button"
                  size="sm"
                  onClick={() => handleConfirmVerify('rejected')}
                  className="bg-red-600 hover:bg-red-700 text-white text-xs gap-1.5"
                >
                  <X className="h-3.5 w-3.5" />
                  Tolak Pengajuan
                </Button>
                <Button
                  type="button"
                  size="sm"
                  onClick={() => handleConfirmVerify('verified')}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5"
                >
                  <CheckCircle2 className="h-3.5 w-3.5" />
                  Setujui & Terbitkan Poin
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Lomba / Delegasi / Prestasi */}
      <ConfirmDialog
        isOpen={deleteConfirm.isOpen}
        onClose={() => setDeleteConfirm((prev) => ({ ...prev, isOpen: false }))}
        onConfirm={async () => {
          setDeleteConfirm((prev) => ({ ...prev, isLoading: true }));
          try {
            await deleteConfirm.onConfirm();
            setDeleteConfirm((prev) => ({ ...prev, isOpen: false, isLoading: false }));
          } catch {
            setDeleteConfirm((prev) => ({ ...prev, isLoading: false }));
          }
        }}
        title={deleteConfirm.title}
        description={deleteConfirm.description}
        affectedItemName={deleteConfirm.affectedItemName}
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={deleteConfirm.isLoading}
      />
    </div>
  );
}
