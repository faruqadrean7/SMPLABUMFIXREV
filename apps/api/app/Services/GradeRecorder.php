<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\GradeHistory;

/**
 * Satu-satunya pintu penulisan nilai ke buku nilai.
 *
 * Kebijakan: NILAI TERTINGGI MENANG.
 *   - nilai manual (diketik guru) tidak pernah diturunkan oleh nilai otomatis
 *   - nilai otomatis (tugas / CBT) dipakai bila lebih tinggi dari manual
 *   - `value` selalu berisi nilai efektif; nilai asal tetap tersimpan
 *     di `manual_value` / `auto_value` sehingga tidak ada data yang hilang
 *   - setiap perubahan dicatat di `grade_histories` (append only)
 */
class GradeRecorder
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_ASSIGNMENT = 'assignment';

    public const SOURCE_CBT = 'cbt';

    /**
     * Catat nilai manual (diketik guru / impor spreadsheet).
     */
    public function recordManual(
        string $studentId,
        string $componentId,
        string $periodId,
        float $value,
        ?string $changedBy = null,
        ?string $note = null,
    ): Grade {
        return $this->record(
            studentId: $studentId,
            componentId: $componentId,
            periodId: $periodId,
            manualValue: $value,
            autoValue: null,
            changeSource: self::SOURCE_MANUAL,
            changedBy: $changedBy,
            note: $note,
        );
    }

    /**
     * Catat nilai otomatis (hasil tugas atau ujian CBT).
     * Nilai manual yang lebih tinggi TIDAK ditimpa.
     */
    public function recordAuto(
        string $studentId,
        string $componentId,
        string $periodId,
        float $value,
        string $changeSource,
        ?string $changedBy = null,
        ?string $examId = null,
        ?string $note = null,
    ): Grade {
        return $this->record(
            studentId: $studentId,
            componentId: $componentId,
            periodId: $periodId,
            manualValue: null,
            autoValue: $value,
            changeSource: $changeSource,
            changedBy: $changedBy,
            examId: $examId,
            note: $note,
        );
    }

    private function record(
        string $studentId,
        string $componentId,
        string $periodId,
        ?float $manualValue,
        ?float $autoValue,
        string $changeSource,
        ?string $changedBy = null,
        ?string $examId = null,
        ?string $note = null,
    ): Grade {
        $existing = Grade::where('student_id', $studentId)
            ->where('component_id', $componentId)
            ->where('period_id', $periodId)
            ->first();

        $manual = $manualValue !== null ? (float) $manualValue : ($existing?->manual_value !== null ? (float) $existing->manual_value : null);

        // Nilai otomatis memakai yang tertinggi antar sumber otomatis.
        $autoSebelum = $existing?->auto_value !== null ? (float) $existing->auto_value : null;
        $autoSource = $existing?->auto_source;

        if ($autoValue !== null) {
            $auto = $autoSebelum === null ? (float) $autoValue : max($autoSebelum, (float) $autoValue);
            if ($autoSebelum === null || (float) $autoValue >= $autoSebelum) {
                $autoSource = $changeSource;
            }
        } else {
            $auto = $autoSebelum;
        }

        [$effective, $effectiveSource] = $this->resolveEffective($manual, $auto, $autoSource);

        $grade = $existing ?? new Grade([
            'student_id' => $studentId,
            'component_id' => $componentId,
            'period_id' => $periodId,
        ]);

        $oldValue = $existing?->value;
        $oldSource = $existing?->source;

        $grade->value = $effective;
        $grade->manual_value = $manual;
        $grade->auto_value = $auto;
        $grade->auto_source = $autoSource;
        $grade->source = $effectiveSource;
        $grade->entered_by = $changedBy ?? $grade->entered_by;

        if ($examId !== null) {
            $grade->exam_id = $examId;
        }

        $grade->save();

        GradeHistory::create([
            'grade_id' => $grade->id,
            'student_id' => $studentId,
            'component_id' => $componentId,
            'period_id' => $periodId,
            'old_value' => $oldValue,
            'new_value' => $effective,
            'old_source' => $oldSource,
            'new_source' => $effectiveSource,
            'change_source' => $changeSource,
            'changed_by' => $changedBy,
            'note' => $note,
            'created_at' => now(),
        ]);

        return $grade;
    }

    /**
     * Nilai efektif = tertinggi antara manual & otomatis.
     *
     * @return array{0: float, 1: string}
     */
    public function resolveEffective(?float $manual, ?float $auto, ?string $autoSource): array
    {
        if ($manual === null && $auto === null) {
            return [0.0, self::SOURCE_MANUAL];
        }

        if ($manual === null) {
            return [(float) $auto, $autoSource ?? self::SOURCE_ASSIGNMENT];
        }

        if ($auto === null) {
            return [(float) $manual, self::SOURCE_MANUAL];
        }

        return $manual >= $auto
            ? [(float) $manual, self::SOURCE_MANUAL]
            : [(float) $auto, $autoSource ?? self::SOURCE_ASSIGNMENT];
    }
}
