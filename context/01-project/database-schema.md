# Skema Basis Data

## Sumber kebenaran

- Migration: `database/migrations/` adalah sumber perubahan skema yang harus dipakai aplikasi Laravel.
- DDL awal: `database/sql/mer_ddl.sql` mendeskripsikan fondasi PostgreSQL multi-schema.
- Model Eloquent: `app/Models/` memetakan nama tabel ber-skema, relasi, cast, dan *soft delete*.

Jangan mengubah data/schema produksi melalui DDL ad-hoc. Buat migration, uji di staging, lalu jalankan `php artisan migrate --force` hanya dalam prosedur deploy yang disetujui.

## Namespace PostgreSQL

| Schema | Tanggung jawab | Tabel utama |
| --- | --- | --- |
| `tenant` | Organisasi/isolasi tenant | `organisasi` |
| `akun` | Identitas, peran, sesi, notifikasi | `pengguna`, `peran`, `pengguna_peran`, `reset_kata_sandi`, `sesi`, `notifikasi` |
| `master` | Referensi operasional | `unit_kerja`, `kategori_kesalahan`, `matriks_dampak`, `matriks_probabilitas`, `jenis_kesalahan`, `tipe_cedera`, `faktor_penyebab`, `tindakan_intervensi` |
| `pelaporan` | Data insiden dan penanganannya | `insiden`, `detail_pasien`, `insiden_kategori`, `penilaian_risiko`, `investigasi_rca`, `tindak_lanjut` |
| `audit` | Jejak aktivitas | `log_aktivitas` |

## Entitas dan relasi

```text
tenant.organisasi 1──* akun.pengguna *──* akun.peran
        │                   │
        ├──* master.unit_kerja ──* akun.pengguna (unit_id)
        └──* pelaporan.insiden ──1 pelaporan.detail_pasien
                              ├──* pelaporan.tindak_lanjut
                              ├──* pelaporan.penilaian_risiko
                              ├──* pelaporan.investigasi_rca
                              └──* master.kategori_kesalahan (pivot insiden_kategori)
```

## Tabel inti

| Tabel | Kunci/kolom penting | Catatan |
| --- | --- | --- |
| `tenant.organisasi` | `id`, `uuid`, `kode_organisasi`, `nama_organisasi` | Induk isolasi tenant. |
| `akun.pengguna` | `tenant_id`, `unit_id`, `nomor_induk`, `username`, `nama_lengkap`, `email`, `nomor_hp`, `kata_sandi`, `jabatan`, `is_aktif`, `wajib_ganti_sandi` | Kredensial dan profil; email/nomor HP dapat nullable berdasarkan migration terbaru. |
| `akun.peran` | `tenant_id`, `nama_peran` | Peran baku: Nakes, Kepala Ruangan, Komite, Admin, Direktur, Peneliti. |
| `akun.pengguna_peran` | `pengguna_id`, `peran_id` | Pivot many-to-many; primary key gabungan. |
| `master.unit_kerja` | `tenant_id`, `kode_unit`, `nama_unit`, `keterangan` | Satu kode unit unik per tenant; *soft delete*. |
| `pelaporan.insiden` | `tenant_id`, `nomor_laporan`, `pelapor_id`, `unit_id`, `nama_unit_kerja`, `tipe_insiden`, `fase_kesalahan`, `status_saat_ini`, `is_anonim`, `is_eskalasi_direktur`, `solusi_direktur` | Entitas utama, *soft delete*, nomor unik per tenant. |
| `pelaporan.detail_pasien` | `insiden_id` (unik), data pasien/obat/dosis/kronologi | Relasi satu-ke-satu dengan insiden; data sensitif. |
| `pelaporan.tindak_lanjut` | `insiden_id`, `pengguna_id`, `status`, `catatan` | Histori tindakan/status per pelaku. |
| `akun.notifikasi` | relasi morph `notifiable`, payload/status baca | Laravel notification diarahkan ke tabel ini oleh model `Pengguna`. |
| `audit.log_aktivitas` | `tenant_id`, `pengguna_id`, `aksi`, `nama_tabel`, `id_data`, data lama/baru, IP/user agent | Audit mutasi; retensi diatur `config/audit.php`. |

## Konvensi data

- Mayoritas entitas memakai `BIGSERIAL id` dan `UUID` publik/internal sebagai identitas tambahan.
- Semua data bisnis yang harus terisolasi membawa `tenant_id`; query baru wajib memfilter tenant.
- Tabel `Insiden`, `UnitKerja`, dan `Pengguna` menggunakan `SoftDeletes` pada model bila kolom tersedia.
- Timestamp memakai zona aplikasi `Asia/Jakarta`; standar Laravel biasanya menyimpan tanpa offset. Hindari asumsi timezone di query lintas waktu.
- Draf tetap memiliki `nomor_laporan`; pembeda utamanya adalah `status_saat_ini = 'DRAF'`.

## Status dan referensi nilai

| Domain | Nilai yang digunakan kode |
| --- | --- |
| Tipe insiden | `KPC`, `KNC`, `KTC`, `KTD`, `SENTINEL` |
| Status insiden | `DRAF`, `kasus_baru`, `investigasi`, `tindak_lanjut`, `selesai` |
| Peran | `Nakes`, `Kepala Ruangan`, `Komite`, `Admin`, `Direktur`, `Peneliti` |

## Perlindungan data

- Jangan menyertakan nama pasien, nomor rekam medis, kronologi mentah, sandi, token, atau dump database pada log, test fixture publik, commit, dan dokumen context.
- Gunakan data sintetis untuk test/seed demo.
- IP dan user agent audit bersifat data pribadi; aplikasi memaskannya untuk selain peran Admin.
