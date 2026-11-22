# Portal Siswa "Fun" Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Re-theme the student web portal (`/portal/siswa/**`) into a fun, playful, game-like experience for SMP students with a hand-drawn SVG mascot "Labi" and CSS animations — without changing any data, API, or business logic.

**Architecture:** A presentation-only layer scoped to a `.portal-siswa` wrapper. A fun color/animation theme lives in `globals.css` under that scope only (never touching other portals). A reusable `Labi` SVG mascot and a set of student-only UI primitives (page header, stat tile, card, loading/empty/error states) are applied across the 15 student pages. Points shown reuse the existing points hook as-is; no XP/gamification engine is added.

**Tech Stack:** Next.js 14 (App Router, client components), React 18, TailwindCSS 3, lucide-react, vitest + @testing-library/react. **No new dependencies.**

**Spec:** `docs/superpowers/specs/2026-11-22-portal-siswa-fun-redesign-design.md`

## Global Constraints

- **No new dependencies.** Animations are CSS/Tailwind keyframes only.
- **Scope is student portal only** (`apps/web/src/app/(portal)/portal/siswa/**` + `apps/web/src/features/portal/**` used only by siswa). Do not modify guru/ortu/kepsek/staf/admin or public.
- **Do not modify shared cross-portal components** (`src/components/shared/loading-spinner.tsx`, `empty-state.tsx`, `error-state.tsx`, `data-table.tsx`, etc.). Create student-only variants instead.
- **No API / DTO / query / DB / business-logic changes.** Points use existing hooks as-is; no XP engine.
- **Theme must not leak:** all fun tokens live under `.portal-siswa` scope; other portals must render unchanged.
- **Respect `prefers-reduced-motion`:** every animation disabled automatically when the OS requests reduced motion.
- **UI copy in Bahasa Indonesia; identifiers/comments in English.**
- **No real personal data** in code/tests/fixtures — fictional only.
- **Exam kiosk mode** (active CBT/ujian session, already detected in `siswa/layout.tsx`) must stay distraction-free: no persistent mascot, no ambient animation during an in-progress exam.
- **Test naming:** vitest `describe` blocks prefixed `FE-SISWA-UI-01`; test files in `src/test/`.

## Review Focus

- **Reduced motion:** with `prefers-reduced-motion: reduce`, mascot/confetti/transition animations must not play — owned by Task 1 (mascot) and Task 6 (confetti).
- **Theme isolation:** rendering a guru/admin page must not pick up `.portal-siswa` colors/animations — owned by Task 3.
- **State preservation:** every student page keeps a loading, empty, and error state (dressed up, not removed) — owned by Tasks 2, 5, 7, 8.
- **Missing avatar / long name:** header & hero must still render with no `photoUrl` and with a very long student name without breaking layout — owned by Task 3 and Task 5.
- **Exam focus:** confetti/success moment fires only on submit confirmation, never mid-exam ambient — owned by Task 6.

---

### Task 1: Labi mascot component + animation keyframes

**Files:**
- Create: `apps/web/src/features/portal/mascot/labi.tsx`
- Modify: `apps/web/src/app/globals.css` (append scoped keyframes + reduced-motion guard)
- Test: `apps/web/src/test/fe-siswa-ui-01-mascot.test.tsx`

**Interfaces:**
- Produces: `export type LabiExpression = 'wave' | 'happy' | 'study' | 'sleepy' | 'cheer';`
- Produces: `export function Labi(props: { expression?: LabiExpression; size?: number; className?: string }): JSX.Element` — renders an inline SVG mascot, `aria-hidden="true"` (decorative), wrapped in a span carrying animation class `labi-anim labi-anim--<expression>`. Default `expression='wave'`, `size=120`.

- [ ] **Step 1: Write failing tests**

