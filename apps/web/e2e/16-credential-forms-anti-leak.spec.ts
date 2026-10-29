import { test, expect } from '@playwright/test';

test.describe('E2E BATCH 5C: Form Kredensial Anti-Bocor URL (Strict method="POST" & No-JS Guard)', () => {
  // Gunakan konteks baru dengan JavaScript DINONAKTIFKAN secara penuh
  test.use({ javaScriptEnabled: false });

  test('1. Login Civitas (/masuk): Tombol submit disabled tanpa JS dan form ber-method POST (tidak bocor ke URL)', async ({ page }) => {
    await page.goto('/masuk');

    // 1. Assert submit button rendered as disabled in pure HTML (sebelum hidrasi)
    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeDisabled();

    // 2. Assert form tag method is strictly POST
    const formMethod = await page.locator('form').first().getAttribute('method');
    expect(formMethod?.toUpperCase()).toBe('POST');

    // 3. Coba isi input identifier dan password secara paksa
    await page.locator('input#identifier').fill('siswa_001');
    await page.locator('input#password').fill('SecretPass123!');

    // 4. Force submit form lewat tombol enter / dispatch
    await page.locator('input#password').press('Enter');

    // 5. Buktikan URL saat ini TIDAK MEMUAT password atau identifier sebagai query parameter
    const currentUrl = page.url();
    expect(currentUrl).not.toContain('SecretPass123');
    expect(currentUrl).not.toContain('siswa_001');
    expect(currentUrl).not.toContain('?');
  });

  test('2. Login Admin Gateway: Tombol submit disabled tanpa JS dan form ber-method POST', async ({ page }) => {
    const adminPath = process.env.ADMIN_LOGIN_PATH || '/secret-admin-gate-login';
    await page.goto(adminPath);

    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeDisabled();

    const formMethod = await page.locator('form').first().getAttribute('method');
    expect(formMethod?.toUpperCase()).toBe('POST');

    await page.locator('input#identifier').fill('super_admin');
    await page.locator('input#password').fill('AdminSecret999!');
    await page.locator('input#password').press('Enter');

    const currentUrl = page.url();
    expect(currentUrl).not.toContain('AdminSecret999');
    expect(currentUrl).not.toContain('super_admin');
    expect(currentUrl).not.toContain('?');
  });

  test('3. PPDB Masuk (/ppdb/masuk): Tombol submit disabled tanpa JS dan form method POST', async ({ page }) => {
    await page.goto('/ppdb/masuk');

    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeDisabled();

    const formMethod = await page.locator('form').first().getAttribute('method');
    expect(formMethod?.toUpperCase()).toBe('POST');

    await page.locator('input[type="text"]').fill('REG-2026-999');
    await page.locator('input[type="password"]').fill('PpdbPass123!');
    await page.locator('input[type="password"]').press('Enter');

    const currentUrl = page.url();
    expect(currentUrl).not.toContain('PpdbPass123');
    expect(currentUrl).not.toContain('REG-2026-999');
    expect(currentUrl).not.toContain('?');
  });

  test('4. PPDB Daftar (/ppdb/daftar): Form registrasi ber-method POST dan submit disabled tanpa JS', async ({ page }) => {
    await page.goto('/ppdb/daftar');

    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeDisabled();

    const formMethod = await page.locator('form').first().getAttribute('method');
    expect(formMethod?.toUpperCase()).toBe('POST');

    await page.locator('input[name="full_name"]').fill('Calon Siswa Rahasia');
    await page.locator('input[name="email"]').fill('calon@test.sch.id');
    await page.locator('input[name="password"]').fill('DaftarPass123!');
    await page.locator('input[name="password"]').press('Enter');

    const currentUrl = page.url();
    expect(currentUrl).not.toContain('DaftarPass123');
    expect(currentUrl).not.toContain('Calon+Siswa');
    expect(currentUrl).not.toContain('calon%40test');
    expect(currentUrl).not.toContain('?');
  });

  test('5. Pemulihan Kata Sandi (/lupa-password): Form method POST dan submit disabled tanpa JS', async ({ page }) => {
    await page.goto('/lupa-password');

    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeDisabled();

    const formMethod = await page.locator('form').first().getAttribute('method');
    expect(formMethod?.toUpperCase()).toBe('POST');

    await page.locator('input#email').fill('guru.mtk@sistlabum.sch.id');
    await page.locator('input#email').press('Enter');

    const currentUrl = page.url();
    expect(currentUrl).not.toContain('guru.mtk');
    expect(currentUrl).not.toContain('?');
  });
});
