<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pengguna:export-production {--path= : Lokasi file .xls output, relatif dari root project atau absolut}', function (): int {
    $path = $this->option('path') ?: 'storage/app/exports/pengguna-production-'.now()->format('Ymd-His').'.xls';
    $fullPath = Str::startsWith($path, '/') ? $path : base_path($path);

    if (! str_ends_with(strtolower($fullPath), '.xls')) {
        $this->error('Path output harus menggunakan ekstensi .xls');

        return self::FAILURE;
    }

    $directory = dirname($fullPath);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        $this->error("Gagal membuat folder output: {$directory}");

        return self::FAILURE;
    }

    $handle = fopen($fullPath, 'wb');

    if ($handle === false) {
        $this->error("Gagal membuat file output: {$fullPath}");

        return self::FAILURE;
    }

    $writeCell = static function ($handle, mixed $value): void {
        $value = $value === null ? '' : (string) $value;

        fwrite($handle, '<Cell><Data ss:Type="String">'.htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</Data></Cell>');
    };

    $writeRow = static function ($handle, array $values) use ($writeCell): void {
        fwrite($handle, '<Row>');

        foreach ($values as $value) {
            $writeCell($handle, $value);
        }

        fwrite($handle, '</Row>');
    };

    fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>');
    fwrite($handle, '<?mso-application progid="Excel.Sheet"?>');
    fwrite($handle, '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ');
    fwrite($handle, 'xmlns:o="urn:schemas-microsoft-com:office:office" ');
    fwrite($handle, 'xmlns:x="urn:schemas-microsoft-com:office:excel" ');
    fwrite($handle, 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">');
    fwrite($handle, '<Worksheet ss:Name="Pengguna Production"><Table>');

    $writeRow($handle, [
        'ID',
        'Tenant ID',
        'Nomor Induk',
        'Username',
        'Nama Lengkap',
        'Email',
        'Nomor HP',
        'Peran',
        'Kode Unit',
        'Unit Kerja',
        'Jabatan',
        'Aktif',
        'Wajib Ganti Sandi',
        'Terakhir Login',
        'Tanggal Bergabung Unit',
        'Alamat',
        'Dibuat',
        'Diperbarui',
    ]);

    $jumlah = 0;

    DB::table('akun.pengguna as p')
        ->leftJoin('master.unit_kerja as u', 'u.id', '=', 'p.unit_id')
        ->leftJoin('akun.pengguna_peran as pp', 'pp.pengguna_id', '=', 'p.id')
        ->leftJoin('akun.peran as r', 'r.id', '=', 'pp.peran_id')
        ->select([
            'p.id',
            'p.tenant_id',
            'p.nomor_induk',
            'p.username',
            'p.nama_lengkap',
            'p.email',
            'p.nomor_hp',
            'u.kode_unit',
            'u.nama_unit',
            'p.jabatan',
            'p.is_aktif',
            'p.wajib_ganti_sandi',
            'p.terakhir_login_pada',
            'p.tanggal_bergabung_unit',
            'p.alamat',
            'p.created_at',
            'p.updated_at',
            DB::raw("COALESCE(string_agg(r.nama_peran, ', ' ORDER BY r.nama_peran), '') as daftar_peran"),
        ])
        ->groupBy([
            'p.id',
            'p.tenant_id',
            'p.nomor_induk',
            'p.username',
            'p.nama_lengkap',
            'p.email',
            'p.nomor_hp',
            'u.kode_unit',
            'u.nama_unit',
            'p.jabatan',
            'p.is_aktif',
            'p.wajib_ganti_sandi',
            'p.terakhir_login_pada',
            'p.tanggal_bergabung_unit',
            'p.alamat',
            'p.created_at',
            'p.updated_at',
        ])
        ->orderBy('p.id')
        ->chunk(500, function ($penggunaList) use ($handle, $writeRow, &$jumlah): void {
            foreach ($penggunaList as $pengguna) {
                $writeRow($handle, [
                    $pengguna->id,
                    $pengguna->tenant_id,
                    $pengguna->nomor_induk,
                    $pengguna->username,
                    $pengguna->nama_lengkap,
                    $pengguna->email,
                    $pengguna->nomor_hp,
                    $pengguna->daftar_peran,
                    $pengguna->kode_unit,
                    $pengguna->nama_unit,
                    $pengguna->jabatan,
                    $pengguna->is_aktif ? 'Aktif' : 'Nonaktif',
                    $pengguna->wajib_ganti_sandi ? 'Ya' : 'Tidak',
                    $pengguna->terakhir_login_pada,
                    $pengguna->tanggal_bergabung_unit,
                    $pengguna->alamat,
                    $pengguna->created_at,
                    $pengguna->updated_at,
                ]);

                $jumlah++;
            }
        });

    fwrite($handle, '</Table></Worksheet></Workbook>');
    fclose($handle);

    $this->info("Berhasil mengekspor {$jumlah} pengguna.");
    $this->line("File: {$fullPath}");

    return self::SUCCESS;
})->purpose('Ekspor seluruh data pengguna production ke file Excel .xls');

// -----------------------------------------------------------------------------
// Data Management Commands (Used by deploy.sh menu)
// -----------------------------------------------------------------------------

Artisan::command('data:reset-db', function () {
    $this->call('migrate:fresh', ['--seed' => true]);
    $this->info('Database reset and seeded successfully.');
})->purpose('Reset the entire database and run seeders');

Artisan::command('data:reset-laporan', function () {
    DB::statement('TRUNCATE TABLE pelaporan.insiden CASCADE');
    $this->info('Seluruh data laporan (insiden) telah dihapus.');
})->purpose('Mengosongkan seluruh data laporan');

Artisan::command('data:reset-user', function () {
    // Gunakan Eloquent agar menjalankan SoftDeletes dan menghindari Foreign Key Violation
    $admin = App\Models\Pengguna::where('username', 'admin')->first();
    $adminId = $admin ? $admin->id : 0;
    
    $deleted = App\Models\Pengguna::where('id', '!=', $adminId)->delete();
    $this->info("Berhasil menghapus (soft-delete) {$deleted} user (Admin utama dipertahankan).");
})->purpose('Menghapus seluruh user kecuali admin utama');

Artisan::command('data:delete-laporan {nomor}', function ($nomor) {
    // Gunakan Eloquent agar menjalankan SoftDeletes
    $insiden = App\Models\Insiden::where('nomor_laporan', $nomor)->first();
    if ($insiden) {
        $insiden->delete();
        $this->info("Laporan dengan Nomor {$nomor} berhasil dihapus (soft-delete).");
    } else {
        $this->error("Laporan dengan Nomor {$nomor} tidak ditemukan.");
    }
})->purpose('Menghapus satu laporan spesifik berdasarkan Nomor Laporan');

Artisan::command('data:delete-user {username}', function ($username) {
    // Gunakan Eloquent agar menjalankan SoftDeletes
    $user = App\Models\Pengguna::where('username', $username)->first();
    if (!$user) {
        $this->error("User dengan username '{$username}' tidak ditemukan.");
        return;
    }
    if ($user->username === 'admin') {
        $this->error("Akses ditolak: Tidak dapat menghapus admin utama.");
        return;
    }
    
    $user->delete();
    $this->info("User dengan username '{$username}' berhasil dihapus (soft-delete).");
})->purpose('Menghapus satu user spesifik berdasarkan Username');
