{{--
|--------------------------------------------------------------------------
| Navbar Atas (navbar.blade.php)
|--------------------------------------------------------------------------
| Komponen navigasi atas yang menampilkan:
|   - Nama sistem ("Sistem MER")
|   - Nama pengguna dan peran + unit kerja
|   - Ikon profil minimalis (inisial dalam lingkaran)
|
| Dirancang sebagai strip horizontal di atas area konten utama.
|--------------------------------------------------------------------------
--}}

{{-- Ambil data pengguna saat ini untuk ditampilkan di navbar --}}
@php
    $pengguna    = auth()->user();
    $namaLengkap = $pengguna->nama_lengkap ?? 'Pengguna';
    $daftarPeran = $pengguna->daftarPeran();
    $peranUtama  = $daftarPeran[0] ?? '—';

    // Ambil nama unit kerja jika ada relasi, fallback ke strip
    $namaUnit = $pengguna->unitKerja?->nama_unit ?? null;

    // Gabungkan peran + unit untuk tampilan ringkas, mis. "Perawat - ICU"
    $labelPeran = $namaUnit ? "{$peranUtama} - {$namaUnit}" : $peranUtama;

    // Inisial untuk avatar lingkaran (ambil huruf pertama tiap kata, maks 2)
    $inisial = collect(explode(' ', $namaLengkap))
        ->map(fn (string $kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-6">

    {{-- Sisi kiri: Hamburger + Nama sistem --}}
    <div class="flex items-center gap-3">
        {{-- Tombol hamburger — hanya tampil di mobile --}}
        <button type="button"
                class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                onclick="toggleSidebar()"
                aria-label="Toggle menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
        </button>

        {{-- Ikon rumah sakit kecil (SVG inline agar tidak perlu library ikon) --}}
        <svg class="hidden h-6 w-6 text-brand sm:block" xmlns="http://www.w3.org/2000/svg" fill="none"
             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332
                     A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21" />
        </svg>
        <span class="text-lg font-bold text-brand">Sistem MER</span>
    </div>

    {{-- Sisi kanan: Info pengguna + avatar inisial --}}
    <div class="flex items-center gap-4">

        {{-- Info teks pengguna --}}
        <div class="hidden text-right sm:block">
            <p class="text-sm font-semibold text-slate-800">{{ $namaLengkap }}</p>
            <p class="text-xs text-slate-400">{{ $labelPeran }}</p>
        </div>

        {{-- Avatar inisial dalam lingkaran --}}
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand text-sm font-bold text-white"
             title="{{ $namaLengkap }}">
            {{ $inisial }}
        </div>
    </div>
</header>
