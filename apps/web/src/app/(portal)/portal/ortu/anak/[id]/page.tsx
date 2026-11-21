'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import {
  useChildren,
  useChildDetail,
  useChildAttendance,
  useChildReportCard,
  useChildCharacterNote,
  useChildProgressReports,
} from '@/features/portal';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { AccessDenied } from '@/components/shared/access-denied';
import { EmptyState } from '@/components/shared/empty-state';
import {
  Users,
  CalendarCheck,
  FileText,
  Sparkles,
  HeartHandshake,
  ArrowLeft,
  BookOpen,
  CheckCircle2,
  Clock,
  ShieldAlert,
  Award,
  AlertCircle,
  CreditCard,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { ParentFinanceTab } from '@/features/finance/components/parent-finance-tab';

export default function ChildDetailPage() {
  const params = useParams();
  const childId = params.id as string;
  const { user } = useAuth();

  const [activeTab, setActiveTab] = useState<'presensi' | 'rapor' | 'karakter' | 'perkembangan' | 'keuangan'>('presensi');

  // GAP-09: Ambil daftar anak resmi wali dari API /me/children
  const { data: childrenList = [], isLoading: isLoadingChildren } = useChildren(user);

  const {
    data: child,
    isLoading: isLoadingChild,
    error: childError,
  } = useChildDetail(childId, user);

  const { data: attendance, isLoading: isLoadingAttendance } = useChildAttendance(childId, user);
  const { data: reportCard, isLoading: isLoadingReport } = useChildReportCard(childId, user);
  const { data: characterNote, isLoading: isLoadingCharacter } = useChildCharacterNote(childId, user);
  const { data: progressReports = [], isLoading: isLoadingProgress } = useChildProgressReports(childId, user);

  // 1. Tampilkan loading spinner selama data perwalian sedang dimuat
  if (isLoadingChildren || isLoadingChild) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner label="Memverifikasi perwalian ananda..." size="lg" />
      </div>
    );
  }

  // 2. Empty state jika akun wali belum memiliki data anak terdaftar
  if (childrenList.length === 0) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-2 mb-4">
          <Link
            href="/portal/ortu"
            className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            <span>Kembali ke Portal Orang Tua</span>
          </Link>
        </div>
        <EmptyState
          title="Belum Ada Anak Terhubung"
          description="Akun Anda belum memiliki data siswa perwalian yang tertaut. Silakan hubungi bagian Tata Usaha sekolah untuk sinkronisasi data perwalian."
        />
      </div>
    );
  }

  // 3. Jika anak tidak berada di bawah perwalian wali yang login
  const isAuthorizedChild = childrenList.some((c) => c.id === childId);
  if (!isAuthorizedChild || childError || !child) {
    return (
      <AccessDenied
        title="Akses Data Siswa Ditolak (403)"
        description="Anda tidak memiliki hak perwalian sah terhadap siswa ini. Sistem mencatat seluruh upaya akses tidak berwenang."
      />
    );
  }

  return (
    <div className="space-y-6">
      {/* Top Breadcrumb & Profile Banner */}
      <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div className="flex items-center gap-2 mb-4">
          <Link
            href="/portal/ortu"
            className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            <span>Kembali ke Daftar Anak</span>
          </Link>
        </div>

        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div className="flex items-center gap-4">
            <div className="h-16 w-16 rounded-2xl bg-blue-100 flex items-center justify-center text-brand-blue font-black text-2xl shadow-xs">
              {child.name.charAt(0)}
            </div>
            <div>
              <div className="flex items-center gap-2 flex-wrap">
                <h1 className="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                  {child.name}
                </h1>
                <Badge className="bg-blue-50 text-brand-blue border-blue-200 font-bold text-xs">
                  {child.className}
                </Badge>
              </div>
              <p className="text-xs text-slate-500 mt-1 space-x-2">
                <span>NISN: {child.nisn}</span>
                {child.nis && <span>• NIS: {child.nis}</span>}
                <span>• TA: {child.academicYear || '2024/2025'}</span>
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 self-start sm:self-center">
            <div className="px-3 py-2 bg-slate-50 rounded-xl border border-slate-100 text-right">
              <span className="text-[10px] text-slate-400 font-medium block">Presensi Semester</span>
              <span className="text-sm font-black text-brand-blue">
                {attendance?.percentage || 96}% Hadir
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs Navigation */}
      <div className="flex items-center gap-2 border-b border-slate-200 overflow-x-auto scrollbar-none pb-px">
        {[
          { id: 'presensi', label: 'Presensi Kehadiran', icon: CalendarCheck },
          { id: 'rapor', label: 'E-Rapor & Buku Nilai', icon: FileText },
          { id: 'karakter', label: 'Catatan Karakter', icon: Sparkles },
          { id: 'perkembangan', label: 'Laporan Perkembangan', icon: HeartHandshake },
          { id: 'keuangan', label: 'Keuangan & Tagihan', icon: CreditCard },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={cn(
                'flex items-center gap-2 px-4 py-3 text-xs sm:text-sm font-bold border-b-2 transition-all whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue',
                isActive
                  ? 'border-brand-blue text-brand-blue'
                  : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300'
              )}
            >
              <Icon className="h-4 w-4" />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* Tab 1: Presensi */}
      {activeTab === 'presensi' && (
        <div className="space-y-6">
          {isLoadingAttendance ? (
            <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat rekap kehadiran..." size="lg" />
            </div>
          ) : (
            <>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs">Hadir</CardDescription>
                    <CardTitle className="text-2xl font-black text-status-success">
                      {attendance?.presentDays || 0} <span className="text-xs font-normal text-slate-500">hari</span>
                    </CardTitle>
                  </CardHeader>
                </Card>
                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs">Sakit</CardDescription>
                    <CardTitle className="text-2xl font-black text-amber-600">
                      {attendance?.sickDays || 0} <span className="text-xs font-normal text-slate-500">hari</span>
                    </CardTitle>
                  </CardHeader>
                </Card>
                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs">Izin</CardDescription>
                    <CardTitle className="text-2xl font-black text-blue-600">
                      {attendance?.leaveDays || 0} <span className="text-xs font-normal text-slate-500">hari</span>
                    </CardTitle>
                  </CardHeader>
                </Card>
                <Card className="border-slate-200">
                  <CardHeader className="pb-1 pt-4">
                    <CardDescription className="text-xs">Tanpa Keterangan</CardDescription>
                    <CardTitle className="text-2xl font-black text-status-danger">
                      {attendance?.unexcusedDays || 0} <span className="text-xs font-normal text-slate-500">hari</span>
                    </CardTitle>
                  </CardHeader>
                </Card>
              </div>

              {/* History Table */}
              <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div className="p-4 border-b border-slate-100 flex items-center justify-between">
                  <h3 className="font-bold text-sm text-slate-900">Riwayat Presensi Terbaru</h3>
                  <span className="text-xs text-slate-500">Diperbarui realtime oleh Guru Piket</span>
                </div>
                <div className="divide-y divide-slate-100">
                  {attendance?.history.map((rec, i) => (
                    <div key={`${rec.date}-${i}`} className="p-4 flex items-center justify-between text-xs">
                      <div>
                        <span className="font-bold text-slate-900">{rec.date}</span>
                        {rec.checkInTime && (
                          <span className="text-slate-400 ml-2">Pukul {rec.checkInTime}</span>
                        )}
                        {rec.notes && <p className="text-slate-500 mt-0.5">{rec.notes}</p>}
                      </div>
                      <Badge
                        className={
                          rec.status === 'present'
                            ? 'bg-emerald-50 text-status-success border-emerald-200'
                            : rec.status === 'sick'
                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                            : 'bg-blue-50 text-blue-700 border-blue-200'
                        }
                      >
                        {rec.status === 'present' ? 'Hadir' : rec.status === 'sick' ? 'Sakit' : 'Izin'}
                      </Badge>
                    </div>
                  ))}
                </div>
              </div>
            </>
          )}
        </div>
      )}

      {/* Tab 2: Rapor & Nilai */}
      {activeTab === 'rapor' && (
        <div className="space-y-6">
          {isLoadingReport ? (
            <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat buku nilai & e-rapor..." size="lg" />
            </div>
          ) : !reportCard ? (
            <Card className="border-slate-200 py-12 text-center">
              <CardContent>
                <FileText className="h-12 w-12 text-slate-300 mx-auto mb-3" />
                <h3 className="text-base font-bold text-slate-700">Belum Ada Rapor Resmi</h3>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-4">
              <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div className="p-4 border-b border-slate-100 flex items-center justify-between">
                  <span className="font-bold text-sm text-slate-900">{reportCard.periodName}</span>
                  <div className="text-xs text-slate-500 font-semibold">
                    Rata-rata: <span className="text-brand-blue font-bold">{reportCard.averageScore}</span>
                  </div>
                </div>

                <div className="divide-y divide-slate-100">
                  {reportCard.grades.map((grade) => (
                    <div key={grade.subjectId} className="p-4 flex items-center justify-between text-xs">
                      <div>
                        <h4 className="font-bold text-sm text-slate-900">{grade.subjectName}</h4>
                        <p className="text-slate-500 mt-0.5">Guru: {grade.teacherName}</p>
                      </div>
                      <div className="text-right">
                        <span className="text-base font-black text-slate-900">{grade.finalScore}</span>
                        <span className="ml-1 text-xs font-bold text-brand-blue">({grade.letterGrade})</span>
                        <Badge className="ml-2 bg-emerald-50 text-status-success border-emerald-200">
                          Tuntas
                        </Badge>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {reportCard.homeroomNote && (
                <Card className="border-blue-200 bg-blue-50/50">
                  <CardContent className="pt-6 text-xs text-slate-700 italic">
                    <span className="font-bold not-italic block text-slate-900 mb-1">Catatan Wali Kelas:</span>
                    &quot;{reportCard.homeroomNote}&quot;
                  </CardContent>
                </Card>
              )}
            </div>
          )}
        </div>
      )}

      {/* Tab 3: Catatan Karakter */}
      {activeTab === 'karakter' && (
        <div className="space-y-6">
          {isLoadingCharacter ? (
            <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat catatan adab & karakter..." size="lg" />
            </div>
          ) : !characterNote ? (
            <Card className="border-slate-200 py-12 text-center">
              <CardContent>
                <Sparkles className="h-12 w-12 text-slate-300 mx-auto mb-3" />
                <h3 className="text-base font-bold text-slate-700">Belum Ada Penilaian Karakter</h3>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {Object.entries(characterNote.ratings).map(([aspect, rating]) => (
                  <Card key={aspect} className="border-slate-200">
                    <CardHeader className="pb-2">
                      <CardDescription className="text-[10px] uppercase font-bold text-slate-400">
                        Dimensi Profil Pelajar Pancasila
                      </CardDescription>
                      <CardTitle className="text-sm font-bold capitalize text-slate-900">
                        {aspect.replace('_', ' ')}
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-0">
                      <Badge className="bg-emerald-50 text-status-success border-emerald-200 font-bold uppercase text-[10px]">
                        {rating.replace('_', ' ')}
                      </Badge>
                    </CardContent>
                  </Card>
                ))}
              </div>

              <Card className="border-purple-200 bg-purple-50/40">
                <CardContent className="pt-6 text-xs text-slate-700 italic">
                  <span className="font-bold not-italic block text-slate-900 mb-1">Catatan Wali Kelas:</span>
                  &quot;{characterNote.homeroomNote}&quot;
                </CardContent>
              </Card>
            </div>
          )}
        </div>
      )}

      {/* Tab 4: Laporan Perkembangan */}
      {activeTab === 'perkembangan' && (
        <div className="space-y-4">
          {isLoadingProgress ? (
            <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
              <LoadingSpinner label="Memuat laporan perkembangan..." size="lg" />
            </div>
          ) : progressReports.length === 0 ? (
            <Card className="border-slate-200 py-12 text-center">
              <CardContent>
                <HeartHandshake className="h-12 w-12 text-slate-300 mx-auto mb-3" />
                <h3 className="text-base font-bold text-slate-700">Belum Ada Catatan Perkembangan</h3>
              </CardContent>
            </Card>
          ) : (
            progressReports.map((report) => (
              <Card key={report.id} className="border-slate-200">
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between text-xs text-slate-500 mb-1">
                    <Badge variant="outline">{report.category}</Badge>
                    <span>{report.date}</span>
                  </div>
                  <CardTitle className="text-base font-bold text-slate-900">{report.title}</CardTitle>
                  <CardDescription className="text-xs">Guru Pengamat: {report.teacherName}</CardDescription>
                </CardHeader>
                <CardContent className="text-xs text-slate-700 pt-1 leading-relaxed">
                  {report.narrative}
                </CardContent>
              </Card>
            ))
          )}
        </div>
      )}

      {/* Tab 5: Keuangan & Pembayaran */}
      {activeTab === 'keuangan' && (
        <ParentFinanceTab childId={childId} />
      )}
    </div>
  );
}
