'use client';

import React, { useState, useRef } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Link from 'next/link';
import {
  Shield,
  ShieldCheck,
  ShieldAlert,
  Lock,
  Copy,
  Check,
  Send,
  AlertTriangle,
  Info,
  ArrowRight,
  RefreshCw,
  PhoneCall,
  UserCheck,
} from 'lucide-react';
import { publicRelationsApi } from '../public-relations-api';
import { useAuth } from '@/providers/auth-provider';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { toast } from '@/components/ui/toast';

const complaintSchema = z.object({
  category: z.enum(
    ['bullying', 'fasilitas', 'akademik', 'pelayanan', 'pungli', 'lainnya'],
    {
      required_error: 'Kategori aduan wajib dipilih',
    }
  ),
  content: z
    .string({ required_error: 'Uraian pengaduan wajib diisi' })
    .min(10, 'Uraian pengaduan minimal 10 karakter')
    .max(5000, 'Uraian pengaduan maksimal 5000 karakter')
    .refine(
      (val) => {
        const clean = val.replace(/\s+/g, '');
        if (clean.length >= 10 && new Set(clean.split('')).size <= 2) {
          return false;
        }
        return true;
      },
      { message: 'Uraian laporan terdeteksi tidak wajar atau berisi karakter berulang' }
    ),
  is_anonymous: z.boolean().default(false),
  website_url: z.string().optional(),
});

export type ComplaintFormData = z.infer<typeof complaintSchema>;

interface CategoryOption {
  id: 'bullying' | 'fasilitas' | 'akademik' | 'pelayanan' | 'pungli' | 'lainnya';
  label: string;
  badge?: string;
  desc: string;
}

const CATEGORY_OPTIONS: CategoryOption[] = [
  {
    id: 'bullying',
    label: 'Perundungan (Bullying)',
    badge: 'Prioritas Khusus',
    desc: 'Intimidasi, ejekan, pengucilan, kekerasan fisik, pemalakan, atau cyberbullying.',
  },
  {
    id: 'akademik',
    label: 'Akademik & Pembelajaran',
    desc: 'Masalah proses belajar mengajar, tugas berlebih, atau kesulitan kurikulum.',
  },
  {
    id: 'fasilitas',
    label: 'Fasilitas & Sarpras',
    desc: 'Kerusakan sarana kelas, toilet, lab, atau fasilitas belajar lainnya.',
  },
  {
    id: 'pelayanan',
    label: 'Pelayanan & Administrasi',
    desc: 'Layanan tata usaha, perpustakaan, atau administrasi sekolah.',
  },
  {
    id: 'pungli',
    label: 'Pungutan Liar (Pungli)',
    desc: 'Permintaan biaya atau iuran yang tidak resmi di lingkungan sekolah.',
  },
  {
    id: 'lainnya',
    label: 'Aspirasi / Lainnya',
    desc: 'Saran perbaikan, ide inovasi, atau topik aduan lainnya.',
  },
] as const;

export interface ComplaintFormProps {
  minSubmitDelayMs?: number;
}

