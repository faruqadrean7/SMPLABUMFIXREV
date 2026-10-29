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

  test('4. Bendahara dipaksa setup 2FA pada login pertama dan berhasil login ke shell admin', async ({ page }) => {
    await page.goto('/secret-admin-gate-login');

    // 1. Submit kredensial bendahara
    await page.fill('input#identifier', 'bendahara_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // 2. Verifikasi UI beralih ke dialog Aktivasi Wajib 2FA
    await expect(page.locator('body')).toContainText(/Aktivasi Wajib Otentikasi 2 Faktor/i);
    await expect(page.locator('body')).toContainText(/Kunci Rahasia Base32/i);

    // 3. Ambil secret key dari input / kode manual di layar
    const secretCodeEl = page.locator('[data-testid="secret-code"]').first();
    await expect(secretCodeEl).toBeVisible();
    const rawSecret = await secretCodeEl.textContent();
    const secret = (rawSecret || '').replace(/\s+/g, '');
    expect(secret.length).toBeGreaterThanOrEqual(16);

    // 4. Generate kode TOTP yang valid
    const totpCode = generateTOTP(secret);

    // 5. Masukkan kode TOTP 6 digit
    await page.fill('input#enrollment-code', totpCode);
    await page.click('button:has-text("Aktifkan 2FA")');

    // 6. Verifikasi kode pemulihan (recovery codes) ditampilkan & ambil satu
    await expect(page.locator('body')).toContainText(/2FA Berhasil Diaktifkan/i);
    await expect(page.locator('body')).toContainText(/Simpan kode pemulihan/i);
    const recoveryCodeEl = page.locator('.select-all').first();
    const recoveryCode = (await recoveryCodeEl.textContent())?.trim();
    expect(recoveryCode).toBeTruthy();

    // 7. Lanjutkan ke halaman masuk
    await page.click('button:has-text("Lanjut ke Halaman Masuk")');

    // 8. Sekarang login dengan akun bendahara + challenge MFA menggunakan recovery code
    await page.fill('input#identifier', 'bendahara_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // Layar Verifikasi Dua Faktor
    await expect(page.locator('body')).toContainText(/Verifikasi Dua Faktor/i);
    await page.fill('input#totp-code', recoveryCode!);
    await page.click('button:has-text("Verifikasi")');

    // 9. Mendarat di shell admin
    await page.waitForURL('**/admin', { timeout: 15000 });
    await expect(page).toHaveURL(/.*\/admin$/);
    await expect(page.locator('body')).toContainText(/Dashboard|SISTLABUM Admin|SISTLABUM/i);
  });
});
