<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Insiden — memetakan tabel `pelaporan.insiden`.
 *
 * Merepresentasikan satu laporan insiden medication error
 * yang diajukan oleh pelapor (nakes).
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
        'is_eskalasi_direktur',
        'solusi_direktur',
        'waktu_solusi_direktur',
    ];

    /* ------------------------------------------------------------------
     | Konversi Atribut
     | ----------------------------------------------------------------*/

    protected function casts(): array
    {
        return [
            'tgl_kejadian' => 'datetime',
            'tgl_lapor'    => 'datetime',
            'is_anonim'              => 'boolean',
            'sudah_dibaca'           => 'boolean',
            'is_eskalasi_direktur'   => 'boolean',
            'waktu_solusi_direktur'  => 'datetime',
            'created_at'             => 'datetime',
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

    /**
     * Kategori kesalahan yang terkait dengan insiden ini (pivot: pelaporan.insiden_kategori).
     */
    public function kategoriKesalahans(): BelongsToMany
    {
        return $this->belongsToMany(
            KategoriKesalahan::class,
            'pelaporan.insiden_kategori',
            'insiden_id',
            'kategori_id',
        );
    }

    /* ------------------------------------------------------------------
     | Local Scopes — Filter data berdasarkan peran pengguna
     | -----------------------------------------------------------------
     | Digunakan oleh controller agar query SELALU dibatasi sesuai peran.
     | Karu   → hanya insiden dari unit kerja miliknya.
     | Komite → semua insiden tenant.
     | Direktur → semua insiden tenant (read-only, dibatasi di Policy).
     | Nakes → hanya insiden yang ia buat sendiri.
     | ----------------------------------------------------------------*/

    /**
     * Scope: filter insiden berdasarkan peran pengguna yang terautentikasi.
     *
     * Pendekatan: cek peran dari hierarki tertinggi ke terendah.
     * Komite & Direktur melihat semua data tenant.
     * Karu hanya melihat data dari unit kerjanya.
     * Nakes hanya melihat laporan miliknya sendiri.
     */
    public function scopeUntukPeran(Builder $query, Pengguna $pengguna): Builder
    {
        // Selalu batasi ke tenant pengguna (multi-tenant safety).
        $query->where($this->qualifyColumn('tenant_id'), $pengguna->tenant_id);

        // Admin, Komite, dan Direktur: lihat SEMUA laporan tenant (kecuali DRAF).
        if (
            $pengguna->memilikiPeran(Peran::ADMIN)
            || $pengguna->memilikiPeran(Peran::KOMITE)
            || $pengguna->memilikiPeran(Peran::DIREKTUR)
        ) {
            return $query->where($this->qualifyColumn('status_saat_ini'), '!=', 'DRAF');
        }

        // Kepala Ruangan: hanya insiden dari unit kerja yang sama (kecuali DRAF).
        if ($pengguna->memilikiPeran(Peran::KEPALA_RUANGAN)) {
            // Jika Karu belum ditugaskan ke unit manapun, tampilkan 0 data
            // (bukan semua data). Admin harus menetapkan unit_id terlebih dahulu.
            if ($pengguna->unit_id === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $q) use ($pengguna) {
                $q->where($this->qualifyColumn('unit_id'), $pengguna->unit_id)
                  ->orWhere($this->qualifyColumn('nama_unit_kerja'), $pengguna->unitKerja?->nama_unit);
            });
        }

        // Nakes (default): hanya insiden yang ia buat.
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
            'DRAF'          => 'Draf',
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
            'DRAF'          => 'bg-slate-100 text-slate-600',
            'kasus_baru'    => 'bg-amber-100 text-amber-700',
            'investigasi'   => 'bg-blue-100 text-blue-700',
            'tindak_lanjut' => 'bg-violet-100 text-violet-700',
            'selesai'       => 'bg-emerald-100 text-emerald-700',
            default         => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Generate nomor laporan unik per tenant.
     *
     * Format: INC-{YYYYMMDD}-{HHmmss}-{XXXX}
     *   Contoh: INC-20260304-143025-K7M2
     *
     * Keunggulan:
     *   - Dapat diurutkan secara kronologis hanya dari nomornya.
     *   - Tidak bergantung pada counter terpusat → bebas race-condition.
     *   - Suffix 4-karakter dari 32-char clean charset
     *     (tanpa 0/O/1/I/L agar tidak membingungkan) → ~1 juta
     *     kombinasi per detik, collision rate praktis nol.
     *   - Panjang tetap 24 karakter, jauh di bawah VARCHAR(100).
     *   - Constraint UNIQUE [tenant_id, nomor_laporan] di DB menjadi
     *     safety-net terakhir; do-while memastikan retry bila ada tabrakan.
     */
    public static function generateNomorLaporan(int $tenantId): string
    {
        // Charset bersih: tanpa karakter yang mudah membingungkan (0/O, 1/I/L)
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $charset[random_int(0, strlen($charset) - 1)];
            }
            $nomor = 'INC-' . now()->format('Ymd-His') . '-' . $suffix;
        } while (static::where('tenant_id', $tenantId)->where('nomor_laporan', $nomor)->exists());

        return $nomor;
    }

    /**
     * Status tindak lanjut terakhir yang dibuat oleh pengguna dengan peran tertentu.
     *
     * Digunakan untuk memastikan Karu dan Komite memiliki alur status
     * yang INDEPENDEN — status salah satu tidak memblokir yang lain.
     *
     * Jika relasi `tindakLanjut` sudah di-eager-load (beserta
     * `pengguna.peran`), method ini menggunakan collection tersebut
     * tanpa menembak query tambahan ke DB.
     *
     * @param  string  $namaPeran  Konstanta Peran::KEPALA_RUANGAN atau Peran::KOMITE
     */
    public function statusTerakhirOlehPeran(string $namaPeran): ?string
    {
        // Gunakan collection yang sudah eager-load jika tersedia.
        if ($this->relationLoaded('tindakLanjut')) {
            return $this->tindakLanjut
                ->filter(fn (TindakLanjut $tl) => $tl->pengguna?->memilikiPeran($namaPeran))
                ->sortByDesc('created_at')
                ->first()?->status_baru;
        }

        // Fallback: query langsung ke DB (misal dari Policy).
        return TindakLanjut::where('insiden_id', $this->id)
            ->whereHas('pengguna', fn ($q) =>
                $q->whereHas('peran', fn ($q2) => $q2->where('nama_peran', $namaPeran))
            )
            ->latest()
            ->value('status_baru');
    }

    /**
     * Cek apakah insiden berstatus draf.
     */
    public function isDraf(): bool
    {
        return $this->status_saat_ini === 'DRAF';
    }

    /**
     * Scope: hanya ambil laporan draf milik Nakes tertentu.
     */
    public function scopeDrafMilik(Builder $query, int $nakesId): Builder
    {
        return $query->where('pelapor_id', $nakesId)
            ->where('status_saat_ini', 'DRAF');
    }

    /**
     * Scope: hanya ambil laporan yang sudah di-submit (bukan draf).
     */
    public function scopeBukanDraf(Builder $query): Builder
    {
        return $query->where('status_saat_ini', '!=', 'DRAF');
    }
}
