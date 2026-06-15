<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Peran;
use App\Models\UnitKerja;
use Exception;

class ProductionPenggunaSeeder extends Seeder
{
    private const SPREADSHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Jalankan database seeds.
     */
    public function run(): void
    {
        // Jalur file data di dalam container Docker Staging.
        // File boleh berupa CSV koma/titik-koma/tab atau XLSX walaupun ekstensinya .csv.
        $filePath = database_path('seeders/data/pengguna-staging.csv');

        if (!file_exists($filePath)) {
            $this->command->error("❌ File CSV tidak ditemukan di: {$filePath}");
            return;
        }

        // Mengambil tenant_id default dari organisasi yang sudah di-seed sebelumnya
        $tenantId = DB::table('tenant.organisasi')->value('id');
        if (!$tenantId) {
            $this->command->error("❌ Tenant ID tidak ditemukan. Jalankan OrganisasiSeeder terlebih dahulu.");
            return;
        }

        $baris = 0;
        $berhasil = 0;
        $dilewati = 0;

        try {
            $rows = $this->readRows($filePath);

            $jumlahBarisPengguna = count(array_filter(
                array_slice($rows, 1),
                fn ($row) => isset($row[0]) && trim((string) $row[0]) !== '' && trim((string) $row[0]) !== '-'
            ));

            if ($jumlahBarisPengguna === 0) {
                $this->command->error('❌ File data tidak berisi baris pengguna yang bisa diimport. Database tidak dibersihkan.');
                return;
            }

            DB::beginTransaction();

            $this->command->info('🧹 Membersihkan database staging sebelum import...');

            // Membersihkan tabel pengguna secara aman dengan CASCADE di PostgreSQL.
            DB::statement('TRUNCATE TABLE akun.pengguna RESTART IDENTITY CASCADE');

            foreach ($rows as $data) {
                $baris++;

                if ($baris <= 1) {
                    continue;
                }

                // Indeks mengikuti tabel: nama_lengkap, username, unit_kerja, peran, kata_sandi.
                $namaLengkap = isset($data[0]) ? trim($data[0]) : ''; // Kolom A
                $usernameRaw = isset($data[1]) ? trim($data[1]) : ''; // Kolom B
                $unitKerja   = isset($data[2]) ? trim($data[2]) : ''; // Kolom C
                $peran       = isset($data[3]) ? trim($data[3]) : ''; // Kolom D
                $kataSandi   = isset($data[4]) ? trim($data[4]) : ''; // Kolom E

                // Jika nama lengkap kosong atau berisi baris rusak, abaikan
                if (empty($namaLengkap) || $namaLengkap === '-' || trim($namaLengkap) === '') {
                    $dilewati++;
                    continue;
                }

                // Logika pembuatan Username otomatis jika kolom B kosong (seperti data Ruang VK)
                if (empty($usernameRaw) || $usernameRaw === '-' || trim($usernameRaw) === '') {
                    // Mengubah "Tania Cantika, A. Md. Keb" menjadi "tania.cantika"
                    $cleanName = preg_replace('/[^a-zA-Z\s]/', '', $namaLengkap);
                    $username = Str::slug(trim($cleanName), '.');
                } else {
                    $username = strtolower(trim($usernameRaw));
                }

                $username = $this->makeUniqueUsername($username);
                $nomorInduk = $this->makeUniqueNomorInduk($tenantId, $username);

                // Kata sandi dari kolom E jika ada, fallback ke password.
                $passwordHashed = Hash::make($kataSandi !== '' && $kataSandi !== '-' ? $kataSandi : 'password');

                // Kueri Relasi Peran
                $peranId = null;
                if (!empty($peran) && $peran !== '-') {
                    $peranModel = Peran::where('tenant_id', $tenantId)
                        ->where(DB::raw('lower(nama_peran)'), strtolower($peran))
                        ->first();

                    if (!$peranModel) {
                        $peranModel = Peran::create([
                            'tenant_id' => $tenantId,
                            'nama_peran' => $peran,
                        ]);
                    }
                    $peranId = $peranModel->id;
                }

                // Kueri Relasi Unit Kerja
                $masterUnitKerjaId = null;
                if (!empty($unitKerja) && $unitKerja !== '-') {
                    $unitModel = UnitKerja::where('tenant_id', $tenantId)
                        ->where(DB::raw('lower(nama_unit)'), strtolower($unitKerja))
                        ->first();

                    if (!$unitModel) {
                        $unitModel = UnitKerja::create([
                            'tenant_id' => $tenantId,
                            'kode_unit' => strtoupper(Str::slug($unitKerja, '_')),
                            'nama_unit' => $unitKerja
                        ]);
                    }
                    $masterUnitKerjaId = $unitModel->id;
                }

                // Suntik data ke database PostgreSQL
                $penggunaId = DB::table('akun.pengguna')->insertGetId([
                    'tenant_id'               => $tenantId,
                    'unit_id'                 => $masterUnitKerjaId,
                    'nomor_induk'             => $nomorInduk,
                    'username'                => $username,
                    'email'                   => null,
                    'nomor_hp'                => null,
                    'alamat'                  => '',
                    'nama_lengkap'            => $namaLengkap,
                    'kata_sandi'              => $passwordHashed,
                    'wajib_ganti_sandi'       => true,
                    'is_aktif'                => true,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);

                if ($peranId) {
                    DB::table('akun.pengguna_peran')->insert([
                        'pengguna_id' => $penggunaId,
                        'peran_id' => $peranId,
                    ]);
                }

                $berhasil++;
            }

            DB::commit();
            $this->command->info("🎉 Sukses! Database dibersihkan dan {$berhasil} pengguna baru berhasil dikonversi. {$dilewati} baris dilewati.");

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->command->error("❌ Terjadi kesalahan fatal pada baris ke-{$baris}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(string $filePath): array
    {
        return $this->isXlsx($filePath)
            ? $this->readXlsxRows($filePath)
            : $this->readCsvRows($filePath);
    }

    private function isXlsx(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        $signature = $handle ? fread($handle, 4) : '';

        if ($handle) {
            fclose($handle);
        }

        return $signature === "PK\x03\x04";
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("File CSV tidak bisa dibuka: {$filePath}");
        }

        $rows = [];
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return $rows;
        }

        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        while (($data = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
            if ($data === [null]) {
                continue;
            }

            if (isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]);
            }

            $rows[] = array_map(
                fn ($value) => trim((string) $value),
                $data
            );
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [",", ";", "\t"];
        $bestDelimiter = ",";
        $bestCount = 0;

        foreach ($candidates as $candidate) {
            $count = count(str_getcsv($line, $candidate));
            if ($count > $bestCount) {
                $bestDelimiter = $candidate;
                $bestCount = $count;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Membaca XLSX sederhana tanpa package tambahan. Ini cukup untuk worksheet tabel datar.
     *
     * @return array<int, array<int, string>>
     */
    private function readXlsxRows(string $filePath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new Exception('File terdeteksi sebagai XLSX, tetapi ekstensi PHP ZipArchive belum aktif. Simpan ulang file sebagai CSV UTF-8 atau aktifkan ekstensi zip PHP.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("File XLSX tidak bisa dibuka: {$filePath}");
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $worksheetPath = $this->firstWorksheetPath($zip);
        $worksheetXml = $zip->getFromName($worksheetPath);
        $zip->close();

        if ($worksheetXml === false) {
            throw new Exception("Worksheet pertama tidak ditemukan di file XLSX: {$worksheetPath}");
        }

        $sheet = simplexml_load_string($worksheetXml);
        if (!$sheet) {
            throw new Exception('Worksheet XLSX tidak bisa dibaca.');
        }

        $rows = [];

        $sheetData = $sheet->children(self::SPREADSHEET_NS)->sheetData;

        foreach ($sheetData->children(self::SPREADSHEET_NS)->row as $row) {
            $values = [];

            foreach ($row->children(self::SPREADSHEET_NS)->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndexFromCellReference($reference);
                $values[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            if ($values !== []) {
                ksort($values);
                $max = max(array_keys($values));
                $rows[] = array_map(
                    fn ($index) => trim($values[$index] ?? ''),
                    range(0, $max)
                );
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $sharedStrings = [];
        $document = simplexml_load_string($xml);
        if (!$document) {
            return [];
        }

        foreach ($document->children(self::SPREADSHEET_NS)->si as $stringItem) {
            $stringChildren = $stringItem->children(self::SPREADSHEET_NS);

            if (isset($stringChildren->t)) {
                $sharedStrings[] = (string) $stringChildren->t;
                continue;
            }

            $text = '';
            foreach ($stringChildren->r as $run) {
                $text .= (string) $run->children(self::SPREADSHEET_NS)->t;
            }
            $sharedStrings[] = $text;
        }

        return $sharedStrings;
    }

    private function firstWorksheetPath(\ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if (!$workbook || !$rels) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $workbookChildren = $workbook->children(self::SPREADSHEET_NS);
        $sheet = $workbookChildren->sheets->children(self::SPREADSHEET_NS)->sheet[0] ?? null;
        $relationshipId = $sheet ? (string) $sheet->attributes('r', true)->id : '';

        if ($relationshipId === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        foreach ($rels->children(self::RELATIONSHIPS_NS)->Relationship as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                $target = (string) $relationship['Target'];

                return str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/' . ltrim($target, '/');
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];
        $cellChildren = $cell->children(self::SPREADSHEET_NS);

        if ($type === 's') {
            $index = (int) $cellChildren->v;

            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            $inlineString = $cellChildren->is;
            $inlineChildren = $inlineString->children(self::SPREADSHEET_NS);

            if (isset($inlineChildren->t)) {
                return (string) $inlineChildren->t;
            }

            $text = '';
            foreach ($inlineChildren->r as $run) {
                $text .= (string) $run->children(self::SPREADSHEET_NS)->t;
            }

            return $text;
        }

        return isset($cellChildren->v) ? (string) $cellChildren->v : '';
    }

    private function columnIndexFromCellReference(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function makeUniqueUsername(string $username): string
    {
        $base = Str::limit($username !== '' ? $username : 'pengguna', 45, '');
        $candidate = $base;
        $counter = 2;

        while (DB::table('akun.pengguna')->where('username', $candidate)->exists()) {
            $suffix = '.' . $counter;
            $candidate = Str::limit($base, 50 - strlen($suffix), '') . $suffix;
            $counter++;
        }

        return $candidate;
    }

    private function makeUniqueNomorInduk(int $tenantId, string $username): string
    {
        $base = Str::limit($username !== '' ? $username : 'pengguna', 95, '');
        $candidate = $base;
        $counter = 2;

        while (
            DB::table('akun.pengguna')
                ->where('tenant_id', $tenantId)
                ->where('nomor_induk', $candidate)
                ->exists()
        ) {
            $suffix = '-' . $counter;
            $candidate = Str::limit($base, 100 - strlen($suffix), '') . $suffix;
            $counter++;
        }

        return $candidate;
    }
}
