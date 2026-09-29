# EDOM – Manajemen Periode

Modul Laravel untuk periode EDOM (Evaluasi Dosen oleh Mahasiswa):

- **Unit #1 — Membuat periode EDOM:** CRUD periode, normalisasi data, cegah duplikat, batas jadwal UAS.
- **Unit #2 — Status periode dari tanggal:** validasi tanggal (format, urutan, bentrok), aktivasi, dan
  status `DRAF` / `TERJADWAL` / `AKTIF` / `DITUTUP` yang dihitung dari tanggal hari ini.

Setiap endpoint mengembalikan **satu data periode EDOM**.

## Constraint Unit #1 — Membuat periode EDOM

| No | Constraint | Gejala yang dicegah |
|---|---|---|
| 1 | `fakultas_id` dan `prodi_id` wajib; `null`, string kosong, dan spasi ditolak; prodi harus ada dan milik fakultas yang dipilih. | Edge case terlewat |
| 2 | Tahun akademik disimpan `YYYY/YYYY` (`2026`, `26/27`, `2026/2027` → `2026/2027`). Semester hanya `Ganjil`/`Genap` (`ganjil`, `gasal`, `1` → `Ganjil`; `genap`, `2` → `Genap`). Tanggal wajib, input `dd-mm-yyyy`, disimpan `Y-m-d`. Status periode baru selalu `DRAF`, diisi sistem. | Format output tidak sesuai |
| 3 | Kombinasi prodi + tahun akademik + semester unik; dicek setelah normalisasi, di dalam transaksi; duplikat → 422. Unique index dipasang lewat `Schema::table`. | Edge case terlewat |
| 4 | Tanggal UAS diambil dari model jadwal UAS yang sudah ada; ditolak jika `tanggal_selesai ≥ tanggal_mulai` UAS. Kolom baru hanya ditambahkan ke tabel `periode_edom` yang sudah ada. Tidak ada model/tabel baru. | Scope creep |

## Constraint Unit #2 — Status periode dari tanggal

| No | Constraint | Gejala yang dicegah |
|---|---|---|
| 1 | Input tanggal hanya `dd-mm-yyyy`, diparse dengan `createFromFormat('!d-m-Y')` + `checkdate`. Dilarang `strtotime`, `Carbon::parse`, `new DateTime` untuk input pengguna. | Format DD/MM dan MM/DD tertukar |
| 2 | Saat disimpan, `tanggal_mulai = tanggal_selesai` diterima; ditolak hanya jika `tanggal_mulai > tanggal_selesai`. | Mulai = selesai tidak jelas |
| 3 | Tolak jika rentang beririsan dengan periode lain di prodi yang sama: `baru.mulai ≤ lama.selesai` dan `lama.mulai ≤ baru.selesai`. Saat update, periode itu sendiri dikecualikan. | Tidak mengecek bentrok |
| 4 | Semua penolakan memakai `ValidationException::withMessages()` → 422, key nama field, pesan berbahasa Indonesia. Pesan bentrok menyebut periode lawan beserta rentangnya. Tidak ada `Exception` generik. | Exception generik tanpa pesan |
| 5 | Periode yang belum diaktifkan → status selalu `DRAF` pada tanggal berapa pun. | — (AC-02.5) |
| 6 | Periode yang sudah diaktifkan, status dihitung per tanggal (tanpa jam, zona `Asia/Jakarta`), `hariIni` sebagai parameter: `hari_ini < mulai` → `TERJADWAL`; `mulai ≤ hari_ini ≤ selesai` → `AKTIF`; `hari_ini > selesai` → `DITUTUP`. | — (AC-02.1 s.d. 02.4) |

## Acceptance Criteria

| AC | Skenario | Test |
|---|---|---|
| AC-01.1 | 2026/2027 Ganjil, 01-12-2026 s.d. 18-12-2026 → status `DRAF` | `PeriodeEdomCreateTest::test_ac_01_1_…` |
| AC-01.2 | Tanggal mulai > tanggal selesai → ditolak | `PeriodeEdomCreateTest::test_ac_01_2_…` |
| AC-01.3 | Semester sudah punya periode → ditolak | `PeriodeEdomCreateTest::test_ac_01_3_…` |
| AC-01.4 | UAS mulai 21-12-2026, periode selesai 22-12-2026 → ditolak | `PeriodeEdomCreateTest::test_ac_01_4_…` |
| AC-02.1 | Diaktifkan (01-12 s.d. 18-12-2026), hari ini 30-11 → `TERJADWAL` | `PeriodeEdomStatusTest::test_ac_02_1_…` + `PeriodeEdomStatusApiTest` |
| AC-02.2 | Hari ini 01-12 → `AKTIF` | `PeriodeEdomStatusTest::test_ac_02_2_…` + `PeriodeEdomStatusApiTest` |
| AC-02.3 | Hari ini 18-12 → `AKTIF` | `PeriodeEdomStatusTest::test_ac_02_3_…` + `PeriodeEdomStatusApiTest` |
| AC-02.4 | Hari ini 19-12 → `DITUTUP` | `PeriodeEdomStatusTest::test_ac_02_4_…` + `PeriodeEdomStatusApiTest` |
| AC-02.5 | Belum diaktifkan, tanggal berapa pun → `DRAF` | `PeriodeEdomStatusTest::test_ac_02_5_…` + `PeriodeEdomStatusApiTest` |

