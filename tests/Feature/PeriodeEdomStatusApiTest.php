<?php

namespace Tests\Feature;

// Unit #2 lewat API: validasi tanggal saat simpan, aktivasi, dan status dari tanggal.

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeriodeEdomStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private int $fakultasId;
    private int $prodiId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakultasId = DB::table('fakultas')->insertGetId(['nama' => 'Fakultas Teknik']);
        $this->prodiId    = DB::table('prodi')->insertGetId(['fakultas_id' => $this->fakultasId, 'nama' => 'Informatika']);

        DB::table('jadwal_uas')->insert([
            'tahun_akademik' => '2026/2027',
            'semester'       => 'Ganjil',
            'tanggal_mulai'  => '2026-12-21',
        ]);
    }

    private function payload(array $ubah = []): array
    {
        return array_merge([
            'fakultas_id'     => $this->fakultasId,
            'prodi_id'        => $this->prodiId,
            'tahun_akademik'  => '2026/2027',
            'semester'        => 'Ganjil',
            'tanggal_mulai'   => '01-12-2026',
            'tanggal_selesai' => '18-12-2026',
        ], $ubah);
    }

    private function buatDanAktifkan(): int
    {
        $id = $this->postJson('/api/periode-edom', $this->payload())->assertCreated()->json('data.id');
        $this->postJson("/api/periode-edom/{$id}/aktifkan")->assertOk();

        return $id;
    }

    private function statusPada(int $id, string $waktuJakarta): string
    {
        $this->travelTo(Carbon::createFromFormat('Y-m-d H:i', $waktuJakarta, 'Asia/Jakarta'));

        return $this->getJson("/api/periode-edom/{$id}")->assertOk()->json('data.status');
    }

    // --------------------------- AC-02 lewat API ------------------------------
    public function test_ac_02_status_periode_yang_diaktifkan_mengikuti_tanggal(): void
    {
        $id = $this->buatDanAktifkan();

        $this->assertSame('TERJADWAL', $this->statusPada($id, '2026-11-30 10:00')); // AC-02.1
        $this->assertSame('AKTIF', $this->statusPada($id, '2026-12-01 00:00'));     // AC-02.2
        $this->assertSame('AKTIF', $this->statusPada($id, '2026-12-18 23:59'));     // AC-02.3
        $this->assertSame('DITUTUP', $this->statusPada($id, '2026-12-19 00:00'));   // AC-02.4
    }

    public function test_ac_02_5_periode_belum_diaktifkan_tetap_draf(): void
    {
        $id = $this->postJson('/api/periode-edom', $this->payload())->assertCreated()->json('data.id');

        foreach (['2026-11-30 10:00', '2026-12-01 10:00', '2026-12-18 10:00', '2026-12-19 10:00'] as $waktu) {
            $this->assertSame('DRAF', $this->statusPada($id, $waktu));
        }
    }

    // ------------------------- validasi tanggal (simpan) ----------------------
    public function test_tanggal_mulai_sama_dengan_selesai_diterima(): void
    {
        $this->postJson('/api/periode-edom', $this->payload([
            'tanggal_mulai'   => '10-12-2026',
            'tanggal_selesai' => '10-12-2026',
        ]))->assertCreated();
    }

    public function test_format_mm_dd_ditolak_dengan_pesan(): void
    {
        $this->postJson('/api/periode-edom', $this->payload(['tanggal_selesai' => '12/18/2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tanggal_selesai');
    }

    public function test_bentrok_di_prodi_yang_sama_ditolak_dengan_pesan_periode_lawan(): void
    {
        $this->postJson('/api/periode-edom', $this->payload())->assertCreated();

        // Semester lain (Genap) agar tidak kena aturan duplikat, tapi rentangnya beririsan.
        $respon = $this->postJson('/api/periode-edom', $this->payload([
            'semester'        => 'Genap',
            'tanggal_mulai'   => '10-12-2026',
            'tanggal_selesai' => '25-12-2026',
        ]))->assertStatus(422)->assertJsonValidationErrors('tanggal_mulai');

        $pesan = $respon->json('errors.tanggal_mulai.0');
        $this->assertStringContainsString('Ganjil 2026/2027', $pesan);
        $this->assertStringContainsString('01-12-2026 s.d. 18-12-2026', $pesan);
    }

    public function test_tidak_bentrok_dengan_prodi_lain(): void
    {
        $this->postJson('/api/periode-edom', $this->payload())->assertCreated();

        $prodiLain = DB::table('prodi')->insertGetId(['fakultas_id' => $this->fakultasId, 'nama' => 'Sistem Informasi']);

        $this->postJson('/api/periode-edom', $this->payload(['prodi_id' => $prodiLain]))->assertCreated();
    }

    public function test_update_tidak_bentrok_dengan_dirinya_sendiri(): void
    {
        $id = $this->postJson('/api/periode-edom', $this->payload())->assertCreated()->json('data.id');

        $this->putJson("/api/periode-edom/{$id}", ['tanggal_selesai' => '15-12-2026'])
            ->assertOk()
            ->assertJsonPath('data.tanggal_selesai', '2026-12-15');
    }

    // ------------------------------- aktivasi ---------------------------------
    public function test_aktivasi_periode_tanpa_tanggal_ditolak_422(): void
    {
        // Baris lama (sebelum Unit #1) bisa saja belum punya tanggal.
        $id = DB::table('periode_edom')->insertGetId([
            'fakultas_id'    => $this->fakultasId,
            'prodi_id'       => $this->prodiId,
            'tahun_akademik' => '2025/2026',
            'semester'       => 'Genap',
            'status'         => 'DRAF',
        ]);

        $this->postJson("/api/periode-edom/{$id}/aktifkan")
            ->assertStatus(422)
            ->assertJsonValidationErrors('tanggal_mulai');
    }

    public function test_aktivasi_periode_tidak_ada_404(): void
    {
        $this->postJson('/api/periode-edom/999/aktifkan')->assertNotFound();
    }
}
