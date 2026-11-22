import React from 'react';
import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { BookOpen } from 'lucide-react';
import { StudentPageHeader, StatTile, SectionCard } from '@/features/portal/mascot/student-ui';

describe('FE-SISWA-UI-01: student UI primitives', () => {
  it('FE-SISWA-UI-01-ui: StudentPageHeader shows title and subtitle', () => {
    const { getByText } = render(<StudentPageHeader title="Nilai Kamu" subtitle="Semester Gasal" icon={BookOpen} />);
    expect(getByText('Nilai Kamu')).toBeTruthy();
    expect(getByText('Semester Gasal')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-ui: StatTile shows label and value with tone class', () => {
    const { getByText, container } = render(<StatTile label="Tugas" value={2} tone="coral" />);
    expect(getByText('Tugas')).toBeTruthy();
    expect(getByText('2')).toBeTruthy();
    expect(container.querySelector('.tile-coral')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-ui: SectionCard renders title and children', () => {
    const { getByText } = render(
      <SectionCard title="Tugas Kamu">
        <p>daftar tugas</p>
      </SectionCard>
    );
    expect(getByText('Tugas Kamu')).toBeTruthy();
    expect(getByText('daftar tugas')).toBeTruthy();
  });
});
