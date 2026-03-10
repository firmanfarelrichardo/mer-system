<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\UnitKerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validasi untuk membuat unit kerja baru.
 */
class SimpanUnitKerjaRequest extends FormRequest
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
        $tenantId = auth()->user()?->tenant_id ?? 1;

        return [
            'kode_unit' => [
                'required', 'string', 'max:50',
                Rule::unique(UnitKerja::class, 'kode_unit')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'nama_unit'  => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_unit.required' => 'Kode unit wajib diisi.',
            'kode_unit.unique'   => 'Kode unit sudah terdaftar.',
            'kode_unit.max'      => 'Kode unit maksimal 50 karakter.',
            'nama_unit.required' => 'Nama unit wajib diisi.',
            'nama_unit.max'      => 'Nama unit maksimal 255 karakter.',
            'keterangan.max'     => 'Keterangan maksimal 500 karakter.',
        ];
    }
}
