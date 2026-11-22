import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, fireEvent } from '@testing-library/react';
import { StudentLoading, StudentEmpty, StudentError } from '@/features/portal/mascot/student-states';

describe('FE-SISWA-UI-01: student state components', () => {
  it('FE-SISWA-UI-01-states: StudentLoading shows label and mascot', () => {
    const { container, getByText } = render(<StudentLoading label="Memuat..." />);
    expect(getByText('Memuat...')).toBeTruthy();
    expect(container.querySelector('svg')).toBeTruthy();
  });

  it('FE-SISWA-UI-01-states: StudentError renders message and retry calls onRetry', () => {
    const onRetry = vi.fn();
    const { getByText } = render(<StudentError message="Gagal" onRetry={onRetry} />);
    expect(getByText('Gagal')).toBeTruthy();
    fireEvent.click(getByText('Coba lagi'));
    expect(onRetry).toHaveBeenCalledOnce();
  });

  it('FE-SISWA-UI-01-states: StudentError without onRetry hides the retry button', () => {
    const { queryByText } = render(<StudentError message="Gagal" />);
    expect(queryByText('Coba lagi')).toBeNull();
  });

  it('FE-SISWA-UI-01-states: StudentEmpty shows title and hint', () => {
    const { getByText } = render(<StudentEmpty title="Belum ada tugas" hint="Santai dulu" />);
    expect(getByText('Belum ada tugas')).toBeTruthy();
    expect(getByText('Santai dulu')).toBeTruthy();
  });
});
