{{--
|--------------------------------------------------------------------------
| Komponen Sidebar Item (sidebar-item.blade.php)
|--------------------------------------------------------------------------
| Partial reusable untuk satu item menu di sidebar.
| Di-@include dari sidebar.blade.php dengan variabel:
|   $label  — teks menu
|   $route  — nama route (null = belum tersedia)
|   $aktif  — array nama route untuk pencocokan halaman aktif
|   $ikon   — SVG <path> d attribute
|   $segera — bool, tampilkan badge "Segera" (opsional)
|   $badge  — int, tampilkan badge angka (opsional)
|--------------------------------------------------------------------------
--}}

@php
    $tersedia = isset($route) && $route !== null;
    $isAktif  = $tersedia && request()->routeIs(...($aktif ?? []));
    $href     = $tersedia ? route($route) : '#';
@endphp

<a href="{{ $href }}"
   @class([
       'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
       'bg-white/15 text-white'                                        => $isAktif,
       'text-white/70 hover:bg-white/10 hover:text-white'              => ! $isAktif && $tersedia,
       'cursor-not-allowed text-white/40 hover:bg-transparent'         => ! $tersedia,
   ])
   @if(! $tersedia) onclick="return false;" @endif
   @if(isset($segera) && $segera) title="Fitur ini akan segera tersedia" @endif
>
    {{-- Ikon --}}
    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}" />
    </svg>

    {{-- Label --}}
    <span class="truncate">{{ $label }}</span>

    {{-- Badge "Segera" (coming soon) --}}
    @if (isset($segera) && $segera)
        <span class="ml-auto whitespace-nowrap rounded-full bg-amber-500/20 px-2 py-0.5
                     text-[10px] font-semibold leading-none text-amber-300">
            Segera
        </span>
    @endif

    {{-- Badge angka (notifikasi, dll.) --}}
    @if (isset($badge) && $badge > 0)
        <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full
                     bg-red-500 px-1.5 text-[11px] font-bold leading-none text-white">
            {{ $badge }}
        </span>
    @endif
</a>
