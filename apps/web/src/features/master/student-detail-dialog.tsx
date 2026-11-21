'use client';

import React from 'react';
import { StudentItem } from '@/types/master';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar } from '@/components/ui/avatar';
import { X, User, BookOpen, Users, Clock, MapPin, Calendar } from 'lucide-react';

export interface StudentDetailDialogProps {
  isOpen: boolean;
  onClose: () => void;
  student: StudentItem | null;
}

export function StudentDetailDialog({ isOpen, onClose, student }: StudentDetailDialogProps) {
  if (!isOpen || !student) return null;

  const statusBadgeMap: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'info' }> = {
    aktif: { label: 'Aktif', variant: 'success' },
    lulus: { label: 'Lulus', variant: 'info' },
    pindah: { label: 'Pindah', variant: 'warning' },
    keluar: { label: 'Keluar', variant: 'danger' },
  };

  const statusInfo = statusBadgeMap[student.status] || { label: student.status, variant: 'info' };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-elevated border border-slate-200 my-8">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-3">
            <Avatar
              src={student.photo_file?.url || (student as any).photo_url}
              name={student.full_name}
              size="lg"
              shape="rounded"
              alt={`Foto ${student.full_name}`}
              className="border border-slate-200 shadow-2xs"
            />
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base font-bold text-slate-900">{student.full_name}</h2>
                <Badge variant={statusInfo.variant} className="text-[10px] py-0 px-2 uppercase">
                  {statusInfo.label}
                </Badge>
              </div>
              <p className="text-xs text-slate-500">
                NIS: {student.nis} • NISN: {student.nisn}
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

        <div className="mt-4 space-y-5 text-xs text-slate-600">
          {/* Identitas Kependudukan & Pribadi */}
          <div>
            <h3 className="font-semibold text-slate-900 mb-2 flex items-center gap-1.5 text-xs">
              <User className="h-3.5 w-3.5 text-brand-blue" />
              <span>Identitas Pribadi</span>
            </h3>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 rounded-lg bg-slate-50 border border-slate-100">
              <div>
                <span className="text-[11px] text-slate-400 block">Jenis Kelamin</span>
                <span className="font-medium text-slate-800">
                  {student.gender === 'L' ? 'Laki-laki (L)' : 'Perempuan (P)'}
                </span>
              </div>
              <div>
                <span className="text-[11px] text-slate-400 block">Tempat, Tanggal Lahir</span>
                <span className="font-medium text-slate-800">
                  {student.birth_place},{' '}
                  {student.birth_date ? new Date(student.birth_date).toLocaleDateString('id-ID') : '-'}
                </span>
              </div>
              <div>
                <span className="text-[11px] text-slate-400 block">Agama</span>
                <span className="font-medium text-slate-800">{student.religion || '-'}</span>
              </div>
              <div>
                <span className="text-[11px] text-slate-400 block">Tanggal Masuk</span>
                <span className="font-medium text-slate-800">
                  {student.entry_date ? new Date(student.entry_date).toLocaleDateString('id-ID') : '-'}
                </span>
              </div>
              <div className="sm:col-span-2">
                <span className="text-[11px] text-slate-400 block">Alamat Domisili</span>
                <span className="font-medium text-slate-800">{student.address || '-'}</span>
              </div>
            </div>
          </div>

          {/* Rombel / Kelas Aktif */}
          <div>
            <h3 className="font-semibold text-slate-900 mb-2 flex items-center gap-1.5 text-xs">
              <BookOpen className="h-3.5 w-3.5 text-brand-teal" />
              <span>Rombongan Belajar (Kelas)</span>
            </h3>
            {student.classes && student.classes.length > 0 ? (
              <div className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                {student.classes.map((cls) => (
                  <div key={cls.id} className="p-3 flex items-center justify-between">
                    <div>
                      <span className="font-semibold text-slate-800">{cls.name}</span>
                      <span className="text-[11px] text-slate-400 block">
                        Terdaftar sejak:{' '}
                        {cls.pivot?.joined_at
                          ? new Date(cls.pivot.joined_at).toLocaleDateString('id-ID')
                          : '-'}
                      </span>
                    </div>
                    <Badge variant="success" className="text-[10px]">
                      Aktif di Kelas
                    </Badge>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-slate-400 italic p-3 rounded bg-slate-50 border border-slate-100">
                Siswa belum terdaftar pada rombel aktif mana pun.
              </p>
            )}
          </div>

          {/* Orang Tua / Wali */}
          <div>
            <h3 className="font-semibold text-slate-900 mb-2 flex items-center gap-1.5 text-xs">
              <Users className="h-3.5 w-3.5 text-purple-600" />
              <span>Data Orang Tua / Wali Terhubung</span>
            </h3>
            {student.guardians && student.guardians.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {student.guardians.map((g) => (
                  <div key={g.id} className="p-3 rounded-lg border border-slate-200 bg-slate-50">
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-slate-900">{g.full_name}</span>
                      <Badge variant="info" className="text-[10px] capitalize">
                        {g.pivot?.relationship || 'Wali'}
                      </Badge>
                    </div>
                    <p className="text-[11px] text-slate-500 mt-1">Telepon: {g.phone}</p>
                    {g.occupation && <p className="text-[11px] text-slate-400">Profesi: {g.occupation}</p>}
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-slate-400 italic p-3 rounded bg-slate-50 border border-slate-100">
                Belum ada data orang tua/wali yang terhubung.
              </p>
            )}
          </div>

          {/* Riwayat Mutasi Status */}
          {student.statusHistory && student.statusHistory.length > 0 && (
            <div>
              <h3 className="font-semibold text-slate-900 mb-2 flex items-center gap-1.5 text-xs">
                <Clock className="h-3.5 w-3.5 text-amber-600" />
                <span>Riwayat Perubahan Status</span>
              </h3>
              <div className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                {student.statusHistory.map((h) => (
                  <div key={h.id} className="p-2.5 flex items-center justify-between">
                    <div>
                      <span className="font-medium text-slate-800 capitalize">
                        {h.previous_status ? `${h.previous_status} → ` : ''}
                        {h.new_status}
                      </span>
                      {h.reason && <p className="text-[11px] text-slate-400">Alasan: {h.reason}</p>}
                    </div>
                    <span className="text-[11px] text-slate-500">
                      {new Date(h.effective_date).toLocaleDateString('id-ID')}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        <div className="flex items-center justify-end pt-4 border-t border-slate-100 mt-5">
          <Button type="button" variant="outline" size="sm" onClick={onClose}>
            Tutup
          </Button>
        </div>
      </div>
    </div>
  );
}
