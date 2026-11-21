import React from 'react';
import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

describe('FE-UX-02: Frontend Action Button Audit & Double-Submit Protection', () => {
  const webSrcDir = path.resolve(__dirname, '..');

  it('FE-UX-02-AC1: verifies double-submit disabled guards exist on critical mutation buttons across target pages', () => {
    const targetChecks = [
      {
        relPath: 'app/(admin)/admin/cbt/exams/page.tsx',
        requiredDisabledStates: [
          'disabled={isCreatingExam}',
          'disabled={isEnrolling}',
          'disabled={isRunningAutoGrade}',
          'disabled={isCreatingRemedial}',
          'disabled={togglingExamId === exam.id}',
          'disabled={regeneratingRoomId === room.id}',
          'disabled={isPublishingGradebook}',
        ],
      },
      {
        relPath: 'app/(admin)/admin/cbt/questions/page.tsx',
        requiredDisabledStates: [
          'disabled={isCreatingBank}',
          'disabled={isSavingQuestion}',
          'isLoading={isDeletingQuestion}',
        ],
      },
      {
        relPath: 'app/(admin)/admin/cbt/monitor/page.tsx',
        requiredDisabledStates: [
          'disabled={isSubmittingAction}',
          'disabled={isRefreshing}',
        ],
      },
      {
        relPath: 'app/(admin)/admin/finance/invoices/page.tsx',
        requiredDisabledStates: [
          'disabled={isBulkSubmitting}',
          'disabled={isCancelling}',
        ],
      },
      {
        relPath: 'app/(admin)/admin/finance/payments/page.tsx',
        requiredDisabledStates: [
          'isLoading={isVerifying}',
          'disabled={isRejecting}',
          'disabled={isVoiding}',
        ],
      },
      {
        relPath: 'app/(portal)/portal/guru/nilai/page.tsx',
        requiredDisabledStates: [
          'disabled={saveMutation.isPending || period?.is_locked}',
          'disabled={lockMutation.isPending}',
          'disabled={approvalStatus === \'menunggu_acc\' || approvalStatus === \'disetujui\'}',
        ],
      },
      {
        relPath: 'app/(portal)/portal/guru/presensi/page.tsx',
        requiredDisabledStates: [
          'isLoading={isSaving}',
          'disabled={!selectedSubjectId || !selectedClassId || students.length === 0}',
        ],
      },
      {
        relPath: 'app/(portal)/portal/siswa/ujian/[id]/page.tsx',
        requiredDisabledStates: [
          'disabled={isSubmitting}',
          'disabled={tokenSubmitting}',
        ],
      },
    ];

    for (const item of targetChecks) {
      const fullPath = path.join(webSrcDir, item.relPath);
      expect(fs.existsSync(fullPath)).toBe(true);
      const content = fs.readFileSync(fullPath, 'utf-8');

      for (const requiredState of item.requiredDisabledStates) {
        expect(
          content.includes(requiredState),
          `Expected ${item.relPath} to include double-submit protection: "${requiredState}"`
        ).toBe(true);
      }
    }
  });

  it('FE-UX-02-AC2: verifies audited pages do not contain dead button elements without handlers or type="submit"', () => {
    const targetPages = [
      'app/(admin)/admin/cbt/exams/page.tsx',
      'app/(admin)/admin/cbt/questions/page.tsx',
      'app/(admin)/admin/cbt/monitor/page.tsx',
      'app/(admin)/admin/finance/invoices/page.tsx',
      'app/(admin)/admin/finance/payments/page.tsx',
      'app/(portal)/portal/guru/nilai/page.tsx',
      'app/(portal)/portal/guru/presensi/page.tsx',
    ];

    for (const relPath of targetPages) {
      const fullPath = path.join(webSrcDir, relPath);
      const content = fs.readFileSync(fullPath, 'utf-8');

      const regex = /<(?:Button|button)[\s\S]*?>/g;
      let match;
      while ((match = regex.exec(content)) !== null) {
        const btnTag = match[0];
        const precedingText = content.slice(Math.max(0, match.index - 100), match.index);
        const isInsideLink = /<Link\s+[^>]*>\s*$/i.test(precedingText.trim());

        const hasOnClick = /onClick\s*=/.test(btnTag);
        const hasTypeSubmit = /type\s*=\s*["']submit["']/.test(btnTag);
        const hasTypeReset = /type\s*=\s*["']reset["']/.test(btnTag);
        const isTrigger = /asChild|trigger|data-state/i.test(btnTag);

        expect(
          hasOnClick || hasTypeSubmit || hasTypeReset || isTrigger || isInsideLink,
          `Found button without action handler in ${relPath}: ${btnTag.slice(0, 80)}...`
        ).toBe(true);
      }
    }
  });
});
