{{--
|--------------------------------------------------------------------------
| Layout Utama Aplikasi (app.blade.php)
|--------------------------------------------------------------------------
| Layout master yang digunakan oleh semua halaman terautentikasi.
| Menggunakan pola wrapper: sidebar tetap di kiri, konten utama di kanan.
| Navbar ditampilkan di atas area konten (bukan di atas sidebar).
|
| Responsive:
|   - Desktop (lg+): sidebar tetap di kiri, konten di kanan
|   - Mobile (<lg):   sidebar tersembunyi, ditampilkan via hamburger
|
| Slot yang tersedia:
|   @section('judul')   - judul tab browser
|   @section('konten')  - konten halaman utama
|--------------------------------------------------------------------------
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('judul', 'Sistem Pelaporan Kesalahan Pengobatan')</title>

    {{-- Cegah mesin pencari mengindeks halaman internal --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- Favicon: menggunakan logo Rumah Sakit --}}
    <link rel="icon" type="image/jpeg" href="{{ asset('images/icon-rmh_sakit.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/icon-rmh_sakit.jpg') }}">

    {{-- Aset Vite: Tailwind CSS + JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- Latar belakang halaman: abu-abu sangat terang agar mata nyaman --}}
{{-- x-data="idleTimer()" : Komponen Alpine untuk auto-logout idle (5 menit tanpa aktivitas). --}}
{{-- Event listener dipasang di sini agar menangkap aktivitas dari seluruh halaman.         --}}
<body
    x-data="idleTimer()"
    @mousemove="updateActivity"
    @keydown="updateActivity"
    @scroll.window="updateActivity"
    @click="updateActivity"
    class="h-full bg-slate-50 font-sans text-slate-700 antialiased">

    {{-- Wrapper utama: sidebar + area konten --}}
    <div class="flex h-full min-h-screen">

        {{-- Overlay backdrop (mobile only) --}}
        <div id="sidebar-overlay"
             class="fixed inset-0 z-30 hidden bg-black/50 transition-opacity lg:hidden"
             onclick="toggleSidebar()"></div>

        {{-- Sidebar - komponen terpisah untuk modularitas --}}
        <div id="sidebar-container"
             class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transition-transform duration-300
                    lg:static lg:z-auto lg:translate-x-0 lg:transition-none">
            @include('layouts.sidebar')
        </div>

        {{-- Area konten utama (navbar + halaman) --}}
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">

            {{-- Navbar atas --}}
            @include('layouts.navbar')

            {{-- Konten halaman yang bisa di-scroll --}}
            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                @yield('konten')
            </main>

            {{-- Footer global -- konsisten di seluruh halaman --}}
            @include('layouts.footer')
        </div>
    </div>

    {{-- Script toggle sidebar untuk mobile --}}
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar-container');
            const overlay = document.getElementById('sidebar-overlay');
            const isOpen  = !sidebar.classList.contains('-translate-x-full');

            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            } else {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }
    </script>

    {{-- ================================================================
         Auto-Logout on Idle (Alpine.js - 5 menit tanpa aktivitas)

         ARSITEKTUR:
           - updateActivity() hanya meng-update variabel lastActivity.
             Throttle manual (500 ms) mencegah eksekusi ribuan kali/detik
             akibat mousemove, sehingga 0% lag pada UI.
           - checkIdle() berjalan via setInterval setiap 10 detik.
             Metode ini jauh lebih efisien daripada mengevaluasi kondisi
             pada setiap event aktivitas.
           - Saat idle terdeteksi: POST /logout-idle (+ CSRF token) dikirim
             via Fetch, lalu window.location.href diarahkan ke login.

         ISOLASI: Script ini hanya ada di app.blade.php (layout
         terautentikasi). guest.blade.php tidak terpengaruh.
         ================================================================ --}}
    @auth
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('idleTimer', () => ({
                lastActivity  : Date.now(),
                idleLimit     : 10 * 60 * 1000, // 600.000 ms = 5 menit
                _lastThrottle : 0,
                _intervalId   : null,

                init() {
                    // Pengecekan setiap 10 detik - jauh lebih hemat daripada setiap event.
                    this._intervalId = setInterval(() => this.checkIdle(), 10_000);
                },

                destroy() {
                    // Bersihkan interval saat komponen dihancurkan (SPA navigation, dll.)
                    if (this._intervalId) clearInterval(this._intervalId);
                },

                updateActivity() {
                    // Throttle manual: perbarui lastActivity maks. 1x per 500ms.
                    // Mencegah eksekusi berat akibat event mousemove yang sangat sering.
                    const now = Date.now();
                    if (now - this._lastThrottle < 500) return;
                    this._lastThrottle = now;
                    this.lastActivity  = now;
                },

                checkIdle() {
                    if ((Date.now() - this.lastActivity) < this.idleLimit) return;

                    // Hentikan interval agar checkIdle tidak terpanggil dua kali.
                    clearInterval(this._intervalId);

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                    fetch('{{ route("logout.idle") }}', {
                        method  : 'POST',
                        headers : {
                            'Content-Type' : 'application/json',
                            'Accept'       : 'application/json',
                            'X-CSRF-TOKEN' : csrfToken,
                        },
                    })
                    .catch(() => {
                        // Abaikan error jaringan - logout tetap dilakukan di sisi klien.
                    })
                    .finally(() => {
                        // Paksa redirect ke halaman login tanpa menunggu respons server.
                        window.location.href = '{{ route("login") }}';
                    });
                },
            }));
        });
    </script>
    @endauth

    {{-- ================================================================
         Komponen UI Global: Toast & Confirm Modal
         Dipasang di sini agar tersedia di semua halaman terautentikasi.
         ================================================================ --}}
    <x-toast />
    <x-confirm-modal />

    {{-- ================================================================
         Alpine.data - toastManager
         Mengelola stack toast notifikasi (sukses, error, warning, info).
         ================================================================ --}}
    <script>
        document.addEventListener('alpine:init', () => {

            // ── Toast Manager ─────────────────────────────────────────
            Alpine.data('toastManager', () => ({
                toasts: [],
                _id: 0,

                _cfg: {
                    sukses: {
                        title: 'Berhasil',
                        wrapperClass: 'bg-emerald-50 border-emerald-200',
                        iconBgClass:  'bg-emerald-100',
                        iconClass:    'text-emerald-600',
                        iconPath:     'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                        titleClass:   'text-emerald-800',
                        textClass:    'text-emerald-600',
                        closeClass:   'text-emerald-400 hover:text-emerald-600 hover:bg-emerald-100',
                        barClass:     'bg-emerald-400',
                    },
                    error: {
                        title: 'Terjadi Kesalahan',
                        wrapperClass: 'bg-red-50 border-red-200',
                        iconBgClass:  'bg-red-100',
                        iconClass:    'text-red-600',
                        iconPath:     'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
                        titleClass:   'text-red-800',
                        textClass:    'text-red-600',
                        closeClass:   'text-red-400 hover:text-red-600 hover:bg-red-100',
                        barClass:     'bg-red-400',
                    },
                    warning: {
                        title: 'Perhatian',
                        wrapperClass: 'bg-amber-50 border-amber-200',
                        iconBgClass:  'bg-amber-100',
                        iconClass:    'text-amber-600',
                        iconPath:     'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
                        titleClass:   'text-amber-800',
                        textClass:    'text-amber-700',
                        closeClass:   'text-amber-400 hover:text-amber-600 hover:bg-amber-100',
                        barClass:     'bg-amber-400',
                    },
                    info: {
                        title: 'Informasi',
                        wrapperClass: 'bg-blue-50 border-blue-200',
                        iconBgClass:  'bg-blue-100',
                        iconClass:    'text-blue-600',
                        iconPath:     'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
                        titleClass:   'text-blue-800',
                        textClass:    'text-blue-600',
                        closeClass:   'text-blue-400 hover:text-blue-600 hover:bg-blue-100',
                        barClass:     'bg-blue-400',
                    },
                },

                add({ type = 'info', message = '', duration = 4500 }) {
                    const id  = ++this._id;
                    const cfg = this._cfg[type] ?? this._cfg.info;
                    this.toasts.push({ id, message, duration, visible: true, progress: 100, ...cfg });

                    // Setelah elemen dirender pada 100%, animasikan ke 0% via CSS transition.
                    this.$nextTick(() => {
                        setTimeout(() => {
                            const toast = this.toasts.find(t => t.id === id);
                            if (toast) toast.progress = 0;
                        }, 30);
                    });

                    setTimeout(() => this.dismiss(id), duration);
                },

                dismiss(id) {
                    const toast = this.toasts.find(t => t.id === id);
                    if (toast) toast.visible = false;
                    setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 300);
                },
            }));

            // ── Confirm Modal ────────────────────────────────────────
            Alpine.data('confirmModal', () => ({
                isOpen:       false,
                message:      '',
                confirmLabel: 'Ya, Lanjutkan',
                isDestructive: false,
                _onConfirm:   null,

                open({ message, confirmLabel = 'Ya, Lanjutkan', destructive = false, onConfirm = null }) {
                    this.message      = message;
                    this.confirmLabel = confirmLabel;
                    this.isDestructive = destructive;
                    this._onConfirm   = onConfirm;
                    this.isOpen       = true;
                    this.$nextTick(() => this.$refs.confirmBtn?.focus());
                },

                confirm() {
                    this.isOpen = false;
                    const cb = this._onConfirm;
                    this._onConfirm = null;
                    if (typeof cb === 'function') setTimeout(cb, 150);
                },

                cancel() {
                    this.isOpen = false;
                    this._onConfirm = null;
                },
            }));
        });

        // ── Intercept semua form dengan atribut data-confirm ────────────
        // Menggantikan native confirm() dialog dengan x-confirm-modal.
        document.addEventListener('submit', function (e) {
            const form    = e.target;
            const message = form.dataset.confirm;
            if (!message) return;

            e.preventDefault();
            e.stopImmediatePropagation();

            window.dispatchEvent(new CustomEvent('confirm-modal', {
                detail: {
                    message,
                    confirmLabel: form.dataset.confirmLabel  ?? 'Ya, Lanjutkan',
                    destructive:  form.dataset.confirmDestructive === 'true',
                    onConfirm() {
                        // Hapus data-confirm agar tidak masuk loop infinit.
                        delete form.dataset.confirm;
                        form.submit();
                    },
                },
            }));
        }, true); // capture phase - sebelum handler lain
    </script>

    {{-- Script yang di-push oleh halaman/komponen individual (mis. Chart.js) --}}
    @stack('scripts')
</body>
</html>
