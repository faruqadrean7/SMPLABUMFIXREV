import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

test.describe.serial('BATCH 5E-1: Detektor Kontrol Mati & Inventaris Fungsional Admin', () => {
  let adminToken: string;

  test.beforeAll(async () => {
    // Ambil token admin langsung dari container via helper artisan
    adminToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'admin_demo\')->first()->createToken(\'audit-5e1-token\')->plainTextToken;"'
    ).toString().trim();
  });

  const setupAdminSession = async (page: Page) => {
    await page.goto('/masuk');
    await page.context().addCookies([
      {
        name: 'sistlabum_admin_session',
        value: '1',
        domain: 'localhost',
        path: '/',
      },
    ]);

    await page.evaluate(
      ({ token }) => {
        sessionStorage.setItem('sistlabum_access_token', token);
        localStorage.setItem('sistlabum_refresh_token', 'audit-5e1-dummy-refresh');
        sessionStorage.setItem(
          'sistlabum_user_profile',
          JSON.stringify({
            id: 'admin-audit-5e1-id',
            username: 'admin_demo',
            roles: ['super_admin'],
            permissions: ['*'],
          })
        );
      },
      { token: adminToken }
    );
  };

  const setupErrorListeners = (page: Page, errorLogs: string[]) => {
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (
          !text.includes('favicon') &&
          !text.includes('next-dev') &&
          !text.includes('ResizeObserver') &&
          !text.includes('status of 4')
        ) {
          errorLogs.push(`[Console Error] ${text}`);
        }
      }
    });

    page.on('response', (response) => {
      if (response.status() >= 500) {
        errorLogs.push(`[HTTP ${response.status()}] ${response.url()}`);
      }
    });
  };

  const waitForLoaders = async (page: Page) => {
    const loader = page.locator('text=/Memuat|Loading/i').first();
    if (await loader.isVisible().catch(() => false)) {
      await loader.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
    }
  };

  test('5E-1.A: Deteksi kontrol mati & tab navigasi pada Master Data Admin', async ({ page }) => {
    test.setTimeout(90000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);
    await setupAdminSession(page);

    const masterRoutes = [
      '/admin/master/academic-years',
      '/admin/master/students',
      '/admin/master/teachers',
      '/admin/master/guardians',
      '/admin/master/classes',
      '/admin/master/subjects',
    ];

    for (const route of masterRoutes) {
      await page.goto(route);
      await waitForLoaders(page);

      // 1. Verifikasi konten halaman bebas dari error / crash
      const bodyText = await page.locator('body').innerText();
      expect(bodyText).not.toContain('Internal Server Error');
      expect(bodyText).not.toContain('Cannot read properties');
      expect(bodyText).not.toMatch(/lorem ipsum/i);
      expect(bodyText).not.toMatch(/\bTODO\b/);

      // 2. Klik tab jika ada
      const tabs = page.locator('[role="tab"], button[data-state]');
      const tabCount = await tabs.count();
      for (let i = 0; i < Math.min(tabCount, 3); i++) {
        const tab = tabs.nth(i);
        if (await tab.isVisible()) {
          await tab.click();
          await page.waitForTimeout(300);
          await waitForLoaders(page);
        }
      }

      // 3. Tes input pencarian (non-destruktif)
      const searchInput = page.locator('input[placeholder*="Cari" i], input[type="search"]').first();
      if (await searchInput.isVisible().catch(() => false)) {
        await searchInput.fill('TestSearch');
        await page.waitForTimeout(400);
        // Clear search
        await searchInput.fill('');
        await page.waitForTimeout(300);
      }

      // 4. Verifikasi tombol tambah/buka dialog tidak memicu crash
      const addBtn = page.locator('button:has-text("Tambah"), button:has-text("Baru")').first();
      if (await addBtn.isVisible().catch(() => false)) {
        await addBtn.click();
        await page.waitForTimeout(500);
        // Dialog harus terbuka
        const dialog = page.locator('[role="dialog"], [data-state="open"]').first();
        if (await dialog.isVisible().catch(() => false)) {
          // Tutup dialog dengan tombol Batal / close
          const closeBtn = page.locator('button:has-text("Batal"), button[aria-label="Close"], button:has-text("Tutup")').first();
          if (await closeBtn.isVisible().catch(() => false)) {
            await closeBtn.click();
            await page.waitForTimeout(300);
          } else {
            await page.keyboard.press('Escape');
          }
        }
      }
    }

    expect(errorLogs, `Error terdeteksi pada Master Data: ${errorLogs.join(', ')}`).toEqual([]);
  });

  test('5E-1.B: Deteksi kontrol mati pada Akademik & KBM Admin', async ({ page }) => {
    test.setTimeout(120000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);
    await setupAdminSession(page);

    const academicRoutes = [
      '/admin/academic/schedules',
      '/admin/academic/journals',
      '/admin/academic/attendance',
      '/admin/academic/staff-attendance',
      '/admin/academic/leave-requests',
      '/admin/academic/teaching-materials',
      '/admin/academic/assignments',
      '/admin/academic/grades',
      '/admin/academic/progress-reports',
      '/admin/academic/report-cards',
    ];

    for (const route of academicRoutes) {
      await page.goto(route);
      await waitForLoaders(page);

      const bodyText = await page.locator('body').innerText();
      expect(bodyText).not.toContain('Internal Server Error');
      expect(bodyText).not.toContain('Cannot read properties');

      // Tes filter dropdown
      const filterSelect = page.locator('button[role="combobox"], select').first();
      if (await filterSelect.isVisible().catch(() => false)) {
        await filterSelect.click();
        await page.waitForTimeout(300);
        await page.keyboard.press('Escape');
      }

      // Tes search
      const searchInput = page.locator('input[placeholder*="Cari" i]').first();
      if (await searchInput.isVisible().catch(() => false)) {
        await searchInput.fill('Matematika');
        await page.waitForTimeout(300);
        await searchInput.fill('');
      }
    }

    expect(errorLogs, `Error terdeteksi pada Akademik & KBM: ${errorLogs.join(', ')}`).toEqual([]);
  });

  test('5E-1.C: Deteksi kontrol mati pada CBT, Kesiswaan, Sarpras & BK Admin', async ({ page }) => {
    test.setTimeout(120000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);
    await setupAdminSession(page);

    const serviceRoutes = [
      '/admin/cbt/questions',
      '/admin/cbt/exams',
      '/admin/cbt/monitor',
      '/admin/student-affairs/points',
      '/admin/student-affairs/extracurriculars',
      '/admin/student-affairs/competitions',
      '/admin/counseling',
      '/admin/counseling/early-warnings',
      '/admin/facilities',
      '/admin/assets',
      '/admin/tickets',
      '/admin/library',
      '/admin/public-relations',
    ];

    for (const route of serviceRoutes) {
      await page.goto(route);
      await waitForLoaders(page);

      const bodyText = await page.locator('body').innerText();
      expect(bodyText).not.toContain('Internal Server Error');
      expect(bodyText).not.toContain('Cannot read properties');

      // Klik tab jika tersedia
      const tabs = page.locator('[role="tab"]');
      const tabCount = await tabs.count();
      for (let i = 0; i < Math.min(tabCount, 2); i++) {
        const tab = tabs.nth(i);
        if (await tab.isVisible()) {
          await tab.click();
          await page.waitForTimeout(300);
        }
      }
    }

    expect(errorLogs, `Error terdeteksi pada Layanan Siswa & Sarpras: ${errorLogs.join(', ')}`).toEqual([]);
  });

  test('5E-1.D: Deteksi kontrol mati pada Keuangan, CMS, Laporan & Pengaturan Admin', async ({ page }) => {
    test.setTimeout(120000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);
    await setupAdminSession(page);

    const settingsRoutes = [
      '/admin/finance/fee-types',
      '/admin/finance/invoices',
      '/admin/finance/payments',
      '/admin/finance/arrears',
      '/admin/finance/reports',
      '/admin/finance/settings',
      '/admin/cms',
      '/admin/reports',
      '/admin/notifications',
      '/admin/settings/users',
      '/admin/settings/roles',
      '/admin/settings/audit-logs',
    ];

    for (const route of settingsRoutes) {
      await page.goto(route);
      await waitForLoaders(page);

      const bodyText = await page.locator('body').innerText();
      expect(bodyText).not.toContain('Internal Server Error');
      expect(bodyText).not.toContain('Cannot read properties');

      // Tes pagination next button bila aktif
      const nextBtn = page.locator('button:has-text("Selanjutnya"), button:has-text("Next"), button[aria-label="Next page"]').first();
      if (await nextBtn.isVisible().catch(() => false) && await nextBtn.isEnabled().catch(() => false)) {
        await nextBtn.click();
        await page.waitForTimeout(400);
      }
    }

    expect(errorLogs, `Error terdeteksi pada Keuangan & Pengaturan: ${errorLogs.join(', ')}`).toEqual([]);
  });
});
