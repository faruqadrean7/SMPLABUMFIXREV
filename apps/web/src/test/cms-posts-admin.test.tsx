import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import CmsAdminPage from '@/app/(admin)/admin/cms/page';
import { cmsApi } from '@/features/cms/cms-api';
import { PostItem, PostCategoryItem } from '@/features/cms/types';
import { storageApi } from '@/features/storage/storage-api';

// Mock storageApi for FileUpload component
vi.mock('@/features/storage/storage-api', () => ({
  storageApi: {
    uploadFileDirect: vi.fn(),
    getFile: vi.fn(),
  },
}));

describe('INT-04: CMS Admin Edit News Posts (ADM-CMS-02 & BE-CMS-02)', () => {
  const mockCategories: PostCategoryItem[] = [
    { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
    { id: 'cat-2', name: 'Akademik', slug: 'akademik' },
  ];

  const mockPosts: PostItem[] = [
    {
      id: 'post-101',
      title: 'Juara 1 Lomba Cerdas Cermat Sains 2026',
      slug: 'juara-1-lomba-cerdas-cermat-sains-2026',
      content: 'Tim siswa berhasil meraih emas pada ajang bergengsi.',
      category_id: 'cat-1',
      status: 'terbit',
      visibility: 'publik',
      published_at: '2026-10-15T08:00:00Z',
      category: { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
    },
    {
      id: 'post-102',
      title: 'Draf Rencana Studi Lapangan Laboratorium',
      slug: 'draf-rencana-studi-lapangan',
      content: 'Naskah draf panduan studi lapangan kelas 8.',
      category_id: 'cat-2',
      status: 'draft',
      visibility: 'internal',
      category: { id: 'cat-2', name: 'Akademik', slug: 'akademik' },
    },
  ];

  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    // Default mock responses
    vi.spyOn(cmsApi, 'getSiteSettings').mockResolvedValue({
      data: {
        identity: { name: 'SMP Laboratorium UM' },
      },
    } as any);

    vi.spyOn(cmsApi, 'getPosts').mockResolvedValue({
      data: mockPosts,
      meta: { page: 1, limit: 10, total: 2, totalPages: 1 },
    } as any);

    vi.spyOn(cmsApi, 'getPostCategories').mockResolvedValue({
      data: mockCategories,
    } as any);
  });

  const navigateToPostsTab = async () => {
    render(<CmsAdminPage />);
    const postsTabBtn = screen.getByRole('button', { name: /Berita & Artikel/i });
    fireEvent.click(postsTabBtn);
    await waitFor(() => {
      expect(screen.getByText('Juara 1 Lomba Cerdas Cermat Sains 2026')).toBeInTheDocument();
    });
  };

  it('1 & 2. Tombol Edit membuka form untuk berita yang benar dan terisi dengan nilai aktual', async () => {
    await navigateToPostsTab();

    // Temukan tombol Edit untuk post 101
    const editButtons = screen.getAllByTitle(/Edit berita/i);
    expect(editButtons).toHaveLength(2);

    fireEvent.click(editButtons[0]);

    // Modal Edit terbuka dengan judul yang sesuai
    expect(screen.getByRole('heading', { name: /Edit Berita \/ Pengumuman/i })).toBeInTheDocument();

    // Nilai form terisi data aktual post-101
    const titleInput = screen.getByLabelText(/Judul Berita \*/i) as HTMLInputElement;
    expect(titleInput.value).toBe('Juara 1 Lomba Cerdas Cermat Sains 2026');

    const slugInput = screen.getByLabelText(/Slug \/ URL Ramah/i) as HTMLInputElement;
    expect(slugInput.value).toBe('juara-1-lomba-cerdas-cermat-sains-2026');

    const contentInput = screen.getByLabelText(/Konten Berita \*/i) as HTMLTextAreaElement;
    expect(contentInput.value).toBe('Tim siswa berhasil meraih emas pada ajang bergengsi.');

    const statusSelect = screen.getByLabelText(/Status Publikasi/i) as HTMLSelectElement;
    expect(statusSelect.value).toBe('terbit');

    const visibilitySelect = screen.getByLabelText(/Visibilitas/i) as HTMLSelectElement;
    expect(visibilitySelect.value).toBe('publik');

    // Tombol submit bernama "Simpan Perubahan"
    expect(screen.getByRole('button', { name: /Simpan Perubahan/i })).toBeInTheDocument();
  });

  it('3 & 4. Admin dapat mengubah field dan menyimpan perubahan, memanggil API update, dan memperbarui daftar', async () => {
    const updateSpy = vi.spyOn(cmsApi, 'updatePost').mockResolvedValueOnce({
      data: {
        id: 'post-101',
        title: 'Juara 1 Lomba Cerdas Cermat Sains Internasional 2026',
        slug: 'juara-1-lomba-cerdas-cermat-sains-2026',
        content: 'Konten terverifikasi telah diperbarui.',
        status: 'terbit',
        visibility: 'publik',
      },
    } as any);

    await navigateToPostsTab();

    // Buka edit post-101
    const editButtons = screen.getAllByTitle(/Edit berita/i);
    fireEvent.click(editButtons[0]);

    // Ubah judul
    const titleInput = screen.getByLabelText(/Judul Berita \*/i);
    fireEvent.change(titleInput, {
      target: { value: 'Juara 1 Lomba Cerdas Cermat Sains Internasional 2026' },
    });

    // Ubah konten
    const contentInput = screen.getByLabelText(/Konten Berita \*/i);
    fireEvent.change(contentInput, {
      target: { value: 'Konten terverifikasi telah diperbarui.' },
    });

    // Simpan perubahan
    const submitBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(updateSpy).toHaveBeenCalledWith(
        'post-101',
        expect.objectContaining({
          title: 'Juara 1 Lomba Cerdas Cermat Sains Internasional 2026',
          content: 'Konten terverifikasi telah diperbarui.',
          status: 'terbit',
          visibility: 'publik',
        })
      );
    });

    // Notifikasi sukses tampil
    await waitFor(() => {
      expect(screen.getByText(/Berita atau artikel berhasil diperbarui/i)).toBeInTheDocument();
    });

    // Memuat ulang daftar berita dari backend
    expect(cmsApi.getPosts).toHaveBeenCalledTimes(2);
  });

  it('5 & 6. Menangani kesalahan validasi backend dan kegagalan jaringan tanpa menutup modal atau menghapus input pengguna', async () => {
    const updateSpy = vi.spyOn(cmsApi, 'updatePost').mockRejectedValueOnce(
      new Error('Slug berita sudah digunakan')
    );

    await navigateToPostsTab();

    const editButtons = screen.getAllByTitle(/Edit berita/i);
    fireEvent.click(editButtons[0]);

    // Ubah slug yang bentrok
    const slugInput = screen.getByLabelText(/Slug \/ URL Ramah/i);
    fireEvent.change(slugInput, {
      target: { value: 'slug-yang-sudah-ada' },
    });

    // Submit
    const submitBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
    fireEvent.click(submitBtn);

    // Pesan error tampil di dalam modal
    await waitFor(() => {
      expect(screen.getAllByText(/Slug berita sudah digunakan/i).length).toBeGreaterThanOrEqual(1);
    });

    // Modal tetap terbuka dan nilai form pengguna tetap tersimpan
    expect(screen.getByRole('heading', { name: /Edit Berita \/ Pengumuman/i })).toBeInTheDocument();
    expect((screen.getByLabelText(/Slug \/ URL Ramah/i) as HTMLInputElement).value).toBe('slug-yang-sudah-ada');
  });

  it('7. Tombol Batal menutup form edit tanpa mengirim perubahan', async () => {
    const updateSpy = vi.spyOn(cmsApi, 'updatePost');

    await navigateToPostsTab();

    const editButtons = screen.getAllByTitle(/Edit berita/i);
    fireEvent.click(editButtons[0]);

    expect(screen.getByRole('heading', { name: /Edit Berita \/ Pengumuman/i })).toBeInTheDocument();

    // Klik Batal
    const cancelBtn = screen.getByRole('button', { name: /Batal/i });
    fireEvent.click(cancelBtn);

    // Modal tertutup dan API update tidak dipanggil
    await waitFor(() => {
      expect(screen.queryByRole('heading', { name: /Edit Berita \/ Pengumuman/i })).not.toBeInTheDocument();
    });
    expect(updateSpy).not.toHaveBeenCalled();
  });

  it('8. Fitur pembuatan berita baru (create) dan penghapusan (delete) tetap berfungsi normal', async () => {
    const createSpy = vi.spyOn(cmsApi, 'createPost').mockResolvedValueOnce({
      data: {
        id: 'post-103',
        title: 'Berita Baru Terkini',
        slug: 'berita-baru-terkini',
        content: 'Isi pengumuman terbaru.',
        status: 'draft',
        visibility: 'publik',
      },
    } as any);

    const deleteSpy = vi.spyOn(cmsApi, 'deletePost').mockResolvedValueOnce({
      data: { message: 'Berita berhasil dihapus' },
    } as any);

    await navigateToPostsTab();

    // 1. Klik Tulis Berita Baru
    const createBtn = screen.getByRole('button', { name: /Tulis Berita Baru/i });
    fireEvent.click(createBtn);

    // Judul modal adalah pembuatan baru
    expect(screen.getByRole('heading', { name: /Tulis Berita \/ Pengumuman Baru/i })).toBeInTheDocument();
    expect((screen.getByLabelText(/Judul Berita \*/i) as HTMLInputElement).value).toBe('');
    expect(screen.getByRole('button', { name: /Simpan Berita/i })).toBeInTheDocument();

    // Isi dan submit create
    fireEvent.change(screen.getByLabelText(/Judul Berita \*/i), {
      target: { value: 'Berita Baru Terkini' },
    });
    fireEvent.change(screen.getByLabelText(/Konten Berita \*/i), {
      target: { value: 'Isi pengumuman terbaru.' },
    });

    fireEvent.click(screen.getByRole('button', { name: /Simpan Berita/i }));

    await waitFor(() => {
      expect(createSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Berita Baru Terkini',
          content: 'Isi pengumuman terbaru.',
        })
      );
    });

    // 2. Klik Hapus pada post kedua
    const deleteButtons = screen.getAllByTitle(/Hapus berita/i);
    fireEvent.click(deleteButtons[1]);

    const confirmBtn = screen.getByRole('button', { name: /Ya, Hapus/i });
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(deleteSpy).toHaveBeenCalledWith('post-102');
    });
  });
});

