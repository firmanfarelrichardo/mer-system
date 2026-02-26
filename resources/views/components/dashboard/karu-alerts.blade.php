{{--
|--------------------------------------------------------------------------
| Komponen: Karu Alerts (dashboard/karu-alerts)
|--------------------------------------------------------------------------
| Fokus: To-Do & SLA Alerts untuk Kepala Ruangan.
| Menampilkan pending counter, SLA breach alert, ringkasan status unit,
| dan timeline aktivitas terbaru.
|
| Props:
|   $data      — array ['menunggu_tindak_lanjut', 'sla_breach',
|                        'aktivitas_terbaru', 'ringkasan_status']
|   $pengguna  — App\Models\Pengguna
|--------------------------------------------------------------------------
--}}

@props(['data', 'pengguna'])

<div class="space-y-6">

    {{-- ================================================================
         SLA ALERT BANNER — hanya muncul jika ada breach
         ================================================================ --}}
    @if ($data['sla_breach'] > 0)
        <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100">
                <svg class="h-5 w-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-red-800">{{ $data['sla_breach'] }} laporan KTD/Sentinel belum direspons &gt; 24 jam</p>
                <p class="mt-0.5 text-xs text-red-600">Segera tindak lanjuti laporan ini untuk menjaga keselamatan pasien.</p>
            </div>
        </div>
    @endif

    {{-- ================================================================
         RINGKASAN ANGKA — 4 kartu metrik
         ================================================================ --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">

        {{-- Menunggu Tindak Lanjut (prominent) --}}
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
            <p class="text-4xl font-extrabold text-amber-700">{{ $data['menunggu_tindak_lanjut'] }}</p>
            <p class="mt-1 text-center text-xs font-semibold text-amber-600">Menunggu Tindakan</p>
        </div>

        {{-- Kasus Baru --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-slate-800">{{ $data['ringkasan_status']['kasus_baru'] }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Kasus Baru</p>
        </div>

        {{-- Investigasi + Tindak Lanjut --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-slate-800">{{ $data['ringkasan_status']['investigasi'] + $data['ringkasan_status']['tindak_lanjut'] }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Dalam Proses</p>
        </div>

        {{-- Selesai --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
            <p class="text-2xl font-bold text-emerald-600">{{ $data['ringkasan_status']['selesai'] }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">Selesai</p>
        </div>

    </div>

    {{-- ================================================================
         DISTRIBUSI STATUS — mini bar horizontal
         ================================================================ --}}
    @php
        $totalUnit = array_sum($data['ringkasan_status']);
        $persen = fn(string $key) => $totalUnit > 0
            ? round(($data['ringkasan_status'][$key] / $totalUnit) * 100)
            : 0;
    @endphp
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="mb-3 text-sm font-semibold text-slate-700">Distribusi Status Unit Anda</p>
        @if ($totalUnit > 0)
            <div class="flex h-4 w-full overflow-hidden rounded-full bg-slate-100">
                @if ($persen('kasus_baru') > 0)
                    <div class="bg-amber-400" style="width: {{ $persen('kasus_baru') }}%" title="Kasus Baru {{ $persen('kasus_baru') }}%"></div>
                @endif
                @if ($persen('investigasi') > 0)
                    <div class="bg-blue-400" style="width: {{ $persen('investigasi') }}%" title="Investigasi {{ $persen('investigasi') }}%"></div>
                @endif
                @if ($persen('tindak_lanjut') > 0)
                    <div class="bg-violet-400" style="width: {{ $persen('tindak_lanjut') }}%" title="Tindak Lanjut {{ $persen('tindak_lanjut') }}%"></div>
                @endif
                @if ($persen('selesai') > 0)
                    <div class="bg-emerald-400" style="width: {{ $persen('selesai') }}%" title="Selesai {{ $persen('selesai') }}%"></div>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400"></span> Kasus Baru</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-400"></span> Investigasi</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-violet-400"></span> Tindak Lanjut</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400"></span> Selesai</span>
            </div>
        @else
            <p class="text-sm text-slate-400">Belum ada data insiden di unit Anda.</p>
        @endif
    </div>

    {{-- ================================================================
         TIMELINE AKTIVITAS TERBARU — 5 entri terakhir
         ================================================================ --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="mb-4 text-sm font-semibold text-slate-700">Aktivitas Terbaru di Unit Anda</p>

        @forelse ($data['aktivitas_terbaru'] as $tl)
            <div class="flex items-start gap-3 {{ !$loop->last ? 'mb-4 border-b border-slate-100 pb-4' : '' }}">
                {{-- Timeline dot --}}
                <div class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full
                    {{ $tl->status === 'selesai' ? 'bg-emerald-100 text-emerald-600' : 'bg-blue-100 text-blue-600' }}">
                    @if ($tl->status === 'selesai')
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    @else
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    @endif
                </div>
                {{-- Content --}}
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-700">
                        <span class="font-semibold">{{ $tl->pengguna?->nama_lengkap ?? 'Sistem' }}</span>
                        mengubah status menjadi
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ match($tl->status) {
                                'investigasi' => 'bg-blue-100 text-blue-700',
                                'tindak_lanjut' => 'bg-violet-100 text-violet-700',
                                'selesai' => 'bg-emerald-100 text-emerald-700',
                                default => 'bg-slate-100 text-slate-600',
                            } }}">
                            {{ $tl->labelStatus() }}
                        </span>
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ $tl->insiden?->nomor_laporan ?? '—' }}
                        &nbsp;·&nbsp;
                        {{ $tl->created_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-400">Belum ada aktivitas terbaru.</p>
        @endforelse
    </div>

</div>
