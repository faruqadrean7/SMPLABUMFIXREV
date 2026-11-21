'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { getPortalRoleOptions } from '@/lib/rbac/evaluator';
import { Button } from '@/components/ui/button';
import { Layers, Check, ChevronDown } from 'lucide-react';

export function RoleSwitcher() {
  const { user, setActiveRole } = useAuth();
  const router = useRouter();
  const [isOpen, setIsOpen] = useState(false);

  if (!user || !user.roles || user.roles.length === 0) {
    return null;
  }

  const portals = getPortalRoleOptions(user.roles as string[]);

  // Jika hanya memiliki akses ke 1 shell, tidak perlu tampilkan switcher
  if (portals.length <= 1) {
    return null;
  }

  return (
    <div className="relative inline-block text-left">
      <Button
        variant="outline"
        size="sm"
        onClick={() => setIsOpen(!isOpen)}
        className="gap-1.5 border-slate-300 text-slate-700 bg-white"
        aria-haspopup="true"
        aria-expanded={isOpen}
      >
        <Layers className="h-3.5 w-3.5 text-brand-blue" />
        <span className="text-xs font-semibold">Ganti Portal</span>
        <ChevronDown className="h-3 w-3 text-slate-400" />
      </Button>

      {isOpen && (
        <>
          <div
            className="fixed inset-0 z-40"
            onClick={() => setIsOpen(false)}
            aria-hidden="true"
          />
          <div className="absolute right-0 mt-1.5 w-56 rounded-md bg-white p-1 shadow-lg ring-1 ring-black ring-opacity-5 z-50">
            <div className="px-3 py-1.5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              Pilih Portal Akses
            </div>
            {portals.map((item) => (
              <button
                key={item.portal}
                onClick={() => {
                  setIsOpen(false);
                  setActiveRole(item.portal);
                  router.push(item.href);
                }}
                className="w-full flex items-center justify-between px-3 py-2 text-xs rounded-sm text-left text-slate-700 hover:bg-slate-50 transition-colors"
              >
                <span className="font-medium">{item.label}</span>
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
