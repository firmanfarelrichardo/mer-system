{{--
|--------------------------------------------------------------------------
| Navbar Atas (navbar.blade.php)
|--------------------------------------------------------------------------
| Komponen navigasi atas yang menampilkan:
|   - Tombol hamburger (mobile)
|   - Dropdown "Ganti Peran" (eksklusif akun peneliti)
|   - Nama pengguna dan peran / unit kerja aktif
|   - Avatar inisial
|
| Variabel global: $isPeneliti (disuntikkan oleh View::composer('*') di
| AppServiceProvider). Peran aktif dibaca langsung via peranAktif().
|--------------------------------------------------------------------------
--}}

@php
    /** @var \App\Models\Pengguna $pengguna */
    $pengguna    = auth()->user();
    $namaLengkap = $pengguna->nama_lengkap ?? 'Pengguna';

    // ── Peran aktif (session-aware untuk peneliti) ────────────────────────
    // peranAktif() mengembalikan session('active_role') untuk peneliti, atau
    // peran DB pertama untuk pengguna biasa.
    $peranAktifNama    = $pengguna->peranAktif();
    $labelPeranDisplay = \App\Models\Peran::PETA_LABEL_DISPLAY[$peranAktifNama] ?? $peranAktifNama;

    // Untuk pengguna biasa: tambahkan nama unit kerja jika ada.
    $namaUnit   = $pengguna->unitKerja?->nama_unit ?? null;
    $labelPeran = ($isPeneliti && session()->has('active_role'))
        ? "Simulasi: {$labelPeranDisplay}"
        : ($namaUnit ? "{$labelPeranDisplay} - {$namaUnit}" : $labelPeranDisplay);

    // Inisial untuk avatar lingkaran (maks 2 huruf pertama tiap kata).
    $inisial = collect(explode(' ', $namaLengkap))
        ->map(fn (string $kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-6">

    {{-- Sisi kiri: Hamburger --}}
    <div class="flex items-center gap-3">
        <button type="button"
                class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                onclick="toggleSidebar()"
                aria-label="Toggle menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
        </button>
    </div>

    {{-- Sisi kanan: Dropdown "Ganti Peran" (peneliti only) + Info pengguna + Avatar --}}
    <div class="flex items-center gap-3">

        {{-- ============================================================
             Dropdown "Ganti Peran" — EKSKLUSIF untuk akun peneliti.
             Menggunakan Alpine.js untuk toggle visibility.
             Setiap opsi dikirim via form POST yang terpisah agar CSRF
             terjaga dan tidak memerlukan JavaScript tambahan.
             ============================================================ --}}
        @if ($isPeneliti)
        <div x-data="{ terbuka: false }" class="relative" @keydown.escape.window="terbuka = false">

            {{-- Tombol pemicu --}}
            <button @click="terbuka = !terbuka"
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50
                           px-3 py-1.5 text-xs font-semibold text-indigo-700 transition
                           hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    aria-haspopup="true"
                    :aria-expanded="terbuka">
                {{-- Ikon mata --}}
                <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5
                             c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49
                             16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                Lihat Sebagai
                <svg class="h-3.5 w-3.5 shrink-0 transition-transform duration-150"
                     :class="{ 'rotate-180': terbuka }"
                     xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>

            {{-- Panel dropdown --}}
            <div x-show="terbuka"
                 @click.outside="terbuka = false"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 top-full z-50 mt-2 w-56 origin-top-right rounded-xl
                        border border-slate-200 bg-white py-1 shadow-lg"
                 style="display: none;"
                 role="menu">

                {{-- Header informasi mode aktif --}}
                <div class="border-b border-slate-100 px-4 py-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                        Mode aktif
                    </p>
                    <p class="mt-0.5 text-sm font-semibold text-indigo-700">
                        {{ $labelPeranDisplay }}
                    </p>
                </div>

                {{-- Opsi peran — masing-masing form POST terpisah --}}
                @php
                    $opsiPeran = [
                        \App\Models\Peran::NAKES           => 'Tenaga Medis/Tenaga Kesehatan',
                        \App\Models\Peran::KEPALA_RUANGAN  => 'Kepala Ruangan',
                        \App\Models\Peran::KOMITE          => 'Komite',
                        \App\Models\Peran::ADMIN           => 'Admin',
                        \App\Models\Peran::DIREKTUR        => 'Direktur',
                        \App\Models\Peran::PENELITI        => 'Kembali ke Peneliti',
                    ];
                @endphp

                @foreach ($opsiPeran as $nilaiPeran => $labelOpsi)
                    @php
                        $isAktif    = $peranAktifNama === $nilaiPeran;
                        $isSeparator = $nilaiPeran === \App\Models\Peran::PENELITI;
                    @endphp

                    {{-- Garis pemisah sebelum opsi "Kembali ke Peneliti" --}}
                    @if ($isSeparator)
                        <div class="my-1 border-t border-slate-100"></div>
                    @endif

                    <form method="POST" action="{{ route('peneliti.ganti-peran') }}" class="w-full">
                        @csrf
                        <input type="hidden" name="peran" value="{{ $nilaiPeran }}">
                        <button type="submit"
                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm
                                       transition-colors hover:bg-slate-50
                                       {{ $isAktif ? 'font-semibold text-indigo-700' : 'font-normal text-slate-700' }}"
                                role="menuitem">
                            {{-- Indikator opsi aktif --}}
                            @if ($isAktif)
                                <svg class="h-4 w-4 shrink-0 text-indigo-600" xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                          d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                          clip-rule="evenodd" />
                                </svg>
                            @else
                                <span class="h-4 w-4 shrink-0"></span>
                            @endif
                            {{ $labelOpsi }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
        @endif
        {{-- /Dropdown Ganti Peran --}}

        {{-- Info teks pengguna --}}
        <div class="hidden text-right sm:block">
            <p class="text-sm font-semibold text-slate-800">{{ $namaLengkap }}</p>
            <p class="text-xs {{ $isPeneliti && session()->has('active_role') ? 'font-semibold text-indigo-600' : 'text-slate-400' }}">
                {{ $labelPeran }}
            </p>
        </div>

        {{-- Avatar inisial dalam lingkaran --}}
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand text-sm font-bold text-white"
             title="{{ $namaLengkap }}">
            {{ $inisial }}
        </div>
    </div>
</header>
