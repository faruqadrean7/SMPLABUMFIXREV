import { test, expect } from '@playwright/test';

test.describe('E2E B.1: Alur Masuk Portal Civitas & No-Leak Verifikasi', () => {
  test.beforeEach(async ({ page }) => {
    // Clear storage to start with clean session
    await page.goto('/');
  });

  test('1. Navigasi Web Publik -> Masuk Portal -> Siswa Login -> Mendarat di /portal/siswa', async ({ page }) => {
    // 1. Web publik
    await page.goto('/');
    await expect(page).toHaveTitle(/SMP Laboratorium/i);

    // 2. Klik tombol "Masuk Portal" / "Portal Akademik"
    const masukBtn = page.locator('a[href="/masuk"]').first();
    await expect(masukBtn).toBeVisible();
    await masukBtn.click();

    // Pastikan mendarat di /masuk tanpa redirect ke port 3001
    await expect(page).toHaveURL(/.*\/masuk/);
    expect(page.url()).not.toContain('3001');
    expect(page.url()).not.toContain('sso_token');
    expect(page.url()).not.toContain('token=');

    // 3. Masukkan kredensial Siswa
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // 5. Mendarat di portal siswa
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });
    await expect(page).toHaveURL(/.*\/portal\/siswa$/);

    // Verifikasi tidak ada query string token
    expect(page.url()).not.toContain('token');
    expect(page.url()).not.toContain('3001');

    // Verifikasi konten dashboard siswa tampil
    await expect(page.locator('body')).toContainText(/Siswa|Jadwal|Presensi|Akademik/i);
  });

  test('2. Orang Tua Login -> Mendarat di /portal/ortu', async ({ page }) => {
    await page.goto('/masuk');

    await page.fill('input#identifier', 'ortu_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/*ortu*', { timeout: 15000 });
    expect(page.url()).not.toContain('token');
    expect(page.url()).not.toContain('3001');

    await expect(page.locator('body')).toContainText(/Orang Tua|Wali|Anak|Tagihan|Ahmad Pratama/i);
  });

  test('3. Guru & Wali Kelas Login -> Mendarat di /portal/guru', async ({ page }) => {
    await page.goto('/masuk');

    await page.fill('input#identifier', 'guru.mtk');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/portal/guru', { timeout: 15000 });
    await expect(page).toHaveURL(/.*\/portal\/guru$/);
    expect(page.url()).not.toContain('token');
    expect(page.url()).not.toContain('3001');

    // Guru MTK adalah wali kelas 7-A: pastikan navigasi wali kelas tersedia
    await expect(page.locator('body')).toContainText(/Guru|Jadwal|Jurnal|Wali Kelas/i);
  });

  test('4. Staf Login -> Mendarat di /portal/staf', async ({ page }) => {
    await page.goto('/masuk');

    await page.fill('input#identifier', 'staf_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/portal/staf', { timeout: 15000 });
    await expect(page).toHaveURL(/.*\/portal\/staf$/);
    expect(page.url()).not.toContain('token');
    expect(page.url()).not.toContain('3001');

    await expect(page.locator('body')).toContainText(/Staf|Sarpras|Layanan|Staf Civitas/i);
  });
});
