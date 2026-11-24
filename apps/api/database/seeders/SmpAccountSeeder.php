<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membuat akun login untuk data yang sudah disemai SmpDataSeeder.
 *
 * - Siswa   : username = NIS              (mis. 20240001)
 * - Guru    : username = NIP, atau guru_<urut> kalau NIP kosong (honorer)
 * - Ortu    : username = ortu_<NIS>_<ayah|ibu>
 * - Semua   : password awal Password123!, wajib ganti saat login pertama
 *
 * Idempoten: entri yang sudah punya user_id dilewati, jadi aman dijalankan ulang.
 *
 *   php artisan db:seed --class=SmpAccountSeeder
 */
class SmpAccountSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'Password123!';

    public function run(): void
    {
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru Mata Pelajaran', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Orang Tua / Wali Murid', 'is_system' => true]);

        $hash = Hash::make(self::DEFAULT_PASSWORD);
        $now = now();

        // Penting: baris `users` harus masuk SEBELUM kolom user_id di
        // students/teachers/guardians diisi, karena ada foreign key.
        $userRows = [];
        $roleRows = [];
        $updates = [];

        // ---------------- SISWA ----------------
        foreach (Student::whereNull('user_id')->orderBy('nis')->get() as $student) {
            $username = $this->uniqueUsername((string) $student->nis, $userRows);
            $userId = (string) Str::uuid7();

            $userRows[] = $this->userRow($userId, $student->school_id, $username, null, $hash, $now);
            $roleRows[] = ['user_id' => $userId, 'role_id' => $roleStudent->id, 'created_at' => $now];
            $updates[] = ['students', $student->id, $userId];
        }
        $studentCount = count($updates);

        // ---------------- GURU ----------------
        $teacherStart = count($updates);
        $i = 0;
        foreach (Teacher::whereNull('user_id')->orderBy('full_name')->get() as $teacher) {
            $i++;
            $base = $teacher->nip ?: 'guru_'.$i;
            $username = $this->uniqueUsername((string) $base, $userRows);
            $userId = (string) Str::uuid7();

            $userRows[] = $this->userRow($userId, $teacher->school_id, $username, null, $hash, $now);
            $roleRows[] = ['user_id' => $userId, 'role_id' => $roleTeacher->id, 'created_at' => $now];
            $updates[] = ['teachers', $teacher->id, $userId];
        }
        $teacherCount = count($updates) - $teacherStart;

        // ---------------- ORANG TUA ----------------
        $guardianStart = count($updates);
        foreach (Guardian::whereNull('user_id')->get() as $guardian) {
            // Username mengikuti NIS anak + peran (ayah/ibu) agar mudah ditebak saat uji.
            $link = DB::table('student_guardians')
                ->join('students', 'students.id', '=', 'student_guardians.student_id')
                ->where('student_guardians.guardian_id', $guardian->id)
                ->select('students.nis', 'student_guardians.relationship')
                ->first();

            $base = $link
                ? 'ortu_'.$link->nis.'_'.$link->relationship
                : 'ortu_'.substr(preg_replace('/\D/', '', (string) $guardian->phone), -8);

            $username = $this->uniqueUsername($base, $userRows);
            $userId = (string) Str::uuid7();

            $userRows[] = $this->userRow($userId, $guardian->school_id, $username, $guardian->phone, $hash, $now);
            $roleRows[] = ['user_id' => $userId, 'role_id' => $roleGuardian->id, 'created_at' => $now];
            $updates[] = ['guardians', $guardian->id, $userId];
        }
        $guardianCount = count($updates) - $guardianStart;

        DB::transaction(function () use ($userRows, $roleRows, $updates) {
            foreach (array_chunk($userRows, 200) as $chunk) {
                DB::table('users')->insert($chunk);
            }
            foreach (array_chunk($roleRows, 200) as $chunk) {
                DB::table('user_roles')->insertOrIgnore($chunk);
            }
            foreach ($updates as [$table, $id, $userId]) {
                DB::table($table)->where('id', $id)->update(['user_id' => $userId]);
            }
        });

        // Wali kelas butuh peran `homeroom` agar menu wali kelas muncul di portal guru.
        // Peran ini terpisah dari `teacher`: satu guru bisa mengampu mapel sekaligus wali kelas.
        $roleHomeroom = Role::firstOrCreate(['code' => 'homeroom'], ['name' => 'Wali Kelas', 'is_system' => true]);
        $homeroomUserIds = DB::table('classes')
            ->join('teachers', 'teachers.id', '=', 'classes.homeroom_teacher_id')
            ->whereNull('classes.deleted_at')
            ->whereNotNull('teachers.user_id')
            ->pluck('teachers.user_id')
            ->unique();

        $homeroomRows = $homeroomUserIds->map(fn ($id) => [
            'user_id' => $id,
            'role_id' => $roleHomeroom->id,
            'created_at' => $now,
        ])->all();

        foreach (array_chunk($homeroomRows, 200) as $chunk) {
            DB::table('user_roles')->insertOrIgnore($chunk);
        }

        $this->command?->info("Peran wali kelas diberikan ke {$homeroomUserIds->count()} guru.");
        $this->command?->info("Akun dibuat: {$studentCount} siswa, {$teacherCount} guru, {$guardianCount} ortu.");
        $this->command?->line('  Password awal semua akun: '.self::DEFAULT_PASSWORD.' (wajib ganti saat login).');
        $this->command?->line('  Login siswa pakai NIS sebagai username, di /masuk.');
    }

    private function userRow(string $id, ?string $schoolId, string $username, ?string $phone, string $hash, $now): array
    {
        return [
            'id' => $id,
            'school_id' => $schoolId,
            'username' => $username,
            'email' => null,
            'phone' => $phone,
            'password_hash' => $hash,
            'status' => 'active',
            'must_change_password' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Pastikan username unik, termasuk terhadap baris yang belum di-insert
     * pada batch ini (unique index users.username).
     */
    private function uniqueUsername(string $base, array $pending = []): string
    {
        $username = Str::lower(trim($base));
        if ($username === '') {
            $username = 'user_'.Str::lower(Str::random(6));
        }

        $taken = [];
        foreach ($pending as $row) {
            $taken[$row['username']] = true;
        }

        $candidate = $username;
        $i = 1;
        while (DB::table('users')->where('username', $candidate)->exists() || isset($taken[$candidate])) {
            $candidate = $username.$i;
            $i++;
        }

        return $candidate;
    }
}
