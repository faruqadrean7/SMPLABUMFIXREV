<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CbtLoadCleanupCommand extends Command
{
    protected $signature = 'cbt:load-cleanup {--dry-run : Only show what would be deleted without executing} {--force : Force cleanup without confirmation}';

    protected $description = 'Clean up CBT staging load test data safely';

    public function handle(): int
    {
        // SEC-SAFE-01: Production Safety Guard
        if (app()->isProduction()) {
            $this->error('CRITICAL SAFETY VIOLATION: cbt:load-cleanup is strictly forbidden in the production environment!');

            return self::FAILURE;
        }

        $dbName = (string) DB::connection()->getDatabaseName();
        if (str_contains(strtolower($dbName), 'prod')) {
            $this->error("CRITICAL SAFETY VIOLATION: Database '{$dbName}' appears to be production! Cleanup aborted.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('Scanning CBT staging load test artifacts...');

        $exam = Exam::where('title', 'Ujian Biologi Uji Beban 500 Siswa')->first();
        $bank = QuestionBank::where('name', 'Bank Soal Load Drill')->first();
        $room = Room::withTrashed()->where('code', 'LAB-CBT-500')->first();
        $subject = Subject::withTrashed()->where('code', 'BIO-LOAD')->first();
        $stagingStudents = Student::withTrashed()
            ->where('full_name', 'like', 'Siswa Uji Beban %')
            ->get();
        $stagingStudentIds = $stagingStudents->pluck('id')->all();
        $stagingUserIds = $stagingStudents->pluck('user_id')->filter()->all();
        $studentsCount = count($stagingStudentIds);
        $usersCount = count($stagingUserIds);

        $sessionIds = [];
        $answerCount = 0;
        $violationCount = 0;
        $participantCount = 0;

        if ($exam) {
            $sessionIds = ExamSession::where('exam_id', $exam->id)->pluck('id')->all();
            $answerCount = ExamAnswer::whereIn('session_id', $sessionIds)->count();
            $violationCount = ExamViolation::whereIn('session_id', $sessionIds)->count();
            $participantCount = ExamParticipant::where('exam_id', $exam->id)->count();
        }

        $this->table(
            ['Artifact Entity', 'Count Found'],
            [
                ['Exam Sessions', count($sessionIds)],
                ['Saved Answers', $answerCount],
                ['Recorded Violations', $violationCount],
                ['Exam Participants', $participantCount],
                ['Staging Students', $studentsCount],
                ['Staging Users', $usersCount],
                ['Exam Record', $exam ? 1 : 0],
                ['Question Bank', $bank ? 1 : 0],
                ['Staging Room (LAB-CBT-500)', $room ? 1 : 0],
                ['Staging Subject (BIO-LOAD)', $subject ? 1 : 0],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN: No records were modified or deleted.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Are you sure you want to permanently delete all CBT staging load test records?')) {
            $this->info('Cleanup cancelled.');

            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            if ($exam) {
                // Delete answers and violations
                ExamAnswer::whereIn('session_id', $sessionIds)->delete();
                ExamViolation::whereIn('session_id', $sessionIds)->delete();
                ExamSession::where('exam_id', $exam->id)->delete();
                ExamParticipant::where('exam_id', $exam->id)->delete();
                ExamQuestion::where('exam_id', $exam->id)->delete();
                ExamRoom::where('exam_id', $exam->id)->delete();
                $exam->delete();
            }

            if ($bank) {
                $questions = Question::where('bank_id', $bank->id)->get();
                foreach ($questions as $q) {
                    QuestionOption::where('question_id', $q->id)->delete();
                    $q->delete();
                }
                $bank->delete();
            }

            // Delete students & users strictly isolated to staging test records
            foreach ($stagingStudents as $student) {
                $student->forceDelete();
            }

            if (! empty($stagingUserIds)) {
                $users = User::withTrashed()->whereIn('id', $stagingUserIds)->get();
                foreach ($users as $u) {
                    $u->roles()->detach();
                    $u->tokens()->delete();
                    $u->forceDelete();
                }
            }

            $room = Room::withTrashed()->where('code', 'LAB-CBT-500')->first();
            if ($room) {
                $room->forceDelete();
            }

            $subject = Subject::withTrashed()->where('code', 'BIO-LOAD')->first();
            if ($subject) {
                $subject->forceDelete();
            }

            DB::commit();
            $this->info('CBT staging load test records cleaned up successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to cleanup CBT staging load test records: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
