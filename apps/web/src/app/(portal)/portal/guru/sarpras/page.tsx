'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { LoadingSpinner } from '@/components/shared/loading-spinner';

export default function SarprasRedirectPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace('/portal/guru/fasilitas');
  }, [router]);

  return (
    <div className="py-20 flex items-center justify-center">
      <LoadingSpinner label="Mengalihkan ke Fasilitas &amp; Sarpras..." size="lg" />
    </div>
  );
}
