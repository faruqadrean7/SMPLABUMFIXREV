import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

test.describe.serial('BATCH 5E-2: Persona Portals, Keuangan & PPDB', () => {
  let guruToken: string;
  let siswaToken: string;
  let ortuToken: string;
  let kepsekToken: string;
  let bendaharaToken: string;
  let adminToken: string;
  let childId: string;

  test.beforeAll(async () => {
    // 1. Generate token untuk masing-masing persona dari user yang sudah ada di database seeder
    guruToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'guru.indo\')->first()->createToken(\'audit-5e2-guru\')->plainTextToken;"'
    ).toString().trim();

    siswaToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'siswa_001\')->first()->createToken(\'audit-5e2-siswa\')->plainTextToken;"'
    ).toString().trim();

    ortuToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'ortu_001\')->first()->createToken(\'audit-5e2-ortu\')->plainTextToken;"'
    ).toString().trim();

    kepsekToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'kepsek_demo\')->first()->createToken(\'audit-5e2-kepsek\')->plainTextToken;"'
    ).toString().trim();

    bendaharaToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'bendahara_demo\')->first()->createToken(\'audit-5e2-bendahara\')->plainTextToken;"'
    ).toString().trim();

    adminToken = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\User::where(\'username\', \'admin_demo\')->first()->createToken(\'audit-5e2-admin\')->plainTextToken;"'
    ).toString().trim();

    // Dapatkan childId untuk ortu_001
    childId = execSync(
      'docker exec -i sistlabum-api php artisan tinker --execute="echo App\\Models\\Guardian::whereHas(\'user\', fn(\\$q) => \\$q->where(\'username\', \'ortu_001\'))->first()->students->first()->id;"'
    ).toString().trim();
  });

  const setupSession = async (
    page: Page,
    token: string,
    username: string,
    roles: string[],
    isAdmin = false
  ) => {
    await page.goto('/masuk');

    if (isAdmin) {
      await page.context().addCookies([
        {
          name: 'sistlabum_admin_session',
          value: '1',
          domain: 'localhost',
          path: '/',
        },
      ]);
    }

    await page.evaluate(
      ({ token, username, roles }) => {
        sessionStorage.setItem('sistlabum_access_token', token);
        localStorage.setItem('sistlabum_refresh_token', 'audit-5e2-dummy-refresh');
        sessionStorage.setItem(
          'sistlabum_user_profile',
          JSON.stringify({
            id: `audit-${username}-id`,
            username,
            roles,
            permissions: ['*'],
          })
        );
      },
      { token, username, roles }
    );
  };

  const waitForLoaders = async (page: Page) => {
    const loader = page.locator('text=/Memuat|Loading/i').first();
    if (await loader.isVisible().catch(() => false)) {
      await loader.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
    }
  };

  // =========================================================================
  // 1. PORTAL GURU & WALI KELAS
  // =========================================================================
  test('1. Portal Guru: Dashboard, Jadwal, Presensi, Jurnal, Nilai, Materi, Wali Kelas & Layanan Pribadi', async ({
    page,
  }) => {
    test.setTimeout(90000);
    await setupSession(page, guruToken, 'guru.indo', ['teacher', 'homeroom']);

    // 1.A. Dashboard Guru
    await page.goto('/portal/guru');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Portal Guru|Rina Marlina|Jadwal Mengajar/i').first()).toBeVisible();

    // 1.B. Jadwal Mengajar
    await page.goto('/portal/guru/jadwal');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Jadwal Mengajar|Senin|Selasa|Rabu|Kamis|Jumat/i').first()).toBeVisible();

    // 1.C. Presensi Siswa
    await page.goto('/portal/guru/presensi');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Presensi Siswa|Kelas|Pertemuan/i').first()).toBeVisible();

    // 1.D. Jurnal Pembelajaran
    await page.goto('/portal/guru/jurnal');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Jurnal Mengajar|Jurnal Pembelajaran|Materi/i').first()).toBeVisible();

    // 1.E. Penilaian / Nilai
    await page.goto('/portal/guru/nilai');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Penilaian|Buku Nilai|Formatif|Sumatif/i').first()).toBeVisible();

    // 1.F. Materi Ajar
    await page.goto('/portal/guru/materi');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Materi Pembelajaran|Bahan Ajar|Materi/i').first()).toBeVisible();

    // 1.G. Portal Wali Kelas
    await page.goto('/portal/guru/wali-kelas');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Wali Kelas|Roster Siswa|Catatan Wali/i').first()).toBeVisible();

    // 1.H. Layanan Pribadi Staf: Presensi-Saya, Izin-Cuti, Slip-Gaji
    await page.goto('/portal/guru/presensi-saya');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Presensi Saya|Riwayat Presensi|Kehadiran/i').first()).toBeVisible();

    await page.goto('/portal/guru/izin-cuti');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Pengajuan Izin|Izin & Cuti/i').first()).toBeVisible();

    await page.goto('/portal/guru/slip-gaji');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Slip Gaji|Rincian Gaji|Bulan/i').first()).toBeVisible();
  });

  // =========================================================================
  // 2. PORTAL SISWA
  // =========================================================================
  test('2. Portal Siswa: Dashboard, Jadwal, Tugas, Nilai, Tagihan & Konseling', async ({
    page,
  }) => {
    test.setTimeout(60000);
    await setupSession(page, siswaToken, 'siswa_001', ['student']);

    // 2.A. Dashboard Siswa
    await page.goto('/portal/siswa');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Ahmad Pratama|Portal Siswa|Halo/i').first()).toBeVisible();

    // 2.B. Jadwal Pelajaran
    await page.goto('/portal/siswa/jadwal');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Jadwal Pelajaran|Jadwal Kelas/i').first()).toBeVisible();

    // 2.C. Tugas Siswa
    await page.goto('/portal/siswa/tugas');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Daftar Tugas|Tugas Belajar|Tenggat/i').first()).toBeVisible();

    // 2.D. Nilai & Rapor Siswa
    await page.goto('/portal/siswa/nilai');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Nilai Akademik|Capaian Belajar|Rapor/i').first()).toBeVisible();

    // 2.E. Tagihan Keuangan Siswa
    await page.goto('/portal/siswa/tagihan');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Status Tagihan|Keuangan Siswa|Tagihan/i').first()).toBeVisible();

    // 2.F. Layanan Konseling & Peringatan Dini
    await page.goto('/portal/siswa/konseling');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Layanan Bimbingan|Konseling Siswa|Janji Temu/i').first()).toBeVisible();
  });

  // =========================================================================
  // 3. PORTAL ORANG TUA / WALI MURID
  // =========================================================================
  test('3. Portal Orang Tua: Dashboard Wali & Tab Profil Anak', async ({
    page,
  }) => {
    test.setTimeout(60000);
    await setupSession(page, ortuToken, 'ortu_001', ['guardian']);

    // 3.A. Dashboard Ortu (menampilkan kartu anak)
    await page.goto('/portal/ortu');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Selamat Datang|Pilih Profil Ananda|Ahmad Pratama/i').first()).toBeVisible();

    // 3.B. Halaman Detail Anak
    if (childId) {
      await page.goto(`/portal/ortu/anak/${childId}`);
      await waitForLoaders(page);
      await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
      await expect(page.locator('text=/Ahmad Pratama|Presensi|Akademik|Tagihan/i').first()).toBeVisible();
    }
  });

  // =========================================================================
  // 4. PORTAL KEPALA SEKOLAH & APPROVALS
  // =========================================================================
  test('4. Portal Eksekutif Kepala Sekolah & Antrean Persetujuan (Approvals)', async ({
    page,
  }) => {
    test.setTimeout(60000);
    await setupSession(page, kepsekToken, 'kepsek_demo', ['principal'], true);

    // 4.A. Dashboard Eksekutif Kepsek
    await page.goto('/portal/kepsek');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Portal Pimpinan Eksekutif|Selamat Datang, kepsek_demo|Dashboard KPI/i').first()).toBeVisible();

    // 4.B. Antrean Persetujuan (Approvals)
    await page.goto('/portal/kepsek/approvals');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Antrean Persetujuan Berkas|Antrean Persetujuan/i').first()).toBeVisible();
  });

  // =========================================================================
  // 5. MODUL KEUANGAN SEKOLAH (FINANCE)
  // =========================================================================
  test('5. Modul Keuangan: Dashboard, Tagihan Siswa, Verifikasi Slip & Tunggakan', async ({
    page,
  }) => {
    test.setTimeout(60000);
    await setupSession(page, bendaharaToken, 'bendahara_demo', ['bendahara'], true);

    // 5.A. Dashboard Keuangan (redirect ke /admin/finance/invoices)
    await page.goto('/admin/finance');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Tagihan & Piutang Siswa|Faktur/i').first()).toBeVisible();

    // 5.B. Daftar Tagihan & Invoice Siswa
    await page.goto('/admin/finance/invoices');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Tagihan & Piutang Siswa|Faktur/i').first()).toBeVisible();

    // 5.C. Antrean Verifikasi Pembayaran (Slip Verification)
    await page.goto('/admin/finance/payments');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Antrean Verifikasi Slip Pembayaran|Slip/i').first()).toBeVisible();

    // 5.D. Laporan Tunggakan Siswa
    await page.goto('/admin/finance/arrears');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Rekap Tunggakan Piutang Siswa|Tunggakan/i').first()).toBeVisible();

    // 5.E. Laporan Keuangan
    await page.goto('/admin/finance/reports');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Laporan Realisasi Pemasukan Keuangan|Pemasukan/i').first()).toBeVisible();
  });

  // =========================================================================
  // 6. PPDB ONLINE & ADMIN PPDB
  // =========================================================================
  test('6. PPDB Publik & Shell Admin PPDB: Gelombang, Pendaftar & Seleksi', async ({
    page,
  }) => {
    test.setTimeout(60000);

    // 6.A. PPDB Publik Landing Page
    await page.goto('/ppdb');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Sistem Penerimaan Murid Baru|PPDB|Laboratorium UM/i').first()).toBeVisible();

    // 6.B. PPDB Halaman Masuk Calon Siswa
    await page.goto('/ppdb/masuk');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Masuk Akun Pendaftar|Nomor Pendaftaran|Email/i').first()).toBeVisible();

    // 6.C. Admin PPDB Gelombang (menggunakan admin session)
    await setupSession(page, adminToken, 'admin_demo', ['super_admin'], true);

    await page.goto('/admin/ppdb/waves');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Konfigurasi Gelombang & Jalur PPDB|Gelombang/i').first()).toBeVisible();

    // 6.D. Admin PPDB Pendaftar
    await page.goto('/admin/ppdb/applicants');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Verifikasi Berkas Calon Siswa|Calon Siswa/i').first()).toBeVisible();

    // 6.E. Admin PPDB Seleksi & Perankingan
    await page.goto('/admin/ppdb/selection');
    await waitForLoaders(page);
    await expect(page.locator('body')).not.toHaveText(/500 Internal Server Error/i);
    await expect(page.locator('text=/Seleksi & Perankingan PPDB|Hasil Seleksi/i').first()).toBeVisible();
  });
});
