import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, act } from '@testing-library/react';
import fs from 'fs';
import path from 'path';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { toast, Toaster, dismissToast } from '@/components/ui/toast';

describe('FE-UX-01: Frontend UX Alert and Confirm Replacements', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('FE-UX-01-AC1: verifies zero native alert/confirm/prompt calls exist across web production source files', () => {
    const webSrcDir = path.resolve(__dirname, '..');
    const targetDirs = ['app', 'components', 'features', 'lib', 'providers'];

    function getAllFiles(dirPath: string, arrayOfFiles: string[] = []): string[] {
      if (!fs.existsSync(dirPath)) return arrayOfFiles;
      const files = fs.readdirSync(dirPath);

      files.forEach((file) => {
        const fullPath = path.join(dirPath, file);
        if (fs.statSync(fullPath).isDirectory()) {
          getAllFiles(fullPath, arrayOfFiles);
        } else if (file.endsWith('.ts') || file.endsWith('.tsx')) {
          arrayOfFiles.push(fullPath);
        }
      });

      return arrayOfFiles;
    }

    const allSourceFiles: string[] = [];
    for (const subDir of targetDirs) {
      getAllFiles(path.join(webSrcDir, subDir), allSourceFiles);
    }

    expect(allSourceFiles.length).toBeGreaterThan(20);

    const violations: { file: string; line: number; text: string }[] = [];

    // Regex to match native window alert, confirm, prompt or unadorned alert() / confirm()
    // Negative lookbehind ensures we do not flag object methods like storageApi.confirm(...)
    const nativeDialogPattern = /(window\.(?:alert|confirm|prompt)\s*\(|(?<!\.)\b(?:alert|confirm)\s*\()/g;

    allSourceFiles.forEach((file) => {
      const content = fs.readFileSync(file, 'utf-8');
      const lines = content.split('\n');

      lines.forEach((line, idx) => {
        // Strip single line comments
        const cleanLine = line.replace(/\/\/.*$/, '');
        // Exclude legitimate type/prop references or variable declarations
        if (
          cleanLine.includes('storageApi.confirm') ||
          cleanLine.includes('export interface ConfirmDialogProps') ||
          cleanLine.includes('ConfirmDialogProps')
        ) {
          return;
        }

        if (nativeDialogPattern.test(cleanLine)) {
          violations.push({
            file: path.relative(webSrcDir, file),
            line: idx + 1,
            text: line.trim(),
          });
        }
      });
    });

    expect(violations).toEqual([]);
  });

  it('FE-UX-01-AC2: ConfirmDialog displays title, description, affected item, and responds to action callbacks', () => {
    const onConfirmMock = vi.fn();
    const onCloseMock = vi.fn();

    const { rerender } = render(
      <ConfirmDialog
        isOpen={true}
        title="Hapus Materi"
        description="Apakah Anda yakin ingin menghapus materi ini?"
        affectedItemName="Modul Tajwid Lengkap"
        confirmLabel="Hapus"
        cancelLabel="Batal"
        variant="danger"
        onConfirm={onConfirmMock}
        onClose={onCloseMock}
      />
    );

    // Title and Description rendered
    expect(screen.getByText('Hapus Materi')).toBeInTheDocument();
    expect(screen.getByText('Apakah Anda yakin ingin menghapus materi ini?')).toBeInTheDocument();
    expect(screen.getByText('Modul Tajwid Lengkap')).toBeInTheDocument();

    // Confirm button click
    const confirmButton = screen.getByRole('button', { name: /Hapus/i });
    fireEvent.click(confirmButton);
    expect(onConfirmMock).toHaveBeenCalledTimes(1);

    // Cancel button click
    const cancelButton = screen.getByRole('button', { name: /Batal/i });
    fireEvent.click(cancelButton);
    expect(onCloseMock).toHaveBeenCalledTimes(1);

    // Test Escape key trigger
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(onCloseMock).toHaveBeenCalledTimes(2);

    // When closed, should render nothing
    rerender(
      <ConfirmDialog
        isOpen={false}
        title="Hapus Materi"
        description="Apakah Anda yakin ingin menghapus materi ini?"
        onConfirm={onConfirmMock}
        onClose={onCloseMock}
      />
    );
    expect(screen.queryByText('Hapus Materi')).not.toBeInTheDocument();
  });

  it('FE-UX-01-AC3: Toast and Toaster system dispatches notification items with title and descriptions', () => {
    render(<Toaster />);

    act(() => {
      toast.info('Silakan hubungi proktor lab untuk bantuan verifikasi.', 'Bantuan Lab CBT');
      toast.error('Gagal memuat kuitansi resmi.', 'Gagal Memuat Kuitansi');
      toast.success('Materi pembelajaran berhasil dihapus.', 'Berhasil');
    });

    expect(screen.getByText('Bantuan Lab CBT')).toBeInTheDocument();
    expect(screen.getByText('Silakan hubungi proktor lab untuk bantuan verifikasi.')).toBeInTheDocument();
    expect(screen.getByText('Gagal Memuat Kuitansi')).toBeInTheDocument();
    expect(screen.getByText('Gagal memuat kuitansi resmi.')).toBeInTheDocument();
    expect(screen.getByText('Materi pembelajaran berhasil dihapus.')).toBeInTheDocument();
  });

  it('FE-UX-01-AC4: Validates the 6 target pages utilize ConfirmDialog and toast appropriately', () => {
    const webSrcDir = path.resolve(__dirname, '..');

    const targetFiles = [
      {
        relPath: 'app/(portal)/portal/siswa/cbt/page.tsx',
        expectedImports: ['toast'],
        forbiddenCalls: ['alert(', 'confirm('],
      },
      {
        relPath: 'app/(portal)/portal/siswa/tugas/page.tsx',
        expectedImports: ['toast'],
        forbiddenCalls: ['alert(', 'confirm('],
      },
      {
        relPath: 'app/(portal)/portal/siswa/tagihan/page.tsx',
        expectedImports: ['toast'],
        forbiddenCalls: ['alert(', 'confirm('],
      },
      {
        relPath: 'app/(portal)/portal/guru/program-khusus/page.tsx',
        expectedImports: ['ConfirmDialog', 'toast'],
        forbiddenCalls: ['alert(', 'confirm('],
      },
      {
        relPath: 'app/(portal)/portal/siswa/ujian/[id]/page.tsx',
        expectedImports: ['ConfirmDialog', 'toast'],
        forbiddenCalls: ['alert(', 'confirm('],
      },
      {
        relPath: 'app/(ppdb)/ppdb/dashboard/page.tsx',
        expectedImports: ['ConfirmDialog'],
        forbiddenCalls: ['alert(', 'confirm('],
      },
    ];

    for (const item of targetFiles) {
      const fullPath = path.join(webSrcDir, item.relPath);
      expect(fs.existsSync(fullPath)).toBe(true);
      const content = fs.readFileSync(fullPath, 'utf-8');

      // Verify required imports
      for (const expectedImport of item.expectedImports) {
        expect(content).toContain(expectedImport);
      }

      // Verify no forbidden unadorned calls
      const lines = content.split('\n');
      for (const line of lines) {
        const cleanLine = line.replace(/\/\/.*$/, '');
        for (const forbidden of item.forbiddenCalls) {
          const matchRegex = new RegExp(`(?<!\\.)\\b${forbidden.replace('(', '\\(')}`);
          expect(matchRegex.test(cleanLine)).toBe(false);
        }
      }
    }
  });
});
