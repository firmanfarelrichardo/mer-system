<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\KategoriKesalahan;
use App\Models\Organisasi;
use App\Models\Pengguna;
use App\Models\Peran;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ProductionInsidenSeeder - 100 laporan insiden medication error sepanjang 2025.
 *
 * Data laporan tersebar secara acak dari 1 Januari hingga 31 Desember 2025,
 * menggunakan nama staf RSUD HM. Ryacudu Kotabumi sebagai pelapor.
 *
 * DISTRIBUSI STATUS (dilihat dari tanggal seed: 10 Maret 2026):
 *   Laporan Jan–Mar 2025 (>360 hari)  → 65% selesai, 20% tindak_lanjut, ...
 *   Laporan Apr–Jun 2025 (270–360 hr) → 50% selesai, 30% tindak_lanjut, ...
 *   Laporan Jul–Sep 2025 (180–270 hr) → 30% selesai, 35% tindak_lanjut, ...
 *   Laporan Okt–Nov 2025 (90–180 hr)  → 20% selesai, 30% tindak_lanjut, ...
 *   Laporan Des 2025     (<90 hari)   → 15% selesai, 20% tindak_lanjut, ...
 *
 * DEPENDENSI (jalankan setelah seeder-seeder ini):
 *   1. OrganisasiSeeder
 *   2. PeranSeeder
 *   3. UnitKerjaSeeder
 *   4. MasterFormSeeder  (atau InsidenSeeder - untuk master kategori/matriks)
 *   5. ProductionPenggunaSeeder
 *
 * IDEMPOTENT: insertOrIgnore pada nomor_laporan (PROD-2025-XXXX) + cek
 * detail_pasien sebelum menyisipkan child records. Aman dijalankan ulang.
 *
 * Nomor laporan PROD-2025-XXXX sengaja BERBEDA dengan format INC-YYYYMMDD-...
 * milik InsidenSeeder agar tidak pernah bentrok meski kedua seeder dijalankan
 * pada database yang sama.
 *
 * Jalankan:
 *   php artisan db:seed --class=ProductionInsidenSeeder
 */
class ProductionInsidenSeeder extends Seeder
{
    use WithoutModelEvents;

    /* ---------------------------------------------------------------
     | Konstanta Domain
     | ------------------------------------------------------------*/

    private const TIPE_INSIDEN = ['KPC', 'KNC', 'KTC', 'KTD', 'SENTINEL'];

    /**
     * Bobot per tipe (searah TIPE_INSIDEN).
     * KNC paling sering di rumah sakit ini berdasarkan pola pelaporan riil.
     */
    private const BOBOT_TIPE = [25, 30, 22, 16, 7];

    private const FASE_KESALAHAN = ['prescribing', 'transcribing', 'dispensing', 'administration'];

    private const STATUS_URUT = ['kasus_baru', 'investigasi', 'tindak_lanjut', 'selesai'];

    private const NAMA_OBAT = [
        'Amoxicillin 500 mg',         'Metformin 500 mg',           'Amlodipine 10 mg',
        'Paracetamol 500 mg',         'Omeprazole 20 mg',           'Furosemide 40 mg',
        'Atorvastatin 20 mg',         'Captopril 25 mg',            'Heparin 5000 IU/mL',
        'Insulin Glargine 100 IU/mL', 'Digoxin 0,25 mg',           'Warfarin 2 mg',
        'Tramadol 50 mg',             'Ondansetron 4 mg',           'Alprazolam 0,5 mg',
        'Ranitidine 150 mg',          'Domperidone 10 mg',          'Ceftriaxone 1 g IV',
        'Vancomycin 500 mg IV',       'Morfin 10 mg/mL',            'KCl 7,46% IV',
        'Dexamethasone 5 mg/mL',      'Lorazepam 2 mg/mL IV',       'Salbutamol Inhaler 100 mcg',
        'Metronidazole 500 mg',       'Ciprofloxacin 500 mg',        'Codeine 30 mg',
        'Dopamin 200 mg/5 mL',        'Norepinefrin 4 mg/4 mL IV',  'Oxytocin 10 IU/mL',
        'MgSO4 20% 25 mL',            'Amiodarone 150 mg/3 mL',     'Enoxaparin 40 mg',
    ];

