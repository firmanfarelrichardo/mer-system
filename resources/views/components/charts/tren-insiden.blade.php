{{--
|--------------------------------------------------------------------------
| Komponen: x-charts.tren-insiden
|--------------------------------------------------------------------------
| Line Chart - tren jumlah insiden per bulan (6 bulan terakhir).
| Menggunakan Chart.js via CDN.
|
| Props:
|   $trenBulanan - array{labels: list<string>, data: list<int>}
|
| Akses: Semua peran
|--------------------------------------------------------------------------
--}}
@props(['trenBulanan'])

{{-- Muat Chart.js CDN satu kali untuk seluruh halaman. --}}
@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"
            integrity="sha256-oVuPpCd0re4VaHStGbFbTc9sVD8koU7gkS7vpNL7fZg="
            crossorigin="anonymous" defer></script>
@endPushOnce

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5 flex items-start justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-800">Tren Insiden Bulanan</h2>
            <p class="text-xs text-slate-400">Jumlah laporan dalam 6 bulan terakhir</p>
        </div>
        {{-- Legend warna garis yang minimal --}}
        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
            <span class="h-2 w-2 rounded-full bg-blue-500"></span>
            Insiden
        </span>
    </div>

    <div class="relative" style="height: 240px;">
        <canvas id="chartTrenInsiden"></canvas>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // Data dari server - encode PHP → JSON yang aman.
    const labels = @json($trenBulanan['labels']);
    const data   = @json($trenBulanan['data']);

    // Tunggu Chart.js selesai dimuat (karena defer).
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('chartTrenInsiden');
        if (!ctx) return;

        // Gradient fill untuk estetika.
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.18)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Insiden',
                    data: data,
                    borderColor: 'rgb(37, 99, 235)',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: 'rgb(37, 99, 235)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#94a3b8',
                        bodyColor: '#f1f5f9',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            title: (items) => items[0].label,
                            label: (item) => ' ' + item.raw + ' insiden',
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 11 } },
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
