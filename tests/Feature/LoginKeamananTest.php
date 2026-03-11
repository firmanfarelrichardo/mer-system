<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginKeamananTest extends TestCase
{
    use RefreshDatabase; 

    public function test_sistem_memblokir_nakes_setelah_5_kali_gagal_login()
    {
        // 1. Siapkan data Nakes
        // Sesuaikan nama kolom dengan struktur database-mu (misal: 'nip', 'username', dll)
        $nakes = User::factory()->create([
            'nip' => '19801231', 
            'password' => bcrypt('passwordbenar')
        ]);

        // 2. Simulasikan Hacker menyerang 5 kali
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', [
                'nip' => '19801231',
                'password' => 'passwordsalah'
            ]);
        }

        // 3. Serangan ke-6
        $response = $this->post('/masuk', [
            'nip' => '19801231',
            'password' => 'passwordsalah'
        ]);

        // 4. Pastikan sistem memblokir dengan error "Too Many Requests"
        $response->assertSessionHasErrors('nip');
    }
}