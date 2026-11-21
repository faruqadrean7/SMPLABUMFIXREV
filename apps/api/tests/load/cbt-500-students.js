import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

// Custom Metrik Telemetri
export const cbtLoginDuration = new Trend('cbt_login_duration', true);
export const cbtSaveAnswerDuration = new Trend('cbt_save_answer_duration', true);
export const cbtHeartbeatDuration = new Trend('cbt_heartbeat_duration', true);
export const cbtSubmitDuration = new Trend('cbt_submit_duration', true);
export const cbtErrorRate = new Rate('cbt_error_rate');
export const cbtSuccessfulAnswers = new Counter('cbt_successful_answers');
export const cbtSuccessfulFinishes = new Counter('cbt_successful_finishes');
export const cbtSuccessfulSubmissions = new Counter('cbt_successful_submissions'); // Backward compatibility

// Usulan Ambang Batas Penerimaan (Proposed SLA Thresholds)
export const options = {
  scenarios: {
    cbt_concurrent_examinees: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '3m', target: 100 },  // Ramp-up bertahap 100 siswa
        { duration: '5m', target: 300 },  // Akselerasi ke 300 siswa
        { duration: '5m', target: 500 },  // Puncak ujian serentak 500 siswa
        { duration: '15m', target: 500 }, // Steady state 500 siswa aktif selama ujian
        { duration: '2m', target: 0 },    // Ramp-down setelah ujian selesai
      ],
      gracefulRampDown: '30s',
    },
  },
  thresholds: {
    // Kriteria Keberhasilan Beban (SLA Mandat):
    // 1. 95% request latensi < 500ms
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    // 2. Tingkat kegagalan HTTP < 1%
    http_req_failed: ['rate<0.01'],
    // 3. Tingkat error alur CBT < 1% (wajib diuji secara akurat)
    cbt_error_rate: ['rate<0.01'],
    // 4. Latensi penyimpanan autosave jawaban p95 < 250ms
    cbt_save_answer_duration: ['p(95)<250'],
    // 5. Latensi penyelesaian submit ujian p95 < 500ms
    cbt_submit_duration: ['p(95)<500'],
    // 6. Validasi bahwa autosave benar-benar tereksekusi dan tersimpan (gagal jika 0 jawaban tersimpan)
    cbt_successful_answers: ['count>0'],
    // 7. Validasi kelulusan seluruh assertion checks bisnis > 99%
    checks: ['rate>0.99'],
  },
};

// Jika mode auto-submit aktif, wajibkan minimal 1 submit sukses terekam
if (__ENV.CBT_AUTO_SUBMIT === 'true') {
  options.thresholds['cbt_successful_finishes'] = ['count>0'];
}

const BASE_URL = __ENV.API_BASE_URL || 'http://127.0.0.1:8000/api/v1';
const EXAM_ID = __ENV.CBT_EXAM_ID || '01a12e30-d622-70b9-94dd-2b34c1dc023b';
const ROOM_TOKEN = __ENV.CBT_ROOM_TOKEN || 'BIO123';

