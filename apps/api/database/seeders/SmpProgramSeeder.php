<?php

namespace Database\Seeders;

use App\Models\ProgramMaterial;
use App\Models\ProgramTarget;
use App\Models\ProgramTask;
use App\Models\Student;
use App\Models\StudentProgramProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Data program khusus (jalur) untuk SISTLABUM.
 *
 * Mengisi:
 *   - student_program_profiles : meta per jalur
 *       tahfidz  -> current_juz, target_juz, current_surah, last_tasmi_grade, tasmi_evaluator
 *       icp      -> cambridge_candidate_id, checkpoint_readiness{subject:{score,status}}
 *       inklusi  -> gpk{name,contact_phone,room}, ppi_milestones[]
 *   - program_materials        : bahan ajar per jalur (target_scope = all)
 *   - program_targets          : target capaian per siswa
 *   - program_tasks            : tugas program per jalur
 *
 * Idempoten: aman dijalankan berulang.
 *
 *   php artisan db:seed --class=SmpProgramSeeder
 */
class SmpProgramSeeder extends Seeder
{
    private const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    private const SURAH = ['An-Naba', 'An-Nazi\'at', 'Abasa', 'At-Takwir', 'Al-Infitar', 'Al-Mutaffifin'];

    private const EVALUATOR = ['Ust. Ahmad Fauzi, S.Pd.I', 'Ustzh. Nur Hidayah, S.Ag', 'Ust. Ridwan Hakim, Lc'];

    private const GPK = [
        ['name' => 'Ibu Retno Wulandari, S.Pd', 'contact_phone' => '081234500011', 'room' => 'Ruang PPI 1'],
        ['name' => 'Bapak Hendra Setiawan, S.Pd', 'contact_phone' => '081234500012', 'room' => 'Ruang PPI 2'],
    ];

    public function run(): void
    {
        $this->seedProfiles();
        $this->seedMaterials();
        $this->seedTasks();
        $this->seedTargets();
    }

    private function seedProfiles(): void
    {
        $students = Student::whereNotNull('program_type')
            ->where('program_type', '!=', 'reguler')
            ->get();

        $i = 0;
        foreach ($students as $student) {
            $i++;
            $meta = match ($student->program_type) {
                'tahfidz' => [
                    'current_juz' => ($i % 4) + 1,
                    'target_juz' => 5,
                    'current_surah' => self::SURAH[$i % count(self::SURAH)],
                    'last_tasmi_grade' => $i % 3 === 0 ? 'B' : 'A',
                    'tasmi_evaluator' => self::EVALUATOR[$i % count(self::EVALUATOR)],
                ],
                'icp' => [
                    'cambridge_candidate_id' => sprintf('CAM-2026-%04d', $i),
                    'checkpoint_readiness' => [
                        'Mathematics' => ['score' => 4.5 + ($i % 3) * 0.5, 'status' => 'Siap ujian'],
                        'Science' => ['score' => 4.0 + ($i % 4) * 0.5, 'status' => 'Latihan intensif'],
                        'English' => ['score' => 5.0 + ($i % 2) * 0.5, 'status' => 'Siap ujian'],
                    ],
                ],
                'inklusi' => [
                    'gpk' => self::GPK[$i % count(self::GPK)],
                    'ppi_milestones' => [
                        ['id' => (string) Str::uuid7(), 'title' => 'Membaca kata dua suku kata tanpa bantuan', 'is_completed' => true],
                        ['id' => (string) Str::uuid7(), 'title' => 'Menulis kalimat sederhana dengan ejaan benar', 'is_completed' => $i % 2 === 0],
                        ['id' => (string) Str::uuid7(), 'title' => 'Mengikuti instruksi dua langkah di kelas', 'is_completed' => false],
                    ],
                ],
                default => [],
            };

            StudentProgramProfile::updateOrCreate(
                ['student_id' => $student->id],
                ['program_type' => $student->program_type, 'meta_data' => $meta]
            );
        }

        $this->command?->info("profil program: {$students->count()} siswa");
    }

