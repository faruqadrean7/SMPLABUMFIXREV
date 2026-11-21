import React from 'react';

interface IconProps extends React.SVGProps<SVGSVGElement> {
  size?: number;
  className?: string;
}

/**
 * Flaticon-Style Premium Educational Icons
 * Desain bergaya Flat / Duotone khas Flaticon dengan palet emas (Pub Gold #C98A1F),
 * amber hangat (#E5A83B), dan navy (#0A3D62).
 */

// 1. Icon Medali Penghargaan / Akreditasi A (Flaticon Style)
export function FlaticonMedal({ size = 48, className = '', ...props }: IconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      {...props}
    >
      {/* Pita Gantung Belakang */}
      <path d="M22 36L14 58L26 53L32 40" fill="#B27615" />
      <path d="M42 36L50 58L38 53L32 40" fill="#C98A1F" />
      <path d="M22 36L14 58L22 55L26 40" fill="#99630E" opacity="0.4" />
      
      {/* Lingkaran Luar Medali */}
      <circle cx="32" cy="28" r="22" fill="#E5A83B" />
      <circle cx="32" cy="28" r="19" fill="#F5C35D" />
      <circle cx="32" cy="28" r="16" fill="#C98A1F" />
      <circle cx="32" cy="28" r="14" fill="#F8DF9E" />
      
      {/* Lambang Bintang / Kemenangan di Tengah */}
      <path
        d="M32 18L35.2 24.5L42.4 25.5L37.2 30.6L38.4 37.8L32 34.4L25.6 37.8L26.8 30.6L21.6 25.5L28.8 24.5L32 18Z"
        fill="#C98A1F"
      />
    </svg>
  );
}

// 2. Icon Buku Terbuka / Kurikulum Merdeka (Flaticon Gold Style Sesuai Referensi Gambar 1)
export function FlaticonBook({ size = 52, className = '', ...props }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" className={className} {...props}>
      {/* Sampul buku terbuka dua sisi melengkung */}
      <path
        d="M6 14C6 11.8 7.8 10 10 10H22C23.1 10 24 10.9 24 12V38C24 38 20 36 10 36C7 36 6 37 6 37V14Z"
        fill="#C48D28"
      />
      <path
        d="M42 14C42 11.8 40.2 10 38 10H26C24.9 10 24 10.9 24 12V38C24 38 28 36 38 36C41 36 42 37 42 37V14Z"
        fill="#C48D28"
      />
      {/* Garis halaman putih di dalam lembaran buku */}
      <rect x="10" y="15" width="9" height="2" rx="1" fill="#FFFFFF" />
      <rect x="10" y="20" width="9" height="2" rx="1" fill="#FFFFFF" />
      <rect x="10" y="25" width="6" height="2" rx="1" fill="#FFFFFF" />
      <rect x="29" y="15" width="9" height="2" rx="1" fill="#FFFFFF" />
      <rect x="29" y="20" width="9" height="2" rx="1" fill="#FFFFFF" />
      <rect x="29" y="25" width="6" height="2" rx="1" fill="#FFFFFF" />
    </svg>
  );
}

// 3. Icon Globe Dunia / Program Cambridge ICP (Flaticon Gold Style Sesuai Referensi Gambar 1)
export function FlaticonGlobe({ size = 52, className = '', ...props }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" className={className} {...props}>
      {/* Bola Dunia dengan Garis Lintang dan Bujur */}
      <circle cx="24" cy="20" r="13" stroke="#C48D28" strokeWidth="2.5" />
      <ellipse cx="24" cy="20" rx="5.5" ry="13" stroke="#C48D28" strokeWidth="2" />
      <line x1="11" y1="20" x2="37" y2="20" stroke="#C48D28" strokeWidth="2" />
      <path d="M14 13C17 15 20 16 24 16C28 16 31 15 34 13" stroke="#C48D28" strokeWidth="1.8" />
      <path d="M14 27C17 25 20 24 24 24C28 24 31 25 34 27" stroke="#C48D28" strokeWidth="1.8" />
      {/* Dudukan Kaki Bola Dunia */}
      <path
        d="M10 20C10 28 16.5 34.5 24 34.5C28 34.5 31.5 33 34 30.5"
        stroke="#C48D28"
        strokeWidth="2.5"
        strokeLinecap="round"
      />
      <path d="M24 35V42" stroke="#C48D28" strokeWidth="2.5" strokeLinecap="round" />
      <path d="M16 42H32" stroke="#C48D28" strokeWidth="2.5" strokeLinecap="round" />
    </svg>
  );
}

