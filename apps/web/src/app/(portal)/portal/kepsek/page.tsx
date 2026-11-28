'use client';

import React from 'react';
import { useAuth } from '@/providers/auth-provider';
import { useExecutiveOverview } from '@/features/executive';
import { hasPermission } from '@/lib/rbac/evaluator';
import { useActiveAcademicYearLabel } from '@/lib/use-active-academic-year';
import { LoadingSpinner } from '@/components/shared/loading-spinner';
import { ErrorState } from '@/components/shared/error-state';
import { ExecutiveDashboardPanel } from '@/components/shared/executive-dashboard-panel';

export default function KepsekDashboardPage() {
  const { user } = useAuth();
  const { data: metrics, isLoading, isError, refetch } = useExecutiveOverview();
  const academicYearLabel = useActiveAcademicYearLabel();

  if (isLoading) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        <LoadingSpinner label="Memuat ringkasan indikator kinerja pimpinan..." size="lg" />
      </div>
    );
  }

  if (isError || !metrics) {
    return (
      <ErrorState
        title="Gagal Memuat Dashboard Pimpinan"
        message="Terjadi kendala saat mengambil ringkasan indikator kinerja sekolah dari server."
        onRetry={() => refetch()}
      />
    );
  }

  return (
    <ExecutiveDashboardPanel
      metrics={metrics}
      user={user}
      approvalsHref="/portal/kepsek/approvals"
      reportsHref="/admin/reports"
      academicYearLabel={academicYearLabel}
      canManageApprovals={hasPermission(user, 'executive.approval.manage')}
      canViewReports={hasPermission(user, 'reports.view')}
      canViewCounseling={hasPermission(user, 'counseling.case.read')}
    />
  );
}
