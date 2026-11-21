import { test, expect } from '@playwright/test';
import { getCivitasToken, getAdminToken } from './helpers/auth-helper';

test.describe('E2E B.2: Propagasi Data Lengkap (Admin / Backend -> Portal Civitas)', () => {
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

  let studentId = '';
  let classId = '';
  let subjectId = '';
  let componentId = '';
  let periodId = '';

  test.beforeAll(async () => {
    // Ambil metadata secara resmi via REST API (bukan via tinker/SQL)
    const { token: teacherToken } = await getCivitasToken('guru.mtk', 'Password123!');

    // 1. Dapatkan kelas guru
    const classesRes = await fetch(`${API_BASE_URL}/me/teacher/classes`, {
      headers: { Authorization: `Bearer ${teacherToken}`, Accept: 'application/json' },
    });
    const classesData = await classesRes.json();
    classId = classesData.data?.[0]?.id;

    // 2. Dapatkan siswa perwalian/ajar (Ahmad Pratama / siswa_001)
    if (classId) {
      const studentsRes = await fetch(`${API_BASE_URL}/me/teacher/classes/${classId}/students`, {
        headers: { Authorization: `Bearer ${teacherToken}`, Accept: 'application/json' },
      });
      const studentsData = await studentsRes.json();
      const ahmad = (studentsData.data || []).find((s: any) => s.studentName?.includes('Ahmad')) || studentsData.data?.[0];
      studentId = ahmad?.studentId;
    }

    // 3. Dapatkan mata pelajaran guru dari jadwal
    const schedulesRes = await fetch(`${API_BASE_URL}/me/teacher/schedules`, {
      headers: { Authorization: `Bearer ${teacherToken}`, Accept: 'application/json' },
    });
    const schedulesData = await schedulesRes.json();
    subjectId = schedulesData.data?.[0]?.subjectId;

    // 4. Dapatkan komponen nilai & periode penilaian
    const compRes = await fetch(`${API_BASE_URL}/grade-components`, {
      headers: { Authorization: `Bearer ${teacherToken}`, Accept: 'application/json' },
    });
    const compData = await compRes.json();
    componentId = compData.data?.[0]?.id;

    const perRes = await fetch(`${API_BASE_URL}/grade-periods`, {
      headers: { Authorization: `Bearer ${teacherToken}`, Accept: 'application/json' },
    });
    const perData = await perRes.json();
    periodId = perData.data?.[0]?.id;
  });

  test('1. Propagasi Nilai: Update nilai siswa di admin/guru terefleksi di Portal Siswa', async ({ page }) => {
    // 1. Guru menginput nilai 97.00 via API POST /api/v1/grades
    const { token: teacherToken } = await getCivitasToken('guru.mtk', 'Password123!');

    const gradeRes = await fetch(`${API_BASE_URL}/grades`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${teacherToken}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        student_id: studentId,
        component_id: componentId,
        period_id: periodId,
        value: 97.00,
        reason: 'Evaluasi Matematika Propagasi API Resmi',
      }),
    });

    expect([200, 201]).toContain(gradeRes.status);

    // 2. Siswa login dan periksa halaman nilai di browser
    await page.goto('/masuk');
    await page.locator('text=Siswa').first().click();
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    await page.goto('/portal/siswa/nilai');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toContain('Matematika');
    expect(bodyText).toMatch(/95\.8|97|Tuntas/);
  });

  test('2. Propagasi Presensi: Pencatatan presensi gerbang harian di admin terefleksi di tab Presensi Portal Ortu', async ({ page }) => {
    // 1. Admin mencatat presensi harian siswa via API POST /api/v1/attendances/daily
    const { token: adminToken } = await getAdminToken('admin_demo', 'Password123!');

    const attRes = await fetch(`${API_BASE_URL}/attendances/daily`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${adminToken}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        student_id: studentId,
        att_date: new Date().toISOString().split('T')[0],
        status: 'izin',
        note: 'Izin Dispensasi Lomba B2',
      }),
    });

    expect([200, 201]).toContain(attRes.status);

    // 2. Ortu login dan periksa tab presensi anak
    await page.goto('/masuk');
    await page.locator('text=Orang Tua').first().click();
    await page.fill('input#identifier', 'ortu_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/ortu', { timeout: 15000 });

    const childLink = page.locator('a[href*="/portal/ortu/anak/"]').first();
    await expect(childLink).toBeVisible();
    await childLink.click();
    await page.waitForURL('**/portal/ortu/anak/*', { timeout: 15000 });

    const presensiTab = page.locator('button:has-text("Presensi")').first();
    await expect(presensiTab).toBeVisible();
    await presensiTab.click();
    await page.waitForTimeout(1000);

    const text = await page.locator('body').innerText();
    expect(text).toMatch(/Izin|Dispensasi/i);
  });

  test('3. Propagasi Jurnal & Tugas: Tugas baru di kelas terefleksi di Portal Siswa', async ({ page }) => {
    // 1. Guru membuat tugas via API POST /api/v1/assignments
    const { token: teacherToken } = await getCivitasToken('guru.mtk', 'Password123!');

    const deadline = new Date(Date.now() + 5 * 86400000).toISOString().replace('T', ' ').substring(0, 19);
    const assignRes = await fetch(`${API_BASE_URL}/assignments`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${teacherToken}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        class_id: classId,
        subject_id: subjectId,
        title: 'Tugas Aljabar B2 Mandiri',
        description: 'Kerjakan modul aljabar bab 3 halaman 45',
        due_at: deadline,
        max_score: 100,
      }),
    });

    expect([200, 201]).toContain(assignRes.status);

    // 2. Siswa login dan periksa daftar tugas
    await page.goto('/masuk');
    await page.locator('text=Siswa').first().click();
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    await page.goto('/portal/siswa/tugas');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    const text = await page.locator('body').innerText();
    expect(text).toContain('Tugas Aljabar B2 Mandiri');
  });

  test('4. Propagasi Materi Pembelajaran: Materi pembelajaran kelas tampil di portal guru/siswa', async ({ request, page }) => {
    // 1. Guru mengunggah materi ajar baru via API POST /api/v1/teaching-materials
    const { token: teacherToken } = await getCivitasToken('guru.mtk', 'Password123!');

    const validPdfBuffer = Buffer.from(
      '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF'
    );

    const matRes = await request.post(`${API_BASE_URL}/teaching-materials`, {
      headers: {
        Authorization: `Bearer ${teacherToken}`,
        Accept: 'application/json',
      },
      multipart: {
        title: 'Modul Geometri dan Ruang B2',
        description: 'Ringkasan rumus bangun ruang sisi datar',
        material_type: 'modul',
        subject_id: subjectId,
        file: {
          name: 'modul_geometri.pdf',
          mimeType: 'application/pdf',
          buffer: validPdfBuffer,
        },
      },
    });

    expect([200, 201]).toContain(matRes.status());

    // 2. Guru verifikasi di portal guru
    await page.goto('/masuk');
    await page.locator('text=Guru').first().click();
    await page.fill('input#identifier', 'guru.mtk');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/guru', { timeout: 15000 });

    await page.goto('/portal/guru/materi');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    const text = await page.locator('body').innerText();
    expect(text).toContain('Modul Geometri dan Ruang B2');
  });

  test('5. Propagasi Jadwal Pelajaran: Jadwal yang terdaftar tampil di Portal Siswa', async ({ page }) => {
    await page.goto('/masuk');
    await page.locator('text=Siswa').first().click();
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    await page.goto('/portal/siswa/jadwal');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    const text = await page.locator('body').innerText();
    expect(text).toMatch(/Matematika|Bahasa Indonesia|IPA|Pendidikan/i);
  });

  test('6. Propagasi Keuangan: Siklus Tagihan Baru -> Slip Ditolak -> Upload Ulang -> Disetujui & Terbit Kuitansi', async ({ request, page }) => {
    // 1. Admin login via API
    const { token: adminToken } = await getAdminToken('admin_demo', 'Password123!');
    // Ortu login via API
    const { token: ortuToken } = await getCivitasToken('ortu_001', 'Password123!');

    // 2. Dapatkan fee type SPP atau pertama
    const feeRes = await fetch(`${API_BASE_URL}/finance/fee-types`, {
      headers: { Authorization: `Bearer ${adminToken}`, Accept: 'application/json' },
    });
    const feeData = await feeRes.json();
    const feeTypeId = feeData.data?.[0]?.id;

    // 3. Admin membuat tagihan baru via API POST /api/v1/finance/invoices
    const dueDate = new Date(Date.now() + 14 * 86400000).toISOString().split('T')[0];
    const invoiceRes = await request.post(`${API_BASE_URL}/finance/invoices`, {
      headers: {
        Authorization: `Bearer ${adminToken}`,
        Accept: 'application/json',
      },
      data: {
        student_id: studentId,
        period: '2026-10',
        due_date: dueDate,
        notes: 'Iuran Praktikum Sains B2',
        items: [
          {
            fee_type_id: feeTypeId,
            description: 'Biaya Praktikum Terpadu B2',
            amount: 350000,
            discount: 0,
          },
        ],
      },
    });

    expect(invoiceRes.status()).toBe(201);
    const invoiceBody = await invoiceRes.json();
    const invoiceId = invoiceBody.data.id;
    expect(invoiceId).toBeTruthy();

    // 4. Ortu upload slip pembayaran pertama (akan ditolak)
    const validPngBuffer = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      'base64'
    );

    const submitRes1 = await request.post(`${API_BASE_URL}/me/invoices/${invoiceId}/payments`, {
      headers: {
        Authorization: `Bearer ${ortuToken}`,
        Accept: 'application/json',
      },
      multipart: {
        amount: 350000,
        channel: 'Bank Mandiri',
        external_reference: `REF-B2-REJ-${Date.now()}`,
        payment_date: new Date().toISOString().split('T')[0],
        proof_file: {
          name: 'bukti_transfer.png',
          mimeType: 'image/png',
          buffer: validPngBuffer,
        },
      },
    });

    expect(submitRes1.status()).toBe(201);
    const slip1 = (await submitRes1.json()).data;
    expect(slip1.id).toBeTruthy();
    expect(slip1.status).toBe('menunggu_verifikasi');

    // 5. Admin bendahara menolak slip pertama via API
    const rejectRes = await request.post(`${API_BASE_URL}/finance/payments/${slip1.id}/reject`, {
      headers: {
        Authorization: `Bearer ${adminToken}`,
        Accept: 'application/json',
      },
      data: {
        reason: 'Bukti transfer buram dan nomor referensi tidak terbaca',
      },
    });
    expect(rejectRes.status()).toBe(200);

    // 6. Ortu buka portal dan verifikasi status slip Ditolak di UI
    await page.goto('/masuk');
    await page.locator('text=Orang Tua').first().click();
    await page.fill('input#identifier', 'ortu_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/ortu', { timeout: 15000 });

    const childLink = page.locator('a[href*="/portal/ortu/anak/"]').first();
    await childLink.click();
    await page.waitForURL('**/portal/ortu/anak/*', { timeout: 15000 });

    const financeTab = page.locator('button:has-text("Keuangan")').first();
    await financeTab.click();
    await page.waitForTimeout(1000);

    let portalText = await page.locator('body').innerText();
    expect(portalText).toMatch(/Ditolak|buram/i);

    // 7. Ortu upload ulang slip yang benar via API
    const submitRes2 = await request.post(`${API_BASE_URL}/me/invoices/${invoiceId}/payments`, {
      headers: {
        Authorization: `Bearer ${ortuToken}`,
        Accept: 'application/json',
      },
      multipart: {
        amount: 350000,
        channel: 'Bank BCA',
        external_reference: `REF-B2-ACC-${Date.now()}`,
        payment_date: new Date().toISOString().split('T')[0],
        proof_file: {
          name: 'bukti_transfer_jelas.png',
          mimeType: 'image/png',
          buffer: validPngBuffer,
        },
      },
    });
    expect(submitRes2.status()).toBe(201);
    const slip2 = (await submitRes2.json()).data;
    expect(slip2.id).toBeTruthy();

    // 8. Admin setujui slip kedua via API
    const verifyRes = await request.post(`${API_BASE_URL}/finance/payments/${slip2.id}/verify`, {
      headers: {
        Authorization: `Bearer ${adminToken}`,
        Accept: 'application/json',
      },
      data: {
        notes: 'Verifikasi slip berhasil, dana terkonfirmasi di rekening',
      },
    });
    expect(verifyRes.status()).toBe(200);

    // 9. Refresh portal ortu dan pastikan status Lunas / Kuitansi resmi muncul di UI
    await page.reload();
    await page.waitForLoadState('domcontentloaded');
    const financeTabReloaded = page.locator('button:has-text("Keuangan")').first();
    if (await financeTabReloaded.isVisible()) {
      await financeTabReloaded.click();
      await page.waitForTimeout(1000);
    }

    portalText = await page.locator('body').innerText();
    expect(portalText).toMatch(/Lunas|Terverifikasi|KW\//i);
  });
});
