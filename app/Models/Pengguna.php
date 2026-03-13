<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model Pengguna — memetakan tabel `akun.pengguna`.
 *
 * Mewarisi Authenticatable agar guard Auth Laravel dapat menggunakannya
 * untuk autentikasi berbasis sesi secara langsung.
 */
class Pengguna extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /* ------------------------------------------------------------------
     | Konfigurasi Tabel & Kunci
     | ----------------------------------------------------------------*/

    protected $table = 'akun.pengguna';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /* ------------------------------------------------------------------
     | Mass Assignment
     | ----------------------------------------------------------------*/

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'nomor_induk',
        'username',
        'email',
        'nomor_hp',
        'alamat',
        'nama_lengkap',
        'kata_sandi',
        'is_aktif',
        'wajib_ganti_sandi',
        'terakhir_login_pada',
        'jabatan',
        'tanggal_bergabung_unit',
    ];

    protected $hidden = [
        'kata_sandi',
    ];

    /* ------------------------------------------------------------------
     | Konversi Atribut
     | ----------------------------------------------------------------*/

    protected function casts(): array
    {
        return [
            'is_aktif'               => 'boolean',
            'wajib_ganti_sandi'      => 'boolean',
            'terakhir_login_pada'    => 'datetime',
            'kata_sandi'             => 'hashed',   // Hash otomatis saat di-set (Laravel 10+)
            'tanggal_bergabung_unit' => 'date',
            'created_at'             => 'datetime',
            'updated_at'             => 'datetime',
            'deleted_at'             => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Override Kolom Kata Sandi
     | -----------------------------------------------------------------
     | Kontrak Authenticatable mencari getAuthPassword().
     | Kolom kita adalah `kata_sandi`, bukan kolom default `password`.
     | ----------------------------------------------------------------*/

    public function getAuthPassword(): string
    {
        return $this->kata_sandi;
    }

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    /**
     * Peran yang dimiliki pengguna melalui tabel pivot.
     *
     * @return BelongsToMany<Peran, $this>
     */
    public function peran(): BelongsToMany
    {
        return $this->belongsToMany(
            related:         Peran::class,
            table:           'akun.pengguna_peran',
            foreignPivotKey: 'pengguna_id',
            relatedPivotKey: 'peran_id',
        );
    }

    /**
     * Tenant (organisasi) tempat pengguna terdaftar.
     *
     * @return BelongsTo<Organisasi, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }

    /**
     * Unit kerja yang opsional untuk pengguna ini.
     *
     * @return BelongsTo<UnitKerja, $this>
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    /* ------------------------------------------------------------------
     | Pembantu
     | ----------------------------------------------------------------*/

    /**
     * Periksa apakah pengguna memiliki peran tertentu berdasarkan nama.
     *
     * Untuk pengguna yang bisa ganti peran (peneliti atau dual-role), gunakan
     * peranAktif() sebagai satu-satunya acuan agar konsisten di seluruh app.
     * peranAktif() sudah menangani: sesi aktif, mode penuh peneliti, dan default DB.
     *
     * Untuk pengguna biasa (1 peran): cek langsung ke koleksi DB.
     */
    public function memilikiPeran(string $namaPeran): bool
    {
        if ($this->bisaGantiPeran()) {
            return $this->peranAktif() === $namaPeran;
        }

        return $this->peran->contains('nama_peran', $namaPeran);
    }

    /**
     * Periksa apakah pengguna memiliki peran di database (tanpa memperhatikan sesi).
     *
     * Berguna saat perlu cek kepemilikan peran riil, misalnya untuk
     * menampilkan tombol "Ganti Peran" di navbar.
     */
    public function punyaPeranDiDb(string $namaPeran): bool
    {
        return $this->peran->contains('nama_peran', $namaPeran);
    }

    /**
     * Periksa apakah pengguna ini adalah Kepala Ruangan (Karu).
     *
     * Cek berdasarkan peran di database, bukan sesi aktif.
     * Digunakan untuk menentukan apakah tombol switch peran ditampilkan.
     */
    public function adalahKaru(): bool
    {
        return $this->punyaPeranDiDb(Peran::KEPALA_RUANGAN);
    }

    /**
     * Periksa apakah pengguna ini adalah Nakes.
     *
     * Cek berdasarkan peran di database, bukan sesi aktif.
     */
    public function adalahNakes(): bool
    {
        return $this->punyaPeranDiDb(Peran::NAKES);
    }

    /**
     * Periksa apakah pengguna dapat mengganti peran aktif.
     *
     * True jika:
     * - Akun peneliti (dapat simulasi semua peran), ATAU
     * - Pengguna memiliki >1 peran di database (dual-role, misal Nakes + Karu)
     */
    public function bisaGantiPeran(): bool
    {
        if ($this->isPeneliti()) {
            return true;
        }

        return $this->peran->count() > 1;
    }

    /**
     * Kembalikan daftar peran yang dapat dipilih pengguna untuk switch.
     *
     * - Untuk peneliti: semua peran tersedia (simulasi penuh).
     * - Untuk dual-role: hanya peran yang dimiliki di database.
     *
     * @return list<string>
     */
    public function peranYangDapatDipilih(): array
    {
        if ($this->isPeneliti()) {
            return [
                Peran::NAKES,
                Peran::KEPALA_RUANGAN,
                Peran::KOMITE,
                Peran::ADMIN,
                Peran::DIREKTUR,
                Peran::PENELITI,
            ];
        }

        return $this->peran->pluck('nama_peran')->all();
    }

    /**
     * Kembalikan daftar label tampilan peran pengguna (menggunakan accessor nama_display).
     * Contoh: ['Tenaga Kesehatan'] bukan ['Nakes'].
     *
     * @return list<string>
     */
    public function daftarPeran(): array
    {
        return $this->peran->map(fn (Peran $p) => $p->nama_display)->all();
    }

    /**
     * Label ringkas untuk histori tindak lanjut (Karu / Komite).
     * Format: "[Peran] [NamaUnit]"
     * Contoh: "Kepala Ruangan ICU", "Komite"
     *
     * Nama pemilik akun sengaja tidak ditampilkan untuk menjaga
     * netralitas posisi jabatan dalam alur tindak lanjut.
     */
    public function labelPeranDanUnit(): string
    {
        $peran = $this->daftarPeran()[0] ?? null;
        $unit  = $this->unitKerja?->nama_unit;

        return collect([$peran, $unit])->filter()->implode(' ') ?: '—';
    }

    /**
     * Label untuk entri unggah laporan (Nakes).
     * Format: "[Jabatan] - [NamaLengkap]"
     * Contoh: "Perawat - Budi Santoso"
     */
    public function labelPelapor(): string
    {
        $jabatan = $this->jabatan ?? null;
        $nama    = $this->nama_lengkap ?? '—';

        return $jabatan ? "{$jabatan} - {$nama}" : $nama;
    }

    /**
     * Apakah pengguna ini memiliki peran Peneliti di database?
     *
     * Sumber kebenaran tunggal: peran di database.
     * Untuk mencabut akses, cukup hapus akun atau cabut peran Peneliti
     * melalui UI Admin — tanpa perlu mengubah .env atau kode.
     */
    public function isPeneliti(): bool
    {
        return $this->peran->contains(function ($peran) {
         return strtolower($peran->nama_peran) === strtolower(Peran::PENELITI);
            });
    }

    /**
     * Kembalikan nama peran yang sedang aktif untuk pengguna ini.
     *
     * Prioritas:
     * 1. Jika ada session 'active_role' DAN pengguna bisa ganti peran → gunakan sesi.
     * 2. Untuk peneliti tanpa sesi → kembalikan Peran::PENELITI.
     * 3. Untuk pengguna biasa → kembalikan peran pertama dari database.
     *
     * Digunakan oleh navbar (label peran), sidebar composer (routing &
     * filter menu), dan dasbor hub untuk menentukan redirect yang tepat.
     */
    public function peranAktif(): string
    {
        // Jika ada active_role di sesi DAN pengguna berhak switch
        if (session()->has('active_role') && $this->bisaGantiPeran()) {
            return session('active_role');
        }

        // Peneliti tanpa sesi aktif → mode penuh (Peneliti view)
        if ($this->isPeneliti()) {
            return Peran::PENELITI;
        }

        // Pengguna dual-role tanpa sesi → gunakan peran utama (bukan Karu).
        // Karu adalah peran tambahan yang harus diaktifkan secara eksplisit
        // via dropdown "Ganti Peran" di navbar.
        if ($this->bisaGantiPeran()) {
            return $this->peran
                ->first(fn (Peran $p) => $p->nama_peran !== Peran::KEPALA_RUANGAN)
                ->nama_peran
                ?? $this->peran->first()->nama_peran
                ?? '—';
        }

        // Pengguna biasa (1 peran) → peran dari database
        return $this->peran->first()->nama_peran ?? '—';
    }

    /**
     * Set peran aktif di sesi untuk pengguna ini.
     *
     * Validasi: peran harus ada di daftar peran yang dapat dipilih.
     * Jika peran tidak valid, metode tidak melakukan apa-apa.
     */
    public function setPeranAktif(string $namaPeran): void
    {
        if (! in_array($namaPeran, $this->peranYangDapatDipilih(), true)) {
            return;
        }

        // Khusus peneliti: memilih Peran::PENELITI = kembali ke mode penuh
        if ($this->isPeneliti() && $namaPeran === Peran::PENELITI) {
            session()->forget('active_role');

            return;
        }

        session()->put('active_role', $namaPeran);
    }

    /**
     * Hapus peran aktif dari sesi (kembali ke peran default).
     */
    public function resetPeranAktif(): void
    {
        session()->forget('active_role');
    }

    /* ------------------------------------------------------------------
     | Override Notifiable: Custom Notification Table
     | -----------------------------------------------------------------
     | Mengarahkan ke tabel `akun.notifikasi` alih-alih `notifications`.
     | ----------------------------------------------------------------*/

    /**
     * Dapatkan relasi notifications dari tabel akun.notifikasi.
     *
     * @return MorphMany<Notifikasi, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(
            Notifikasi::class,
            'notifiable',
        )->orderBy('created_at', 'desc');
    }
}
