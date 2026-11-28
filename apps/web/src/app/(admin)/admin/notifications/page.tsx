'use client';

import React, { useCallback, useEffect, useState } from 'react';
import { Bell, Megaphone, Send, FileCode2, RefreshCw, AlertCircle, Eye, Trash2, RotateCcw, Users } from 'lucide-react';
import { NotificationInboxView } from '@/components/notifications/notification-inbox-view';
import { PermissionGate } from '@/components/shared/permission-gate';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { notificationsApi } from '@/features/notifications/notifications-api';

type AdminNotifTab = 'inbox' | 'broadcast' | 'announcements' | 'messaging';

interface AnnouncementRow {
  id: string;
  title: string;
  body: string;
  target_type: 'semua' | 'role' | 'kelas' | 'tingkat';
  priority?: string;
  publish_at?: string;
  author?: { username?: string };
}

interface ReaderItem {
  user_id: string;
  username?: string;
  email?: string;
  read_at?: string;
}

interface TemplateRow {
  id: string;
  code: string;
  name: string;
  channel: 'whatsapp' | 'email';
  subject?: string;
  template_text: string;
  is_active: boolean;
}

interface LogRow {
  id: string;
  channel: string;
  recipient: string;
  subject?: string;
  body: string;
  status: string;
  error?: string | null;
  retry_count?: number;
  created_at?: string;
}

