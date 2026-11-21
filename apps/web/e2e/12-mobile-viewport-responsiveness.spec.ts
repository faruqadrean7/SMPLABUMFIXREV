import { test, expect } from '@playwright/test';

test.describe('E2E B.6: Responsiveness Tampilan Mobile Viewport (390x844)', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    userAgent:
      'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
  });

  test('1. Portal Siswa: Seluruh modul utama responsif tanpa overflow horizontal pada viewport 390x844', async ({ page }) => {
    // Login Siswa
    await page.goto('/masuk');
    await page.locator('text=Siswa').first().click();
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    const siswaPages = [
      '/portal/siswa',
      '/portal/siswa/jadwal',
      '/portal/siswa/tugas',
      '/portal/siswa/nilai',
      '/portal/siswa/tagihan',
    ];

    for (const route of siswaPages) {
      await page.goto(route);
      await page.waitForLoadState('networkidle');

      // 1. Verifikasi tidak ada horizontal scrollbar liar di dokumen utama
      const isOverflowing = await page.evaluate(() => {
        return document.documentElement.scrollWidth > window.innerWidth;
      });
      expect(isOverflowing, `Halaman ${route} tidak boleh memiliki horizontal scrollbar liar`).toBe(false);

      // 2. Verifikasi konten utama dapat dibaca dan tombol aksi terlihat
      const body = page.locator('body');
      await expect(body).toBeVisible();
      const text = await body.innerText();
      expect(text.length).toBeGreaterThan(50);
    }
  });

  test('2. Portal Ortu: Beranda & Detail Anak (5 Tab) responsif tanpa overflow horizontal pada viewport 390x844', async ({ page }) => {
    // Login Ortu
    await page.goto('/masuk');
    await page.locator('text=Orang Tua').first().click();
    await page.fill('input#identifier', 'ortu_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/ortu', { timeout: 15000 });

    // 1. Cek Beranda Pemilih Anak
    const isHomeOverflowing = await page.evaluate(() => {
      return document.documentElement.scrollWidth > window.innerWidth;
    });
    expect(isHomeOverflowing, 'Halaman /portal/ortu tidak boleh overflow horizontal').toBe(false);

    // 2. Buka Profil Anak
    const childLink = page.locator('a[href*="/portal/ortu/anak/"]').first();
    await expect(childLink).toBeVisible();
    await childLink.click();
    await page.waitForURL('**/portal/ortu/anak/*', { timeout: 15000 });

    // 3. Uji Setiap Tab: Presensi, Rapor Akademik, Catatan Karakter, Perkembangan, Keuangan
    const tabs = ['Presensi', 'Rapor Akademik', 'Catatan Karakter', 'Perkembangan', 'Keuangan'];

    for (const tabName of tabs) {
      const tabBtn = page.locator(`button:has-text("${tabName}")`).first();
      if (await tabBtn.isVisible()) {
        await tabBtn.click();
        await page.waitForTimeout(300);

        // Verifikasi tidak ada horizontal scrollbar halaman
        const isTabOverflowing = await page.evaluate(() => {
          return document.documentElement.scrollWidth > window.innerWidth;
        });
        expect(isTabOverflowing, `Tab ${tabName} tidak boleh menyebabkan overflow horizontal`).toBe(false);

        const tabText = await page.locator('body').innerText();
        expect(tabText.length).toBeGreaterThan(50);
      }
    }
  });
});
