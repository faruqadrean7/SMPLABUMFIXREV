import {
  LayoutDashboard,
  MonitorPlay,
  Calendar,
  CheckSquare,
  FileSpreadsheet,
  Award,
  HeartHandshake,
  BookOpen,
  Globe,
  Target,
  Layers,
  LucideIcon,
} from 'lucide-react';
import { StudentProgramType, StudentProgramProfileData } from './use-student-data';

export type { StudentProgramType };

export interface StudentNavLink {
  name: string;
  href: string;
  icon: LucideIcon;
  badge?: string;
  badgeColor?: string;
}

export function getStudentNavLinks(
  programType?: StudentProgramType,
  programProfile?: StudentProgramProfileData | Record<string, any>
): StudentNavLink[] {
  const currentProg = programType || 'reguler';

  const baseLinks: StudentNavLink[] = [
    {
      name: 'Ringkasan & Beranda',
      href: '/siswa/dashboard',
      icon: LayoutDashboard,
    },
  ];

  // Sisipkan link program spesifik tepat di bawah dashboard beranda
  if (currentProg === 'tahfidz') {
    const currentJuz = programProfile?.current_juz ?? 2;
    baseLinks.push({
      name: 'Hafalan & Tasmi',
      href: '/siswa/hafalan',
      icon: BookOpen,
      badge: `${currentJuz} Juz`,
      badgeColor: 'bg-emerald-700 text-white',
    });
  } else if (currentProg === 'icp') {
    baseLinks.push({
      name: 'Cambridge & Portofolio',
      href: '/siswa/cambridge',
      icon: Globe,
      badge: 'Cambridge',
      badgeColor: 'bg-blue-800 text-white',
    });
  } else if (currentProg === 'inklusi') {
    baseLinks.push({
      name: 'Target Mandiri & PPI',
      href: '/siswa/ppi',
      icon: Target,
      badge: 'PPI Aktif',
      badgeColor: 'bg-purple-700 text-white',
    });
  } else if (currentProg === 'reguler') {
    baseLinks.push({
      name: 'Proyek P5 & Karya',
      href: '/siswa/p5',
      icon: Layers,
      badge: 'P5 Aktif',
      badgeColor: 'bg-slate-700 text-white',
    });
  }

  // Modul KBM Umum
  const commonAcademicLinks: StudentNavLink[] = [
    {
      name: 'Ujian CBT Web',
      href: '/siswa/cbt',
      icon: MonitorPlay,
      badge: '1 Aktif',
      badgeColor: 'bg-rose-700 text-white',
    },
    {
      name: 'Jadwal & Kalender',
      href: '/siswa/jadwal',
      icon: Calendar,
      badge: 'Hari Ini',
      badgeColor: 'bg-blue-900 text-white',
    },
    {
      name: 'Modul & Tugas LMS',
      href: '/siswa/tugas',
      icon: CheckSquare,
      badge: '2 Tugas',
      badgeColor: 'bg-blue-900 text-white',
    },
    {
      name: 'Capaian Nilai & Rapor',
      href: '/siswa/nilai',
      icon: FileSpreadsheet,
      badge: 'KKTP 75',
      badgeColor: 'bg-slate-800 text-white',
    },
    {
      name: 'Poin & Ekstrakurikuler',
      href: '/siswa/poin',
      icon: Award,
      badge: '+30 Poin',
      badgeColor: 'bg-emerald-800 text-white',
    },
    {
      name: 'Konseling & Bantuan',
      href: '/siswa/konseling',
      icon: HeartHandshake,
      badge: 'Privat',
      badgeColor: 'bg-teal-800 text-white',
    },
  ];

  return [...baseLinks, ...commonAcademicLinks];
}
