/**
 * Shared Portal Types (Digunakan bersama oleh Guru dan Staf/Tenaga Kependidikan)
 */

export interface SharedSelfAttendance {
  todayDate: string;
  checkInTime?: string;
  checkOutTime?: string;
  status: 'present' | 'late' | 'not_checked_in';
  workHoursThisMonth: number;
  targetWorkHours: number;
  attendancePercentage: number;
}

export type LeaveType = 'sick' | 'annual' | 'family' | 'duty';

export interface SharedLeaveRequest {
  id: string;
  leaveType: LeaveType;
  startDate: string;
  endDate: string;
  daysCount: number;
  reason: string;
  attachmentUrl?: string;
  status: 'pending' | 'approved' | 'rejected';
  reviewNote?: string;
  createdAt: string;
}

export interface CreateLeaveRequestInput {
  leaveType: LeaveType;
  startDate: string;
  endDate: string;
  reason: string;
  attachmentUrl?: string;
}

export interface SharedSalarySlipItem {
  id: string;
  slipNumber: string;
  periodMonth: string; // contoh: "Oktober 2026"
  basicSalary: number;
  teachingAllowance?: number;
  functionalAllowance?: number;
  operationalAllowance?: number;
  deduction: number;
  totalNetSalary: number;
  paymentDate: string;
  isPaid: boolean;
  downloadUrl?: string;
}