## Struktur file

```
app/Services/PeriodeEdomService.php            # Unit #1: CRUD, normalisasi, duplikat, batas UAS
app/Services/PeriodeEdomTanggalValidator.php   # Unit #2: format, urutan, bentrok (dipakai Unit #1)
app/Services/PeriodeEdomStatusService.php      # Unit #2: aktivasi & hitung status
app/Http/Controllers/PeriodeEdomController.php
routes/api.php
database/migrations/2026_09_29_000001_update_periode_edom_table.php              # Unit #1
database/migrations/2026_09_29_000002_add_diaktifkan_pada_to_periode_edom_table.php # Unit #2
tests/Feature/PeriodeEdomCreateTest.php        # AC-01.1 s.d. AC-01.4
tests/Feature/PeriodeEdomStatusApiTest.php     # AC-02 lewat API, bentrok, aktivasi
tests/Unit/PeriodeEdomStatusTest.php           # AC-02.1 s.d. AC-02.5, jam & zona waktu
tests/Unit/PeriodeEdomValidasiTest.php         # normalisasi, format tanggal, irisan
```

**Alur validasi saat simpan:** prodi/fakultas → tahun & semester → format & urutan tanggal → batas UAS →
(di dalam transaksi) duplikat prodi+tahun+semester → bentrok rentang tanggal di prodi yang sama.

**Skema dasar (simulasi tabel yang sudah ada):** `app/Models/{PeriodeEdom,Prodi,Fakultas,JadwalUas}.php` dan
`database/migrations/2026_09_01_000000_create_skema_dasar_akademik.php` hanya ada agar repository ini
bisa dijalankan dan dites sendiri. **Saat integrasi ke proyek yang sudah punya tabel/model tersebut, jangan salin
file-file ini** — cukup salin file service, controller, route, dan dua migration `Schema::table`. Di model
`PeriodeEdom` yang sudah ada, tambahkan cast `tanggal_mulai`/`tanggal_selesai` (`date`) dan `diaktifkan_pada` (`datetime`).

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

## API

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/periode-edom` | Buat periode (status awal `DRAF`) |
| GET | `/api/periode-edom/{id}` | Ambil satu periode beserta status hari ini |
| PUT/PATCH | `/api/periode-edom/{id}` | Ubah periode (validasi yang sama berlaku) |
| DELETE | `/api/periode-edom/{id}` | Hapus periode, mengembalikan data yang dihapus |
| POST | `/api/periode-edom/{id}/aktifkan` | Aktifkan periode; tanggal kosong/tidak valid → 422 |

Data tidak valid → `422` dengan pesan per field. ID tidak ditemukan → `404`.

Field `status` di respons adalah **status terhitung pada hari ini** (Asia/Jakarta). Kolom `status` di database
tetap `DRAF` sebagai status awal; periode dianggap diaktifkan jika `diaktifkan_pada` terisi.

### Contoh

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
    "diaktifkan_pada": null,
    "status": "DRAF"
  }
}
```

Contoh penolakan bentrok (`422`):

```json
{
  "message": "Rentang 10-12-2026 s.d. 25-12-2026 bentrok dengan periode EDOM Ganjil 2026/2027 (01-12-2026 s.d. 18-12-2026) di program studi yang sama.",
  "errors": {
    "tanggal_mulai": [
      "Rentang 10-12-2026 s.d. 25-12-2026 bentrok dengan periode EDOM Ganjil 2026/2027 (01-12-2026 s.d. 18-12-2026) di program studi yang sama."
    ]
  }
}
```

## Asumsi

- Jika jadwal UAS untuk tahun akademik & semester itu belum ada, penyimpanan diizinkan.
- Tanggal selesai yang jatuh tepat di hari UAS dimulai juga ditolak.
- AC-01.3 berlaku per prodi (prodi + tahun akademik + semester).
- Periode yang hanya bersentuhan tanggalnya (lama selesai 18-12, baru mulai 18-12) dihitung bentrok.
- Duplikat prodi+tahun+semester dicek lebih dulu daripada bentrok tanggal, sehingga periode kedua di semester
  yang sama mendapat pesan duplikat (`periode`), bukan pesan bentrok.
