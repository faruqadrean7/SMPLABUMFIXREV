'use client';

import { useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';

export default function DeprecatedCbtIdRedirectPage() {
  const params = useParams();
  const router = useRouter();

  useEffect(() => {
    if (params?.id) {
      router.replace(`/portal/siswa/ujian/${params.id}`);
    } else {
      router.replace('/portal/siswa');
    }
  }, [params, router]);

  return null;
}
