<?php

namespace Tests\Unit;

// Unit #2 — Status periode dari tanggal, sesuai AC-02.1 s.d. AC-02.5 (tanpa database).

use App\Models\PeriodeEdom;
use App\Services\PeriodeEdomStatusService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodeEdomStatusTest extends TestCase
{
    private PeriodeEdomStatusService $status;

    protected function setUp(): void
    {
        parent::setUp();
        $this->status = new PeriodeEdomStatusService();
    }

    private function periode(bool $diaktifkan, string $mulai = '2026-12-01', string $selesai = '2026-12-18'): PeriodeEdom
    {
        return (new PeriodeEdom())->forceFill([
            'tanggal_mulai'   => $mulai,
            'tanggal_selesai' => $selesai,
            'status'          => 'DRAF',
            'diaktifkan_pada' => $diaktifkan ? '2026-11-20 08:00:00' : null,
        ]);
    }

    private static function hari(string $tanggal, string $jam = '00:00', string $zona = 'Asia/Jakarta'): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!d-m-Y H:i', "{$tanggal} {$jam}", $zona);
    }

    /** Given periode sudah diaktifkan (01-12 s.d. 18-12-2026) */
    public function test_ac_02_1_sebelum_mulai_terjadwal(): void
    {
        $this->assertSame('TERJADWAL', $this->status->hitungStatus($this->periode(true), self::hari('30-11-2026')));
    }

    public function test_ac_02_2_hari_pertama_aktif(): void
    {
        $this->assertSame('AKTIF', $this->status->hitungStatus($this->periode(true), self::hari('01-12-2026')));
    }

    public function test_ac_02_3_hari_terakhir_aktif(): void
    {
        $this->assertSame('AKTIF', $this->status->hitungStatus($this->periode(true), self::hari('18-12-2026')));
    }

    public function test_ac_02_4_setelah_selesai_ditutup(): void
    {
        $this->assertSame('DITUTUP', $this->status->hitungStatus($this->periode(true), self::hari('19-12-2026')));
    }

    public static function tanggalBebas(): array
    {
        return [['30-11-2026'], ['01-12-2026'], ['18-12-2026'], ['19-12-2026'], ['01-01-2030']];
    }

    /** AC-02.5 Given periode belum diaktifkan, When status dihitung pada tanggal berapa pun, Then tetap DRAF. */
    #[DataProvider('tanggalBebas')]
    public function test_ac_02_5_belum_diaktifkan_selalu_draf(string $tanggal): void
    {
        $this->assertSame('DRAF', $this->status->hitungStatus($this->periode(false), self::hari($tanggal)));
    }

    // ----------------------- kasus tepi jam & zona waktu -----------------------
    public function test_hari_terakhir_pukul_23_59_wib_masih_aktif(): void
    {
        $this->assertSame('AKTIF', $this->status->hitungStatus($this->periode(true), self::hari('18-12-2026', '23:59')));
    }

    public function test_waktu_utc_dibaca_sebagai_tanggal_jakarta(): void
    {
        // 18-12 17:30 UTC = 19-12 00:30 WIB
        $this->assertSame('DITUTUP', $this->status->hitungStatus($this->periode(true), self::hari('18-12-2026', '17:30', 'UTC')));
    }

    public function test_periode_satu_hari_aktif_pada_hari_itu(): void
    {
        $periode = $this->periode(true, '2026-12-10', '2026-12-10');

        $this->assertSame('AKTIF', $this->status->hitungStatus($periode, self::hari('10-12-2026')));
    }

    public function test_status_hanya_empat_nilai(): void
    {
        $this->assertSame(['DRAF', 'TERJADWAL', 'AKTIF', 'DITUTUP'], PeriodeEdomStatusService::SEMUA_STATUS);
    }
}
