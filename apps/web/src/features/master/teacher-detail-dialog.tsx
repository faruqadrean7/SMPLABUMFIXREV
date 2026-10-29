'use client';

import React, { useState, useEffect } from 'react';
import { TeacherItem, TeacherSalarySlipItem } from '@/types/master';
import { teachersApi } from '@/features/master/teachers-api';
import { TeacherSalarySlipDialog } from '@/features/master/teacher-salary-slip-dialog';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import {
  X,
  GraduationCap,
  BookOpen,
  Layers,
  ShieldCheck,
  Mail,
  Clock,
  CheckCircle2,
  AlertTriangle,
  Receipt,
  Upload,
  Download,
  Trash2,
  FileText,
  Loader2,
} from 'lucide-react';

export interface TeacherDetailDialogProps {
  isOpen: boolean;
  onClose: () => void;
  teacher: TeacherItem | null;
  onTeacherUpdated?: (updatedTeacher: TeacherItem) => void;
}

export function TeacherDetailDialog({
  isOpen,
  onClose,
  teacher,
  onTeacherUpdated,
}: TeacherDetailDialogProps) {
  const [activeTab, setActiveTab] = useState<'teaching' | 'salary'>('teaching');
  const [isUploadDialogOpen, setIsUploadDialogOpen] = useState<boolean>(false);
  const [salarySlips, setSalarySlips] = useState<TeacherSalarySlipItem[]>([]);
  const [isLoadingSlips, setIsLoadingSlips] = useState<boolean>(false);
  const [slipSuccessMessage, setSlipSuccessMessage] = useState<string | null>(null);
  const [slipErrorMessage, setSlipErrorMessage] = useState<string | null>(null);
  const [deletingSlipId, setDeletingSlipId] = useState<string | null>(null);
  const [slipToDelete, setSlipToDelete] = useState<string | null>(null);

  // Initialize or fetch salary slips
  useEffect(() => {
    if (!teacher || !isOpen) return;

    const initialSlips = teacher.salarySlips || (teacher as any).salary_slips || [];
    setSalarySlips(initialSlips);

    // If teacher ID is present, fetch latest slips in background
    let isMounted = true;
    const fetchSlips = async () => {
      try {
        setIsLoadingSlips(true);
        const data = await teachersApi.listSalarySlips(teacher.id);
        if (isMounted) {
          setSalarySlips(data);
        }
      } catch (err) {
        // Fallback to initial slips if already present
      } finally {
        if (isMounted) setIsLoadingSlips(false);
      }
    };

    fetchSlips();

    return () => {
      isMounted = false;
    };
  }, [teacher, isOpen]);

  if (!isOpen || !teacher) return null;

  const statusVariant =
    teacher.status === 'tetap'
      ? 'success'
      : teacher.status === 'kontrak'
      ? 'info'
      : 'warning';

  const teachingAssignments =
    teacher.teachingAssignments || (teacher as any).teaching_assignments || [];
  const homeroomClasses =
    teacher.homeroomClasses || (teacher as any).homeroom_classes || [];

  // Calculate JJM (Jam Pelajaran per minggu)
  const totalTeachingHours = teachingAssignments.reduce(
    (sum: number, a: any) => sum + (Number(a.hours_per_week) || 0),
    0
  );
  const targetHours = 24; // Standar Sertifikasi Kemendikbud
  const isTargetMet = totalTeachingHours >= targetHours;

  const handleSalarySlipUploaded = (newSlip: TeacherSalarySlipItem) => {
    setSalarySlips((prev) => [newSlip, ...prev.filter((s) => s.id !== newSlip.id)]);
    setSlipSuccessMessage(
      `Slip gaji periode ${newSlip.period_formatted || `Bulan ${newSlip.period_month}/${newSlip.period_year}`} berhasil diunggah.`
    );
    setTimeout(() => setSlipSuccessMessage(null), 5000);
  };

  const handleDeleteSlip = (slipId: string) => {
    setSlipToDelete(slipId);
  };

  const handleConfirmDeleteSlip = async () => {
    if (!slipToDelete) return;
    try {
      setDeletingSlipId(slipToDelete);
      await teachersApi.deleteSalarySlip(teacher.id, slipToDelete);
      setSalarySlips((prev) => prev.filter((s) => s.id !== slipToDelete));
      toast.success('Slip gaji berhasil dihapus.');
      setSlipToDelete(null);
    } catch (err: any) {
      const errMsg = err?.response?.data?.message || 'Gagal menghapus slip gaji.';
      toast.error(errMsg);
    } finally {
      setDeletingSlipId(null);
    }
  };

  const handleDownloadSlip = (slipId: string) => {
    const downloadUrl = `/api/v1/teachers/${teacher.id}/salary-slips/${slipId}/download`;
    window.open(downloadUrl, '_blank');
  };

  return (
    <>
      <div
        className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
        role="dialog"
        aria-modal="true"
      >
        <div className="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-elevated border border-slate-200 my-8 max-h-[90vh] flex flex-col">
          {/* Header */}
          <div className="flex items-center justify-between pb-4 border-b border-slate-100 shrink-0">
            <div className="flex items-center gap-3">
              <Avatar
                src={teacher.photo_file?.url || (teacher as any).photo_url}
                name={teacher.full_name}
                size="lg"
                shape="rounded"
                alt={`Foto ${teacher.full_name}`}
                className="border border-slate-200 shadow-2xs"
              />
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="text-base font-bold text-slate-900">{teacher.full_name}</h2>
                  <Badge variant={statusVariant} className="text-[10px] capitalize">
                    {teacher.status}
                  </Badge>
                </div>
                <p className="text-xs text-slate-500">
                  {teacher.position || 'Tenaga Pendidik'} • NIP: {teacher.nip || '-'}
                </p>
              </div>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* Tab Navigation */}
          <div className="flex items-center gap-2 border-b border-slate-200 pt-3 pb-1 text-xs shrink-0">
            <button
              type="button"
              onClick={() => setActiveTab('teaching')}
              className={`flex items-center gap-1.5 px-3 py-2 font-semibold border-b-2 transition-colors ${
                activeTab === 'teaching'
                  ? 'border-brand-blue text-brand-blue'
                  : 'border-transparent text-slate-500 hover:text-slate-800'
              }`}
            >
              <Clock className="h-4 w-4" />
              <span>Penugasan & Jam Mengajar (JJM)</span>
              <span className="ml-1 rounded-full bg-slate-100 px-1.5 py-0.2 text-[10px] font-mono text-slate-600">
                {totalTeachingHours} JP
              </span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('salary')}
              className={`flex items-center gap-1.5 px-3 py-2 font-semibold border-b-2 transition-colors ${
                activeTab === 'salary'
                  ? 'border-emerald-600 text-emerald-700'
                  : 'border-transparent text-slate-500 hover:text-slate-800'
              }`}
            >
              <Receipt className="h-4 w-4" />
              <span>Slip Gaji & Payroll</span>
              {salarySlips.length > 0 && (
                <span className="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.2 text-[10px] font-mono text-emerald-800">
                  {salarySlips.length}
                </span>
              )}
            </button>
          </div>

          {/* Feedback alerts */}
          {slipSuccessMessage && (
            <div className="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 p-2.5 text-xs text-emerald-800 border border-emerald-200">
              <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
              <span>{slipSuccessMessage}</span>
            </div>
          )}

          {slipErrorMessage && (
            <div className="mt-3 flex items-center gap-2 rounded-lg bg-rose-50 p-2.5 text-xs text-rose-800 border border-rose-200">
              <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600" />
              <span>{slipErrorMessage}</span>
            </div>
          )}

          {/* Body Content */}
          <div className="mt-4 space-y-4 text-xs text-slate-600 overflow-y-auto flex-1 pr-1">
            {activeTab === 'teaching' ? (
              <>
                {/* Identitas Kepegawaian */}
                <div className="grid grid-cols-2 gap-3 p-3 rounded-lg bg-slate-50 border border-slate-100">
                  <div>
                    <span className="text-[11px] text-slate-400 block">NIP</span>
                    <span className="font-mono font-medium text-slate-800">{teacher.nip || '-'}</span>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-400 block">NUPTK</span>
                    <span className="font-mono font-medium text-slate-800">{teacher.nuptk || '-'}</span>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-400 block">Akun Portal Terhubung</span>
                    <span className="font-medium text-slate-800 flex items-center gap-1">
                      {teacher.user ? (
                        <>
                          <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                          <span>{teacher.user.username}</span>
                        </>
                      ) : (
                        <span className="text-slate-400 italic">Belum dibuatkan akun</span>
                      )}
                    </span>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-400 block">Email Akun</span>
                    <span className="font-medium text-slate-800 flex items-center gap-1">
                      {teacher.user?.email ? (
                        <>
                          <Mail className="h-3.5 w-3.5 text-slate-400" />
                          <span>{teacher.user.email}</span>
                        </>
                      ) : (
                        '-'
                      )}
                    </span>
                  </div>
                </div>

                {/* KARTU REKAPITULASI JAM MENGAJAR (JJM) */}
                <div className="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50/70 to-indigo-50/50 p-4 shadow-2xs">
                  <div className="flex items-center justify-between pb-3 border-b border-blue-200/60">
                    <div className="flex items-center gap-2">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white shadow-2xs">
                        <Clock className="h-4 w-4" />
                      </div>
                      <div>
                        <h3 className="font-bold text-slate-900 text-xs">
                          Rekapitulasi Jam Mengajar (JJM)
                        </h3>
                        <p className="text-[11px] text-slate-500">
                          Beban tatap muka mengajar per minggu aktif
                        </p>
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="font-mono text-base font-extrabold text-blue-900">
                        {totalTeachingHours} <span className="text-xs font-normal text-slate-600">JP / Minggu</span>
                      </div>
                      <span className="text-[10px] text-slate-400">Target Sertifikasi: 24 JP</span>
                    </div>
                  </div>

                  {/* Status Sertifikasi Badge & Progress Bar */}
                  <div className="mt-3 space-y-2">
                    <div className="flex items-center justify-between text-[11px]">
                      <span className="text-slate-600 font-medium">Beban Mengajar Sertifikasi Kemendikbud:</span>
                      {isTargetMet ? (
                        <span className="flex items-center gap-1 font-semibold text-emerald-700 bg-emerald-100/80 px-2 py-0.5 rounded-full text-[10px]">
                          <CheckCircle2 className="h-3 w-3" />
                          Memenuhi Beban (≥ 24 JP)
                        </span>
                      ) : (
                        <span className="flex items-center gap-1 font-semibold text-amber-700 bg-amber-100/80 px-2 py-0.5 rounded-full text-[10px]">
                          <AlertTriangle className="h-3 w-3" />
                          Kurang {targetHours - totalTeachingHours} JP
                        </span>
                      )}
                    </div>
                    <div className="w-full h-2 rounded-full bg-slate-200 overflow-hidden">
                      <div
                        className={`h-full transition-all duration-300 ${
                          isTargetMet ? 'bg-emerald-600' : 'bg-amber-500'
                        }`}
                        style={{ width: `${Math.min(100, (totalTeachingHours / targetHours) * 100)}%` }}
                      />
                    </div>
                  </div>
                </div>

                {/* Penugasan Mata Pelajaran yang Diampu */}
                <div>
                  <h3 className="font-semibold text-slate-900 mb-2 flex items-center gap-1.5 text-xs">
                    <BookOpen className="h-3.5 w-3.5 text-brand-teal" />
                    <span>Rincian Beban Mata Pelajaran ({teachingAssignments.length} Kelas)</span>
                  </h3>
                  {teachingAssignments.length > 0 ? (
                    <div className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                      {teachingAssignments.map((a: any, idx: number) => (
                        <div key={idx} className="p-2.5 flex items-center justify-between">
                          <div>
                            <span className="font-medium text-slate-800">
                              {a.subject?.name || 'Mata Pelajaran'}
                            </span>
                            <span className="text-[11px] text-slate-400 block">
                              Kelas: <strong className="text-slate-700">{a.class?.name || '-'}</strong>
                            </span>
                          </div>
                          <div className="text-right">
                            <span className="font-mono text-xs font-bold text-slate-800 block">
                              {a.hours_per_week || 2} JP
                            </span>
                            <span className="text-[10px] text-slate-400">per minggu</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-slate-400 italic p-3 rounded-lg bg-slate-50 border border-slate-100">
                      Belum ada penugasan mengajar terdaftar pada semester ini.
                    </p>
                  )}
                </div>

                {/* Wali Kelas */}
                <div>
                  <h3 className="font-semibold text-slate-900 mb-2 flex items-center gap-1.5 text-xs">
                    <Layers className="h-3.5 w-3.5 text-brand-blue" />
                    <span>Penugasan Wali Kelas</span>
                  </h3>
                  {homeroomClasses.length > 0 ? (
                    <div className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                      {homeroomClasses.map((cls: any) => (
                        <div key={cls.id} className="p-2.5 flex items-center justify-between">
                          <span className="font-medium text-slate-800">{cls.name}</span>
                          <Badge variant="info" className="text-[10px]">
                            Wali Kelas Aktif
                          </Badge>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-slate-400 italic p-2.5 rounded bg-slate-50 border border-slate-100">
                      Tidak bertugas sebagai wali kelas.
                    </p>
                  )}
                </div>
              </>
            ) : (
              /* TAB 2: SLIP GAJI & PAYROLL */
              <div className="space-y-4">
                <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                  <div>
                    <h3 className="font-bold text-slate-900 text-xs">
                      Dokumen Rincian Slip Gaji & Payroll
                    </h3>
                    <p className="text-[11px] text-slate-500">
                      Rincian honorarium bulanan yang dapat dilihat guru melalui Portal Mandiri
                    </p>
                  </div>
                  <Button
                    type="button"
                    size="sm"
                    onClick={() => setIsUploadDialogOpen(true)}
                    className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1 text-xs"
                  >
                    <Upload className="h-3.5 w-3.5" />
                    <span>Upload Slip Gaji</span>
                  </Button>
                </div>

                {isLoadingSlips && salarySlips.length === 0 ? (
                  <div className="flex items-center justify-center p-8 text-slate-400 gap-2">
                    <Loader2 className="h-4 w-4 animate-spin text-emerald-600" />
                    <span>Memuat riwayat slip gaji...</span>
                  </div>
                ) : salarySlips.length > 0 ? (
                  <div className="space-y-3">
                    {salarySlips.map((slip) => {
                      const isDeleting = deletingSlipId === slip.id;
                      const netVal = Number(slip.net_salary) || 0;
                      const basicVal = Number(slip.basic_salary) || 0;
                      const allowVal = Number(slip.allowances) || 0;
                      const deductVal = Number(slip.deductions) || 0;

                      return (
                        <div
                          key={slip.id}
                          className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs hover:border-slate-300 transition-colors"
                        >
                          <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                            <div className="flex items-center gap-2">
                              <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <FileText className="h-4 w-4" />
                              </div>
                              <div>
                                <span className="font-bold text-slate-900 text-xs block">
                                  {slip.period_formatted || `Periode Bulan ${slip.period_month} ${slip.period_year}`}
                                </span>
                                <span className="font-mono text-[10px] text-slate-400">
                                  {slip.slip_number || '-'}
                                </span>
                              </div>
                            </div>
                            <div className="text-right">
                              <span className="font-mono text-sm font-extrabold text-emerald-700 block">
                                Rp {netVal.toLocaleString('id-ID')}
                              </span>
                              <span className="text-[10px] text-slate-400">Take Home Pay</span>
                            </div>
                          </div>

                          {/* Rincian Komponen */}
                          <div className="mt-2.5 grid grid-cols-3 gap-2 bg-slate-50 p-2 rounded-lg text-[11px]">
                            <div>
                              <span className="text-slate-400 block text-[10px]">Gaji Pokok:</span>
                              <span className="font-mono font-semibold text-slate-700">
                                Rp {basicVal.toLocaleString('id-ID')}
                              </span>
                            </div>
                            <div>
                              <span className="text-slate-400 block text-[10px]">Tunjangan:</span>
                              <span className="font-mono font-semibold text-emerald-600">
                                + Rp {allowVal.toLocaleString('id-ID')}
                              </span>
                            </div>
                            <div>
                              <span className="text-slate-400 block text-[10px]">Potongan:</span>
                              <span className="font-mono font-semibold text-rose-600">
                                - Rp {deductVal.toLocaleString('id-ID')}
                              </span>
                            </div>
                          </div>

                          {slip.notes && (
                            <p className="mt-2 text-[11px] text-slate-500 italic">
                              Catatan: {slip.notes}
                            </p>
                          )}

                          {/* Actions */}
                          <div className="mt-3 flex items-center justify-between pt-2 border-t border-slate-100">
                            <span className="text-[10px] text-slate-400">
                              Status: <strong className="text-emerald-600 capitalize">{slip.status}</strong>
                            </span>
                            <div className="flex items-center gap-1.5">
                              {slip.file_id && (
                                <Button
                                  type="button"
                                  size="sm"
                                  variant="outline"
                                  className="h-7 px-2.5 text-[11px] gap-1 text-slate-700 hover:text-emerald-700 hover:border-emerald-300"
                                  onClick={() => handleDownloadSlip(slip.id)}
                                >
                                  <Download className="h-3 w-3" />
                                  <span>Unduh Slip (PDF)</span>
                                </Button>
                              )}
                              <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-7 px-2 text-[11px] text-rose-600 hover:bg-rose-50 hover:border-rose-300"
                                onClick={() => handleDeleteSlip(slip.id)}
                                disabled={isDeleting}
                              >
                                {isDeleting ? (
                                  <Loader2 className="h-3 w-3 animate-spin" />
                                ) : (
                                  <Trash2 className="h-3 w-3" />
                                )}
                              </Button>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="rounded-xl border border-dashed border-slate-300 p-8 text-center bg-slate-50/60">
                    <Receipt className="h-8 w-8 text-slate-300 mx-auto mb-2" />
                    <p className="text-xs font-semibold text-slate-700">Belum Ada Slip Gaji</p>
                    <p className="text-[11px] text-slate-400 mt-1 max-w-sm mx-auto">
                      Belum ada dokumen slip gaji yang diunggah untuk pendidik ini. Klik tombol di bawah untuk mengunggah berkas periode terbaru.
                    </p>
                    <Button
                      type="button"
                      size="sm"
                      onClick={() => setIsUploadDialogOpen(true)}
                      className="mt-3 bg-emerald-600 hover:bg-emerald-700 text-white gap-1 text-xs"
                    >
                      <Upload className="h-3.5 w-3.5" />
                      <span>Upload Slip Gaji Sekarang</span>
                    </Button>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="flex items-center justify-end pt-4 border-t border-slate-100 mt-4 shrink-0">
            <Button type="button" variant="outline" size="sm" onClick={onClose}>
              Tutup
            </Button>
          </div>
        </div>
      </div>

      {/* Upload Dialog Modal */}
      <TeacherSalarySlipDialog
        isOpen={isUploadDialogOpen}
        onClose={() => setIsUploadDialogOpen(false)}
        teacher={teacher}
        onSuccess={handleSalarySlipUploaded}
      />

      {/* Dialog Konfirmasi Hapus Slip Gaji */}
      <ConfirmDialog
        isOpen={!!slipToDelete}
        onClose={() => setSlipToDelete(null)}
        onConfirm={handleConfirmDeleteSlip}
        title="Hapus Slip Gaji"
        description="Apakah Anda yakin ingin menghapus dokumen slip gaji ini? Dokumen yang telah dihapus tidak dapat dipulihkan."
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={!!deletingSlipId}
      />
    </>
  );
}
