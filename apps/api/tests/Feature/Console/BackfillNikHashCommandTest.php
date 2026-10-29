<?php

namespace Tests\Feature\Console;

use App\Models\Applicant;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Student;
use App\Models\User;
use App\Traits\HasNikBlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackfillNikHashCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_be_sec_01_backfill_nik_hash_updates_records_with_missing_hash(): void
    {
        $user = User::create([
            'username' => 'siswa_hash_test',
            'email' => 'siswa_hash@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'nis' => '12345',
            'nisn' => '0012345678',
            'nik_enc' => '3578010202100099',
            'full_name' => 'Siswa Hash Test',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2025-07-01',
            'status' => 'aktif',
        ]);

        // Simulasikan data lama dengan mengosongkan nik_hash langsung di database
        DB::table('students')->where('id', $student->id)->update(['nik_hash' => null]);
        $this->assertNull(Student::find($student->id)->nik_hash);

        // Jalankan perintah backfill
        $this->artisan('sistlabum:backfill-nik-hash')
            ->expectsOutputToContain('Backfill selesai')
            ->assertExitCode(0);

        // Pastikan nik_hash terisi dengan hash yang benar
        $updatedStudent = Student::find($student->id);
        $expectedHash = HasNikBlindIndex::hashNik('3578010202100099');
        $this->assertNotNull($updatedStudent->nik_hash);
        $this->assertSame($expectedHash, $updatedStudent->nik_hash);
    }

    public function test_be_sec_01_backfill_nik_hash_dry_run_does_not_mutate_records(): void
    {
        $user = User::create([
            'username' => 'siswa_dry_run',
            'email' => 'siswa_dry@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'nis' => '12346',
            'nisn' => '0012345679',
            'nik_enc' => '3578010202100088',
            'full_name' => 'Siswa Dry Run',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2025-07-01',
            'status' => 'aktif',
        ]);

        DB::table('students')->where('id', $student->id)->update(['nik_hash' => null]);

        $this->artisan('sistlabum:backfill-nik-hash', ['--dry-run' => true])
            ->expectsOutputToContain('Simulasi selesai')
            ->assertExitCode(0);

        // Data tidak boleh berubah pada dry-run
        $this->assertNull(Student::find($student->id)->nik_hash);
    }

    public function test_be_sec_01_backfill_deterministic_hash_matches_for_applicant(): void
    {
        $user = User::create([
            'username' => 'calon_hash_test',
            'email' => 'calon_hash@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $wave = PPDBWave::create([
            'name' => 'Gelombang 1 Test',
            'year' => 2026,
            'open_at' => now()->subDay(),
            'close_at' => now()->addDays(10),
            'result_publish_at' => now()->addDays(20),
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'reguler_test',
            'name' => 'Reguler Test',
            'quota' => 10,
        ]);

        $applicant = Applicant::create([
            'user_id' => $user->id,
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'registration_no' => 'PPDB-HASH-01',
            'nik_enc' => '3578010202100077',
            'full_name' => 'Calon Siswa Test',
            'birth_date' => '2013-05-15',
            'status' => 'submitted',
        ]);

        DB::table('applicants')->where('id', $applicant->id)->update(['nik_hash' => null]);

        $this->artisan('sistlabum:backfill-nik-hash')
            ->assertExitCode(0);

        $updatedApplicant = Applicant::find($applicant->id);
        $expectedHash = HasNikBlindIndex::hashNik('3578010202100077');
        $this->assertSame($expectedHash, $updatedApplicant->nik_hash);
    }
}
