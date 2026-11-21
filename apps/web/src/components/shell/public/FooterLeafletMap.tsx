'use client';

import React, { useEffect, useRef, useState } from 'react';

/**
 * Koordinat Presisi SMP Laboratorium Universitas Negeri Malang
 * Jl. Simpang Bogor No. T-7, Lowokwaru, Kota Malang (-7.960875, 112.617388)
 */
const SMP_LAB_COORDS: [number, number] = [-7.960875, 112.617388];

export function FooterLeafletMap() {
  const mapContainerRef = useRef<HTMLDivElement>(null);
  const mapInstanceRef = useRef<any>(null);
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    // 1. Muat Leaflet CSS jika belum tersedia
    if (!document.getElementById('leaflet-css')) {
      const link = document.createElement('link');
      link.id = 'leaflet-css';
      link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(link);
    }

    // 2. Fungsi inisialisasi peta Leaflet dengan skin Google Maps
    const initMap = (L: any) => {
      if (!mapContainerRef.current || mapInstanceRef.current) return;

      try {
        const map = L.map(mapContainerRef.current, {
          center: SMP_LAB_COORDS,
          zoom: 16,
          zoomControl: false,
          attributionControl: false,
          scrollWheelZoom: false,
          dragging: false,
          doubleClickZoom: false,
          boxZoom: false,
          keyboard: false,
          touchZoom: false,
        });

        // Skin Google Maps Roadmap tiles
        L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
          maxZoom: 20,
          subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
        }).addTo(map);

        // Marker Pin Google Maps khas merah dengan anchor presisi di ujung jarum pin
        const markerIcon = L.divIcon({
          className: 'google-map-marker-pin',
          html: `
            <div style="position: relative; display: flex; flex-direction: column; align-items: center; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.35)); pointer-events: none;">
              <div style="background: #FFFFFF; color: #0A3D62; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 9999px; box-shadow: 0 1px 4px rgba(0,0,0,0.2); white-space: nowrap; border: 1px solid #E2E8F0; margin-bottom: 2px; line-height: 1.2;">
                SMP Lab UM
              </div>
              <svg width="24" height="30" viewBox="0 0 24 30" fill="none">
                <path d="M12 0C5.37 0 0 5.37 0 12C0 19.5 12 30 12 30C12 30 24 19.5 24 12C24 5.37 18.63 0 12 0Z" fill="#EA4335"/>
                <circle cx="12" cy="11" r="4.5" fill="#FFFFFF"/>
                <circle cx="12" cy="11" r="2.2" fill="#C5221F"/>
              </svg>
            </div>
          `,
          iconSize: [80, 52],
          iconAnchor: [40, 50],
        });

        L.marker(SMP_LAB_COORDS, { icon: markerIcon }).addTo(map);

        mapInstanceRef.current = map;
        setIsLoaded(true);
      } catch (err) {
        console.error('Failed to initialize Leaflet map:', err);
      }
    };

    // 3. Muat script Leaflet jika belum ada di window
    if ((window as any).L) {
      initMap((window as any).L);
    } else {
      let script = document.getElementById('leaflet-js') as HTMLScriptElement;
      if (!script) {
        script = document.createElement('script');
        script.id = 'leaflet-js';
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        document.body.appendChild(script);
      }

      const handleLoad = () => {
        if ((window as any).L) {
          initMap((window as any).L);
        }
      };

      script.addEventListener('load', handleLoad);

      return () => {
        script.removeEventListener('load', handleLoad);
        if (mapInstanceRef.current) {
          mapInstanceRef.current.remove();
          mapInstanceRef.current = null;
        }
      };
    }

    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove();
        mapInstanceRef.current = null;
      }
    };
  }, []);

  return (
    <>
      <style jsx global>{`
        .google-map-marker-pin {
          background: transparent !important;
          border: none !important;
        }
        .footer-map-canvas .leaflet-tile-pane {
          filter: saturate(1.1) contrast(1.02);
        }
      `}</style>

      <a
        href="https://maps.google.com/?q=SMP+Laboratorium+Universitas+Negeri+Malang"
        target="_blank"
        rel="noopener noreferrer"
        className="group relative block w-full max-w-[320px] h-[130px] rounded-2xl overflow-hidden border border-white/20 bg-slate-800 shadow-md hover:border-[#C89332]/80 hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#C89332]"
        aria-label="Buka lokasi SMP Laboratorium UM di Google Maps"
      >
        {/* Kanvas Leaflet */}
        <div
          ref={mapContainerRef}
          className="footer-map-canvas w-full h-full bg-[#E5E3DF]"
          style={{ zIndex: 1 }}
        />

        {/* Loading Skeleton */}
        {!isLoaded && (
          <div className="absolute inset-0 bg-slate-800 animate-pulse flex items-center justify-center text-xs text-white/50">
            <span>Memuat Google Maps...</span>
          </div>
        )}

        {/* Header Badge di kiri atas */}
        <div className="absolute top-2 left-2 z-10 flex items-center gap-1.5 bg-white/95 backdrop-blur-md px-2.5 py-1 rounded-md shadow-sm border border-slate-200 pointer-events-none">
          <span className="w-2 h-2 rounded-full bg-[#EA4335] animate-pulse" />
          <span className="text-[10px] font-bold text-[#0A3D62] tracking-tight">Lokasi Kampus Lab UM</span>
        </div>

        {/* Action Button Badge di kanan bawah */}
        <div className="absolute bottom-2 right-2 z-10 flex items-center gap-1 bg-[#0A3D62]/90 group-hover:bg-[#C89332] text-white text-[10px] font-semibold px-2 py-1 rounded-md shadow-md backdrop-blur-xs transition-colors pointer-events-none">
          <span>Buka di Maps</span>
          <span aria-hidden="true">↗</span>
        </div>
      </a>
    </>
  );
}
