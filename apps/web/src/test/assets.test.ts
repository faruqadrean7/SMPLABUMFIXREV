import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  assetsApi,
  AssetItem,
  AssetSummary,
  AssetQrData,
  AssetAuditSession,
  CreateAssetPayload,
  UpdateAssetPayload,
  CreateAssetAuditPayload,
  UpdateAssetAuditPayload,
} from '@/features/sarpras/assets-api';
import { apiClient } from '@/lib/api/api-client';

describe('BE-SAR-02 / ADM-SAR-02: Sirkulasi Aset & Inventarisasi QR/Barcode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. MASTER ASET & FILTER
  // =========================================================================
  describe('1. Master Aset & Filter', () => {
    it('mengambil daftar aset dengan filter ruangan, kategori, kondisi, dan pencarian', async () => {
      const mockAssets: AssetItem[] = [
        {
          id: 'asset-1',
          school_id: 'school-1',
          asset_code: 'AST-LAB-001',
          name: 'Mikroskop Binokuler Olympus CX23',
          category: 'Laboratorium',
          brand: 'Olympus',
          model: 'CX23',
          serial_number: 'SN-OLY-9921',
          asset_condition: 'baik',
          room_id: 'room-lab-bio',
          acquired_at: '2024-01-15',
          notes: 'Kondisi optik bersih dan terkalibrasi',
          created_at: '2024-01-15T08:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
          room: { id: 'room-lab-bio', name: 'Laboratorium Biologi', code: 'LAB-BIO' },
        },
        {
          id: 'asset-2',
          school_id: 'school-1',
          asset_code: 'AST-KOMP-015',
          name: 'PC Desktop Core i7 16GB',
          category: 'Elektronik',
          brand: 'Dell',
          model: 'OptiPlex 7090',
          serial_number: 'SN-DELL-4412',
          asset_condition: 'rusak_ringan',
          room_id: 'room-lab-komp',
          acquired_at: '2023-06-20',
          notes: 'Kipas power supply bising',
          created_at: '2023-06-20T08:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
          room: { id: 'room-lab-komp', name: 'Laboratorium Komputer', code: 'LAB-KOMP' },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockAssets,
          meta: { page: 1, limit: 15, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await assetsApi.getAssets({
        category: 'Laboratorium',
        asset_condition: 'baik',
        search: 'Mikroskop',
        room_id: 'room-lab-bio',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/assets', {
        params: {
          category: 'Laboratorium',
          asset_condition: 'baik',
          search: 'Mikroskop',
          room_id: 'room-lab-bio',
        },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].asset_code).toBe('AST-LAB-001');
      expect(res.data[0].asset_condition).toBe('baik');
    });

    it('mengambil detail aset tunggal beserta riwayat tiket perbaikan dan audit fisik', async () => {
      const mockDetail: AssetItem = {
        id: 'asset-1',
        school_id: 'school-1',
        asset_code: 'AST-LAB-001',
        name: 'Mikroskop Binokuler Olympus CX23',
        category: 'Laboratorium',
        asset_condition: 'rusak_ringan',
        room_id: 'room-lab-bio',
        created_at: '2024-01-15T08:00:00Z',
        updated_at: '2026-10-14T08:00:00Z',
        room: { id: 'room-lab-bio', name: 'Laboratorium Biologi', code: 'LAB-BIO' },
        tickets: [
          {
            id: 'tck-101',
            ticket_number: 'TCK-202610-0088',
            title: 'Lensa Okuler Berjamur',
            description: 'Perlu pembersihan kimia optik',
            priority: 'sedang',
            status: 'diproses',
            created_at: '2026-10-13T10:00:00Z',
            reporter: { id: 'u-1', username: 'guru_bio' },
          },
        ],
        auditItems: [
          {
            id: 'ai-1',
            audit_id: 'audit-sess-1',
            found_condition: 'rusak_ringan',
            is_present: true,
            notes: 'Jamur terdeteksi pada optik kanan',
            audit: {
              id: 'audit-sess-1',
              title: 'Stok Opname Semester Ganjil 2026',
              audit_date: '2026-10-01',
              status: 'selesai',
            },
          },
        ],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockDetail },
      } as any);

      const res = await assetsApi.getAsset('asset-1');

      expect(apiClient.get).toHaveBeenCalledWith('/assets/asset-1');
      expect(res.data.id).toBe('asset-1');
      expect(res.data.tickets).toHaveLength(1);
      expect(res.data.tickets![0].ticket_number).toBe('TCK-202610-0088');
      expect(res.data.auditItems).toHaveLength(1);
    });

    it('membuat aset baru dengan payload valid', async () => {
      const payload: CreateAssetPayload = {
        asset_code: 'AST-PROJ-009',
        name: 'Proyektor Epson EB-X51',
        category: 'Elektronik',
        room_id: 'room-xa',
        brand: 'Epson',
        model: 'EB-X51',
        serial_number: 'SN-EPS-1102',
        asset_condition: 'baik',
        acquired_at: '2026-02-10',
        notes: 'Pemasangan di plafon kelas X-A',
      };

      const mockCreated: AssetItem = {
        id: 'asset-new-1',
        ...payload,
        asset_condition: payload.asset_condition || 'baik',
        created_at: '2026-10-14T08:00:00Z',
        updated_at: '2026-10-14T08:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockCreated },
      } as any);

      const res = await assetsApi.createAsset(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/assets', payload);
      expect(res.data.asset_code).toBe('AST-PROJ-009');
      expect(res.data.id).toBe('asset-new-1');
    });

    it('memperbarui aset yang sudah ada', async () => {
      const payload: UpdateAssetPayload = {
        name: 'Proyektor Epson EB-X51 Updated',
        asset_condition: 'rusak_ringan',
        notes: 'Lampu proyektor mulai redup',
      };

      vi.spyOn(apiClient, 'put').mockResolvedValueOnce({
        data: {
          data: {
            id: 'asset-1',
            asset_code: 'AST-PROJ-009',
            name: payload.name,
            asset_condition: payload.asset_condition,
            notes: payload.notes,
            created_at: '2026-02-10T08:00:00Z',
            updated_at: '2026-10-14T09:00:00Z',
          },
        },
      } as any);

      const res = await assetsApi.updateAsset('asset-1', payload);

      expect(apiClient.put).toHaveBeenCalledWith('/assets/asset-1', payload);
      expect(res.data.name).toBe('Proyektor Epson EB-X51 Updated');
      expect(res.data.asset_condition).toBe('rusak_ringan');
    });

    it('menghapus aset dari inventaris', async () => {
      vi.spyOn(apiClient, 'delete').mockResolvedValueOnce({
        data: { data: { message: 'Asset deleted' } },
      } as any);

      const res = await assetsApi.deleteAsset('asset-del-1');

      expect(apiClient.delete).toHaveBeenCalledWith('/assets/asset-del-1');
      expect(res.data.message).toBe('Asset deleted');
    });
  });

  // =========================================================================
  // 2. METRIK RINGKASAN & QR SCANNER / LOOKUP
  // =========================================================================
  describe('2. Metrik Ringkasan & QR Code Scanner', () => {
    it('mengambil metrik ringkasan aset dan kondisi untuk dashboard kpi', async () => {
      const mockSummary: AssetSummary = {
        total_assets: 142,
        condition_baik: 118,
        condition_rusak_ringan: 18,
        condition_rusak_berat: 6,
        category_counts: {
          Laboratorium: 45,
          Elektronik: 52,
          Mebel: 35,
          Olahraga: 10,
        },
        total_audits: 8,
        completed_audits: 7,
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSummary },
      } as any);

      const res = await assetsApi.getSummary();

      expect(apiClient.get).toHaveBeenCalledWith('/assets/summary');
      expect(res.data.total_assets).toBe(142);
      expect(res.data.condition_baik).toBe(118);
      expect(res.data.category_counts['Laboratorium']).toBe(45);
    });

    it('melakukan pencarian cepat aset via barcode/QR code', async () => {
      const mockFoundAsset: AssetItem = {
        id: 'asset-scanner-1',
        asset_code: 'AST/2026/009',
        name: 'Smart TV Samsung 65 Inch',
        category: 'Elektronik',
        asset_condition: 'baik',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-10-14T08:00:00Z',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockFoundAsset },
      } as any);

      const res = await assetsApi.getAssetByCode('AST/2026/009');

      expect(apiClient.get).toHaveBeenCalledWith('/assets/by-code/AST%2F2026%2F009');
      expect(res.data.asset_code).toBe('AST/2026/009');
      expect(res.data.name).toBe('Smart TV Samsung 65 Inch');
    });

    it('mengambil payload cetak label QR untuk aset fisik', async () => {
      const mockQrData: AssetQrData = {
        asset: {
          id: 'asset-qr-1',
          asset_code: 'AST-LAB-001',
          name: 'Mikroskop Binokuler',
          category: 'Laboratorium',
          asset_condition: 'baik',
          created_at: '2026-01-01T00:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
        },
        qr_code: 'AST-LAB-001',
        qr_data_string: '{"system":"SISTLABUM","module":"SARPRAS_ASSET","asset_id":"asset-qr-1"}',
        qr_payload: {
          system: 'SISTLABUM',
          module: 'SARPRAS_ASSET',
          asset_id: 'asset-qr-1',
          code: 'AST-LAB-001',
          name: 'Mikroskop Binokuler',
          room: 'Laboratorium Biologi',
          category: 'Laboratorium',
        },
        label_title: 'SISTLABUM INVENTARIS ASET',
        printed_at: '2026-10-14T08:30:00Z',
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockQrData },
      } as any);

      const res = await assetsApi.getAssetQr('asset-qr-1');

      expect(apiClient.get).toHaveBeenCalledWith('/assets/asset-qr-1/qr');
      expect(res.data.qr_code).toBe('AST-LAB-001');
      expect(res.data.qr_payload.system).toBe('SISTLABUM');
      expect(res.data.label_title).toBe('SISTLABUM INVENTARIS ASET');
    });
  });

  // =========================================================================
  // 3. SESI STOK OPNAME & AUDIT FISIK
  // =========================================================================
  describe('3. Sesi Stok Opname & Audit Fisik', () => {
    it('mengambil daftar sesi stok opname dengan filter status dan tanggal', async () => {
      const mockSessions: AssetAuditSession[] = [
        {
          id: 'audit-sess-1',
          school_id: 'school-1',
          title: 'Audit Semester Ganjil 2026/2027',
          audit_date: '2026-10-01',
          auditor_id: 'user-auditor-1',
          status: 'selesai',
          notes: 'Audit seluruh inventaris Lab Komputer dan Lab Bahasa',
          created_at: '2026-10-01T07:00:00Z',
          updated_at: '2026-10-05T12:00:00Z',
          auditor: { id: 'user-auditor-1', username: 'staf_sarpras' },
          items_count: 48,
        },
        {
          id: 'audit-sess-2',
          school_id: 'school-1',
          title: 'Inspeksi Ruang Kelas X dan XI',
          audit_date: '2026-10-14',
          auditor_id: 'user-auditor-1',
          status: 'proses',
          created_at: '2026-10-14T07:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
          auditor: { id: 'user-auditor-1', username: 'staf_sarpras' },
          items_count: 24,
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockSessions,
          meta: { page: 1, limit: 15, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await assetsApi.getAudits({
        status: 'selesai',
        from_date: '2026-10-01',
        to_date: '2026-10-31',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/asset-audits', {
        params: {
          status: 'selesai',
          from_date: '2026-10-01',
          to_date: '2026-10-31',
        },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].status).toBe('selesai');
      expect(res.data[0].items_count).toBe(48);
    });

    it('mengambil detail satu sesi audit beserta daftar item fisik yang diaudit', async () => {
      const mockDetailSession: AssetAuditSession = {
        id: 'audit-sess-1',
        title: 'Audit Semester Ganjil',
        audit_date: '2026-10-01',
        auditor_id: 'user-auditor-1',
        status: 'selesai',
        created_at: '2026-10-01T08:00:00Z',
        updated_at: '2026-10-05T10:00:00Z',
        auditor: { id: 'user-auditor-1', username: 'staf_sarpras' },
        items: [
          {
            id: 'item-audit-1',
            audit_id: 'audit-sess-1',
            asset_id: 'asset-1',
            found_condition: 'baik',
            is_present: true,
            notes: 'Fisik lengkap di meja 1',
            asset: {
              id: 'asset-1',
              asset_code: 'AST-LAB-001',
              name: 'Mikroskop CX23',
              category: 'Laboratorium',
              asset_condition: 'baik',
              created_at: '2024-01-15T08:00:00Z',
              updated_at: '2026-10-14T08:00:00Z',
            },
          },
          {
            id: 'item-audit-2',
            audit_id: 'audit-sess-1',
            asset_id: 'asset-2',
            found_condition: 'rusak_ringan',
            is_present: true,
            notes: 'Power supply butuh servis',
          },
        ],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockDetailSession },
      } as any);

      const res = await assetsApi.getAudit('audit-sess-1');

      expect(apiClient.get).toHaveBeenCalledWith('/asset-audits/audit-sess-1');
      expect(res.data.id).toBe('audit-sess-1');
      expect(res.data.items).toHaveLength(2);
      expect(res.data.items![0].is_present).toBe(true);
      expect(res.data.items![0].found_condition).toBe('baik');
    });

    it('membuat sesi audit stok opname baru', async () => {
      const payload: CreateAssetAuditPayload = {
        title: 'Stok Opname Peralatan Musik',
        audit_date: '2026-10-15',
        room_id: 'room-musik',
        status: 'draf',
        notes: 'Inventarisasi alat musik band dan gamelan',
        items: [
          {
            asset_id: 'asset-drum-1',
            is_present: true,
            found_condition: 'baik',
            notes: 'Snare drum baru ganti kulit',
          },
        ],
      };

      const mockCreated: AssetAuditSession = {
        id: 'audit-new-99',
        title: payload.title,
        audit_date: payload.audit_date,
        room_id: payload.room_id,
        status: payload.status || 'draf',
        notes: payload.notes,
        auditor_id: 'current-user-id',
        items: [
          {
            id: 'item-1',
            audit_id: 'audit-new-99',
            asset_id: 'asset-drum-1',
            is_present: true,
            found_condition: 'baik',
            notes: 'Snare drum baru ganti kulit',
          },
        ],
        created_at: '2026-10-14T08:30:00Z',
        updated_at: '2026-10-14T08:30:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockCreated },
      } as any);

      const res = await assetsApi.createAudit(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/asset-audits', payload);
      expect(res.data.id).toBe('audit-new-99');
      expect(res.data.title).toBe('Stok Opname Peralatan Musik');
      expect(res.data.status).toBe('draf');
    });

    it('memperbarui status sesi audit dan hasil cek fisik aset', async () => {
      const payload: UpdateAssetAuditPayload = {
        status: 'selesai',
        notes: 'Seluruh aset musik telah diverifikasi fisik',
        items: [
          {
            asset_id: 'asset-drum-1',
            is_present: true,
            found_condition: 'baik',
            notes: 'Lengkap dan siap pakai',
          },
        ],
      };

      const mockUpdated: AssetAuditSession = {
        id: 'audit-new-99',
        title: 'Stok Opname Peralatan Musik',
        audit_date: '2026-10-15',
        auditor_id: 'current-user-id',
        status: 'selesai',
        notes: payload.notes,
        created_at: '2026-10-14T08:30:00Z',
        updated_at: '2026-10-14T09:00:00Z',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: { data: mockUpdated },
      } as any);

      const res = await assetsApi.updateAudit('audit-new-99', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/asset-audits/audit-new-99', payload);
      expect(res.data.status).toBe('selesai');
      expect(res.data.notes).toBe('Seluruh aset musik telah diverifikasi fisik');
    });
  });
});
