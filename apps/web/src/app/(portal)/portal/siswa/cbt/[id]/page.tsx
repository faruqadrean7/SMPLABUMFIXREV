'use client';

import React, { useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';

/**
 * FE-CBT-UJIAN-01: Rute kanonis dialihkan ke /portal/siswa/ujian/[id].
 * Menjamin kompatibilitas mundur untuk deep link / notifikasi lama tanpa memuat data mock.
 */
export default function CBTExamWorkspaceRedirect() {
  const router = useRouter();
  const routeParams = useParams();
  const examId = (routeParams?.id as string) || '';

  useEffect(() => {
    if (examId) {
      router.replace(`/portal/siswa/ujian/${examId}`);
    }
  }, [examId, router]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-900 text-white">
      <div className="text-center space-y-3">
        <div className="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto" />
        <p className="text-sm text-slate-300 font-medium">Mengalihkan ke workspace ujian CBT...</p>
      </div>
    </div>
  );
}
