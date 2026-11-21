<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CBT Exam Timing & Grace Period Configurations
    |--------------------------------------------------------------------------
    |
    | Toleransi waktu (detik) untuk toleransi keterlambatan jaringan saat
    | autosave / submit akhir sebelum server menolak dengan EXAM_TIME_OVER.
    |
    */
    'grace_period_seconds' => (int) env('CBT_GRACE_PERIOD_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Anti-Cheat Violation Thresholds
    |--------------------------------------------------------------------------
    |
    | Jumlah pelanggaran untuk mengunci sesi (lock) dan diskualifikasi (disqualify).
    |
    */
    'lock_threshold' => (int) env('CBT_LOCK_THRESHOLD', 3),
    'disqualify_threshold' => (int) env('CBT_DISQUALIFY_THRESHOLD', 5),
];