    private function seedMaterials(): void
    {
        $materials = [
            ['tahfidz', 'Panduan Tahsin & Tajwid Level 1', 'Dasar makhorijul huruf dan hukum bacaan untuk setoran juz 30.', 'document'],
            ['tahfidz', 'Jurnal Murajaah Mingguan', 'Format catatan murajaah harian yang diisi bersama musyrif.', 'document'],
            ['icp', 'Cambridge Checkpoint Past Papers — Mathematics', 'Kumpulan soal checkpoint Matematika beserta kunci pembahasan.', 'link'],
            ['icp', 'Academic Vocabulary Builder B2', 'Daftar kosakata akademik wajib untuk kelas internasional.', 'document'],
            ['inklusi', 'Modul Adaptif Matematika — Operasi Hitung', 'Materi disederhanakan dengan gambar pendukung dan langkah bertahap.', 'document'],
            ['inklusi', 'Kartu Komunikasi Visual', 'Kartu bantu komunikasi untuk kegiatan belajar sehari-hari.', 'document'],
            ['reguler', 'Panduan Belajar Mandiri', 'Strategi belajar efektif dan manajemen waktu untuk siswa SMP.', 'link'],
        ];

        foreach ($materials as [$program, $title, $desc, $type]) {
            ProgramMaterial::updateOrCreate(
                ['program_type' => $program, 'title' => $title, 'target_scope' => 'all'],
                [
                    'school_id' => self::SCHOOL_ID,
                    'description' => $desc,
                    'material_type' => $type,
                    'external_url' => $type === 'link' ? 'https://www.cambridgeinternational.org/' : null,
                ]
            );
        }

        $this->command?->info('materi program: '.ProgramMaterial::count().' baris');
    }

    private function seedTasks(): void
    {
        $tasks = [
            ['tahfidz', 'Setoran Hafalan Juz 30 (Surah An-Naba)', 'Setorkan hafalan di hadapan musyrif, sertakan rekaman audio.', 7],
            ['tahfidz', 'Murajaah Pekanan 1 Juz', 'Murajaah minimal 5 halaman per hari selama sepekan.', 14],
            ['icp', 'Cambridge Checkpoint Practice Set 1', 'Kerjakan 20 soal Mathematics checkpoint, unggah lembar jawaban.', 10],
            ['icp', 'English Academic Essay (250 kata)', 'Tulis esai argumentatif sesuai rubrik Cambridge Writing.', 12],
            ['inklusi', 'Lembar Kerja Adaptif: Operasi Hitung', 'Kerjakan 10 soal dengan bantuan garis bilangan.', 7],
            ['inklusi', 'Dokumentasi Perkembangan Belajar', 'Unggah foto kegiatan belajar bersama GPK.', 10],
        ];

        foreach ($tasks as [$program, $title, $instructions, $dueDays]) {
            ProgramTask::updateOrCreate(
                ['program_type' => $program, 'title' => $title, 'target_scope' => 'all'],
                [
                    'school_id' => self::SCHOOL_ID,
                    'instructions' => $instructions,
                    'due_at' => Carbon::now()->addDays($dueDays)->setTime(23, 59),
                ]
            );
        }

        $this->command?->info('tugas program: '.ProgramTask::count().' baris');
    }

    private function seedTargets(): void
    {
        $students = Student::whereIn('program_type', ['tahfidz', 'icp', 'inklusi'])->get();
        $created = 0;

        foreach ($students as $student) {
            $rows = match ($student->program_type) {
                'tahfidz' => [
                    ['Hafalan Juz 30', 'hafalan', '5 juz', '2 juz', 'Semester Ganjil', 'on_track'],
                    ['Tasmi\' Bulanan', 'tasmi', 'A', 'A', 'November 2026', 'achieved'],
                ],
                'icp' => [
                    ['Cambridge Checkpoint Mathematics', 'akademik', '5.0', '4.5', 'Semester Ganjil', 'on_track'],
                    ['English Proficiency B2', 'bahasa', 'B2', 'B1+', 'Semester Ganjil', 'on_track'],
                ],
                'inklusi' => [
                    ['Kemampuan Membaca Fungsional', 'literasi', 'Level 2', 'Level 1', 'Semester Ganjil', 'on_track'],
                    ['Kemandirian Belajar', 'kemandirian', 'Mandiri', 'Terbimbing', 'Semester Ganjil', 'on_track'],
                ],
                default => [],
            };

            foreach ($rows as [$title, $domain, $target, $current, $period, $status]) {
                ProgramTarget::updateOrCreate(
                    ['student_id' => $student->id, 'title' => $title],
                    [
                        'school_id' => self::SCHOOL_ID,
                        'program_type' => $student->program_type,
                        'domain' => $domain,
                        'target_value' => $target,
                        'current_value' => $current,
                        'period' => $period,
                        'status' => $status,
                    ]
                );
                $created++;
            }
        }

        $this->command?->info("target program: {$created} baris untuk {$students->count()} siswa");
    }
}
