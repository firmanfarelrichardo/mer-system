{{--
|--------------------------------------------------------------------------
| Komponen: x-toast
|--------------------------------------------------------------------------
| Toast notification global.
|
| Otomatis menampilkan flash session dari server saat halaman dimuat:
|   - session('sukses')  → emerald (berhasil)
|   - session('error')   → merah   (kesalahan)
|   - session('galat')   → merah   (alias Bahasa Indonesia untuk error)
|   - session('warning') → amber   (peringatan)
|   - session('info')    → biru    (informasi)
|
| Penggunaan dari JavaScript (semua role):
|   window.dispatchEvent(new CustomEvent('toast', {
|       detail: { type: 'sukses'|'error'|'warning'|'info', message: '...' }
|   }));
|
| Komponen ini di-include sekali di layouts/app.blade.php.
| DRY: tidak perlu @if (session(...)) di setiap view lagi.
|--------------------------------------------------------------------------
--}}

<div id="toast-container"
     x-data="toastManager()"
     @toast.window="add($event.detail)"
     class="pointer-events-none fixed right-4 top-4 z-[9999] flex flex-col items-end gap-2 sm:right-6 sm:top-6"
     aria-live="polite"
     aria-atomic="false">

    <template x-for="toast in toasts" :key="toast.id">
        <div class="pointer-events-auto relative flex w-80 max-w-[calc(100vw-2rem)] items-start gap-3 overflow-hidden rounded-2xl border p-4 shadow-xl"
             :class="toast.wrapperClass"
             x-show="toast.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-6 scale-95"
             x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 translate-x-6 scale-95"
             role="alert">

            {{-- Ikon --}}
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="toast.iconBgClass">
                <svg class="h-4.5 w-4.5" :class="toast.iconClass"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="toast.iconPath"/>
                </svg>
            </div>

            {{-- Teks --}}
            <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-sm font-semibold leading-snug" :class="toast.titleClass" x-text="toast.title"></p>
                <p class="mt-0.5 text-xs leading-relaxed" :class="toast.textClass" x-text="toast.message"></p>
            </div>

            {{-- Tombol tutup --}}
            <button @click="dismiss(toast.id)"
                    class="mt-0.5 shrink-0 rounded-lg p-1 transition-colors"
                    :class="toast.closeClass"
                    aria-label="Tutup notifikasi">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Progress bar auto-dismiss --}}
            <div class="absolute bottom-0 left-0 h-0.5 rounded-full transition-[width] ease-linear"
                 :class="toast.barClass"
                 :style="`width: ${toast.progress}%; transition-duration: ${toast.duration}ms`"></div>
        </div>
    </template>
</div>

{{-- Flash server → toast (dijalankan setelah Alpine siap) --}}
@php
    $toastFlash = [
        'sukses'  => 'sukses',
        'error'   => 'error',
        'galat'   => 'error',
        'warning' => 'warning',
        'info'    => 'info',
    ];
@endphp
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @foreach ($toastFlash as $key => $type)
            @if (session($key))
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: '{{ $type }}', message: @json(session($key)) }
                }));
            @endif
        @endforeach
    });
</script>
