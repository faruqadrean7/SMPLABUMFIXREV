import React from 'react';
import Link from 'next/link';
import { ShieldCheck, HeartHandshake, ArrowRight, Lock, ExternalLink } from 'lucide-react';

interface SafetyCounselingCardProps {
  role?: 'student' | 'guardian';
}

export function SafetyCounselingCard({ role = 'student' }: SafetyCounselingCardProps) {
  const isStudent = role === 'student';

  return (
    <div className="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 text-white rounded-xl p-4 sm:p-5 shadow-xs border border-blue-800/60 relative overflow-hidden">
      {/* Background soft glow decoration */}
      <div
        aria-hidden="true"
        className="absolute -right-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full blur-2xl pointer-events-none"
      />

      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
        <div className="flex items-start gap-3.5">
          <div className="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-xs text-amber-300 border border-white/15 flex items-center justify-center shrink-0">
            {isStudent ? (
              <HeartHandshake className="w-5 h-5 text-amber-300" />
            ) : (
              <ShieldCheck className="w-5 h-5 text-emerald-300" />
            )}
          </div>
          <div>
            <div className="flex items-center gap-2 mb-1">
              <span className="text-xs font-bold text-amber-300 uppercase tracking-wider">
                {isStudent ? 'Ruang Aman Siswa' : 'Kanal Perlindungan Ananda'}
              </span>
              <span className="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white/10 text-white/90 border border-white/10">
                <Lock className="w-2.5 h-2.5" /> Kerahasiaan Terjamin
              </span>
            </div>
            <h3 className="text-sm sm:text-base font-extrabold tracking-tight text-white leading-snug">
              {isStudent
                ? 'Merasa Tidak Nyaman atau Mengalami Perundungan (Bullying)?'
                : 'Mendapati Masalah Perundungan atau Kekhawatiran pada Ananda?'}
            </h3>
            <p className="text-xs text-white/80 mt-1 max-w-xl leading-relaxed">
              {isStudent
                ? 'Kamu tidak sendirian. Sampaikan kejadian yang kamu alami atau saksikan secara rahasia langsung kepada Tim Bimbingan Konseling.'
                : 'Laporkan segala bentuk intimidasi, kekerasan verbal/fisik, atau masalah di sekolah secara aman. Laporan ditindaklanjuti secara tertutup oleh Guru BK.'}
            </p>
          </div>
        </div>

        {/* CTA ACTIONS */}
        <div className="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0 pt-1 md:pt-0">
          <Link
            href="/lapor"
            className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-bold bg-amber-400 hover:bg-amber-300 text-slate-950 transition-colors shadow-xs"
          >
            <span>{isStudent ? 'Lapor Aman ke BK' : 'Sampaikan Pengaduan'}</span>
            <ArrowRight className="w-3.5 h-3.5" />
          </Link>
          <Link
            href="/lapor/lacak"
            className="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg text-xs font-medium text-white/85 hover:text-white hover:bg-white/10 transition-colors border border-white/15"
          >
            <span>Lacak Tiket</span>
            <ExternalLink className="w-3 h-3 text-white/60" />
          </Link>
        </div>
      </div>
    </div>
  );
}
