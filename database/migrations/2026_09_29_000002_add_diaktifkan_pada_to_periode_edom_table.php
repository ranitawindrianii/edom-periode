<?php

// Menambah penanda aktivasi ke tabel periode_edom yang SUDAH ADA (Schema::table).
// null = belum diaktifkan (status dihitung DRAF).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('periode_edom', 'diaktifkan_pada')) {
            Schema::table('periode_edom', function (Blueprint $table) {
                $table->timestamp('diaktifkan_pada')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Tidak dihapus otomatis: kolom mungkin sudah ada sebelum migration ini.
    }
};
