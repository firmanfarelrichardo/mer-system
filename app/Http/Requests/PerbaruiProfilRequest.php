<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Pengguna;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pembaruan profil mandiri oleh pengguna.
 *
 * Field yang boleh diperbarui sendiri (semua peran):
 *   - nama_lengkap, email, nomor_hp, jabatan, tanggal_bergabung_unit
 *
 * Field terlindungi (hanya Admin yang bisa mengubah via panel admin):
 *   - nomor_induk, unit_id, peran, is_aktif
 */
class PerbaruiProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $penggunaId = $this->user()?->id;
        $tenantId   = $this->user()?->tenant_id;

        return [
            'nama_lengkap'           => ['required', 'string', 'max:255'],
            'email'                  => [
                'required', 'email', 'max:255',
                Rule::unique(Pengguna::class, 'email')
                    ->where('tenant_id', $tenantId)
                    ->ignore($penggunaId),
            ],
            'nomor_hp'               => ['nullable', 'string', 'max:20'],
            'jabatan'                => ['nullable', 'string', 'max:150'],
            'tanggal_bergabung_unit' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_lengkap.required'          => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'               => 'Nama lengkap maksimal 255 karakter.',
            'email.required'                 => 'Email wajib diisi.',
            'email.email'                    => 'Format email tidak valid.',
            'email.unique'                   => 'Email sudah digunakan oleh akun lain.',
            'nomor_hp.max'                   => 'Nomor HP maksimal 20 karakter.',
            'jabatan.max'                    => 'Jabatan maksimal 150 karakter.',
            'tanggal_bergabung_unit.date'    => 'Format tanggal bergabung tidak valid.',
        ];
    }
}
