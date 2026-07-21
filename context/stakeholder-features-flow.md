# Fitur dan Alur Pemangku Kepentingan

## Matriks kemampuan

| Fitur | Nakes | Kepala Ruangan | Komite | Direktur | Admin | Peneliti |
| --- | --- | --- | --- | --- | --- | --- |
| Login/profil/notifikasi | Ya | Ya | Ya | Ya | Ya | Ya |
| Buat laporan/draf | Ya | Ya (sebagai pelapor) | Tidak sebagai alur utama | Tidak sebagai alur utama | Tidak sebagai alur utama | Melalui simulasi |
| Lihat daftar laporan | Milik sendiri | Unit sendiri | Tenant | Tenant | Tenant | Sesuai simulasi/dasbor |
| Tindak lanjut/status | Tidak | Ya | Ya | Tidak | Tidak sebagai alur utama | Sesuai simulasi |
| Eskalasi Direktur | Tidak | Tidak | Ya | Tidak | Tidak | Sesuai simulasi |
| Solusi Direktur | Tidak | Tidak | Tidak | Ya, setelah eskalasi | Tidak | Sesuai simulasi |
| Kelola master/pengguna | Tidak | Tidak | Tidak | Tidak | Ya | Tidak, kecuali simulasi tampilan |
| Statistik/ekspor rekap | Tidak sebagai akses utama | Statistik | Statistik/ekspor | Statistik/ekspor | Dasbor admin | Dasbor peneliti |

## Alur pelaporan dan tindak lanjut

```text
Nakes/Karu sebagai pelapor
  ├─ auto-save / simpan → DRAF (pribadi, tanpa notifikasi)
  └─ kirim → kasus_baru
                ├─ notifikasi Karu unit + Komite
                └─ Karu: verifikasi/investigasi
                       ├─ notifikasi Komite (+ Direktur jika SENTINEL)
                       └─ notifikasi pelapor
Komite → tindak_lanjut / selesai → notifikasi pelapor (+ Karu saat selesai)
Komite → eskalasi Direktur → Direktur mengisi solusi eksekutif
```

## Rincian alur per peran

### Nakes

1. Masuk dan, bila diwajibkan, mengganti sandi.
2. Membuka form laporan, mengisi informasi insiden dan pasien; dapat memilih pelaporan anonim dengan mengosongkan nama pelapor.
3. Sistem menyimpan draf otomatis atau manual tanpa mengirim pihak lain.
4. Saat submit, menerima nomor `INC-...`; laporan memasuki `kasus_baru`.
5. Membuka riwayat dan detail laporan sendiri; menerima notifikasi saat investigasi, tindak lanjut, atau selesai.

### Kepala Ruangan

1. Mendapat notifikasi kasus baru pada unit yang ditugaskan.
2. Melihat laporan unit, menandai sudah dibaca, lalu menambah tindak lanjut/status selama alur Karu belum selesai.
3. Dapat tetap membuat dan melihat riwayat laporan sendiri sebagai pelapor.
4. Bila belum mempunyai unit, scope sengaja menampilkan nol laporan agar tidak terjadi akses berlebih.

### Komite

1. Mendapat notifikasi seluruh kasus baru tenant dan kasus investigasi.
2. Melihat semua laporan tenant, memasukkan tindak lanjut, dan menyelesaikan alur Komite secara independen dari Karu.
3. Mengekspor rekap dan, bila diperlukan, mengeskalasi laporan ke Direktur.

### Direktur

1. Memantau seluruh laporan tenant dan statistik dalam mode read-only untuk proses status normal.
2. Mendapat notifikasi khusus untuk Sentinel pada tahap investigasi.
3. Hanya menambahkan solusi/arahannya ketika `is_eskalasi_direktur` aktif.

### Admin dan Peneliti

- Admin memelihara identitas pengguna, status akun, penugasan unit/Karu, master data, serta log aktivitas.
- Peneliti memiliki dasbor sendiri dan dapat memilih peran aktif untuk simulasi; hak simulasi tidak boleh dianggap sebagai hak produksi pengguna biasa.

## Catatan komunikasi

- Notifikasi pada implementasi saat ini adalah notifikasi in-app berbasis Laravel notification/table custom.
- Fitur email pada pengaturan ditandai masih tertunda; jangan menjanjikan notifikasi email tanpa implementasi dan konfigurasi yang diuji.
- Untuk insiden sangat sensitif, gunakan nomor laporan dan data minimum dalam komunikasi eksternal; jangan menyalin detail pasien ke chat/tiket umum.
