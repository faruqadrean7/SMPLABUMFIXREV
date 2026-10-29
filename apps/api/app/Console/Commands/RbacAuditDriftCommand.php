<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;

class RbacAuditDriftCommand extends Command
{
    protected $signature = 'rbac:audit-drift
                            {--json : Tampilkan output laporan dalam format JSON}
                            {--strict : Keluar dengan status gagal (exit code 1) bila ada perbedaan apapun}';

    protected $description = 'Audit read-only untuk membandingkan konfigurasi role-permission aktual dengan standar kanonis SISTLABUM';

    /**
     * Daftar role resmi kanonis SISTLABUM (SSOT).
     */
    public const CANONICAL_ROLES = [
        'super_admin',
        'principal',
        'admin_tu',
        'teacher',
        'homeroom',
        'counselor',
        'facility_staff',
        'ppdb_committee',
        'librarian',
        'student_affairs',
        'public_relations',
        'student',
        'guardian',
        'staff',
        'guest',
        'bendahara',
        'treasurer',
        // Ekstensi sistem yang sah
        'admin_kurikulum',
        'kurikulum',
    ];

    /**
     * Pemetaan permission default resmi per role.
     */
    public const CANONICAL_ROLE_PERMISSIONS = [
        'principal' => [
            'executive.approval.manage',
            'dashboard.executive',
            'executive.dashboard.view',
            'reports.view',
            'academic.grade.read',
            'academic.report.generate',
            'academic.attendance.read',
            'academic.schedule.read',
            'academic.journal.read',
            'cbt.exam.manage',
            'student.point.read',
            'counseling.case.read',
            'facility.booking.manage',
            'facility.asset.manage',
            'guest.book.read',
            'complaint.read',
            'complaint.manage',
            'complaint.view_reporter',
            'audit.read',
            'master.student.read',
            'master.teacher.manage',
            'ppdb.applicant.read',
            'finance.report.view',
        ],
        'admin_tu' => [
            'master.academic_year.manage',
            'master.student.read',
            'master.student.write',
            'master.teacher.manage',
            'master.class.manage',
            'master.subject.manage',
            'master.room.manage',
            'master.promotion.manage',
            'academic.schedule.read',
            'academic.schedule.manage',
            'academic.attendance.read',
            'academic.attendance.write',
            'academic.report.generate',
            'guest.book.read',
            'guest.book.manage',
            'student.point.read',
            'facility.booking.manage',
            'facility.asset.manage',
            'audit.read',
        ],
        'teacher' => [
            'academic.schedule.read',
            'academic.journal.read',
            'academic.journal.write',
            'academic.attendance.read',
            'academic.attendance.write',
            'academic.grade.read',
            'academic.grade.write',
            'cbt.bank.write',
            'cbt.proctor.action',
            'student.point.read',
            'student.point.write',
        ],
        'homeroom' => [
            'academic.schedule.read',
            'academic.journal.read',
            'academic.journal.write',
            'academic.attendance.read',
            'academic.attendance.write',
            'academic.grade.read',
            'academic.grade.write',
            'academic.report.generate',
            'cbt.bank.write',
            'cbt.proctor.action',
            'student.point.read',
            'student.point.write',
        ],
        'counselor' => [
            'counseling.case.read',
            'counseling.case.write',
            'bk.counseling.manage',
            'complaint.read',
            'complaint.manage',
            'student.point.read',
            'student.point.write',
            'academic.attendance.read',
            'academic.grade.read',
        ],
        'facility_staff' => [
            'facility.booking.manage',
            'facility.asset.manage',
        ],
        'librarian' => [
            'library.book.manage',
            'facility.asset.manage',
        ],
        'ppdb_committee' => [
            'ppdb.wave.manage',
            'ppdb.applicant.read',
            'ppdb.applicant.verify',
            'ppdb.selection.run',
        ],
        'student_affairs' => [
            'student.point.read',
            'student.point.write',
            'master.student.read',
            'reports.view',
        ],
        'public_relations' => [
            'guest.book.read',
            'guest.book.manage',
            'complaint.read',
            'complaint.manage',
            'cms.site.manage',
            'cms.post.manage',
            'cms.gallery.manage',
            'cms.event.manage',
            'cms.download.manage',
            'cms.page.manage',
        ],
        'student' => [
            'cbt.exam.take',
            'cbt.exam.review',
            'academic.schedule.read',
            'student.point.read',
            'academic.grade.read',
        ],
        'guardian' => [
            'academic.attendance.read',
            'academic.grade.read',
            'student.point.read',
        ],
        'guest' => [],
        'bendahara' => [
            'finance.fee.manage',
            'finance.invoice.manage',
            'finance.payment.verify',
            'finance.report.view',
            'audit.read',
            'master.student.read',
            'reports.view',
        ],
        'treasurer' => [
            'finance.fee.manage',
            'finance.invoice.manage',
            'finance.payment.verify',
            'finance.report.view',
            'audit.read',
            'master.student.read',
            'reports.view',
        ],
    ];

