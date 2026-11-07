import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import React from 'react';
import { RichQuestionEditor } from '../features/cbt/rich-editor/RichQuestionEditor';
import { cbtApi } from '../features/cbt/cbt-api';

vi.mock('../features/cbt/cbt-api', () => ({
  cbtApi: {
    uploadQuestionMedia: vi.fn(),
  },
}));

describe('FE-CBT-11: RichQuestionEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fe-cbt-11 editor serializes bold+table to html', async () => {
    const initialHtml = '<p><strong>Teks Tebal</strong></p><table><tbody><tr><th>Header</th></tr><tr><td>Data</td></tr></tbody></table>';
    const handleChange = vi.fn();

    render(
      <RichQuestionEditor
        value={initialHtml}
        onChange={handleChange}
        bankId="bank-123"
        placeholder="Tulis soal..."
      />
    );

    // Assert that table elements exist in the editor
    expect(screen.getByRole('table')).toBeInTheDocument();
    expect(screen.getByText('Teks Tebal')).toBeInTheDocument();
    expect(screen.getByText('Header')).toBeInTheDocument();
    expect(screen.getByText('Data')).toBeInTheDocument();
  });

  it('fe-cbt-11 editor inserts image node with data-file-id', async () => {
    const handleChange = vi.fn();
    (cbtApi.uploadQuestionMedia as any).mockResolvedValueOnce({
      fileId: 'img-uuid-xyz',
      mimeType: 'image/png',
      previewUrl: 'https://example.com/preview.png',
    });

    render(
      <RichQuestionEditor
        value="<p>Soal awal</p>"
        onChange={handleChange}
        bankId="bank-123"
      />
    );

    const fileInput = screen.getByTestId('rich-editor-file-input');
    const dummyFile = new File(['fake-png-content'], 'diagram.png', { type: 'image/png' });

    fireEvent.change(fileInput, { target: { files: [dummyFile] } });

    await waitFor(() => {
      expect(cbtApi.uploadQuestionMedia).toHaveBeenCalledWith('bank-123', dummyFile);
    });

    await waitFor(() => {
      expect(handleChange).toHaveBeenCalled();
      const lastCallArg = handleChange.mock.calls[handleChange.mock.calls.length - 1][0];
      expect(lastCallArg).toContain('data-file-id="img-uuid-xyz"');
    });
  });

  it('fe-cbt-11 editor shows loading state while uploading', async () => {
    let resolveUpload: any;
    const uploadPromise = new Promise((resolve) => {
      resolveUpload = resolve;
    });
    (cbtApi.uploadQuestionMedia as any).mockReturnValueOnce(uploadPromise);

    render(
      <RichQuestionEditor
        value="<p>Menunggu gambar</p>"
        onChange={vi.fn()}
        bankId="bank-123"
      />
    );

    const fileInput = screen.getByTestId('rich-editor-file-input');
    const dummyFile = new File(['fake-png-content'], 'photo.png', { type: 'image/png' });

    fireEvent.change(fileInput, { target: { files: [dummyFile] } });

    // Assert loading indicator is visible
    expect(await screen.findByText(/mengunggah gambar/i)).toBeInTheDocument();

    // Resolve upload
    resolveUpload({
      fileId: 'img-123',
      mimeType: 'image/png',
      previewUrl: 'https://example.com/p.png',
    });

    await waitFor(() => {
      expect(screen.queryByText(/mengunggah gambar/i)).not.toBeInTheDocument();
    });
  });
});
