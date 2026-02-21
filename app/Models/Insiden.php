<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Insiden — memetakan tabel `pelaporan.insiden`.
 *
 * Merepresentasikan satu laporan insiden medication error
 * yang diajukan oleh pelapor (perawat/nakes).
 */
class Insiden extends Model
{
    use HasFactory;
    use SoftDeletes;

    /* ------------------------------------------------------------------
     | Konfigurasi Tabel & Kunci
     | ----------------------------------------------------------------*/

    protected $table = 'pelaporan.insiden';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /* ------------------------------------------------------------------
     | Mass Assignment
     | ----------------------------------------------------------------*/

    protected $fillable = [
        'tenant_id',
        'nomor_laporan',
        'pelapor_id',
        'unit_id',
        'nama_unit_kerja',
        'tipe_insiden',
        'fase_kesalahan',
        'status_saat_ini',
        'tgl_kejadian',
        'tgl_lapor',
        'nama_pelapor',
        'kontak_pelapor',
        'is_anonim',
        'sudah_dibaca',
    ];

    /* ------------------------------------------------------------------
     | Konversi Atribut
     | ----------------------------------------------------------------*/

    protected function casts(): array
    {
        return [
            'tgl_kejadian' => 'datetime',
            'tgl_lapor'    => 'datetime',
            'is_anonim'    => 'boolean',
            'sudah_dibaca' => 'boolean',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------
     | Relasi
     | ----------------------------------------------------------------*/

    /**
     * Pelapor — pengguna yang membuat laporan.
     */
    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pelapor_id');
    }

    /**
     * Tenant (organisasi) tempat insiden terdaftar.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisasi::class, 'tenant_id');
    }

    /**
     * Unit kerja tempat kejadian insiden.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    /**
     * Detail pasien yang terlibat dalam insiden.
     */
    public function detailPasien(): HasOne
    {
        return $this->hasOne(DetailPasien::class, 'insiden_id');
    }

    /**
     * Riwayat tindak lanjut / umpan balik dari Karu/Komite.
     */
    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjut::class, 'insiden_id')
            ->latest('created_at');
    }

    /* ------------------------------------------------------------------
     | Local Scopes — Filter data berdasarkan peran pengguna
     | -----------------------------------------------------------------
     | Digunakan oleh controller agar query SELALU dibatasi sesuai peran.
     | Karu   → hanya insiden dari unit kerja miliknya.
     | Komite → semua insiden tenant.
     | Direktur → semua insiden tenant (read-only, dibatasi di Policy).
     | Perawat → hanya insiden yang ia buat sendiri.
     | ----------------------------------------------------------------*/

    /**
     * Scope: filter insiden berdasarkan peran pengguna yang terautentikasi.
     *
     * Pendekatan: cek peran dari hierarki tertinggi ke terendah.
     * Komite & Direktur melihat semua data tenant.
     * Karu hanya melihat data dari unit kerjanya.
     * Perawat hanya melihat laporan miliknya sendiri.
     */
    public function scopeUntukPeran(Builder $query, Pengguna $pengguna): Builder
    {
        // Selalu batasi ke tenant pengguna (multi-tenant safety).
        $query->where($this->qualifyColumn('tenant_id'), $pengguna->tenant_id);

        // Komite & Direktur: lihat SEMUA laporan dalam tenant.
        if ($pengguna->memilikiPeran(Peran::KOMITE) || $pengguna->memilikiPeran(Peran::DIREKTUR)) {
            return $query;
        }

        // Kepala Ruangan: hanya insiden dari unit kerja yang sama.
        if ($pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)) {
            return $query->where(function (Builder $q) use ($pengguna) {
                $q->where($this->qualifyColumn('unit_id'), $pengguna->unit_id)
                  ->orWhere($this->qualifyColumn('nama_unit_kerja'), $pengguna->unitKerja?->nama_unit);
            });
        }

        // Perawat (default): hanya insiden yang ia buat.
        return $query->where($this->qualifyColumn('pelapor_id'), $pengguna->id);
    }

    /* ------------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------*/

    /**
     * Label tipe insiden yang ramah pengguna.
     */
    public function labelTipeInsiden(): string
    {
        return match ($this->tipe_insiden) {
            'KPC'      => 'KPC',
            'KNC'      => 'KNC',
            'KTC'      => 'KTC',
            'KTD'      => 'KTD',
            'SENTINEL' => 'Sentinel',
            default    => $this->tipe_insiden ?? '—',
        };
    }

    /**
     * Warna badge berdasarkan tipe insiden.
     */
    public function warnaInsiden(): string
    {
        return match ($this->tipe_insiden) {
            'KPC'      => 'bg-sky-100 text-sky-700',
            'KNC'      => 'bg-orange-100 text-orange-700',
            'KTC'      => 'bg-yellow-100 text-yellow-700',
            'KTD'      => 'bg-red-100 text-red-700',
            'SENTINEL' => 'bg-purple-100 text-purple-700',
            default    => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Label status dalam bahasa Indonesia.
     */
    public function labelStatus(): string
    {
        return match ($this->status_saat_ini) {
            'kasus_baru'   => 'Kasus Baru',
            'investigasi'  => 'Investigasi',
            'tindak_lanjut' => 'Tindak Lanjut',
            'selesai'      => 'Selesai',
            default        => $this->status_saat_ini ?? '—',
        };
    }

    /**
     * Warna badge berdasarkan status.
     */
    public function warnaStatus(): string
    {
        return match ($this->status_saat_ini) {
            'kasus_baru'    => 'bg-amber-100 text-amber-700',
            'investigasi'   => 'bg-blue-100 text-blue-700',
            'tindak_lanjut' => 'bg-violet-100 text-violet-700',
            'selesai'       => 'bg-emerald-100 text-emerald-700',
            default         => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Generate nomor laporan unik per tenant.
     */
    public static function generateNomorLaporan(int $tenantId): string
    {
        $tahun = now()->format('Y');
        $urutan = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $tahun)
            ->count() + 1;

        return sprintf('INC-%s-%04d', $tahun, $urutan);
    }
}
