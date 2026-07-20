<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Laporan Insiden</title>
    <style>
        /* ═══════════════════════════════════════════════════════════════
           1. ZONA AMAN KERTAS - margin top diperbesar untuk kop surat,
              semua sisi diberi jarak 2 cm agar tidak terlalu dekat tepi.
           ═══════════════════════════════════════════════════════════════ */
        @page {
            margin-top: 4cm;
            margin-right: 2cm;
            margin-bottom: 2cm;
            margin-left: 2cm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.3;
        }

        /* ═══════════════════════════════════════════════════════════════
           2. KOP SURAT PERMANEN - fixed position, berulang di setiap halaman
           top: -4.5cm agar masuk ke zona margin-top: 5cm tanpa menabrak konten.
           ═══════════════════════════════════════════════════════════════ */
        .kop-surat {
            position: fixed;
            top: -3.0cm;
            left: 0;
            right: 0;
            height: 4cm;
        }

        table {
            border-collapse: collapse;
        }

        .doc-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            letter-spacing: 0.5pt;
        }

        .sub-title {
            font-size: 11pt;
            text-align: center;
            margin-top: 3px;
        }

        /* ═══════════════════════════════════════════════════════════════
           3. TABEL REKAPITULASI - bordered, dengan aturan page-break
           ═══════════════════════════════════════════════════════════════ */
        .tbl-bordered {
            border: 1px solid #000;
            width: 100%;
            page-break-inside: auto;
        }

        .tbl-bordered td,
        .tbl-bordered th {
            border: 1px solid #000;
            padding: 5px 7px;
            font-size: 9.5pt;
            vertical-align: top;
        }

        .tbl-bordered th {
            font-weight: bold;
            text-align: center;
            background-color: #f0f0f0;
        }

        /* Setiap baris TIDAK boleh terpotong di tengah saat ganti halaman */
        .tbl-bordered tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        .ttd-area {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .ttd-cell {
            font-size: 11pt;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
    </style>
</head>
<body>

    {{-- ================================================================
         TEKNIK BASE64: Gambar dikonversi ke string agar pasti tampil
         di lingkungan Docker/Linux tanpa akses filesystem dari DOMPDF.
         ================================================================ --}}
    @php
        $logoPemkab = file_exists(public_path('images/logo-pemkab.png'))
            ? 'data:image/png;base64,'  . base64_encode(file_get_contents(public_path('images/logo-pemkab.png')))
            : '';

        $logoRsud = file_exists(public_path('images/logo-rsud.jpg'))
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents(public_path('images/logo-rsud.jpg')))
            : '';
    @endphp

    {{-- ================================================================
         FIXED HEADER (KOP SURAT) - berulang di setiap halaman
         ================================================================ --}}
    <div class="kop-surat">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="12%" style="text-align: center; vertical-align: middle;">
                    @if($logoPemkab)
                        <img src="{{ $logoPemkab }}" alt="Logo Pemkab" style="width: 95px; height: auto;">
                    @endif
                </td>
                <td width="76%" style="text-align: center; vertical-align: middle; padding: 0 5px;">
                    <div style="font-size: 11pt; text-transform: uppercase; line-height: 1.2;">
                        PEMERINTAH KABUPATEN LAMPUNG UTARA
                    </div>
                    <div style="font-size: 11pt; text-transform: uppercase; line-height: 1.2;">
                        DINAS KESEHATAN
                    </div>
                    <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase; line-height: 1.3; white-space: nowrap;">
                        UPTD. RUMAH SAKIT UMUM DAERAH HM. RYACUDU
                    </div>
                    <div style="font-size: 9pt; line-height: 1.3; margin-top: 2px;">
                        Jl. Jenderal Sudirman No.02 Telp/Fax. (0724) 22095 KOTABUMI-34511
                    </div>
                    <div style="font-size: 9pt; line-height: 1.3;">
                        Email: rumahsakit_ryacudu@yahoo.com
                    </div>
                </td>
                <td width="12%" style="text-align: center; vertical-align: middle;">
                    @if($logoRsud)
                        <img src="{{ $logoRsud }}" alt="Logo RSUD" style="width: 95px; height: auto;">
                    @endif
                </td>
            </tr>
        </table>
        <div style="margin-top: 8px; border-bottom: 3px solid #000;"></div>
        <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>
    </div>

    {{-- ================================================================
         KONTEN UTAMA
         ================================================================ --}}
    <div>

        {{-- Judul & Sub-judul --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
            <tr>
                <td class="text-center" style="padding-bottom: 3px;">
                    <span class="doc-title">REKAPITULASI LAPORAN INSIDEN KESALAHAN PENGOBATAN</span>
                </td>
            </tr>
            <tr>
                <td class="sub-title">
                    Periode:
                    {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }}
                    s.d
                    {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                </td>
            </tr>
        </table>

        {{-- Tabel Data Rekapitulasi --}}
        <table class="tbl-bordered">
            <thead>
                <tr>
                    <th style="width: 3%;">No</th>
                    <th style="width: 12%;">Tanggal Kejadian</th>
                    <th style="width: 12%;">No. Laporan</th>
                    <th style="width: 10%;">No. RM</th>
                    <th style="width: 15%;">Nama Pasien</th>
                    <th style="width: 16%;">Unit Kerja</th>
                    <th style="width: 10%;">Jenis Insiden</th>
                    <th style="width: 22%;">Status Laporan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($insidens as $i => $insiden)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="text-center">
                            @if($insiden->tgl_kejadian)
                                {{ \Carbon\Carbon::parse($insiden->tgl_kejadian)->translatedFormat('d M Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $insiden->nomor_laporan ?? '-' }}</td>
                        <td>{{ $insiden->detailPasien?->nomor_rekam_medis ?? '-' }}</td>
                        <td>
                            @if ($insiden->is_anonim)
                                <em style="color: #666;">Anonim</em>
                            @else
                                {{ $insiden->detailPasien?->nama_pasien ?? '-' }}
                            @endif
                        </td>
                        <td>{{ $insiden->nama_unit_kerja ?? $insiden->unitKerja?->nama_unit ?? '-' }}</td>
                        <td class="text-center">{{ $insiden->labelTipeInsiden() }}</td>
                        <td>{{ $insiden->labelStatus() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 12px; font-style: italic; color: #555;">
                            Tidak ada data insiden pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8" style="text-align: right; font-weight: bold; font-size: 10pt; padding: 5px 7px;">
                        Total: {{ $insidens->count() }} laporan
                    </td>
                </tr>
            </tfoot>
        </table>

        {{-- Area Tanda Tangan --}}
        @php
            $tglCetak = now()->translatedFormat('d F Y');
        @endphp
        <table width="100%" cellpadding="0" cellspacing="0" class="ttd-area">
            <tr>
                <td width="33%" class="ttd-cell">
                    <div>Kotabumi, {{ $tglCetak }}</div>
                    <div style="margin-top: 4px;">Komite Keselamatan Pasien,</div>
                    <div style="margin-top: 80px;">
                        <b><u>.....................................................</u></b>
                    </div>
                    <div style="font-size: 10pt; margin-top: 3px;">NIP. .............................................</div>
                </td>
                <td width="34%"></td>
                <td width="33%" class="ttd-cell">
                    <div>&nbsp;</div>
                    <div style="margin-top: 4px;">Direktur RSUD HM. Ryacudu,</div>
                    <div style="margin-top: 80px;">
                        <b><u>.....................................................</u></b>
                    </div>
                    <div style="font-size: 10pt; margin-top: 3px;">NIP. .............................................</div>
                </td>
            </tr>
        </table>

    </div>

</body>
</html>
