{{--
|--------------------------------------------------------------------------
| Komponen Pagination (components/pagination.blade.php)
|--------------------------------------------------------------------------
| Props:
|   $paginator          — instance LengthAwarePaginator (wajib)
|   $pilihanPerHalaman  — array opsi per-halaman (opsional, default dari Paginasi::PILIHAN)
|
| Penggunaan:
|   <x-pagination :paginator="$daftarX" />
|--------------------------------------------------------------------------
--}}

@use('App\Support\Paginasi')

@props([
    'paginator',
    'pilihanPerHalaman' => Paginasi::PILIHAN,
])

@php
    $perHalamanAktif = Paginasi::perHalaman();

    // Sertakan semua query-string aktif (filter, per_halaman, dll.) ke semua URL paginator
    $paginator   = $paginator->withQueryString();
    $currentPage = $paginator->currentPage();
    $lastPage    = $paginator->lastPage();

    /**
     * Bangun daftar halaman dengan ellipsis.
     * Selalu tampilkan: halaman 1, lastPage, dan currentPage ± 1.
     * Celah tepat 2 → tampilkan halaman perantara; celah > 2 → tampilkan '...'
     */
    $window = collect(
        array_unique(array_filter([
            1,
            $currentPage - 1 > 1 ? $currentPage - 1 : null,
            $currentPage,
            $currentPage + 1 < $lastPage ? $currentPage + 1 : null,
            $lastPage > 1 ? $lastPage : null,
        ]))
    )->sort()->values()->all();

    $rendered = [];
    $prev     = null;
    foreach ($window as $page) {
        if ($prev !== null) {
            $gap = $page - $prev;
            if ($gap === 2) {
                $rendered[] = $prev + 1;
            } elseif ($gap > 2) {
                $rendered[] = '...';
            }
        }
        $rendered[] = $page;
        $prev = $page;
    }
@endphp

<div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">

    {{-- Keterangan jumlah data --}}
    <p class="text-sm text-slate-400">
        @if ($paginator->total() > 0)
            Menampilkan
            <span class="font-medium text-slate-600">{{ $paginator->firstItem() }}</span>
            –
            <span class="font-medium text-slate-600">{{ $paginator->lastItem() }}</span>
            dari
            <span class="font-medium text-slate-600">{{ number_format($paginator->total()) }}</span>
            data
        @else
            Tidak ada data yang ditampilkan
        @endif
    </p>

    {{-- Kontrol: selector per halaman + navigasi halaman --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- Selector jumlah per halaman --}}
        <div class="flex items-center gap-2">
            <label class="whitespace-nowrap text-xs text-slate-400">Per halaman</label>
            <select
                onchange="const u=new URL(window.location.href);u.searchParams.set('per_halaman',this.value);u.searchParams.delete('page');window.location.href=u.toString()"
                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium
                       text-slate-600 shadow-sm focus:border-brand focus:outline-none focus:ring-1
                       focus:ring-brand"
            >
                @foreach ($pilihanPerHalaman as $pilihan)
                    <option value="{{ $pilihan }}" @selected($perHalamanAktif === $pilihan)>
                        {{ $pilihan }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Navigasi halaman (custom — tanpa teks bawaan Laravel) --}}
        @if ($paginator->hasPages())
            <nav class="flex items-center gap-1" aria-label="Navigasi halaman">

                {{-- Tombol Sebelumnya --}}
                @if ($paginator->onFirstPage())
                    <span class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-lg
                                 border border-slate-200 text-slate-300 text-sm select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200
                              text-slate-500 text-sm transition hover:border-brand hover:text-brand">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                @endif

                {{-- Nomor halaman --}}
                @foreach ($rendered as $item)
                    @if ($item === '...')
                        <span class="inline-flex h-8 w-8 items-center justify-center text-xs text-slate-400 select-none">
                            &hellip;
                        </span>
                    @elseif ($item === $currentPage)
                        <span class="inline-flex h-8 min-w-[2rem] px-1.5 items-center justify-center rounded-lg
                                     bg-brand text-sm font-semibold text-white select-none">
                            {{ $item }}
                        </span>
                    @else
                        <a href="{{ $paginator->url($item) }}"
                           class="inline-flex h-8 min-w-[2rem] px-1.5 items-center justify-center rounded-lg
                                  border border-slate-200 text-sm text-slate-600 transition
                                  hover:border-brand hover:text-brand">
                            {{ $item }}
                        </a>
                    @endif
                @endforeach

                {{-- Tombol Berikutnya --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200
                              text-slate-500 text-sm transition hover:border-brand hover:text-brand">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @else
                    <span class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-lg
                                 border border-slate-200 text-slate-300 text-sm select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                @endif

            </nav>
        @endif
    </div>

</div>
