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
use Illuminate\Support\Facades\Hash;

/**
 * InsidenSeeder — 500 laporan insiden medication error selama tiga tahun (2024-2026).
 *
 * Urutan seed:
 *   1. Master data  : kategori_kesalahan, matriks_dampak/probabilitas
 *                     (unit_kerja diambil dari UnitKerjaSeeder)
 *   2. Pengguna     : tambahan nakes & kepala ruangan per unit
 *   3. Insiden      : 500 laporan tersebar acak di rentang 3 tahun (2024-2026)
 *   4. Detail pasien: 1 record per insiden
 *   5. Kategori     : 1-3 kategori per insiden (pivot)
 *   6. Penilaian    : untuk insiden berstatus investigasi/tindak_lanjut/selesai
 *   7. Investigasi  : RCA untuk insiden berstatus tindak_lanjut/selesai
 *   8. Tindak lanjut: histori transisi status
 *
 * Idempotent: aman dijalankan berulang kali (truncate terlebih dahulu).
 */
class InsidenSeeder extends Seeder
{
    use WithoutModelEvents;

    /* ---------------------------------------------------------------
     | Konstanta Domain
     | ------------------------------------------------------------*/

    private const TIPE_INSIDEN = ['KPC', 'KNC', 'KTC', 'KTD', 'SENTINEL'];

    /**
     * Bobot probabilitas tipe insiden (indeks searah TIPE_INSIDEN).
     * KPC paling sering, Sentinel sangat jarang.
     */
    private const BOBOT_TIPE = [30, 25, 20, 15, 10];

    private const FASE_KESALAHAN = ['prescribing', 'transcribing', 'dispensing', 'administration'];

    private const STATUS_URUT = ['kasus_baru', 'investigasi', 'tindak_lanjut', 'selesai'];

    private const WARNA_GRADING = [
        'Hijau'  => [1, 2, 3, 4],
        'Kuning' => [5, 6, 8],
        'Oranye' => [9, 10, 12],
        'Merah'  => [15, 16, 20, 25],
    ];

    private const NAMA_OBAT = [
        'Amoxicillin 500 mg', 'Metformin 500 mg', 'Amlodipine 10 mg',
        'Paracetamol 500 mg', 'Omeprazole 20 mg', 'Furosemide 40 mg',
        'Atorvastatin 20 mg', 'Captopril 25 mg', 'Heparin 5000 IU/mL',
        'Insulin Glargine 100 IU/mL', 'Digoxin 0,25 mg', 'Warfarin 2 mg',
        'Tramadol 50 mg', 'Ondansetron 4 mg', 'Alprazolam 0,5 mg',
        'Ranitidine 150 mg', 'Domperidone 10 mg', 'Ceftriaxone 1 g IV',
        'Vancomycin 500 mg IV', 'Morfin 10 mg/mL', 'KCl 7,46% IV',
        'Dexamethasone 5 mg/mL', 'Lorazepam 2 mg/mL', 'Salbutamol Inhaler',
        'Metronidazole 500 mg', 'Ciprofloxacin 500 mg', 'Codeine 30 mg',
    ];

    private const NAMA_DOKTER = [
        'dr. Andi Prasetyo, Sp.PD', 'dr. Siti Rahayu, Sp.An', 'dr. Budi Santoso, Sp.B',
        'dr. Dewi Kurniawati, Sp.A', 'dr. Hendra Susanto, SpJP', 'dr. Rina Wijaya, Sp.N',
        'dr. Yusuf Hakim, Sp.OG', 'dr. Laila Putri, Sp.RM', 'dr. Deni Setiawan, Sp.M',
        'dr. Fitri Handayani, Sp.THT', 'dr. Agus Pratama, Sp.KJ', 'dr. Murni Lestari, Sp.GK',
    ];

