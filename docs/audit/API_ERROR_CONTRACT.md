# SISTLABUM — API ERROR CONTRACT SPECIFICATION & MIGRATION GUIDE

**Dokumen:** Spesifikasi Kontrak Error API & Panduan Integrasi Klien  
**Versi:** 1.0.0 (Unified Canonical Contract)  
**Baseline Commit:** Terverifikasi pasca-unifikasi handler  
**Tanggal:** 2026-10-12  
**Status Release Gate:** CONDITIONAL GO (Gate G-4 tetap BLOCKED untuk Staging Load Drill)

---

## 1. Latar Belakang & Analisis Gap (H-01)

Sebelum unifikasi ini, SISTLABUM memiliki dua format error validasi input yang tidak konsisten:
1. **Modul Warisan (Auth, Master, PPDB, CMS, Guest)**: Menggunakan `BaseApiRequest` yang melempar `HttpResponseException` dengan status **HTTP 400** dan amplop `{ error: { code: "VALIDATION_FAILED", message: "Data tidak valid", details: [...] } }`.
2. **Modul Baru (Ntf, Sar, Hum, Std, CBT, Academic)**: Menggunakan subclass `Illuminate\Foundation\Http\FormRequest` standar yang menghasilkan respons bawaan Laravel dengan status **HTTP 422** dan amplop `{ message: "...", errors: { field: [...] } }`.

Kondisi ini menyulitkan tim pengembang Web Admin (Next.js) dan Mobile (Flutter) karena harus menulis parser khusus per modul.

---

## 2. Kontrak Kanonis JSON Error API (Unified)

Semua respons error API SISTLABUM pada prefiks `/api/*` kini dijamin memiliki struktur kanonis terpadu dengan dukungan kompatibilitas mundur ganda (*dual-compatible schema*):

```jsonc
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Data tidak valid",
    "details": [
      {
        "field": "identifier",
        "message": "Kolom identifier wajib diisi."
      }
    ],
    "requestId": "019266e7-73b0-711c-99c8-d0694e9f7831"
  },
  // Field pendukung di root untuk kompatibilitas Flutter, React Hook Form, dan Laravel client
  "message": "Data tidak valid",
  "errors": {
    "identifier": [
      "Kolom identifier wajib diisi."
    ]
  }
}
```

### 2.1 Header Wajib
Setiap respons menyertakan header korelasi pelacakan:
- `X-Request-Id`: UUID unik untuk setiap request (dapat diteruskan dari client atau dibuat otomatis oleh server).

---

## 3. Matriks Kode HTTP & Kode Error Baku

Sesuai `04_PRD_BACKEND.md` Bagian 1.2:

| HTTP Status | `error.code` | Deskripsi Bisnis | Contoh Kejadian |
|---|---|---|---|
| **400** | `VALIDATION_FAILED` | Input salah / format tidak valid (BaseApiRequest) | Parameter login kosong, format UUID salah |
| **422** | `VALIDATION_FAILED` | Input salah / validasi form (FormRequest) | Kolom wajib broadcast notifikasi tidak lengkap |
| **422** | `BUSINESS_RULE_VIOLATION` | Pelanggaran aturan alur bisnis | Menyimpan jawaban CBT setelah sesi ujian di-submit, pembatalan izin yang sudah kadaluwarsa |
| **401** | `UNAUTHENTICATED` | Pengguna belum masuk atau token tidak valid | Token Sanctum hilang/salah, refresh token reuse terdeteksi |
| **401** | `TOKEN_EXPIRED` | Sesi token telah kedaluwarsa | Access token melebihi 15 menit, refresh token melebihi 14 hari |
| **403** | `FORBIDDEN` | Tidak memiliki hak akses / pelanggaran IDOR | Guru input nilai kelas lain, siswa buka profil siswa lain, akses endpoint BK tanpa role konselor |
| **404** | `NOT_FOUND` | Sumber daya tidak ditemukan | ID siswa/jadwal/ujian tidak ada di database |
| **409** | `CONFLICT` | Bentrok data / duplikasi unik | NIK/NISN duplikat, jadwal ruangan bentrok |
| **429** | `RATE_LIMITED` | Pembatasan frekuensi permintaan | Lebih dari 5 kali gagal login, forgot password berulang |
| **500** | `INTERNAL_SERVER_ERROR` | Kesalahan internal server tak terduga | Kegagalan koneksi database, unhandled exception |

---

## 4. Keamanan & Sanitasi Error

