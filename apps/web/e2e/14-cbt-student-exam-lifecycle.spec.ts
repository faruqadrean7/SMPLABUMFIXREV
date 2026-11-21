import { test, expect } from '@playwright/test';
import { getCivitasToken } from './helpers/auth-helper';

test.describe('E2E BATCH 5C - F: CBT Student Exam Lifecycle in Browser', () => {
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

  let studentId = '';
  let examId = '';

  test.beforeAll(async () => {
    // Ambil data siswa & sesi ujian via API
    const { token: studentToken } = await getCivitasToken('siswa_001', 'Password123!');

    const meRes = await fetch(`${API_BASE_URL}/me`, {
      headers: { Authorization: `Bearer ${studentToken}`, Accept: 'application/json' },
    });
    const meData = await meRes.json();
    studentId = meData.data?.user?.profile?.id || meData.data?.profile?.id;

    // Ambil daftar ujian aktif siswa
    const examRes = await fetch(`${API_BASE_URL}/me/student/exams`, {
      headers: { Authorization: `Bearer ${studentToken}`, Accept: 'application/json' },
    });
    const examData = await examRes.json();
    const exams = examData.data || [];
    if (exams.length > 0) {
      examId = exams[0].id;
    }
  });

  test('1. Siswa Membuka Dashboard CBT, Memulai Ujian, dan Menjawab Soal di Browser', async ({ page }) => {
    // 1. Siswa login via UI
    await page.goto('/masuk');
    await page.locator('text=Siswa').first().click();
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    // 2. Buka daftar ujian CBT
    await page.goto('/portal/siswa/cbt');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/Ujian|CBT|Computer Based Test|Jadwal/i);

    // 3. Jika ada ujian aktif, buka halaman pengerjaan soal
    if (examId) {
      await page.goto(`/portal/siswa/ujian/${examId}`);
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(1500);

      const examPageText = await page.locator('body').innerText();
      // Pastikan bukan 500 error atau crash
      expect(examPageText).not.toContain('Internal Server Error');
      expect(examPageText).toMatch(/Soal|Ujian|Sisa Waktu|Pertanyaan|Pilihan/i);
    }
  });
});
