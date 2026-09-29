<?php

namespace App\Http\Controllers;

// Endpoint CRUD periode EDOM. Setiap endpoint me-return SATU data periode EDOM (JSON).
// Semua aturan ada di PeriodeEdomService.
//  - ValidationException    -> 422 (otomatis oleh Laravel)
//  - ModelNotFoundException -> 404 (otomatis oleh Laravel)
// Field "status" sengaja tidak diteruskan dari request: status diisi sistem (DRAF).

use App\Services\PeriodeEdomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodeEdomController extends Controller
{
    private const FIELD = [
        'fakultas_id', 'prodi_id', 'tahun_akademik', 'semester', 'tanggal_mulai', 'tanggal_selesai',
    ];

    public function __construct(private readonly PeriodeEdomService $service)
    {
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->only(self::FIELD))], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->get($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->update($id, $request->only(self::FIELD))]);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->delete($id)]);
    }
}
