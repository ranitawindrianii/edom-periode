<?php

namespace App\Models;

// SKEMA DASAR (dianggap sudah ada di proyek utama). Jangan disalin saat integrasi.

use Illuminate\Database\Eloquent\Model;

class JadwalUas extends Model
{
    protected $table = 'jadwal_uas';

    protected $casts = ['tanggal_mulai' => 'date:Y-m-d'];
}
