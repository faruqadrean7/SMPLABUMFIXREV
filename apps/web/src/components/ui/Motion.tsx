'use client';

import React, { useEffect, useRef, useState } from 'react';

// =========================================================================
// 1. FADE IN REVEAL (MUNCUL HALUS SAAT DI-SCROLL KE VIEWPORT)
// =========================================================================
export interface FadeInProps {
  children: React.ReactNode;
  direction?: 'up' | 'down' | 'left' | 'right' | 'none';
  delay?: number;
  duration?: number;
  className?: string;
  once?: boolean;
}

export function FadeIn({
  children,
  direction = 'up',
  delay = 0,
  duration = 0.6,
  className = '',
  once = true,
}: FadeInProps) {
  const [isVisible, setIsVisible] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          if (once) observer.disconnect();
        } else if (!once) {
          setIsVisible(false);
        }
      },
      { rootMargin: '-40px' }
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [once]);

  const getTransform = () => {
    if (isVisible) return 'none';
    switch (direction) {
      case 'up':
        return 'translateY(28px)';
      case 'down':
        return 'translateY(-28px)';
      case 'left':
        return 'translateX(28px)';
      case 'right':
        return 'translateX(-28px)';
      case 'none':
      default:
        return 'none';
    }
  };

  return (
    <div
      ref={ref}
      style={{
        opacity: isVisible ? 1 : 0,
        transform: getTransform(),
        transition: `opacity ${duration}s cubic-bezier(0.22, 1, 0.36, 1) ${delay}s, transform ${duration}s cubic-bezier(0.22, 1, 0.36, 1) ${delay}s`,
      }}
      className={className}
    >
      {children}
    </div>
  );
}

// =========================================================================
// 2. STAGGER CONTAINER & ITEM (ANIMASI BERUNTUN UNTUK GRID / KARTU)
// =========================================================================
export interface StaggerContainerProps {
  children: React.ReactNode;
  delayChildren?: number;
  staggerChildren?: number;
  className?: string;
  once?: boolean;
}

export function StaggerContainer({
  children,
  className = '',
}: StaggerContainerProps) {
  return <div className={className}>{children}</div>;
}

export function StaggerItem({
  children,
  className = '',
  yOffset = 24,
}: {
  children: React.ReactNode;
  className?: string;
  yOffset?: number;
}) {
  const [isVisible, setIsVisible] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          observer.disconnect();
        }
      },
      { rootMargin: '-50px' }
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div
      ref={ref}
      style={{
        opacity: isVisible ? 1 : 0,
        transform: isVisible ? 'none' : `translateY(${yOffset}px)`,
        transition: `opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1), transform 0.55s cubic-bezier(0.22, 1, 0.36, 1)`,
      }}
      className={className}
    >
      {children}
    </div>
  );
}

// =========================================================================
// 3. ANIMATED NUMBER COUNTER (ANGKA BERPUTAR NAIK SAAT TERLIHAT)
// =========================================================================
export interface AnimatedNumberProps {
  value: number;
  duration?: number;
  prefix?: string;
  suffix?: string;
  className?: string;
}

export function AnimatedNumber({
  value,
  duration = 1.8,
  prefix = '',
  suffix = '',
  className = '',
}: AnimatedNumberProps) {
  const [displayValue, setDisplayValue] = useState(0);
  const [isInView, setIsInView] = useState(false);
  const ref = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsInView(true);
          observer.disconnect();
        }
      },
      { rootMargin: '-30px' }
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    if (!isInView) return;

    let startTime: number | null = null;
    let animationFrameId: number;

    const animate = (timestamp: number) => {
      if (!startTime) startTime = timestamp;
      const progress = Math.min((timestamp - startTime) / (duration * 1000), 1);
      const easedProgress = 1 - Math.pow(1 - progress, 3);
      setDisplayValue(Math.floor(easedProgress * value));

      if (progress < 1) {
        animationFrameId = requestAnimationFrame(animate);
      } else {
        setDisplayValue(value);
      }
    };

    animationFrameId = requestAnimationFrame(animate);
    return () => cancelAnimationFrame(animationFrameId);
  }, [isInView, value, duration]);

  return (
    <span ref={ref} className={className}>
      {prefix}
      {displayValue.toLocaleString('id-ID')}
      {suffix}
    </span>
  );
}

// =========================================================================
// 4. RUNNING MARQUEE TICKER (PITA TEKS BERJALAN KONTINU PERSIS SITUS KAMPUS KELAS DUNIA)
// =========================================================================
export interface MarqueeTickerProps {
  items: string[];
  speed?: number;
  className?: string;
}

export function MarqueeTicker({
  items,
  className = '',
}: MarqueeTickerProps) {
  return (
    <div className={`overflow-hidden select-none flex whitespace-nowrap ${className}`}>
      <div className="flex items-center shrink-0 animate-marquee">
        {[...items, ...items].map((item, idx) => (
          <div key={idx} className="flex items-center">
            <span className="text-xs sm:text-sm font-semibold tracking-wide px-4">
              {item}
            </span>
            <span className="w-1.5 h-1.5 rounded-full bg-[#C89332] inline-block shrink-0 opacity-80" aria-hidden="true" />
          </div>
        ))}
      </div>
    </div>
  );
}