    private const NAMA_DOKTER = [
        'dr. Andi Prasetyo, Sp.PD',      'dr. Siti Rahayu, Sp.An',
        'dr. Budi Santoso, Sp.B',         'dr. Dewi Kurniawati, Sp.A',
        'dr. Hendra Susanto, Sp.JP',      'dr. Rina Wijaya, Sp.N',
        'dr. Yusuf Hakim, Sp.OG',         'dr. Laila Putri, Sp.RM',
        'dr. Deni Setiawan, Sp.M',        'dr. Fitri Handayani, Sp.THT',
        'dr. Agus Pratama, Sp.KJ',        'dr. Murni Lestari, Sp.GK',
        'dr. Reza Fahlevi, Sp.P',         'dr. Nurhasanah, Sp.KK',
        'dr. Bambang Wicaksono, Sp.Ort',  'dr. Haryanti Putri, Sp.An',
    ];

    private const DOSIS_POOL = [
        '500mg', '250mg', '1000mg', '10mg/mL', '50mg', '5mg',
        '200mg', '100mg', '20mg', '1g', '2mg/mL', '40mg',
    ];

    private const JENIS_KESALAHAN_POOL = [
        'Salah obat',                'Salah dosis',           'Salah pasien',
        'Salah rute pemberian',      'Salah waktu pemberian', 'Salah konsentrasi',
        'Obat kadaluarsa',           'Duplikasi terapi',      'Interaksi obat berbahaya',
        'Omisi (obat tidak diberikan)', 'Kesalahan rekonsiliasi', 'Salah label/kemasan',
    ];

    private const CEDERA_POOL = [
        'Tidak ada cedera',                        'Tidak ada cedera, kondisi dipantau',
        'Cedera ringan sementara',                 'Cedera sedang memerlukan intervensi medis',
        'Hampir cedera (near-miss)',               'Reaksi alergi ringan',
        'Hipoglikemia',                            'Hipotensi',
        'Peningkatan waktu rawat inap',
    ];

    private const FAKTOR_PENYEBAB_POOL = [
        'Beban kerja tinggi',               'Kelelahan petugas',
        'Komunikasi tidak efektif',         'Sistem labeling kurang jelas',
        'Obat LASA (Look-Alike Sound-Alike)', 'Gangguan saat persiapan obat',
        'Tidak ada double-check',           'Pengetahuan kurang',
        'Prosedur tidak diikuti',           'Faktor lingkungan (pencahayaan buruk)',
        'Sistem komputerisasi error',       'Kekurangan tenaga pada shift',
    ];

    private const INTERVENSI_POOL = [
        'Monitoring ketat tanda vital',        'Pemberian antidot',
        'Penghentian obat segera',             'Konsultasi dokter spesialis',
        'Pemeriksaan laboratorium ulang',      'Penggantian obat',
        'Edukasi ulang pasien dan keluarga',   'Pemasangan infus cairan',
        'Observasi 24 jam',                    'Tidak diperlukan intervensi',
        'Penanganan reaksi alergi (IV antihistamin)', 'Koreksi elektrolit',
    ];

    /**
     * Template kronologi - jumlah %s harus konsisten dengan sprintf di generateKronologi().
     * Template 0–1: 5 parameter. Template 2–4: 4 parameter.
     */
    private const KRONOLOGI_TEMPLATE = [
        // [0] 5 param: obat, namaPasien, namaUnit, fase, jenisKesalahan
        'Petugas menyiapkan obat %s untuk pasien %s di %s. Pada saat %s, ditemukan terjadi %s. Kondisi segera dilaporkan kepada penanggung jawab shift untuk penanganan lebih lanjut.',
        // [1] 5 param: namaPasien, obat, fase, namaUnit, jenisKesalahan
        'Pasien %s menerima obat %s yang disiapkan oleh petugas farmasi. Saat tahap %s di %s, terjadi insiden berupa %s. Petugas segera mengambil tindakan dan menghubungi dokter jaga.',
        // [2] 4 param: namaUnit, obat, namaPasien, jenisKesalahan
        'Pada jam pergantian shift di %s, obat %s untuk pasien %s mengalami %s. Dokter jaga segera dihubungi dan tindakan korektif dilakukan sesuai protokol RS.',
        // [3] 4 param: fase, obat, namaPasien, jenisKesalahan
        'Proses %s obat %s mengalami penyimpangan. Perawat menemukan bahwa pasien %s mengalami %s. Kejadian dilaporkan ke komite keselamatan sesuai alur pelaporan SOP.',
        // [4] 4 param: namaUnit, obat, namaPasien, jenisKesalahan
        'Di %s, obat %s disiapkan untuk pasien %s. Insiden berupa %s teridentifikasi oleh petugas sebelum/sesudah pemberian dan penanganan segera diberikan.',
    ];

