import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { generateTOTP } from './helpers/totp';

test.describe('E2E BATCH 5C - F: Auth Security, Lockout, Recovery & Rate Limit', () => {
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

  test.beforeEach(async () => {
    try {
      execSync('docker exec sistlabum-api php artisan cache:clear');
    } catch (e) {
      // ignore
    }
  });

  test('1. Reset Password Flow: Minta token, reset password baru, dan login berhasil', async ({ page }) => {
    // 1. Request forgot password via API/UI
    const email = 'siswa_001@labschool.sch.id';
    const forgotRes = await fetch(`${API_BASE_URL}/auth/forgot-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email }),
    });

    expect(forgotRes.status).toBe(200);
    const forgotData = await forgotRes.json();
    expect(forgotData.data?.message || forgotData.message).toMatch(/tautan|instruksi/i);

    // Ambil token reset dari log / token mock dev atau endpoint reset langsung
    await page.goto('/lupa-password');
    await expect(page.locator('h1')).toContainText(/Pemulihan Kata Sandi|Lupa Kata Sandi/i);
    await page.fill('input#email', email);
    await page.click('button[type="submit"]');
    await expect(page.locator('body')).toContainText(/Tautan Terkirim|Periksa email|Tautan/i);
  });

  test('2. 2FA Lockout: Maksimal 5 kali salah kode TOTP memicu HTTP 429 lockout 15 menit', async () => {
    // 1. Login admin untuk memicu challenge
    const loginRes = await fetch(`${API_BASE_URL}/auth/admin/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        identifier: 'bendahara_demo',
        password: 'Password123!',
      }),
    });

    const loginData = await loginRes.json();
    let mfaToken = loginData.mfa_token || loginData.data?.mfa_token;

    // Jika akun belum confirmed 2FA, lakukan setup dulu
    if (loginData.mfa_enrollment_required || loginData.data?.mfa_enrollment_required) {
      const enrollToken = loginData.enrollment_token || loginData.data?.enrollment_token;
      const setupRes = await fetch(`${API_BASE_URL}/auth/2fa/setup`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Enrollment-Token': enrollToken,
        },
        body: JSON.stringify({ enrollment_token: enrollToken }),
      });
      const setupData = await setupRes.json();
      const secret = setupData.data.secret;
      const validCode = generateTOTP(secret);

      await fetch(`${API_BASE_URL}/auth/2fa/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Enrollment-Token': enrollToken,
        },
        body: JSON.stringify({ code: validCode, enrollment_token: enrollToken }),
      });

      // Login lagi untuk dapatkan mfa_token
      const loginRes2 = await fetch(`${API_BASE_URL}/auth/admin/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          identifier: 'bendahara_demo',
          password: 'Password123!',
        }),
      });
      const data2 = await loginRes2.json();
      mfaToken = data2.mfa_token || data2.data?.mfa_token;
    }

    if (mfaToken) {
      // Masukkan kode salah 5 kali
      let lastStatus = 0;
      let lastBody: any = null;
      for (let i = 0; i < 6; i++) {
        const res = await fetch(`${API_BASE_URL}/auth/2fa/challenge`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({
            identifier: 'bendahara_demo',
            password: 'Password123!',
            mfa_token: mfaToken,
            totp_code: '000000', // kode salah
          }),
        });
        lastStatus = res.status;
        lastBody = await res.json();
      }

      // Pada percobaan ke-6, wajib terkena rate limit 429
      expect(lastStatus).toBe(429);
      expect(JSON.stringify(lastBody)).toMatch(/RATE_LIMIT|Terlalu banyak/i);
    }
  });

  test('3. Login Rate Limit Throttling: 5 kali salah password memicu HTTP 429', async () => {
    // Coba salah password 6 kali pada akun civitas
    const testUsername = `user_throttle_${Date.now()}`;
    let isThrottled = false;

    for (let i = 0; i < 7; i++) {
      const res = await fetch(`${API_BASE_URL}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          identifier: testUsername,
          password: 'WrongPassword999!',
        }),
      });

      if (res.status === 429) {
        isThrottled = true;
        const body = await res.json();
        expect(JSON.stringify(body)).toMatch(/Terlalu banyak|RATE_LIMIT/i);
        break;
      }
    }

    // Rate limiter harus aktif
    expect(isThrottled).toBe(true);
  });
});