```tsx
// FE-SISWA-UI-01: Labi mascot
import { render } from '@testing-library/react';
import { Labi } from '@/features/portal/mascot/labi';

it('FE-SISWA-UI-01-mascot: renders an svg and is decorative (aria-hidden)', () => {
  const { container } = render(<Labi expression="happy" />);
  const svg = container.querySelector('svg');
  expect(svg).toBeTruthy();
  expect(container.querySelector('[aria-hidden="true"]')).toBeTruthy();
});

it('FE-SISWA-UI-01-mascot: applies the expression animation class', () => {
  const { container } = render(<Labi expression="cheer" />);
  expect(container.querySelector('.labi-anim--cheer')).toBeTruthy();
});

it('FE-SISWA-UI-01-mascot: respects size prop on the svg', () => {
  const { container } = render(<Labi size={64} />);
  expect(container.querySelector('svg')?.getAttribute('width')).toBe('64');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/web && npx vitest run src/test/fe-siswa-ui-01-mascot.test.tsx`
Expected: FAIL (module not found).

- [ ] **Step 3: Implement `Labi` and append CSS**

Draw one friendly student character in inline SVG; show expression via swapped facial/prop elements (reuse the SVG shapes proven in the approved mockup: round head, cap, eyes/smile, wave arm, book, star-eyes, zzz). In `globals.css`, add keyframes `labi-bob`, `labi-wave`, `labi-blink`, `labi-float` and map `.labi-anim--wave/happy/study/sleepy/cheer` to them, all nested so they only apply inside `.portal-siswa`. Wrap the whole block so that under `@media (prefers-reduced-motion: reduce)` all `animation: none`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd apps/web && npx vitest run src/test/fe-siswa-ui-01-mascot.test.tsx`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/web/src/features/portal/mascot/labi.tsx apps/web/src/app/globals.css apps/web/src/test/fe-siswa-ui-01-mascot.test.tsx
git commit -m "feat(fe-siswa-ui-01): add Labi mascot component and scoped animations"
```

---

### Task 2: Student-only state components (loading / empty / error)

**Files:**
- Create: `apps/web/src/features/portal/mascot/student-states.tsx`
- Test: `apps/web/src/test/fe-siswa-ui-01-states.test.tsx`

**Interfaces:**
- Consumes: `Labi` from Task 1.
- Produces: `StudentLoading(props: { label?: string })`, `StudentEmpty(props: { title: string; hint?: string })`, `StudentError(props: { message: string; onRetry?: () => void })`. Each renders a `Labi` (loading→`wave`, empty→`sleepy`, error→`study`), the given Bahasa Indonesia text, and — for `StudentError` with `onRetry` — a "Coba lagi" button wired to `onRetry`.

- [ ] **Step 1: Write failing tests**

```tsx
it('FE-SISWA-UI-01-states: StudentLoading shows label and mascot', () => {
  const { container, getByText } = render(<StudentLoading label="Memuat..." />);
  expect(getByText('Memuat...')).toBeTruthy();
  expect(container.querySelector('svg')).toBeTruthy();
});
it('FE-SISWA-UI-01-states: StudentError renders message and retry calls onRetry', () => {
  const onRetry = vi.fn();
  const { getByText } = render(<StudentError message="Gagal" onRetry={onRetry} />);
  fireEvent.click(getByText('Coba lagi'));
  expect(onRetry).toHaveBeenCalledOnce();
});
it('FE-SISWA-UI-01-states: StudentEmpty shows title and hint', () => {
  const { getByText } = render(<StudentEmpty title="Belum ada tugas" hint="Santai dulu" />);
  expect(getByText('Belum ada tugas')).toBeTruthy();
  expect(getByText('Santai dulu')).toBeTruthy();
});
```