// 4. Icon Al-Qur'an di atas Rehal / Program Tahfidz (Flaticon Gold Style Sesuai Referensi Gambar 1)
export function FlaticonQuran({ size = 52, className = '', ...props }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" className={className} {...props}>
      {/* Ornamen Kubah Puncak */}
      <circle cx="24" cy="8" r="2" fill="#C48D28" />
      <path d="M24 10V13" stroke="#C48D28" strokeWidth="1.8" strokeLinecap="round" />
      {/* Kitab Terbuka */}
      <path
        d="M10 16C14 14.5 19 15.5 24 18C29 15.5 34 14.5 38 16V30C34 28.5 29 29.5 24 32C19 29.5 14 28.5 10 30V16Z"
        fill="#C48D28"
      />
      <line x1="24" y1="18" x2="24" y2="32" stroke="#FFFFFF" strokeWidth="1.5" strokeLinecap="round" />
      {/* Meja Rehal Kayu Menyilang di Bawah */}
      <path
        d="M13 32L35 43M35 32L13 43"
        stroke="#C48D28"
        strokeWidth="2.8"
        strokeLinecap="round"
      />
      <path d="M10 44H16M32 44H38" stroke="#C48D28" strokeWidth="2.5" strokeLinecap="round" />
    </svg>
  );
}

// 5. Icon 3 Orang / Pendidikan Inklusi (Flaticon Gold Style Sesuai Referensi Gambar 1)
export function FlaticonInclusion({ size = 52, className = '', ...props }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" className={className} {...props}>
      {/* Figur Tengah Utama */}
      <circle cx="24" cy="16" r="5.5" fill="#C48D28" />
      <path
        d="M15 34C15 29 19 25 24 25C29 25 33 29 33 34V36H15V34Z"
        fill="#C48D28"
      />
      {/* Figur Samping Kiri */}
      <circle cx="12" cy="19" r="4.5" fill="#C48D28" />
      <path
        d="M6 36C6 31.5 9.5 28 14 28C14.8 28 15.6 28.1 16.3 28.4C15.5 30 15 31.9 15 34H6V36Z"
        fill="#C48D28"
      />
      {/* Figur Samping Kanan */}
      <circle cx="36" cy="19" r="4.5" fill="#C48D28" />
      <path
        d="M42 36C42 31.5 38.5 28 34 28C33.2 28 32.4 28.1 31.7 28.4C32.5 30 33 31.9 33 34H42V36Z"
        fill="#C48D28"
      />
    </svg>
  );
}

// 6. Icon Gedung Universitas / P2LP UM (Flaticon Style untuk Ribbon)
export function FlaticonUniversity({ size = 36, className = '', ...props }: IconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      {...props}
    >
      {/* Atap Segitiga Klasik */}
      <path d="M24 6L6 16H42L24 6Z" fill="#FFFFFF" />
      <rect x="8" y="16" width="32" height="3" fill="#E2E8F0" />
      
      {/* 4 Pilar Neoklasik */}
      <rect x="10" y="21" width="4" height="15" rx="1" fill="#FFFFFF" />
      <rect x="18" y="21" width="4" height="15" rx="1" fill="#FFFFFF" />
      <rect x="26" y="21" width="4" height="15" rx="1" fill="#FFFFFF" />
      <rect x="34" y="21" width="4" height="15" rx="1" fill="#FFFFFF" />
      
      {/* Tangga & Pondasi Bawah */}
      <rect x="6" y="38" width="36" height="3" rx="0.5" fill="#E2E8F0" />
      <rect x="4" y="41" width="40" height="3" rx="0.5" fill="#CBD5E1" />
    </svg>
  );
}

// 7. Icon Roset Penghargaan Putih (Flaticon Style untuk Ribbon Akreditasi A)
export function FlaticonRosette({ size = 36, className = '', ...props }: IconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      {...props}
    >
      {/* Pita Gantung */}
      <path d="M16 28L10 44L19 40L24 30" fill="#E2E8F0" />
      <path d="M32 28L38 44L29 40L24 30" fill="#FFFFFF" />
      
      {/* Roset Medali */}
      <circle cx="24" cy="20" r="14" fill="#FFFFFF" />
      <circle cx="24" cy="20" r="11" fill="#0A3D62" />
      <circle cx="24" cy="20" r="9" fill="#FFFFFF" />
      <path
        d="M24 14L25.8 17.8L30 18.4L27 21.3L27.7 25.5L24 23.5L20.3 25.5L21 21.3L18 18.4L22.2 17.8L24 14Z"
        fill="#0A3D62"
      />
    </svg>
  );
}

