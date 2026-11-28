'use client';

import React from 'react';
import { useAuth } from '@/providers/auth-provider';
import { useExecutiveOverview } from '@/features/executive';
import { hasPermission, isReadOnlyRole } from '@/lib/rbac/evaluator';
import { useActiveAcademicYearLabel } from '@/lib/use-active-academic-year';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { ExecutiveDashboardPanel } from '@/components/shared/executive-dashboard-panel';

export default function ExecutiveDashboardPage() {
  const { user } = useAuth();
  const { data: metrics, isLoading, isError, refetch } = useExecutiveOverview();
  const academicYearLabel = useActiveAcademicYearLabel();

  if (isLoading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner label="Memuat ringkasan eksekutif pimpinan..." size="lg" />
      </div>
    );
  }

  if (isError || !metrics) {
    return (
      <ErrorState
        title="Gagal Memuat Dashboard Eksekutif"
        message="Terjadi kendala saat mengambil ringkasan indikator kinerja sekolah."
        onRetry={() => refetch()}
      />
    );
  }

  const isReadOnly = isReadOnlyRole(user?.roles);

  return (
    <ExecutiveDashboardPanel
      metrics={metrics}
      user={user}
      approvalsHref="/admin/executive/approvals"
      reportsHref="/admin/reports"
      academicYearLabel={academicYearLabel}
      canManageApprovals={hasPermission(user, 'executive.approval.manage') && !isReadOnly}
      canViewReports={hasPermission(user, 'reports.view')}
      canViewCounseling={hasPermission(user, 'counseling.case.read') && !isReadOnly}
      isReadOnly={isReadOnly}
    />
  );
}
