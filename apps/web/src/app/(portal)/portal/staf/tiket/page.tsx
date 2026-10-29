'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useStaffTickets,
  stafApi,
  StaffTicketItem,
} from '@/features/portal/staf';
import { TicketPriority, TicketStatus } from '@/features/sarpras/tickets-api';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  Wrench,
  ArrowLeft,
  Plus,
  Clock,
  CheckCircle2,
  AlertTriangle,
  AlertOctagon,
  MapPin,
  X,
  Send,
  Layers,
} from 'lucide-react';

export default function StaffTicketsPage() {
  const { data: initialTickets = [], isLoading } = useStaffTickets();
  const [tickets, setTickets] = useState<StaffTicketItem[]>([]);
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Form State
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('Laboratorium & Elektronik');
  const [location, setLocation] = useState('');
  const [description, setDescription] = useState('');
  const [priority, setPriority] = useState<TicketPriority>('sedang');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [modalError, setModalError] = useState<string | null>(null);

  React.useEffect(() => {
    if (Array.isArray(initialTickets) && initialTickets.length > 0) {
      setTickets(initialTickets);
    }
  }, [initialTickets]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !location.trim() || !description.trim()) return;

    setIsSubmitting(true);
    setModalError(null);
    try {
      const created = await stafApi.createTicket({
        title,
        category,
        location,
        description,
        priority,
      });

      setTickets((prev) => [created, ...prev]);
      setSuccessMessage('Tiket aduan sarpras berhasil diterbitkan dan segera ditindaklanjuti.');
      setIsModalOpen(false);
      setTitle('');
      setLocation('');
      setDescription('');
      setPriority('sedang');
      setModalError(null);
      setTimeout(() => setSuccessMessage(null), 4000);
    } catch (err: any) {
      setModalError(err?.message || 'Gagal menerbitkan tiket aduan sarpras. Silakan periksa kembali formulir.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const safeTickets = Array.isArray(tickets) ? tickets : [];
  const filteredTickets = safeTickets.filter((t) => {
    if (filterStatus === 'all') return true;
    return t.status === filterStatus;
  });

  const getPriorityBadge = (prio: TicketPriority) => {
    switch (prio) {
      case 'darurat':
        return (
          <Badge className="bg-red-50 text-status-danger border-red-200 gap-1 text-[11px] font-bold">
            <AlertOctagon className="h-3 w-3" />
            <span>Darurat</span>
          </Badge>
        );
      case 'tinggi':
        return (
          <Badge className="bg-amber-50 text-amber-800 border-amber-200 gap-1 text-[11px] font-bold">
            <AlertTriangle className="h-3 w-3" />
            <span>Tinggi</span>
          </Badge>
        );
      case 'sedang':
        return (
          <Badge className="bg-blue-50 text-brand-blue border-blue-200 text-[11px] font-semibold">
            Sedang
          </Badge>
        );
      case 'rendah':
      default:
        return (
          <Badge className="bg-slate-100 text-slate-600 border-slate-200 text-[11px]">
            Rendah
          </Badge>
        );
    }
  };

  const getStatusBadge = (status: TicketStatus) => {
    switch (status) {
      case 'selesai':
        return (
          <Badge className="bg-emerald-50 text-status-success border-emerald-200 gap-1 text-[11px] font-bold">
            <CheckCircle2 className="h-3 w-3" />
            <span>Selesai</span>
          </Badge>
        );
      case 'diproses':
        return (
          <Badge className="bg-purple-50 text-purple-700 border-purple-200 gap-1 text-[11px] font-bold">
            <Wrench className="h-3 w-3" />
            <span>Sedang Diproses</span>
          </Badge>
        );
      case 'pending':
      default:
        return (
          <Badge className="bg-amber-50 text-amber-800 border-amber-200 gap-1 text-[11px] font-bold">
            <Clock className="h-3 w-3" />
            <span>Menunggu Penanganan</span>
          </Badge>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Link
              href="/portal/staf"
              className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 font-medium transition-colors"
            >
              <ArrowLeft className="h-4 w-4" />
              <span>Kembali ke Beranda Staf</span>
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <Wrench className="h-6 w-6 text-brand-blue" />
            <span>Tiket Aduan Kerusakan Sarpras</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pelaporan kerusakan sarana, prasarana, kelistrikan, dan fasilitas laboratorium sekolah.
          </p>
        </div>

        <Button
          onClick={() => setIsModalOpen(true)}
          className="gap-2 bg-brand-blue hover:bg-blue-700 text-white font-bold"
        >
          <Plus className="h-4 w-4" />
          <span>Buat Tiket Aduan Baru</span>
        </Button>
      </div>

      {successMessage && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-status-success text-xs sm:text-sm font-bold flex items-center gap-2 animate-in fade-in duration-200">
          <CheckCircle2 className="h-5 w-5 shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      {/* Filter Tabs */}
      <div className="flex flex-wrap items-center gap-2">
        {[
          { label: 'Semua Tiket', value: 'all' },
          { label: 'Menunggu', value: 'pending' },
          { label: 'Sedang Diproses', value: 'diproses' },
          { label: 'Selesai', value: 'selesai' },
        ].map((tab) => (
          <button
            key={tab.value}
            type="button"
            onClick={() => setFilterStatus(tab.value)}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              filterStatus === tab.value
                ? 'bg-brand-blue text-white shadow-xs'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tickets List */}
      {isLoading ? (
        <div className="min-h-[250px] flex items-center justify-center bg-white rounded-2xl border border-slate-200">
          <LoadingSpinner label="Memuat tiket sarpras staf..." size="lg" />
        </div>
      ) : filteredTickets.length === 0 ? (
        <EmptyState
          title="Tidak Ada Tiket Sarpras"
          description={
            filterStatus === 'all'
              ? 'Anda belum pernah melaporkan kerusakan fasilitas atau sarana prasarana.'
              : `Tidak ada tiket dengan status "${filterStatus}".`
          }
          actionLabel="Buat Tiket Aduan Sekarang"
          onAction={() => setIsModalOpen(true)}
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filteredTickets.map((ticket) => (
            <Card key={ticket.id} className="border-slate-200 hover:shadow-xs transition-shadow flex flex-col justify-between">
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                      {ticket.code}
                    </span>
                    {getPriorityBadge(ticket.priority)}
                  </div>
                  {getStatusBadge(ticket.status)}
                </div>

                <CardTitle className="text-base font-bold text-slate-900 mt-2 leading-snug">
                  {ticket.title}
                </CardTitle>

                <div className="flex flex-col gap-1 mt-1 text-xs text-slate-500">
                  <div className="flex items-center gap-1.5">
                    <Layers className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                    <span>{ticket.category}</span>
                  </div>
                  <div className="flex items-center gap-1.5 text-slate-700 font-medium">
                    <MapPin className="h-3.5 w-3.5 text-brand-blue shrink-0" />
                    <span>{ticket.location}</span>
                  </div>
                </div>
              </CardHeader>

              <CardContent className="pt-0 space-y-3">
                <p className="text-xs text-slate-600 bg-slate-50/80 p-3 rounded-xl border border-slate-100 leading-relaxed">
                  {ticket.description}
                </p>

                {ticket.resolutionNotes && (
                  <div className="p-3 bg-emerald-50/70 border border-emerald-100 rounded-xl text-xs text-slate-700 space-y-0.5">
                    <span className="font-bold text-status-success block">Catatan Penanganan Sarpras:</span>
                    <p className="text-slate-600">{ticket.resolutionNotes}</p>
                  </div>
                )}

                <div className="text-[11px] text-slate-400 flex items-center justify-between pt-2 border-t border-slate-100">
                  <span>Dilaporkan: {ticket.reportedAt}</span>
                  {ticket.updatedAt && <span>Update: {ticket.updatedAt}</span>}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Modal Buat Tiket Aduan Baru */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <Card className="w-full max-w-lg bg-white shadow-xl animate-in zoom-in-95 duration-150">
            <CardHeader className="flex flex-row items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <CardTitle className="text-lg font-bold text-slate-900">
                  Formulir Laporan Kerusakan Sarpras
                </CardTitle>
                <CardDescription className="text-xs text-slate-500">
                  Tiket akan diteruskan langsung ke tim Sarana & Prasarana
                </CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setIsModalOpen(false)}
                className="h-8 w-8 p-0 text-slate-400 hover:text-slate-600"
              >
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>

            <form onSubmit={handleSubmit}>
              <CardContent className="space-y-4 pt-4">
                {modalError && (
                  <div className="p-3 bg-red-50 border border-red-200 text-status-danger text-xs rounded-lg flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    <span>{modalError}</span>
                  </div>
                )}
                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">
                    Judul Masalah / Kerusakan
                  </label>
                  <Input
                    type="text"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Contoh: AC Ruang Lab Komputer 2 Bocor"
                    required
                    className="text-xs"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs font-bold text-slate-700 block mb-1">
                      Kategori Fasilitas
                    </label>
                    <select
                      value={category}
                      onChange={(e) => setCategory(e.target.value)}
                      className="w-full text-xs font-medium rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-blue"
                    >
                      <option value="Laboratorium & Elektronik">Laboratorium & Elektronik</option>
                      <option value="Kelistrikan">Kelistrikan & Penerangan</option>
                      <option value="Peralatan Kelas">Peralatan Kelas (LCD/Papan)</option>
                      <option value="Fasilitas Sanitasi">Toilet & Sanitasi</option>
                      <option value="Sarana Gedung">Pintu, Jendela, & Gedung</option>
                    </select>
                  </div>

                  <div>
                    <label className="text-xs font-bold text-slate-700 block mb-1">
                      Tingkat Urgensi
                    </label>
                    <select
                      value={priority}
                      onChange={(e) => setPriority(e.target.value as TicketPriority)}
                      className="w-full text-xs font-medium rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-blue"
                    >
                      <option value="rendah">Rendah (Dapat ditunda)</option>
                      <option value="sedang">Sedang (Standar operasional)</option>
                      <option value="tinggi">Tinggi (Mengganggu KBM)</option>
                      <option value="darurat">Darurat (Berisiko keselamatan)</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">
                    Lokasi Spesifik (Gedung & Ruang)
                  </label>
                  <Input
                    type="text"
                    value={location}
                    onChange={(e) => setLocation(e.target.value)}
                    placeholder="Contoh: Gedung B Lantai 2 - Lab Komputer 2"
                    required
                    className="text-xs"
                  />
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">
                    Deskripsi Kerusakan / Gejala
                  </label>
                  <Textarea
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="Jelaskan detail kerusakan, unit yang terkena dampak, dan kondisi terkini..."
                    rows={3}
                    required
                    className="text-xs"
                  />
                </div>
              </CardContent>

              <div className="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 rounded-b-xl">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsModalOpen(false)}
                  disabled={isSubmitting}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={isSubmitting}
                  className="bg-brand-blue hover:bg-blue-700 text-white font-bold gap-1.5"
                >
                  <Send className="h-3.5 w-3.5" />
                  <span>{isSubmitting ? 'Menerbitkan...' : 'Terbitkan Tiket'}</span>
                </Button>
              </div>
            </form>
          </Card>
        </div>
      )}
    </div>
  );
}
