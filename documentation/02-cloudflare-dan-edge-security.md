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

### Mengapa WAF Custom Rules?

**Versi Formal:** WAF managed rules (OWASP CRS) memberikan perlindungan generik terhadap serangan umum. Namun untuk sistem medis yang menangani PHI (Protected Health Information), diperlukan custom rules tambahan yang menyesuaikan pola traffic spesifik aplikasi — seperti melindungi endpoint login, API, dan halaman yang menampilkan data sensitif.

**Versi Sederhana:** Managed rules seperti detektor logam di bandara — menangkap senjata umum. Custom rules seperti daftar VIP dan daftar hitam khusus rumah sakit — menolak orang tertentu dan memberikan perlakuan khusus untuk tamu tertentu.

### Eksekusi — Aktifkan Managed Rulesets

```
Navigasi: Cloudflare Dashboard > Domain Anda > Security > WAF > Managed rules
```

Aktifkan ruleset berikut:

| Ruleset | Status | Alasan |
|---------|--------|--------|
| **Cloudflare Managed Ruleset** | ON | Perlindungan dasar terhadap OWASP Top 10 |
| **Cloudflare OWASP Core Rule Set** | ON | Deteksi SQL injection, XSS, RFI, LFI |
| **Cloudflare Exposed Credentials Check** | ON | Deteksi login menggunakan credential yang bocor |

### Eksekusi — Buat Custom WAF Rules

```
Navigasi: Cloudflare Dashboard > Domain Anda > Security > WAF > Custom rules
```

#### Rule 1: Blokir Akses Langsung ke File Sensitif

Mencegah akses ke file konfigurasi, log, dan backup database.

| Field | Value |
|-------|-------|
| **Rule name** | `Block Sensitive Files` |
| **Expression** | *(lihat di bawah)* |
| **Action** | Block |

```
(http.request.uri.path contains ".env") or
(http.request.uri.path contains ".sql") or
(http.request.uri.path contains ".log") or
(http.request.uri.path contains ".bak") or
(http.request.uri.path contains ".git") or
(http.request.uri.path contains "storage/") or
(http.request.uri.path contains "bootstrap/") or
(http.request.uri.path contains "artisan") or
(http.request.uri.path contains "composer") or
(http.request.uri.path contains "phpinfo") or
(http.request.uri.path contains "adminer") or
(http.request.uri.path contains "phpmyadmin") or
(http.request.uri.path contains "wp-admin") or
(http.request.uri.path contains "wp-login")
```

**Mengapa:** Attacker rutin melakukan automated scanning untuk mencari file `.env` (berisi kredensial), `.sql` (dump database), dan panel admin seperti phpMyAdmin. Rule ini memblokir semua request tersebut di edge bahkan sebelum mencapai server.

#### Rule 2: Rate Limit pada Endpoint Login

Membatasi jumlah percobaan login berlebihan.

| Field | Value |
|-------|-------|
| **Rule name** | `Rate Limit Login` |
| **Expression** | *(lihat di bawah)* |
| **Action** | Block (dengan response code 429) |

```
(http.request.uri.path eq "/login" and http.request.method eq "POST")
```

> [!NOTE]
> Rate limiting with counting diatur terpisah di menu **Security > WAF > Rate limiting rules**. Buat rule dengan: 10 requests per 1 menit per IP pada path `/login` dengan method POST.

#### Rule 3: Blokir User-Agent Mencurigakan

Memblokir bot, scanner, dan tools hacking yang teridentifikasi.

| Field | Value |
|-------|-------|
| **Rule name** | `Block Malicious User-Agents` |
| **Expression** | *(lihat di bawah)* |
| **Action** | Block |

```
(http.user_agent contains "sqlmap") or
(http.user_agent contains "nikto") or
(http.user_agent contains "nmap") or
(http.user_agent contains "masscan") or
(http.user_agent contains "dirbuster") or
(http.user_agent contains "gobuster") or
(http.user_agent contains "wpscan") or
(http.user_agent contains "nuclei") or
(http.user_agent contains "zgrab") or
(http.user_agent contains "python-requests" and not http.request.uri.path contains "/api/")
```

