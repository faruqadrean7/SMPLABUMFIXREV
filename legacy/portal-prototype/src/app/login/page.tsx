'use client';

import React, { useState, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { 
  School, 
  Lock, 
  User, 
  Eye, 
  EyeOff, 
  ArrowRight, 
  AlertCircle, 
  Loader2, 
  GraduationCap, 
  BookOpen, 
  Users, 
  CheckCircle2, 
  KeyRound
} from 'lucide-react';
import { toast } from 'sonner';
import { useAuth, getRoleDefaultPath } from '@/lib/auth-context';
import { tokenStorage } from '@/lib/token-storage';
import { apiClient } from '@/lib/api-client';

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { login } = useAuth();

  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Otomatis tangani SSO token transfer dari web public (port 3000)
  React.useEffect(() => {
    const ssoToken = searchParams.get('sso_token') || searchParams.get('token');
    const ssoRefresh = searchParams.get('sso_refresh') || searchParams.get('refresh');
    const redirectQuery = searchParams.get('redirect');

    if (ssoToken) {
      setIsLoading(true);
      tokenStorage.setAccessToken(ssoToken);
      if (ssoRefresh) {
        tokenStorage.setRefreshToken(ssoRefresh);
      }

      apiClient
        .get('/auth/me', {
          headers: { Authorization: `Bearer ${ssoToken}` },
        })
        .then((res) => {
          const userData = res.data?.data?.user || res.data?.data || res.data?.user;
          if (userData) {
            tokenStorage.setUser(userData);
            const targetPath =
              redirectQuery && redirectQuery.startsWith('/')
                ? redirectQuery
                : getRoleDefaultPath(userData);

            toast.success(`Selamat datang, ${userData.profile?.name || userData.name || userData.username}!`);
            if (typeof window !== 'undefined') {
              window.history.replaceState({}, document.title, window.location.pathname);
            }
            router.replace(targetPath);
          } else {
            setIsLoading(false);
          }
        })
        .catch(() => {
          tokenStorage.clear();
          setIsLoading(false);
          setErrorMessage('Gagal memvalidasi token sesi dari portal utama. Silakan masuk manual.');
        });
    }
  }, [searchParams, router]);

  const testPresets = [
    {
      role: 'Siswa',
      label: 'siswa.lab.001',
      badge: 'Siswa Kelas 7-A (Ahmad Fauzi)',
      icon: GraduationCap,
      identifier: 'siswa.lab.001',
      password: 'SistLabUM2026!UAT',
    },
    {
      role: 'Guru',
      label: 'guru.mtk.staging',
      badge: 'Guru MTK (Agus Santoso)',
      icon: BookOpen,
      identifier: 'guru.mtk.staging',
      password: 'SistLabUM2026!UAT',
    },
    {
      role: 'Wali Kelas',
      label: 'walas.7a.staging',
      badge: 'Walas 7-A (Nur Hayati)',
      icon: BookOpen,
      identifier: 'walas.7a.staging',
      password: 'SistLabUM2026!UAT',
    },
    {
      role: 'Wali Murid',
      label: 'ortu.siswa.001',
      badge: 'Orang Tua (Ahmad Fauzi)',
      icon: Users,
      identifier: 'ortu.siswa.001',
      password: 'SistLabUM2026!UAT',
    },
  ];

  const handleApplyPreset = (presetId: string, presetPass: string) => {
    setIdentifier(presetId);
    setPassword(presetPass);
    setErrorMessage(null);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    const trimmedId = identifier.trim();
    if (!trimmedId) {
      setErrorMessage('Silakan masukkan username, NIS, atau email akun Anda.');
      return;
    }
    if (!password) {
      setErrorMessage('Silakan masukkan kata sandi akun Anda.');
      return;
    }

    setIsLoading(true);
    try {
      const response = await login(trimmedId, password);
      const user = response.user;
      const targetDefaultPath = getRoleDefaultPath(user);

      // Check if redirect query param exists and matches user role
      const redirectQuery = searchParams.get('redirect');
      let finalPath = targetDefaultPath;

      if (redirectQuery && redirectQuery.startsWith('/')) {
        const userRoles = (user.roles || []).map((r) => String(r).toLowerCase());
        const isStudent = userRoles.includes('student');
        const isTeacher = userRoles.includes('teacher') || userRoles.includes('homeroom');
        const isGuardian = userRoles.includes('guardian');

        if (redirectQuery.startsWith('/siswa') && isStudent) {
          finalPath = redirectQuery;
        } else if (redirectQuery.startsWith('/guru') && isTeacher) {
          finalPath = redirectQuery;
        } else if (redirectQuery.startsWith('/ortu') && isGuardian) {
          finalPath = redirectQuery;
        }
      }

      toast.success(`Selamat datang, ${user.profile?.name || user.username}!`, {
        description: `Mengarahkan ke ${finalPath}...`,
      });

      router.replace(finalPath);
    } catch (err: unknown) {
      let message = 'Gagal masuk. Periksa kembali identitas dan kata sandi Anda.';
      if (typeof err === 'object' && err !== null && 'response' in err) {
        const axiosErr = err as { response?: { data?: { message?: string; error?: { message?: string } } } };
        message =
          axiosErr.response?.data?.error?.message ||
          axiosErr.response?.data?.message ||
          message;
      }
      setErrorMessage(message);
      toast.error('Autentikasi Gagal', { description: message });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 font-sans text-slate-900">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        {/* Brand Header */}
        <div className="flex justify-center">
          <div className="w-14 h-14 rounded-2xl bg-blue-900 text-white flex items-center justify-center shadow-sm">
            <School className="w-8 h-8 text-white" />
          </div>
        </div>
        <h2 className="mt-4 text-center text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
          LABSCHOOL UM
        </h2>
        <p className="mt-1 text-center text-xs sm:text-sm text-slate-600 font-medium">
          Portal Terpadu Siswa, Guru, dan Orang Tua
        </p>
      </div>

      <div className="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-6 sm:px-10 shadow-sm border border-slate-200/90 rounded-2xl">
          {/* Quick Presets for Rapid Evaluation */}
          <div className="mb-6 pb-5 border-b border-slate-100">
            <div className="flex items-center justify-between mb-2.5">
              <span className="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                <KeyRound className="w-3.5 h-3.5 text-blue-900" />
                Pilihan Akun Uji Coba Cepat
              </span>
              <span className="text-[10px] text-slate-500 font-mono bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                SistLabUM2026!UAT
              </span>
            </div>
            <div className="grid grid-cols-2 gap-2">
              {testPresets.map((preset) => {
                const Icon = preset.icon;
                const isSelected = identifier === preset.identifier;
                return (
                  <button
                    key={preset.label}
                    type="button"
                    onClick={() => handleApplyPreset(preset.identifier, preset.password)}
                    title={`ID: ${preset.identifier} | Pass: ${preset.password}`}
                    className={`flex items-center gap-2 p-2 rounded-xl border text-left transition-all cursor-pointer ${
                      isSelected
                        ? 'border-blue-900 bg-blue-50/70 text-blue-950 font-semibold shadow-xs'
                        : 'border-slate-200 bg-slate-50 hover:bg-slate-100/80 text-slate-700'
                    }`}
                  >
                    <div
                      className={`w-7 h-7 rounded-lg flex items-center justify-center shrink-0 ${
                        isSelected ? 'bg-blue-900 text-white' : 'bg-white border border-slate-200 text-slate-600'
                      }`}
                    >
                      <Icon className="w-3.5 h-3.5" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="text-[11px] font-bold truncate leading-tight">{preset.role}</p>
                      <p className="text-[10px] text-slate-500 truncate leading-tight">{preset.badge}</p>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Form */}
          <form className="space-y-4" onSubmit={handleSubmit}>
            {errorMessage && (
              <div className="p-3 rounded-xl bg-rose-50 border border-rose-200 flex items-start gap-2.5 text-xs text-rose-800">
                <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-rose-600" />
                <div className="flex-1 font-medium leading-relaxed">{errorMessage}</div>
              </div>
            )}

            <div>
              <label htmlFor="identifier" className="block text-xs font-semibold text-slate-700 mb-1.5">
                Identitas Akun (Username / NIS / Email)
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <User className="w-4 h-4" />
                </div>
                <input
                  id="identifier"
                  type="text"
                  autoComplete="username"
                  required
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                  placeholder="Contoh: siswa.lab.001 atau NIS"
                  className="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-900/20 focus:border-blue-900 transition-all font-mono"
                />
              </div>
            </div>

            <div>
              <label htmlFor="password" className="block text-xs font-semibold text-slate-700 mb-1.5">
                Kata Sandi
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <Lock className="w-4 h-4" />
                </div>
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Masukkan kata sandi"
                  className="w-full pl-9 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-900/20 focus:border-blue-900 transition-all font-mono"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
                  aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <div className="pt-2">
              <button
                type="submit"
                disabled={isLoading}
                className="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-semibold text-white bg-blue-900 hover:bg-blue-950 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-blue-900 disabled:opacity-60 transition-all shadow-xs cursor-pointer"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>Memverifikasi Akun...</span>
                  </>
                ) : (
                  <>
                    <span>Masuk ke Portal</span>
                    <ArrowRight className="w-4 h-4" />
                  </>
                )}
              </button>
            </div>
          </form>

          <div className="mt-6 pt-5 border-t border-slate-100 text-center">
            <p className="text-[11px] text-slate-500">
              SISTLABUM Portal • SMP Laboratorium Universitas Negeri Malang
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-slate-100 flex items-center justify-center">
          <Loader2 className="w-8 h-8 text-blue-900 animate-spin" />
        </div>
      }
    >
      <LoginForm />
    </Suspense>
  );
}
