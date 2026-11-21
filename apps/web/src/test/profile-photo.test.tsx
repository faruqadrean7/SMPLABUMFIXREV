import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { Avatar } from '@/components/ui/avatar';
import { ProfilePhotoUploader } from '@/components/ui/profile-photo-uploader';
import { StudentDialog } from '@/features/master/student-dialog';
import { TeacherDialog } from '@/features/master/teacher-dialog';
import { storageApi } from '@/features/storage/storage-api';

// Mock storageApi
vi.mock('@/features/storage/storage-api', () => ({
  storageApi: {
    uploadFileDirect: vi.fn(),
  },
}));

describe('FE-PHOTO-01: Avatar Component', () => {
  it('menampilkan inisial dua huruf jika tidak ada tautan foto', () => {
    render(<Avatar name="Budi Santoso" alt="Foto Budi" />);
    const avatar = screen.getByRole('img', { name: /budi/i });
    expect(avatar).toBeDefined();
    expect(avatar.textContent).toBe('BS');
  });

  it('menampilkan inisial huruf pertama jika nama hanya satu kata', () => {
    render(<Avatar name="Siti" alt="Foto Siti" />);
    const avatar = screen.getByRole('img', { name: /siti/i });
    expect(avatar).toBeDefined();
    expect(avatar.textContent).toBe('SI');
  });

  it('merender elemen img jika src foto valid diberikan', () => {
    render(<Avatar src="https://example.com/avatar.jpg" name="Ahmad Dahlan" alt="Pasfoto Ahmad" />);
    const imgElement = screen.getByAltText(/pasfoto ahmad/i);
    expect(imgElement).toBeDefined();
    expect(imgElement.getAttribute('src')).toBe('https://example.com/avatar.jpg');
  });

  it('beralih ke inisial jika gambar gagal dimuat (onError fallback)', () => {
    render(<Avatar src="https://example.com/broken-link.jpg" name="Dewi Sartika" alt="Foto Dewi" />);
    const imgElement = screen.getByAltText(/foto dewi/i);
    expect(imgElement).not.toBeNull();

    // Trigger onError
    fireEvent.error(imgElement);

    // Should now display fallback initials
    expect(screen.getByRole('img', { name: /foto dewi/i }).textContent).toBe('DS');
  });
});

describe('FE-PHOTO-02: ProfilePhotoUploader Component', () => {
  const mockOnChange = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    global.URL.createObjectURL = vi.fn(() => 'blob:mock-preview-url');
    global.URL.revokeObjectURL = vi.fn();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('menolak berkas dengan format selain gambar (PDF / Script)', async () => {
    render(
      <ProfilePhotoUploader
        label="Pasfoto Resmi"
        onChange={mockOnChange}
      />
    );

    const input = screen.getByLabelText(/pilih berkas foto profil/i) as HTMLInputElement;
    const invalidFile = new File(['%PDF-1.4...'], 'dokumen.pdf', { type: 'application/pdf' });

    fireEvent.change(input, { target: { files: [invalidFile] } });

    await waitFor(() => {
      expect(screen.getByText(/format berkas tidak didukung/i)).toBeDefined();
    });

    expect(storageApi.uploadFileDirect).not.toHaveBeenCalled();
    expect(mockOnChange).not.toHaveBeenCalled();
  });

  it('menolak berkas foto yang ukurannya melebihi batas (maks. 2 MB)', async () => {
    render(
      <ProfilePhotoUploader
        label="Pasfoto Resmi"
        maxSizeMb={2}
        onChange={mockOnChange}
      />
    );

    const input = screen.getByLabelText(/pilih berkas foto profil/i) as HTMLInputElement;
    // Berkas 3 MB
    const largeFile = new File([new Uint8Array(3 * 1024 * 1024)], 'foto_besar.jpg', {
      type: 'image/jpeg',
    });

    fireEvent.change(input, { target: { files: [largeFile] } });

    await waitFor(() => {
      expect(screen.getByText(/melebihi batas maksimal 2 mb/i)).toBeDefined();
    });

    expect(storageApi.uploadFileDirect).not.toHaveBeenCalled();
  });

  it('berhasil memproses unggahan foto valid (JPEG) dan memanggil onChange dengan fileId', async () => {
    const mockFileItem = {
      id: 'f9411854-469b-4b13-91ee-001122334455',
      bucket: 'public',
      object_key: 'public/2026/10/foto_resmi.jpg',
      mime_type: 'image/jpeg',
      size_bytes: 512000,
      visibility: 'publik' as const,
      created_at: '2026-10-16T10:00:00Z',
    };

    vi.mocked(storageApi.uploadFileDirect).mockResolvedValueOnce(mockFileItem);

    render(
      <ProfilePhotoUploader
        label="Pasfoto Resmi"
        name="Ahmad Siswa"
        onChange={mockOnChange}
      />
    );

    const input = screen.getByLabelText(/pilih berkas foto profil/i) as HTMLInputElement;
    const validFile = new File([new Uint8Array(512000)], 'pasfoto.jpg', { type: 'image/jpeg' });

    fireEvent.change(input, { target: { files: [validFile] } });

    await waitFor(() => {
      expect(storageApi.uploadFileDirect).toHaveBeenCalledWith(
        expect.objectContaining({
          file: validFile,
          visibility: 'publik',
          purpose: 'avatar',
        })
      );
    });

    await waitFor(() => {
      expect(mockOnChange).toHaveBeenCalledWith(mockFileItem.id, mockFileItem);
    });
  });

  it('mendukung penghapusan dan pembatalan foto profil', async () => {
    render(
      <ProfilePhotoUploader
        label="Pasfoto Resmi"
        currentPhotoUrl="https://example.com/existing.jpg"
        value="existing-file-id"
        onChange={mockOnChange}
      />
    );

    const removeBtn = screen.getByTitle(/hapus foto profil/i);
    expect(removeBtn).toBeDefined();

    fireEvent.click(removeBtn);

    expect(mockOnChange).toHaveBeenCalledWith(null, null);
  });
});

describe('FE-PHOTO-03: StudentDialog & TeacherDialog Photo Integration', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('merender komponen unggah foto profil pada StudentDialog', () => {
    const mockOnSubmit = vi.fn();
    render(
      <StudentDialog
        isOpen={true}
        onClose={vi.fn()}
        onSubmit={mockOnSubmit}
      />
    );

    expect(screen.getByText('Pasfoto Resmi Siswa')).toBeDefined();
    expect(screen.getByText(/format: jpg, png, atau webp/i)).toBeDefined();
  });

  it('merender foto yang sudah tersimpan saat edit data guru pada TeacherDialog', () => {
    const mockOnSubmit = vi.fn();
    const existingTeacher = {
      id: 'teacher-uuid-1',
      full_name: 'Dr. Hendra Gunawan, M.Pd.',
      nip: '198501012010011001',
      nuptk: '1234567890123456',
      position: 'Guru Matematika',
      status: 'tetap' as const,
      photo_file_id: 'file-uuid-photo-1',
      photo_url: 'https://example.com/storage/hendra.jpg',
    };

    render(
      <TeacherDialog
        isOpen={true}
        onClose={vi.fn()}
        onSubmit={mockOnSubmit}
        initialData={existingTeacher}
      />
    );

    expect(screen.getByText('Pasfoto Resmi Pendidik')).toBeDefined();
    expect(screen.getByText('Tersimpan')).toBeDefined();
  });
});
