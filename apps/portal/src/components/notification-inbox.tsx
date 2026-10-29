'use client';

import React from 'react';
import { Bell, CheckCheck, Dot } from 'lucide-react';
import {
  useNotifications,
  useMarkNotificationRead,
  useMarkAllNotificationsRead,
  isNotificationRead,
} from '@/lib/notifications-api';
import { ErrorState } from '@/components/ui/error-state';
import { EmptyState } from '@/components/ui/empty-state';

export function NotificationInbox({ title = 'Notifikasi' }: { title?: string }) {
  const { data, isLoading, isError, refetch } = useNotifications();
  const markRead = useMarkNotificationRead();
  const markAll = useMarkAllNotificationsRead();

  const items = data?.items ?? [];
  const unread = data?.unreadCount ?? 0;

  return (
    <div className="flex flex-col gap-3.5 max-w-3xl">
      <div className="flex items-center justify-between shrink-0">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-extrabold text-slate-900 tracking-tight">{title}</h1>
            {unread > 0 && (
              <span className="px-2.5 py-0.5 rounded-lg bg-rose-700 text-white text-xs font-bold">{unread} Baru</span>
            )}
          </div>
          <p className="text-xs text-slate-600 mt-1">Pemberitahuan resmi dari sekolah dan sistem akademik.</p>
        </div>
        <button
          type="button"
          disabled={unread === 0 || markAll.isPending}
          onClick={() => markAll.mutate()}
          className="px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-bold flex items-center gap-1.5 shrink-0"
        >
          <CheckCheck className="w-4 h-4" />
          <span>Tandai semua dibaca</span>
        </button>
      </div>

      <div className="bg-white border border-slate-200/90 rounded-2xl p-2 shadow-xs min-h-100">
        {isLoading ? (
          <div className="p-3 space-y-2">
            {[0, 1, 2, 3].map((i) => (
              <div key={i} className="h-14 rounded-xl bg-slate-100 animate-pulse" />
            ))}
          </div>
        ) : isError ? (
          <div className="p-4">
            <ErrorState
              title="Gagal Memuat Notifikasi"
              message="Tidak dapat mengambil inbox notifikasi dari server. Silakan coba kembali."
              onRetry={() => refetch()}
            />
          </div>
        ) : items.length === 0 ? (
          <div className="p-4">
            <EmptyState icon={Bell} title="Belum Ada Notifikasi" description="Semua pemberitahuan dari sekolah akan muncul di sini." />
          </div>
        ) : (
          <ul className="divide-y divide-slate-100">
            {items.map((n) => {
              const read = isNotificationRead(n);
              return (
                <li key={n.id}>
                  <button
                    type="button"
                    onClick={() => (!read ? markRead.mutate(n.id) : undefined)}
                    className={`w-full text-left px-3 py-3 rounded-xl flex gap-3 transition-colors ${
                      read ? 'hover:bg-slate-50' : 'bg-blue-50/60 hover:bg-blue-50'
                    }`}
                  >
                    <div className={`mt-0.5 w-9 h-9 rounded-xl flex items-center justify-center shrink-0 ${read ? 'bg-slate-100 text-slate-500' : 'bg-blue-900 text-white'}`}>
                      <Bell className="w-4 h-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-1">
                        <p className={`text-sm truncate ${read ? 'font-semibold text-slate-700' : 'font-bold text-slate-900'}`}>{n.title}</p>
                        {!read && <Dot className="w-5 h-5 text-rose-600 shrink-0" />}
                      </div>
                      {n.body && <p className="text-xs text-slate-600 mt-0.5 line-clamp-2">{n.body}</p>}
                      {n.created_at && <p className="text-[11px] text-slate-400 mt-1">{n.created_at}</p>}
                    </div>
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}

export default NotificationInbox;