export function ComplaintForm({
  minSubmitDelayMs = process.env.NODE_ENV === 'test' ? 0 : 1500,
}: ComplaintFormProps = {}) {
  const { user, isAuthenticated } = useAuth();
  const mountedAtRef = useRef<number>(Date.now());
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submittedTicket, setSubmittedTicket] = useState<{
    ticket_code: string;
    category: string;
    is_anonymous: boolean;
  } | null>(null);
  const [isCopied, setIsCopied] = useState(false);

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    reset,
    formState: { errors },
  } = useForm<ComplaintFormData>({
    resolver: zodResolver(complaintSchema),
    defaultValues: {
      category: 'bullying',
      content: '',
      is_anonymous: false,
      website_url: '',
    },
  });

  const selectedCategory = watch('category');
  const isAnonymous = watch('is_anonymous');
  const contentValue = watch('content') || '';

  const onSubmit = async (data: ComplaintFormData) => {
    // 1. Anti-spam honeypot detection (FE-REPORT-02)
    if (data.website_url && data.website_url.trim().length > 0) {
      // Bot terdeteksi mengisi honeypot. Simulasikan respon sukses agar bot tidak mencoba variasi lain
      // tanpa menyimpan spam ke database sekolah.
      setSubmittedTicket({
        ticket_code: 'ASP-20261103-SPAM',
        category: data.category,
        is_anonymous: true,
      });
      return;
    }

    // 2. Anti-spam timing protection (< 1.5 detik)
    const timeElapsed = Date.now() - mountedAtRef.current;
    if (minSubmitDelayMs > 0 && timeElapsed < minSubmitDelayMs) {
      setSubmitError(
        'Pengiriman terlalu cepat terdeteksi oleh sistem anti-bot. Mohon luangkan waktu sejenak dan tekan kirim kembali.'
      );
      return;
    }

    setIsSubmitting(true);
    setSubmitError(null);

    try {
      const response = await publicRelationsApi.submitComplaintPublic({
        category: data.category,
        content: data.content,
        is_anonymous: Boolean(data.is_anonymous),
      });

      if (response && response.data) {
        setSubmittedTicket({
          ticket_code: response.data.ticket_code,
          category: response.data.category,
          is_anonymous: response.data.is_anonymous,
        });
        toast.success('Laporan berhasil dikirim!');
      } else {
        throw new Error('Respon server tidak valid');
      }
    } catch (err: any) {
      const errorMsg =
        err?.response?.data?.message ||
        err?.message ||
        'Gagal mengirimkan laporan. Silakan periksa koneksi Anda dan coba beberapa saat lagi.';
      setSubmitError(errorMsg);
      toast.error('Gagal mengirimkan laporan');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCopyTicket = async () => {
    if (!submittedTicket?.ticket_code) return;
    try {
      await navigator.clipboard.writeText(submittedTicket.ticket_code);
      setIsCopied(true);
      toast.success('Kode tiket disalin ke papan klip');
      setTimeout(() => setIsCopied(false), 2500);
    } catch {
      toast.error('Gagal menyalin kode tiket');
    }
  };

  const handleResetForm = () => {
    setSubmittedTicket(null);
    setSubmitError(null);
    reset({
      category: 'bullying',
      content: '',
      is_anonymous: false,
      website_url: '',
    });
    mountedAtRef.current = Date.now();
  };

  // =========================================================================
  // VIEW: SUKSES SUBMIT (TICKET CODE CARD)
  // =========================================================================
  if (submittedTicket) {
    return (
      <Card className="border-emerald-200 bg-white shadow-elevated max-w-2xl mx-auto overflow-hidden">
        <div className="bg-gradient-to-r from-emerald-600 to-teal-600 p-6 text-white text-center">
          <div className="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-xs">
            <ShieldCheck className="w-10 h-10 text-white" />
          </div>
          <h2 className="text-2xl font-bold font-pub-heading">Laporan Pengaduan Terkirim</h2>
          <p className="text-emerald-50 text-sm mt-1 max-w-md mx-auto">
            Terima kasih atas kepedulian dan keberanian Anda. Laporan Anda telah tercatat dengan aman dalam sistem pengaduan sekolah.
          </p>
        </div>

        <CardContent className="p-6 sm:p-8 space-y-6">
          <div className="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
              Kode Tiket Pelacakan Anda
            </p>
            <div className="flex items-center justify-center gap-3 my-2">
              <span
                data-testid="ticket-code-display"
                className="font-mono text-2xl sm:text-3xl font-extrabold text-pub-navy-deep tracking-wider selection:bg-pub-gold-soft"
              >
                {submittedTicket.ticket_code}
              </span>
              <button
                type="button"
                onClick={handleCopyTicket}
                aria-label="Salin Kode Tiket"
                className="p-2 text-slate-500 hover:text-pub-navy-deep hover:bg-slate-200 rounded-lg transition-colors"
                title="Salin Kode Tiket"
              >
                {isCopied ? <Check className="w-5 h-5 text-emerald-600" /> : <Copy className="w-5 h-5" />}
              </button>
            </div>
            {isCopied && <p className="text-xs text-emerald-600 font-medium">Kode tiket berhasil disalin!</p>}
            <p className="text-xs text-slate-500 mt-2">
              Kategori: <strong className="capitalize">{submittedTicket.category}</strong> •{' '}
              {submittedTicket.is_anonymous ? (
                <span className="inline-flex items-center gap-1 text-slate-600">
                  <Lock className="w-3 h-3" /> Anonim
                </span>
              ) : (
                <span className="text-pub-navy">Teridentifikasi</span>
              )}
            </p>
          </div>

          <div className="bg-amber-50/80 border border-amber-200 rounded-lg p-4 text-xs text-amber-900 flex items-start gap-3">
            <Info className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <div>
              <p className="font-semibold mb-0.5">Penting: Harap simpan kode tiket ini</p>
              <p className="leading-relaxed">
                Untuk menjaga privasi dan kerahasiaan Anda, kode tiket ini tidak akan dikirimkan melalui surel atau pesan singkat jika Anda melapor secara anonim. Anda memerlukan kode ini untuk memantau status tindak lanjut penanganan oleh Guru BK atau Kepala Sekolah.
              </p>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row gap-3 pt-2">
            <Link
              href={`/lapor/lacak?kode=${submittedTicket.ticket_code}`}
              className="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg font-medium text-sm bg-pub-navy text-white hover:bg-pub-navy-deep transition-colors shadow-xs"
            >
              <span>Lacak Perkembangan Status</span>
              <ArrowRight className="w-4 h-4" />
            </Link>
            <Button
              type="button"
              variant="outline"
              onClick={handleResetForm}
              className="sm:w-auto"
            >
              Kirim Laporan Lain
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  // =========================================================================
  // VIEW: FORM PENGADUAN
  // =========================================================================
  return (
    <div className="space-y-6 max-w-3xl mx-auto">
      {/* DISCLAIMER & DARURAT CALLOUT */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Kerahasiaan Pelapor */}
        <div className="bg-blue-50/70 border border-blue-200 rounded-xl p-4 text-xs text-blue-900 flex items-start gap-3">
          <div className="p-1.5 bg-blue-100 rounded-lg shrink-0">
            <Shield className="w-4 h-4 text-blue-700" />
          </div>
          <div>
            <h4 className="font-semibold text-blue-950 mb-1">Perlindungan Kerahasiaan</h4>
            <p className="leading-relaxed text-blue-800">
              SMP Laboratorium UM menjamin kerahasiaan identitas dan isi laporan Anda. Laporan ditangani langsung oleh Tim Bimbingan Konseling dan Pimpinan Sekolah.
            </p>
          </div>
        </div>

        {/* Tanggap Darurat */}
        <div className="bg-red-50/70 border border-red-200 rounded-xl p-4 text-xs text-red-900 flex items-start gap-3">
          <div className="p-1.5 bg-red-100 rounded-lg shrink-0">
            <PhoneCall className="w-4 h-4 text-red-700" />
          </div>
          <div>
            <h4 className="font-semibold text-red-950 mb-1">Bantuan Darurat Langsung</h4>
            <p className="leading-relaxed text-red-800">
              Bila ada ancaman bahaya fisik segera, jangan menunggu formulir. Hubungi Hotline Darurat Sekolah: <strong className="font-semibold">(0341) 551-312</strong> atau ruang BK.
            </p>
          </div>
        </div>
      </div>

      {/* FORM CARD */}
      <Card className="border-slate-200 shadow-elevated bg-white">
        <CardHeader className="pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <span className="p-2 bg-pub-navy/10 text-pub-navy rounded-lg">
              <ShieldAlert className="w-5 h-5 text-pub-navy" />
            </span>
            <div>
              <CardTitle className="text-xl font-bold font-pub-heading text-slate-900">
                Formulir Lapor Perundungan & Pengaduan
              </CardTitle>
              <CardDescription className="text-xs text-slate-500 mt-0.5">
                Sampaikan kejadian atau aspirasi Anda. Kami siap mendengar dan menindaklanjuti secara adil.
              </CardDescription>
            </div>
          </div>
        </CardHeader>

        <CardContent className="p-6">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6" noValidate>
            {/* SERVER ERROR ALERT */}
            {submitError && (
              <div
                role="alert"
                className="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 text-sm flex items-start gap-3"
              >
                <AlertTriangle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                <div className="flex-1">
                  <p className="font-medium">Pengiriman laporan gagal</p>
                  <p className="text-xs mt-0.5 text-red-700">{submitError}</p>
                </div>
              </div>
            )}

            {/* HONEYPOT ANTI-SPAM (FE-REPORT-02) */}
            <div
              style={{ position: 'absolute', opacity: 0, zIndex: -1, width: 0, height: 0, overflow: 'hidden' }}
              aria-hidden="true"
            >
              <label htmlFor="website_url_check">Jangan isi kolom ini</label>
              <input
                id="website_url_check"
                type="text"
                tabIndex={-1}
                autoComplete="off"
                {...register('website_url')}
              />
            </div>

            {/* 1. KATEGORI ADUAN */}
            <div className="space-y-2">
              <label className="block text-sm font-semibold text-slate-800">
                Pilih Kategori Masalah <span className="text-red-500">*</span>
              </label>
              <p className="text-xs text-slate-500">
                Pilih kategori yang paling sesuai agar laporan dapat diarahkan ke pihak penanggung jawab yang tepat.
              </p>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                {CATEGORY_OPTIONS.map((cat) => {
                  const isSelected = selectedCategory === cat.id;
                  return (
                    <button
                      type="button"
                      key={cat.id}
                      onClick={() => setValue('category', cat.id as any, { shouldValidate: true })}
                      className={`text-left p-3.5 rounded-xl border transition-all flex flex-col justify-between ${
                        isSelected
                          ? cat.id === 'bullying'
                            ? 'border-red-400 bg-red-50/40 ring-2 ring-red-500/20'
                            : 'border-pub-navy bg-pub-navy-subtle ring-2 ring-pub-navy/20'
                          : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/60 bg-white'
                      }`}
                    >
                      <div className="flex items-center justify-between gap-2 mb-1">
                        <span
                          className={`text-sm font-semibold ${
                            isSelected
                              ? cat.id === 'bullying'
                                ? 'text-red-900'
                                : 'text-pub-navy-deep'
                              : 'text-slate-800'
                          }`}
                        >
                          {cat.label}
                        </span>
                        {cat.badge && (
                          <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 border border-red-200 uppercase tracking-wider">
                            {cat.badge}
                          </span>
                        )}
                      </div>
                      <p className="text-xs text-slate-500 leading-relaxed">{cat.desc}</p>
                    </button>
                  );
                })}
              </div>
              <input type="hidden" {...register('category')} />
              {errors.category && (
                <p className="text-xs text-red-600 font-medium">{errors.category.message}</p>
              )}
            </div>

            {/* 2. URAIAN LAPORAN */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label htmlFor="complaint-content" className="block text-sm font-semibold text-slate-800">
                  Uraian Kejadian / Isi Pengaduan <span className="text-red-500">*</span>
                </label>
                <span
                  className={`text-xs ${
                    contentValue.length > 5000
                      ? 'text-red-600 font-bold'
                      : contentValue.length >= 10
                      ? 'text-slate-500'
                      : 'text-slate-400'
                  }`}
                >
                  {contentValue.length} / 5000 karakter (min. 10)
                </span>
              </div>
              <textarea
                id="complaint-content"
                rows={5}
                {...register('content')}
                placeholder="Ceritakan kejadian secara jelas: apa yang terjadi, kapan, di mana, dan siapa saja pihak yang terlibat (bila diketahui)..."
                className={`w-full p-3.5 text-sm rounded-lg border bg-white focus:outline-none focus:ring-2 transition-all leading-relaxed ${
                  errors.content
                    ? 'border-red-400 focus:ring-red-300'
                    : 'border-slate-300 focus:border-pub-navy focus:ring-pub-navy/20'
                }`}
              />
              {errors.content ? (
                <p role="alert" className="text-xs text-red-600 font-medium">
                  {errors.content.message}
                </p>
              ) : (
                <p className="text-xs text-slate-400">
                  Hindari mencantumkan kata sandi atau informasi rahasia perbankan. Ceritakan kronologi seobjektif mungkin.
                </p>
              )}
            </div>

            {/* 3. STATUS IDENTITAS & TOGGLE ANONIM */}
            <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3">
              <div className="flex items-start gap-3">
                <input
                  type="checkbox"
                  id="toggle-anonymous"
                  {...register('is_anonymous')}
                  className="mt-1 h-4 w-4 rounded border-slate-300 text-pub-navy focus:ring-pub-navy cursor-pointer"
                />
                <label htmlFor="toggle-anonymous" className="cursor-pointer select-none flex-1">
                  <div className="flex items-center gap-1.5">
                    <span className="text-sm font-semibold text-slate-800">
                      Kirim sebagai Laporan Anonim (Rahasiakan Identitas)
                    </span>
                    <Lock className="w-3.5 h-3.5 text-slate-400" />
                  </div>
                  <p className="text-xs text-slate-500 mt-0.5 leading-relaxed">
                    Bila dicentang, nama akun, surel, alamat IP, dan data peramban Anda sama sekali tidak akan dicatat atau disimpan di basis data sekolah.
                  </p>
                </label>
              </div>

              {/* INDIKATOR STATUS PENGGUNA TERKAIT SESI */}
              {isAuthenticated && user ? (
                <div
                  className={`rounded-lg p-3 text-xs flex items-center gap-2.5 transition-colors ${
                    isAnonymous
                      ? 'bg-amber-100/60 text-amber-900 border border-amber-200'
                      : 'bg-emerald-50 text-emerald-900 border border-emerald-200'
                  }`}
                >
                  {isAnonymous ? (
                    <>
                      <Lock className="w-4 h-4 text-amber-700 shrink-0" />
                      <p>
                        Anda sedang login sebagai <strong>{user.username}</strong>, tetapi{' '}
                        <strong>mode anonim aktif</strong>. Identitas Anda tidak akan dikaitkan dengan laporan ini.
                      </p>
                    </>
                  ) : (
                    <>
                      <UserCheck className="w-4 h-4 text-emerald-700 shrink-0" />
                      <p>
                        Dilaporkan sebagai civitas terdaftar: <strong>{user.username}</strong> ({user.email}). Identitas ini hanya dapat dibuka oleh Kepala Sekolah & Guru BK berwenang.
                      </p>
                    </>
                  )}
                </div>
              ) : (
                <div className="rounded-lg p-3 text-xs bg-slate-100 text-slate-600 border border-slate-200 flex items-center gap-2">
                  <Info className="w-4 h-4 text-slate-500 shrink-0" />
                  <p>
                    Anda tidak sedang login. Laporan ini dikirim melalui kanal publik tamu.
                  </p>
                </div>
              )}
            </div>

            {/* 4. TOMBOL KIRIM */}
            <div className="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="text-xs text-slate-400 text-center sm:text-left">
                Semua laporan diproses dengan asas praduga tak bersalah dan kerahasiaan penuh.
              </div>
              <Button
                type="submit"
                disabled={isSubmitting}
                className="w-full sm:w-auto bg-pub-navy hover:bg-pub-navy-deep text-white px-7 py-2.5 rounded-lg flex items-center justify-center gap-2 font-medium transition-all shadow-sm"
              >
                {isSubmitting ? (
                  <>
                    <RefreshCw className="w-4 h-4 animate-spin" />
                    <span>Mengirimkan Laporan...</span>
                  </>
                ) : (
                  <>
                    <Send className="w-4 h-4" />
                    <span>Kirim Laporan Pengaduan</span>
                  </>
                )}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
