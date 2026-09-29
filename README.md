# EDOM – Manajemen Periode (Unit #1)

Modul Laravel untuk **membuat dan mengelola periode EDOM** (Evaluasi Dosen oleh Mahasiswa).
Setiap endpoint mengembalikan **satu data periode EDOM**.

## Constraint yang diterapkan

| No | Constraint | Gejala yang dicegah |
|---|---|---|
| 1 | `fakultas_id` dan `prodi_id` wajib; `null`, string kosong, dan spasi ditolak; prodi harus ada dan milik fakultas yang dipilih. | Edge case terlewat |
| 2 | Tahun akademik disimpan `YYYY/YYYY` (`2026`, `26/27`, `2026/2027` → `2026/2027`). Semester hanya `Ganjil`/`Genap` (`ganjil`, `gasal`, `1` → `Ganjil`; `genap`, `2` → `Genap`). Tanggal wajib, input `dd-mm-yyyy`, disimpan `Y-m-d`. Status periode baru selalu `DRAF`, diisi sistem. | Format output tidak sesuai |
| 3 | Kombinasi prodi + tahun akademik + semester unik; dicek setelah normalisasi, di dalam transaksi; duplikat → 422. Unique index dipasang lewat `Schema::table`. | Edge case terlewat |
| 4 | Tanggal UAS diambil dari model jadwal UAS yang sudah ada; ditolak jika `tanggal_selesai ≥ tanggal_mulai` UAS; ditolak jika `tanggal_mulai > tanggal_selesai`; format tanggal tidak valid ditolak. Tidak ada model/tabel baru. | Scope creep, Edge case terlewat |

## Acceptance Criteria

| AC | Skenario | Test |
|---|---|---|
| AC-01.1 | 2026/2027 Ganjil, 01-12-2026 s.d. 18-12-2026 → status `DRAF` | `test_ac_01_1_periode_valid_tersimpan_dengan_status_draf` |
| AC-01.2 | Tanggal mulai > tanggal selesai → ditolak | `test_ac_01_2_tanggal_mulai_lebih_besar_ditolak` |
| AC-01.3 | Semester sudah punya periode → ditolak | `test_ac_01_3_periode_duplikat_ditolak` |
| AC-01.4 | UAS mulai 21-12-2026, periode selesai 22-12-2026 → ditolak | `test_ac_01_4_tanggal_selesai_melewati_uas_ditolak` |

## Struktur file Unit #1

```
app/Services/PeriodeEdomService.php          # CRUD + seluruh constraint
app/Http/Controllers/PeriodeEdomController.php
routes/api.php                               # /api/periode-edom
database/migrations/2026_09_29_000001_update_periode_edom_table.php
tests/Feature/PeriodeEdomCreateTest.php      # AC-01.1 s.d. AC-01.4
tests/Unit/PeriodeEdomValidasiTest.php       # normalisasi & validasi
```

**Skema dasar (simulasi tabel yang sudah ada):** `app/Models/{PeriodeEdom,Prodi,Fakultas,JadwalUas}.php` dan
`database/migrations/2026_09_01_000000_create_skema_dasar_akademik.php` hanya ada agar repository ini
bisa dijalankan dan dites sendiri. **Saat integrasi ke proyek yang sudah punya tabel/model tersebut, jangan salin
file-file ini** — cukup salin file Unit #1 di atas.

## Menjalankan

Butuh PHP 8.3+ dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

## Menjalankan test

```bash
php artisan test
```

Test juga berjalan otomatis di GitHub Actions setiap push (`.github/workflows/tests.yml`).

## Contoh API

`POST /api/periode-edom`

```json
{
  "fakultas_id": 1,
  "prodi_id": 1,
  "tahun_akademik": "26/27",
  "semester": "ganjil",
  "tanggal_mulai": "01-12-2026",
  "tanggal_selesai": "18-12-2026"
}
```

Respons `201`:

```json
{
  "data": {
    "id": 1,
    "fakultas_id": 1,
    "prodi_id": 1,
    "tahun_akademik": "2026/2027",
    "semester": "Ganjil",
    "tanggal_mulai": "2026-12-01",
    "tanggal_selesai": "2026-12-18",
    "status": "DRAF"
  }
}
```

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/periode-edom` | Buat periode (status otomatis `DRAF`) |
| GET | `/api/periode-edom/{id}` | Ambil satu periode |
| PUT/PATCH | `/api/periode-edom/{id}` | Ubah periode (constraint yang sama berlaku) |
| DELETE | `/api/periode-edom/{id}` | Hapus periode, mengembalikan data yang dihapus |

Data tidak valid → `422` dengan pesan per field. ID tidak ditemukan → `404`.

## Asumsi

- Jika jadwal UAS untuk tahun akademik & semester itu belum ada, penyimpanan diizinkan.
- Tanggal selesai yang jatuh tepat di hari UAS dimulai juga ditolak.
- AC-01.3 berlaku per prodi (prodi + tahun akademik + semester).
