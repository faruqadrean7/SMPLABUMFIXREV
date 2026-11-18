export type AssignmentStatusType =
  | 'locked'
  | 'needs_grading'
  | 'all_graded'
  | 'past_due_empty'
  | 'active';

export interface AssignmentStatusInfo {
  type: AssignmentStatusType;
  label: string;
  unreviewedCount: number;
}

// FIX-GURU-04: Helper untuk menghitung default tenggat selalu di masa depan (+7 hari).
export function getDefaultDueDate(daysAhead = 7, baseDate: Date = new Date()): string {
  const d = new Date(baseDate);
  d.setDate(d.getDate() + daysAhead);
  d.setHours(23, 59, 0, 0);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// FIX-GURU-04: Derivasi status tugas yang tepat
// "Selesai Dinilai" HANYA jika ada pengumpulan (collected > 0) dan semua telah dinilai (unreviewed === 0).
export function getAssignmentStatus(
  assignment: {
    is_locked?: boolean;
    due_at: string;
    submissions_count?: number;
    reviewed_count?: number;
  },
  now: number = Date.now()
): AssignmentStatusInfo {
  if (assignment.is_locked) {
    return { type: 'locked', label: 'Terkunci', unreviewedCount: 0 };
  }

  const collected = assignment.submissions_count || 0;
  const reviewed = assignment.reviewed_count || 0;
  const unreviewed = Math.max(0, collected - reviewed);
  const isPastDue = new Date(assignment.due_at).getTime() < now;

  if (unreviewed > 0) {
    return {
      type: 'needs_grading',
      label: `${unreviewed} Perlu Dinilai`,
      unreviewedCount: unreviewed,
    };
  }

  if (collected > 0) {
    return {
      type: 'all_graded',
      label: 'Selesai Dinilai',
      unreviewedCount: 0,
    };
  }

  if (isPastDue) {
    return {
      type: 'past_due_empty',
      label: 'Berakhir (Tanpa Pengumpulan)',
      unreviewedCount: 0,
    };
  }

  return {
    type: 'active',
    label: 'Sedang Berjalan',
    unreviewedCount: 0,
  };
}
