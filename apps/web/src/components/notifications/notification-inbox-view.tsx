'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import {
  Bell,
  Check,
  CheckCheck,
  Trash2,
  RefreshCw,
  Search,
  Filter,
  AlertCircle,
  Inbox,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
} from 'lucide-react';
import { notificationsApi } from '@/features/notifications/notifications-api';
import { NotificationItem, NotificationMeta } from '@/features/notifications/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { getSafeDeepLink } from './notification-bell';

interface NotificationInboxViewProps {
  portalName: string;
}

export function NotificationInboxView({ portalName }: NotificationInboxViewProps) {
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [meta, setMeta] = useState<NotificationMeta>({
    page: 1,
    limit: 10,
    total: 0,
    totalPages: 1,
    unread_count: 0,
  });

  const [statusFilter, setStatusFilter] = useState<'all' | 'unread' | 'read'>('all');
  const [searchKeyword, setSearchKeyword] = useState('');
  const [currentPage, setCurrentPage] = useState(1);

  const fetchInbox = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const isReadParam =
        statusFilter === 'unread' ? false : statusFilter === 'read' ? true : undefined;

      const res = await notificationsApi.getNotifications({
        is_read: isReadParam,
        page: currentPage,
        per_page: 10,
      });

      setNotifications(res.data || []);
      setMeta(
        res.meta || {
          page: currentPage,
          limit: 10,
          total: res.data?.length || 0,
          totalPages: 1,
          unread_count: 0,
        }
      );
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat daftar notifikasi.';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, [statusFilter, currentPage]);

  useEffect(() => {
    fetchInbox();
  }, [fetchInbox]);

  const handleMarkAsRead = async (id: string) => {
    try {
      await notificationsApi.markAsRead(id);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      );
      setMeta((prev) => ({ ...prev, unread_count: Math.max(0, prev.unread_count - 1) }));
    } catch (err) {
      // silent
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      await notificationsApi.markAllAsRead();
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
      );
      setMeta((prev) => ({ ...prev, unread_count: 0 }));
    } catch (err) {
      // silent
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await notificationsApi.deleteNotification(id);
      setNotifications((prev) => prev.filter((n) => n.id !== id));
      setMeta((prev) => ({ ...prev, total: Math.max(0, prev.total - 1) }));
    } catch (err) {
      // silent
    }
  };

  const handleItemClick = async (notif: NotificationItem) => {
    if (!notif.read_at) {
      await handleMarkAsRead(notif.id);
    }
    const rawTarget =
      (notif.data && typeof notif.data === 'object' && ('target_url' in notif.data ? notif.data.target_url : notif.data.url)) ||
      null;

    const safeUrl = getSafeDeepLink(rawTarget);
    if (safeUrl) {
      router.push(safeUrl);
    }
  };

  // Filter in memory for quick text search
  const filteredNotifications = notifications.filter((item) => {
    if (!searchKeyword.trim()) return true;
    const q = searchKeyword.toLowerCase();
    return item.title.toLowerCase().includes(q) || item.body.toLowerCase().includes(q);
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
            <Bell className="h-6 w-6 text-primary" />
            Kotak Masuk Notifikasi ({portalName})
          </h1>
          <p className="text-sm text-slate-600 mt-1">
            Riwayat pemberitahuan akademik, pengumuman sekolah, jadwal ujian, dan evaluasi.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {meta.unread_count > 0 && (
            <Button
              variant="outline"
              size="sm"
              onClick={handleMarkAllAsRead}
              className="gap-2 text-primary border-primary/20 hover:bg-primary/5"
            >
              <CheckCheck className="h-4 w-4" />
              Tandai Semua Dibaca
            </Button>
          )}
          <Button variant="outline" size="sm" onClick={fetchInbox} disabled={isLoading} className="gap-2">
            <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            Segarkan
          </Button>
        </div>
      </div>

      {/* Filter Bar */}
      <Card className="rounded-sm">
        <CardContent className="p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div className="relative w-full sm:w-64">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
              <Input
                placeholder="Cari pesan notifikasi..."
                value={searchKeyword}
                onChange={(e) => setSearchKeyword(e.target.value)}
                className="pl-9 h-9 text-xs"
              />
            </div>
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value as any);
                setCurrentPage(1);
              }}
              className="h-9 px-3 text-xs rounded-sm border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-1 focus:ring-primary"
            >
              <option value="all">Semua Status</option>
              <option value="unread">Belum Dibaca Saja</option>
              <option value="read">Sudah Dibaca</option>
            </select>
          </div>
          <div className="text-xs text-slate-500 font-medium">
            Total {meta.total} pesan ({meta.unread_count} belum dibaca)
          </div>
        </CardContent>
      </Card>

      {/* Error state */}
      {error && (
        <div className="rounded-sm border border-red-200 bg-red-50 p-4 text-sm text-red-800 flex items-center justify-between gap-2 shadow-sm">
          <div className="flex items-center gap-2">
            <AlertCircle className="h-5 w-5 text-red-600 shrink-0" />
            <span>{error}</span>
          </div>
          <Button variant="outline" size="sm" onClick={fetchInbox} className="bg-white border-red-200 text-red-700 hover:bg-red-50">
            Coba Lagi
          </Button>
        </div>
      )}

      {/* Main Content */}
      {isLoading ? (
        <Card className="rounded-sm">
          <CardContent className="p-12 text-center text-slate-500 space-y-3">
            <RefreshCw className="h-8 w-8 animate-spin mx-auto text-primary" />
            <p className="text-sm">Memuat daftar notifikasi...</p>
          </CardContent>
        </Card>
      ) : filteredNotifications.length === 0 ? (
        <Card className="rounded-sm border-dashed">
          <CardContent className="p-12 text-center">
            <Inbox className="h-12 w-12 mx-auto mb-3 text-slate-300" />
            <h3 className="text-base font-semibold text-slate-900">Tidak Ada Notifikasi</h3>
            <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1">
              Saat ini tidak ada pesan pemberitahuan yang sesuai dengan kriteria filter yang Anda pilih.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-3">
          {filteredNotifications.map((item) => {
            const isUnread = !item.read_at;
            const rawTarget =
              (item.data && typeof item.data === 'object' && ('target_url' in item.data ? item.data.target_url : item.data.url)) ||
              null;
            const hasSafeLink = Boolean(getSafeDeepLink(rawTarget));

            return (
              <Card
                key={item.id}
                className={`rounded-sm transition-all border ${
                  isUnread
                    ? 'border-blue-200 bg-blue-50/20 shadow-xs'
                    : 'border-slate-200 bg-white hover:border-slate-300'
                }`}
              >
                <CardContent className="p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                  <div className="flex items-start gap-3 flex-1 min-w-0">
                    <div className="mt-1 shrink-0">
                      {isUnread ? (
                        <span className="block h-2.5 w-2.5 rounded-full bg-blue-600 ring-4 ring-blue-100" />
                      ) : (
                        <span className="block h-2.5 w-2.5 rounded-full bg-slate-300" />
                      )}
                    </div>
                    <div className="space-y-1 min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <h4
                          className={`text-sm ${
                            isUnread ? 'font-bold text-slate-900' : 'font-semibold text-slate-800'
                          }`}
                        >
                          {item.title}
                        </h4>
                        <Badge variant={isUnread ? 'info' : 'outline'} className="text-[10px]">
                          {isUnread ? 'Baru' : 'Dibaca'}
                        </Badge>
                        {item.notif_type && (
                          <span className="text-[10px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-xs">
                            {item.notif_type}
                          </span>
                        )}
                        <span className="text-[11px] text-slate-400">
                          {new Date(item.created_at).toLocaleString('id-ID', {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                          })}
                        </span>
                      </div>
                      <p className="text-xs text-slate-600 leading-relaxed max-w-3xl">
                        {item.body}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    {hasSafeLink && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleItemClick(item)}
                        className="h-8 gap-1.5 text-xs text-primary border-primary/20 hover:bg-primary/5"
                      >
                        <span>Buka Menu</span>
                        <ExternalLink className="h-3.5 w-3.5" />
                      </Button>
                    )}
                    {isUnread && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleMarkAsRead(item.id)}
                        className="h-8 gap-1 text-xs text-slate-700"
                        title="Tandai telah dibaca"
                      >
                        <Check className="h-3.5 w-3.5" />
                        <span className="hidden md:inline">Baca</span>
                      </Button>
                    )}
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleDelete(item.id)}
                      className="h-8 p-2 text-red-600 hover:text-red-700 hover:bg-red-50 border-slate-200"
                      title="Hapus notifikasi"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {meta.totalPages > 1 && (
        <div className="flex items-center justify-between border-t border-slate-200 pt-4 text-xs text-slate-600">
          <div>
            Halaman {meta.page} dari {meta.totalPages} ({meta.total} item)
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage <= 1}
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              className="gap-1 h-8"
            >
              <ChevronLeft className="h-4 w-4" />
              <span>Sebelumnya</span>
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage >= meta.totalPages}
              onClick={() => setCurrentPage((p) => p + 1)}
              className="gap-1 h-8"
            >
              <span>Berikutnya</span>
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