export default function AdminNotificationPage() {
  const [activeTab, setActiveTab] = useState<AdminNotifTab>('inbox');

  // Broadcast state
  const [bcTargetType, setBcTargetType] = useState<'all' | 'role' | 'class'>('all');
  const [bcTargetRole, setBcTargetRole] = useState('student');
  const [bcTargetClassId, setBcTargetClassId] = useState('');
  const [bcTitle, setBcTitle] = useState('');
  const [bcBody, setBcBody] = useState('');
  const [bcTargetUrl, setBcTargetUrl] = useState('');
  const [bcSending, setBcSending] = useState(false);
  const [bcFeedback, setBcFeedback] = useState<{ type: 'ok' | 'err'; message: string } | null>(null);

  // Announcements state
  const [announcements, setAnnouncements] = useState<AnnouncementRow[]>([]);
  const [annLoading, setAnnLoading] = useState(false);
  const [annError, setAnnError] = useState<string | null>(null);
  const [annTitle, setAnnTitle] = useState('');
  const [annBody, setAnnBody] = useState('');
  const [annTargetType, setAnnTargetType] = useState<'semua' | 'role' | 'kelas' | 'tingkat'>('semua');
  const [annTargetIds, setAnnTargetIds] = useState('');
  const [annPriority, setAnnPriority] = useState<'normal' | 'high' | 'urgent'>('normal');
  const [annSubmitting, setAnnSubmitting] = useState(false);
  const [readersModal, setReadersModal] = useState<{ title: string; total: number; readers: ReaderItem[] } | null>(null);

  // Messaging Templates & Logs state
  const [templates, setTemplates] = useState<TemplateRow[]>([]);
  const [logs, setLogs] = useState<LogRow[]>([]);
  const [msgLoading, setMsgLoading] = useState(false);
  const [msgError, setMsgError] = useState<string | null>(null);
  const [tplCode, setTplCode] = useState('');
  const [tplName, setTplName] = useState('');
  const [tplChannel, setTplChannel] = useState<'whatsapp' | 'email'>('whatsapp');
  const [tplSubject, setTplSubject] = useState('');
  const [tplBody, setTplBody] = useState('');
  const [sendTemplateId, setSendTemplateId] = useState('');
  const [sendRecipient, setSendRecipient] = useState('');
  const [msgFeedback, setMsgFeedback] = useState<string | null>(null);

  const loadAnnouncements = useCallback(async () => {
    setAnnLoading(true);
    setAnnError(null);
    try {
      const res = await notificationsApi.getAnnouncements({ published_only: false, per_page: 25 });
      setAnnouncements(Array.isArray(res?.data) ? res.data : []);
    } catch (err: unknown) {
      setAnnError(err instanceof Error ? err.message : 'Gagal memuat daftar pengumuman.');
    } finally {
      setAnnLoading(false);
    }
  }, []);

  const loadMessaging = useCallback(async () => {
    setMsgLoading(true);
    setMsgError(null);
    try {
      const [tplRes, logRes] = await Promise.all([
        notificationsApi.getTemplates({ per_page: 25 }),
        notificationsApi.getLogs({ per_page: 25 }),
      ]);
      const tplList = Array.isArray(tplRes?.data) ? tplRes.data : [];
      setTemplates(tplList);
      if (!sendTemplateId && tplList.length > 0) {
        setSendTemplateId(tplList[0].id);
      }
      setLogs(Array.isArray(logRes?.data) ? logRes.data : []);
    } catch (err: unknown) {
      setMsgError(err instanceof Error ? err.message : 'Gagal memuat data template dan log pengiriman.');
    } finally {
      setMsgLoading(false);
    }
  }, [sendTemplateId]);

  useEffect(() => {
    if (activeTab === 'announcements') {
      loadAnnouncements();
    } else if (activeTab === 'messaging') {
      loadMessaging();
    }
  }, [activeTab, loadAnnouncements, loadMessaging]);

  const handleBroadcastSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBcSending(true);
    setBcFeedback(null);
    try {
      const res = await notificationsApi.broadcast({
        target_type: bcTargetType,
        target_role: bcTargetType === 'role' ? bcTargetRole : undefined,
        target_class_id: bcTargetType === 'class' ? bcTargetClassId : undefined,
        title: bcTitle.trim(),
        body: bcBody.trim(),
        notif_type: 'academic_announcement',
        data: bcTargetUrl.trim() ? { target_url: bcTargetUrl.trim() } : undefined,
      });
      const count = (res.data as any)?.sent_count ?? res.data?.dispatched_count ?? 0;
      setBcFeedback({ type: 'ok', message: `Broadcast terkirim ke ${count} pengguna aktif.` });
      setBcTitle('');
      setBcBody('');
      setBcTargetUrl('');
    } catch (err: unknown) {
      setBcFeedback({ type: 'err', message: err instanceof Error ? err.message : 'Gagal mengirim broadcast.' });
    } finally {
      setBcSending(false);
    }
  };

  const handleCreateAnnouncement = async (e: React.FormEvent) => {
    e.preventDefault();
    setAnnSubmitting(true);
    setAnnError(null);
    try {
      const ids = annTargetIds
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
      await notificationsApi.createAnnouncement({
        title: annTitle.trim(),
        body: annBody.trim(),
        target_type: annTargetType,
        target_ids: annTargetType === 'semua' ? undefined : ids,
        priority: annPriority,
      });
      setAnnTitle('');
      setAnnBody('');
      setAnnTargetIds('');
      await loadAnnouncements();
    } catch (err: unknown) {
      setAnnError(err instanceof Error ? err.message : 'Gagal menerbitkan pengumuman.');
    } finally {
      setAnnSubmitting(false);
    }
  };

  const handleOpenReaders = async (id: string) => {
    try {
      const res = await notificationsApi.getAnnouncementReaders(id);
      if (res.data) {
        setReadersModal({
          title: res.data.title,
          total: res.data.total_readers,
          readers: res.data.readers || [],
        });
      }
    } catch (err: unknown) {
      setAnnError(err instanceof Error ? err.message : 'Gagal memuat daftar pembaca pengumuman.');
    }
  };

  const handleDeleteAnnouncement = async (id: string) => {
    try {
      await notificationsApi.deleteAnnouncement(id);
      setAnnouncements((prev) => prev.filter((item) => item.id !== id));
    } catch (err: unknown) {
      setAnnError(err instanceof Error ? err.message : 'Gagal menghapus pengumuman.');
    }
  };

  const handleCreateTemplate = async (e: React.FormEvent) => {
    e.preventDefault();
    setMsgFeedback(null);
    setMsgError(null);
    try {
      await notificationsApi.createTemplate({
        code: tplCode.trim(),
        name: tplName.trim(),
        channel: tplChannel,
        subject: tplChannel === 'email' ? tplSubject.trim() : undefined,
        template_text: tplBody.trim(),
      });
      setTplCode('');
      setTplName('');
      setTplSubject('');
      setTplBody('');
      setMsgFeedback('Template pesan baru berhasil disimpan.');
      await loadMessaging();
    } catch (err: unknown) {
      setMsgError(err instanceof Error ? err.message : 'Gagal membuat template pesan.');
    }
  };

  const handleSendTemplate = async (e: React.FormEvent) => {
    e.preventDefault();
    setMsgFeedback(null);
    setMsgError(null);
    try {
      const res = await notificationsApi.sendTemplate({
        template_id: sendTemplateId,
        recipient: sendRecipient.trim(),
      });
      setMsgFeedback(`Status pengiriman ke ${sendRecipient}: ${res.data?.status ?? 'diproses'}.`);
      setSendRecipient('');
      await loadMessaging();
    } catch (err: unknown) {
      setMsgError(err instanceof Error ? err.message : 'Gagal mengirim pesan template.');
    }
  };

  const handleRetryLog = async (id: string) => {
    try {
      await notificationsApi.retryLog(id);
      await loadMessaging();
    } catch (err: unknown) {
      setMsgError(err instanceof Error ? err.message : 'Gagal mencoba ulang pengiriman.');
    }
  };

  return (
    <div className="space-y-6">
      {/* Tab Navigation Bar */}
      <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3">
        <Button
          type="button"
          variant={activeTab === 'inbox' ? 'default' : 'outline'}
          size="sm"
          onClick={() => setActiveTab('inbox')}
          className="gap-2"
        >
          <Bell className="h-4 w-4" />
          <span>Kotak Masuk</span>
        </Button>

        <PermissionGate permission="notification.broadcast">
          <Button
            type="button"
            variant={activeTab === 'broadcast' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setActiveTab('broadcast')}
            className="gap-2"
          >
            <Send className="h-4 w-4" />
            <span>Broadcast</span>
          </Button>
          <Button
            type="button"
            variant={activeTab === 'announcements' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setActiveTab('announcements')}
            className="gap-2"
          >
            <Megaphone className="h-4 w-4" />
            <span>Pengumuman &amp; Pembaca</span>
          </Button>
          <Button
            type="button"
            variant={activeTab === 'messaging' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setActiveTab('messaging')}
            className="gap-2"
          >
            <FileCode2 className="h-4 w-4" />
            <span>Template &amp; Log WA/Email</span>
          </Button>
        </PermissionGate>
      </div>

      {activeTab === 'inbox' && <NotificationInboxView portalName="Admin & Staf" />}

      {activeTab === 'broadcast' && (
        <PermissionGate
          permission="notification.broadcast"
          fallback={<p className="text-sm text-slate-500">Anda tidak memiliki izin mengirim broadcast.</p>}
        >
          <Card className="rounded-sm">
            <CardContent className="p-6 space-y-4">
              <div>
                <h2 className="text-lg font-bold text-slate-900">Kirim Broadcast Notifikasi In-App</h2>
                <p className="text-xs text-slate-500">Siarkan pemberitahuan tersegmentasi ke peran, kelas, atau seluruh pengguna aktif.</p>
              </div>

              {bcFeedback && (
                <div
                  className={`rounded-sm border px-3 py-2 text-xs ${
                    bcFeedback.type === 'ok'
                      ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                      : 'border-red-200 bg-red-50 text-red-800'
                  }`}
                >
                  {bcFeedback.message}
                </div>
              )}

              <form onSubmit={handleBroadcastSubmit} className="space-y-4 max-w-2xl">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-medium text-slate-700 mb-1">Target Audiens</label>
                    <select
                      value={bcTargetType}
                      onChange={(e) => setBcTargetType(e.target.value as 'all' | 'role' | 'class')}
                      className="w-full h-9 px-3 text-xs border border-slate-200 rounded-sm bg-white"
                    >
                      <option value="all">Seluruh Warga Sekolah Aktif</option>
                      <option value="role">Berdasarkan Peran (Role)</option>
                      <option value="class">Berdasarkan ID Kelas</option>
                    </select>
                  </div>
                  {bcTargetType === 'role' && (
                    <div>
                      <label className="block text-xs font-medium text-slate-700 mb-1">Peran Tujuan</label>
                      <select
                        value={bcTargetRole}
                        onChange={(e) => setBcTargetRole(e.target.value)}
                        className="w-full h-9 px-3 text-xs border border-slate-200 rounded-sm bg-white"
                      >
                        <option value="student">Siswa</option>
                        <option value="guardian">Wali Murid</option>
                        <option value="teacher">Guru</option>
                        <option value="admin_tu">Admin TU</option>
                      </select>
                    </div>
                  )}
                  {bcTargetType === 'class' && (
                    <div>
                      <label className="block text-xs font-medium text-slate-700 mb-1">ID Kelas</label>
                      <Input
                        required
                        value={bcTargetClassId}
                        onChange={(e) => setBcTargetClassId(e.target.value)}
                        placeholder="UUID kelas target..."
                        className="h-9 text-xs"
                      />
                    </div>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Judul Broadcast *</label>
                  <Input
                    required
                    value={bcTitle}
                    onChange={(e) => setBcTitle(e.target.value)}
                    placeholder="Contoh: Jadwal Penilaian Akhir Semester"
                    className="h-9 text-xs"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Isi Pesan *</label>
                  <Textarea
                    required
                    rows={4}
                    value={bcBody}
                    onChange={(e) => setBcBody(e.target.value)}
                    placeholder="Tuliskan informasi resmi..."
                    className="text-xs"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">Tautan Tujuan Internal (opsional)</label>
                  <Input
                    value={bcTargetUrl}
                    onChange={(e) => setBcTargetUrl(e.target.value)}
                    placeholder="/portal/siswa/jadwal"
                    className="h-9 text-xs"
                  />
                </div>

                <Button type="submit" disabled={bcSending} className="gap-2">
                  <Send className="h-4 w-4" />
                  <span>{bcSending ? 'Mengirim Broadcast...' : 'Kirim Broadcast'}</span>
                </Button>
              </form>
            </CardContent>
          </Card>
        </PermissionGate>
      )}

      {activeTab === 'announcements' && (
        <PermissionGate
          permission="notification.broadcast"
          fallback={<p className="text-sm text-slate-500">Anda tidak memiliki izin mengelola pengumuman.</p>}
        >
          <div className="space-y-6">
            {annError && (
              <div className="rounded-sm border border-red-200 bg-red-50 p-3 text-xs text-red-700 flex items-center gap-2">
                <AlertCircle className="h-4 w-4 shrink-0" />
                <span>{annError}</span>
              </div>
            )}

            <Card className="rounded-sm">
              <CardContent className="p-5 space-y-4">
                <h2 className="text-base font-bold text-slate-900">Buat Pengumuman Terarah</h2>
                <form onSubmit={handleCreateAnnouncement} className="space-y-3 max-w-2xl">
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-xs font-medium text-slate-700 mb-1">Target</label>
                      <select
                        value={annTargetType}
                        onChange={(e) => setAnnTargetType(e.target.value as any)}
                        className="w-full h-9 px-3 text-xs border border-slate-200 rounded-sm bg-white"
                      >
                        <option value="semua">Semua</option>
                        <option value="role">Peran</option>
                        <option value="kelas">Kelas</option>
                        <option value="tingkat">Tingkat</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-slate-700 mb-1">Prioritas</label>
                      <select
                        value={annPriority}
                        onChange={(e) => setAnnPriority(e.target.value as any)}
                        className="w-full h-9 px-3 text-xs border border-slate-200 rounded-sm bg-white"
                      >
                        <option value="normal">Normal</option>
                        <option value="high">Tinggi</option>
                        <option value="urgent">Mendesak</option>
                      </select>
                    </div>
                    {annTargetType !== 'semua' && (
                      <div>
                        <label className="block text-xs font-medium text-slate-700 mb-1">ID / Kode Target (koma)</label>
                        <Input
                          required
                          value={annTargetIds}
                          onChange={(e) => setAnnTargetIds(e.target.value)}
                          placeholder="student, guardian atau UUID"
                          className="h-9 text-xs"
                        />
                      </div>
                    )}
                  </div>
                  <Input
                    required
                    value={annTitle}
                    onChange={(e) => setAnnTitle(e.target.value)}
                    placeholder="Judul pengumuman..."
                    className="h-9 text-xs"
                  />
                  <Textarea
                    required
                    rows={3}
                    value={annBody}
                    onChange={(e) => setAnnBody(e.target.value)}
                    placeholder="Isi pengumuman resmi..."
                    className="text-xs"
                  />
                  <Button type="submit" size="sm" disabled={annSubmitting}>
                    {annSubmitting ? 'Menyimpan...' : 'Terbitkan Pengumuman'}
                  </Button>
                </form>
              </CardContent>
            </Card>

            <Card className="rounded-sm">
              <CardContent className="p-5 space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-bold text-slate-900">Daftar Pengumuman</h3>
                  <Button variant="outline" size="sm" onClick={loadAnnouncements} disabled={annLoading} className="gap-1.5">
                    <RefreshCw className={`h-3.5 w-3.5 ${annLoading ? 'animate-spin' : ''}`} />
                    <span>Muat Ulang</span>
                  </Button>
                </div>

                {annLoading ? (
                  <p className="text-xs text-slate-500">Memuat pengumuman...</p>
                ) : announcements.length === 0 ? (
                  <p className="text-xs text-slate-500">Belum ada pengumuman yang dibuat.</p>
                ) : (
                  <div className="space-y-2">
                    {announcements.map((item) => (
                      <div key={item.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 border border-slate-200 rounded-sm">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-bold text-slate-900">{item.title}</span>
                            <Badge variant="outline" className="text-[10px]">{item.target_type}</Badge>
                          </div>
                          <p className="text-xs text-slate-600 line-clamp-2">{item.body}</p>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                          <Button type="button" variant="outline" size="sm" onClick={() => handleOpenReaders(item.id)} className="h-8 gap-1 text-xs">
                            <Eye className="h-3.5 w-3.5" />
                            <span>Pembaca</span>
                          </Button>
                          <Button type="button" variant="outline" size="sm" onClick={() => handleDeleteAnnouncement(item.id)} className="h-8 p-2 text-red-600">
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {readersModal && (
                  <div className="mt-4 p-4 rounded-sm border border-slate-200 bg-slate-50 space-y-2">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2 text-xs font-bold text-slate-900">
                        <Users className="h-4 w-4 text-primary" />
                        <span>Pembaca &ldquo;{readersModal.title}&rdquo;: {readersModal.total} pengguna</span>
                      </div>
                      <Button type="button" variant="outline" size="sm" onClick={() => setReadersModal(null)}>
                        Tutup
                      </Button>
                    </div>
                    {readersModal.readers.length === 0 ? (
                      <p className="text-xs text-slate-500">Belum ada yang membaca pengumuman ini.</p>
                    ) : (
                      <ul className="space-y-1 text-xs text-slate-700">
                        {readersModal.readers.map((r) => (
                          <li key={r.user_id}>{r.username || r.email || r.user_id} — {r.read_at || '-'}</li>
                        ))}
                      </ul>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </PermissionGate>
      )}

      {activeTab === 'messaging' && (
        <PermissionGate
          permission="notification.broadcast"
          fallback={<p className="text-sm text-slate-500">Anda tidak memiliki izin mengelola template &amp; log WA/Email.</p>}
        >
          <div className="space-y-6">
            {msgError && (
              <div className="rounded-sm border border-red-200 bg-red-50 p-3 text-xs text-red-700">{msgError}</div>
            )}
            {msgFeedback && (
              <div className="rounded-sm border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">{msgFeedback}</div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Card className="rounded-sm">
                <CardContent className="p-5 space-y-3">
                  <h2 className="text-base font-bold text-slate-900">Buat Template WA / Email</h2>
                  <form onSubmit={handleCreateTemplate} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                      <Input required value={tplCode} onChange={(e) => setTplCode(e.target.value)} placeholder="Kode (WA_INFO)" className="h-9 text-xs" />
                      <select
                        value={tplChannel}
                        onChange={(e) => setTplChannel(e.target.value as 'whatsapp' | 'email')}
                        className="h-9 px-3 text-xs border border-slate-200 rounded-sm bg-white"
                      >
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                      </select>
                    </div>
                    <Input required value={tplName} onChange={(e) => setTplName(e.target.value)} placeholder="Nama template" className="h-9 text-xs" />
                    {tplChannel === 'email' && (
                      <Input value={tplSubject} onChange={(e) => setTplSubject(e.target.value)} placeholder="Subjek email" className="h-9 text-xs" />
                    )}
                    <Textarea required rows={3} value={tplBody} onChange={(e) => setTplBody(e.target.value)} placeholder="Teks template ({name}, ...)" className="text-xs" />
                    <Button type="submit" size="sm">Simpan Template</Button>
                  </form>
                </CardContent>
              </Card>

              <Card className="rounded-sm">
                <CardContent className="p-5 space-y-3">
                  <h2 className="text-base font-bold text-slate-900">Kirim Pesan Template</h2>
                  <form onSubmit={handleSendTemplate} className="space-y-3">
                    <select
                      value={sendTemplateId}
                      onChange={(e) => setSendTemplateId(e.target.value)}
                      className="w-full h-9 px-3 text-xs border border-slate-200 rounded-sm bg-white"
                    >
                      <option value="">-- Pilih Template --</option>
                      {templates.map((t) => (
                        <option key={t.id} value={t.id}>
                          [{t.channel.toUpperCase()}] {t.code} - {t.name}
                        </option>
                      ))}
                    </select>
                    <Input
                      required
                      value={sendRecipient}
                      onChange={(e) => setSendRecipient(e.target.value)}
                      placeholder="Nomor WA (08...) atau Email penerima"
                      className="h-9 text-xs"
                    />
                    <Button type="submit" size="sm" disabled={!sendTemplateId}>
                      Kirim Pesan
                    </Button>
                  </form>
                </CardContent>
              </Card>
            </div>

            <Card className="rounded-sm">
              <CardContent className="p-5 space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-bold text-slate-900">Riwayat &amp; Log Pengiriman WA/Email</h3>
                  <Button variant="outline" size="sm" onClick={loadMessaging} disabled={msgLoading} className="gap-1.5">
                    <RefreshCw className={`h-3.5 w-3.5 ${msgLoading ? 'animate-spin' : ''}`} />
                    <span>Segarkan Log</span>
                  </Button>
                </div>

                {msgLoading ? (
                  <p className="text-xs text-slate-500">Memuat riwayat log pengiriman...</p>
                ) : logs.length === 0 ? (
                  <p className="text-xs text-slate-500">Belum ada riwayat pengiriman pesan.</p>
                ) : (
                  <div className="space-y-2">
                    {logs.map((log) => (
                      <div key={log.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 border border-slate-200 rounded-sm text-xs">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <Badge variant="outline" className="uppercase text-[10px]">{log.channel}</Badge>
                            <span className="font-semibold text-slate-900">{log.recipient}</span>
                            <Badge variant={log.status === 'sent' ? 'success' : 'danger'} className="text-[10px]">
                              {log.status}
                            </Badge>
                          </div>
                          <p className="text-slate-600 line-clamp-1">{log.body}</p>
                          {log.error && <p className="text-red-600 text-[11px]">{log.error}</p>}
                        </div>
                        {log.status !== 'sent' && (
                          <Button type="button" variant="outline" size="sm" onClick={() => handleRetryLog(log.id)} className="h-8 gap-1 text-xs shrink-0">
                            <RotateCcw className="h-3.5 w-3.5" />
                            <span>Coba Lagi</span>
                          </Button>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </PermissionGate>
      )}
    </div>
  );
}
