<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\UnitKerja;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller Daftar Kepala Ruangan - pengelolaan assignment Karu per unit.
 *
 * Admin dapat:
 *   - Melihat seluruh unit beserta status Karu-nya
 *   - Menunjuk Nakes sebagai Karu di unit tertentu
 *   - Mencabut peran Karu dari pengguna
 */
class KepalaRuanganController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /* ------------------------------------------------------------------
     | INDEX - Daftar seluruh unit dan status Karu-nya
     | ----------------------------------------------------------------*/

    public function index(): View
    {
        $tenantId = auth()->user()->tenant_id;

        // Ambil semua unit + Karu-nya (eager load via subquery)
        $daftarUnit = UnitKerja::where('tenant_id', $tenantId)
            ->orderBy('nama_unit')
            ->get()
            ->map(function (UnitKerja $unit) {
                $unit->setAttribute('karu', $unit->kepalaRuangan());

                return $unit;
            });

        $jumlahTanpaKaru = $daftarUnit->whereNull('karu')->count();

        return view('admin.kepala-ruangan.index', compact('daftarUnit', 'jumlahTanpaKaru'));
    }

    /* ------------------------------------------------------------------
     | CREATE - Form untuk menunjuk Karu di unit tertentu
     | ----------------------------------------------------------------*/

    public function tunjuk(int $unitKerja): View
    {
        $tenantId = auth()->user()->tenant_id;
        $unit     = UnitKerja::where('tenant_id', $tenantId)->findOrFail($unitKerja);

        // Karu yang saat ini menjabat (null jika belum ada)
        $karuSaatIni = $unit->kepalaRuangan();

        // Hanya Nakes aktif yang terdaftar di unit ini dan belum menjadi Karu
        $daftarNakes = Pengguna::where('tenant_id', $tenantId)
            ->where('unit_id', $unit->id)
            ->where('is_aktif', true)
            ->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::NAKES))
            ->whereDoesntHave('peran', fn ($q) => $q->where('nama_peran', Peran::KEPALA_RUANGAN))
            ->orderBy('nama_lengkap')
            ->get();

        // Data yang di-serialize ke JavaScript (hindari multi-line @json di Blade)
        $nakesUntukJs = $daftarNakes->map(fn (Pengguna $n) => [
            'id'   => $n->id,
            'nama' => $n->nama_lengkap,
            'nip'  => $n->nomor_induk ?? '',
        ])->values()->all();

        return view('admin.kepala-ruangan.tunjuk', compact('unit', 'daftarNakes', 'karuSaatIni', 'nakesUntukJs'));
    }

    /* ------------------------------------------------------------------
     | STORE - Proses penunjukan Karu
     | ----------------------------------------------------------------*/

    public function simpanPenunjukan(Request $request, int $unitKerja): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $unit     = UnitKerja::where('tenant_id', $tenantId)->findOrFail($unitKerja);

        $validated = $request->validate([
            'pengguna_id' => ['required', 'integer'],
        ], [
            'pengguna_id.required' => 'Silakan pilih Nakes yang akan ditunjuk.',
        ]);

        $pengguna = Pengguna::where('tenant_id', $tenantId)
            ->where('is_aktif', true)
            ->findOrFail($validated['pengguna_id']);

        // Pastikan pengguna adalah Nakes dan belum Karu
        abort_unless($pengguna->punyaPeranDiDb(Peran::NAKES), 422, 'Pengguna bukan Nakes.');
        abort_if($pengguna->punyaPeranDiDb(Peran::KEPALA_RUANGAN), 422, 'Pengguna sudah menjadi Kepala Ruangan.');

        DB::transaction(function () use ($pengguna, $unit): void {
            $karuPeran = Peran::where('nama_peran', Peran::KEPALA_RUANGAN)->first();

            // Jika unit sudah punya Karu, cabut peran lama terlebih dahulu (flow "Ganti")
            $karuLamaSemua = $unit->pengguna()->whereHas('peran', fn ($q) => $q->where('nama_peran', Peran::KEPALA_RUANGAN))->get();
            foreach ($karuLamaSemua as $karuLama) {
                $karuLama->peran()->detach($karuPeran->id);

                $this->auditLog->catat(
                    namaTabel: 'akun.pengguna_peran',
                    aksi:      'DELETE',
                    idData:    $karuLama->id,
                    dataLama:  [
                        'aksi'     => 'cabut_karu_ganti',
                        'pengguna' => $karuLama->nama_lengkap,
                        'unit'     => $unit->nama_unit,
                    ],
                );
            }

            // Tambahkan peran Kepala Ruangan ke pengguna baru
            $pengguna->peran()->attach($karuPeran->id);

            // Set unit kerja ke unit yang dikepalai
            $pengguna->update(['unit_id' => $unit->id]);

            $this->auditLog->catat(
                namaTabel: 'akun.pengguna_peran',
                aksi:      'CREATE',
                idData:    $pengguna->id,
                dataBaru:  [
                    'aksi'       => 'tunjuk_karu',
                    'pengguna'   => $pengguna->nama_lengkap,
                    'unit'       => $unit->nama_unit,
                ],
            );
        });

        return redirect()
            ->route('admin.kepala-ruangan.index')
            ->with('sukses', "{$pengguna->nama_lengkap} berhasil ditunjuk sebagai Kepala Ruangan di {$unit->nama_unit}.");
    }

    /* ------------------------------------------------------------------
     | DELETE - Cabut peran Karu dari pengguna
     | ----------------------------------------------------------------*/

    public function cabut(int $unitKerja): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $unit     = UnitKerja::where('tenant_id', $tenantId)->findOrFail($unitKerja);
        $karu     = $unit->kepalaRuangan();

        abort_if(! $karu, 404, 'Unit ini tidak memiliki Kepala Ruangan.');

        DB::transaction(function () use ($karu, $unit): void {
            $dataLama = [
                'aksi'       => 'cabut_karu',
                'pengguna'   => $karu->nama_lengkap,
                'unit'       => $unit->nama_unit,
            ];

            // Hapus peran Kepala Ruangan dari pivot
            $karuPeran = Peran::where('nama_peran', Peran::KEPALA_RUANGAN)->first();
            $karu->peran()->detach($karuPeran->id);

            // Hapus active_role dari sesi jika user ini sedang login sebagai Karu
            // (akan ter-reset otomatis saat session expire)

            $this->auditLog->catat(
                namaTabel: 'akun.pengguna_peran',
                aksi:      'DELETE',
                idData:    $karu->id,
                dataLama:  $dataLama,
            );
        });

        return redirect()
            ->route('admin.kepala-ruangan.index')
            ->with('sukses', "Peran Kepala Ruangan {$karu->nama_lengkap} di {$unit->nama_unit} berhasil dicabut.");
    }
}
