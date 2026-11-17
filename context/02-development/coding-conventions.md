# Konvensi Kode

> Konvensi penamaan, pattern, dan praktik kode yang digunakan di MER System.

## Bahasa

- **Domain / bisnis**: Bahasa Indonesia — nama tabel, kolom, route, variabel domain, method bisnis, view, pesan validasi
- **Framework / teknis**: Bahasa Inggris — nama class Laravel bawaan, design pattern, konfigurasi, dan istilah teknis umum

Contoh:
```php
// ✅ Benar — domain Indonesia, framework English
class LaporanService                    // Service pattern (EN) + domain (ID)
public function simpanDraf(...)         // Method bisnis (ID)
$insiden->status_saat_ini               // Kolom database (ID)
Route::get('/laporan/buat', ...)        // Route path (ID)

// ❌ Salah
class ReportService                     // Jangan terjemahkan domain ke English
public function saveDraft(...)          // Jangan campur bahasa tanpa alasan
```

## Design Patterns yang Digunakan

### Controller → Service → Repository/Model

```
Controller (orkestrasi, validasi, response)
    ↓
Service (logika bisnis, transaksi)
    ↓
Repository (query terfokus) atau Model (Eloquent langsung)
```

- **Controller**: Tipis, hanya menerima request, memanggil service, dan mengembalikan response/view
- **Service**: Logika bisnis kompleks, transaksi database, koordinasi antar model
- **Repository**: Query yang digunakan berulang atau kompleks; opsional jika query sederhana
- **Model**: Eloquent ORM — relasi, scope, accessor, mutator, cast

### Data Transfer Object (DTO)

- Gunakan DTO di `app/DataTransferObjects/` untuk mentransfer data tervalidasi antar lapisan
- DTO mendukung data parsial (untuk auto-save/draf)
- Contoh: `InsidenData` untuk data laporan

### Policy

- Otorisasi aksi per model melalui `app/Policies/`
- Contoh: `InsidenPolicy` mengatur siapa yang boleh baca/tindak lanjut/eskalasi/solusi
- **Jangan** mengganti policy dengan sekadar menyembunyikan elemen UI

### Observer

- `app/Observers/` digunakan sebagai safety net untuk audit log
- Pastikan observer tidak menghasilkan spam audit pada operasi auto-save

### Event + Listener

- Perubahan status insiden memicu `InsidenStatusBerubah`
- Listener `KirimNotifikasiInsiden` menentukan penerima berdasarkan status dan peran

## Konvensi Penamaan

### File dan Class

| Elemen | Konvensi | Contoh |
|--------|----------|--------|
| Controller | PascalCase + `Controller` | `LaporanController`, `PenggunaController` |
| Service | PascalCase + `Service` | `LaporanService`, `AuditLogService` |
| Repository | PascalCase + `Repository` | `LaporanRepository` |
| Model | PascalCase, singular | `Insiden`, `Pengguna`, `UnitKerja` |
| DTO | PascalCase + `Data` | `InsidenData` |
| Policy | PascalCase + `Policy` | `InsidenPolicy` |
| Event | PascalCase, deskriptif | `InsidenStatusBerubah` |
| Listener | PascalCase, verb phrase | `KirimNotifikasiInsiden` |
| Request | PascalCase + `Request` | `SimpanLaporanRequest`, `LoginRequest` |
| Migration | Snake_case, timestamped | `2026_02_16_000010_create_pelaporan_insiden_table.php` |

### Database

| Elemen | Konvensi | Contoh |
|--------|----------|--------|
| Schema | Lowercase, singular | `tenant`, `akun`, `master`, `pelaporan`, `audit` |
| Tabel | Lowercase, snake_case | `insiden`, `detail_pasien`, `unit_kerja` |
| Kolom | Lowercase, snake_case | `tenant_id`, `status_saat_ini`, `nama_lengkap` |
| Foreign key | `nama_tabel_id` atau `nama_id` | `pelapor_id`, `insiden_id`, `unit_id` |
| Primary key | `id` (BIGSERIAL) | — |
| UUID | `uuid` sebagai kolom tambahan | — |

### Route

| Elemen | Konvensi | Contoh |
|--------|----------|--------|
| Path | Lowercase, dash-separated, Bahasa Indonesia | `/laporan/buat`, `/admin/unit-kerja` |
| Name | Dot-separated, hierarchical | `laporan.simpan`, `admin.pengguna.edit` |
| Verb | RESTful mapping | GET=tampil/index, POST=simpan, PUT/PATCH=perbarui, DELETE=hapus |

### Blade View

| Elemen | Konvensi | Contoh |
|--------|----------|--------|
| Folder | Lowercase, sesuai domain | `resources/views/laporan/`, `resources/views/admin/` |
| File | Lowercase, dash-separated | `buat.blade.php`, `detail-insiden.blade.php` |
| Component | Lowercase, dash-separated | `komponen/grafik-statistik.blade.php` |

## Praktik Kode

### PHP

- Gunakan `declare(strict_types=1)` pada semua file aplikasi
- Gunakan type hint pada parameter dan return type
- Gunakan named arguments untuk kejelasan pada method dengan banyak parameter
- Ikuti PSR-12 (dikelola oleh Laravel Pint)

### Database

- Selalu gunakan `tenant_id` pada query data bisnis
- Bungkus mutasi terkait dalam transaksi database
- Gunakan migration untuk perubahan skema, bukan DDL langsung
- Model memakai nama tabel ber-skema: `protected $table = 'pelaporan.insiden'`

### Frontend

- Server-side validation adalah sumber kebenaran
- Alpine.js dan JavaScript hanya pelengkap UX (auto-save, interaksi dinamis)
- Blade escape (`{{ }}`) default untuk konten pengguna — jangan gunakan `{!! !!}` tanpa sanitasi
- Perhatikan responsivitas desktop dan mobile

### Keamanan

- Otorisasi selalu server-side melalui Policy dan scope
- Gunakan `scopeUntukPeran()` untuk membatasi visibilitas data
- Periksa peran melalui `memilikiPeran()` (session-aware), bukan asumsi peran DB
- Jangan nonaktifkan CSRF, autentikasi, atau validasi
