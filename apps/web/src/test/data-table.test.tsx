import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { DataTable, ColumnDef } from '@/components/shared/data-table';

interface TestItem {
  id: string;
  name: string;
  code: string;
}

const testColumns: ColumnDef<TestItem>[] = [
  { key: 'name', header: 'Nama', sortable: true },
  { key: 'code', header: 'Kode', sortable: false },
];

const testData: TestItem[] = [
  { id: '1', name: 'Item Pertama', code: 'IP-01' },
  { id: '2', name: 'Item Kedua', code: 'IK-02' },
];

describe('DataTable Shared Component', () => {
  it('renders table columns and data rows correctly', () => {
    render(
      <DataTable
        columns={testColumns}
        data={testData}
        keyExtractor={(item) => item.id}
      />
    );

    expect(screen.getByText('Nama')).toBeInTheDocument();
    expect(screen.getByText('Kode')).toBeInTheDocument();
    expect(screen.getByText('Item Pertama')).toBeInTheDocument();
    expect(screen.getByText('IK-02')).toBeInTheDocument();
  });

  it('renders loading skeleton when isLoading is true', () => {
    const { container } = render(
      <DataTable
        columns={testColumns}
        data={[]}
        keyExtractor={(item) => item.id}
        isLoading={true}
      />
    );

    // Should render animate-pulse skeletons
    const skeletons = container.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('renders empty state when data is empty and not loading', () => {
    render(
      <DataTable
        columns={testColumns}
        data={[]}
        keyExtractor={(item) => item.id}
        isLoading={false}
        emptyTitle="Tidak ada rekaman"
      />
    );

    expect(screen.getByText('Tidak ada rekaman')).toBeInTheDocument();
  });

  it('renders error state and triggers onRetry callback', () => {
    const handleRetry = vi.fn();
    render(
      <DataTable
        columns={testColumns}
        data={[]}
        keyExtractor={(item) => item.id}
        error="Gagal memuat data dari server"
        onRetry={handleRetry}
      />
    );

    expect(screen.getByText('Gagal memuat data dari server')).toBeInTheDocument();
    const retryBtn = screen.getByRole('button', { name: /Coba Lagi/i });
    fireEvent.click(retryBtn);
    expect(handleRetry).toHaveBeenCalledTimes(1);
  });

  it('handles search input with debounce', async () => {
    const handleSearch = vi.fn();
    render(
      <DataTable
        columns={testColumns}
        data={testData}
        keyExtractor={(item) => item.id}
        searchable={true}
        searchPlaceholder="Cari item..."
        onSearchChange={handleSearch}
      />
    );

    const input = screen.getByPlaceholderText('Cari item...');
    fireEvent.change(input, { target: { value: 'Pertama' } });

    // Wait for 300ms debounce
    await waitFor(
      () => {
        expect(handleSearch).toHaveBeenCalledWith('Pertama');
      },
      { timeout: 1000 }
    );
  });

  it('triggers onSortChange when clicking sortable header', () => {
    const handleSort = vi.fn();
    render(
      <DataTable
        columns={testColumns}
        data={testData}
        keyExtractor={(item) => item.id}
        sortColumn="name"
        sortDirection="asc"
        onSortChange={handleSort}
      />
    );

    const sortableHeader = screen.getByText('Nama');
    fireEvent.click(sortableHeader);

    // Clicking 'asc' sorted column should flip to 'desc'
    expect(handleSort).toHaveBeenCalledWith('name', 'desc');
  });

  it('handles pagination navigation controls', () => {
    const handlePageChange = vi.fn();
    render(
      <DataTable
        columns={testColumns}
        data={testData}
        keyExtractor={(item) => item.id}
        pagination={{
          page: 2,
          limit: 10,
          total: 30,
          totalPages: 3,
        }}
        onPageChange={handlePageChange}
      />
    );

    expect(screen.getByText(/Menampilkan/i)).toBeInTheDocument();
    expect(screen.getByText('11')).toBeInTheDocument();
    expect(screen.getByText('20')).toBeInTheDocument();
    expect(screen.getByText('30')).toBeInTheDocument();

    const prevPageBtn = screen.getByRole('button', { name: 'Halaman sebelumnya' });
    const nextPageBtn = screen.getByRole('button', { name: 'Halaman selanjutnya' });

    if (prevPageBtn) {
      fireEvent.click(prevPageBtn);
      expect(handlePageChange).toHaveBeenCalledWith(1);
    }

    if (nextPageBtn) {
      fireEvent.click(nextPageBtn);
      expect(handlePageChange).toHaveBeenCalledWith(3);
    }
  });
});