- [ ] **Step 2: Run to verify fail.** `npx vitest run src/test/fe-siswa-ui-01-states.test.tsx` → FAIL.
- [ ] **Step 3: Implement the three components** in `student-states.tsx` using `Labi` and themed wrapper classes.
- [ ] **Step 4: Run to verify pass.**
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): add student-only loading/empty/error states with mascot`.

---

### Task 3: Fun theme foundation + re-themed student shell (layout)

**Files:**
- Modify: `apps/web/src/app/globals.css` (add `.portal-siswa` theme tokens: ceria palette as CSS variables, rounded/surface helpers)
- Modify: `apps/web/src/app/(portal)/portal/siswa/layout.tsx` (wrap in `.portal-siswa`, re-skin sidebar + header playful/game-like; keep nav links, role switcher, kiosk-mode branch, avatar fallback)
- Test: `apps/web/src/test/fe-siswa-ui-01-shell.test.tsx`

**Interfaces:**
- Consumes: `Labi` (Task 1) for a small sidebar/header mascot (non-kiosk only).
- Produces: a root element carrying class `portal-siswa` that all child pages inherit the theme from.

- [ ] **Step 1: Write failing tests** (mock `@/lib/auth-context`, `@/lib/use-student-data`, `@/components/role-guard` per existing test patterns):

```tsx
it('FE-SISWA-UI-01-shell: wraps student portal in .portal-siswa scope', () => {
  const { container } = render(<StudentLayout><div>isi</div></StudentLayout>);
  expect(container.querySelector('.portal-siswa')).toBeTruthy();
});
it('FE-SISWA-UI-01-shell: still renders all student nav links', () => {
  const { getByText } = render(<StudentLayout><div/></StudentLayout>);
  expect(getByText('Ringkasan & Beranda')).toBeTruthy();
});
it('FE-SISWA-UI-01-shell: renders with missing avatar and a very long name without throwing', () => {
  // mock dashboard name to a 120-char string, photoUrl null → expect no throw, initials fallback present
});
```

- [ ] **Step 2: Run to verify fail.**
- [ ] **Step 3: Implement.** Add `.portal-siswa` CSS variable tokens + helper classes in `globals.css`. In `layout.tsx` add the `portal-siswa` class to the outermost non-kiosk wrapper, restyle sidebar/header to the playful theme using those tokens, keep logic identical. Do **not** apply the wrapper (or ambient mascot) in the kiosk `isExamTakingSession` branch.
- [ ] **Step 4: Run to verify pass.**
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): fun theme foundation and re-skinned student shell`.

---

### Task 4: Student UI primitives (page header, stat tile, section card)

**Files:**
- Create: `apps/web/src/features/portal/mascot/student-ui.tsx`
- Test: `apps/web/src/test/fe-siswa-ui-01-ui.test.tsx`

**Interfaces:**
- Produces:
  - `StudentPageHeader(props: { title: string; subtitle?: string; icon?: LucideIcon; expression?: LabiExpression })` — playful page header, optional small Labi.
  - `StatTile(props: { label: string; value: React.ReactNode; icon?: LucideIcon; tone?: 'coral'|'sun'|'mint'|'grape'|'sky' })` — colorful tile.
  - `SectionCard(props: { title?: string; children: React.ReactNode; className?: string })` — rounded themed card.

- [ ] **Step 1: Write failing tests**

```tsx
it('FE-SISWA-UI-01-ui: StudentPageHeader shows title and subtitle', () => { /* assert both texts render */ });
it('FE-SISWA-UI-01-ui: StatTile shows label and value with tone class', () => {
  const { getByText, container } = render(<StatTile label="Tugas" value={2} tone="coral" />);
  expect(getByText('Tugas')).toBeTruthy(); expect(getByText('2')).toBeTruthy();
  expect(container.querySelector('.tile-coral')).toBeTruthy();
});
it('FE-SISWA-UI-01-ui: SectionCard renders children', () => { /* assert child text */ });
```

