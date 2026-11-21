<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'notif_type',
        'channel_push',
        'channel_wa',
        'channel_email',
        'is_enabled',
    ];

    protected $casts = [
        'channel_push' => 'boolean',
        'channel_wa' => 'boolean',
        'channel_email' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public const NOTIFICATION_TYPES = [
        'academic_announcement' => [
            'label' => 'Pengumuman & Kegiatan Akademik',
            'description' => 'Notifikasi terkait jadwal ujian, kegiatan sekolah, dan kalender akademik.',
            'supported_channels' => ['push', 'wa', 'email'],
            'allowed_roles' => ['student', 'teacher', 'guardian', 'staff', 'super_admin', 'admin'],
        ],
        'grade_report' => [
            'label' => 'Penerbitan Nilai & Rapor Siswa',
            'description' => 'Pemberitahuan ketika rapor atau nilai asesmen siswa telah diterbitkan.',
            'supported_channels' => ['push', 'wa', 'email'],
            'allowed_roles' => ['student', 'teacher', 'guardian', 'staff', 'super_admin', 'admin'],
        ],
        'attendance_alert' => [
            'label' => 'Presensi & Kehadiran',
            'description' => 'Pemberitahuan ketidakhadiran, dispensasi, atau keterlambatan siswa/staf.',
            'supported_channels' => ['push', 'wa'],
            'allowed_roles' => ['student', 'teacher', 'guardian', 'staff', 'super_admin', 'admin'],
        ],
        'payment_reminder' => [
            'label' => 'Tagihan & Pengingat SPP',
            'description' => 'Pengingat jatuh tempo pembayaran SPP dan biaya pendidikan.',
            'supported_channels' => ['wa', 'email'],
            'allowed_roles' => ['student', 'guardian', 'staff', 'super_admin', 'admin'],
        ],
        'counseling_update' => [
            'label' => 'Bimbingan & Konseling (BK)',
            'description' => 'Pemberitahuan perubahan status janji temu konseling siswa.',
            'supported_channels' => ['push', 'email'],
            'allowed_roles' => ['student', 'teacher', 'guardian', 'staff', 'super_admin', 'admin'],
        ],
        'system_alert' => [
            'label' => 'Pemberitahuan Akun & Sistem',
            'description' => 'Informasi pembaruan keamanan akun, login baru, dan pemeliharaan portal.',
            'supported_channels' => ['push', 'email'],
            'allowed_roles' => ['student', 'teacher', 'guardian', 'staff', 'super_admin', 'admin'],
        ],
        'teacher_agenda' => [
            'label' => 'Agenda & Penugasan Mengajar Guru',
            'description' => 'Notifikasi agenda mengajar harian, pergantian jam, dan tugas piket.',
            'supported_channels' => ['push', 'email'],
            'allowed_roles' => ['teacher', 'staff', 'super_admin', 'admin'],
        ],
        'assignment_submissions' => [
            'label' => 'Pengumpulan Tugas & Ujian Siswa',
            'description' => 'Notifikasi saat siswa mengumpulkan tugas atau menyelesaikan ujian.',
            'supported_channels' => ['push', 'email'],
            'allowed_roles' => ['teacher', 'staff', 'super_admin', 'admin'],
        ],
    ];

    public function getTopicAttribute(): ?string
    {
        return $this->attributes['notif_type'] ?? null;
    }

    public function setTopicAttribute(?string $value): void
    {
        $this->attributes['notif_type'] = $value;
    }

    public function getEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['is_enabled'] ?? true);
    }

    public function setEnabledAttribute($value): void
    {
        $this->attributes['is_enabled'] = (bool) $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
