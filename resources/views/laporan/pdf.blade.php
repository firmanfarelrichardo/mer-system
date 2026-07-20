<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Insiden - {{ $insiden->nomor_laporan ?? 'Draft' }}</title>
    <style>
        /* 1. ZONA AMAN KERTAS (Margin Absolut) */
        @page {
            margin-top: 4.0cm; 
            margin-right: 2cm;
            margin-bottom: 2cm;
            margin-left: 2cm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            color: #000;
            line-height: 1.35;
        }

        /* 2. KOP SURAT PERMANEN */
        .kop-surat {
            position: fixed;
            top: -3.0cm; 
            left: 0;
            right: 0;
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

        .section-header {
            font-size: 12pt;
            font-weight: bold;
            padding: 8px 0 4px 0;
            page-break-after: avoid;
        }

        .row-label, .row-colon, .row-value {
            font-size: 11pt;
            vertical-align: top;
            padding: 3px 0;
        }
        .row-colon { padding: 3px 12px 3px 0; width: 8px; text-align: left; }

        .cb {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 14pt;
            line-height: 1;
            vertical-align: middle;
        }

        .tbl-bordered { border: 1px solid #000; width: 100%; }
        .tbl-bordered td, .tbl-bordered th {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 10pt;
            vertical-align: top;
        }
        .tbl-bordered th { font-weight: bold; text-align: center; }
        .tbl-bordered tr { page-break-inside: avoid; }

        .section-block { page-break-inside: avoid; }
        .ttd-area { margin-top: 30px; page-break-inside: avoid; }
        .ttd-cell { font-size: 11pt; text-align: left; vertical-align: top; padding-right: 20px; }
        .ttd-cell-right { font-size: 11pt; text-align: left; vertical-align: top; padding-left: 50px; }
        .text-center { text-align: center; }
        .mt-6 { margin-top: 6px; }
    </style>
</head>
<body>

    {{-- ================================================================
         TEKNIK BASE64: Mengubah gambar menjadi string agar pasti tampil
         ================================================================ --}}
    @php
        $logoPemkab = file_exists(public_path('images/logo-pemkab.png')) 
            ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/logo-pemkab.png'))) 
            : '';
            
        $logoRsud = file_exists(public_path('images/logo-rsud.jpg')) 
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents(public_path('images/logo-rsud.jpg'))) 
            : '';
    @endphp

    {{-- ================================================================
         FIXED HEADER (KOP SURAT)
         ================================================================ --}}
    <div class="kop-surat">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                {{-- Kolom logo diperkecil menjadi 12% agar teks di tengah lebih luas --}}
                <td width="12%" style="text-align: center; vertical-align: middle;">
                    @if($logoPemkab)
                        <img src="{{ $logoPemkab }}" alt="Logo Pemkab" style="width: 100px; height: auto;">
                    @endif
                </td>
                <td width="76%" style="text-align: center; vertical-align: middle; padding: 0 5px;">
                    <div style="font-size: 11pt; text-transform: uppercase; line-height: 1.2;">
                        PEMERINTAH KABUPATEN LAMPUNG UTARA
                    </div>
                    <div style="font-size: 11pt; text-transform: uppercase; line-height: 1.2;">
                        DINAS KESEHATAN
                    </div>
                    {{-- Tambahan white-space: nowrap agar nama rumah sakit DIPAKSA 1 baris --}}
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
                        <img src="{{ $logoRsud }}" alt="Logo RSUD" style="width: 100px; height: auto;">
                    @endif
                </td>
            </tr>
        </table>
        {{-- Garis pembatas --}}
        <div style="margin-top: 8px; border-bottom: 3px solid #000;"></div>
        <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>
    </div>


    {{-- ================================================================
         KONTEN UTAMA
         ================================================================ --}}
    <div>
        {{-- Tabel judul diberi jarak atas tambahan (padding) sebagai bantalan ekstra --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 15px;">
            <tr>
                <td class="text-center" style="padding-bottom: 4px;">
                    <span class="doc-title">LAPORAN INSIDEN KESALAHAN PENGOBATAN</span>
                </td>
            </tr>
            <tr>
                <td class="text-center" style="font-size: 11pt;">
                    Nomor: {{ $insiden->nomor_laporan ?? '....................' }}
                </td>
            </tr>
        </table>

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

        <div class="section-block">
            <div class="section-header mt-6">II. RINCIAN KEJADIAN</div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="160" class="row-label">1.&nbsp;Tanggal &amp; Waktu Kejadian</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        @if($insiden->tgl_kejadian)
                            {{ \Carbon\Carbon::parse($insiden->tgl_kejadian)->translatedFormat('d F Y') }},
                            Pukul {{ \Carbon\Carbon::parse($insiden->tgl_kejadian)->translatedFormat('H:i') }} WIB
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="row-label" style="padding-top: 5px;">2.&nbsp;Jenis Insiden</td>
                    <td class="row-colon" style="padding-top: 5px;">:</td>
                    <td class="row-value" style="padding-top: 3px;">
                        @php $tipe = $insiden->tipe_insiden; @endphp
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KPC' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kondisi Potensial Cedera (KPC)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KNC' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Nyaris Cedera (KNC)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KTC' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Tidak Cedera (KTC)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'KTD' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Tidak Diharapkan (KTD)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! $tipe === 'SENTINEL' ? '&#9745;' : '&#9744;' !!}</span>&nbsp;Kejadian Sentinel
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
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
                                    <span class="cb">{!! str_contains($fase, 'menyiapkan') || str_contains($fase, 'dispensing') || str_contains($fase, 'peracikan') ? '&#9745;' : '&#9744;' !!}</span>
                                    &nbsp;Tahap Menyiapkan/Peracikan Obat (<i>Dispensing Error</i>)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size: 11pt; padding: 2px 0;">
                                    <span class="cb">{!! str_contains($fase, 'penyerahan') || str_contains($fase, 'administration') ? '&#9745;' : '&#9744;' !!}</span>
                                    &nbsp;Tahap Penyerahan Obat kepada Pasien (<i>Administration Error</i>)
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="row-label" style="padding-top: 5px;">4.&nbsp;Status Laporan</td>
                    <td class="row-colon" style="padding-top: 5px;">:</td>
                    <td class="row-value" style="padding-top: 5px;">{{ $insiden->labelStatus() }}</td>
                </tr>
            </table>
        </div>

        <div class="section-block">
            <div class="section-header mt-6">III. KLASIFIKASI INSIDEN</div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="160" class="row-label">1.&nbsp;Jenis Kesalahan</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        {{ !empty($insiden->detailPasien?->jenis_kesalahan) ? implode(', ', (array) $insiden->detailPasien->jenis_kesalahan) : '-' }}
                    </td>
                </tr>
                <tr>
                    <td class="row-label">2.&nbsp;Cedera yang Terjadi</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        {{ !empty($insiden->detailPasien?->cedera) ? implode(', ', (array) $insiden->detailPasien->cedera) : '-' }}
                    </td>
                </tr>
                <tr>
                    <td class="row-label">3.&nbsp;Faktor Penyebab</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        {{ !empty($insiden->detailPasien?->faktor_penyebab) ? implode(', ', (array) $insiden->detailPasien->faktor_penyebab) : '-' }}
                    </td>
                </tr>
                <tr>
                    <td class="row-label">4.&nbsp;Intervensi pada Pasien</td>
                    <td class="row-colon">:</td>
                    <td class="row-value">
                        {{ !empty($insiden->detailPasien?->intervensi_pasien) ? implode(', ', (array) $insiden->detailPasien->intervensi_pasien) : '-' }}
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

        <div class="section-header mt-6">IV. KRONOLOGI KEJADIAN</div>
        <p style="font-size: 11pt; text-align: justify; line-height: 1.5; margin-top: 3px;">
            @if($insiden->detailPasien?->kronologi)
                {!! nl2br(e($insiden->detailPasien->kronologi)) !!}
            @else
                -
            @endif
        </p>

        <div class="section-header mt-6">V. RIWAYAT STATUS</div>
        <table width="100%" cellpadding="0" cellspacing="0" class="tbl-bordered" style="margin-top: 5px;">
            <thead>
                <tr>
                    <th style="width: 25px;">No.</th>
                    <th style="width: 180px;">Tanggal</th>
                    <th style="width: 160px;">Status</th>
                    <th style="width: 150px;">Petugas</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1.</td>
                    <td class="text-center">{{ $insiden->created_at ? \Carbon\Carbon::parse($insiden->created_at)->translatedFormat('d M Y, H:i') : '-' }}</td>
                    <td class="text-center">Unggah Laporan</td>
                    <td>{{ $insiden->is_anonim ? 'Anonim' : ($insiden->pelapor?->labelPelapor() ?? '-') }}</td>
                    <td>Laporan insiden diunggah ke sistem.</td>
                </tr>
                @foreach($insiden->tindakLanjut->sortBy('created_at')->values() as $idx => $tl)
                <tr>
                    <td class="text-center">{{ $idx + 2 }}.</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($tl->created_at)->translatedFormat('d M Y, H:i') }}</td>
                    <td class="text-center">{{ $tl->labelStatus() }}</td>
                    <td>{{ $tl->pengguna?->labelPeranDanUnit() ?? '-' }}</td>
                    <td>{{ $tl->catatan ?? '-' }}</td>
                </tr>
                @endforeach
                @if($insiden->is_eskalasi_direktur && $insiden->solusi_direktur)
                <tr>
                    <td class="text-center">{{ $insiden->tindakLanjut->count() + 2 }}.</td>
                    <td class="text-center">{{ $insiden->waktu_solusi_direktur?->translatedFormat('d M Y, H:i') ?? '-' }}</td>
                    <td class="text-center">Feedback Direktur</td>
                    <td>Direktur</td>
                    <td>{{ $insiden->solusi_direktur }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        @php
            $tglLapor    = $insiden->created_at ? \Carbon\Carbon::parse($insiden->created_at)->translatedFormat('d F Y') : now()->translatedFormat('d F Y');
            $namaPelapor = $insiden->is_anonim ? 'Anonim' : ($insiden->nama_pelapor ?: ($insiden->pelapor?->nama_lengkap ?? null));
            $nipPelapor  = (!$insiden->is_anonim) ? ($insiden->pelapor?->nomor_induk ?? null) : null;
        @endphp
        <table width="100%" cellpadding="0" cellspacing="0" class="ttd-area">
            <tr>
                <td width="50%" class="ttd-cell">
                    <div>Kotabumi, {{ $tglLapor }}</div>
                    <div style="margin-top: 4px;">Pelapor,</div>
                    <div style="margin-top: 80px;">
                        <b><u>{{ $namaPelapor ?? '.....................................................' }}</u></b>
                    </div>
                    @if(!$insiden->is_anonim)
                    <div style="font-size: 10pt; margin-top: 3px;">
                        NIP. {{ $nipPelapor ?? '.............................................' }}
                    </div>
                    @endif
                </td>
                <td width="50%" class="ttd-cell-right">
                    <div style="margin-top: 4px;">Mengetahui,<br>Kepala Ruangan,</div>
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