    /**
     * Izin terlarang ketat (Prohibited Administrative Leaks) per role.
     */
    public const PROHIBITED_ROLE_PERMISSIONS = [
        'student' => [
            'cbt.bank.write',
            'cbt.exam.manage',
            'cbt.proctor.action',
            'academic.grade.write',
            'academic.attendance.write',
            'academic.journal.write',
            'academic.report.generate',
            'facility.booking.manage',
            'facility.asset.manage',
            'complaint.manage',
            'counseling.case.write',
            'bk.counseling.manage',
            'auth.role.manage',
            'auth.user.write',
        ],
        'guardian' => [
            'cbt.exam.take',
            'cbt.exam.review',
            'cbt.bank.write',
            'cbt.exam.manage',
            'cbt.proctor.action',
            'academic.grade.write',
            'academic.attendance.write',
            'academic.journal.write',
            'academic.report.generate',
            'facility.booking.manage',
            'facility.asset.manage',
            'complaint.manage',
            'counseling.case.write',
            'bk.counseling.manage',
            'auth.role.manage',
            'auth.user.write',
        ],
        'student_affairs' => [
            'cbt.bank.write',
            'facility.asset.manage',
            'academic.grade.write',
            'auth.role.manage',
        ],
        'public_relations' => [
            'complaint.view_reporter',
            'academic.grade.write',
            'student.point.write',
            'cbt.bank.write',
            'auth.role.manage',
        ],
        'counselor' => [
            'facility.asset.manage',
            'cbt.bank.write',
            'master.class.manage',
            'academic.grade.write',
        ],
        'facility_staff' => [
            'student.point.write',
            'academic.grade.write',
            'cbt.bank.write',
        ],
        'librarian' => [
            'academic.grade.write',
            'counseling.case.write',
            'student.point.write',
        ],
        'teacher' => [
            'master.academic_year.manage',
            'guest.book.manage',
            'complaint.manage',
            'facility.booking.manage',
            'facility.asset.manage',
            'auth.role.manage',
        ],
    ];

