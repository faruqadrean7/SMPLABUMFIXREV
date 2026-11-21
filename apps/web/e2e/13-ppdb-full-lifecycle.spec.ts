import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { getAdminToken } from './helpers/auth-helper';

test.describe('E2E BATCH 5C - F: PPDB Full Lifecycle End-to-End', () => {
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

  test.beforeEach(async () => {
    try {
      execSync('docker exec sistlabum-api php artisan cache:clear');
    } catch (e) {
      // ignore
    }
  });

  let waveId = '';
  let trackId = '';
  let applicationId = '';
  let applicantToken = '';
  const testPhone = `0812${Math.floor(10000000 + Math.random() * 90000000)}`;
  const testEmail = `calon_${Date.now()}@example.com`;
  const testNik = `3579${Math.floor(100000000000 + Math.random() * 900000000000)}`;

  test('1. Alur Lengkap PPDB: Daftar Akun -> OTP -> Form 4 Langkah -> Verifikasi -> Ranking -> Daftar Ulang -> Enroll', async ({ request }) => {
    // 1. Ambil gelombang dan jalur aktif publik
    const waveRes = await request.get(`${API_BASE_URL}/public/ppdb/waves/active`);
    expect(waveRes.status()).toBe(200);
    const waveBody = await waveRes.json();
    const wave = waveBody.data;
    waveId = wave.id;
    trackId = wave.tracks?.[0]?.id;
    expect(waveId).toBeTruthy();

    // 2. Daftar akun calon siswa baru
    const regRes = await request.post(`${API_BASE_URL}/ppdb/register`, {
      data: {
        full_name: 'Budi Santoso Calon Siswa',
        email: testEmail,
        phone: testPhone,
        password: 'Password123!',
        password_confirmation: 'Password123!',
      },
    });

    expect([200, 201]).toContain(regRes.status());
    const regBody = await regRes.json();
    let otpCode = regBody.data?.otp;
    if (!otpCode) {
      try {
        const out = execSync(`docker exec sistlabum-api php artisan tinker --execute="echo Cache::get('ppdb_otp:${testEmail}')['otp'] ?? '';"`, { encoding: 'utf-8' }).trim();
        otpCode = out;
      } catch (e) {
        // ignore
      }
    }
    expect(otpCode).toBeTruthy();

    // 3. Verifikasi OTP
    const otpRes = await request.post(`${API_BASE_URL}/ppdb/verify-otp`, {
      data: {
        email: testEmail,
        otp: otpCode,
      },
    });

    expect(otpRes.status()).toBe(200);
    const otpBody = await otpRes.json();
    applicantToken = otpBody.data?.accessToken || otpBody.data?.token || otpBody.data?.tokens?.accessToken;
    expect(applicantToken).toBeTruthy();

    // 4. Buat aplikasi formulir pendaftaran
    const appRes = await request.post(`${API_BASE_URL}/ppdb/applications`, {
      headers: { Authorization: `Bearer ${applicantToken}` },
      data: {
        wave_id: waveId,
        track_id: trackId,
        nik: testNik,
        full_name: 'Budi Santoso Calon Siswa',
        birth_date: '2013-05-15',
      },
    });

    expect(appRes.status()).toBe(201);
    const appBody = await appRes.json();
    applicationId = appBody.data.id;
    expect(applicationId).toBeTruthy();

    // 5. Simpan Step 1 (Data Pribadi)
    const step1Res = await request.patch(`${API_BASE_URL}/ppdb/applications/${applicationId}/steps/1`, {
      headers: { Authorization: `Bearer ${applicantToken}` },
      data: {
        gender: 'L',
        birth_place: 'Malang',
        religion: 'Islam',
        address: 'Jl. Veteran No. 1 Malang',
        phone: testPhone,
      },
    });
    expect(step1Res.status()).toBe(200);

    // 6. Simpan Step 2 (Data Orang Tua / Wali)
    const step2Res = await request.patch(`${API_BASE_URL}/ppdb/applications/${applicationId}/steps/2`, {
      headers: { Authorization: `Bearer ${applicantToken}` },
      data: {
        parents: [
          {
            parent_type: 'ayah',
            name: 'Santoso Senior',
            occupation: 'Wiraswasta',
            phone: testPhone,
          },
          {
            parent_type: 'ibu',
            name: 'Siti Aminah',
            occupation: 'Guru',
            phone: testPhone,
          },
        ],
      },
    });
    expect(step2Res.status()).toBe(200);

    // 7. Simpan Step 3 (Sekolah Asal)
    const step3Res = await request.patch(`${API_BASE_URL}/ppdb/applications/${applicationId}/steps/3`, {
      headers: { Authorization: `Bearer ${applicantToken}` },
      data: {
        school: {
          name: 'SD Laboratorium UM',
          npsn: '20533812',
          address: 'Jl. Simpang Bogor Malang',
        },
      },
    });
    expect(step3Res.status()).toBe(200);

    // 8. Simpan Step 4 (Pilihan Jalur & Jurusan)
    const step4Res = await request.patch(`${API_BASE_URL}/ppdb/applications/${applicationId}/steps/4`, {
      headers: { Authorization: `Bearer ${applicantToken}` },
      data: {
        track_id: trackId,
        major_choice: 'MIPA',
      },
    });
    expect(step4Res.status()).toBe(200);

    // 9. Submit Final Aplikasi Pendaftaran
    const submitRes = await request.post(`${API_BASE_URL}/ppdb/applications/${applicationId}/submit`, {
      headers: { Authorization: `Bearer ${applicantToken}` },
    });
    expect(submitRes.status()).toBe(200);
    const submitBody = await submitRes.json();
    expect(submitBody.data.status).toBe('submitted');

    // 10. Admin Verifikasi Berkas Aplikasi
    const { token: adminToken } = await getAdminToken('admin_demo', 'Password123!');
    const verifyRes = await request.patch(`${API_BASE_URL}/ppdb/applications/${applicationId}/verify`, {
      headers: { Authorization: `Bearer ${adminToken}` },
      data: {
        status: 'verified',
        note: 'Berkas lengkap dan sesuai kriteria',
      },
    });
    expect(verifyRes.status()).toBe(200);

    // 11. Admin Mengambil Keputusan Seleksi (diterima)
    const decisionRes = await request.patch(`${API_BASE_URL}/ppdb/applications/${applicationId}/decision`, {
      headers: { Authorization: `Bearer ${adminToken}` },
      data: {
        decision: 'diterima',
        reason: 'Lolos seleksi tahap reguler',
      },
    });
    expect(decisionRes.status()).toBe(200);

    // 12. Calon Siswa Melakukan Daftar Ulang
    const reRegRes = await request.post(`${API_BASE_URL}/ppdb/applications/${applicationId}/re-register`, {
      headers: { Authorization: `Bearer ${applicantToken}` },
      data: {
        notes: 'Konfirmasi kehadiran dan kesiapan daftar ulang',
      },
    });
    expect(reRegRes.status()).toBe(200);

    // 13. Admin Menjalankan Enrollment ke Master Siswa
    const enrollRes = await request.post(`${API_BASE_URL}/ppdb/applications/${applicationId}/enroll`, {
      headers: { Authorization: `Bearer ${adminToken}` },
      data: {
        class_id: null,
      },
    });
    expect([200, 201]).toContain(enrollRes.status());
    const enrollBody = await enrollRes.json();
    expect(enrollBody.data?.student?.id || enrollBody.data?.applicant?.id || enrollBody.data?.student).toBeTruthy();
  });
});
