'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Loader2 } from 'lucide-react';
import { useAuth } from '@/lib/auth-context';

export default function HomePage() {
  const router = useRouter();
  const { user, isAuthenticated, isLoading, getRoleDefaultPath } = useAuth();

  useEffect(() => {
    if (!isLoading) {
      if (isAuthenticated && user) {
        const destination = getRoleDefaultPath(user);
        router.replace(destination);
      } else {
        router.replace('/login');
      }
    }
  }, [isLoading, isAuthenticated, user, router, getRoleDefaultPath]);

  return (
    <div className="min-h-screen bg-slate-100 flex flex-col items-center justify-center p-6 text-slate-800">
      <div className="flex flex-col items-center gap-3">
        <Loader2 className="w-8 h-8 text-blue-900 animate-spin" />
        <p className="text-xs font-semibold text-slate-600">Mengarahkan ke portal Anda...</p>
      </div>
    </div>
  );
}
