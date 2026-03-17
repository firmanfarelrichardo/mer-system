{{--
|--------------------------------------------------------------------------
| Komponen: x-charts.severity-bar
|--------------------------------------------------------------------------
| Bar Chart — distribusi insiden per tipe/tingkat keparahan.
| Menampilkan: KPC, KNC, KTC, KTD, SENTINEL.
|
| Props:
|   $distribusiTipe — array{labels: list<string>, data: list<int>, colors: list<string>}
|
| Akses: Semua peran
|--------------------------------------------------------------------------
--}}
@props(['distribusiTipe'])

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <h2 class="text-base font-semibold text-slate-800">Distribusi Tipe Insiden</h2>
        <p class="text-xs text-slate-400">KPC, KNC, KTC, KTD, dan Sentinel</p>
    </div>

    <div class="relative" style="height: 220px;">
        <canvas id="chartSeverityBar"></canvas>
    </div>

    {{-- Keterangan deskriptif di bawah chart --}}
    <div class="mt-4 grid grid-cols-5 gap-1.5 text-center text-xs text-slate-500">
        @foreach (['Potensial Cedera', 'Nyaris Cedera', 'Tidak Cedera', 'Tidak Diharapkan', 'Sentinel'] as $idx => $ket)
            <div class="leading-tight">{{ $distribusiTipe['labels'][$idx] ?? '' }}<br><span class="text-slate-400">{{ $ket }}</span></div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
(function () {
    const labels = @json($distribusiTipe['labels']);
    const data   = @json($distribusiTipe['data']);
    const colors = @json($distribusiTipe['colors']);

    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('chartSeverityBar');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah',
                    data: data,
                    backgroundColor: colors,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 52,
                }],
            },
            options: {
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
                        grid: { display: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 12, weight: '600' },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148,163,184,0.12)', drawBorder: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 },
                            precision: 0,
                        },
                    },
                },
            },
        });
    });
}());
</script>
@endpush
