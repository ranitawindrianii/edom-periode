<?php

namespace App\Services;

// Validasi tanggal periode EDOM.
//
// Constraint yang diterapkan:
//  1. Input tanggal hanya dd-mm-yyyy, diparse dengan createFromFormat('!d-m-Y') + checkdate.
//     DILARANG strtotime, Carbon::parse, atau new DateTime untuk input pengguna,
//     supaya 01-12-2026 selalu terbaca 1 Desember 2026 (bukan 12 Januari).
//  2. tanggal_mulai = tanggal_selesai diterima; ditolak hanya jika tanggal_mulai > tanggal_selesai.
//  3. Tolak jika rentang tanggal beririsan dengan periode lain di prodi yang sama.
//     Irisan inklusif: baru.mulai <= lama.selesai DAN lama.mulai <= baru.selesai.
//     Saat update, periode itu sendiri dikecualikan.
//  4. Semua penolakan memakai ValidationException::withMessages() -> HTTP 422,
//     key = nama field (tanggal_mulai / tanggal_selesai), pesan jelas berbahasa Indonesia.
//     Pesan bentrok menyebut periode yang bentrok beserta rentang tanggalnya.
//     DILARANG throw new Exception(), \RuntimeException, atau pesan kosong.

use App\Models\PeriodeEdom;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PeriodeEdomTanggalValidator
{
    private const LABEL = [
        'tanggal_mulai'   => 'Tanggal mulai',
        'tanggal_selesai' => 'Tanggal selesai',
    ];

    /** Parse tanggal input pengguna. Hanya dd-mm-yyyy; tanggal yang tidak ada ditolak. */
    public static function parse(mixed $nilai, string $field = 'tanggal_mulai'): CarbonImmutable
    {
        $label = self::LABEL[$field] ?? 'Tanggal';
        $teks  = trim((string) ($nilai ?? ''));

        if ($teks === '') {
            throw ValidationException::withMessages([$field => "{$label} wajib diisi."]);
        }

        if (! preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $teks, $m)) {
            throw ValidationException::withMessages([
                $field => "{$label} harus berformat dd-mm-yyyy, contoh: 01-12-2026.",
            ]);
        }

        if (! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            throw ValidationException::withMessages([
                $field => "{$label} tidak valid: tanggal {$teks} tidak ada di kalender.",
            ]);
        }

        return CarbonImmutable::createFromFormat('!d-m-Y', $teks);
    }

    /**
     * Validasi lengkap: format, urutan, dan bentrok di prodi yang sama.
     *
     * @param  int|null  $kecualiId  id periode yang sedang di-update (dikecualikan dari cek bentrok)
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function validasi(mixed $tanggalMulai, mixed $tanggalSelesai, int $prodiId, ?int $kecualiId = null): array
    {
        [$mulai, $selesai] = self::validasiUrutan($tanggalMulai, $tanggalSelesai);

        self::validasiBentrok($prodiId, $mulai, $selesai, $kecualiId);

        return [$mulai, $selesai];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function validasiUrutan(mixed $tanggalMulai, mixed $tanggalSelesai): array
    {
        $mulai   = self::parse($tanggalMulai, 'tanggal_mulai');
        $selesai = self::parse($tanggalSelesai, 'tanggal_selesai');

        // Constraint 2: mulai = selesai diterima; ditolak hanya jika mulai > selesai.
        if ($mulai->greaterThan($selesai)) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => sprintf(
                    'Tanggal mulai (%s) tidak boleh lebih besar dari tanggal selesai (%s).',
                    $mulai->format('d-m-Y'),
                    $selesai->format('d-m-Y'),
                ),
            ]);
        }

        return [$mulai, $selesai];
    }

    /** Constraint 3: tolak jika beririsan dengan periode lain di prodi yang sama. */
    public static function validasiBentrok(int $prodiId, CarbonImmutable $mulai, CarbonImmutable $selesai, ?int $kecualiId = null): void
    {
        $lawan = PeriodeEdom::query()
            ->where('prodi_id', $prodiId)
            ->when($kecualiId !== null, fn ($q) => $q->whereKeyNot($kecualiId))
            ->whereDate('tanggal_mulai', '<=', $selesai->format('Y-m-d'))   // lama.mulai <= baru.selesai
            ->whereDate('tanggal_selesai', '>=', $mulai->format('Y-m-d'))   // baru.mulai <= lama.selesai
            ->orderBy('tanggal_mulai')
            ->first();

        if ($lawan) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => self::pesanBentrok($mulai, $selesai, $lawan),
            ]);
        }
    }

    /** Pesan bentrok: menyebut periode lawan beserta rentang tanggalnya. */
    public static function pesanBentrok(CarbonImmutable $mulai, CarbonImmutable $selesai, object $lawan): string
    {
        return sprintf(
            'Rentang %s s.d. %s bentrok dengan periode EDOM %s %s (%s s.d. %s) di program studi yang sama.',
            $mulai->format('d-m-Y'),
            $selesai->format('d-m-Y'),
            $lawan->semester,
            $lawan->tahun_akademik,
            self::formatTersimpan($lawan->tanggal_mulai),
            self::formatTersimpan($lawan->tanggal_selesai),
        );
    }

    /** Rumus irisan inklusif (dipakai juga untuk pengujian tanpa database). */
    public static function beririsan(
        CarbonImmutable $baruMulai,
        CarbonImmutable $baruSelesai,
        CarbonImmutable $lamaMulai,
        CarbonImmutable $lamaSelesai,
    ): bool {
        return $baruMulai->lessThanOrEqualTo($lamaSelesai) && $lamaMulai->lessThanOrEqualTo($baruSelesai);
    }

    /** Tanggal dari database (cast date atau string Y-m-d) -> dd-mm-yyyy untuk pesan. */
    private static function formatTersimpan(mixed $nilai): string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('d-m-Y');
        }

        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', substr((string) $nilai, 0, 10));

        return $tanggal ? $tanggal->format('d-m-Y') : (string) $nilai;
    }
}
