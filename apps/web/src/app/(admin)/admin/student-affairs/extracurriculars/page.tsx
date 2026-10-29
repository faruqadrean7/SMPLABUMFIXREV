'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import {
  Compass,
  Users,
  BookOpen,
  FileText,
  Plus,
  Search,
  Filter,
  Trash2,
  Edit,
  CheckCircle2,
  AlertTriangle,
  Calendar,
  Clock,
  ArrowLeft,
  X,
  RefreshCw,
  Award,
  ChevronRight,
  UserPlus,
  ShieldCheck,
  Check,
  Upload,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { extracurricularApi } from '@/features/student-affairs/extracurricular-api';
import { teachersApi } from '@/features/master/teachers-api';
import { studentsApi } from '@/features/master/students-api';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import {
  ExtracurricularItem,
  ExtracurricularMemberItem,
  ExtracurricularJournalItem,
  ActivityDocumentItem,
  ExtracurricularRole,
  ExtracurricularStatus,
  ExtracurricularAttendanceItem,
} from '@/features/student-affairs/extracurricular-types';
import { TeacherItem, StudentItem } from '@/types/master';

const CATEGORIES = [
  'Olahraga',
  'Seni & Budaya',
  'Akademik & Sains',
  'Keagamaan',
  'Kepemimpinan & OSIS',
  'Teknologi & Robotik',
  'Bahasa & Sastra',
  'Lainnya',
];

const ROLES: { value: ExtracurricularRole; label: string }[] = [
  { value: 'ketua', label: 'Ketua' },
  { value: 'wakil_ketua', label: 'Wakil Ketua' },
  { value: 'sekretaris', label: 'Sekretaris' },
  { value: 'bendahara', label: 'Bendahara' },
  { value: 'anggota', label: 'Anggota' },
];

const REPORT_GRADES = [
  { value: 'A', label: 'A (Sangat Baik)' },
  { value: 'B', label: 'B (Baik)' },
  { value: 'C', label: 'C (Cukup)' },
  { value: 'D', label: 'D (Kurang)' },
];

