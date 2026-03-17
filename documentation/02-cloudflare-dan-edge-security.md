# 02 — Cloudflare & Edge Security

> **Sistem**: Medication Error Reporting (MER) — Rumah Sakit  
> **Klasifikasi**: HIGH-RISK (Data Medis Sensitif / PHI)  
> **Prasyarat**: Dokumen [01-arsitektur-dan-hardening-os.md](./01-arsitektur-dan-hardening-os.md) sudah selesai dilaksanakan

---

## Daftar Isi

1. [Konsep Edge Security](#1-konsep-edge-security)
2. [Setup DNS dengan Proxy Cloudflare](#2-setup-dns-dengan-proxy-cloudflare)
3. [Konfigurasi SSL/TLS Full (Strict)](#3-konfigurasi-ssltls-full-strict)
4. [Generate Origin Certificate](#4-generate-origin-certificate)
5. [Instalasi Origin Certificate di Server](#5-instalasi-origin-certificate-di-server)
6. [WAF Rules untuk Sistem Medis](#6-waf-rules-untuk-sistem-medis)
7. [Rate Limiting](#7-rate-limiting)
8. [Bot Fight Mode & Security Level](#8-bot-fight-mode--security-level)
9. [Page Rules & Caching Strategy](#9-page-rules--caching-strategy)
10. [Verifikasi Edge Security](#10-verifikasi-edge-security)

---

## 1. Konsep Edge Security

### Versi Sederhana (Analogi Pemula)

Cloudflare bertindak sebagai **pos satpam di gerbang utama** rumah sakit digital:

| Fungsi | Analogi |
|--------|---------|
| **DNS Proxied** | Alamat rumah sakit yang tertera di Google Maps menunjuk ke pos satpam, BUKAN langsung ke gedung. Sehingga tidak ada yang tahu lokasi asli gedung. |
| **WAF (Web Application Firewall)** | Satpam memeriksa setiap tas tamu menggunakan X-ray. Jika ditemukan senjata (SQL injection, XSS), tamu langsung ditolak. |
| **SSL/TLS Full Strict** | Semua surat yang dikirim dari dan ke rumah sakit dimasukkan ke dalam amplop berlak segel (enkripsi) — baik dari tamu ke satpam, maupun dari satpam ke gedung. |
| **DDoS Protection** | Jika ada seribu orang datang bersamaan untuk memblokir pintu masuk (DDoS), satpam punya jalur khusus untuk tamu asli dan mengusir kerumunan. |
| **Origin Certificate** | Sertifikat identitas khusus antara satpam dan gedung. Satpam hanya mau meneruskan tamu ke gedung yang punya sertifikat ini. |

### Versi Formal (Standar Industri)

Cloudflare berfungsi sebagai **reverse proxy edge** yang beroperasi di layer 7 (application layer) dari model OSI. Dengan menempatkan Cloudflare di depan origin server, kita mendapatkan:

1. **IP Masking** — IP asli VPS tersembunyi di balik anycast network Cloudflare (AS13335), mencegah direct-to-origin attacks.
2. **TLS Termination** — Cloudflare menangani handshake TLS dengan client, mengurangi beban CPU di origin server.
3. **WAF Inspection** — Setiap HTTP request diinspeksi terhadap ruleset OWASP Core Rule Set dan custom rules sebelum diteruskan ke origin.
4. **DDoS Mitigation** — Cloudflare menyerap volumetric attacks di edge network mereka yang berkapasitas 209+ Tbps.
5. **Authenticated Origin Pull** — Koneksi antara Cloudflare dan origin server diverifikasi menggunakan mutual TLS (mTLS) via Origin Certificate.

---

## 2. Setup DNS dengan Proxy Cloudflare

### Prasyarat

- Domain sudah terdaftar dan nameserver sudah diarahkan ke Cloudflare
- Akun Cloudflare sudah aktif (free plan sudah cukup untuk fitur yang dibutuhkan)

### Eksekusi — Dashboard Cloudflare

```
Navigasi: Cloudflare Dashboard > Domain Anda > DNS > Records
```

Buat DNS record berikut:

| Type | Name | Content | Proxy Status | TTL |
|------|------|---------|--------------|-----|
| `A` | `@` | `<IP_VPS_ANDA>` | **Proxied** (awan oranye) | Auto |
| `A` | `www` | `<IP_VPS_ANDA>` | **Proxied** (awan oranye) | Auto |

> [!IMPORTANT]
> **Proxy Status HARUS "Proxied" (awan oranye).** Jika di-set "DNS only" (awan abu-abu), IP asli VPS terekspos langsung ke publik. Semua perlindungan Cloudflare (WAF, DDoS, dll) menjadi tidak aktif.

### Mengapa Proxied dan Bukan DNS Only?

**SBerdasarkan diagram arsitektur di atas:**
- Ketika user melakukan DNS lookup ke `mers-rsryacudu.com`, jawaban yang dikembalikan adalah IP Cloudflare, **bukan** IP VPS asli.
- Atacker tidak mengetahui IP VPS asli, sehingga tidak dapat menyerang server secara langsung melalui DDoS volumetrik.
- Traffic berbahaya (SQLi, XSS, bot) diblokir di Edge (server Cloudflare) sebelum mencapai VPS.

**Risiko DNS Only:**
- IP VPS terekspos langsung.
- Attacker bisa mem-bypass Cloudflare dan menyerang server langsung.
- Semua investasi keamanan di edge layer menjadi sia-sia.

---

## 3. Konfigurasi SSL/TLS Full (Strict)

### Mengapa Full (Strict)?

Cloudflare menawarkan 4 mode SSL:

| Mode | Client → CF | CF → Origin | Keamanan |
|------|-------------|-------------|----------|
| **Off** | HTTP | HTTP | Tidak ada enkripsi sama sekali |
| **Flexible** | HTTPS | HTTP | Berbahaya: data telanjang antara CF dan server |
| **Full** | HTTPS | HTTPS (self-signed OK) | Rentan MITM: sertifikat bisa dipalsukan |
| **Full (Strict)** | HTTPS | HTTPS (valid cert wajib) | Aman: sertifikat diverifikasi keduanya |

> [!CAUTION]
> **JANGAN gunakan mode "Flexible".** Mode ini menciptakan ilusi keamanan yang berbahaya. User melihat gembok HTTPS di browser, tapi data antara Cloudflare dan server Anda dikirim dalam bentuk teks biasa (HTTP). Untuk sistem yang menangani data medis, ini adalah pelanggaran keamanan fundamental.

### Eksekusi — Dashboard Cloudflare

```
Navigasi: Cloudflare Dashboard > Domain Anda > SSL/TLS > Overview
```

1.  Set **SSL/TLS encryption mode** ke **Full (Strict)**
2.  Pastikan toggle **Always Use HTTPS** di tab **Edge Certificates** dalam keadaan **ON**

### Konfigurasi Tambahan SSL/TLS

```
Navigasi: Cloudflare Dashboard > Domain Anda > SSL/TLS > Edge Certificates
```

| Setting | Nilai | Alasan |
|---------|-------|--------|
| **Always Use HTTPS** | ON | Redirect otomatis HTTP → HTTPS |
| **HTTP Strict Transport Security (HSTS)** | Enable | Browser mengingat bahwa domain ini hanya HTTPS |
| **HSTS Max-Age** | 6 bulan (15768000) | Durasi browser mengingat policy HSTS |
| **Include Subdomains** | ON | HSTS berlaku juga untuk subdomain |
| **Minimum TLS Version** | TLS 1.2 | TLS 1.0 dan 1.1 sudah deprecated dan rentan |
| **Opportunistic Encryption** | ON | Upgrade koneksi ke HTTPS jika memungkinkan |
| **TLS 1.3** | ON | Versi TLS terbaru dengan 0-RTT handshake |
| **Automatic HTTPS Rewrites** | ON | Fix mixed content (HTTP resource di halaman HTTPS) |

---

## 4. Generate Origin Certificate

### Apa Itu Origin Certificate?

**Versi Formal:** Origin Certificate adalah sertifikat TLS yang diterbitkan oleh Cloudflare khusus untuk mengenkripsi koneksi antara Cloudflare edge server dan origin server Anda. Sertifikat ini hanya dikenali oleh Cloudflare (bukan oleh browser publik), sehingga memastikan hanya Cloudflare yang bisa berkomunikasi dengan origin server via HTTPS.

**Versi Sederhana:** Origin Certificate adalah "kartu identitas khusus" antara pos satpam (Cloudflare) dan gedung (VPS). Pos satpam hanya mau mengirim tamu ke gedung yang punya kartu identitas ini. Gedung lain yang mengaku-aku akan ditolak.

### Eksekusi — Generate di Dashboard Cloudflare

```
Navigasi: Cloudflare Dashboard > Domain Anda > SSL/TLS > Origin Server
```

1.  Klik **Create Certificate**
2.  Pilih opsi berikut:

| Setting | Nilai |
|---------|-------|
| **Private key type** | RSA (2048) |
| **Hostnames** | `mers-rsryacudu.com`, `*.mers-rsryacudu.com` |
| **Certificate validity** | 15 years |

3.  Klik **Create**
4.  Cloudflare akan menampilkan dua blok teks:
    -   **Origin Certificate** (PEM) — Public certificate
    -   **Private Key** (PEM) — Kunci privat

> [!CAUTION]
> **SALIN DAN SIMPAN PRIVATE KEY SEKARANG.** Cloudflare hanya menampilkan private key SEKALI. Jika tab ditutup sebelum disalin, Anda harus membuat sertifikat baru.

---

## 5. Instalasi Origin Certificate di Server

### Eksekusi

> **Konteks Eksekusi:** `User: mer_ops @ VPS`

```bash
# Buat direktori khusus untuk sertifikat SSL
sudo mkdir -p /etc/ssl/cloudflare

# Simpan Origin Certificate (public cert).
# Paste isi sertifikat yang diperoleh dari Cloudflare Dashboard.
sudo tee /etc/ssl/cloudflare/origin-cert.pem > /dev/null << 'CERT'
-----BEGIN CERTIFICATE-----
<PASTE_ORIGIN_CERTIFICATE_DARI_CLOUDFLARE_DASHBOARD_DI_SINI>
-----END CERTIFICATE-----
CERT

# Simpan Private Key.
# Paste isi private key yang diperoleh dari Cloudflare Dashboard.
sudo tee /etc/ssl/cloudflare/origin-key.pem > /dev/null << 'KEY'
-----BEGIN PRIVATE KEY-----
<PASTE_PRIVATE_KEY_DARI_CLOUDFLARE_DASHBOARD_DI_SINI>
-----END PRIVATE KEY-----
KEY

# Set permission ketat pada private key.
# 600 = hanya root yang bisa membaca file ini.
# Private key yang bisa dibaca oleh user lain adalah kerentanan kritis.
sudo chmod 600 /etc/ssl/cloudflare/origin-key.pem
sudo chmod 644 /etc/ssl/cloudflare/origin-cert.pem
sudo chown root:root /etc/ssl/cloudflare/*
```

### Download Cloudflare Authenticated Origin Pull CA

```bash
# Download CA certificate Cloudflare untuk Authenticated Origin Pull.
# Sertifikat ini digunakan oleh Nginx untuk memverifikasi bahwa
# koneksi masuk benar-benar berasal dari Cloudflare (bukan attacker
# yang mengirim request langsung ke port 443 server).
sudo curl -o /etc/ssl/cloudflare/authenticated-origin-pull-ca.pem \
    https://developers.cloudflare.com/ssl/static/authenticated_origin_pull_ca.pem

sudo chmod 644 /etc/ssl/cloudflare/authenticated-origin-pull-ca.pem
```

### Verifikasi File Sertifikat

```bash
# Pastikan semua file sertifikat ada dan permission benar
ls -la /etc/ssl/cloudflare/

# Output yang diharapkan:
# -rw-r--r-- 1 root root  xxxx  ... authenticated-origin-pull-ca.pem
# -rw-r--r-- 1 root root  xxxx  ... origin-cert.pem
# -rw------- 1 root root  xxxx  ... origin-key.pem

# Verifikasi sertifikat valid (tampilkan info)
openssl x509 -in /etc/ssl/cloudflare/origin-cert.pem -noout -subject -dates
```

> [!NOTE]
> Konfigurasi Nginx untuk menggunakan sertifikat ini akan dibahas di dokumen [04-nginx-waf-dan-laravel.md](./04-nginx-waf-dan-laravel.md). Pada tahap ini, kita hanya memastikan file sertifikat sudah tersimpan dengan benar di server.

---

## 6. WAF Rules untuk Sistem Medis

### Pendekatan "Living off the Land" (Free Tier)

**Versi Formal:** Paket gratis Cloudflare tidak mensupport Managed WAF Ruleset (seperti OWASP Core Rule Set) yang terkunci di balik paywall Pro. Oleh karena itu, kita menggunakan pendekatan *Living off the Land* dengan memaksimalkan kuota 5 Custom Rules gratis. Rules ini didesain secara spesifik untuk mereplikasi arsitektur Enterprise dengan pola blocklist/challenge yang presisi terhadap aset sensitif dan layar autentikasi Laravel.

**Versi Sederhana:** Karena fitur satpam otomatis (Managed WAF) berbayar, kita mendaftarkan 3 aturan khusus (Custom Rules) ke satpam gerbang depan kita (Cloudflare) secara manual. Aturan ini sama amannya, tapi dikhususkan hanya untuk menjaga pintu-pintu rahasia dan gerbang masuk aplikasi kita.

### Eksekusi — Buat Custom WAF Rules

```
Navigasi: Cloudflare Dashboard > Domain Anda > Security > WAF > Custom rules
```

Klik **Create rule** untuk masing-masing rule di bawah ini. Saat di halaman pembuatan, klik link **Edit expression** (di sebelah tombol "Use expression builder") untuk menempelkan sintaks teks secara eksplisit.

#### Rule 1: Laravel Shield (Block Sensitive Files)

Mencegah akses publik ke file inti framework, repositori, dan sistem log.

| Field | Value |
|-------|-------|
| **Rule name** | `Laravel Shield` |
| **Expression** | *(lihat di bawah)* |
| **Action** | Block |

Gunakan sintaks berikut pada Expression Builder:
```text
(http.request.uri.path contains "/.env") or 
(http.request.uri.path contains "/.git") or 
(http.request.uri.path contains "/vendor/") or 
(http.request.uri.path contains "/storage/logs/") or 
(http.request.uri.path contains "composer.") or 
(http.request.uri.path eq "/phpunit.xml")
```

**Mengapa:** Attacker menggunakan tool otomatis (scanner) untuk mencari file `.env` (berisi target password database) atau folder `/vendor/` (mencari celah aplikasi eksternal). Memblokir jalur ini di level jaringan (Edge) memastikan request berbahaya langsung di-drop tanpa menyentuh dan membebani server Nginx VPS kita.

#### Rule 2: Auth Challenge (Mitigasi Bot)

Mencegah bot otomatis menebak password, namun mengizinkan login pengguna asli dengan transparan.

| Field | Value |
|-------|-------|
| **Rule name** | `Auth Challenge` |
| **Expression** | *(lihat di bawah)* |
| **Action** | Managed Challenge |

Gunakan sintaks berikut pada Expression Builder:
```text
(http.request.uri.path contains "/login") or 
(http.request.uri.path contains "/password")
```

**Mengapa:** Serangan credential stuffing dan dictionary attack masuk via form autentikasi. Dengan status "Managed Challenge", Cloudflare akan menyajikan *Turnstile* (pengganti CAPTCHA) yang tidak kasat mata bagi browser perawat/dokter, tetapi langsung mencekik eksekusi script Python/cURL jahat.

#### Rule 3: Geo-Blocking (Opsional tapi Kuat)

Memfilter akses geografis sembari mempertahankan kelonggaran sistem bagi tenaga kesehatan yang sedang bertugas di luar negeri.

| Field | Value |
|-------|-------|
| **Rule name** | `Geo-Protect Non-ID` |
| **Expression** | *(lihat di bawah)* |
| **Action** | Managed Challenge |

Gunakan sintaks berikut pada Expression Builder:
```text
(not ip.geoip.country eq "ID")
```

**Mengapa:** Sistem internal Rumah Sakit sewajarnya diakses dari dalam negeri. Meski demikian, kita tidak menggunakan "Block" melainkan "Managed Challenge". Ini berarti traffic dari China atau Rusia (mayoritas sumber botnet) akan tertahan *Turnstile*, namun jika Direktur RS sedang dinas ke Singapura dan ingin login, beliau tetap bisa masuk usai melewati verifikasi browser instan.

---

## 7. Rate Limiting

### Eksekusi — Rate Limiting Rule (Pencegahan Brute-Force Spesifik)

Akun Free Tier memberikan kuota komplementer berupa 1 (satu) Rate Limiting Rule. Kita mendedikasikan rule langka ini khusus mengamankan titik terlemah sistem: formulir Login.

```
Navigasi: Cloudflare Dashboard > Domain Anda > Security > WAF > Rate limiting rules
```

Klik **Create rule** dan atur konfigurasi murni berikut:

| Setting | Value |
|---------|-------|
| **Rule name** | `Login Brute-Force Prevention` |
| **If incoming requests match** | `URI Path` `contains` `/login` |
| **Rate** | `10` requests per `1 minute` |
| **Counting expression** | Same as rule expression |
| **Mitigation timeout** | `1 hour` |
| **Action** | Block |
| **Response code** | 429 |

**Konteks Keamanan:**
Andaikata peretas handal mampu mengakali "Managed Challenge" pada WAF Rule 2, mereka akan menggunakan software untuk meng-inject 10.000 kombinasi password. Kuota 10 percobaan per 1 menit ini sangat memadai bagi pengguna manula yang salah ketik password, namun sangat melumpuhkan eksekusi *brute-force*. Pelanggar aturan ini seketika dikerangkeng (Blocked) selama 1 jam penuh.

---

## 8. Bot Fight Mode & Security Level

### Eksekusi — Dashboard Cloudflare

```
Navigasi: Cloudflare Dashboard > Domain Anda > Security > Bots
```

| Setting | Value | Alasan |
|---------|-------|--------|
| **Bot Fight Mode** | ON | Menantang bot otomatis dengan JavaScript challenge |

```
Navigasi: Cloudflare Dashboard > Domain Anda > Security > Settings
```

| Setting | Value | Alasan |
|---------|-------|--------|
| **Security Level** | High | Menantang visitor dari IP dengan reputasi buruk di database Cloudflare |
| **Challenge Passage** | 30 minutes | Durasi setelah melewati challenge, visitor tidak perlu challenge lagi |
| **Browser Integrity Check** | ON | Mendeteksi header HTTP yang tidak konsisten (biasa dari bot) |

---

## 9. Page Rules & Caching Strategy

### Mengapa Caching Strategy Penting untuk Sistem Medis?

**Versi Formal:** Halaman yang menampilkan data medis pasien (PHI) tidak boleh di-cache di edge server Cloudflare karena data tersebut bersifat per-user dan sensitif. Hanya static assets (CSS, JS, gambar) yang boleh di-cache. Salah konfigurasi caching bisa menyebabkan data pasien A terlihat oleh pasien B (*cache poisoning*).

**Versi Sederhana:** Cloudflare tidak boleh menyimpan salinan halaman yang berisi data pasien. Seperti resepsionis yang tidak boleh menyimpan fotokopi rekam medis pasien — harus selalu minta asli langsung dari dokter (origin server).

### Eksekusi — Konfigurasi Caching

```
Navigasi: Cloudflare Dashboard > Domain Anda > Caching > Configuration
```

| Setting | Value | Alasan |
|---------|-------|--------|
| **Caching Level** | Standard | Caching berdasarkan query string |
| **Browser Cache TTL** | Respect Existing Headers | Nginx sudah mengatur cache header per tipe file |

### Eksekusi — Cache Rules

```
Navigasi: Cloudflare Dashboard > Domain Anda > Caching > Cache Rules
```

#### Rule 1: Bypass Cache untuk Halaman Dinamis

| Setting | Value |
|---------|-------|
| **Rule name** | `Bypass Dynamic Content` |
| **When** | `Cookie contains laravel_session` |
| **Cache eligibility** | Bypass cache |

**Mengapa:** Jika request mengandung cookie `laravel_session`, artinya user sudah login dan halaman berisi data personalized/sensitif. Halaman ini TIDAK BOLEH di-cache di edge.

#### Rule 2: Cache Static Assets Agresif

| Setting | Value |
|---------|-------|
| **Rule name** | `Cache Static Assets` |
| **When** | `URI Path matches /build/* OR URI Path matches /images/* OR URI Path matches /favicon.ico` |
| **Cache eligibility** | Eligible for cache |
| **Edge TTL** | 1 month |
| **Browser TTL** | 1 year |

---

## 10. Verifikasi Edge Security

Setelah semua konfigurasi selesai, jalankan verifikasi berikut:

### Verifikasi DNS Proxy Status

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
# Opsional: Jika command 'dig' tidak ditemukan, install paket DNS Utils terlebih dahulu:
# sudo apt install bind9-dnsutils -y

# Cek resolusi DNS dari komputer lokal Anda
dig +short mers-rsryacudu.com

# Alternatif tanpa install paket, Anda juga bisa menggunakan:
# getent hosts mers-rsryacudu.com

# Output HARUS BUKAN IP VPS Anda.
# Harus menampilkan 2-3 IP milik Cloudflare (contoh: 104.21.x.x, 172.67.x.x)
```

### Verifikasi SSL

```bash
# Paksa koneksi ke port 443 dan lihat subject/issuer sertifikat
echo | openssl s_client -connect mers-rsryacudu.com:443 -servername mers-rsryacudu.com 2>/dev/null | openssl x509 -noout -issuer -subject

# Output yang diharapkan:
# issuer=C = US, O = Google Trust Services LLC... ATAU O = Let's Encrypt... (Sertifikat edge Cloudflare)
# subject=CN = mers-rsryacudu.com
```
```bash
# Periksa response headers
curl -sI https://mers-rsryacudu.com | grep -iE '(strict-transport|x-frame|x-content|cf-ray|server)'

# Output yang diharapkan:
# strict-transport-security: max-age=...
# x-frame-options: SAMEORIGIN
# x-content-type-options: nosniff
# cf-ray: xxxxx-xxx (menandakan traffic lewat Cloudflare)
# server: cloudflare
```

> [!NOTE]
> **Penting**: Jika output Anda pada tahap ini belum menampilkan `strict-transport-security` atau `x-content-type-options: nosniff`, ini **SANGAT WAJAR (Bukan Masalah)**.
> 
> Saat ini kita baru mengkonfigurasi pertahanan lapis terluar (Cloudflare). Header keamanan tambahan yang lebih rapat dan ketat akan diinjeksikan secara *hardcode* oleh Nginx dari dalam VPS Anda pada pelaksanaaan **Dokumen 04 (Nginx WAF & Laravel)**. Selama `server: cloudflare` sudah muncul, Cloudflare Anda sudah bekerja!

### Verifikasi WAF Custom Rules

```bash
# Mencoba akses file .env (Harus mendapat 403 Forbidden)
curl -sI https://mers-rsryacudu.com/.env

# Mencoba akses .git config (Harus mendapat 403 Forbidden)
curl -sI https://mers-rsryacudu.com/.git/config

# Mencoba akses endpoint sensitif yang diblokir (Harus mendapat 403 Forbidden)
curl -sI https://mers-rsryacudu.com/vendor/
```

### Test 5: Direct IP Access Should Fail

```bash
# Test akses langsung ke IP VPS (harus gagal karena UFW hanya izinkan IP Cloudflare)
curl -sk --connect-timeout 5 https://<IP_VPS_ANDA>
# Output: Connection timed out (UFW memblokir)
```

### Checklist Verifikasi

| # | Item | Status yang Diharapkan |
|---|------|----------------------|
| 1 | DNS resolves ke IP Cloudflare | IP bukan milik VPS |
| 2 | SSL mode Full (Strict) | Aktif di dashboard |
| 3 | Origin Certificate terpasang | File ada di `/etc/ssl/cloudflare/` |
| 4 | Always Use HTTPS | ON |
| 5 | HSTS | Enabled, max-age 6 bulan |
| 6 | Minimum TLS | 1.2 |
| 7 | Custom WAF Rules | 3 rules aktif (Shield, Auth, Geo) |
| 8 | Rate Limiting Login | 10 req/min (1 rule aktif) |
| 9 | Bot Fight Mode | ON |
| 10 | Security Level | High |
| 11 | Direct IP access | Blocked/timeout |

---

> **Dokumen selanjutnya:** [03-docker-production-staging.md](./03-docker-production-staging.md) — Struktur folder server, Docker Compose production & staging, network isolation, dan pengamanan port dari jebakan UFW.
