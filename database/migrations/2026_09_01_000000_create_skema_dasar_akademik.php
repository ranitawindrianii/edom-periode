<?php

// SKEMA DASAR — mensimulasikan tabel yang SUDAH ADA di proyek utama
// (fakultas, prodi, jadwal_uas, periode_edom versi lama), supaya repository ini
// bisa dijalankan dan dites berdiri sendiri.
//
// Saat mengintegrasikan Unit #1 ke proyek yang sudah punya tabel-tabel ini,
// JANGAN salin migration ini. Cukup salin migration
// 2026_09_29_000001_update_periode_edom_table.php.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fakultas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fakultas_id')->constrained('fakultas');
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('jadwal_uas', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_akademik', 9);
            $table->string('semester', 10);
            $table->date('tanggal_mulai');
            $table->timestamps();
        });

        // Versi lama: belum punya tanggal_mulai, tanggal_selesai, status, dan unique index.
        Schema::create('periode_edom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fakultas_id')->nullable()->constrained('fakultas');
            $table->foreignId('prodi_id')->nullable()->constrained('prodi');
            $table->string('tahun_akademik', 20)->nullable();
            $table->string('semester', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_edom');
        Schema::dropIfExists('jadwal_uas');
        Schema::dropIfExists('prodi');
        Schema::dropIfExists('fakultas');
    }
};
