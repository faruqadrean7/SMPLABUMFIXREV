<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun staf sekolah (selain guru/siswa/ortu).
 *
 * Tanpa akun ini sekolah tidak bisa beroperasi: tidak ada TU yang mengelola data,
 * tidak ada bendahara yang menagih, tidak ada kepsek yang memantau, tidak ada
 * pustakawan/BK/kesiswaan/humas.
 *
 * Idempoten: username yang sudah ada dilewati.
 *
 *   php artisan db:seed --class=SmpStaffAccountSeeder
 */
class SmpStaffAccountSeeder extends Seeder
{
    private const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    private const PASSWORD = 'Password123!';

    /**
     * username => [nama, kode peran, jabatan guru (null = bukan guru)]
     */
    private const AKUN = [
        'admin.tu'    => ['Rina Kusumawati, S.A.P.', 'admin_tu', null],
        'bendahara'   => ['Dwi Handayani, S.E.', 'bendahara', null],
        'kepsek'      => ['Dr. Bambang Suryanto, M.Pd.', 'principal', 'Kepala Sekolah'],
        'wakakurikulum' => ['Siti Nurhaliza, M.Pd.', 'admin_tu', 'Wakil Kepala Sekolah Bidang Kurikulum'],
        'bk'          => ['Ayu Prameswari, S.Psi.', 'counselor', 'Guru BK'],
        'pustakawan'  => ['Yusuf Maulana, S.IP.', 'librarian', null],
        'kesiswaan'   => ['Hendra Gunawan, S.Pd.', 'student_affairs', 'Wakil Kepala Sekolah Bidang Kesiswaan'],
        'humas'       => ['Maya Anggraini, S.I.Kom.', 'public_relations', null],
        'sarpras'     => ['Agus Setiawan, S.T.', 'facility_staff', null],
        'ppdb'        => ['Nadia Rahmawati, S.Pd.', 'ppdb_committee', null],
    ];

    public function run(): void
    {
        $hash = Hash::make(self::PASSWORD);
        $now = now();
        $dibuat = 0;
        $dilewati = 0;

        foreach (self::AKUN as $username => [$nama, $roleCode, $jabatan]) {
            $role = Role::firstOrCreate(['code' => $roleCode], ['name' => Str::headline($roleCode), 'is_system' => true]);

            $user = User::where('username', $username)->first();

            if ($user) {
                // Pastikan peran & status tetap benar walau akun sudah ada.
                $user->roles()->syncWithoutDetaching([$role->id]);
                $dilewati++;

                continue;
            }

            $userId = (string) Str::uuid7();

            DB::table('users')->insert([
                'id' => $userId,
                'school_id' => self::SCHOOL_ID,
                'username' => $username,
                'email' => $username.'@labschool.sch.id',
                'phone' => null,
                'password_hash' => $hash,
                'status' => 'active',
                'must_change_password' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $userId,
                'role_id' => $role->id,
                'created_at' => $now,
            ]);

            // Kepsek, wakasek, dan guru BK juga butuh profil guru agar
            // fitur yang memeriksa profil guru (jurnal, presensi mandiri) bisa dipakai.
            if ($jabatan) {
                $sudahAda = Teacher::where('user_id', $userId)->exists();
                if (! $sudahAda) {
                    DB::table('teachers')->insert([
                        'id' => (string) Str::uuid7(),
                        'school_id' => self::SCHOOL_ID,
                        'user_id' => $userId,
                        'nip' => null,
                        'nuptk' => null,
                        'full_name' => $nama,
                        'position' => $jabatan,
                        'status' => 'tetap',
                        'photo_file_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $dibuat++;
        }

        $this->command?->info("Akun staf: {$dibuat} dibuat, {$dilewati} sudah ada.");
        $this->command?->line('  Password awal: '.self::PASSWORD.' (wajib ganti saat login).');
        $this->command?->line('  Username: '.implode(', ', array_keys(self::AKUN)));
    }
}
