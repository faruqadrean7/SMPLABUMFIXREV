'use client';

import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import {
  Sparkles,
  BookOpen,
  ClipboardList,
  Target,
  Plus,
  Search,
  ExternalLink,
  Download,
  Trash2,
  Edit2,
  CheckCircle2,
  Clock,
  XCircle,
  FileText,
  Music,
  Users,
  Award,
  Calendar,
  X,
  RefreshCw,
  AlertCircle,
  Send,
  Eye,
} from 'lucide-react';
import {
  useTeacherProgramMaterials,
  useTeacherProgramTasks,
  useTeacherProgramTargets,
  useTeacherTaskSubmissions,
  useCreateProgramMaterial,
  useUpdateProgramMaterial,
  useDeleteProgramMaterial,
  useCreateProgramTask,
  useUpdateProgramTask,
  useDeleteProgramTask,
  useCreateProgramTarget,
  useUpdateProgramTarget,
  useDeleteProgramTarget,
  useGradeProgramSubmission,
  TeacherProgramMaterialItem,
  TeacherProgramTaskItem,
  TeacherProgramTargetItem,
  TeacherTaskSubmissionItem,
} from '@/lib/use-teacher-program-data';
import { useTeacherClasses } from '@/lib/use-teacher-data';
import { PermissionGate } from '@/components/shared/permission-gate';
import { cn } from '@/lib/utils';

// =========================================================================
// ZOD SCHEMAS
// =========================================================================

const materialFormSchema = z.object({
  program_type: z.enum(['tahfidz', 'icp', 'inklusi', 'reguler']),
  title: z.string().trim().min(3, 'Judul materi minimal 3 karakter').max(255),
  material_type: z.string().min(1, 'Tipe materi wajib dipilih'),
  description: z.string().trim().optional(),
  external_url: z
    .string()
    .trim()
    .refine((val) => !val || /^https?:\/\/.+/.test(val), 'Tautan harus berupa URL valid berawalan http:// atau https://')
    .optional()
    .or(z.literal('')),
  class_id: z.string().optional().or(z.literal('')),
});

const taskFormSchema = z.object({
  program_type: z.enum(['tahfidz', 'icp', 'inklusi', 'reguler']),
  title: z.string().trim().min(3, 'Judul tugas minimal 3 karakter').max(255),
  instructions: z.string().trim().optional(),
  due_at: z.string().optional().or(z.literal('')),
  class_id: z.string().optional().or(z.literal('')),
});

const targetFormSchema = z.object({
  student_id: z.string().trim().min(1, 'ID Siswa wajib diisi'),
  program_type: z.enum(['tahfidz', 'icp', 'inklusi', 'reguler']),
  title: z.string().trim().min(3, 'Judul target minimal 3 karakter').max(255),
  domain: z.string().optional().or(z.literal('')),
  target_value: z.string().optional().or(z.literal('')),
  current_value: z.string().optional().or(z.literal('')),
  period: z.string().optional().or(z.literal('')),
  status: z.enum(['aktif', 'tercapai', 'batal']),
});

const gradingFormSchema = z.object({
  score: z.coerce.number().min(0, 'Nilai minimal 0').max(100, 'Nilai maksimal 100'),
  feedback: z.string().trim().optional(),
  status: z.enum(['dinilai', 'perlu_revisi']),
});

type MaterialFormValues = z.infer<typeof materialFormSchema>;
type TaskFormValues = z.infer<typeof taskFormSchema>;
type TargetFormValues = z.infer<typeof targetFormSchema>;
type GradingFormValues = z.infer<typeof gradingFormSchema>;

