<?php

namespace App\Services;

// Unit #1 — Manajemen periode EDOM (CRUD).
//
// Format : setiap operasi me-return SATU data periode EDOM (model PeriodeEdom).
//
// Constraint yang diterapkan:
//  1. fakultas_id & prodi_id wajib; null / string kosong / spasi ditolak;
//     prodi harus ada dan harus milik fakultas yang dipilih.
//  2. tahun_akademik disimpan "YYYY/YYYY" (2026, 26/27, 2026/2027 -> 2026/2027);
//     semester hanya "Ganjil"/"Genap" (ganjil, gasal, 1 -> Ganjil; genap, 2 -> Genap);
//     tanggal_mulai & tanggal_selesai wajib, input dd-mm-yyyy, disimpan Y-m-d;
//     status periode baru selalu "DRAF", diisi sistem (input status diabaikan).
//  3. prodi + tahun_akademik + semester unik; dicek SETELAH normalisasi,
//     di dalam transaksi; duplikat -> 422. Unique index dipasang lewat Schema::table.
//  4. tanggal UAS diambil dari model jadwal UAS yang SUDAH ADA; tolak jika
//     tanggal_selesai >= tanggal_mulai UAS pada tahun akademik & semester yang sama;
//     tolak jika tanggal_mulai > tanggal_selesai; tanggal format tidak valid ditolak.
//
// Tidak ada model/tabel baru. Sesuaikan nama model/kolom di bawah bila di proyek berbeda:
//  - App\Models\PeriodeEdom : fakultas_id, prodi_id, tahun_akademik, semester,
//                             tanggal_mulai, tanggal_selesai, status
//  - App\Models\Prodi       : id, fakultas_id
//  - App\Models\JadwalUas   : tahun_akademik, semester, tanggal_mulai

