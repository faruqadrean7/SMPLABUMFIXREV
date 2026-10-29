'use client';

import React, { useEffect, useState, useCallback, useMemo } from 'react';
import {
  Boxes,
  QrCode,
  Search,
  Filter,
  Plus,
  RefreshCw,
  Printer,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Building2,
  Calendar,
  Eye,
  Edit,
  Trash2,
  Check,
  X,
  Clock,
  Scan,
  Laptop,
  Layers,
  FileText,
  AlertCircle,
  ShieldCheck,
  ArrowRight,
  ClipboardCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  assetsApi,
  AssetItem,
  AssetSummary,
  AssetCondition,
  AssetAuditSession,
  AssetAuditStatus,
  AssetQrData,
} from '@/features/sarpras/assets-api';
import { ticketsApi } from '@/features/sarpras/tickets-api';
import { useAuth } from '@/providers/auth-provider';
import { hasPermission } from '@/lib/rbac/evaluator';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast } from '@/components/ui/toast';

export default function AssetsAndInventoryPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<'aset' | 'opname' | 'scan'>('aset');

  // =========================================================================
  // DATA STATE
  // =========================================================================
  const [assets, setAssets] = useState<AssetItem[]>([]);
  const [summary, setSummary] = useState<AssetSummary | null>(null);
  const [audits, setAudits] = useState<AssetAuditSession[]>([]);
  const [rooms, setRooms] = useState<Array<{ id: string; name: string; code: string }>>([]);

  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [assetToDelete, setAssetToDelete] = useState<AssetItem | null>(null);
  const [isDeletingAsset, setIsDeletingAsset] = useState(false);

  // =========================================================================
  // FILTER & PENCARIAN ASET
  // =========================================================================
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [conditionFilter, setConditionFilter] = useState('all');
  const [roomFilter, setRoomFilter] = useState('all');

  // =========================================================================
  // MODAL 1: TAMBAH / EDIT ASET
  // =========================================================================
  const [isAssetModalOpen, setIsAssetModalOpen] = useState(false);
  const [editingAsset, setEditingAsset] = useState<AssetItem | null>(null);
  const [formAssetCode, setFormAssetCode] = useState('');
  const [formName, setFormName] = useState('');
  const [formCategory, setFormCategory] = useState('');
  const [formRoomId, setFormRoomId] = useState('');
  const [formBrand, setFormBrand] = useState('');
  const [formModel, setFormModel] = useState('');
  const [formSerialNumber, setFormSerialNumber] = useState('');
  const [formCondition, setFormCondition] = useState<AssetCondition>('baik');
  const [formAcquiredAt, setFormAcquiredAt] = useState('');
  const [formNotes, setFormNotes] = useState('');
  const [isSubmittingAsset, setIsSubmittingAsset] = useState(false);
  const [assetFormError, setAssetFormError] = useState<string | null>(null);

  // =========================================================================
  // MODAL 2: CETAK LABEL QR
  // =========================================================================
  const [isQrModalOpen, setIsQrModalOpen] = useState(false);
  const [qrData, setQrData] = useState<AssetQrData | null>(null);
  const [isLoadingQr, setIsLoadingQr] = useState(false);

  // =========================================================================
  // MODAL 3: DETAIL ASET & RIWAYAT (AUDIT + TIKET KERUSAKAN)
  // =========================================================================
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [selectedAssetForDetail, setSelectedAssetForDetail] = useState<AssetItem | null>(null);

  // =========================================================================
  // MODAL 4: SESI STOK OPNAME BARU
  // =========================================================================
  const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
  const [auditTitle, setAuditTitle] = useState('');
  const [auditDate, setAuditDate] = useState(new Date().toISOString().split('T')[0]);
  const [auditRoomId, setAuditRoomId] = useState('');
  const [auditStatus, setAuditStatus] = useState<AssetAuditStatus>('selesai');
  const [auditNotes, setAuditNotes] = useState('');
  const [auditItemsState, setAuditItemsState] = useState<
    Array<{ asset_id: string; is_present: boolean; found_condition: AssetCondition; notes: string }>
  >([]);
  const [isSubmittingAudit, setIsSubmittingAudit] = useState(false);
  const [auditFormError, setAuditFormError] = useState<string | null>(null);

  // =========================================================================
  // MODAL 5: DETAIL SESI STOK OPNAME
  // =========================================================================
  const [isAuditDetailOpen, setIsAuditDetailOpen] = useState(false);
  const [selectedAuditForDetail, setSelectedAuditForDetail] = useState<AssetAuditSession | null>(null);

  // =========================================================================
  // TAB SCAN / LOOKUP INSTAN
  // =========================================================================
  const [scanCodeInput, setScanCodeInput] = useState('');
  const [scannedAsset, setScannedAsset] = useState<AssetItem | null>(null);
  const [isScanning, setIsScanning] = useState(false);
  const [scanError, setScanError] = useState<string | null>(null);

  // =========================================================================
  // WEWENANG PENGGUNA
  // =========================================================================
  const canManageAssets = useMemo(() => {
    if (!user) return false;
    const userRoles = (user.roles || []) as string[];
    return (
      userRoles.includes('super_admin') ||
      userRoles.includes('facility_staff') ||
      userRoles.includes('admin_tu') ||
      hasPermission(user, ['facility.asset.manage', 'facility.booking.manage'])
    );
  }, [user]);

  // =========================================================================
  // FETCH DATA
  // =========================================================================
  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [assetsRes, summaryRes, auditsRes, roomsRes] = await Promise.all([
        assetsApi.getAssets({ per_page: 100 }),
        assetsApi.getSummary().catch(() => null),
        assetsApi.getAudits({ per_page: 50 }).catch(() => null),
        ticketsApi.getRooms().catch(() => null),
      ]);

      setAssets(assetsRes.data || []);
      if (summaryRes?.data) setSummary(summaryRes.data);
      if (auditsRes?.data) setAudits(auditsRes.data);
      if (roomsRes?.data) setRooms(roomsRes.data);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat data inventaris aset.';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Auto-dismiss success message
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  // Categories list
  const availableCategories = useMemo(() => {
    const set = new Set<string>();
    assets.forEach((a) => {
      if (a.category) set.add(a.category);
    });
    return Array.from(set).sort();
  }, [assets]);

  // Filtered Assets
  const filteredAssets = useMemo(() => {
    return assets.filter((a) => {
      const matchSearch =
        searchQuery === '' ||
        a.asset_code.toLowerCase().includes(searchQuery.toLowerCase()) ||
        a.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (a.brand && a.brand.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (a.model && a.model.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (a.room && a.room.name.toLowerCase().includes(searchQuery.toLowerCase()));

      const matchCategory = categoryFilter === 'all' || a.category === categoryFilter;
      const matchCondition = conditionFilter === 'all' || a.asset_condition === conditionFilter;
      const matchRoom = roomFilter === 'all' || a.room_id === roomFilter;

      return matchSearch && matchCategory && matchCondition && matchRoom;
    });
  }, [assets, searchQuery, categoryFilter, conditionFilter, roomFilter]);

  // =========================================================================
  // HANDLERS
  // =========================================================================

  // 1. Buka Modal Buat / Edit Aset
  const openCreateAssetModal = () => {
    setEditingAsset(null);
    setFormAssetCode(`AST-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${String(assets.length + 1).padStart(3, '0')}`);
    setFormName('');
    setFormCategory('');
    setFormRoomId('');
    setFormBrand('');
    setFormModel('');
    setFormSerialNumber('');
    setFormCondition('baik');
    setFormAcquiredAt(new Date().toISOString().split('T')[0]);
    setFormNotes('');
    setAssetFormError(null);
    setIsAssetModalOpen(true);
  };

  const openEditAssetModal = (asset: AssetItem) => {
    setEditingAsset(asset);
    setFormAssetCode(asset.asset_code);
    setFormName(asset.name);
    setFormCategory(asset.category);
    setFormRoomId(asset.room_id || '');
    setFormBrand(asset.brand || '');
    setFormModel(asset.model || '');
    setFormSerialNumber(asset.serial_number || '');
    setFormCondition(asset.asset_condition);
    setFormAcquiredAt(asset.acquired_at ? asset.acquired_at.split('T')[0] : '');
    setFormNotes(asset.notes || '');
    setAssetFormError(null);
    setIsAssetModalOpen(true);
  };

  const handleSaveAsset = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formName.trim() || !formAssetCode.trim() || !formCategory.trim()) {
      setAssetFormError('Kode aset, nama barang, dan kategori wajib diisi.');
      return;
    }

    setIsSubmittingAsset(true);
    setAssetFormError(null);
    try {
      const payload = {
        asset_code: formAssetCode.trim(),
        name: formName.trim(),
        category: formCategory.trim(),
        room_id: formRoomId || null,
        brand: formBrand.trim() || null,
        model: formModel.trim() || null,
        serial_number: formSerialNumber.trim() || null,
        asset_condition: formCondition,
        acquired_at: formAcquiredAt || null,
        notes: formNotes.trim() || null,
      };

      if (editingAsset) {
        await assetsApi.updateAsset(editingAsset.id, payload);
        setSuccessMessage(`Aset ${payload.name} berhasil diperbarui.`);
      } else {
        await assetsApi.createAsset(payload);
        setSuccessMessage(`Aset ${payload.name} berhasil diregistrasi.`);
      }

      setIsAssetModalOpen(false);
      loadData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan data aset.';
      setAssetFormError(msg);
    } finally {
      setIsSubmittingAsset(false);
    }
  };

  // 2. Hapus Aset
  const handleDeleteAsset = (asset: AssetItem) => {
    setAssetToDelete(asset);
  };

  const handleConfirmDeleteAsset = async () => {
    if (!assetToDelete) return;
    setIsDeletingAsset(true);
    try {
      await assetsApi.deleteAsset(assetToDelete.id);
      setSuccessMessage(`Aset ${assetToDelete.asset_code} berhasil dihapus.`);
      toast.success(`Aset ${assetToDelete.asset_code} berhasil dihapus.`);
      setAssetToDelete(null);
      loadData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menghapus aset.';
      setError(msg);
      toast.error(msg);
    } finally {
      setIsDeletingAsset(false);
    }
  };

  // 3. Cetak Label QR
  const handleOpenQrModal = async (asset: AssetItem) => {
    setIsLoadingQr(true);
    setQrData(null);
    setIsQrModalOpen(true);
    try {
      const res = await assetsApi.getAssetQr(asset.id);
      if (res.data) setQrData(res.data);
    } catch {
      setQrData({
        asset,
        qr_code: asset.asset_code,
        qr_data_string: asset.asset_code,
        qr_payload: {
          system: 'SISTLABUM',
          module: 'SAR-02',
          asset_id: asset.id,
          code: asset.asset_code,
          name: asset.name,
          room: asset.room?.name || 'Belum ditentukan',
          category: asset.category,
        },
        label_title: 'INVENTARIS SEKOLAH',
        printed_at: new Date().toISOString(),
      });
    } finally {
      setIsLoadingQr(false);
    }
  };

  // 4. Detail Aset
  const handleOpenDetailModal = async (asset: AssetItem) => {
    setSelectedAssetForDetail(asset);
    setIsDetailModalOpen(true);
    try {
      const res = await assetsApi.getAsset(asset.id);
      if (res.data) setSelectedAssetForDetail(res.data);
    } catch {
      // Keep existing data
    }
  };

  // 5. Buat Sesi Stok Opname Baru
  const handleOpenAuditModal = () => {
    setAuditTitle(`Stok Opname ${new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}`);
    setAuditDate(new Date().toISOString().split('T')[0]);
    setAuditRoomId('');
    setAuditStatus('selesai');
    setAuditNotes('');
    setAuditFormError(null);

    // Siapkan checklist aset
    const initialItems = assets.map((a) => ({
      asset_id: a.id,
      is_present: true,
      found_condition: a.asset_condition,
      notes: '',
    }));
    setAuditItemsState(initialItems);
    setIsAuditModalOpen(true);
  };

  const handleRoomChangeForAudit = (roomId: string) => {
    setAuditRoomId(roomId);
    const roomAssets = roomId ? assets.filter((a) => a.room_id === roomId) : assets;
    setAuditItemsState(
      roomAssets.map((a) => ({
        asset_id: a.id,
        is_present: true,
        found_condition: a.asset_condition,
        notes: '',
      }))
    );
  };

  const handleSaveAudit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!auditTitle.trim()) {
      setAuditFormError('Judul sesi stok opname wajib diisi.');
      return;
    }

    setIsSubmittingAudit(true);
    setAuditFormError(null);
    try {
      await assetsApi.createAudit({
        title: auditTitle.trim(),
        audit_date: auditDate,
        room_id: auditRoomId || null,
        status: auditStatus,
        notes: auditNotes.trim() || null,
        items: auditItemsState,
      });

      setSuccessMessage('Sesi stok opname berhasil disimpan dan kondisi aset telah disinkronkan.');
      setIsAuditModalOpen(false);
      loadData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan sesi stok opname.';
      setAuditFormError(msg);
    } finally {
      setIsSubmittingAudit(false);
    }
  };

  // 6. Detail Sesi Stok Opname
  const handleOpenAuditDetail = async (audit: AssetAuditSession) => {
    setSelectedAuditForDetail(audit);
    setIsAuditDetailOpen(true);
    try {
      const res = await assetsApi.getAudit(audit.id);
      if (res.data) setSelectedAuditForDetail(res.data);
    } catch {
      // Keep existing data
    }
  };

  // 7. Scan / Lookup Cepat
  const handleScanLookup = async (e: React.FormEvent) => {
    e.preventDefault();
    const query = scanCodeInput.trim();
    if (!query) return;

    setIsScanning(true);
    setScanError(null);
    setScannedAsset(null);
    try {
      const res = await assetsApi.getAssetByCode(query);
      if (res.data) {
        setScannedAsset(res.data);
      }
    } catch {
      // Coba cari dari local assets array jika by-code error
      const match = assets.find((a) => a.asset_code.toLowerCase() === query.toLowerCase());
      if (match) {
        setScannedAsset(match);
      } else {
        setScanError(`Aset dengan kode "${query}" tidak ditemukan.`);
      }
    } finally {
      setIsScanning(false);
    }
  };

  // Format Condition Badge
  const getConditionBadge = (condition: AssetCondition) => {
    switch (condition) {
      case 'baik':
        return (
          <Badge className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium flex items-center gap-1">
            <CheckCircle2 className="w-3 h-3" /> Baik
          </Badge>
        );
      case 'rusak_ringan':
        return (
          <Badge className="bg-amber-500 hover:bg-amber-600 text-white font-medium flex items-center gap-1">
            <AlertTriangle className="w-3 h-3" /> Rusak Ringan
          </Badge>
        );
      case 'rusak_berat':
        return (
          <Badge className="bg-red-600 hover:bg-red-700 text-white font-medium flex items-center gap-1">
            <XCircle className="w-3 h-3" /> Rusak Berat
          </Badge>
        );
      default:
        return <Badge variant="outline">{condition}</Badge>;
    }
  };

  return (
    <div className="space-y-6 p-6">
      {/* =================================================================== */}
      {/* HEADER & TOP ACTIONS */}
      {/* =================================================================== */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-5">
        <div>
          <div className="flex items-center gap-2">
            <div className="p-2 bg-emerald-100 text-emerald-700 rounded-lg">
              <QrCode className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-gray-900">
                Aset & Inventarisasi Berbasis QR / Barcode
              </h1>
              <p className="text-sm text-gray-500">
                Modul BE-SAR-02 / ADM-SAR-02: Registrasi master aset, cetak label QR, scan lookup instan, dan sesi stok opname berkala.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={loadData}
            disabled={isLoading}
            className="flex items-center gap-1.5"
          >
            <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
            Muat Ulang
          </Button>

          {canManageAssets && (
            <>
              <Button
                variant="outline"
                size="sm"
                onClick={handleOpenAuditModal}
                className="border-indigo-300 text-indigo-700 hover:bg-indigo-50 flex items-center gap-1.5"
              >
                <ClipboardCheck className="w-4 h-4" />
                Sesi Stok Opname
              </Button>

              <Button
                size="sm"
                onClick={openCreateAssetModal}
                className="bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1.5"
              >
                <Plus className="w-4 h-4" />
                Tambah Aset Baru
              </Button>
            </>
          )}
        </div>
      </div>

      {/* =================================================================== */}
      {/* BANNER NOTIFIKASI SUCCESS / ERROR */}
      {/* =================================================================== */}
      {successMessage && (
        <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center justify-between text-sm shadow-xs">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
            <span>{successMessage}</span>
          </div>
          <button onClick={() => setSuccessMessage(null)} className="text-emerald-600 hover:text-emerald-800">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between text-sm shadow-xs">
          <div className="flex items-center gap-2">
            <AlertCircle className="w-4 h-4 text-red-600 flex-shrink-0" />
            <span>{error}</span>
          </div>
          <Button variant="ghost" size="sm" onClick={loadData} className="text-red-700 hover:bg-red-100">
            Coba Lagi
          </Button>
        </div>
      )}

      {/* =================================================================== */}
      {/* KPI & METRIK RINGKASAN ASET */}
      {/* =================================================================== */}
      {summary && (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <Card className="shadow-xs border-gray-200">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Aset</p>
              <p className="text-2xl font-bold text-gray-900 mt-1">{summary.total_assets}</p>
              <p className="text-xs text-gray-400 mt-1">Barang terinventarisir</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-emerald-200 bg-emerald-50/30">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-emerald-700 uppercase tracking-wider">Kondisi Baik</p>
              <p className="text-2xl font-bold text-emerald-600 mt-1">{summary.condition_baik}</p>
              <p className="text-xs text-emerald-600/80 mt-1">Siap pakai prima</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-amber-200 bg-amber-50/30">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-amber-700 uppercase tracking-wider">Rusak Ringan</p>
              <p className="text-2xl font-bold text-amber-600 mt-1">{summary.condition_rusak_ringan}</p>
              <p className="text-xs text-amber-600/80 mt-1">Perlu perbaikan kecil</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-red-200 bg-red-50/30">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-red-700 uppercase tracking-wider">Rusak Berat</p>
              <p className="text-2xl font-bold text-red-600 mt-1">{summary.condition_rusak_berat}</p>
              <p className="text-xs text-red-600/80 mt-1">Tidak dapat digunakan</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-indigo-200 bg-indigo-50/30">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-indigo-700 uppercase tracking-wider">Sesi Opname</p>
              <p className="text-2xl font-bold text-indigo-600 mt-1">{summary.completed_audits} / {summary.total_audits}</p>
              <p className="text-xs text-indigo-600/80 mt-1">Selesai / Total sesi</p>
            </CardContent>
          </Card>

          <Card className="shadow-xs border-gray-200">
            <CardContent className="p-4">
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori Aset</p>
              <p className="text-2xl font-bold text-gray-900 mt-1">{Object.keys(summary.category_counts || {}).length}</p>
              <p className="text-xs text-gray-400 mt-1">Jenis klasifikasi</p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* =================================================================== */}
      {/* TABS & FILTER BAR */}
      {/* =================================================================== */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-b pb-3">
        {/* Navigation Tabs */}
        <div className="flex items-center gap-2">
          <Button
            variant={activeTab === 'aset' ? 'default' : 'ghost'}
            size="sm"
            onClick={() => setActiveTab('aset')}
            className={activeTab === 'aset' ? 'bg-emerald-600 text-white' : 'text-gray-600'}
          >
            Daftar Master Aset ({filteredAssets.length})
          </Button>

          <Button
            variant={activeTab === 'opname' ? 'default' : 'ghost'}
            size="sm"
            onClick={() => setActiveTab('opname')}
            className={activeTab === 'opname' ? 'bg-emerald-600 text-white' : 'text-gray-600'}
          >
            Sesi Stok Opname ({audits.length})
          </Button>

          <Button
            variant={activeTab === 'scan' ? 'default' : 'ghost'}
            size="sm"
            onClick={() => setActiveTab('scan')}
            className={activeTab === 'scan' ? 'bg-emerald-600 text-white' : 'text-gray-600'}
          >
            <Scan className="w-3.5 h-3.5 mr-1" />
            Scan / Lookup Cepat
          </Button>
        </div>

        {/* Filter Bar (Aktif di Tab Aset) */}
        {activeTab === 'aset' && (
          <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-[200px]">
              <Search className="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
              <Input
                type="text"
                placeholder="Cari kode QR, nama, merk..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-8 h-9 text-xs"
              />
            </div>

            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              className="h-9 px-2.5 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="all">Semua Kategori</option>
              {availableCategories.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>

            <select
              value={conditionFilter}
              onChange={(e) => setConditionFilter(e.target.value)}
              className="h-9 px-2.5 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="all">Semua Kondisi</option>
              <option value="baik">Kondisi Baik</option>
              <option value="rusak_ringan">Rusak Ringan</option>
              <option value="rusak_berat">Rusak Berat</option>
            </select>

            <select
              value={roomFilter}
              onChange={(e) => setRoomFilter(e.target.value)}
              className="h-9 px-2.5 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="all">Semua Ruangan</option>
              {rooms.map((r) => (
                <option key={r.id} value={r.id}>{r.name}</option>
              ))}
            </select>
          </div>
        )}
      </div>

      {/* =================================================================== */}
      {/* TAB 1: DAFTAR MASTER ASET (BE-SAR-02 / ADM-SAR-02) */}
      {/* =================================================================== */}
      {activeTab === 'aset' && (
        <Card className="shadow-xs border-gray-200">
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-gray-600">
                <thead className="bg-gray-50 text-gray-700 font-semibold border-b border-gray-200 uppercase tracking-wider">
                  <tr>
                    <th className="py-3 px-4">Kode QR / Aset</th>
                    <th className="py-3 px-4">Nama Barang & Detail</th>
                    <th className="py-3 px-4">Kategori</th>
                    <th className="py-3 px-4">Ruangan / Lokasi</th>
                    <th className="py-3 px-4">Kondisi</th>
                    <th className="py-3 px-4">No. Seri / Merk</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {filteredAssets.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="py-12 text-center text-gray-400">
                        {isLoading ? 'Memuat inventaris aset...' : 'Tidak ada aset yang sesuai kriteria pencarian.'}
                      </td>
                    </tr>
                  ) : (
                    filteredAssets.map((asset) => (
                      <tr key={asset.id} className="hover:bg-gray-50/70 transition-colors">
                        <td className="py-3 px-4 font-mono font-semibold text-emerald-800">
                          <div className="flex items-center gap-1.5">
                            <QrCode className="w-4 h-4 text-emerald-600" />
                            <span>{asset.asset_code}</span>
                          </div>
                        </td>
                        <td className="py-3 px-4 max-w-[220px]">
                          <p className="font-semibold text-gray-900 line-clamp-1">{asset.name}</p>
                          {asset.notes && (
                            <p className="text-gray-400 text-[11px] line-clamp-1">{asset.notes}</p>
                          )}
                        </td>
                        <td className="py-3 px-4">
                          <Badge variant="outline" className="text-gray-700 bg-gray-50">
                            {asset.category}
                          </Badge>
                        </td>
                        <td className="py-3 px-4 text-gray-700">
                          {asset.room ? (
                            <div className="flex items-center gap-1 font-medium">
                              <Building2 className="w-3.5 h-3.5 text-gray-400" />
                              <span>{asset.room.name}</span>
                            </div>
                          ) : (
                            <span className="text-gray-400 italic">Belum ditentukan</span>
                          )}
                        </td>
                        <td className="py-3 px-4">{getConditionBadge(asset.asset_condition)}</td>
                        <td className="py-3 px-4 text-gray-600">
                          <div>{asset.brand || '-'} {asset.model ? `(${asset.model})` : ''}</div>
                          {asset.serial_number && (
                            <div className="text-[10px] text-gray-400 font-mono">SN: {asset.serial_number}</div>
                          )}
                        </td>
                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleOpenQrModal(asset)}
                              className="h-7 px-2 text-[11px] text-emerald-700 border-emerald-300 hover:bg-emerald-50"
                              title="Cetak Label QR Fisik"
                            >
                              <Printer className="w-3.5 h-3.5 mr-1" />
                              Label QR
                            </Button>

                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleOpenDetailModal(asset)}
                              className="h-7 px-2 text-[11px] text-gray-600 hover:text-gray-900"
                              title="Lihat Detail & Riwayat Perbaikan"
                            >
                              <Eye className="w-3.5 h-3.5 mr-0.5" />
                              Detail
                            </Button>

                            {canManageAssets && (
                              <>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => openEditAssetModal(asset)}
                                  className="h-7 w-7 p-0 text-gray-600 hover:text-blue-600"
                                  title="Edit Aset"
                                >
                                  <Edit className="w-3.5 h-3.5" />
                                </Button>

                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleDeleteAsset(asset)}
                                  className="h-7 w-7 p-0 text-gray-400 hover:text-red-600"
                                  title="Hapus Aset"
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                </Button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}

      {/* =================================================================== */}
      {/* TAB 2: SESI STOK OPNAME (BE-SAR-02 / ADM-SAR-02) */}
      {/* =================================================================== */}
      {activeTab === 'opname' && (
        <Card className="shadow-xs border-gray-200">
          <CardHeader className="pb-3 border-b border-gray-100 flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-base font-semibold text-gray-900 flex items-center gap-2">
                <ClipboardCheck className="w-5 h-5 text-indigo-600" />
                Sesi Stok Opname & Audit Kondisi Berkala
              </CardTitle>
              <CardDescription className="text-xs text-gray-500">
                Pemeriksaan fisik aset per ruangan dengan pembaruan kondisi otomatis saat sesi diselesaikan.
              </CardDescription>
            </div>
            {canManageAssets && (
              <Button
                size="sm"
                onClick={handleOpenAuditModal}
                className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1.5"
              >
                <Plus className="w-4 h-4" />
                Mulai Sesi Opname Baru
              </Button>
            )}
          </CardHeader>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-gray-600">
                <thead className="bg-gray-50 text-gray-700 font-semibold border-b border-gray-200 uppercase tracking-wider">
                  <tr>
                    <th className="py-3 px-4">Judul Sesi Opname</th>
                    <th className="py-3 px-4">Tanggal Audit</th>
                    <th className="py-3 px-4">Ruangan Target</th>
                    <th className="py-3 px-4">Petugas Auditor</th>
                    <th className="py-3 px-4">Jumlah Aset Diperiksa</th>
                    <th className="py-3 px-4">Status Sesi</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {audits.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="py-12 text-center text-gray-400">
                        Belum ada sesi stok opname tercatat.
                      </td>
                    </tr>
                  ) : (
                    audits.map((audit) => (
                      <tr key={audit.id} className="hover:bg-gray-50/70 transition-colors">
                        <td className="py-3 px-4 font-semibold text-gray-900">
                          {audit.title}
                          {audit.notes && (
                            <p className="text-gray-400 text-[11px] font-normal">{audit.notes}</p>
                          )}
                        </td>
                        <td className="py-3 px-4">
                          {audit.audit_date ? new Date(audit.audit_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'}
                        </td>
                        <td className="py-3 px-4 text-gray-800">
                          {audit.room?.name || 'Seluruh Ruangan'}
                        </td>
                        <td className="py-3 px-4 text-gray-700">
                          {audit.auditor?.username || '-'}
                        </td>
                        <td className="py-3 px-4 font-semibold text-gray-900">
                          {audit.items_count || (audit.items ? audit.items.length : 0)} Aset
                        </td>
                        <td className="py-3 px-4">
                          {audit.status === 'selesai' ? (
                            <Badge className="bg-emerald-600 text-white font-medium">Selesai</Badge>
                          ) : audit.status === 'proses' ? (
                            <Badge className="bg-blue-600 text-white font-medium">Sedang Berjalan</Badge>
                          ) : (
                            <Badge variant="outline" className="border-gray-300 text-gray-700">Draf</Badge>
                          )}
                        </td>
                        <td className="py-3 px-4 text-right">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenAuditDetail(audit)}
                            className="h-7 px-2.5 text-[11px] text-gray-700 hover:bg-gray-50"
                          >
                            <Eye className="w-3.5 h-3.5 mr-1" />
                            Detail Hasil
                          </Button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}

      {/* =================================================================== */}
      {/* TAB 3: SCAN / LOOKUP CEPAT (BE-SAR-02 / ADM-SAR-02) */}
      {/* =================================================================== */}
      {activeTab === 'scan' && (
        <div className="max-w-2xl mx-auto space-y-4">
          <Card className="shadow-xs border-gray-200">
            <CardHeader className="text-center pb-3">
              <div className="mx-auto p-3 bg-emerald-100 text-emerald-700 rounded-full w-fit mb-2">
                <Scan className="w-8 h-8" />
              </div>
              <CardTitle className="text-lg font-bold text-gray-900">
                Pindai / Lookup Kode QR Aset
              </CardTitle>
              <CardDescription className="text-xs text-gray-500">
                Masukkan kode QR / Barcode aset untuk memeriksa identitas, lokasi, kondisi, dan riwayat perbaikannya seketika.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={handleScanLookup} className="flex gap-2">
                <Input
                  type="text"
                  placeholder="Ketik atau scan barcode (contoh: AST-PC-LAB-001)..."
                  value={scanCodeInput}
                  onChange={(e) => setScanCodeInput(e.target.value)}
                  className="font-mono text-sm"
                  autoFocus
                />
                <Button
                  type="submit"
                  disabled={isScanning || !scanCodeInput.trim()}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-5"
                >
                  {isScanning ? 'Mencari...' : 'Cari'}
                </Button>
              </form>

              {scanError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs flex items-center gap-2">
                  <AlertCircle className="w-4 h-4 flex-shrink-0" />
                  <span>{scanError}</span>
                </div>
              )}

              {scannedAsset && (
                <div className="p-4 bg-emerald-50/50 border border-emerald-200 rounded-xl space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="font-mono text-xs font-bold text-emerald-800 bg-white px-2 py-0.5 rounded border border-emerald-200">
                      {scannedAsset.asset_code}
                    </span>
                    {getConditionBadge(scannedAsset.asset_condition)}
                  </div>

                  <div>
                    <h3 className="font-bold text-gray-900 text-base">{scannedAsset.name}</h3>
                    <p className="text-xs text-gray-500">{scannedAsset.category}</p>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-emerald-200">
                    <div>
                      <span className="text-gray-400 text-[10px] uppercase">Lokasi Ruangan</span>
                      <p className="font-semibold text-gray-800">{scannedAsset.room?.name || 'Belum ditentukan'}</p>
                    </div>
                    <div>
                      <span className="text-gray-400 text-[10px] uppercase">Merk & Model</span>
                      <p className="font-semibold text-gray-800">{scannedAsset.brand || '-'} {scannedAsset.model || ''}</p>
                    </div>
                  </div>

                  <div className="flex items-center justify-end gap-2 pt-2 border-t border-emerald-200">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleOpenQrModal(scannedAsset)}
                      className="text-xs h-8 text-emerald-700 border-emerald-300 hover:bg-emerald-100"
                    >
                      <Printer className="w-3.5 h-3.5 mr-1" />
                      Cetak Label QR
                    </Button>
                    <Button
                      size="sm"
                      onClick={() => handleOpenDetailModal(scannedAsset)}
                      className="text-xs h-8 bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                      Lihat Riwayat & Detail
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 1: FORM TAMBAH / EDIT ASET */}
      {/* =================================================================== */}
      {isAssetModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <h2 className="font-semibold text-gray-900 text-sm flex items-center gap-2">
                <Boxes className="w-4 h-4 text-emerald-600" />
                {editingAsset ? 'Edit Data Aset Inventaris' : 'Registrasi Aset Baru'}
              </h2>
              <button onClick={() => setIsAssetModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveAsset} className="p-4 space-y-4">
              {assetFormError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
                  {assetFormError}
                </div>
              )}

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">
                    Kode Aset (QR / Barcode) <span className="text-red-500">*</span>
                  </label>
                  <Input
                    type="text"
                    placeholder="Contoh: AST-PC-LAB-001"
                    value={formAssetCode}
                    onChange={(e) => setFormAssetCode(e.target.value)}
                    className="font-mono text-xs"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">
                    Kategori Aset <span className="text-red-500">*</span>
                  </label>
                  <Input
                    type="text"
                    placeholder="Elektronik, Furnitur, Lab..."
                    value={formCategory}
                    onChange={(e) => setFormCategory(e.target.value)}
                    className="text-xs"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Nama Barang / Aset <span className="text-red-500">*</span>
                </label>
                <Input
                  type="text"
                  placeholder="Contoh: PC Desktop All-in-One HP Pavilion"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  className="text-xs"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">
                    Lokasi Ruangan
                  </label>
                  <select
                    value={formRoomId}
                    onChange={(e) => setFormRoomId(e.target.value)}
                    className="w-full h-9 px-2 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                  >
                    <option value="">Pilih Ruangan...</option>
                    {rooms.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name} ({r.code})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">
                    Kondisi Fisik
                  </label>
                  <select
                    value={formCondition}
                    onChange={(e) => setFormCondition(e.target.value as AssetCondition)}
                    className="w-full h-9 px-2 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                  >
                    <option value="baik">Baik (Layak Pakai)</option>
                    <option value="rusak_ringan">Rusak Ringan</option>
                    <option value="rusak_berat">Rusak Berat</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-2">
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Merk / Brand</label>
                  <Input
                    type="text"
                    placeholder="HP, Epson..."
                    value={formBrand}
                    onChange={(e) => setFormBrand(e.target.value)}
                    className="text-xs"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Tipe / Model</label>
                  <Input
                    type="text"
                    placeholder="Pavilion 24..."
                    value={formModel}
                    onChange={(e) => setFormModel(e.target.value)}
                    className="text-xs"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">No. Seri</label>
                  <Input
                    type="text"
                    placeholder="SN-12345..."
                    value={formSerialNumber}
                    onChange={(e) => setFormSerialNumber(e.target.value)}
                    className="text-xs font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Catatan Keterangan / Sumber Pengadaan
                </label>
                <Textarea
                  placeholder="Contoh: Hibah Lab Komputer 2026, Garansi resmi 2 tahun..."
                  value={formNotes}
                  onChange={(e) => setFormNotes(e.target.value)}
                  rows={2}
                  className="text-xs"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsAssetModalOpen(false)}
                  disabled={isSubmittingAsset}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingAsset}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white"
                >
                  {isSubmittingAsset ? 'Menyimpan...' : 'Simpan Data Aset'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 2: CETAK LABEL QR FISIK */}
      {/* =================================================================== */}
      {isQrModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <h2 className="font-semibold text-gray-900 text-sm flex items-center gap-2">
                <Printer className="w-4 h-4 text-emerald-600" />
                Cetak Label QR Fisik Aset
              </h2>
              <button onClick={() => setIsQrModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 space-y-4">
              {isLoadingQr ? (
                <div className="py-12 text-center text-gray-400 text-xs">
                  <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-600" />
                  Menyiapkan label barcode/QR...
                </div>
              ) : qrData ? (
                <div className="border-2 border-dashed border-gray-300 rounded-xl p-4 bg-white space-y-3 print:border-solid print:m-0">
                  {/* Kop Label */}
                  <div className="text-center border-b pb-2">
                    <p className="text-[10px] uppercase font-bold tracking-widest text-emerald-800">
                      {qrData.label_title}
                    </p>
                    <p className="text-xs font-bold text-gray-900 mt-0.5">SISTLABUM — INVENTARISASI</p>
                  </div>

                  {/* QR Pattern Visual */}
                  <div className="flex flex-col items-center justify-center py-2">
                    <div className="p-3 bg-white border border-gray-200 rounded-lg shadow-2xs flex flex-col items-center">
                      <QrCode className="w-24 h-24 text-gray-900" />
                      <span className="font-mono text-xs font-bold text-gray-900 mt-1">
                        {qrData.qr_code}
                      </span>
                    </div>
                  </div>

                  {/* Informasi Barang */}
                  <div className="text-xs space-y-1 bg-gray-50 p-2.5 rounded-lg border">
                    <p className="font-bold text-gray-900 line-clamp-1">{qrData.asset.name}</p>
                    <p className="text-[11px] text-gray-600">Kategori: <b>{qrData.asset.category}</b></p>
                    <p className="text-[11px] text-gray-600">Ruangan: <b>{qrData.asset.room?.name || 'Belum ditentukan'}</b></p>
                  </div>
                </div>
              ) : null}

              <div className="flex items-center justify-end gap-2 pt-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setIsQrModalOpen(false)}
                >
                  Tutup
                </Button>
                <Button
                  size="sm"
                  onClick={() => window.print()}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white"
                >
                  <Printer className="w-4 h-4 mr-1.5" />
                  Cetak Sekarang
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 3: DETAIL ASET & RIWAYAT TIKET/AUDIT */}
      {/* =================================================================== */}
      {isDetailModalOpen && selectedAssetForDetail && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono font-bold text-emerald-800 text-sm">
                    {selectedAssetForDetail.asset_code}
                  </span>
                  {getConditionBadge(selectedAssetForDetail.asset_condition)}
                </div>
                <h3 className="font-bold text-gray-900 text-base mt-0.5">
                  {selectedAssetForDetail.name}
                </h3>
              </div>
              <button onClick={() => setIsDetailModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-5 overflow-y-auto space-y-6 flex-1 text-xs">
              {/* Info Grid */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-gray-50 p-3 rounded-lg border">
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Kategori</p>
                  <p className="font-semibold text-gray-800 mt-0.5">{selectedAssetForDetail.category}</p>
                </div>
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Ruangan</p>
                  <p className="font-semibold text-gray-800 mt-0.5">{selectedAssetForDetail.room?.name || '-'}</p>
                </div>
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Merk / Model</p>
                  <p className="font-semibold text-gray-800 mt-0.5">
                    {selectedAssetForDetail.brand || '-'} {selectedAssetForDetail.model ? `(${selectedAssetForDetail.model})` : ''}
                  </p>
                </div>
                <div>
                  <p className="text-gray-400 text-[10px] uppercase font-medium">Nomor Seri</p>
                  <p className="font-mono text-gray-800 mt-0.5">{selectedAssetForDetail.serial_number || '-'}</p>
                </div>
              </div>

              {/* Riwayat Pemeliharaan & Tiket Kerusakan (Integrasi BE-SAR-03) */}
              <div>
                <h4 className="font-bold text-gray-900 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                  <AlertTriangle className="w-4 h-4 text-amber-500" />
                  Riwayat Kerusakan & Tiket Helpdesk Terhubung ({selectedAssetForDetail.tickets?.length || 0})
                </h4>
                {(!selectedAssetForDetail.tickets || selectedAssetForDetail.tickets.length === 0) ? (
                  <p className="text-gray-400 italic bg-gray-50 p-3 rounded-lg border">
                    Tidak ada riwayat tiket kerusakan yang pernah dilaporkan untuk aset ini.
                  </p>
                ) : (
                  <div className="space-y-2">
                    {selectedAssetForDetail.tickets.map((t) => (
                      <div key={t.id} className="p-3 bg-white border rounded-lg flex items-center justify-between">
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-mono font-bold text-gray-900">{t.ticket_number}</span>
                            <Badge variant="outline" className="text-[10px] capitalize">{t.priority}</Badge>
                            <Badge className="text-[10px] bg-blue-600 text-white">{t.status}</Badge>
                          </div>
                          <p className="text-gray-700 mt-1 font-medium">{t.title || t.description}</p>
                        </div>
                        <span className="text-gray-400 text-[11px]">
                          {new Date(t.created_at).toLocaleDateString('id-ID')}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Riwayat Stok Opname */}
              <div>
                <h4 className="font-bold text-gray-900 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                  <ClipboardCheck className="w-4 h-4 text-indigo-500" />
                  Riwayat Stok Opname Kondisi ({selectedAssetForDetail.auditItems?.length || 0})
                </h4>
                {(!selectedAssetForDetail.auditItems || selectedAssetForDetail.auditItems.length === 0) ? (
                  <p className="text-gray-400 italic bg-gray-50 p-3 rounded-lg border">
                    Aset ini belum pernah dimasukkan dalam sesi pemeriksaan stok opname.
                  </p>
                ) : (
                  <div className="space-y-2">
                    {selectedAssetForDetail.auditItems.map((item) => (
                      <div key={item.id} className="p-3 bg-white border rounded-lg flex items-center justify-between">
                        <div>
                          <p className="font-semibold text-gray-900">{item.audit?.title || 'Sesi Audit'}</p>
                          <p className="text-gray-500 text-[11px]">
                            Kondisi Ditemukan: <b>{item.found_condition}</b> — {item.is_present ? 'Ada di tempat' : 'Tidak ditemukan'}
                          </p>
                          {item.notes && <p className="text-gray-400 text-[10px] mt-0.5">{item.notes}</p>}
                        </div>
                        <span className="text-gray-400 text-[11px]">
                          {item.audit?.audit_date ? new Date(item.audit.audit_date).toLocaleDateString('id-ID') : '-'}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>

            <div className="p-3 border-t bg-gray-50 flex items-center justify-end">
              <Button variant="outline" size="sm" onClick={() => setIsDetailModalOpen(false)}>
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 4: SESI STOK OPNAME BARU */}
      {/* =================================================================== */}
      {isAuditModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <h2 className="font-semibold text-gray-900 text-sm flex items-center gap-2">
                <ClipboardCheck className="w-4 h-4 text-indigo-600" />
                Mulai Sesi Stok Opname Aset Baru
              </h2>
              <button onClick={() => setIsAuditModalOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveAudit} className="p-5 overflow-y-auto space-y-4 flex-1 text-xs">
              {auditFormError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
                  {auditFormError}
                </div>
              )}

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">
                    Judul Sesi Opname <span className="text-red-500">*</span>
                  </label>
                  <Input
                    type="text"
                    value={auditTitle}
                    onChange={(e) => setAuditTitle(e.target.value)}
                    required
                    className="text-xs"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Tanggal Audit</label>
                  <Input
                    type="date"
                    value={auditDate}
                    onChange={(e) => setAuditDate(e.target.value)}
                    required
                    className="text-xs"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Ruangan Target</label>
                  <select
                    value={auditRoomId}
                    onChange={(e) => handleRoomChangeForAudit(e.target.value)}
                    className="w-full h-9 px-2 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  >
                    <option value="">Seluruh Ruangan ({assets.length} aset)</option>
                    {rooms.map((r) => (
                      <option key={r.id} value={r.id}>{r.name}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Status Sesi</label>
                  <select
                    value={auditStatus}
                    onChange={(e) => setAuditStatus(e.target.value as AssetAuditStatus)}
                    className="w-full h-9 px-2 text-xs rounded-md border border-gray-300 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  >
                    <option value="selesai">Selesai (Langsung Sinkronkan Kondisi)</option>
                    <option value="proses">Sedang Berjalan</option>
                    <option value="draf">Draf</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">Catatan Audit</label>
                <Textarea
                  placeholder="Catatan umum sesi stok opname fisik..."
                  value={auditNotes}
                  onChange={(e) => setAuditNotes(e.target.value)}
                  rows={2}
                  className="text-xs"
                />
              </div>

              {/* Checklist Barang */}
              <div>
                <h3 className="font-bold text-gray-900 mb-2">
                  Checklist Fisik Aset Terpilih ({auditItemsState.length} Barang)
                </h3>
                <div className="border rounded-lg max-h-52 overflow-y-auto divide-y">
                  {auditItemsState.map((item, idx) => {
                    const assetObj = assets.find((a) => a.id === item.asset_id);
                    return (
                      <div key={item.asset_id} className="p-2.5 flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2 flex-1">
                          <input
                            type="checkbox"
                            checked={item.is_present}
                            onChange={(e) => {
                              const checked = e.target.checked;
                              setAuditItemsState((prev) =>
                                prev.map((it, i) => (i === idx ? { ...it, is_present: checked } : it))
                              );
                            }}
                            className="rounded text-indigo-600 focus:ring-indigo-500"
                          />
                          <div>
                            <span className="font-semibold text-gray-800">{assetObj?.name || 'Aset'}</span>
                            <span className="font-mono text-[10px] text-gray-400 ml-1">({assetObj?.asset_code})</span>
                          </div>
                        </div>

                        <select
                          value={item.found_condition}
                          onChange={(e) => {
                            const val = e.target.value as AssetCondition;
                            setAuditItemsState((prev) =>
                              prev.map((it, i) => (i === idx ? { ...it, found_condition: val } : it))
                            );
                          }}
                          className="h-7 text-[11px] px-1.5 rounded border border-gray-300 bg-white text-gray-700"
                        >
                          <option value="baik">Baik</option>
                          <option value="rusak_ringan">Rusak Ringan</option>
                          <option value="rusak_berat">Rusak Berat</option>
                        </select>
                      </div>
                    );
                  })}
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsAuditModalOpen(false)}
                  disabled={isSubmittingAudit}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmittingAudit}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white"
                >
                  {isSubmittingAudit ? 'Menyimpan...' : 'Simpan & Sinkronkan Opname'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =================================================================== */}
      {/* MODAL 5: DETAIL SESI STOK OPNAME */}
      {/* =================================================================== */}
      {isAuditDetailOpen && selectedAuditForDetail && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] flex flex-col overflow-hidden border">
            <div className="flex items-center justify-between p-4 border-b bg-gray-50">
              <div>
                <h3 className="font-bold text-gray-900 text-sm">{selectedAuditForDetail.title}</h3>
                <p className="text-[11px] text-gray-500">
                  Tanggal: {selectedAuditForDetail.audit_date} | Auditor: {selectedAuditForDetail.auditor?.username || '-'}
                </p>
              </div>
              <button onClick={() => setIsAuditDetailOpen(false)} className="text-gray-400 hover:text-gray-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-4 overflow-y-auto space-y-3 flex-1 text-xs">
              <h4 className="font-bold text-gray-900 uppercase tracking-wider text-[11px]">
                Hasil Pemeriksaan Aset ({selectedAuditForDetail.items?.length || 0} Barang)
              </h4>

              <div className="border rounded-lg divide-y">
                {(!selectedAuditForDetail.items || selectedAuditForDetail.items.length === 0) ? (
                  <p className="p-4 text-center text-gray-400 italic">Tidak ada item tercatat dalam sesi ini.</p>
                ) : (
                  selectedAuditForDetail.items.map((it) => (
                    <div key={it.id} className="p-2.5 flex items-center justify-between">
                      <div>
                        <p className="font-semibold text-gray-800">{it.asset?.name || 'Aset'}</p>
                        <p className="font-mono text-[10px] text-gray-400">{it.asset?.asset_code}</p>
                      </div>
                      <div className="text-right">
                        {getConditionBadge(it.found_condition)}
                        <p className="text-[10px] text-gray-500 mt-0.5">
                          {it.is_present ? 'Ditemukan' : 'Tidak Ditemukan'}
                        </p>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>

            <div className="p-3 border-t bg-gray-50 flex items-center justify-end">
              <Button variant="outline" size="sm" onClick={() => setIsAuditDetailOpen(false)}>
                Tutup
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Dialog Konfirmasi Hapus Aset */}
      <ConfirmDialog
        isOpen={!!assetToDelete}
        onClose={() => setAssetToDelete(null)}
        onConfirm={handleConfirmDeleteAsset}
        title="Hapus Aset"
        description="Apakah Anda yakin ingin menghapus data aset ini? Tindakan ini tidak dapat dibatalkan."
        affectedItemName={assetToDelete ? `${assetToDelete.name} (${assetToDelete.asset_code})` : undefined}
        confirmLabel="Ya, Hapus"
        cancelLabel="Batal"
        variant="danger"
        isLoading={isDeletingAsset}
      />
    </div>
  );
}
