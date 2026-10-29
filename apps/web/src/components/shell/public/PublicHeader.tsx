'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { usePathname } from 'next/navigation';
import { Menu, X, Phone, Clock, Building, MapPin, ChevronRight } from 'lucide-react';
import { PUBLIC_NAV_ITEMS, SCHOOL_CONTACT_INFO } from '@/features/public-site/content/navigation';
import { PublicAuthNav } from './PublicAuthNav';

export function PublicHeader() {
  const pathname = usePathname();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const menuTriggerRef = useRef<HTMLButtonElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const drawerRef = useRef<HTMLElement>(null);

  // Buka menu mobile
  const openMobileMenu = () => {
    setIsMobileMenuOpen(true);
  };

  // Tutup menu mobile dan kembalikan fokus ke tombol pemicu
  const closeMobileMenu = useCallback((restoreFocus: boolean = true) => {
    setIsMobileMenuOpen(false);
    if (restoreFocus) {
      // Defer focus restoration slightly to prevent scroll jump
      requestAnimationFrame(() => {
        menuTriggerRef.current?.focus();
      });
    }
  }, []);

  // Pengelolaan interaksi keyboard (Escape, Focus trap dasar) & Scroll Lock
  useEffect(() => {
    if (!isMobileMenuOpen) {
      document.body.style.overflow = '';
      return;
    }

    // Kunci scroll saat drawer modal terbuka
    document.body.style.overflow = 'hidden';

    // Pindahkan fokus ke tombol tutup di dalam drawer
    const timer = setTimeout(() => {
      closeButtonRef.current?.focus();
    }, 50);

    const handleKeyDown = (e: KeyboardEvent) => {
      // Tutup via tombol Escape
      if (e.key === 'Escape') {
        e.preventDefault();
        closeMobileMenu(true);
        return;
      }

      // Sederhana: Focus trap pada elemen interaktif di dalam drawer
      if (e.key === 'Tab' && drawerRef.current) {
        const focusableElements = drawerRef.current.querySelectorAll<HTMLElement>(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (e.shiftKey) {
          if (document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
          }
        } else {
          if (document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
          }
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      clearTimeout(timer);
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isMobileMenuOpen, closeMobileMenu]);

  // Tutup menu saat berpindah rute
  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [pathname]);

  return (
    <>
      {/* Header Sticky Utama */}
      <header className="sticky top-0 z-40 transition-all duration-200 bg-white shadow-xs border-b border-gray-100">
        {/* Top Utility Bar (Informasi Kampus & Kontak Resmi Sesuai Mockup) */}
        <div className="hidden lg:block bg-[#062940] border-b border-white/10 text-xs text-white/90 py-3 px-4 sm:px-8 lg:px-12 xl:px-16">
          <div className="w-full flex items-center justify-between">
            {/* Sisi Kiri: Institusi & Alamat */}
            <div className="flex items-center gap-7">
              <div className="flex items-center gap-2 text-white/95 font-medium">
                <Building className="w-4 h-4 text-white/85" aria-hidden="true" />
                <span>P2LP-Universitas Negeri Malang</span>
              </div>
              <div className="flex items-center gap-2 text-white/85">
                <MapPin className="w-4 h-4 text-white/85" aria-hidden="true" />
                <span>Jl. Simpang Bogor No. T-7, Sumbersari, Malang</span>
              </div>
            </div>

            {/* Sisi Kanan: Telepon & Jam Kantor */}
            <div className="flex items-center gap-7">
              <a
                href={SCHOOL_CONTACT_INFO.phone.tel}
                className="flex items-center gap-2 text-white/85 hover:text-white transition-colors"
                title="Telepon Resmi Sekolah"
              >
                <Phone className="w-4 h-4 text-white/85" aria-hidden="true" />
                <span>(0341) 588550</span>
              </a>
              <div className="flex items-center gap-2 text-white/85">
                <Clock className="w-4 h-4 text-white/85" aria-hidden="true" />
                <span>Senin - Jumat 07.00 - 15.00</span>
              </div>
            </div>
          </div>
        </div>

        {/* Bar Navigasi Utama */}
        <div className="w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-3 flex items-center justify-between bg-white">
          {/* Dua Logo Institusi Berdampingan (UM & SMP Lab UM) */}
          <Link
            href="/"
            className="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2 rounded-lg p-0.5"
          >
            <div className="flex items-center gap-1.5 sm:gap-2">
              {/* <Image
                src="/images/logo-um.png"
                alt="Logo Universitas Negeri Malang"
                width={40}
                height={40}
                priority
                unoptimized
                className="w-7 h-7 sm:w-9 sm:h-9 lg:w-10 lg:h-10 object-contain flex-shrink-0 drop-shadow-xs transition-transform group-hover:scale-105"
              /> */}
              <Image
                src="/images/logo.png"
                alt="Logo SMP Laboratorium"
                width={40}
                height={40}
                priority
                unoptimized
                className="w-7 h-7 sm:w-9 sm:h-9 lg:w-10 lg:h-10 object-contain flex-shrink-0 drop-shadow-xs transition-transform group-hover:scale-105"
              />
            </div>
            <div className="flex flex-col">
              <span className="font-pub-heading text-sm sm:text-base lg:text-[1.25rem] font-bold text-[#0A3D62] leading-tight tracking-tight">
                SMP Laboratorium
              </span>
              <span className="text-[9px] sm:text-[11px] lg:text-xs text-[#4A6B82] font-medium tracking-tight">
                P2LP Universitas Negeri Malang
              </span>
            </div>
          </Link>

          {/* Navigasi Desktop Bersih Sesuai Mockup (Tanpa Garis Bawah, Hanya Teks Bold / Warna) */}
          <nav className="hidden lg:flex items-center gap-7" aria-label="Navigasi Utama">
            {PUBLIC_NAV_ITEMS.map((item) => {
              const isActive =
                item.href === '/'
                  ? pathname === '/'
                  : pathname === item.href || pathname.startsWith(`${item.href}/`);

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`py-1 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332] rounded ${isActive
                    ? 'text-[#0A3D62] font-bold'
                    : 'text-gray-600 hover:text-[#0A3D62] font-medium'
                    }`}
                  aria-current={isActive ? 'page' : undefined}
                >
                  {item.label}
                </Link>
              );
            })}
          </nav>

          {/* Quick Actions Desktop (PPDB Online Emas & Masuk Portal Outline Sesuai Mockup) */}
          <div className="hidden lg:flex items-center gap-3">
            <Link
              href="/ppdb"
              target="_blank"
              rel="noopener noreferrer"
              className="bg-pub-gold hover:bg-pub-gold-hover text-pub-navy-deep font-semibold text-sm px-4 py-2 rounded-[10px] shadow-sm hover:shadow transition-all inline-flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-pub-gold focus:ring-offset-2 focus:ring-offset-pub-navy"
            >
              PPDB Online
            </Link>
            <PublicAuthNav variant="desktop" />
          </div>

          {/* Tombol Pemicu Drawer Mobile (Hamburger) */}
          <button
            ref={menuTriggerRef}
            id="mobileMenuBtn"
            type="button"
            onClick={openMobileMenu}
            aria-label={isMobileMenuOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'}
            aria-expanded={isMobileMenuOpen}
            aria-controls="mobile-nav-drawer"
            className="lg:hidden p-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332] focus:ring-offset-2"
          >
            <Menu className="w-5 h-5" aria-hidden="true" />
          </button>
        </div>
      </header>

      {/* Drawer Modal Mobile (Hanya dirender ke DOM saat terbuka agar tidak ada kebocoran fokus / screen reader saat tertutup) */}
      {isMobileMenuOpen && (
        <>
          <div
            data-testid="mobile-backdrop"
            onClick={() => closeMobileMenu(true)}
            className="fixed inset-0 bg-black/60 z-50 lg:hidden transition-opacity backdrop-blur-sm"
            aria-hidden="true"
          />

          <aside
            ref={drawerRef}
            id="mobile-nav-drawer"
            role="dialog"
            aria-modal="true"
            aria-label="Menu navigasi mobile"
            className="fixed top-0 right-0 bottom-0 w-[min(340px,85vw)] bg-white z-50 p-6 flex flex-col shadow-2xl lg:hidden animate-in slide-in-from-right duration-200 border-l border-gray-100 overflow-y-auto"
          >
            {/* Header Drawer */}
            <div className="flex items-center justify-between pb-5 border-b border-gray-100 shrink-0">
              <Link
                href="/"
                onClick={() => closeMobileMenu(false)}
                className="flex items-center gap-3 focus:outline-none focus:ring-2 focus:ring-[#C89332] rounded-lg p-0.5"
              >
                <Image
                  src="/images/logo.png"
                  alt="Logo SMP Laboratorium"
                  width={40}
                  height={40}
                  priority
                  unoptimized
                  className="w-9 h-9 object-contain shrink-0"
                />
                <div className="flex flex-col">
                  <span className="font-pub-heading text-base font-bold text-[#0A3D62] leading-tight tracking-tight">
                    SMP Laboratorium
                  </span>
                  <span className="text-[11px] text-[#4A6B82] font-medium tracking-tight">
                    P2LP Universitas Negeri Malang
                  </span>
                </div>
              </Link>
              <button
                ref={closeButtonRef}
                type="button"
                onClick={() => closeMobileMenu(true)}
                aria-label="Tutup panel navigasi"
                className="p-2 rounded-lg border border-gray-200 text-gray-500 hover:text-[#0A3D62] hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-[#C89332]"
              >
                <X className="w-5 h-5" aria-hidden="true" />
              </button>
            </div>

            {/* Daftar Navigasi Utama */}
            <nav className="py-5 flex flex-col gap-1.5 shrink-0" aria-label="Menu Mobile">
              {PUBLIC_NAV_ITEMS.map((item) => {
                const isActive =
                  item.href === '/'
                    ? pathname === '/'
                    : pathname === item.href || pathname.startsWith(`${item.href}/`);

                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    onClick={() => closeMobileMenu(false)}
                    className={`group flex items-center justify-between px-4 py-3 rounded-xl text-sm transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332] ${isActive
                      ? 'bg-[#062940] text-white font-semibold shadow-xs'
                      : 'text-gray-700 hover:text-[#0A3D62] hover:bg-gray-50 font-medium'
                      }`}
                    aria-current={isActive ? 'page' : undefined}
                  >
                    <span>{item.label}</span>
                    <ChevronRight
                      className={`w-4 h-4 transition-transform group-hover:translate-x-0.5 ${isActive ? 'text-[#C89332]' : 'text-gray-400 group-hover:text-[#0A3D62]'
                        }`}
                      aria-hidden="true"
                    />
                  </Link>
                );
              })}
            </nav>

            {/* CTA Bawah Drawer Mobile */}
            <div className="mt-auto pt-6 border-t border-white/10 flex flex-col gap-3">
              <Link
                href="/ppdb"
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => closeMobileMenu(false)}
                className="w-full text-center bg-pub-gold hover:bg-pub-gold-hover text-pub-navy-deep font-semibold text-sm py-2.5 rounded-[10px] shadow transition-colors inline-block focus:outline-none focus:ring-2 focus:ring-pub-gold focus:ring-offset-2 focus:ring-offset-pub-navy"
              >
                Daftar PPDB Online
              </Link>
              <PublicAuthNav variant="mobile" onNavigate={() => closeMobileMenu(false)} />
            </div>
          </aside>
        </>
      )}
    </>
  );
}
