<?php

namespace App\Services;

// Status & aktivasi periode EDOM.
//
// Constraint yang diterapkan:
//  4. Semua penolakan memakai ValidationException::withMessages() -> HTTP 422,
//     pesan berbahasa Indonesia yang jelas. Tidak ada Exception generik.
//  5. Periode yang belum diaktifkan -> status selalu DRAF pada tanggal berapa pun.
//  6. Periode yang sudah diaktifkan, status dihitung per tanggal (tanpa jam, zona Asia/Jakarta),
//     hariIni sebagai parameter:
//        hari_ini <  tanggal_mulai                  -> TERJADWAL
//        tanggal_mulai <= hari_ini <= tanggal_selesai -> AKTIF
//        hari_ini >  tanggal_selesai                -> DITUTUP
//
// Penanda aktivasi: kolom diaktifkan_pada (null = belum diaktifkan) pada tabel periode_edom
// yang sudah ada, ditambahkan lewat Schema::table bila belum ada.

use App\Models\PeriodeEdom;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PeriodeEdomStatusService
{
    public const ZONA_WAKTU = 'Asia/Jakarta';

    public const STATUS_DRAF      = 'DRAF';
    public const STATUS_TERJADWAL = 'TERJADWAL';
    public const STATUS_AKTIF     = 'AKTIF';
    public const STATUS_DITUTUP   = 'DITUTUP';

    public const SEMUA_STATUS = [
        self::STATUS_DRAF, self::STATUS_TERJADWAL, self::STATUS_AKTIF, self::STATUS_DITUTUP,
    ];

    /**
     * Hitung status periode pada tanggal tertentu.
     *
     * @param  \DateTimeInterface|null  $hariIni  default: sekarang di Asia/Jakarta.
     *                                            Hanya tanggalnya (di zona Asia/Jakarta) yang dipakai.
     */
    public function hitungStatus(PeriodeEdom $periode, ?\DateTimeInterface $hariIni = null): string
    {
        // Constraint 5: belum diaktifkan -> DRAF, dicek sebelum perbandingan tanggal.
        if (! self::sudahDiaktifkan($periode)) {
            return self::STATUS_DRAF;
        }

        // Constraint 6: bandingkan per tanggal (Y-m-d) di zona Asia/Jakarta, tanpa jam.
        $hari    = self::tanggalJakarta($hariIni ?? CarbonImmutable::now(self::ZONA_WAKTU));
        $mulai   = self::tanggalTersimpan($periode->tanggal_mulai, 'tanggal_mulai', 'Tanggal mulai');
        $selesai = self::tanggalTersimpan($periode->tanggal_selesai, 'tanggal_selesai', 'Tanggal selesai');

        if ($hari < $mulai) {
            return self::STATUS_TERJADWAL;
        }
        if ($hari <= $selesai) {
            return self::STATUS_AKTIF;
        }

        return self::STATUS_DITUTUP;
    }

    /** Aktivasi periode. Tanggal kosong / tidak valid / terbalik -> 422. */
    public function aktifkan(PeriodeEdom $periode): PeriodeEdom
    {
        $mulai   = self::tanggalTersimpan($periode->tanggal_mulai, 'tanggal_mulai', 'Tanggal mulai');
        $selesai = self::tanggalTersimpan($periode->tanggal_selesai, 'tanggal_selesai', 'Tanggal selesai');

        if ($mulai > $selesai) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Periode tidak bisa diaktifkan: tanggal mulai lebih besar dari tanggal selesai.',
            ]);
        }

        if (! self::sudahDiaktifkan($periode)) {
            $periode->diaktifkan_pada = CarbonImmutable::now(self::ZONA_WAKTU);
            $periode->save();
        }

        return $periode;
    }

    private static function sudahDiaktifkan(PeriodeEdom $periode): bool
    {
        return $periode->diaktifkan_pada !== null;
    }

    /** Ambil tanggal kalender (Y-m-d) dari suatu waktu, dilihat di zona Asia/Jakarta. */
    private static function tanggalJakarta(\DateTimeInterface $waktu): string
    {
        return CarbonImmutable::createFromInterface($waktu)
            ->setTimezone(self::ZONA_WAKTU)
            ->format('Y-m-d');
    }

    /**
     * Tanggal yang sudah tersimpan di database (cast date atau string Y-m-d) -> "Y-m-d".
     * Bukan input pengguna; dibaca dengan format eksplisit, tanpa Carbon::parse.
     */
    private static function tanggalTersimpan(mixed $nilai, string $field, string $label): string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        $teks = trim((string) ($nilai ?? ''));

        if ($teks === '') {
            throw ValidationException::withMessages([$field => "{$label} periode belum diisi."]);
        }

        $teks = substr($teks, 0, 10);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $teks, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw ValidationException::withMessages([$field => "{$label} periode tidak valid."]);
        }

        return $teks;
    }
}
