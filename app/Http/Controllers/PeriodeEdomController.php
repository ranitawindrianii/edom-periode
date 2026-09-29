<?php

namespace App\Http\Controllers;

// Endpoint periode EDOM. Setiap endpoint me-return SATU data periode EDOM (JSON).
//
//  - Unit #1: CRUD (PeriodeEdomService)
//  - Unit #2: aktivasi & status dari tanggal (PeriodeEdomStatusService)
//
// Field "status" di respons adalah status TERHITUNG pada hari ini (Asia/Jakarta):
// DRAF (belum diaktifkan) | TERJADWAL | AKTIF | DITUTUP.
// Field "status" dari request selalu diabaikan.
//
//  - ValidationException    -> 422 (otomatis oleh Laravel)
//  - ModelNotFoundException -> 404 (otomatis oleh Laravel)

use App\Models\PeriodeEdom;
use App\Services\PeriodeEdomService;
use App\Services\PeriodeEdomStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodeEdomController extends Controller
{
    private const FIELD = [
        'fakultas_id', 'prodi_id', 'tahun_akademik', 'semester', 'tanggal_mulai', 'tanggal_selesai',
    ];

    public function __construct(
        private readonly PeriodeEdomService $service,
        private readonly PeriodeEdomStatusService $status,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $periode = $this->service->create($request->only(self::FIELD));

        return response()->json(['data' => $this->tampil($periode)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->tampil($this->service->get($id))]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $periode = $this->service->update($id, $request->only(self::FIELD));

        return response()->json(['data' => $this->tampil($periode)]);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json(['data' => $this->tampil($this->service->delete($id))]);
    }

    /** Unit #2: aktifkan periode. Tanggal kosong / tidak valid -> 422. */
    public function aktifkan(int $id): JsonResponse
    {
        $periode = $this->status->aktifkan($this->service->get($id));

        return response()->json(['data' => $this->tampil($periode)]);
    }

    private function tampil(PeriodeEdom $periode): array
    {
        return array_merge($periode->toArray(), [
            'status' => $this->status->hitungStatus($periode),
        ]);
    }
}
