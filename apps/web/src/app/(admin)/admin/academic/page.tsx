'use client';

import React from 'react';
import Link from 'next/link';
import {
  CalendarDays,
  Clock,
  BookMarked,
  UserCheck,
  FileCheck2,
  BookOpen,
  FileSpreadsheet,
  Award,
  ArrowRight,
  Sparkles,
} from 'lucide-react';

interface AcademicModuleCard {
  title: string;
  description: string;
  href: string;
  icon: React.ElementType;
  badge: string;
  badgeColor: string;
  features: string[];
}

const academicModules: AcademicModuleCard[] = [
  {
    title: 'Jadwal Pelajaran KBM',
    description:
      'Penyusunan matriks timetable mingguan per rombel/kelas, guru, dan ruangan dengan deteksi bentrok otomatis.',
    href: '/admin/academic/schedules',
    icon: Clock,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Matriks Timetable Grid interaktif',
      'Deteksi bentrok guru, kelas, & ruangan (HTTP 409)',
      'Manajemen slot jam pelajaran (Time Slots)',
      'Cetak & ekspor format jadwal KBM',
    ],
  },
  {
    title: 'Jurnal Mengajar Guru',
    description:
      'Pencatatan sesi pertemuan KBM harian oleh guru pengajar, materi, metode, dan integrasi presensi siswa.',
    href: '/admin/academic/journals',
    icon: BookMarked,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Entri topik & metode per jadwal',
      'Otorisasi khusus guru penugas',
      'Batas waktu pembaruan N hari',
      'Rekap monitoring oleh Kepala Sekolah / TU',
    ],
  },
  {
    title: 'Presensi KBM Harian',
    description:
      'Pencatatan kehadiran siswa per sesi kelas dan gerbang masuk harian dengan rekonsiliasi izin & sakit.',
    href: '/admin/academic/attendance',
    icon: UserCheck,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Presensi sesi kelas dari jurnal mengajar',
      'Integrasi otomatis surat izin/sakit walas',
      'Status hadir, izin, sakit, alpa, terlambat',
      'Rekap statistik bulanan & semester',
    ],
  },
  {
    title: 'Izin & Sakit Siswa',
    description:
      'Verifikasi dispensasi, izin, dan surat sakit dari wali murid atau siswa oleh wali kelas dengan sinkronisasi presensi.',
    href: '/admin/academic/leave-requests',
    icon: FileCheck2,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Antrean verifikasi perwalian wali kelas',
      'Dukungan lampiran surat dokter (PDF/Gambar)',
      'Sinkronisasi otomatis ke presensi harian',
      'Notifikasi keputusan instan ke pemohon',
    ],
  },
  {
    title: 'Presensi Guru & Staf',
    description:
      'Pencatatan presensi mandiri PTK dengan geofencing GPS, selfie, jam batas keterlambatan, dan entri koreksi resmi.',
    href: '/admin/academic/staff-attendance',
    icon: UserCheck,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Presensi mandiri geofencing & selfie foto',
      'Deteksi keterlambatan & jam batas otomatis',
      'Entri manual & koreksi data berizin audit',
      'Rekap persentase kehadiran bulanan PTK',
    ],
  },
  {
    title: 'Bank Modul Ajar',
    description:
      'Repositori terpadu modul ajar Kurikulum Merdeka, alur tujuan pembelajaran (ATP), silabus, dan modul proyek P5.',
    href: '/admin/academic/teaching-materials',
    icon: BookOpen,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Katalog modul ajar, ATP, silabus, & P5',
      'Pengelompokan per tingkat kelas & mapel',
      'Sistem riwayat revisi dan penomoran versi',
      'Tautan unduhan dokumen digital terintegrasi',
    ],
  },
  {
    title: 'Tugas & Materi LMS',
    description:
      'LMS Ringan terintegrasi untuk penerbitan tugas berbatas waktu, publikasi materi pembelajaran, pengumpulan siswa, dan penilaian formatif.',
    href: '/admin/academic/assignments',
    icon: FileSpreadsheet,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Penerbitan tugas kelas berbatas waktu',
      'Katalog materi ajar digital & lampiran',
      'Pengumpulan tugas & deteksi keterlambatan',
      'Penilaian formatif & umpan balik guru',
    ],
  },
  {
    title: 'Buku Nilai & Komponen Penilaian',
    description:
      'Pengelolaan bobot komponen penilaian formatif-sumatif, penguncian periode bulanan, dan entri spreadsheet matriks nilai siswa.',
    href: '/admin/academic/grades',
    icon: Award,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Entri massal grid spreadsheet interaktif',
      'Komponen formatif & sumatif berbobot',
      'Penguncian periode nilai bulanan resmi',
      'Validasi rentang 0–100 & rata-rata berbobot',
    ],
  },
  {
    title: 'Catatan Karakter Bulanan',
    description:
      'Penilaian 8 dimensi Profil Pelajar Pancasila, catatan naratif wali kelas per siswa, dan matriks rekap kelas bulanan.',
    href: '/admin/academic/character-notes',
    icon: Sparkles,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      '8 Dimensi Profil Pelajar Pancasila & Pembiasaan',
      'Catatan naratif deskriptif wali kelas',
      'Entri massal matriks cepat per kelas',
      'Integrasi data snapshot E-Progress Report',
    ],
  },
  {
    title: 'E-Progress Report Bulanan',
    description:
      'Laporan capaian belajar bulanan integrasi nilai akademik, kehadiran KBM, 8 dimensi Profil Pelajar Pancasila, dan pengesahan digital QR Kepala Sekolah.',
    href: '/admin/academic/progress-reports',
    icon: Award,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Integrasi otomatis Buku Nilai & Karakter',
      'Pengesahan bertingkat (Draf → ACC → Terbit)',
      'Dokumen PDF standar & verifikasi token QR',
      'Akses resmi bagi wali kelas, ortu, & siswa',
    ],
  },
  {
    title: 'E-Rapor Semester & Capaian Pembelajaran',
    description:
      'Laporan hasil belajar semester resmi Kurikulum Merdeka (KKTP), deskripsi capaian per mapel, rekap absensi & ekstrakurikuler, serta pengesahan dan penerbitan terpusat.',
    href: '/admin/academic/report-cards',
    icon: FileCheck2,
    badge: 'Aktif Produksi',
    badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    features: [
      'Nilai akhir terbobot formatif-sumatif semester',
      'Deskripsi capaian pembelajaran otomatis (KKTP: 75.0)',
      'Penguncian Kurikulum & ACC resmi Kepala Sekolah',
      'Dokumen PDF standar & verifikasi token QR publik',
    ],
  },
];

