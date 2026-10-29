import { test, expect } from '@playwright/test';

test.describe('E2E B.5: Keamanan Akses Lintas Entitas & Pencegahan IDOR', () => {
  test('1. Ortu A dilarang mengakses API rincian anak milik Ortu B (HTTP 403 / 404)', async ({ request }) => {
    // 1. Login sebagai ortu_001
    const loginOrtuA = await request.post('http://localhost:8000/api/v1/auth/login', {
      data: {
        identifier: 'ortu_001',
        password: 'Password123!',
      },
    });
    expect(loginOrtuA.status()).toBe(200);
    const tokenOrtuA = (await loginOrtuA.json()).data.tokens.accessToken;

    // 2. Dapatkan anak milik ortu_002
    const loginOrtuB = await request.post('http://localhost:8000/api/v1/auth/login', {
      data: {
        identifier: 'ortu_002',
        password: 'Password123!',
      },
    });
    expect(loginOrtuB.status()).toBe(200);
    const tokenOrtuB = (await loginOrtuB.json()).data.tokens.accessToken;

    const childrenBRes = await request.get('http://localhost:8000/api/v1/me/children', {
      headers: { Authorization: `Bearer ${tokenOrtuB}` },
    });
    expect(childrenBRes.status()).toBe(200);
    const childrenB = (await childrenBRes.json()).data;
    expect(childrenB.length).toBeGreaterThan(0);
    const childBId = childrenB[0].id;

    // 3. Ortu A mencoba memanggil API detail anak B menggunakan token Ortu A -> DITOLAK
    const idorRes = await request.get(`http://localhost:8000/api/v1/me/children/${childBId}`, {
      headers: { Authorization: `Bearer ${tokenOrtuA}` },
    });
    expect([403, 404]).toContain(idorRes.status());
  });

  test('2. Siswa dilarang mengakses daftar tagihan admin atau memodifikasi data siswa lain (HTTP 403)', async ({ request }) => {
    const loginSiswaA = await request.post('http://localhost:8000/api/v1/auth/login', {
      data: {
        identifier: 'siswa_001',
        password: 'Password123!',
      },
    });
    expect(loginSiswaA.status()).toBe(200);
    const tokenSiswaA = (await loginSiswaA.json()).data.tokens.accessToken;

    // Siswa dilarang mengakses endpoint finance invoice admin
    const unauthorizedFinanceRes = await request.get('http://localhost:8000/api/v1/finance/invoices', {
      headers: { Authorization: `Bearer ${tokenSiswaA}` },
    });
    expect([401, 403]).toContain(unauthorizedFinanceRes.status());

    // Siswa dilarang membuat atau memodifikasi master siswa
    const unauthorizedStudentWriteRes = await request.post('http://localhost:8000/api/v1/students', {
      headers: { Authorization: `Bearer ${tokenSiswaA}` },
      data: { name: 'Illegal Student', nisn: '9999999999' },
    });
    expect([401, 403]).toContain(unauthorizedStudentWriteRes.status());
  });

  test('3. Pengguna non-bendahara (Guru / Siswa) dilarang memverifikasi slip pembayaran (HTTP 403)', async ({ request }) => {
    const loginGuru = await request.post('http://localhost:8000/api/v1/auth/login', {
      data: {
        identifier: 'guru.mtk',
        password: 'Password123!',
      },
    });
    expect(loginGuru.status()).toBe(200);
    const tokenGuru = (await loginGuru.json()).data.tokens.accessToken;

    // Guru mencoba verifikasi pembayaran tanpa hak finance.payment.verify
    const dummyPaymentId = '00000000-0000-0000-0000-000000000001';
    const verifyRes = await request.post(`http://localhost:8000/api/v1/finance/payments/${dummyPaymentId}/verify`, {
      headers: { Authorization: `Bearer ${tokenGuru}` },
    });
    expect(verifyRes.status()).toBe(403);
  });
});