    public function handle(): int
    {
        $asJson = $this->option('json');
        $isStrict = $this->option('strict');

        $auditResult = $this->performAudit();

        if ($asJson) {
            $this->line(json_encode($auditResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderConsoleReport($auditResult);
        }

        if ($auditResult['summary']['prohibited_leaks_count'] > 0) {
            return 1;
        }

        if ($isStrict && ($auditResult['summary']['missing_permissions_count'] > 0 || $auditResult['summary']['unknown_roles_count'] > 0)) {
            return 1;
        }

        return 0;
    }

    public function performAudit(): array
    {
        $dbRoles = Role::with('permissions')->get();
        $dbRoleCodes = $dbRoles->pluck('code')->toArray();
        $allDbPermissions = Permission::pluck('code')->toArray();

        // 1. Unknown Roles
        $unknownRoles = array_values(array_diff($dbRoleCodes, self::CANONICAL_ROLES));

        // 2. Audit per canonical role
        $roleAudits = [];
        $totalMissing = 0;
        $totalProhibited = 0;
        $totalCustom = 0;

        foreach ($dbRoles as $role) {
            $code = $role->code;
            $currentPerms = $role->permissions->pluck('code')->toArray();

            // Expected canonical perms
            $expectedPerms = self::CANONICAL_ROLE_PERMISSIONS[$code] ?? null;
            $prohibitedPerms = self::PROHIBITED_ROLE_PERMISSIONS[$code] ?? [];

            $missing = [];
            $prohibited = [];
            $custom = [];

            if ($code === 'super_admin') {
                // super_admin berhak memiliki semua permission
                $expectedPerms = $allDbPermissions;
            }

            if ($expectedPerms !== null) {
                $missing = array_values(array_diff($expectedPerms, $currentPerms));
            }

            // Cek prohibited leaks
            $prohibited = array_values(array_intersect($currentPerms, $prohibitedPerms));

            // Cek custom permissions (ada di current tapi tidak ada di expected dan tidak prohibited)
            if ($expectedPerms !== null) {
                $extra = array_diff($currentPerms, $expectedPerms);
                $custom = array_values(array_diff($extra, $prohibited));
            }

            $totalMissing += count($missing);
            $totalProhibited += count($prohibited);
            $totalCustom += count($custom);

            $roleAudits[$code] = [
                'role_name' => $role->name,
                'is_system' => (bool) $role->is_system,
                'total_permissions' => count($currentPerms),
                'missing_permissions' => $missing,
                'prohibited_permissions' => $prohibited,
                'custom_permissions' => $custom,
                'status' => count($prohibited) > 0 ? 'CRITICAL_LEAK' : (count($missing) > 0 ? 'DEFICIENT' : (count($custom) > 0 ? 'CUSTOMIZED' : 'CLEAN')),
            ];
        }

        // 3. Orphan Permissions (Permissions not assigned to any role)
        $assignedPermCodes = \DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->distinct()
            ->pluck('permissions.code')
            ->toArray();
        $orphanPermissions = array_values(array_diff($allDbPermissions, $assignedPermCodes));

        // Overall status
        $overallStatus = 'CLEAN';
        if ($totalProhibited > 0) {
            $overallStatus = 'CRITICAL_DRIFT';
        } elseif ($totalMissing > 0 || count($unknownRoles) > 0) {
            $overallStatus = 'INCOMPLETE_DRIFT';
        } elseif ($totalCustom > 0 || count($orphanPermissions) > 0) {
            $overallStatus = 'CUSTOM_EXTENSIONS_PRESENT';
        }

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'database_driver' => \DB::connection()->getDriverName(),
            'summary' => [
                'total_roles_audited' => $dbRoles->count(),
                'unknown_roles_count' => count($unknownRoles),
                'missing_permissions_count' => $totalMissing,
                'prohibited_leaks_count' => $totalProhibited,
                'custom_extensions_count' => $totalCustom,
                'orphan_permissions_count' => count($orphanPermissions),
            ],
            'unknown_roles' => $unknownRoles,
            'orphan_permissions' => $orphanPermissions,
            'roles' => $roleAudits,
        ];
    }

    protected function renderConsoleReport(array $report): void
    {
        $this->info('========================================================================');
        $this->info(' SISTLABUM - Laporan Audit Permission Drift (Read-Only) ');
        $this->info('========================================================================');
        $this->line("Lingkungan : {$report['environment']} (Driver: {$report['database_driver']})");
        $this->line("Waktu Uji  : {$report['timestamp']}");
        $this->line("Status     : {$report['status']}");
        $this->newLine();

        $this->table(
            ['Metrik Audit', 'Nilai'],
            [
                ['Total Peran Diaudit', $report['summary']['total_roles_audited']],
                ['Peran Tidak Dikenal', $report['summary']['unknown_roles_count']],
                ['Permission Hilang (Deficient)', $report['summary']['missing_permissions_count']],
                ['Kebocoran Terlarang (Prohibited Leaks)', $report['summary']['prohibited_leaks_count']],
                ['Kustomisasi Sah (Custom Extensions)', $report['summary']['custom_extensions_count']],
                ['Permission Yatim (Orphan)', $report['summary']['orphan_permissions_count']],
            ]
        );

        if (! empty($report['unknown_roles'])) {
            $this->warn('⚠ Peran Tidak Dikenal (Unknown Roles): '.implode(', ', $report['unknown_roles']));
        }

        if (! empty($report['orphan_permissions'])) {
            $this->comment('ℹ Permission Yatim (Belum Terpasang ke Role): '.implode(', ', $report['orphan_permissions']));
        }

        $this->newLine();
        $this->info('--- Status Role dan Penyimpangan Izin ---');

        $rows = [];
        foreach ($report['roles'] as $code => $details) {
            $notes = [];
            if (! empty($details['prohibited_permissions'])) {
                $notes[] = 'LEAK: '.implode(', ', $details['prohibited_permissions']);
            }
            if (! empty($details['missing_permissions'])) {
                $notes[] = 'MISSING: '.count($details['missing_permissions']).' item';
            }
            if (! empty($details['custom_permissions'])) {
                $notes[] = 'CUSTOM: '.implode(', ', $details['custom_permissions']);
            }

            $rows[] = [
                $code,
                $details['role_name'],
                $details['total_permissions'],
                $details['status'],
                empty($notes) ? '✓ Sesuai standar' : implode(' | ', $notes),
            ];
        }

        $this->table(['Role Code', 'Nama Role', 'Total Izin', 'Status', 'Catatan Audit'], $rows);

        if ($report['summary']['prohibited_leaks_count'] > 0) {
            $this->error('✗ DITEMUKAN KEBOCORAN IZIN TERLARANG! Lakukan sanitasi segera.');
        } elseif ($report['summary']['missing_permissions_count'] > 0) {
            $this->warn('⚠ Ditemukan permission default yang belum terpasang.');
        } else {
            $this->info('✓ Integritas RBAC kanonis terpelihara dengan baik.');
        }
    }
}
