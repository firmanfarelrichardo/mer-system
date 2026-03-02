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
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Insiden — {{ $insiden->nomor_laporan ?? 'Draft' }}</title>
    <style>
        /* ── Margin: DOMPDF selalu menghormati padding elemen, bukan @page ── */
        @page {
            margin: 0;
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

        /* Wrapper dengan padding eksplisit — ini yang benar-benar berlaku di DOMPDF */
        #content {
            padding-top:    {{ $mt }}cm;
            padding-right:  {{ $mr }}cm;
            padding-bottom: {{ $mb }}cm;
            padding-left:   {{ $ml }}cm;
        }

        table {
            border-collapse: collapse;
        }

        /* ── Section Header ──────────────────────────────────────── */
        .section-header {
            font-size: 12pt;
            font-weight: bold;
            padding: 8px 0 3px 0;
        }

        /* ── Data Row — tanpa border ─────────────────────────────── */
        .row-label {
            font-size: 11pt;
            vertical-align: top;
            padding: 2px 0;
        }

        .row-colon {
            font-size: 11pt;
            vertical-align: top;
            padding: 2px 4px;
            width: 10px;
            text-align: center;
        }

        .row-value {
            font-size: 11pt;
            vertical-align: top;
            padding: 2px 0;
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
            padding: 4px 6px;
            font-size: 10pt;
            vertical-align: top;
        }

        .tbl-bordered th {
            font-weight: bold;
            background-color: #fff;
            text-align: center;
        }

        /* ── Helper ──────────────────────────────────────────────── */
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-justify { text-align: justify; }
    </style>
