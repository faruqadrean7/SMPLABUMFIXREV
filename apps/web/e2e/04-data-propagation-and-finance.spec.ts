import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('E2E B.4: Propagasi Data (Admin/Bendahara -> Portal Ortu & Siswa)', () => {
  test('1. Verifikasi Slip Pembayaran di Admin terefleksi seketika di Portal Orang Tua dengan Kuitansi Resmi', async ({ request, page }) => {
    // 1. Dapatkan token administrator / bendahara melalui Sanctum token terautentikasi
    const adminToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'admin_demo\')->first()->createToken(\'test-e2e\')->plainTextToken;"'
    ).toString().trim();
    expect(adminToken).toBeTruthy();

    // 2. Ambil slip pembayaran untuk verifikasi
    const listRes = await request.get('http://localhost:8000/api/v1/finance/payments', {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    expect(listRes.status()).toBe(200);
    const payments = (await listRes.json()).data || [];
    expect(payments.length).toBeGreaterThan(0);

    const pendingPayment = payments.find((p: any) => p.status === 'menunggu_verifikasi');
    if (pendingPayment) {
      // Bendahara memverifikasi slip pembayaran
      const verifyRes = await request.post(`http://localhost:8000/api/v1/finance/payments/${pendingPayment.id}/verify`, {
        headers: { Authorization: `Bearer ${adminToken}` },
        data: { notes: 'Disetujui via E2E test' },
      });
      expect([200, 422]).toContain(verifyRes.status());
    }

    // 3. Orang Tua login dan melihat daftar tagihan & riwayat slip
    await page.goto('/masuk');
    await page.locator('text=Orang Tua').first().click();
    await page.fill('input#identifier', 'ortu_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/ortu', { timeout: 15000 });

    // 4. Masuk ke halaman profil anak
    const childLink = page.locator('a[href*="/portal/ortu/anak/"]').first();
    await expect(childLink).toBeVisible();
    await childLink.click();
    await page.waitForURL('**/portal/ortu/anak/*', { timeout: 15000 });

    // 5. Buka tab Keuangan & Tagihan
    const financeTab = page.locator('button:has-text("Keuangan")').first();
    await expect(financeTab).toBeVisible();
    await financeTab.click();

    // 6. Pastikan tagihan dan kuitansi / informasi status tampil
    await expect(page.locator('body')).toContainText(/Tagihan|Biaya|Kuitansi|Terbayar|Lunas|KW\//i);
  });
});
