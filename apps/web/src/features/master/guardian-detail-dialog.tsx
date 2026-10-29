'use client';

import React, { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { GuardianItem, StudentItem } from '@/types/master';
import { guardiansApi } from '@/features/master/guardians-api';
import { studentsApi } from '@/features/master/students-api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import {
  X,
  Users,
  Phone,
  GraduationCap,
  Plus,
  Trash2,
  Check,
  AlertCircle,
  Star,
  Search,
  ChevronDown,
} from 'lucide-react';

export interface GuardianDetailDialogProps {
  isOpen: boolean;
  onClose: () => void;
  guardian: GuardianItem | null;
  onUpdated?: () => void;
}

export function GuardianDetailDialog({
  isOpen,
  onClose,
  guardian,
  onUpdated,
}: GuardianDetailDialogProps) {
  const [isLinkingOpen, setIsLinkingOpen] = useState(false);
  const [searchStudent, setSearchStudent] = useState('');
  const [selectedStudent, setSelectedStudent] = useState<StudentItem | null>(null);
  const [relationship, setRelationship] = useState<'ayah' | 'ibu' | 'wali'>('wali');
  const [isPrimary, setIsPrimary] = useState(false);
  const [linkError, setLinkError] = useState<string | null>(null);
  const [studentToDetach, setStudentToDetach] = useState<{ id: string; name: string } | null>(null);

  // Ambil detail lengkap wali terkini (termasuk relasi siswa & kelas)
  const { data: detailedGuardian, refetch } = useQuery({
    queryKey: ['guardian-detail', guardian?.id],
    queryFn: () => (guardian ? guardiansApi.get(guardian.id) : null),
    enabled: !!guardian?.id && isOpen,
    initialData: guardian || undefined,
  });

  // Query pencarian kandidat siswa untuk dihubungkan
  const { data: candidateStudents, isLoading: isSearchingStudents } = useQuery({
    queryKey: ['students-candidate-search', searchStudent],
    queryFn: () => studentsApi.list({ q: searchStudent, limit: 10 }),
    enabled: isLinkingOpen && searchStudent.trim().length >= 1,
  });

  // Mutasi hubungkan siswa
  const attachMutation = useMutation({
    mutationFn: () => {
      if (!selectedStudent || !guardian) {
        throw new Error('Pilih siswa terlebih dahulu');
      }
      return guardiansApi.attachToStudent(selectedStudent.id, {
        guardian_id: guardian.id,
        relationship,
        is_primary: isPrimary,
      });
    },
    onSuccess: async () => {
      await refetch();
      setIsLinkingOpen(false);
      setSelectedStudent(null);
      setSearchStudent('');
      setLinkError(null);
      onUpdated?.();
    },
    onError: (err: any) => {
      setLinkError(err?.response?.data?.error?.message || err?.message || 'Gagal menghubungkan siswa.');
    },
  });

  // Mutasi putuskan hubungan siswa
  const detachMutation = useMutation({
    mutationFn: (studentId: string) => {
      if (!guardian) throw new Error('Data wali tidak ditemukan');
      return guardiansApi.detachFromStudent(studentId, guardian.id);
    },
    onSuccess: async () => {
      await refetch();
      setLinkError(null);
      onUpdated?.();
    },
    onError: (err: any) => {
      setLinkError(err?.response?.data?.error?.message || err?.message || 'Gagal memutuskan hubungan siswa.');
    },
  });

  if (!isOpen || !guardian) return null;

  const currentGuardian = detailedGuardian || guardian;
  const linkedStudents = currentGuardian.students || [];

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-xl rounded-xl bg-white p-6 shadow-elevated border border-slate-200 my-8 max-h-[90vh] overflow-y-auto">
        {/* Header Dialog */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-700">
              <Users className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">{currentGuardian.full_name}</h2>
              <p className="text-xs text-slate-500 flex items-center gap-1.5">
                <Phone className="h-3 w-3 text-slate-400" />
                <span>{currentGuardian.phone}</span>
                {currentGuardian.occupation && (
                  <span className="text-slate-400">&bull; {currentGuardian.occupation}</span>
                )}
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

        {/* Informasi Kontak & Domisili */}
        <div className="mt-4 space-y-4 text-xs text-slate-600">
          <div className="grid grid-cols-2 gap-3 p-3 rounded-lg bg-slate-50 border border-slate-100">
            <div>
              <span className="text-[11px] text-slate-400 block font-medium">Pekerjaan</span>
              <span className="font-semibold text-slate-800">{currentGuardian.occupation || '-'}</span>
            </div>
            <div>
              <span className="text-[11px] text-slate-400 block font-medium">Alamat Domisili</span>
              <span className="font-semibold text-slate-800">{currentGuardian.address || '-'}</span>
            </div>
          </div>

          {/* Notifikasi Error jika ada */}
          {linkError && (
            <div className="p-3 rounded-lg bg-red-50 border border-red-200 flex items-start gap-2 text-status-danger">
              <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
              <div className="flex-1 text-xs">{linkError}</div>
              <button
                type="button"
                onClick={() => setLinkError(null)}
                className="text-status-danger/70 hover:text-status-danger"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            </div>
          )}

          {/* Bagian Siswa Terhubung */}
          <div>
            <div className="flex items-center justify-between mb-2.5">
              <h3 className="font-semibold text-slate-900 flex items-center gap-1.5 text-xs">
                <GraduationCap className="h-3.5 w-3.5 text-brand-blue" />
                <span>Siswa Terhubung ({linkedStudents.length})</span>
              </h3>
              {!isLinkingOpen && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsLinkingOpen(true)}
                  className="h-7 text-xs gap-1 border-purple-200 text-purple-700 hover:bg-purple-50"
                >
                  <Plus className="h-3 w-3" />
                  <span>Hubungkan Siswa</span>
                </Button>
              )}
            </div>

            {/* Form Tambah Tautan Siswa Baru */}
            {isLinkingOpen && (
              <div className="p-3.5 rounded-lg border border-purple-200 bg-purple-50/40 mb-3 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="font-semibold text-xs text-purple-900">
                    Hubungkan Siswa dengan Wali Ini
                  </span>
                  <button
                    type="button"
                    onClick={() => {
                      setIsLinkingOpen(false);
                      setSelectedStudent(null);
                      setLinkError(null);
                    }}
                    className="text-slate-400 hover:text-slate-600"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>

                {/* Pencarian Siswa */}
                <div>
                  <label className="text-[11px] font-medium text-slate-700 block mb-1">
                    Cari Siswa (Ketik Nama / NIS)
                  </label>
                  <div className="relative">
                    <Search className="h-3.5 w-3.5 absolute left-2.5 top-2.5 text-slate-400" />
                    <Input
                      type="text"
                      placeholder="Ketik minimal 1 karakter..."
                      value={searchStudent}
                      onChange={(e) => setSearchStudent(e.target.value)}
                      className="h-8 pl-8 text-xs bg-white"
                    />
                  </div>

                  {/* Hasil pencarian siswa */}
                  {isSearchingStudents && (
                    <p className="text-[11px] text-slate-400 mt-1">Mencari siswa...</p>
                  )}
                  {candidateStudents && candidateStudents.data.length > 0 && (
                    <div className="mt-1.5 max-h-36 overflow-y-auto divide-y divide-slate-100 rounded border border-slate-200 bg-white">
                      {candidateStudents.data.map((st) => {
                        const isAlreadyLinked = linkedStudents.some((ls) => ls.id === st.id);
                        return (
                          <button
                            key={st.id}
                            type="button"
                            disabled={isAlreadyLinked}
                            onClick={() => setSelectedStudent(st)}
                            className={`w-full text-left p-2 text-xs flex items-center justify-between hover:bg-slate-50 transition-colors ${
                              selectedStudent?.id === st.id ? 'bg-purple-50 font-medium' : ''
                            } ${isAlreadyLinked ? 'opacity-40 cursor-not-allowed' : ''}`}
                          >
                            <div>
                              <span className="font-semibold text-slate-900 block">{st.full_name}</span>
                              <span className="text-[10px] text-slate-400 font-mono">
                                NIS: {st.nis} &bull; NISN: {st.nisn}
                              </span>
                            </div>
                            {isAlreadyLinked ? (
                              <span className="text-[10px] text-slate-400">Sudah terhubung</span>
                            ) : selectedStudent?.id === st.id ? (
                              <Check className="h-3.5 w-3.5 text-purple-700" />
                            ) : null}
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>

                {/* Form Opsi Hubungan & Status Primary */}
                {selectedStudent && (
                  <div className="space-y-2.5 pt-2 border-t border-purple-200">
                    <div className="flex items-center gap-2 text-xs text-purple-900 bg-white p-2 rounded border border-purple-200">
                      <GraduationCap className="h-4 w-4 text-purple-700 shrink-0" />
                      <div>
                        <span>Siswa Terpilih: </span>
                        <strong>{selectedStudent.full_name}</strong>
                        <span className="text-[11px] text-slate-500 ml-1">({selectedStudent.nis})</span>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                      <div>
                        <label className="text-[11px] font-medium text-slate-700 block mb-1">
                          Hubungan Keluarga
                        </label>
                        <select
                          value={relationship}
                          onChange={(e) => setRelationship(e.target.value as any)}
                          className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-xs text-slate-800"
                        >
                          <option value="ayah">Ayah</option>
                          <option value="ibu">Ibu</option>
                          <option value="wali">Wali</option>
                        </select>
                      </div>

                      <div className="flex items-center pt-5">
                        <label className="flex items-center gap-2 cursor-pointer select-none text-xs text-slate-700">
                          <input
                            type="checkbox"
                            checked={isPrimary}
                            onChange={(e) => setIsPrimary(e.target.checked)}
                            className="rounded border-slate-300 text-purple-600 focus:ring-purple-500 h-3.5 w-3.5"
                          />
                          <span>Kontak Utama Siswa</span>
                        </label>
                      </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-1">
                      <Button
                        type="button"
                        size="sm"
                        disabled={attachMutation.isPending}
                        onClick={() => attachMutation.mutate()}
                        className="h-7 text-xs bg-purple-700 hover:bg-purple-800 text-white gap-1"
                      >
                        <Check className="h-3 w-3" />
                        <span>{attachMutation.isPending ? 'Menyimpan...' : 'Simpan Hubungan'}</span>
                      </Button>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* List Siswa Terhubung */}
            {linkedStudents.length > 0 ? (
              <div className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                {linkedStudents.map((st) => (
                  <div key={st.id} className="p-3 flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-slate-900">{st.full_name}</span>
                        {st.pivot?.is_primary && (
                          <Badge variant="warning" className="text-[10px] gap-1 py-0 px-1.5">
                            <Star className="h-2.5 w-2.5 fill-amber-500 text-amber-500" />
                            <span>Kontak Utama</span>
                          </Badge>
                        )}
                      </div>
                      <span className="text-[11px] text-slate-400 font-mono block mt-0.5">
                        NIS: {st.nis} &bull; NISN: {st.nisn}
                      </span>
                    </div>

                    <div className="flex items-center gap-2">
                      <Badge variant="info" className="text-[10px] capitalize">
                        {st.pivot?.relationship || 'Wali'}
                      </Badge>

                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={detachMutation.isPending}
                        onClick={() => {
                          setStudentToDetach({ id: st.id, name: st.full_name });
                        }}
                        className="h-7 w-7 p-0 text-slate-400 hover:text-status-danger"
                        title="Putuskan Hubungan"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-slate-400 italic p-3 rounded bg-slate-50 border border-slate-100">
                Belum ada siswa yang terhubung dengan data orang tua/wali ini.
              </p>
            )}
          </div>
        </div>

        {/* Footer Dialog */}
        <div className="flex items-center justify-end pt-4 border-t border-slate-100 mt-5">
          <Button type="button" variant="outline" size="sm" onClick={onClose}>
            Tutup
          </Button>
        </div>
      </div>

      {/* Dialog Konfirmasi Putuskan Hubungan Perwalian */}
      <ConfirmDialog
        isOpen={!!studentToDetach}
        onClose={() => setStudentToDetach(null)}
        onConfirm={async () => {
          if (!studentToDetach) return;
          detachMutation.mutate(studentToDetach.id);
          setStudentToDetach(null);
        }}
        title="Putuskan Hubungan Perwalian"
        description="Apakah Anda yakin ingin memutuskan hubungan perwalian dengan siswa ini?"
        affectedItemName={studentToDetach?.name}
        confirmLabel="Ya, Putuskan"
        cancelLabel="Batal"
        variant="danger"
        isLoading={detachMutation.isPending}
      />
    </div>
  );
}
