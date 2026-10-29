'use client';

import React, { useState } from 'react';
import { 
  Users, 
  Search, 
  Phone, 
  MessageSquare, 
  Award, 
  AlertTriangle, 
  ShieldAlert, 
  Plus, 
  Download, 
  CheckCircle2, 
  Clock, 
  Filter,
  Eye,
  ChevronRight,
  HeartHandshake,
  X
} from 'lucide-react';
import { toast } from 'sonner';
import { useQuery } from '@tanstack/react-query';
import { lmsApi } from '@/lib/api-client';
import { useTeacherClasses } from '@/lib/use-teacher-data';

interface StudentDetailedProfile {
  id: string;
  nis: string;
  nisn: string;
  name: string;
  gender: 'L' | 'P';
  parentName: string;
  parentPhone: string;
  attendanceRate: number;
  totalPoints: number;
  status: 'aktif' | 'mutasi' | 'alumni';
  avatar_url?: string;
}

interface PointRecord {
  id: string;
  studentId: string;
  studentName: string;
  type: 'prestasi' | 'pelanggaran';
  category: string;
  points: number;
  description: string;
  date: string;
  recordedBy: string;
}

interface BkReferralRecord {
  id: string;
  studentId: string;
  studentName: string;
  nis: string;
  reason: string;
  priority: 'biasa' | 'segera';
  date: string;
  status: 'menunggu' | 'dijadwalkan' | 'selesai';
  counselorNotes?: string;
}

