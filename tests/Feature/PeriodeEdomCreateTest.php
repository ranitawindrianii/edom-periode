<?php

namespace Tests\Feature;

// Feature Test Unit #1 — Membuat periode EDOM, sesuai AC-01.1 s.d. AC-01.4.
//
// Asumsi skema (sesuaikan dengan proyek):
//  - tabel fakultas   : id, nama
//  - tabel prodi      : id, fakultas_id, nama
//  - tabel jadwal_uas : id, tahun_akademik, semester, tanggal_mulai
//  - tabel periode_edom sudah dimigrasi (termasuk migration update_periode_edom_table)
// Data dibuat lewat DB::table agar tidak bergantung pada factory/$fillable.

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeriodeEdomCreateTest extends TestCase
{
    use RefreshDatabase;

    private int $fakultasId;
    private int $prodiId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakultasId = DB::table('fakultas')->insertGetId(['nama' => 'Fakultas Teknik']);
        $this->prodiId    = DB::table('prodi')->insertGetId([
            'fakultas_id' => $this->fakultasId,
            'nama'        => 'Informatika',
        ]);

        // UAS 2026/2027 Ganjil dimulai 21-12-2026
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

    /** AC-01.1 Given 2026/2027 Ganjil, 01-12-2026 s.d. 18-12-2026, When disimpan, Then status DRAF. */
    public function test_ac_01_1_periode_valid_tersimpan_dengan_status_draf(): void
    {
        $respon = $this->postJson('/api/periode-edom', $this->payload(['status' => 'AKTIF']));

        $respon->assertCreated()
            ->assertJsonPath('data.tahun_akademik', '2026/2027')
            ->assertJsonPath('data.semester', 'Ganjil')
            ->assertJsonPath('data.status', 'DRAF');

        $this->assertDatabaseHas('periode_edom', [
            'prodi_id'        => $this->prodiId,
            'tahun_akademik'  => '2026/2027',
            'semester'        => 'Ganjil',
            'tanggal_mulai'   => '2026-12-01',
            'tanggal_selesai' => '2026-12-18',
            'status'          => 'DRAF',
        ]);
    }

    /** AC-01.2 Given tanggal mulai > tanggal selesai, When disimpan, Then ditolak. */
    public function test_ac_01_2_tanggal_mulai_lebih_besar_ditolak(): void
    {
        $this->postJson('/api/periode-edom', $this->payload([
            'tanggal_mulai'   => '18-12-2026',
            'tanggal_selesai' => '01-12-2026',
        ]))->assertStatus(422)->assertJsonValidationErrors('tanggal_mulai');

        $this->assertDatabaseCount('periode_edom', 0);
    }

    /** AC-01.3 Given semester itu sudah punya periode, When Admin membuat lagi, Then ditolak. */
    public function test_ac_01_3_periode_duplikat_ditolak(): void
    {
        $this->postJson('/api/periode-edom', $this->payload())->assertCreated();

        // Format berbeda, makna sama -> tetap terdeteksi duplikat setelah normalisasi
        $this->postJson('/api/periode-edom', $this->payload([
            'tahun_akademik' => '26/27',
            'semester'       => 'ganjil',
        ]))->assertStatus(422)->assertJsonValidationErrors('periode');

        $this->assertDatabaseCount('periode_edom', 1);
    }

    /** AC-01.4 Given UAS dimulai 21-12-2026, When tanggal selesai 22-12-2026, Then ditolak. */
    public function test_ac_01_4_tanggal_selesai_melewati_uas_ditolak(): void
    {
        $this->postJson('/api/periode-edom', $this->payload([
            'tanggal_selesai' => '22-12-2026',
        ]))->assertStatus(422)->assertJsonValidationErrors('tanggal_selesai');

        $this->assertDatabaseCount('periode_edom', 0);
    }

    /** Constraint 1: prodi/fakultas kosong ditolak. */
    public function test_prodi_kosong_ditolak(): void
    {
        $this->postJson('/api/periode-edom', $this->payload(['prodi_id' => '   ']))
            ->assertStatus(422)->assertJsonValidationErrors('prodi_id');
    }
}
