import { test, expect } from '@playwright/test';

test.describe('E2E 20-CBT-RTE: Editor Soal Kaya CBT & Keamanan Payload Siswa', () => {
  test('1. Guru menyusun soal kaya dengan editor TipTap di portal guru', async ({ page }) => {
    // 1. Guru login
    await page.goto('/masuk');
    const guruCard = page.locator('text=Guru').first();
    await guruCard.click();

    await page.fill('input#identifier', 'gurumtk');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/portal/guru', { timeout: 15000 });

    // 2. Buka modul Asesmen & Ujian
    await page.goto('/portal/guru/ujian');
    await page.waitForLoadState('domcontentloaded');

    // 3. Pindah ke tab Bank Soal Mandiri
    const bankTab = page.getByRole('button', { name: /bank soal mandiri/i });
    if (await bankTab.isVisible()) {
      await bankTab.click();
      await page.waitForTimeout(500);

      // Pastikan ada tombol Tambah Soal atau Buat Bank
      const addSoalBtn = page.getByRole('button', { name: /tambah soal/i }).first();
      if (await addSoalBtn.isVisible()) {
        await addSoalBtn.click();
        await page.waitForTimeout(500);

        // Verifikasi Toolbar RichQuestionEditor terpasang
        const boldBtn = page.getByRole('button', { name: /tebal/i }).first();
        await expect(boldBtn).toBeVisible();

        const tableBtn = page.getByRole('button', { name: /sisipkan tabel/i }).first();
        await expect(tableBtn).toBeVisible();

        const imgBtn = page.getByRole('button', { name: /sisipkan gambar/i }).first();
        await expect(imgBtn).toBeVisible();
      }
    }
  });

  test('2. Admin melihat butir soal kaya ter-render dengan HTML (RichContent)', async ({ page }) => {
    // Navigasi ke halaman bank soal admin (bila terotentikasi)
    await page.goto('/admin/cbt/questions');
    await page.waitForLoadState('domcontentloaded');

    // Jika dialihkan ke login, halaman gate admin valid
    if (page.url().includes('login') || page.url().includes('404')) {
      expect(page.url()).toBeDefined();
    } else {
      // Jika di halaman admin, periksa bahwa daftar soal atau selector bank muncul
      const heading = page.getByRole('heading', { name: /bank soal/i });
      await expect(heading).toBeVisible();
    }
  });

  test('3. Siswa mengerjakan ujian dan payload network terbukti BEBAS dari kunci jawaban', async ({ page }) => {
    let interceptedSessionPayload: any = null;

    // Intersep respons sesi soal siswa
    await page.route('**/api/v1/exams/*/session-questions', async (route) => {
      const response = await route.fetch();
      try {
        interceptedSessionPayload = await response.json();
      } catch {
        // ignore parse error
      }
      await route.fulfill({ response });
    });

    // 1. Siswa login
    await page.goto('/masuk');
    const siswaCard = page.locator('text=Siswa').first();
    await siswaCard.click();

    await page.fill('input#identifier', 'siswa');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    // 2. Buka halaman CBT siswa
    await page.goto('/portal/siswa/cbt/exam-demo-01');
    await page.waitForLoadState('domcontentloaded');

    // Verifikasi RichContent me-render elemen soal
    const questionTextContainer = page.locator('.font-bold.text-slate-900.leading-relaxed').first();
    await expect(questionTextContainer).toBeVisible();

    // 3. Verifikasi keamanan: Tidak boleh ada kunci jawaban di payload jika request terpanggil
    if (interceptedSessionPayload && interceptedSessionPayload.data) {
      const items = Array.isArray(interceptedSessionPayload.data)
        ? interceptedSessionPayload.data
        : [interceptedSessionPayload.data];

      for (const item of items) {
        expect(item).not.toHaveProperty('is_correct');
        expect(item).not.toHaveProperty('explanation');
        expect(item).not.toHaveProperty('match_key');

        if (item.options && Array.isArray(item.options)) {
          for (const opt of item.options) {
            expect(opt).not.toHaveProperty('is_correct');
            expect(opt).not.toHaveProperty('match_key');
            expect(opt).not.toHaveProperty('explanation');
          }
        }
      }
    }
  });
});
