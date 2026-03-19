<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organisasi;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ProductionPenggunaSeeder — Akun pengguna resmi RSUD HM. Ryacudu Kotabumi.
 *
 * Sumber data: SK UPTD RSUD HM. Ryacudu Kabupaten Lampung Utara Tahun 2025.
 *
 * DAFTAR AKUN YANG DIBUAT:
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ AKUN KHUSUS (6)                                                             │
 * ├───────────────────┬──────────────────────────────┬─────────────────────────┤
 * │ Peran             │ Username (login)             │ Sandi Sementara         │
 * ├───────────────────┼──────────────────────────────┼─────────────────────────┤
 * │ Admin             │ admin.rsud                   │ password                │
 * │ Direktur          │ direktur.rsud                │ password                │
 * │ Komite (Kes.)     │ komite.keselamatan           │ password                │
 * │ Komite (Mutu)     │ komite.mutu                  │ password                │
 * │ Komite (Medik)    │ komite.medik                 │ password                │
 * │ Peneliti/Dosen    │ dosenpeneliti                │ Peneliti@2026!          │
 * ├───────────────────┴──────────────────────────────┴─────────────────────────┤
 * │ NAKES PIC (25) — Perawat/Bidan Pelaksana per Unit                          │
 * │ KEPALA RUANGAN (28) — Validator per Unit                                   │
 * │ Semua non-admin: wajib_ganti_sandi = true (ganti sandi saat login pertama) │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * IDEMPOTENT: menggunakan updateOrCreate dengan (tenant_id, email) sebagai kunci
 * pencarian — kolom yang memiliki unique constraint di database.
 *
 * Jalankan secara mandiri:
 *   php artisan db:seed --class=ProductionPenggunaSeeder
 */
class ProductionPenggunaSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Alamat kantor untuk semua staf RS. */
    private const ALAMAT_RS = 'RSUD HM. Ryacudu, Jl. HM. Ryacudu No. 1, Kotabumi, Lampung Utara';

    // ─────────────────────────────────────────────────────────────────────────
    // Format setiap baris:
    //   [kode_unit, nomor_induk, username, nama_lengkap, jabatan, email, nomor_hp]
    //
    // Catatan:
    //  - Ns. Zulda Purnawati, S.Kep menjabat sekaligus PIC dan Kepala Ruangan
    //    Poli Ortopedi → satu akun, peran Kepala Ruangan (lebih tinggi).
    //  - Puji Astuti Sepriani, AMKG menjabat sekaligus PIC dan Kepala Ruangan
    //    Poli Gigi → satu akun, peran Kepala Ruangan.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Data PIC (perawat/bidan pelaksana) sebagai Nakes.
     * PIC Poli Ortopedi & Poli Gigi tidak ada di sini karena
     * kedua orang tersebut juga menjabat Kepala Ruangan (lihat KARU_DATA).
     */
    private const NAKES_DATA = [
        // ── PIC Poliklinik ────────────────────────────────────────────────
        ['POLI-ANAK',  'NP001', 'hartati',       'Ns. Hartati, S.Kep',             'Perawat Pelaksana',         'hartati@rsudryacudu.go.id',       '081100000101'],
        ['POLI-KBD',   'NP002', 'herlinawati',   'Herlina Wati, Amd. Keb',         'Bidan Pelaksana',           'herlinawati@rsudryacudu.go.id',   '081100000102'],
        ['POLI-SARAF', 'NP003', 'srisumini',     'Sri Sumini, Amd.Kep',            'Perawat Pelaksana',         'srisumini@rsudryacudu.go.id',     '081100000103'],
        ['POLI-PD',    'NP004', 'vicania',        'Ns. Vicania, S.Kep',             'Perawat Pelaksana',         'vicania@rsudryacudu.go.id',       '081100000104'],
        ['POLI-THT',   'NP005', 'sririzki',      'Sri Rizki Yanti, Amd.Kep',       'Perawat Pelaksana',         'sririzki@rsudryacudu.go.id',      '081100000105'],
        ['POLI-BEDAH', 'NP006', 'oktarina',      'Oktarina, Amd.Kep',              'Perawat Pelaksana',         'oktarina@rsudryacudu.go.id',      '081100000106'],
        ['POLI-PARU',  'NP007', 'karwanti',      'Ns. Karwanti, S.Kep',            'Perawat Pelaksana',         'karwanti@rsudryacudu.go.id',      '081100000107'],
        ['POLI-KULIT', 'NP008', 'istihernani',   'Isti Hernani, Amd.Kep',          'Perawat Pelaksana',         'istihernani@rsudryacudu.go.id',   '081100000108'],
        ['POLI-MATA',  'NP009', 'komariah',      'Ns. Komariah, S.Kep',            'Perawat Pelaksana',         'komariah@rsudryacudu.go.id',      '081100000109'],
        ['POLI-JIWA',  'NP010', 'denifrengky',   'Ns. Deni Frengky, S.Kep',        'Perawat Pelaksana',         'denifrengky@rsudryacudu.go.id',   '081100000110'],
        ['POLI-ANEST', 'NP011', 'ledioktaria',   'Ns. Ledi Oktaria, S.Kep',        'Perawat Pelaksana',         'ledioktaria@rsudryacudu.go.id',   '081100000111'],

        // ── PIC Rawat Inap ────────────────────────────────────────────────
        ['RW-BEDAH',   'NR001', 'meritasundari', 'Merita Sundari, Amd.Kep',        'Perawat Pelaksana',         'meritasundari@rsudryacudu.go.id', '081200000201'],
        ['RW-VIP',     'NR002', 'saripaulina',   'Sari Paulina, Amd.Kep',          'Perawat Pelaksana',         'saripaulina@rsudryacudu.go.id',   '081200000202'],
        ['RW-HD',      'NR003', 'juliprabowo',   'Ns. Juli Prabowo, S.Kep',        'Perawat Pelaksana',         'juliprabowo@rsudryacudu.go.id',   '081200000203'],
        ['RW-PD',      'NR004', 'novidahlia',    'Novi Dahlia, Amd.Kep',           'Perawat Pelaksana',         'novidahlia@rsudryacudu.go.id',    '081200000204'],
        ['RW-SARAF',   'NR005', 'enilestari',    'Eni Lestari, Amd.Kep',           'Perawat Pelaksana',         'enilestari@rsudryacudu.go.id',    '081200000205'],
        ['RW-NEONAT',  'NR006', 'viagina',       'Ns. Via Gina Mahardhika, S.Kep', 'Perawat Pelaksana',         'viagina@rsudryacudu.go.id',       '081200000206'],
        ['RW-ANAK',    'NR007', 'niryana',       'Niryana, Amd.Kep',               'Perawat Pelaksana',         'niryana@rsudryacudu.go.id',       '081200000207'],
        ['RW-KBD',     'NR008', 'merimaydiana',  'Meri Maydiana, Amd.Keb',         'Bidan Pelaksana',           'merimaydiana@rsudryacudu.go.id',  '081200000208'],
        ['RW-VK',      'NR009', 'sydesmaalia',   'Sydesma Alia, Amd.Keb',          'Bidan Pelaksana',           'sydesmaalia@rsudryacudu.go.id',   '081200000209'],
        ['ICU',        'NR010', 'heniapri',      'Heni Apriyani, Amd.Kep',         'Perawat Pelaksana',         'heniapri@rsudryacudu.go.id',      '081200000210'],
        ['IGD',        'NR011', 'devizana',      'Devi Zana Junjungan, Amd.Kep',   'Perawat Pelaksana',         'devizana@rsudryacudu.go.id',      '081200000211'],
        ['IBS',        'NR012', 'yeninarina',    'Ns. Yeni Narina, S.Kep',         'Perawat Pelaksana',         'yeninarina@rsudryacudu.go.id',    '081200000212'],
        ['RW-PONEK',   'NR013', 'septiamei',     'Septia Meinitasari, Amd.Keb',    'Bidan Pelaksana',           'septiamei@rsudryacudu.go.id',     '081200000213'],

        // ── PIC Penunjang ─────────────────────────────────────────────────
        ['FARMASI',    'NPJ01', 'dianasari',     'Diana Sari, S.Farm',             'Asisten Apoteker Penyelia', 'dianasari@rsudryacudu.go.id',     '081300000301'],
    ];

    /**
     * Data Kepala Ruangan (Validator) per unit.
     * Termasuk Ns. Zulda Purnawati (Poli Ortopedi) dan
     * Puji Astuti Sepriani (Poli Gigi) yang juga menjadi PIC.
     */
    private const KARU_DATA = [
        // ── Kepala Ruangan Poliklinik ─────────────────────────────────────
        ['POLI-ANAK',    'KP001', 'dahlia',        'Ns. Dahlia, S.Kep',             'Kepala Ruangan', 'dahlia@rsudryacudu.go.id',        '081400000401'],
        ['POLI-KBD',     'KP002', 'wardalia',      'Wardalia, SST',                  'Kepala Ruangan', 'wardalia@rsudryacudu.go.id',      '081400000402'],
        ['POLI-ORTHO',   'KP003', 'zuldapurnawati','Ns. Zulda Purnawati, S.Kep',    'Kepala Ruangan', 'zuldapurnawati@rsudryacudu.go.id','081400000403'],
        ['POLI-SARAF',   'KP004', 'aprilinda',     'Aprilinda, Amd. Kep',            'Kepala Ruangan', 'aprilinda@rsudryacudu.go.id',     '081400000404'],
        ['POLI-PD',      'KP005', 'suhartini',     'Ns. Suhartini, S.Kep',           'Kepala Ruangan', 'suhartini@rsudryacudu.go.id',     '081400000405'],
        ['POLI-THT',     'KP006', 'srianggareny',  'Ns. Sri Anggareny, S.Kep',       'Kepala Ruangan', 'srianggareny@rsudryacudu.go.id',  '081400000406'],
        ['POLI-BEDAH',   'KP007', 'idayati',       'Ns. Ida Yati, S.Kep',            'Kepala Ruangan', 'idayati@rsudryacudu.go.id',       '081400000407'],
        ['POLI-PARU',    'KP008', 'muliana',       'Ns. Muliana, S.Kep',             'Kepala Ruangan', 'muliana@rsudryacudu.go.id',       '081400000408'],
        ['POLI-GIGI',    'KP009', 'pujiastuti',    'Puji Astuti Sepriani, AMKG',     'Kepala Ruangan', 'pujiastuti@rsudryacudu.go.id',    '081400000409'],
        ['POLI-KULIT',   'KP010', 'yuliafrida',    'Ns. Yuli Afrida, S.Kep',         'Kepala Ruangan', 'yuliafrida@rsudryacudu.go.id',    '081400000410'],
        ['POLI-MATA',    'KP011', 'aprilnita',     'Ns. April Nita, S.Kep',          'Kepala Ruangan', 'aprilnita@rsudryacudu.go.id',     '081400000411'],
        ['POLI-JIWA',    'KP012', 'fitriantina',   'Ns. Fitriantina, S.Kep',         'Kepala Ruangan', 'fitriantina@rsudryacudu.go.id',   '081400000412'],
        ['POLI-ANEST',   'KP013', 'desyhastuti',   'Ns. Desy Hastuti, S.Kep',        'Kepala Ruangan', 'desyhastuti@rsudryacudu.go.id',   '081400000413'],

        // ── Kepala Ruangan Rawat Inap ─────────────────────────────────────
        ['RW-BEDAH',     'KR001', 'novaliana',     'Ns. Nova Liana Sari, S.Kep',     'Kepala Ruangan', 'novaliana@rsudryacudu.go.id',     '081500000501'],
        ['RW-VIP',       'KR002', 'rahmawaty',     'Ns. Rahmawaty, S.Kep',           'Kepala Ruangan', 'rahmawaty@rsudryacudu.go.id',     '081500000502'],
        ['RW-ISOLASI-B', 'KR003', 'rahmadsaleh',   'Ns. Rahmad Saleh, S.Kep',        'Kepala Ruangan', 'rahmadsaleh@rsudryacudu.go.id',   '081500000503'],
        ['RW-HD',        'KR004', 'trihananto',    'Ns. Tri Hananto, S.Kep',         'Kepala Ruangan', 'trihananto@rsudryacudu.go.id',    '081500000504'],
        ['RW-PD',        'KR005', 'madjahuri',     'Ns. Mad Jahuri, S.Kep',          'Kepala Ruangan', 'madjahuri@rsudryacudu.go.id',     '081500000505'],
        ['RW-SARAF',     'KR006', 'deninovice',    'Ns. Deni Novice, S.Kep',         'Kepala Ruangan', 'deninovice@rsudryacudu.go.id',    '081500000506'],
        ['RW-NEONAT',    'KR007', 'resmareny',     'Ns. Resmareny, S.Kep',           'Kepala Ruangan', 'resmareny@rsudryacudu.go.id',     '081500000507'],
        ['RW-ANAK',      'KR008', 'revisesiana',   'Ns. Revi Sesiana, S.Kep',        'Kepala Ruangan', 'revisesiana@rsudryacudu.go.id',   '081500000508'],
        ['RW-KBD',       'KR009', 'muhartini',     'Muhartini, SST',                  'Kepala Ruangan', 'muhartini@rsudryacudu.go.id',     '081500000509'],
        ['RW-VK',        'KR010', 'yulicaturini',  'Yuli Caturini, STT.,M.Kes',      'Kepala Ruangan', 'yulicaturini@rsudryacudu.go.id',  '081500000510'],
        ['ICU',          'KR011', 'harisawaludin', 'Ns. Haris Awaludin, S.Kep',      'Kepala Ruangan', 'harisawaludin@rsudryacudu.go.id', '081500000511'],
        ['IGD',          'KR012', 'bayuirda',      'Ns. Bayu Irda Manita, S.Kep',    'Kepala Ruangan', 'bayuirda@rsudryacudu.go.id',      '081500000512'],
        ['IBS',          'KR013', 'riduanhusin',   'Ns. Riduan Husin, S.Kep',        'Kepala Ruangan', 'riduanhusin@rsudryacudu.go.id',   '081500000513'],
        ['RW-PONEK',     'KR014', 'meidaliana',    'Meida Liana, S.ST.,M.Kes',       'Kepala Ruangan', 'meidaliana@rsudryacudu.go.id',    '081500000514'],

        // ── Kepala Ruangan Penunjang ──────────────────────────────────────
        ['FARMASI',      'KRJ01', 'desinopi',      'Desi Nopiyanti, S.Si.,Apt',      'Kepala Ruangan', 'desinopi@rsudryacudu.go.id',      '081600000601'],
    ];

    /* ---------------------------------------------------------------
     | Entry Point
     | ------------------------------------------------------------*/

    public function run(): void
    {
        $tenant = Organisasi::where('kode_organisasi', 'default')->firstOrFail();

        // Pastikan semua peran tersedia sebelum digunakan sebagai FK.
        $this->ensurePeranExists($tenant->id);

        $peranAdmin    = Peran::where(['tenant_id' => $tenant->id, 'nama_peran' => Peran::ADMIN])->firstOrFail();
        $peranDirektur = Peran::where(['tenant_id' => $tenant->id, 'nama_peran' => Peran::DIREKTUR])->firstOrFail();
        $peranKomite   = Peran::where(['tenant_id' => $tenant->id, 'nama_peran' => Peran::KOMITE])->firstOrFail();
        $peranPeneliti = Peran::where(['tenant_id' => $tenant->id, 'nama_peran' => Peran::PENELITI])->firstOrFail();
        $peranNakes    = Peran::where(['tenant_id' => $tenant->id, 'nama_peran' => Peran::NAKES])->firstOrFail();
        $peranKaru     = Peran::where(['tenant_id' => $tenant->id, 'nama_peran' => Peran::KEPALA_RUANGAN])->firstOrFail();

        // ── Step 1: Akun khusus ───────────────────────────────────────────
        $this->command->info('  ▶ Membuat akun khusus (Admin, Direktur, 3 Komite, Peneliti, Nakes Demo)...');
        $this->seedSpecialAccounts($tenant->id, $peranAdmin, $peranDirektur, $peranKomite, $peranPeneliti, $peranNakes);

        // ── Step 2: Nakes PIC ─────────────────────────────────────────────
        $this->command->info('  ▶ Membuat 25 akun Nakes (PIC per Unit) ...');
        $this->seedStaff($tenant->id, self::NAKES_DATA, $peranNakes);

        // ── Step 3: Kepala Ruangan / Validator ───────────────────────────
        $this->command->info('  ▶ Membuat 28 akun Kepala Ruangan (Didaftarkan sebagai Nakes awal) ...');
        $this->seedStaff($tenant->id, self::KARU_DATA, $peranNakes, $peranKaru);

        $this->command->info('  ✔ ProductionPenggunaSeeder selesai: 60 akun production diproses.');
        $this->command->warn('  ⚠ Semua akun non-Admin diwajibkan ganti sandi pada login pertama.');
    }

    /* ---------------------------------------------------------------
     | Private Helpers
     | ------------------------------------------------------------*/

    /**
     * Pastikan semua peran tersedia di tenant (idempotent).
     * Diperlukan agar seeder ini bisa jalan standalone tanpa PeranSeeder.
     */
    private function ensurePeranExists(int $tenantId): void
    {
        foreach ([Peran::NAKES, Peran::KEPALA_RUANGAN, Peran::KOMITE, Peran::ADMIN, Peran::DIREKTUR, Peran::PENELITI] as $nama) {
            Peran::firstOrCreate(['tenant_id' => $tenantId, 'nama_peran' => $nama]);
        }
    }

    /**
     * Buat / perbarui 6 akun khusus (admin, direktur, 3 komite, peneliti).
     */
    private function seedSpecialAccounts(
        int $tenantId,
        Peran $peranAdmin,
        Peran $peranDirektur,
        Peran $peranKomite,
        Peran $peranPeneliti,
        Peran $peranNakes,
    ): void {
        $entries = [
            // ── Admin ──────────────────────────────────────────────────────
            // wajib_ganti_sandi=false: Admin adalah pemilik sistem &
            // sudah mengetahui sandi yang ditetapkan.
            [
                'data'  => [
                    'tenant_id'         => $tenantId,
                    'nomor_induk'       => 'ADM001',
                    'username'          => 'admin',
                    'nama_lengkap'      => 'Administrator Sistem RSUD HM. Ryacudu',
                    'jabatan'           => 'Administrator Sistem',
                    'email'             => 'admin@rsudryacudu.go.id',
                    'nomor_hp'          => '082100000001',
                    'alamat'            => self::ALAMAT_RS,
                    'kata_sandi'        => 'password',
                    'is_aktif'          => true,
                    'wajib_ganti_sandi' => false,
                ],
                'peran' => $peranAdmin,
            ],

            // ── Direktur ───────────────────────────────────────────────────
            [
                'data'  => [
                    'tenant_id'         => $tenantId,
                    'nomor_induk'       => 'DIR001',
                    'username'          => 'direktur',
                    'nama_lengkap'      => 'Direktur RSUD HM. Ryacudu',
                    'jabatan'           => 'Direktur',
                    'email'             => 'direktur@rsudryacudu.go.id',
                    'nomor_hp'          => '082100000002',
                    'alamat'            => self::ALAMAT_RS,
                    'kata_sandi'        => 'password',
                    'is_aktif'          => true,
                    'wajib_ganti_sandi' => true,
                ],
                'peran' => $peranDirektur,
            ],

            // ── Komite 1 — Keselamatan Pasien ─────────────────────────────
            [
                'data'  => [
                    'tenant_id'         => $tenantId,
                    'nomor_induk'       => 'KOM001',
                    'username'          => 'komite',
                    'nama_lengkap'      => 'Ketua Komite Keselamatan Pasien',
                    'jabatan'           => 'Ketua Komite Keselamatan Pasien',
                    'email'             => 'komite.keselamatan@rsudryacudu.go.id',
                    'nomor_hp'          => '082100000003',
                    'alamat'            => self::ALAMAT_RS,
                    'kata_sandi'        => 'password',
                    'is_aktif'          => true,
                    'wajib_ganti_sandi' => true,
                ],
                'peran' => $peranKomite,
            ],

            // ── Komite 2 — Mutu dan Keselamatan ───────────────────────────
            [
                'data'  => [
                    'tenant_id'         => $tenantId,
                    'nomor_induk'       => 'KOM002',
                    'username'          => 'komite.mutu',
                    'nama_lengkap'      => 'Anggota Komite Mutu dan Keselamatan',
                    'jabatan'           => 'Anggota Komite Mutu dan Keselamatan',
                    'email'             => 'komite.mutu@rsudryacudu.go.id',
                    'nomor_hp'          => '082100000004',
                    'alamat'            => self::ALAMAT_RS,
                    'kata_sandi'        => 'password',
                    'is_aktif'          => true,
                    'wajib_ganti_sandi' => true,
                ],
                'peran' => $peranKomite,
            ],

            // ── Komite 3 — Medik ───────────────────────────────────────────
            [
                'data'  => [
                    'tenant_id'         => $tenantId,
                    'nomor_induk'       => 'KOM003',
                    'username'          => 'komite.medik',
                    'nama_lengkap'      => 'Anggota Komite Medik',
                    'jabatan'           => 'Anggota Komite Medik',
                    'email'             => 'komite.medik@rsudryacudu.go.id',
                    'nomor_hp'          => '082100000005',
                    'alamat'            => self::ALAMAT_RS,
                    'kata_sandi'        => 'password',
                    'is_aktif'          => true,
                    'wajib_ganti_sandi' => true,
                ],
                'peran' => $peranKomite,
            ],

            // ── Peneliti / Dosen Pembimbing ────────────────────────────────
            // Email menggunakan domain universitas (bukan RS) karena akun ini
            // milik dosen luar yang diberi akses sementara untuk penelitian.
            // Konsisten dengan nilai di PenelitiSeeder agar tidak konflik.
            [
                'data'  => [
                    'tenant_id'         => $tenantId,
                    'nomor_induk'       => 'dosenpeneliti',
                    'username'          => 'dosenpeneliti',
                    'nama_lengkap'      => 'Dosen Peneliti',
                    'jabatan'           => 'Dosen Peneliti',
                    'email'             => 'peneliti.mer@universitas.ac.id',
                    'nomor_hp'          => '082100000099',
                    'alamat'            => 'Kampus Universitas, Gedung Kesehatan',
                    'kata_sandi'        => 'password',
                    'is_aktif'          => true,
                    'wajib_ganti_sandi' => false,
                ],
                'peran' => $peranPeneliti,
            ],

            // ── Nakes Demo ────────────────────────────────────────────────
            // Akun nakes generik untuk keperluan demo / uji coba alur pelaporan.
            [
                'data'  => [
                    'tenant_id'         => $tenantId,
                    'nomor_induk'       => 'NK000',
                    'username'          => 'nakes',
                    'nama_lengkap'      => 'Tenaga Kesehatan',
                    'jabatan'           => 'Perawat Pelaksana',
                    'email'             => 'nakes@rsudryacudu.go.id',
                    'nomor_hp'          => '082100000010',
                    'alamat'            => self::ALAMAT_RS,
                    'kata_sandi'        => 'password',
                    'is_aktif'          => true,
                    'wajib_ganti_sandi' => false,
                ],
                'peran' => $peranNakes,
            ],
        ];

        foreach ($entries as $entry) {
            $p = Pengguna::updateOrCreate(
                [
                    'tenant_id' => $entry['data']['tenant_id'],
                    'email'     => $entry['data']['email'],
                ],
                $entry['data'],
            );
            $p->peran()->syncWithoutDetaching([$entry['peran']->id]);
            $this->command->line(sprintf(
                '    ✓ %-42s | %-18s | %s',
                $p->nama_lengkap,
                $entry['peran']->nama_peran,
                $p->email,
            ));
        }
    }

    /**
     * Buat / perbarui akun staf (Nakes atau Kepala Ruangan) secara batch.
     *
     * @param array<int, array<int, string>> $staffData
     */
    private function seedStaff(int $tenantId, array $staffData, Peran ...$peranList): void
    {
        // Ambil semua unit kerja sekaligus untuk menghindari query per-record.
        $unitMap = DB::table('master.unit_kerja')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->pluck('id', 'kode_unit')
            ->all();

        // Sandi default
        // Semua bersifat sementara dan wajib diganti pada login pertama.
        $kata_sandi = 'password';

        foreach ($staffData as [$kodeUnit, $nip, $username, $nama, $jabatan, $email, $hp]) {
            $unitId = $unitMap[$kodeUnit] ?? null;

            $values = [
                'tenant_id'         => $tenantId,
                'unit_id'           => $unitId,
                'nomor_induk'       => $nip,
                'username'          => $username,
                'nama_lengkap'      => $nama,
                'jabatan'           => $jabatan,
                'email'             => $email,
                'nomor_hp'          => $hp,
                'alamat'            => self::ALAMAT_RS,
                'kata_sandi'        => $kata_sandi,
                'is_aktif'          => true,
                'wajib_ganti_sandi' => true,
            ];

            // Strategi lookup 2-tahap untuk idempotency yang benar:
            // 1. Cari berdasarkan nomor_induk (unique constraint utama):
            //    mencegah UniqueConstraintViolationException ketika seeder lain
            //    (mis. InsidenSeeder) sudah membuat akun dengan NIP yang sama
            //    tetapi menggunakan email placeholder (mis. karu01@mer.test).
            // 2. Jika tidak ditemukan via NIP, cari via email.
            // 3. Jika masih tidak ditemukan, buat baru.
            $p = Pengguna::where('tenant_id', $tenantId)
                    ->where('nomor_induk', $nip)
                    ->first()
                ?? Pengguna::where('tenant_id', $tenantId)
                    ->where('email', $email)
                    ->first();

            if ($p) {
                $p->fill($values)->save();
            } else {
                $p = Pengguna::create($values);
            }

            // Sync seberapa banyak pun peran yang di-passing dengan spread operator
            $peranIds = array_map(fn($r) => $r->id, $peranList);
            $p->peran()->syncWithoutDetaching($peranIds);
        }

        $namaPeranStr = implode(', ', array_map(fn($r) => $r->nama_peran, $peranList));
        $this->command->line(sprintf(
            '    ✓ %d akun %s diproses.',
            count($staffData),
            $namaPeranStr
        ));
    }
}
