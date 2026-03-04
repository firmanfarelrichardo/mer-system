<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Insiden;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SimpanLaporanRequest — validasi formulir buat/edit laporan insiden.
 *
 * Mendukung DUA mode pengiriman via parameter `action`:
 *   - `simpan_draf`   → Validasi longgar (hanya unit_kerja & tanggal_kejadian required).
 *   - `kirim_laporan`  → Validasi ketat (semua field pasien, kronologi, kategori wajib).
 *
 * Memvalidasi seluruh data dari 4 tahap wizard:
 *   1. Data Demografis   — data pasien, unit, tanggal, jenis insiden
 *   2. Detail Insiden     — klasifikasi kesalahan, cedera, faktor, intervensi, fase, obat
 *   3. Kronologi Kejadian — narasi kronologi, disclaimer non-hukum
 *   4. Konfirmasi         — checkbox pengiriman
 */
class SimpanLaporanRequest extends FormRequest
{
    /**
     * Semua pengguna terautentikasi boleh mengirim laporan.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Apakah request ini adalah aksi simpan draf?
     */
    public function isDraf(): bool
    {
        return $this->input('action') === 'simpan_draf';
    }

    /**
     * Aturan validasi — kondisional berdasarkan action.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $faseValid  = ['prescribing', 'transcribing', 'dispensing', 'administration'];
        $jenisValid = ['KPC', 'KNC', 'KTC', 'KTD', 'SENTINEL'];

        // Validasi insiden_id: gunakan closure agar tidak bergantung
        // pada string 'exists:pelaporan.insiden,id' yang membuat Laravel
        // salah menginterpretasikan 'pelaporan' sebagai nama DB connection.
        $validasiInsidenId = [
            'nullable',
            'integer',
            function (string $attr, mixed $nilai, \Closure $gagal): void {
                if ($nilai !== null && ! Insiden::where('id', $nilai)->exists()) {
                    $gagal('Draf laporan tidak ditemukan.');
                }
            },
        ];

        // ── Simpan Draf: validasi sangat longgar ──
        if ($this->isDraf()) {
            return [
                'action'             => ['required', 'in:simpan_draf,kirim_laporan'],
                'insiden_id'         => $validasiInsidenId,
                'unit_kerja'         => ['required', 'string', 'max:255'],
                'tanggal_kejadian'   => ['required', 'date', 'before_or_equal:today'],

                // Semua field lain opsional saat simpan draf.
                'nama_pasien'        => ['nullable', 'string', 'max:255'],
                'nomor_rekam_medis'  => ['nullable', 'string', 'max:100'],
                'waktu_kejadian'     => ['nullable', 'date_format:H:i'],
                'jenis_insiden'      => ['nullable', 'string', 'in:' . implode(',', $jenisValid)],
                'nama_pelapor'       => ['nullable', 'string', 'max:255'],
                'kontak_pelapor'     => ['nullable', 'string', 'max:255'],
                'fase_kesalahan'     => ['nullable', 'string', 'in:' . implode(',', $faseValid)],
                'jenis_kesalahan'    => ['nullable', 'array'],
                'jenis_kesalahan.*'  => ['string', 'max:100'],
                'jenis_kesalahan_lainnya'   => ['nullable', 'string', 'max:255'],
                'cedera'             => ['nullable', 'array'],
                'cedera.*'           => ['string', 'max:100'],
                'cedera_lainnya'     => ['nullable', 'string', 'max:255'],
                'faktor_penyebab'    => ['nullable', 'array'],
                'faktor_penyebab.*'  => ['string', 'max:100'],
                'faktor_penyebab_lainnya'   => ['nullable', 'string', 'max:255'],
                'intervensi_pasien'  => ['nullable', 'array'],
                'intervensi_pasien.*' => ['string', 'max:100'],
                'intervensi_pasien_lainnya' => ['nullable', 'string', 'max:255'],
                'nama_obat'          => ['nullable', 'string', 'max:255'],
                'dosis_obat'         => ['nullable', 'string', 'max:255'],
                'kronologi_kejadian' => ['nullable', 'string', 'max:2000'],
                'pernyataan_kronologi' => ['nullable'],
                'konfirmasi_kirim'   => ['nullable'],
            ];
        }

        // ── Kirim Laporan: validasi ketat (strict) ──
        return [
            'action'             => ['required', 'in:simpan_draf,kirim_laporan'],
            'insiden_id'         => $validasiInsidenId,

            // ── Tahap 1: Data Demografis ──
            'nama_pasien'        => ['required', 'string', 'max:255'],
            'nomor_rekam_medis'  => ['required', 'string', 'max:100'],
            'unit_kerja'         => ['required', 'string', 'max:255'],
            'tanggal_kejadian'   => ['required', 'date', 'before_or_equal:today'],
            'waktu_kejadian'     => ['required', 'date_format:H:i'],
            'jenis_insiden'      => ['required', 'string', 'in:' . implode(',', $jenisValid)],
            'nama_pelapor'       => ['nullable', 'string', 'max:255'],
            'kontak_pelapor'     => ['nullable', 'string', 'max:255'],

            // ── Tahap 2: Detail Insiden ──
            'fase_kesalahan'           => ['required', 'string', 'in:' . implode(',', $faseValid)],
            'jenis_kesalahan'          => ['required', 'array', 'min:1'],
            'jenis_kesalahan.*'        => ['string', 'max:100'],
            'jenis_kesalahan_lainnya'  => ['nullable', 'string', 'max:255'],
            'cedera'                   => ['required', 'array', 'min:1'],
            'cedera.*'                 => ['string', 'max:100'],
            'cedera_lainnya'           => ['nullable', 'string', 'max:255'],
            'faktor_penyebab'          => ['required', 'array', 'min:1'],
            'faktor_penyebab.*'        => ['string', 'max:100'],
            'faktor_penyebab_lainnya'  => ['nullable', 'string', 'max:255'],
            'intervensi_pasien'        => ['required', 'array', 'min:1'],
            'intervensi_pasien.*'      => ['string', 'max:100'],
            'intervensi_pasien_lainnya' => ['nullable', 'string', 'max:255'],
            'nama_obat'               => ['required', 'string', 'max:255'],
            'dosis_obat'              => ['nullable', 'string', 'max:255'],

            // ── Tahap 3: Kronologi ──
            'kronologi_kejadian'    => ['required', 'string', 'max:2000'],
            'pernyataan_kronologi'  => ['required', 'accepted'],

            // ── Tahap 4: Konfirmasi ──
            'konfirmasi_kirim' => ['required', 'accepted'],
        ];
    }

    /**
     * Pesan validasi dalam bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_kerja.required'          => 'Unit kerja wajib dipilih.',
            'tanggal_kejadian.required'    => 'Tanggal kejadian wajib diisi.',
            'tanggal_kejadian.before_or_equal' => 'Tanggal kejadian tidak boleh di masa depan.',
            'nama_pasien.required'         => 'Nama pasien wajib diisi.',
            'nomor_rekam_medis.required'   => 'Nomor rekam medis wajib diisi.',
            'waktu_kejadian.required'      => 'Waktu kejadian wajib diisi.',
            'jenis_insiden.required'       => 'Jenis insiden wajib dipilih.',
            'jenis_insiden.in'             => 'Jenis insiden tidak valid.',
            'fase_kesalahan.required'      => 'Fase kesalahan obat wajib dipilih.',
            'fase_kesalahan.in'            => 'Fase kesalahan tidak valid.',
            'jenis_kesalahan.required'     => 'Pilih minimal satu jenis kesalahan.',
            'cedera.required'              => 'Pilih minimal satu cedera.',
            'faktor_penyebab.required'     => 'Pilih minimal satu faktor penyebab.',
            'intervensi_pasien.required'   => 'Pilih minimal satu intervensi pasien.',
            'nama_obat.required'           => 'Nama obat yang terlibat wajib diisi.',
            'kronologi_kejadian.required'  => 'Kronologi kejadian wajib diisi.',
            'kronologi_kejadian.max'       => 'Kronologi kejadian maksimal 2000 karakter.',
            'pernyataan_kronologi.required' => 'Anda harus menyetujui pernyataan kebenaran.',
            'pernyataan_kronologi.accepted' => 'Anda harus menyetujui pernyataan kebenaran.',
            'konfirmasi_kirim.required'    => 'Anda harus mengonfirmasi pengiriman laporan.',
            'konfirmasi_kirim.accepted'    => 'Anda harus mengonfirmasi pengiriman laporan.',
        ];
    }
}
