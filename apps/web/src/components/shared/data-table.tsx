'use client';

import React, { useState, useEffect, useMemo } from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { EmptyState } from '@/components/shared/empty-state';
import { ErrorState } from '@/components/shared/error-state';
import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  Search,
  X,
  RefreshCw,
} from 'lucide-react';

export interface ColumnDef<T> {
  key: string;
  header: string;
  render?: (item: T, index: number) => React.ReactNode;
  sortable?: boolean;
  className?: string;
  align?: 'left' | 'center' | 'right';
}

export interface DataTablePagination {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface DataTableProps<T> {
  columns: ColumnDef<T>[];
  data: T[];
  keyExtractor: (item: T) => string;
  isLoading?: boolean;
  error?: string | null;
  onRetry?: () => void;
  // Fitur Pencarian
  searchable?: boolean;
  searchPlaceholder?: string;
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  // Fitur Sorting
  sortColumn?: string;
  sortDirection?: 'asc' | 'desc';
  onSortChange?: (column: string, direction: 'asc' | 'desc') => void;
  // Fitur Paginasi
  pagination?: DataTablePagination;
  onPageChange?: (page: number) => void;
  // Aksi Kustom Topbar
  actions?: React.ReactNode;
  // Empty State Kustom
  emptyTitle?: string;
  emptyDescription?: string;
  emptyActionLabel?: string;
  onEmptyAction?: () => void;
}

export function DataTable<T>({
  columns,
  data,
  keyExtractor,
  isLoading = false,
  error = null,
  onRetry,
  searchable = true,
  searchPlaceholder = 'Cari data...',
  searchValue,
  onSearchChange,
  sortColumn,
  sortDirection,
  onSortChange,
  pagination,
  onPageChange,
  actions,
  emptyTitle = 'Belum Ada Data',
  emptyDescription = 'Tidak ada catatan data yang ditemukan saat ini.',
  emptyActionLabel,
  onEmptyAction,
}: DataTableProps<T>) {
  // Local state pencarian dengan debounce jika onSearchChange disediakan
  const [internalSearch, setInternalSearch] = useState(searchValue || '');

  useEffect(() => {
    if (searchValue !== undefined) {
      setInternalSearch(searchValue);
    }
  }, [searchValue]);

  // Debounced search trigger (300ms)
  useEffect(() => {
    if (!onSearchChange) return;

    const timer = setTimeout(() => {
      if (internalSearch !== searchValue) {
        onSearchChange(internalSearch);
      }
    }, 300);

    return () => clearTimeout(timer);
  }, [internalSearch, onSearchChange, searchValue]);

  const handleSort = (colKey: string, sortable?: boolean) => {
    if (!sortable || !onSortChange) return;

    if (sortColumn === colKey) {
      const nextDir = sortDirection === 'asc' ? 'desc' : 'asc';
      onSortChange(colKey, nextDir);
    } else {
      onSortChange(colKey, 'asc');
    }
  };

  return (
    <div className="w-full space-y-4">
      {/* Header Tabel: Pencarian & Tombol Aksi */}
      {(searchable || actions) && (
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          {searchable ? (
            <div className="relative w-full sm:w-72">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <Search className="h-4 w-4" />
              </div>
              <Input
                type="text"
                placeholder={searchPlaceholder}
                value={internalSearch}
                onChange={(e) => setInternalSearch(e.target.value)}
                className="pl-9 pr-8 h-9 text-xs"
                aria-label="Pencarian tabel"
              />
              {internalSearch && (
                <button
                  type="button"
                  onClick={() => {
                    setInternalSearch('');
                    onSearchChange?.('');
                  }}
                  className="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                  aria-label="Hapus pencarian"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
          ) : (
            <div />
          )}

          {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
      )}

      {/* Kontainer Tabel */}
      <div className="rounded-lg border border-slate-200 bg-white overflow-hidden shadow-sm">
        {error ? (
          <div className="p-6">
            <ErrorState
              title="Gagal Memuat Data Tabel"
              message={error}
              onRetry={onRetry}
            />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-700" role="table">
              <thead className="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                <tr>
                  {columns.map((col) => {
                    const isSorted = sortColumn === col.key;
                    return (
                      <th
                        key={col.key}
                        scope="col"
                        onClick={() => handleSort(col.key, col.sortable)}
                        className={cn(
                          'px-4 py-3 select-none',
                          col.sortable && 'cursor-pointer hover:bg-slate-100/80 transition-colors',
                          col.align === 'center' && 'text-center',
                          col.align === 'right' && 'text-right',
                          col.className
                        )}
                        aria-sort={
                          isSorted
                            ? sortDirection === 'asc'
                              ? 'ascending'
                              : 'descending'
                            : undefined
                        }
                      >
                        <div
                          className={cn(
                            'inline-flex items-center gap-1.5',
                            col.align === 'center' && 'justify-center',
                            col.align === 'right' && 'justify-end'
                          )}
                        >
                          <span>{col.header}</span>
                          {col.sortable && (
                            <span className="text-slate-400">
                              {isSorted ? (
                                sortDirection === 'asc' ? (
                                  <ArrowUp className="h-3.5 w-3.5 text-brand-blue" />
                                ) : (
                                  <ArrowDown className="h-3.5 w-3.5 text-brand-blue" />
                                )
                              ) : (
                                <ArrowUpDown className="h-3 w-3" />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                    );
                  })}
                </tr>
              </thead>

              <tbody className="divide-y divide-slate-100">
                {isLoading ? (
                  // Skeleton Loading Rows
                  Array.from({ length: 5 }).map((_, rIdx) => (
                    <tr key={`loading-${rIdx}`} className="animate-pulse">
                      {columns.map((col, cIdx) => (
                        <td key={`loading-${rIdx}-${cIdx}`} className="px-4 py-3.5">
                          <div className="h-4 bg-slate-200 rounded w-3/4" />
                        </td>
                      ))}
                    </tr>
                  ))
                ) : data.length === 0 ? (
                  <tr>
                    <td colSpan={columns.length} className="p-8 text-center">
                      <EmptyState
                        title={emptyTitle}
                        description={emptyDescription}
                        actionLabel={emptyActionLabel}
                        onAction={onEmptyAction}
                      />
                    </td>
                  </tr>
                ) : (
                  data.map((item, index) => (
                    <tr
                      key={keyExtractor(item)}
                      className="hover:bg-slate-50/70 transition-colors"
                    >
                      {columns.map((col) => {
                        const cellContent = col.render
                          ? col.render(item, index)
                          : (item as Record<string, unknown>)[col.key];

                        return (
                          <td
                            key={`${keyExtractor(item)}-${col.key}`}
                            className={cn(
                              'px-4 py-3',
                              col.align === 'center' && 'text-center',
                              col.align === 'right' && 'text-right',
                              col.className
                            )}
                          >
                            {cellContent as React.ReactNode}
                          </td>
                        );
                      })}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* Footer Paginasi Server-Side */}
        {pagination && pagination.totalPages > 0 && !error && (
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
            <div>
              Menampilkan{' '}
              <span className="font-semibold text-slate-800">
                {Math.min(
                  (pagination.page - 1) * pagination.limit + 1,
                  pagination.total
                )}
              </span>{' '}
              sampai{' '}
              <span className="font-semibold text-slate-800">
                {Math.min(pagination.page * pagination.limit, pagination.total)}
              </span>{' '}
              dari{' '}
              <span className="font-semibold text-slate-800">
                {pagination.total}
              </span>{' '}
              data
            </div>

            <div className="flex items-center gap-1.5" aria-label="Navigasi Halaman">
              <Button
                variant="outline"
                size="sm"
                onClick={() => onPageChange?.(1)}
                disabled={pagination.page <= 1 || isLoading}
                className="h-7 w-7 p-0"
                aria-label="Halaman pertama"
              >
                <ChevronsLeft className="h-3.5 w-3.5" />
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => onPageChange?.(pagination.page - 1)}
                disabled={pagination.page <= 1 || isLoading}
                className="h-7 w-7 p-0"
                aria-label="Halaman sebelumnya"
              >
                <ChevronLeft className="h-3.5 w-3.5" />
              </Button>

              <span className="px-2 text-xs font-medium text-slate-700">
                Halaman {pagination.page} dari {pagination.totalPages}
              </span>

              <Button
                variant="outline"
                size="sm"
                onClick={() => onPageChange?.(pagination.page + 1)}
                disabled={pagination.page >= pagination.totalPages || isLoading}
                className="h-7 w-7 p-0"
                aria-label="Halaman selanjutnya"
              >
                <ChevronRight className="h-3.5 w-3.5" />
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => onPageChange?.(pagination.totalPages)}
                disabled={pagination.page >= pagination.totalPages || isLoading}
                className="h-7 w-7 p-0"
                aria-label="Halaman terakhir"
              >
                <ChevronsRight className="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
