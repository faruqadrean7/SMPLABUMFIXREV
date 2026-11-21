import { test, expect } from '@playwright/test';
import { generateTOTP } from './helpers/totp';
import { execSync } from 'child_process';

test.describe('E2E B.2: Admin Hidden Gate, 2FA Enrollment & Gateway Isolation', () => {
  test.beforeEach(async () => {
    try {
      execSync(
        'docker exec -i sistlabum-api php artisan tinker --execute="\\$u = App\\Models\\User::where(\'username\', \'bendahara_demo\')->first(); if (\\$u) { \\$u->two_factor_secret = null; \\$u->two_factor_confirmed_at = null; \\$u->two_factor_recovery_codes = null; \\$u->save(); }"'
      );
    } catch {
      // Ignore if docker exec fails
    }
  });

  test('1. Akses langsung /admin tanpa sesi mengembalikan HTTP 404 asli', async ({ request }) => {
    const res = await request.get('/admin', { maxRedirects: 0 });
    expect(res.status()).toBe(404);
  });

  test('2. Akun civitas (siswa/guru) ditolak di pintu masuk admin dengan pesan 401 generik', async ({ page }) => {
    await page.goto('/secret-admin-gate-login');
    await expect(page.locator('h1')).toContainText(/Back-Office/i);

    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // Pesan error generik
    await expect(page.locator('body')).toContainText(/Kredensial yang diberikan tidak cocok dengan data kami/i);
    // Tidak boleh mendarat di /admin
    expect(page.url()).toContain('/secret-admin-gate-login');
  });

  test('3. Akun admin ditolak di gerbang civitas (/masuk) dengan pesan 401 generik', async ({ page }) => {
    await page.goto('/masuk');

    // Pilih persona Guru atau Siswa
    await page.locator('text=Guru').first().click();

    await page.fill('input#identifier', 'admin_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // Pesan error generik
    await expect(page.locator('body')).toContainText(/Kredensial yang diberikan tidak cocok dengan data kami/i);
    expect(page.url()).toContain('/masuk');
  });

  test('4. Bendahara dan Admin login langsung dengan kredensial ke shell admin (2FA dinonaktifkan)', async ({ page }) => {
    await page.goto('/secret-admin-gate-login');

    // 1. Submit kredensial bendahara
    await page.fill('input#identifier', 'bendahara_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // 2. Langsung mendarat di shell admin tanpa kewajiban tantangan 2FA
    await page.waitForURL('**/admin**', { timeout: 15000 });
    expect(page.url()).toContain('/admin');
    await expect(page.locator('body')).not.toContainText(/404/);
  });
});
