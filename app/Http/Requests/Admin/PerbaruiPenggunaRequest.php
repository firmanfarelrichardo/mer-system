<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validasi untuk memperbarui data pengguna.
 */
class PerbaruiPenggunaRequest extends FormRequest
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
        $tenantId   = auth()->user()?->tenant_id ?? 1;
        $penggunaId = $this->route('pengguna'); // ID dari URL parameter

        return [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_induk'  => [
                'required', 'string', 'max:100',
                Rule::unique('akun.pengguna', 'nomor_induk')
                    ->where('tenant_id', $tenantId)
                    ->ignore($penggunaId),
            ],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('akun.pengguna', 'email')
                    ->where('tenant_id', $tenantId)
                    ->ignore($penggunaId),
            ],
            'nomor_hp'    => ['required', 'string', 'max:20'],
            'alamat'      => ['nullable', 'string', 'max:255'],
            'unit_id'     => ['nullable', 'integer', 'exists:master.unit_kerja,id'],
            'kata_sandi'  => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_aktif'    => ['sometimes', 'boolean'],
            'peran_ids'   => ['required', 'array', 'min:1'],
            'peran_ids.*' => ['integer', 'exists:akun.peran,id'],
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
            'kata_sandi.min'        => 'Kata sandi minimal 8 karakter.',
            'kata_sandi.confirmed'  => 'Konfirmasi kata sandi tidak cocok.',
            'peran_ids.required'    => 'Minimal satu peran harus dipilih.',
        ];
    }
}
