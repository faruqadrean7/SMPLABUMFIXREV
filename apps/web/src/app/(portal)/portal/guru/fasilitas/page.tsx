'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import {
  useFacilities,
  useFacilityBookings,
  useCreateFacilityBooking,
  useSarprasTickets,
  useCreateSarprasTicket,
} from '@/features/portal/guru';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { EmptyState } from '@/components/shared/empty-state';
import {
  Building2,
  Calendar,
  Clock,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Plus,
  Send,
  ArrowLeft,
  Wrench,
  AlertCircle,
  Users,
} from 'lucide-react';

export default function TeacherFacilitiesPage() {
  const [activeTab, setActiveTab] = useState<'booking' | 'tiket'>('booking');

  // Booking Query & Mutation
  const { data: bookings = [], isLoading: isLoadingBookings, error: bookingsError } = useFacilityBookings();
  const { data: facilities = [], isLoading: isLoadingFacilities } = useFacilities();
  const createBookingMutation = useCreateFacilityBooking();

  // Booking Form State
  const [facilityId, setFacilityId] = useState('');
  const [startDate, setStartDate] = useState('');
  const [startTime, setStartTime] = useState('08:00');
  const [endTime, setEndTime] = useState('10:00');
  const [bookingPurpose, setBookingPurpose] = useState('');
  const [attendeesCount, setAttendeesCount] = useState<number>(30);
  const [bookingFeedback, setBookingFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // FIX-GURU-11: Otomatis pilih fasilitas pertama yang tersedia saat data fasilitas selesai dimuat
  useEffect(() => {
    if (facilities.length > 0 && !facilityId) {
      setFacilityId(facilities[0].id);
    }
  }, [facilities, facilityId]);

  // Ticket Query & Form State
  const { data: tickets = [], isLoading: isLoadingTickets, error: ticketsError } = useSarprasTickets();
  const createTicketMutation = useCreateSarprasTicket();
  const [ticketTitle, setTicketTitle] = useState('');
  const [ticketCategory, setTicketCategory] = useState('Laboratorium Komputer');
  const [ticketPriority, setTicketPriority] = useState<'rendah' | 'sedang' | 'tinggi' | 'darurat'>('sedang');
  const [ticketLocation, setTicketLocation] = useState('');
  const [ticketDescription, setTicketDescription] = useState('');
  const [ticketFeedback, setTicketFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const handleCreateBooking = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!facilityId) {
      setBookingFeedback({ type: 'error', message: 'Pilih fasilitas yang akan dipinjam terlebih dahulu.' });
      return;
    }
    if (!startDate || !startTime || !endTime) {
      setBookingFeedback({ type: 'error', message: 'Tanggal dan rentang waktu peminjaman wajib diisi.' });
      return;
    }
    if (!bookingPurpose.trim()) {
      setBookingFeedback({ type: 'error', message: 'Tujuan peminjaman fasilitas wajib diisi.' });
      return;
    }

    const startAt = `${startDate}T${startTime}:00`;
    const endAt = `${startDate}T${endTime}:00`;

    if (new Date(startAt) >= new Date(endAt)) {
      setBookingFeedback({ type: 'error', message: 'Waktu selesai harus lebih lambat dari waktu mulai.' });
      return;
    }

    setBookingFeedback(null);
    try {
      await createBookingMutation.mutateAsync({
        facility_id: facilityId,
        start_at: startAt,
        end_at: endAt,
        purpose: bookingPurpose.trim(),
        attendees_count: Number(attendeesCount) || 1,
      });

      setBookingFeedback({
        type: 'success',
        message: 'Pengajuan peminjaman fasilitas berhasil dikirimkan dan menunggu peninjauan tim Sarpras.',
      });
      setBookingPurpose('');
      setTimeout(() => setBookingFeedback(null), 6000);
    } catch (err: any) {
      const status = err.response?.status;
      const msg =
        status === 409
          ? 'Konflik Jadwal: Fasilitas sudah dipesan pada rentang waktu tersebut oleh guru/kegiatan lain.'
          : err.response?.data?.error?.message || err.message || 'Gagal mengajukan peminjaman fasilitas.';
      setBookingFeedback({ type: 'error', message: msg });
    }
  };

  const handleCreateTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!ticketDescription.trim()) {
      setTicketFeedback({ type: 'error', message: 'Deskripsi kendala kerusakan wajib diisi.' });
      return;
    }

    setTicketFeedback(null);
    try {
      const res = await createTicketMutation.mutateAsync({
        title: ticketTitle.trim() || `Laporan Kendala ${ticketCategory}`,
        category: ticketCategory,
        priority: ticketPriority,
        location: ticketLocation.trim() || undefined,
        description: ticketDescription.trim(),
      });

      setTicketFeedback({
        type: 'success',
        message: `Tiket sarpras berhasil diterbitkan dengan nomor: ${res.ticket_number || 'TKT-PENDING'}. Teknisi akan segera menindaklanjuti.`,
      });
      setTicketTitle('');
      setTicketLocation('');
      setTicketDescription('');
      setTimeout(() => setTicketFeedback(null), 6000);
    } catch (err: any) {
      const isTimeout =
        err.code === 'REQUEST_TIMEOUT' ||
        err.code === 'ECONNABORTED' ||
        err.message?.toLowerCase().includes('timeout') ||
        err.message?.toLowerCase().includes('batas waktu');

      const msg = isTimeout
        ? 'Batas waktu permintaan habis. Server membutuhkan waktu lebih lama untuk memproses tiket, silakan periksa daftar tiket Anda sebelum mengirim ulang.'
        : err.response?.data?.error?.message || err.message || 'Gagal membuat tiket pelaporan sarpras.';
      setTicketFeedback({ type: 'error', message: msg });
    }
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
            <Building2 className="h-6 w-6 text-brand-blue" />
            <span>Peminjaman Fasilitas & Tiket Sarpras</span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Pengajuan reservasi ruang laboratorium, aula sekolah, dan pelaporan kendala sarana prasarana KBM.
          </p>
        </div>
      </div>

      {/* Tab Switcher */}
      <div className="flex border-b border-slate-200 bg-white px-4 rounded-xl gap-2 shadow-2xs">
        <button
          onClick={() => setActiveTab('booking')}
          className={`py-3 px-4 font-bold text-xs sm:text-sm border-b-2 transition-all flex items-center gap-2 ${
            activeTab === 'booking'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-500 hover:text-slate-900'
          }`}
        >
          <Calendar className="w-4 h-4" />
          <span>Peminjaman Fasilitas</span>
        </button>

        <button
          onClick={() => setActiveTab('tiket')}
          className={`py-3 px-4 font-bold text-xs sm:text-sm border-b-2 transition-all flex items-center gap-2 ${
            activeTab === 'tiket'
              ? 'border-brand-blue text-brand-blue'
              : 'border-transparent text-slate-500 hover:text-slate-900'
          }`}
        >
          <Wrench className="w-4 h-4" />
          <span>Lapor Kendala Sarpras (Tiket)</span>
        </button>
      </div>

      {/* TAB 1: PEMINJAMAN FASILITAS */}
      {activeTab === 'booking' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Form Pengajuan */}
          <Card className="border-slate-200 shadow-2xs lg:col-span-1">
            <CardHeader>
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <Plus className="w-4 h-4 text-brand-blue" />
                <span>Form Reservasi Fasilitas</span>
              </CardTitle>
              <CardDescription className="text-xs">
                Pilih ruang dan rentang jam kegiatan KBM / evaluasi.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleCreateBooking} className="space-y-3.5 text-xs">
                {bookingFeedback && (
                  <div
                    className={`p-3 rounded-lg text-xs font-semibold flex items-center gap-2 ${
                      bookingFeedback.type === 'success'
                        ? 'bg-emerald-50 text-status-success border border-emerald-200'
                        : 'bg-rose-50 text-rose-800 border border-rose-200'
                    }`}
                  >
                    {bookingFeedback.type === 'success' ? (
                      <CheckCircle2 className="w-4 h-4 shrink-0" />
                    ) : (
                      <AlertCircle className="w-4 h-4 shrink-0" />
                    )}
                    <span>{bookingFeedback.message}</span>
                  </div>
                )}

                <div className="space-y-1">
                  <label htmlFor="booking-facility" className="font-bold text-slate-700">Ruangan / Fasilitas</label>
                  <select
                    id="booking-facility"
                    value={facilityId}
                    onChange={(e) => setFacilityId(e.target.value)}
                    disabled={isLoadingFacilities}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white text-slate-800 font-medium disabled:bg-slate-100"
                  >
                    <option value="">
                      {isLoadingFacilities ? 'Memuat daftar fasilitas...' : '-- Pilih Fasilitas Sekolah --'}
                    </option>
                    {facilities.map((f) => (
                      <option key={f.id} value={f.id}>
                        {f.name}
                      </option>
                    ))}
                  </select>
                  {!facilityId && !isLoadingFacilities && (
                    <p className="text-[11px] text-amber-600 font-medium">
                      * Silakan pilih fasilitas sekolah yang akan dipinjam.
                    </p>
                  )}
                </div>

                <div className="space-y-1">
                  <label htmlFor="booking-date" className="font-bold text-slate-700">Tanggal Kegiatan</label>
                  <input
                    id="booking-date"
                    type="date"
                    value={startDate}
                    onChange={(e) => setStartDate(e.target.value)}
                    required
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1">
                    <label htmlFor="booking-start-time" className="font-bold text-slate-700">Jam Mulai</label>
                    <input
                      id="booking-start-time"
                      type="time"
                      value={startTime}
                      onChange={(e) => setStartTime(e.target.value)}
                      required
                      className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                    />
                  </div>
                  <div className="space-y-1">
                    <label htmlFor="booking-end-time" className="font-bold text-slate-700">Jam Selesai</label>
                    <input
                      id="booking-end-time"
                      type="time"
                      value={endTime}
                      onChange={(e) => setEndTime(e.target.value)}
                      required
                      className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label htmlFor="booking-attendees" className="font-bold text-slate-700">Estimasi Peserta</label>
                  <input
                    id="booking-attendees"
                    type="number"
                    min={1}
                    max={200}
                    value={attendeesCount}
                    onChange={(e) => setAttendeesCount(Number(e.target.value))}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                    required
                  />
                </div>

                <div className="space-y-1">
                  <label htmlFor="booking-purpose" className="font-bold text-slate-700">Tujuan Peminjaman</label>
                  <textarea
                    id="booking-purpose"
                    rows={3}
                    placeholder="Contoh: Praktikum Pemrograman Web Kelas VII-A / Simulasi CBT..."
                    value={bookingPurpose}
                    onChange={(e) => setBookingPurpose(e.target.value)}
                    required
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs"
                  />
                </div>

                <Button
                  type="submit"
                  size="sm"
                  disabled={createBookingMutation.isPending || !facilityId || isLoadingFacilities}
                  isLoading={createBookingMutation.isPending}
                  className="w-full font-bold bg-brand-blue hover:bg-blue-800 text-white disabled:bg-slate-300 disabled:cursor-not-allowed"
                >
                  Ajukan Peminjaman
                </Button>
              </form>
            </CardContent>
          </Card>

          {/* Daftar Riwayat Peminjaman Guru */}
          <div className="lg:col-span-2 space-y-4">
            <h3 className="font-bold text-sm text-slate-900 flex items-center justify-between">
              <span>Riwayat Pengajuan Peminjaman Saya</span>
              <span className="text-xs text-slate-500 font-normal">{bookings.length} pengajuan tercatat</span>
            </h3>

            {isLoadingBookings ? (
              <div className="min-h-[250px] flex items-center justify-center bg-white rounded-xl border border-slate-200">
                <LoadingSpinner label="Memuat riwayat peminjaman fasilitas..." />
              </div>
            ) : bookingsError ? (
              <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-semibold flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 shrink-0" />
                <span>Gagal memuat daftar peminjaman fasilitas.</span>
              </div>
            ) : bookings.length === 0 ? (
              <EmptyState
                title="Belum Ada Pengajuan Fasilitas"
                description="Anda belum memiliki pengajuan peminjaman laboratorium atau fasilitas sekolah."
                className="bg-white border-slate-200 py-12"
              />
            ) : (
              <div className="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 shadow-2xs overflow-hidden">
                {bookings.map((b) => {
                  const facilityName = typeof b.facility === 'object' && b.facility !== null ? b.facility.name : (b.facility || b.facility_name || 'Fasilitas');
                  const startStr = b.start_at || b.start_time || '';
                  const endStr = b.end_at || b.end_time || '';
                  const dateStr = b.date || (startStr ? startStr.slice(0, 10) : '-');
                  const timeSlotStr = b.time_slot || (startStr && endStr ? `${startStr.slice(11, 16)} - ${endStr.slice(11, 16)}` : '-');
                  const count = b.attendees_count || b.participant_count || 1;

                  return (
                    <div key={b.id} className="p-4 hover:bg-slate-50/50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-slate-900 text-sm">{facilityName}</span>
                          <Badge
                            className={`text-[10px] font-bold px-2 py-0.5 ${
                              b.status === 'disetujui' || (b.status as string) === 'approved'
                                ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                : b.status === 'ditolak' || (b.status as string) === 'rejected'
                                ? 'bg-rose-100 text-rose-800 border-rose-200'
                                : 'bg-amber-100 text-amber-800 border-amber-200'
                            }`}
                          >
                            {b.status}
                          </Badge>
                        </div>

                        <div className="text-slate-600 flex items-center gap-3">
                          <span className="font-medium text-slate-700">{dateStr}</span>
                          <span>•</span>
                          <span>{timeSlotStr}</span>
                          <span>•</span>
                          <span>{count} orang</span>
                        </div>

                        <p className="text-slate-500 italic">{`"${b.purpose}"`}</p>

                        {b.decision_notes && (
                          <p className="text-[11px] text-slate-500 bg-slate-50 p-1.5 rounded border border-slate-100">
                            Catatan Sarpras: {b.decision_notes}
                          </p>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB 2: TIKET SARPRAS */}
      {activeTab === 'tiket' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <Card className="border-slate-200 shadow-2xs lg:col-span-1">
            <CardHeader>
              <CardTitle className="text-base font-bold flex items-center gap-2">
                <Wrench className="w-4 h-4 text-indigo-600" />
                <span>Tiket Pelaporan Gangguan Fasilitas KBM</span>
              </CardTitle>
              <CardDescription className="text-xs">
                Laporkan kerusakan komputer, proyektor, AC, atau fasilitas KBM lainnya.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleCreateTicket} className="space-y-3.5 text-xs">
                {ticketFeedback && (
                  <div
                    className={`p-3 rounded-lg text-xs font-semibold flex items-center gap-2 ${
                      ticketFeedback.type === 'success'
                        ? 'bg-emerald-50 text-status-success border border-emerald-200'
                        : 'bg-rose-50 text-rose-800 border border-rose-200'
                    }`}
                  >
                    {ticketFeedback.type === 'success' ? (
                      <CheckCircle2 className="w-4 h-4 shrink-0" />
                    ) : (
                      <AlertCircle className="w-4 h-4 shrink-0" />
                    )}
                    <span>{ticketFeedback.message}</span>
                  </div>
                )}

                <div className="space-y-1">
                  <label htmlFor="ticket-title" className="font-bold text-slate-700">Judul Laporan</label>
                  <input
                    id="ticket-title"
                    type="text"
                    placeholder="Contoh: Proyektor Kelas 7-A Tidak Menyala"
                    value={ticketTitle}
                    onChange={(e) => setTicketTitle(e.target.value)}
                    disabled={createTicketMutation.isPending}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs disabled:bg-slate-50"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1">
                    <label htmlFor="ticket-category" className="font-bold text-slate-700">Kategori</label>
                    <select
                      id="ticket-category"
                      value={ticketCategory}
                      onChange={(e) => setTicketCategory(e.target.value)}
                      disabled={createTicketMutation.isPending}
                      className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white disabled:bg-slate-50"
                    >
                      <option value="Laboratorium Komputer">Lab Komputer / PC</option>
                      <option value="Proyektor & Audio">Proyektor & Audio</option>
                      <option value="Jaringan Internet">Internet / Wi-Fi</option>
                      <option value="Listrik & Lampu">Listrik & Lampu</option>
                      <option value="Meja Kursi Kelas">Meja & Kursi</option>
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label htmlFor="ticket-priority" className="font-bold text-slate-700">Prioritas</label>
                    <select
                      id="ticket-priority"
                      value={ticketPriority}
                      onChange={(e) => setTicketPriority(e.target.value as any)}
                      disabled={createTicketMutation.isPending}
                      className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white disabled:bg-slate-50"
                    >
                      <option value="rendah">Rendah</option>
                      <option value="sedang">Sedang</option>
                      <option value="tinggi">Tinggi</option>
                      <option value="darurat">Darurat</option>
                    </select>
                  </div>
                </div>

                <div className="space-y-1">
                  <label htmlFor="ticket-location" className="font-bold text-slate-700">Lokasi / Ruangan</label>
                  <input
                    id="ticket-location"
                    type="text"
                    placeholder="Contoh: Lab Komputer 1 Meja No. 12"
                    value={ticketLocation}
                    onChange={(e) => setTicketLocation(e.target.value)}
                    disabled={createTicketMutation.isPending}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs disabled:bg-slate-50"
                  />
                </div>

                <div className="space-y-1">
                  <label htmlFor="ticket-desc" className="font-bold text-slate-700">Deskripsi Kendala</label>
                  <textarea
                    id="ticket-desc"
                    rows={4}
                    required
                    placeholder="Jelaskan kendala secara spesifik, kode error yang muncul jika ada..."
                    value={ticketDescription}
                    onChange={(e) => setTicketDescription(e.target.value)}
                    disabled={createTicketMutation.isPending}
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs disabled:bg-slate-50"
                  />
                </div>

                <Button
                  type="submit"
                  size="sm"
                  disabled={createTicketMutation.isPending || !ticketDescription.trim()}
                  isLoading={createTicketMutation.isPending}
                  className="w-full font-bold bg-indigo-600 hover:bg-indigo-700 text-white disabled:bg-slate-300 disabled:cursor-not-allowed"
                >
                  Kirim Tiket Pelaporan
                </Button>
              </form>
            </CardContent>
          </Card>

          {/* Daftar Riwayat Tiket Kendala Guru */}
          <div className="lg:col-span-2 space-y-4">
            <h3 className="font-bold text-sm text-slate-900 flex items-center justify-between">
              <span>Riwayat Laporan Kendala Sarpras Saya</span>
              <span className="text-xs text-slate-500 font-normal">{tickets.length} tiket tercatat</span>
            </h3>

            {isLoadingTickets ? (
              <div className="min-h-[250px] flex items-center justify-center bg-white rounded-xl border border-slate-200">
                <LoadingSpinner label="Memuat riwayat tiket sarpras..." />
              </div>
            ) : ticketsError ? (
              <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-semibold flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 shrink-0" />
                <span>Gagal memuat daftar tiket sarpras.</span>
              </div>
            ) : tickets.length === 0 ? (
              <EmptyState
                title="Belum Ada Laporan Kendala"
                description="Anda belum pernah membuat laporan tiket kerusakan sarpras atau kendala KBM."
                className="bg-white border-slate-200 py-12"
              />
            ) : (
              <div className="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 shadow-2xs overflow-hidden">
                {tickets.map((t) => (
                  <div key={t.id} className="p-4 hover:bg-slate-50/50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-mono font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                          {t.ticket_number}
                        </span>
                        <span className="font-bold text-slate-900 text-sm">{t.title}</span>
                        <Badge
                          className={`text-[10px] font-bold px-2 py-0.5 ${
                            t.status === 'selesai'
                              ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                              : t.status === 'diproses'
                              ? 'bg-blue-100 text-blue-800 border-blue-200'
                              : t.status === 'dibatalkan'
                              ? 'bg-rose-100 text-rose-800 border-rose-200'
                              : 'bg-amber-100 text-amber-800 border-amber-200'
                          }`}
                        >
                          {t.status}
                        </Badge>
                        <Badge variant="outline" className="text-[10px] uppercase font-semibold text-slate-600">
                          {t.priority}
                        </Badge>
                      </div>

                      <p className="text-slate-600 whitespace-pre-line line-clamp-2">{t.description}</p>

                      <div className="text-[11px] text-slate-400">
                        <span>Dilaporkan pada: {t.created_at ? new Date(t.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}</span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
