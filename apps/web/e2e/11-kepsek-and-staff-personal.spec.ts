import { test, expect } from '@playwright/test';
import { generateTOTP } from './helpers/totp';
import { execSync } from 'child_process';

test.describe('E2E B.5: Kepsek Mandatory 2FA Flow & Admin TU Personal Portal Services', () => {
  const runArtisan = (cmd: string): string => {
    const b64 = Buffer.from(cmd).toString('base64');
    return execSync(`docker exec -i sistlabum-api php artisan tinker --execute="eval(base64_decode('${b64}'));"`).toString().trim();
  };

  test.beforeEach(async () => {
    // Reset status 2FA kepsek_demo dan staf_tu agar pengujian alur enrollment 100% konsisten
    runArtisan(`
      foreach (['kepsek_demo', 'staf_tu'] as $username) {
        $u = \\App\\Models\\User::where('username', $username)->first();
        if ($u) {
          $u->two_factor_secret = null;
          $u->two_factor_confirmed_at = null;
          $u->two_factor_recovery_codes = null;
          $u->save();
        }
      }
    `);
  });

  test('1. Kepala Sekolah dipaksa setup 2FA saat pertama login dan diarahkan ke Dashboard Kepsek', async ({ page }) => {
    await page.goto('/secret-admin-gate-login');

    // 1. Submit kredensial Kepala Sekolah
    await page.fill('input#identifier', 'kepsek_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // 2. Layar Aktivasi Wajib 2FA (A.2: Semua peran shell admin wajib 2FA)
    await expect(page.locator('body')).toContainText(/Aktivasi Wajib Otentikasi 2 Faktor/i);
    await expect(page.locator('body')).toContainText(/Kunci Rahasia Base32/i);

    // 3. Ambil Base32 secret dan generate kode TOTP
    const secretCodeEl = page.locator('[data-testid="secret-code"]').first();
    await expect(secretCodeEl).toBeVisible();
    const rawSecret = await secretCodeEl.textContent();
    const secret = (rawSecret || '').replace(/\s+/g, '');
    expect(secret.length).toBeGreaterThanOrEqual(16);

    const totpCode = generateTOTP(secret);

    // 4. Masukkan kode verifikasi 2FA
    await page.fill('input#enrollment-code', totpCode);
    await page.click('button:has-text("Aktifkan 2FA")');

    // 5. Layar sukses 2FA dan kode pemulihan
    await expect(page.locator('body')).toContainText(/2FA Berhasil Diaktifkan/i);
    const recoveryCodeEl = page.locator('.select-all').first();
    const recoveryCode = (await recoveryCodeEl.textContent())?.trim();
    expect(recoveryCode).toBeTruthy();

    await page.click('button:has-text("Lanjut ke Halaman Masuk")');

    // 6. Login kembali dengan kredensial + challenge TOTP / recovery code aktif
    await page.fill('input#identifier', 'kepsek_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // Dialog Verifikasi Dua Faktor
    await expect(page.locator('body')).toContainText(/Verifikasi Dua Faktor/i);
    await page.fill('input#totp-code', recoveryCode!);
    await page.click('button:has-text("Verifikasi")');

    // 7. Berhasil login dan mendarat di Dashboard Kepsek (/portal/kepsek atau /admin)
    await page.waitForURL(/.*(\/portal\/kepsek|\/admin)/, { timeout: 15000 });
    const currentUrl = page.url();
    expect(currentUrl).toMatch(/(\/portal\/kepsek|\/admin)/);

    // Buka Portal Kepsek dan pastikan ringkasan eksekutif dapat diakses
    await page.goto('/portal/kepsek');
    await page.waitForLoadState('networkidle');
    const kepsekText = await page.locator('body').innerText();
    expect(kepsekText).toMatch(/Kepala Sekolah|Eksekutif|Persetujuan/i);
  });

  test('2. Admin TU login via gerbang admin, memakai tautan Layanan Pribadi, dan mengakses fitur portal staf', async ({ page }) => {
    // 1. Setup 2FA staf_tu secara otomatis di backend agar login langsung dengan tantangan TOTP
    const secret = 'JBSWY3DPEHPK3PXP'; // Base32 valid test secret
    runArtisan(`
      $u = \\App\\Models\\User::where('username', 'staf_tu')->first();
      $u->two_factor_secret = '${secret}';
      $u->two_factor_confirmed_at = now();
      $u->save();
    `);

    await page.goto('/secret-admin-gate-login');
    await page.fill('input#identifier', 'staf_tu');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    // 2. Submit tantangan 2FA
    await expect(page.locator('body')).toContainText(/Verifikasi Dua Faktor/i);
    const totp = generateTOTP(secret);
    await page.fill('input#totp-code', totp);
    await page.click('button:has-text("Verifikasi")');

    // 3. Mendarat di Shell Admin
    await page.waitForURL('**/admin', { timeout: 15000 });
    expect(page.url()).toContain('/admin');

    // 4. Periksa tautan "Layanan Pribadi" pada sidebar shell admin (hasil implementasi A.1)
    const personalServicesLink = page.locator('a[href="/portal/staf"]').first();
    await expect(personalServicesLink).toBeVisible();

    // 5. Klik Layanan Pribadi dan pastikan membuka Portal Staf tanpa ditolak
    await personalServicesLink.click();
    await page.waitForURL('**/portal/staf', { timeout: 15000 });
    expect(page.url()).toContain('/portal/staf');

    // 6. Uji akses seluruh submenu layanan pribadi staf
    const staffSubmodules = [
      '/portal/staf/presensi-saya',
      '/portal/staf/izin-cuti',
      '/portal/staf/slip-gaji',
      '/portal/staf/tiket',
    ];

    for (const sub of staffSubmodules) {
      await page.goto(sub);
      await page.waitForLoadState('networkidle');
      const text = await page.locator('body').innerText();
      expect(text.length, `Halaman ${sub} tidak boleh kosong`).toBeGreaterThan(50);
      expect(text).not.toContain('403');
      expect(text).not.toContain('Akses Dibatasi');
    }
  });
});
