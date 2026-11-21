import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  ticketsApi,
  TicketItem,
  TicketSummary,
  TechnicianItem,
  TicketEventItem,
} from '@/features/sarpras/tickets-api';
import { apiClient } from '@/lib/api/api-client';

describe('BE-SAR-03 / ADM-SAR-03: Helpdesk Perbaikan & Kerusakan Sarpras', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // 1. PENGAMBILAN DATA TIKET & FILTER
  // =========================================================================
  describe('1. Pengambilan Data Tiket & Filter', () => {
    it('mengambil daftar tiket perbaikan dengan filter status, prioritas, dan pencarian', async () => {
      const mockTickets: TicketItem[] = [
        {
          id: 'tck-1',
          ticket_number: 'TCK-202610-0001',
          title: 'AC Bocor Menetes di Kelas X-A',
          reporter_id: 'user-guru-1',
          room_id: 'room-1',
          description: 'Air menetes ke meja baris depan',
          priority: 'tinggi',
          status: 'pending',
          created_at: '2026-10-14T08:00:00Z',
          updated_at: '2026-10-14T08:00:00Z',
          reporter: { id: 'user-guru-1', username: 'guru_ahmad' },
          room: { id: 'room-1', code: 'R-XA', name: 'Ruang Kelas X-A' },
        },
        {
          id: 'tck-2',
          ticket_number: 'TCK-202610-0002',
          title: 'Kabel Proyektor Putus di Lab Komputer',
          reporter_id: 'user-guru-2',
          room_id: 'room-2',
          description: 'Kabel VGA/HDMI terkelupas',
          priority: 'darurat',
          status: 'diproses',
          created_at: '2026-10-13T10:00:00Z',
          updated_at: '2026-10-14T09:00:00Z',
          reporter: { id: 'user-guru-2', username: 'guru_dewi' },
          assignee: { id: 'user-tech-1', username: 'teknisi_joko' },
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: {
          data: mockTickets,
          meta: { page: 1, limit: 15, total: 2, totalPages: 1 },
        },
      } as any);

      const res = await ticketsApi.getTickets({
        status: 'pending',
        priority: 'tinggi',
        search: 'Bocor',
      });

      expect(apiClient.get).toHaveBeenCalledWith('/tickets', {
        params: { status: 'pending', priority: 'tinggi', search: 'Bocor' },
      });
      expect(res.data).toHaveLength(2);
      expect(res.data[0].ticket_number).toBe('TCK-202610-0001');
      expect(res.data[0].priority).toBe('tinggi');
    });

    it('mengambil detail satu tiket beserta riwayat timeline event', async () => {
      const mockTicketDetail: TicketItem = {
        id: 'tck-1',
        ticket_number: 'TCK-202610-0001',
        title: 'AC Bocor Menetes',
        reporter_id: 'user-guru-1',
        description: 'Air menetes ke meja baris depan',
        priority: 'tinggi',
        status: 'diproses',
        created_at: '2026-10-14T08:00:00Z',
        updated_at: '2026-10-14T09:00:00Z',
        reporter: { id: 'user-guru-1', username: 'guru_ahmad' },
        assignee: { id: 'user-tech-1', username: 'teknisi_joko' },
        events: [
          {
            id: 'ev-1',
            ticket_id: 'tck-1',
            actor_id: 'user-guru-1',
            event_type: 'lapor',
            note: 'Tiket dibuat.',
            created_at: '2026-10-14T08:00:00Z',
            actor: { id: 'user-guru-1', username: 'guru_ahmad' },
          },
          {
            id: 'ev-2',
            ticket_id: 'tck-1',
            actor_id: 'user-sarpras-1',
            event_type: 'disposisi',
            note: 'Mohon dicek selang pembuangan freon.',
            created_at: '2026-10-14T08:30:00Z',
            actor: { id: 'user-sarpras-1', username: 'staf_sarpras' },
          },
        ],
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockTicketDetail },
      } as any);

      const res = await ticketsApi.getTicket('tck-1');

      expect(apiClient.get).toHaveBeenCalledWith('/tickets/tck-1');
      expect(res.data.id).toBe('tck-1');
      expect(res.data.events).toHaveLength(2);
      expect(res.data.events?.[1].event_type).toBe('disposisi');
    });
  });

  // =========================================================================
  // 2. PELAPORAN TIKET BARU (BE-SAR-03)
  // =========================================================================
  describe('2. Pelaporan Tiket Baru', () => {
    it('mengirimkan request pembuatan tiket kerusakan baru dengan benar', async () => {
      const payload = {
        title: 'Kran Air Wastafel Patah',
        room_id: 'room-1',
        asset_id: 'asset-1',
        priority: 'sedang' as const,
        description: 'Kran air patah di wastafel dekat pintu samping.',
      };

      const mockCreatedTicket: TicketItem = {
        id: 'tck-new-1',
        ticket_number: 'TCK-202610-0003',
        title: payload.title,
        reporter_id: 'user-guru-1',
        room_id: payload.room_id,
        asset_id: payload.asset_id,
        description: payload.description,
        priority: payload.priority,
        status: 'pending',
        created_at: '2026-10-14T10:00:00Z',
        updated_at: '2026-10-14T10:00:00Z',
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockCreatedTicket },
      } as any);

      const res = await ticketsApi.createTicket(payload);

      expect(apiClient.post).toHaveBeenCalledWith('/tickets', payload);
      expect(res.data.status).toBe('pending');
      expect(res.data.ticket_number).toBe('TCK-202610-0003');
    });
  });

  // =========================================================================
  // 3. DISPOSISI & PENUGASAN TEKNISI (ADM-SAR-03)
  // =========================================================================
  describe('3. Disposisi & Penugasan Teknisi', () => {
    it('mendisposisikan tiket kerusakan kepada teknisi yang ditugaskan', async () => {
      const payload = {
        assignee_id: 'user-tech-1',
        note: 'Harap periksa suku cadang sebelum jam istirahat kedua.',
      };

      const mockDisposedTicket: TicketItem = {
        id: 'tck-1',
        ticket_number: 'TCK-202610-0001',
        reporter_id: 'user-guru-1',
        description: 'Kerusakan meja guru',
        priority: 'sedang',
        status: 'diproses',
        assignee_id: 'user-tech-1',
        created_at: '2026-10-14T08:00:00Z',
        updated_at: '2026-10-14T08:30:00Z',
        assignee: { id: 'user-tech-1', username: 'teknisi_joko' },
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockDisposedTicket },
      } as any);

      const res = await ticketsApi.disposeTicket('tck-1', payload);

      expect(apiClient.post).toHaveBeenCalledWith('/tickets/tck-1/dispose', payload);
      expect(res.data.status).toBe('diproses');
      expect(res.data.assignee_id).toBe('user-tech-1');
    });
  });

  // =========================================================================
  // 4. PEMBARUAN STATUS & PENYELESAIAN PERBAIKAN
  // =========================================================================
  describe('4. Pembaruan Status & Penyelesaian Perbaikan', () => {
    it('memperbarui status tiket menjadi selesai beserta catatan hasil perbaikan', async () => {
      const payload = {
        status: 'selesai' as const,
        note: 'Kran telah diganti dengan tipe kuningan baru dan sambungan pipa telah di-seal.',
      };

      const mockResolvedTicket: TicketItem = {
        id: 'tck-1',
        ticket_number: 'TCK-202610-0001',
        reporter_id: 'user-guru-1',
        description: 'Kran bocor',
        priority: 'sedang',
        status: 'selesai',
        resolved_at: '2026-10-14T11:00:00Z',
        created_at: '2026-10-14T08:00:00Z',
        updated_at: '2026-10-14T11:00:00Z',
      };

      vi.spyOn(apiClient, 'patch').mockResolvedValueOnce({
        data: { data: mockResolvedTicket },
      } as any);

      const res = await ticketsApi.updateStatus('tck-1', payload);

      expect(apiClient.patch).toHaveBeenCalledWith('/tickets/tck-1/status', payload);
      expect(res.data.status).toBe('selesai');
      expect(res.data.resolved_at).toBe('2026-10-14T11:00:00Z');
    });

    it('menambahkan komentar pemantauan pada riwayat tiket', async () => {
      const payload = {
        note: 'Suku cadang sudah dipesan ke vendor dan diperkirakan tiba besok.',
      };

      const mockEvent: TicketEventItem = {
        id: 'ev-new-1',
        ticket_id: 'tck-1',
        actor_id: 'user-tech-1',
        event_type: 'komentar',
        note: payload.note,
        created_at: '2026-10-14T10:15:00Z',
        actor: { id: 'user-tech-1', username: 'teknisi_joko' },
      };

      vi.spyOn(apiClient, 'post').mockResolvedValueOnce({
        data: { data: mockEvent },
      } as any);

      const res = await ticketsApi.addComment('tck-1', payload);

      expect(apiClient.post).toHaveBeenCalledWith('/tickets/tck-1/comments', payload);
      expect(res.data.event_type).toBe('komentar');
      expect(res.data.note).toBe(payload.note);
    });
  });

  // =========================================================================
  // 5. METRIK RINGKASAN SLA & EVALUASI TEKNISI
  // =========================================================================
  describe('5. Metrik Ringkasan SLA & Evaluasi Teknisi', () => {
    it('mengambil ringkasan metrik SLA papan Kanban', async () => {
      const mockSummary: TicketSummary = {
        total: 15,
        pending: 4,
        diproses: 5,
        selesai: 6,
        darurat: 2,
        sla_warning: 3,
        avg_resolution_hours: 4.5,
      };

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockSummary },
      } as any);

      const res = await ticketsApi.getSummary();

      expect(apiClient.get).toHaveBeenCalledWith('/tickets/summary');
      expect(res.data.total).toBe(15);
      expect(res.data.pending).toBe(4);
      expect(res.data.sla_warning).toBe(3);
      expect(res.data.avg_resolution_hours).toBe(4.5);
    });

    it('mengambil daftar teknisi dan metrik beban kerja aktif untuk penugasan merata', async () => {
      const mockTechnicians: TechnicianItem[] = [
        {
          id: 'tech-1',
          name: 'Joko Susilo',
          username: 'teknisi_joko',
          email: 'joko@sekolah.sch.id',
          roles: { technician: 'Teknisi' },
          active_tickets_count: 2,
          completed_tickets_count: 14,
          avg_resolution_hours: 3.2,
        },
        {
          id: 'tech-2',
          name: 'Bambang Irawan',
          username: 'teknisi_bambang',
          email: 'bambang@sekolah.sch.id',
          roles: { technician: 'Teknisi' },
          active_tickets_count: 5,
          completed_tickets_count: 20,
          avg_resolution_hours: 5.1,
        },
      ];

      vi.spyOn(apiClient, 'get').mockResolvedValueOnce({
        data: { data: mockTechnicians },
      } as any);

      const res = await ticketsApi.getTechnicians();

      expect(apiClient.get).toHaveBeenCalledWith('/tickets/technicians');
      expect(res.data).toHaveLength(2);
      expect(res.data[0].active_tickets_count).toBe(2);
      expect(res.data[1].active_tickets_count).toBe(5);
    });

    it('mengambil referensi ruangan dan aset untuk form pelaporan', async () => {
      vi.spyOn(apiClient, 'get')
        .mockResolvedValueOnce({
          data: {
            data: [{ id: 'room-1', name: 'Lab Kimia', code: 'LAB-KIM' }],
            meta: { total: 1 },
          },
        } as any)
        .mockResolvedValueOnce({
          data: {
            data: [{ id: 'asset-1', name: 'Mikroskop Olympus', asset_code: 'AST-MIK-01' }],
            meta: { total: 1 },
          },
        } as any);

      const roomsRes = await ticketsApi.getRooms();
      const assetsRes = await ticketsApi.getAssets();

      expect(roomsRes.data[0].name).toBe('Lab Kimia');
      expect(assetsRes.data[0].asset_code).toBe('AST-MIK-01');
    });
  });

  // =========================================================================
  // 6. LOGIKA BISNIS SLA & PRIORITAS TIKET
  // =========================================================================
  describe('6. Logika Bisnis SLA & Prioritas Tiket', () => {
    it('mengidentifikasi tiket dengan SLA terlewati bila berstatus aktif dan dibuat > 24 jam lalu', () => {
      const now = Date.now();
      const thirtyHoursAgo = new Date(now - 30 * 60 * 60 * 1000).toISOString();
      const twoHoursAgo = new Date(now - 2 * 60 * 60 * 1000).toISOString();

      const ticketBreached: TicketItem = {
        id: 't-breach',
        ticket_number: 'TCK-202610-0991',
        description: 'Listrik mati',
        priority: 'darurat',
        status: 'diproses',
        reporter_id: 'user-1',
        created_at: thirtyHoursAgo,
        updated_at: thirtyHoursAgo,
      };

      const ticketNormal: TicketItem = {
        id: 't-normal',
        ticket_number: 'TCK-202610-0992',
        description: 'AC kurang dingin',
        priority: 'sedang',
        status: 'pending',
        reporter_id: 'user-1',
        created_at: twoHoursAgo,
        updated_at: twoHoursAgo,
      };

      const ticketResolvedOld: TicketItem = {
        id: 't-resolved',
        ticket_number: 'TCK-202610-0993',
        description: 'Lampu diganti',
        priority: 'rendah',
        status: 'selesai',
        reporter_id: 'user-1',
        created_at: thirtyHoursAgo,
        updated_at: thirtyHoursAgo,
      };

      const checkSla = (t: TicketItem) => {
        if (t.status === 'selesai') return false;
        const diffHours = (Date.now() - new Date(t.created_at).getTime()) / (1000 * 60 * 60);
        return diffHours >= 24;
      };

      expect(checkSla(ticketBreached)).toBe(true);
      expect(checkSla(ticketNormal)).toBe(false);
      expect(checkSla(ticketResolvedOld)).toBe(false); // Selesai tidak dihitung breach
    });

    it('memvalidasi pembagian tiket ke kolom Kanban (pending, diproses, selesai)', () => {
      const sampleTickets: TicketItem[] = [
        { id: '1', ticket_number: 'T1', status: 'pending', priority: 'sedang', description: 'a', reporter_id: 'u1', created_at: '', updated_at: '' },
        { id: '2', ticket_number: 'T2', status: 'diproses', priority: 'tinggi', description: 'b', reporter_id: 'u1', created_at: '', updated_at: '' },
        { id: '3', ticket_number: 'T3', status: 'selesai', priority: 'rendah', description: 'c', reporter_id: 'u1', created_at: '', updated_at: '' },
        { id: '4', ticket_number: 'T4', status: 'diproses', priority: 'darurat', description: 'd', reporter_id: 'u1', created_at: '', updated_at: '' },
      ];

      const pending = sampleTickets.filter((t) => t.status === 'pending');
      const inProgress = sampleTickets.filter((t) => t.status === 'diproses');
      const resolved = sampleTickets.filter((t) => t.status === 'selesai');

      expect(pending).toHaveLength(1);
      expect(inProgress).toHaveLength(2);
      expect(resolved).toHaveLength(1);
    });
  });
});
