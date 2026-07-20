<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LogAktivitasSeeder - Seed 1500+ log aktivitas audit selama 3 tahun (2024-2026).
 *
 * Mensimulasikan aktivitas harian pengguna: login, CRUD insiden, kelola pengguna,
 * perubahan unit kerja, dan lain-lain.
 *
 * Idempotent: truncate tabel sebelum insert.
 */
class LogAktivitasSeeder extends Seeder
{
    use WithoutModelEvents;

    /* ---------------------------------------------------------------
     | Konstanta Domain
     | ------------------------------------------------------------*/

    private const AKSI_LOGIN = [
        'aksi'       => 'LOGIN',
        'nama_tabel' => 'akun.pengguna',
    ];

    private const AKSI_LOGOUT = [
        'aksi'       => 'LOGOUT',
        'nama_tabel' => 'akun.pengguna',
    ];

    /**
     * Template aktivitas dengan nama_tabel, aksi, dan generator data.
     */
    private const AKSI_TEMPLATES = [
        // ── Insiden ──────────────────────────────────────────────────
        [
            'nama_tabel' => 'pelaporan.insiden',
            'aksi'       => 'CREATE',
            'bobot'      => 30,
        ],
        [
            'nama_tabel' => 'pelaporan.insiden',
            'aksi'       => 'UPDATE',
            'bobot'      => 25,
        ],
        [
            'nama_tabel' => 'pelaporan.insiden',
            'aksi'       => 'READ',
            'bobot'      => 20,
        ],
        // ── Tindak Lanjut ────────────────────────────────────────────
        [
            'nama_tabel' => 'pelaporan.tindak_lanjut',
            'aksi'       => 'CREATE',
            'bobot'      => 10,
        ],
        // ── Pengguna ─────────────────────────────────────────────────
        [
            'nama_tabel' => 'akun.pengguna',
            'aksi'       => 'CREATE',
            'bobot'      => 3,
        ],
        [
            'nama_tabel' => 'akun.pengguna',
            'aksi'       => 'UPDATE',
            'bobot'      => 5,
        ],
        // ── Unit Kerja ───────────────────────────────────────────────
        [
            'nama_tabel' => 'master.unit_kerja',
            'aksi'       => 'CREATE',
            'bobot'      => 2,
        ],
        [
            'nama_tabel' => 'master.unit_kerja',
            'aksi'       => 'UPDATE',
            'bobot'      => 3,
        ],
        // ── Kategori Kesalahan ───────────────────────────────────────
        [
            'nama_tabel' => 'master.kategori_kesalahan',
            'aksi'       => 'CREATE',
            'bobot'      => 1,
        ],
        [
            'nama_tabel' => 'master.kategori_kesalahan',
            'aksi'       => 'UPDATE',
            'bobot'      => 1,
        ],
    ];

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Edg/120.0.0.0',
    ];

    private const IP_POOLS = [
        '192.168.1.', '192.168.10.', '10.0.0.', '10.10.1.', '172.16.0.',
    ];

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        $pengguna = Pengguna::where('tenant_id', $tenant->id)
            ->where('is_aktif', true)
            ->get();

        if ($pengguna->isEmpty()) {
            $this->command->warn('  ⚠ Tidak ada pengguna aktif. Lewati LogAktivitasSeeder.');
            return;
        }

        $this->command->info('  ▶ Seeding log aktivitas (3 tahun: 2024-2026) ...');

        // Truncate existing data
        DB::table('audit.log_aktivitas')->truncate();

        $mulai  = Carbon::parse('2024-01-01');
        $akhir  = Carbon::parse('2026-12-31');
        $batch  = [];
        $total  = 0;

        // Generate login/logout + aktivitas per hari
        $tanggal = $mulai->copy();
        while ($tanggal->lte($akhir)) {
            $isWeekday = ! $tanggal->isWeekend();

            // Jumlah aktivitas per hari: lebih banyak di hari kerja
            $jumlahAktivitas = $isWeekday
                ? random_int(3, 8)
                : random_int(0, 2);

            // Pilih pengguna aktif hari ini (subset acak)
            $penggunaHariIni = $pengguna->random(min(random_int(2, 6), $pengguna->count()));

            foreach ($penggunaHariIni as $p) {
                $ip = self::IP_POOLS[array_rand(self::IP_POOLS)] . random_int(10, 254);
                $ua = self::USER_AGENTS[array_rand(self::USER_AGENTS)];

                // Login di pagi hari
                $jamLogin = $tanggal->copy()->setTime(random_int(6, 9), random_int(0, 59), random_int(0, 59));
                $batch[] = [
                    'tenant_id'   => $tenant->id,
                    'nama_tabel'  => self::AKSI_LOGIN['nama_tabel'],
                    'aksi'        => self::AKSI_LOGIN['aksi'],
                    'id_data'     => $p->id,
                    'id_pengguna' => $p->id,
                    'alamat_ip'   => $ip,
                    'user_agent'  => $ua,
                    'data_lama'   => null,
                    'data_baru'   => json_encode(['event' => 'login', 'nama' => $p->nama_lengkap]),
                    'created_at'  => $jamLogin,
                ];
                $total++;

                // Aktivitas selama hari kerja
                for ($a = 0; $a < $jumlahAktivitas; $a++) {
                    $template = $this->pilihTemplate();
                    $jamAksi  = $tanggal->copy()->setTime(
                        random_int(8, 17),
                        random_int(0, 59),
                        random_int(0, 59),
                    );

                    $batch[] = [
                        'tenant_id'   => $tenant->id,
                        'nama_tabel'  => $template['nama_tabel'],
                        'aksi'        => $template['aksi'],
                        'id_data'     => random_int(1, 500),
                        'id_pengguna' => $p->id,
                        'alamat_ip'   => $ip,
                        'user_agent'  => $ua,
                        'data_lama'   => $template['aksi'] === 'UPDATE'
                            ? json_encode($this->generateDataLama($template['nama_tabel']))
                            : null,
                        'data_baru'   => in_array($template['aksi'], ['CREATE', 'UPDATE'], true)
                            ? json_encode($this->generateDataBaru($template['nama_tabel']))
                            : null,
                        'created_at'  => $jamAksi,
                    ];
                    $total++;
                }

                // Logout di sore/malam hari
                $jamLogout = $tanggal->copy()->setTime(random_int(16, 22), random_int(0, 59), random_int(0, 59));
                $batch[] = [
                    'tenant_id'   => $tenant->id,
                    'nama_tabel'  => self::AKSI_LOGOUT['nama_tabel'],
                    'aksi'        => self::AKSI_LOGOUT['aksi'],
                    'id_data'     => $p->id,
                    'id_pengguna' => $p->id,
                    'alamat_ip'   => $ip,
                    'user_agent'  => $ua,
                    'data_lama'   => null,
                    'data_baru'   => json_encode(['event' => 'logout', 'nama' => $p->nama_lengkap]),
                    'created_at'  => $jamLogout,
                ];
                $total++;

                // Flush batch setiap 500 records
                if (count($batch) >= 500) {
                    DB::table('audit.log_aktivitas')->insert($batch);
                    $batch = [];
                }
            }

            $tanggal->addDay();
        }

        // Flush remaining
        if (! empty($batch)) {
            DB::table('audit.log_aktivitas')->insert($batch);
        }

        $this->command->info("  ✔ LogAktivitasSeeder selesai ({$total} records).");
    }

    /* ---------------------------------------------------------------
     | Helpers
     | ------------------------------------------------------------*/

    /**
     * Pilih template aktivitas berdasarkan bobot.
     */
    private function pilihTemplate(): array
    {
        $totalBobot = array_sum(array_column(self::AKSI_TEMPLATES, 'bobot'));
        $acak       = random_int(1, $totalBobot);
        $kumulatif  = 0;

        foreach (self::AKSI_TEMPLATES as $template) {
            $kumulatif += $template['bobot'];
            if ($acak <= $kumulatif) {
                return $template;
            }
        }

        return self::AKSI_TEMPLATES[0];
    }

    /**
     * Generate sample data_lama untuk UPDATE operations.
     */
    private function generateDataLama(string $tabel): array
    {
        return match ($tabel) {
            'pelaporan.insiden' => [
                'status_saat_ini' => ['kasus_baru', 'investigasi', 'tindak_lanjut'][random_int(0, 2)],
                'sudah_dibaca'    => false,
            ],
            'akun.pengguna' => [
                'is_aktif'   => (bool) random_int(0, 1),
                'nama_lengkap' => 'Nama Sebelumnya',
            ],
            'master.unit_kerja' => [
                'nama_unit'  => 'Unit Sebelumnya',
                'keterangan' => 'Keterangan lama',
            ],
            'master.kategori_kesalahan' => [
                'nama_kategori' => 'Kategori Sebelumnya',
            ],
            default => ['field' => 'old_value'],
        };
    }

    /**
     * Generate sample data_baru untuk CREATE/UPDATE operations.
     */
    private function generateDataBaru(string $tabel): array
    {
        return match ($tabel) {
            'pelaporan.insiden' => [
                'status_saat_ini' => ['investigasi', 'tindak_lanjut', 'selesai'][random_int(0, 2)],
                'sudah_dibaca'    => true,
            ],
            'pelaporan.tindak_lanjut' => [
                'status_baru' => ['investigasi', 'tindak_lanjut', 'selesai'][random_int(0, 2)],
                'catatan'     => 'Tindak lanjut telah dilakukan.',
            ],
            'akun.pengguna' => [
                'is_aktif'     => true,
                'nama_lengkap' => 'Nama Diperbarui',
            ],
            'master.unit_kerja' => [
                'nama_unit'  => 'Unit Diperbarui',
                'keterangan' => 'Keterangan baru',
            ],
            'master.kategori_kesalahan' => [
                'nama_kategori' => 'Kategori Diperbarui',
            ],
            default => ['field' => 'new_value'],
        };
    }
}
