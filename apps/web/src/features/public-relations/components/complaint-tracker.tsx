'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import {
  Search,
  Clock,
  Shield,
  ShieldCheck,
  AlertCircle,
  RefreshCw,
  CheckCircle2,
  FileText,
  ArrowLeft,
  Calendar,
  MessageSquare,
  Lock,
} from 'lucide-react';
import { publicRelationsApi } from '../public-relations-api';
import { PublicComplaintTrackResult } from '../types';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

const STATUS_CONFIG: Record<
  string,
  { label: string; color: string; badgeVariant: string; desc: string }
> = {
  baru: {
    label: 'Menunggu Ditinjau',
    color: 'text-amber-700 bg-amber-50 border-amber-200',
    badgeVariant: 'bg-amber-100 text-amber-800 border-amber-300',
    desc: 'Laporan telah diterima dan masuk dalam antrean evaluasi awal oleh Tim Bimbingan Konseling.',
  },
  ditinjau: {
    label: 'Sedang Ditinjau',
    color: 'text-blue-700 bg-blue-50 border-blue-200',
    badgeVariant: 'bg-blue-100 text-blue-800 border-blue-300',
    desc: 'Tim BK / Kepala Sekolah sedang menganalisis detail laporan untuk menentukan langkah penanganan.',
  },
  ditindaklanjuti: {
    label: 'Sedang Ditindaklanjuti',
    color: 'text-indigo-700 bg-indigo-50 border-indigo-200',
    badgeVariant: 'bg-indigo-100 text-indigo-800 border-indigo-300',
    desc: 'Langkah penyelesaian kasus sedang aktif dijalankan bersama pihak-pihak terkait secara aman.',
  },
  selesai: {
    label: 'Selesai Ditangani',
    color: 'text-emerald-700 bg-emerald-50 border-emerald-200',
    badgeVariant: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    desc: 'Penanganan aduan telah diselesaikan sesuai protokol perlindungan dan etika sekolah.',
  },
  ditolak: {
    label: 'Ditolak / Diarsipkan',
    color: 'text-red-700 bg-red-50 border-red-200',
    badgeVariant: 'bg-red-100 text-red-800 border-red-300',
    desc: 'Laporan tidak dapat diproses lebih lanjut (misal: informasi tidak memadai atau bukan ranah sekolah).',
  },
};

interface ComplaintTrackerProps {
  initialCode?: string;
}

