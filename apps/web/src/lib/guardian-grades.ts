import type { ChildSubjectGrade } from './mock-guardian-data';

/**
 * FE-ORTU-NILAI-01: Pemetaan nilai anak untuk portal ortu.
 *
 * Prinsip kejujuran data (AGENTS.md §6.2 / §9): HANYA menampilkan nilai yang
 * benar-benar dikirim backend. Dilarang mengarang angka (mis. default 85,
 * formatif = skor × 1.02) atau narasi kompetensi.
 *
 * Backend `GET /me/children/{id}/grades` saat ini hanya menyediakan nilai akhir
 * (rata-rata), KKM, dan predikat — TANPA rincian formatif/sumatif/STS nyata.
 * Karena itu field tersebut dikembalikan `null` sampai backend menyediakan
 * rinciannya (tiket backend + aturan nilai sekolah, AGENTS.md §8).
 */

type RawApiGrade = {
  finalScore?: number | null;
  score?: number | null;
  passingGrade?: number | null;
  kkm?: number | null;
  isPassed?: boolean;
  is_passed?: boolean;
  subjectName?: string;
  subject_name?: string;
  subjectCode?: string;
  subject_code?: string;
  subjectId?: string;
  teacherName?: string;
  teacher_name?: string;
  letterGrade?: string;
  letter_grade?: string;
  description?: string;
};

type RawReportCardGrade = {
  subject: string;
  score?: number | null;
  predicate?: string;
  description?: string;
};

type Predicate = ChildSubjectGrade['predicate'];

const DEFAULT_KKM = 75;

function deriveLetter(hasScore: boolean, finalScore: number): Predicate {
  if (!hasScore) return null;
  if (finalScore >= 88) return 'A';
  if (finalScore >= 75) return 'B';
  if (finalScore >= 65) return 'C';
  return 'D';
}

/**
 * Bentuk daftar nilai mapel dari respons API (prioritas) atau e-rapor (fallback).
 * Mengembalikan array kosong bila tidak ada sumber data — bukan data contoh.
 */
export function buildChildSubjectGrades(
  gradesData: unknown,
  reportCardData?: { grades?: RawReportCardGrade[] } | null,
): ChildSubjectGrade[] {
  const rawList: RawApiGrade[] = Array.isArray(gradesData)
    ? (gradesData as RawApiGrade[])
    : Array.isArray((gradesData as { grades?: RawApiGrade[] })?.grades)
      ? ((gradesData as { grades: RawApiGrade[] }).grades)
      : [];

  if (rawList.length > 0) {
    return rawList.map((g) => {
      const rawScore = g.finalScore ?? g.score;
      const finalScore = Number(rawScore);
      const hasScore = rawScore !== null && rawScore !== undefined && Number.isFinite(finalScore);
      const kkm = Number(g.passingGrade ?? g.kkm ?? DEFAULT_KKM);
      const isPassed = g.isPassed ?? g.is_passed ?? (hasScore ? finalScore >= kkm : false);
      const subName = g.subjectName || g.subject_name || 'Mata Pelajaran';
      const subCode = (g.subjectCode || g.subject_code || g.subjectId || 'MAPEL').toString().substring(0, 8);
      const letter = (g.letterGrade || g.letter_grade || deriveLetter(hasScore, finalScore)) as Predicate;

      return {
        subject_code: subCode,
        subject_name: subName,
        // Pass-through apa adanya; FE tidak menambah nama guru buatan.
        teacher_name: g.teacherName || g.teacher_name || '',
        kktp: Number.isFinite(kkm) ? kkm : DEFAULT_KKM,
        // Backend belum menyediakan rincian nyata → null (ditunda ke tiket backend).
        formative_avg: null,
        summative_avg: null,
        sts_score: null,
        final_score: hasScore ? finalScore : null,
        predicate: letter,
        status: isPassed ? 'Tuntas' : 'Perlu Pendampingan',
        // Tidak mengarang narasi; pakai deskripsi backend bila ada.
        competency_desc: g.description || '',
      };
    });
  }

  if (reportCardData?.grades && reportCardData.grades.length > 0) {
    return reportCardData.grades.map((rg, idx) => {
      const hasScore = typeof rg.score === 'number' && Number.isFinite(rg.score);
      return {
        subject_code: `MAPEL-0${idx + 1}`,
        subject_name: rg.subject,
        teacher_name: '',
        kktp: DEFAULT_KKM,
        formative_avg: null,
        summative_avg: null,
        sts_score: null,
        final_score: hasScore ? (rg.score as number) : null,
        predicate: (rg.predicate || null) as Predicate,
        status: (hasScore && (rg.score as number) >= DEFAULT_KKM ? 'Tuntas' : 'Perlu Pendampingan') as ChildSubjectGrade['status'],
        competency_desc: rg.description || '',
      };
    });
  }

  return [];
}
