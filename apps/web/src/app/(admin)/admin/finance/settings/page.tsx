'use client';

import React, { useEffect, useState, useCallback } from 'react';
import {
  Settings,
  Plus,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  Edit2,
  Trash2,
  ExternalLink,
  X,
  AlertCircle,
  BookOpen,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import { financeApi } from '@/features/finance/finance-api';
import { PaymentProviderSetting } from '@/types/finance';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';

export default function FinanceSettingsPage() {
  const [providers, setProviders] = useState<PaymentProviderSetting[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<PaymentProviderSetting | null>(null);
  const [providerToDelete, setProviderToDelete] = useState<{ id: string; name: string } | null>(null);
  const [isDeletingProvider, setIsDeletingProvider] = useState(false);
  const [formData, setFormData] = useState<{
    name: string;
    instructions: string;
    info_url: string;
    is_active: boolean;
  }>({
    name: '',
    instructions: '',
    info_url: '',
    is_active: true,
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await financeApi.getPaymentProviders();
      setProviders(data);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Gagal memuat pengaturan kanal pembayaran.';
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
      name: '',
      instructions: '',
      info_url: '',
      is_active: true,
    });
    setFormError(null);
    setIsModalOpen(true);
  };

  const handleOpenEdit = (item: PaymentProviderSetting) => {
    setEditingItem(item);
    setFormData({
      name: item.name,
      instructions: item.instructions,
      info_url: item.info_url || '',
      is_active: item.is_active,
    });
    setFormError(null);
    setIsModalOpen(true);
  };

  const handleDelete = (id: string, name: string) => {
    setProviderToDelete({ id, name });
  };

  const handleConfirmDelete = async () => {
    if (!providerToDelete) return;
    setIsDeletingProvider(true);
    try {
      await financeApi.deletePaymentProvider(providerToDelete.id);
      toast.success(`Kanal pembayaran "${providerToDelete.name}" berhasil dihapus.`);
      setProviderToDelete(null);
      await loadData();
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Gagal menghapus penyedia pembayaran.');
    } finally {
      setIsDeletingProvider(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name.trim() || !formData.instructions.trim()) {
      setFormError('Nama penyedia dan instruksi cara bayar wajib diisi.');
      return;
    }

    setIsSubmitting(true);
    setFormError(null);
    try {
      if (editingItem) {
        await financeApi.updatePaymentProvider(editingItem.id, {
          name: formData.name,
          instructions: formData.instructions,
          info_url: formData.info_url || undefined,
          is_active: formData.is_active,
        });
      } else {
        await financeApi.createPaymentProvider({
          name: formData.name,
          instructions: formData.instructions,
          info_url: formData.info_url || undefined,
          is_active: formData.is_active,
        });
      }
      setIsModalOpen(false);
      await loadData();
    } catch (err: unknown) {
      setFormError(err instanceof Error ? err.message : 'Gagal menyimpan pengaturan penyedia.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const filteredProviders = providers.filter((p) =>
    p.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Settings className="h-6 w-6 text-brand-blue" />
            Pengaturan Kanal & Cara Pembayaran Pihak Ketiga
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Konfigurasi penyedia layanan transfer/VA luar sistem dan petunjuk cara bayar yang tampil di portal orang tua & siswa.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={loadData} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
          <Button size="sm" onClick={handleOpenCreate} className="gap-2 bg-brand-blue hover:bg-brand-blue/90">
            <Plus className="h-4 w-4" />
            Tambah Penyedia
          </Button>
        </div>
      </div>

      {/* Filter / Search */}
      <Card>
        <CardContent className="p-4">
          <div className="relative max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input
              placeholder="Cari nama kanal/penyedia..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9"
            />
          </div>
        </CardContent>
      </Card>

      {/* Konten Daftar */}
      {isLoading ? (
        <Card className="p-12">
          <LoadingSpinner label="Memuat kanal pembayaran..." size="lg" />
        </Card>
      ) : error ? (
        <ErrorState
          title="Gagal Memuat Pengaturan"
          message={error}
          onRetry={loadData}
        />
      ) : filteredProviders.length === 0 ? (
        <Card className="p-8">
          <EmptyState
            title="Belum Ada Penyedia Pembayaran"
            description="Tambahkan kanal pembayaran pihak ketiga (mis. Virtual Account Bank Jatim, BSI VA, Indomaret) agar tampil petunjuknya pada portal."
            actionLabel="Tambah Penyedia Baru"
            onAction={handleOpenCreate}
          />
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filteredProviders.map((item) => (
            <Card key={item.id} className="overflow-hidden border-slate-200">
              <CardHeader className="pb-3 border-b border-slate-100 flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                    <BookOpen className="h-4 w-4 text-brand-blue" />
                    {item.name}
                  </CardTitle>
                </div>
                <div className="flex items-center gap-2">
                  {item.is_active ? (
                    <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200">Aktif</Badge>
                  ) : (
                    <Badge variant="outline" className="text-slate-400">Nonaktif</Badge>
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleOpenEdit(item)}
                    className="h-8 w-8 p-0 text-slate-500 hover:text-brand-blue"
                    title="Ubah Konfigurasi"
                  >
                    <Edit2 className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleDelete(item.id, item.name)}
                    className="h-8 w-8 p-0 text-slate-400 hover:text-rose-600"
                    title="Hapus"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="p-4 space-y-3">
                <div className="text-xs text-slate-700 whitespace-pre-line bg-slate-50 p-3 rounded-lg border border-slate-100 font-sans leading-relaxed">
                  {item.instructions}
                </div>
                {item.info_url && (
                  <div className="text-xs">
                    <a
                      href={item.info_url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1 text-brand-blue hover:underline"
                    >
                      <ExternalLink className="h-3.5 w-3.5" />
                      Tautan Informasi / Panduan Resmi
                    </a>
                  </div>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Modal Tambah / Edit */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-slate-900">
                {editingItem ? 'Ubah Pengaturan Kanal Pembayaran' : 'Tambah Penyedia Pembayaran Pihak Ketiga'}
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
                  Nama Layanan / Kanal <span className="text-red-500">*</span>
                </label>
                <Input
                  placeholder="Mis: Bank Jatim Virtual Account, BSI Virtual Account, Loket Minimarket"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  Petunjuk & Instruksi Cara Bayar <span className="text-red-500">*</span>
                </label>
                <Textarea
                  rows={5}
                  placeholder="Tuliskan langkah-langkah pembayaran singkat dan jelas untuk orang tua..."
                  value={formData.instructions}
                  onChange={(e) => setFormData({ ...formData, instructions: e.target.value })}
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                  URL Panduan / Website Info (Opsional)
                </label>
                <Input
                  placeholder="https://..."
                  value={formData.info_url}
                  onChange={(e) => setFormData({ ...formData, info_url: e.target.value })}
                />
              </div>

              <div className="pt-2 border-t border-slate-100">
                <label className="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                  <input
                    type="checkbox"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    className="rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                  />
                  <span>Tampilkan dan aktifkan panduan ini pada portal</span>
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
                  {isSubmitting ? 'Menyimpan...' : editingItem ? 'Simpan Perubahan' : 'Buat Kanal'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Payment Provider */}
      <ConfirmDialog
        isOpen={!!providerToDelete}
        onClose={() => setProviderToDelete(null)}
        onConfirm={handleConfirmDelete}
        title="Hapus Penyedia Pembayaran"
        description="Apakah Anda yakin ingin menghapus konfigurasi penyedia pembayaran ini?"
        affectedItemName={providerToDelete?.name}
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isDeletingProvider}
      />
    </div>
  );
}