**Mengapa:** Tools seperti `sqlmap` (SQL injection), `nikto` (vulnerability scanner), dan `nmap` (port scanner) secara jujur mengidentifikasi diri mereka di header User-Agent. Memblokir mereka di edge lebih efisien daripada menunggu sampai request mencapai server.

#### Rule 4: Geo-Blocking (Opsional tapi Direkomendasikan)

Jika sistem MER hanya diakses dari Indonesia:

| Field | Value |
|-------|-------|
| **Rule name** | `Allow Indonesia Only` |
| **Expression** | *(lihat di bawah)* |
| **Action** | Block |

```
(not ip.geoip.country eq "ID")
```

**Mengapa:** Sistem pelaporan insiden medis rumah sakit biasanya hanya diakses oleh staf di Indonesia. Memblokir traffic dari negara lain mengeliminasi sebagian besar automated attacks yang berasal dari luar negeri.

> [!WARNING]
> Aktifkan geo-blocking HANYA jika Anda yakin tidak ada staf atau vendor yang mengakses dari luar Indonesia. Jika ada kebutuhan akses internasional, pertimbangkan untuk menggunakan Cloudflare Access (Zero Trust) sebagai pengganti geo-blocking.

---

## 7. Rate Limiting

### Eksekusi — Rate Limiting Rules

```
Navigasi: Cloudflare Dashboard > Domain Anda > Security > WAF > Rate limiting rules
```

#### Rule: Login Brute Force Protection

| Setting | Value |
|---------|-------|
| **Rule name** | `Login Brute Force Protection` |
| **If incoming requests match** | `URI Path equals /login AND Request Method equals POST` |
| **Rate** | 10 requests per 1 minute |
| **Counting expression** | Same as rule expression |
| **Mitigation timeout** | 600 seconds (10 menit) |
| **Action** | Block |
| **Response code** | 429 |

#### Rule: API Rate Limiting

| Setting | Value |
|---------|-------|
| **Rule name** | `API Rate Limit` |
| **If incoming requests match** | `URI Path starts with /api/` |
| **Rate** | 60 requests per 1 minute |
| **Counting expression** | Same as rule expression |
| **Mitigation timeout** | 60 seconds |
| **Action** | Block |
| **Response code** | 429 |

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

### Test 1: DNS Resolution

> **Konteks Eksekusi:** `Terminal Komputer Lokal`

```bash
### Verifikasi DNS Proxy Status

```bash
# Cek resolusi DNS dari komputer lokal Anda
dig +short mers-rsryacudu.com

# Output HARUS BUKAN IP VPS Anda.
# Harus menampilkan 2-3 IP milik Cloudflare (contoh: 104.21.x.x, 172.67.x.x)
```
```

### Verifikasi SSL

```bash
# Paksa koneksi ke port 443 dan lihat subject/issuer sertifikat
echo | openssl s_client -connect mers-rsryacudu.com:443 -servername mers-rsryacudu.com 2>/dev/null | openssl x509 -noout -issuer -subject

# Output yang diharapkan:
# issuer=C = US, O = Google Trust Services LLC, CN = GTS CA 1P5 (Sertifikat edge Cloudflare)
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

### Verifikasi WAF Custom Rules

```bash
# Mencoba akses file .env (Harus mendapat 403 Forbidden)
curl -sI https://mers-rsryacudu.com/.env

# Mencoba akses .git config (Harus mendapat 403 Forbidden)
curl -sI https://mers-rsryacudu.com/.git/config

# Mencoba akses endpoint sensitif yang diblokir (Harus mendapat 403 Forbidden)
curl -sI https://mers-rsryacudu.com/phpmyadmin
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
| 7 | WAF Managed Rules | ON (3 ruleset) |
| 8 | Custom WAF Rules | 3-4 rules aktif |
| 9 | Rate Limiting Login | 10 req/min |
| 10 | Bot Fight Mode | ON |
| 11 | Security Level | High |
| 12 | Direct IP access | Blocked/timeout |

---

> **Dokumen selanjutnya:** [03-docker-production-staging.md](./03-docker-production-staging.md) — Struktur folder server, Docker Compose production & staging, network isolation, dan pengamanan port dari jebakan UFW.
