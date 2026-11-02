import { describe, it, expect } from 'vitest';
import { buildChildSubjectGrades } from '@/lib/guardian-grades';

/**
 * FE-ORTU-NILAI-01: Pemetaan nilai anak untuk portal ortu HARUS jujur.
 * Tidak boleh mengarang skor, rincian formatif/sumatif/STS, nama guru, atau narasi.
 */
describe('FE-ORTU-NILAI-01: buildChildSubjectGrades (kejujuran data nilai ortu)', () => {
  describe('Tidak mengarang data (AC utama)', () => {
    it('FE-ORTU-NILAI-01: tidak memberi skor default 85 saat backend tak mengirim nilai', () => {
      const result = buildChildSubjectGrades([{ subject_name: 'Matematika' }]);
      expect(result).toHaveLength(1);
      // Dulu kode lama memaksa 85 — sekarang harus null.
      expect(result[0].final_score).toBeNull();
      expect(result[0].final_score).not.toBe(85);
    });

    it('FE-ORTU-NILAI-01: formatif/sumatif/STS selalu null (backend belum sediakan rincian)', () => {
      const result = buildChildSubjectGrades([{ subject_name: 'IPA', finalScore: 90 }]);
      expect(result[0].formative_avg).toBeNull();
      expect(result[0].summative_avg).toBeNull();
      expect(result[0].sts_score).toBeNull();
    });

    it('FE-ORTU-NILAI-01: tidak mengarang nama guru maupun narasi kompetensi', () => {
      const result = buildChildSubjectGrades([{ subject_name: 'IPS', finalScore: 80 }]);
      expect(result[0].teacher_name).toBe('');
      expect(result[0].competency_desc).toBe('');
      expect(result[0].competency_desc).not.toContain('Menunjukkan capaian');
    });

    it('FE-ORTU-NILAI-01: formatif tidak lagi dihitung dari skor × 1.02', () => {
      const result = buildChildSubjectGrades([{ subject_name: 'B. Indonesia', finalScore: 90 }]);
      // Nilai lama yang salah: Math.min(100, 90*1.02)=91.8.
      expect(result[0].formative_avg).not.toBe(91.8);
      expect(result[0].summative_avg).not.toBe(90); // tidak lagi menyalin skor jadi sumatif palsu
    });
  });

  describe('Memakai nilai nyata dari backend', () => {
    it('FE-ORTU-NILAI-01: meneruskan final_score dan nama/kode mapel nyata', () => {
      const result = buildChildSubjectGrades([
        { subject_name: 'Matematika', subject_code: 'MTK', finalScore: 88, teacherName: 'Bu Sari' },
      ]);
      expect(result[0].final_score).toBe(88);
      expect(result[0].subject_name).toBe('Matematika');
      expect(result[0].subject_code).toBe('MTK');
      expect(result[0].teacher_name).toBe('Bu Sari');
    });

    it('FE-ORTU-NILAI-01: memakai letterGrade backend bila tersedia', () => {
      const result = buildChildSubjectGrades([{ subject_name: 'IPA', finalScore: 70, letterGrade: 'C' }]);
      expect(result[0].predicate).toBe('C');
    });

    it('FE-ORTU-NILAI-01: menurunkan predikat dari skor nyata saat letterGrade kosong', () => {
      expect(buildChildSubjectGrades([{ subject_name: 'A', finalScore: 90 }])[0].predicate).toBe('A');
      expect(buildChildSubjectGrades([{ subject_name: 'B', finalScore: 80 }])[0].predicate).toBe('B');
      expect(buildChildSubjectGrades([{ subject_name: 'C', finalScore: 66 }])[0].predicate).toBe('C');
      expect(buildChildSubjectGrades([{ subject_name: 'D', finalScore: 50 }])[0].predicate).toBe('D');
    });

    it('FE-ORTU-NILAI-01: predikat null bila tidak ada skor & tidak ada letterGrade', () => {
      expect(buildChildSubjectGrades([{ subject_name: 'X' }])[0].predicate).toBeNull();
    });

    it('FE-ORTU-NILAI-01: KKM dari passingGrade backend, default 75 bila absen', () => {
      expect(buildChildSubjectGrades([{ subject_name: 'A', finalScore: 80, passingGrade: 70 }])[0].kktp).toBe(70);
      expect(buildChildSubjectGrades([{ subject_name: 'B', finalScore: 80 }])[0].kktp).toBe(75);
    });

    it('FE-ORTU-NILAI-01: status Tuntas/Perlu Pendampingan dari skor nyata vs KKM', () => {
      expect(buildChildSubjectGrades([{ subject_name: 'A', finalScore: 80 }])[0].status).toBe('Tuntas');
      expect(buildChildSubjectGrades([{ subject_name: 'B', finalScore: 60 }])[0].status).toBe('Perlu Pendampingan');
    });

    it('FE-ORTU-NILAI-01: menghormati isPassed eksplisit dari backend', () => {
      expect(buildChildSubjectGrades([{ subject_name: 'A', finalScore: 60, isPassed: true }])[0].status).toBe('Tuntas');
    });
  });

  describe('Bentuk input & fallback', () => {
    it('FE-ORTU-NILAI-01: mendukung payload berbentuk { grades: [...] }', () => {
      const result = buildChildSubjectGrades({ grades: [{ subject_name: 'IPA', finalScore: 77 }] });
      expect(result).toHaveLength(1);
      expect(result[0].final_score).toBe(77);
    });

    it('FE-ORTU-NILAI-01: fallback ke e-rapor saat grades kosong, rincian tetap null', () => {
      const result = buildChildSubjectGrades([], { grades: [{ subject: 'Matematika', score: 85, predicate: 'A' }] });
      expect(result).toHaveLength(1);
      expect(result[0].final_score).toBe(85);
      expect(result[0].predicate).toBe('A');
      expect(result[0].formative_avg).toBeNull();
      expect(result[0].teacher_name).toBe('');
    });

    it('FE-ORTU-NILAI-01: array kosong bila tak ada sumber data (bukan data contoh)', () => {
      expect(buildChildSubjectGrades([])).toEqual([]);
      expect(buildChildSubjectGrades(null)).toEqual([]);
      expect(buildChildSubjectGrades(undefined)).toEqual([]);
    });
  });
});
