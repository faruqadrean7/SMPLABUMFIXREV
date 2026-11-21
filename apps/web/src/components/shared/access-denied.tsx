'use client';

import React from 'react';
import { ShieldAlert, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/providers/auth-provider';
import { resolveUserDestination, getDestinationPath } from '@/lib/rbac/evaluator';

export function AccessDenied({
  title = 'Akses Ditolak',
  description = 'Anda tidak memiliki hak akses atau wewenang untuk membuka halaman ini.',
}: {
  title?: string;
  description?: string;
}) {
  const router = useRouter();
  const { user } = useAuth();

  const handleReturn = () => {
    if (user) {
      const destination = resolveUserDestination(user.roles as string[]);
      router.push(getDestinationPath(destination));
    } else {
      router.push('/masuk');
    }
  };

  return (
    <div className="min-h-[60vh] flex flex-col items-center justify-center p-6 text-center">
      <div className="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-status-danger mb-4">
        <ShieldAlert className="h-8 w-8" />
      </div>
      <h2 className="text-xl font-bold text-slate-900">{title}</h2>
      <p className="mt-2 max-w-md text-sm text-slate-600">{description}</p>
      <div className="mt-6 flex gap-3">
        <Button onClick={handleReturn} className="gap-2">
          <ArrowLeft className="h-4 w-4" />
          Kembali ke Portal Anda
        </Button>
      </div>
    </div>
  );
}
