<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Peran;
use App\Models\UnitKerja;
use Database\Seeders\Concerns\NormalizesUnitKerjaNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * ProductionPenggunaSeeder - data akun pengguna staging/production dari daftar CSV.
 *
 * Seeder ini sengaja mandiri: data pengguna sudah ditanam sebagai array PHP,
 * sehingga eksekusi di VPS tidak bergantung pada file CSV eksternal.
 *
 * Sumber data awal: database/seeders/data/pengguna-staging.csv
 * Format baris data: [nama_lengkap, username, unit_kerja, peran, kata_sandi]
 */
class ProductionPenggunaSeeder extends Seeder
{
    use NormalizesUnitKerjaNames;

    private const ALAMAT_RS = 'RSUD HM. Ryacudu, Jl. HM. Ryacudu No. 1, Kotabumi, Lampung Utara';

    /**
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private const PENGGUNA_DATA = [
        ['Administrator Sistem RSUD HM. Ryacudu', 'admin.rsud', '', 'Admin', 'password'],
        ['Direktur RSUD HM. Ryacudu', 'direktur.rsud', '', 'Direktur', 'password'],
        ['Anggota Komite Medik', 'komite.medik', '', 'Komite', 'password'],
        ['Anggota Komite Mutu dan Keselamatan', 'komite.mutu', '', 'Komite', 'password'],
        ['Ketua Komite Keselamatan Pasien', 'komite.keselamatan', '', 'Komite', 'password'],
        ['Aprilinda, Amd. Kep', 'aprilinda', 'Poli Saraf', 'Nakes', 'password'],
        ['Desi Nopiyanti, S.Si.,Apt', 'desinopi', 'Instalasi Farmasi', 'Nakes', 'password'],
        ['Devi Zana Junjungan, Amd.Kep', 'devizana', 'IGD', 'Nakes', 'password'],
        ['Diana Sari, S.Farm', 'dianasari', 'Instalasi Farmasi', 'Nakes', 'password'],
        ['Eni Lestari, Amd.Kep', 'enilestari', 'Ruang Saraf', 'Nakes', 'password'],
        ['Heni Apriyani, Amd.Kep', 'heniapri', 'ICU', 'Nakes', 'password'],
        ['Herlina Wati, Amd. Keb', 'herlinawati', 'Poli Kebidanan', 'Nakes', 'password'],
        ['Isti Hernani, Amd.Kep', 'istihernani', 'Poli Kulit Kelamin', 'Nakes', 'password'],
        ['Meida Liana, S.ST.,M.Kes', 'meidaliana', 'Ruang PONEK', 'Nakes', 'password'],
        ['Meri Maydiana, Amd.Keb', 'merimaydiana', 'Ruang Kebidanan', 'Nakes', 'password'],
        ['Merita Sundari, Amd.Kep', 'meritasundari', 'Ruang Bedah', 'Nakes', 'password'],
        ['Muhartini, SST', 'muhartini', 'Ruang Kebidanan', 'Nakes', 'password'],
        ['Niryana, Amd.Kep', 'niryana', 'Ruang Anak', 'Nakes', 'password'],
        ['Novi Dahlia, Amd.Kep', 'novidahlia', 'Ruang Penyakit Dalam', 'Nakes', 'password'],
        ['Ns. April Nita, S.Kep', 'aprilnita', 'Poli Mata', 'Nakes', 'password'],
        ['Ns. Bayu Irda Manita, S.Kep', 'bayuirda', 'IGD', 'Nakes', 'password'],
        ['Ns. Dahlia, S.Kep', 'dahlia', 'Poli Anak', 'Nakes', 'password'],
        ['Ns. Deni Frengky, S.Kep', 'denifrengky', 'Poli Jiwa', 'Nakes', 'password'],
        ['Ns. Deni Novice, S.Kep', 'deninovice', 'Ruang Saraf', 'Nakes', 'password'],
        ['Ns. Desy Hastuti, S.Kep', 'desyhastuti', 'Poli Anestesi', 'Nakes', 'password'],
        ['Ns. Fitriantina, S.Kep', 'fitriantina', 'Poli Jiwa', 'Nakes', 'password'],
        ['Ns. Haris Awaludin, S.Kep', 'harisawaludin', 'ICU', 'Nakes', 'password'],
        ['Ns. Hartati, S.Kep', 'hartati', 'Poli Anak', 'Nakes', 'password'],
        ['Ns. Ida Yati, S.Kep', 'idayati', 'Poli Bedah', 'Nakes', 'password'],
        ['Ns. Juli Prabowo, S.Kep', 'juliprabowo', 'Ruang HD', 'Nakes', 'password'],
        ['Ns. Karwanti, S.Kep', 'karwanti', 'Poli Paru', 'Nakes', 'password'],
        ['Ns. Komariah, S.Kep', 'komariah', 'Poli Mata', 'Nakes', 'password'],
        ['Ns. Ledi Oktaria, S.Kep', 'ledioktaria', 'Poli Anestesi', 'Nakes', 'password'],
        ['Ns. Mad Jahuri, S.Kep', 'madjahuri', 'Ruang Penyakit Dalam', 'Nakes', 'password'],
        ['Ns. Muliana, S.Kep', 'muliana', 'Poli Paru', 'Nakes', 'password'],
        ['Ns. Nova Liana Sari, S.Kep', 'novaliana', 'Ruang Bedah', 'Nakes', 'password'],
        ['Ns. Rahmad Saleh, S.Kep', 'rahmadsaleh', 'Ruang Isolasi B', 'Nakes', 'password'],
        ['Ns. Rahmawaty, S.Kep', 'rahmawaty', 'Ruang VIP', 'Nakes', 'password'],
        ['Ns. Resmareny, S.Kep', 'resmareny', 'Ruang Neonatus', 'Nakes', 'password'],
        ['Ns. Revi Sesiana, S.Kep', 'revisesiana', 'Ruang Anak', 'Nakes', 'password'],
        ['Ns. Riduan Husin, S.Kep', 'riduanhusin', 'Instalasi Bedah Sentral (IBS)', 'Nakes', 'password'],
        ['Ns. Sri Anggareny, S.Kep', 'srianggareny', 'Poli THT', 'Nakes', 'password'],
        ['Ns. Suhartini, S.Kep', 'suhartini', 'Poli Penyakit Dalam', 'Nakes', 'password'],
        ['Ns. Tri Hananto, S.Kep', 'trihananto', 'Ruang HD', 'Nakes', 'password'],
        ['Ns. Via Gina Mahardhika, S.Kep', 'viagina', 'Ruang Neonatus', 'Nakes', 'password'],
        ['Ns. Vicania, S.Kep', 'vicania', 'Poli Penyakit Dalam', 'Nakes', 'password'],
        ['Ns. Yeni Narina, S.Kep', 'yeninarina', 'Instalasi Bedah Sentral (IBS)', 'Nakes', 'password'],
        ['Ns. Yuli Afrida, S.Kep', 'yuliafrida', 'Poli Kulit Kelamin', 'Nakes', 'password'],
        ['Ns. Zulda Purnawati, S.Kep', 'zuldapurnawati', 'Poli Orthopedi', 'Nakes', 'password'],
        ['Oktarina, Amd.Kep', 'oktarina', 'Poli Bedah', 'Nakes', 'password'],
        ['Puji Astuti Sepriani, AMKG', 'pujiastuti', 'Poli Gigi', 'Nakes', 'password'],
        ['Sari Paulina, Amd.Kep', 'saripaulina', 'Ruang VIP', 'Nakes', 'password'],
        ['Septia Meinitasari, Amd.Keb', 'septiamei', 'Ruang PONEK', 'Nakes', 'password'],
        ['Sri Rizki Yanti, Amd.Kep', 'sririzki', 'Poli THT', 'Nakes', 'password'],
        ['Sri Sumini, Amd.Kep', 'srisumini', 'Poli Saraf', 'Nakes', 'password'],
        ['Sydesma Alia, Amd.Keb', 'sydesmaalia', 'Ruang VK', 'Nakes', 'password'],
        ['Wardalia, SST', 'wardalia', 'Poli Kebidanan', 'Nakes', 'password'],
        ['Yuli Caturini, STT.,M.Kes', 'yulicaturini', 'Ruang VK', 'Nakes', 'password'],
        ['Dosen Peneliti', 'dosenpeneliti', '', 'Peneliti', 'Peneliti@2026!'],
        ['dr. Nanik Zulaichah, Sp. KK', '', 'Poliklinik Kulit Kelamin', 'Nakes', 'password'],
        ['Ns. Parnila, S. Kep', '', 'Poliklinik Kulit Kelamin', 'Nakes', 'password'],
        ['Yulita, A.Md.Kep', '', 'Poliklinik Saraf', 'Nakes', 'password'],
        ['dr. Doddy Afprianto, M.Sc.,Sp. PD', '', 'Poliklinik Penyakit Dalam', 'Nakes', 'password'],
        ['Ns. Suharti Rahayu, S. Kep', '', 'Poliklinik Penyakit Dalam', 'Nakes', 'password'],
        ['Ns. Rita Novalinda, S. Kep', '', 'Poliklinik Penyakit Dalam', 'Nakes', 'password'],
        ['Denti', '', 'Poliklinik Penyakit Dalam', 'Nakes', 'password'],
        ['drg. Fitri Setia Rahayu, Sp. KG', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['drg. Wiewied Priosambodo, Sp.Perio', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['drg. Rakhmida Sari', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Cumiati, AMKG', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Puji Astuti Sepriani, A.Md.KG', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Zidni Aulia Rahma S. Tr. Kes', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Wiwin Agung Setiawati, S.Tr.KG', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Choirinnisa, S. Tr. Kes', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Aniq Fadliatuz, S. Tr. Kes', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Fathatin Jawad, S. Tr. Kes', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Fattah Kurniawan, S. Tr. Kes', '', 'Poliklinik Gigi', 'Nakes', 'password'],
        ['Aqida El Fadila', '', 'Poliklinik Mata', 'Nakes', 'password'],
        ['April Hairi', '', 'Poliklinik Mata', 'Nakes', 'password'],
        ['Belly Sutopo, Sp. THT., KL', '', 'Poliklinik THT', 'Nakes', 'password'],
        ['Ns. Berta Septarina, S.Kep', '', 'Poliklinik THT', 'Nakes', 'password'],
        ['Sri Rizki Yanti, A. Md. Kep', '', 'Poliklinik THT', 'Nakes', 'password'],
        ['Agitya Yohana, A.Md.Kep', '', 'Poliklinik THT', 'Nakes', 'password'],
        ['Ona Siska, S.Tr.Keb', '', 'Poliklinik  Kebidanan', 'Nakes', 'password'],
        ['Herlina', '', 'Poliklinik  Kebidanan', 'Nakes', 'password'],
        ['Dewi Listiowati, S. Tr.Keb', '', 'Poliklinik  Kebidanan', 'Nakes', 'password'],
        ['Deviana Pratama Christianti, A. Md. Keb', '', 'Poliklinik  Kebidanan', 'Nakes', 'password'],
        ['Ns.Hesti Mutiance, S. Kep', '', 'Poliklinik Anestesi', 'Nakes', 'password'],
        ['Ns.Ledi Oktaria Th, S.Kep', '', 'Poliklinik Anestesi', 'Nakes', 'password'],
        ['dr. I Kadek Suaryana, M.Biomed, Sp.Kj', '', 'Poliklinik Jiwa', 'Nakes', 'password'],
        ['dr. Novi Susilowati, Sp. KJ', '', 'Poliklinik Jiwa', 'Nakes', 'password'],
        ['Ns. Sry Anggraeny, S.Kep', '', 'Poliklinik Jiwa', 'Nakes', 'password'],
        ['Ns. Dhanang Ardi Saputra, S.Kep', '', 'Poliklinik Jiwa', 'Nakes', 'password'],
        ['Ns. Dahlia, S.Kep', '', 'Poliklinik Bedah', 'Nakes', 'password'],
        ['Isti Hernani, A.Md.Kep', '', 'Poliklinik Bedah', 'Nakes', 'password'],
        ['Muliana, S.Kep., Ns', '', 'Poliklinik Paru', 'Nakes', 'password'],
        ['Karwanti, S.Kep.,Ns', '', 'Poliklinik Paru', 'Nakes', 'password'],
        ['Khoiriyah, A.Md.Kep', '', 'Poliklinik Paru', 'Nakes', 'password'],
        ['Octarina, A. Md. Kep', '', 'Poliklinik Paru', 'Nakes', 'password'],
        ['Ns. Komariah MK, S.Kep', '', 'Poliklinik Tumbuh Kembang Anak', 'Nakes', 'password'],
        ['Ika Miarti, A. Md. Kep', '', 'Poliklinik Tumbuh Kembang Anak', 'Nakes', 'password'],
        ['dr. Budi Agus Setiawan, Sp. OT', '', 'Poliklinik Orthopedi', 'Nakes', 'password'],
        ['Ns. Zulda Purnawati, S. Kep', '', 'Poliklinik Orthopedi', 'Nakes', 'password'],
        ['Lusiana Usman, A. Md. Kep', '', 'Poliklinik Orthopedi', 'Nakes', 'password'],
        ['Mukhlisin, S. Kep', '', 'Ruang Cese Manager', 'Nakes', 'password'],
        ['Ns. Aprilnita,S.Kep', '', 'Ruang Cese Manager', 'Nakes', 'password'],
        ['Isana Oktaria, A. Md. Kep', '', 'Ruang Cese Manager', 'Nakes', 'password'],
        ['dr. Betty Soedaly, Sp. S', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Ns. Deni Novice, S.Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Nizar Seprida, A.Md.Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Ns.Melisa Megayanti Turnip,S.Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Ns. Dini, S.Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Ns. Mardiyanti, S.Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Ns. Evi Anggraini Gultom, S.Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Eni Lestari, A. Md. Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Meliya Sari, A. Md. Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Heni Apriyani, A. Md. Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Triyana, A.Md.Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Efriyana, A. Md. Kep', '', 'RUANG : SARAF', 'Nakes', 'password'],
        ['Ns. Revi Sesiana, S.Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Aprilinda, A. Md. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Ns.Dwi Afriyanti, S.Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Baita, A. Md. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Ria Ariyana, S. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Sri Nurhayati, A.Md.Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Niryana, A. Md. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Isri Yana Putri Yani, A. Md. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Susilawati, A. Md. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Ns.Tommy Ari Sandy, S. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Shelvita Mandasari, A. Md. Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['Riya Septiana. A.Md.Kep', '', 'RUANG ANAK', 'Nakes', 'password'],
        ['dr. Mareti Pandan Ayu, Sp. OG', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Muhartini, SST', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Angga Selvia,S.Tr. Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Mery Maydiana, A. Md. Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Anisa Febriani, A.Md.Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Siti Azerina Harahap, S.Keb., Bd', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Septika Zahra, S. Tr. Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Yuli Astuti, A.Md.Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Gendhy Prima Putri, S.Keb., Bd', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Afriyanti Margaretha, A.Md.Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Homsah Fitriyanti, A. Md.Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Iis Agustina, A. Md. Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Jayanti Mulinda Sari, A. Md. Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['Eka Novita Handayani, A.Md.Keb', '', 'Ruang KEBIDANAN', 'Nakes', 'password'],
        ['dr. Jan Markus S, Sp. B', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Ns. Riduan Husien, S.Kep', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Ns. Hendra, S.Kep', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Hidar Hamzah', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Tati Hartati', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Ns. Ega Frihani Wiagi, S.Kep', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Tri Agung Nugroho, A.Md.Kep', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Deni Muhammad I.T, S.Kep., Ns', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Yeni Marina, S.Kep., Ns', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Sugi Hartono, S.Kep.Ns', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Ernawati, S.Kep., Ns', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Sigit Febiantoro, A. Md. Kep', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['Rinta Karisma Dewi , SST', '', 'INSTALASI BEDAH SENTRAL', 'Nakes', 'password'],
        ['dr. H. Joko Susilo Sp. An', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['dr. Haris Riyadi, M. Kes., Sp. An', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['Jamaluddin', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['Evita Gani', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['Zulkifli', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['Taufik, S. Kep', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['Dafania Megananda Ayu', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['Novian Adi Sabhara, A.Md.Kep', '', 'RUANG ANESTESI', 'Nakes', 'password'],
        ['Ns.Nova Liana Sari, S.Kep.', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Yunita Yanti, A.Md.Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Ns. Widya Puspitasari, S.Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Ns. Ari Handoko, S.Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Nia Susanti, A.Md.Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Al Fadli Abdurrohim SR. A. Md. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Zulfikri, A. Md. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Ns. Vivi Adrima Adni, S. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Ns. Soerika Januarta S. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Arya Putra, A.Md.Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Merita Sundari, A. Md. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Nindita Hermawati', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Berza Candry, A. Md. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Reni Novita, A. Md. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['Rika Oktaria, A. Md. Kep', '', 'RUANG BEDAH', 'Nakes', 'password'],
        ['dr. Saipul Huda, S. PD', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['dr. I Gede Putu Arinanda, Sp. PD', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['dr. Apriani', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Ns. Mad Jahuri, S.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Marlia Tanjungan,A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Ns. Jaka Juniver, S.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Yuriza Hanifah, A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Ns. Angga Bagus Widya Saputra, S.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['M. Fadillah Mextio,A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Ns. Sepki Anggarini, S.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Novi Dahlia, A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Insiatun, A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Meriza afialis, A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Helna Sari, A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Maria Ramadini, A. Md. Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Nurul Husna, A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Ayu Samfika, A.Md.Kep', '', 'RUANG PENYAKIT DALAM', 'Nakes', 'password'],
        ['Ns. Eka Oktasari, S. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Ns. Eni Suryani, S. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Ns. Riza Umami, S.Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Ns. Epi Suspalinda, S. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Rio Pradana Adri, A. Md. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Ns. Butro Zagali, S.Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Yuli Purnama Sari, A. Md. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Meri Puji Astuti, A.Md.Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Muchowir, S. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Sari Paulina', '', 'VIP.', 'Nakes', 'password'],
        ['Desi Yana Guba, A. Md. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['Ns.Riya Astuti, S. Kep', '', 'VIP.', 'Nakes', 'password'],
        ['apt.Desi Nopi Yanti, S. Si,', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Vita Rahmawati, S.Farm', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Diana Sari, A.Md.Farm', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Elvina Sary, A.Md.Farm', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Yulida Sari, SE', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Fitri Yanti', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Sri Ramis, SE', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Baninar, A. Md. F', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Linda Firyani', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Apt. Yeni Setyawati, S.Far.', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Apt. Metha Linda Yanti, S. Farm', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Anggun Yulistiani, A.Md', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Ongga Novanda, SH', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Mela Sari, A.Md.Keb', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Mike Lusia', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Darma Kesuma Jaya, S. Kom', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Nova Ria Safitri, A.Md.Keb', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Dera Isjayanti, A.Md.Keb', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Eka Havitasyari Anom, A.Md.Keb', '', 'INSTALASI FARMASI', 'Nakes', 'password'],
        ['Ns, Haris Awaludin, S. Kep', '', 'ICU', 'Nakes', 'password'],
        ['Kurnia Akbar FR,A.Md.Kep', '', 'ICU', 'Nakes', 'password'],
        ['Ns. Arif Tahta Prayogi, S.Kep', '', 'ICU', 'Nakes', 'password'],
        ['Heny Septina, S.Kep', '', 'ICU', 'Nakes', 'password'],
        ['Nur Susilawati, A.Md.Kep', '', 'ICU', 'Nakes', 'password'],
        ['Jumraini, A.Md.Kep', '', 'ICU', 'Nakes', 'password'],
        ['Atika Triyani, A.Md.Kep', '', 'ICU', 'Nakes', 'password'],
        ['Ns. Riensi Nurdika Yani, S.Kep', '', 'ICU', 'Nakes', 'password'],
        ['Tri Mardyandoko, A. Md. Kep', '', 'ICU', 'Nakes', 'password'],
        ['Ns. Bayu Irda Manita, S. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Ns. Hendra Setya Pratama, S. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Haris Iswanto, A. Md. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Ariyanti, A. Md. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Ns. Romie Saputra, S.Kep', '', 'IGD', 'Nakes', 'password'],
        ['Firmansyah, A.Md.Kep', '', 'IGD', 'Nakes', 'password'],
        ['Hendra MP Halil, A. Md. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Ns.Syandri Irawan, S.Kep', '', 'IGD', 'Nakes', 'password'],
        ['Rahmad Gani,A.Md.Kep', '', 'IGD', 'Nakes', 'password'],
        ['Devi Zana Junjungan, A. Md. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Juli Agus Stiawan, A. Md. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Ahmad Sunandar, A. Md. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Ervan Wijaya, S.Kep., NS', '', 'IGD', 'Nakes', 'password'],
        ['Deni Johansyah R, S. Kep', '', 'IGD', 'Nakes', 'password'],
        ['Dwi Wahyudi, A.Md.Kep', '', 'IGD', 'Nakes', 'password'],
        ['M. Imam Saputra, A.Md.Kep', '', 'IGD', 'Nakes', 'password'],
        ['Achmad Rikho Benisya Rio BN, A.Md.Kep', '', 'IGD', 'Nakes', 'password'],
        ['Resmareny, S.Kep., Ns', '', 'NEONATUS', 'Nakes', 'password'],
        ['Ns. Meila Suri, S.Kep', '', 'NEONATUS', 'Nakes', 'password'],
        ['Ns. Neli Putri, S. Kep', '', 'NEONATUS', 'Nakes', 'password'],
        ['Renika, S. Tr. Keb', '', 'NEONATUS', 'Nakes', 'password'],
        ['Martini, A. Md. Kep', '', 'NEONATUS', 'Nakes', 'password'],
        ['Erika Permana, S.Kep.,Ns', '', 'NEONATUS', 'Nakes', 'password'],
        ['Zur\'Aini Wulandari, A.Md.Keb', '', 'NEONATUS', 'Nakes', 'password'],
        ['Yeni Firda, A. Md. Keb', '', 'NEONATUS', 'Nakes', 'password'],
        ['Afrizal, A.Md.Kep', '', 'NEONATUS', 'Nakes', 'password'],
        ['Novi Darmita, A.Md.Keb', '', 'NEONATUS', 'Nakes', 'password'],
        ['Mauli Nopitri, A. Md. Keb', '', 'NEONATUS', 'Nakes', 'password'],
        ['Via Gina Mahardhika, S.Kep.,Ns', '', 'NEONATUS', 'Nakes', 'password'],
        ['dr. Vemi Fitria, Sp. P', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Ns. Rahmawaty, S.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Ns. Saiyidah, S. Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Ns. Tati Wulandari, S.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Ns. Santi Astri, A.Md.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Melly Rosalia Indah, A.Md.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Mardona Arisando, A. Md. Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Gandi Irawan, A. Md. Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Fera Suciawati, A.Md.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Merry Octarina, A.Md.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Rizqi Dwi Apriyanto, A.Md.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Mardiyana, A. Md. Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Rahmad Mulyono, A.Md.Kep', '', 'RUANG PARU', 'Nakes', 'password'],
        ['Meida Liana, S. ST., M. Kes', '', 'PONEK', 'Nakes', 'password'],
        ['Misni Agustin, SST', '', 'PONEK', 'Nakes', 'password'],
        ['Uci Marina, SST', '', 'PONEK', 'Nakes', 'password'],
        ['Apriyanti  A. Md. Keb', '', 'PONEK', 'Nakes', 'password'],
        ['Anita Gina Indriyana, SST', '', 'PONEK', 'Nakes', 'password'],
        ['Pika Putri Purnama Dewi, SST', '', 'PONEK', 'Nakes', 'password'],
        ['Oktaria Sari, A.Md.Keb', '', 'PONEK', 'Nakes', 'password'],
        ['Devita Amalia, A.Md.Keb', '', 'PONEK', 'Nakes', 'password'],
        ['Lilia Faristi, SST', '', 'PONEK', 'Nakes', 'password'],
        ['Ima Agustina, A. Md. Keb', '', 'PONEK', 'Nakes', 'password'],
        ['Vetti Silvia, A.Md.Keb', '', 'PONEK', 'Nakes', 'password'],
        ['Seftia Meinita Sari, A.Md.Keb', '', 'PONEK', 'Nakes', 'password'],
        ['Fadhila Agnia, A.Md.Keb', '', 'PONEK', 'Nakes', 'password'],
        ['Evi Herlinda, S.Tr.Keb., M.Kes', '', 'PONEK', 'Nakes', 'password'],
        ['Erry Nathalia, A. Md. Keb', '', 'PONEK', 'Nakes', 'password'],
        ['dr. Artyca Wahyu Utami', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Ns. Tri Hananto, S.Kep', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Hi. Supardi, A.Md.Kep', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Ns. Juli Prabowo, S.Kep', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Mario Martin, A. Md. Kep', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Rendi Yosfi Kurniawan, A.Md.Kep', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Selvia Sari, A.Md.Kep', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Sunarmila    ( Adm )', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Marlia Tanjungan,A.Md.Kep', '', 'HEMODIALISA', 'Nakes', 'password'],
        ['Yuli Caturini, SST.,M.Kes', '', 'RUANG VK', 'Nakes', 'password'],
        ['Feny Eka Putri Isun. SST', '', 'RUANG VK', 'Nakes', 'password'],
        ['Tania Cantika, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Weci Vectoria, S.ST', '', 'RUANG VK', 'Nakes', 'password'],
        ['Amelia Rosadi, SST', '', 'RUANG VK', 'Nakes', 'password'],
        ['Maya Zamarmah, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Dina Ariani, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Sydesma Alia, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Siti Handayani, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Meila Sari, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Siti Husna, A.Md.Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Desti Candra Yunita, A.Md.Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Riani, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Ria Febrianti, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Meiza Trizna, A. Md. Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Eva Astin Qomariah, A.Md.Keb', '', 'RUANG VK', 'Nakes', 'password'],
        ['Ns.Rahmad Saleh, S.Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Ns.Linda Hermalia, S.Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Ns. Metty Anggraeni, S. Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Mohammad Salim, A.Md.Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Dennyy Lestari, A.Md.Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Desi Satya Purwaningsih, A.Md.Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Eron Eka Putra, A.Md.Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Andika Oktario, A. Md. Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Ana Maryana, A. Md. Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['Fatmawati, A.Md. Kep', '', 'ISOLASI B', 'Nakes', 'password'],
        ['dr. Febi Merdika', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. Ficky Orina Sari', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. Wawan Ridwan', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. Alef Adlia Rahmani', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. Santo Fitriantoro', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. Agung Laksana', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. Imbri Fernando Ginting', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. Refa A.', '', 'DOKTER UMUM', 'Nakes', 'password'],
        ['dr. M. Azzibaginda Ganie', '', 'DOKTER UMUM', 'Nakes', 'password'],
    ];

    /**
     * Jalankan database seeds.
     *
     * Seeder ini membersihkan ulang akun.pengguna dan akun.pengguna_peran,
     * lalu mengisi ulang seluruh pengguna dari PENGGUNA_DATA.
     * Unit kerja yang belum ada akan dibuat otomatis.
     */
    public function run(): void
    {
        $tenantId = DB::table('tenant.organisasi')->value('id');

        if (!$tenantId) {
            $this->command->error('❌ Tenant ID tidak ditemukan. Jalankan OrganisasiSeeder terlebih dahulu.');
            return;
        }

        if (self::PENGGUNA_DATA === []) {
            $this->command->error('❌ Data pengguna kosong. Database tidak dibersihkan.');
            return;
        }

        $baris = 0;
        $berhasil = 0;
        $dilewati = 0;

        try {
            DB::beginTransaction();

            $this->command->info('🧹 Membersihkan database sebelum import pengguna...');
            DB::statement('TRUNCATE TABLE akun.pengguna RESTART IDENTITY CASCADE');

            $peranMap = $this->ensurePeran($tenantId);
            $unitSeedResult = $this->ensureCanonicalUnitKerja($tenantId);
            $this->command->line("    ✓ Unit kerja kanonis tersedia ({$unitSeedResult['created']} baru, {$unitSeedResult['updated']} diperbarui).");
            $unitMap = $this->existingUnitMap($tenantId);

            foreach (self::PENGGUNA_DATA as [$namaLengkap, $usernameRaw, $unitKerja, $peran, $kataSandi]) {
                $baris++;

                $namaLengkap = trim($namaLengkap);
                $usernameRaw = trim($usernameRaw);
                $unitKerja = trim($unitKerja);
                $peran = trim($peran);
                $kataSandi = trim($kataSandi);

                if ($namaLengkap === '' || $namaLengkap === '-' || $peran === '' || $peran === '-') {
                    $dilewati++;
                    continue;
                }

                $username = $this->makeUniqueUsername(
                    $usernameRaw !== '' && $usernameRaw !== '-'
                        ? strtolower($usernameRaw)
                        : $this->usernameFromName($namaLengkap)
                );

                $penggunaId = DB::table('akun.pengguna')->insertGetId([
                    'tenant_id' => $tenantId,
                    'unit_id' => $this->resolveUnitId($tenantId, $unitMap, $unitKerja),
                    'nomor_induk' => $this->makeUniqueNomorInduk($tenantId, $username),
                    'username' => $username,
                    'email' => null,
                    'nomor_hp' => null,
                    'alamat' => self::ALAMAT_RS,
                    'nama_lengkap' => $namaLengkap,
                    'kata_sandi' => Hash::make($kataSandi !== '' && $kataSandi !== '-' ? $kataSandi : 'password'),
                    'wajib_ganti_sandi' => true,
                    'is_aktif' => true,
                    'jabatan' => $peran,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('akun.pengguna_peran')->insert([
                    'pengguna_id' => $penggunaId,
                    'peran_id' => $peranMap[strtolower($peran)],
                ]);

                $berhasil++;
            }

            DB::commit();

            $this->command->info("🎉 Sukses! {$berhasil} pengguna berhasil dibuat dari data seeder. {$dilewati} baris dilewati.");
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->command->error("❌ Terjadi kesalahan fatal pada data ke-{$baris}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string, int>
     */
    private function ensurePeran(int $tenantId): array
    {
        $peranMap = [];

        foreach (array_unique(array_map(fn (array $row): string => trim($row[3]), self::PENGGUNA_DATA)) as $namaPeran) {
            if ($namaPeran === '' || $namaPeran === '-') {
                continue;
            }

            $peran = Peran::firstOrCreate([
                'tenant_id' => $tenantId,
                'nama_peran' => $namaPeran,
            ]);

            $peranMap[strtolower($namaPeran)] = $peran->id;
        }

        return $peranMap;
    }

    /**
     * @return array<string, int>
     */
    private function existingUnitMap(int $tenantId): array
    {
        $unitMap = [];

        UnitKerja::query()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'kode_unit', 'nama_unit'])
            ->each(function (UnitKerja $unit) use (&$unitMap): void {
                $namaUnit = $this->normalizeUnitKerjaName($unit->nama_unit) ?? $unit->nama_unit;
                $key = $this->unitKerjaLookupKey($namaUnit);
                $officialCode = $this->canonicalUnitKerjaCodeForName($namaUnit);

                if (!isset($unitMap[$key]) || ($officialCode !== null && $unit->kode_unit === $officialCode)) {
                    $unitMap[$key] = $unit->id;
                }
            });

        return $unitMap;
    }

    /**
     * @param  array<string, int>  $unitMap
     */
    private function resolveUnitId(int $tenantId, array &$unitMap, string $unitKerja): ?int
    {
        $unitKerja = $this->normalizeUnitKerjaName($unitKerja);

        if ($unitKerja === null) {
            return null;
        }

        $key = $this->unitKerjaLookupKey($unitKerja);

        if (isset($unitMap[$key])) {
            return $unitMap[$key];
        }

        $unit = UnitKerja::create([
            'tenant_id' => $tenantId,
            'kode_unit' => $this->makeUniqueKodeUnit($tenantId, $unitKerja),
            'nama_unit' => $unitKerja,
            'keterangan' => null,
        ]);

        $unitMap[$key] = $unit->id;

        return $unit->id;
    }

    private function usernameFromName(string $namaLengkap): string
    {
        $namaTanpaGelar = $this->nameWithoutTitles($namaLengkap);
        $cleanName = preg_replace('/[^a-zA-Z\s]/', ' ', $namaTanpaGelar) ?? $namaTanpaGelar;
        $cleanName = preg_replace('/\s+/', ' ', $cleanName) ?? $cleanName;
        $username = Str::slug(trim($cleanName), '.');

        return $username !== '' ? $username : 'pengguna';
    }

    private function nameWithoutTitles(string $namaLengkap): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', $namaLengkap) ?? $namaLengkap);
        $gelarDepanPattern = '/^(?:drg?|ns|prof|apt|hi|hj|h)(?:\.|,|\s+)/i';

        while (preg_match($gelarDepanPattern, $nama) === 1) {
            $nama = trim(preg_replace($gelarDepanPattern, '', $nama) ?? $nama);
        }

        $nama = trim(explode(',', $nama, 2)[0]);

        $gelarBelakangPatterns = [
            '/(?:\s|\.)+(?:A\.?\s*Md\.?(?:\s*\.?\s*(?:Kep|Keb|KG|Farm))?|AMKG|S\.?\s*Tr\.?\s*(?:Kep|Keb|Kes|KG)?|S\.?\s*Kep\.?|S\.?\s*Far(?:m)?\.?|S\.?\s*Farm\.?|S\.?\s*Si\.?|SST|STT|M\.?\s*Kes\.?|M\.?\s*Sc\.?|M\.?\s*Biomed\.?|Sp\.?\s*[A-Za-z.]+(?:\s*[A-Za-z.]+)?|Apt|Bd|Ns)\.?$/i',
        ];

        do {
            $sebelum = $nama;

            foreach ($gelarBelakangPatterns as $pattern) {
                $nama = trim(preg_replace($pattern, '', $nama) ?? $nama);
            }
        } while ($nama !== $sebelum);

        $nama = trim(preg_replace('/\s+/', ' ', $nama) ?? $nama);

        return $nama !== '' ? $nama : $namaLengkap;
    }

    private function makeUniqueUsername(string $username): string
    {
        $base = Str::limit($username !== '' ? $username : 'pengguna', 45, '');
        $candidate = $base;
        $counter = 2;

        while (DB::table('akun.pengguna')->where('username', $candidate)->exists()) {
            $suffix = '.' . $counter;
            $candidate = Str::limit($base, 50 - strlen($suffix), '') . $suffix;
            $counter++;
        }

        return $candidate;
    }

    private function makeUniqueNomorInduk(int $tenantId, string $username): string
    {
        $base = Str::limit($username !== '' ? $username : 'pengguna', 95, '');
        $candidate = $base;
        $counter = 2;

        while (
            DB::table('akun.pengguna')
                ->where('tenant_id', $tenantId)
                ->where('nomor_induk', $candidate)
                ->exists()
        ) {
            $suffix = '-' . $counter;
            $candidate = Str::limit($base, 100 - strlen($suffix), '') . $suffix;
            $counter++;
        }

        return $candidate;
    }

    private function makeUniqueKodeUnit(int $tenantId, string $namaUnit): string
    {
        $base = Str::limit(strtoupper(Str::slug($namaUnit, '_')), 45, '');
        $base = $base !== '' ? $base : 'UNIT';
        $candidate = $base;
        $counter = 2;

        while (
            UnitKerja::query()
                ->where('tenant_id', $tenantId)
                ->where('kode_unit', $candidate)
                ->exists()
        ) {
            $suffix = '_' . $counter;
            $candidate = Str::limit($base, 50 - strlen($suffix), '') . $suffix;
            $counter++;
        }

        return $candidate;
    }
}
