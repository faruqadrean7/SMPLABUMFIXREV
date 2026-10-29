<?php

namespace App\Enums;

enum ExamStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'dijadwalkan';
    case IN_PROGRESS = 'berlangsung';
    case FINISHED = 'selesai';
    case CANCELLED = 'dibatalkan';
    case ARCHIVED = 'arsip';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isLive(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::SCHEDULED => 'Dijadwalkan',
            self::IN_PROGRESS => 'Sedang Berlangsung',
            self::FINISHED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
            self::ARCHIVED => 'Arsip',
        };
    }
}
