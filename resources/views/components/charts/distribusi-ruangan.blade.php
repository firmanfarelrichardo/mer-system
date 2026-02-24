{{--
|--------------------------------------------------------------------------
| Komponen: x-charts.distribusi-ruangan
|--------------------------------------------------------------------------
| Horizontal Bar Chart — perbandingan jumlah insiden antar unit kerja.
|
| RBAC: Komponen ini HANYA dirender untuk direktur & komite.
|        Pembungkus @if ada di view induk (statistik/index.blade.php).
|
| Props:
|   $distribusiRuangan — array{labels: list<string>, data: list<int>, colors: list<string>}
|
| Akses: Direktur, Komite SAJA
|--------------------------------------------------------------------------
--}}
@props(['distribusiRuangan'])

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <h2 class="text-base font-semibold text-slate-800">Distribusi Insiden per Ruangan</h2>
        <p class="text-xs text-slate-400">Perbandingan jumlah insiden antar unit kerja (top 12)</p>
    </div>

    @if (empty($distribusiRuangan['data']) || array_sum($distribusiRuangan['data']) === 0)
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <svg class="h-12 w-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3"/>
            </svg>
            <p class="mt-3 text-sm text-slate-400">Belum ada data unit kerja</p>
        </div>
    @else
        @php
            // Tinggi chart dinamis berdasarkan jumlah unit (min 200px, maks 480px).
            $chartHeight = max(200, min(480, count($distribusiRuangan['labels']) * 38 + 20));
        @endphp
        <div class="relative" style="height: {{ $chartHeight }}px;">
            <canvas id="chartDistribusiRuangan"></canvas>
        </div>
    @endif
</div>

@if (!empty($distribusiRuangan['data']) && array_sum($distribusiRuangan['data']) > 0)
    @push('scripts')
    <script>
    (function () {
        const labels = @json($distribusiRuangan['labels']);
        const data   = @json($distribusiRuangan['data']);
        const colors = @json($distribusiRuangan['colors']);

        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('chartDistribusiRuangan');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Insiden',
                        data: data,
                        backgroundColor: colors,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 28,
                    }],
                },
                options: {
                    indexAxis: 'y',   // Horizontal bar
                    responsive: true,
                    maintainAspectRatio: false,
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
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: 'rgba(148,163,184,0.12)', drawBorder: false },
                            ticks: { color: '#94a3b8', font: { size: 11 }, precision: 0 },
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                color: '#475569',
                                font: { size: 11 },
                                // Potong label yang terlalu panjang.
                                callback: function (value, index) {
                                    const lbl = this.getLabelForValue(index);
                                    return lbl.length > 22 ? lbl.substring(0, 20) + '…' : lbl;
                                },
                            },
                        },
                    },
                },
            });
        });
    }());
    </script>
    @endpush
@endif
