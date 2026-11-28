'use client';

import { useEffect, useState } from 'react';
import { academicYearsApi } from '@/features/master/academic-years-api';

const FALLBACK_PERIOD_LABEL = 'Tahun Ajaran Aktif';

/**
 * Mengambil label Tahun Ajaran & Semester aktif dari API tanpa mewajibkan QueryClientProvider.
 */
export function useActiveAcademicYearLabel(): string {
  const [label, setLabel] = useState<string>(FALLBACK_PERIOD_LABEL);

  useEffect(() => {
    let active = true;

    academicYearsApi
      .getActive()
      .then((period) => {
        if (!active || !period?.academicYear?.name) return;
        const yearName = period.academicYear.name;
        const semesterName = period.semester?.name ? ` ${period.semester.name}` : '';
        setLabel(`T.A. ${yearName}${semesterName}`);
      })
      .catch(() => {
        // Pertahankan label aman jika sesi belum termuat atau berada di mode offline/test
      });

    return () => {
      active = false;
    };
  }, []);

  return label;
}
