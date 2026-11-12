<!DOCTYPE html>
<html class="h-full bg-slate-50" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" name="viewport" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ __('Iniciar Sesión') }} | {{ config('app.name', 'MyBarber') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.pwa')

    <style data-purpose="ambient-lighting">
        .glow-brand-radial {
            background-color: #f8fafc;
            background-image: radial-gradient(ellipse 120% 70% at 50% 8%, rgba(192, 28, 21, 0.055) 0%, rgba(192, 28, 21, 0.02) 45%, rgba(248, 250, 252, 0) 78%);
        }

        .brand-glow-box {
            box-shadow: 0 0 30px -8px rgba(192, 28, 21, 0.30);
        }

        .brand-btn-shadow {
            box-shadow: 0 8px 24px -6px rgba(192, 28, 21, 0.45);
        }
    </style>
</head>

<body
    class="h-full min-h-screen bg-slate-50 glow-brand-radial text-slate-800 font-sans antialiased selection:bg-brand-500/20 selection:text-brand-700"
    style="font-family: 'Inter', system-ui, -apple-system, sans-serif;">
    <!-- BEGIN: MainContainer -->
    <main
        class="min-h-full flex flex-col justify-between relative px-4 py-6 sm:px-6 max-w-[430px] mx-auto"
        data-purpose="mobile-viewport-wrapper">
        <!-- Top Decorative Subtle Spotlight -->
        <div aria-hidden="true"
            class="pointer-events-none absolute -top-24 left-1/2 -translate-x-1/2 w-80 h-80 rounded-full bg-brand-500/5 blur-3xl"></div>

        <!-- BEGIN: HeaderSection -->
        <header class="w-full pt-4 pb-2 flex flex-col items-center text-center relative z-10" data-purpose="brand-header">
            <!-- Role Badge / Environment Status -->
            <div
                class="mb-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white border border-brand-500/25 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
                <span class="text-[11px] font-semibold tracking-wider uppercase text-brand-600">Acceso Dueño &amp; Equipo</span>
            </div>

            <!-- Brand Logo Container -->
            <div
                class="relative mb-3 flex items-center justify-center p-1 rounded-2xl bg-white brand-glow-box">
                <div
                    class="w-24 h-24 bg-white rounded-[14px] flex items-center justify-center overflow-hidden p-1.5 border border-brand-500/20">
                    <img alt="MyBarber Logo" class="w-full h-full object-contain" height="96"
                        src="{{ asset('mybarberlogo.png') }}" width="96" />
                </div>
            </div>

            <!-- Studio Title & Subtitle -->
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                MY<span class="text-brand-600 font-black">BARBER</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1 font-medium tracking-wide">
                Plataforma de Control &amp; Gestión Integral
            </p>
        </header>
        <!-- END: HeaderSection -->

        <!-- BEGIN: LoginFormSection -->
        <section class="w-full my-auto py-2 z-10" data-purpose="login-card-container">
            <div
                class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xl shadow-slate-200/60">

                @if (session('status'))
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

                <form action="{{ route('login') }}" class="space-y-4" data-purpose="authentication-form" method="POST">
                    @csrf

                    <!-- Email Input Group -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600" for="email">
                            Correo Electrónico
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="h-5 w-5 text-brand-500" fill="currentColor" viewBox="0 0 20 20"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.161V6a2 2 0 00-2-2H3z"></path>
                                    <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z"></path>
                                </svg>
                            </div>
                            <input autocomplete="email"
                                class="block w-full pl-10 pr-3.5 py-3.5 text-sm rounded-xl bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-colors @error('email') border-red-400 @enderror"
                                id="email" name="email" placeholder="barber@mybarber.com" required autofocus
                                value="{{ old('email') }}" type="email" />
                        </div>
                        @error('email')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Input Group with Toggle Visibility -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600" for="password">
                            Contraseña
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="h-5 w-5 text-brand-500" fill="currentColor" viewBox="0 0 20 20"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path clip-rule="evenodd"
                                        d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"
                                        fill-rule="evenodd"></path>
                                </svg>
                            </div>
                            <input autocomplete="current-password"
                                class="block w-full pl-10 pr-11 py-3.5 text-sm rounded-xl bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-colors @error('password') border-red-400 @enderror"
                                id="password" name="password" placeholder="••••••••••••" required type="password" />
                            <button aria-label="Mostrar u ocultar contraseña"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-brand-500 focus:outline-none"
                                id="togglePassword" type="button">
                                <svg class="h-5 w-5" fill="none" id="eyeIcon" stroke="currentColor" stroke-width="1.5"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                                <svg class="h-5 w-5 hidden" fill="none" id="eyeSlashIcon" stroke="currentColor"
                                    stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Options Row: Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input
                                class="w-4 h-4 rounded border-slate-300 bg-white text-brand-500 focus:ring-brand-500 focus:ring-offset-0 focus:ring-1"
                                id="remember_me" name="remember" type="checkbox" />
                            <span class="text-xs font-medium text-slate-600 select-none">Recordarme</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a class="text-xs font-semibold text-brand-600 hover:text-brand-500 transition-colors"
                                href="{{ route('password.request') }}">
                                ¿Olvidaste tu contraseña?
                            </a>
                        @endif
                    </div>

                    <!-- Primary Submit CTA Button (50px+ min touch target height) -->
                    <button
                        class="w-full min-h-[50px] py-3.5 px-4 mt-2 flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-500 via-brand-500 to-brand-600 hover:from-brand-400 hover:to-brand-500 active:scale-[0.99] text-white font-black text-sm tracking-wider uppercase transition-all brand-btn-shadow"
                        type="submit">
                        <span>Iniciar Sesión</span>
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path clip-rule="evenodd"
                                d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z"
                                fill-rule="evenodd"></path>
                        </svg>
                    </button>

                    <!-- Divider -->
                    <div class="relative flex items-center justify-center pt-2 pb-1">
                        <div class="w-full border-t border-slate-200"></div>
                        <span
                            class="absolute px-3 bg-white text-[11px] font-medium text-slate-400 uppercase tracking-widest">
                            o continúa con
                        </span>
                    </div>

                    <!-- BEGIN: Google OAuth Access -->
                    @php($googleUrl = (Route::has('auth.google') && config('services.google.client_id')) ? route('auth.google') : null)
                    <a href="{{ $googleUrl ?? '#' }}"
                        @unless ($googleUrl) aria-disabled="true" onclick="return false;" @endunless
                        class="w-full min-h-[46px] py-2.5 px-4 flex items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 active:scale-[0.99] text-slate-700 font-semibold text-sm tracking-wide transition-all @unless ($googleUrl) cursor-not-allowed opacity-60 @endunless"
                        data-purpose="google-oauth-action">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill="#4285F4"
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z" />
                            <path fill="#34A853"
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0012 23z" />
                            <path fill="#FBBC05"
                                d="M5.84 14.09a6.6 6.6 0 010-4.18V7.07H2.18a11 11 0 000 9.86l3.66-2.84z" />
                            <path fill="#EA4335"
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1a11 11 0 00-9.82 6.07l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z" />
                        </svg>
                        <span>Continuar con Google</span>
                    </a>
                    <!-- END: Google OAuth Access -->
                </form>
            </div>
        </section>
        <!-- END: LoginFormSection -->

        <!-- BEGIN: FooterSection -->
        <footer class="w-full pb-3 pt-2 text-center text-xs text-slate-500 space-y-1.5 relative z-10"
            data-purpose="app-footer">
            <div>
                <span>¿Necesitas asistencia técnica? </span>
                <a class="text-brand-600 hover:text-brand-500 font-medium underline underline-offset-2"
                   href="mailto:juandonquis07@gmail.com">
                    juandonquis07@gmail.com
                </a>
            </div>
            <div class="text-[11px] text-slate-400 font-medium">
                v4.0 · MyBarber Management
            </div>
        </footer>
        <!-- END: FooterSection -->
    </main>
    <!-- END: MainContainer -->

    <!-- Interactive script for password visibility toggle -->
    <script data-purpose="password-toggle">
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeSlashIcon = document.getElementById('eyeSlashIcon');

            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', () => {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    eyeIcon.classList.toggle('hidden', isPassword);
                    eyeSlashIcon.classList.toggle('hidden', !isPassword);
                });
            }
        });
    </script>
</body>
</html>
