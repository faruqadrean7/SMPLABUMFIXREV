'use client';

import React, { useState } from 'react';
import { TeacherItem, TeacherSalarySlipFormValues, TeacherSalarySlipItem } from '@/types/master';
import { teachersApi } from '@/features/master/teachers-api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, Upload, FileText, AlertCircle, CheckCircle, Loader2 } from 'lucide-react';

export interface TeacherSalarySlipDialogProps {
  isOpen: boolean;
  onClose: () => void;
  teacher: TeacherItem | null;
  onSuccess: (newSlip: TeacherSalarySlipItem) => void;
}

const MONTH_NAMES = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

export function TeacherSalarySlipDialog({
  isOpen,
  onClose,
  teacher,
  onSuccess,
}: TeacherSalarySlipDialogProps) {
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;

  const [periodMonth, setPeriodMonth] = useState<number>(currentMonth);
  const [periodYear, setPeriodYear] = useState<number>(currentYear);
  const [paymentDate, setPaymentDate] = useState<string>(
    new Date().toISOString().split('T')[0]
  );
  const [basicSalary, setBasicSalary] = useState<number>(4500000);
  const [allowances, setAllowances] = useState<number>(1500000);
  const [deductions, setDeductions] = useState<number>(200000);
  const [notes, setNotes] = useState<string>('Ditransfer via Bank Jatim Syariah');
  const [file, setFile] = useState<File | null>(null);

  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  if (!isOpen || !teacher) return null;

  const netSalary = Math.max(0, basicSalary + allowances - deductions);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const selectedFile = e.target.files[0];
      if (selectedFile.size > 10 * 1024 * 1024) {
        setErrorMessage('Ukuran berkas tidak boleh melebihi 10 MB.');
        return;
      }
      setFile(selectedFile);
      setErrorMessage(null);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!periodMonth || periodMonth < 1 || periodMonth > 12) {
      setErrorMessage('Bulan periode tidak valid.');
      return;
    }

    if (!periodYear || periodYear < 2000) {
      setErrorMessage('Tahun periode tidak valid.');
      return;
    }

    if (!file) {
      setErrorMessage('Berkas dokumen slip gaji (PDF) wajib diunggah.');
      return;
    }

    setIsLoading(true);
    try {
      const payload: TeacherSalarySlipFormValues = {
        period_month: periodMonth,
        period_year: periodYear,
        payment_date: paymentDate,
        basic_salary: basicSalary,
        allowances: allowances,
        deductions: deductions,
        net_salary: netSalary,
        notes: notes || undefined,
        status: 'published',
        file: file,
      };

      const result = await teachersApi.createSalarySlip(teacher.id, payload);
      onSuccess(result);
      onClose();
    } catch (err: any) {
      const msg =
        err?.response?.data?.message ||
        err?.message ||
        'Gagal mengunggah slip gaji. Pastikan periode belum pernah diunggah.';
      setErrorMessage(msg);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div className="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-elevated border border-slate-200 my-8">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
              <Upload className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-slate-900">Upload Slip Gaji Guru</h2>
              <p className="text-xs text-slate-500">
                Pendidik: <span className="font-semibold text-slate-700">{teacher.full_name}</span>
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={isLoading}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {errorMessage && (
          <div className="mt-4 flex items-center gap-2 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 border border-rose-200">
            <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
            <span>{errorMessage}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="mt-4 space-y-3.5 text-xs">
          {/* Periode */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-[11px] font-semibold text-slate-700 mb-1">
                Bulan Periode <span className="text-rose-500">*</span>
              </label>
              <select
                className="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-brand-blue focus:outline-none focus:ring-1 focus:ring-brand-blue"
                value={periodMonth}
                onChange={(e) => setPeriodMonth(Number(e.target.value))}
                disabled={isLoading}
              >
                {MONTH_NAMES.map((name, idx) => (
                  <option key={idx + 1} value={idx + 1}>
                    {name} ({idx + 1})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-slate-700 mb-1">
                Tahun Periode <span className="text-rose-500">*</span>
              </label>
              <Input
                type="number"
                min={2020}
                max={2035}
                value={periodYear}
                onChange={(e) => setPeriodYear(Number(e.target.value))}
                disabled={isLoading}
                required
              />
            </div>
          </div>

          {/* Tanggal Pembayaran */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-700 mb-1">
              Tanggal Transfer / Pembayaran
            </label>
            <Input
              type="date"
              value={paymentDate}
              onChange={(e) => setPaymentDate(e.target.value)}
              disabled={isLoading}
            />
          </div>

          {/* Rincian Finansial: Gaji Pokok, Tunjangan, Potongan */}
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2.5">
            <div className="grid grid-cols-3 gap-2">
              <div>
                <label className="block text-[10px] font-medium text-slate-500 mb-0.5">
                  Gaji Pokok (Rp)
                </label>
                <Input
                  type="number"
                  min={0}
                  step={1000}
                  value={basicSalary}
                  onChange={(e) => setBasicSalary(Number(e.target.value))}
                  disabled={isLoading}
                />
              </div>

              <div>
                <label className="block text-[10px] font-medium text-slate-500 mb-0.5">
                  Tunjangan (Rp)
                </label>
                <Input
                  type="number"
                  min={0}
                  step={1000}
                  value={allowances}
                  onChange={(e) => setAllowances(Number(e.target.value))}
                  disabled={isLoading}
                />
              </div>

              <div>
                <label className="block text-[10px] font-medium text-slate-500 mb-0.5">
                  Potongan (Rp)
                </label>
                <Input
                  type="number"
                  min={0}
                  step={1000}
                  value={deductions}
                  onChange={(e) => setDeductions(Number(e.target.value))}
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Take Home Pay calculation */}
            <div className="flex items-center justify-between pt-2 border-t border-slate-200">
              <span className="text-[11px] font-bold text-slate-700">Total Take Home Pay (Gaji Bersih):</span>
              <span className="font-mono font-bold text-emerald-700 text-sm">
                Rp {netSalary.toLocaleString('id-ID')}
              </span>
            </div>
          </div>

          {/* Catatan */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-700 mb-1">
              Catatan / Keterangan Pembayaran
            </label>
            <Input
              type="text"
              placeholder="Contoh: Transfer Bank Jatim Syariah (Rek. 0123-456)"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              disabled={isLoading}
            />
          </div>

          {/* File Upload */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-700 mb-1">
              Dokumen Slip Gaji (PDF/Gambar) <span className="text-rose-500">*</span>
            </label>
            <div className="flex items-center gap-2">
              <label className="flex flex-1 items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white p-3 hover:bg-slate-50 cursor-pointer transition-colors">
                <FileText className="h-4 w-4 text-slate-400" />
                <span className="text-xs text-slate-600 truncate max-w-[280px]">
                  {file ? file.name : 'Pilih berkas slip gaji (maks. 10MB)'}
                </span>
                <input
                  type="file"
                  accept=".pdf,image/png,image/jpeg,image/jpg"
                  onChange={handleFileChange}
                  className="hidden"
                  disabled={isLoading}
                />
              </label>
              {file && (
                <button
                  type="button"
                  onClick={() => setFile(null)}
                  className="text-slate-400 hover:text-rose-600 p-1"
                >
                  <X className="h-4 w-4" />
                </button>
              )}
            </div>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onClose}
              disabled={isLoading}
            >
              Batal
            </Button>
            <Button
              type="submit"
              variant="default"
              size="sm"
              disabled={isLoading}
              className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
            >
              {isLoading ? (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  <span>Mengunggah...</span>
                </>
              ) : (
                <>
                  <Upload className="h-3.5 w-3.5" />
                  <span>Unggah Slip Gaji</span>
                </>
              )}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
