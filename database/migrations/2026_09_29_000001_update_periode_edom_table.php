<?php

// Hanya MENGUBAH tabel periode_edom yang SUDAH ADA (Schema::table, bukan Schema::create).
//  - Menambah tanggal_mulai, tanggal_selesai, status HANYA jika kolomnya belum ada.
//  - Menambah unique index prodi_id + tahun_akademik + semester (pengaman duplikat).
//
// Sebelum migrate, rapikan dulu data lama (format tahun/semester & data dobel). Cek dobel:
//   SELECT prodi_id, tahun_akademik, semester, COUNT(*) FROM periode_edom
//   GROUP BY prodi_id, tahun_akademik, semester HAVING COUNT(*) > 1;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABEL = 'periode_edom';
    private const INDEX = 'periode_edom_prodi_tahun_semester_unique';

    public function up(): void
    {
        $kolomBaru = array_values(array_filter(
            ['tanggal_mulai', 'tanggal_selesai', 'status'],
            fn ($kolom) => ! Schema::hasColumn(self::TABEL, $kolom)
        ));

        if ($kolomBaru !== []) {
            Schema::table(self::TABEL, function (Blueprint $table) use ($kolomBaru) {
                // nullable agar baris lama tidak gagal; service tetap mewajibkan tanggal untuk data baru.
                if (in_array('tanggal_mulai', $kolomBaru, true)) {
                    $table->date('tanggal_mulai')->nullable();
                }
                if (in_array('tanggal_selesai', $kolomBaru, true)) {
                    $table->date('tanggal_selesai')->nullable();
                }
                if (in_array('status', $kolomBaru, true)) {
                    $table->string('status', 20)->default('DRAF');
                }
            });
        }

        if (! Schema::hasIndex(self::TABEL, self::INDEX)) {
            Schema::table(self::TABEL, function (Blueprint $table) {
                $table->unique(['prodi_id', 'tahun_akademik', 'semester'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABEL, self::INDEX)) {
            Schema::table(self::TABEL, function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }

        // Kolom tanggal_mulai/tanggal_selesai/status TIDAK dihapus saat rollback,
        // karena bisa jadi kolom itu sudah ada sebelum migration ini (dan berisi data).
        // Hapus manual bila memang ditambahkan oleh migration ini.
    }
};