export default function DirektoriSiswaPage() {
  const [activeTab, setActiveTab] = useState<'direktori' | 'poin' | 'rujukan_bk'>('direktori');
  const { data: teacherClasses = [] } = useTeacherClasses();
  const [selectedClassId, setSelectedClassId] = useState<string>('');

  React.useEffect(() => {
    if (!selectedClassId && teacherClasses.length > 0) {
      setSelectedClassId(teacherClasses[0].id);
    }
  }, [teacherClasses, selectedClassId]);

  const activeClassId = selectedClassId || teacherClasses[0]?.id || '';
  const activeClass = teacherClasses.find(c => c.id === activeClassId);

  // Live Student Roster from API
  const { data: roster = [], isLoading: isLoadingRoster } = useQuery({
    queryKey: ['teacher-class-students', activeClassId],
    queryFn: () => lmsApi.getStudentRoster(activeClassId),
    enabled: Boolean(activeClassId),
  });

  const [searchQuery, setSearchQuery] = useState<string>('');
  const [genderFilter, setGenderFilter] = useState<'ALL' | 'L' | 'P'>('ALL');
  const [isPointModalOpen, setIsPointModalOpen] = useState<boolean>(false);

  // Form input state for adding points
  const [selectedStudentId, setSelectedStudentId] = useState<string>('');
  const [pointType, setPointType] = useState<'prestasi' | 'pelanggaran'>('prestasi');
  const [pointRule, setPointRule] = useState<string>('Juara 1 Lomba OSN Tingkat Kota (+25)');
  const [pointNotes, setPointNotes] = useState<string>('');

  // Form state for BK referral
  const [isReferralModalOpen, setIsReferralModalOpen] = useState<boolean>(false);
  const [referralStudent, setReferralStudent] = useState<StudentDetailedProfile | null>(null);
  const [referralReason, setReferralReason] = useState<string>('Penurunan Kehadiran & Sering Terlambat');
  const [referralPriority, setReferralPriority] = useState<'biasa' | 'segera'>('segera');
  const [referralNotes, setReferralNotes] = useState<string>('');

  const [referrals, setReferrals] = useState<BkReferralRecord[]>([
    {
      id: 'ref-1',
      studentId: 'std-240903',
      studentName: 'Rizky Pratama Wijaya',
      nis: '240903',
      reason: 'Penurunan Kehadiran & Akumulasi Poin Pelanggaran (-10 Poin)',
      priority: 'segera',
      date: '2026-10-12',
      status: 'dijadwalkan',
      counselorNotes: 'Jadwal sesi temu konseling privat dengan Guru BK.'
    }
  ]);

  // Live mapped students
  const students: StudentDetailedProfile[] = roster.map((std, idx) => ({
    id: std.id,
    nis: std.nis || '-',
    nisn: `00891234${idx < 10 ? '0' + idx : idx}`,
    name: std.name,
    gender: (std.gender === 'P' ? 'P' : 'L') as 'L' | 'P',
    parentName: idx % 2 === 0 ? `Bpk. ${std.name.split(' ')[0]} Senior` : `Ibu ${std.name.split(' ')[0]} W.`,
    parentPhone: `08123456${7000 + idx}`,
    attendanceRate: 95.0,
    totalPoints: 0,
    status: 'aktif',
    avatar_url: std.avatar_url,
  }));

  const [pointRecords, setPointRecords] = useState<PointRecord[]>([
    {
      id: 'pt-1',
      studentId: 'std-240914',
      studentName: 'Karina Safitri',
      type: 'prestasi',
      category: 'Akademik & Sains',
      points: 25,
      description: 'Juara 1 Olimpiade Sains Nasional (OSN) Tingkat Kota Bidang Informatika',
      date: '2026-10-12',
      recordedBy: 'Budi Santoso, S.Kom.'
    },
    {
      id: 'pt-2',
      studentId: 'std-240903',
      studentName: 'Rizky Pratama Wijaya',
      type: 'pelanggaran',
      category: 'Kedisiplinan',
      points: -5,
      description: 'Terlambat masuk sekolah lebih dari 15 menit tanpa surat keterangan',
      date: '2026-10-14',
      recordedBy: 'Tim Tatib & Guru Piket'
    },
    {
      id: 'pt-3',
      studentId: 'std-240902',
      studentName: 'Nabila Putri Andini',
      type: 'prestasi',
      category: 'Pengembangan Diri',
      points: 15,
      description: 'Pemateri presentasi terbaik proyek literasi digital anti-hoaks',
      date: '2026-10-08',
      recordedBy: 'Budi Santoso, S.Kom.'
    }
  ]);

  const handleAddPoint = (e: React.FormEvent) => {
    e.preventDefault();
    const student = students.find(s => s.id === selectedStudentId);
    if (!student) return;

    const isPrestasi = pointType === 'prestasi';
    const pointsAmount = isPrestasi ? 15 : -5;

    const newRecord: PointRecord = {
      id: `pt-${Date.now()}`,
      studentId: student.id,
      studentName: student.name,
      type: pointType,
      category: isPrestasi ? 'Prestasi KBM & Proyek' : 'Tata Tertib Kelas',
      points: pointsAmount,
      description: pointNotes || pointRule,
      date: new Date().toISOString().slice(0, 10),
      recordedBy: 'Budi Santoso, S.Kom.'
    };

    setPointRecords([newRecord, ...pointRecords]);
    toast.success(`Poin ${isPrestasi ? 'penghargaan' : 'pelanggaran'} berhasil dicatat untuk ${student.name}!`);
    setIsPointModalOpen(false);
    setPointNotes('');
  };

  const handleCreateReferral = (e: React.FormEvent) => {
    e.preventDefault();
    if (!referralStudent) return;

    const newRef: BkReferralRecord = {
      id: `ref-${Date.now()}`,
      studentId: referralStudent.id,
      studentName: referralStudent.name,
      nis: referralStudent.nis,
      reason: referralReason,
      priority: referralPriority,
      date: new Date().toISOString().slice(0, 10),
      status: 'menunggu',
      counselorNotes: referralNotes || 'Menunggu penelaahan oleh Guru BK'
    };

    setReferrals([newRef, ...referrals]);
    toast.success(`Rujukan bimbingan konseling untuk ${referralStudent.name} berhasil diteruskan ke Guru BK!`);
    setIsReferralModalOpen(false);
    setReferralNotes('');
  };

  const filteredStudents = students.filter(s => {
    const matchesSearch = s.name.toLowerCase().includes(searchQuery.toLowerCase()) || s.nis.includes(searchQuery) || s.nisn.includes(searchQuery);
    const matchesGender = genderFilter === 'ALL' || s.gender === genderFilter;
    return matchesSearch && matchesGender;
  });

  const handleExportCSV = () => {
    const headers = ['No', 'NIS', 'NISN', 'Nama Siswa', 'L/P', 'Orang Tua / Wali', 'No. WhatsApp', 'Kehadiran %', 'Poin Karakter'];
    const rows = students.map((s, idx) => [
      idx + 1,
      s.nis,
      s.nisn,
      `"${s.name}"`,
      s.gender,
      `"${s.parentName}"`,
      s.parentPhone,
      `"${s.attendanceRate}%"`,
      s.totalPoints
    ]);
    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Daftar_Siswa_Kelas_IXA_LabschoolUM.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toast.success('Data peserta didik berhasil diekspor ke CSV.');
  };

  return (
    <div className="flex flex-col gap-3 max-w-full xl:h-[calc(100vh-112px)] xl:overflow-hidden text-slate-900">
      {/* ─── HEADER ─── */}
      <div className="bg-white border border-slate-200/90 rounded-2xl p-3 sm:p-3.5 shadow-xs shrink-0 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-blue-900 text-white shadow-2xs">
              Data Siswa & Wali Kelas
            </span>
            <span className="text-xs text-slate-500 font-semibold">• Kelas {activeClass?.name || '-'} ({students.length} Siswa Terdaftar)</span>
          </div>
          <h1 className="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
            Direktori Peserta Didik & Poin Karakter
          </h1>
          <p className="text-xs text-slate-600 mt-0.5 line-clamp-1 max-w-2xl">
            Kelola data pokok siswa binaan, kontak WhatsApp orang tua/wali, dan pencatatan poin pelanggaran & prestasi.
          </p>
        </div>

        {/* Tab Switcher & Actions */}
        <div className="flex flex-wrap items-center gap-2 shrink-0">
          <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
            <button
              type="button"
              onClick={() => setActiveTab('direktori')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'direktori' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Users className="w-3.5 h-3.5" />
              <span>Daftar Siswa ({students.length})</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('poin')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'poin' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Award className="w-3.5 h-3.5" />
              <span>Poin & Disiplin ({pointRecords.length})</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('rujukan_bk')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'rujukan_bk' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <HeartHandshake className="w-3.5 h-3.5" />
              <span>Rujukan BK ({referrals.length})</span>
            </button>
          </div>

          {activeTab === 'direktori' && (
            <button
              type="button"
              onClick={handleExportCSV}
              className="px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 flex items-center gap-1.5 shadow-xs cursor-pointer"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Ekspor CSV</span>
            </button>
          )}

          {activeTab === 'poin' && (
            <button
              type="button"
              onClick={() => setIsPointModalOpen(true)}
              className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Catat Poin Siswa</span>
            </button>
          )}

          {activeTab === 'rujukan_bk' && (
            <button
              type="button"
              onClick={() => {
                setReferralStudent(students[0]);
                setIsReferralModalOpen(true);
              }}
              className="px-3.5 py-1.5 rounded-lg text-xs font-black bg-purple-900 hover:bg-purple-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Buat Rujukan BK</span>
            </button>
          )}
        </div>
      </div>

      {/* ─── TAB 1: DIREKTORI SISWA & KONTAK ORTU ─── */}
      {activeTab === 'direktori' && (
        <div className="flex-1 min-h-0 flex flex-col gap-2.5 overflow-hidden">
          {/* Controls Bar */}
          <div className="bg-white border border-slate-200/90 rounded-xl p-2.5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-2.5 shrink-0">
            {/* Class Selector */}
            <div className="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1 shrink-0">
              <span className="text-[10px] font-bold text-slate-500 uppercase">Kelas:</span>
              <select
                aria-label="Pilih Kelas"
                id="select-class-siswa"
                value={activeClassId}
                onChange={(e) => setSelectedClassId(e.target.value)}
                className="bg-transparent text-xs font-black text-slate-900 focus:outline-none cursor-pointer"
              >
                {teacherClasses.map((c) => (
                  <option key={c.id} value={c.id}>
                    Kelas {c.name} ({c.studentCount} Siswa)
                  </option>
                ))}
              </select>
            </div>

            <div className="relative flex-1 max-w-sm">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Cari siswa berdasarkan nama, NIS, atau NISN..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-blue-900 font-medium"
              />
            </div>

            <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
              <button
                type="button"
                onClick={() => setGenderFilter('ALL')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer ${
                  genderFilter === 'ALL' ? 'bg-blue-900 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Semua ({students.length})
              </button>
              <button
                type="button"
                onClick={() => setGenderFilter('L')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer ${
                  genderFilter === 'L' ? 'bg-blue-900 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Laki-Laki ({students.filter(s => s.gender === 'L').length})
              </button>
              <button
                type="button"
                onClick={() => setGenderFilter('P')}
                className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer ${
                  genderFilter === 'P' ? 'bg-blue-900 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Perempuan ({students.filter(s => s.gender === 'P').length})
              </button>
            </div>
          </div>

          {/* Students Roster Table with Sticky Header */}
          <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs flex-1 min-h-0 flex flex-col">
            <div className="overflow-x-auto overflow-y-auto flex-1 h-full">
            <table className="w-full text-left text-xs border-collapse">
              <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-300 shadow-xs">
                <tr className="text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                  <th className="py-3 px-3 w-12 text-center bg-slate-100">No</th>
                  <th className="py-3 px-3 w-24 bg-slate-100">NIS</th>
                  <th className="py-3 px-3 w-28 bg-slate-100">NISN</th>
                  <th className="py-3 px-3 min-w-52 bg-slate-100">Nama Siswa</th>
                  <th className="py-3 px-2 w-14 text-center bg-slate-100">L/P</th>
                  <th className="py-3 px-3 min-w-44 bg-slate-100">Orang Tua / Wali</th>
                  <th className="py-3 px-3 w-40 bg-slate-100">Kontak WhatsApp</th>
                  <th className="py-3 px-3 w-28 text-center bg-slate-100">Kehadiran</th>
                  <th className="py-3 px-3 w-28 text-center bg-slate-100">Poin Disiplin</th>
                  <th className="py-3 px-3 w-28 text-center bg-slate-100">Aksi Walas</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {filteredStudents.map((std, idx) => (
                  <tr key={std.id} className="hover:bg-slate-50 transition-colors">
                    <td className="py-2.5 px-3 text-center font-bold text-slate-500">
                      {idx + 1}
                    </td>
                    <td className="py-2.5 px-3 font-mono font-semibold text-slate-700">
                      {std.nis}
                    </td>
                    <td className="py-2.5 px-3 font-mono text-slate-500">
                      {std.nisn}
                    </td>
                    <td className="py-2.5 px-3 font-bold text-slate-900">
                      <div className="flex items-center gap-2.5">
                        {std.avatar_url ? (
                          <img
                            src={std.avatar_url}
                            alt={std.name}
                            className="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                          />
                        ) : (
                          <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-900 font-bold text-[10px] flex items-center justify-center shrink-0">
                            {std.name.slice(0, 2).toUpperCase()}
                          </div>
                        )}
                        <span className="truncate">{std.name}</span>
                      </div>
                    </td>
                    <td className="py-2.5 px-2 text-center">
                      <span className={`text-[10px] font-black px-1.5 py-0.5 rounded ${
                        std.gender === 'L' ? 'bg-slate-200 text-slate-800' : 'bg-slate-300 text-slate-900'
                      }`}>
                        {std.gender}
                      </span>
                    </td>
                    <td className="py-2.5 px-3 font-medium text-slate-700">
                      {std.parentName}
                    </td>
                    <td className="py-2.5 px-3">
                      <a
                        href={`https://wa.me/62${std.parentPhone.slice(1)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-800 text-white shadow-xs hover:bg-emerald-900 transition-colors"
                      >
                        <MessageSquare className="w-3 h-3" />
                        <span>{std.parentPhone}</span>
                      </a>
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <span className="font-extrabold text-slate-800">
                        {std.attendanceRate}%
                      </span>
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      {std.totalPoints > 0 ? (
                        <span className="px-2 py-0.5 rounded text-[10px] font-black bg-blue-900 text-white shadow-xs">
                          +{std.totalPoints} Poin
                        </span>
                      ) : std.totalPoints < 0 ? (
                        <span className="px-2 py-0.5 rounded text-[10px] font-black bg-rose-700 text-white shadow-xs">
                          {std.totalPoints} Poin
                        </span>
                      ) : (
                        <span className="text-slate-400 font-bold">-</span>
                      )}
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      <div className="flex items-center justify-center gap-1.5">
                        <button
                          type="button"
                          onClick={() => toast.info(`Membuka berkas profil komprehensif untuk ${std.name}`)}
                          className="px-2.5 py-1 rounded text-[11px] font-bold border border-slate-300 hover:bg-slate-100 text-slate-700 cursor-pointer shadow-xs"
                        >
                          Profil
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            setReferralStudent(std);
                            setIsReferralModalOpen(true);
                          }}
                          className="px-2.5 py-1 rounded text-[11px] font-bold bg-purple-900 hover:bg-purple-950 text-white shadow-xs cursor-pointer"
                          title="Rujuk Siswa ke Guru BK"
                        >
                          Rujuk BK
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            </div>
          </div>
        </div>
      )}

      {/* ─── TAB 2: POIN KEDISIPLINAN & PRESTASI SISWA (BE-STD-01) ─── */}
      {activeTab === 'poin' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs space-y-3.5">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
              <div>
                <h2 className="text-sm font-black text-slate-900 uppercase tracking-wider">
                  Log Poin Karakter, Tata Tertib &amp; Prestasi Siswa
                </h2>
                <p className="text-xs text-slate-500 font-medium">
                  Ambang batas pelanggaran: -25 poin memicu Peringatan Wali Kelas, -50 poin memicu Panggilan Orang Tua ke Sekolah.
                </p>
              </div>

              <button
                type="button"
                onClick={() => setIsPointModalOpen(true)}
                className="px-4 py-2 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white flex items-center gap-1.5 shadow-xs cursor-pointer"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>+ Catat Poin Siswa</span>
              </button>
            </div>

            {/* List Records with border-l-4 */}
            <div className="space-y-3">
              {pointRecords.map((rec) => (
                <div
                  key={rec.id}
                  className={`p-4 rounded-xl border border-slate-200/90 bg-white shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3 border-l-4 ${
                    rec.type === 'prestasi' ? 'border-l-blue-900' : 'border-l-rose-700'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    {students.find(s => s.id === rec.studentId)?.avatar_url ? (
                      <div className="relative shrink-0">
                        <img
                          src={students.find(s => s.id === rec.studentId)?.avatar_url}
                          alt={rec.studentName}
                          className="w-10 h-10 rounded-full object-cover border border-slate-200 shadow-2xs"
                        />
                        <span className={`absolute -bottom-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center text-[10px] text-white shadow-xs ${
                          rec.type === 'prestasi' ? 'bg-blue-900' : 'bg-rose-700'
                        }`}>
                          {rec.type === 'prestasi' ? '🏆' : '⚠️'}
                        </span>
                      </div>
                    ) : (
                      <div className={`w-10 h-10 rounded-lg text-white font-black text-xs flex items-center justify-center shrink-0 shadow-xs ${
                        rec.type === 'prestasi' ? 'bg-blue-900' : 'bg-rose-700'
                      }`}>
                        {rec.type === 'prestasi' ? '🏆' : '⚠️'}
                      </div>
                    )}

                    <div>
                      <div className="flex items-center gap-2">
                        <h3 className="font-extrabold text-sm text-slate-900">{rec.studentName}</h3>
                        <span className={`px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider ${
                          rec.type === 'prestasi' ? 'bg-blue-900 text-white' : 'bg-rose-700 text-white'
                        }`}>
                          {rec.points > 0 ? `+${rec.points} Poin` : `${rec.points} Poin`}
                        </span>
                      </div>
                      <p className="text-xs text-slate-700 font-medium mt-0.5">
                        {rec.description}
                      </p>
                      <span className="text-[11px] text-slate-400 font-medium">
                        Dicatat oleh: {rec.recordedBy} pada {rec.date}
                      </span>
                    </div>
                  </div>

                  <span className="text-[11px] font-bold px-2.5 py-1 rounded bg-slate-100 border border-slate-300 text-slate-700 shrink-0">
                    {rec.category}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* ─── MODAL CATAT POIN SISWA ─── */}
      {isPointModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border-2 border-slate-300 rounded-2xl w-full max-w-lg p-6 shadow-xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <h3 className="font-black text-base text-slate-900">
                Pencatatan Poin Siswa (BE-STD-01)
              </h3>
              <button
                type="button"
                onClick={() => setIsPointModalOpen(false)}
                className="text-slate-400 hover:text-slate-700 text-sm font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleAddPoint} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Pilih Siswa</label>
                <select
                  value={selectedStudentId}
                  onChange={(e) => setSelectedStudentId(e.target.value)}
                  className="w-full px-3 py-2 text-xs font-bold bg-white border border-slate-300 rounded-lg focus:border-blue-900 focus:outline-none"
                >
                  {students.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.nis} - {s.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Jenis Poin</label>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setPointType('prestasi')}
                    className={`py-2 px-3 rounded-lg text-xs font-bold border transition-all cursor-pointer ${
                      pointType === 'prestasi' ? 'bg-blue-900 text-white border-blue-900' : 'bg-white border-slate-300 text-slate-700'
                    }`}
                  >
                    🏆 Poin Prestasi (+)
                  </button>
                  <button
                    type="button"
                    onClick={() => setPointType('pelanggaran')}
                    className={`py-2 px-3 rounded-lg text-xs font-bold border transition-all cursor-pointer ${
                      pointType === 'pelanggaran' ? 'bg-rose-800 text-white border-rose-800' : 'bg-white border-slate-300 text-slate-700'
                    }`}
                  >
                    ⚠️ Poin Pelanggaran (-)
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Deskripsi &amp; Alasan Pencatatan</label>
                <textarea
                  rows={3}
                  required
                  placeholder="Keterangan lengkap kejadian / prestasi yang diraih..."
                  value={pointNotes}
                  onChange={(e) => setPointNotes(e.target.value)}
                  className="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:border-blue-900 focus:outline-none font-medium"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                <button
                  type="button"
                  onClick={() => setIsPointModalOpen(false)}
                  className="px-4 py-2 rounded-lg text-xs font-bold border border-slate-300 text-slate-700 hover:bg-slate-100"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-lg text-xs font-black bg-blue-900 hover:bg-blue-950 text-white shadow-xs"
                >
                  Simpan Poin Siswa
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ─── TAB 3: RUJUKAN BIMBINGAN KONSELING / BK (BE-BK-01 & ADM-BK-01) ─── */}
      {activeTab === 'rujukan_bk' && (
        <div className="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">
          <div className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs space-y-3.5">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-purple-900 text-white">
                    LAYANAN BIMBINGAN KONSELING
                  </span>
                  <span className="text-xs text-slate-500 font-semibold">• BE-BK-01 &amp; ADM-BK-01 (P0)</span>
                </div>
                <h3 className="font-extrabold text-sm text-slate-900">
                  Riwayat Rujukan Bimbingan Konseling Siswa Kelas IX-A
                </h3>
                <p className="text-xs text-slate-500">
                  Data pembinaan khusus siswa binaan. Demi privasi siswa, isi sesi konseling terenkripsi dan guru memantau perkembangan status rujukan.
                </p>
              </div>
            </div>

            <div className="border border-slate-200/90 rounded-xl overflow-x-auto bg-white shadow-xs max-h-160 overflow-y-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="sticky top-0 bg-slate-100 z-10 border-b border-slate-200">
                  <tr className="text-slate-800 font-extrabold uppercase text-[10px] tracking-wider">
                    <th className="py-2.5 px-3 w-12 text-center">No</th>
                    <th className="py-2.5 px-3 w-28">NIS</th>
                    <th className="py-2.5 px-3 min-w-44">Nama Siswa</th>
                    <th className="py-2.5 px-3 w-32">Tanggal Rujuk</th>
                    <th className="py-2.5 px-3 min-w-56">Alasan Permasalahan</th>
                    <th className="py-2.5 px-2 w-24 text-center">Urgensi</th>
                    <th className="py-2.5 px-3 w-36 text-center">Status Bimbingan</th>
                    <th className="py-2.5 px-3 min-w-64">Catatan Tindak Lanjut Guru BK</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200/80">
                  {referrals.map((ref, idx) => (
                    <tr key={ref.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="py-2.5 px-3 text-center font-bold text-slate-500">{idx + 1}</td>
                      <td className="py-2.5 px-3 font-mono font-semibold text-slate-700">{ref.nis}</td>
                      <td className="py-2.5 px-3 font-bold text-slate-900">
                        <div className="flex items-center gap-2.5">
                          {students.find(s => s.id === ref.studentId)?.avatar_url ? (
                            <img
                              src={students.find(s => s.id === ref.studentId)?.avatar_url}
                              alt={ref.studentName}
                              className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs"
                            />
                          ) : (
                            <div className="w-7 h-7 rounded-full bg-purple-100 text-purple-900 font-bold text-[10px] flex items-center justify-center shrink-0">
                              {ref.studentName.slice(0, 2).toUpperCase()}
                            </div>
                          )}
                          <span className="truncate">{ref.studentName}</span>
                        </div>
                      </td>
                      <td className="py-2.5 px-3 font-mono text-slate-600">{ref.date}</td>
                      <td className="py-2.5 px-3 font-medium text-slate-800">{ref.reason}</td>
                      <td className="py-2.5 px-2 text-center">
                        {ref.priority === 'segera' ? (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-rose-700 text-white shadow-xs">
                            SEGERA
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-slate-700 text-white shadow-xs">
                            BIASA
                          </span>
                        )}
                      </td>
                      <td className="py-2.5 px-3 text-center">
                        {ref.status === 'menunggu' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-amber-600 text-white shadow-xs">
                            MENUNGGU
                          </span>
                        )}
                        {ref.status === 'dijadwalkan' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-blue-900 text-white shadow-xs">
                            DIJADWALKAN
                          </span>
                        )}
                        {ref.status === 'selesai' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-800 text-white shadow-xs">
                            SELESAI
                          </span>
                        )}
                      </td>
                      <td className="py-2.5 px-3 text-slate-600 text-[11px] italic">
                        {ref.counselorNotes || 'Belum ada catatan tindak lanjut dari konselor.'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ─── MODAL RUJUKAN KONSELING SISWA KE BK (BE-BK-01) ─── */}
      {isReferralModalOpen && referralStudent && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 border-2 border-slate-300">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div>
                <h3 className="font-extrabold text-base text-slate-900">Rujuk Siswa ke Bimbingan Konseling (BK)</h3>
                <p className="text-xs text-slate-500">Kirim permohonan pendampingan konselor untuk siswa binaan</p>
              </div>
              <button
                type="button"
                onClick={() => setIsReferralModalOpen(false)}
                className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateReferral} className="space-y-4 text-xs">
              <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                <span className="text-[10px] font-bold text-slate-500 uppercase block">Peserta Didik yang Dirujuk:</span>
                <p className="font-black text-sm text-slate-900 mt-0.5">{referralStudent.name}</p>
                <p className="text-[11px] font-mono text-slate-600">NIS: {referralStudent.nis} • Kelas IX-A</p>
              </div>

              <div>
                <label className="font-bold text-slate-800 block mb-1">Alasan Pokok Rujukan BK:</label>
                <select
                  value={referralReason}
                  onChange={(e) => setReferralReason(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-900"
                >
                  <option value="Penurunan Kehadiran & Sering Terlambat">Penurunan Kehadiran &amp; Sering Terlambat</option>
                  <option value="Kesulitan Belajar & Penurunan Drastis Nilai Sumatif">Kesulitan Belajar &amp; Penurunan Drastis Nilai Sumatif</option>
                  <option value="Masalah Kedisiplinan & Sikap di Kelas">Masalah Kedisiplinan &amp; Sikap di Kelas</option>
                  <option value="Perubahan Perilaku / Butuh Motivasi Belajar">Perubahan Perilaku / Butuh Motivasi Belajar</option>
                  <option value="Konsultasi Hubungan Pertemanan & Emosional">Konsultasi Hubungan Pertemanan &amp; Emosional</option>
                </select>
              </div>

              <div>
                <label className="font-bold text-slate-800 block mb-1">Tingkat Urgensi Bimbingan:</label>
                <select
                  value={referralPriority}
                  onChange={(e) => setReferralPriority(e.target.value as 'biasa' | 'segera')}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-black text-slate-900 focus:outline-none focus:border-blue-900"
                >
                  <option value="biasa">Biasa (Konseling Terjadwal Rutin)</option>
                  <option value="segera">Segera (Butuh Pendampingan Cepat)</option>
                </select>
              </div>

              <div>
                <label className="font-bold text-slate-800 block mb-1">Catatan Awal / Observasi Guru:</label>
                <textarea
                  rows={3}
                  required
                  placeholder="Ceritakan temuan observasi atau kendala siswa di kelas..."
                  value={referralNotes}
                  onChange={(e) => setReferralNotes(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium focus:outline-none focus:border-blue-900"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                <button
                  type="button"
                  onClick={() => setIsReferralModalOpen(false)}
                  className="px-3.5 py-2 text-xs font-bold border border-slate-300 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-black bg-purple-900 hover:bg-purple-950 text-white rounded-lg shadow-xs cursor-pointer"
                >
                  Kirim Rujukan ke Guru BK
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
