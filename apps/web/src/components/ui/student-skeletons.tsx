import React from 'react';
import { Skeleton } from './skeleton';

export function DashboardHeroSkeleton() {
  return (
    <div className="bg-white border border-slate-200/90 rounded-xl p-3 px-4 shadow-xs shrink-0">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3.5 min-w-0">
          <Skeleton className="w-12 h-12 rounded-xl shrink-0" />
          <div className="space-y-2 min-w-0">
            <div className="flex items-center gap-2">
              <Skeleton className="h-5 w-44" />
              <Skeleton className="h-5 w-12 rounded-lg" />
              <Skeleton className="h-5 w-24 rounded-lg" />
            </div>
            <div className="flex items-center gap-3">
              <Skeleton className="h-3.5 w-32" />
              <Skeleton className="h-3.5 w-28 hidden sm:block" />
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Skeleton className="h-8 w-28 rounded-xl" />
          <Skeleton className="h-8 w-28 rounded-xl" />
        </div>
      </div>
    </div>
  );
}

export function MetricCardsSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 shrink-0">
      {[1, 2, 3, 4].map((i) => (
        <div
          key={i}
          className="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs flex flex-col justify-between"
        >
          <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
            <Skeleton className="h-3.5 w-28" />
            <Skeleton className="w-7 h-7 rounded-lg" />
          </div>
          <div className="my-2 space-y-1.5">
            <div className="flex items-baseline justify-between">
              <Skeleton className="h-8 w-20" />
              <Skeleton className="h-4 w-16 rounded-md" />
            </div>
            <Skeleton className="h-3 w-40" />
          </div>
          <div className="pt-2 border-t border-slate-100">
            <Skeleton className="h-3.5 w-32" />
          </div>
        </div>
      ))}
    </div>
  );
}

export function ScheduleListSkeleton() {
  return (
    <div className="space-y-2 mt-2.5">
      {[1, 2].map((i) => (
        <div
          key={i}
          className="p-3 rounded-xl border border-slate-200/80 bg-slate-50/50 space-y-2"
        >
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Skeleton className="h-4 w-28" />
              <Skeleton className="h-4 w-16 rounded-md" />
            </div>
            <Skeleton className="h-4 w-20 rounded-md" />
          </div>
          <div className="space-y-1.5 pt-1">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-3 w-36" />
          </div>
        </div>
      ))}
    </div>
  );
}

export function TaskListSkeleton({ count = 3 }: { count?: number } = {}) {
  return (
    <div className="space-y-2 mt-2.5">
      {Array.from({ length: count }).map((_, i) => (
        <div
          key={i}
          className="p-3 rounded-xl border border-slate-200/90 bg-white flex items-center justify-between gap-3"
        >
          <div className="flex items-center gap-3 min-w-0">
            <Skeleton className="w-8 h-8 rounded-full shrink-0" />
            <div className="space-y-1.5 min-w-0">
              <div className="flex items-center gap-2">
                <Skeleton className="h-3.5 w-24" />
                <Skeleton className="h-3 w-20" />
              </div>
              <Skeleton className="h-3.5 w-40" />
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Skeleton className="h-5 w-16 rounded-md" />
            <Skeleton className="h-6 w-14 rounded-lg" />
          </div>
        </div>
      ))}
    </div>
  );
}

export function GradesLedgerSkeleton() {
  return (
    <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-xs">
      <div className="p-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
        <div className="space-y-1">
          <Skeleton className="h-4 w-52" />
          <Skeleton className="h-3 w-72" />
        </div>
        <Skeleton className="h-6 w-32 rounded-lg" />
      </div>
      <div className="p-4 space-y-3">
        {[1, 2, 3, 4, 5].map((i) => (
          <div
            key={i}
            className="flex items-center justify-between py-2 border-b border-slate-100 last:border-0"
          >
            <div className="space-y-1">
              <Skeleton className="h-4 w-40" />
              <Skeleton className="h-3 w-28" />
            </div>
            <div className="flex items-center gap-4">
              <Skeleton className="h-4 w-8" />
              <Skeleton className="h-4 w-8" />
              <Skeleton className="h-4 w-8" />
              <Skeleton className="h-6 w-12 rounded-md" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export function CounselingListSkeleton() {
  return (
    <div className="space-y-2.5">
      {[1, 2].map((i) => (
        <div
          key={i}
          className="p-3.5 rounded-xl border border-slate-200/90 bg-white space-y-2"
        >
          <div className="flex items-center justify-between">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-5 w-20 rounded-md" />
          </div>
          <div className="flex items-center gap-4 pt-1">
            <Skeleton className="h-3 w-32" />
            <Skeleton className="h-3 w-36" />
          </div>
        </div>
      ))}
    </div>
  );
}

export function ExamListSkeleton({ count = 2 }: { count?: number }) {
  return (
    <div className="space-y-3">
      {Array.from({ length: count }).map((_, i) => (
        <div
          key={i}
          className="p-3.5 rounded-xl border border-slate-200/90 bg-white space-y-3"
        >
          <div className="flex items-center justify-between pb-2 border-b border-slate-100">
            <div className="space-y-1.5">
              <div className="flex items-center gap-2">
                <Skeleton className="h-4 w-20 rounded-md" />
                <Skeleton className="h-4 w-32" />
              </div>
              <Skeleton className="h-5 w-56" />
              <Skeleton className="h-3.5 w-40" />
            </div>
            <Skeleton className="h-7 w-20 rounded-lg" />
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <Skeleton className="h-12 rounded-xl" />
            <Skeleton className="h-12 rounded-xl" />
            <Skeleton className="h-12 rounded-xl" />
            <Skeleton className="h-12 rounded-xl" />
          </div>
        </div>
      ))}
    </div>
  );
}
