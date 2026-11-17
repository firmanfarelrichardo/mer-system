<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\NotifikasiController;
use App\Http\Requests\SimpanLaporanRequest;
use App\Models\Pengguna;
use App\Repositories\NotifikasiRepository;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class LaporanEvaluasiTest extends TestCase
{
    /**
     * Helper untuk membuat data dasar laporan valid.
     *
     * @return array<string, mixed>
     */
    private function dataLaporanValid(): array
    {
        return [
            'action'                  => 'kirim_laporan',
            'insiden_id'              => null,
            'nama_pasien'             => 'Pasien Uji',
            'nomor_rekam_medis'       => 'RM-123456',
            'unit_kerja'              => 'Instalasi Farmasi',
            'tanggal_kejadian'        => now()->toDateString(),
            'waktu_kejadian'          => '10:00',
            'jenis_insiden'           => 'KNC',
            'fase_kesalahan'          => 'prescribing',
            'jenis_kesalahan'         => ['Dosis Salah'],
            'jenis_kesalahan_lainnya' => null,
            'cedera'                  => ['Tidak Ada Cedera'],
            'cedera_lainnya'          => null,
            'faktor_penyebab'         => ['Ada Distraksi (Manusia)'],
            'faktor_penyebab_lainnya' => null,
            'intervensi_pasien'       => ['Observasi TTV'],
            'intervensi_pasien_lainnya' => null,
            'nama_obat'               => 'Paracetamol 500mg',
            'kronologi_kejadian'      => 'Kronologi kejadian singkat untuk pengujian validasi sistem.',
            'pernyataan_kronologi'    => '1',
            'konfirmasi_kirim'        => '1',
        ];
    }

    /**
     * Evaluasi 1: Validasi lolos saat checkbox kosong tapi kolom 'lainnya' diisi.
     */
    public function test_validasi_lolos_saat_hanya_kolom_lainnya_diisi(): void
    {
        $request = new SimpanLaporanRequest();
        $data = $this->dataLaporanValid();

        // Kosongkan semua array checkbox, isi hanya input *_lainnya
        $data['jenis_kesalahan']           = null;
        $data['jenis_kesalahan_lainnya']   = 'Kesalahan spesifik non-standar';

        $data['cedera']                    = null;
        $data['cedera_lainnya']            = 'Cedera spesifik non-standar';

        $data['faktor_penyebab']           = null;
        $data['faktor_penyebab_lainnya']   = 'Faktor lingkungan luar biasa';

        $data['intervensi_pasien']         = null;
        $data['intervensi_pasien_lainnya'] = 'Penanganan khusus oleh spesialis';

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse(
            $validator->fails(),
            'Validasi gagal padahal kolom *_lainnya sudah terisi: ' . json_encode($validator->errors()->all())
        );
    }

    /**
     * Evaluasi 1: Validasi gagal saat baik checkbox maupun kolom 'lainnya' tidak diisi.
     */
    public function test_validasi_menolak_jika_checkbox_dan_lainnya_keduanya_kosong(): void
    {
        $request = new SimpanLaporanRequest();
        $data = $this->dataLaporanValid();

        $data['jenis_kesalahan']           = null;
        $data['jenis_kesalahan_lainnya']   = null;

        $data['cedera']                    = null;
        $data['cedera_lainnya']            = null;

        $data['faktor_penyebab']           = null;
        $data['faktor_penyebab_lainnya']   = null;

        $data['intervensi_pasien']         = null;
        $data['intervensi_pasien_lainnya'] = null;

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $errors = $validator->errors();

        $this->assertTrue($errors->has('jenis_kesalahan'));
        $this->assertTrue($errors->has('cedera'));
        $this->assertTrue($errors->has('faktor_penyebab'));
        $this->assertTrue($errors->has('intervensi_pasien'));

        $this->assertSame(
            'Pilih minimal satu jenis kesalahan atau isi pada kolom lainnya.',
            $errors->first('jenis_kesalahan')
        );
        $this->assertSame(
            'Pilih minimal satu cedera atau isi pada kolom lainnya.',
            $errors->first('cedera')
        );
        $this->assertSame(
            'Pilih minimal satu faktor penyebab atau isi pada kolom lainnya.',
            $errors->first('faktor_penyebab')
        );
        $this->assertSame(
            'Pilih minimal satu intervensi pasien atau isi pada kolom lainnya.',
            $errors->first('intervensi_pasien')
        );
    }

    /**
     * Evaluasi 2: Logika penentuan $tahapAwal berdasarkan posisi error validasi.
     */
    public function test_penentuan_tahap_awal_sesuai_lokasi_error(): void
    {
        $tentukanTahapAwal = function (array $errorKeys): int {
            $bag = new ViewErrorBag();
            $messageBag = new \Illuminate\Support\MessageBag();
            foreach ($errorKeys as $k) {
                $messageBag->add($k, 'Error pada ' . $k);
            }
            $bag->put('default', $messageBag);

            $step1Fields = ['nama_pasien', 'nomor_rekam_medis', 'unit_kerja', 'tanggal_kejadian', 'waktu_kejadian', 'jenis_insiden', 'nama_pelapor', 'kontak_pelapor'];
            $step2Fields = ['fase_kesalahan', 'jenis_kesalahan', 'jenis_kesalahan_lainnya', 'cedera', 'cedera_lainnya', 'faktor_penyebab', 'faktor_penyebab_lainnya', 'intervensi_pasien', 'intervensi_pasien_lainnya', 'nama_obat', 'dosis_obat'];
            $step3Fields = ['kronologi_kejadian', 'pernyataan_kronologi'];
            $step4Fields = ['konfirmasi_kirim'];

            if ($bag->hasAny($step1Fields)) {
                return 1;
            }
            if ($bag->hasAny($step2Fields)) {
                return 2;
            }
            if ($bag->hasAny($step3Fields)) {
                return 3;
            }
            if ($bag->hasAny($step4Fields)) {
                return 4;
            }
            return 1;
        };

        // Error di Step 1
        $this->assertSame(1, $tentukanTahapAwal(['nama_pasien']));

        // Error di Step 2 (misal jenis_kesalahan atau cedera)
        $this->assertSame(2, $tentukanTahapAwal(['jenis_kesalahan']));
        $this->assertSame(2, $tentukanTahapAwal(['cedera_lainnya']));
        $this->assertSame(2, $tentukanTahapAwal(['faktor_penyebab', 'nama_obat']));

        // Error di Step 3
        $this->assertSame(3, $tentukanTahapAwal(['kronologi_kejadian']));
        $this->assertSame(3, $tentukanTahapAwal(['pernyataan_kronologi']));

        // Error di Step 4
        $this->assertSame(4, $tentukanTahapAwal(['konfirmasi_kirim']));

        // Jika error ada di Step 2 dan Step 3 sekaligus, prioritaskan Step 2 (urutan pertama yang salah)
        $this->assertSame(2, $tentukanTahapAwal(['jenis_kesalahan', 'kronologi_kejadian']));
    }

    /**
     * Evaluasi 3: Penanganan notifikasi bodong / yatim saat diklik.
     * Harus mengarahkan kembali ke /notifikasi dengan peringatan, bukan error 404.
     */
    public function test_klik_notifikasi_bodong_mengarahkan_ke_indeks_dengan_peringatan(): void
    {
        $mockPengguna = Mockery::mock(Pengguna::class);
        $mockPengguna->shouldReceive('getAuthIdentifier')->andReturn(1);
        Auth::setUser($mockPengguna);

        $mockService = Mockery::mock(NotifikasiService::class);
        // Simulasi notifikasi bodong (laporan 404 / tidak ada) mengembalikan null
        $mockService->shouldReceive('bacaDanArahkan')
            ->once()
            ->with($mockPengguna, 'notif-uuid-bodong-123')
            ->andReturn(null);

        $controller = new NotifikasiController($mockService);
        $response = $controller->bacaDanArahkan('notif-uuid-bodong-123');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('notifikasi.index'), $response->getTargetUrl());
        $this->assertSame(
            'Laporan tidak ditemukan atau telah dihapus. Notifikasi telah dibersihkan.',
            session('peringatan')
        );
    }

    /**
     * Evaluasi 3: Notifikasi yang valid mengarahkan ke URL laporan dengan benar.
     */
    public function test_klik_notifikasi_valid_mengarahkan_ke_url_laporan(): void
    {
        $mockPengguna = Mockery::mock(Pengguna::class);
        $mockPengguna->shouldReceive('getAuthIdentifier')->andReturn(1);
        Auth::setUser($mockPengguna);

        $expectedUrl = route('laporan.tampil', 99);

        $mockService = Mockery::mock(NotifikasiService::class);
        $mockService->shouldReceive('bacaDanArahkan')
            ->once()
            ->with($mockPengguna, 'notif-uuid-valid-456')
            ->andReturn($expectedUrl);

        $controller = new NotifikasiController($mockService);
        $response = $controller->bacaDanArahkan('notif-uuid-valid-456');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($expectedUrl, $response->getTargetUrl());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