</head>
<body>
<div id="content">

    {{-- ================================================================
         KOP SURAT RESMI (3-Kolom Table — auto-centered)
         ================================================================ --}}
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="15%" style="text-align: center; vertical-align: middle;">
                <img src="{{ public_path('images/logo-pemkab.png') }}"
                     alt="Logo Pemkab"
                     style="width: 100px; height: auto;">
            </td>
            <td width="70%" style="text-align: center; vertical-align: middle; padding: 0 6px;">
                <div style="font-size: 12pt; text-transform: uppercase; letter-spacing: 0.5pt; line-height: 1.2;">
                    PEMERINTAH KABUPATEN LAMPUNG UTARA
                </div>
                <div style="font-size: 12pt; text-transform: uppercase; letter-spacing: 0.5pt; line-height: 1.2;">
                    DINAS KESEHATAN
                </div>
                <div style="font-size: 13pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; line-height: 1.3;">
                    UPTD. RUMAH SAKIT UMUM DAERAH HM. RYACUDU
                </div>
                <div style="font-size: 9pt; line-height: 1.3;">
                    Jl. Jenderal Sudirman No.02 Telp/Fax. (0724) 22095 KOTABUMI-34511
                </div>
                <div style="font-size: 9pt; line-height: 1.3;">
                    Email: rumahsakit_ryacudu@yahoo.com
                </div>
            </td>
            <td width="15%" style="text-align: center; vertical-align: middle;">
                <img src="{{ public_path('images/icon-rmh_sakit.jpg') }}"
                     alt="Logo RSUD"
                     style="width: 100px; height: auto;">
            </td>
        </tr>
    </table>

    {{-- Garis pembatas kop (double-line) --}}
    <div style="margin-top: 4px; border-bottom: 3px solid #000;"></div>
    <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>

    {{-- ================================================================
         JUDUL DOKUMEN (center)
         ================================================================ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px;">
        <tr>
            <td class="text-center" style="padding-bottom: 2px;">
                <span style="font-size: 13pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; letter-spacing: 0.5pt;">
                    LAPORAN INSIDEN KESALAHAN PENGOBATAN
                </span>
            </td>
        </tr>
        <tr>
            <td class="text-center" style="font-size: 11pt; padding-bottom: 10px;">
                Nomor: {{ $insiden->nomor_laporan ?? '....................' }}
            </td>
        </tr>
    </table>

    {{-- ================================================================
         I. DATA PASIEN
         ================================================================ --}}
    <div class="section-header">I. DATA PASIEN</div>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="170" class="row-label">Nama Pasien</td>
            <td class="row-colon">:</td>
            <td class="row-value">{{ $insiden->detailPasien?->nama_pasien ?? '-' }}</td>
        </tr>
        <tr>
            <td class="row-label">No. Rekam Medis</td>
            <td class="row-colon">:</td>
            <td class="row-value">{{ $insiden->detailPasien?->nomor_rekam_medis ?? '-' }}</td>
        </tr>
        <tr>
            <td class="row-label">Ruangan</td>
            <td class="row-colon">:</td>
            <td class="row-value">{{ $insiden->nama_unit_kerja ?? $insiden->unitKerja?->nama_unit ?? '-' }}</td>
        </tr>
    </table>

    {{-- ================================================================
         II. RINCIAN KEJADIAN
         ================================================================ --}}
    <div class="section-header" style="margin-top: 6px;">II. RINCIAN KEJADIAN</div>
    <table width="100%" cellpadding="0" cellspacing="0">
        {{-- 1. Tanggal & Waktu Kejadian --}}
        <tr>
            <td width="170" class="row-label">1. Tanggal &amp; Waktu Kejadian</td>
            <td class="row-colon">:</td>
            <td class="row-value">
                @if($insiden->tgl_kejadian)
                    {{ $insiden->tgl_kejadian->translatedFormat('d F Y / H:i') }} WIB
                @else
                    -
                @endif
            </td>
        </tr>

        {{-- 2. Jenis Insiden --}}
        <tr>
            <td width="170" class="row-label">2. Jenis Insiden</td>
            <td class="row-colon">:</td>
            <td class="row-value">
                @php $tipe = $insiden->tipe_insiden; @endphp
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! $tipe === 'KPC' ? '&#9745;' : '&#9744;' !!}</span> Kondisi Potensial Cedera (KPC)
                        </td>
                        <td width="50%" style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! $tipe === 'KTC' ? '&#9745;' : '&#9744;' !!}</span> Kejadian Tidak Cedera (KTC)
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! $tipe === 'KNC' ? '&#9745;' : '&#9744;' !!}</span> Kejadian Nyaris Cedera (KNC)
                        </td>
                        <td style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! $tipe === 'KTD' ? '&#9745;' : '&#9744;' !!}</span> Kejadian Tidak Diharapkan (KTD)
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! $tipe === 'SENTINEL' ? '&#9745;' : '&#9744;' !!}</span> Kejadian Sentinel
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- 3. Tahap Kesalahan Pengobatan --}}
        <tr>
            <td width="170" class="row-label">3. Tahap Kesalahan Pengobatan</td>
            <td class="row-colon">:</td>
            <td class="row-value">
                @php
                    $fase = strtolower($insiden->fase_kesalahan ?? '');
                @endphp
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! str_contains($fase, 'peresepan') || str_contains($fase, 'prescribing') ? '&#9745;' : '&#9744;' !!}</span>
                            Tahap Peresepan (<i>Prescribing Error</i>)
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! str_contains($fase, 'penerjemahan') || str_contains($fase, 'transcribing') ? '&#9745;' : '&#9744;' !!}</span>
                            Tahap Penerjemahan Resep (<i>Transcribing Error</i>)
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! str_contains($fase, 'menyiapkan') || str_contains($fase, 'dispensing') || str_contains($fase, 'penyiapan') || str_contains($fase, 'peracikan') ? '&#9745;' : '&#9744;' !!}</span>
                            Tahap Menyiapkan/Peracikan Obat (<i>Dispensing Error</i>)
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 11pt; padding: 1px 0;">
                            <span class="cb">{!! str_contains($fase, 'penyerahan') || str_contains($fase, 'administration') || str_contains($fase, 'pemberian') ? '&#9745;' : '&#9744;' !!}</span>
                            Tahap Penyerahan Obat kepada Pasien (<i>Administration Error</i>)
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- 4. Status Laporan --}}
        <tr>
            <td width="170" class="row-label">4. Status Laporan</td>
            <td class="row-colon">:</td>
            <td class="row-value">{{ $insiden->labelStatus() }}</td>
        </tr>
    </table>

    {{-- ================================================================
         III. KLASIFIKASI INSIDEN
         ================================================================ --}}
    <div class="section-header" style="margin-top: 6px;">III. KLASIFIKASI INSIDEN</div>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="170" class="row-label">1. Jenis Kesalahan</td>
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
            <td class="row-label">2. Cedera yang Terjadi</td>
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
            <td class="row-label">3. Faktor Penyebab</td>
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
            <td class="row-label">4. Intervensi pada Pasien</td>
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
            <td class="row-label">5. Nama Obat Terkait</td>
            <td class="row-colon">:</td>
            <td class="row-value">{{ $insiden->detailPasien?->obat_terkait ?? '-' }}</td>
        </tr>
    </table>

    {{-- ================================================================
         IV. KRONOLOGI KEJADIAN
         ================================================================ --}}
    <div class="section-header" style="margin-top: 6px;">IV. KRONOLOGI KEJADIAN</div>
    <p style="font-size: 11pt; text-align: justify; line-height: 1.45; margin-top: 2px;">
        @if($insiden->detailPasien?->kronologi)
            {!! nl2br(e($insiden->detailPasien->kronologi)) !!}
        @else
            -
        @endif
    </p>

    {{-- ================================================================
         V. TINDAKAN AWAL YANG DILAKUKAN
         ================================================================ --}}
    @if($insiden->detailPasien?->tindakan_awal)
    <div class="section-header" style="margin-top: 6px;">V. TINDAKAN AWAL YANG DILAKUKAN</div>
    <p style="font-size: 11pt; text-align: justify; line-height: 1.45; margin-top: 2px;">
        {!! nl2br(e($insiden->detailPasien->tindakan_awal)) !!}
    </p>
    @endif

    {{-- ================================================================
         VI. HISTORI TINDAK LANJUT (jika ada)
         ================================================================ --}}
    @if($insiden->tindakLanjut->isNotEmpty())
    <div class="section-header" style="margin-top: 6px;">VI. HISTORI TINDAK LANJUT</div>
    <table width="100%" cellpadding="0" cellspacing="0" class="tbl-bordered" style="margin-top: 4px;">
        <thead>
            <tr>
                <th style="width: 26px;">No.</th>
                <th style="width: 105px;">Tanggal</th>
                <th style="width: 95px;">Status Baru</th>
                <th style="width: 110px;">Petugas</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($insiden->tindakLanjut->sortBy('created_at')->values() as $idx => $tl)
            <tr>
                <td class="text-center">{{ $idx + 1 }}.</td>
                <td class="text-center">{{ $tl->created_at?->translatedFormat('d M Y, H:i') }}</td>
                <td class="text-center">{{ $tl->labelStatus() }}</td>
                <td>{{ $tl->pengguna?->nama_lengkap ?? '-' }}</td>
                <td>{{ $tl->catatan ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ================================================================
         FOOTER — TANDA TANGAN PELAPOR (rata kanan)
         ================================================================ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 28px;">
        <tr>
            <td width="55%">&nbsp;</td>
            <td width="45%" class="text-center" style="font-size: 11pt;">
                <div>Kotabumi, {{ $insiden->tgl_lapor?->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}</div>
                <div style="margin-top: 3px;">Pelapor,</div>

                <br><br><br>

                <div style="font-weight: bold; text-decoration: underline;">
                    @if($insiden->is_anonim)
                        ( Anonim )
                    @else
                        ( {{ $insiden->nama_pelapor ?? $insiden->pelapor?->nama_lengkap ?? '............................' }} )
                    @endif
                </div>
                @if(!$insiden->is_anonim && $insiden->pelapor?->nomor_induk)
                    <div style="font-size: 10pt;">NIP. {{ $insiden->pelapor->nomor_induk }}</div>
                @endif
            </td>
        </tr>
    </table>

</div>{{-- /#content --}}
</body>
</html>
