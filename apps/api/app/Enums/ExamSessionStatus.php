<?php

namespace App\Enums;

enum ExamSessionStatus: string
{
    case NOT_STARTED = 'belum';
    case IN_PROGRESS = 'mengerjakan';
    case FINISHED = 'selesai';
    case LOCKED = 'terkunci';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Belum Dimulai',
            self::IN_PROGRESS => 'Sedang Mengerjakan',
            self::FINISHED => 'Selesai',
            self::LOCKED => 'Terkunci',
        };
    }
}
