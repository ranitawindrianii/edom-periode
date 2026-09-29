<?php

namespace Tests\Unit;

// Uji aturan normalisasi & validasi (tanpa database).
//  - Unit #1: tahun akademik, semester, id wajib, batas UAS
//  - Unit #2: format tanggal dd-mm-yyyy, urutan tanggal, rumus irisan, pesan bentrok

use App\Services\PeriodeEdomService as S;
use App\Services\PeriodeEdomTanggalValidator as V;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodeEdomValidasiTest extends TestCase
{
    // ----------------------------- Unit #1 -----------------------------------
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

    public function test_tanggal_selesai_pada_hari_uas_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        S::validasiTerhadapUas(V::parse('21-12-2026'), V::parse('21-12-2026'));
    }

    // ----------------------------- Unit #2 -----------------------------------
    public function test_dd_mm_yyyy_dibaca_hari_lalu_bulan(): void
    {
        $this->assertSame('2026-12-01', V::parse('01-12-2026')->format('Y-m-d'));
    }

    public static function tanggalTidakValid(): array
    {
        return [['01/12/2026'], ['12/01/2026'], ['2026-12-01'], ['1-12-2026'], ['31-02-2026'], ['12-13-2026'], ['']];
    }

    #[DataProvider('tanggalTidakValid')]
    public function test_format_tanggal_tidak_valid_ditolak_dengan_pesan(string $input): void
    {
        try {
            V::parse($input, 'tanggal_mulai');
            $this->fail('Seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tanggal_mulai', $e->errors());
            $this->assertNotEmpty($e->errors()['tanggal_mulai'][0]);
        }
    }

    public function test_mulai_sama_dengan_selesai_diterima(): void
    {
        [$mulai, $selesai] = V::validasiUrutan('18-12-2026', '18-12-2026');

        $this->assertTrue($mulai->equalTo($selesai));
    }

    public function test_mulai_lebih_besar_dari_selesai_ditolak(): void
    {
        try {
            V::validasiUrutan('18-12-2026', '01-12-2026');
            $this->fail('Seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tanggal_mulai', $e->errors());
        }
    }

    public static function kasusIrisan(): array
    {
        // periode lama: 01-12-2026 s.d. 18-12-2026
        return [
            'irisan tengah'           => ['10-12-2026', '25-12-2026', true],
            'bersentuhan di akhir'    => ['18-12-2026', '20-12-2026', true],
            'bersentuhan di awal'     => ['20-11-2026', '01-12-2026', true],
            'melingkupi'              => ['25-11-2026', '31-12-2026', true],
            'di dalam'                => ['05-12-2026', '06-12-2026', true],
            'setelahnya'              => ['19-12-2026', '20-12-2026', false],
            'sebelumnya'              => ['20-11-2026', '30-11-2026', false],
        ];
    }

    #[DataProvider('kasusIrisan')]
    public function test_rumus_irisan_inklusif(string $mulai, string $selesai, bool $harap): void
    {
        $this->assertSame($harap, V::beririsan(
            V::parse($mulai), V::parse($selesai), V::parse('01-12-2026'), V::parse('18-12-2026'),
        ));
    }

    public function test_pesan_bentrok_menyebut_periode_dan_rentangnya(): void
    {
        $lawan = (object) [
            'semester'        => 'Ganjil',
            'tahun_akademik'  => '2026/2027',
            'tanggal_mulai'   => CarbonImmutable::create(2026, 12, 1),
            'tanggal_selesai' => CarbonImmutable::create(2026, 12, 18),
        ];

        $pesan = V::pesanBentrok(V::parse('10-12-2026'), V::parse('25-12-2026'), $lawan);

        $this->assertStringContainsString('Ganjil 2026/2027', $pesan);
        $this->assertStringContainsString('01-12-2026 s.d. 18-12-2026', $pesan);
    }
}
