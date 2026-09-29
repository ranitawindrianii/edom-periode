<?php

namespace App\Models;

// SKEMA DASAR (dianggap sudah ada di proyek utama). Jangan disalin saat integrasi.

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fakultas extends Model
{
    protected $table = 'fakultas';

    public function prodi(): HasMany
    {
        return $this->hasMany(Prodi::class, 'fakultas_id');
    }
}
