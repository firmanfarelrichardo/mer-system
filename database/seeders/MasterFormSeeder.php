<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MasterFormSeeder — Seed data awal untuk 7 tabel master formulir:
 *   1. Jenis Kesalahan      (master.jenis_kesalahan)
 *   2. Tipe Cedera          (master.tipe_cedera)
 *   3. Faktor Penyebab      (master.faktor_penyebab)
 *   4. Tindakan Intervensi  (master.tindakan_intervensi)
 *   5. Kategori Kesalahan   (master.kategori_kesalahan)  — NCC MERP A–I
 *   6. Matriks Dampak       (master.matriks_dampak)      — Tingkat 1–5
 *   7. Matriks Probabilitas (master.matriks_probabilitas) — Tingkat 1–5
 *
 * Idempotent: menggunakan updateOrInsert di dalam satu DB::transaction.
 * Aman dieksekusi berulang kali tanpa duplikasi data.
 */
class MasterFormSeeder extends Seeder
{
    use WithoutModelEvents;

    /* ------------------------------------------------------------------
     | DATA: Jenis Kesalahan / Medication Error Types
     | Kolom: nama, is_aktif, created_at, updated_at
     | ----------------------------------------------------------------*/

    private const JENIS_KESALAHAN = [
        'Salah Pasien',
        'Salah Obat',
        'Salah Dosis',
        'Salah Frekuensi/Interval',
        'Salah Formula',
        'Salah Rute',
        'Salah Nomor',
        'Salah Label',
        'Kontraindikasi',
        'Salah Penyimpanan',
        'Obat Terlewat/Tidak Diberikan',
        'Obat Kadaluarsa',
        'Reaksi Obat Merugikan (ROM)',
    ];

    /* ------------------------------------------------------------------
     | DATA: Tipe Cedera / Injuries
     | Kolom: nama, is_aktif, created_at, updated_at
     | ----------------------------------------------------------------*/

    private const TIPE_CEDERA = [
        'Tidak Ada Cedera',
        'Luka Bakar',
        'Edema',
        'Hipoksia',
        'Kegagalan Jalur IV',
        'Blister (Gelembung Berisi Cairan di Kulit)',
        'Perubahan Kesadaran',
        'Hematoma',
        'Nyeri',
        'Mual',
        'Kehilangan Darah',
        'Meninggal',
        'Gatal-gatal',
        'Infiltrasi/Ekstravasasi',
        'Perubahan Nilai Lab Signifikan',
        'Perubahan TTV',
    ];

    /* ------------------------------------------------------------------
     | DATA: Faktor Penyebab / Contributing Factors
     | Kolom: nama, is_aktif, created_at, updated_at
     | ----------------------------------------------------------------*/

    private const FAKTOR_PENYEBAB = [
        'Alergi Obat Tidak Tercatat',
        'Kesalahan Perhitungan Dosis',
        'Tidak Mengikuti SPO/Prosedur',
        'Tidak Baca Label Etiket Obat',
        'Resep Obat Tidak Terbaca',
        'Monitoring Tidak Adekuat',
        'Defisit Pengetahuan',
        'Label Etiket Obat Tidak Terbaca',
        'Tidak Diresepkan',
        'Masalah Stok',
        'Distribusi Obat',
        'Salah Baca/Interpretasi Resep',
        'Alat Kesehatan Rusak/Tidak Layak Pakai',
        'Ada Distraksi (Manusia)',
    ];

    /* ------------------------------------------------------------------
     | DATA: Tindakan Intervensi / Patient Interventions
     | Kolom: nama, is_aktif, created_at, updated_at
     | ----------------------------------------------------------------*/

    private const TINDAKAN_INTERVENSI = [
        'Transfusi Darah',
        'Konsultasi ke DPJP',
        'Konsultasi ke Apoteker',
        'Intubasi',
        'Dibawa ke IGD',
        'Lama Rawat Bertambah',
        'Melakukan Uji Lab Tambahan',
        'Kunjungan Tambahan',
        'Tingkatkan Monitoring',
        'Dibawa ke OK',
        'Memerlukan Pengobatan Tambahan',
        'Prosedur Tambahan Dilakukan',
        'Dilakukan Rawat Inap',
        'Transfer ke ICU',
    ];

    /* ------------------------------------------------------------------
     | DATA: Kategori Kesalahan / NCC MERP Categories A–I
     | Standar: National Coordinating Council for Medication Error
     |          Reporting and Prevention (NCC MERP)
     | Kolom: nama_kategori, created_at
     | CATATAN: Tabel ini TIDAK memiliki kolom updated_at dan is_aktif.
     | ----------------------------------------------------------------*/

    private const KATEGORI_KESALAHAN = [
        'Kategori A' => 'Keadaan atau kondisi yang berpotensi menyebabkan kesalahan',
        'Kategori B' => 'Terjadi kesalahan, tetapi tidak sampai mencapai pasien',
        'Kategori C' => 'Terjadi kesalahan, mencapai pasien, tetapi tidak menyebabkan bahaya',
        'Kategori D' => 'Terjadi kesalahan, mencapai pasien, memerlukan monitoring, tidak ada bahaya',
        'Kategori E' => 'Terjadi kesalahan, menyebabkan bahaya sementara, memerlukan intervensi',
        'Kategori F' => 'Terjadi kesalahan, menyebabkan bahaya sementara, memerlukan rawat inap atau perpanjangan',
        'Kategori G' => 'Terjadi kesalahan, menyebabkan bahaya permanen pada pasien',
        'Kategori H' => 'Terjadi kesalahan, menyebabkan kondisi kritis atau mengancam jiwa',
        'Kategori I' => 'Terjadi kesalahan, berkontribusi atau menyebabkan kematian pasien',
    ];

