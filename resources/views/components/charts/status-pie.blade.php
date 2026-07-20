{{--
|--------------------------------------------------------------------------
| Komponen: x-charts.status-pie
|--------------------------------------------------------------------------
| Doughnut Chart - distribusi status penanganan insiden.
|
| Props:
|   $distribusiStatus - array{labels: list<string>, data: list<int>, colors: list<string>}
|
| Akses: Semua peran
|--------------------------------------------------------------------------
--}}
@props(['distribusiStatus'])

@php
    $total = array_sum($distribusiStatus['data']);
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <h2 class="text-base font-semibold text-slate-800">Distribusi Status</h2>
        <p class="text-xs text-slate-400">Status penanganan seluruh insiden</p>
    </div>

    <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
        {{-- Canvas chart --}}
        <div class="relative mx-auto w-40 shrink-0">
            <canvas id="chartStatusPie" style="max-width: 160px; max-height: 160px;"></canvas>
            {{-- Angka total di tengah donut --}}
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl font-bold text-slate-800">{{ $total }}</span>
                <span class="text-xs text-slate-400">Total</span>
            </div>
        </div>

        {{-- Legenda kustom --}}
        <div class="w-full space-y-2.5">
            @foreach ($distribusiStatus['labels'] as $i => $label)
                @php
                    $jumlah = $distribusiStatus['data'][$i];
                    $persen = $total > 0 ? round(($jumlah / $total) * 100) : 0;
                    // Dot warna sesuai WARNA_STATUS di StatistikController.
                    $dotClass = match($i) {
                        0 => 'bg-blue-500',    // kasus_baru
                        1 => 'bg-indigo-500',  // investigasi
                        2 => 'bg-violet-500',  // tindak_lanjut
                        3 => 'bg-emerald-500', // selesai
                        default => 'bg-slate-400',
                    };
                @endphp
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                        <span class="truncate text-sm text-slate-600">{{ $label }}</span>
                    </div>
                    <div class="shrink-0 text-right">
                        <span class="text-sm font-semibold text-slate-800">{{ $jumlah }}</span>
                        <span class="ml-1 text-xs text-slate-400">({{ $persen }}%)</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const labels = @json($distribusiStatus['labels']);
    const data   = @json($distribusiStatus['data']);
    const colors = @json($distribusiStatus['colors']);

    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('chartStatusPie');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#94a3b8',
                        bodyColor: '#f1f5f9',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: (item) => ' ' + item.raw + ' insiden',
                        },
                    },
                },
            },
        });
    });
}());
</script>
@endpush
