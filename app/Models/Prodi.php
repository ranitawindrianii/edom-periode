<?php

namespace App\Models;

// SKEMA DASAR (dianggap sudah ada di proyek utama). Jangan disalin saat integrasi.

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prodi extends Model
{
    protected $table = 'prodi';

    protected $casts = ['fakultas_id' => 'integer'];

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id');
    }
}
