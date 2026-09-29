<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /** Data contoh untuk mencoba API secara manual (php artisan migrate --seed). */
    public function run(): void
    {
        $teknik = DB::table('fakultas')->insertGetId(['nama' => 'Fakultas Teknik', 'created_at' => now(), 'updated_at' => now()]);
        $ekonomi = DB::table('fakultas')->insertGetId(['nama' => 'Fakultas Ekonomi', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('prodi')->insert([
            ['fakultas_id' => $teknik, 'nama' => 'Informatika', 'created_at' => now(), 'updated_at' => now()],
            ['fakultas_id' => $teknik, 'nama' => 'Sistem Informasi', 'created_at' => now(), 'updated_at' => now()],
            ['fakultas_id' => $ekonomi, 'nama' => 'Manajemen', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('jadwal_uas')->insert([
            ['tahun_akademik' => '2026/2027', 'semester' => 'Ganjil', 'tanggal_mulai' => '2026-12-21', 'created_at' => now(), 'updated_at' => now()],
            ['tahun_akademik' => '2026/2027', 'semester' => 'Genap', 'tanggal_mulai' => '2027-06-14', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
