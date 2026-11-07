import { describe, it, expect } from 'vitest';
import {
  buildAttendanceRecap,
  buildAttendanceCalendar,
  formatGateTapTime,
} from '@/lib/guardian-attendance';

/**
 * FE-ORTU-PRESENSI-01: Presensi portal ortu HARUS jujur.
 * Tidak boleh mengarang rekap (25 hari hadir, 96%) atau jam tap ('06:48 WIB', '15:15 WIB').
 */
describe('FE-ORTU-PRESENSI-01: pemetaan presensi ortu (kejujuran data)', () => {
  describe('buildAttendanceRecap', () => {
    it('FE-ORTU-PRESENSI-01: memetakan summary backend nyata (snake_case)', () => {
      const recap = buildAttendanceRecap({
        present_days: 40,
        sick_days: 2,
        leave_days: 3,
        unexcused_days: 1,
        percentage: 88.5,
      });
      expect(recap).toEqual({ hadir: 40, sakit: 2, izin: 3, alpa: 1, terlambat: 0, rate: 88.5 });
    });

    it('FE-ORTU-PRESENSI-01: mendukung varian camelCase', () => {
      const recap = buildAttendanceRecap({ presentDays: 10, sickDays: 1, leaveDays: 0, unexcusedDays: 0, attendance_percentage: 95 });
      expect(recap.hadir).toBe(10);
      expect(recap.sakit).toBe(1);
      expect(recap.rate).toBe(95);
    });

    it('FE-ORTU-PRESENSI-01: summary kosong → semua 0, BUKAN 25/1/1/96', () => {
      const recap = buildAttendanceRecap(undefined);
      expect(recap).toEqual({ hadir: 0, sakit: 0, izin: 0, alpa: 0, terlambat: 0, rate: 0 });
      expect(recap.hadir).not.toBe(25);
      expect(recap.rate).not.toBe(96);
    });

    it('FE-ORTU-PRESENSI-01: terlambat selalu 0 karena backend tak menyediakannya', () => {
      expect(buildAttendanceRecap({ present_days: 5 }).terlambat).toBe(0);
    });
  });

  describe('formatGateTapTime', () => {
    it('FE-ORTU-PRESENSI-01: memformat jam tap nyata HH:mm:ss → HH:mm WIB', () => {
      expect(formatGateTapTime('06:48:00')).toBe('06:48 WIB');
      expect(formatGateTapTime('07:15:30')).toBe('07:15 WIB');
    });

    it('FE-ORTU-PRESENSI-01: tanpa data → undefined, tidak mengarang 06:48 WIB', () => {
      expect(formatGateTapTime(undefined)).toBeUndefined();
      expect(formatGateTapTime(null)).toBeUndefined();
      expect(formatGateTapTime('')).toBeUndefined();
    });
  });

  describe('buildAttendanceCalendar', () => {
    it('FE-ORTU-PRESENSI-01: memetakan riwayat nyata (status, jam masuk, catatan)', () => {
      const days = buildAttendanceCalendar({
        history: [
          { date: '2026-10-05', status: 'present', check_in_time: '06:50:00', notes: 'Hadir KBM' },
        ],
      });
      expect(days).toHaveLength(1);
      expect(days[0].status).toBe('hadir');
      expect(days[0].tap_in).toBe('06:50 WIB');
      expect(days[0].note).toBe('Hadir KBM');
    });

    it('FE-ORTU-PRESENSI-01: memetakan seluruh status backend', () => {
      const days = buildAttendanceCalendar({
        history: [
          { date: '2026-10-01', status: 'present' },
          { date: '2026-10-02', status: 'sick' },
          { date: '2026-10-03', status: 'permission' },
          { date: '2026-10-04', status: 'unexcused' },
        ],
      });
      expect(days.map((d) => d.status)).toEqual(['hadir', 'sakit', 'izin', 'alpa']);
    });

    it('FE-ORTU-PRESENSI-01: tanpa check_out/notes → undefined, tidak mengarang 15:15 WIB / Presensi KBM', () => {
      const days = buildAttendanceCalendar({ history: [{ date: '2026-10-05', status: 'present', check_in_time: '06:50:00' }] });
      expect(days[0].tap_out).toBeUndefined();
      expect(days[0].note).toBeUndefined();
    });

    it('FE-ORTU-PRESENSI-01: mendukung kunci records sebagai fallback', () => {
      const days = buildAttendanceCalendar({ records: [{ date: '2026-10-06', status: 'sick' }] });
      expect(days).toHaveLength(1);
      expect(days[0].status).toBe('sakit');
    });

    it('FE-ORTU-PRESENSI-01: tanpa data → array kosong (bukan data contoh)', () => {
      expect(buildAttendanceCalendar(undefined)).toEqual([]);
      expect(buildAttendanceCalendar({})).toEqual([]);
      expect(buildAttendanceCalendar({ history: [] })).toEqual([]);
    });
  });
});
