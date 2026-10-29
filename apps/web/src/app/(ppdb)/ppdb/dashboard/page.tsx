'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ppdbApi } from '@/features/ppdb/ppdb-api';
import { tokenStorage } from '@/lib/auth/token-storage';
import {
  ApplicantProfileResponse,
  ApplicantProfileApplication,
  PPDBWaveItem,
  PPDBTrackItem,
} from '@/types/ppdb';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import {
  School,
  LogOut,
  User,
  FileText,
  Upload,
  CheckCircle2,
  AlertCircle,
  Clock,
  Printer,
  ChevronRight,
  ShieldCheck,
  Building,
  GraduationCap,
  Calendar,
  AlertTriangle,
  Loader2,
  Save,
  Send,
  Trash2,
  ExternalLink,
} from 'lucide-react';

const EMPTY_TRACKS: PPDBTrackItem[] = [];

export default function ApplicantDashboardPage() {
  const router = useRouter();
  const queryClient = useQueryClient();

  // Auth Guard: Cek apakah token tersedia via tokenStorage
  useEffect(() => {
    const token = tokenStorage.getAccessToken();
    if (!token) {
      router.replace('/ppdb/masuk');
    }
  }, [router]);

  // Query Data Profil Pendaftar
  const {
    data: profile,
    isLoading: isLoadingProfile,
    isError: isProfileError,
    refetch: refetchProfile,
  } = useQuery<ApplicantProfileResponse>({
    queryKey: ['ppdb-applicant-me'],
    queryFn: () => ppdbApi.getMe(),
    retry: 1,
  });

  // Query Active Waves & Tracks untuk jika pendaftar baru belum memiliki aplikasi
  const { data: activeWave } = useQuery<PPDBWaveItem | null>({
    queryKey: ['ppdb-active-wave-for-dashboard'],
    queryFn: () => ppdbApi.getActiveWave(),
  });

  const { data: tracksData } = useQuery<PPDBTrackItem[]>({
    queryKey: ['ppdb-tracks-for-dashboard', activeWave?.id],
    queryFn: () => ppdbApi.listPublicTracks(activeWave?.id),
    enabled: !!activeWave?.id,
  });
  const tracks = tracksData ?? EMPTY_TRACKS;

  const activeApplication: ApplicantProfileApplication | undefined =
    profile?.applications?.[0];

  // Stepper State
  const [activeStep, setActiveStep] = useState<number>(1);
  const [feedbackMessage, setFeedbackMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Form Data State
  const [personalData, setPersonalData] = useState({
    full_name: '',
    nik: '',
    nisn: '',
    birth_place: '',
    birth_date: '',
    gender: 'L',
    religion: 'Islam',
    address: '',
  });

  const [parentData, setParentData] = useState({
    father_name: '',
    father_job: '',
    father_phone: '',
    mother_name: '',
    mother_job: '',
    mother_phone: '',
    income_range: '< 3 Juta',
  });

  const [schoolData, setSchoolData] = useState({
    origin_school: '',
    npsn: '',
    graduation_year: '2026',
    average_score: '',
    rapor_details: {
      indonesian: '',
      math: '',
      science: '',
      english: '',
    },
  });

  const [choiceData, setChoiceData] = useState({
    track_id: '',
    wave_id: '',
    notes: '',
  });

  // Re-registration & Receipt States (GAP-PPDB-02 & BE-PPDB-08/09)
  const [isReRegisterModalOpen, setIsReRegisterModalOpen] = useState(false);
  const [reRegisterNotes, setReRegisterNotes] = useState('');
  const [agreedToRules, setAgreedToRules] = useState(false);
  const [isDownloadingReceipt, setIsDownloadingReceipt] = useState(false);
  const [uploadingDocType, setUploadingDocType] = useState<string | null>(null);

  // Helper Auto-Hitung Rata-Rata Nilai Rapor
  const handleRaporDetailChange = (subject: 'indonesian' | 'math' | 'science' | 'english', value: string) => {
    const updatedDetails = {
      ...schoolData.rapor_details,
      [subject]: value,
    };
    const validScores = Object.values(updatedDetails)
      .map(Number)
      .filter((n) => !isNaN(n) && n > 0);
    const autoAvg =
      validScores.length > 0
        ? (validScores.reduce((a, b) => a + b, 0) / validScores.length).toFixed(2)
        : schoolData.average_score;

    setSchoolData({
      ...schoolData,
      average_score: autoAvg,
      rapor_details: updatedDetails,
    });
  };

  // Populate data jika aplikasi sudah ada
  useEffect(() => {
    if (activeApplication) {
      const steps = activeApplication.step_data || {};
      const personal = (steps.personal as any) || {};
      const parent = (steps.parent as any) || {};
      const school = (steps.school as any) || {};

      setPersonalData({
        full_name: activeApplication.full_name || personal.full_name || '',
        nik: personal.nik || '',
        nisn: personal.nisn || '',
        birth_place: personal.birth_place || '',
        birth_date: activeApplication.birth_date || personal.birth_date || '',
        gender: personal.gender || 'L',
        religion: personal.religion || 'Islam',
        address: personal.address || '',
      });

      setParentData({
        father_name: parent.father_name || '',
        father_job: parent.father_job || '',
        father_phone: parent.father_phone || '',
        mother_name: parent.mother_name || '',
        mother_job: parent.mother_job || '',
        mother_phone: parent.mother_phone || '',
        income_range: parent.income_range || '< 3 Juta',
      });

      setSchoolData({
        origin_school: school.origin_school || '',
        npsn: school.npsn || '',
        graduation_year: school.graduation_year || '2026',
        average_score: school.average_score || '',
        rapor_details: school.rapor_details || {
          indonesian: '',
          math: '',
          science: '',
          english: '',
        },
      });

      setChoiceData({
        track_id: activeApplication.track?.id || '',
        wave_id: activeApplication.wave?.id || '',
        notes: (steps.track as any)?.notes || '',
      });
    } else if (activeWave && tracks.length > 0) {
      setChoiceData((prev) => ({
        ...prev,
        wave_id: activeWave.id,
        track_id: tracks[0].id,
      }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeApplication?.id, activeWave?.id, tracks.length]);

  // Mutation Inisialisasi Pendaftaran Baru (jika belum ada aplikasi)
  const createApplicationMutation = useMutation({
    mutationFn: () =>
      ppdbApi.createApplication({
        wave_id: choiceData.wave_id || activeWave?.id || '',
        track_id: choiceData.track_id || tracks[0]?.id || '',
        full_name: personalData.full_name || profile?.username || 'Calon Siswa',
        nik: personalData.nik || '3579000000000000',
        birth_date: personalData.birth_date || '2010-01-01',
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicant-me'] });
      setFeedbackMessage({ type: 'success', text: 'Draf pendaftaran berhasil dibuat! Silakan lengkapi formulir.' });
    },
    onError: (err: any) => {
      setFeedbackMessage({
        type: 'error',
        text: err.response?.data?.message || 'Gagal memulai pendaftaran. Pastikan gelombang pendaftaran aktif.',
      });
    },
  });

  // Mutation Simpan Draf Langkah Formulir
  const saveStepMutation = useMutation({
    mutationFn: async ({ step, data }: { step: string | number; data: any }) => {
      if (!activeApplication) return;
      return ppdbApi.updateStep(activeApplication.id, step, data);
    },
    onSuccess: () => {
      setFeedbackMessage({ type: 'success', text: 'Draf formulir berhasil disimpan.' });
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicant-me'] });
    },
    onError: (err: any) => {
      setFeedbackMessage({
        type: 'error',
        text: err.response?.data?.message || 'Gagal menyimpan draf.',
      });
    },
  });

  // Mutation Submit Final Pendaftaran
  const submitApplicationMutation = useMutation({
    mutationFn: async () => {
      if (!activeApplication) return;
      return ppdbApi.submitApplication(activeApplication.id);
    },
    onSuccess: () => {
      setFeedbackMessage({
        type: 'success',
        text: 'Formulir pendaftaran berhasil disubmit secara resmi! Berkas Anda akan diverifikasi panitia.',
      });
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicant-me'] });
    },
    onError: (err: any) => {
      setFeedbackMessage({
        type: 'error',
        text: err.response?.data?.message || 'Gagal submit formulir. Pastikan semua berkas wajib telah diunggah.',
      });
    },
  });

  // Handler Simpan Step Aktif
  const handleSaveStep = () => {
    if (!activeApplication) {
      createApplicationMutation.mutate();
      return;
    }

    if (activeStep === 1) {
      saveStepMutation.mutate({ step: 'personal', data: personalData });
    } else if (activeStep === 2) {
      saveStepMutation.mutate({
        step: 'parent',
        data: {
          ...parentData,
          parents: [
            { parent_type: 'ayah', name: parentData.father_name, occupation: parentData.father_job, phone: parentData.father_phone },
            { parent_type: 'ibu', name: parentData.mother_name, occupation: parentData.mother_job, phone: parentData.mother_phone },
          ],
        },
      });
    } else if (activeStep === 3) {
      saveStepMutation.mutate({ step: 'school', data: schoolData });
    } else if (activeStep === 4) {
      saveStepMutation.mutate({ step: 'track', data: choiceData });
    }
  };

  // Handler Logout
  const handleLogout = () => {
    tokenStorage.clearAll();
    if (typeof window !== 'undefined') {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user_role');
    }
    router.push('/ppdb/masuk');
  };

  // Mutation Konfirmasi Pendaftaran Ulang (GAP-PPDB-02 / BE-PPDB-08)
  const reRegisterMutation = useMutation({
    mutationFn: (notes?: string) => {
      if (!activeApplication) throw new Error('Aplikasi pendaftaran tidak ditemukan');
      return ppdbApi.reRegister(activeApplication.id, { notes });
    },
    onSuccess: () => {
      setFeedbackMessage({
        type: 'success',
        text: 'Konfirmasi pendaftaran ulang berhasil dicatat! Selamat datang di SISTLABUM.',
      });
      setIsReRegisterModalOpen(false);
      queryClient.invalidateQueries({ queryKey: ['ppdb-applicant-me'] });
      refetchProfile();
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || 'Gagal memproses pendaftaran ulang.';
      setFeedbackMessage({ type: 'error', text: msg });
    },
  });

  // Handler Download PDF Tanda Bukti Resmi (BE-PPDB-09)
  const handleDownloadReceipt = async () => {
    if (!activeApplication?.id) return;
    setIsDownloadingReceipt(true);
    try {
      const blob = await ppdbApi.downloadReceiptPdf(activeApplication.id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `Bukti_Pendaftaran_${activeApplication.registration_no || activeApplication.id}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch {
      // Fallback ke browser print jika endpoint PDF belum aktif/network error
      window.print();
    } finally {
      setIsDownloadingReceipt(false);
    }
  };

  // Handler Unggah Berkas dengan Validasi Ukuran (2MB) & Ekstensi (GAP-PPDB)
  const handleFileUpload = (docType: string, docLabel: string, file?: File) => {
    if (!file || !activeApplication) return;

    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const ALLOWED_EXTS = ['.pdf', '.jpg', '.jpeg', '.png', '.webp'];
    const ext = '.' + file.name.split('.').pop()?.toLowerCase();

    if (!ALLOWED_EXTS.includes(ext)) {
      setFeedbackMessage({
        type: 'error',
        text: `Format berkas "${file.name}" tidak didukung. Harap unggah berkas bertipe PDF, JPG, PNG, atau WEBP.`,
      });
      return;
    }

    if (file.size > MAX_FILE_SIZE) {
      setFeedbackMessage({
        type: 'error',
        text: `Ukuran berkas "${file.name}" (${(file.size / (1024 * 1024)).toFixed(1)} MB) melebihi batas maksimal 2 MB.`,
      });
      return;
    }

    setUploadingDocType(docType);
    const fd = new FormData();
    fd.append('file', file);
    fd.append('document_type', docType);

    ppdbApi
      .uploadDocument(activeApplication.id, fd)
      .then(() => {
        setFeedbackMessage({ type: 'success', text: `Berkas ${docLabel} berhasil diunggah.` });
        refetchProfile();
      })
      .catch((err: any) => {
        const msg =
          err.response?.data?.message ||
          err.response?.data?.error?.message ||
          `Gagal mengunggah berkas ${docLabel}. Periksa koneksi dan coba lagi.`;
        setFeedbackMessage({ type: 'error', text: msg });
      })
      .finally(() => {
        setUploadingDocType(null);
      });
  };

  // Status Badge Helper
  const renderStatusBadge = (status?: string, decision?: string | null) => {
    if (decision === 'diterima') {
      return <Badge className="bg-emerald-600 text-white font-semibold">DITERIMA (LOLOS SELEKSI)</Badge>;
    }
    if (decision === 'cadangan') {
      return <Badge className="bg-amber-600 text-white font-semibold">CADANGAN (WAITLIST)</Badge>;
    }
    if (decision === 'ditolak') {
      return <Badge className="bg-red-600 text-white font-semibold">BELUM LOLOS SELEKSI</Badge>;
    }

    switch (status) {
      case 'draft':
        return <Badge className="bg-slate-200 text-slate-800">Draf (Belum Dikirim)</Badge>;
      case 'submitted':
        return <Badge className="bg-blue-600 text-white">Menunggu Verifikasi Berkas</Badge>;
      case 'revision':
        return <Badge className="bg-amber-500 text-white">Perlu Revisi Dokumen</Badge>;
      case 'verified':
        return <Badge className="bg-indigo-600 text-white">Terverifikasi (Siap Seleksi)</Badge>;
      case 'enrolled':
        return <Badge className="bg-teal-600 text-white font-semibold">Telah Daftar Ulang Resmi</Badge>;
      default:
        return <Badge className="bg-slate-100 text-slate-600">Pendaftaran Baru</Badge>;
    }
  };

  // Jika sedang loading awal
  if (isLoadingProfile) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
        <div className="text-center">
          <Loader2 className="h-8 w-8 text-brand-blue animate-spin mx-auto mb-3" />
          <p className="text-sm font-semibold text-slate-700">Memuat Dashboard Pendaftar...</p>
        </div>
      </div>
    );
  }

  // Jika error (misal token kedaluwarsa)
  if (isProfileError) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-2xl p-6 border border-slate-200 shadow-md text-center">
          <AlertCircle className="h-10 w-10 text-red-500 mx-auto mb-3" />
          <h2 className="text-lg font-bold text-slate-900">Sesi Telah Berakhir</h2>
          <p className="text-xs text-slate-600 mt-1 mb-5">
            Sesi masuk pendaftaran Anda telah kedaluwarsa demi keamanan. Silakan masuk kembali.
          </p>
          <Button onClick={handleLogout} className="bg-brand-blue hover:bg-blue-700 text-white font-semibold w-full">
            Masuk Kembali ke Portal Pendaftar
          </Button>
        </div>
      </div>
    );
  }

  const isLocked = activeApplication?.status && !['draft', 'revision'].includes(activeApplication.status);

  return (
    <div className="min-h-screen bg-slate-50 pb-16">
      {/* Top Navbar Dashboard */}
      <nav aria-label="Bilah Aksi Dashboard Pendaftar" className="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="h-9 w-9 rounded-lg bg-brand-blue text-white flex items-center justify-center shadow-sm">
              <School className="h-5 w-5" />
            </div>
            <div>
              <span className="text-sm font-bold text-slate-900 block leading-tight">Dashboard Pendaftar</span>
              <span className="text-[11px] text-slate-500">SPMB SMP-SMA Laboratorium UM</span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="hidden sm:block text-right">
              <span className="text-xs font-semibold text-slate-800 block">{profile?.email}</span>
              <span className="text-[10px] text-emerald-600 font-medium flex items-center justify-end gap-1">
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Akun Terverifikasi
              </span>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={handleLogout}
              className="text-xs text-slate-600 hover:text-red-600 hover:border-red-200 gap-1.5"
            >
              <LogOut className="h-3.5 w-3.5" />
              Keluar
            </Button>
          </div>
        </div>
      </nav>

      {/* Main Container */}
      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-6">
        {/* Banner Notifikasi Feedback */}
        {feedbackMessage && (
          <div
            className={`p-4 rounded-xl border flex items-start gap-3 text-sm ${
              feedbackMessage.type === 'success'
                ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                : 'bg-red-50 border-red-200 text-red-800'
            }`}
          >
            {feedbackMessage.type === 'success' ? (
              <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
            ) : (
              <AlertCircle className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
            )}
            <div className="flex-1">
              <p className="font-semibold">{feedbackMessage.text}</p>
            </div>
            <button
              onClick={() => setFeedbackMessage(null)}
              className="text-xs opacity-70 hover:opacity-100"
            >
              Tutup
            </button>
          </div>
        )}

        {/* Banner Revisi Panitia (jika status = revision) */}
        {activeApplication?.status === 'revision' && (
          <div className="p-4 rounded-xl bg-amber-50 border border-amber-300 flex items-start gap-3 text-amber-900 shadow-sm">
            <AlertTriangle className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
            <div className="flex-1">
              <h3 className="font-bold text-sm">Pemberitahuan Revisi Berkas Pendaftaran</h3>
              <p className="text-xs text-amber-800 mt-1">
                Panitia verifikator telah memeriksa berkas Anda dan meminta perbaikan. Silakan periksa tab berkas dan unggah ulang dokumen yang diminta sebelum batas waktu verifikasi berakhir.
              </p>
            </div>
          </div>
        )}

        {/* Banner & Kartu Konfirmasi Pendaftaran Ulang (GAP-PPDB-02) */}
        {activeApplication?.decision === 'diterima' && (
          <div className="p-6 rounded-2xl bg-linear-to-r from-emerald-50 via-teal-50 to-green-50 border-2 border-emerald-300 shadow-sm space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-600 text-white">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    LOLOS SELEKSI RESMI
                  </span>
                  {activeApplication.re_registration?.status ? (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                      Daftar Ulang:{' '}
                      {activeApplication.re_registration.status === 'diverifikasi'
                        ? 'Terverifikasi'
                        : 'Menunggu Verifikasi Panitia'}
                    </span>
                  ) : null}
                </div>
                <h2 className="text-lg font-bold text-emerald-950">
                  Selamat! Anda Dinyatakan Diterima di SMP/SMA Laboratorium UM
                </h2>
                <p className="text-xs text-emerald-800">
                  {activeApplication.re_registration
                    ? 'Konfirmasi pendaftaran ulang Anda telah tersimpan di sistem. Panitia PPDB akan segera menerbitkan Nomor Induk Siswa (NIS) dan rombongan belajar.'
                    : 'Tahap selanjutnya adalah konfirmasi pendaftaran ulang untuk mengunci kuota dan memastikan kesiapan menjadi peserta didik baru.'}
                </p>
              </div>

              {!activeApplication.re_registration && (
                <Button
                  onClick={() => setIsReRegisterModalOpen(true)}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs gap-1.5 shrink-0 px-4 py-2.5 shadow-sm"
                >
                  <CheckCircle2 className="h-4 w-4" />
                  Konfirmasi Daftar Ulang
                </Button>
              )}
            </div>
          </div>
        )}

        {/* Kartu Ringkasan Status Pendaftaran */}
        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div>
              <div className="flex items-center gap-2 mb-1.5">
                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Status Seleksi</span>
                {renderStatusBadge(activeApplication?.status, activeApplication?.decision)}
              </div>
              <h1 className="text-xl sm:text-2xl font-bold text-slate-900">
                {activeApplication?.full_name || profile?.username || 'Pendaftar Baru'}
              </h1>
              <p className="text-xs text-slate-600 mt-0.5">
                Nomor Registrasi Resmi:{' '}
                <span className="font-mono font-bold text-brand-blue text-sm">
                  {activeApplication?.registration_no || 'Belum Diterbitkan (Isi & Simpan Draf)'}
                </span>
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {activeApplication && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleDownloadReceipt}
                  disabled={isDownloadingReceipt}
                  className="gap-1.5 text-xs text-slate-700 font-medium"
                >
                  {isDownloadingReceipt ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <Printer className="h-3.5 w-3.5" />
                  )}
                  {isDownloadingReceipt ? 'Mengunduh...' : 'Cetak Bukti Pendaftaran'}
                </Button>
              )}
              <Link href="/ppdb" className="text-xs text-brand-blue font-medium hover:underline inline-flex items-center gap-1">
                Informasi SPMB
                <ExternalLink className="h-3 w-3" />
              </Link>
            </div>
          </div>

          {/* Info Gelombang & Jalur */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-5 text-xs text-slate-600">
            <div className="flex items-center gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-100">
              <Calendar className="h-4 w-4 text-brand-blue shrink-0" />
              <div>
                <span className="text-slate-500 block text-[11px]">Gelombang</span>
                <span className="font-semibold text-slate-900">
                  {activeApplication?.wave?.name || activeWave?.name || 'Gelombang Belum Dipilih'}
                </span>
              </div>
            </div>

            <div className="flex items-center gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-100">
              <GraduationCap className="h-4 w-4 text-brand-teal shrink-0" />
              <div>
                <span className="text-slate-500 block text-[11px]">Jalur Penerimaan</span>
                <span className="font-semibold text-slate-900">
                  {activeApplication?.track?.name || tracks[0]?.name || 'Jalur Reguler'}
                </span>
              </div>
            </div>

            <div className="flex items-center gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-100">
              <ShieldCheck className="h-4 w-4 text-indigo-600 shrink-0" />
              <div>
                <span className="text-slate-500 block text-[11px]">Pemberkasan</span>
                <span className="font-semibold text-slate-900">
                  {activeApplication?.documents?.length || 0} Dokumen Terunggah
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Stepper Navigasi 5 Langkah */}
        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
          <div className="flex items-center justify-between border-b border-slate-200 pb-4 mb-6 overflow-x-auto">
            {[
              { num: 1, label: 'Data Diri Siswa', icon: User },
              { num: 2, label: 'Orang Tua / Wali', icon: Building },
              { num: 3, label: 'Asal Sekolah', icon: School },
              { num: 4, label: 'Jalur & Pilihan', icon: GraduationCap },
              { num: 5, label: 'Upload Berkas', icon: Upload },
            ].map((step) => {
              const Icon = step.icon;
              const isActive = activeStep === step.num;
              return (
                <button
                  key={step.num}
                  onClick={() => setActiveStep(step.num)}
                  className={`flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all ${
                    isActive
                      ? 'bg-brand-blue text-white shadow-sm'
                      : 'text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  <Icon className="h-4 w-4" />
                  <span>{step.num}. {step.label}</span>
                </button>
              );
            })}
          </div>

          {/* Tab 1: Data Diri Siswa */}
          {activeStep === 1 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 border-b pb-2">Langkah 1: Identitas Calon Siswa</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Lengkap (Sesuai Akta)</label>
                  <Input
                    value={personalData.full_name}
                    onChange={(e) => setPersonalData({ ...personalData, full_name: e.target.value })}
                    disabled={isLocked}
                    placeholder="Nama Lengkap"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">NIK (Nomor Induk Kependudukan)</label>
                  <Input
                    value={personalData.nik}
                    onChange={(e) => setPersonalData({ ...personalData, nik: e.target.value })}
                    disabled={isLocked}
                    maxLength={16}
                    placeholder="16 Digit NIK"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">NISN (Nomor Induk Siswa Nasional)</label>
                  <Input
                    value={personalData.nisn}
                    onChange={(e) => setPersonalData({ ...personalData, nisn: e.target.value })}
                    disabled={isLocked}
                    placeholder="10 Digit NISN"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Tempat Lahir</label>
                  <Input
                    value={personalData.birth_place}
                    onChange={(e) => setPersonalData({ ...personalData, birth_place: e.target.value })}
                    disabled={isLocked}
                    placeholder="Contoh: Malang"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Tanggal Lahir</label>
                  <Input
                    type="date"
                    value={personalData.birth_date}
                    onChange={(e) => setPersonalData({ ...personalData, birth_date: e.target.value })}
                    disabled={isLocked}
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Jenis Kelamin</label>
                  <select
                    value={personalData.gender}
                    onChange={(e) => setPersonalData({ ...personalData, gender: e.target.value })}
                    disabled={isLocked}
                    className="w-full h-10 px-3 border rounded-md text-xs bg-white text-slate-900 border-slate-300"
                  >
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                  </select>
                </div>
                <div className="sm:col-span-2">
                  <label className="block font-semibold text-slate-700 mb-1">Alamat Tempat Tinggal Lengkap</label>
                  <Input
                    value={personalData.address}
                    onChange={(e) => setPersonalData({ ...personalData, address: e.target.value })}
                    disabled={isLocked}
                    placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Tab 2: Data Orang Tua / Wali */}
          {activeStep === 2 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 border-b pb-2">Langkah 2: Data Orang Tua / Wali</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Ayah Kandung</label>
                  <Input
                    value={parentData.father_name}
                    onChange={(e) => setParentData({ ...parentData, father_name: e.target.value })}
                    disabled={isLocked}
                    placeholder="Nama Lengkap Ayah"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Pekerjaan Ayah</label>
                  <Input
                    value={parentData.father_job}
                    onChange={(e) => setParentData({ ...parentData, father_job: e.target.value })}
                    disabled={isLocked}
                    placeholder="PNS / Swasta / Wiraswasta"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Ibu Kandung</label>
                  <Input
                    value={parentData.mother_name}
                    onChange={(e) => setParentData({ ...parentData, mother_name: e.target.value })}
                    disabled={isLocked}
                    placeholder="Nama Lengkap Ibu"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Pekerjaan Ibu</label>
                  <Input
                    value={parentData.mother_job}
                    onChange={(e) => setParentData({ ...parentData, mother_job: e.target.value })}
                    disabled={isLocked}
                    placeholder="Ibu Rumah Tangga / Pegawai"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nomor Telepon / WhatsApp Orang Tua</label>
                  <Input
                    value={parentData.father_phone || parentData.mother_phone}
                    onChange={(e) => setParentData({ ...parentData, father_phone: e.target.value })}
                    disabled={isLocked}
                    placeholder="081234567890"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Rentang Penghasilan Keluarga</label>
                  <select
                    value={parentData.income_range}
                    onChange={(e) => setParentData({ ...parentData, income_range: e.target.value })}
                    disabled={isLocked}
                    className="w-full h-10 px-3 border rounded-md text-xs bg-white text-slate-900 border-slate-300"
                  >
                    <option value="< 3 Juta">&lt; Rp 3.000.000 / bulan</option>
                    <option value="3 - 7 Juta">Rp 3.000.000 - Rp 7.000.000 / bulan</option>
                    <option value="7 - 15 Juta">Rp 7.000.000 - Rp 15.000.000 / bulan</option>
                    <option value="> 15 Juta">&gt; Rp 15.000.000 / bulan</option>
                  </select>
                </div>
              </div>
            </div>
          )}

          {/* Tab 3: Asal Sekolah & Nilai Rapor */}
          {activeStep === 3 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 border-b pb-2">
                Langkah 3: Riwayat Asal Sekolah & Nilai Rapor
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Nama Sekolah Asal (SD/SMP)</label>
                  <Input
                    value={schoolData.origin_school}
                    onChange={(e) => setSchoolData({ ...schoolData, origin_school: e.target.value })}
                    disabled={isLocked}
                    placeholder="Contoh: SMP Negeri 1 Malang"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">NPSN Sekolah Asal</label>
                  <Input
                    value={schoolData.npsn}
                    onChange={(e) => setSchoolData({ ...schoolData, npsn: e.target.value })}
                    disabled={isLocked}
                    placeholder="8 Digit NPSN"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Tahun Kelulusan</label>
                  <Input
                    value={schoolData.graduation_year}
                    onChange={(e) => setSchoolData({ ...schoolData, graduation_year: e.target.value })}
                    disabled={isLocked}
                    placeholder="2026"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">
                    Rata-Rata Nilai Rapor Kumulatif (Semester 1–5)
                  </label>
                  <Input
                    type="number"
                    step="0.01"
                    value={schoolData.average_score}
                    onChange={(e) => setSchoolData({ ...schoolData, average_score: e.target.value })}
                    disabled={isLocked}
                    placeholder="Contoh: 88.50"
                  />
                </div>
              </div>

              {/* Rincian Nilai per Mata Pelajaran (GAP-PPDB-03) */}
              <div className="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                  <span className="text-xs font-bold text-slate-800">
                    Rincian Nilai Rata-Rata per Mata Pelajaran (Semester 1–5):
                  </span>
                  <span className="text-[11px] text-slate-500 italic">
                    Otomatis memperbarui rata-rata kumulatif di atas
                  </span>
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                  <div>
                    <label className="block font-medium text-slate-600 mb-1">B. Indonesia</label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      placeholder="0 - 100"
                      value={schoolData.rapor_details?.indonesian || ''}
                      onChange={(e) => handleRaporDetailChange('indonesian', e.target.value)}
                      disabled={isLocked}
                    />
                  </div>
                  <div>
                    <label className="block font-medium text-slate-600 mb-1">Matematika</label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      placeholder="0 - 100"
                      value={schoolData.rapor_details?.math || ''}
                      onChange={(e) => handleRaporDetailChange('math', e.target.value)}
                      disabled={isLocked}
                    />
                  </div>
                  <div>
                    <label className="block font-medium text-slate-600 mb-1">IPA / Sains</label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      placeholder="0 - 100"
                      value={schoolData.rapor_details?.science || ''}
                      onChange={(e) => handleRaporDetailChange('science', e.target.value)}
                      disabled={isLocked}
                    />
                  </div>
                  <div>
                    <label className="block font-medium text-slate-600 mb-1">B. Inggris</label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      placeholder="0 - 100"
                      value={schoolData.rapor_details?.english || ''}
                      onChange={(e) => handleRaporDetailChange('english', e.target.value)}
                      disabled={isLocked}
                    />
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Tab 4: Pilihan Jalur */}
          {activeStep === 4 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 border-b pb-2">Langkah 4: Jalur Penerimaan & Peminatan</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Pilihan Jalur Masuk</label>
                  <select
                    value={choiceData.track_id}
                    onChange={(e) => setChoiceData({ ...choiceData, track_id: e.target.value })}
                    disabled={isLocked || !!activeApplication?.track?.id}
                    className="w-full h-10 px-3 border rounded-md text-xs bg-white text-slate-900 border-slate-300"
                  >
                    {tracks.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.name} (Kuota: {t.quota} siswa)
                      </option>
                    ))}
                  </select>
                  <p className="text-[11px] text-slate-500 mt-1">
                    Jalur yang telah disubmit dikunci untuk menjaga ketepatan kuota seleksi.
                  </p>
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Catatan Tambahan / Prestasi</label>
                  <Input
                    value={choiceData.notes}
                    onChange={(e) => setChoiceData({ ...choiceData, notes: e.target.value })}
                    disabled={isLocked}
                    placeholder="Contoh: Juara 1 OSN Matematika Tingkat Kota"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Tab 5: Berkas Persyaratan */}
          {activeStep === 5 && (
            <div className="space-y-4">
              <div className="flex items-center justify-between border-b pb-2">
                <h3 className="text-sm font-bold text-slate-900">Langkah 5: Unggah Dokumen Berkas</h3>
                <span className="text-xs text-slate-500">Format PDF/JPG/PNG (Maks 2MB)</span>
              </div>

              {/* Daftar Berkas */}
              <div className="space-y-3">
                {[
                  { type: 'KK', label: 'Kartu Keluarga (KK)', req: true },
                  { type: 'AKTA', label: 'Akta Kelahiran', req: true },
                  { type: 'FOTO', label: 'Pas Foto Terbaru (3x4)', req: true },
                  { type: 'RAPOR', label: 'Scan Nilai Rapor / SKL', req: true },
                  { type: 'PRESTASI', label: 'Sertifikat Prestasi (Bagi Jalur Prestasi)', req: false },
                ].map((doc) => {
                  const uploaded = activeApplication?.documents?.find((d) => d.document_type === doc.type);
                  return (
                    <div
                      key={doc.type}
                      className="p-3.5 rounded-xl border border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                    >
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-slate-900">{doc.label}</span>
                          {doc.req && <span className="text-red-500 font-bold">*Wajib</span>}
                          {uploaded && (
                            <Badge
                              className={`text-[10px] ${
                                uploaded.status === 'valid'
                                  ? 'bg-emerald-600 text-white'
                                  : uploaded.status === 'ditolak'
                                  ? 'bg-red-600 text-white'
                                  : 'bg-blue-600 text-white'
                              }`}
                            >
                              {uploaded.status === 'valid'
                                ? 'Valid'
                                : uploaded.status === 'ditolak'
                                ? 'Ditolak (Perlu Revisi)'
                                : 'Menunggu Verifikasi'}
                            </Badge>
                          )}
                        </div>
                        {uploaded?.reject_reason && (
                          <p className="text-red-600 mt-1 text-[11px] font-medium">
                            Alasan Penolakan: {uploaded.reject_reason}
                          </p>
                        )}
                      </div>

                      <div className="flex items-center gap-2">
                        {uploaded ? (
                          <span className="text-emerald-700 font-medium flex items-center gap-1 text-[11px]">
                            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                            Dokumen Siap
                          </span>
                        ) : (
                          <label
                            className={cn(
                              'cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50 transition-colors shadow-2xs',
                              uploadingDocType === doc.type && 'opacity-60 pointer-events-none'
                            )}
                          >
                            {uploadingDocType === doc.type ? (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-brand-blue" />
                            ) : (
                              <Upload className="h-3.5 w-3.5 text-slate-500" />
                            )}
                            <span>{uploadingDocType === doc.type ? 'Mengunggah...' : 'Pilih Berkas'}</span>
                            <input
                              type="file"
                              className="hidden"
                              accept=".pdf,.jpg,.jpeg,.png,.webp"
                              disabled={uploadingDocType === doc.type || isLocked}
                              onChange={(e) => {
                                const file = e.target.files?.[0];
                                handleFileUpload(doc.type, doc.label, file);
                                e.target.value = '';
                              }}
                            />
                          </label>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Action Footer Stepper */}
          <div className="mt-8 pt-5 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              {activeStep > 1 && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setActiveStep((prev) => prev - 1)}
                  className="text-xs"
                >
                  Kembali
                </Button>
              )}
              {activeStep < 5 && (
                <Button
                  size="sm"
                  onClick={() => {
                    handleSaveStep();
                    setActiveStep((prev) => prev + 1);
                  }}
                  className="bg-brand-blue hover:bg-blue-700 text-white text-xs gap-1"
                >
                  Lanjut ke Langkah {activeStep + 1}
                  <ChevronRight className="h-3.5 w-3.5" />
                </Button>
              )}
            </div>

            <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
              {!isLocked && (
                <Button
                  variant="outline"
                  size="sm"
                  disabled={saveStepMutation.isPending}
                  onClick={handleSaveStep}
                  className="text-xs gap-1.5"
                >
                  <Save className="h-3.5 w-3.5 text-slate-500" />
                  Simpan Draf
                </Button>
              )}

              {!isLocked && (
                <Button
                  size="sm"
                  disabled={submitApplicationMutation.isPending}
                  onClick={() => {
                    if (confirm('Kirim formulir pendaftaran ini secara resmi? Setelah disubmit data akan dikunci untuk verifikasi berkas oleh panitia.')) {
                      submitApplicationMutation.mutate();
                    }
                  }}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold gap-1.5 shadow-sm"
                >
                  {submitApplicationMutation.isPending ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <Send className="h-3.5 w-3.5" />
                  )}
                  Submit Final Pendaftaran
                </Button>
              )}
            </div>
          </div>
        </div>

        {/* Modal Konfirmasi Pendaftaran Ulang (GAP-PPDB-02 / BE-PPDB-08) */}
        {isReRegisterModalOpen && (
          <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-5 animate-in fade-in zoom-in-95">
              <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                <div className="flex items-center gap-2.5">
                  <div className="h-9 w-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <CheckCircle2 className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-slate-900">Konfirmasi Daftar Ulang</h3>
                    <p className="text-xs text-slate-500">Penerimaan Peserta Didik Baru 2026/2027</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setIsReRegisterModalOpen(false)}
                  className="text-slate-400 hover:text-slate-600 text-sm font-semibold p-1"
                >
                  ✕
                </button>
              </div>

              <div className="space-y-2.5 text-xs text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div className="flex justify-between items-center">
                  <span className="text-slate-500">Nomor Registrasi:</span>
                  <span className="font-mono font-bold text-slate-900">{activeApplication?.registration_no}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-slate-500">Nama Calon Siswa:</span>
                  <span className="font-bold text-slate-900">{activeApplication?.full_name}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-slate-500">Jalur Penerimaan:</span>
                  <span className="font-semibold text-slate-900">{activeApplication?.track?.name || 'Reguler'}</span>
                </div>
              </div>

              <div className="space-y-3">
                <label className="block text-xs font-semibold text-slate-700">
                  Catatan / Pernyataan Tambahan (Opsional):
                </label>
                <textarea
                  value={reRegisterNotes}
                  onChange={(e) => setReRegisterNotes(e.target.value)}
                  placeholder="Contoh: Bersedia mematuhi tata tertib sekolah dan menghadiri kegiatan Masa Pengenalan Lingkungan Sekolah (MPLS)."
                  className="w-full h-20 p-3 text-xs border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 border-slate-300 resize-none"
                />

                <label className="flex items-start gap-2.5 cursor-pointer pt-1">
                  <input
                    type="checkbox"
                    checked={agreedToRules}
                    onChange={(e) => setAgreedToRules(e.target.checked)}
                    className="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                  />
                  <span className="text-xs text-slate-700 leading-snug">
                    Saya menyatakan bersedia mendaftar ulang dan berkomitmen mematuhi seluruh peraturan, tata tertib, serta kalender akademik yang berlaku di SMP/SMA Laboratorium UM.
                  </span>
                </label>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsReRegisterModalOpen(false)}
                  disabled={reRegisterMutation.isPending}
                >
                  Batal
                </Button>
                <Button
                  type="button"
                  size="sm"
                  disabled={!agreedToRules || reRegisterMutation.isPending}
                  onClick={() => reRegisterMutation.mutate(reRegisterNotes)}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                >
                  {reRegisterMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <CheckCircle2 className="h-4 w-4" />
                  )}
                  {reRegisterMutation.isPending ? 'Memproses...' : 'Kirim Konfirmasi Daftar Ulang'}
                </Button>
              </div>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
