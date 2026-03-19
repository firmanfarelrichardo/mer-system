# 01 — Arsitektur Sistem & Hardening OS

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Klasifikasi**: HIGH-RISK (Data Medis Sensitif / PHI)  
> **Server**: VPS Ubuntu 24.04 LTS — 2 Core CPU, 8GB RAM, 100GB SSD (Hostinger)  
> **Tanggal**: Maret 2026

---

## Daftar Isi

1. [Gambaran Arsitektur Sistem](#1-gambaran-arsitektur-sistem)
2. [Diagram Arsitektur Defense in Depth](#2-diagram-arsitektur-defense-in-depth)
3. [Provisioning Awal & Pembaruan Sistem](#3-provisioning-awal--pembaruan-sistem)
4. [Alokasi Swap File 4GB (Pencegahan OOM)](#4-alokasi-swap-file-4gb-pencegahan-oom)
5. [Pembuatan User Operasional (Non-Root)](#5-pembuatan-user-operasional-non-root)
6. [Autentikasi Kriptografis SSH Key Ed25519](#6-autentikasi-kriptografis-ssh-key-ed25519)
7. [Hardening Daemon SSH](#7-hardening-daemon-ssh)
8. [Konfigurasi Firewall UFW Eksklusif Cloudflare](#8-konfigurasi-firewall-ufw-eksklusif-cloudflare)
9. [Skrip Otomatis Pembaruan IP Cloudflare (Cronjob)](#9-skrip-otomatis-pembaruan-ip-cloudflare-cronjob)
10. [Konfigurasi Fail2Ban Anti Brute-Force](#10-konfigurasi-fail2ban-anti-brute-force)
11. [Hardening Kernel (Sysctl)](#11-hardening-kernel-sysctl)
12. [Automatic Security Updates (Unattended Upgrades)](#12-automatic-security-updates-unattended-upgrades)
13. [Verifikasi Akhir](#13-verifikasi-akhir)

---

## 1. Gambaran Arsitektur Sistem

### Versi Sederhana (Analogi Pemula)

Bayangkan MER System sebagai **brankas rekam medis digital** di dalam gedung rumah sakit:

| # | Komponen | Analogi |
|---|----------|---------|
| 1 | **Cloudflare (Edge)** | Pos satpam di gerbang utama. Semua tamu (request) diperiksa identitasnya di sini. Hanya tamu dengan izin yang diteruskan ke dalam gedung. |
| 2 | **UFW (Firewall OS)** | Pintu baja otomatis di lobi. Hanya mau terbuka jika tamu diantar oleh satpam Cloudflare. Tamu yang datang langsung dari jalan (IP publik acak) ditolak. |
| 3 | **Nginx (Reverse Proxy)** | Resepsionis di meja depan. Menerima permintaan dari tamu yang sudah lolos pintu baja, lalu mengarahkan ke ruangan yang tepat (PHP-FPM). |
| 4 | **PHP-FPM (Laravel)** | Dokter spesialis yang memproses data medis. Hanya bisa diakses lewat resepsionis, tidak pernah langsung dari luar. |
| 5 | **PostgreSQL (Database)** | Ruang brankas bawah tanah. Tidak ada jendela atau pintu ke luar. Hanya dokter (PHP-FPM) yang punya akses lewat lorong internal. |
| 6 | **Redis (Cache/Queue)** | Lemari arsip cepat di samping meja dokter. Menyimpan salinan data yang sering dipakai agar tidak perlu bolak-balik ke brankas. |

### Versi Formal (Standar Industri)

Arsitektur ini menerapkan **Defense in Depth** — strategi keamanan berlapis dimana setiap lapisan berfungsi independen sehingga kompromi pada satu lapisan tidak otomatis membahayakan lapisan lain.

**Lapisan keamanan (dari luar ke dalam):**

1. **Edge Layer (Cloudflare)** — WAF, DDoS mitigation, TLS termination, bot filtering
2. **Network Layer (UFW + iptables)** — Allowlist IP Cloudflare pada port 80/443, deny-all default policy
3. **Transport Layer (SSH Hardening)** — Ed25519 key-only auth, non-standard port, Fail2Ban IPS
4. **Container Layer (Docker Network)** — Bridge network isolation, loopback port binding untuk database
5. **Application Layer (Laravel)** — CSRF protection, input validation, encrypted sessions
6. **Data Layer (PostgreSQL)** — Network-isolated, credential-based auth, encrypted backups

---

## 2. Diagram Arsitektur Defense in Depth

```
INTERNET
    |
    v
+------------------------------------------+
| CLOUDFLARE (Edge Security Layer)         |
| - WAF Rules (OWASP + Custom Medical)    |
| - DDoS Protection (Layer 3/4/7)         |
| - Bot Management                         |
| - Full (Strict) SSL/TLS                 |
| - Rate Limiting                          |
+------------------------------------------+
    | Hanya IP Cloudflare yang lolos
    v
+------------------------------------------+
| UFW FIREWALL (Network Layer)             |
| - Default: DENY ALL incoming             |
| - Allow: Port 443/80 FROM Cloudflare IPs |
| - Allow: Port 49152 (SSH custom)         |
| - Cronjob: Auto-update Cloudflare IPs    |
+------------------------------------------+
    |
    v
+------------------------------------------+
| FAIL2BAN (Intrusion Prevention)          |
| - Monitor: /var/log/auth.log             |
| - Action: Ban IP via iptables            |
| - Threshold: 5 failures = 4 hour ban    |
+------------------------------------------+
    |
    v
+---------------------------------------------------+
| DOCKER HOST (Container Orchestration)              |
|                                                    |
|  +--- mer-prod-network (bridge, isolated) ------+ |
|  |                                               | |
|  |  [Nginx :80] --> [PHP-FPM :9000]              | |
|  |       |               |        |              | |
|  |       |          [PostgreSQL]  [Redis]         | |
|  |       |           127.0.0.1    no ports        | |
|  |       |           :5432 only   exposed         | |
|  +-----------------------------------------------+ |
|                                                    |
|  +--- mer-staging-network (bridge, isolated) ---+  |
|  |  [Nginx :8080] --> [PHP-FPM :9000]           |  |
|  |       |               |        |             |  |
|  |       |          [PostgreSQL]  [Redis]        |  |
|  +----------------------------------------------+  |
+----------------------------------------------------+
```

---

## 3. Provisioning Awal & Pembaruan Sistem

### Mengapa Ini Penting?

**Versi Formal:** Patch management adalah kontrol keamanan paling fundamental. Kernel dan package yang belum di-patch mengandung CVE (Common Vulnerabilities and Exposures) yang sudah dipublikasikan. Attacker menggunakan scanner otomatis yang mencocokkan versi software dengan database CVE untuk menemukan target mudah.

**Versi Sederhana:** Saat VPS baru dibeli, software di dalamnya sudah "basi" beberapa minggu/bulan. Update pertama ini seperti menambal semua lubang di dinding gedung sebelum memasukkan brankas.

### Eksekusi

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Login ke VPS menggunakan password root sementara dari panel Hostinger.
# Password ini hanya dipakai SEKALI untuk setup awal, setelah itu dinonaktifkan.
ssh root@<IP_VPS_ANDA>
```

> **Konteks Eksekusi:** `User: root @ VPS`

```bash
# Memperbarui indeks repository dan mengupgrade seluruh paket ke versi terbaru.
# -y = auto-confirm agar tidak perlu menekan Enter berkali-kali.
apt update && apt upgrade -y

# Instalasi utilitas yang dibutuhkan sepanjang panduan ini.
# curl, wget    : download file dan hit API
# nano          : text editor ringan untuk edit konfigurasi
# ufw           : frontend iptables (firewall)
# fail2ban      : intrusion prevention system
# htop          : monitoring proses real-time
# jq            : parser JSON untuk skrip otomasi
# net-tools     : utilitas jaringan (ifconfig, netstat)
# unattended-upgrades : auto-install security patches
# gnupg, lsb-release : kebutuhan verifikasi repository
apt install -y curl wget nano ufw fail2ban htop jq net-tools \
    unattended-upgrades apt-listchanges gnupg lsb-release

# Set timezone server ke WIB (zona waktu Indonesia Barat).
# Konsistensi timezone penting agar timestamp log, cronjob, dan
# audit trail medis semuanya sinkron.
timedatectl set-timezone Asia/Jakarta

# Verifikasi timezone
timedatectl status
```

---

## 4. Alokasi Swap File 4GB (Pencegahan OOM)

### Mengapa Perlu Swap File?

**Versi Formal:** Swap adalah mekanisme *virtual memory* di Linux yang menggunakan sebagian disk sebagai ekstensi RAM. Ketika physical RAM hampir penuh, kernel memindahkan (*page out*) data yang jarang diakses dari RAM ke swap space, membebaskan RAM untuk proses yang aktif. Pada VPS 8GB yang menjalankan Docker build (multi-stage compilation), PostgreSQL, dan PHP-FPM secara simultan, puncak penggunaan RAM dapat melampaui kapasitas fisik. Tanpa swap, Linux OOM Killer akan secara paksa membunuh (*kill*) proses dengan konsumsi RAM tertinggi — yang biasanya adalah database production. Swap memberikan *safety net* agar kernel punya opsi paging sebelum memicu OOM Killer.

**Versi Sederhana:** RAM itu seperti meja kerja dokter — terbatas luasnya. Saat meja penuh dan ada berkas baru masuk, tanpa swap berarti berkas-berkas yang sedang dikerjakan dibuang ke lantai (proses di-kill). Swap seperti menyediakan laci tambahan di samping meja: berkas yang jarang dilihat disimpan di laci (disk), sehingga meja tetap punya ruang untuk tugas yang sedang aktif.

### Mengapa 4GB?

| Skenario | Estimasi Puncak RAM | Tanpa Swap | Dengan Swap 4GB |
|----------|---------------------|------------|------------------|
| Docker multi-stage build (Node + PHP + Composer) | ~6-7GB | OOM Kill saat kompilasi asset | Build selesai, sedikit lebih lambat |
| Lonjakan traffic + PDF generation DOMPDF | ~7-8GB | PostgreSQL atau PHP-FPM di-kill | Sistem tetap responsif |
| Operasi normal (idle/rendah) | ~3-4GB | Aman | Swap tidak terpakai (0% usage) |

**Aturan praktis:** Untuk server dengan RAM 8GB yang bukan dedicated database server, swap 4GB (50% RAM) memberikan keseimbangan antara *safety margin* dan *disk I/O overhead*.

### Eksekusi

> **Konteks Eksekusi:** `User: root @ VPS`

```bash
# Periksa apakah swap sudah ada sebelumnya.
# Jika output kosong, artinya belum ada swap — lanjutkan.
# Jika sudah ada entri, evaluasi apakah ukurannya cukup.
swapon --show

# Buat swap file berukuran 4GB.
# fallocate lebih cepat dari dd karena mengalokasikan blok disk
# tanpa perlu menulis data (zero-copy allocation).
fallocate -l 4G /swapfile

# Set permission ketat: HANYA root yang boleh baca/tulis.
# Swap file yang bisa dibaca user lain adalah kerentanan keamanan
# karena bisa mengandung data sensitif yang di-page-out dari RAM
# (potongan password, session token, data medis).
chmod 600 /swapfile

# Format file sebagai swap space.
mkswap /swapfile

# Aktifkan swap file.
swapon /swapfile

# Verifikasi swap sudah aktif.
swapon --show
# Output yang diharapkan:
# NAME      TYPE  SIZE USED PRIO
# /swapfile file    4G   0B   -2

# Tampilkan ringkasan memori (RAM + Swap)
free -h
# Baris "Swap:" harus menunjukkan total 4.0Gi
```

### Persistensi Setelah Reboot

```bash
# Tanpa baris ini di /etc/fstab, swap file akan hilang setelah server reboot.
# Kita menambahkan entri agar Linux otomatis mengaktifkan swap saat boot.
echo '/swapfile none swap sw 0 0' | tee -a /etc/fstab

# Verifikasi entri fstab
grep swap /etc/fstab
# Output: /swapfile none swap sw 0 0
```

### Tuning Kernel: Swappiness

```bash
# vm.swappiness mengontrol seberapa agresif kernel menggunakan swap.
# Nilai 0-100:
#   0  = kernel HAMPIR TIDAK PERNAH swap (hanya saat emergency)
#   60 = default Ubuntu (terlalu agresif untuk server database)
#   10 = kernel hanya swap jika RAM benar-benar hampir penuh
#
# Nilai 10 dipilih karena:
# - PostgreSQL dan Redis sangat bergantung pada data di RAM
# - Swapping data database ke disk menyebabkan latency 100-1000x lipat
# - Swap hanya boleh dipakai sebagai safety net, BUKAN operasi normal

# Terapkan langsung (berlaku sampai reboot)
sysctl vm.swappiness=10

# Persist ke konfigurasi kernel agar bertahan setelah reboot.
# File ini akan dibaca oleh sysctl saat boot.
echo 'vm.swappiness=10' | tee -a /etc/sysctl.d/99-mer-security.conf

# Verifikasi nilai aktif
sysctl vm.swappiness
# Output: vm.swappiness = 10
```

> [!WARNING]
> **Swap BUKAN pengganti RAM.** Disk I/O (bahkan SSD) 10-100x lebih lambat dari RAM. Jika `swapon --show` secara konsisten menunjukkan USED > 1GB saat operasi normal, ini adalah sinyal bahwa VPS membutuhkan upgrade RAM, bukan penambahan swap.

---

## 5. Pembuatan User Operasional (Non-Root)

### Mengapa Tidak Boleh Pakai Root?

**Versi Formal:** Prinsip *Least Privilege* (PoLP) mengharuskan setiap aktor hanya memiliki hak akses minimum yang dibutuhkan untuk menjalankan tugasnya. User `root` memiliki UID 0 dengan akses tak terbatas ke seluruh filesystem, proses, dan kernel — menjadikannya target utama attacker. Jika SSH session sebagai root dikompromikan, attacker mendapat kontrol penuh tanpa eskalasi tambahan.

**Versi Sederhana:** Menggunakan root sehari-hari seperti membawa kunci master gedung kemana-mana. Jika dicuri, pencuri bisa membuka semua ruangan. Lebih aman membuat kunci khusus staf (user `mer_ops`) yang hanya bisa membuka ruangan tertentu dengan izin (`sudo`).

### Eksekusi

> **Konteks Eksekusi:** `User: root @ VPS`

```bash
# Membuat user baru bernama mer_ops (Medication Error Operations).
# --gecos "" menghilangkan prompt interaktif untuk Full Name, Room Number, dll.
# --disabled-password artinya user ini TIDAK bisa login via password
# (nantinya login hanya via SSH Key).
adduser --gecos "" --disabled-password mer_ops

# Menambahkan mer_ops ke grup sudo sehingga bisa menjalankan
# perintah administratif dengan prefix 'sudo'.
usermod -aG sudo mer_ops

# Konfigurasi sudo tanpa password untuk mer_ops.
# Ini aman karena login hanya via SSH Key (password sudah dinonaktifkan).
# Menghindari prompt password yang mengganggu saat menjalankan skrip otomasi.
echo "mer_ops ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/mer_ops
chmod 440 /etc/sudoers.d/mer_ops

# Verifikasi: pastikan sudoers file valid (salah syntax = terkunci dari sudo)
visudo -c
```

**Mengapa `--disabled-password` dan `NOPASSWD:ALL`?**

Kombinasi ini menerapkan model *"key-only authentication"*:
- User `mer_ops` tidak memiliki password sama sekali (bukan password kosong — password *disabled*).
- Autentikasi sepenuhnya bergantung pada SSH Key Ed25519 (langkah berikutnya).
- `NOPASSWD:ALL` pada sudoers diperlukan karena tanpa password, sudo tidak bisa meminta verifikasi. Ini aman selama SSH Key dijaga dengan baik.

---

## 6. Autentikasi Kriptografis SSH Key Ed25519

### Mengapa Ed25519?

**Versi Formal:** Ed25519 adalah algoritma tanda tangan digital berbasis kurva eliptik Curve25519 yang menawarkan keamanan 128-bit dengan kunci berukuran hanya 256-bit. Dibandingkan RSA-4096 (keamanan setara ~140-bit dengan kunci 4096-bit), Ed25519 menghasilkan operasi sign/verify yang 20-30x lebih cepat dengan ukuran kunci yang jauh lebih kecil. Algoritma ini juga resistant terhadap side-channel timing attack karena operasi constant-time.

**Versi Sederhana:** Ed25519 adalah "gembok sidik jari" generasi terbaru. Lebih kecil dari gembok lama (RSA), tapi justru lebih kuat dan lebih cepat membuka/mengunci.

### Eksekusi — Generate Key di Komputer Lokal

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Generate key pair Ed25519.
# -t ed25519    : algoritma yang digunakan
# -C "..."      : komentar identifikasi (biasanya email admin)
# -f ~/.ssh/... : lokasi penyimpanan file key
ssh-keygen -t ed25519 -C "devops@mer-system.rs" -f ~/.ssh/mer_ops_ed25519

# Saat diminta passphrase: MASUKKAN passphrase yang kuat.
# Passphrase mengenkripsi private key di disk sehingga jika file dicuri,
# attacker tetap tidak bisa menggunakannya tanpa passphrase.
```

Hasil generate:
- `~/.ssh/mer_ops_ed25519` — **Private Key** (RAHASIA, tidak boleh dibagikan)
- `~/.ssh/mer_ops_ed25519.pub` — **Public Key** (aman untuk disalin ke server)

### Eksekusi — Transfer Public Key ke VPS

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Salin public key ke VPS untuk user mer_ops.
# ssh-copy-id akan membuat file ~/.ssh/authorized_keys di server
# dan mengatur permission secara otomatis.
ssh-copy-id -i ~/.ssh/mer_ops_ed25519.pub root@<IP_VPS_ANDA>
```

Karena user `mer_ops` belum bisa diakses via SSH (belum ada key), kita menggunakan sesi `root` yang masih aktif untuk menempatkan key secara manual:

> **Konteks Eksekusi:** `User: root @ VPS`

```bash
# Buat direktori .ssh untuk user mer_ops
mkdir -p /home/mer_ops/.ssh

# Salin authorized_keys dari root (yang barusan di-copy oleh ssh-copy-id)
# ke home directory mer_ops
cp /root/.ssh/authorized_keys /home/mer_ops/.ssh/authorized_keys

# Set ownership dan permission yang ketat.
# SSH daemon menolak key jika permission terlalu longgar (security feature).
# 700 = hanya pemilik yang bisa baca/tulis/eksekusi direktori
# 600 = hanya pemilik yang bisa baca/tulis file
chown -R mer_ops:mer_ops /home/mer_ops/.ssh
chmod 700 /home/mer_ops/.ssh
chmod 600 /home/mer_ops/.ssh/authorized_keys
```

### Verifikasi — Login dengan Key dari Terminal Baru

> **Konteks Eksekusi:** `Terminal Komputer Lokal (TERMINAL BARU — jangan tutup terminal lama)`

```bash
# Test login sebagai mer_ops menggunakan SSH Key
ssh -i ~/.ssh/mer_ops_ed25519 mer_ops@<IP_VPS_ANDA>

# Jika berhasil masuk tanpa diminta password server (hanya passphrase key),
# maka SSH Key sudah terpasang dengan benar.

# Test sudo access
sudo whoami
# Output yang diharapkan: root
```

> [!CAUTION]
> **JANGAN tutup terminal root yang lama** sampai Anda berhasil login di terminal baru. Jika ada kesalahan konfigurasi, terminal root lama adalah satu-satunya jalan masuk.

---

## 7. Hardening Daemon SSH

### Mengapa Harus Di-hardening?

**Versi Formal:** Daemon SSH (sshd) adalah satu-satunya entry point remote ke server. Konfigurasi default mengizinkan root login dan password authentication — dua vektor yang paling sering dieksploitasi melalui brute-force dan credential stuffing attack. Hardening sshd mengeliminasi kedua vektor ini.

**Versi Sederhana:** Pintu SSH default-nya terbuka lebar dan menerima siapa saja yang tahu password. Kita ganti pintunya: pindahkan ke lokasi rahasia (port custom), ganti gemboknya jadi sidik jari (key-only), dan tutup pintu untuk boss besar (root).

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Backup konfigurasi SSH yang asli sebelum modifikasi.
# Jika terjadi kesalahan, bisa dikembalikan.
sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup.$(date +%Y%m%d)

# Tulis konfigurasi hardened SSH.
# Menggunakan tee agar bisa menulis ke file yang memerlukan sudo.
sudo tee /etc/ssh/sshd_config > /dev/null << 'SSHD_CONFIG'
# ===========================================
# MER System - Hardened SSH Configuration
# ===========================================

# --- Network ---
# Port 49152 dipilih dari range dynamic/private ports (49152-65535).
# Memindahkan dari port 22 mengurangi 99% automated scanning bots
# yang hanya mentarget port default.
Port 49152

# Hanya listen di IPv4. Jika VPS mendukung IPv6, tambahkan ListenAddress ::
ListenAddress 0.0.0.0

# --- Authentication ---
# Menonaktifkan root login mencegah eksploitasi langsung ke UID 0.
PermitRootLogin no

# Menonaktifkan password authentication memaksa penggunaan SSH Key.
# Ini mengeliminasi SELURUH brute-force dan credential stuffing attack.
PasswordAuthentication no
PermitEmptyPasswords no

# Mengaktifkan public key authentication (SSH Key Ed25519).
PubkeyAuthentication yes
AuthorizedKeysFile .ssh/authorized_keys

# Menonaktifkan metode autentikasi lain yang tidak digunakan.
ChallengeResponseAuthentication no
KbdInteractiveAuthentication no
UsePAM yes

# --- Session ---
# Putuskan koneksi idle setelah 10 menit (600 detik).
# Mencegah session hijacking dari terminal yang ditinggalkan.
ClientAliveInterval 300
ClientAliveCountMax 2

# Batasi jumlah sesi SSH simultan per user.
MaxSessions 3

# Batasi percobaan autentikasi per koneksi.
MaxAuthTries 3

# --- Security Hardening ---
# Menonaktifkan X11 forwarding (tidak perlu GUI di server).
X11Forwarding no

# Menonaktifkan TCP forwarding kecuali untuk SSH tunnel ke database.
AllowTcpForwarding yes

# Menonaktifkan agent forwarding (mencegah lateral movement).
AllowAgentForwarding no

# Hanya izinkan user mer_ops yang boleh SSH.
AllowUsers mer_ops

# Log level VERBOSE untuk audit trail yang detail.
LogLevel VERBOSE

# Banner kosong agar tidak leak informasi OS/versi.
Banner none

# Menonaktifkan protokol tunneling yang tidak dibutuhkan.
PermitTunnel no

# --- Performance ---
# Menggunakan algoritma kriptografi modern saja.
# Menghapus cipher, MAC, dan KEX yang lemah/deprecated.
KexAlgorithms curve25519-sha256,curve25519-sha256@libssh.org
Ciphers chacha20-poly1305@openssh.com,aes256-gcm@openssh.com,aes128-gcm@openssh.com
MACs hmac-sha2-512-etm@openssh.com,hmac-sha2-256-etm@openssh.com
SSHD_CONFIG

# Validasi syntax konfigurasi SEBELUM restart.
# Jika ada error, sshd tidak akan restart dan koneksi aktif tetap aman.
sudo sshd -t
```

Jika `sshd -t` tidak mengeluarkan error apapun, lanjutkan:

```bash
# Restart SSH daemon untuk menerapkan konfigurasi baru.
sudo systemctl restart ssh

# Periksa status daemon
sudo systemctl status ssh
```

> [!CAUTION]
> **JANGAN tutup terminal ini.** Buka terminal baru dan test koneksi SSH dengan port baru:

> **Konteks Eksekusi:** `Terminal Komputer Lokal (TERMINAL BARU)`

```bash
# Test login dengan port baru
ssh -i ~/.ssh/mer_ops_ed25519 -p 49152 mer_ops@<IP_VPS_ANDA>
```

Setelah berhasil login di terminal baru, buat SSH config untuk kemudahan:

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Tambahkan konfigurasi SSH shortcut
cat >> ~/.ssh/config << 'SSH_CONFIG'

Host mer-vps
    HostName <IP_VPS_ANDA>
    User mer_ops
    Port 49152
    IdentityFile ~/.ssh/mer_ops_ed25519
    IdentitiesOnly yes
SSH_CONFIG

# Sekarang cukup ketik:
ssh mer-vps
```

---

## 8. Konfigurasi Firewall UFW Eksklusif Cloudflare

### Mengapa UFW Harus Eksklusif Cloudflare?

**Versi Formal:** Jika UFW mengizinkan port 80/443 dari semua IP (`0.0.0.0/0`), maka attacker dapat mem-bypass Cloudflare WAF dengan mengakses server langsung menggunakan IP asli VPS. Ini meniadakan seluruh investasi keamanan di edge layer. Dengan membatasi allowlist hanya pada ASN Cloudflare (AS13335), kita memastikan 100% traffic HTTP/HTTPS melewati inspeksi WAF.

**Versi Sederhana:** Jika pintu lobi menerima semua tamu — bukan hanya yang diantar satpam — maka satpam (Cloudflare) jadi percuma. Kita program pintunya agar hanya mau terbuka jika tamu menunjukkan tanda pengenal dari satpam.

### Penting: Jebakan Docker & UFW

> [!WARNING]
> **Docker secara default mem-bypass UFW.** Docker menulis aturan langsung ke iptables chain `DOCKER-USER`, melewati chain `ufw-user-input`. Artinya, sebuah container dengan `ports: "5432:5432"` akan terekspos ke internet meskipun UFW tidak mengizinkan port 5432.
>
> **Solusi yang diterapkan di panduan ini:**
> 1. Semua port database dan Redis di `docker-compose.yml` di-bind ke `127.0.0.1` (loopback) sehingga hanya bisa diakses dari server itu sendiri.
> 2. Hanya port 80 (Nginx) yang di-expose ke `0.0.0.0`, dan UFW memfilter traffic ke port ini hanya dari IP Cloudflare.

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Reset UFW ke kondisi bersih (menghapus semua aturan sebelumnya).
sudo ufw --force reset

# Set policy default: tolak semua koneksi masuk, izinkan semua keluar.
# "deny incoming" adalah prinsip default-deny yang fundamental di network security.
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Izinkan port SSH custom (49152) dari mana saja.
# Ini perlu agar kita tidak terkunci dari server.
# Fail2Ban akan melindungi port ini dari brute-force.
sudo ufw allow 49152/tcp comment 'MER-SSH-Custom-Port'
```

### Memasukkan IP Cloudflare ke UFW

```bash
# Skrip ini mengambil daftar IP resmi Cloudflare (IPv4 dan IPv6)
# dan membuat aturan UFW yang mengizinkan hanya IP tersebut
# mengakses port 80 dan 443.

echo "--- Menambahkan IPv4 Cloudflare ---"
for ip in $(curl -s https://www.cloudflare.com/ips-v4/); do
    sudo ufw allow proto tcp from "$ip" to any port 80,443 comment 'Cloudflare-IPv4'
done

echo "--- Menambahkan IPv6 Cloudflare ---"
for ip in $(curl -s https://www.cloudflare.com/ips-v6/); do
    sudo ufw allow proto tcp from "$ip" to any port 80,443 comment 'Cloudflare-IPv6'
done

# Aktifkan UFW
sudo ufw --force enable

# Tampilkan semua aturan yang telah dibuat
sudo ufw status verbose
```

---

## 9. Skrip Otomatis Pembaruan IP Cloudflare (Cronjob)

### Mengapa Perlu Otomatisasi?

**Versi Formal:** Cloudflare secara berkala menambahkan blok IP baru ke jaringan anycast mereka saat melakukan ekspansi infrastruktur. Jika aturan UFW tidak diperbarui, traffic dari blok IP baru akan ditolak, menyebabkan *partial outage* dimana sebagian pengguna tidak bisa mengakses sistem.

**Versi Sederhana:** Satpam Cloudflare kadang mengganti seragam (IP baru). Kalau pintu tidak diupdate daftar seragam yang dikenali, satpam yang pakai seragam baru akan ditolak masuk. Skrip ini otomatis mengupdate daftar setiap minggu.

### Eksekusi — Buat Skrip

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Buat direktori untuk skrip operasional
sudo mkdir -p /opt/mer-system/scripts

# Tulis skrip pembaruan IP Cloudflare
sudo tee /opt/mer-system/scripts/update-cloudflare-ufw.sh > /dev/null << 'SCRIPT'
#!/usr/bin/env bash
# ===========================================
# MER System - Cloudflare IP Updater for UFW
# ===========================================
# Skrip ini dijalankan via cronjob untuk memperbarui
# aturan UFW agar selalu sinkron dengan IP Cloudflare terbaru.
#
# Alur kerja:
# 1. Download daftar IP terbaru dari Cloudflare
# 2. Hapus semua aturan UFW lama bertanda 'Cloudflare'
# 3. Tambahkan aturan baru dari daftar terbaru
# 4. Log hasilnya untuk audit trail
# ===========================================

set -euo pipefail

LOG_FILE="/var/log/mer-system/cloudflare-ufw-update.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

mkdir -p /var/log/mer-system

log() {
    echo "[$TIMESTAMP] $1" | tee -a "$LOG_FILE"
}

log "=== Memulai pembaruan IP Cloudflare ==="

# Download daftar IP dari Cloudflare (sumber resmi)
CF_IPV4=$(curl -sf https://www.cloudflare.com/ips-v4/)
CF_IPV6=$(curl -sf https://www.cloudflare.com/ips-v6/)

if [ -z "$CF_IPV4" ]; then
    log "ERROR: Gagal mengunduh daftar IPv4 Cloudflare. Membatalkan update."
    exit 1
fi

# Hapus semua aturan UFW lama yang bertanda 'Cloudflare'
# Penghapusan dilakukan dari nomor terbesar ke terkecil agar indeks tidak bergeser.
EXISTING_RULES=$(sudo ufw status numbered | grep -i 'Cloudflare' | awk -F'[][]' '{print $2}' | sort -rn)

if [ -n "$EXISTING_RULES" ]; then
    log "Menghapus $(echo "$EXISTING_RULES" | wc -l) aturan Cloudflare lama..."
    for rule_num in $EXISTING_RULES; do
        sudo ufw --force delete "$rule_num"
    done
fi

# Tambahkan aturan baru dari daftar IPv4
IPV4_COUNT=0
for ip in $CF_IPV4; do
    sudo ufw allow proto tcp from "$ip" to any port 80,443 comment 'Cloudflare-IPv4'
    IPV4_COUNT=$((IPV4_COUNT + 1))
done

# Tambahkan aturan baru dari daftar IPv6
IPV6_COUNT=0
for ip in $CF_IPV6; do
    sudo ufw allow proto tcp from "$ip" to any port 80,443 comment 'Cloudflare-IPv6'
    IPV6_COUNT=$((IPV6_COUNT + 1))
done

log "Berhasil: $IPV4_COUNT aturan IPv4 dan $IPV6_COUNT aturan IPv6 ditambahkan."
log "=== Pembaruan selesai ==="
SCRIPT

# Set permission: hanya root yang bisa mengeksekusi
sudo chmod 700 /opt/mer-system/scripts/update-cloudflare-ufw.sh
```

### Eksekusi — Daftarkan Cronjob

```bash
# Tambahkan cronjob yang berjalan setiap hari Minggu pukul 03:00 WIB.
# Dipilih Minggu dini hari karena traffic biasanya paling rendah.
(sudo crontab -l 2>/dev/null; echo "0 3 * * 0 /opt/mer-system/scripts/update-cloudflare-ufw.sh") | sudo crontab -

# Verifikasi cronjob terdaftar
sudo crontab -l
```

### Test Manual Skrip

```bash
# Jalankan skrip secara manual untuk memastikan bekerja dengan benar
sudo /opt/mer-system/scripts/update-cloudflare-ufw.sh

# Periksa log hasil eksekusi
cat /var/log/mer-system/cloudflare-ufw-update.log

# Periksa aturan UFW setelah update
sudo ufw status numbered
```

---

## 10. Konfigurasi Fail2Ban Anti Brute-Force

### Mengapa Fail2Ban?

**Versi Formal:** Fail2Ban adalah Intrusion Prevention System (IPS) yang melakukan real-time log parsing pada file autentikasi (`/var/log/auth.log`) dan secara otomatis membuat aturan iptables untuk memblokir IP yang menunjukkan pola serangan (multiple failed login attempts). Ini adalah lapisan pertahanan aktif yang melengkapi hardening pasif SSH.

**Versi Sederhana:** Fail2Ban adalah CCTV otomatis. Jika ada orang yang salah memasukkan kunci pintu 3 kali, CCTV langsung mengunci orang itu di luar selama 4 jam. Tidak perlu operator manusia.

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Tulis konfigurasi jail khusus MER System.
# Menggunakan jail.local (bukan jail.conf) agar aman dari overwrite saat update paket.
sudo tee /etc/fail2ban/jail.local > /dev/null << 'JAIL_CONFIG'
# ===========================================
# MER System - Fail2Ban Configuration
# ===========================================

[DEFAULT]
# Ban IP selama 4 jam (14400 detik) setelah pelanggaran.
bantime = 14400

# Jendela waktu observasi: 10 menit.
# Jika ada 3 kegagalan dalam 10 menit, IP langsung di-ban.
findtime = 600

# Jumlah percobaan gagal sebelum di-ban.
# 3 kali dipilih (bukan 5) karena SSH sudah di-hardening dengan key-only.
# Percobaan password seharusnya 0 — jadi 3 kali mencoba artinya pasti attacker.
maxretry = 3

# Abaikan IP loopback dan private network agar tidak mem-ban diri sendiri.
ignoreip = 127.0.0.1/8 ::1

# Gunakan systemd backend untuk membaca journal log.
backend = systemd

# --- Jail: SSH ---
[sshd]
enabled = true
# Port SSH custom yang sudah dikonfigurasi di sshd_config.
port = 49152
# Filter bawaan Fail2Ban untuk mendeteksi pola failed SSH login.
filter = sshd
# Sumber log autentikasi SSH.
logpath = /var/log/auth.log
# Override: SSH lebih agresif — ban 8 jam untuk repeated offenders.
bantime = 28800
maxretry = 3
JAIL_CONFIG

# Restart Fail2Ban untuk menerapkan konfigurasi baru.
sudo systemctl restart fail2ban
sudo systemctl enable fail2ban

# Verifikasi status jail sshd
sudo fail2ban-client status sshd
```

**Output yang diharapkan:**

```
Status for the jail: sshd
|- Filter
|  |- Currently failed: 0
|  |- Total failed:     0
|  `- File list:        /var/log/auth.log
`- Actions
   |- Currently banned: 0
   |- Total banned:     0
   `- Banned IP list:
```

---

## 11. Hardening Kernel (Sysctl)

### Mengapa Perlu Tuning Kernel?

**Versi Formal:** Parameter kernel default di Ubuntu dioptimasi untuk kompatibilitas umum, bukan keamanan. Sysctl tuning memungkinkan kita menonaktifkan fitur-fitur yang bisa dieksploitasi (IP forwarding, ICMP redirect) dan mengaktifkan fitur proteksi (SYN cookies, reverse path filtering).

**Versi Sederhana:** Kernel adalah "otak" server. Secara default, otaknya diprogram untuk ramah ke semua orang. Kita program ulang agar lebih waspada dan menolak trik-trik yang biasa dipakai peretas.

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Tulis parameter keamanan kernel
sudo tee /etc/sysctl.d/99-mer-security.conf > /dev/null << 'SYSCTL_CONFIG'
# ===========================================
# MER System - Kernel Security Parameters
# ===========================================

# --- IP Forwarding ---
# Nonaktifkan IP forwarding karena server ini BUKAN router.
# Jika aktif, attacker bisa menggunakan server sebagai relay untuk
# menyerang jaringan internal lain.
# CATATAN: Docker membutuhkan ip_forward=1. Jangan set ke 0 jika
# Docker sudah terinstal. Docker mengelola forwarding sendiri
# via iptables chain DOCKER-USER.
net.ipv4.ip_forward = 1

# --- ICMP (Ping) Hardening ---
# Ignore ICMP redirect agar server tidak mengikuti instruksi routing
# dari sumber yang tidak dipercaya (MITM vector).
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.default.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.default.send_redirects = 0

# --- SYN Flood Protection ---
# SYN cookies mencegah SYN flood attack yang menghabiskan memori
# kernel dengan half-open connections.
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.tcp_synack_retries = 2

# --- Reverse Path Filtering ---
# Memverifikasi bahwa paket masuk berasal dari interface yang benar.
# Mencegah IP spoofing attack.
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# --- Logging ---
# Log paket dengan alamat sumber yang "tidak mungkin" (martian packets).
# Berguna untuk forensik dan deteksi serangan.
net.ipv4.conf.all.log_martians = 1
net.ipv4.conf.default.log_martians = 1

# --- Connection Tracking ---
# Tingkatkan jumlah maksimum koneksi yang dilacak oleh netfilter.
# Default 65536 terlalu kecil untuk server dengan Docker + banyak koneksi.
net.netfilter.nf_conntrack_max = 131072

# --- File Descriptor Limit ---
# Tingkatkan limit file descriptor untuk mendukung banyak koneksi
# database dan PHP-FPM worker secara simultan.
fs.file-max = 65535
SYSCTL_CONFIG

# Terapkan parameter baru tanpa reboot
sudo sysctl -p /etc/sysctl.d/99-mer-security.conf
```

---

## 12. Automatic Security Updates (Unattended Upgrades)

### Mengapa Auto-Update?

**Versi Formal:** Zero-day vulnerabilities dipublikasikan rata-rata 25 kali per bulan untuk komponen Linux. *Mean Time to Exploit* (MTTE) setelah CVE dipublikasikan berkisar 7-14 hari. Unattended upgrades memastikan security patches diterapkan dalam hitungan jam, bukan hari/minggu.

**Versi Sederhana:** Seperti memasang sistem tambal-otomatis di dinding gedung. Setiap kali ditemukan lubang baru, sistem langsung menambalnya tanpa perlu menunggu tukang datang.

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Konfigurasi unattended-upgrades
sudo tee /etc/apt/apt.conf.d/50unattended-upgrades > /dev/null << 'UNATTENDED_CONFIG'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};

// Tidak auto-reboot — sistem medis harus dijadwalkan maintenance window.
Unattended-Upgrade::Automatic-Reboot "false";

// Bersihkan paket lama yang tidak terpakai setelah upgrade
Unattended-Upgrade::Remove-Unused-Dependencies "true";

// Log aktivitas auto-update untuk audit
Unattended-Upgrade::SyslogEnable "true";
Unattended-Upgrade::SyslogFacility "daemon";
UNATTENDED_CONFIG

# Aktifkan auto-update periodic
sudo tee /etc/apt/apt.conf.d/20auto-upgrades > /dev/null << 'AUTO_CONFIG'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
AUTO_CONFIG

# Verifikasi konfigurasi
sudo unattended-upgrades --dry-run --debug 2>&1 | head -20
```

---

## 13. Verifikasi Akhir

Setelah semua langkah selesai, jalankan checklist verifikasi berikut:

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
echo "========================================="
echo "  MER System - OS Hardening Verification"
echo "========================================="
echo ""

echo "[1] User & Sudo"
echo "  Current user : $(whoami)"
echo "  Sudo access  : $(sudo -n whoami 2>/dev/null || echo 'REQUIRES PASSWORD')"
echo ""

echo "[2] SSH Configuration"
echo "  SSH Port     : $(grep '^Port' /etc/ssh/sshd_config)"
echo "  Root Login   : $(grep '^PermitRootLogin' /etc/ssh/sshd_config)"
echo "  Password Auth: $(grep '^PasswordAuthentication' /etc/ssh/sshd_config)"
echo ""

echo "[3] Firewall (UFW)"
sudo ufw status | head -5
echo "  Total rules  : $(sudo ufw status numbered | grep -c '\[.*\]')"
echo ""

echo "[4] Fail2Ban"
echo "  Service      : $(systemctl is-active fail2ban)"
echo "  SSH Jail     : $(sudo fail2ban-client status sshd 2>/dev/null | grep 'Currently banned' || echo 'NOT RUNNING')"
echo ""

echo "[5] Swap File"
echo "  Swap active  : $(swapon --show --noheadings | awk '{print $1, $3}' || echo 'NO SWAP')"
echo "  Swappiness   : $(sysctl -n vm.swappiness)"
echo ""

echo "[6] Kernel Hardening"
echo "  SYN cookies  : $(sysctl -n net.ipv4.tcp_syncookies)"
echo "  RP filter    : $(sysctl -n net.ipv4.conf.all.rp_filter)"
echo "  Redirects    : $(sysctl -n net.ipv4.conf.all.accept_redirects)"
echo ""

echo "[7] Auto Updates"
echo "  Service      : $(systemctl is-active unattended-upgrades)"
echo ""

echo "[8] Timezone"
echo "  Timezone     : $(timedatectl show -p Timezone --value)"
echo ""

echo "[9] Cloudflare Cronjob"
sudo crontab -l 2>/dev/null | grep -c 'cloudflare' && echo "  Status: ACTIVE" || echo "  Status: NOT FOUND"
echo ""
echo "========================================="
echo "  Verifikasi selesai."
echo "========================================="
```

**Output yang diharapkan (semua hijau):**

| # | Item | Nilai Benar |
|---|------|-------------|
| 1 | Current user | `mer_ops` |
| 2 | SSH Port | `Port 49152` |
| 3 | Root Login | `PermitRootLogin no` |
| 4 | Password Auth | `PasswordAuthentication no` |
| 5 | Swap File | `/swapfile 4G`, swappiness `10` |
| 6 | UFW Status | `active` dengan 15+ rules Cloudflare |
| 7 | Fail2Ban | `active`, SSH jail running |
| 8 | SYN cookies | `1` |
| 9 | Auto Updates | `active` |
| 10 | Timezone | `Asia/Jakarta` |
| 11 | CF Cronjob | `ACTIVE` |

---

> **Dokumen selanjutnya:** [02-cloudflare-dan-edge-security.md](./02-cloudflare-dan-edge-security.md) — Setup DNS Proxied, Full Strict SSL, Origin Certificate, dan WAF Rules untuk sistem medis.
