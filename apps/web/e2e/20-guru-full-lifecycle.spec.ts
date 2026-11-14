import { test, expect } from '@playwright/test';

test.describe('E2E BATCH 5E: Alur Lengkap Aktivitas Guru (End-to-End User Journey)', () => {
  test.setTimeout(90000);

  test('Siklus Lengkap Aktivitas Guru: Masuk Portal -> Navigasi KBM -> Buku Nilai & Jurnal -> Layanan Pribadi -> Keluar Sesi', async ({ page }) => {
    // -------------------------------------------------------------------------
    // 1. NAVIGASI AWAL: Halaman Publik -> Masuk Portal
    // -------------------------------------------------------------------------
    await page.goto('/');
    await expect(page).toHaveTitle(/SMP Laboratorium/i);

    // Klik tombol portal akademik di header
    const portalBtn = page.locator('a[href="/masuk"]').first();
    await expect(portalBtn).toBeVisible();
    await portalBtn.click();

    await page.waitForURL('**/masuk', { timeout: 15000 });
    await expect(page).toHaveURL(/.*\/masuk/);

    // -------------------------------------------------------------------------
    // 2. AUTENTIKASI: Login sebagai Guru Mata Pelajaran & Wali Kelas (guru.mtk)
    // -------------------------------------------------------------------------
    await page.fill('input#identifier', 'guru.mtk');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // Pastikan diarahkan ke dashboard portal guru
    await page.waitForURL('**/portal/guru', { timeout: 15000 });
    await expect(page).toHaveURL(/.*\/portal\/guru$/);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);

    // -------------------------------------------------------------------------
    // 3. AKTIVITAS DASHBOARD KBM GURU
    // -------------------------------------------------------------------------
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Guru|KBM|Jadwal|Jurnal|Wali Kelas/i);

    // -------------------------------------------------------------------------
    // 4. AKTIVITAS JADWAL MENGAJAR MINGGUAN
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/jadwal');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Jadwal|Sesi|Mengajar|Kelas/i);

    // -------------------------------------------------------------------------
    // 5. AKTIVITAS JURNAL MENGAJAR HARIAN
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/jurnal');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Jurnal|Materi|Pembelajaran|Catatan/i);

    // -------------------------------------------------------------------------
    // 6. AKTIVITAS PRESENSI SISWA DI KELAS
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/presensi');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Presensi|Kehadiran|Siswa|Hadir/i);

    // -------------------------------------------------------------------------
    // 7. AKTIVITAS BUKU NILAI & ASESMEN (Formatif & Sumatif)
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/nilai');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Buku Nilai|Ledger|KKTP|Penilaian|Nilai/i);

    // -------------------------------------------------------------------------
    // 8. AKTIVITAS TUGAS BELAJAR SISWA (LMS)
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/tugas');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Tugas|Penugasan|Batas Waktu/i);

    // -------------------------------------------------------------------------
    // 9. AKTIVITAS BANK SOAL & CBT
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/ujian');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Bank Soal|Ujian|CBT|Asesmen/i);

    // -------------------------------------------------------------------------
    // 10. AKTIVITAS BANK MODUL AJAR (KURIKULUM MERDEKA)
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/materi');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Modul Ajar|Perangkat KBM|Bahan Ajar|Materi/i);

    // -------------------------------------------------------------------------
    // 11. AKTIVITAS PROGRAM KHUSUS (Tahfidz / ICP)
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/program-khusus');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Program Khusus|Tahfidz|ICP|Kelas Khusus/i);

    // -------------------------------------------------------------------------
    // 12. AKTIVITAS PERIZINAN SISWA & POIN KEDISIPLINAN
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/perizinan');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Perizinan|Izin|Disetujui|Menunggu/i);

    await page.goto('/portal/guru/siswa');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Data Siswa|Poin|Pelanggaran|Siswa/i);

    // -------------------------------------------------------------------------
    // 13. KONSOL WALI KELAS 7-A (ROSTER & REKAP KELAS PERWALIAN)
    // -------------------------------------------------------------------------
    await page.goto('/portal/guru/wali-kelas');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Wali Kelas|Kelas 7-A|Rombel|Perwalian/i);

    // -------------------------------------------------------------------------
    // 14. LAYANAN PRIBADI STAF PEGAWAI GURU
    // -------------------------------------------------------------------------
    // 14.A. Presensi Kehadiran Mandiri Guru
    await page.goto('/portal/guru/presensi-saya');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Presensi Mandiri|Presensi Pegawai|Kehadiran Saya/i);

    // 14.B. Pengajuan Izin / Cuti Guru
    await page.goto('/portal/guru/izin-cuti');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Pengajuan Izin|Cuti|Permohonan/i);

    // 14.C. Riwayat Slip Gaji Guru
    await page.goto('/portal/guru/slip-gaji');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('body')).toContainText(/Slip Gaji|Penghasilan|Rincian/i);

    // -------------------------------------------------------------------------
    // 15. KELUAR SESI / LOGOUT GURU SECARA BERSIH
    // -------------------------------------------------------------------------
    // Buka menu profil di sidebar
    const profileBtn = page.locator('button:has-text("Beralih Portal"), button:has-text("Wali Kelas")').first();
    await expect(profileBtn).toBeVisible();
    await profileBtn.click();

    // Klik tombol Keluar Sesi
    const logoutBtn = page.locator('button:has-text("Keluar Sesi Guru")').first();
    await expect(logoutBtn).toBeVisible();
    await logoutBtn.click();

    // Pastikan teralihkan kembali ke halaman masuk atau landing page
    await page.waitForURL(/\/masuk|\/$/, { timeout: 15000 });
    expect(page.url()).not.toContain('/portal/guru');
  });
});