    private const AKAR_MASALAH_POOL = [
        'Kegagalan sistem komunikasi antar-shift menyebabkan instruksi dokter tidak tersampaikan dengan jelas kepada perawat pelaksana di RSUD HM. Ryacudu.',
        'Tidak tersedianya prosedur double-check yang baku untuk obat risiko tinggi di unit ini, sehingga verifikasi sebelum pemberian tidak selalu dilakukan.',
        'Kesamaan nama dan kemasan obat (LASA) di lemari penyimpanan berakibat pada pengambilan obat yang salah oleh petugas.',
        'Beban kerja berlebih dan kekurangan tenaga pada shift malam menjadi kontributor utama kesalahan identifikasi pasien.',
        'Kurangnya pelatihan rutin tentang penanganan obat high-alert menyebabkan kesalahan penghitungan dosis infus oleh perawat.',
        'Pelabelan obat yang tidak standar di instalasi farmasi menyebabkan obat dikirimkan ke unit yang tidak tepat.',
        'Kesalahan transkripsi resep akibat tulisan dokter yang sulit dibaca dan tidak adanya proses konfirmasi sebelum dispensing.',
        'Sistem informasi RS mengalami gangguan teknis sehingga instruksi orderan tidak terbaca secara real-time oleh perawat pelaksana.',
        'Proses rekonsiliasi obat saat admisi tidak dilakukan secara menyeluruh sehingga duplikasi terapi tidak terdeteksi sejak awal.',
    ];

    private const REKOMENDASI_POOL = [
        'Implementasi sistem barcode scanning untuk verifikasi identitas pasien dan obat sebelum setiap tindakan pemberian obat.',
        'Mewajibkan prosedur double-check oleh dua perawat untuk seluruh obat high-alert sesuai daftar ISMP yang berlaku.',
        'Pisahkan penyimpanan obat LASA dengan tall-man lettering berwarna dan label peringatan yang mencolok.',
        'Pengaturan ulang beban kerja serta penambahan tenaga perawat pada shift malam, terutama di unit kritis (ICU, IGD, IBS).',
        'Pelatihan dan simulasi penanganan medication error secara berkala setiap 6 bulan untuk seluruh staf klinis.',
        'Penerapan sistem CPOE (Computerized Physician Order Entry) terintegrasi untuk meminimalkan kesalahan transkripsi resep.',
        'Audit berkala manajemen penyimpanan obat di semua unit dan penerapan sistem 5R di area farmasi.',
        'Sosialisasi budaya pelaporan insiden tanpa sanksi hukuman agar seluruh staf lebih proaktif melaporkan kejadian.',
        'Standarisasi formulir rekonsiliasi obat dan mewajibkan pengisian saat admisi, transfer, dan discharge pasien.',
    ];

    private const CATATAN_TINDAK_LANJUT = [
        'investigasi' => [
            'Laporan diterima dan ditelaah oleh Komite Keselamatan Pasien. Tim investigasi telah dibentuk untuk analisis mendalam.',
            'Investigasi awal menunjukkan adanya faktor sistem yang berkontribusi. Pengumpulan bukti dan wawancara saksi sedang berlangsung.',
            'Dokumentasi awal lengkap. Estimasi penyelesaian investigasi 14 hari sesuai standar akreditasi RS.',
        ],
        'tindak_lanjut' => [
            'RCA selesai dilakukan. Rekomendasi perbaikan telah ditetapkan dan didistribusikan kepada kepala unit terkait.',
            'Hasil investigasi dibahas dalam rapat Komite Keselamatan Pasien. Tindakan korektif dalam proses implementasi.',
            'SOP baru telah disusun berdasarkan temuan RCA. Pelatihan staf dijadwalkan dalam 2 minggu.',
        ],
        'selesai' => [
            'Seluruh tindakan korektif telah diimplementasikan dan terverifikasi oleh komite. Kasus dinyatakan selesai.',
            'Evaluasi efektivitas perbaikan selesai dilakukan. Tidak ada rekurensi dalam 90 hari terakhir. Kasus ditutup.',
            'Laporan final telah dicetak, ditandatangani, dan diarsipkan. Pembelajaran disiseminasikan ke seluruh unit RS.',
        ],
    ];

