import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('E2E B.3: Propagasi Data (Portal Civitas -> Admin Back-Office)', () => {
  const runArtisan = (cmd: string): string => {
    const b64 = Buffer.from(cmd).toString('base64');
    return execSync(`docker exec -i sistlabum-api php artisan tinker --execute="eval(base64_decode('${b64}'));"`).toString().trim();
  };

  test('1. Guru input nilai di portal guru terefleksi seketika di Admin Shell', async ({ request, page }) => {
    // 1. Ambil data kelas 7-A, mata pelajaran Matematika, dan token guru.mtk
    const guruDataJson = runArtisan(`
      $u = \\App\\Models\\User::where('username', 'guru.mtk')->first();
      $t = $u->teacher;
      $cls = \\App\\Models\\SchoolClass::where('name', 'Kelas 7-A')->first();
      $subj = \\App\\Models\\Subject::where('name', 'Matematika')->first();
      $comp = \\App\\Models\\GradeComponent::where('subject_id', $subj->id)->first() ?: \\App\\Models\\GradeComponent::first();
      $student = $cls->students()->first();
      
      echo json_encode([
        'token' => $u->createToken('guru-e2e')->plainTextToken,
        'class_id' => $cls->id,
        'subject_id' => $subj->id,
        'component_id' => $comp->id,
        'student_id' => $student->id,
        'student_name' => $student->full_name,
      ]);
    `);
    const guruData = JSON.parse(guruDataJson);

    // 2. Guru input nilai via bulk endpoint portal guru
    const inputRes = await request.post('http://localhost:8000/api/v1/me/teacher/grades/bulk', {
      headers: {
        Authorization: `Bearer ${guruData.token}`,
        Accept: 'application/json',
      },
      data: {
        class_id: guruData.class_id,
        subject_id: guruData.subject_id,
        grades: [
          {
            student_id: guruData.student_id,
            scores: {
              [guruData.component_id]: 93.5,
            },
            notes: 'Penilaian Harian Siklus B3',
          },
        ],
      },
    });
    expect([200, 201]).toContain(inputRes.status());

    // 3. Admin membuka shell admin dan memverifikasi data nilai masuk
    const adminToken = runArtisan(`
      echo \\App\\Models\\User::where('username', 'admin_demo')->first()->createToken('admin-e2e')->plainTextToken;
    `);

    const adminCheckRes = await request.get('http://localhost:8000/api/v1/grades', {
      headers: {
        Authorization: `Bearer ${adminToken}`,
        Accept: 'application/json',
      },
      params: {
        student_id: guruData.student_id,
        component_id: guruData.component_id,
      },
    });
    expect(adminCheckRes.status()).toBe(200);
    const body = await adminCheckRes.json();
    const grades = body.data?.data || body.data || [];
    const matchedGrade = grades.find((g: any) => g.student_id === guruData.student_id);
    expect(matchedGrade).toBeTruthy();
    expect(Number(matchedGrade.value)).toBe(93.5);
  });

  test('2. Slip pembayaran diunggah orang tua muncul di antrean verifikasi Bendahara Admin', async ({ request, page }) => {
    // 1. Siapkan invoice belum bayar untuk siswa_001
    const invJson = runArtisan(`
      $s = \\App\\Models\\Student::whereHas('user', fn($q) => $q->where('username', 'siswa_001'))->first();
      $ay = \\App\\Models\\AcademicYear::first();
      $ft = \\App\\Models\\FeeType::first();
      
      $inv = \\App\\Models\\Invoice::create([
        'invoice_number' => 'INV/' . date('Y/m') . '/QUEUE-' . rand(100, 999),
        'student_id' => $s->id,
        'academic_year_id' => $ay->id,
        'period' => date('Y-m'),
        'due_date' => now()->addDays(7)->toDateString(),
        'total_amount' => 200000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'notes' => 'Tagihan Uji Antrean Bendahara B3',
      ]);
      
      \\App\\Models\\InvoiceItem::create([
        'invoice_id' => $inv->id,
        'fee_type_id' => $ft->id,
        'description' => 'Iuran Fasilitas B3',
        'amount' => 200000,
        'discount' => 0,
      ]);

      echo json_encode([
        'invoice_id' => $inv->id,
        'ortu_token' => \\App\\Models\\User::where('username', 'ortu_001')->first()->createToken('ortu-q')->plainTextToken,
        'admin_token' => \\App\\Models\\User::where('username', 'bendahara_demo')->first()->createToken('bendahara-q')->plainTextToken,
      ]);
    `);
    const qData = JSON.parse(invJson);
    const refCode = `REF-QUEUE-${Date.now()}`;

    // 2. Ortu upload slip pembayaran
    const validPngBuffer = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      'base64'
    );

    const submitRes = await request.post(`http://localhost:8000/api/v1/me/invoices/${qData.invoice_id}/payments`, {
      headers: {
        Authorization: `Bearer ${qData.ortu_token}`,
        Accept: 'application/json',
      },
      multipart: {
        amount: 200000,
        channel: 'BSI Mobile',
        external_reference: refCode,
        payment_date: new Date().toISOString().split('T')[0],
        proof_file: {
          name: 'bukti_transfer_queue.png',
          mimeType: 'image/png',
          buffer: validPngBuffer,
        },
      },
    });
    expect(submitRes.status()).toBe(201);

    // 3. Bendahara buka halaman /admin/finance/payments di browser
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
        localStorage.setItem('sistlabum_refresh_token', 'refresh-token-bendahara');
        sessionStorage.setItem(
          'sistlabum_user_profile',
          JSON.stringify({
            id: 'bendahara-id',
            username: 'bendahara_demo',
            roles: ['bendahara'],
            permissions: ['finance.*', 'finance.payment.verify'],
          })
        );
      },
      { token: qData.admin_token }
    );

    await page.goto('/admin/finance/payments');
    await page.waitForLoadState('networkidle');

    // Pastikan slip baru dengan refCode muncul di antrean verifikasi
    const pageText = await page.locator('body').innerText();
    expect(pageText).toContain(refCode);
    expect(pageText).toContain('Menunggu Verifikasi');
  });

  test('3. Pengumpulan tugas oleh siswa masuk ke antrean penilaian guru', async ({ request }) => {
    // 1. Buat tugas baru dan dapatkan token siswa_001 dan guru.mtk
    const assignDataJson = runArtisan(`
      $cls = \\App\\Models\\SchoolClass::where('name', 'Kelas 7-A')->first();
      $t = \\App\\Models\\Teacher::first();
      $subj = \\App\\Models\\Subject::where('name', 'Matematika')->first();
      $s = \\App\\Models\\Student::whereHas('user', fn($q) => $q->where('username', 'siswa_001'))->first();
      
      $assign = \\App\\Models\\Assignment::create([
        'class_id' => $cls->id,
        'teacher_id' => $t->id,
        'subject_id' => $subj->id,
        'title' => 'Tugas Matrik Pembuktian B3',
        'description' => 'Kerjakan pembuktian rumus teorema pythagoras',
        'due_at' => now()->addDays(3)->toDateTimeString(),
        'max_score' => 100,
      ]);

      echo json_encode([
        'assignment_id' => $assign->id,
        'student_token' => $s->user->createToken('siswa-sub')->plainTextToken,
        'teacher_token' => $t->user->createToken('guru-sub')->plainTextToken,
        'student_name' => $s->full_name,
      ]);
    `);
    const assignData = JSON.parse(assignDataJson);

    // 2. Siswa kumpulkan tugas
    const submitRes = await request.post(`http://localhost:8000/api/v1/me/tasks/${assignData.assignment_id}/submit`, {
      headers: {
        Authorization: `Bearer ${assignData.student_token}`,
        Accept: 'application/json',
      },
      data: {
        notes: 'Pengerjaan lengkap dan runtut telah diunggah oleh Ahmad Pratama',
      },
    });
    expect(submitRes.status()).toBe(201);

    // 3. Guru memanggil endpoint submissions tugas terkait
    const submissionsRes = await request.get(`http://localhost:8000/api/v1/me/teacher/assignments/${assignData.assignment_id}/submissions`, {
      headers: {
        Authorization: `Bearer ${assignData.teacher_token}`,
        Accept: 'application/json',
      },
    });
    expect(submissionsRes.status()).toBe(200);
    const subsBody = await submissionsRes.json();
    const submissions = subsBody.data || [];
    expect(submissions.length).toBeGreaterThan(0);
    
    // Pastikan submission siswa_001 tercatat
    const mySub = submissions.find((sub: any) => 
      sub.studentName === assignData.student_name ||
      sub.student_name === assignData.student_name || 
      sub.notes?.includes('Ahmad Pratama') ||
      sub.student?.full_name === assignData.student_name
    );
    expect(mySub).toBeTruthy();
    expect(mySub.status).toMatch(/pending|submitted|menunggu_dinilai/i);
  });
});