export function ComplaintTracker({ initialCode }: ComplaintTrackerProps) {
  const searchParams = useSearchParams();
  const queryCode = searchParams.get('kode') || initialCode || '';

  const [ticketInput, setTicketInput] = useState(queryCode);
  const [currentTicket, setCurrentTicket] = useState(queryCode);
  const [isLoading, setIsLoading] = useState(false);
  const [trackResult, setTrackResult] = useState<PublicComplaintTrackResult | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const performTrack = useCallback(async (code: string) => {
    const trimmed = code.trim().toUpperCase();
    if (!trimmed) {
      setErrorMessage('Silakan masukkan kode tiket pengaduan');
      setTrackResult(null);
      return;
    }

    setIsLoading(true);
    setErrorMessage(null);

    try {
      const response = await publicRelationsApi.trackComplaintPublic(trimmed);
      if (response && response.data) {
        setTrackResult(response.data);
        setCurrentTicket(trimmed);
      } else {
        throw new Error('Data tidak ditemukan');
      }
    } catch (err: any) {
      setTrackResult(null);
      if (err?.response?.status === 404 || err?.status === 404) {
        setErrorMessage(
          'Data pengaduan dengan kode tiket tersebut tidak ditemukan. Pastikan format penulisan sudah benar (contoh: ASP-20261103-XXXX).'
        );
      } else {
        setErrorMessage(
          err?.response?.data?.message ||
            'Terjadi kendala saat memeriksa kode tiket. Silakan coba kembali sesaat lagi.'
        );
      }
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (queryCode) {
      setTicketInput(queryCode);
      performTrack(queryCode);
    }
  }, [queryCode, performTrack]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    performTrack(ticketInput);
  };

  const formatDate = (isoString?: string) => {
    if (!isoString) return '-';
    try {
      return new Date(isoString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return isoString;
    }
  };

  return (
    <div className="space-y-6 max-w-3xl mx-auto">
      {/* SEARCH CARD */}
      <Card className="border-slate-200 shadow-elevated bg-white">
        <CardHeader className="pb-3 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <span className="p-2 bg-pub-navy/10 text-pub-navy rounded-lg">
              <Search className="w-5 h-5 text-pub-navy" />
            </span>
            <div>
              <CardTitle className="text-xl font-bold font-pub-heading text-slate-900">
                Lacak Status Pengaduan
              </CardTitle>
              <CardDescription className="text-xs text-slate-500 mt-0.5">
                Gunakan kode tiket (ASP-YYYYMMDD-XXXX) yang Anda dapatkan saat mengirimkan laporan.
              </CardDescription>
            </div>
          </div>
        </CardHeader>

        <CardContent className="p-6">
          <form onSubmit={handleSearchSubmit} className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3">
              <div className="relative flex-1">
                <input
                  type="text"
                  value={ticketInput}
                  onChange={(e) => setTicketInput(e.target.value)}
                  placeholder="Contoh: ASP-20261103-XXXX"
                  aria-label="Kode Tiket Pengaduan"
                  className="w-full uppercase font-mono tracking-wider pl-4 pr-10 py-3 text-sm rounded-lg border border-slate-300 focus:outline-none focus:border-pub-navy focus:ring-2 focus:ring-pub-navy/20 bg-white"
                />
                {ticketInput && (
                  <button
                    type="button"
                    onClick={() => setTicketInput('')}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600 p-1"
                  >
                    ✕
                  </button>
                )}
              </div>
              <Button
                type="submit"
                disabled={isLoading}
                className="bg-pub-navy hover:bg-pub-navy-deep text-white px-6 py-3 rounded-lg flex items-center justify-center gap-2 font-medium shrink-0 shadow-sm"
              >
                {isLoading ? (
                  <>
                    <RefreshCw className="w-4 h-4 animate-spin" />
                    <span>Memeriksa...</span>
                  </>
                ) : (
                  <>
                    <Search className="w-4 h-4" />
                    <span>Lacak Tiket</span>
                  </>
                )}
              </Button>
            </div>
            <p className="text-xs text-slate-400">
              Pelacakan publik tidak menampilkan identitas pelapor untuk menjamin asas perlindungan privasi.
            </p>
          </form>
        </CardContent>
      </Card>

      {/* ERROR STATE */}
      {errorMessage && (
        <Card className="border-red-200 bg-red-50/50 p-6 text-center">
          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <AlertCircle className="w-6 h-6 text-red-600" />
          </div>
          <h3 className="text-base font-semibold text-red-900 mb-1">Tiket Tidak Ditemukan</h3>
          <p className="text-xs text-red-700 max-w-md mx-auto leading-relaxed">{errorMessage}</p>
          <div className="mt-4 pt-3 border-t border-red-200/60 flex items-center justify-center gap-3">
            <Link
              href="/lapor"
              className="text-xs font-semibold text-pub-navy hover:underline inline-flex items-center gap-1"
            >
              <ArrowLeft className="w-3.5 h-3.5" />
              <span>Buat Laporan Baru</span>
            </Link>
          </div>
        </Card>
      )}

      {/* SUCCESS RESULT VIEW */}
      {trackResult && (
        <div className="space-y-6 animate-fade-in" data-testid="track-result-card">
          {/* TICKET SUMMARY CARD */}
          <Card className="border-slate-200 shadow-elevated bg-white overflow-hidden">
            <div className="bg-slate-900 text-white p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <div className="flex items-center gap-2 text-xs text-slate-400 uppercase tracking-wider mb-1 font-semibold">
                  <ShieldCheck className="w-4 h-4 text-emerald-400" />
                  <span>Kode Tiket Resmi</span>
                </div>
                <h2 className="font-mono text-2xl font-bold tracking-wider text-white">
                  {trackResult.ticket_code}
                </h2>
              </div>

              <div className="flex items-center gap-2">
                <span className="text-xs text-slate-300">Status Saat Ini:</span>
                <span
                  className={`px-3 py-1 rounded-full text-xs font-bold border ${
                    STATUS_CONFIG[trackResult.status]?.badgeVariant ||
                    'bg-slate-800 text-slate-200 border-slate-700'
                  }`}
                >
                  {STATUS_CONFIG[trackResult.status]?.label || trackResult.status}
                </span>
              </div>
            </div>

            <CardContent className="p-6 space-y-6">
              {/* STATUS EXPLANATION BOX */}
              <div
                className={`p-4 rounded-xl border text-xs leading-relaxed ${
                  STATUS_CONFIG[trackResult.status]?.color || 'bg-slate-50 border-slate-200 text-slate-700'
                }`}
              >
                <div className="flex items-center gap-1.5 font-bold mb-1">
                  <Clock className="w-4 h-4" />
                  <span>Tahapan: {STATUS_CONFIG[trackResult.status]?.label || trackResult.status}</span>
                </div>
                <p>{STATUS_CONFIG[trackResult.status]?.desc}</p>
              </div>

              {/* DETAILS GRID */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div className="bg-slate-50 p-3.5 rounded-lg border border-slate-200/80">
                  <span className="text-slate-400 block mb-0.5">Kategori Masalah</span>
                  <span className="font-semibold text-slate-800 capitalize text-sm">
                    {trackResult.category === 'bullying'
                      ? 'Perundungan (Bullying)'
                      : trackResult.category}
                  </span>
                </div>
                <div className="bg-slate-50 p-3.5 rounded-lg border border-slate-200/80">
                  <span className="text-slate-400 block mb-0.5">Waktu Laporan Masuk</span>
                  <span className="font-semibold text-slate-800 text-sm">
                    {formatDate(trackResult.created_at)}
                  </span>
                </div>
              </div>

              {/* ISI LAPORAN */}
              <div className="space-y-1.5">
                <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                  <FileText className="w-4 h-4 text-slate-400" />
                  <span>Isi Uraian Laporan</span>
                </div>
                <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                  {trackResult.content}
                </div>
              </div>

              {/* TIMELINE SECTION */}
              <div className="space-y-4 pt-2">
                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                  <div className="flex items-center gap-1.5 text-sm font-bold text-slate-900">
                    <MessageSquare className="w-4 h-4 text-pub-navy" />
                    <span>Kronologi & Tanggapan Penanganan</span>
                  </div>
                  <span className="text-xs text-slate-400">
                    {trackResult.timeline?.length || 0} catatan
                  </span>
                </div>

                {!trackResult.timeline || trackResult.timeline.length === 0 ? (
                  <div className="p-6 text-center bg-slate-50 border border-dashed border-slate-200 rounded-xl text-slate-500 text-xs">
                    <Clock className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                    <p className="font-medium text-slate-700">Menunggu Tanggapan Tim Sekolah</p>
                    <p className="mt-0.5 text-slate-400">
                      Laporan Anda telah berhasil masuk ke antrean dan sedang dalam tahap verifikasi awal Guru BK. Perkembangan penanganan akan diperbarui di sini.
                    </p>
                  </div>
                ) : (
                  <div className="relative pl-6 space-y-6 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                    {trackResult.timeline.map((event, index) => (
                      <div key={index} className="relative group">
                        {/* Dot indicator */}
                        <div className="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 border-white bg-pub-navy group-hover:scale-110 transition-transform shadow-xs flex items-center justify-center">
                          <div className="w-1.5 h-1.5 rounded-full bg-white" />
                        </div>

                        <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs group-hover:border-slate-300 transition-colors">
                          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-2">
                            <span className="text-xs font-semibold text-pub-navy uppercase tracking-wider">
                              {event.event_type === 'created'
                                ? 'Laporan Dibuat'
                                : event.event_type === 'status_update'
                                ? 'Pembaruan Status Penanganan'
                                : 'Catatan Tindak Lanjut'}
                            </span>
                            <span className="text-[11px] text-slate-400">
                              {formatDate(event.created_at)}
                            </span>
                          </div>
                          <p className="text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">
                            {event.response_text}
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* BOTTOM ACTIONS */}
              <div className="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                <Link
                  href="/lapor"
                  className="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 text-xs font-semibold text-pub-navy hover:underline"
                >
                  <ArrowLeft className="w-4 h-4" />
                  <span>Kirim Laporan Pengaduan Lain</span>
                </Link>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => performTrack(currentTicket)}
                  className="w-full sm:w-auto text-xs"
                >
                  <RefreshCw className="w-3.5 h-3.5 mr-1.5" />
                  <span>Segarkan Status</span>
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