    private const JENIS_KESALAHAN_POOL = [
        'Salah obat', 'Salah dosis', 'Salah pasien', 'Salah rute pemberian',
        'Salah waktu pemberian', 'Salah konsentrasi', 'Obat kadaluarsa',
        'Duplikasi terapi', 'Interaksi obat', 'Omisi (obat tidak diberikan)',
        'Kesalahan rekonsiliasi', 'Salah label/kemasan',
    ];

    private const CEDERA_POOL = [
        'Tidak ada cedera', 'Tidak ada cedera, kondisi dipantau',
        'Cedera ringan sementara', 'Cedera sedang memerlukan intervensi',
        'Cedera berat permanen', 'Kematian', 'Hampir cedera (near-miss)',
        'Reaksi alergi ringan', 'Hipoglikemia', 'Hipotensi',
    ];

    private const FAKTOR_PENYEBAB_POOL = [
        'Beban kerja tinggi', 'Kelelahan petugas', 'Komunikasi tidak efektif',
        'Sistem labeling kurang jelas', 'Obat LASA (Look-Alike Sound-Alike)',
        'Gangguan saat persiapan', 'Tidak ada double-check', 'Pengetahuan kurang',
        'Peralatan rusak', 'Prosedur tidak diikuti', 'Faktor lingkungan (pencahayaan)',
        'Sistem komputerisasi error',
    ];

    private const INTERVENSI_POOL = [
        'Monitoring ketat tanda vital', 'Pemberian antidot', 'Penghentian obat segera',
        'Konsultasi dokter spesialis', 'Pemeriksaan laboratorium ulang',
        'Penggantian obat', 'Edukasi ulang pasien dan keluarga',
        'Pemasangan infus cairan', 'Observasi 24 jam', 'Tidak diperlukan intervensi',
    ];

    private const KRONOLOGI_TEMPLATE = [
        'Petugas menyiapkan obat %s untuk pasien %s di %s. Pada saat %s, ditemukan terjadi %s. Kondisi segera dilaporkan ke penanggung jawab shift.',
        'Pasien %s menerima obat %s yang disiapkan oleh petugas farmasi. Saat tahap %s, terjadi insiden berupa %s. Petugas segera mengambil tindakan.',
        'Pada jam pergantian shift di %s, obat %s untuk pasien %s mengalami %s. Dokter jaga dihubungi dan tindakan korektif dilakukan.',
        'Proses %s obat %s mengalami kesalahan. Perawat menemukan bahwa pasien %s telah menerima %s. Kejadian dilaporkan sesuai SOP.',
        'Selama proses perawatan di ruang %s, obat %s dipersiapkan untuk pasien %s. Insiden %s teridentifikasi sebelum/sesudah pemberian oleh petugas yang bertugas.',
    ];

    private const AKAR_MASALAH_POOL = [
        'Kegagalan sistem komunikasi antar-shift menyebabkan informasi instruksi dokter tidak tersampaikan dengan jelas kepada perawat pelaksana.',
        'Tidak tersedianya prosedur double-check yang baku untuk obat risiko tinggi di unit ini, sehingga verifikasi sebelum pemberian tidak dilakukan.',
        'Kesamaan nama dan kemasan obat (LASA) berakibat pada terambilnya obat yang salah dari lemari penyimpanan.',
        'Beban kerja berlebih dan kekurangan tenaga pada shift malam menjadi kontributor utama pada terjadinya kesalahan identifikasi pasien.',
        'Sistem electronic health record (EHR) mengalami gangguan teknis sehingga instruksi dokter tidak terbaca oleh perawat pelaksana.',
        'Kurangnya pelatihan berkala tentang penanganan obat heparinisasi menyebabkan kesalahan penghitungan dosis infus.',
        'Pelabelan obat yang tidak standar di gudang farmasi menyebabkan obat dikirim ke unit yang tidak tepat.',
        'Kesalahan transkripsi resep akibat tulisan dokter yang sulit terbaca dan tidak ada konfirmasi ulang sebelum dispensing.',
    ];