- [ ] **Step 2: Run to verify fail.**
- [ ] **Step 3: Implement** the three primitives using theme tokens; tones map to `.tile-<tone>` classes defined in Task 3's CSS scope.
- [ ] **Step 4: Run to verify pass.**
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): add reusable student UI primitives`.

---

### Task 5: Re-theme the dashboard view

**Files:**
- Modify: `apps/web/src/features/portal/student-dashboard-view.tsx`
- Test: `apps/web/src/test/fe-siswa-ui-01-dashboard.test.tsx`

**Interfaces:**
- Consumes: `Labi`, `StudentLoading`/`StudentError` (Tasks 1–2), `StudentPageHeader`/`StatTile`/`SectionCard` (Task 4). Existing hooks `useStudentDashboard`, `useStudentTasks`, `useSchoolAnnouncements` unchanged. Points, if shown, via the existing points hook as-is.

- [ ] **Step 1: Write failing tests** (mock the hooks as in `fe-student-multiprogram-dashboard.test.tsx`):

```tsx
it('FE-SISWA-UI-01-dashboard: greets the student by name with a mascot', () => {
  // mock name "Raka" → expect greeting text containing "Raka" and an <svg>
});
it('FE-SISWA-UI-01-dashboard: renders stat tiles from existing data (active tasks, attendance)', () => {
  // mock 2 active tasks + attendanceRate 96 → expect "2" and "96%" present
});
it('FE-SISWA-UI-01-dashboard: shows StudentError with retry on dashboard error', () => {
  // mock isError → expect "Coba lagi"
});
```

- [ ] **Step 2: Run to verify fail.**
- [ ] **Step 3: Implement.** Replace the current header/cards markup with the playful hero (Labi greeting + contextual message built from existing counts), `StatTile` row from existing data, `SectionCard` task list, keeping program widgets (`TahfidzWidget` etc.) and `SafetyCounselingCard` intact. Swap `LoadingSpinner`/`ErrorState` usage here for `StudentLoading`/`StudentError`. Points shown read from existing hook; no new logic.
- [ ] **Step 4: Run to verify pass.**
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): playful student dashboard with mascot greeting`.

---

### Task 6: Celebration (confetti) on task/exam submit

**Files:**
- Create: `apps/web/src/features/portal/mascot/confetti.tsx`
- Modify: `apps/web/src/app/(portal)/portal/siswa/tugas/page.tsx` (fire on successful submit)
- Modify: `apps/web/src/app/(portal)/portal/siswa/cbt/[id]/page.tsx` and/or `ujian/[id]/page.tsx` (fire on submit confirmation only)
- Modify: `apps/web/src/app/globals.css` (confetti keyframes, reduced-motion guarded)
- Test: `apps/web/src/test/fe-siswa-ui-01-confetti.test.tsx`

**Interfaces:**
- Produces: `Confetti(props: { show: boolean; pieces?: number })` — renders celebratory pieces only while `show` is true; renders nothing when `show` is false; emits no animated nodes under reduced motion.

- [ ] **Step 1: Write failing tests**

```tsx
it('FE-SISWA-UI-01-confetti: renders nothing when show=false', () => {
  const { container } = render(<Confetti show={false} />); expect(container.firstChild).toBeNull();
});
it('FE-SISWA-UI-01-confetti: renders pieces when show=true', () => {
  const { container } = render(<Confetti show pieces={10} />);
  expect(container.querySelectorAll('.confetti-piece').length).toBe(10);
});
```

