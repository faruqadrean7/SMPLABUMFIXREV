export interface ExecutiveKpiMetrics {
  studentAttendanceRate: number; // e.g. 96.8 %
  teacherAttendanceRate: number; // e.g. 98.2 %
  totalStudents: number;
  totalTeachers: number;
  activeEarlyWarningsCount: number;
  openTicketsCount: number;
  ppdbEnrolledCount: number;
  ppdbTargetQuota: number;
  pendingApprovalsCount: number;
  gradeLevelAverages: Array<{
    level: string; // "Kelas 7", "Kelas 8", "Kelas 9"
    averageScore: number;
    totalStudents: number;
  }>;
  recentIncidents: Array<{
    id: string;
    title: string;
    severity: 'low' | 'medium' | 'high';
    timestamp: string;
  }>;
}

export type ApprovalCategory = 'report_card' | 'leave_request' | 'facility_purchase' | 'curriculum';
export type ApprovalStatus = 'pending' | 'approved' | 'rejected';

export interface ApprovalItem {
  id: string;
  code: string;
  category: ApprovalCategory;
  title: string;
  requesterName: string;
  requesterRole: string;
  details: string;
  submittedAt: string;
  status: ApprovalStatus;
  reviewedAt?: string;
  reviewedBy?: string;
  reviewNotes?: string;
}

export interface ReviewApprovalPayload {
  action: 'approve' | 'reject';
  notes?: string;
  rejectionReason?: string;
}
