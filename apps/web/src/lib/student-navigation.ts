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
  ShieldAlert,
  Receipt,
  Bell,
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
      href: '/portal/siswa/dashboard',
      icon: LayoutDashboard,
    },
  ];

  // Sisipkan link program spesifik tepat di bawah dashboard beranda
  if (currentProg === 'tahfidz') {
    const currentJuz = programProfile?.current_juz ?? 2;
    baseLinks.push({
      name: 'Hafalan & Tasmi',
      href: '/portal/siswa/hafalan',
      icon: BookOpen,
      badge: `${currentJuz} Juz`,
      badgeColor: 'bg-emerald-700 text-white',
    });
  } else if (currentProg === 'icp') {
    baseLinks.push({
      name: 'Cambridge & Portofolio',
      href: '/portal/siswa/cambridge',
      icon: Globe,
      badge: 'Cambridge',
      badgeColor: 'bg-blue-800 text-white',
    });
  } else if (currentProg === 'inklusi') {
    baseLinks.push({
      name: 'Target Mandiri & PPI',
      href: '/portal/siswa/ppi',
      icon: Target,
      badge: 'PPI Aktif',
      badgeColor: 'bg-purple-700 text-white',
    });
  } else if (currentProg === 'reguler') {
    baseLinks.push({
      name: 'Proyek P5 & Karya',
      href: '/portal/siswa/p5',
      icon: Layers,
      badge: 'P5 Aktif',
      badgeColor: 'bg-slate-700 text-white',
    });
  }

  // Modul KBM & Layanan Umum
  const commonAcademicLinks: StudentNavLink[] = [
    {
      name: 'Ujian CBT Web',
      href: '/portal/siswa/cbt',
      icon: MonitorPlay,
    },
    {
      name: 'Jadwal & Kalender',
      href: '/portal/siswa/jadwal',
      icon: Calendar,
    },
    {
      name: 'Modul & Tugas LMS',
      href: '/portal/siswa/tugas',
      icon: CheckSquare,
    },
    {
      name: 'Capaian Nilai & Rapor',
      href: '/portal/siswa/nilai',
      icon: FileSpreadsheet,
    },
    {
      name: 'Poin & Ekstrakurikuler',
      href: '/portal/siswa/poin',
      icon: Award,
    },
    {
      name: 'Tagihan & Biaya',
      href: '/portal/siswa/tagihan',
      icon: Receipt,
    },
    {
      name: 'Konseling & Bantuan',
      href: '/portal/siswa/konseling',
      icon: HeartHandshake,
    },
    {
      name: 'Notifikasi',
      href: '/portal/notifikasi',
      icon: Bell,
    },
    {
      name: 'Lapor Bullying',
      href: '/lapor',
      icon: ShieldAlert,
    },
  ];

  return [...baseLinks, ...commonAcademicLinks];
}
