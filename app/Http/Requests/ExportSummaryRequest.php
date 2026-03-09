<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ExportSummaryRequest — validasi input rentang tanggal untuk cetak laporan rekapitulasi.
 *
 * Aturan:
 *   - tanggal_mulai : wajib, format tanggal valid.
 *   - tanggal_akhir : wajib, format tanggal valid, tidak boleh sebelum tanggal_mulai.
 */
class ExportSummaryRequest extends FormRequest
{
    /**
     * Hanya Komite & Direktur yang boleh mengakses — otorisasi dilakukan di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_akhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'orientation'   => ['sometimes', 'in:portrait,landscape'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_mulai.required'           => 'Tanggal mulai wajib diisi.',
            'tanggal_mulai.date'               => 'Format tanggal mulai tidak valid.',
            'tanggal_akhir.required'           => 'Tanggal akhir wajib diisi.',
            'tanggal_akhir.date'               => 'Format tanggal akhir tidak valid.',
            'tanggal_akhir.after_or_equal'     => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
            'orientation.in'                   => 'Orientasi hanya boleh portrait atau landscape.',
        ];
    }
}