use App\Models\JadwalUas;
use App\Models\PeriodeEdom;
use App\Models\Prodi;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodeEdomService
{
    public const STATUS_DRAF = 'DRAF';

    private const FIELD_INPUT = [
        'fakultas_id', 'prodi_id', 'tahun_akademik', 'semester', 'tanggal_mulai', 'tanggal_selesai',
    ];

    private const PETA_SEMESTER = [
        'ganjil' => 'Ganjil',
        'gasal'  => 'Ganjil',
        '1'      => 'Ganjil',
        'genap'  => 'Genap',
        '2'      => 'Genap',
    ];

    // ============================== CREATE ================================
    public function create(array $input): PeriodeEdom
    {
        $data = $this->validasi(array_intersect_key($input, array_flip(self::FIELD_INPUT)));

        return $this->simpanAman($data, function () use ($data) {
            $this->cekDuplikat($data);

            $periode = new PeriodeEdom();
            $this->isi($periode, $data);
            $periode->status = self::STATUS_DRAF; // constraint 2: diisi sistem, bukan dari input
            $periode->save();

            return $periode->refresh();
        });
    }

    // =============================== READ =================================
    public function get(int $periodeId): PeriodeEdom
    {
        return PeriodeEdom::query()->findOrFail($periodeId); // 404 bila tidak ada
    }

    // ============================== UPDATE ================================
    public function update(int $periodeId, array $input): PeriodeEdom
    {
        $periode = $this->get($periodeId);

        // Gabungkan data lama + perubahan, lalu validasi ulang hasil akhirnya.
        $gabungan = array_merge([
            'fakultas_id'     => $periode->fakultas_id,
            'prodi_id'        => $periode->prodi_id,
            'tahun_akademik'  => $periode->tahun_akademik,
            'semester'        => $periode->semester,
            'tanggal_mulai'   => self::keInputTanggal($periode->tanggal_mulai),
            'tanggal_selesai' => self::keInputTanggal($periode->tanggal_selesai),
        ], array_intersect_key($input, array_flip(self::FIELD_INPUT)));

        $data = $this->validasi($gabungan);

        return $this->simpanAman($data, function () use ($periode, $data) {
            $this->cekDuplikat($data, $periode->getKey());

            $this->isi($periode, $data); // status tidak diubah dari input
            $periode->save();

            return $periode->refresh();
        });
    }

    // ============================== DELETE ================================
    /** Menghapus periode dan me-return data periode yang dihapus. */
    public function delete(int $periodeId): PeriodeEdom
    {
        $periode = $this->get($periodeId);
        $periode->delete();

        return $periode;
    }

    // ============================ Validasi ================================
    /** @return array{fakultas_id:int, prodi_id:int, tahun_akademik:string, semester:string, tanggal_mulai:CarbonImmutable, tanggal_selesai:CarbonImmutable} */
    private function validasi(array $input): array
    {
        // Constraint 1
        $fakultasId = self::wajibId($input['fakultas_id'] ?? null, 'fakultas_id', 'Fakultas');
        $prodiId    = self::wajibId($input['prodi_id'] ?? null, 'prodi_id', 'Program studi');
        $this->validasiProdiMilikFakultas($prodiId, $fakultasId);

        // Constraint 2
        $tahunAkademik  = self::normalisasiTahunAkademik($input['tahun_akademik'] ?? null);
        $semester       = self::normalisasiSemester($input['semester'] ?? null);
        $tanggalMulai   = self::parseTanggal($input['tanggal_mulai'] ?? null, 'tanggal_mulai', 'Tanggal mulai');
        $tanggalSelesai = self::parseTanggal($input['tanggal_selesai'] ?? null, 'tanggal_selesai', 'Tanggal selesai');

        // Constraint 4 — urutan tanggal
        self::validasiUrutanTanggal($tanggalMulai, $tanggalSelesai);

        // Constraint 4 — harus selesai sebelum UAS dimulai
        $this->validasiSebelumUas($tahunAkademik, $semester, $tanggalSelesai);

        return [
            'fakultas_id'     => $fakultasId,
            'prodi_id'        => $prodiId,
            'tahun_akademik'  => $tahunAkademik,
            'semester'        => $semester,
            'tanggal_mulai'   => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
        ];
    }

    public static function wajibId(mixed $nilai, string $field, string $label): int
    {
        if ($nilai === null || is_bool($nilai)) {
            throw ValidationException::withMessages([$field => "{$label} wajib diisi."]);
        }

        $teks = is_string($nilai) ? trim($nilai) : (string) $nilai;

        if ($teks === '') {
            throw ValidationException::withMessages([$field => "{$label} wajib diisi."]);
        }
        if (! ctype_digit($teks) || (int) $teks <= 0) {
            throw ValidationException::withMessages([$field => "{$label} tidak valid."]);
        }

        return (int) $teks;
    }

    private function validasiProdiMilikFakultas(int $prodiId, int $fakultasId): void
    {
        $prodi = Prodi::query()->find($prodiId);

        if (! $prodi) {
            throw ValidationException::withMessages(['prodi_id' => 'Program studi tidak ditemukan.']);
        }
        if ((int) $prodi->fakultas_id !== $fakultasId) {
            throw ValidationException::withMessages([
                'prodi_id' => 'Program studi tidak berada di fakultas yang dipilih.',
            ]);
        }
    }

    /** "2026" | "26/27" | "2026/2027" | "2026-27" -> "2026/2027". Lainnya ditolak. */
    public static function normalisasiTahunAkademik(mixed $nilai): string
    {
        $teks = preg_replace('/\s+/', '', (string) ($nilai ?? ''));

        if ($teks === '') {
            throw ValidationException::withMessages(['tahun_akademik' => 'Tahun akademik wajib diisi.']);
        }

        if (preg_match('/^(\d{4})$/', $teks, $m)) {
            $awal  = (int) $m[1];
            $akhir = $awal + 1;
        } elseif (preg_match('/^(\d{2}|\d{4})[\/\-](\d{2}|\d{4})$/', $teks, $m)) {
            $awal  = self::keEmpatDigit($m[1]);
            $akhir = self::keEmpatDigit($m[2]);
        } else {
            throw ValidationException::withMessages([
                'tahun_akademik' => 'Format tahun akademik tidak dikenali. Contoh: 2026/2027.',
            ]);
        }

        if ($akhir !== $awal + 1) {
            throw ValidationException::withMessages([
                'tahun_akademik' => 'Tahun akademik harus dua tahun berurutan. Contoh: 2026/2027.',
            ]);
        }
        if ($awal < 2000 || $awal > 2099) {
            throw ValidationException::withMessages(['tahun_akademik' => 'Tahun akademik di luar rentang yang wajar.']);
        }

        return "{$awal}/{$akhir}";
    }

    /** "Ganjil" | "ganjil" | "gasal" | "1" -> "Ganjil"; "genap" | "2" -> "Genap". Lainnya ditolak. */
    public static function normalisasiSemester(mixed $nilai): string
    {
        $kunci = mb_strtolower(trim((string) ($nilai ?? '')));

        if ($kunci === '') {
            throw ValidationException::withMessages(['semester' => 'Semester wajib diisi.']);
        }
        if (! array_key_exists($kunci, self::PETA_SEMESTER)) {
            throw ValidationException::withMessages(['semester' => 'Semester harus Ganjil atau Genap.']);
        }

        return self::PETA_SEMESTER[$kunci];
    }

    /** Input wajib dd-mm-yyyy dan tanggalnya harus benar-benar ada (31-02-2026 ditolak). */
    public static function parseTanggal(mixed $nilai, string $field, string $label): CarbonImmutable
    {
        $teks = trim((string) ($nilai ?? ''));

        if ($teks === '') {
            throw ValidationException::withMessages([$field => "{$label} wajib diisi."]);
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $teks, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return CarbonImmutable::createFromFormat('!d-m-Y', $teks);
        }

        throw ValidationException::withMessages([
            $field => "{$label} tidak valid. Gunakan format dd-mm-yyyy, contoh: 01-12-2026.",
        ]);
    }

    public static function validasiUrutanTanggal(CarbonImmutable $mulai, CarbonImmutable $selesai): void
    {
        if ($mulai->greaterThan($selesai)) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.',
            ]);
        }
    }

    private function validasiSebelumUas(string $tahunAkademik, string $semester, CarbonImmutable $tanggalSelesai): void
    {
        $mulaiUas = JadwalUas::query()
            ->where('tahun_akademik', $tahunAkademik)
            ->where('semester', $semester)
            ->min('tanggal_mulai');

        // Jika jadwal UAS semester itu belum ada, tidak ada batas pembanding -> tidak ditolak.
        if ($mulaiUas === null) {
            return;
        }

        self::validasiTerhadapUas($tanggalSelesai, CarbonImmutable::parse($mulaiUas)->startOfDay());
    }

    public static function validasiTerhadapUas(CarbonImmutable $tanggalSelesai, CarbonImmutable $mulaiUas): void
    {
        if ($tanggalSelesai->greaterThanOrEqualTo($mulaiUas)) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => 'Tanggal selesai harus sebelum UAS dimulai (' . $mulaiUas->format('d-m-Y') . ').',
            ]);
        }
    }

    // ======================= Duplikat & penyimpanan =======================
    private function cekDuplikat(array $data, ?int $kecualiId = null): void
    {
        $sudahAda = PeriodeEdom::query()
            ->where('prodi_id', $data['prodi_id'])
            ->where('tahun_akademik', $data['tahun_akademik'])
            ->where('semester', $data['semester'])
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->lockForUpdate()
            ->exists();

        if ($sudahAda) {
            throw self::errorDuplikat($data);
        }
    }

    /** Jalankan di transaksi; tabrakan unique index (SQLSTATE 23000) -> 422, bukan 500. */
    private function simpanAman(array $data, callable $aksi): PeriodeEdom
    {
        try {
            return DB::transaction($aksi);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23000') {
                throw self::errorDuplikat($data);
            }
            throw $e;
        }
    }

    private function isi(PeriodeEdom $periode, array $data): void
    {
        $periode->fakultas_id     = $data['fakultas_id'];
        $periode->prodi_id        = $data['prodi_id'];
        $periode->tahun_akademik  = $data['tahun_akademik'];
        $periode->semester        = $data['semester'];
        $periode->tanggal_mulai   = $data['tanggal_mulai']->format('Y-m-d');
        $periode->tanggal_selesai = $data['tanggal_selesai']->format('Y-m-d');
    }

    private static function errorDuplikat(array $data): ValidationException
    {
        return ValidationException::withMessages([
            'periode' => "Periode EDOM {$data['semester']} {$data['tahun_akademik']} untuk program studi ini sudah ada.",
        ]);
    }

    private static function keEmpatDigit(string $tahun): int
    {
        return strlen($tahun) === 2 ? 2000 + (int) $tahun : (int) $tahun;
    }

    private static function keInputTanggal(mixed $nilai): ?string
    {
        return $nilai ? CarbonImmutable::parse($nilai)->format('d-m-Y') : null;
    }
}
