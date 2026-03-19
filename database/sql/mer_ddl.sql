-- ============================================================
-- MER SYSTEM - ENTERPRISE DDL (PostgreSQL)
-- Production Ready • Fully Normalized • UUID Hybrid
-- ============================================================

-- ============================================================
-- EXTENSIONS
-- ============================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ============================================================
-- SCHEMAS
-- ============================================================

CREATE SCHEMA IF NOT EXISTS tenant;
CREATE SCHEMA IF NOT EXISTS akun;
CREATE SCHEMA IF NOT EXISTS master;
CREATE SCHEMA IF NOT EXISTS pelaporan;
CREATE SCHEMA IF NOT EXISTS audit;

-- ============================================================
-- TENANT (Single now, Multi-tenant ready)
-- ============================================================

CREATE TABLE tenant.organisasi (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    kode_organisasi VARCHAR(50) NOT NULL UNIQUE,
    nama_organisasi VARCHAR(255) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_tenant_uuid UNIQUE(uuid)
);

-- ============================================================
-- MASTER DATA
-- ============================================================

CREATE TABLE master.unit_kerja (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    kode_unit VARCHAR(50) NOT NULL,
    nama_unit VARCHAR(255) NOT NULL,
    keterangan TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT uq_unit_uuid UNIQUE(uuid),
    CONSTRAINT uq_unit_kode UNIQUE(tenant_id, kode_unit),

    CONSTRAINT fk_unit_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenant.organisasi(id)
);

CREATE TABLE master.kategori_kesalahan (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    nama_kategori VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_kategori_uuid UNIQUE(uuid),

    CONSTRAINT fk_kategori_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenant.organisasi(id)
);

CREATE TABLE master.matriks_dampak (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    tingkat INTEGER NOT NULL,
    deskripsi VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_dampak_uuid UNIQUE(uuid),

    CONSTRAINT fk_dampak_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenant.organisasi(id)
);

CREATE TABLE master.matriks_probabilitas (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    tingkat INTEGER NOT NULL,
    deskripsi VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_prob_uuid UNIQUE(uuid),

    CONSTRAINT fk_prob_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenant.organisasi(id)
);

-- ============================================================
-- AKUN & RBAC
-- ============================================================

CREATE TABLE akun.pengguna (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    unit_id BIGINT,

    nomor_induk VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(255) NOT NULL,

    kata_sandi VARCHAR(255) NOT NULL,

    is_aktif BOOLEAN DEFAULT TRUE,

    terakhir_login_pada TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT uq_pengguna_uuid UNIQUE(uuid),
    CONSTRAINT uq_pengguna_email UNIQUE(tenant_id, email),
    CONSTRAINT uq_pengguna_nomor_induk UNIQUE(tenant_id, nomor_induk),

    CONSTRAINT fk_pengguna_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenant.organisasi(id),

    CONSTRAINT fk_pengguna_unit
        FOREIGN KEY (unit_id)
        REFERENCES master.unit_kerja(id)
);

CREATE TABLE akun.peran (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    nama_peran VARCHAR(100) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_peran_uuid UNIQUE(uuid),
    CONSTRAINT uq_peran UNIQUE(tenant_id, nama_peran),

    CONSTRAINT fk_peran_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenant.organisasi(id)
);

