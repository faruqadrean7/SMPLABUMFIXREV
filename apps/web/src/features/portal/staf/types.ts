import { SharedSelfAttendance } from '../shared/types';
import { TicketPriority, TicketStatus } from '@/features/sarpras/tickets-api';

export interface StaffDashboardSummary {
  staffName: string;
  department: string;
  todayAttendance: SharedSelfAttendance;
  pendingLeavesCount: number;
  openTicketsCount: number;
  resolvedTicketsCount: number;
  recentAnnouncements: Array<{
    id: string;
    title: string;
    date: string;
    category: string;
  }>;
}

export interface StaffTicketItem {
  id: string;
  code: string;
  title: string;
  category: string;
  location: string;
  description: string;
  priority: TicketPriority;
  status: TicketStatus;
  reportedAt: string;
  updatedAt?: string;
  resolutionNotes?: string;
}

export interface CreateStaffTicketInput {
  title: string;
  category: string;
  location: string;
  description: string;
  priority: TicketPriority;
}
