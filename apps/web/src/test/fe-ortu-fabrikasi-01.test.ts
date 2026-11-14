import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

/**
 * FE-ORTU-FABRIKASI-01: Hentikan data palsu jadwal, poin, dan wali kelas di portal ortu.
 * Memastikan AGENTS.md §6.2 ("jangan tulis data olahan seolah nyata") dan §9 dipatuhi penuh.
 */
describe('FE-ORTU-FABRIKASI-01: Verifikasi Ketiadaan Data Palsu di Portal Ortu', () => {
  const contextFilePath = path.resolve(__dirname, '../lib/guardian-context.tsx');
  const contextContent = fs.readFileSync(contextFilePath, 'utf-8');

  it('AC1: Tidak ada lagi jadwal pelajaran, guru, ruang, atau topik hardcoded di activeSchedule', () => {
    expect(contextContent).not.toContain('Ahmad Yani, M.Pd.');
    expect(contextContent).not.toContain('Fitri Handayani, S.Pd.');
    expect(contextContent).not.toContain('Dr. Hendra Wijaya');
    expect(contextContent).not.toContain('Budi Santoso, S.Kom., M.T.');
    expect(contextContent).not.toContain('Siti Aminah, M.Pd.');
    expect(contextContent).not.toContain('Persamaan Kuadrat & Fungsi Polinomial');
    expect(contextContent).not.toContain('Analytical Exposition & Argumentative Essay');
    expect(contextContent).not.toContain('Bioteknologi Konvensional & Modern');
  });

  it('AC2: Tidak ada angka poin atau entri poin fiktif yang dikarang (pt-01, pt-02, 25/20 poin)', () => {
    expect(contextContent).not.toContain('pt-01');
    expect(contextContent).not.toContain('pt-02');
    expect(contextContent).not.toContain('Apresiasi Karakter Kemandirian & Gotong Royong');
    expect(contextContent).not.toContain('Ketertiban Kehadiran & Partisipasi KBM');
  });

  it('AC3: Tidak ada NIP & HP wali kelas fiktif di mana pun di guardian-context.tsx', () => {
    expect(contextContent).not.toContain('19840214 200902 2 003');
    expect(contextContent).not.toContain('0812-3456-7890');
    expect(contextContent).not.toContain('Nur Hayati, S.Pd.');
  });

  it('AC4: Grep bersih dari seluruh identitas/profil fiktif (Adrean Pratama, 87.4, dsb)', () => {
    const forbiddenPatterns = [
      'Ahmad Yani',
      'Fitri Handayani',
      'Hendra Wijaya',
      'Budi Santoso',
      'Siti Aminah',
      '19840214 200902 2 003',
      '0812-3456-7890',
      'Nur Hayati',
      'Adrean Pratama',
      '87.4',
    ];

    for (const pattern of forbiddenPatterns) {
      expect(contextContent.includes(pattern)).toBe(false);
    }
  });

  it('AC5: Default fallback nilai dan poin pada DEFAULT_CHILD adalah 0 dan kosong (jujur)', () => {
    // Memastikan fallback tidak menyuntikkan poin atau nilai rekaan
    expect(contextContent).toContain('grade_average: 0');
    expect(contextContent).toContain('points_balance: 0');
    expect(contextContent).toContain('points_prestasi: 0');
    expect(contextContent).toContain('points_pelanggaran: 0');
  });
});