// 8. Icon Kompas / Karakter Beradab Berprestasi (Flaticon Style untuk Ribbon)
export function FlaticonCompass({ size = 36, className = '', ...props }: IconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      {...props}
    >
      <circle cx="24" cy="24" r="18" stroke="#FFFFFF" strokeWidth="2.5" />
      <circle cx="24" cy="24" r="14" stroke="#E2E8F0" strokeWidth="1" strokeDasharray="2 2" />
      
      {/* Jarum Kompas */}
      <path d="M24 10L28 22L24 26L20 22L24 10Z" fill="#F59E0B" />
      <path d="M24 38L28 26L24 22L20 26L24 38Z" fill="#FFFFFF" />
      <circle cx="24" cy="24" r="2.5" fill="#0A3D62" />
    </svg>
  );
}

// 9. Icon Gedung Kampus / Tahun Berdiri (Flaticon Gold Style)
export function FlaticonBuilding({ size = 40, className = '', ...props }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" className={className} {...props}>
      <path d="M24 6L6 17H42L24 6Z" fill="#C48D28" />
      <rect x="8" y="17" width="32" height="3" fill="#D49D38" />
      <rect x="11" y="22" width="4" height="14" rx="1" fill="#C48D28" />
      <rect x="19" y="22" width="4" height="14" rx="1" fill="#C48D28" />
      <rect x="25" y="22" width="4" height="14" rx="1" fill="#C48D28" />
      <rect x="33" y="22" width="4" height="14" rx="1" fill="#C48D28" />
      <rect x="6" y="38" width="36" height="3" rx="0.5" fill="#D49D38" />
      <rect x="4" y="41" width="40" height="3" rx="0.5" fill="#B27615" />
    </svg>
  );
}

// 10. Icon Trofi Prestasi / 24 Cabang Ekstrakurikuler (Flaticon Gold Style)
export function FlaticonTrophy({ size = 40, className = '', ...props }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" className={className} {...props}>
      {/* Cawan Trofi Emas */}
      <path d="M14 10H34V22C34 27.5 29.5 32 24 32C18.5 32 14 27.5 14 22V10Z" fill="#C48D28" />
      <path d="M16 12H32V21C32 25.4 28.4 29 24 29C19.6 29 16 25.4 16 21V12Z" fill="#E5A83B" />
      {/* Bintang Kemenangan di Tengah */}
      <path d="M24 16L25.5 19.5L29 20L26.5 22.5L27 26L24 24.2L21 26L21.5 22.5L19 20L22.5 19.5L24 16Z" fill="#FFFFFF" />
      {/* Gagang Kiri dan Kanan */}
      <path d="M14 13H10C8.3 13 7 14.3 7 16V19C7 22.3 9.7 25 13 25H14.5" stroke="#C48D28" strokeWidth="2.5" strokeLinecap="round" />
      <path d="M34 13H38C39.7 13 41 14.3 41 16V19C41 22.3 38.3 25 35 25H33.5" stroke="#C48D28" strokeWidth="2.5" strokeLinecap="round" />
      {/* Batang & Tatakan Bawah */}
      <path d="M22 32H26V37H22V32Z" fill="#B27615" />
      <rect x="15" y="37" width="18" height="5" rx="1" fill="#C48D28" />
    </svg>
  );
}

// 11. Icon Topi Wisuda Toga / 4 Program Pilihan Akademik (Flaticon Gold Style)
export function FlaticonGraduationCap({ size = 40, className = '', ...props }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" fill="none" className={className} {...props}>
      {/* Belah Ketupat Puncak Toga */}
      <path d="M24 8L6 17L24 26L42 17L24 8Z" fill="#C48D28" />
      <path d="M24 10.5L10 17L24 23.5L38 17L24 10.5Z" fill="#E5A83B" />
      {/* Kopiah Toga di Bawah */}
      <path d="M14 21.5V30C14 33.5 18.5 36.5 24 36.5C29.5 36.5 34 33.5 34 30V21.5L24 26.5L14 21.5Z" fill="#B27615" />
      {/* Tali & Kuncir Toga */}
      <circle cx="24" cy="17" r="2" fill="#FFFFFF" />
      <path d="M24 17L37 21V33" stroke="#C48D28" strokeWidth="2" strokeLinecap="round" />
      <path d="M35 33H39L38 38H36L35 33Z" fill="#C48D28" />
    </svg>
  );
}
