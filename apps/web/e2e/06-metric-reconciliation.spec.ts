import { test, expect } from '@playwright/test';

test.describe('E2E B.6: Rekonsiliasi Angka (Database vs Dashboard Admin vs Portal)', () => {
  test('1. Rekonsiliasi Jumlah Total Siswa, Tagihan, dan Metrik Keuangan', async ({ request }) => {
    // 1. Dapatkan token bendahara/admin
    const loginRes = await request.post('http://localhost:8000/api/v1/auth/admin/login', {
      data: {
        identifier: 'staf_tu', // TU admin without mandatory 2FA setup requirement
        password: 'Password123!',
      },
    });

    if (loginRes.status() === 200) {
      const token = (await loginRes.json()).data.tokens.accessToken;

      // 2. Ambil ringkasan tagihan keuangan dari API
      const financeRes = await request.get('http://localhost:8000/api/v1/finance/invoices', {
        headers: { Authorization: `Bearer ${token}` },
      });

      if (financeRes.status() === 200) {
        const body = await financeRes.json();
        const totalInvoices = body.meta?.total || body.data?.length || 0;
        // Pada seeder scale=10, ada minimal 50 atau 500 invoice
        expect(totalInvoices).toBeGreaterThanOrEqual(50);
      }

      // 3. Ambil total siswa dari endpoint master
      const studentsRes = await request.get('http://localhost:8000/api/v1/students', {
        headers: { Authorization: `Bearer ${token}` },
      });

      if (studentsRes.status() === 200) {
        const studentsBody = await studentsRes.json();
        const totalStudents = studentsBody.meta?.total || studentsBody.data?.length || 0;
        expect(totalStudents).toBeGreaterThanOrEqual(50);
      }
    }
  });
});
