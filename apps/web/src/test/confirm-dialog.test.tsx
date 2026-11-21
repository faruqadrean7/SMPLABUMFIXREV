import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';

describe('ConfirmDialog Shared Component', () => {
  it('does not render when isOpen is false', () => {
    const { container } = render(
      <ConfirmDialog
        isOpen={false}
        title="Konfirmasi Tindakan"
        description="Apakah Anda yakin ingin melanjutkan?"
        onClose={vi.fn()}
        onConfirm={vi.fn()}
      />
    );

    expect(container.firstChild).toBeNull();
  });

  it('renders title, description, and affected item when isOpen is true', () => {
    render(
      <ConfirmDialog
        isOpen={true}
        title="Hapus Tahun Ajaran"
        description="Data yang dihapus tidak dapat dipulihkan."
        affectedItemName="Tahun Ajaran 2024/2025"
        onClose={vi.fn()}
        onConfirm={vi.fn()}
      />
    );

    expect(screen.getByText('Hapus Tahun Ajaran')).toBeInTheDocument();
    expect(screen.getByText('Data yang dihapus tidak dapat dipulihkan.')).toBeInTheDocument();
    expect(screen.getByText('Tahun Ajaran 2024/2025')).toBeInTheDocument();
  });

  it('calls onConfirm when confirm button is clicked', () => {
    const handleConfirm = vi.fn();
    render(
      <ConfirmDialog
        isOpen={true}
        title="Aktifkan Periode"
        description="Periode aktif akan diubah."
        confirmLabel="Ya, Aktifkan"
        onClose={vi.fn()}
        onConfirm={handleConfirm}
      />
    );

    const confirmBtn = screen.getByRole('button', { name: /Ya, Aktifkan/i });
    fireEvent.click(confirmBtn);
    expect(handleConfirm).toHaveBeenCalledTimes(1);
  });

  it('calls onClose when cancel button is clicked', () => {
    const handleClose = vi.fn();
    render(
      <ConfirmDialog
        isOpen={true}
        title="Aktifkan Periode"
        description="Periode aktif akan diubah."
        cancelLabel="Batal"
        onClose={handleClose}
        onConfirm={vi.fn()}
      />
    );

    const cancelBtn = screen.getByRole('button', { name: /Batal/i });
    fireEvent.click(cancelBtn);
    expect(handleClose).toHaveBeenCalledTimes(1);
  });

  it('closes dialog on Escape key press when not loading', () => {
    const handleClose = vi.fn();
    render(
      <ConfirmDialog
        isOpen={true}
        title="Tindakan"
        description="Deskripsi"
        onClose={handleClose}
        onConfirm={vi.fn()}
        isLoading={false}
      />
    );

    fireEvent.keyDown(window, { key: 'Escape', code: 'Escape' });
    expect(handleClose).toHaveBeenCalledTimes(1);
  });

  it('disables buttons and shows spinner when isLoading is true', () => {
    const handleClose = vi.fn();
    const handleConfirm = vi.fn();

    render(
      <ConfirmDialog
        isOpen={true}
        title="Sedang Memproses"
        description="Harap tunggu..."
        confirmLabel="Konfirmasi"
        onClose={handleClose}
        onConfirm={handleConfirm}
        isLoading={true}
      />
    );

    const confirmBtn = screen.getByRole('button', { name: /Konfirmasi/i });
    const cancelBtn = screen.getByRole('button', { name: /Batal/i });
    expect(confirmBtn).toBeDisabled();
    expect(cancelBtn).toBeDisabled();

    // Escape should not close when loading
    fireEvent.keyDown(window, { key: 'Escape', code: 'Escape' });
    expect(handleClose).not.toHaveBeenCalled();
  });

  it('renders error message banner when error prop is provided', () => {
    render(
      <ConfirmDialog
        isOpen={true}
        title="Konfirmasi Gagal"
        description="Deskripsi tindakan"
        error="Terjadi kesalahan server HTTP 500"
        onClose={vi.fn()}
        onConfirm={vi.fn()}
      />
    );

    expect(screen.getByText('Terjadi kesalahan server HTTP 500')).toBeInTheDocument();
  });
});