describe('INT-05: CMS Admin News Cover Image Upload (ADM-CMS-02 & BE-CMS-02)', () => {
  const mockCategories: PostCategoryItem[] = [
    { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
  ];

  const mockPostWithCover: PostItem = {
    id: 'post-cover-1',
    title: 'Inovasi Robotik Laboratorium Meraih Prestasi Nasional',
    slug: 'inovasi-robotik-laboratorium',
    content: 'Tim robotik sekolah berhasil mengukir prestasi tingkat nasional.',
    category_id: 'cat-1',
    cover_file_id: 'file-existing-uuid-111',
    cover_file: {
      id: 'file-existing-uuid-111',
      url: 'https://cdn.example.com/robotik-cover.jpg',
    },
    status: 'terbit',
    visibility: 'publik',
    published_at: '2026-10-15T09:00:00Z',
    category: { id: 'cat-1', name: 'Prestasi', slug: 'prestasi' },
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    if (typeof window.URL.createObjectURL === 'undefined') {
      window.URL.createObjectURL = vi.fn(() => 'blob:mock-preview-url');
    } else {
      vi.spyOn(window.URL, 'createObjectURL').mockReturnValue('blob:mock-preview-url');
    }

    if (typeof window.URL.revokeObjectURL === 'undefined') {
      window.URL.revokeObjectURL = vi.fn();
    } else {
      vi.spyOn(window.URL, 'revokeObjectURL').mockImplementation(() => {});
    }

    vi.spyOn(cmsApi, 'getSiteSettings').mockResolvedValue({
      data: { identity: { name: 'SMP Laboratorium UM' } },
    } as any);

    vi.spyOn(cmsApi, 'getPosts').mockResolvedValue({
      data: [mockPostWithCover],
      meta: { page: 1, limit: 10, total: 1, totalPages: 1 },
    } as any);

    vi.spyOn(cmsApi, 'getPost').mockImplementation(async (id) => ({
      data: mockPostWithCover,
    } as any));

    vi.spyOn(cmsApi, 'getPostCategories').mockResolvedValue({
      data: mockCategories,
    } as any);
  });

  const navigateToPostsTab = async () => {
    render(<CmsAdminPage />);
    const postsTabBtn = screen.getByRole('button', { name: /Berita & Artikel/i });
    fireEvent.click(postsTabBtn);
    await waitFor(() => {
      expect(
        screen.getByText('Inovasi Robotik Laboratorium Meraih Prestasi Nasional')
      ).toBeInTheDocument();
    });
  };

  it('1. Komponen FileUpload untuk cover berita tersedia di modal tambah berita', async () => {
    await navigateToPostsTab();

    const createBtn = screen.getByRole('button', { name: /Tulis Berita Baru/i });
    fireEvent.click(createBtn);

    expect(
      screen.getByRole('heading', { name: /Tulis Berita \/ Pengumuman Baru/i })
    ).toBeInTheDocument();

    // Label cover file upload
    expect(screen.getByText(/Unggah Gambar Sampul Berita/i)).toBeInTheDocument();
    expect(screen.getByText(/Maks\. 5 MB/i)).toBeInTheDocument();
  });

  it('2. Mengunggah gambar sampul memanggil storageApi.uploadFileDirect dan mengaitkan cover_file_id saat create post', async () => {
    const directUploadSpy = vi.mocked(storageApi.uploadFileDirect).mockResolvedValueOnce({
      id: 'file-uploaded-uuid-888',
      object_key: 'public/cms/cover-888.jpg',
      original_name: 'cover-robotik.jpg',
      mime_type: 'image/jpeg',
      size_bytes: 102400,
      visibility: 'publik',
      purpose: 'cms_post_cover',
      url: 'https://cdn.example.com/cover-robotik.jpg',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    } as any);

    const createPostSpy = vi.spyOn(cmsApi, 'createPost').mockResolvedValueOnce({
      data: {
        id: 'post-new-with-cover',
        title: 'Berita Bercover Baru',
        slug: 'berita-bercover-baru',
        content: 'Konten berita dengan cover terunggah.',
        cover_file_id: 'file-uploaded-uuid-888',
        status: 'draft',
        visibility: 'publik',
      },
    } as any);

    await navigateToPostsTab();

    const createBtn = screen.getByRole('button', { name: /Tulis Berita Baru/i });
    fireEvent.click(createBtn);

    // Isi judul dan konten
    fireEvent.change(screen.getByLabelText(/Judul Berita \*/i), {
      target: { value: 'Berita Bercover Baru' },
    });
    fireEvent.change(screen.getByLabelText(/Konten Berita \*/i), {
      target: { value: 'Konten berita dengan cover terunggah.' },
    });

    // Cari input file
    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    expect(fileInput).not.toBeNull();

    const mockFile = new File(['dummy content'], 'cover-robotik.jpg', { type: 'image/jpeg' });
    fireEvent.change(fileInput, { target: { files: [mockFile] } });

    await waitFor(() => {
      expect(directUploadSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          file: mockFile,
          visibility: 'publik',
          purpose: 'cms_post_cover',
        })
      );
    });

    // Simpan berita
    const submitBtn = screen.getByRole('button', { name: /Simpan Berita/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(createPostSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Berita Bercover Baru',
          cover_file_id: 'file-uploaded-uuid-888',
        })
      );
    });
  });

  it('3. Form edit menampilkan pratinjau cover yang sudah ada dan mempertahankan cover_file_id saat disimpan tanpa penggantian cover', async () => {
    const updatePostSpy = vi.spyOn(cmsApi, 'updatePost').mockResolvedValueOnce({
      data: mockPostWithCover,
    } as any);

    await navigateToPostsTab();

    // Buka edit post
    const editBtn = screen.getByTitle(/Edit berita/i);
    fireEvent.click(editBtn);

    // Modal Edit terbuka
    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /Edit Berita \/ Pengumuman/i })).toBeInTheDocument();
    });

    // Pratinjau cover yang ada tampil
    const previewImg = screen.getByAltText(/Pratinjau berkas/i);
    expect(previewImg).toBeInTheDocument();
    expect(previewImg.getAttribute('src')).toBe('https://cdn.example.com/robotik-cover.jpg');

    // Simpan tanpa mengganti cover
    const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
    fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(updatePostSpy).toHaveBeenCalledWith(
        'post-cover-1',
        expect.objectContaining({
          cover_file_id: 'file-existing-uuid-111',
        })
      );
    });
  });

  it('4. Mengunggah gambar baru saat edit menggantikan cover_file_id dengan UUID berkas baru', async () => {
    vi.mocked(storageApi.uploadFileDirect).mockResolvedValueOnce({
      id: 'file-new-uuid-999',
      object_key: 'public/cms/cover-new.jpg',
      original_name: 'cover-baru.jpg',
      mime_type: 'image/jpeg',
      size_bytes: 204800,
      visibility: 'publik',
      purpose: 'cms_post_cover',
      url: 'https://cdn.example.com/cover-baru.jpg',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    } as any);

    const updatePostSpy = vi.spyOn(cmsApi, 'updatePost').mockResolvedValueOnce({
      data: {
        ...mockPostWithCover,
        cover_file_id: 'file-new-uuid-999',
      },
    } as any);

    await navigateToPostsTab();

    const editBtn = screen.getByTitle(/Edit berita/i);
    fireEvent.click(editBtn);

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /Edit Berita \/ Pengumuman/i })).toBeInTheDocument();
    });

    // Unggah file baru
    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    const newFile = new File(['new image bytes'], 'cover-baru.jpg', { type: 'image/jpeg' });
    fireEvent.change(fileInput, { target: { files: [newFile] } });

    await waitFor(() => {
      expect(storageApi.uploadFileDirect).toHaveBeenCalledWith(
        expect.objectContaining({
          file: newFile,
          purpose: 'cms_post_cover',
        })
      );
    });

    // Simpan perubahan
    const saveBtn = screen.getByRole('button', { name: /Simpan Perubahan/i });
    fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(updatePostSpy).toHaveBeenCalledWith(
        'post-cover-1',
        expect.objectContaining({
          cover_file_id: 'file-new-uuid-999',
        })
      );
    });
  });

  it('5. Tombol Simpan dinonaktifkan saat upload cover berlangsung untuk mencegah submit premature', async () => {
    let resolveUpload: (val: any) => void = () => {};
    const pendingUploadPromise = new Promise((resolve) => {
      resolveUpload = resolve;
    });

    vi.mocked(storageApi.uploadFileDirect).mockReturnValueOnce(pendingUploadPromise as any);

    await navigateToPostsTab();

    const createBtn = screen.getByRole('button', { name: /Tulis Berita Baru/i });
    fireEvent.click(createBtn);

    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
    const file = new File(['data'], 'slow.png', { type: 'image/png' });
    fireEvent.change(fileInput, { target: { files: [file] } });

    // Saat upload in-flight, tombol Simpan Berita dinonaktifkan dengan label Mengunggah Sampul...
    await waitFor(() => {
      const submitBtn = screen.getByRole('button', { name: /Mengunggah Sampul\.\.\./i });
      expect(submitBtn).toBeDisabled();
      const cancelBtn = screen.getByRole('button', { name: /Batal/i });
      expect(cancelBtn).toBeDisabled();
    });

    // Resolve upload
    resolveUpload({
      id: 'file-slow-uuid',
      url: 'https://cdn.example.com/slow.png',
    });

    // Setelah selesai upload, tombol aktif kembali dengan label Simpan Berita
    await waitFor(() => {
      const submitBtn = screen.getByRole('button', { name: /Simpan Berita/i });
      expect(submitBtn).not.toBeDisabled();
    });
  });

  it('6. Tabel daftar berita admin menampilkan thumbnail sampul dan fallback ikon jika tidak ada cover', async () => {
    await navigateToPostsTab();

    // Thumbnail untuk mockPostWithCover harus memiliki img dengan src sampul
    const thumbnailImg = document.querySelector('img[alt="Sampul Inovasi Robotik Laboratorium Meraih Prestasi Nasional"]') as HTMLImageElement;
    expect(thumbnailImg).not.toBeNull();
    expect(thumbnailImg.getAttribute('src')).toBe('https://cdn.example.com/robotik-cover.jpg');
  });
});
