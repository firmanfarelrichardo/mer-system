{{--
|--------------------------------------------------------------------------
| Daftar Kepala Ruangan (admin/kepala-ruangan/index.blade.php)
|--------------------------------------------------------------------------
| Menampilkan semua unit kerja beserta status Kepala Ruangan-nya.
| Admin dapat menunjuk atau mencabut peran Karu dari halaman ini.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('judul', 'Daftar Kepala Ruangan — Sistem MER')

@section('konten')

    {{-- HEADER --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Daftar Kepala Ruangan</h1>
        <p class="mt-1 text-sm text-slate-400">
            Kelola penunjukan Kepala Ruangan untuk setiap unit kerja di rumah sakit.
        </p>
    </div>

    {{-- PERINGATAN UNIT TANPA KARU --}}
    @if ($jumlahTanpaKaru > 0)
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-amber-800">
                    {{ $jumlahTanpaKaru }} unit belum memiliki Kepala Ruangan
                </p>
                <p class="mt-0.5 text-xs text-amber-700">
                    Segera pilih Kepala Ruangan agar laporan insiden dari unit tersebut dapat ditindaklanjuti.
                </p>
            </div>
        </div>
    @endif

    {{-- TABEL UNIT & KARU --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="px-5 py-3 font-semibold text-slate-500">Unit Kerja</th>
                        <th class="px-5 py-3 font-semibold text-slate-500">Kepala Ruangan</th>
                        <th class="px-5 py-3 font-semibold text-slate-500">NIP</th>
                        <th class="px-5 py-3 text-center font-semibold text-slate-500">Status</th>
                        <th class="px-5 py-3 text-center font-semibold text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($daftarUnit as $unit)
                        <tr class="transition-colors hover:bg-slate-50/50">
                            {{-- Unit Kerja --}}
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">{{ $unit->nama_unit }}</p>
                                @if ($unit->kode_unit)
                                    <p class="text-xs text-slate-400">{{ $unit->kode_unit }}</p>
                                @endif
                            </td>

                            {{-- Kepala Ruangan --}}
                            <td class="px-5 py-3">
                                @if ($unit->karu)
                                    <p class="font-medium text-slate-700">{{ $unit->karu->nama_lengkap }}</p>
                                    @if ($unit->karu->email)
                                        <p class="text-xs text-slate-400">{{ $unit->karu->email }}</p>
                                    @endif
                                @else
                                    <span class="text-sm text-slate-400 italic">Belum ditentukan</span>
                                @endif
                            </td>

                            {{-- NIP --}}
                            <td class="px-5 py-3 font-mono text-xs text-slate-500">
                                {{ $unit->karu?->nomor_induk ?? '—' }}
                            </td>

                            {{-- Status --}}
                            <td class="px-5 py-3 text-center">
                                @if ($unit->karu)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                        Terisi
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        Kosong
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-5 py-3 text-center">
                                @if ($unit->karu)
                                    <div class="flex items-center justify-center gap-1">
                                        {{-- Ganti Karu: cabut lalu arahkan ke pilihan baru --}}
                                        <a href="{{ route('admin.kepala-ruangan.tunjuk', $unit->id) }}"
                                           title="Ganti Kepala Ruangan"
                                           class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-blue-50 hover:text-blue-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                                            </svg>
                                        </a>

                                        {{-- Cabut Karu --}}
                                        <form method="POST" action="{{ route('admin.kepala-ruangan.cabut', $unit->id) }}"
                                              data-confirm="Cabut peran Kepala Ruangan {{ $unit->karu->nama_lengkap }} dari {{ $unit->nama_unit }}?"
                                              data-confirm-label="Cabut">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    title="Cabut Kepala Ruangan"
                                                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <a href="{{ route('admin.kepala-ruangan.tunjuk', $unit->id) }}"
                                       class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white
                                              shadow-sm transition-colors hover:bg-brand/90">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/>
                                        </svg>
                                        Pilih Kepala Ruangan
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
