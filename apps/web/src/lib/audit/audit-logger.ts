import { apiClient } from '@/lib/api/api-client';

/**
 * Telemetri UI Client (Non-Authoritative)
 *
 * CATATAN KEAMANAN (GAP-06 / AGENTS.md):
 * Log audit untuk 5 aksi bisnis sensitif (ubah nilai, kunci/buka rapor, persetujuan eksekutif,
 * penetapan role, dan ekspor data) sepenuhnya dicatat secara OTORITATIF di server (backend)
 * di dalam transaksi database yang sama dengan mutasinya.
 *
 * Alasan (reason / note) untuk aksi bisnis sensitif wajib dikirim sebagai bagian dari payload
 * endpoint bisnis terkait (misal POST /grades, PATCH /report-cards/{id}/lock, dll.),
 * BUKAN dikirim terpisah ke endpoint audit-logs.
 *
 * Modul ini HANYA digunakan untuk merekam peristiwa telemetri antarmuka (UI telemetry)
 * yang bersifat non-otoritatif (contoh: tombol diklik, modal dibuka, filter diubah).
 */

export interface UiTelemetryPayload {
  action: string;
  entity?: string;
  entity_id?: string | null;
  metadata?: Record<string, unknown> | null;
}

export const uiTelemetryLogger = {
  /**
   * Catat telemetri interaksi UI klien ke endpoint backend.
   * Aksi akan otomatis berawalan 'ui.' jika belum diawali dengan prefix tersebut.
   */
  async logTelemetry(payload: UiTelemetryPayload): Promise<boolean> {
    const rawAction = payload.action.trim();
    const action = rawAction.startsWith('ui.') ? rawAction : `ui.telemetry.${rawAction}`;

    try {
      await apiClient.post('/audit-logs', {
        action,
        entity: payload.entity || 'UserInterface',
        entity_id: payload.entity_id || null,
        new_values: payload.metadata || null,
        timestamp: new Date().toISOString(),
      });
      return true;
    } catch {
      // Graceful degradation bila telemetri gagal dikirim
      return false;
    }
  },

  /**
   * Telemetri klik tombol / interaksi UI
   */
  async logButtonClick(buttonName: string, metadata?: Record<string, unknown>): Promise<boolean> {
    return this.logTelemetry({
      action: 'ui.button_click',
      entity: 'Button',
      metadata: { button: buttonName, ...metadata },
    });
  },

  /**
   * Telemetri pembukaan modal / dialog
   */
  async logModalOpen(modalName: string, metadata?: Record<string, unknown>): Promise<boolean> {
    return this.logTelemetry({
      action: 'ui.modal_open',
      entity: 'Modal',
      metadata: { modal: modalName, ...metadata },
    });
  },
};

/**
 * Alias kompatibilitas mundur.
 * Peringatan (GAP-06 / AGENTS.md): Gunakan uiTelemetryLogger untuk telemetri UI.
 * Aksi bisnis sensitif dicatat secara otoritatif oleh server.
 */
export const auditLogger = {
  ...uiTelemetryLogger,
  async logGradeUpdate(params: {
    studentId: string;
    studentName?: string;
    subjectId: string;
    subjectName?: string;
    component: string;
    oldScore: number;
    newScore: number;
    reason?: string;
  }): Promise<boolean> {
    return uiTelemetryLogger.logTelemetry({
      action: 'ui.telemetry.grade_update_view',
      entity: 'StudentGrade',
      entity_id: `${params.studentId}:${params.subjectId}`,
      metadata: {
        score: params.newScore,
        component: params.component,
        reason: params.reason,
      },
    });
  },

  async logReportCardStatusChange(params: {
    classId: string;
    className: string;
    academicYearId: string;
    isLocked: boolean;
    reason?: string;
  }): Promise<boolean> {
    return uiTelemetryLogger.logTelemetry({
      action: params.isLocked
        ? 'ui.telemetry.report_card_lock_view'
        : 'ui.telemetry.report_card_unlock_view',
      entity: 'ReportCardBatch',
      entity_id: `${params.classId}:${params.academicYearId}`,
      metadata: { is_locked: params.isLocked, reason: params.reason },
    });
  },

  async logSensitiveAction(payload: { action: string; entity: string; [key: string]: unknown }): Promise<boolean> {
    return uiTelemetryLogger.logTelemetry({
      action: `ui.client_event.${payload.action.replace(/[^a-zA-Z0-9_]/g, '_')}`,
      entity: payload.entity,
      metadata: { attemptedAction: payload.action },
    });
  },
};


