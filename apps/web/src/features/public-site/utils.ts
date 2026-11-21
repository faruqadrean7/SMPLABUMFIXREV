/**
 * Helper utilitas untuk pemformatan kontak dan data publik
 */

/**
 * Memformat nomor telepon menjadi URI tel: yang valid (E.164-compatible).
 * Contoh: '(0341) 588550' -> '+62341588550'
 */
export function formatPhoneLink(phone?: string | null): string {
  if (!phone) return '';
  const cleaned = phone.replace(/[^0-9+]/g, '');
  if (cleaned.startsWith('0')) {
    return '+62' + cleaned.slice(1);
  }
  if (cleaned.startsWith('62') && !cleaned.startsWith('+')) {
    return '+' + cleaned;
  }
  return cleaned;
}

/**
 * Memformat nomor WhatsApp menjadi ID wa.me yang valid (hanya angka).
 * Contoh: '0851-2608-8473' -> '6285126088473'
 */
export function formatWaLink(wa?: string | null): string {
  if (!wa) return '';
  const cleaned = wa.replace(/[^0-9]/g, '');
  if (cleaned.startsWith('0')) {
    return '62' + cleaned.slice(1);
  }
  return cleaned;
}

/**
 * Memformat surel menjadi URI mailto: yang aman.
 */
export function formatMailtoLink(email?: string | null): string {
  if (!email) return '';
  const trimmed = email.trim();
  if (!trimmed.includes('@')) return '';
  return `mailto:${trimmed}`;
}

/**
 * Mengambil inisial dari nama pimpinan (maks 2 karakter).
 */
export function getInitials(name?: string | null): string {
  if (!name) return 'TS';
  const cleaned = name.replace(/^(Dr\.|Dra\.|Drs\.|Prof\.|Hj\.|H\.|Bpk\.|Ibu|Bapak)\s+/gi, '');
  const words = cleaned.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return 'TS';
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
  return (words[0][0] + words[1][0]).toUpperCase();
}
