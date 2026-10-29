import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

test.describe.serial('BATCH 5E-1: Matriks CRUD, Validasi, Izin & Audit Admin Shell', () => {
  let adminToken: string;

  test.beforeAll(async () => {
    // Generate fresh admin token for UI session
    adminToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'admin_demo\')->first()->createToken(\'audit-5e1-crud-token\')->plainTextToken;"'
    ).toString().trim();
  });

  test.afterAll(async () => {
    // Bersihkan semua data uji berprefiks [E2E]
    try {
      execSync(`
        docker exec -i sistlabum-api php artisan tinker --execute="
          App\\Models\\Subject::where('code', 'like', '%E2E%')->forceDelete();
          App\\Models\\SchoolClass::where('name', 'like', '%E2E%')->forceDelete();
          App\\Models\\AcademicYear::where('name', 'like', '%2031%')->forceDelete();
          App\\Models\\FeeType::where('code', 'like', '%E2E%')->forceDelete();
          App\\Models\\Post::where('title', 'like', '%E2E%')->forceDelete();
        "
      `);
    } catch {
      // ignore
    }
  });

  const setupAdminSession = async (page: Page) => {
    await page.goto('/masuk');
    await page.context().addCookies([
      {
        name: 'sistlabum_admin_session',
        value: '1',
        domain: 'localhost',
        path: '/',
      },
    ]);

    await page.evaluate(
      ({ token }) => {
        sessionStorage.setItem('sistlabum_access_token', token);
        localStorage.setItem('sistlabum_refresh_token', 'audit-5e1-dummy-refresh');
        sessionStorage.setItem(
          'sistlabum_user_profile',
          JSON.stringify({
            id: 'admin-audit-5e1-id',
            username: 'admin_demo',
            roles: ['super_admin'],
            permissions: ['*'],
          })
        );
      },
      { token: adminToken }
    );
  };

  const waitForLoaders = async (page: Page) => {
    const loader = page.locator('text=/Memuat|Loading/i').first();
    if (await loader.isVisible().catch(() => false)) {
      await loader.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
    }
  };

  test('1. CRUD Master Data Tahun Ajaran & Proteksi Integritas Referensial', async ({ page }) => {
    test.setTimeout(60000);
    await setupAdminSession(page);
    await page.goto('/admin/master/academic-years');
    await waitForLoaders(page);

    // 1.A. Negative Test: Validasi Form Kosong
    await page.locator('button:has-text("Tambah Tahun Ajaran")').click();
    await page.waitForTimeout(500);
    const dialog = page.locator('[role="dialog"]').first();
    await expect(dialog).toBeVisible();

    await dialog.locator('button[type="submit"]').click();
    await page.waitForTimeout(300);
    await expect(page.locator('text=/Nama tahun ajaran wajib diisi/i')).toBeVisible();

    // 1.B. Create: Isi form valid
    await dialog.locator('input[name="name"], input[placeholder*="202"]').fill('2031/2032');
    await dialog.locator('input[name="start_date"]').fill('2031-07-15');
    await dialog.locator('input[name="end_date"]').fill('2032-06-20');
    await dialog.locator('button[type="submit"]').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);

    // 1.C. Read: Cari di tabel
    const searchInput = page.locator('input[placeholder*="Cari" i]').first();
    await searchInput.fill('2031/2032');
    await page.waitForTimeout(500);
    await expect(page.locator('td:has-text("2031/2032")')).toBeVisible();

    // 1.D. Update: Ubah rekaman
    const editBtn = page.locator('tr:has-text("2031/2032") button[title*="Ubah"]').first();
    await editBtn.click();
    await page.waitForTimeout(500);
    const editDialog = page.locator('[role="dialog"]').first();
    await expect(editDialog).toBeVisible();
    await editDialog.locator('#ay-end, input[name="end_date"]').fill('2032-06-25');
    await editDialog.locator('button[type="submit"]').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);

    // 1.E. Delete: Hapus rekaman non-aktif
    const deleteBtn = page.locator('tr:has-text("2031/2032") button[title*="Hapus"]').first();
    await deleteBtn.click();
    await page.waitForTimeout(400);
    const confirmDialog = page.locator('[role="alertdialog"], [role="dialog"]:has-text("Hapus")');
    await expect(confirmDialog).toBeVisible();
    await confirmDialog.locator('button:has-text("Hapus"), button:has-text("Ya")').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);
    await expect(page.locator('td:has-text("2031/2032")')).toHaveCount(0);

    // 1.F. Referential Integrity: Hapus tahun ajaran aktif dilarang (tombol hapus tidak ditampilkan pada baris aktif)
    await searchInput.fill('');
    await page.waitForTimeout(500);
    const activeRow = page.locator('tr:has-text("Aktif")').first();
    await expect(activeRow).toBeVisible();
    // Tombol hapus tidak tersedia pada baris tahun ajaran aktif
    await expect(activeRow.locator('button[title*="Hapus"]')).toHaveCount(0);
  });

  test('2. CRUD Master Mata Pelajaran & Validasi Duplikasi Kode', async ({ page }) => {
    test.setTimeout(60000);
    await setupAdminSession(page);
    await page.goto('/admin/master/subjects');
    await waitForLoaders(page);

    // 2.A. Create: Tambah mapel baru
    await page.locator('button:has-text("Tambah Mata Pelajaran")').click();
    await page.waitForTimeout(400);
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    await dialog.locator('input[name="code"]').fill('E2E-AST');
    await dialog.locator('input[name="name"]').fill('[E2E] Astronomi Dasar');
    await dialog.locator('button[type="submit"]').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);

    // 2.B. Read: Verifikasi di tabel
    const searchInput = page.locator('input[placeholder*="Cari" i]').first();
    await searchInput.fill('E2E-AST');
    await page.waitForTimeout(500);
    await expect(page.locator('td:has-text("E2E-AST")')).toBeVisible();

    // 2.C. Update: Ubah nama mapel
    const editBtn = page.locator('tr:has-text("E2E-AST") button[title*="Edit"], tr:has-text("E2E-AST") button').first();
    await editBtn.click();
    await page.waitForTimeout(500);
    const editDialog = page.locator('[role="dialog"]').first();
    await editDialog.locator('input[name="name"]').fill('[E2E] Astronomi Lanjut');
    await editDialog.locator('button[type="submit"]').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);
    await expect(page.locator('td:has-text("[E2E] Astronomi Lanjut")')).toBeVisible();

    // 2.D. Negative: Coba tambah dengan kode duplikat E2E-AST
    await page.locator('button:has-text("Tambah Mata Pelajaran")').click();
    await page.waitForTimeout(400);
    const dupDialog = page.locator('[role="dialog"]').first();
    await dupDialog.locator('input[name="code"]').fill('E2E-AST');
    await dupDialog.locator('input[name="name"]').fill('[E2E] Mapel Duplikat');
    await dupDialog.locator('button[type="submit"]').click();
    await page.waitForTimeout(800);
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).toMatch(/sudah digunakan|sudah terdaftar|Gagal/i);

    // Tutup dialog
    const closeBtn = dupDialog.locator('button:has-text("Batal"), button[aria-label="Close"]').first();
    if (await closeBtn.isVisible().catch(() => false)) {
      await closeBtn.click();
    } else {
      await page.keyboard.press('Escape');
    }

    // 2.E. Delete: Hapus mapel
    const deleteBtn = page.locator('tr:has-text("E2E-AST") button[title*="Hapus"], tr:has-text("E2E-AST") button').last();
    await deleteBtn.click();
    await page.waitForTimeout(400);
    const confirmDialog = page.locator('[role="alertdialog"], [role="dialog"]:has-text("Hapus")');
    await confirmDialog.locator('button:has-text("Hapus"), button:has-text("Ya")').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);
    await expect(page.locator('td:has-text("E2E-AST")')).toHaveCount(0);
  });

  test('3. CRUD Master Rombel & Kelas serta Penolakan Hapus Kelas Berisi Siswa', async ({ page }) => {
    test.setTimeout(60000);
    await setupAdminSession(page);
    await page.goto('/admin/master/classes');
    await waitForLoaders(page);

    // 3.A. Create: Tambah kelas baru [E2E] 9-Z
    await page.locator('button:has-text("Buat Rombel Baru")').click();
    await page.waitForTimeout(400);
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    await dialog.locator('input[name="name"]').fill('[E2E] 9-Z');
    await dialog.locator('input[name="capacity"]').fill('30');
    // Pilih select opsi pertama jika ada
    const selects = dialog.locator('select');
    const selectCount = await selects.count();
    for (let i = 0; i < selectCount; i++) {
      const sel = selects.nth(i);
      const options = await sel.locator('option').all();
      if (options.length > 1) {
        const val = await options[1].getAttribute('value');
        if (val) await sel.selectOption(val);
      }
    }

    await dialog.locator('button[type="submit"]').click();
    await page.waitForTimeout(1000);
    await waitForLoaders(page);

    // 3.B. Read & Search
    const searchInput = page.locator('input[placeholder*="Cari" i]').first();
    await searchInput.fill('[E2E] 9-Z');
    await page.waitForTimeout(500);
    await expect(page.locator('td:has-text("[E2E] 9-Z")')).toBeVisible();

    // 3.C. Delete: Hapus kelas kosong [E2E] 9-Z
    const deleteBtn = page.locator('tr:has-text("[E2E] 9-Z") button[title*="Hapus"], tr:has-text("[E2E] 9-Z") button').last();
    await deleteBtn.click();
    await page.waitForTimeout(400);
    const confirmDialog = page.locator('[role="alertdialog"], [role="dialog"]:has-text("Hapus")');
    await confirmDialog.locator('button:has-text("Hapus"), button:has-text("Ya")').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);
    await expect(page.locator('td:has-text("[E2E] 9-Z")')).toHaveCount(0);

    // 3.D. Referential Integrity: Hapus kelas 7-A (yang memiliki siswa aktif) harus ditolak
    await searchInput.fill('7-A');
    await page.waitForTimeout(500);
    const class7ARow = page.locator('tr:has-text("7-A")').first();
    if (await class7ARow.isVisible()) {
      const classDeleteBtn = class7ARow.locator('button[title*="Hapus"], button').last();
      await classDeleteBtn.click();
      await page.waitForTimeout(400);
      const classConfirm = page.locator('[role="alertdialog"], [role="dialog"]:has-text("Hapus")');
      if (await classConfirm.isVisible()) {
        await classConfirm.locator('button:has-text("Hapus"), button:has-text("Ya")').click();
        await page.waitForTimeout(800);
        // Harus ada pesan penolakan yang jelas
        const bodyText = await page.locator('body').innerText();
        expect(bodyText).toMatch(/siswa aktif|memiliki siswa|Gagal menghapus/i);
      }
    }
  });

  test('4. CRUD Keuangan Jenis Biaya & CMS Berita Sekolah', async ({ page }) => {
    test.setTimeout(60000);
    await setupAdminSession(page);

    // 4.A. Keuangan: Jenis Biaya
    await page.goto('/admin/finance/fee-types');
    await waitForLoaders(page);

    await page.locator('button:has-text("Tambah Biaya")').click();
    await page.waitForTimeout(400);
    const feeModal = page.locator('div.fixed:has-text("Tambah Jenis Biaya")');
    await expect(feeModal).toBeVisible();

    await feeModal.locator('input[placeholder*="SPP-SMP" i], input').nth(0).fill('E2E-FEE-ROBOTIK');
    await feeModal.locator('input[placeholder*="SPP Bulanan SMP" i], input').nth(1).fill('[E2E] Biaya Ekskul Robotik');
    await feeModal.locator('input[type="number"]').fill('150000');
    await feeModal.locator('button[type="submit"]').click();

    await page.waitForTimeout(1000);
    await waitForLoaders(page);
    await expect(page.locator('td:has-text("E2E-FEE-ROBOTIK")')).toBeVisible();

    // Hapus jenis biaya (menggunakan browser window.confirm)
    page.once('dialog', async (dialog) => {
      await dialog.accept();
    });
    const delFeeBtn = page.locator('tr:has-text("E2E-FEE-ROBOTIK") button').last();
    await delFeeBtn.click();
    await page.waitForTimeout(1000);

    // 4.B. CMS Berita
    await page.goto('/admin/cms');
    await waitForLoaders(page);

    const postsTab = page.locator('button:has-text("Berita & Artikel")').first();
    await expect(postsTab).toBeVisible();
    await postsTab.click();
    await page.waitForTimeout(500);

    await page.locator('button:has-text("Tulis Berita Baru")').first().click();
    await page.waitForTimeout(500);

    const cmsModal = page.locator('div.fixed:has-text("Berita")');
    await expect(cmsModal).toBeVisible();
    await cmsModal.locator('#post-title').fill('[E2E] Prestasi Robotik Nasional');
    await cmsModal.locator('#post-content').fill('Siswa SISTLABUM berhasil meraih medali emas robotika nasional 2026.');
    await cmsModal.locator('button[type="submit"]').click();
    await page.waitForTimeout(1000);
    await waitForLoaders(page);
    await expect(page.locator('text=[E2E] Prestasi Robotik Nasional')).toBeVisible();
  });

  test('5. Verifikasi Otorisasi Penolakan (403) Civitas & Jejak Audit Log Admin', async ({ page }) => {
    test.setTimeout(30000);

    // 5.A. Token civitas siswa mencoba mutasi admin via API -> 403 Forbidden
    const studentToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'siswa_001\')->first()->createToken(\'test-student\')->plainTextToken;"'
    ).toString().trim();

    const mutateRes = await fetch('http://localhost:8000/api/v1/academic-years', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${studentToken}`,
        Accept: 'application/json',
      },
      body: JSON.stringify({
        name: '2040/2041',
        start_date: '2040-07-01',
        end_date: '2041-06-30',
      }),
    });

    expect(mutateRes.status).toBe(403);
    const body403 = await mutateRes.json();
    expect(body403.error?.code).toBe('FORBIDDEN');

    // 5.B. Verifikasi Audit Log: Cek bahwa aksi-aksi admin tercatat di tabel audit_logs
    const auditCount = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\AuditLog::whereHas(\'actor\', fn(\\$q) => \\$q->where(\'username\', \'admin_demo\'))->count();"'
    ).toString().trim();

    expect(parseInt(auditCount, 10)).toBeGreaterThan(0);
  });
});
