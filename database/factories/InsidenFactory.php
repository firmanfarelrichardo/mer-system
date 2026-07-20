<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Insiden;
use App\Models\Organisasi;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * InsidenFactory - menghasilkan data insiden tiruan yang realistis.
 *
 * Properti yang diisi:
 *   - tenant_id          : ID tenant default
 *   - nomor_laporan      : Auto-increment per tenant (INC-YYYY-NNNN)
 *   - pelapor_id         : Pengguna perawat/karu secara acak
 *   - unit_id            : NULL (gunakan nama_unit_kerja)
 *   - nama_unit_kerja    : Nama unit dari daftar standar rumah sakit
 *   - tipe_insiden       : KPC/KNC/KTC/KTD/SENTINEL
 *   - fase_kesalahan     : prescribing/transcribing/dispensing/administration
 *   - status_saat_ini    : kasus_baru/investigasi/tindak_lanjut/selesai
 *   - tgl_kejadian       : Random dalam 6 bulan terakhir
 *   - tgl_lapor          : 0–2 hari setelah tgl_kejadian
 *   - nama_pelapor       : Nama lengkap dummy
 *   - kontak_pelapor     : Nomor HP dummy
 *   - is_anonim          : Acak (30% anonim)
 *   - sudah_dibaca       : Acak (70% sudah dibaca)
 *
 * Distribusi yang realistis:
 *   - KPC: 20%, KNC: 30%, KTC: 25%, KTD: 20%, SENTINEL: 5%
 *   - Selesai: 40%, Tindak Lanjut: 25%, Investigasi: 20%, Kasus Baru: 15%
 */
class InsidenFactory extends Factory
{
    protected $model = Insiden::class;

    // ----------------------------------------------------------------
    // Data Referensi
    // ----------------------------------------------------------------

    /**
     * Unit kerja yang tersedia di rumah sakit.
     * Sesuai dengan daftar yang ada di formulir laporan.
     *
     * @var list<string>
     */
    private static array $unitKerja = [
        'IGD', 'ICU', 'Instalasi Farmasi', 'Ruang Penyakit Dalam',
        'Ruang Bedah', 'Ruang Anak', 'Ruang Kebidanan', 'Ruang Neonatus',
        'Rawat Jalan (Poli Umum)', 'Instalasi Bedah Sentral (IBS)',
        'Ruang Saraf', 'Ruang Paru', 'Ruang HD', 'Ruang PONEK',
        'Poli Kebidanan', 'Poli Anak',
    ];

    /**
     * Tipe insiden dengan bobot probabilitas.
     * Total bobot = 100.
     *
     * @var array<string, int>
     */
    private static array $tipeInsiden = [
        'KPC'      => 20,
        'KNC'      => 30,
        'KTC'      => 25,
        'KTD'      => 20,
        'SENTINEL' => 5,
    ];

    /**
     * Status insiden dengan bobot probabilitas.
     *
     * @var array<string, int>
     */
    private static array $statusInsiden = [
        'kasus_baru'    => 15,
        'investigasi'   => 20,
        'tindak_lanjut' => 25,
        'selesai'       => 40,
    ];

    /**
     * Fase/tahapan kesalahan obat.
     *
     * @var list<string>
     */
    private static array $faseKesalahan = [
        'prescribing',
        'transcribing',
        'dispensing',
        'administration',
    ];

    /**
     * Counter per tenant untuk nomor laporan yang unik.
     *
     * @var array<int, int>
     */
    private static array $nomorCounter = [];

    // ----------------------------------------------------------------
    // Definition
    // ----------------------------------------------------------------

    public function definition(): array
    {
        // Dapatkan tenant default - cache agar tidak query berulang.
        $tenant = Organisasi::where('kode_organisasi', 'default')->first();
        $tenantId = $tenant?->id ?? 1;

        // Generate nomor laporan yang unik.
        static::$nomorCounter[$tenantId] = (static::$nomorCounter[$tenantId] ?? 0) + 1;
        $nomor = sprintf(
            'INC-%s-%04d',
            now()->format('Y'),
            static::$nomorCounter[$tenantId],
        );

        // Tanggal kejadian: acak dalam 6 bulan terakhir.
        $tglKejadian = Carbon::now()
            ->subDays(fake()->numberBetween(0, 180))
            ->setTime(
                fake()->numberBetween(0, 23),
                fake()->numberBetween(0, 59),
            );

        // Tanggal lapor: 0–2 hari setelah kejadian.
        $tglLapor = $tglKejadian->copy()->addDays(fake()->numberBetween(0, 2));

        return [
            'tenant_id'       => $tenantId,
            'nomor_laporan'   => $nomor,
            'pelapor_id'      => null,
            'unit_id'         => null,
            'nama_unit_kerja' => fake()->randomElement(static::$unitKerja),
            'tipe_insiden'    => $this->acakBerbobot(static::$tipeInsiden),
            'fase_kesalahan'  => fake()->randomElement(static::$faseKesalahan),
            'status_saat_ini' => $this->acakBerbobot(static::$statusInsiden),
            'tgl_kejadian'    => $tglKejadian,
            'tgl_lapor'       => $tglLapor,
            'nama_pelapor'    => fake('id_ID')->name(),
            'kontak_pelapor'  => '08' . fake()->numerify('#########'),
            'is_anonim'       => fake()->boolean(30),
            'sudah_dibaca'    => fake()->boolean(70),
        ];
    }

    // ----------------------------------------------------------------
    // States - buat factory lebih fleksibel saat dipakai di tempat lain
    // ----------------------------------------------------------------

    /**
     * State: insiden dari unit IGD.
     */
    public function igd(): static
    {
        return $this->state(['nama_unit_kerja' => 'IGD']);
    }

    /**
     * State: insiden dengan status selesai.
     */
    public function selesai(): static
    {
        return $this->state(['status_saat_ini' => 'selesai']);
    }

    /**
     * State: insiden dengan tipe KTD (tingkat cedera tertinggi-sangat kritis).
     */
    public function ktd(): static
    {
        return $this->state(['tipe_insiden' => 'KTD']);
    }

    // ----------------------------------------------------------------
    // Helpers Privat
    // ----------------------------------------------------------------

    /**
     * Pilih item secara acak dengan bobot probabilitas.
     *
     * @param  array<string, int> $bobot  ['item' => bobot, ...]
     */
    private function acakBerbobot(array $bobot): string
    {
        $total = array_sum($bobot);
        $angka = fake()->numberBetween(1, $total);

        $kumulatif = 0;
        foreach ($bobot as $item => $nilai) {
            $kumulatif += $nilai;
            if ($angka <= $kumulatif) {
                return $item;
            }
        }

        return array_key_first($bobot);
    }
}
