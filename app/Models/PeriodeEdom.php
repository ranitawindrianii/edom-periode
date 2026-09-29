<?php

namespace App\Models;

// SKEMA DASAR (dianggap sudah ada di proyek utama).
// Saat diintegrasikan ke proyek yang sudah punya model ini, JANGAN salin file ini;
// pakai model yang sudah ada.

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodeEdom extends Model
{
    protected $table = 'periode_edom';

    protected $casts = [
        'fakultas_id'     => 'integer',
        'prodi_id'        => 'integer',
        'tanggal_mulai'   => 'date:Y-m-d',
        'tanggal_selesai' => 'date:Y-m-d',
    ];

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_id');
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id');
    }
}
