<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CbtStagingLoadSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();
        try {
            $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
            $adminUser = User::first() ?? User::create([
                'username' => 'admin_cbt',
                'email' => 'admin_cbt@labschool.sch.id',
                'password_hash' => Hash::make('Admin123!@#'),
                'status' => 'active',
            ]);

            $subject = Subject::firstOrCreate(['code' => 'BIO-LOAD'], ['name' => 'Biologi Uji Beban', 'subject_group' => 'pilihan']);
            $room = Room::firstOrCreate(['code' => 'LAB-CBT-500'], ['name' => 'Laboratorium CBT 500', 'room_type' => 'laboratorium', 'capacity' => 500]);

            // Exam
            $exam = Exam::firstOrCreate(
                ['title' => 'Ujian Biologi Uji Beban 500 Siswa'],
                [
                    'subject_id' => $subject->id,
                    'created_by' => $adminUser->id,
                    'exam_type' => 'ujian_akhir',
                    'duration_min' => 120,
                    'start_at' => Carbon::now()->subMinutes(10),
                    'end_at' => Carbon::now()->addDays(7),
                    'status' => 'aktif',
                    'passing_grade' => 75.0,
                    'shuffle_questions' => false,
                    'shuffle_options' => false,
                ]
            );

            // Exam Room with token BIO123
            $examRoom = ExamRoom::firstOrCreate(
                ['exam_id' => $exam->id, 'token' => 'BIO123'],
                [
                    'room_id' => $room->id,
                    'proctor_id' => $adminUser->id,
                    'session_no' => 1,
                    'token_expires_at' => Carbon::now()->addDays(7),
                ]
            );

            // Question Bank and Questions
            $bank = QuestionBank::firstOrCreate(
                ['name' => 'Bank Soal Load Drill'],
                ['subject_id' => $subject->id, 'owner_id' => $adminUser->id]
            );

            for ($q = 1; $q <= 10; $q++) {
                $question = Question::firstOrCreate(
                    [
                        'bank_id' => $bank->id,
                        'cp_code' => "CP-BIO-{$q}",
                    ],
                    [
                        'question_type' => 'pilihan_ganda',
                        'body' => ['text' => "Pertanyaan Uji Beban {$q}: Manakah organel sel yang berfungsi respirasi seluler?"],
                        'weight' => 10.0,
                        'difficulty' => 2,
                    ]
                );

                foreach (['A', 'B', 'C', 'D'] as $opt) {
                    QuestionOption::firstOrCreate(
                        ['question_id' => $question->id, 'label' => $opt],
                        [
                            'body' => ['text' => "Pilihan {$opt} untuk soal {$q}"],
                            'is_correct' => ($opt === 'A'),
                        ]
                    );
                }

                ExamQuestion::firstOrCreate(
                    ['exam_id' => $exam->id, 'question_id' => $question->id],
                    ['sort_order' => $q, 'weight_override' => 10.0]
                );
            }

            // Seed 500 student users and participants
            $now = Carbon::now();
            $hashedPassword = Hash::make('Password123!');

            for ($i = 1; $i <= 500; $i++) {
                $email = "student{$i}@labschool.sch.id";
                $username = "student{$i}";

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'username' => $username,
                        'password_hash' => $hashedPassword,
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );

                if (! $user->roles()->where('role_id', $studentRole->id)->exists()) {
                    $user->roles()->attach($studentRole->id);
                }

                $student = Student::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nisn' => sprintf('00%08d', $i),
                        'nis' => sprintf('26%04d', $i),
                        'nik_enc' => '357801010101'.sprintf('%04d', $i),
                        'full_name' => "Siswa Uji Beban {$i}",
                        'gender' => ($i % 2 === 0) ? 'P' : 'L',
                        'birth_place' => 'Malang',
                        'birth_date' => '2008-01-01',
                        'status' => 'aktif',
                        'entry_date' => '2024-07-15',
                    ]
                );

                ExamParticipant::firstOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $student->id],
                    [
                        'exam_room_id' => $examRoom->id,
                    ]
                );
            }

            DB::commit();
            $this->command->info("CBT_STAGING_SEEDED: Exam ID={$exam->id}, Room Token=BIO123, 500 Students created.");
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