export default function AcademicHubPage() {
  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="rounded-xl border border-emerald-100 bg-gradient-to-r from-emerald-500/10 via-teal-500/5 to-transparent p-6">
        <div className="flex items-center gap-3">
          <div className="rounded-xl bg-emerald-600 p-2.5 text-white shadow-sm">
            <CalendarDays className="h-6 w-6" />
          </div>
          <div>
            <h1 className="text-xl font-bold tracking-tight text-gray-900">
              Portal Akademik & Kegiatan Belajar Mengajar (KBM)
            </h1>
            <p className="text-xs text-gray-600 mt-0.5">
              Kelola jadwal pelajaran, pemantauan jurnal mengajar guru, kehadiran, dan surat izin siswa
            </p>
          </div>
        </div>
      </div>

      {/* Grid Modules */}
      <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
        {academicModules.map((mod) => {
          const Icon = mod.icon;
          return (
            <div
              key={mod.title}
              className="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-xs transition hover:border-emerald-300 hover:shadow-md"
            >
              <div>
                <div className="flex items-center justify-between">
                  <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600 border border-emerald-100">
                    <Icon className="h-5 w-5" />
                  </div>
                  <span
                    className={`rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${mod.badgeColor}`}
                  >
                    {mod.badge}
                  </span>
                </div>

                <h3 className="mt-4 text-base font-bold text-gray-900">{mod.title}</h3>
                <p className="mt-1 text-xs text-gray-600 leading-relaxed">{mod.description}</p>

                <div className="mt-4 border-t border-gray-100 pt-3">
                  <span className="text-[11px] font-semibold text-gray-700">Fitur Utama:</span>
                  <ul className="mt-1.5 space-y-1">
                    {mod.features.map((f, i) => (
                      <li key={i} className="flex items-start gap-1.5 text-xs text-gray-600">
                        <span className="text-emerald-500 font-bold">•</span>
                        <span>{f}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>

              <div className="mt-5 pt-3 border-t border-gray-100">
                <Link
                  href={mod.href}
                  className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-emerald-700"
                >
                  <span>Buka Modul</span>
                  <ArrowRight className="h-3.5 w-3.5" />
                </Link>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
