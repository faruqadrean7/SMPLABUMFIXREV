export interface AuditLogActor {
  id: string;
  username: string;
  email: string | null;
}

export interface AuditLogItem {
  id: string;
  actor_id: string | null;
  action: string;
  entity: string | null;
  entity_id: string | null;
  ip: string | null;
  user_agent: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  created_at: string;
  actor?: AuditLogActor | null;
}

export interface AuditLogDetail extends AuditLogItem {
  actor?: AuditLogActor | null;
}

export interface AuditLogQueryParams {
  page?: number;
  limit?: number;
  q?: string;
  actor_id?: string;
  action?: string;
  entity?: string;
  entity_id?: string;
  start_date?: string;
  end_date?: string;
}

/**
 * Kode Aksi Sensitif Resmi SISTLABUM (Audit Trail Wajib)
 */
export const SENSITIVE_AUDIT_ACTIONS = {
  GRADE_UPDATE: 'academic.grade.update',
  REPORT_CARD_LOCK: 'academic.report_card.lock',
  REPORT_CARD_UNLOCK: 'academic.report_card.unlock',
  APPROVAL_DECISION: 'executive.approval.decision',
  ROLE_ASSIGNMENT: 'auth.role.assign',
  BULK_DATA_EXPORT: 'report.bulk.export',
} as const;

export type SensitiveAuditAction =
  (typeof SENSITIVE_AUDIT_ACTIONS)[keyof typeof SENSITIVE_AUDIT_ACTIONS];

export interface RecordAuditLogPayload {
  action: SensitiveAuditAction | string;
  entity: string;
  entity_id?: string | null;
  old_values?: Record<string, unknown> | null;
  new_values?: Record<string, unknown> | null;
  reason?: string | null;
}
