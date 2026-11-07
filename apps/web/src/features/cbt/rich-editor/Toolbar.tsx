'use client';

import React from 'react';
import { type Editor } from '@tiptap/react';
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  List,
  ListOrdered,
  Table as TableIcon,
  Image as ImageIcon,
  Rows,
  Columns,
  Trash2,
  Loader2,
} from 'lucide-react';

interface ToolbarProps {
  editor: Editor | null;
  onOpenImagePicker: () => void;
  dense?: boolean;
  disabled?: boolean;
  isUploading?: boolean;
}

export function Toolbar({
  editor,
  onOpenImagePicker,
  dense = false,
  disabled = false,
  isUploading = false,
}: ToolbarProps) {
  if (!editor) {
    return null;
  }

  const isTableActive = editor.isActive('table');
  const btnSize = dense ? 'p-1 h-7 w-7' : 'p-1.5 h-8 w-8';
  const iconSize = dense ? 14 : 16;

  const btnClass = (active: boolean) =>
    `inline-flex items-center justify-center rounded transition-colors ${btnSize} ${
      active
        ? 'bg-blue-100 text-blue-700 font-semibold'
        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
    } disabled:opacity-40 disabled:cursor-not-allowed`;

  return (
    <div className={`flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 ${dense ? 'p-1' : 'p-2'}`}>
      {/* Format Teks */}
      <button
        type="button"
        title="Tebal (Ctrl+B)"
        aria-label="Tebal"
        disabled={disabled}
        onClick={() => editor.chain().focus().toggleBold().run()}
        className={btnClass(editor.isActive('bold'))}
      >
        <Bold size={iconSize} />
      </button>

      <button
        type="button"
        title="Miring (Ctrl+I)"
        aria-label="Miring"
        disabled={disabled}
        onClick={() => editor.chain().focus().toggleItalic().run()}
        className={btnClass(editor.isActive('italic'))}
      >
        <Italic size={iconSize} />
      </button>

      <button
        type="button"
        title="Garis Bawah (Ctrl+U)"
        aria-label="Garis Bawah"
        disabled={disabled}
        onClick={() => editor.chain().focus().toggleUnderline().run()}
        className={btnClass(editor.isActive('underline'))}
      >
        <UnderlineIcon size={iconSize} />
      </button>

      <div className="h-4 w-px bg-slate-300 mx-1" />

      {/* List */}
      <button
        type="button"
        title="Daftar Poin"
        aria-label="Daftar Poin"
        disabled={disabled}
        onClick={() => editor.chain().focus().toggleBulletList().run()}
        className={btnClass(editor.isActive('bulletList'))}
      >
        <List size={iconSize} />
      </button>

      <button
        type="button"
        title="Daftar Nomor"
        aria-label="Daftar Nomor"
        disabled={disabled}
        onClick={() => editor.chain().focus().toggleOrderedList().run()}
        className={btnClass(editor.isActive('orderedList'))}
      >
        <ListOrdered size={iconSize} />
      </button>

      <div className="h-4 w-px bg-slate-300 mx-1" />

      {/* Tabel */}
      <button
        type="button"
        title="Sisipkan Tabel (3x3)"
        aria-label="Sisipkan Tabel"
        disabled={disabled}
        onClick={() =>
          editor
            .chain()
            .focus()
            .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
            .run()
        }
        className={btnClass(isTableActive)}
      >
        <TableIcon size={iconSize} />
      </button>

      {/* Kontrol Tambahan saat di dalam Tabel */}
      {isTableActive && (
        <div className="flex items-center gap-1 bg-white px-1.5 py-0.5 rounded border border-slate-200">
          <button
            type="button"
            title="Tambah Baris Bawah"
            aria-label="Tambah Baris"
            disabled={disabled}
            onClick={() => editor.chain().focus().addRowAfter().run()}
            className={btnClass(false)}
          >
            <Rows size={iconSize} />
          </button>
          <button
            type="button"
            title="Tambah Kolom Kanan"
            aria-label="Tambah Kolom"
            disabled={disabled}
            onClick={() => editor.chain().focus().addColumnAfter().run()}
            className={btnClass(false)}
          >
            <Columns size={iconSize} />
          </button>
          <button
            type="button"
            title="Hapus Tabel"
            aria-label="Hapus Tabel"
            disabled={disabled}
            onClick={() => editor.chain().focus().deleteTable().run()}
            className="p-1 text-red-500 hover:bg-red-50 hover:text-red-700 rounded transition-colors"
          >
            <Trash2 size={iconSize} />
          </button>
        </div>
      )}

      <div className="h-4 w-px bg-slate-300 mx-1" />

      {/* Gambar */}
      <button
        type="button"
        title="Sisipkan Gambar"
        aria-label="Sisipkan Gambar"
        disabled={disabled || isUploading}
        onClick={onOpenImagePicker}
        className={btnClass(false)}
      >
        {isUploading ? (
          <Loader2 size={iconSize} className="animate-spin text-blue-600" />
        ) : (
          <ImageIcon size={iconSize} />
        )}
      </button>

      {isUploading && (
        <span className="text-xs text-blue-600 flex items-center gap-1 font-medium ml-1">
          <Loader2 size={12} className="animate-spin" /> Mengunggah gambar...
        </span>
      )}
    </div>
  );
}
