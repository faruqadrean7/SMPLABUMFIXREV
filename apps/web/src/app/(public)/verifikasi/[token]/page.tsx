'use client';

import React, { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { AlertCircle, CheckCircle2, FileCheck2, Loader2, RefreshCw, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { apiClient } from '@/lib/api/api-client';

interface VerificationPayload {
  document_type?: string;
  is_valid: boolean;
  status: string;
  decision?: string | null;
  verification_token: string;
  document_title: string;
  school_name: string;
  registration_no?: string;
  applicant?: {
    name?: string;
  };
  student?: {
    nis?: string;
    nisn?: string;
    name?: string;
    class?: string;
  };
  period?: {
    semester?: string;
    academic_year?: string;
  };
  wave?: string;
  track?: string;
  submitted_at?: string;
  approval?: {
    approved_by?: string;
    approved_at?: string;
  };
  message: string;
}

export default function PublicVerificationPage() {
  const params = useParams<{ token: string }>();
  const token = typeof params?.token === 'string' ? params.token : '';

  const [data, setData] = useState<VerificationPayload | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const verifyToken = useCallback(async () => {
    if (!token) {
      setErrorMessage('Token verifikasi dokumen tidak ditemukan.');
      setIsLoading(false);
      return;
    }

    setIsLoading(true);
    setErrorMessage(null);
    try {
      const res = await apiClient.get<{ data: VerificationPayload }>(
        `/public/verify/${encodeURIComponent(token)}`
      );
      setData(res.data?.data ?? null);
    } catch (err: any) {
      const msg =
        err?.response?.data?.message ||
        err?.response?.data?.error?.message ||
        'Dokumen tidak ditemukan atau token verifikasi QR tidak valid.';
      setErrorMessage(msg);
      setData(null);
    } finally {
      setIsLoading(false);
    }
  }, [token]);

  useEffect(() => {
    verifyToken();
  }, [verifyToken]);

  return (
    <div className="min-h-screen bg-slate-50 py-12 px-4 sm:px-6">
      <div className="max-w-xl mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="bg-slate-900 text-white px-6 py-5 flex items-center gap-3">
          <div className="h-10 w-10 rounded-xl bg-white/10 flex items-center justify-center">
            <FileCheck2 className="h-5 w-5" />
          </div>
          <div>
            <h1 className="text-base font-bold">Verifikasi Keaslian Dokumen Sekolah</h1>
            <p className="text-xs text-slate-300">Validasi resmi tanda tangan & bukti digital SISTLABUM</p>
          </div>
        </div>

        <div className="p-6 sm:p-8">
          {isLoading ? (
            <div className="py-10 text-center space-y-3">
              <Loader2 className="h-8 w-8 animate-spin mx-auto text-brand-blue" />
              <p className="text-sm text-slate-600 font-medium">Memverifikasi keaslian token dokumen...</p>
            </div>
          ) : errorMessage ? (
            <div className="text-center space-y-4 py-4">
              <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">
                <ShieldAlert className="h-6 w-6" />
              </div>
              <h2 className="text-base font-bold text-slate-900">Dokumen Tidak Terverifikasi</h2>
              <p className="text-sm text-slate-600 leading-relaxed">{errorMessage}</p>
              <div className="flex items-center justify-center gap-3 pt-2">
                <Button variant="outline" size="sm" onClick={verifyToken}>
                  <RefreshCw className="h-4 w-4 mr-1.5" />
                  Coba Lagi
                </Button>
                <Link
                  href="/"
                  className="inline-flex items-center justify-center h-9 px-4 rounded-lg bg-slate-900 text-white text-xs font-medium hover:bg-slate-800"
                >
                  Ke Beranda
                </Link>
              </div>
            </div>
          ) : data ? (
            <div className="space-y-5">
              <div
                className={`p-4 rounded-xl border flex items-start gap-3 ${
                  data.is_valid
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
                    : 'bg-amber-50 border-amber-200 text-amber-900'
                }`}
              >
                {data.is_valid ? (
                  <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
                ) : (
                  <AlertCircle className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                )}
                <div className="text-xs leading-relaxed">
                  <p className="font-bold text-sm">{data.document_title}</p>
                  <p className="mt-1">{data.message}</p>
                </div>
              </div>

              <dl className="divide-y divide-slate-100 border border-slate-200 rounded-xl text-xs">
                <div className="px-4 py-3 flex justify-between gap-4">
                  <dt className="text-slate-500">Sekolah Penerbit</dt>
                  <dd className="font-semibold text-slate-900 text-right">{data.school_name}</dd>
                </div>
                <div className="px-4 py-3 flex justify-between gap-4">
                  <dt className="text-slate-500">Status Dokumen</dt>
                  <dd className="font-semibold text-slate-900 uppercase text-right">{data.status}</dd>
                </div>

                {data.document_type === 'ppdb_receipt' ? (
                  <>
                    <div className="px-4 py-3 flex justify-between gap-4">
                      <dt className="text-slate-500">Nomor Pendaftaran</dt>
                      <dd className="font-mono font-semibold text-slate-900 text-right">
                        {data.registration_no}
                      </dd>
                    </div>
                    <div className="px-4 py-3 flex justify-between gap-4">
                      <dt className="text-slate-500">Nama Calon Siswa</dt>
                      <dd className="font-semibold text-slate-900 text-right">
                        {data.applicant?.name || '-'}
                      </dd>
                    </div>
                    {data.wave && (
                      <div className="px-4 py-3 flex justify-between gap-4">
                        <dt className="text-slate-500">Gelombang / Jalur</dt>
                        <dd className="font-semibold text-slate-900 text-right">
                          {data.wave}
                          {data.track ? ` • ${data.track}` : ''}
                        </dd>
                      </div>
                    )}
                  </>
                ) : (
                  <>
                    <div className="px-4 py-3 flex justify-between gap-4">
                      <dt className="text-slate-500">Nama Siswa</dt>
                      <dd className="font-semibold text-slate-900 text-right">
                        {data.student?.name || '-'}
                      </dd>
                    </div>
                    {data.student?.class && (
                      <div className="px-4 py-3 flex justify-between gap-4">
                        <dt className="text-slate-500">Kelas</dt>
                        <dd className="font-semibold text-slate-900 text-right">{data.student.class}</dd>
                      </div>
                    )}
                    {data.period?.semester && (
                      <div className="px-4 py-3 flex justify-between gap-4">
                        <dt className="text-slate-500">Periode</dt>
                        <dd className="font-semibold text-slate-900 text-right">
                          {data.period.semester}
                          {data.period.academic_year ? ` (${data.period.academic_year})` : ''}
                        </dd>
                      </div>
                    )}
                    {data.approval?.approved_by && (
                      <div className="px-4 py-3 flex justify-between gap-4">
                        <dt className="text-slate-500">Disahkan Oleh</dt>
                        <dd className="font-semibold text-slate-900 text-right">
                          {data.approval.approved_by}
                        </dd>
                      </div>
                    )}
                  </>
                )}
              </dl>

              <div className="pt-2 text-center">
                <Link href="/" className="text-xs font-medium text-brand-blue hover:underline">
                  Kembali ke Beranda Sekolah
                </Link>
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