    private const REKOMENDASI_POOL = [
        'Implementasi sistem barcode scanning untuk verifikasi identitas pasien dan obat sebelum setiap pemberian.',
        'Mewajibkan prosedur double-check oleh dua perawat untuk seluruh obat high-alert sesuai daftar ISMP.',
        'Pisahkan penyimpanan obat LASA dengan penamaan dan labeling khusus (tall-man lettering) yang berwarna.',
        'Pengaturan ulang beban kerja dan penambahan tenaga perawat pada shift malam, terutama di unit kritis.',
        'Pelatihan dan simulasi insiden medication error secara berkala minimal setiap 6 bulan untuk seluruh staf.',
        'Penerapan sistem CPOE (Computerized Physician Order Entry) terintegrasi untuk meminimalkan kesalahan transkripsi.',
        'Audit berkala penyimpanan obat di unit-unit perawatan dan penerapan sistem 5R di area penyimpanan obat.',
        'Sosialisasi budaya pelaporan insiden tanpa sanksi hukuman agar petugas lebih proaktif melaporkan kejadian.',
    ];

    private const CATATAN_TINDAK_LANJUT = [
        'investigasi' => [
            'Tim investigasi telah dibentuk. Pengumpulan data dan wawancara saksi sedang dilakukan sesuai protokol.',
            'Laporan diterima dan didisposisikan ke tim mutu untuk investigasi mendalam. Dokumentasi awal sudah lengkap.',
            'Investigasi awal menunjukkan adanya faktor sistem yang berkontribusi. Perlu analisis lebih lanjut.',
        ],
        'tindak_lanjut' => [
            'Hasil investigasi telah dibahas dalam rapat komite. Rekomendasi perbaikan telah ditetapkan dan didistribusikan.',
            'RCA selesai dilakukan. Tindakan korektif berupa revisi SOP dan pelatihan staf sedang dalam proses implementasi.',
            'Kepala unit telah diberikan rekomendasi tertulis. Follow-up dijadwalkan dalam 30 hari.',
        ],
        'selesai' => [
            'Seluruh tindakan korektif telah diimplementasikan dan terverifikasi. Kasus dinyatakan selesai.',
            'Evaluasi efektivitas tindakan perbaikan telah dilakukan. Tidak ada rekurensi dalam 3 bulan terakhir. Kasus ditutup.',
            'Laporan final telah dicetak dan diarsipkan. Pembelajaran disiseminasikan ke seluruh unit terkait.',
        ],
    ];

    /* ---------------------------------------------------------------
     | Entry Point
     | ------------------------------------------------------------*/

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        $this->command->info('  ▶ Seeding master data ...');
        [$unitKerja, $kategori, $dampakList, $probabilitasList] = $this->seedMasterData($tenant->id);

        $this->command->info('  ▶ Seeding tambahan pengguna nakes & kepala ruangan ...');
        [$nakesList, $karuList, $komite, $admin] = $this->seedTambahanPengguna($tenant->id, $unitKerja);

        $this->command->info('  ▶ Seeding 500 insiden (3 tahun: 2024-2026) ...');
        $this->seedInsiden(
            $tenant->id,
            $unitKerja,
            $kategori,
            $dampakList,
            $probabilitasList,
            $nakesList,
            $karuList,
            $komite,
            $admin,
        );

