<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\KategoriKesalahan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validasi untuk membuat kategori kesalahan baru.
 */
class SimpanKategoriRequest extends FormRequest
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
        $tenantId = auth()->user()->tenant_id ?? 1;

        return [
            'nama_kategori' => [
                'required', 'string', 'max:255',
                Rule::unique(KategoriKesalahan::class, 'nama_kategori')
                    ->where('tenant_id', $tenantId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.unique'   => 'Nama kategori sudah terdaftar.',
            'nama_kategori.max'      => 'Nama kategori maksimal 255 karakter.',
        ];
    }
}