export default function TeacherProgramKhususPage() {
  const [activeTab, setActiveTab] = useState<'materi' | 'tugas' | 'target'>('materi');
  const [selectedProgram, setSelectedProgram] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Dropdown classes for assigned teacher
  const { data: teacherClasses = [] } = useTeacherClasses();

  // Modals state
  const [isMaterialModalOpen, setIsMaterialModalOpen] = useState(false);
  const [editingMaterial, setEditingMaterial] = useState<TeacherProgramMaterialItem | null>(null);

  const [isTaskModalOpen, setIsTaskModalOpen] = useState(false);
  const [editingTask, setEditingTask] = useState<TeacherProgramTaskItem | null>(null);

  const [isTargetModalOpen, setIsTargetModalOpen] = useState(false);
  const [editingTarget, setEditingTarget] = useState<TeacherProgramTargetItem | null>(null);

  const [inspectingTask, setInspectingTask] = useState<TeacherProgramTaskItem | null>(null);
  const [gradingSubmission, setGradingSubmission] = useState<TeacherTaskSubmissionItem | null>(null);

  // Queries
  const programFilter = selectedProgram !== 'all' ? selectedProgram : undefined;
  const materialsQuery = useTeacherProgramMaterials({
    program_type: programFilter,
    q: searchQuery || undefined,
  });
  const tasksQuery = useTeacherProgramTasks({
    program_type: programFilter,
    q: searchQuery || undefined,
  });
  const targetsQuery = useTeacherProgramTargets({
    program_type: programFilter,
    q: searchQuery || undefined,
  });
  const submissionsQuery = useTeacherTaskSubmissions(inspectingTask?.id);

  // Mutations
  const createMaterialMutation = useCreateProgramMaterial();
  const updateMaterialMutation = useUpdateProgramMaterial();
  const deleteMaterialMutation = useDeleteProgramMaterial();

  const createTaskMutation = useCreateProgramTask();
  const updateTaskMutation = useUpdateProgramTask();
  const deleteTaskMutation = useDeleteProgramTask();

  const createTargetMutation = useCreateProgramTarget();
  const updateTargetMutation = useUpdateProgramTarget();
  const deleteTargetMutation = useDeleteProgramTarget();

  const gradeSubmissionMutation = useGradeProgramSubmission();

  // Form Hooks
  const materialForm = useForm<MaterialFormValues>({
    resolver: zodResolver(materialFormSchema),
    defaultValues: {
      program_type: 'tahfidz',
      title: '',
      material_type: 'panduan',
      description: '',
      external_url: '',
      class_id: '',
    },
  });

  const taskForm = useForm<TaskFormValues>({
    resolver: zodResolver(taskFormSchema),
    defaultValues: {
      program_type: 'tahfidz',
      title: '',
      instructions: '',
      due_at: '',
      class_id: '',
    },
  });

  const targetForm = useForm<TargetFormValues>({
    resolver: zodResolver(targetFormSchema),
    defaultValues: {
      student_id: '',
      program_type: 'tahfidz',
      title: '',
      domain: 'hafalan',
      target_value: '',
      current_value: '',
      period: 'Semester Ganjil 2026/2027',
      status: 'aktif',
    },
  });

  const gradingForm = useForm<GradingFormValues>({
    resolver: zodResolver(gradingFormSchema),
    defaultValues: {
      score: 85,
      feedback: '',
      status: 'dinilai',
    },
  });

  // Modal handlers
  const handleOpenMaterialModal = (material?: TeacherProgramMaterialItem) => {
    if (material) {
      setEditingMaterial(material);
      materialForm.reset({
        program_type: material.program_type,
        title: material.title,
        material_type: material.material_type,
        description: material.description || '',
        external_url: material.external_url || '',
        class_id: material.class_id || '',
      });
    } else {
      setEditingMaterial(null);
      materialForm.reset({
        program_type: 'tahfidz',
        title: '',
        material_type: 'panduan',
        description: '',
        external_url: '',
        class_id: '',
      });
    }
    setIsMaterialModalOpen(true);
  };

  const handleOpenTaskModal = (task?: TeacherProgramTaskItem) => {
    if (task) {
      setEditingTask(task);
      taskForm.reset({
        program_type: task.program_type,
        title: task.title,
        instructions: task.instructions || '',
        due_at: task.due_at ? task.due_at.substring(0, 16) : '',
        class_id: task.class_id || '',
      });
    } else {
      setEditingTask(null);
      taskForm.reset({
        program_type: 'tahfidz',
        title: '',
        instructions: '',
        due_at: '',
        class_id: '',
      });
    }
    setIsTaskModalOpen(true);
  };

  const handleOpenTargetModal = (target?: TeacherProgramTargetItem) => {
    if (target) {
      setEditingTarget(target);
      targetForm.reset({
        student_id: target.student_id,
        program_type: target.program_type,
        title: target.title,
        domain: target.domain || '',
        target_value: target.target_value || '',
        current_value: target.current_value || '',
        period: target.period || '',
        status: (target.status as any) || 'aktif',
      });
    } else {
      setEditingTarget(null);
      targetForm.reset({
        student_id: '',
        program_type: 'tahfidz',
        title: '',
        domain: 'hafalan',
        target_value: '',
        current_value: '',
        period: 'Semester Ganjil 2026/2027',
        status: 'aktif',
      });
    }
    setIsTargetModalOpen(true);
  };

  const handleOpenGradingModal = (submission: TeacherTaskSubmissionItem) => {
    setGradingSubmission(submission);
    gradingForm.reset({
      score: submission.score ?? 85,
      feedback: submission.feedback || '',
      status: (submission.status === 'perlu_revisi' ? 'perlu_revisi' : 'dinilai') as any,
    });
  };

  // Submit actions
  const onSubmitMaterial = async (data: MaterialFormValues) => {
    try {
      if (editingMaterial) {
        await updateMaterialMutation.mutateAsync({
          id: editingMaterial.id,
          payload: {
            title: data.title,
            material_type: data.material_type,
            description: data.description || undefined,
            external_url: data.external_url || undefined,
            class_id: data.class_id || undefined,
          },
        });
      } else {
        await createMaterialMutation.mutateAsync({
          program_type: data.program_type,
          title: data.title,
          material_type: data.material_type,
          description: data.description || undefined,
          external_url: data.external_url || undefined,
          class_id: data.class_id || undefined,
        });
      }
      setIsMaterialModalOpen(false);
    } catch {
      // handled by mutation error
    }
  };

  const onSubmitTask = async (data: TaskFormValues) => {
    try {
      if (editingTask) {
        await updateTaskMutation.mutateAsync({
          id: editingTask.id,
          payload: {
            title: data.title,
            instructions: data.instructions || undefined,
            due_at: data.due_at || undefined,
            class_id: data.class_id || undefined,
          },
        });
      } else {
        await createTaskMutation.mutateAsync({
          program_type: data.program_type,
          title: data.title,
          instructions: data.instructions || undefined,
          due_at: data.due_at || undefined,
          class_id: data.class_id || undefined,
        });
      }
      setIsTaskModalOpen(false);
    } catch {
      // handled by mutation error
    }
  };

  const onSubmitTarget = async (data: TargetFormValues) => {
    try {
      if (editingTarget) {
        await updateTargetMutation.mutateAsync({
          id: editingTarget.id,
          payload: {
            title: data.title,
            domain: data.domain || undefined,
            target_value: data.target_value || undefined,
            current_value: data.current_value || undefined,
            period: data.period || undefined,
            status: data.status,
          },
        });
      } else {
        await createTargetMutation.mutateAsync({
          student_id: data.student_id,
          program_type: data.program_type,
          title: data.title,
          domain: data.domain || undefined,
          target_value: data.target_value || undefined,
          current_value: data.current_value || undefined,
          period: data.period || undefined,
          status: data.status,
        });
      }
      setIsTargetModalOpen(false);
    } catch {
      // handled by mutation error
    }
  };

  const onSubmitGrading = async (data: GradingFormValues) => {
    if (!gradingSubmission) return;
    try {
      await gradeSubmissionMutation.mutateAsync({
        submissionId: gradingSubmission.id,
        payload: {
          score: data.score,
          feedback: data.feedback || undefined,
          status: data.status,
        },
      });
      setGradingSubmission(null);
    } catch {
      // handled by mutation error
    }
  };

  return (
    <div className="flex flex-col gap-4 text-slate-900 pb-12">
      {/* Page Header */}
      <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center gap-3.5">
          <div className="w-12 h-12 rounded-xl bg-blue-900 text-white flex items-center justify-center shrink-0 shadow-xs">
            <Sparkles className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg font-extrabold text-slate-900 tracking-tight">
              Kelola Program Khusus
            </h1>
            <p className="text-xs text-slate-500">
              Panel Pembina Tahfidz Al-Qur&apos;an, Cambridge ICP, dan Layanan Inklusi (PPI)
            </p>
          </div>
        </div>

        {/* Program Filter Pills */}
        <div className="flex flex-wrap items-center gap-1.5 bg-slate-50 p-1 rounded-xl border border-slate-200">
          {[
            { id: 'all', label: 'Semua Program' },
            { id: 'tahfidz', label: 'Tahfidz' },
            { id: 'icp', label: 'Cambridge (ICP)' },
            { id: 'inklusi', label: 'Inklusi (PPI)' },
          ].map((prog) => (
            <button
              key={prog.id}
              onClick={() => setSelectedProgram(prog.id)}
              className={cn(
                'px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer',
                selectedProgram === prog.id
                  ? 'bg-blue-900 text-white shadow-2xs'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'
              )}
            >
              {prog.label}
            </button>
          ))}
        </div>
      </div>

      {/* Main Tab Switcher and Search */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl p-3 shadow-xs">
        <div className="flex items-center gap-1.5 bg-slate-100 p-1 rounded-lg text-xs font-bold">
          <button
            onClick={() => setActiveTab('materi')}
            className={cn(
              'px-3.5 py-1.5 rounded-md transition-all inline-flex items-center gap-1.5 cursor-pointer',
              activeTab === 'materi'
                ? 'bg-white text-blue-900 shadow-2xs font-extrabold'
                : 'text-slate-600 hover:text-slate-900'
            )}
          >
            <BookOpen className="w-3.5 h-3.5" /> Materi Program
          </button>
          <button
            onClick={() => setActiveTab('tugas')}
            className={cn(
              'px-3.5 py-1.5 rounded-md transition-all inline-flex items-center gap-1.5 cursor-pointer',
              activeTab === 'tugas'
                ? 'bg-white text-blue-900 shadow-2xs font-extrabold'
                : 'text-slate-600 hover:text-slate-900'
            )}
          >
            <ClipboardList className="w-3.5 h-3.5" /> Tugas &amp; Penilaian
          </button>
          <button
            onClick={() => setActiveTab('target')}
            className={cn(
              'px-3.5 py-1.5 rounded-md transition-all inline-flex items-center gap-1.5 cursor-pointer',
              activeTab === 'target'
                ? 'bg-white text-blue-900 shadow-2xs font-extrabold'
                : 'text-slate-600 hover:text-slate-900'
            )}
          >
            <Target className="w-3.5 h-3.5" /> Target Capaian
          </button>
        </div>

        <div className="relative w-full sm:w-64">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          <input
            type="text"
            placeholder="Cari materi/tugas/siswa..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-9 pr-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:border-blue-500"
          />
        </div>
      </div>

      {/* =====================================================================
          TAB 1: MATERI PROGRAM
          ===================================================================== */}
      {activeTab === 'materi' && (
        <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
              <h2 className="text-sm font-extrabold text-slate-900">Daftar Materi &amp; Sumber Belajar</h2>
              <p className="text-[11px] text-slate-500">
                Kelola modul ajar, past papers, audio murottal, dan materi adaptif untuk siswa
              </p>
            </div>

            <PermissionGate permission="program.manage">
              <button
                onClick={() => handleOpenMaterialModal()}
                className="px-3.5 py-1.5 rounded-lg bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-2xs cursor-pointer"
              >
                <Plus className="w-3.5 h-3.5" /> Tambah Materi
              </button>
            </PermissionGate>
          </div>

          {/* Table / List */}
          <div className="mt-4">
            {materialsQuery.isLoading && (
              <div className="space-y-3 py-4">
                <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
                <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
                <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
              </div>
            )}

            {materialsQuery.isError && (
              <div className="py-8 text-center bg-red-50/50 border border-red-200 rounded-xl p-4">
                <AlertCircle className="w-6 h-6 text-red-500 mx-auto mb-1.5" />
                <p className="text-xs font-bold text-red-800">Gagal Memuat Materi Program</p>
                <button
                  onClick={() => materialsQuery.refetch()}
                  className="mt-2 px-3 py-1 text-xs font-bold text-red-700 bg-white border border-red-200 rounded-md hover:bg-red-50"
                >
                  <RefreshCw className="w-3 h-3 inline mr-1" /> Coba Lagi
                </button>
              </div>
            )}

            {!materialsQuery.isLoading && !materialsQuery.isError && (materialsQuery.data?.data?.length ?? 0) === 0 && (
              <div className="py-12 text-center border border-dashed border-slate-200 rounded-xl p-4">
                <BookOpen className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                <p className="text-xs font-bold text-slate-700">Belum Ada Materi Program</p>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Klik tombol &quot;Tambah Materi&quot; untuk mengunggah bahan ajar baru.
                </p>
              </div>
            )}

            {!materialsQuery.isLoading && !materialsQuery.isError && (materialsQuery.data?.data?.length ?? 0) > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="border-b border-slate-200 text-slate-400 uppercase text-[10px] font-bold">
                      <th className="py-3 px-3">Judul Materi</th>
                      <th className="py-3 px-3">Program</th>
                      <th className="py-3 px-3">Tipe</th>
                      <th className="py-3 px-3">Target Kelas</th>
                      <th className="py-3 px-3">Tautan / Berkas</th>
                      <th className="py-3 px-3 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {materialsQuery.data?.data.map((mat) => (
                      <tr key={mat.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3 px-3 font-bold text-slate-900 max-w-xs truncate">
                          {mat.title}
                          {mat.description && (
                            <p className="text-[11px] font-normal text-slate-500 truncate mt-0.5">
                              {mat.description}
                            </p>
                          )}
                        </td>
                        <td className="py-3 px-3">
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                            {mat.program_type}
                          </span>
                        </td>
                        <td className="py-3 px-3">
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                            {mat.material_type}
                          </span>
                        </td>
                        <td className="py-3 px-3 text-slate-600">
                          {mat.schoolClass?.name || 'Semua Kelas'}
                        </td>
                        <td className="py-3 px-3">
                          {mat.external_url ? (
                            <a
                              href={mat.external_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-blue-700 font-bold hover:underline inline-flex items-center gap-1"
                            >
                              Tautan <ExternalLink className="w-3 h-3" />
                            </a>
                          ) : (
                            <span className="text-slate-400">-</span>
                          )}
                        </td>
                        <td className="py-3 px-3 text-right">
                          <div className="inline-flex items-center gap-1">
                            <PermissionGate permission="program.manage">
                              <button
                                onClick={() => handleOpenMaterialModal(mat)}
                                className="p-1 rounded text-slate-500 hover:text-slate-800 hover:bg-slate-100 cursor-pointer"
                                title="Edit Materi"
                              >
                                <Edit2 className="w-3.5 h-3.5" />
                              </button>
                              <button
                                onClick={() => {
                                  if (confirm('Yakin ingin menghapus materi ini?')) {
                                    deleteMaterialMutation.mutate(mat.id);
                                  }
                                }}
                                className="p-1 rounded text-red-500 hover:text-red-700 hover:bg-red-50 cursor-pointer"
                                title="Hapus Materi"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                              </button>
                            </PermissionGate>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 2: TUGAS PROGRAM & PENILAIAN
          ===================================================================== */}
      {activeTab === 'tugas' && (
        <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
              <h2 className="text-sm font-extrabold text-slate-900">Daftar Tugas &amp; Setoran Mandiri</h2>
              <p className="text-[11px] text-slate-500">
                Buat penugasan halaqah, essay checkpoint, latihan modul adaptif, dan telaah submission siswa
              </p>
            </div>

            <PermissionGate permission="program.manage">
              <button
                onClick={() => handleOpenTaskModal()}
                className="px-3.5 py-1.5 rounded-lg bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-2xs cursor-pointer"
              >
                <Plus className="w-3.5 h-3.5" /> Tambah Tugas
              </button>
            </PermissionGate>
          </div>

          {/* Table / List */}
          <div className="mt-4">
            {tasksQuery.isLoading && (
              <div className="space-y-3 py-4">
                <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
                <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
              </div>
            )}

            {tasksQuery.isError && (
              <div className="py-8 text-center bg-red-50/50 border border-red-200 rounded-xl p-4">
                <AlertCircle className="w-6 h-6 text-red-500 mx-auto mb-1.5" />
                <p className="text-xs font-bold text-red-800">Gagal Memuat Tugas Program</p>
                <button
                  onClick={() => tasksQuery.refetch()}
                  className="mt-2 px-3 py-1 text-xs font-bold text-red-700 bg-white border border-red-200 rounded-md hover:bg-red-50"
                >
                  <RefreshCw className="w-3 h-3 inline mr-1" /> Coba Lagi
                </button>
              </div>
            )}

            {!tasksQuery.isLoading && !tasksQuery.isError && (tasksQuery.data?.data?.length ?? 0) === 0 && (
              <div className="py-12 text-center border border-dashed border-slate-200 rounded-xl p-4">
                <ClipboardList className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                <p className="text-xs font-bold text-slate-700">Belum Ada Tugas Program</p>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Klik tombol &quot;Tambah Tugas&quot; untuk membuat lembar penugasan baru.
                </p>
              </div>
            )}

            {!tasksQuery.isLoading && !tasksQuery.isError && (tasksQuery.data?.data?.length ?? 0) > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="border-b border-slate-200 text-slate-400 uppercase text-[10px] font-bold">
                      <th className="py-3 px-3">Judul Tugas</th>
                      <th className="py-3 px-3">Program</th>
                      <th className="py-3 px-3">Target Kelas</th>
                      <th className="py-3 px-3">Batas Waktu</th>
                      <th className="py-3 px-3">Pengumpulan</th>
                      <th className="py-3 px-3 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {tasksQuery.data?.data.map((task) => (
                      <tr key={task.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3 px-3 font-bold text-slate-900 max-w-xs truncate">
                          {task.title}
                          {task.instructions && (
                            <p className="text-[11px] font-normal text-slate-500 truncate mt-0.5">
                              {task.instructions}
                            </p>
                          )}
                        </td>
                        <td className="py-3 px-3">
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                            {task.program_type}
                          </span>
                        </td>
                        <td className="py-3 px-3 text-slate-600">
                          {task.schoolClass?.name || 'Semua Kelas'}
                        </td>
                        <td className="py-3 px-3 text-slate-600">
                          {task.due_at
                            ? new Date(task.due_at).toLocaleDateString('id-ID', {
                                day: 'numeric',
                                month: 'short',
                                hour: '2-digit',
                                minute: '2-digit',
                              })
                            : '-'}
                        </td>
                        <td className="py-3 px-3">
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            {task.submissions_count ?? 0} Dikumpulkan
                          </span>
                        </td>
                        <td className="py-3 px-3 text-right">
                          <div className="inline-flex items-center gap-1.5">
                            <button
                              onClick={() => setInspectingTask(task)}
                              className="px-2.5 py-1 rounded bg-blue-50 hover:bg-blue-100 text-blue-900 font-bold text-[11px] border border-blue-200 inline-flex items-center gap-1 cursor-pointer"
                            >
                              <Eye className="w-3 h-3" /> Telaah
                            </button>
                            <PermissionGate permission="program.manage">
                              <button
                                onClick={() => handleOpenTaskModal(task)}
                                className="p-1 rounded text-slate-500 hover:text-slate-800 hover:bg-slate-100 cursor-pointer"
                                title="Edit Tugas"
                              >
                                <Edit2 className="w-3.5 h-3.5" />
                              </button>
                              <button
                                onClick={() => {
                                  if (confirm('Yakin ingin menghapus tugas ini?')) {
                                    deleteTaskMutation.mutate(task.id);
                                  }
                                }}
                                className="p-1 rounded text-red-500 hover:text-red-700 hover:bg-red-50 cursor-pointer"
                                title="Hapus Tugas"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                              </button>
                            </PermissionGate>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* =====================================================================
          TAB 3: TARGET CAPAIAN SISWA
          ===================================================================== */}
      {activeTab === 'target' && (
        <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
              <h2 className="text-sm font-extrabold text-slate-900">Daftar Target Capaian Siswa</h2>
              <p className="text-[11px] text-slate-500">
                Tetapkan target hafalan, skor kesiapan checkpoint Cambridge, atau milestone pembelajaran individual PPI
              </p>
            </div>

            <PermissionGate permission="program.manage">
              <button
                onClick={() => handleOpenTargetModal()}
                className="px-3.5 py-1.5 rounded-lg bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-2xs cursor-pointer"
              >
                <Plus className="w-3.5 h-3.5" /> Tambah Target Siswa
              </button>
            </PermissionGate>
          </div>

          {/* Table / List */}
          <div className="mt-4">
            {targetsQuery.isLoading && (
              <div className="space-y-3 py-4">
                <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
                <div className="h-14 bg-slate-100 rounded-lg animate-pulse" />
              </div>
            )}

            {targetsQuery.isError && (
              <div className="py-8 text-center bg-red-50/50 border border-red-200 rounded-xl p-4">
                <AlertCircle className="w-6 h-6 text-red-500 mx-auto mb-1.5" />
                <p className="text-xs font-bold text-red-800">Gagal Memuat Target Siswa</p>
                <button
                  onClick={() => targetsQuery.refetch()}
                  className="mt-2 px-3 py-1 text-xs font-bold text-red-700 bg-white border border-red-200 rounded-md hover:bg-red-50"
                >
                  <RefreshCw className="w-3 h-3 inline mr-1" /> Coba Lagi
                </button>
              </div>
            )}

            {!targetsQuery.isLoading && !targetsQuery.isError && (targetsQuery.data?.data?.length ?? 0) === 0 && (
              <div className="py-12 text-center border border-dashed border-slate-200 rounded-xl p-4">
                <Target className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                <p className="text-xs font-bold text-slate-700">Belum Ada Target Capaian</p>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Klik tombol &quot;Tambah Target Siswa&quot; untuk menetapkan target pembelajaran baru.
                </p>
              </div>
            )}

            {!targetsQuery.isLoading && !targetsQuery.isError && (targetsQuery.data?.data?.length ?? 0) > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="border-b border-slate-200 text-slate-400 uppercase text-[10px] font-bold">
                      <th className="py-3 px-3">Siswa Sasaran</th>
                      <th className="py-3 px-3">Target Capaian</th>
                      <th className="py-3 px-3">Domain</th>
                      <th className="py-3 px-3">Progres / Target</th>
                      <th className="py-3 px-3">Periode</th>
                      <th className="py-3 px-3">Status</th>
                      <th className="py-3 px-3 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {targetsQuery.data?.data.map((target) => (
                      <tr key={target.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3 px-3 font-bold text-slate-900">
                          {target.student?.full_name || target.student_id}
                          <p className="text-[10px] font-normal text-slate-400">
                            NIS: {target.student?.nis || '-'}
                          </p>
                        </td>
                        <td className="py-3 px-3 font-semibold text-slate-800">
                          {target.title}
                        </td>
                        <td className="py-3 px-3">
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                            {target.domain || 'Umum'}
                          </span>
                        </td>
                        <td className="py-3 px-3 text-slate-700 font-medium">
                          {target.current_value || '0'} / {target.target_value || '-'}
                        </td>
                        <td className="py-3 px-3 text-slate-500 text-[11px]">
                          {target.period || '-'}
                        </td>
                        <td className="py-3 px-3">
                          <span
                            className={cn(
                              'px-2 py-0.5 rounded text-[10px] font-bold border inline-flex items-center gap-1',
                              target.status === 'tercapai'
                                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                : target.status === 'batal'
                                ? 'bg-rose-50 text-rose-800 border-rose-200'
                                : 'bg-amber-50 text-amber-800 border-amber-200'
                            )}
                          >
                            {target.status === 'tercapai'
                              ? 'Tercapai'
                              : target.status === 'batal'
                              ? 'Batal'
                              : 'Dalam Proses'}
                          </span>
                        </td>
                        <td className="py-3 px-3 text-right">
                          <div className="inline-flex items-center gap-1">
                            <PermissionGate permission="program.manage">
                              <button
                                onClick={() => handleOpenTargetModal(target)}
                                className="p-1 rounded text-slate-500 hover:text-slate-800 hover:bg-slate-100 cursor-pointer"
                                title="Edit Target"
                              >
                                <Edit2 className="w-3.5 h-3.5" />
                              </button>
                              <button
                                onClick={() => {
                                  if (confirm('Yakin ingin menghapus target ini?')) {
                                    deleteTargetMutation.mutate(target.id);
                                  }
                                }}
                                className="p-1 rounded text-red-500 hover:text-red-700 hover:bg-red-50 cursor-pointer"
                                title="Hapus Target"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                              </button>
                            </PermissionGate>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* =====================================================================
          DRAWER / MODAL: TELAAH SUBMISSION TUGAS
          ===================================================================== */}
      {inspectingTask && (
        <div
          role="dialog"
          aria-labelledby="submissions-title"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
        >
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden">
            <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 id="submissions-title" className="text-sm font-extrabold text-slate-900">
                  Pengumpulan Tugas: {inspectingTask.title}
                </h3>
                <p className="text-[11px] text-slate-500">
                  Program {inspectingTask.program_type.toUpperCase()} &middot; Batas:{' '}
                  {inspectingTask.due_at
                    ? new Date(inspectingTask.due_at).toLocaleDateString('id-ID')
                    : 'Tidak ada batas'}
                </p>
              </div>
              <button
                onClick={() => setInspectingTask(null)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="p-4 sm:p-5 overflow-y-auto flex-1">
              {submissionsQuery.isLoading && (
                <div className="py-12 text-center text-xs text-slate-400">
                  <RefreshCw className="w-5 h-5 animate-spin mx-auto mb-2 text-blue-900" />
                  Memuat data pengumpulan tugas...
                </div>
              )}

              {!submissionsQuery.isLoading && (submissionsQuery.data?.data?.length ?? 0) === 0 && (
                <div className="py-12 text-center border border-dashed border-slate-200 rounded-xl p-4">
                  <Clock className="w-7 h-7 text-slate-300 mx-auto mb-2" />
                  <p className="text-xs font-bold text-slate-700">Belum Ada Siswa Mengumpulkan</p>
                  <p className="text-[11px] text-slate-500 mt-0.5">
                    Tugas ini belum memiliki respon pengumpulan dari siswa.
                  </p>
                </div>
              )}

              {!submissionsQuery.isLoading && (submissionsQuery.data?.data?.length ?? 0) > 0 && (
                <div className="space-y-3">
                  {submissionsQuery.data?.data.map((sub) => (
                    <div
                      key={sub.id}
                      className="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col gap-2.5"
                    >
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                          <h4 className="text-xs font-bold text-slate-900">
                            {sub.student?.full_name || sub.student_id}
                          </h4>
                          <span className="text-[10px] text-slate-500 font-mono">
                            NIS: {sub.student?.nis || '-'} &middot; Dikumpulkan:{' '}
                            {new Date(sub.submitted_at).toLocaleDateString('id-ID', {
                              day: 'numeric',
                              month: 'short',
                              hour: '2-digit',
                              minute: '2-digit',
                            })}
                          </span>
                        </div>

                        <div className="flex items-center gap-2">
                          <span
                            className={cn(
                              'px-2 py-0.5 rounded text-[10px] font-bold border',
                              sub.status === 'dinilai'
                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                : 'bg-amber-100 text-amber-800 border-amber-300'
                            )}
                          >
                            {sub.status === 'dinilai' ? `Nilai: ${sub.score}/100` : 'Menunggu Penilaian'}
                          </span>

                          <PermissionGate permission="program.manage">
                            <button
                              onClick={() => handleOpenGradingModal(sub)}
                              className="px-2.5 py-1 rounded bg-blue-900 hover:bg-blue-950 text-white font-bold text-[10px] transition-colors cursor-pointer"
                            >
                              {sub.status === 'dinilai' ? 'Ubah Nilai' : 'Beri Nilai'}
                            </button>
                          </PermissionGate>
                        </div>
                      </div>

                      {/* Content Link / Notes */}
                      {sub.link && (
                        <div className="text-xs">
                          <span className="font-semibold text-slate-600">Tautan Berkas: </span>
                          <a
                            href={sub.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-700 font-bold hover:underline inline-flex items-center gap-1"
                          >
                            Buka Tautan Tugas Siswa <ExternalLink className="w-3 h-3" />
                          </a>
                        </div>
                      )}

                      {sub.notes && (
                        <div className="text-xs bg-white p-2.5 rounded-lg border border-slate-200">
                          <span className="font-semibold text-slate-700 block mb-0.5">Catatan Siswa:</span>
                          <p className="text-slate-600 whitespace-pre-wrap">{sub.notes}</p>
                        </div>
                      )}

                      {sub.feedback && (
                        <div className="text-xs bg-emerald-50/70 p-2.5 rounded-lg border border-emerald-200 text-emerald-950">
                          <span className="font-bold text-emerald-900 block mb-0.5">Catatan Evaluasi Guru:</span>
                          <p>{sub.feedback}</p>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL: FORM MATERI PROGRAM
          ===================================================================== */}
      {isMaterialModalOpen && (
        <div
          role="dialog"
          aria-labelledby="material-modal-title"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
        >
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
              <h3 id="material-modal-title" className="text-sm font-extrabold text-slate-900">
                {editingMaterial ? 'Edit Materi Program' : 'Tambah Materi Program'}
              </h3>
              <button
                onClick={() => setIsMaterialModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={materialForm.handleSubmit(onSubmitMaterial)} className="p-4 sm:p-5 space-y-3.5">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Jenis Program</label>
                <select
                  {...materialForm.register('program_type')}
                  disabled={Boolean(editingMaterial)}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                >
                  <option value="tahfidz">Tahfidz Al-Qur&apos;an</option>
                  <option value="icp">Cambridge (ICP)</option>
                  <option value="inklusi">Inklusi (PPI)</option>
                  <option value="reguler">Reguler</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Judul Materi *</label>
                <input
                  type="text"
                  placeholder="Contoh: Kaidah Tajwid Makhraj Huruf"
                  {...materialForm.register('title')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                />
                {materialForm.formState.errors.title && (
                  <p className="text-[11px] text-red-600 mt-1">
                    {materialForm.formState.errors.title.message}
                  </p>
                )}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Tipe Materi *</label>
                  <select
                    {...materialForm.register('material_type')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                  >
                    <option value="panduan">Panduan Ajar</option>
                    <option value="tajwid">Tajwid</option>
                    <option value="murottal">Murottal</option>
                    <option value="past_paper">Past Paper Cambridge</option>
                    <option value="modul_adaptif">Modul Adaptif</option>
                    <option value="reading">Reading Passage</option>
                    <option value="dokumen">Dokumen PDF</option>
                    <option value="video">Video</option>
                    <option value="audio">Audio</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Target Kelas</label>
                  <select
                    {...materialForm.register('class_id')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                  >
                    <option value="">Semua Kelas Terkait</option>
                    {teacherClasses.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Tautan URL Eksternal</label>
                <input
                  type="url"
                  placeholder="https://drive.google.com/..."
                  {...materialForm.register('external_url')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                />
                {materialForm.formState.errors.external_url && (
                  <p className="text-[11px] text-red-600 mt-1">
                    {materialForm.formState.errors.external_url.message}
                  </p>
                )}
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Deskripsi / Petunjuk</label>
                <textarea
                  rows={3}
                  placeholder="Tuliskan petunjuk atau catatan materi di sini..."
                  {...materialForm.register('description')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 resize-none"
                />
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setIsMaterialModalOpen(false)}
                  className="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={createMaterialMutation.isPending || updateMaterialMutation.isPending}
                  className="px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-900 hover:bg-blue-950 transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {createMaterialMutation.isPending || updateMaterialMutation.isPending
                    ? 'Menyimpan...'
                    : 'Simpan Materi'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL: FORM TUGAS PROGRAM
          ===================================================================== */}
      {isTaskModalOpen && (
        <div
          role="dialog"
          aria-labelledby="task-modal-title"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
        >
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
              <h3 id="task-modal-title" className="text-sm font-extrabold text-slate-900">
                {editingTask ? 'Edit Tugas Program' : 'Tambah Tugas Program Baru'}
              </h3>
              <button
                onClick={() => setIsTaskModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={taskForm.handleSubmit(onSubmitTask)} className="p-4 sm:p-5 space-y-3.5">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Jenis Program</label>
                <select
                  {...taskForm.register('program_type')}
                  disabled={Boolean(editingTask)}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                >
                  <option value="tahfidz">Tahfidz Al-Qur&apos;an</option>
                  <option value="icp">Cambridge (ICP)</option>
                  <option value="inklusi">Inklusi (PPI)</option>
                  <option value="reguler">Reguler</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Judul Tugas *</label>
                <input
                  type="text"
                  placeholder="Contoh: Setoran Mandiri Surah An-Naba 1-20"
                  {...taskForm.register('title')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                />
                {taskForm.formState.errors.title && (
                  <p className="text-[11px] text-red-600 mt-1">{taskForm.formState.errors.title.message}</p>
                )}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Target Kelas</label>
                  <select
                    {...taskForm.register('class_id')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                  >
                    <option value="">Semua Kelas Terkait</option>
                    {teacherClasses.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Batas Waktu (Due Date)</label>
                  <input
                    type="datetime-local"
                    {...taskForm.register('due_at')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Petunjuk Penugasan</label>
                <textarea
                  rows={3}
                  placeholder="Tuliskan petunjuk pengerjaan dan format pengumpulan..."
                  {...taskForm.register('instructions')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 resize-none"
                />
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setIsTaskModalOpen(false)}
                  className="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={createTaskMutation.isPending || updateTaskMutation.isPending}
                  className="px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-900 hover:bg-blue-950 transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {createTaskMutation.isPending || updateTaskMutation.isPending
                    ? 'Menyimpan...'
                    : 'Simpan Tugas'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL: FORM TARGET SISWA
          ===================================================================== */}
      {isTargetModalOpen && (
        <div
          role="dialog"
          aria-labelledby="target-modal-title"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
        >
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
              <h3 id="target-modal-title" className="text-sm font-extrabold text-slate-900">
                {editingTarget ? 'Edit Target Capaian Siswa' : 'Tambah Target Capaian Siswa'}
              </h3>
              <button
                onClick={() => setIsTargetModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={targetForm.handleSubmit(onSubmitTarget)} className="p-4 sm:p-5 space-y-3.5">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">ID Siswa Sasaran *</label>
                <input
                  type="text"
                  placeholder="ID Siswa (UUID)"
                  disabled={Boolean(editingTarget)}
                  {...targetForm.register('student_id')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 font-mono"
                />
                {targetForm.formState.errors.student_id && (
                  <p className="text-[11px] text-red-600 mt-1">
                    {targetForm.formState.errors.student_id.message}
                  </p>
                )}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Jenis Program</label>
                  <select
                    {...targetForm.register('program_type')}
                    disabled={Boolean(editingTarget)}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                  >
                    <option value="tahfidz">Tahfidz</option>
                    <option value="icp">Cambridge (ICP)</option>
                    <option value="inklusi">Inklusi (PPI)</option>
                    <option value="reguler">Reguler</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Domain</label>
                  <input
                    type="text"
                    placeholder="hafalan, english, motorik..."
                    {...targetForm.register('domain')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Judul Target *</label>
                <input
                  type="text"
                  placeholder="Contoh: Hafalan Juz 3 Mutqin"
                  {...targetForm.register('title')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                />
                {targetForm.formState.errors.title && (
                  <p className="text-[11px] text-red-600 mt-1">
                    {targetForm.formState.errors.title.message}
                  </p>
                )}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Target Nilai</label>
                  <input
                    type="text"
                    placeholder="Contoh: 3 Juz atau Band 5.0"
                    {...targetForm.register('target_value')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Capaian Saat Ini</label>
                  <input
                    type="text"
                    placeholder="Contoh: 1.5 Juz atau Band 4.5"
                    {...targetForm.register('current_value')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Periode</label>
                  <input
                    type="text"
                    placeholder="Semester Ganjil 2026/2027"
                    {...targetForm.register('period')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Status Target</label>
                  <select
                    {...targetForm.register('status')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                  >
                    <option value="aktif">Dalam Proses (Aktif)</option>
                    <option value="tercapai">Tercapai</option>
                    <option value="batal">Batal</option>
                  </select>
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setIsTargetModalOpen(false)}
                  className="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={createTargetMutation.isPending || updateTargetMutation.isPending}
                  className="px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-900 hover:bg-blue-950 transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {createTargetMutation.isPending || updateTargetMutation.isPending
                    ? 'Menyimpan...'
                    : 'Simpan Target'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =====================================================================
          MODAL: FORM PENILAIAN SUBMISSION
          ===================================================================== */}
      {gradingSubmission && (
        <div
          role="dialog"
          aria-labelledby="grading-modal-title"
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in"
        >
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md overflow-hidden flex flex-col">
            <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 id="grading-modal-title" className="text-sm font-extrabold text-slate-900">
                  Penilaian Submission Tugas
                </h3>
                <p className="text-[11px] text-slate-500">
                  {gradingSubmission.student?.full_name || gradingSubmission.student_id}
                </p>
              </div>
              <button
                onClick={() => setGradingSubmission(null)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={gradingForm.handleSubmit(onSubmitGrading)} className="p-4 sm:p-5 space-y-3.5">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Nilai Angka (0 - 100) *</label>
                <input
                  type="number"
                  step="0.5"
                  min="0"
                  max="100"
                  {...gradingForm.register('score')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm font-bold text-slate-900 focus:outline-hidden focus:border-blue-500"
                />
                {gradingForm.formState.errors.score && (
                  <p className="text-[11px] text-red-600 mt-1">
                    {gradingForm.formState.errors.score.message}
                  </p>
                )}
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Status Penilaian</label>
                <select
                  {...gradingForm.register('status')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 bg-white"
                >
                  <option value="dinilai">Dinilai (Selesai)</option>
                  <option value="perlu_revisi">Perlu Revisi</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Catatan Evaluasi / Feedback</label>
                <textarea
                  rows={4}
                  placeholder="Tuliskan umpan balik untuk siswa..."
                  {...gradingForm.register('feedback')}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-hidden focus:border-blue-500 resize-none"
                />
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setGradingSubmission(null)}
                  className="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={gradeSubmissionMutation.isPending}
                  className="px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-900 hover:bg-blue-950 transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {gradeSubmissionMutation.isPending ? 'Menyimpan...' : 'Simpan Nilai'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
