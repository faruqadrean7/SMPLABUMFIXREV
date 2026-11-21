# SISTLABUM — API Contract, Routing Architecture & Endpoint Audit (Final)

> **Document Type:** Production API Contract & Route Compliance Audit  
> **Application:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11)  
> **Reference Specifications:** `04_PRD_BACKEND.md`, `13_MASTER_MODULES.md`  
> **Status:** AUDITED & COMPLIANT

---

## 1. Architectural Patterns & Controller-Service Separation

### 1.1 Layered Architecture
The backend strictly adheres to a three-tier separation of concerns:
1. **HTTP Layer (Controllers):** Tipis (*thin controllers*), responsible strictly for request intake, FormRequest trigger, invoking domain services, and returning formatted JSON responses.
2. **Business Domain Layer (Services & Actions):** Enforces business logic, state machines, deadline calculations, and multi-table transactions.
3. **Data Access Layer (Eloquent Models & Repositories):** Manages database queries, foreign key relationships, attribute casts, encryption, and local scopes.

### 1.2 Form Request Validation
Every mutating endpoint (`POST`, `PUT`, `PATCH`) routes inputs through dedicated `FormRequest` classes with:
- Strict field whitelisting (`validated()` calls).
- String length boundaries and regex validations (NIK 16 digits, NISN 10 digits).
- MIME-type and magic-byte checks for file uploads.
- Per-field validation feedback in Bahasa Indonesia.

---

## 2. Response Envelope Standards & Error Handling

### 2.1 Standard Success Envelope
```json
{
  "status": "success",
  "data": { ... },
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 142
  }
}
```

### 2.2 Standard Error Envelope with Tracing
All API exceptions captured in `bootstrap/app.php` conform to:
```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Data tidak valid",
    "details": [
      {
        "field": "identifier",
        "message": "Identitas (username, email, NIS, atau NIP) wajib diisi"
      }
    ],
    "requestId": "2154f453-5014-4a37-85a0-76641a73cd2e"
  }
}
```
Headers include `X-Request-Id: <uuid>` on every error response for distributed telemetry and support debugging.

### 2.3 HTTP Status Code Conventions
| Status Code | Scenario | Code Identifier |
|---|---|---|
| `200 OK` | Query, calculation, status inquiry | `SUCCESS` |
| `201 Created` | New resource creation | `CREATED` |
| `204 No Content`| Resource deletion or session logout | None |
| `400 Bad Request`| State machine invariant violation | `INVALID_STATE` |
| `401 Unauthorized`| Missing, expired, or invalid token | `UNAUTHENTICATED` |
| `403 Forbidden` | Insufficient role or permission | `FORBIDDEN` |
| `404 Not Found` | Model not found | `NOT_FOUND` |
| `409 Conflict` | Unique key violation or booking overlap | `CONFLICT` |
| `422 Unprocessable`| FormRequest input validation failure | `VALIDATION_FAILED` |
| `429 Too Many Req`| Rate limiter exceeded | `RATE_LIMITED` |
| `500 Server Error`| Unexpected failure (sanitized in prod) | `INTERNAL_SERVER_ERROR` |

---

## 3. Comprehensive Route Inventory & Distribution

Reflected directly from the Laravel router (`php artisan route:list --json`):

| Category | Total Routes | Method Breakdown | Auth Scoping |
|---|:---:|---|---|
| **00-Health** | 1 | `GET` (1) | Public (Health check with sanitized errors) |
| **01-Auth & RBAC** | 22 | `GET` (7), `POST` (11), `PUT` (3), `DELETE` (1) | Mixed: 6 Public, 16 `auth:sanctum` |
| **02-MasterData** | 80 | `GET` (36), `POST` (24), `PUT` (12), `PATCH` (3), `DELETE` (5) | `auth:sanctum` + `RequirePermission` |
| **03-CMS** | 42 | `GET` (18), `POST` (10), `PUT` (7), `DELETE` (7) | Mixed: Public views + Protected CMS write |
| **04-PPDB** | 40 | `GET` (14), `POST` (17), `PUT` (3), `PATCH` (5), `DELETE` (1) | Mixed: Public waves + Applicant + Admin |
| **05-Guest** | 7 | `GET` (3), `POST` (2), `PATCH` (2) | Public Kiosk / Whistleblower + Admin |
| **06-Academic** | 41 | `GET` (19), `POST` (12), `PUT` (3), `PATCH` (4), `DELETE` (3) | `auth:sanctum` (Teacher / Student / Homeroom) |
| **07-Grading** | 27 | `GET` (12), `POST` (8), `PUT` (2), `PATCH` (5) | `auth:sanctum` (Teacher / Walas / Principal) |
| **08-CBT** | 50 | `GET` (23), `POST` (18), `PUT` (3), `PATCH` (1), `DELETE` (5) | Mixed: Student taking, Proctor isolation, Admin |
| **09-StudentAffairs** | 51 | `GET` (22), `POST` (14), `PUT` (7), `PATCH` (1), `DELETE` (7) | `auth:sanctum` (Staff, Coach, Walas) |
| **10-CounselingBK** | 19 | `GET` (8), `POST` (5), `PUT` (1), `PATCH` (4), `DELETE` (1) | `auth:sanctum` (Strict Counselor-only) |
| **11-SarprasLibrary**| 45 | `GET` (18), `POST` (12), `PUT` (5), `PATCH` (4), `DELETE` (6) | `auth:sanctum` (Asset manager, Librarian) |
| **12-PublicRelations**| 27 | `GET` (12), `POST` (7), `PUT` (5), `DELETE` (3) | `auth:sanctum` (Humas, Alumni) |
| **13-Reports** | 6 | `GET` (6) | `auth:sanctum` (Principal, Management) |
| **14-Notifications** | 34 | `GET` (14), `POST` (9), `PUT` (3), `PATCH` (4), `DELETE` (4) | `auth:sanctum` (All users, Admin broadcast) |
| **15-Public Directory**| 29 | `GET` (27), `POST` (2) | Public (QR verification, guest submissions) |
| **Subtotal API** | **521** | **GET (236), POST (145), PUT (52), PATCH (33), DELETE (55)** | **442 Protected, 79 Public** |
| **Non-API Framework** | 5 | `GET` (4), `PUT` (1) | Utility (`/up`, `/sanctum/csrf-cookie`, etc.) |
| **GRAND TOTAL** | **526** | **All 526 routes mapped without unmapped endpoints** | — |

---

## 4. Sensitive Field Masking & Privacy Audit

1. **Student Personal Identifiers (NIK):**
   - Implemented via `nik => 'encrypted'` model cast.
   - Database stores AES-256-CBC ciphertext. Public API endpoints never return NIK.
2. **CBT Answer Keys & Scoring Rubrics:**
   - Filtered out in `ExamTakingController@startSession` and `ExamTakingController@questions`.
   - Candidates receive question text, options, and media, but never correct answers or explanations.
3. **BK Counseling Case Notes:**
   - Enforced by `RequirePermission:counseling.manage` and `CounselingPolicy`.
   - Notes encrypted at rest; inaccessible to regular teachers, homeroom teachers, students, or parents.
4. **Health Check Exceptions:**
   - Sanitized to `"unreachable"` string in `HealthController` (`SEC-INFO-01`).
   - Prevents SQL hostname, username, or connection stack trace disclosure.
