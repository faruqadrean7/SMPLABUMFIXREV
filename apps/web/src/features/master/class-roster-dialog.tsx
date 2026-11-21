'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { SchoolClassItem, ClassStudentItem, StudentItem } from '@/types/master';
import { classesApi } from '@/features/master/classes-api';
import { studentsApi } from '@/features/master/students-api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import {
  X,
  Users,
  UserMinus,
  UserPlus,
  AlertTriangle,
  Search,
  Check,
  CheckSquare,
  Square,
  ArrowLeft,
  UserCheck,
} from 'lucide-react';

export interface ClassRosterDialogProps {
  isOpen: boolean;
  onClose: () => void;
  selectedClass: SchoolClassItem | null;
}

export function ClassRosterDialog({ isOpen, onClose, selectedClass }: ClassRosterDialogProps) {
  const queryClient = useQueryClient();

  // Mode: 'roster' (lihat daftar siswa di kelas) atau 'add' (tambah siswa baru ke kelas)
  const [activeTab, setActiveTab] = useState<'roster' | 'add'>('roster');
  const [searchCandidate, setSearchCandidate] = useState('');
  const [selectedStudentIds, setSelectedStudentIds] = useState<string[]>([]);
  const [addError, setAddError] = useState<string | null>(null);
  const [studentToRemove, setStudentToRemove] = useState<{ id: string; name: string } | null>(null);

  // 1. Query Anggota Kelas Eksisting
  const {
    data: students,
    isLoading: isLoadingStudents,
    error: rosterError,
    refetch: refetchStudents,
  } = useQuery({
    queryKey: ['class-students', selectedClass?.id],
    queryFn: () => (selectedClass ? classesApi.listStudents(selectedClass.id) : []),
    enabled: isOpen && !!selectedClass,
  });

  // 2. Query Siswa Aktif yang Belum Memiliki Rombel (Kandidat untuk Ditambahkan)
  const {
    data: candidatesData,
    isLoading: isLoadingCandidates,
    refetch: refetchCandidates,
  } = useQuery({
    queryKey: [
      'unassigned-students',
      selectedClass?.academic_year_id,
      searchCandidate,
    ],
    queryFn: () =>
      studentsApi.list({
        limit: 50,
        status: 'aktif',
        q: searchCandidate || undefined,
        without_class: true,
        academic_year_id: selectedClass?.academic_year_id,
      }),
    enabled: isOpen && !!selectedClass && activeTab === 'add',
  });

  // Mutasi: Keluarkan Siswa dari Kelas
  const removeMutation = useMutation({
    mutationFn: (studentId: string) => {
      if (!selectedClass) throw new Error('No class selected');
      return classesApi.removeStudent(selectedClass.id, studentId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['class-students', selectedClass?.id] });
      queryClient.invalidateQueries({ queryKey: ['classes'] });
      queryClient.invalidateQueries({ queryKey: ['unassigned-students'] });
    },
  });

  // Mutasi: Tambahkan Siswa ke Kelas
  const addMutation = useMutation({
    mutationFn: (studentIds: string[]) => {
      if (!selectedClass) throw new Error('No class selected');
      return classesApi.addStudents(selectedClass.id, studentIds);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['class-students', selectedClass?.id] });
      queryClient.invalidateQueries({ queryKey: ['classes'] });
      queryClient.invalidateQueries({ queryKey: ['unassigned-students'] });
      setSelectedStudentIds([]);
      setActiveTab('roster');
      setAddError(null);
    },
    onError: (err: any) => {
      setAddError(err.message || 'Gagal menambahkan siswa ke rombel.');
    },
  });

  if (!isOpen || !selectedClass) return null;

  const currentCount = students?.length || 0;
  const availableSlots = Math.max(0, selectedClass.capacity - currentCount);
  const isFull = currentCount >= selectedClass.capacity;
  const isAtStandardLimit = currentCount >= 36;
  const candidateList: StudentItem[] = candidatesData?.data || [];

  const handleToggleSelect = (studentId: string) => {
    if (selectedStudentIds.includes(studentId)) {
      setSelectedStudentIds(selectedStudentIds.filter((id) => id !== studentId));
    } else {
      if (selectedStudentIds.length >= availableSlots) {
        setAddError(`Sisa kuota rombel hanya ${availableSlots} siswa.`);
        return;
      }
      setAddError(null);
      setSelectedStudentIds([...selectedStudentIds, studentId]);
    }
  };

  const handleSelectAllCandidates = () => {
    if (selectedStudentIds.length === candidateList.length) {
      setSelectedStudentIds([]);
    } else {
      const allowedCount = Math.min(candidateList.length, availableSlots);
      setSelectedStudentIds(candidateList.slice(0, allowedCount).map((s) => s.id));
      if (candidateList.length > availableSlots) {
        setAddError(`Hanya dapat memilih maksimal ${availableSlots} siswa sesuai sisa kuota.`);
      }
    }
  };

  const handleSubmitAddStudents = () => {
    if (selectedStudentIds.length === 0) return;
    setAddError(null);
    addMutation.mutate(selectedStudentIds);
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-elevated border border-slate-200 my-8">
        {/* Header Dialog */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2.5">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50 text-brand-teal">
              <Users className="h-5 w-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base font-bold text-slate-900">
                  Roster Anggota Kelas {selectedClass.name}
                </h2>
                <Badge
                  variant={isFull ? 'danger' : isAtStandardLimit ? 'warning' : 'info'}
                  className="text-[10px]"
                >
                  {currentCount} / {selectedClass.capacity} Siswa
                </Badge>
              </div>
              <p className="text-xs text-slate-500">
                Wali Kelas:{' '}
                <span className="font-semibold text-slate-700">
                  {selectedClass.homeroomTeacher?.full_name || 'Belum Ditentukan'}
                </span>
                {' • '}
                Sisa Kuota:{' '}
                <span className={`font-semibold ${availableSlots === 0 ? 'text-status-danger' : 'text-emerald-600'}`}>
                  {availableSlots} Kursi
                </span>
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

        {/* Tab Navigasi Aksi */}
        <div className="flex items-center justify-between mt-4 pb-2 border-b border-slate-100">
          <div className="flex items-center gap-1.5">
            <Button
              type="button"
              variant={activeTab === 'roster' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => {
                setActiveTab('roster');
                setAddError(null);
              }}
              className={`h-8 text-xs ${
                activeTab === 'roster'
                  ? 'bg-brand-blue text-white'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Users className="h-3.5 w-3.5 mr-1.5" />
              Anggota Saat Ini ({currentCount})
            </Button>

            <Button
              type="button"
              variant={activeTab === 'add' ? 'default' : 'outline'}
              size="sm"
              disabled={isFull}
              onClick={() => {
                setActiveTab('add');
                setAddError(null);
              }}
              className={`h-8 text-xs ${
                activeTab === 'add'
                  ? 'bg-brand-teal text-white'
                  : isFull
                  ? 'opacity-50 cursor-not-allowed'
                  : 'border-slate-300 text-brand-teal hover:bg-teal-50'
              }`}
            >
              <UserPlus className="h-3.5 w-3.5 mr-1.5" />
              + Tambah Siswa ke Rombel
            </Button>
          </div>

          {isFull && (
            <span className="text-[11px] font-medium text-status-danger">
              Kelas Penuh
            </span>
          )}
        </div>

        {/* Warning jika kapasitas mencapai 36 */}
        {isAtStandardLimit && (
          <div className="mt-3 flex items-center gap-2 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">
            <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600" />
            <span>
              Kelas ini telah mencapai atau melebihi batas standar maksimal rombel Labschool UM (36 siswa).
            </span>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 1: LIST ANGGOTA EKSISTING */}
        {/* ========================================================================= */}
        {activeTab === 'roster' && (
          <div className="mt-3 max-h-96 overflow-y-auto">
            {isLoadingStudents ? (
              <div className="p-8 text-center text-xs text-slate-400 animate-pulse">
                Memuat data anggota rombel...
              </div>
            ) : rosterError ? (
              <div className="p-4 text-center text-xs text-status-danger">
                Gagal memuat anggota rombel.{' '}
                <button onClick={() => refetchStudents()} className="underline font-semibold">
                  Coba lagi
                </button>
              </div>
            ) : students && students.length > 0 ? (
              <div className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                {students.map((st, index) => (
                  <div
                    key={st.id}
                    className="p-3 flex items-center justify-between hover:bg-slate-50 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <span className="w-5 text-center text-xs font-mono text-slate-400">
                        {index + 1}
                      </span>
                      <div>
                        <span className="font-semibold text-slate-900 text-xs">{st.full_name}</span>
                        <p className="text-[11px] text-slate-400 font-mono">
                          NIS: {st.nis} • NISN: {st.nisn} • {st.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                        </p>
                      </div>
                    </div>

                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-8 text-xs text-slate-400 hover:text-status-danger hover:bg-red-50 gap-1"
                      title="Keluarkan siswa dari rombel"
                      disabled={removeMutation.isPending}
                      onClick={() => {
                        setStudentToRemove({ id: st.id, name: st.full_name });
                      }}
                    >
                      <UserMinus className="h-3.5 w-3.5" />
                      <span className="text-[11px]">Keluarkan</span>
                    </Button>
                  </div>
                ))}
              </div>
            ) : (
              <div className="p-8 text-center text-xs text-slate-500 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                <p className="font-medium text-slate-700">Belum Ada Siswa di Rombel Ini</p>
                <p className="text-[11px] text-slate-400 mt-1">
                  Klik tombol <strong>&quot;+ Tambah Siswa ke Rombel&quot;</strong> di atas untuk menempatkan siswa aktif ke kelas ini.
                </p>
              </div>
            )}
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 2: TAMBAH SISWA BARU KE ROMBEL */}
        {/* ========================================================================= */}
        {activeTab === 'add' && (
          <div className="mt-3 space-y-3">
            {addError && (
              <div className="p-2.5 rounded-lg bg-red-50 border border-red-200 text-xs text-status-danger flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 shrink-0" />
                <span>{addError}</span>
              </div>
            )}

            {/* Input Pencarian Siswa */}
            <div className="relative">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
              <Input
                placeholder="Cari berdasarkan Nama Siswa, NIS, atau NISN..."
                value={searchCandidate}
                onChange={(e) => setSearchCandidate(e.target.value)}
                className="pl-9 h-9 text-xs"
              />
            </div>

            {/* Toolbar Pemilihan */}
            <div className="flex items-center justify-between text-xs text-slate-600 px-1">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={handleSelectAllCandidates}
                  disabled={candidateList.length === 0}
                  className="flex items-center gap-1.5 font-medium hover:text-brand-blue disabled:opacity-40"
                >
                  {selectedStudentIds.length > 0 && selectedStudentIds.length === candidateList.length ? (
                    <CheckSquare className="h-4 w-4 text-brand-blue" />
                  ) : (
                    <Square className="h-4 w-4 text-slate-400" />
                  )}
                  <span>Pilih Semua yang Tampil</span>
                </button>
              </div>

              <span>
                Terpilih: <strong>{selectedStudentIds.length}</strong> dari maksimal {availableSlots} kursi
              </span>
            </div>

            {/* List Kandidat Siswa */}
            <div className="max-h-72 overflow-y-auto divide-y divide-slate-100 rounded-lg border border-slate-200">
              {isLoadingCandidates ? (
                <div className="p-8 text-center text-xs text-slate-400 animate-pulse">
                  Mencari siswa aktif yang belum memiliki rombel...
                </div>
              ) : candidateList.length > 0 ? (
                candidateList.map((cand) => {
                  const isSelected = selectedStudentIds.includes(cand.id);
                  return (
                    <div
                      key={cand.id}
                      onClick={() => handleToggleSelect(cand.id)}
                      className={`p-2.5 flex items-center justify-between cursor-pointer transition-colors ${
                        isSelected ? 'bg-teal-50/60' : 'hover:bg-slate-50'
                      }`}
                    >
                      <div className="flex items-center gap-3">
                        <div
                          className={`flex h-4 w-4 items-center justify-center rounded border transition-colors ${
                            isSelected
                              ? 'bg-brand-teal border-brand-teal text-white'
                              : 'border-slate-300 bg-white'
                          }`}
                        >
                          {isSelected && <Check className="h-3 w-3 stroke-[3]" />}
                        </div>
                        <div>
                          <span className="font-semibold text-slate-900 text-xs">
                            {cand.full_name}
                          </span>
                          <p className="text-[11px] text-slate-400 font-mono">
                            NIS: {cand.nis} • NISN: {cand.nisn} • {cand.gender === 'L' ? 'L' : 'P'}
                          </p>
                        </div>
                      </div>

                      <Badge variant="outline" className="text-[10px] bg-slate-50 text-slate-600">
                        {cand.status}
                      </Badge>
                    </div>
                  );
                })
              ) : (
                <div className="p-8 text-center text-xs text-slate-400 italic">
                  Tidak ditemukan siswa aktif tanpa rombel{' '}
                  {searchCandidate ? `dengan kata kunci "${searchCandidate}"` : ''}.
                </div>
              )}
            </div>

            {/* Tombol Simpan Penambahan */}
            <div className="flex items-center justify-between pt-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setActiveTab('roster')}
                className="h-8 text-xs text-slate-600 hover:text-slate-900"
              >
                <ArrowLeft className="h-3.5 w-3.5 mr-1.5" />
                Kembali ke Roster
              </Button>

              <Button
                type="button"
                size="sm"
                disabled={selectedStudentIds.length === 0 || addMutation.isPending}
                onClick={handleSubmitAddStudents}
                className="h-8 text-xs bg-brand-teal hover:bg-teal-700 text-white gap-1.5 shadow-sm"
              >
                <UserCheck className="h-3.5 w-3.5" />
                <span>
                  {addMutation.isPending
                    ? 'Menambahkan...'
                    : `Masukkan ${selectedStudentIds.length} Siswa Terpilih`}
                </span>
              </Button>
            </div>
          </div>
        )}

        {/* Footer Dialog */}
        <div className="flex items-center justify-end pt-4 border-t border-slate-100 mt-4">
          <Button type="button" variant="outline" size="sm" onClick={onClose}>
            Tutup
          </Button>
        </div>
      </div>

      {/* Dialog Konfirmasi Keluarkan Siswa dari Rombel */}
      <ConfirmDialog
        isOpen={!!studentToRemove}
        onClose={() => setStudentToRemove(null)}
        onConfirm={async () => {
          if (!studentToRemove) return;
          removeMutation.mutate(studentToRemove.id);
          setStudentToRemove(null);
        }}
        title="Keluarkan Siswa dari Rombel"
        description={`Apakah Anda yakin ingin mengeluarkan siswa dari rombel ${selectedClass?.name}?`}
        affectedItemName={studentToRemove?.name}
        confirmLabel="Ya, Keluarkan"
        cancelLabel="Batal"
        variant="danger"
        isLoading={removeMutation.isPending}
      />
    </div>
  );
}
