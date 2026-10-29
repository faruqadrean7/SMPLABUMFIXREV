import { test, expect, Page } from '@playwright/test';

test.describe('E2E B.3: Integritas Halaman Portal (Tanpa 5xx, Bebas Error Konsol, Data Tampil)', () => {
  // Helper untuk memonitor error konsol dan respons HTTP 5xx
  const setupErrorListeners = (page: Page, errors: string[]) => {
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Ignore expected favicon or benign hydration warnings
        if (!text.includes('favicon') && !text.includes('next-dev') && !text.includes('ResizeObserver')) {
          errors.push(`[Console Error] ${text}`);
        }
      }
    });

    page.on('response', (response) => {
      if (response.status() >= 500) {
        errors.push(`[HTTP ${response.status()}] ${response.url()}`);
      }
    });
  };

  test('1. Portal Siswa: Seluruh Halaman Terbuka dengan Data', async ({ page }) => {
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    // Login sebagai Siswa
    await page.goto('/masuk');
    await page.locator('text=Siswa').first().click();
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    const studentRoutes = [
      '/portal/siswa',
      '/portal/siswa/jadwal',
      '/portal/siswa/tugas',
      '/portal/siswa/nilai',
      '/portal/siswa/tagihan',
    ];

    for (const route of studentRoutes) {
      await page.goto(route);
      await page.waitForLoadState('networkidle');
      // Pastikan konten tidak kosong
      const bodyText = await page.locator('body').innerText();
      expect(bodyText.length).toBeGreaterThan(50);
      expect(errorLogs).toEqual([]);
    }
  });

  test('2. Portal Guru: Seluruh Halaman Terbuka dengan Data', async ({ page }) => {
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    // Login sebagai Guru (Wali Kelas)
    await page.goto('/masuk');
    await page.locator('text=Guru').first().click();
    await page.fill('input#identifier', 'guru.mtk');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/guru', { timeout: 15000 });

    const teacherRoutes = [
      '/portal/guru',
      '/portal/guru/jadwal',
      '/portal/guru/jurnal',
      '/portal/guru/nilai',
      '/portal/guru/fasilitas',
      '/portal/guru/wali-kelas',
    ];

    for (const route of teacherRoutes) {
      await page.goto(route);
      await page.waitForLoadState('networkidle');
      const bodyText = await page.locator('body').innerText();
      expect(bodyText.length).toBeGreaterThan(50);
      expect(errorLogs).toEqual([]);
    }
  });

  test('3. Portal Orang Tua: Halaman Dashboard & Detail Anak Terbuka dengan Data', async ({ page }) => {
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    // Login sebagai Orang Tua
    await page.goto('/masuk');
    await page.locator('text=Orang Tua').first().click();
    await page.fill('input#identifier', 'ortu_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/ortu', { timeout: 15000 });

    // 1. Periksa Halaman Pemilih Anak (/portal/ortu)
    await page.waitForLoadState('networkidle');
    const selectorText = await page.locator('body').innerText();
    expect(selectorText.length).toBeGreaterThan(50);
    expect(errorLogs).toEqual([]);

    // 2. Klik Masuk ke Detail Anak
    const childLink = page.locator('a[href*="/portal/ortu/anak/"]').first();
    await expect(childLink).toBeVisible();
    await childLink.click();

    await page.waitForURL('**/portal/ortu/anak/*', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    const childDetailText = await page.locator('body').innerText();
    expect(childDetailText.length).toBeGreaterThan(50);
    expect(errorLogs).toEqual([]);
  });
});
