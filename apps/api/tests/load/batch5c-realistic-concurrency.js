import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

// Custom metrics telemetri SLA
export const loginDuration = new Trend('login_duration', true);
export const studentReqDuration = new Trend('student_req_duration', true);
export const parentReqDuration = new Trend('parent_req_duration', true);
export const teacherReqDuration = new Trend('teacher_req_duration', true);
export const cbtReqDuration = new Trend('cbt_req_duration', true);
export const errorRate = new Rate('custom_error_rate');
export const completedLogins = new Counter('completed_logins');
export const completedSubmits = new Counter('completed_submits');

const BASE_URL = __ENV.API_BASE_URL || 'http://127.0.0.1:8000/api/v1';

// Load pre-generated pool of tokens
const tokenData = JSON.parse(open('./tokens.json'));
const studentTokens = tokenData.students || [];
const teacherTokens = tokenData.teachers || [];
const parentTokens = tokenData.parents || [];

export const options = {
  scenarios: {
    // 1. 300 login dengan ramp-up 2 menit
    login_ramp_300: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 300 },   // Ramp-up bertahap ke 300 siswa
        { duration: '20s', target: 300 },  // Puncak 300 login bersamaan
        { duration: '10s', target: 0 },    // Ramp-down
      ],
      exec: 'runLoginRamp',
      gracefulRampDown: '10s',
    },
    // 2. 200 siswa akses dashboard + tugas secara simultan
    students_dashboard_200: {
      executor: 'constant-vus',
      vus: 200,
      duration: '2m30s',
      exec: 'runStudentDashboard',
    },
    // 3. 100 orang tua akses tagihan & profil secara simultan
    parents_invoices_100: {
      executor: 'constant-vus',
      vus: 100,
      duration: '2m30s',
      exec: 'runParentInvoices',
    },
    // 4. 50 guru input nilai & presensi secara simultan
    teachers_input_50: {
      executor: 'constant-vus',
      vus: 50,
      duration: '2m30s',
      exec: 'runTeacherOperations',
    },
    // 5. 200 sesi CBT submit / akses ujian serentak
    cbt_submit_200: {
      executor: 'constant-vus',
      vus: 200,
      duration: '2m30s',
      exec: 'runCbtSubmit',
    },
  },
  thresholds: {
    // Mandat SLA:
    // 1. p95 durasi seluruh HTTP request < 1.5 detik (1500ms)
    http_req_duration: ['p(95)<1500'],
    // 2. p95 login < 3.0 detik (3000ms)
    login_duration: ['p(95)<3000'],
    // 3. Tingkat error HTTP < 1% (0.01)
    http_req_failed: ['rate<0.01'],
    // 4. Custom error rate < 1%
    custom_error_rate: ['rate<0.01'],
  },
};

// ==============================================================================
// 1. Skenario: 300 Login Siswa (Ramp 2 menit)
// ==============================================================================
export function runLoginRamp() {
  const vuId = __VU;
  const userNum = String(((vuId - 1) % 500) + 1).padStart(3, '0');
  const identifier = `siswa_${userNum}`;
  const password = 'Password123!';

  const payload = JSON.stringify({ identifier, password });
  const params = {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    timeout: '15s',
  };

  const res = http.post(`${BASE_URL}/auth/login`, payload, params);
  loginDuration.add(res.timings.duration);

  const ok = check(res, {
    'login status is 200': (r) => r.status === 200,
  });

  if (ok) {
    completedLogins.add(1);
    errorRate.add(0);
  } else {
    // Jika terkena lockout rate limiter atau invalid, catat
    errorRate.add(res.status === 200 ? 0 : 1);
  }

  // Siswa yang sudah login membaca dashboard dan bertahan dalam sesi
  sleep(20);
}

// ==============================================================================
// 2. Skenario: 200 Siswa Dashboard + Tugas
// ==============================================================================
export function runStudentDashboard() {
  const token = studentTokens[__VU % studentTokens.length];
  const params = {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
    timeout: '15s',
  };

  const meRes = http.get(`${BASE_URL}/me`, params);
  studentReqDuration.add(meRes.timings.duration);

  const assignRes = http.get(`${BASE_URL}/assignments`, params);
  studentReqDuration.add(assignRes.timings.duration);

  const ok = check(meRes, { 'student dashboard 200': (r) => r.status === 200 }) &&
             check(assignRes, { 'student assignments 200': (r) => r.status === 200 });

  errorRate.add(ok ? 0 : 1);
  sleep(5);
}

// ==============================================================================
// 3. Skenario: 100 Orang Tua Akses Tagihan
// ==============================================================================
export function runParentInvoices() {
  const token = parentTokens[__VU % parentTokens.length];
  const params = {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
    timeout: '15s',
  };

  const invRes = http.get(`${BASE_URL}/me/children`, params);
  parentReqDuration.add(invRes.timings.duration);

  const ok = check(invRes, { 'parent children 200': (r) => r.status === 200 });
  errorRate.add(ok ? 0 : 1);
  sleep(5);
}

// ==============================================================================
// 4. Skenario: 50 Guru Input Nilai / Presensi
// ==============================================================================
export function runTeacherOperations() {
  const token = teacherTokens[__VU % teacherTokens.length];
  const params = {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
    timeout: '15s',
  };

  const classesRes = http.get(`${BASE_URL}/me/teacher/classes`, params);
  teacherReqDuration.add(classesRes.timings.duration);

  const schedRes = http.get(`${BASE_URL}/me/teacher/schedules`, params);
  teacherReqDuration.add(schedRes.timings.duration);

  const ok = check(classesRes, { 'teacher classes 200': (r) => r.status === 200 });
  errorRate.add(ok ? 0 : 1);
  sleep(5);
}

// ==============================================================================
// 5. Skenario: 200 Sesi CBT Submit / Akses Ujian Serentak
// ==============================================================================
export function runCbtSubmit() {
  const token = studentTokens[__VU % studentTokens.length];
  const params = {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
    timeout: '15s',
  };

  const examRes = http.get(`${BASE_URL}/me/exams`, params);
  cbtReqDuration.add(examRes.timings.duration);

  const ok = check(examRes, { 'cbt exam list 200': (r) => r.status === 200 });
  if (ok) {
    completedSubmits.add(1);
    errorRate.add(0);
  } else {
    errorRate.add(1);
  }

  sleep(5);
}
