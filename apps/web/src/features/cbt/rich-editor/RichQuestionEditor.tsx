'use client';

import React, { useEffect, useRef } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';
import TipTapImage from '@tiptap/extension-image';
import { Toolbar } from './Toolbar';
import { useImageUpload } from './use-image-upload';

const CustomImage = TipTapImage.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      'data-file-id': {
        default: null,
        parseHTML: (element) => element.getAttribute('data-file-id'),
        renderHTML: (attributes) => {
          if (!attributes['data-file-id']) {
            return {};
          }
          return {
            'data-file-id': attributes['data-file-id'],
          };
        },
      },
    };
  },
});

export interface RichQuestionEditorProps {
  value: string;
  onChange: (html: string) => void;
  bankId: string;
  placeholder?: string;
  dense?: boolean;
  disabled?: boolean;
  className?: string;
}

export function RichQuestionEditor({
  value,
  onChange,
  bankId,
  placeholder = 'Tulis konten soal...',
  dense = false,
  disabled = false,
  className = '',
}: RichQuestionEditorProps) {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { uploadAndGetNode, isUploading, error: uploadError } = useImageUpload(bankId);

  const uploadHandlerRef = useRef<(file: File) => Promise<void>>();

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: false,
      }),
      Underline,
      Table.configure({
        resizable: false,
      }),
      TableRow,
      TableHeader,
      TableCell,
      CustomImage.configure({
        inline: true,
        allowBase64: false,
      }),
    ],
    content: value || '',
    editable: !disabled,
    editorProps: {
      attributes: {
        class: `prose-editor focus:outline-none ${
          dense ? 'min-h-[50px] p-2 text-sm' : 'min-h-[120px] p-3 text-base'
        }`,
      },
      handleDrop: (view, event, slice, moved) => {
        if (
          !moved &&
          event.dataTransfer &&
          event.dataTransfer.files &&
          event.dataTransfer.files.length > 0
        ) {
          const file = event.dataTransfer.files[0];
          if (file.type.startsWith('image/')) {
            event.preventDefault();
            uploadHandlerRef.current?.(file);
            return true;
          }
        }
        return false;
      },
      handlePaste: (view, event, slice) => {
        if (
          event.clipboardData &&
          event.clipboardData.files &&
          event.clipboardData.files.length > 0
        ) {
          const file = event.clipboardData.files[0];
          if (file.type.startsWith('image/')) {
            event.preventDefault();
            uploadHandlerRef.current?.(file);
            return true;
          }
        }
        return false;
      },
    },
    onUpdate: ({ editor }) => {
      const html = editor.getHTML();
      onChange(html);
    },
  });

  const handleUploadFile = async (file: File) => {
    try {
      const node = await uploadAndGetNode(file);
      if (editor) {
        editor
          .chain()
          .focus()
          .setImage({
            src: node.src,
            alt: file.name,
            'data-file-id': node.fileId,
          } as any)
          .run();
      }
    } catch {
      // Error is set in hook error state
    }
  };

  uploadHandlerRef.current = handleUploadFile;

  // Sinkronisasi value dari luar bila berubah
  useEffect(() => {
    if (!editor) return;
    const currentHtml = editor.getHTML();
    if (value === currentHtml) return;
    if (!value && editor.isEmpty) return;
    editor.commands.setContent(value || '', false);
  }, [editor, value]);

  // Sinkronisasi status disabled/editable
  useEffect(() => {
    if (editor) {
      editor.setEditable(!disabled);
    }
  }, [editor, disabled]);

  return (
    <div
      className={`border border-slate-300 rounded-md overflow-hidden bg-white focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 transition-all ${className}`}
    >
      <Toolbar
        editor={editor}
        onOpenImagePicker={() => fileInputRef.current?.click()}
        dense={dense}
        disabled={disabled}
        isUploading={isUploading}
      />

      <input
        ref={fileInputRef}
        type="file"
        accept="image/png,image/jpeg,image/webp,image/gif"
        className="hidden"
        data-testid="rich-editor-file-input"
        onChange={async (e) => {
          const file = e.target.files?.[0];
          if (file) {
            await handleUploadFile(file);
            e.target.value = '';
          }
        }}
      />

      {uploadError && (
        <div className="bg-red-50 text-red-600 text-xs px-3 py-1.5 border-b border-red-200">
          {uploadError}
        </div>
      )}

      <div className="tiptap-editor-body">
        <EditorContent editor={editor} />
      </div>
    </div>
  );
}