CREATE TABLE akun.pengguna_peran (
    pengguna_id BIGINT NOT NULL,
    peran_id BIGINT NOT NULL,

    PRIMARY KEY (pengguna_id, peran_id),

    CONSTRAINT fk_pp_pengguna
        FOREIGN KEY (pengguna_id)
        REFERENCES akun.pengguna(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_pp_peran
        FOREIGN KEY (peran_id)
        REFERENCES akun.peran(id)
        ON DELETE CASCADE
);

CREATE TABLE akun.reset_kata_sandi (
    id BIGSERIAL PRIMARY KEY,

    pengguna_id BIGINT NOT NULL,
    email VARCHAR(255) NOT NULL,

    token_hash VARCHAR(255) NOT NULL,

    ip_request VARCHAR(45),
    user_agent TEXT,

    expired_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reset_pengguna
        FOREIGN KEY (pengguna_id)
        REFERENCES akun.pengguna(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_reset_email 
    ON akun.reset_kata_sandi(email);

CREATE INDEX idx_reset_token_hash 
    ON akun.reset_kata_sandi(token_hash);

CREATE INDEX idx_reset_expired 
    ON akun.reset_kata_sandi(expired_at);



CREATE TABLE akun.sesi (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),

    pengguna_id BIGINT NOT NULL,

    refresh_token_hash VARCHAR(255),

    ip_address VARCHAR(45),
    user_agent TEXT,

    last_activity TIMESTAMP NOT NULL,
    expired_at TIMESTAMP NOT NULL,

    is_revoked BOOLEAN NOT NULL DEFAULT FALSE,
    revoked_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sesi_pengguna
        FOREIGN KEY (pengguna_id)
        REFERENCES akun.pengguna(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_sesi_pengguna 
    ON akun.sesi(pengguna_id);

CREATE INDEX idx_sesi_expired 
    ON akun.sesi(expired_at);

CREATE INDEX idx_sesi_revoked 
    ON akun.sesi(is_revoked);



-- ============================================================
-- PELAPORAN
-- ============================================================

CREATE TABLE pelaporan.insiden (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    nomor_laporan VARCHAR(100) NOT NULL,

    pelapor_id BIGINT,
    unit_id BIGINT NOT NULL,

    tipe_insiden VARCHAR(100) NOT NULL,
    status_saat_ini VARCHAR(50) NOT NULL,

    tgl_kejadian TIMESTAMP NOT NULL,
    tgl_lapor TIMESTAMP NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT uq_insiden_uuid UNIQUE(uuid),
    CONSTRAINT uq_nomor_laporan UNIQUE(tenant_id, nomor_laporan),

    CONSTRAINT fk_insiden_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenant.organisasi(id),

    CONSTRAINT fk_insiden_pelapor
        FOREIGN KEY (pelapor_id)
        REFERENCES akun.pengguna(id),

    CONSTRAINT fk_insiden_unit
        FOREIGN KEY (unit_id)
        REFERENCES master.unit_kerja(id)
);

CREATE TABLE pelaporan.detail_pasien (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    insiden_id BIGINT NOT NULL UNIQUE,

    nama_pasien VARCHAR(255) NOT NULL,
    nomor_rekam_medis VARCHAR(100) NOT NULL,

    obat_terkait VARCHAR(255),
    dokter_penulis_resep VARCHAR(255),

    kronologi TEXT NOT NULL,
    tindakan_awal TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_detail_uuid UNIQUE(uuid),

    CONSTRAINT fk_detail_insiden
        FOREIGN KEY (insiden_id)
        REFERENCES pelaporan.insiden(id)
        ON DELETE CASCADE
);

CREATE TABLE pelaporan.insiden_kategori (
    insiden_id BIGINT NOT NULL,
    kategori_id BIGINT NOT NULL,

    PRIMARY KEY(insiden_id, kategori_id),

    FOREIGN KEY(insiden_id)
        REFERENCES pelaporan.insiden(id)
        ON DELETE CASCADE,

    FOREIGN KEY(kategori_id)
        REFERENCES master.kategori_kesalahan(id)
);

CREATE TABLE pelaporan.penilaian_risiko (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    insiden_id BIGINT NOT NULL,
    penilai_id BIGINT NOT NULL,

    probabilitas_id BIGINT NOT NULL,
    dampak_id BIGINT NOT NULL,

    warna_grading VARCHAR(50) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_penilaian_uuid UNIQUE(uuid),

    FOREIGN KEY(insiden_id)
        REFERENCES pelaporan.insiden(id),

    FOREIGN KEY(penilai_id)
        REFERENCES akun.pengguna(id),

    FOREIGN KEY(probabilitas_id)
        REFERENCES master.matriks_probabilitas(id),

    FOREIGN KEY(dampak_id)
        REFERENCES master.matriks_dampak(id)
);

CREATE TABLE pelaporan.investigasi_rca (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    insiden_id BIGINT NOT NULL,
    investigator_id BIGINT NOT NULL,

    akar_masalah TEXT NOT NULL,
    rekomendasi_sistem TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_rca_uuid UNIQUE(uuid),

    FOREIGN KEY(insiden_id)
        REFERENCES pelaporan.insiden(id),

    FOREIGN KEY(investigator_id)
        REFERENCES akun.pengguna(id)
);

-- ============================================================
-- AUDIT LOG (Enterprise)
-- ============================================================

CREATE TABLE audit.log_aktivitas (

    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),

    tenant_id BIGINT NOT NULL,

    nama_tabel VARCHAR(100) NOT NULL,
    aksi VARCHAR(20) NOT NULL,

    id_data BIGINT,

    id_pengguna BIGINT,

    data_lama JSONB,
    data_baru JSONB,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_audit_uuid UNIQUE(uuid),

    FOREIGN KEY(tenant_id)
        REFERENCES tenant.organisasi(id)
);
