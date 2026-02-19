{{--
|--------------------------------------------------------------------------
| Riwayat Laporan Insiden (laporan/index.blade.php)
|--------------------------------------------------------------------------
| Halaman daftar riwayat laporan insiden medication errors.
| Menampilkan:
|   1. Header judul + sub-judul
|   2. Empat kartu ringkasan (Total, Kasus Baru, Diproses, Selesai)
|   3. Filter pencarian, status, dan tipe insiden
|   4. Tabel data dengan data dummy realistis
|
| Menggunakan layout utama (@extends layouts.app) agar sidebar
| dan navbar otomatis tampil.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Riwayat Laporan — Sistem MER')

@section('konten')

    {{-- ================================================================
         HEADER HALAMAN
         ================================================================ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Riwayat Laporan</h1>
        <p class="mt-1 text-sm text-slate-400">Sistem Pelaporan Insiden Obat</p>
    </div>

    {{-- ================================================================
         KARTU RINGKASAN (4 kolom)
         Setiap kartu menampilkan ikon, label, dan angka.
         ================================================================ --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Kartu: Total Laporan --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                {{-- Ikon: tumpukan dokumen --}}
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125
                             1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25
                             0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125
                             1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0
                             0-9-9Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-slate-400">Total Laporan</p>
                {{-- Angka dummy — nanti diganti variabel dari controller --}}
                <p class="text-2xl font-bold text-slate-800">{{ $totalLaporan ?? 128 }}</p>
            </div>
        </div>

        {{-- Kartu: Kasus Baru --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-500">
                {{-- Ikon: seru dalam segitiga --}}
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73
                             0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                             0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-slate-400">Kasus Baru</p>
                <p class="text-2xl font-bold text-slate-800">{{ $kasusBaru ?? 12 }}</p>
            </div>
        </div>

        {{-- Kartu: Sedang Diproses --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                {{-- Ikon: jam / proses --}}
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-slate-400">Sedang Diproses</p>
                <p class="text-2xl font-bold text-slate-800">{{ $sedangDiproses ?? 34 }}</p>
            </div>
        </div>

        {{-- Kartu: Selesai --}}
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                {{-- Ikon: centang dalam lingkaran --}}
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0
                             0 1 18 0Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm text-slate-400">Selesai</p>
                <p class="text-2xl font-bold text-slate-800">{{ $selesai ?? 82 }}</p>
            </div>
        </div>
    </div>

    {{-- ================================================================
         FILTER & PENCARIAN
         ================================================================ --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">

        {{-- Input pencarian --}}
        <div class="relative flex-1">
            {{-- Ikon kaca pembesar di dalam input --}}
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0
                         0 0 10.607 10.607Z" />
            </svg>
            <input type="text"
                   name="cari"
                   value="{{ request('cari') }}"
                   placeholder="Cari berdasarkan nama pasien atau ID..."
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-4
                          text-sm text-slate-700 shadow-sm placeholder:text-slate-300
                          focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
        </div>

        {{-- Dropdown: Status --}}
        <select name="status"
                class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700
                       shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
            <option value="">Semua Status</option>
            <option value="kasus_baru">Kasus Baru</option>
            <option value="investigasi">Investigasi</option>
            <option value="tindak_lanjut">Tindak Lanjut</option>
            <option value="selesai">Selesai</option>
        </select>

        {{-- Dropdown: Tipe / Jenis Insiden --}}
        <select name="tipe"
                class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700
                       shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
            <option value="">Semua Tipe</option>
            <option value="KTD">KTD (Kejadian Tidak Diharapkan)</option>
            <option value="KNC">KNC (Kejadian Nyaris Cedera)</option>
            <option value="KTC">KTC (Kejadian Tidak Cedera)</option>
            <option value="KPC">KPC (Kondisi Potensial Cedera)</option>
        </select>
    </div>

    {{-- ================================================================
         TABEL DATA LAPORAN
         ================================================================ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">

                {{-- Kepala tabel --}}
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-500">ID Insiden</th>
                        <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-500">Pasien</th>
                        <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-500">Tanggal</th>
                        <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-500">Jenis</th>
                        <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-500">Status</th>
                        <th class="whitespace-nowrap px-5 py-3 font-semibold text-slate-500">Aksi</th>
                    </tr>
                </thead>

                {{-- Tubuh tabel — data dummy realistis.
                     Di produksi, ganti dengan @foreach($daftarLaporan as $laporan) --}}
                <tbody class="divide-y divide-slate-100">

                    @php
                        // Data dummy untuk demonstrasi UI.
                        // Setiap elemen mewakili satu baris laporan insiden.
                        $dummyLaporan = [
                            [
                                'id_insiden'  => 'INC-2026-0045',
                                'pasien'      => 'Tn. Ahmad Hidayat',
                                'tanggal'     => '18 Feb 2026',
                                'jenis'       => 'KTD',
                                'jenis_warna' => 'bg-red-100 text-red-700',
                                'status'      => 'Kasus Baru',
                                'status_warna'=> 'bg-amber-100 text-amber-700',
                                'status_kode' => 'kasus_baru',
                            ],
                            [
                                'id_insiden'  => 'INC-2026-0044',
                                'pasien'      => 'Ny. Ratna Sari',
                                'tanggal'     => '17 Feb 2026',
                                'jenis'       => 'KNC',
                                'jenis_warna' => 'bg-orange-100 text-orange-700',
                                'status'      => 'Investigasi',
                                'status_warna'=> 'bg-blue-100 text-blue-700',
                                'status_kode' => 'investigasi',
                            ],
                            [
                                'id_insiden'  => 'INC-2026-0041',
                                'pasien'      => 'An. Budi Prasetyo',
                                'tanggal'     => '15 Feb 2026',
                                'jenis'       => 'KTC',
                                'jenis_warna' => 'bg-yellow-100 text-yellow-700',
                                'status'      => 'Selesai',
                                'status_warna'=> 'bg-emerald-100 text-emerald-700',
                                'status_kode' => 'selesai',
                            ],
                        ];
                    @endphp

                    @foreach ($dummyLaporan as $laporan)
                        <tr class="transition-colors hover:bg-slate-50/60">

                            {{-- ID Insiden — tampilkan sebagai teks monospace --}}
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs font-semibold text-slate-600">
                                {{ $laporan['id_insiden'] }}
                            </td>

                            {{-- Nama Pasien --}}
                            <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-800">
                                {{ $laporan['pasien'] }}
                            </td>

                            {{-- Tanggal kejadian --}}
                            <td class="whitespace-nowrap px-5 py-3.5 text-slate-500">
                                {{ $laporan['tanggal'] }}
                            </td>

                            {{-- Jenis insiden (badge berwarna) --}}
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5
                                             text-xs font-semibold {{ $laporan['jenis_warna'] }}">
                                    {{ $laporan['jenis'] }}
                                </span>
                            </td>

                            {{-- Status (badge berwarna) --}}
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5
                                             text-xs font-semibold {{ $laporan['status_warna'] }}">
                                    {{ $laporan['status'] }}
                                </span>
                            </td>

                            {{-- Kolom Aksi --}}
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <div class="flex items-center gap-2">

                                    {{-- Tombol Lihat — selalu tampil --}}
                                    <a href="{{ route('laporan.tampil', $laporan['id_insiden']) }}"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200
                                              bg-white px-3 py-1.5 text-xs font-semibold text-slate-600
                                              shadow-sm transition-colors hover:bg-slate-50">
                                        {{-- Ikon mata --}}
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423
                                                     7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007
                                                     9.963 7.178.07.207.07.431 0 .639C20.577
                                                     16.49 16.64 19.5 12 19.5c-4.638 0-8.573
                                                     -3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        Lihat
                                    </a>

                                    {{-- Tombol Hapus — hanya muncul jika status = 'Kasus Baru'.
                                         Ini mencegah penghapusan laporan yang sudah ditindaklanjuti. --}}
                                    @if ($laporan['status_kode'] === 'kasus_baru')
                                        <form method="POST" action="#" class="inline"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus laporan ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border
                                                           border-red-200 bg-white px-3 py-1.5 text-xs font-semibold
                                                           text-red-600 shadow-sm transition-colors hover:bg-red-50">
                                                {{-- Ikon tong sampah --}}
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="m14.74 9-.346 9m-4.788 0L9.26
                                                             9m9.968-3.21c.342.052.682.107
                                                             1.022.166m-1.022-.165L18.16
                                                             19.673a2.25 2.25 0 0 1-2.244
                                                             2.077H8.084a2.25 2.25 0 0
                                                             1-2.244-2.077L4.772
                                                             5.79m14.456 0a48.108 48.108
                                                             0 0 0-3.478-.397m-12 .562c.34
                                                             -.059.68-.114 1.022-.165m0
                                                             0a48.11 48.11 0 0 1 3.478
                                                             -.397m7.5 0v-.916c0-1.18-.91
                                                             -2.164-2.09-2.201a51.964 51.964
                                                             0 0 0-3.32 0c-1.18.037-2.09
                                                             1.022-2.09 2.201v.916m7.5
                                                             0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer tabel — info paginasi (placeholder).
             Di produksi, ganti dengan: {{ $daftarLaporan->links() }} --}}
        <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 text-sm text-slate-400">
            <span>Menampilkan 1–3 dari 128 laporan</span>
            <div class="flex gap-1">
                <button disabled
                        class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium
                               text-slate-300">
                    Sebelumnya
                </button>
                <button class="rounded-lg border border-brand bg-brand px-3 py-1 text-xs font-medium text-white">
                    1
                </button>
                <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium
                               text-slate-600 hover:bg-slate-50">
                    2
                </button>
                <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium
                               text-slate-600 hover:bg-slate-50">
                    3
                </button>
                <button class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-medium
                               text-slate-600 hover:bg-slate-50">
                    Berikutnya
                </button>
            </div>
        </div>
    </div>

@endsection
