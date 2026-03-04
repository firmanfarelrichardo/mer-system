@php
    /*
    |---------------------------------------------------------------
    | Konfigurasi Cetak:
    | margin → cm (top, right, bottom, left)
    | scale  → persentase (100 = normal, 90 = lebih kecil, dsb.)
    |
    | Dikirim dari controller via compact('margin','scale').
    | Margin dihitung simetris — konten selalu di tengah kertas.
    |---------------------------------------------------------------
    */
    $mt = $margin['top']    ?? 2.5;
    $mr = $margin['right']  ?? 2.5;
    $mb = $margin['bottom'] ?? 2.5;
    $ml = $margin['left']   ?? 2.5;

    /*
    | Tinggi estimasi kop surat untuk padding-top konten agar tidak
    | tertindih dengan fixed header di halaman pertama.
    | Logo 90px ≈ 2.38cm, teks + garis ≈ 0.9cm → total kop ≈ mt + 2.7 cm
    */
    $headerHeight = $mt + 2.7;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Insiden — {{ $insiden->nomor_laporan ?? 'Draft' }}</title>
    <style>
        /* ── Reset ──────────────────────────────────────────────── */
        @page {
            margin: 2.5cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            color: #000;
            line-height: 1.35;
        }

        /* ── Fixed header — muncul di setiap halaman (DOMPDF) ──── */
        #page-header {
            position: fixed;
            top:   0;
            left:  0;
            right: 0;
            padding-top:   {{ $mt }}cm;
            padding-left:  {{ $ml }}cm;
            padding-right: {{ $mr }}cm;
            background:    #fff;
        }

        /* ── Konten utama ───────────────────────────────────────── */
        #content {
            /*
             * Dorong konten ke bawah kop di halaman pertama.
             * Halaman berikutnya: DOMPDF merender ulang #page-header
             * secara otomatis di atas setiap halaman baru.
             */
            padding-top:    {{ $headerHeight }}cm;
            padding-right:  {{ $mr }}cm;
            padding-bottom: {{ $mb }}cm;
            padding-left:   {{ $ml }}cm;;
        }

        table {
            border-collapse: collapse;
        }

        /* ── Judul dokumen ──────────────────────────────────────── */
        .doc-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            letter-spacing: 0.5pt;
        }

        /* ── Section Header ──────────────────────────────────────── */
        .section-header {
            font-size: 12pt;
            font-weight: bold;
            padding: 8px 0 4px 0;
            page-break-after: avoid;
        }

        /* ── Data Row — tanpa border ─────────────────────────────── */
        .row-label {
            font-size: 11pt;
            vertical-align: top;
            padding: 3px 0;
        }

        .row-colon {
            font-size: 11pt;
            vertical-align: top;
            padding: 3px 4px;
            width: 10px;
            text-align: center;
        }

        .row-value {
            font-size: 11pt;
            vertical-align: top;
            padding: 3px 0;
        }

        /* ── Checkbox (DejaVu Sans agar ☐/☑ render di DOMPDF) ──── */
        .cb {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 14pt;
            line-height: 1;
            vertical-align: middle;
        }

        /* ── Histori Tabel (tetap pakai border) ──────────────────── */
        .tbl-bordered {
            border: 1px solid #000;
        }
        .tbl-bordered td,
        .tbl-bordered th {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 10pt;
            vertical-align: top;
        }
        .tbl-bordered th {
            font-weight: bold;
            background-color: #fff;
            text-align: center;
        }

        /* ── Hindari bagian terpotong di tengah halaman ──────────── */
        .section-block {
            page-break-inside: avoid;
        }

        /* ── Tanda tangan ───────────────────────────────────────── */
        .ttd-area {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .ttd-cell {
            font-size: 11pt;
            text-align: left;
            vertical-align: top;
            padding: 0 20px 0 0;
        }
        .ttd-cell-right {
            font-size: 11pt;
            text-align: left;
            vertical-align: top;
            padding: 0 0 0 20px;
        }

        /* ── Helper ──────────────────────────────────────────────── */
        .text-center  { text-align: center; }
        .text-right   { text-align: right; }
        .text-justify { text-align: justify; }
        .mt-2  { margin-top: 2px; }
        .mt-4  { margin-top: 4px; }
        .mt-6  { margin-top: 6px; }
        .mt-10 { margin-top: 10px; }
    </style>
</head>
<body>

    {{-- ================================================================
         FIXED HEADER — kop surat ditampilkan ulang di setiap halaman
         ================================================================ --}}
    <div id="page-header">

        {{-- Kop Surat 3-Kolom --}}
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="15%" style="text-align: center; vertical-align: middle;">
                    <img src="{{ public_path('images/logo-pemkab.png') }}"
                         alt="Logo Pemkab"
                         style="width: 90px; height: auto;">
                </td>
                <td width="70%" style="text-align: center; vertical-align: middle; padding: 0 6px;">
                    <div style="font-size: 11pt; text-transform: uppercase; letter-spacing: 0.5pt; line-height: 1.25;">
                        PEMERINTAH KABUPATEN LAMPUNG UTARA
                    </div>
                    <div style="font-size: 11pt; text-transform: uppercase; letter-spacing: 0.5pt; line-height: 1.25;">
                        DINAS KESEHATAN
                    </div>
                    <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; line-height: 1.3;">
                        UPTD. RUMAH SAKIT UMUM DAERAH HM. RYACUDU
                    </div>
                    <div style="font-size: 8.5pt; line-height: 1.3;">
                        Jl. Jenderal Sudirman No.02 Telp/Fax. (0724) 22095 KOTABUMI-34511
                    </div>
                    <div style="font-size: 8.5pt; line-height: 1.3;">
                        Email: rumahsakit_ryacudu@yahoo.com
                    </div>
                </td>
                <td width="15%" style="text-align: center; vertical-align: middle;">
                    <img src="{{ public_path('images/icon-rmh_sakit.jpg') }}"
                         alt="Logo RSUD"
                         style="width: 90px; height: auto;">
                </td>
            </tr>
        </table>

        {{-- Garis pembatas kop (double-line) --}}
        <div style="margin-top: 4px; border-bottom: 3px solid #000;"></div>
        <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>

    </div>{{-- /#page-header --}}


    {{-- ================================================================
         KONTEN UTAMA
         ================================================================ --}}
    <div id="content">

        {{-- ── Judul Dokumen ────────────────────────────────────────── --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
            <tr>
                <td class="text-center" style="padding-bottom: 2px;">
                    <span class="doc-title">LAPORAN INSIDEN KESALAHAN PENGOBATAN</span>
                </td>
            </tr>
            <tr>
                <td class="text-center" style="font-size: 11pt;">
                    Nomor: {{ $insiden->nomor_laporan ?? '....................' }}
                </td>
            </tr>
        </table>

        {{-- ================================================================
             I. DATA PASIEN
             ================================================================ --}}
        <div class="section-block">
            <div class="section-header">I. DATA PASIEN</div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="160" class="row-label">Nama Pasien</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">{{ $insiden->detailPasien?->nama_pasien ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="row-label">No. Rekam Medis</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">{{ $insiden->detailPasien?->nomor_rekam_medis ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="row-label">Ruangan / Unit Kerja</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">{{ $insiden->nama_unit_kerja ?? $insiden->unitKerja?->nama_unit ?? '-' }}</td>
                </tr>
            </table>
        </div>

        {{-- ================================================================
             II. RINCIAN KEJADIAN
             ================================================================ --}}
        <div class="section-block">
            <div class="section-header mt-6">II. RINCIAN KEJADIAN</div>
            <table width="100%" cellpadding="0" cellspacing="0">

                {{-- 1. Tanggal & Waktu Kejadian --}}
                <tr>
                    <td width="160" class="row-label">1.&nbsp;Tanggal &amp; Waktu Kejadian</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        @if($insiden->tgl_kejadian)
                            {{ $insiden->tgl_kejadian->translatedFormat('d F Y') }},
                            Pukul {{ $insiden->tgl_kejadian->translatedFormat('H:i') }} WIB
                        @else
                            -
                        @endif
                    </td>
                </tr>

                {{-- 2. Jenis Insiden --}}
                <tr>
                    <td class="row-label" style="padding-top: 5px;">2.&nbsp;Jenis Insiden</td>
                    <td class="row-colon" style="padding-top: 5px;">:</td>
                    <td class="row-value" style="padding-top: 3px;">
                        @php $tipe = $insiden->tipe_insiden; @endphp
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="50%" style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KPC' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kondisi Potensial Cedera (KPC)
                                </td>
                                <td width="50%" style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KTC' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Tidak Cedera (KTC)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KNC' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Nyaris Cedera (KNC)
                                </td>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KTD' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Tidak Diharapkan (KTD)
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'SENTINEL' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Sentinel
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- 3. Tahap Kesalahan Pengobatan --}}
                <tr>
                    <td class="row-label" style="padding-top: 5px;">3.&nbsp;Tahap Kesalahan Pengobatan</td>
                    <td class="row-colon" style="padding-top: 5px;">:</td>
                    <td class="row-value" style="padding-top: 3px;">
                        @php $fase = strtolower($insiden->fase_kesalahan ?? ''); @endphp
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! str_contains($fase, 'peresepan') || str_contains($fase, 'prescribing') ? '&#9745;' : '&#9744;' !!}</span>
                                    &nbsp;Tahap Peresepan (<i>Prescribing Error</i>)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! str_contains($fase, 'penerjemahan') || str_contains($fase, 'transcribing') ? '&#9745;' : '&#9744;' !!}</span>
                                    &nbsp;Tahap Penerjemahan Resep (<i>Transcribing Error</i>)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! str_contains($fase, 'menyiapkan') || str_contains($fase, 'dispensing') || str_contains($fase, 'penyiapan') || str_contains($fase, 'peracikan') ? '&#9745;' : '&#9744;' !!}</span>
                                    &nbsp;Tahap Menyiapkan/Peracikan Obat (<i>Dispensing Error</i>)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! str_contains($fase, 'penyerahan') || str_contains($fase, 'administration') || str_contains($fase, 'pemberian') ? '&#9745;' : '&#9744;' !!}</span>
                                    &nbsp;Tahap Penyerahan Obat kepada Pasien (<i>Administration Error</i>)
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- 4. Status Laporan --}}
                <tr>
                    <td class="row-label" style="padding-top: 5px;">4.&nbsp;Status Laporan</td>
                    <td class="row-colon" style="padding-top: 5px;">:</td>
                    <td class="row-value" style="padding-top: 5px;">{{ $insiden->labelStatus() }}</td>
                </tr>

            </table>
        </div>

        {{-- ================================================================
             III. KLASIFIKASI INSIDEN
             ================================================================ --}}
        <div class="section-block">
            <div class="section-header mt-6">III. KLASIFIKASI INSIDEN</div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="160" class="row-label">1.&nbsp;Jenis Kesalahan</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        @if(!empty($insiden->detailPasien?->jenis_kesalahan))
                            {{ implode(', ', (array) $insiden->detailPasien->jenis_kesalahan) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="row-label">2.&nbsp;Cedera yang Terjadi</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        @if(!empty($insiden->detailPasien?->cedera))
                            {{ implode(', ', (array) $insiden->detailPasien->cedera) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="row-label">3.&nbsp;Faktor Penyebab</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        @if(!empty($insiden->detailPasien?->faktor_penyebab))
                            {{ implode(', ', (array) $insiden->detailPasien->faktor_penyebab) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="row-label">4.&nbsp;Intervensi pada Pasien</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        @if(!empty($insiden->detailPasien?->intervensi_pasien))
                            {{ implode(', ', (array) $insiden->detailPasien->intervensi_pasien) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="row-label">5.&nbsp;Nama Obat Terkait</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">{{ $insiden->detailPasien?->obat_terkait ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="row-label">6.&nbsp;Dosis Obat</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">{{ $insiden->detailPasien?->dosis_obat ?? '-' }}</td>
                </tr>
            </table>
        </div>

        {{-- ================================================================
             IV. KRONOLOGI KEJADIAN
             ================================================================ --}}
        <div class="section-header mt-6">IV. KRONOLOGI KEJADIAN</div>
        <p style="font-size: 11pt; text-align: justify; line-height: 1.5; margin-top: 3px;">
            @if($insiden->detailPasien?->kronologi)
                {!! nl2br(e($insiden->detailPasien->kronologi)) !!}
            @else
                -
            @endif
        </p>

        {{-- ================================================================
             V. HISTORI TINDAK LANJUT
             ================================================================ --}}
        <div class="section-header mt-6">V. HISTORI TINDAK LANJUT</div>
        <table width="100%" cellpadding="0" cellspacing="0" class="tbl-bordered" style="margin-top: 5px;">
            <thead>
                <tr>
                    <th style="width: 28px;">No.</th>
                    <th style="width: 180px;">Tanggal</th>
                    <th style="width: 150px;">Status</th>
                    <th style="width: 180px;">Petugas</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                {{-- Baris pertama: unggah laporan oleh Nakes --}}
                <tr>
                    <td class="text-center">1.</td>
                    <td class="text-center">{{ $insiden->created_at?->translatedFormat('d M Y, H:i') }}</td>
                    <td class="text-center">Unggah Laporan</td>
                    <td>{{ $insiden->is_anonim ? 'Anonim' : ($insiden->pelapor?->labelPelapor() ?? '-') }}</td>
                    <td>Laporan insiden diunggah ke sistem.</td>
                </tr>
                {{-- Baris tindak lanjut, diurutkan kronologis --}}
                @foreach($insiden->tindakLanjut->sortBy('created_at')->values() as $idx => $tl)
                <tr>
                    <td class="text-center">{{ $idx + 2 }}.</td>
                    <td class="text-center">{{ $tl->created_at?->translatedFormat('d M Y, H:i') }}</td>
                    <td class="text-center">{{ $tl->labelStatus() }}</td>
                    <td>{{ $tl->pengguna?->labelPeranDanUnit() ?? '-' }}</td>
                    <td>{{ $tl->catatan ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ================================================================
             TANDA TANGAN — 2 Kolom: Pelapor (kiri) | Kepala Ruangan (kanan)
             ================================================================ --}}
        @php
            $tglLapor    = $insiden->created_at?->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y');
            $namaPelapor = $insiden->is_anonim
                ? 'Anonim'
                : ($insiden->nama_pelapor
                    ?: ($insiden->pelapor?->nama_lengkap ?? null));
            $nipPelapor  = (!$insiden->is_anonim)
                ? ($insiden->pelapor?->nomor_induk ?? null)
                : null;
        @endphp
        <table width="100%" cellpadding="0" cellspacing="0" class="ttd-area">
            <tr>

                {{-- Kolom Kiri: Pelapor --}}
                <td width="50%" class="ttd-cell">
                    <div>Kotabumi, {{ $tglLapor }}</div>
                    <div style="margin-top: 4px;">Pelapor,</div>
                    <div style="margin-top: 100px;">
                        {{ $namaPelapor ?? '.....................................................' }}
                    </div>
                    @if(!$insiden->is_anonim)
                    <div style="font-size: 10pt; margin-top: 3px;">
                        NIP. {{ $nipPelapor ?? '.............................................' }}
                    </div>
                    @endif
                </td>

                {{-- Kolom Kanan: Kepala Ruangan --}}
                <td width="50%" class="ttd-cell-right">
                    <div style="margin-top: 4px;">Mengetahui,<br>Kepala Ruangan,</div>
                    <div style="margin-top: 100px;">
                        .....................................................
                    </div>
                    <div style="font-size: 10pt; margin-top: 3px;">NIP. .............................................</div>
                </td>

            </tr>
        </table>

    </div>{{-- /#content --}}
</body>
</html>
