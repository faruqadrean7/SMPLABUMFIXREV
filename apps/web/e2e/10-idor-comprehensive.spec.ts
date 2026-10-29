import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('E2E B.4: Comprehensive IDOR & Scope Authorization Security', () => {
  const runArtisan = (cmd: string): string => {
    const b64 = Buffer.from(cmd).toString('base64');
    return execSync(`docker exec -i sistlabum-api php artisan tinker --execute="eval(base64_decode('${b64}'));"`).toString().trim();
  };

  test('1. Guru dilarang membaca atau menulis data kelas yang tidak ditugaskan (403 Forbidden)', async ({ request }) => {
    // 1. Ambil guru.mtk dan cari kelas yang TIDAK diajar oleh guru.mtk
    const dataJson = runArtisan(`
      $u = \\App\\Models\\User::where('username', 'guru.mtk')->first();
      $t = $u->teacher;
      $assignedClassIds = $t->teachingAssignments->pluck('class_id')->all();
      $ay = \\App\\Models\\AcademicYear::first();
      $gl = \\App\\Models\\GradeLevel::first();
      
      // Ambil kelas yang tidak ada di daftar penugasan guru, atau buat kelas uji unassigned
      $unassignedClass = \\App\\Models\\SchoolClass::whereNotIn('id', $assignedClassIds)->first();
      if (! $unassignedClass) {
        $unassignedClass = \\App\\Models\\SchoolClass::create([
          'name' => 'Kelas 9-Z Unassigned IDOR',
          'academic_year_id' => $ay->id,
          'grade_level_id' => $gl->id,
          'capacity' => 30,
        ]);
      }
      $subj = \\App\\Models\\Subject::where('name', 'Matematika')->first();
      $comp = \\App\\Models\\GradeComponent::first();
      $dummyStudent = \\App\\Models\\Student::first();

      echo json_encode([
        'guru_token' => $u->createToken('guru-idor')->plainTextToken,
        'unassigned_class_id' => $unassignedClass ? $unassignedClass->id : null,
        'subject_id' => $subj ? $subj->id : null,
        'component_id' => $comp ? $comp->id : null,
        'student_id' => $dummyStudent ? $dummyStudent->id : null,
      ]);
    `);
    const data = JSON.parse(dataJson);
    expect(data.unassigned_class_id).toBeTruthy();

    // 2. Guru mencoba input nilai pada kelas yang tidak diajar -> HTTP 403
    const unauthorizedGradeRes = await request.post('http://localhost:8000/api/v1/me/teacher/grades/bulk', {
      headers: {
        Authorization: `Bearer ${data.guru_token}`,
        Accept: 'application/json',
      },
      data: {
        class_id: data.unassigned_class_id,
        subject_id: data.subject_id,
        grades: [
          {
            student_id: data.student_id,
            scores: {
              [data.component_id]: 85,
            },
          },
        ],
      },
    });
    expect(unauthorizedGradeRes.status()).toBe(403);
    const gradeErr = await unauthorizedGradeRes.json();
    const gradeMsg = gradeErr.error?.message || gradeErr.message;
    expect(gradeMsg).toMatch(/Akses ditolak|tidak ditugaskan/i);

    // 3. Guru mencoba mengakses daftar siswa di kelas yang tidak diajar -> HTTP 403
    const unauthorizedStudentsRes = await request.get(`http://localhost:8000/api/v1/me/teacher/classes/${data.unassigned_class_id}/students`, {
      headers: {
        Authorization: `Bearer ${data.guru_token}`,
        Accept: 'application/json',
      },
    });
    expect(unauthorizedStudentsRes.status()).toBe(403);
  });

  test('2. Ortu A dilarang mengunggah slip atau melihat tagihan milik anak Ortu B (403 / 404)', async ({ request }) => {
    // 1. Ambil anak B dan buat invoice untuk anak B
    const dataJson = runArtisan(`
      $ortuA = \\App\\Models\\User::where('username', 'ortu_001')->first();
      $ortuB = \\App\\Models\\User::where('username', 'ortu_002')->first();
      $childB = $ortuB->guardian->students()->first();
      $ay = \\App\\Models\\AcademicYear::first();
      $ft = \\App\\Models\\FeeType::first();

      $invB = \\App\\Models\\Invoice::create([
        'invoice_number' => 'INV/' . date('Y/m') . '/IDOR-B-' . rand(100, 999),
        'student_id' => $childB->id,
        'academic_year_id' => $ay->id,
        'period' => date('Y-m'),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 150000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'notes' => 'Tagihan Khusus Anak B',
      ]);

      \\App\\Models\\InvoiceItem::create([
        'invoice_id' => $invB->id,
        'fee_type_id' => $ft->id,
        'description' => 'Iuran Wajib Anak B',
        'amount' => 150000,
        'discount' => 0,
      ]);

      echo json_encode([
        'token_ortu_a' => $ortuA->createToken('ortu-a-idor')->plainTextToken,
        'child_b_id' => $childB->id,
        'invoice_b_id' => $invB->id,
      ]);
    `);
    const data = JSON.parse(dataJson);

    // 2. Ortu A mencoba melihat invoice anak B via /me/children/{childBId}/invoices -> DITOLAK (403/404)
    const viewRes = await request.get(`http://localhost:8000/api/v1/me/children/${data.child_b_id}/invoices`, {
      headers: {
        Authorization: `Bearer ${data.token_ortu_a}`,
        Accept: 'application/json',
      },
    });
    expect([403, 404]).toContain(viewRes.status());

    // 3. Ortu A mencoba submit slip pembayaran untuk tagihan anak B -> DITOLAK (403 Forbidden)
    const validPngBuffer = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      'base64'
    );

    const submitRes = await request.post(`http://localhost:8000/api/v1/me/invoices/${data.invoice_b_id}/payments`, {
      headers: {
        Authorization: `Bearer ${data.token_ortu_a}`,
        Accept: 'application/json',
      },
      multipart: {
        amount: 150000,
        channel: 'Bank BNI',
        external_reference: `REF-ILLEGAL-${Date.now()}`,
        payment_date: new Date().toISOString().split('T')[0],
        proof_file: {
          name: 'bukti_ilegal.png',
          mimeType: 'image/png',
          buffer: validPngBuffer,
        },
      },
    });
    expect(submitRes.status()).toBe(403);
    const err = await submitRes.json();
    const errMsg = err.error?.message || err.message;
    expect(errMsg).toMatch(/tidak memiliki wewenang|Akses ditolak/i);
  });

  test('3. Siswa dilarang mengakses atau mengumpulkan tugas milik siswa lain (403 / 404)', async ({ request }) => {
    // 1. Buat tugas di kelas 7-A dan dapatkan token siswa_001 dan siswa_002 (kelas 7-B)
    const dataJson = runArtisan(`
      $s1 = \\App\\Models\\User::where('username', 'siswa_001')->first();
      $s2 = \\App\\Models\\User::where('username', 'siswa_002')->first();
      $t = \\App\\Models\\Teacher::first();
      $subj = \\App\\Models\\Subject::first();
      $cls1 = $s1->student->classes()->first();

      $assign = \\App\\Models\\Assignment::create([
        'class_id' => $cls1->id,
        'teacher_id' => $t->id,
        'subject_id' => $subj->id,
        'title' => 'Tugas Khusus Kelas 7-A IDOR',
        'due_at' => now()->addDays(2)->toDateTimeString(),
        'max_score' => 100,
      ]);

      echo json_encode([
        'assign_id' => $assign->id,
        'token_siswa_1' => $s1->createToken('s1')->plainTextToken,
        'token_siswa_2' => $s2->createToken('s2')->plainTextToken,
      ]);
    `);
    const data = JSON.parse(dataJson);

    // 2. Siswa 1 kumpulkan tugas miliknya
    const s1Submit = await request.post(`http://localhost:8000/api/v1/me/tasks/${data.assign_id}/submit`, {
      headers: {
        Authorization: `Bearer ${data.token_siswa_1}`,
        Accept: 'application/json',
      },
      data: { notes: 'Pengumpulan sah siswa 1' },
    });
    expect(s1Submit.status()).toBe(201);

    // 3. Siswa dilarang memanggil endpoint submissions guru -> 403
    const leakRes = await request.get(`http://localhost:8000/api/v1/me/teacher/assignments/${data.assign_id}/submissions`, {
      headers: {
        Authorization: `Bearer ${data.token_siswa_1}`,
        Accept: 'application/json',
      },
    });
    expect([401, 403]).toContain(leakRes.status());
  });

  test('4. Guru dilarang mengakses Shell Admin (/admin dan endpoint admin API)', async ({ request, page }) => {
    // 1. Dapatkan token guru
    const guruToken = runArtisan(`
      echo \\App\\Models\\User::where('username', 'guru.mtk')->first()->createToken('guru-admin-check')->plainTextToken;
    `);

    // 2. Guru memanggil API endpoint admin (mis. daftar tagihan keuangan atau audit logs) -> HTTP 403
    const apiRes = await request.get('http://localhost:8000/api/v1/finance/invoices', {
      headers: {
        Authorization: `Bearer ${guruToken}`,
        Accept: 'application/json',
      },
    });
    expect([401, 403]).toContain(apiRes.status());

    // 3. Guru mencoba membuka URL /admin di browser
    // Tanpa cookie admin -> HTTP 404 (Sesuai aturan Golden AGENTS.md & Batch 4B/5B)
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');
    const text404 = await page.locator('body').innerText();
    expect(text404).toMatch(/404|Tidak Ditemukan/i);

    // Dengan cookie sengaja dipasang tetapi token/user adalah guru -> AccessDenied di layout admin
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
        sessionStorage.setItem(
          'sistlabum_user_profile',
          JSON.stringify({
            id: 'guru-id',
            username: 'guru.mtk',
            roles: ['teacher'],
            permissions: ['academic.*'],
          })
        );
      },
      { token: guruToken }
    );

    await page.goto('/admin');
    await page.waitForLoadState('networkidle');
    const deniedText = await page.locator('body').innerText();
    expect(deniedText).toMatch(/Akses Dibatasi|Akses Modul Terbatas/i);
  });
});
