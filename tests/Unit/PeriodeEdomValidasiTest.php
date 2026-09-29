<?php

namespace Tests\Unit;

// Uji aturan normalisasi & validasi di PeriodeEdomService (tanpa database).

use App\Services\PeriodeEdomService as S;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodeEdomValidasiTest extends TestCase
{
    public static function tahunValid(): array
    {
        return [['2026', '2026/2027'], ['26/27', '2026/2027'], ['2026/2027', '2026/2027'], ['2026-27', '2026/2027']];
    }

    #[DataProvider('tahunValid')]
    public function test_tahun_akademik_dinormalisasi(string $input, string $harap): void
    {
        $this->assertSame($harap, S::normalisasiTahunAkademik($input));
    }

    public static function tahunTidakValid(): array
    {
        return [[''], ['  '], ['2026/2028'], ['abc'], [null]];
    }

    #[DataProvider('tahunTidakValid')]
    public function test_tahun_akademik_tidak_valid_ditolak(?string $input): void
    {
        $this->expectException(ValidationException::class);
        S::normalisasiTahunAkademik($input);
    }

    public static function semesterValid(): array
    {
        return [['Ganjil', 'Ganjil'], ['ganjil', 'Ganjil'], ['gasal', 'Ganjil'], ['1', 'Ganjil'], ['genap', 'Genap'], ['2', 'Genap']];
    }

    #[DataProvider('semesterValid')]
    public function test_semester_dinormalisasi(string $input, string $harap): void
    {
        $this->assertSame($harap, S::normalisasiSemester($input));
    }

    public function test_semester_di_luar_peta_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        S::normalisasiSemester('3');
    }

    public static function idKosong(): array
    {
        return [[null], [''], ['   '], ['abc'], [0]];
    }

    #[DataProvider('idKosong')]
    public function test_id_kosong_atau_tidak_valid_ditolak(mixed $input): void
    {
        $this->expectException(ValidationException::class);
        S::wajibId($input, 'prodi_id', 'Program studi');
    }

    public function test_tanggal_dd_mm_yyyy_disimpan_y_m_d(): void
    {
        $this->assertSame('2026-12-01', S::parseTanggal('01-12-2026', 'tanggal_mulai', 'Tanggal mulai')->format('Y-m-d'));
    }

    public static function tanggalTidakValid(): array
    {
        return [['31-02-2026'], ['2026-12-01'], ['1-12-2026'], ['']];
    }

    #[DataProvider('tanggalTidakValid')]
    public function test_tanggal_format_tidak_valid_ditolak(string $input): void
    {
        $this->expectException(ValidationException::class);
        S::parseTanggal($input, 'tanggal_mulai', 'Tanggal mulai');
    }

    public function test_tanggal_selesai_pada_hari_uas_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        S::validasiTerhadapUas(CarbonImmutable::parse('2026-12-21'), CarbonImmutable::parse('2026-12-21'));
    }
}