        $this->command->info('  ✔ InsidenSeeder selesai.');
    }

    /* ---------------------------------------------------------------
     | 1. Master Data
     | ------------------------------------------------------------*/

    private function seedMasterData(int $tenantId): array
    {
        // -- Unit Kerja (diambil dari tabel, sudah di-seed oleh UnitKerjaSeeder) --
        $unitRows = DB::table('master.unit_kerja')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        if ($unitRows->isEmpty()) {
            throw new \RuntimeException('Tidak ada unit kerja ditemukan. Pastikan UnitKerjaSeeder sudah dijalankan.');
        }

        $unitKerja = [];
        foreach ($unitRows as $unit) {
            $unitKerja[$unit->kode_unit] = $unit;
        }

        // -- Kategori Kesalahan --
        $kategoriData = [
            'Salah Identifikasi Pasien',
            'Salah Nama Obat / LASA',
            'Salah Dosis',
            'Salah Rute Pemberian',
            'Salah Waktu Pemberian',
            'Salah Konsentrasi',
            'Omisi (Obat Tidak Diberikan)',
            'Duplikasi Terapi',
            'Interaksi Obat Berbahaya',
            'Kesalahan Rekonsiliasi Obat',
            'Obat Kadaluarsa',
            'Kesalahan Penyimpanan Obat',
        ];

        $kategori = [];
        foreach ($kategoriData as $nama) {
            $k = KategoriKesalahan::firstOrCreate(
                ['tenant_id' => $tenantId, 'nama_kategori' => $nama],
            );
            $kategori[] = $k;
        }

        // -- Matriks Dampak (1-5) --
        $dampakData = [
            1 => 'Tidak signifikan — tidak ada cedera',
            2 => 'Minor — cedera ringan, dapat pulih sendiri',
            3 => 'Moderat — cedera memerlukan intervensi medis',
            4 => 'Mayor — cedera berat / cacat permanen',
            5 => 'Katastrofik — kematian pasien',
        ];

        $dampakList = [];
        foreach ($dampakData as $tingkat => $deskripsi) {
            $d = DB::table('master.matriks_dampak')
                ->where('tenant_id', $tenantId)
                ->where('tingkat', $tingkat)
                ->first();

            if (! $d) {
                $id = DB::table('master.matriks_dampak')->insertGetId([
                    'tenant_id'  => $tenantId,
                    'tingkat'    => $tingkat,
                    'deskripsi'  => $deskripsi,
                    'created_at' => now(),
                ]);
                $dampakList[$tingkat] = (object) ['id' => $id, 'tingkat' => $tingkat];
            } else {
                $dampakList[$tingkat] = $d;
            }
        }

        // -- Matriks Probabilitas (1-5) --
        $probData = [
            1 => 'Sangat jarang — < 1x setahun',
            2 => 'Jarang — 1-2x setahun',
            3 => 'Kadang-kadang — bulanan',
            4 => 'Sering — mingguan',
            5 => 'Hampir pasti — harian',
        ];

        $probabilitasList = [];
        foreach ($probData as $tingkat => $deskripsi) {
            $p = DB::table('master.matriks_probabilitas')
                ->where('tenant_id', $tenantId)
                ->where('tingkat', $tingkat)
                ->first();

            if (! $p) {
                $id = DB::table('master.matriks_probabilitas')->insertGetId([
                    'tenant_id'  => $tenantId,
                    'tingkat'    => $tingkat,
                    'deskripsi'  => $deskripsi,
                    'created_at' => now(),
                ]);
                $probabilitasList[$tingkat] = (object) ['id' => $id, 'tingkat' => $tingkat];
            } else {
                $probabilitasList[$tingkat] = $p;
            }
        }

        return [$unitKerja, $kategori, $dampakList, $probabilitasList];
    }

    /* ---------------------------------------------------------------
     | 2. Tambahan Pengguna
     | ------------------------------------------------------------*/

    private function seedTambahanPengguna(int $tenantId, array $unitKerja): array
    {
        $peranNakes   = Peran::where(['tenant_id' => $tenantId, 'nama_peran' => Peran::NAKES])->firstOrFail();
        $peranKaru    = Peran::where(['tenant_id' => $tenantId, 'nama_peran' => Peran::KEPALA_RUANGAN])->firstOrFail();
        $peranKomite  = Peran::where(['tenant_id' => $tenantId, 'nama_peran' => Peran::KOMITE])->firstOrFail();
        $peranAdmin   = Peran::where(['tenant_id' => $tenantId, 'nama_peran' => Peran::ADMIN])->firstOrFail();

        $unitIds = array_column(array_values($unitKerja), 'id');

        // Nakes tambahan (25 orang, disebar ke berbagai unit)
        $namaNakes = [
            'Ayu Rahmawati', 'Budi Hartono', 'Citra Dewi', 'Doni Firmansyah',
            'Eka Susilawati', 'Fahmi Nugroho', 'Gita Purnama', 'Hendra Saputra',
            'Indah Lestari', 'Joko Pramono', 'Kartini Sari', 'Lukman Hakim',
            'Mega Pratiwi', 'Nanda Permana', 'Oktaviani Putri', 'Purnama Sandi',
            'Qori Aisyah', 'Rahmat Hidayat', 'Siska Amelia', 'Taufik Ismail',
            'Umar Fadillah', 'Vina Oktavia', 'Wulandari Agustin', 'Yudha Pratama',
            'Zahra Nurfadilah',
        ];

        $nakesList = [];
        foreach ($namaNakes as $i => $nama) {
            $nip   = sprintf('P%03d', $i + 10);
            $email = sprintf('nakes%02d@mer.test', $i + 1);
            $unitId = $unitIds[$i % count($unitIds)];

            $p = Pengguna::firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $email],
                [
                    'tenant_id'    => $tenantId,
                    'unit_id'      => $unitId,
                    'nomor_induk'  => $nip,
                    'email'        => $email,
                    'nomor_hp'     => sprintf('0812%07d', 1000 + $i),
                    'alamat'       => "Jl. Nakes No. {$i}",
                    'nama_lengkap' => $nama,
                    'kata_sandi'   => 'password',
                    'is_aktif'     => true,
                ],
            );
            $p->peran()->syncWithoutDetaching([$peranNakes->id]);
            $nakesList[] = $p;
        }

        // Kepala Ruangan tambahan (8 orang, disebar ke berbagai unit)
        $namaKaru = [
            'Ns. Sari Ratnasari, S.Kep', 'Ns. Andi Wicaksono, S.Kep', 'Ns. Maya Kusuma, S.Kep',
            'Ns. Dewi Anggraini, S.Kep', 'Ns. Riko Setiawan, S.Kep', 'Ns. Lina Marlina, S.Kep',
            'Ns. Bayu Nugroho, S.Kep', 'Ns. Fitria Sari, S.Kep',
        ];

        $karuList = [];
        foreach ($namaKaru as $i => $nama) {
            $nip   = sprintf('KR%03d', $i + 10);
            $email = sprintf('karu%02d@mer.test', $i + 1);
            $unitId = $unitIds[$i % count($unitIds)];

            $k = Pengguna::firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $email],
                [
                    'tenant_id'    => $tenantId,
                    'unit_id'      => $unitId,
                    'nomor_induk'  => $nip,
                    'email'        => $email,
                    'nomor_hp'     => sprintf('0813%07d', 2000 + $i),
                    'alamat'       => "Jl. Kepala Ruangan No. {$i}",
                    'nama_lengkap' => $nama,
                    'kata_sandi'   => 'password',
                    'is_aktif'     => true,
                ],
            );
            $k->peran()->syncWithoutDetaching([$peranKaru->id]);
            $karuList[] = $k;
        }

        // Komite — gunakan yang sudah ada dari PenggunaSeeder
        $komite = Pengguna::where('tenant_id', $tenantId)
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::KOMITE))
            ->first();

        // Admin — gunakan yang sudah ada
        $admin = Pengguna::where('tenant_id', $tenantId)
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::ADMIN))
            ->first();

        return [$nakesList, $karuList, $komite, $admin];
    }

    /* ---------------------------------------------------------------
     | 3-8. Core — Insiden + Relasi
     | ------------------------------------------------------------*/

    private function seedInsiden(
        int $tenantId,
        array $unitKerja,
        array $kategori,
        array $dampakList,
        array $probabilitasList,
        array $nakesList,
        array $karuList,
        ?Pengguna $komite,
        ?Pengguna $admin,
    ): void {
        // Bersihkan data lama (urutan FK)
        DB::table('pelaporan.tindak_lanjut')->truncate();
        DB::table('pelaporan.investigasi_rca')->truncate();
        DB::table('pelaporan.penilaian_risiko')->truncate();
        DB::table('pelaporan.insiden_kategori')->truncate();
        DB::table('pelaporan.detail_pasien')->truncate();
        DB::table('pelaporan.insiden')->truncate();

        $sekarang   = Carbon::parse('2026-12-31 23:59:59');
        $tigaTahunLalu = Carbon::parse('2024-01-01 00:00:00');
        $unitList   = array_values($unitKerja);
        $nomorUrut  = [];  // key: "YYYY" => int

        for ($i = 1; $i <= 500; $i++) {
            // ── Tanggal acak dalam 3 tahun (2024-2026) ──────────────────
            $tglKejadian = Carbon::createFromTimestamp(
                random_int($tigaTahunLalu->timestamp, $sekarang->timestamp),
            );
            $tglLapor = $tglKejadian->copy()->addMinutes(random_int(10, 300));

            // ── Nomor laporan ────────────────────────────────────────────
            $tahun = $tglKejadian->format('Y');
            $nomorUrut[$tahun] = ($nomorUrut[$tahun] ?? 0) + 1;
            $nomorLaporan = sprintf('INC-%s-%04d', $tahun, $nomorUrut[$tahun]);

            // ── Pelapor & unit secara acak ───────────────────────────────
            $pelapor  = $nakesList[array_rand($nakesList)];
            $unit     = $unitList[array_rand($unitList)];
            $isAnonim = (bool) random_int(0, 1);

            // ── Tipe insiden (berbobot) ──────────────────────────────────
            $tipe = $this->pilihBerbobot(self::TIPE_INSIDEN, self::BOBOT_TIPE);

            // ── Status berdasarkan umur laporan ──────────────────────────
            $usiaHari = $sekarang->diffInDays($tglKejadian);
            $status   = $this->tentukanStatus((int) $usiaHari);

            // ── Insert insiden ───────────────────────────────────────────
            $insidenId = DB::table('pelaporan.insiden')->insertGetId([
                'tenant_id'       => $tenantId,
                'nomor_laporan'   => $nomorLaporan,
                'pelapor_id'      => $pelapor->id,
                'unit_id'         => $unit->id,
                'nama_unit_kerja' => $unit->nama_unit,
                'tipe_insiden'    => $tipe,
                'fase_kesalahan'  => self::FASE_KESALAHAN[array_rand(self::FASE_KESALAHAN)],
                'status_saat_ini' => $status,
                'tgl_kejadian'    => $tglKejadian,
                'tgl_lapor'       => $tglLapor,
                'nama_pelapor'    => $isAnonim ? null : $pelapor->nama_lengkap,
                'kontak_pelapor'  => $isAnonim ? null : $pelapor->nomor_hp,
                'is_anonim'       => $isAnonim,
                'sudah_dibaca'    => $status !== 'kasus_baru',
                'created_at'      => $tglLapor,
                'updated_at'      => $tglLapor,
            ]);

            // ── 4. Detail pasien ─────────────────────────────────────────
            $obat               = self::NAMA_OBAT[array_rand(self::NAMA_OBAT)];
            $dokterResep        = self::NAMA_DOKTER[array_rand(self::NAMA_DOKTER)];
            $kronologiTemplate  = self::KRONOLOGI_TEMPLATE[array_rand(self::KRONOLOGI_TEMPLATE)];
            $fasePlain          = ucfirst(self::FASE_KESALAHAN[array_rand(self::FASE_KESALAHAN)]);
            $jenisKesalahanPilih= $this->ambilAcak(self::JENIS_KESALAHAN_POOL, random_int(1, 3));
            $cederaPilih        = $this->ambilAcak(self::CEDERA_POOL, random_int(1, 2));
            $faktorPilih        = $this->ambilAcak(self::FAKTOR_PENYEBAB_POOL, random_int(1, 3));
            $intervensiPilih    = $this->ambilAcak(self::INTERVENSI_POOL, random_int(1, 2));
            $namaPasien         = $this->namaAcak();
            $nomorRM            = sprintf('%03d-%04d-%04d', random_int(1, 99), random_int(0, 9999), random_int(0, 9999));

            $kronologi = vsprintf($kronologiTemplate, [
                $obat,
                $namaPasien,
                $unit->nama_unit,
                $fasePlain,
                strtolower($jenisKesalahanPilih[0]),
            ]);

            DB::table('pelaporan.detail_pasien')->insert([
                'insiden_id'           => $insidenId,
                'nama_pasien'          => $namaPasien,
                'nomor_rekam_medis'    => $nomorRM,
                'obat_terkait'         => $obat,
                'dokter_penulis_resep' => $dokterResep,
                'kronologi'            => $kronologi,
                'tindakan_awal'        => $this->ambilAcak(self::INTERVENSI_POOL, 1)[0],
                'jenis_kesalahan'      => json_encode(array_values($jenisKesalahanPilih)),
                'cedera'               => json_encode(array_values($cederaPilih)),
                'faktor_penyebab'      => json_encode(array_values($faktorPilih)),
                'intervensi_pasien'    => json_encode(array_values($intervensiPilih)),
                'pernyataan_kronologi' => true,
                'created_at'           => $tglLapor,
            ]);

            // ── 5. Kategori (1–3 per insiden) ───────────────────────────
            $pilihKategori = $this->ambilAcak($kategori, random_int(1, 3));
            foreach ($pilihKategori as $kat) {
                DB::table('pelaporan.insiden_kategori')->insertOrIgnore([
                    'insiden_id'  => $insidenId,
                    'kategori_id' => $kat->id,
                ]);
            }

            // ── 6. Penilaian Risiko ──────────────────────────────────────
            // Hanya untuk insiden yang sudah melewati tahap kasus_baru
            if (in_array($status, ['investigasi', 'tindak_lanjut', 'selesai'], true)) {
                $penilai    = $karuList[array_rand($karuList)] ?? $komite;
                $dampakTk   = random_int(1, 5);
                $probTk     = random_int(1, 5);
                $skor       = $dampakTk * $probTk;
                $warna      = $this->tentukanWarnaGrading($skor);

                DB::table('pelaporan.penilaian_risiko')->insert([
                    'insiden_id'      => $insidenId,
                    'penilai_id'      => $penilai->id,
                    'probabilitas_id' => $probabilitasList[$probTk]->id,
                    'dampak_id'       => $dampakList[$dampakTk]->id,
                    'warna_grading'   => $warna,
                    'created_at'      => $tglLapor->copy()->addDays(random_int(1, 7)),
                ]);
            }

            // ── 7. Investigasi RCA ───────────────────────────────────────
            // Untuk insiden tindak_lanjut atau selesai (70% dari mereka)
            if (in_array($status, ['tindak_lanjut', 'selesai'], true) && random_int(1, 10) <= 7) {
                $investigator = $komite ?? $karuList[array_rand($karuList)];

                DB::table('pelaporan.investigasi_rca')->insert([
                    'insiden_id'          => $insidenId,
                    'investigator_id'     => $investigator->id,
                    'akar_masalah'        => self::AKAR_MASALAH_POOL[array_rand(self::AKAR_MASALAH_POOL)],
                    'rekomendasi_sistem'  => self::REKOMENDASI_POOL[array_rand(self::REKOMENDASI_POOL)],
                    'created_at'          => $tglLapor->copy()->addDays(random_int(7, 21)),
                ]);
            }

            // ── 8. Tindak Lanjut (histori transisi status) ─────────────
            $this->seedTindakLanjut($insidenId, $status, $tglLapor, $karuList, $komite);
        }
    }

    /* ---------------------------------------------------------------
     | 8. Histori Tindak Lanjut per Insiden
     | ------------------------------------------------------------*/

    private function seedTindakLanjut(
        int $insidenId,
        string $statusAkhir,
        Carbon $tglLapor,
        array $karuList,
        ?Pengguna $komite,
    ): void {
        $indeksAkhir = array_search($statusAkhir, self::STATUS_URUT, true);

        // Mulai dari status setelah kasus_baru (index 1 = investigasi)
        $tglTransisi = $tglLapor->copy();
        for ($step = 1; $step <= $indeksAkhir; $step++) {
            $statusBaru  = self::STATUS_URUT[$step];
            $tglTransisi = $tglTransisi->copy()->addDays(random_int(3, 14));

            // Karu untuk investigasi & tindak_lanjut; Komite untuk selesai
            if ($statusBaru === 'selesai' && $komite) {
                $pengguna = $komite;
            } else {
                $pengguna = $karuList[array_rand($karuList)] ?? $komite;
            }

            if (! $pengguna) {
                continue;
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
     * Pilih satu item dari array berdasarkan bobot (weighted random).
     */
    private function pilihBerbobot(array $items, array $bobot): string
    {
        $total   = array_sum($bobot);
        $acak    = random_int(1, $total);
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
     * Ambil n elemen unik acak dari array (tanpa preserve keys).
     */
    private function ambilAcak(array $sumber, int $n): array
    {
        $n = min($n, count($sumber));
        $keys = array_rand($sumber, $n);
        if (! is_array($keys)) {
            $keys = [$keys];
        }

        return array_map(fn ($k) => $sumber[$k], $keys);
    }

    /**
     * Tentukan status berdasarkan usia laporan (hari).
     * Laporan lama cenderung sudah selesai; laporan baru masih kasus_baru.
     */
    private function tentukanStatus(int $usiaHari): string
    {
        if ($usiaHari > 540) {        // > 18 bulan: hampir semua selesai
            return $this->pilihBerbobot(self::STATUS_URUT, [5, 10, 20, 65]);
        }

        if ($usiaHari > 360) {        // 12-18 bulan
            return $this->pilihBerbobot(self::STATUS_URUT, [5, 15, 30, 50]);
        }

        if ($usiaHari > 180) {        // 6-12 bulan
            return $this->pilihBerbobot(self::STATUS_URUT, [10, 25, 35, 30]);
        }

        if ($usiaHari > 60) {         // 2-6 bulan
            return $this->pilihBerbobot(self::STATUS_URUT, [20, 35, 30, 15]);
        }

        // < 2 bulan: mayoritas baru atau sedang diproses
        return $this->pilihBerbobot(self::STATUS_URUT, [45, 30, 15, 10]);
    }

    /**
     * Tentukan warna grading berdasarkan skor risiko (probabilitas × dampak).
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
     * Generate nama pasien acak (format Indonesia).
     */
    private function namaAcak(): string
    {
        $depan = ['Agus', 'Budi', 'Citra', 'Desi', 'Eko', 'Fitri', 'Gilang',
                  'Hani', 'Iwan', 'Joko', 'Kartika', 'Lina', 'Mira', 'Nanda',
                  'Oki', 'Putri', 'Rudi', 'Sari', 'Tono', 'Udin', 'Vera',
                  'Wahyu', 'Xena', 'Yanti', 'Zainal', 'Rina', 'Doni', 'Ayu'];

        $tengah = ['Budi', 'Prasetyo', 'Santoso', 'Lestari', 'Wati', 'Sari',
                   'Handoko', 'Kurniawan', 'Rahayu', 'Susilo', '', '', ''];

        $belakang = ['S.', 'P.', 'bin Ahmad', 'binti Hasan', '', '', ''];

        $bagian = [$depan[array_rand($depan)]];

        if (random_int(0, 1)) {
            $t = $tengah[array_rand($tengah)];
            if ($t !== '') {
                $bagian[] = $t;
            }
        }

        return implode(' ', $bagian);
    }
}
