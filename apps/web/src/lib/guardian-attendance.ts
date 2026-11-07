import type { AttendanceCalendarDay } from './mock-guardian-data';

/**
 * FE-ORTU-PRESENSI-01: Pemetaan presensi anak untuk portal ortu.
 *
 * Prinsip kejujuran data (AGENTS.md §6.2 / §9): HANYA menampilkan presensi yang
 * benar-benar dikirim backend. Dilarang mengarang rekap (mis. default 25 hari
 * hadir, 96% kehadiran) atau jam tap gerbang (mis. '06:48 WIB').
 *
 * Backend `GET /me/children/{id}/attendance` menyediakan:
 *  - summary: present_days/sick_days/leave_days/unexcused_days/percentage
 *  - history: { date, status, check_in_time, notes }
 * Backend TIDAK menyediakan jam pulang (check_out) maupun lokasi gerbang.
 */

export interface AttendanceRecap {
  hadir: number;
  sakit: number;
  izin: number;
  alpa: number;
  terlambat: number;
  rate: number;
}

const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/** Format jam tap gerbang 'HH:mm:ss' → 'HH:mm WIB'; undefined bila tak ada data. */
export function formatGateTapTime(raw?: string | null): string | undefined {
  if (!raw) return undefined;
  return `${raw.toString().slice(0, 5)} WIB`;
}

/** Rekap presensi dari summary backend; semua 0 bila summary tak tersedia (bukan angka karangan). */
export function buildAttendanceRecap(summary?: Record<string, unknown> | null): AttendanceRecap {
  const num = (...keys: string[]): number => {
    for (const k of keys) {
      const v = summary?.[k];
      if (typeof v === 'number' && Number.isFinite(v)) return v;
    }
    return 0;
  };
  return {
    hadir: num('present_days', 'presentDays', 'hadir'),
    sakit: num('sick_days', 'sickDays', 'sakit'),
    izin: num('leave_days', 'leaveDays', 'izin'),
    alpa: num('unexcused_days', 'unexcusedDays', 'alpa'),
    // Backend belum membedakan status 'terlambat' pada rekap → 0.
    terlambat: num('terlambat'),
    rate: num('percentage', 'attendance_percentage', 'persentase_kehadiran'),
  };
}

type RawHistoryItem = {
  date?: string;
  status?: string;
  check_in_time?: string | null;
  checkInTime?: string | null;
  check_out_time?: string | null;
  checkOutTime?: string | null;
  notes?: string | null;
  note?: string | null;
};

/** Riwayat presensi backend → kalender; array kosong bila tak ada data (bukan contoh). */
export function buildAttendanceCalendar(attendanceData?: unknown): AttendanceCalendarDay[] {
  const source = attendanceData as { history?: RawHistoryItem[]; records?: RawHistoryItem[] } | undefined;
  const list = source?.history || source?.records || [];
  if (!Array.isArray(list) || list.length === 0) return [];

  return list.map((r, i) => {
    const dateObj = new Date(r.date ?? '');
    const dayName = DAY_NAMES[dateObj.getDay()] || 'Hari';
    const rawSt = r.status;
    const st = (rawSt === 'present' ? 'hadir'
      : rawSt === 'sick' ? 'sakit'
      : rawSt === 'permission' ? 'izin'
      : rawSt === 'unexcused' ? 'alpa'
      : rawSt || 'hadir') as AttendanceCalendarDay['status'];
    return {
      date: r.date ?? '',
      day_number: dateObj.getDate() || (i + 1),
      day_name: dayName,
      status: st,
      // Jam & catatan dari data nyata; tanpa default karangan.
      tap_in: formatGateTapTime(r.check_in_time || r.checkInTime),
      tap_out: formatGateTapTime(r.check_out_time || r.checkOutTime),
      note: r.notes || r.note || undefined,
    };
  });
}
