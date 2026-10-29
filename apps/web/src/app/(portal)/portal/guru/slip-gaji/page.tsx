'use client';

import React from 'react';
import Link from 'next/link';
import { useTeacherSalarySlips } from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  DollarSign,
  ArrowLeft,
  Download,
  CheckCircle2,
  Calendar,
  FileText,
  ShieldCheck,
} from 'lucide-react';

export default function TeacherSalarySlipPage() {
  const { data: slips = [], isLoading } = useTeacherSalarySlips();

  const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(val);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/guru"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <DollarSign className="h-6 w-6 text-brand-blue" />
            <span>Rekapitulasi & Slip Gaji Guru</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Informasi rincian honorarium, tunjangan jam mengajar, dan unduh slip gaji bulanan resmi.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="text-xs font-bold px-3 py-1 bg-emerald-50 text-status-success border-emerald-200">
            Payroll Terintegrasi
          </Badge>
        </div>
      </div>

      {/* Salary Slips List */}
      {isLoading ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat riwayat slip gaji..." size="lg" />
        </div>
      ) : slips.length === 0 ? (
        <EmptyState
          title="Belum Ada Slip Gaji"
          description="Slip gaji bulanan Anda belum diterbitkan oleh bagian Keuangan sekolah."
          icon={<DollarSign className="h-8 w-8 text-slate-400" />}
        />
      ) : (
        <div className="space-y-4">
          {slips.map((slip) => (
            <Card key={slip.id} className="border-slate-200 hover:border-brand-blue hover:shadow-xs transition-all overflow-hidden">
              <div className="h-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 w-full" />
              <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                  <div>
                    <div className="flex items-center gap-2">
                      <CardTitle className="text-lg font-black text-slate-900">
                        Periode: {slip.periodMonth}
                      </CardTitle>
                      <Badge className="bg-emerald-50 text-status-success border-emerald-200 text-[10px] font-bold">
                        Ditransfer
                      </Badge>
                    </div>
                    <CardDescription className="text-xs text-slate-400 mt-0.5">
                      No. Slip: {slip.slipNumber} • Tanggal Bayar: {slip.paymentDate}
                    </CardDescription>
                  </div>

                  <div className="text-right self-start sm:self-center">
                    <span className="text-[10px] text-slate-400 font-bold uppercase block">Gaji Bersih (Take-Home)</span>
                    <span className="text-xl font-black text-slate-900">
                      {formatCurrency(slip.totalNetSalary)}
                    </span>
                  </div>
                </div>
              </CardHeader>

              <CardContent className="pt-2 text-xs border-t border-slate-100 mt-2 space-y-3">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <div>
                    <span className="text-slate-400 text-[11px] block">Gaji Pokok:</span>
                    <span className="font-bold text-slate-800">{formatCurrency(slip.basicSalary)}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 text-[11px] block">Tunjangan Mengajar:</span>
                    <span className="font-bold text-brand-blue">{formatCurrency(slip.teachingAllowance)}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 text-[11px] block">Tunjangan Fungsional:</span>
                    <span className="font-bold text-slate-800">{formatCurrency(slip.functionalAllowance)}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 text-[11px] block">Potongan (BPJS/Koperasi):</span>
                    <span className="font-bold text-status-danger">-{formatCurrency(slip.deduction)}</span>
                  </div>
                </div>

                <div className="flex justify-end pt-1">
                  <a
                    href={slip.downloadUrl || `/api/v1/salary-slips/${slip.id}/download`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-white hover:bg-blue-50 text-brand-blue text-xs font-bold transition-colors shadow-2xs"
                  >
                    <Download className="h-4 w-4" />
                    <span>Unduh Slip Gaji Resmi (PDF)</span>
                  </a>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