1. **Deny Stack Trace**: Bila `APP_DEBUG=false`, server **TIDAK PERNAH** membocorkan nama tabel SQL, klausa query, path file sistem operasi, trace exception, kredensial, atau rahasia ke klien.
2. **Generic Internal Message**: Error 500 selalu menampilkan pesan aman: `"Terjadi kesalahan internal server"` dengan kode `INTERNAL_SERVER_ERROR`.
3. **Audit Korelasi**: Klien dapat melaporkan nilai `requestId` kepada tim DevOps/Backend untuk mencari log detail di server tanpa mengekspos detail sensitif ke pengguna akhir.
4. **Isolasi Rute Web**: Handler terpusat hanya aktif pada rute API (`$request->is('api/*') || $request->expectsJson()`). Rute web reguler dan healthcheck (`/up`) tidak terganggu.

---

## 5. Panduan Integrasi Klien

### 5.1 Flutter Mobile (Dio Interceptor)

```dart
class ApiException implements Exception {
  final int statusCode;
  final String code;
  final String message;
  final List<dynamic> details;
  final Map<String, dynamic> errors;
  final String? requestId;

  ApiException({
    required this.statusCode,
    required this.code,
    required this.message,
    this.details = const [],
    this.errors = const {},
    this.requestId,
  });

  factory ApiException.fromResponse(Response response) {
    final data = response.data;
    if (data is Map<String, dynamic>) {
      final error = data['error'] as Map<String, dynamic>?;
      return ApiException(
        statusCode: response.statusCode ?? 500,
        code: error?['code'] ?? 'UNKNOWN_ERROR',
        message: error?['message'] ?? data['message'] ?? 'Terjadi kesalahan',
        details: error?['details'] ?? [],
        errors: data['errors'] ?? {},
        requestId: error?['requestId'] ?? response.headers.value('x-request-id'),
      );
    }
    return ApiException(
      statusCode: response.statusCode ?? 500,
      code: 'UNKNOWN_ERROR',
      message: 'Format respons tidak dikenali',
    );
  }
}
```

### 5.2 Next.js Web Admin (Axios / TanStack Query Error Normalizer)

```typescript
export interface ApiErrorResponse {
  error: {
    code: string;
    message: string;
    details: Array<{ field: string; message: string }>;
    requestId: string;
  };
  message?: string;
  errors?: Record<string, string[]>;
}

export function normalizeApiError(err: unknown): { code: string; message: string; fieldErrors: Record<string, string> } {
  if (axios.isAxiosError(err) && err.response?.data) {
    const data = err.response.data as ApiErrorResponse;
    const fieldErrors: Record<string, string> = {};

    // Prioritaskan canonical details
    if (Array.isArray(data.error?.details)) {
      data.error.details.forEach(d => {
        fieldErrors[d.field] = d.message;
      });
    } else if (data.errors) {
      Object.entries(data.errors).forEach(([field, msgs]) => {
        fieldErrors[field] = msgs[0];
      });
    }

    return {
      code: data.error?.code ?? 'HTTP_ERROR',
      message: data.error?.message ?? data.message ?? 'Terjadi kesalahan sistem.',
      fieldErrors,
    };
  }
  return {
    code: 'NETWORK_ERROR',
    message: 'Gagal terhubung ke server.',
    fieldErrors: {},
  };
}
```

---

## 6. Bukti Pengujian & Hasil Regresi

### 6.1 Suite Khusus Kontrak Error (`ApiErrorContractTest.php`)
Suite pengujian khusus mencakup 8 skenario:
1. `test_validation_error_contract_on_base_api_request_module`: Validasi 400 kanonis + root errors.
2. `test_validation_error_contract_on_form_request_module`: Validasi 422 kanonis + root errors.
3. `test_unauthenticated_error_contract`: 401 UNAUTHENTICATED + X-Request-Id match.
4. `test_forbidden_authorization_error_contract`: 403 FORBIDDEN.
5. `test_not_found_error_contract`: 404 NOT_FOUND.
6. `test_rate_limited_error_contract`: 429 RATE_LIMITED.
7. `test_internal_error_sanitization_in_production_mode`: Trace dan SQL tidak bocor.
8. `test_web_routes_not_interfered_by_api_json_wrapper`: Rute web non-API tidak terdistorsi.

**Hasil Eksekusi:**
```
$ php artisan test --filter=ApiErrorContractTest
{"tool":"phpunit","result":"passed","tests":8,"passed":8,"assertions":79,"duration_ms":461}
```

### 6.2 Full Backend Regression Suite
Seluruh 786 pengujian di repository lulus tanpa kegagalan:
```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":786,"passed":786,"assertions":4229,"duration_ms":22448}
Exit code: 0
```

### 6.3 Standar Gaya Kode (Pint)
```
$ ./vendor/bin/pint --test
{"tool":"pint","result":"passed"}
Exit code: 0
```
