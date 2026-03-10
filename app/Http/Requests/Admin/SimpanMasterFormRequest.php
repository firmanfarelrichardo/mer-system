<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\TipeCedera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validasi untuk membuat/memperbarui master data formulir
 * (Tipe Cedera, Faktor Penyebab, Tindakan Intervensi).
 *
 * Digunakan oleh ketiga controller master formulir.
 */
class SimpanMasterFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:255',
            ],
            'is_aktif' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'nama.max'      => 'Nama maksimal 255 karakter.',
            'nama.unique'   => 'Nama sudah terdaftar.',
        ];
    }
}
