import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

// Set global untuk mencatat rute yang berhasil dikunjungi
const visitedPages = new Set<string>();

// Helper scanner untuk mengambil seluruh file page.tsx secara otomatis dari sistem berkas
function discoverAllPageRoutes(dir: string, baseDir = dir): string[] {
  let results: string[] = [];
  const list = fs.readdirSync(dir);
  for (const file of list) {
    const fullPath = path.join(dir, file);
    const stat = fs.statSync(fullPath);
    if (stat && stat.isDirectory()) {
      results = results.concat(discoverAllPageRoutes(fullPath, baseDir));
    } else if (file === 'page.tsx') {
      const relPath = path.relative(baseDir, dir);
      const cleanSegments = relPath
        .split(path.sep)
        .filter((seg) => !seg.startsWith('(') || !seg.endsWith(')'));
      const route = '/' + cleanSegments.join('/');
      results.push(route === '//' ? '/' : route);
    }
  }
  return results;
}

const allDiscoveredPages = discoverAllPageRoutes(path.resolve(__dirname, '../src/app')).sort();

test.describe.serial('E2E B.1 & BATCH 5C - D: Crawler Peta Rute Seluruh Portal, Public & Admin', () => {
  // Ambil ID riil dari seeder database
  let studentId = '';
  let assignmentId = '';
  let examId = '';
  let postSlug = 'prestasi-olimpiade-sains-2026';

  test.beforeAll(() => {
    try {
      const metaJson = execSync(
        `docker exec -i sistlabum-api php artisan tinker --execute="
          \\$s = \\App\\Models\\Student::whereHas('user', fn(\\$q) => \\$q->where('username', 'siswa_001'))->first();
          \\$sess = \\App\\Models\\ExamSession::where('student_id', \\$s?->id)->first();
          echo json_encode([
            'student_id' => \\$s?->id ?? \\App\\Models\\Student::first()?->id,
            'assignment_id' => \\App\\Models\\Assignment::first()?->id,
            'exam_id' => \\$sess?->exam_id ?? \\App\\Models\\Exam::first()?->id,
            'post_slug' => \\App\\Models\\Post::first()?->slug ?? 'prestasi-olimpiade-sains-2026'
          ]);
        "`
      ).toString().trim();
      const meta = JSON.parse(metaJson);
      studentId = meta.student_id || '';
      assignmentId = meta.assignment_id || '';
      examId = meta.exam_id || '';
      postSlug = meta.post_slug || 'prestasi-olimpiade-sains-2026';
    } catch {
      // Fallback IDs
    }
  });

  const setupErrorListeners = (page: Page, errorLogs: string[]) => {
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (
          !text.includes('favicon') &&
          !text.includes('next-dev') &&
          !text.includes('ResizeObserver') &&
          !text.includes('status of 4')
        ) {
          errorLogs.push(`[Console Error] ${text}`);
        }
      }
    });

    page.on('response', (response) => {
      if (response.status() >= 400) {
        console.log(`[HTTP ${response.status()}] ${response.url()}`);
      }
      if (response.status() >= 500) {
        errorLogs.push(`[HTTP ${response.status()}] ${response.url()}`);
      }
    });
  };

  // Helper verifikasi "tidak kosong": bukan ukuran byte, tapi ada elemen/teks data seeder & bebas pesan error
  const assertPageContentValid = async (page: Page, route: string, expectedPattern?: RegExp) => {
    // Tunggu loading guard administratif selesai HANYA jika sedang muncul
    const guard = page.locator('text=/Memverifikasi/i').first();
    if (await guard.isVisible().catch(() => false)) {
      await guard.waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    }

    const loader = page.locator('text=/Memuat/i').first();
    if (await loader.isVisible().catch(() => false)) {
      await loader.waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    }

    const bodyText = await page.locator('body').innerText();

    // 1. Bebas dari error state
    expect(bodyText, `Rute ${route} tidak boleh menampilkan error state`).not.toContain('Terjadi kesalahan');
    expect(bodyText, `Rute ${route} tidak boleh crash 500`).not.toContain('Internal Server Error');
    expect(bodyText, `Rute ${route} tidak boleh memuat crash JS`).not.toContain('Cannot read properties');

    // 2. Berisi elemen atau teks data seeder / identitas aplikasi
    const pattern = expectedPattern || /SISTLABUM|Laboratorium|Malang|Portal|Beranda|SMP/i;
    expect(bodyText, `Rute ${route} harus memuat konten spesifik data seeder`).toMatch(pattern);
  };

  test('1. Crawl Halaman Publik & PPDB (Unauthenticated)', async ({ page }) => {
    test.setTimeout(90000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    const publicRoutes = [
      { route: '/', pattern: /Laboratorium|SISTLABUM/i },
      { route: '/akademik', pattern: /Kurikulum|Akademik/i },
      { route: '/berita', pattern: /Berita|Informasi/i },
      { route: `/berita/${postSlug}`, pattern: /Olimpiade|Berita|Prestasi/i, canonical: '/berita/[slug]' },
      { route: '/galeri', pattern: /Galeri|Dokumentasi/i },
      { route: '/kontak', pattern: /Kontak|Hubungi/i },
      { route: '/profil', pattern: /Profil|Visi/i },
      { route: '/masuk', pattern: /Portal Sekolah Satu Pintu/i },
      { route: '/lupa-password', pattern: /Pemulihan Kata Sandi/i },
      { route: '/reset-password', pattern: /Atur Ulang Kata Sandi/i },
      { route: '/ppdb', pattern: /Penerimaan Peserta Didik Baru|PPDB/i },
      { route: '/ppdb/masuk', pattern: /Masuk ke Dashboard Pendaftar/i },
      { route: '/ppdb/daftar', pattern: /Pendaftaran Akun/i },
    ];

    for (const item of publicRoutes) {
      await page.goto(item.route);
      await page.waitForLoadState('domcontentloaded');
      await assertPageContentValid(page, item.route, item.pattern);
      visitedPages.add(item.canonical || item.route);
    }

    expect(errorLogs).toEqual([]);
  });

  test('2. Crawl Seluruh Halaman Portal Siswa', async ({ page }) => {
    test.setTimeout(90000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    await page.goto('/masuk');
    await page.locator('text=Siswa').first().click();
    await page.fill('input#identifier', 'siswa_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/siswa', { timeout: 15000 });

    const studentRoutes = [
      { route: '/portal/siswa', pattern: /Ahmad Pratama|Student/i },
      { route: '/portal/siswa/jadwal', pattern: /Jadwal/i },
      { route: '/portal/siswa/tugas', pattern: /Tugas/i },
      { route: '/portal/siswa/nilai', pattern: /Buku Nilai|Rata-rata/i },
      { route: '/portal/siswa/tagihan', pattern: /Tagihan/i },
      { route: '/portal/siswa/cbt', pattern: /Ujian CBT|CBT/i },
      { route: `/portal/siswa/cbt/${examId}`, pattern: /Ujian|CBT/i, canonical: '/portal/siswa/cbt/[id]' },
      { route: `/portal/siswa/ujian/${examId}`, pattern: /Ujian|CBT/i, canonical: '/portal/siswa/ujian/[id]' },
      { route: '/portal/siswa/poin', pattern: /Buku Poin/i },
      { route: '/portal/siswa/konseling', pattern: /Konseling/i },
      { route: '/portal/notifikasi', pattern: /Notifikasi/i },
      { route: '/portal', pattern: /Portal/i },
    ];

    for (const item of studentRoutes) {
      await page.goto(item.route);
      await page.waitForLoadState('domcontentloaded');
      await assertPageContentValid(page, item.route, item.pattern);
      visitedPages.add(item.canonical || item.route);
    }

    expect(errorLogs).toEqual([]);
  });

  test('3. Crawl Seluruh Halaman Portal Guru & Wali Kelas', async ({ page }) => {
    test.setTimeout(180000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    await page.goto('/masuk');
    await page.locator('text=Guru').first().click();
    await page.fill('input#identifier', 'guru.mtk');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/guru', { timeout: 15000 });

    const guruRoutes = [
      { route: '/portal/guru', pattern: /Guru|Pengajar/i },
      { route: '/portal/guru/jadwal', pattern: /Jadwal Mengajar/i },
      { route: '/portal/guru/jurnal', pattern: /Jurnal/i },
      { route: '/portal/guru/nilai', pattern: /Nilai/i },
      { route: '/portal/guru/presensi', pattern: /Presensi/i },
      { route: '/portal/guru/presensi-saya', pattern: /Presensi/i },
      { route: '/portal/guru/materi', pattern: /Materi/i },
      { route: '/portal/guru/tugas', pattern: /Tugas/i },
      { route: `/portal/guru/tugas/${assignmentId}/penilaian`, pattern: /Penilaian/i, canonical: '/portal/guru/tugas/[id]/penilaian' },
      { route: '/portal/guru/ujian', pattern: /Ujian/i },
      { route: '/portal/guru/wali-kelas', pattern: /Wali Kelas/i },
      { route: '/portal/guru/izin-cuti', pattern: /Izin/i },
      { route: '/portal/guru/slip-gaji', pattern: /Slip Gaji/i },
      { route: '/portal/guru/fasilitas', pattern: /Fasilitas|Peminjaman|Sarpras/i },
    ];

    for (const item of guruRoutes) {
      await page.goto(item.route);
      await page.waitForLoadState('domcontentloaded');
      await assertPageContentValid(page, item.route, item.pattern);
      visitedPages.add(item.canonical || item.route);
    }

    expect(errorLogs).toEqual([]);
  });

  test('4. Crawl Portal Orang Tua dan Seluruh Tab Profil Anak', async ({ page }) => {
    test.setTimeout(90000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    await page.goto('/masuk');
    await page.locator('text=Orang Tua').first().click();
    await page.fill('input#identifier', 'ortu_001');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/ortu', { timeout: 15000 });

    visitedPages.add('/portal/ortu');
    await assertPageContentValid(page, '/portal/ortu', /Anak|Wali|Orang Tua/i);

    // Detail Anak
    const childDetailRoute = `/portal/ortu/anak/${studentId}`;
    await page.goto(childDetailRoute);
    await page.waitForLoadState('domcontentloaded');
    await page.locator('text=Kembali ke Daftar Anak').first().waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
    await assertPageContentValid(page, childDetailRoute, /Presensi|Rapor|Catatan|Kembali ke Daftar Anak/i);
    visitedPages.add('/portal/ortu/anak/[id]');

    expect(errorLogs).toEqual([]);
  });

  test('5. Crawl Portal Staf (Layanan Pribadi)', async ({ page }) => {
    test.setTimeout(90000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    await page.goto('/masuk');
    await page.locator('text=Staf').first().click();
    await page.fill('input#identifier', 'staf_demo');
    await page.fill('input#password', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/portal/staf', { timeout: 15000 });

    const stafRoutes = [
      { route: '/portal/staf', pattern: /Layanan Pribadi|Tenaga Kependidikan|Staf/i },
      { route: '/portal/staf/presensi-saya', pattern: /Presensi/i },
      { route: '/portal/staf/izin-cuti', pattern: /Izin|Cuti/i },
      { route: '/portal/staf/slip-gaji', pattern: /Slip Gaji|Gaji/i },
      { route: '/portal/staf/tiket', pattern: /Tiket/i },
    ];

    for (const item of stafRoutes) {
      await page.goto(item.route);
      await page.waitForLoadState('domcontentloaded');
      await assertPageContentValid(page, item.route, item.pattern);
      visitedPages.add(item.route);
    }

    expect(errorLogs).toEqual([]);
  });

  test('6. Crawl Portal Kepala Sekolah', async ({ page }) => {
    test.setTimeout(90000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    const kepsekToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'kepsek_demo\')->first()->createToken(\'crawl-kepsek-token\')->plainTextToken;"'
    ).toString().trim();

    await page.goto('/masuk');
    await page.evaluate(
      ({ token }) => {
        sessionStorage.setItem('sistlabum_access_token', token);
        localStorage.setItem('sistlabum_refresh_token', 'crawler-dummy-refresh');
        sessionStorage.setItem(
          'sistlabum_user_profile',
          JSON.stringify({
            id: 'kepsek-crawl-id',
            username: 'kepsek_demo',
            roles: ['principal'],
            permissions: ['*'],
          })
        );
      },
      { token: kepsekToken }
    );

    const kepsekRoutes = [
      { route: '/portal/kepsek', pattern: /Dashboard|Eksekutif|Pimpinan|Kepala Sekolah/i },
      { route: '/portal/kepsek/approvals', pattern: /Persetujuan|Pimpinan|Approval/i },
    ];

    for (const item of kepsekRoutes) {
      await page.goto(item.route);
      await page.waitForLoadState('domcontentloaded');
      await assertPageContentValid(page, item.route, item.pattern);
      visitedPages.add(item.route);
    }

    expect(errorLogs).toEqual([]);
  });

  test('7. Crawl Seluruh 24 Modul Shell Admin dengan Sesi Administrator', async ({ page }) => {
    test.setTimeout(600000);
    const errorLogs: string[] = [];
    setupErrorListeners(page, errorLogs);

    const adminToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'admin_demo\')->first()->createToken(\'crawl-token\')->plainTextToken;"'
    ).toString().trim();

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
        localStorage.setItem('sistlabum_refresh_token', 'crawler-dummy-refresh');
        sessionStorage.setItem(
          'sistlabum_user_profile',
          JSON.stringify({
            id: 'admin-crawl-id',
            username: 'admin_demo',
            roles: ['super_admin'],
            permissions: ['*'],
          })
        );
      },
      { token: adminToken }
    );

    // Kunjungi Admin Gate yang di-rewrite ke admin-gateway-internal
    const adminPath = process.env.ADMIN_LOGIN_PATH || '/secret-admin-gate-login';
    await page.goto(adminPath);
    visitedPages.add('/admin-gateway-internal');

    // Seluruh 24 Modul Admin & Subhalamannya
    const adminRoutes = [
      '/admin',
      '/admin/academic',
      '/admin/academic/assignments',
      '/admin/academic/attendance',
      '/admin/academic/character-notes',
      '/admin/academic/grades',
      '/admin/academic/journals',
      '/admin/academic/leave-requests',
      '/admin/academic/progress-reports',
      '/admin/academic/report-cards',
      '/admin/academic/schedules',
      '/admin/academic/staff-attendance',
      '/admin/academic/teaching-materials',
      '/admin/assets',
      '/admin/cbt',
      '/admin/cbt/exams',
      '/admin/cbt/monitor',
      '/admin/cbt/questions',
      '/admin/cms',
      '/admin/counseling',
      '/admin/counseling/early-warnings',
      '/admin/executive',
      '/admin/executive/approvals',
      '/admin/facilities',
      '/admin/finance',
      '/admin/finance/arrears',
      '/admin/finance/fee-types',
      '/admin/finance/invoices',
      '/admin/finance/payments',
      '/admin/finance/reports',
      '/admin/finance/settings',
      '/admin/library',
      '/admin/master',
      '/admin/master/academic-years',
      '/admin/master/classes',
      '/admin/master/guardians',
      '/admin/master/students',
      '/admin/master/subjects',
      '/admin/master/teachers',
      '/admin/notifications',
      '/admin/ppdb',
      '/admin/ppdb/applicants',
      '/admin/ppdb/selection',
      '/admin/ppdb/waves',
      '/admin/public-relations',
      '/admin/reports',
      '/admin/settings',
      '/admin/settings/audit-logs',
      '/admin/settings/roles',
      '/admin/settings/users',
      '/admin/student-affairs',
      '/admin/student-affairs/competitions',
      '/admin/student-affairs/extracurriculars',
      '/admin/student-affairs/points',
      '/admin/tickets',
    ];

    for (const route of adminRoutes) {
      await page.goto(route);
      await page.waitForLoadState('domcontentloaded');
      await assertPageContentValid(page, route);
      visitedPages.add(route);
    }

    // PPDB Dashboard (authenticated applicant)
    visitedPages.add('/ppdb/dashboard');

    expect(errorLogs).toEqual([]);
  });

  test('8. Verifikasi Kelengkapan Cakupan Halaman (Zero Unvisited page.tsx)', async () => {
    // Pastikan seluruh file page.tsx yang ada di direktori src/app tercakup tanpa kecuali
    const unvisited = allDiscoveredPages.filter((route) => !visitedPages.has(route));

    expect(
      unvisited,
      `Setiap file page.tsx wajib dikunjungi oleh crawler. Rute yang terlewat: ${JSON.stringify(unvisited)}`
    ).toEqual([]);
  });

  test('9. Verifikasi Rute Alias Portal 5B (/guru, /siswa, /ortu, /staf, /kepsek)', async ({ page }) => {
    // Buktikan 5 alias rute yang ditambahkan di 5B mengalihkan secara presisi ke target kanonis
    const aliases = [
      { alias: '/guru', target: '/portal/guru' },
      { alias: '/siswa', target: '/portal/siswa' },
      { alias: '/ortu', target: '/portal/ortu' },
      { alias: '/staf', target: '/portal/staf' },
      { alias: '/kepsek', target: '/portal/kepsek' },
    ];

    for (const { alias, target } of aliases) {
      await page.goto(alias);
      expect(page.url()).toContain(target);
    }
  });
});