    /* ------------------------------------------------------------------
     | DATA: Matriks Dampak / Impact Matrix
     | Skala risiko klinis: 1 (terendah) → 5 (tertinggi)
     | Kolom: tingkat (int), deskripsi
     | CATATAN: Tabel ini TIDAK memiliki kolom updated_at dan is_aktif.
     | ----------------------------------------------------------------*/

    private const MATRIKS_DAMPAK = [
        1 => 'Tidak Signifikan — Tidak ada cedera, tidak perlu penanganan tambahan',
        2 => 'Minor — Cedera ringan, dapat ditangani di unit, tidak memerlukan rawat inap',
        3 => 'Moderat — Cedera sedang, memerlukan intervensi medis dan rawat inap',
        4 => 'Mayor — Cedera serius, mengancam fungsi organ, memerlukan penanganan intensif',
        5 => 'Katastropik — Kematian pasien atau cacat permanen permanen',
    ];

    /* ------------------------------------------------------------------
     | DATA: Matriks Probabilitas / Probability Matrix
     | Skala frekuensi kejadian: 1 (sangat jarang) → 5 (sangat sering)
     | Kolom: tingkat (int), deskripsi
     | CATATAN: Tabel ini TIDAK memiliki kolom updated_at dan is_aktif.
     | ----------------------------------------------------------------*/

    private const MATRIKS_PROBABILITAS = [
        1 => 'Sangat Jarang — Dapat terjadi dalam kondisi luar biasa (< 1 kali/tahun)',
        2 => 'Jarang — Tidak mungkin terjadi, tetapi pernah terjadi (1–2 kali/tahun)',
        3 => 'Kadang-kadang — Dapat terjadi sewaktu-waktu (3–5 kali/tahun)',
        4 => 'Sering — Kemungkinan besar akan terjadi dalam kondisi normal (> 6 kali/tahun)',
        5 => 'Sangat Sering — Hampir pasti terjadi berulang kali (> 1 kali/bulan)',
    ];

    /**
     * Seed the database.
     */
    public function run(): void
    {
        $tenant = Organisasi::first();

        if (! $tenant) {
            $this->command->warn('Tidak ada organisasi ditemukan. Jalankan OrganisasiSeeder terlebih dahulu.');
            return;
        }

        $tenantId = $tenant->id;
        $now      = now();

        DB::transaction(function () use ($tenantId, $now): void {

            // ── 1. Jenis Kesalahan ───────────────────────────────────────
            foreach (self::JENIS_KESALAHAN as $nama) {
                DB::table('master.jenis_kesalahan')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  ✓ Jenis Kesalahan: ' . count(self::JENIS_KESALAHAN) . ' data berhasil di-seed.');

            // ── 2. Tipe Cedera ───────────────────────────────────────────
            foreach (self::TIPE_CEDERA as $nama) {
                DB::table('master.tipe_cedera')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  ✓ Tipe Cedera: ' . count(self::TIPE_CEDERA) . ' data berhasil di-seed.');

            // ── 3. Faktor Penyebab ───────────────────────────────────────
            foreach (self::FAKTOR_PENYEBAB as $nama) {
                DB::table('master.faktor_penyebab')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  ✓ Faktor Penyebab: ' . count(self::FAKTOR_PENYEBAB) . ' data berhasil di-seed.');

            // ── 4. Tindakan Intervensi ───────────────────────────────────
            foreach (self::TINDAKAN_INTERVENSI as $nama) {
                DB::table('master.tindakan_intervensi')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama' => $nama],
                    ['is_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $this->command->info('  ✓ Tindakan Intervensi: ' . count(self::TINDAKAN_INTERVENSI) . ' data berhasil di-seed.');

            // ── 5. Kategori Kesalahan (NCC MERP A–I) ────────────────────
            // Kolom lookup: nama_kategori — tidak ada updated_at di tabel ini.
            foreach (self::KATEGORI_KESALAHAN as $kode => $deskripsi) {
                DB::table('master.kategori_kesalahan')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nama_kategori' => $kode],
                    ['created_at' => $now],
                );
            }
            $this->command->info('  ✓ Kategori Kesalahan (NCC MERP): ' . count(self::KATEGORI_KESALAHAN) . ' data berhasil di-seed.');

            // ── 6. Matriks Dampak ────────────────────────────────────────
            // Kolom lookup: tingkat — tidak ada updated_at di tabel ini.
            foreach (self::MATRIKS_DAMPAK as $tingkat => $deskripsi) {
                DB::table('master.matriks_dampak')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'tingkat' => $tingkat],
                    ['deskripsi' => $deskripsi, 'created_at' => $now],
                );
            }
            $this->command->info('  ✓ Matriks Dampak: ' . count(self::MATRIKS_DAMPAK) . ' data berhasil di-seed.');

            // ── 7. Matriks Probabilitas ──────────────────────────────────
            // Kolom lookup: tingkat — tidak ada updated_at di tabel ini.
            foreach (self::MATRIKS_PROBABILITAS as $tingkat => $deskripsi) {
                DB::table('master.matriks_probabilitas')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'tingkat' => $tingkat],
                    ['deskripsi' => $deskripsi, 'created_at' => $now],
                );
            }
            $this->command->info('  ✓ Matriks Probabilitas: ' . count(self::MATRIKS_PROBABILITAS) . ' data berhasil di-seed.');
        });
    }
}
