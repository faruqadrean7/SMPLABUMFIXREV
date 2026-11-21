'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { Bell, Check, CheckCheck, RefreshCw, AlertCircle, ExternalLink, Inbox } from 'lucide-react';
import { notificationsApi } from '@/features/notifications/notifications-api';
import { NotificationItem } from '@/features/notifications/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export function getSafeDeepLink(targetUrl?: unknown): string | null {
  if (typeof targetUrl !== 'string') return null;
  const trimmed = targetUrl.trim();
  if (
    !trimmed.startsWith('/') ||
    trimmed.startsWith('//') ||
    trimmed.includes('\\') ||
    trimmed.startsWith('/\\')
  ) {
    return null;
  }
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) {
    return null;
  }
  return trimmed;
}

interface NotificationBellProps {
  portal: 'admin' | 'siswa' | 'ortu' | 'guru' | 'staf' | 'portal';
}

export function NotificationBell({ portal }: NotificationBellProps) {
  const router = useRouter();
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [unreadCount, setUnreadCount] = useState<number>(0);
  const popoverRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);

  const inboxHref = portal === 'admin' ? '/admin/notifications' : '/portal/notifikasi';

  const fetchLatestNotifications = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await notificationsApi.getNotifications({ page: 1, per_page: 5 });
      setNotifications(res.data || []);
      setUnreadCount(res.meta?.unread_count ?? 0);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat notifikasi.';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchLatestNotifications();
  }, [fetchLatestNotifications]);

  // Click outside listener & ESC key listener
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        popoverRef.current &&
        !popoverRef.current.contains(event.target as Node) &&
        buttonRef.current &&
        !buttonRef.current.contains(event.target as Node)
      ) {
        setIsOpen(false);
      }
    }

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape' && isOpen) {
        setIsOpen(false);
        buttonRef.current?.focus();
      }
    }

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      document.addEventListener('keydown', handleKeyDown);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen]);

  const handleMarkAsRead = async (id: string, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    try {
      await notificationsApi.markAsRead(id);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (err) {
      // silent or retryable
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      await notificationsApi.markAllAsRead();
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
      );
      setUnreadCount(0);
    } catch (err) {
      // silent
    }
  };

  const handleNotificationClick = async (notif: NotificationItem) => {
    if (!notif.read_at) {
      await handleMarkAsRead(notif.id);
    }
    setIsOpen(false);

    const rawTarget =
      (notif.data && typeof notif.data === 'object' && ('target_url' in notif.data ? notif.data.target_url : notif.data.url)) ||
      null;

    const safeUrl = getSafeDeepLink(rawTarget);
    if (safeUrl) {
      router.push(safeUrl);
    } else {
      router.push(inboxHref);
    }
  };

  return (
    <div className="relative inline-block text-left">
      <button
        ref={buttonRef}
        onClick={() => {
          const nextState = !isOpen;
          setIsOpen(nextState);
          if (nextState) {
            fetchLatestNotifications();
          }
        }}
        className="relative p-2 rounded-md text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary transition-colors"
        aria-label={`Notifikasi, ${unreadCount} belum dibaca`}
        aria-expanded={isOpen}
        aria-haspopup="dialog"
      >
        <Bell className="h-5 w-5" />
        {unreadCount > 0 && (
          <span className="absolute top-1.5 right-1.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div
          ref={popoverRef}
          role="dialog"
          aria-label="Daftar Notifikasi Masuk"
          className="absolute right-0 mt-2 w-80 sm:w-96 rounded-sm bg-white shadow-lg ring-1 ring-black/10 z-50 overflow-hidden animate-in fade-in zoom-in-95"
        >
          {/* Header Popover */}
          <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3 bg-slate-50">
            <div className="flex items-center gap-2">
              <span className="font-semibold text-xs text-slate-900">Notifikasi</span>
              {unreadCount > 0 ? (
                <Badge variant="danger" className="text-[10px] px-1.5 py-0">
                  {unreadCount} baru
                </Badge>
              ) : (
                <Badge variant="default" className="text-[10px] px-1.5 py-0">
                  0 belum dibaca
                </Badge>
              )}
            </div>
            {unreadCount > 0 && (
              <button
                onClick={handleMarkAllAsRead}
                className="inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:text-primary/80 transition-colors"
                title="Tandai semua notifikasi telah dibaca"
              >
                <CheckCheck className="h-3.5 w-3.5" />
                <span>Tandai Semua</span>
              </button>
            )}
          </div>

          {/* List Content */}
          <div className="max-h-80 overflow-y-auto divide-y divide-slate-100">
            {isLoading ? (
              <div className="p-6 text-center text-xs text-slate-500 space-y-2">
                <RefreshCw className="h-5 w-5 animate-spin mx-auto text-primary" />
                <p>Memuat notifikasi...</p>
              </div>
            ) : error ? (
              <div className="p-4 text-center space-y-2">
                <div className="inline-flex items-center gap-1.5 text-xs text-red-600 font-medium">
                  <AlertCircle className="h-4 w-4" />
                  <span>{error}</span>
                </div>
                <div>
                  <Button variant="outline" size="sm" onClick={fetchLatestNotifications} className="text-xs h-7 px-2">
                    Coba Lagi
                  </Button>
                </div>
              </div>
            ) : notifications.length === 0 ? (
              <div className="p-8 text-center text-xs text-slate-500">
                <Inbox className="h-8 w-8 mx-auto mb-2 text-slate-300" />
                <p className="font-medium text-slate-700">Tidak ada notifikasi baru</p>
                <p className="text-[11px] text-slate-400 mt-0.5">Semua pesan masuk telah dibaca.</p>
              </div>
            ) : (
              notifications.map((item) => {
                const isUnread = !item.read_at;
                return (
                  <div
                    key={item.id}
                    onClick={() => handleNotificationClick(item)}
                    className={`p-3.5 text-left cursor-pointer transition-colors flex items-start gap-3 hover:bg-slate-50 ${
                      isUnread ? 'bg-blue-50/40' : 'bg-white'
                    }`}
                  >
                    <div className="mt-1 shrink-0">
                      {isUnread ? (
                        <span className="block h-2 w-2 rounded-full bg-blue-600 ring-2 ring-blue-100" />
                      ) : (
                        <span className="block h-2 w-2 rounded-full bg-transparent" />
                      )}
                    </div>
                    <div className="flex-1 min-w-0 space-y-0.5">
                      <div className="flex items-center justify-between gap-2">
                        <h4
                          className={`text-xs truncate ${
                            isUnread ? 'font-bold text-slate-900' : 'font-medium text-slate-700'
                          }`}
                        >
                          {item.title}
                        </h4>
                        <span className="text-[10px] text-slate-400 shrink-0">
                          {new Date(item.created_at).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                          })}
                        </span>
                      </div>
                      <p className="text-[11px] text-slate-600 line-clamp-2 leading-relaxed">
                        {item.body}
                      </p>
                    </div>
                    {isUnread && (
                      <button
                        onClick={(e) => handleMarkAsRead(item.id, e)}
                        className="p-1 rounded text-slate-400 hover:text-primary hover:bg-blue-100/50 transition-colors shrink-0"
                        title="Tandai sudah dibaca"
                        aria-label="Tandai sudah dibaca"
                      >
                        <Check className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </div>
                );
              })
            )}
          </div>

          {/* Footer Popover */}
          <div className="border-t border-slate-200 p-2 bg-slate-50 text-center">
            <button
              onClick={() => {
                setIsOpen(false);
                router.push(inboxHref);
              }}
              className="w-full inline-flex items-center justify-center gap-1.5 py-1.5 text-xs font-semibold text-primary hover:underline transition-colors"
            >
              <span>Lihat Semua Notifikasi</span>
              <ExternalLink className="h-3 w-3" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
