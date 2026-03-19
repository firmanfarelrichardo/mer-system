<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LogAktivitas;

/**
 * Service: Deteksi Login Mencurigakan Berbasis IP.
 *
 * Digunakan setelah event LOGIN berhasil untuk mengecek apakah
 * ip_address yang digunakan pernah digunakan sebelumnya oleh user ini.
 *
 * Ini adalah implementasi risk scoring SEDERHANA yang cocok untuk
 * sistem skala menengah (klinik, RS klas C/D) tanpa infrastruktur SIEM.
 *
 * Untuk RS klas A/B atau sistem dengan throughput tinggi, pertimbangkan:
 *   - Elasticsearch + Kibana untuk log aggregation
 *   - Grafana Loki untuk log streaming
 *   - Wazuh untuk SIEM open-source
 */
class SuspiciousLoginService
{
    /**
     * Periksa apakah login dari IP ini mencurigakan untuk user tertentu.
     *
     * Algoritma risk scoring sederhana (skor 0-100):
     *   +40 : IP belum pernah digunakan user ini sama sekali
     *   +30 : User agent belum pernah digunakan user ini
     *   +20 : Login di luar jam kerja normal (sebelum 07:00 atau setelah 22:00 WIB)
     *   +10 : Login di hari libur/weekend
     *
     * Threshold:
     *   0-30  : Normal, tidak perlu tindakan
     *   31-60 : Peringatan ringan (log saja)
     *   61-100: Peringatan tinggi — perlu notifikasi ke admin
     *
     * @return array{skor: int, alasan: list<string>, level: 'normal'|'peringatan'|'mencurigakan'}
     */
    public function periksa(int $idPengguna, string $alamatIp, ?string $userAgent): array
    {
        $skor    = 0;
        $alasan  = [];
        $periode = now()->subDays(90); // Lihat riwayat 90 hari terakhir

        // ── Cek 1: Apakah IP pernah digunakan? ──────────────────────────
        $ipPernah = LogAktivitas::where('id_pengguna', $idPengguna)
            ->where('aksi', 'LOGIN')
            ->where('alamat_ip', $alamatIp)
            ->where('created_at', '>=', $periode)
            ->exists();

        if (! $ipPernah) {
            $skor  += 40;
            $alasan[] = "Login dari IP baru: {$alamatIp}";
        }

        // ── Cek 2: Apakah user agent pernah digunakan? ──────────────────
        if ($userAgent) {
            $uaPernah = LogAktivitas::where('id_pengguna', $idPengguna)
                ->where('aksi', 'LOGIN')
                ->where('user_agent', $userAgent)
                ->where('created_at', '>=', $periode)
                ->exists();

            if (! $uaPernah) {
                $skor  += 30;
                $alasan[] = 'Login dari browser/perangkat baru';
            }
        }

        // ── Cek 3: Jam tidak normal (luar 07:00–22:00 WIB) ──────────────
        $jam = (int) now()->setTimezone('Asia/Jakarta')->format('H');
        if ($jam < 7 || $jam >= 22) {
            $skor  += 20;
            $alasan[] = 'Login di luar jam kerja normal (' . now()->setTimezone('Asia/Jakarta')->format('H:i') . ' WIB)';
        }

        // ── Cek 4: Weekend ───────────────────────────────────────────────
        if (now()->isWeekend()) {
            $skor  += 10;
            $alasan[] = 'Login di hari ' . now()->setTimezone('Asia/Jakarta')->translatedFormat('l');
        }

        // ── Tentukan level risiko ────────────────────────────────────────
        $level = match(true) {
            $skor >= 61 => 'mencurigakan',
            $skor >= 31 => 'peringatan',
            default     => 'normal',
        };

        return compact('skor', 'alasan', 'level');
    }
}
