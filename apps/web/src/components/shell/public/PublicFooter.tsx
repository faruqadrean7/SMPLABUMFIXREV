import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { MapPin, Phone, Mail } from 'lucide-react';
import { FooterLeafletMap } from './FooterLeafletMap';
import { SCHOOL_CONTACT_INFO } from '@/features/public-site/content/navigation';

export function PublicFooter() {
  return (
    <footer className="bg-[#062940] text-white/80 pt-16 border-t-4 border-[#C89332]">
      <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 pb-12">
          {/* Kolom 1: Identitas Institusi & Mini Map Preview (4 Kolom pada Desktop) */}
          <div className="lg:col-span-4 flex flex-col gap-3.5">
            <div className="flex items-center gap-3">
              <Image
                src="/images/logo.png"
                alt="Logo SMP Laboratorium"
                width={48}
                height={48}
                className="w-12 h-12 object-contain flex-shrink-0 drop-shadow"
              />
              <div className="flex flex-col">
                <span className="font-pub-heading font-bold text-white text-base sm:text-lg leading-tight">
                  SMP Laboratorium
                </span>
                <span className="text-xs text-white/70 tracking-wide mt-0.5">
                  P2LP Universitas Negeri Malang
                </span>
              </div>
            </div>

            <p className="text-xs sm:text-sm text-white/85 font-medium mt-1">
              Beradab, Kreatif, Berprestasi
            </p>

            {/* Mini Map Card Leaflet dengan Skin Google Maps */}
            <div className="pt-2">
              <FooterLeafletMap />
            </div>
          </div>

          {/* Kolom 2: Tautan Cepat (2 Kolom pada Desktop) */}
          <div className="lg:col-span-2">
            <h3 className="font-pub-heading text-base font-bold text-white mb-4">
              Tautan Cepat
            </h3>
            <ul className="flex flex-col gap-2 text-xs sm:text-sm text-white/80">
              <li>
                <Link href="/" className="hover:text-white transition-colors">
                  Beranda
                </Link>
              </li>
              <li>
                <Link href="/profil" className="hover:text-white transition-colors">
                  Profil
                </Link>
              </li>
              <li>
                <Link href="/akademik" className="hover:text-white transition-colors">
                  Akademik
                </Link>
              </li>
              <li>
                <Link href="/berita" className="hover:text-white transition-colors">
                  Berita
                </Link>
              </li>
              <li>
                <Link href="/galeri" className="hover:text-white transition-colors">
                  Galeri
                </Link>
              </li>
              <li>
                <Link href="/kontak" className="hover:text-white transition-colors">
                  Kontak
                </Link>
              </li>
              <li>
                <Link href="/ppdb" className="hover:text-white transition-colors">
                  PPDB Online
                </Link>
              </li>
            </ul>
          </div>

          {/* Kolom 3: Akses Portal (2 Kolom pada Desktop) */}
          <div className="lg:col-span-2">
            <h3 className="font-pub-heading text-base font-bold text-white mb-4">
              Akses Portal
            </h3>
            <ul className="flex flex-col gap-2 text-xs sm:text-sm text-white/80">
              <li>
                <Link href="/portal/siswa" className="hover:text-white transition-colors">
                  Portal Siswa
                </Link>
              </li>
              <li>
                <Link href="/portal/guru" className="hover:text-white transition-colors">
                  Portal Guru
                </Link>
              </li>
              <li>
                <Link href="/portal/ortu" className="hover:text-white transition-colors">
                  Portal Orang Tua
                </Link>
              </li>
              <li>
                <a
                  href="https://sso.um.ac.id"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-white transition-colors"
                >
                  SSO UM
                </a>
              </li>
            </ul>
          </div>

          {/* Kolom 4: Kontak Kami (4 Kolom pada Desktop) */}
          <div className="lg:col-span-4">
            <h3 className="font-pub-heading text-base font-bold text-white mb-4">
              Kontak Kami
            </h3>
            <ul className="flex flex-col gap-3 text-xs sm:text-sm text-white/80">
              <li className="flex gap-3 items-start">
                <MapPin className="w-4 h-4 text-white/90 shrink-0 mt-0.5" aria-hidden="true" />
                <span className="leading-relaxed">
                  {SCHOOL_CONTACT_INFO.address}
                </span>
              </li>
              <li className="flex gap-3 items-center">
                <Phone className="w-4 h-4 text-white/90 shrink-0" aria-hidden="true" />
                <a href={SCHOOL_CONTACT_INFO.phone.tel} className="hover:text-white transition-colors">
                  Telepon: {SCHOOL_CONTACT_INFO.phone.display}
                </a>
              </li>
              <li className="flex gap-3 items-center">
                <svg className="w-4 h-4 text-white/90 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <a href={SCHOOL_CONTACT_INFO.whatsapp.url} target="_blank" rel="noopener noreferrer" className="hover:text-white transition-colors">
                  WhatsApp: {SCHOOL_CONTACT_INFO.whatsapp.display}
                </a>
              </li>
              <li className="flex gap-3 items-center">
                <Mail className="w-4 h-4 text-white/90 shrink-0" aria-hidden="true" />
                <a href={SCHOOL_CONTACT_INFO.email.mailto} className="hover:text-white transition-colors">
                  {SCHOOL_CONTACT_INFO.email.display}
                </a>
              </li>
            </ul>
          </div>
        </div>

        {/* Sub-footer Hak Cipta */}
        <div className="border-t border-white/10 py-6 text-xs text-white/60 flex flex-col md:flex-row items-center justify-between gap-3 text-center md:text-left">
          <div>{SCHOOL_CONTACT_INFO.copyright}</div>
          <div>{SCHOOL_CONTACT_INFO.affiliation}</div>
        </div>
      </div>
    </footer>
  );
}
