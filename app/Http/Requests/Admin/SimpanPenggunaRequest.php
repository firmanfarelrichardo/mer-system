<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\UnitKerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validasi untuk membuat pengguna baru.
 */
class SimpanPenggunaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi di middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? 1;

        return [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_induk'  => [
                'required', 'string', 'max:100',
                Rule::unique(Pengguna::class, 'nomor_induk')
                    ->where('tenant_id', $tenantId),
            ],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique(Pengguna::class, 'email')
                    ->where('tenant_id', $tenantId),
            ],
            'nomor_hp'    => ['required', 'string', 'max:20'],
            'alamat'      => ['nullable', 'string', 'max:255'],
            'unit_id'     => ['nullable', 'integer', Rule::exists(UnitKerja::class, 'id')],
            'kata_sandi'  => ['required', 'string', 'min:8', 'confirmed'],
            'is_aktif'    => ['sometimes', 'boolean'],
            'peran_ids'   => ['required', 'array', 'min:1'],
            'peran_ids.*' => ['integer', Rule::exists(Peran::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nomor_induk.required'  => 'Nomor induk wajib diisi.',
            'nomor_induk.unique'    => 'Nomor induk sudah terdaftar.',
            'email.required'        => 'Email wajib diisi.',
            'email.email'           => 'Format email tidak valid.',
            'email.unique'          => 'Email sudah terdaftar.',
            'nomor_hp.required'     => 'Nomor HP wajib diisi.',
            'kata_sandi.required'   => 'Kata sandi wajib diisi.',
            'kata_sandi.min'        => 'Kata sandi minimal 8 karakter.',
            'kata_sandi.confirmed'  => 'Konfirmasi kata sandi tidak cocok.',
            'peran_ids.required'    => 'Minimal satu peran harus dipilih.',
            'peran_ids.min'         => 'Minimal satu peran harus dipilih.',
        ];
    }
}