- [ ] **Step 2: Run to verify fail.**
- [ ] **Step 3: Implement** `Confetti`; wire a `show` state that flips true on the existing submit-success handler in the task page and on the exam submit confirmation (never during active questions). Confetti auto-hides after a short timeout.
- [ ] **Step 4: Run to verify pass.**
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): celebration confetti on task and exam submit`.

---

### Task 7: Re-theme academic pages (nilai, tugas, jadwal, cbt list)

**Files (Modify):**
- `apps/web/src/app/(portal)/portal/siswa/nilai/page.tsx`
- `apps/web/src/app/(portal)/portal/siswa/tugas/page.tsx`
- `apps/web/src/app/(portal)/portal/siswa/jadwal/page.tsx`
- `apps/web/src/app/(portal)/portal/siswa/cbt/page.tsx`
- Test: `apps/web/src/test/fe-siswa-ui-01-academic-pages.test.tsx`

**Interfaces:** Consumes Task 4 primitives + Task 2 states. No data/hook changes.

- [ ] **Step 1: Write failing tests** — for each page, assert it renders `StudentPageHeader` title and that loading/error paths use `StudentLoading`/`StudentError` (mock hooks; assert "Coba lagi" appears on error). One `describe('FE-SISWA-UI-01: academic pages')` with a case per page.
- [ ] **Step 2: Run to verify fail.**
- [ ] **Step 3: Implement** — apply `StudentPageHeader`, `SectionCard`, `StatTile`, and student states on each page; keep all data wiring and permissions identical.
- [ ] **Step 4: Run to verify pass.**
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): re-theme academic student pages`.

---

### Task 8: Re-theme program & remaining pages

**Files (Modify):**
- `.../siswa/hafalan/page.tsx`, `.../cambridge/page.tsx`, `.../ppi/page.tsx`, `.../p5/page.tsx`, `.../poin/page.tsx`, `.../tagihan/page.tsx`, `.../konseling/page.tsx`
- Test: `apps/web/src/test/fe-siswa-ui-01-remaining-pages.test.tsx`

**Interfaces:** Consumes Task 4 primitives + Task 2 states. No data/hook changes.

- [ ] **Step 1: Write failing tests** — per page assert `StudentPageHeader` title renders and error path uses `StudentError`. **Special case `konseling`:** assert the counseling/BK content keeps a calm, non-playful tone — no `Confetti` and no celebratory mascot expression on that page (assert no `.confetti-piece` and header uses a neutral expression).
- [ ] **Step 2: Run to verify fail.**
- [ ] **Step 3: Implement** — apply theme to each page; `poin` page displays existing points data dressed up (no new logic); keep `konseling` restrained per constraint.
- [ ] **Step 4: Run to verify pass.**
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): re-theme program and remaining student pages`.

---

### Task 9: Exam-taking pages polish + full verification

**Files:**
- Modify: `apps/web/src/app/(portal)/portal/siswa/cbt/[id]/page.tsx`, `.../ujian/[id]/page.tsx` (light friendly touches ONLY where they don't break kiosk focus; success moment reuses Task 6 confetti)
- Test: `apps/web/src/test/fe-siswa-ui-01-exam-focus.test.tsx`

- [ ] **Step 1: Write failing test** — assert the active-exam view does **not** render ambient mascot/confetti while questions are in progress (only on submit confirmation).
- [ ] **Step 2: Run to verify fail.**
- [ ] **Step 3: Implement** minimal, focus-safe polish.
- [ ] **Step 4: Full verification.** Run:
  - `cd apps/web && npx vitest run` (all tests pass; coverage not reduced)
  - `npm run lint` and `npx tsc --noEmit` (or project's typecheck script)
  - `npm run build`
  - Spot-check a guru/admin page test still passes to confirm theme isolation.
- [ ] **Step 5: Commit** `feat(fe-siswa-ui-01): focus-safe exam polish and final verification`.

---

## Self-Review Notes
- **Spec coverage:** mascot (T1), states (T2), theme + shell + isolation (T3), primitives (T4), dashboard (T5), confetti (T6), all 15 pages (T5 dashboard, T7 four academic, T8 seven program/misc, T9 two exam) ✓; no-new-dep & scoped theme & reduced-motion are global constraints enforced in T1/T3/T6 tests ✓; points-as-is in T5/T8 ✓; konseling tone in T8 ✓.
- **Review Focus** items each mapped to an owning task's test (reduced motion T1/T6, isolation T3, state preservation T2/T5/T7/T8, missing avatar/long name T3/T5, exam focus T6/T9).