export default function () {
  const vuId = __VU;
  const iteration = __ITER;
  
  // 1. Autentikasi Siswa Sintetis (Berdasarkan pool 500 akun siswa unik 1:1)
  const studentIndex = ((vuId - 1) % 500) + 1;
  const studentEmail = `student${studentIndex}@labschool.sch.id`;
  const loginPayload = JSON.stringify({
    identifier: studentEmail,
    password: 'Password123!',
  });

  const loginHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  const loginRes = http.post(`${BASE_URL}/auth/login`, loginPayload, { headers: loginHeaders });
  cbtLoginDuration.add(loginRes.timings.duration);

  // Verifikasi otentikasi ketat: hanya 200 OK yang sah untuk akun siswa uji
  const loginSuccess = check(loginRes, {
    'login status is 200 OK': (r) => r.status === 200,
    'no fatal 500 error on login': (r) => r.status !== 500,
  });

  if (!loginSuccess || loginRes.status !== 200) {
    cbtErrorRate.add(1);
    sleep(2);
    return;
  }

  const loginData = loginRes.json();
  const token = loginData && loginData.data && loginData.data.tokens ? loginData.data.tokens.accessToken : null;

  if (!token) {
    cbtErrorRate.add(1);
    sleep(2);
    return;
  }

  const authHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': `Bearer ${token}`,
  };

  // 2. Mulai atau Lanjutkan Sesi Ujian (Start / Resume Exam Session)
  const startPayload = JSON.stringify({ token: ROOM_TOKEN });
  const startRes = http.post(`${BASE_URL}/exams/${EXAM_ID}/start-session`, startPayload, { headers: authHeaders });

  const startCheck = check(startRes, {
    'start session status is 200 or 422': (r) => r.status === 200 || r.status === 422,
    'start session non-500': (r) => r.status !== 500,
  });

  if (!startCheck) {
    cbtErrorRate.add(1);
    return;
  }

  let sessionId = null;
  if (startRes.status === 200) {
    const sessionData = startRes.json();
    sessionId = sessionData && sessionData.data ? (sessionData.data.session_id || sessionData.data.id) : null;
  } else if (startRes.status === 422) {
    // Penanganan jika ujian telah diselesaikan pada iterasi sebelumnya
    const errData = startRes.json();
    if (errData && errData.message && errData.message.includes('selesai')) {
      // Sesi telah selesai secara sah, bukan kegagalan sistem
      cbtErrorRate.add(0);
      sleep(5);
      return;
    } else {
      cbtErrorRate.add(1);
      return;
    }
  }

  // 3. Ambil Butir Soal Ujian (Load Exam Questions)
  const questionsRes = http.get(`${BASE_URL}/exams/${EXAM_ID}/session-questions`, { headers: authHeaders });
  const questionsCheck = check(questionsRes, {
    'questions load status is 200 OK': (r) => r.status === 200,
    'questions load non-500': (r) => r.status !== 500,
  });

  if (!questionsCheck) {
    cbtErrorRate.add(1);
    return;
  }

  let questionIds = [];
  if (questionsRes.status === 200) {
    const qData = questionsRes.json();
    if (!sessionId && qData && qData.data && qData.data.session_id) {
      sessionId = qData.data.session_id;
    }
    const qList = (qData && qData.data && Array.isArray(qData.data.questions))
      ? qData.data.questions
      : ((qData && Array.isArray(qData.data)) ? qData.data : []);
    questionIds = qList.map(q => q.id);
  }

  // 4. Simulasi Siklus Pengerjaan Soal (Answering & Heartbeat Loop)
  // Siswa menjawab butir soal dengan jeda berpikir realistis (2 - 5 detik)
  const questionsToAnswer = questionIds.length > 0 ? questionIds.slice(0, 5) : [
    '00000000-0000-0000-0000-000000000010',
    '00000000-0000-0000-0000-000000000011',
  ];

  for (let i = 0; i < questionsToAnswer.length; i++) {
    // Kirim Heartbeat Browser / Anti-Cheat
    if (sessionId) {
      const heartbeatPayload = JSON.stringify({
        screen_state: 'fullscreen',
        active_tab: true,
        battery_level: 85,
        client_timestamp: new Date().toISOString(),
      });
      const hbRes = http.post(`${BASE_URL}/exam-sessions/${sessionId}/heartbeat`, heartbeatPayload, { headers: authHeaders });
      cbtHeartbeatDuration.add(hbRes.timings.duration);

      const hbCheck = check(hbRes, {
        'heartbeat status is 200 OK': (r) => r.status === 200,
        'heartbeat non-500': (r) => r.status !== 500,
      });

      if (!hbCheck) {
        cbtErrorRate.add(1);
      } else {
        cbtErrorRate.add(0);
      }
    }

    // Waktu berpikir siswa
    sleep(Math.random() * 3 + 2);

    // Kirim Autosave Jawaban
    const answerPayload = JSON.stringify({
      question_id: questionsToAnswer[i],
      answer: ['A', 'B', 'C', 'D'][Math.floor(Math.random() * 4)],
      is_flagged: false,
    });

    const saveRes = http.post(`${BASE_URL}/exams/${EXAM_ID}/save-answer`, answerPayload, { headers: authHeaders });
    cbtSaveAnswerDuration.add(saveRes.timings.duration);

    const saveCheck = check(saveRes, {
      'save answer status is 200 OK': (r) => r.status === 200 || r.status === 201,
      'save answer non-500': (r) => r.status !== 500,
    });

    if (saveCheck) {
      cbtSuccessfulAnswers.add(1);
      cbtSuccessfulSubmissions.add(1);
      cbtErrorRate.add(0);
    } else {
      cbtErrorRate.add(1);
    }
  }

  // 5. Submit Sesi Ujian (Finish Session) jika diaktifkan via environment variable CBT_AUTO_SUBMIT
  if (__ENV.CBT_AUTO_SUBMIT === 'true' && sessionId) {
    const finishRes = http.post(`${BASE_URL}/exams/${EXAM_ID}/finish-session`, JSON.stringify({}), { headers: authHeaders });
    cbtSubmitDuration.add(finishRes.timings.duration);

    const finishCheck = check(finishRes, {
      'finish session status is 200 or 422': (r) => r.status === 200 || r.status === 422,
      'finish session non-500': (r) => r.status !== 500,
    });

    if (finishCheck) {
      cbtSuccessfulFinishes.add(1);
      cbtErrorRate.add(0);
    } else {
      cbtErrorRate.add(1);
    }
  }

  // Jeda akhir sebelum iterasi berikutnya
  sleep(5);
}
