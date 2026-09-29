<?php

use App\Http\Controllers\PeriodeEdomController;
use Illuminate\Support\Facades\Route;

// Unit #1 — Manajemen periode EDOM
Route::apiResource('periode-edom', PeriodeEdomController::class)
    ->only(['store', 'show', 'update', 'destroy'])
    ->parameters(['periode-edom' => 'id']);
