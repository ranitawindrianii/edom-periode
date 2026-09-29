<?php

use App\Http\Controllers\PeriodeEdomController;
use Illuminate\Support\Facades\Route;

// Unit #1 — Membuat & mengelola periode EDOM
Route::apiResource('periode-edom', PeriodeEdomController::class)
    ->only(['store', 'show', 'update', 'destroy'])
    ->parameters(['periode-edom' => 'id']);

// Unit #2 — Aktivasi periode (status kemudian dihitung dari tanggal)
Route::post('periode-edom/{id}/aktifkan', [PeriodeEdomController::class, 'aktifkan']);