export default function ExtracurricularAdminPage() {
  const [activeTab, setActiveTab] = useState<'direktori' | 'anggota' | 'jurnal' | 'dokumen'>('direktori');

  // Master Data State
  const [ekskuls, setEkskuls] = useState<ExtracurricularItem[]>([]);
  const [teachers, setTeachers] = useState<TeacherItem[]>([]);
  const [students, setStudents] = useState<StudentItem[]>([]);
  const [selectedEkskulId, setSelectedEkskulId] = useState<string>('');

  // Tab 2: Members State
  const [members, setMembers] = useState<ExtracurricularMemberItem[]>([]);
  const [memberSearch, setMemberSearch] = useState<string>('');
  const [memberRoleFilter, setMemberRoleFilter] = useState<string>('all');
  const [isMembersLoading, setIsMembersLoading] = useState<boolean>(false);

  // Tab 3: Journals State
  const [journals, setJournals] = useState<ExtracurricularJournalItem[]>([]);
  const [isJournalsLoading, setIsJournalsLoading] = useState<boolean>(false);

  // Tab 4: Documents State
  const [documents, setDocuments] = useState<ActivityDocumentItem[]>([]);
  const [docTypeFilter, setDocTypeFilter] = useState<string>('all');
  const [isDocsLoading, setIsDocsLoading] = useState<boolean>(false);

  // Directory Filter State
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [selectedStatusFilter, setSelectedStatusFilter] = useState<'all' | 'active' | 'inactive'>('all');
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<{
    isOpen: boolean;
    title: string;
    description: string;
    affectedItemName?: string;
    confirmLabel?: string;
    onConfirm: () => Promise<void>;
    isLoading?: boolean;
  }>({
    isOpen: false,
    title: '',
    description: '',
    onConfirm: async () => {},
    isLoading: false,
  });

  // Modals State
  const [isEkskulModalOpen, setIsEkskulModalOpen] = useState<boolean>(false);
  const [editingEkskul, setEditingEkskul] = useState<ExtracurricularItem | null>(null);

  const [isAddMemberModalOpen, setIsAddMemberModalOpen] = useState<boolean>(false);
  const [editingMember, setEditingMember] = useState<ExtracurricularMemberItem | null>(null);

  const [isJournalModalOpen, setIsJournalModalOpen] = useState<boolean>(false);
  const [isDocModalOpen, setIsDocModalOpen] = useState<boolean>(false);

  // Form State: Ekskul Master
  const [formName, setFormName] = useState<string>('');
  const [formCategory, setFormCategory] = useState<string>('Olahraga');
  const [formDescription, setFormDescription] = useState<string>('');
  const [formCoachId, setFormCoachId] = useState<string>('');
  const [formQuota, setFormQuota] = useState<number>(30);
  const [formScheduleDay, setFormScheduleDay] = useState<string>('Jumat');
  const [formScheduleTime, setFormScheduleTime] = useState<string>('15:30 - 17:00');
  const [formIsActive, setFormIsActive] = useState<boolean>(true);
  const [isSubmittingEkskul, setIsSubmittingEkskul] = useState<boolean>(false);

  // Form State: Anggota
  const [formStudentId, setFormStudentId] = useState<string>('');
  const [formMemberRole, setFormMemberRole] = useState<ExtracurricularRole>('anggota');
  const [formMemberGrade, setFormMemberGrade] = useState<string>('A');
  const [formMemberStatus, setFormMemberStatus] = useState<ExtracurricularStatus>('aktif');
  const [isSubmittingMember, setIsSubmittingMember] = useState<boolean>(false);

  // Form State: Jurnal Pertemuan
  const [journalDate, setJournalDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [journalStartTime, setJournalStartTime] = useState<string>('15:30');
  const [journalEndTime, setJournalEndTime] = useState<string>('17:00');
  const [journalActivity, setJournalActivity] = useState<string>('');
  const [journalNotes, setJournalNotes] = useState<string>('');
  const [journalAttendance, setJournalAttendance] = useState<Record<string, 'hadir' | 'izin' | 'sakit' | 'alpa'>>({});
  const [isSubmittingJournal, setIsSubmittingJournal] = useState<boolean>(false);

  // Form State: Dokumen Kegiatan
  const [docTitle, setDocTitle] = useState<string>('');
  const [docType, setDocType] = useState<'proposal' | 'laporan'>('proposal');
  const [docDesc, setDocDesc] = useState<string>('');
  const [docFileId, setDocFileId] = useState<string>('');
  const [isSubmittingDoc, setIsSubmittingDoc] = useState<boolean>(false);

  // 1. Fetch Main Data
  const fetchData = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [ekskulRes, teachersRes, studentsRes] = await Promise.all([
        extracurricularApi.getExtracurriculars({
          category: selectedCategory === 'all' ? undefined : selectedCategory,
          is_active: selectedStatusFilter === 'all' ? undefined : selectedStatusFilter === 'active',
          search: searchQuery || undefined,
          per_page: 100,
        }),
        teachersApi.list({ limit: 100 }),
        studentsApi.list({ limit: 100 }),
      ]);

      const items = ekskulRes.data || [];
      setEkskuls(items);
      setTeachers(teachersRes.data || []);
      setStudents(studentsRes.data || []);

      if (items.length > 0 && !selectedEkskulId) {
        setSelectedEkskulId(items[0].id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat data ekstrakurikuler';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [selectedCategory, selectedStatusFilter]);

  // 2. Fetch Members when selected ekskul changes or tab is anggota
  const fetchMembers = async (ekskulId: string) => {
    if (!ekskulId) return;
    setIsMembersLoading(true);
    try {
      const res = await extracurricularApi.getMembers({
        extracurricular_id: ekskulId,
        role: memberRoleFilter === 'all' ? undefined : memberRoleFilter,
        search: memberSearch || undefined,
        per_page: 100,
      });
      const memberList = res.data || [];
      setMembers(memberList);

      // Inisialisasi attendance map untuk form jurnal
      const initialAtt: Record<string, 'hadir' | 'izin' | 'sakit' | 'alpa'> = {};
      memberList.forEach((m) => {
        if (m.status === 'aktif') {
          initialAtt[m.student_id] = 'hadir';
        }
      });
      setJournalAttendance(initialAtt);
    } catch (err: unknown) {
      console.error('Error fetching members:', err);
    } finally {
      setIsMembersLoading(false);
    }
  };

  // 3. Fetch Journals
  const fetchJournals = async (ekskulId: string) => {
    if (!ekskulId) return;
    setIsJournalsLoading(true);
    try {
      const res = await extracurricularApi.getJournals({
        extracurricular_id: ekskulId,
        per_page: 50,
      });
      setJournals(res.data || []);
    } catch (err: unknown) {
      console.error('Error fetching journals:', err);
    } finally {
      setIsJournalsLoading(false);
    }
  };

  // 4. Fetch Documents
  const fetchDocuments = async (ekskulId?: string) => {
    setIsDocsLoading(true);
    try {
      const res = await extracurricularApi.getDocuments({
        extracurricular_id: ekskulId || undefined,
        doc_type: docTypeFilter === 'all' ? undefined : (docTypeFilter as 'proposal' | 'laporan'),
        per_page: 50,
      });
      setDocuments(res.data || []);
    } catch (err: unknown) {
      console.error('Error fetching documents:', err);
    } finally {
      setIsDocsLoading(false);
    }
  };

  useEffect(() => {
    if (selectedEkskulId) {
      if (activeTab === 'anggota') fetchMembers(selectedEkskulId);
      if (activeTab === 'jurnal') {
        fetchJournals(selectedEkskulId);
        fetchMembers(selectedEkskulId);
      }
      if (activeTab === 'dokumen') fetchDocuments(selectedEkskulId);
    }
  }, [selectedEkskulId, activeTab, memberRoleFilter]);

  // Modal open helpers
  const handleOpenCreateEkskul = () => {
    setEditingEkskul(null);
    setFormName('');
    setFormCategory('Olahraga');
    setFormDescription('');
    setFormCoachId(teachers.length > 0 ? teachers[0].id : '');
    setFormQuota(30);
    setFormScheduleDay('Jumat');
    setFormScheduleTime('15:30 - 17:00');
    setFormIsActive(true);
    setIsEkskulModalOpen(true);
  };

  const handleOpenEditEkskul = (item: ExtracurricularItem) => {
    setEditingEkskul(item);
    setFormName(item.name);
    setFormCategory(item.category || 'Olahraga');
    setFormDescription(item.description || '');
    setFormCoachId(item.coach_id || (teachers.length > 0 ? teachers[0].id : ''));
    setFormQuota(item.quota || 30);
    setFormScheduleDay(item.schedule_day || 'Jumat');
    setFormScheduleTime(item.schedule_time || '15:30 - 17:00');
    setFormIsActive(item.is_active);
    setIsEkskulModalOpen(true);
  };

  const handleSaveEkskul = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formName.trim()) {
      setError('Nama kegiatan ekstrakurikuler wajib diisi.');
      return;
    }

    setIsSubmittingEkskul(true);
    setError(null);
    try {
      const payload = {
        name: formName.trim(),
        category: formCategory,
        description: formDescription.trim() || undefined,
        coach_id: formCoachId || undefined,
        quota: formQuota,
        schedule_day: formScheduleDay,
        schedule_time: formScheduleTime,
        is_active: formIsActive,
      };

      if (editingEkskul) {
        await extracurricularApi.updateExtracurricular(editingEkskul.id, payload);
        setSuccessMessage(`Ekstrakurikuler "${formName}" berhasil diperbarui.`);
      } else {
        await extracurricularApi.createExtracurricular(payload);
        setSuccessMessage(`Ekstrakurikuler baru "${formName}" berhasil dibuat.`);
      }

      setIsEkskulModalOpen(false);
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan ekstrakurikuler';
      setError(msg);
    } finally {
      setIsSubmittingEkskul(false);
    }
  };

  const handleDeleteEkskul = (id: string, name: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Ekstrakurikuler',
      description: `Apakah Anda yakin ingin menghapus ekstrakurikuler "${name}"?`,
      affectedItemName: name,
      onConfirm: async () => {
        setError(null);
        try {
          await extracurricularApi.deleteExtracurricular(id);
          setSuccessMessage(`Ekstrakurikuler "${name}" berhasil dihapus.`);
          toast.success(`Ekstrakurikuler "${name}" berhasil dihapus.`);
          fetchData();
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal menghapus ekstrakurikuler';
          setError(msg);
          toast.error(msg);
        }
      },
    });
  };

  // Member Action Handlers
  const handleOpenAddMember = () => {
    setFormStudentId(students.length > 0 ? students[0].id : '');
    setFormMemberRole('anggota');
    setIsAddMemberModalOpen(true);
  };

  const handleSaveAddMember = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formStudentId || !selectedEkskulId) {
      setError('Pilih siswa yang akan didaftarkan.');
      return;
    }

    setIsSubmittingMember(true);
    setError(null);
    try {
      await extracurricularApi.registerMember({
        extracurricular_id: selectedEkskulId,
        student_id: formStudentId,
        role: formMemberRole,
      });

      setSuccessMessage('Siswa berhasil didaftarkan ke ekstrakurikuler.');
      setIsAddMemberModalOpen(false);
      fetchMembers(selectedEkskulId);
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mendaftarkan anggota';
      setError(msg);
    } finally {
      setIsSubmittingMember(false);
    }
  };

  const handleOpenEditMember = (member: ExtracurricularMemberItem) => {
    setEditingMember(member);
    setFormMemberRole(member.role);
    setFormMemberGrade(member.final_grade || 'A');
    setFormMemberStatus(member.status);
  };

  const handleSaveEditMember = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingMember) return;

    setIsSubmittingMember(true);
    setError(null);
    try {
      await extracurricularApi.updateMember(editingMember.id, {
        role: formMemberRole,
        final_grade: formMemberGrade,
        status: formMemberStatus,
      });

      setSuccessMessage('Peran dan nilai rapor anggota berhasil disimpan.');
      setEditingMember(null);
      fetchMembers(selectedEkskulId);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memperbarui data anggota';
      setError(msg);
    } finally {
      setIsSubmittingMember(false);
    }
  };

  const handleRemoveMember = (memberId: string, studentName: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Keluarkan Anggota Ekstrakurikuler',
      description: `Apakah Anda yakin ingin mengeluarkan siswa "${studentName}" dari ekstrakurikuler ini?`,
      affectedItemName: studentName,
      confirmLabel: 'Ya, Keluarkan',
      onConfirm: async () => {
        setError(null);
        try {
          await extracurricularApi.removeMember(memberId);
          setSuccessMessage(`Siswa "${studentName}" berhasil dikeluarkan.`);
          toast.success(`Siswa "${studentName}" berhasil dikeluarkan.`);
          fetchMembers(selectedEkskulId);
          fetchData();
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal mengeluarkan anggota';
          setError(msg);
          toast.error(msg);
        }
      },
    });
  };

  // Journal Action Handlers
  const handleSaveJournal = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEkskulId || !journalActivity.trim()) {
      setError('Materi / uraian aktivitas pertemuan wajib diisi.');
      return;
    }

    setIsSubmittingJournal(true);
    setError(null);
    try {
      const attendancePayload: ExtracurricularAttendanceItem[] = Object.entries(journalAttendance).map(
        ([studentId, status]) => ({
          student_id: studentId,
          status,
        })
      );

      await extracurricularApi.createJournal({
        extracurricular_id: selectedEkskulId,
        session_date: journalDate,
        start_time: journalStartTime,
        end_time: journalEndTime,
        activity: journalActivity.trim(),
        notes: journalNotes.trim() || undefined,
        attendance: attendancePayload,
      });

      setSuccessMessage('Jurnal kegiatan dan presensi berhasil disimpan.');
      setIsJournalModalOpen(false);
      setJournalActivity('');
      setJournalNotes('');
      fetchJournals(selectedEkskulId);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mencatat jurnal kegiatan';
      setError(msg);
    } finally {
      setIsSubmittingJournal(false);
    }
  };

  const handleDeleteJournal = (id: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Jurnal Pertemuan',
      description: 'Apakah Anda yakin ingin menghapus jurnal kegiatan ini? Data presensi terkait akan ikut terhapus.',
      onConfirm: async () => {
        try {
          await extracurricularApi.deleteJournal(id);
          setSuccessMessage('Jurnal kegiatan berhasil dihapus.');
          toast.success('Jurnal kegiatan berhasil dihapus.');
          fetchJournals(selectedEkskulId);
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal menghapus jurnal';
          setError(msg);
          toast.error(msg);
        }
      },
    });
  };

  // Document Action Handlers
  const handleSaveDoc = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!docTitle.trim() || !docFileId.trim()) {
      setError('Judul dokumen dan ID berkas file wajib diisi.');
      return;
    }

    setIsSubmittingDoc(true);
    setError(null);
    try {
      await extracurricularApi.createDocument({
        extracurricular_id: selectedEkskulId,
        file_id: docFileId.trim(),
        doc_type: docType,
        title: docTitle.trim(),
        description: docDesc.trim() || undefined,
      });

      setSuccessMessage('Dokumen kegiatan berhasil disimpan.');
      setIsDocModalOpen(false);
      setDocTitle('');
      setDocDesc('');
      setDocFileId('');
      fetchDocuments(selectedEkskulId);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal mengunggah dokumen';
      setError(msg);
    } finally {
      setIsSubmittingDoc(false);
    }
  };

  const handleDeleteDoc = (id: string, title: string) => {
    setDeleteConfirm({
      isOpen: true,
      title: 'Hapus Dokumen Kegiatan',
      description: `Apakah Anda yakin ingin menghapus dokumen "${title}"?`,
      affectedItemName: title,
      onConfirm: async () => {
        try {
          await extracurricularApi.deleteDocument(id);
          setSuccessMessage('Dokumen kegiatan berhasil dihapus.');
          toast.success('Dokumen kegiatan berhasil dihapus.');
          fetchDocuments(selectedEkskulId);
        } catch (err: unknown) {
          const msg = err instanceof Error ? err.message : 'Gagal menghapus dokumen';
          setError(msg);
          toast.error(msg);
        }
      },
    });
  };

  // Selected Ekskul Info
  const currentEkskul = ekskuls.find((e) => e.id === selectedEkskulId);

  // Metrics
  const totalEkskul = ekskuls.length;
  const activeEkskul = ekskuls.filter((e) => e.is_active).length;
  const totalCapacity = ekskuls.reduce((acc, curr) => acc + (curr.quota || 0), 0);
  const totalActiveMembers = ekskuls.reduce((acc, curr) => acc + (curr.active_members_count || 0), 0);

  return (
    <div className="space-y-6 p-6">
      {/* Header & Navigasi */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <Link
              href="/admin/student-affairs"
              className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              Kembali ke Kesiswaan
            </Link>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
            <Compass className="h-6 w-6 text-blue-600" />
            ADM-STD-02: Ekstrakurikuler & Keanggotaan Siswa
          </h1>
          <p className="text-xs text-slate-500 mt-1">
            Pengelolaan kegiatan ekstrakurikuler, pembina pelatih, pendaftaran siswa, jurnal kehadiran, dan penilaian E-Rapor Semester.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={fetchData}
            disabled={isLoading}
            className="border-slate-200 text-slate-700 gap-1.5"
          >
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>

          <Button
            size="sm"
            onClick={handleOpenCreateEkskul}
            className="bg-blue-600 hover:bg-blue-700 text-white gap-1.5"
          >
            <Plus className="h-4 w-4" />
            Tambah Ekskul Baru
          </Button>
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
            <CardTitle className="text-xs font-semibold text-slate-600">Total Ekstrakurikuler</CardTitle>
            <Compass className="h-4 w-4 text-blue-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-slate-900">{totalEkskul}</div>
            <p className="text-[11px] text-slate-500 mt-1">{activeEkskul} kegiatan aktif berjalan</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Partisipasi Anggota Aktif</CardTitle>
            <Users className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">{totalActiveMembers}</div>
            <p className="text-[11px] text-slate-500 mt-1">Siswa terdaftar dalam ekskul</p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Kapasitas Kuota Sekolah</CardTitle>
            <ShieldCheck className="h-4 w-4 text-indigo-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-indigo-600">{totalCapacity} Kursi</div>
            <p className="text-[11px] text-slate-500 mt-1">
              Tersisa {Math.max(0, totalCapacity - totalActiveMembers)} kursi terbuka
            </p>
          </CardContent>
        </Card>

        <Card className="border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle className="text-xs font-semibold text-slate-600">Integrasi E-Rapor</CardTitle>
            <Award className="h-4 w-4 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-amber-600">ADM-GRD-04</div>
            <p className="text-[11px] text-slate-500 mt-1">Otomatis sinkron ke rapor semester</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs Menu */}
      <div className="border-b border-slate-200">
        <div className="flex gap-4">
          <button
            onClick={() => setActiveTab('direktori')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'direktori'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <Compass className="h-4 w-4" />
            Direktori Kegiatan Ekskul ({ekskuls.length})
          </button>

          <button
            onClick={() => setActiveTab('anggota')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'anggota'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <Users className="h-4 w-4" />
            Anggota & Penilaian Rapor
          </button>

          <button
            onClick={() => setActiveTab('jurnal')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'jurnal'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <BookOpen className="h-4 w-4" />
            Jurnal Kegiatan & Presensi
          </button>

          <button
            onClick={() => setActiveTab('dokumen')}
            className={`pb-3 text-sm font-semibold transition-colors border-b-2 flex items-center gap-2 ${
              activeTab === 'dokumen'
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <FileText className="h-4 w-4" />
            Dokumen Proposal & LPJ ({documents.length})
          </button>
        </div>
      </div>

      {/* TAB 1: DIREKTORI EKSTRAKURIKULER */}
      {activeTab === 'direktori' && (
        <div className="space-y-4">
          {/* Filter Bar */}
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                <Input
                  placeholder="Cari nama atau kategori..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && fetchData()}
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
                value={selectedStatusFilter}
                onChange={(e) => setSelectedStatusFilter(e.target.value as 'all' | 'active' | 'inactive')}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Status</option>
                <option value="active">Hanya Aktif</option>
                <option value="inactive">Hanya Nonaktif</option>
              </select>

              <Button variant="outline" size="sm" onClick={fetchData} className="text-xs h-9 bg-white">
                Terapkan
              </Button>
            </div>
          </div>

          {/* Table / Cards */}
          {isLoading ? (
            <div className="text-center py-12 text-xs text-slate-500">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat direktori ekstrakurikuler...
            </div>
          ) : ekskuls.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
              <Compass className="h-10 w-10 text-slate-300 mx-auto mb-2" />
              <h3 className="text-sm font-semibold text-slate-800">Belum Ada Ekstrakurikuler</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Tambahkan master ekstrakurikuler untuk membuka pendaftaran siswa dan pengelolaan kegiatan.
              </p>
              <Button size="sm" onClick={handleOpenCreateEkskul} className="mt-3 bg-blue-600 hover:bg-blue-700 text-white gap-1 text-xs">
                <Plus className="h-3.5 w-3.5" /> Tambah Sekarang
              </Button>
            </div>
          ) : (
            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-sm">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-3 px-4">Nama Kegiatan</th>
                    <th className="py-3 px-4">Kategori</th>
                    <th className="py-3 px-4">Pembina / Pelatih</th>
                    <th className="py-3 px-4">Jadwal Pertemuan</th>
                    <th className="py-3 px-4 text-center">Kuota & Keterisian</th>
                    <th className="py-3 px-4 text-center">Status</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {ekskuls.map((item) => {
                    const activeCount = item.active_members_count || 0;
                    const quota = item.quota || 0;
                    const percent = quota > 0 ? Math.min(100, Math.round((activeCount / quota) * 100)) : 0;

                    return (
                      <tr key={item.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-semibold text-slate-900">{item.name}</div>
                          {item.description && (
                            <div className="text-[11px] text-slate-500 line-clamp-1 max-w-xs">{item.description}</div>
                          )}
                        </td>
                        <td className="py-3 px-4">
                          <Badge variant="outline" className="text-[10px] bg-slate-50 text-slate-700">
                            {item.category || 'Umum'}
                          </Badge>
                        </td>
                        <td className="py-3 px-4">
                          {item.coach ? (
                            <div>
                              <div className="font-medium text-slate-800">{item.coach.full_name}</div>
                              <div className="text-[10px] text-slate-400">NIP: {item.coach.nip || '-'}</div>
                            </div>
                          ) : (
                            <span className="text-slate-400 italic">Belum ditentukan</span>
                          )}
                        </td>
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-1.5 text-slate-700">
                            <Calendar className="h-3 w-3 text-slate-400" />
                            <span>{item.schedule_day || 'Jumat'}</span>
                          </div>
                          <div className="flex items-center gap-1.5 text-slate-500 text-[10px] mt-0.5">
                            <Clock className="h-3 w-3 text-slate-400" />
                            <span>{item.schedule_time || '15:30 - 17:00'}</span>
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <div className="inline-block w-28 text-left">
                            <div className="flex justify-between text-[10px] text-slate-500 mb-1">
                              <span>{activeCount} Siswa</span>
                              <span>{quota} Kuota</span>
                            </div>
                            <div className="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                              <div
                                className={`h-1.5 rounded-full ${
                                  percent >= 100 ? 'bg-red-500' : percent >= 75 ? 'bg-amber-500' : 'bg-emerald-500'
                                }`}
                                style={{ width: `${percent}%` }}
                              />
                            </div>
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center">
                          {item.is_active ? (
                            <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 text-[10px]">
                              Aktif
                            </Badge>
                          ) : (
                            <Badge variant="outline" className="text-slate-500 border-slate-300 text-[10px]">
                              Nonaktif
                            </Badge>
                          )}
                        </td>
                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => {
                                setSelectedEkskulId(item.id);
                                setActiveTab('anggota');
                              }}
                              className="text-blue-600 hover:text-blue-700 h-7 px-2 text-xs gap-1"
                              title="Kelola Anggota & Nilai Rapor"
                            >
                              <Users className="h-3.5 w-3.5" />
                              Anggota
                            </Button>

                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleOpenEditEkskul(item)}
                              className="text-slate-600 hover:text-slate-900 h-7 w-7 p-0"
                              title="Edit Data Ekskul"
                            >
                              <Edit className="h-3.5 w-3.5" />
                            </Button>

                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleDeleteEkskul(item.id, item.name)}
                              className="text-red-600 hover:text-red-700 h-7 w-7 p-0"
                              title="Hapus Ekskul"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
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

      {/* TAB 2: ANGGOTA & PENILAIAN RAPOR */}
      {activeTab === 'anggota' && (
        <div className="space-y-4">
          {/* Selector & Actions */}
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
              <label className="text-xs font-semibold text-slate-700">Pilih Ekstrakurikuler:</label>
              <select
                value={selectedEkskulId}
                onChange={(e) => {
                  setSelectedEkskulId(e.target.value);
                  fetchMembers(e.target.value);
                }}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white font-medium text-slate-900 min-w-[220px]"
              >
                {ekskuls.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.name} ({e.active_members_count || 0}/{e.quota} Siswa)
                  </option>
                ))}
              </select>

              <div className="relative w-48">
                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                <Input
                  placeholder="Cari siswa atau NIS..."
                  value={memberSearch}
                  onChange={(e) => setMemberSearch(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && fetchMembers(selectedEkskulId)}
                  className="pl-8 text-xs h-9 bg-white"
                />
              </div>

              <select
                value={memberRoleFilter}
                onChange={(e) => setMemberRoleFilter(e.target.value)}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Jabatan</option>
                {ROLES.map((r) => (
                  <option key={r.value} value={r.value}>
                    {r.label}
                  </option>
                ))}
              </select>
            </div>

            <Button
              size="sm"
              onClick={handleOpenAddMember}
              disabled={!selectedEkskulId || (currentEkskul?.available_quota || 0) <= 0}
              className="bg-blue-600 hover:bg-blue-700 text-white gap-1.5 text-xs"
            >
              <UserPlus className="h-3.5 w-3.5" />
              Tambah Anggota Siswa
            </Button>
          </div>

          {/* Members Table */}
          {isMembersLoading ? (
            <div className="text-center py-12 text-xs text-slate-500">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat data anggota ekstrakurikuler...
            </div>
          ) : members.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
              <Users className="h-10 w-10 text-slate-300 mx-auto mb-2" />
              <h3 className="text-sm font-semibold text-slate-800">Belum Ada Anggota Terdaftar</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Daftarkan siswa ke dalam ekstrakurikuler ini untuk mencatat presensi dan memberikan predikat nilai rapor semester.
              </p>
              <Button size="sm" onClick={handleOpenAddMember} className="mt-3 bg-blue-600 hover:bg-blue-700 text-white gap-1 text-xs">
                <UserPlus className="h-3.5 w-3.5" /> Daftarkan Siswa
              </Button>
            </div>
          ) : (
            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-sm">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-3 px-4">Nama Siswa</th>
                    <th className="py-3 px-4">NIS / Rombel</th>
                    <th className="py-3 px-4">Jabatan Organisasi</th>
                    <th className="py-3 px-4">Tanggal Bergabung</th>
                    <th className="py-3 px-4 text-center">Predikat Nilai Rapor</th>
                    <th className="py-3 px-4 text-center">Status</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {members.map((m) => {
                    const student = m.student;
                    const className = student?.classes && student.classes.length > 0 ? student.classes[0].name : '-';

                    return (
                      <tr key={m.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-semibold text-slate-900">{student?.full_name || 'Siswa'}</div>
                          <div className="text-[10px] text-slate-400">NISN: {student?.nisn || '-'}</div>
                        </td>
                        <td className="py-3 px-4">
                          <div className="font-mono text-slate-700">{student?.nis || '-'}</div>
                          <Badge variant="outline" className="text-[10px] mt-0.5 border-slate-200 text-slate-600">
                            Kelas: {className}
                          </Badge>
                        </td>
                        <td className="py-3 px-4">
                          <Badge
                            className={`text-[10px] capitalize ${
                              m.role === 'ketua'
                                ? 'bg-amber-100 text-amber-800'
                                : m.role === 'wakil_ketua'
                                ? 'bg-blue-100 text-blue-800'
                                : 'bg-slate-100 text-slate-700'
                            }`}
                          >
                            {m.role.replace('_', ' ')}
                          </Badge>
                        </td>
                        <td className="py-3 px-4 text-slate-600">
                          {m.joined_at ? new Date(m.joined_at).toLocaleDateString('id-ID') : '-'}
                        </td>
                        <td className="py-3 px-4 text-center">
                          {m.final_grade ? (
                            <Badge className="bg-emerald-100 text-emerald-800 font-bold px-2.5 py-0.5 text-xs">
                              {m.final_grade}
                            </Badge>
                          ) : (
                            <span className="text-slate-400 italic text-[11px]">Belum Dinilai</span>
                          )}
                        </td>
                        <td className="py-3 px-4 text-center">
                          <Badge
                            className={`text-[10px] ${
                              m.status === 'aktif'
                                ? 'bg-emerald-100 text-emerald-800'
                                : m.status === 'keluar'
                                ? 'bg-red-100 text-red-800'
                                : 'bg-slate-100 text-slate-700'
                            }`}
                          >
                            {m.status}
                          </Badge>
                        </td>
                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleOpenEditMember(m)}
                              className="text-xs h-7 px-2 border-slate-200 gap-1 text-slate-700"
                              title="Beri Nilai Rapor & Ubah Jabatan"
                            >
                              <Award className="h-3 w-3 text-amber-500" />
                              Nilai / Edit
                            </Button>

                            {m.status === 'aktif' && (
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveMember(m.id, student?.full_name || 'Siswa')}
                                className="text-red-600 hover:text-red-700 h-7 w-7 p-0"
                                title="Keluarkan dari Ekskul"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
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
          )}
        </div>
      )}

      {/* TAB 3: JURNAL KEGIATAN & PRESENSI */}
      {activeTab === 'jurnal' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex items-center gap-3">
              <label className="text-xs font-semibold text-slate-700">Pilih Ekstrakurikuler:</label>
              <select
                value={selectedEkskulId}
                onChange={(e) => {
                  setSelectedEkskulId(e.target.value);
                  fetchJournals(e.target.value);
                  fetchMembers(e.target.value);
                }}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white font-medium text-slate-900 min-w-[220px]"
              >
                {ekskuls.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.name}
                  </option>
                ))}
              </select>
            </div>

            <Button
              size="sm"
              onClick={() => setIsJournalModalOpen(true)}
              disabled={!selectedEkskulId}
              className="bg-blue-600 hover:bg-blue-700 text-white gap-1.5 text-xs"
            >
              <Plus className="h-3.5 w-3.5" />
              Catat Pertemuan Baru
            </Button>
          </div>

          {isJournalsLoading ? (
            <div className="text-center py-12 text-xs text-slate-500">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat riwayat jurnal kegiatan...
            </div>
          ) : journals.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
              <BookOpen className="h-10 w-10 text-slate-300 mx-auto mb-2" />
              <h3 className="text-sm font-semibold text-slate-800">Belum Ada Catatan Jurnal Kegiatan</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Pembina dapat mencatat materi latihan dan absensi kehadiran siswa per pertemuan di sini.
              </p>
              <Button size="sm" onClick={() => setIsJournalModalOpen(true)} className="mt-3 bg-blue-600 hover:bg-blue-700 text-white gap-1 text-xs">
                <Plus className="h-3.5 w-3.5" /> Catat Pertemuan
              </Button>
            </div>
          ) : (
            <div className="space-y-3">
              {journals.map((j) => {
                const attList = j.attendance || [];
                const hadirCount = attList.filter((a) => a.status === 'hadir').length;
                const totalAtt = attList.length;

                return (
                  <Card key={j.id} className="border-slate-200 shadow-sm hover:border-slate-300 transition-colors">
                    <CardHeader className="pb-2 flex flex-row items-start justify-between">
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="text-xs font-bold text-slate-800">
                            {new Date(j.session_date).toLocaleDateString('id-ID', {
                              weekday: 'long',
                              day: 'numeric',
                              month: 'long',
                              year: 'numeric',
                            })}
                          </span>
                          {j.start_time && (
                            <Badge variant="outline" className="text-[10px] border-slate-200">
                              <Clock className="h-3 w-3 mr-1 text-slate-400" />
                              {j.start_time} - {j.end_time || 'selesai'}
                            </Badge>
                          )}
                        </div>
                      </div>

                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDeleteJournal(j.id)}
                        className="text-red-600 hover:text-red-700 h-7 w-7 p-0"
                        title="Hapus Jurnal"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </CardHeader>

                    <CardContent className="pt-0 text-xs">
                      <div className="font-medium text-slate-900 mb-1">{j.activity}</div>
                      {j.notes && <div className="text-slate-500 text-[11px] mb-2 italic">Catatan: {j.notes}</div>}

                      {totalAtt > 0 && (
                        <div className="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-600">
                          <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                          <span>
                            Kehadiran: <strong>{hadirCount}</strong> dari {totalAtt} siswa hadir
                          </span>
                        </div>
                      )}
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* TAB 4: DOKUMEN KEGIATAN PROPOSAL & LPJ */}
      {activeTab === 'dokumen' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div className="flex items-center gap-3">
              <label className="text-xs font-semibold text-slate-700">Filter Ekstrakurikuler:</label>
              <select
                value={selectedEkskulId}
                onChange={(e) => {
                  setSelectedEkskulId(e.target.value);
                  fetchDocuments(e.target.value);
                }}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white font-medium text-slate-900 min-w-[200px]"
              >
                <option value="">Semua Dokumen</option>
                {ekskuls.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.name}
                  </option>
                ))}
              </select>

              <select
                value={docTypeFilter}
                onChange={(e) => {
                  setDocTypeFilter(e.target.value);
                  fetchDocuments(selectedEkskulId);
                }}
                className="text-xs h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700"
              >
                <option value="all">Semua Tipe Dokumen</option>
                <option value="proposal">Proposal Kegiatan</option>
                <option value="laporan">Laporan Pertanggungjawaban (LPJ)</option>
              </select>
            </div>

            <Button
              size="sm"
              onClick={() => setIsDocModalOpen(true)}
              disabled={!selectedEkskulId}
              className="bg-blue-600 hover:bg-blue-700 text-white gap-1.5 text-xs"
            >
              <Upload className="h-3.5 w-3.5" />
              Unggah Dokumen Baru
            </Button>
          </div>

          {isDocsLoading ? (
            <div className="text-center py-12 text-xs text-slate-500">
              <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-slate-400" />
              Memuat arsip dokumen kegiatan...
            </div>
          ) : documents.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-300">
              <FileText className="h-10 w-10 text-slate-300 mx-auto mb-2" />
              <h3 className="text-sm font-semibold text-slate-800">Belum Ada Dokumen Tersimpan</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Arsipkan proposal event, permohonan dana, serta LPJ kegiatan OSIS dan ekskul di sini.
              </p>
              <Button size="sm" onClick={() => setIsDocModalOpen(true)} className="mt-3 bg-blue-600 hover:bg-blue-700 text-white gap-1 text-xs">
                <Upload className="h-3.5 w-3.5" /> Unggah Dokumen
              </Button>
            </div>
          ) : (
            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-sm">
              <table className="w-full text-xs text-left">
                <thead className="bg-slate-50 text-slate-600 font-semibold border-b">
                  <tr>
                    <th className="py-3 px-4">Judul Dokumen</th>
                    <th className="py-3 px-4">Kegiatan Ekskul</th>
                    <th className="py-3 px-4">Tipe Dokumen</th>
                    <th className="py-3 px-4">Pengunggah</th>
                    <th className="py-3 px-4">Tanggal Unggah</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {documents.map((d) => (
                    <tr key={d.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-3 px-4">
                        <div className="font-semibold text-slate-900">{d.title}</div>
                        {d.description && <div className="text-[11px] text-slate-500">{d.description}</div>}
                      </td>
                      <td className="py-3 px-4 text-slate-700 font-medium">
                        {d.extracurricular?.name || '-'}
                      </td>
                      <td className="py-3 px-4">
                        <Badge
                          className={`text-[10px] uppercase font-bold ${
                            d.doc_type === 'proposal' ? 'bg-indigo-100 text-indigo-800' : 'bg-emerald-100 text-emerald-800'
                          }`}
                        >
                          {d.doc_type}
                        </Badge>
                      </td>
                      <td className="py-3 px-4 text-slate-600">
                        {d.uploader?.name || 'Staf / Pembina'}
                      </td>
                      <td className="py-3 px-4 text-slate-500">
                        {d.created_at ? new Date(d.created_at).toLocaleDateString('id-ID') : '-'}
                      </td>
                      <td className="py-3 px-4 text-right">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDeleteDoc(d.id, d.title)}
                          className="text-red-600 hover:text-red-700 h-7 w-7 p-0"
                          title="Hapus Dokumen"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
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
      {/* MODAL 1: TAMBAH / EDIT EKSTRAKURIKULER */}
      {/* ========================================================================= */}
      {isEkskulModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Compass className="h-4 w-4 text-blue-600" />
                {editingEkskul ? 'Edit Ekstrakurikuler' : 'Tambah Ekstrakurikuler Baru'}
              </h3>
              <button
                onClick={() => setIsEkskulModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveEkskul} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Nama Kegiatan Ekstrakurikuler *</label>
                <Input
                  required
                  placeholder="Misal: Futsal Prestasi, Robotik, Tari Saman"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Kategori</label>
                  <select
                    value={formCategory}
                    onChange={(e) => setFormCategory(e.target.value)}
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
                  <label className="block font-medium text-slate-700 mb-1">Pembina / Pelatih</label>
                  <select
                    value={formCoachId}
                    onChange={(e) => setFormCoachId(e.target.value)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    <option value="">Pilih Guru Pembina</option>
                    {teachers.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.full_name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Hari Latihan</label>
                  <select
                    value={formScheduleDay}
                    onChange={(e) => setFormScheduleDay(e.target.value)}
                    className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                  >
                    {['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map((d) => (
                      <option key={d} value={d}>
                        {d}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Jam Latihan</label>
                  <Input
                    placeholder="15:30 - 17:00"
                    value={formScheduleTime}
                    onChange={(e) => setFormScheduleTime(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Kapasitas Kuota</label>
                  <Input
                    type="number"
                    min="1"
                    max="200"
                    value={formQuota}
                    onChange={(e) => setFormQuota(parseInt(e.target.value) || 1)}
                    className="text-xs h-9"
                  />
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Deskripsi Kegiatan</label>
                <Textarea
                  placeholder="Penjelasan ringkas mengenai target, prestasi, dan materi ekskul..."
                  value={formDescription}
                  onChange={(e) => setFormDescription(e.target.value)}
                  className="text-xs h-20 resize-none"
                />
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="isActiveCheck"
                  checked={formIsActive}
                  onChange={(e) => setFormIsActive(e.target.checked)}
                  className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4"
                />
                <label htmlFor="isActiveCheck" className="text-xs font-medium text-slate-700 cursor-pointer">
                  Buka pendaftaran & status kegiatan aktif
                </label>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsEkskulModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingEkskul}
                  className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingEkskul && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  {editingEkskul ? 'Simpan Perubahan' : 'Buat Ekstrakurikuler'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 2: TAMBAH ANGGOTA SISWA */}
      {/* ========================================================================= */}
      {isAddMemberModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <UserPlus className="h-4 w-4 text-blue-600" />
                Daftarkan Anggota Siswa
              </h3>
              <button
                onClick={() => setIsAddMemberModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveAddMember} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Pilih Siswa *</label>
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

              <div>
                <label className="block font-medium text-slate-700 mb-1">Jabatan dalam Organisasi Ekskul</label>
                <select
                  value={formMemberRole}
                  onChange={(e) => setFormMemberRole(e.target.value as ExtracurricularRole)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  {ROLES.map((r) => (
                    <option key={r.value} value={r.value}>
                      {r.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsAddMemberModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingMember}
                  className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingMember && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Daftarkan Siswa
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 3: UBAH PERAN & NILAI RAPOR ANGGOTA */}
      {/* ========================================================================= */}
      {editingMember && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Award className="h-4 w-4 text-amber-500" />
                Penilaian Rapor & Jabatan Siswa
              </h3>
              <button onClick={() => setEditingMember(null)} className="text-slate-400 hover:text-slate-600 rounded p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveEditMember} className="p-5 space-y-3.5 text-xs">
              <div className="bg-slate-50 p-3 rounded border border-slate-200">
                <div className="text-[11px] text-slate-500">Nama Siswa:</div>
                <div className="font-bold text-slate-800 text-sm">{editingMember.student?.full_name}</div>
                <div className="text-[10px] text-slate-500">NIS: {editingMember.student?.nis || '-'}</div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Predikat Nilai E-Rapor Semester *</label>
                <select
                  value={formMemberGrade}
                  onChange={(e) => setFormMemberGrade(e.target.value)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white font-bold text-slate-800"
                >
                  {REPORT_GRADES.map((g) => (
                    <option key={g.value} value={g.value}>
                      {g.label}
                    </option>
                  ))}
                </select>
                <p className="text-[10px] text-slate-500 mt-1">
                  Predikat ini otomatis ditarik ke dalam Buku Rapor Semester (ADM-GRD-04).
                </p>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Jabatan dalam Organisasi</label>
                <select
                  value={formMemberRole}
                  onChange={(e) => setFormMemberRole(e.target.value as ExtracurricularRole)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  {ROLES.map((r) => (
                    <option key={r.value} value={r.value}>
                      {r.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Status Keanggotaan</label>
                <select
                  value={formMemberStatus}
                  onChange={(e) => setFormMemberStatus(e.target.value as ExtracurricularStatus)}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  <option value="aktif">Aktif</option>
                  <option value="nonaktif">Nonaktif (Cuti / Izin Panjang)</option>
                  <option value="keluar">Keluar dari Kegiatan</option>
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setEditingMember(null)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingMember}
                  className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingMember && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Simpan Penilaian
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 4: CATAT JURNAL KEGIATAN & PRESENSI */}
      {/* ========================================================================= */}
      {isJournalModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-xl border overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] flex flex-col">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50 shrink-0">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <BookOpen className="h-4 w-4 text-blue-600" />
                Catat Jurnal Pertemuan & Presensi
              </h3>
              <button
                onClick={() => setIsJournalModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 rounded p-1"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveJournal} className="p-5 space-y-3.5 text-xs overflow-y-auto">
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block font-medium text-slate-700 mb-1">Tanggal Pertemuan *</label>
                  <Input
                    type="date"
                    required
                    value={journalDate}
                    onChange={(e) => setJournalDate(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Jam Mulai</label>
                  <Input
                    type="time"
                    value={journalStartTime}
                    onChange={(e) => setJournalStartTime(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>

                <div>
                  <label className="block font-medium text-slate-700 mb-1">Jam Selesai</label>
                  <Input
                    type="time"
                    value={journalEndTime}
                    onChange={(e) => setJournalEndTime(e.target.value)}
                    className="text-xs h-9"
                  />
                </div>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Materi / Uraian Aktivitas Latihan *</label>
                <Textarea
                  required
                  placeholder="Deskripsikan agenda latihan atau materi yang disampaikan pembina hari ini..."
                  value={journalActivity}
                  onChange={(e) => setJournalActivity(e.target.value)}
                  className="text-xs h-20 resize-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                <Input
                  placeholder="Misal: Evaluasi kedisiplinan, target turnamen minggu depan..."
                  value={journalNotes}
                  onChange={(e) => setJournalNotes(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              {/* Presensi Anggota */}
              <div className="pt-2">
                <label className="block font-semibold text-slate-800 mb-2">
                  Presensi Kehadiran Anggota ({members.filter((m) => m.status === 'aktif').length} Siswa Aktif)
                </label>

                {members.filter((m) => m.status === 'aktif').length === 0 ? (
                  <p className="text-slate-400 italic text-[11px]">Tidak ada anggota aktif untuk diabsen.</p>
                ) : (
                  <div className="border rounded-md divide-y max-h-48 overflow-y-auto">
                    {members
                      .filter((m) => m.status === 'aktif')
                      .map((m) => {
                        const curStatus = journalAttendance[m.student_id] || 'hadir';

                        return (
                          <div key={m.id} className="p-2 flex items-center justify-between hover:bg-slate-50">
                            <div>
                              <div className="font-semibold text-slate-800">{m.student?.full_name}</div>
                              <div className="text-[10px] text-slate-400">NIS: {m.student?.nis || '-'}</div>
                            </div>

                            <div className="flex items-center gap-1">
                              {(['hadir', 'izin', 'sakit', 'alpa'] as const).map((st) => (
                                <button
                                  type="button"
                                  key={st}
                                  onClick={() =>
                                    setJournalAttendance((prev) => ({
                                      ...prev,
                                      [m.student_id]: st,
                                    }))
                                  }
                                  className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase border transition-colors ${
                                    curStatus === st
                                      ? st === 'hadir'
                                        ? 'bg-emerald-600 text-white border-emerald-600'
                                        : st === 'izin'
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : st === 'sakit'
                                        ? 'bg-amber-600 text-white border-amber-600'
                                        : 'bg-red-600 text-white border-red-600'
                                      : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'
                                  }`}
                                >
                                  {st}
                                </button>
                              ))}
                            </div>
                          </div>
                        );
                      })}
                  </div>
                )}
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsJournalModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingJournal}
                  className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingJournal && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Simpan Jurnal & Presensi
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 5: UNGGAH DOKUMEN KEGIATAN */}
      {/* ========================================================================= */}
      {isDocModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md border overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-5 py-4 border-b flex justify-between items-center bg-slate-50">
              <h3 className="font-semibold text-slate-900 text-sm flex items-center gap-2">
                <Upload className="h-4 w-4 text-blue-600" />
                Unggah Dokumen Proposal / LPJ
              </h3>
              <button onClick={() => setIsDocModalOpen(false)} className="text-slate-400 hover:text-slate-600 rounded p-1">
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleSaveDoc} className="p-5 space-y-3.5 text-xs">
              <div>
                <label className="block font-medium text-slate-700 mb-1">Judul Dokumen *</label>
                <Input
                  required
                  placeholder="Misal: Proposal Turnamen Futsal Cup 2026"
                  value={docTitle}
                  onChange={(e) => setDocTitle(e.target.value)}
                  className="text-xs h-9"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Tipe Dokumen *</label>
                <select
                  value={docType}
                  onChange={(e) => setDocType(e.target.value as 'proposal' | 'laporan')}
                  className="w-full text-xs h-9 px-3 rounded-md border border-slate-200 bg-white"
                >
                  <option value="proposal">Proposal Kegiatan / Pengajuan Anggaran</option>
                  <option value="laporan">Laporan Pertanggungjawaban (LPJ)</option>
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">ID Berkas Berkas File (UUID) *</label>
                <Input
                  required
                  placeholder="UUID Berkas hasil upload storage..."
                  value={docFileId}
                  onChange={(e) => setDocFileId(e.target.value)}
                  className="text-xs h-9 font-mono"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 mb-1">Keterangan / Catatan Dokumen</label>
                <Textarea
                  placeholder="Catatan tambahan mengenai berkas pengajuan atau laporan..."
                  value={docDesc}
                  onChange={(e) => setDocDesc(e.target.value)}
                  className="text-xs h-16 resize-none"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsDocModalOpen(false)}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingDoc}
                  className="bg-blue-600 hover:bg-blue-700 text-white text-xs gap-1.5"
                >
                  {isSubmittingDoc && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                  Simpan Dokumen
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Ekstrakurikuler / Anggota / Jurnal / Dokumen */}
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
        confirmLabel={deleteConfirm.confirmLabel || 'Ya, Lanjutkan'}
        cancelLabel="Batal"
        variant="danger"
        isLoading={deleteConfirm.isLoading}
      />
    </div>
  );
}
