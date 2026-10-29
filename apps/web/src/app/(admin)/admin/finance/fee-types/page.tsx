'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  Coins,
  Plus,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  Edit2,
  Trash2,
  AlertCircle,
  X,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import { financeApi, formatRupiah } from '@/features/finance/finance-api';
import { FeeType, FeeCategory } from '@/types/finance';

export default function FeeTypesPage() {
  const [feeTypes, setFeeTypes] = useState<FeeType[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<FeeType | null>(null);
  const [formData, setFormData] = useState<{
    code: string;
    name: string;
    category: FeeCategory;
    default_amount: number;
    is_recurring: boolean;
    is_active: boolean;
  }>({
    code: '',
    name: '',
    category: 'spp',
    default_amount: 0,
    is_recurring: true,
    is_active: true,
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await financeApi.getFeeTypes();
      setFeeTypes(data);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat data jenis biaya.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleOpenCreate = () => {
    setEditingItem(null);
    setFormData({
      code: '',
      name: '',
      category: 'spp',
      default_amount: 0,
      is_recurring: true,
      is_active: true,
    });
    setFormError(null);
    setIsModalOpen(true);
  };

  const handleOpenEdit = (item: FeeType) => {
    setEditingItem(item);
    setFormData({
      code: item.code,
      name: item.name,
      category: item.category,
      default_amount: item.default_amount,
      is_recurring: item.is_recurring,
      is_active: item.is_active,
    });
    setFormError(null);
    setIsModalOpen(true);
  };

  const handleDelete = async (id: string, name: string) => {
    if (!window.confirm(`Yakin ingin menghapus jenis biaya "${name}"?`)) return;
    try {
      await financeApi.deleteFeeType(id);
      await loadData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Gagal menghapus jenis biaya.');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.code.trim() || !formData.name.trim()) {
      setFormError('Kode dan nama biaya wajib diisi.');
      return;
    }
    if (formData.default_amount < 0) {
      setFormError('Nominal tarif tidak boleh negatif.');
      return;
    }

    setIsSubmitting(true);
    setFormError(null);
    try {
      if (editingItem) {
        await financeApi.updateFeeType(editingItem.id, formData);
      } else {
        await financeApi.createFeeType(formData);
      }
      setIsModalOpen(false);
      await loadData();
    } catch (err: unknown) {
      setFormError(err instanceof Error ? err.message : 'Gagal menyimpan jenis biaya.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const filteredFeeTypes = feeTypes.filter((f) => {
    const matchesSearch =
      f.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      f.code.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesCat = categoryFilter === 'all' || f.category === categoryFilter;
    return matchesSearch && matchesCat;
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Coins className="h-6 w-6 text-brand-blue" />
            Jenis Biaya & Tarif
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Konfigurasi pos pembayaran sekolah, tarif default per pos biaya, dan status tagihan rutin.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={loadData} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
          <Button size="sm" onClick={handleOpenCreate} className="gap-2 bg-brand-blue hover:bg-brand-blue/90">
            <Plus className="h-4 w-4" />
            Tambah Biaya
          </Button>
        </div>
      </div>

      {/* Filter & Pencarian */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col sm:flex-row items-center gap-3">
            <div className="relative flex-1 w-full">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <Input
                placeholder="Cari kode atau nama biaya..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <select
                value={categoryFilter}
                onChange={(e) => setCategoryFilter(e.target.value)}
                className="h-9 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
              >
                <option value="all">Semua Kategori</option>
                <option value="spp">SPP Bulanan</option>
                <option value="gedung">Uang Gedung</option>
                <option value="seragam">Seragam</option>
                <option value="kegiatan">Kegiatan</option>
                <option value="lainnya">Lainnya</option>
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Tabel Data */}
      {isLoading ? (
        <Card className="p-12">
          <LoadingSpinner label="Memuat jenis biaya sekolah..." size="lg" />
        </Card>
      ) : error ? (
        <ErrorState
          title="Gagal Memuat Jenis Biaya"
          message={error}
          onRetry={loadData}
        />
      ) : filteredFeeTypes.length === 0 ? (
        <Card className="p-8">
          <EmptyState
            title="Tidak Ada Jenis Biaya"
            description="Belum ada data pos biaya atau tidak cocok dengan filter pencarian."
            actionLabel="Tambah Biaya Baru"
            onAction={handleOpenCreate}
          />
        </Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-slate-50 border-b border-slate-200 text-xs uppercase font-semibold text-slate-600">
                <tr>
                  <th className="px-4 py-3">Kode</th>
                  <th className="px-4 py-3">Nama Pos Biaya</th>
                  <th className="px-4 py-3">Kategori</th>
                  <th className="px-4 py-3 text-right">Tarif Default</th>
                  <th className="px-4 py-3 text-center">Rutin</th>
                  <th className="px-4 py-3 text-center">Status</th>
                  <th className="px-4 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filteredFeeTypes.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/50">
                    <td className="px-4 py-3 font-mono font-medium text-slate-800">{item.code}</td>
                    <td className="px-4 py-3 font-medium text-slate-900">{item.name}</td>
                    <td className="px-4 py-3 capitalize">
                      <Badge variant="outline" className="font-normal">
                        {item.category}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-slate-800">
                      {formatRupiah(item.default_amount)}
                    </td>
                    <td className="px-4 py-3 text-center">
                      {item.is_recurring ? (
                        <span className="inline-flex items-center text-xs font-medium text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full">
                          Bulanan / Rutin
                        </span>
                      ) : (
                        <span className="inline-flex items-center text-xs font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                          Sekali Bayar
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-center">
                      {item.is_active ? (
                        <span className="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          Aktif
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-xs text-slate-400 font-medium">
                          <XCircle className="h-3.5 w-3.5" />
                          Nonaktif
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right space-x-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleOpenEdit(item)}
                        className="h-8 w-8 p-0 text-slate-600 hover:text-brand-blue"
                        title="Edit Biaya"
                      >
                        <Edit2 className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDelete(item.id, item.name)}
                        className="h-8 w-8 p-0 text-slate-600 hover:text-red-600"
                        title="Hapus Biaya"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {/* Modal Form Tambah / Edit */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">
                {editingItem ? 'Ubah Jenis Biaya' : 'Tambah Jenis Biaya Baru'}
              </h2>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              {formError && (
                <div className="p-3 bg-red-50 text-red-700 text-xs rounded-lg flex items-center gap-2">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  <span>{formError}</span>
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Kode Biaya
                </label>
                <Input
                  placeholder="Mis: SPP-SMP, GDG-2026"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Nama Pos Biaya
                </label>
                <Input
                  placeholder="Mis: SPP Bulanan SMP"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                    Kategori
                  </label>
                  <select
                    value={formData.category}
                    onChange={(e) => setFormData({ ...formData, category: e.target.value as FeeCategory })}
                    className="w-full h-10 px-3 rounded-md border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blue"
                  >
                    <option value="spp">SPP Bulanan</option>
                    <option value="gedung">Uang Gedung</option>
                    <option value="seragam">Seragam</option>
                    <option value="kegiatan">Kegiatan</option>
                    <option value="lainnya">Lainnya</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                    Tarif Nominal (Rp)
                  </label>
                  <Input
                    type="number"
                    min="0"
                    placeholder="0"
                    value={formData.default_amount}
                    onChange={(e) => setFormData({ ...formData, default_amount: Number(e.target.value) })}
                    required
                  />
                </div>
              </div>

              <div className="space-y-2 pt-2 border-t border-slate-100">
                <label className="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                  <input
                    type="checkbox"
                    checked={formData.is_recurring}
                    onChange={(e) => setFormData({ ...formData, is_recurring: e.target.checked })}
                    className="rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                  />
                  <span>Biaya Rutin Bulanan (Dapat di-generate per periode)</span>
                </label>

                <label className="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                  <input
                    type="checkbox"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    className="rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                  />
                  <span>Status Aktif (Tersedia untuk penagihan baru)</span>
                </label>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsModalOpen(false)}
                  disabled={isSubmitting}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  disabled={isSubmitting}
                  className="bg-brand-blue hover:bg-brand-blue/90"
                >
                  {isSubmitting ? 'Menyimpan...' : editingItem ? 'Simpan Perubahan' : 'Buat Biaya'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
