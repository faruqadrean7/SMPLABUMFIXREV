'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  useFacilityBookings,
  useCreateFacilityBooking,
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
  const createBookingMutation = useCreateFacilityBooking();

  // Booking Form State
  const [facilityId, setFacilityId] = useState('');
  const [startDate, setStartDate] = useState('');
  const [startTime, setStartTime] = useState('08:00');
  const [endTime, setEndTime] = useState('10:00');
  const [bookingPurpose, setBookingPurpose] = useState('');
  const [attendeesCount, setAttendeesCount] = useState<number>(30);
  const [bookingFeedback, setBookingFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Ticket Form State
  const createTicketMutation = useCreateSarprasTicket();
  const [ticketTitle, setTicketTitle] = useState('');
  const [ticketCategory, setTicketCategory] = useState('Laboratorium Komputer');
  const [ticketPriority, setTicketPriority] = useState<'rendah' | 'sedang' | 'tinggi' | 'darurat'>('sedang');
  const [ticketLocation, setTicketLocation] = useState('');
  const [ticketDescription, setTicketDescription] = useState('');
  const [ticketFeedback, setTicketFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const handleCreateBooking = async (e: React.FormEvent) => {
    e.preventDefault();
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
        facility_id: facilityId || '00000000-0000-0000-0000-000000000001',
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
      const msg = err.response?.data?.error?.message || err.message || 'Gagal membuat tiket pelaporan sarpras.';
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
                    className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white text-slate-800 font-medium"
                  >
                    <option value="">-- Pilih Fasilitas Sekolah --</option>
                    <option value="00000000-0000-0000-0000-000000000001">Lab Komputer 1 (Lantai 2)</option>
                    <option value="00000000-0000-0000-0000-000000000002">Lab Multimedia (Lantai 3)</option>
                    <option value="00000000-0000-0000-0000-000000000003">Aula Graha Saraswati</option>
                    <option value="00000000-0000-0000-0000-000000000004">Laboratorium Sains / IPA</option>
                  </select>
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
                  isLoading={createBookingMutation.isPending}
                  className="w-full font-bold bg-brand-blue hover:bg-blue-800 text-white"
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

      {/* TAB 2: LIKET SARPRAS */}
      {activeTab === 'tiket' && (
        <div className="max-w-2xl bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs space-y-4">
          <div>
            <h3 className="font-bold text-base text-slate-900 flex items-center gap-2">
              <Wrench className="w-5 h-5 text-indigo-600" />
              <span>Tiket Pelaporan Gangguan Fasilitas KBM</span>
            </h3>
            <p className="text-xs text-slate-500 mt-1">
              Laporkan kendala komputer, proyektor, AC, atau jaringan internet kelas langsung ke helpdesk teknisi.
            </p>
          </div>

          <form onSubmit={handleCreateTicket} className="space-y-4 text-xs">
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
              <label className="font-bold text-slate-700">Judul Laporan</label>
              <input
                type="text"
                placeholder="Contoh: Proyektor Kelas 7-A Tidak Menyala"
                value={ticketTitle}
                onChange={(e) => setTicketTitle(e.target.value)}
                className="w-full p-2 border border-slate-200 rounded-lg text-xs"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="font-bold text-slate-700">Kategori Perangkat</label>
                <select
                  value={ticketCategory}
                  onChange={(e) => setTicketCategory(e.target.value)}
                  className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white"
                >
                  <option value="Laboratorium Komputer">Laboratorium Komputer / PC</option>
                  <option value="Proyektor & Audio">Proyektor & Audio Ruang Kelas</option>
                  <option value="Jaringan Internet">Jaringan Internet / Wi-Fi</option>
                  <option value="Listrik & Lampu">Listrik & Kelistrikan</option>
                  <option value="Meja Kursi Kelas">Meja & Kursi Siswa</option>
                </select>
              </div>

              <div className="space-y-1">
                <label className="font-bold text-slate-700">Tingkat Prioritas</label>
                <select
                  value={ticketPriority}
                  onChange={(e) => setTicketPriority(e.target.value as any)}
                  className="w-full p-2 border border-slate-200 rounded-lg text-xs bg-white"
                >
                  <option value="rendah">Rendah (Dapat menunggu)</option>
                  <option value="sedang">Sedang (Standar)</option>
                  <option value="tinggi">Tinggi (Mengganggu KBM)</option>
                  <option value="darurat">Darurat (Ujian sedang berjalan)</option>
                </select>
              </div>
            </div>

            <div className="space-y-1">
              <label className="font-bold text-slate-700">Lokasi / Ruangan</label>
              <input
                type="text"
                placeholder="Contoh: Lab Komputer 1 Meja No. 12 atau Ruang Kelas 7-A"
                value={ticketLocation}
                onChange={(e) => setTicketLocation(e.target.value)}
                className="w-full p-2 border border-slate-200 rounded-lg text-xs"
              />
            </div>

            <div className="space-y-1">
              <label className="font-bold text-slate-700">Deskripsi Kendala</label>
              <textarea
                rows={4}
                required
                placeholder="Jelaskan kendala secara spesifik, kode error yang muncul jika ada..."
                value={ticketDescription}
                onChange={(e) => setTicketDescription(e.target.value)}
                className="w-full p-2 border border-slate-200 rounded-lg text-xs"
              />
            </div>

            <Button
              type="submit"
              size="sm"
              isLoading={createTicketMutation.isPending}
              className="w-full font-bold bg-indigo-600 hover:bg-indigo-700 text-white"
            >
              Kirim Tiket Pelaporan
            </Button>
          </form>
        </div>
      )}
    </div>
  );
}
