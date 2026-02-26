{{--
|--------------------------------------------------------------------------
| Cetak Laporan Insiden — PDF (laporan/pdf.blade.php)
|--------------------------------------------------------------------------
| Halaman PDF laporan insiden dengan kop surat resmi RSUD HM Ryacudu.
| Format disesuaikan 100% dengan referensi dokumen resmi surat dinas
| UPTD. RSUD HM. Ryacudu, Pemerintah Kabupaten Lampung Utara.
|
| Variabel dari service:
|   $insiden — App\Models\Insiden (eager-loaded relations)
|--------------------------------------------------------------------------
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Insiden — {{ $insiden->nomor_laporan }}</title>
    <style>
        /* ── Reset & Base ─────────────────────────────────────────── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            color: #000000;
            line-height: 1.5;
        }
        .page-wrapper {
            padding: 25px 50px 30px 50px;
        }

        /* ── KOP SURAT ────────────────────────────────────────────── */
        /* Layout 3-kolom: [logo kiri] | [teks tengah] | [logo kanan]  */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .kop-table td {
            padding: 0;
            vertical-align: middle;
        }
        .kop-col-logo {
            width: 80px;
            text-align: center;
        }
        .kop-col-logo img {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }
        .kop-col-tengah {
            text-align: center;
            padding: 0 8px;
        }
        /* Baris 1: Pemerintah Kabupaten — normal, uppercase */
        .kop-baris-pemkab {
            font-size: 11pt;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }
        /* Baris 2: Dinas Kesehatan — letter-spaced, normal weight */
        .kop-baris-dinas {
            font-size: 11pt;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 8px;
            margin-bottom: 1px;
        }
        /* Baris 3: RSUD — PALING BESAR, BOLD, ini adalah identitas utama */
        .kop-baris-rsud {
            font-size: 15pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 2px 0;
        }
        /* Baris 4–6: Alamat & kontak */
        .kop-baris-alamat {
            font-size: 9.5pt;
            font-weight: normal;
            margin-top: 2px;
            line-height: 1.4;
        }

        /* ── GARIS PEMBATAS KOP (double-line: tebal di atas, tipis di bawah) */
        .garis-kop-wrapper {
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .garis-kop-tebal {
            border: none;
            border-top: 3px solid #000;
            margin: 0;
        }
        .garis-kop-tipis {
            border: none;
            border-top: 1px solid #000;
            margin: 3px 0 0 0;
        }

        /* ── JUDUL LAPORAN ────────────────────────────────────────── */
        .judul-wrapper {
            text-align: center;
            margin-bottom: 3px;
        }
        .judul-utama {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            letter-spacing: 0.5px;
        }
        .judul-nomor {
            text-align: center;
            font-size: 11pt;
            margin-bottom: 16px;
        }

        /* ── SECTION HEADING ─────────────────────────────────────── */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background-color: #d9d9d9;
            padding: 4px 8px;
            margin-top: 14px;
            margin-bottom: 0;
            border: 1px solid #555;
            border-bottom: none;
        }

        /* ── TABEL DATA (field : value) ──────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table td {
            border: 1px solid #555;
            padding: 4px 8px;
            font-size: 11pt;
            vertical-align: top;
        }
        .data-table .label {
            width: 195px;
            font-weight: bold;
            background-color: #f0f0f0;
            white-space: nowrap;
        }
        .data-table .colon {
            width: 14px;
            font-weight: bold;
            background-color: #f0f0f0;
            text-align: center;
            padding-left: 2px;
            padding-right: 2px;
        }
        .data-table .value {
            background-color: #fff;
        }

        /* ── TAG LIST (array fields) ──────────────────────────────── */
        .tag-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .tag-list li {
            display: inline-block;
            background-color: #eeeeee;
            border: 1px solid #aaaaaa;
            padding: 1px 7px;
            font-size: 10pt;
            margin: 1px 2px 1px 0;
        }

        /* ── TEKS PANJANG (kronologi, tindakan) ───────────────────── */
        .teks-panjang {
            white-space: pre-line;
            line-height: 1.6;
            text-align: justify;
        }

        /* ── TABEL HISTORI TINDAK LANJUT ──────────────────────────── */
        .tl-table {
            width: 100%;
            border-collapse: collapse;
        }
        .tl-table th {
            border: 1px solid #555;
            padding: 4px 8px;
            font-size: 10pt;
            font-weight: bold;
            background-color: #d9d9d9;
            text-align: center;
        }
        .tl-table td {
            border: 1px solid #555;
            padding: 4px 8px;
            font-size: 10.5pt;
            vertical-align: top;
        }
        .tl-table td.center {
            text-align: center;
        }

        /* ── TANDA TANGAN ─────────────────────────────────────────── */
        .ttd-wrapper {
            margin-top: 30px;
            width: 100%;
        }
        .ttd-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ttd-table td {
            width: 50%;
            vertical-align: top;
            font-size: 11pt;
            padding: 0 10px;
        }
        .ttd-col-kiri {
            text-align: left;
        }
        .ttd-col-kanan {
            text-align: center;
        }
        .ttd-gap {
            height: 65px;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
        }
        .ttd-nip {
            font-size: 10pt;
        }

        /* ── FOOTER CETAK ─────────────────────────────────────────── */
        .footer-cetak {
            margin-top: 18px;
            padding-top: 5px;
            border-top: 1px solid #aaa;
            font-size: 8.5pt;
            color: #555;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">

        {{-- ================================================================
             KOP SURAT RESMI
             Hierarki (sesuai referensi dokumen):
               1. PEMERINTAH KABUPATEN LAMPUNG UTARA  (normal, kecil)
               2. D I N A S  K E S E H A T A N        (letter-spaced)
               3. UPTD. RUMAH SAKIT UMUM DAERAH HM. RYACUDU  (bold, besar)
               4. Alamat & kontak                      (kecil)
             ================================================================ --}}
        <table class="kop-table">
            <tr>
                {{-- Logo Kiri: Pemkab Lampung Utara --}}
                <td class="kop-col-logo">
                    <img src="{{ public_path('images/logo-pemkab.png') }}" alt="Logo Pemkab Lampung Utara">
                </td>

                {{-- Teks Kop Tengah --}}
                <td class="kop-col-tengah">
                    <div class="kop-baris-pemkab">Pemerintah Kabupaten Lampung Utara</div>
                    <div class="kop-baris-dinas">Dinas Kesehatan</div>
                    <div class="kop-baris-rsud">UPTD. Rumah Sakit Umum Daerah HM. Ryacudu</div>
                    <div class="kop-baris-alamat">
                        Jl. Jenderal Sudirman No.02 &nbsp;Telp/Fax. (0724) 22095<br>
                        KOTABUMI &ndash; 34511<br>
                        Email : rumahsakit_ryacudu@yahoo.com
                    </div>
                </td>

                {{-- Logo Kanan: RSUD --}}
                <td class="kop-col-logo">
                    <img src="{{ public_path('images/logo-rsud.jpg') }}" alt="Logo RSUD HM Ryacudu">
                </td>
            </tr>
        </table>

        {{-- Garis pembatas kop: satu garis tebal di atas, satu tipis di bawah --}}
        <div class="garis-kop-wrapper">
            <hr class="garis-kop-tebal">
            <hr class="garis-kop-tipis">
        </div>

        {{-- ================================================================
             JUDUL LAPORAN
             ================================================================ --}}
        <div class="judul-wrapper">
            <span class="judul-utama">Laporan Insiden Keselamatan Pasien</span>
        </div>
        <div class="judul-nomor">
            Nomor : {{ $insiden->nomor_laporan }}
        </div>

        {{-- ================================================================
             BAGIAN A — INFORMASI PELAPOR
             ================================================================ --}}
        <div class="section-title">A. &nbsp;Informasi Pelapor</div>
        <table class="data-table">
            @if ($insiden->is_anonim)
                <tr>
                    <td class="label">Status Pelapor</td>
                    <td class="colon">:</td>
                    <td class="value"><em>Anonim (identitas dirahasiakan)</em></td>
                </tr>
            @else
                <tr>
                    <td class="label">Nama Pelapor</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $insiden->nama_pelapor ?? $insiden->pelapor?->nama_lengkap ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Nomor Induk</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $insiden->pelapor?->nomor_induk ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Kontak Pelapor</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $insiden->kontak_pelapor ?? '—' }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Unit Kerja</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->nama_unit_kerja ?? $insiden->unitKerja?->nama_unit ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Lapor</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->tgl_lapor?->translatedFormat('d F Y') ?? '—' }}</td>
            </tr>
        </table>

        {{-- ================================================================
             BAGIAN B — DATA PASIEN
             ================================================================ --}}
        <div class="section-title">B. &nbsp;Data Pasien</div>
        <table class="data-table">
            <tr>
                <td class="label">Nama Pasien</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->detailPasien?->nama_pasien ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">No. Rekam Medis</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->detailPasien?->nomor_rekam_medis ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Obat Terkait</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->detailPasien?->obat_terkait ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Dokter Penulis Resep</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->detailPasien?->dokter_penulis_resep ?? '—' }}</td>
            </tr>
        </table>

        {{-- ================================================================
             BAGIAN C — RINCIAN KEJADIAN
             ================================================================ --}}
        <div class="section-title">C. &nbsp;Rincian Kejadian</div>
        <table class="data-table">
            <tr>
                <td class="label">Tanggal Kejadian</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->tgl_kejadian?->translatedFormat('d F Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Waktu Kejadian</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->tgl_kejadian?->format('H:i') ?? '—' }} WIB</td>
            </tr>
            <tr>
                <td class="label">Fase / Jenis Kesalahan</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->fase_kesalahan ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Tipe Insiden</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->labelTipeInsiden() }}</td>
            </tr>
            <tr>
                <td class="label">Status Laporan</td>
                <td class="colon">:</td>
                <td class="value">{{ $insiden->labelStatus() }}</td>
            </tr>
        </table>

        {{-- ================================================================
             BAGIAN D — KLASIFIKASI INSIDEN
             ================================================================ --}}
        @if ($insiden->detailPasien)
            <div class="section-title">D. &nbsp;Klasifikasi Insiden</div>
            <table class="data-table">
                @if ($insiden->detailPasien->jenis_kesalahan)
                    <tr>
                        <td class="label">Jenis Kesalahan</td>
                        <td class="colon">:</td>
                        <td class="value">
                            <ul class="tag-list">
                                @foreach ((array) $insiden->detailPasien->jenis_kesalahan as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
                @if ($insiden->detailPasien->cedera)
                    <tr>
                        <td class="label">Cedera yang Terjadi</td>
                        <td class="colon">:</td>
                        <td class="value">
                            <ul class="tag-list">
                                @foreach ((array) $insiden->detailPasien->cedera as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
                @if ($insiden->detailPasien->faktor_penyebab)
                    <tr>
                        <td class="label">Faktor Penyebab</td>
                        <td class="colon">:</td>
                        <td class="value">
                            <ul class="tag-list">
                                @foreach ((array) $insiden->detailPasien->faktor_penyebab as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
                @if ($insiden->detailPasien->intervensi_pasien)
                    <tr>
                        <td class="label">Intervensi pada Pasien</td>
                        <td class="colon">:</td>
                        <td class="value">
                            <ul class="tag-list">
                                @foreach ((array) $insiden->detailPasien->intervensi_pasien as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
            </table>
        @endif

        {{-- ================================================================
             BAGIAN E — KRONOLOGI KEJADIAN
             ================================================================ --}}
        @if ($insiden->detailPasien?->kronologi)
            <div class="section-title">E. &nbsp;Kronologi Kejadian</div>
            <table class="data-table">
                <tr>
                    <td class="value teks-panjang">{{ $insiden->detailPasien->kronologi }}</td>
                </tr>
            </table>
        @endif

        {{-- ================================================================
             BAGIAN F — TINDAKAN AWAL
             ================================================================ --}}
        @if ($insiden->detailPasien?->tindakan_awal)
            <div class="section-title">F. &nbsp;Tindakan Awal yang Dilakukan</div>
            <table class="data-table">
                <tr>
                    <td class="value teks-panjang">{{ $insiden->detailPasien->tindakan_awal }}</td>
                </tr>
            </table>
        @endif

        {{-- ================================================================
             BAGIAN G — HISTORI TINDAK LANJUT
             ================================================================ --}}
        @if ($insiden->tindakLanjut->isNotEmpty())
            <div class="section-title">G. &nbsp;Histori Tindak Lanjut</div>
            <table class="tl-table">
                <thead>
                    <tr>
                        <th style="width: 28px;">No.</th>
                        <th style="width: 115px;">Tanggal</th>
                        <th style="width: 110px;">Status Baru</th>
                        <th style="width: 130px;">Petugas</th>
                        <th>Catatan Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($insiden->tindakLanjut->sortBy('created_at')->values() as $idx => $tl)
                        <tr>
                            <td class="center">{{ $idx + 1 }}.</td>
                            <td class="center">{{ $tl->created_at?->translatedFormat('d M Y, H:i') }}</td>
                            <td class="center">{{ $tl->labelStatus() }}</td>
                            <td>{{ $tl->pengguna?->nama_lengkap ?? '—' }}</td>
                            <td>{{ $tl->catatan }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- ================================================================
             TANDA TANGAN
             Format mengikuti referensi: kiri = pelapor, kanan = tgl + pejabat
             ================================================================ --}}
        <div class="ttd-wrapper">
            <table class="ttd-table">
                <tr>
                    <td class="ttd-col-kiri">
                        Pelapor,
                    </td>
                    <td class="ttd-col-kanan">
                        Kotabumi, {{ $insiden->tgl_lapor?->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}<br>
                        Kepala Ruangan,
                    </td>
                </tr>
                <tr>
                    <td class="ttd-col-kiri">
                        <div class="ttd-gap"></div>
                    </td>
                    <td class="ttd-col-kanan">
                        <div class="ttd-gap"></div>
                    </td>
                </tr>
                <tr>
                    <td class="ttd-col-kiri">
                        <span class="ttd-nama">
                            @if ($insiden->is_anonim)
                                ( Anonim )
                            @else
                                {{ $insiden->nama_pelapor ?? $insiden->pelapor?->nama_lengkap ?? '..................................' }}
                            @endif
                        </span>
                        @if (! $insiden->is_anonim && $insiden->pelapor?->nomor_induk)
                            <div class="ttd-nip">NIP {{ $insiden->pelapor->nomor_induk }}</div>
                        @endif
                    </td>
                    <td class="ttd-col-kanan">
                        <span class="ttd-nama">..................................</span>
                        <div class="ttd-nip">NIP ..........................</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ================================================================
             FOOTER CETAK
             ================================================================ --}}
        <div class="footer-cetak">
            Dicetak pada {{ now()->translatedFormat('d F Y, H:i:s') }} WIB &nbsp;&bull;&nbsp;
            Sistem Pelaporan Insiden Keselamatan Pasien &mdash; UPTD. RSUD HM. Ryacudu
        </div>

    </div>
</body>
</html>