    /* ---------------------------------------------------------------
     | Entry Point
     | ------------------------------------------------------------*/

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        $this->command->info('  ▶ Memuat master data (unit kerja, kategori, matriks)...');
        [$unitKerja, $kategori, $dampakList, $probabilitasList] = $this->loadMasterData($tenant->id);

        $this->command->info('  ▶ Memuat akun production berdasarkan peran...');
        [$nakesList, $karuList, $komiteList] = $this->loadUsers($tenant->id);

        if (empty($nakesList) && empty($karuList)) {
            throw new \RuntimeException(
                'Tidak ditemukan akun Nakes atau Kepala Ruangan. ' .
                'Jalankan ProductionPenggunaSeeder terlebih dahulu.'
            );
        }

        $this->command->info('  ▶ Menyisipkan 100 laporan insiden medication error (2025)...');
        $this->seedInsiden(
            $tenant->id,
            $unitKerja,
            $kategori,
            $dampakList,
            $probabilitasList,
            $nakesList,
            $karuList,
            $komiteList,
        );

        $this->command->info('  ✔ ProductionInsidenSeeder selesai.');
    }

    /* ---------------------------------------------------------------
     | 1. Load Master Data
     | ------------------------------------------------------------*/

    private function loadMasterData(int $tenantId): array
    {
        // Unit Kerja
        $unitRows = DB::table('master.unit_kerja')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        if ($unitRows->isEmpty()) {
            throw new \RuntimeException('Unit kerja kosong. Pastikan UnitKerjaSeeder sudah dijalankan.');
        }

        $unitKerja = [];
        foreach ($unitRows as $unit) {
            $unitKerja[$unit->kode_unit] = $unit;
        }

        // Kategori Kesalahan
        $kategori = KategoriKesalahan::where('tenant_id', $tenantId)->get()->all();
        if (empty($kategori)) {
            throw new \RuntimeException(
                'Kategori kesalahan kosong. ' .
                'Pastikan MasterFormSeeder atau InsidenSeeder sudah dijalankan.'
            );
        }

        // Matriks Dampak (1–5)
        $dampakList = [];
        foreach (
            DB::table('master.matriks_dampak')
                ->where('tenant_id', $tenantId)
                ->orderBy('tingkat')
                ->get() as $d
        ) {
            $dampakList[$d->tingkat] = $d;
        }

        if (empty($dampakList)) {
            throw new \RuntimeException('Matriks dampak kosong. Pastikan InsidenSeeder sudah dijalankan.');
        }

        // Matriks Probabilitas (1–5)
        $probabilitasList = [];
        foreach (
            DB::table('master.matriks_probabilitas')
                ->where('tenant_id', $tenantId)
                ->orderBy('tingkat')
                ->get() as $p
        ) {
            $probabilitasList[$p->tingkat] = $p;
        }

        if (empty($probabilitasList)) {
            throw new \RuntimeException('Matriks probabilitas kosong. Pastikan InsidenSeeder sudah dijalankan.');
        }

        return [$unitKerja, $kategori, $dampakList, $probabilitasList];
    }

    /* ---------------------------------------------------------------
     | 2. Load Users by Role
     | ------------------------------------------------------------*/

    private function loadUsers(int $tenantId): array
    {
        $nakesList = Pengguna::where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::NAKES))
            ->get()
            ->all();

        $karuList = Pengguna::where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::KEPALA_RUANGAN))
            ->get()
            ->all();

        $komiteList = Pengguna::where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::KOMITE))
            ->get()
            ->all();

        $this->command->line(sprintf(
            '    Nakes: %d | Kepala Ruangan: %d | Komite: %d',
            count($nakesList),
            count($karuList),
            count($komiteList),
        ));

        return [$nakesList, $karuList, $komiteList];
    }

    /* ---------------------------------------------------------------
     | 3. Seed 100 Insiden
     | ------------------------------------------------------------*/

    private function seedInsiden(
        int $tenantId,
        array $unitKerja,
        array $kategori,
        array $dampakList,
        array $probabilitasList,
        array $nakesList,
        array $karuList,
        array $komiteList,
    ): void {
        // Rentang waktu: seluruh tahun 2025.
        $awal2025     = Carbon::parse('2025-01-01 00:00:00');
        $akhir2025    = Carbon::parse('2025-12-31 23:59:59');

        // Tanggal referensi untuk menghitung usia laporan = tanggal seeder dijalankan.
        $tglReferensi = Carbon::parse('2026-03-10');

        $unitList      = array_values($unitKerja);
        // Gabungkan nakes + karu sebagai kandidat pelapor.
        $pelaporPool   = array_merge($nakesList, $karuList);

        $inserted = 0;
        $skipped  = 0;

        for ($i = 1; $i <= 100; $i++) {
            $nomorLaporan = sprintf('PROD-2025-%04d', $i);

            // ── Tanggal & waktu acak dalam 2025 ─────────────────────────
            $tglKejadian = Carbon::createFromTimestamp(
                random_int($awal2025->timestamp, $akhir2025->timestamp)
            );
            // Laporan dibuat 15 menit–8 jam setelah kejadian.
            $tglLapor = $tglKejadian->copy()->addMinutes(random_int(15, 480));

            // ── Pelapor & unit acak ──────────────────────────────────────
            $pelapor  = $pelaporPool[array_rand($pelaporPool)];
            $unit     = $unitList[array_rand($unitList)];

            // 30% laporan bersifat anonim (umum di RS).
            $isAnonim = (random_int(1, 10) <= 3);

            // ── Tipe insiden berbobot & fase acak ────────────────────────
            $tipe = $this->pilihBerbobot(self::TIPE_INSIDEN, self::BOBOT_TIPE);
            $fase = self::FASE_KESALAHAN[array_rand(self::FASE_KESALAHAN)];

            // ── Status berdasarkan usia laporan ──────────────────────────
            $usiaHari = (int) $tglReferensi->diffInDays($tglKejadian);
            $status   = $this->tentukanStatus($usiaHari);

            // ── Eskalasi Direktur (hanya KTD/SENTINEL, ~20% dari keduanya) ──
            // Kasus parah kadang dieskalasi ke direktur untuk tindakan strategis.
            $isEskalasi          = in_array($tipe, ['KTD', 'SENTINEL'], true) && random_int(1, 10) <= 2;
            $solusiDirektur      = null;
            $waktuSolusiDirektur = null;

            if ($isEskalasi && $status === 'selesai') {
                $waktuSolusiDirektur = $tglLapor->copy()->addDays(random_int(3, 30));
                $solusiDirektur      = 'Direktur telah memberikan arahan penanganan dan meminta laporan investigasi lengkap dalam 14 hari kepada Komite Keselamatan Pasien.';
            }

            // ── Insert insiden (ON CONFLICT DO NOTHING) ──────────────────
            $rowsAffected = DB::table('pelaporan.insiden')->insertOrIgnore([
                'tenant_id'            => $tenantId,
                'nomor_laporan'        => $nomorLaporan,
                'pelapor_id'           => $pelapor->id,
                'unit_id'              => $unit->id,
                'nama_unit_kerja'      => $unit->nama_unit,
                'tipe_insiden'         => $tipe,
                'fase_kesalahan'       => $fase,
                'status_saat_ini'      => $status,
                'tgl_kejadian'         => $tglKejadian,
                'tgl_lapor'            => $tglLapor,
                'nama_pelapor'         => $isAnonim ? null : $pelapor->nama_lengkap,
                'kontak_pelapor'       => $isAnonim ? null : $pelapor->nomor_hp,
                'is_anonim'            => $isAnonim,
                // Laporan dianggap sudah dibaca jika sudah melewati kasus_baru.
                'sudah_dibaca'         => ($status !== 'kasus_baru'),
                'is_eskalasi_direktur' => $isEskalasi,
                'solusi_direktur'      => $solusiDirektur,
                'waktu_solusi_direktur'=> $waktuSolusiDirektur,
                'created_at'           => $tglLapor,
                'updated_at'           => $tglLapor,
            ]);

            // Ambil ID insiden (baik baru maupun yang sudah ada).
            $insidenId = DB::table('pelaporan.insiden')
                ->where('tenant_id', $tenantId)
                ->where('nomor_laporan', $nomorLaporan)
                ->value('id');

            if ($rowsAffected === 0) {
                // Sudah ada dari run sebelumnya - lewati seluruh child records.
                $skipped++;
                continue;
            }

            $inserted++;

            // ── 4. Detail Pasien ─────────────────────────────────────────
            $obat           = self::NAMA_OBAT[array_rand(self::NAMA_OBAT)];
            $dokter         = self::NAMA_DOKTER[array_rand(self::NAMA_DOKTER)];
            $namaPasien     = $this->namaAcak();
            $nomorRM        = sprintf('%06d-%04d', random_int(100000, 999999), random_int(0, 9999));
            $jenis          = $this->ambilAcak(self::JENIS_KESALAHAN_POOL, random_int(1, 3));
            $cedera         = $this->ambilAcak(self::CEDERA_POOL, random_int(1, 2));
            $faktor         = $this->ambilAcak(self::FAKTOR_PENYEBAB_POOL, random_int(1, 3));
            $intervensi     = $this->ambilAcak(self::INTERVENSI_POOL, random_int(1, 2));
            $kronologi      = $this->generateKronologi($obat, $namaPasien, $unit->nama_unit, $fase, $jenis[0]);

            DB::table('pelaporan.detail_pasien')->insert([
                'insiden_id'           => $insidenId,
                'nama_pasien'          => $namaPasien,
                'nomor_rekam_medis'    => $nomorRM,
                'obat_terkait'         => $obat,
                'dosis_obat'           => self::DOSIS_POOL[array_rand(self::DOSIS_POOL)],
                'dokter_penulis_resep' => $dokter,
                'kronologi'            => $kronologi,
                'tindakan_awal'        => self::INTERVENSI_POOL[array_rand(self::INTERVENSI_POOL)],
                'jenis_kesalahan'      => json_encode(array_values($jenis)),
                'cedera'               => json_encode(array_values($cedera)),
                'faktor_penyebab'      => json_encode(array_values($faktor)),
                'intervensi_pasien'    => json_encode(array_values($intervensi)),
                'pernyataan_kronologi' => true,
                'created_at'           => $tglLapor,
            ]);

            // ── 5. Kategori Insiden (1–3 per laporan) ───────────────────
            foreach ($this->ambilAcak($kategori, random_int(1, 3)) as $kat) {
                DB::table('pelaporan.insiden_kategori')->insertOrIgnore([
                    'insiden_id'  => $insidenId,
                    'kategori_id' => $kat->id,
                ]);
            }

            // ── 6. Penilaian Risiko ──────────────────────────────────────
            // Hanya dibuat jika laporan sudah melewati tahap kasus_baru.
            if (in_array($status, ['investigasi', 'tindak_lanjut', 'selesai'], true)) {
                // Kepala Ruangan menjadi penilai risiko (bukan komite).
                $penilai  = !empty($karuList)
                    ? $karuList[array_rand($karuList)]
                    : $pelaporPool[array_rand($pelaporPool)];
                $dampakTk = random_int(1, 5);
                $probTk   = random_int(1, 5);
                $skor     = $dampakTk * $probTk;

                DB::table('pelaporan.penilaian_risiko')->insert([
                    'insiden_id'      => $insidenId,
                    'penilai_id'      => $penilai->id,
                    'probabilitas_id' => $probabilitasList[$probTk]->id,
                    'dampak_id'       => $dampakList[$dampakTk]->id,
                    'warna_grading'   => $this->tentukanWarnaGrading($skor),
                    'created_at'      => $tglLapor->copy()->addDays(random_int(1, 7)),
                ]);
            }

            // ── 7. Investigasi RCA ───────────────────────────────────────
            // Dibuat untuk 80% insiden yang statusnya tindak_lanjut atau selesai.
            if (
                in_array($status, ['tindak_lanjut', 'selesai'], true)
                && random_int(1, 10) <= 8
            ) {
                // Komite yang mengerjakan RCA. Fallback ke karu jika tak ada komite.
                $investigator = !empty($komiteList)
                    ? $komiteList[array_rand($komiteList)]
                    : (!empty($karuList) ? $karuList[array_rand($karuList)] : null);

                if ($investigator !== null) {
                    DB::table('pelaporan.investigasi_rca')->insert([
                        'insiden_id'         => $insidenId,
                        'investigator_id'    => $investigator->id,
                        'akar_masalah'       => self::AKAR_MASALAH_POOL[array_rand(self::AKAR_MASALAH_POOL)],
                        'rekomendasi_sistem' => self::REKOMENDASI_POOL[array_rand(self::REKOMENDASI_POOL)],
                        'created_at'         => $tglLapor->copy()->addDays(random_int(7, 21)),
                    ]);
                }
            }

            // ── 8. Histori Tindak Lanjut ────────────────────────────────
            $this->seedTindakLanjut($insidenId, $status, $tglLapor, $karuList, $komiteList);
        }

        $this->command->line(sprintf(
            '    ✓ Disisipkan: %d laporan | Dilewati (sudah ada): %d laporan',
            $inserted,
            $skipped,
        ));
    }

    /* ---------------------------------------------------------------
     | 8. Histori Tindak Lanjut
     | ------------------------------------------------------------*/

    private function seedTindakLanjut(
        int $insidenId,
        string $statusAkhir,
        Carbon $tglLapor,
        array $karuList,
        array $komiteList,
    ): void {
        $indeksAkhir = (int) array_search($statusAkhir, self::STATUS_URUT, true);
        $tglTransisi = $tglLapor->copy();

        // Buat satu record tindak_lanjut per transisi status (mulai dari investigasi).
        for ($step = 1; $step <= $indeksAkhir; $step++) {
            $statusBaru  = self::STATUS_URUT[$step];

            // Selang waktu realistis antar tahapan: 3–14 hari.
            $tglTransisi = $tglTransisi->copy()->addDays(random_int(3, 14));

            // Status selesai dikerjakan oleh Komite; tahap lain oleh Kepala Ruangan.
            if ($statusBaru === 'selesai' && !empty($komiteList)) {
                $pengguna = $komiteList[array_rand($komiteList)];
            } elseif (!empty($karuList)) {
                $pengguna = $karuList[array_rand($karuList)];
            } elseif (!empty($komiteList)) {
                $pengguna = $komiteList[array_rand($komiteList)];
            } else {
                continue; // Tidak ada user tersedia - lewati step ini.
            }

            $pool = self::CATATAN_TINDAK_LANJUT[$statusBaru] ?? ['Kasus diproses sesuai SOP.'];

            DB::table('pelaporan.tindak_lanjut')->insert([
                'insiden_id'  => $insidenId,
                'pengguna_id' => $pengguna->id,
                'status_baru' => $statusBaru,
                'catatan'     => $pool[array_rand($pool)],
                'created_at'  => $tglTransisi,
            ]);
        }
    }

    /* ---------------------------------------------------------------
     | Helper Methods
     | ------------------------------------------------------------*/

    /**
     * Pilih satu item dari array berdasarkan bobot probabilitas (weighted random).
     *
     * @param string[] $items
     * @param int[]    $bobot  (indeks searah dengan $items)
     */
    private function pilihBerbobot(array $items, array $bobot): string
    {
        $total     = array_sum($bobot);
        $acak      = random_int(1, $total);
        $kumulatif = 0;

        foreach ($items as $i => $item) {
            $kumulatif += $bobot[$i];
            if ($acak <= $kumulatif) {
                return $item;
            }
        }

        return $items[count($items) - 1];
    }

    /**
     * Ambil $n elemen unik acak dari array (keys di-reset ke 0-based).
     *
     * @param array<mixed> $sumber
     * @return array<mixed>
     */
    private function ambilAcak(array $sumber, int $n): array
    {
        $n    = min($n, count($sumber));
        $keys = array_rand($sumber, $n);
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        // array_map di sini menghasilkan 0-indexed array karena
        // $keys adalah 0-indexed array (asli dari array_rand pada integer-indexed array).
        return array_map(fn ($k) => $sumber[$k], $keys);
    }

    /**
     * Generate kronologi berdasarkan template acak.
     * Setiap template memiliki jumlah %s yang sudah ditentukan (lihat konstanta).
     */
    private function generateKronologi(
        string $obat,
        string $namaPasien,
        string $namaUnit,
        string $fase,
        string $jenisKesalahan,
    ): string {
        $idx = array_rand(self::KRONOLOGI_TEMPLATE);
        $tpl = self::KRONOLOGI_TEMPLATE[$idx];

        return match ($idx) {
            0 => sprintf($tpl, $obat, $namaPasien, $namaUnit, ucfirst($fase), strtolower($jenisKesalahan)),
            1 => sprintf($tpl, $namaPasien, $obat, ucfirst($fase), $namaUnit, strtolower($jenisKesalahan)),
            2 => sprintf($tpl, $namaUnit, $obat, $namaPasien, strtolower($jenisKesalahan)),
            3 => sprintf($tpl, ucfirst($fase), $obat, $namaPasien, strtolower($jenisKesalahan)),
            4 => sprintf($tpl, $namaUnit, $obat, $namaPasien, strtolower($jenisKesalahan)),
            // Fallback aman jika template bertambah di masa depan.
            default => sprintf(
                'Insiden %s ditemukan pada pemberian obat %s kepada pasien %s di %s.',
                strtolower($jenisKesalahan), $obat, $namaPasien, $namaUnit
            ),
        };
    }

    /**
     * Tentukan status laporan berdasarkan usia (hari) dihitung dari 10 Maret 2026.
     *
     * Semakin tua laporan, semakin besar kemungkinan sudah selesai diproses.
     */
    private function tentukanStatus(int $usiaHari): string
    {
        // Jan–Mar 2025: 12–14 bulan → sebagian besar sudah selesai.
        if ($usiaHari > 360) {
            return $this->pilihBerbobot(self::STATUS_URUT, [3, 12, 20, 65]);
        }

        // Apr–Jun 2025: 9–12 bulan.
        if ($usiaHari > 270) {
            return $this->pilihBerbobot(self::STATUS_URUT, [5, 15, 30, 50]);
        }

        // Jul–Sep 2025: 6–9 bulan.
        if ($usiaHari > 180) {
            return $this->pilihBerbobot(self::STATUS_URUT, [10, 25, 35, 30]);
        }

        // Okt–Nov 2025: 3–6 bulan.
        if ($usiaHari > 90) {
            return $this->pilihBerbobot(self::STATUS_URUT, [20, 30, 30, 20]);
        }

        // Des 2025: <3 bulan → banyak yang masih baru atau sedang investigasi.
        return $this->pilihBerbobot(self::STATUS_URUT, [35, 30, 20, 15]);
    }

    /**
     * Tentukan warna grading risiko berdasarkan skor (probabilitas × dampak).
     */
    private function tentukanWarnaGrading(int $skor): string
    {
        return match (true) {
            $skor >= 15 => 'Merah',
            $skor >= 9  => 'Oranye',
            $skor >= 5  => 'Kuning',
            default     => 'Hijau',
        };
    }

    /**
     * Generate nama pasien Indonesia yang realistis.
     */
    private function namaAcak(): string
    {
        $depan = [
            'Agus', 'Budi', 'Citra', 'Desi', 'Eko', 'Fitri', 'Gilang',
            'Hani', 'Iwan', 'Joko', 'Kartika', 'Lina', 'Mira', 'Nanda',
            'Oki', 'Putri', 'Rudi', 'Sari', 'Tono', 'Udin', 'Vera',
            'Wahyu', 'Yanti', 'Zainal', 'Rina', 'Doni', 'Ayu', 'Adi',
            'Bayu', 'Candra', 'Dewi', 'Erna', 'Fani', 'Hendra', 'Ika',
            'Jefri', 'Kurnia', 'Lestari', 'Maya', 'Nita', 'Opik', 'Peni',
        ];

        $belakang = [
            'Santoso', 'Lestari', 'Wati', 'Sari', 'Handoko',
            'Kurniawan', 'Rahayu', 'Susilo', 'Pratiwi', 'Nugroho',
            'Rahardjo', 'Setiawan', 'Amalia', 'Purnomo', 'Hidayat',
            'Wijaya', 'Permana', 'Sukma', 'Dewantara', 'Fitriana',
        ];

        $nama = $depan[array_rand($depan)];

        // 60% nama dua kata, 40% satu kata.
        if (random_int(1, 10) <= 6) {
            $nama .= ' ' . $belakang[array_rand($belakang)];
        }

        return $nama;
    }
}
