[![Security - Vulnerable by Design](https://img.shields.io/badge/Security-Vulnerable--by--Design-red.svg)](#)
[![Educational - Purpose Only](https://img.shields.io/badge/Purpose-Educational-blue.svg)](#)
[![Environment - Local Only](https://img.shields.io/badge/Environment-Local--Isolated-orange.svg)](#)

# Flaw-me

**Flaw-me** adalah REST API yang sengaja dibangun dengan berbagai celah keamanan (vulnerable by design). Project ini dibuat dengan tujuan edukasi, melatih insting developer dalam memahami bagaimana sebuah serangan bisa terjadi dan terbentuk secara disengaja maupun tidak disengaja, sekaligus sebagai sarana berlatih pentesting (blackbox & whitebox).

## ⚠️ Disclaimer

- Project ini **sengaja dibuat rentan**. Jangan gunakan di production.
- Jalankan hanya di environment lokal atau container terisolasi.
- Dibuat dengan tujuan edukasi, tidak dimaksudkan untuk menyerang sistem orang lain.

## Tech Stack

- Laravel 13 (PHP 8.3.36)
- MySQL
- Laravel Sanctum (API Token Authentication)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Pastikan `APP_DEBUG=true` di `.env` jika ingin mereplikasi temuan **Verbose Error Message** (lihat [#4](#4-security-misconfiguration--verbose-error-message)).

---

## Daftar Kerentanan (Auth Module)

| #   | Vulnerability                                     | Endpoint                           | Severity |
| --- | ------------------------------------------------- | ---------------------------------- | -------- |
| 1   | Information Disclosure (Zombie Endpoint)          | `GET /api/v1/test/users`           | Critical |
| 2   | SQL Injection                                     | `POST /api/v1/auth/login`          | Critical |
| 3   | Broken Object Level Authorization (BOLA)          | `POST /api/v1/auth/reset-password` | Critical |
| 4   | Security Misconfiguration — Verbose Error Message | `POST /api/v1/auth/login`          | Medium   |
| 5   | Plaintext Password Storage                        | Database (`users.password`)        | High     |
| 6   | Forced Session Termination                        | `POST /api/v1/auth/login`          | Low      |

---

### 1. Information Disclosure — Zombie Endpoint

**Endpoint**: `GET /api/v1/test/users`
**CWE**: CWE-200 (Exposure of Sensitive Information), API9:2023 Improper Inventory Management

**Deskripsi**
Endpoint ini merupakan sisa endpoint debugging milik developer yang tidak terdokumentasi dan tidak dilindungi autentikasi apa pun. Endpoint ini mengembalikan daftar seluruh user yang terdaftar di sistem (id, nama, email, role).

**PoC**

```
GET /api/v1/test/users
```

Response:

```json
{
    "message": "Success get all users",
    "data": [
        {
            "id": 1,
            "name": "Hanni Pham",
            "email": "hanni@newjeans.com",
            "role": "User"
        },
        {
            "id": 2,
            "name": "Minji Kim",
            "email": "minji@newjeans.com",
            "role": "User"
        }
    ]
}
```

**Impact**
Meskipun field sensitif seperti `password` sudah difilter menggunakan `UserResource`, kebocoran **email** di sini menjadi informasi kunci yang memungkinkan serangan lanjutan (SQL Injection targeted, BOLA pada reset password).

**Fix**

- Hapus endpoint debugging sebelum rilis ke environment manapun selain local.
- Jika endpoint administratif serupa diperlukan, lindungi dengan middleware autentikasi + otorisasi role admin.

---

### 2. SQL Injection — Login

**Endpoint**: `POST /api/v1/auth/login`
**CWE**: CWE-89 (SQL Injection)

**Root Cause**

```php
$users = DB::select("SELECT * FROM users WHERE password = '$password' AND email = '$email'");
```

Query dibangun menggunakan raw string interpolation tanpa parameter binding. Validasi `email` menggunakan rule `email` yang ketat sehingga tidak dapat disisipi payload, namun validasi `password` hanya `required|string`, menjadikannya titik injeksi.

Contoh request:

```json
{
    "email": "siapapun@gmail.com",
    "password": "x' OR email='hanni@newjeans.com'-- "
}
```

Response:

```json
{
    "message": "Login success",
    "data": {
        "user": {
            "id": 1,
            "name": "Hanni Pham",
            "email": "hanni@newjeans.com",
            "role": "User"
        },
        "token_type": "Bearer",
        "token": "..."
    }
}
```

**Impact**
Full authentication bypass, attacker dapat login sebagai user manapun yang diketahui emailnya, tanpa mengetahui password sama sekali. Akses yang didapat **bersifat sementara** (lihat [#6](#6-forced-session-termination)).

**Fix**
Untuk fixing, bisa menggunakan Hash::check untuk mencocokan user tertentu dengan password hash yang tersimpan di database.

```php
$user = User::where('email', $email)->first();
if (!$user || !Hash::check($password, $user->password)) {
    throw new AuthException('Email atau password salah', 401);
}
```

---

### 3. Broken Object Level Authorization (BOLA) — Reset Password

**Endpoint**: `POST /api/v1/auth/reset-password` (dilindungi `auth:sanctum`)
**CWE**: CWE-639 (Authorization Bypass Through User-Controlled Key), API1:2023 BOLA

**Root Cause**

```php
public function resetPassword(User $user, array $data): array
{
    $userReset = User::where('email', $data['email'])->first();
    // tidak ada pengecekan $userReset->email === $user->email
    $userReset->update(['password' => $data['password']]);
}
```

Parameter `$user` (user yang login, dari Sanctum token) tidak pernah digunakan untuk memvalidasi kepemilikan akun yang ingin direset. Endpoint membutuhkan autentikasi (punya akun sendiri), namun tidak ada otorisasi berbasis kepemilikan objek.

**PoC**

1. Attacker register akun sendiri → dapat token.
2. `GET /test/users` → dapatkan email korban.
3. Reset password korban dengan email dari Zombie Endpoint.

```json
POST /api/v1/auth/reset-password
Authorization: Bearer <token_attacker>

{
  "email": "hanni@newjeans.com",
  "password": "passwordBaruAttacker123"
}
```

4. Password akun Hanni berhasil diubah oleh attacker, tanpa izin pemilik akun.

**Impact**
Full dan **persistent** account takeover, berbeda dengan SQLi yang aksesnya bisa hilang ([#6](#6-forced-session-termination)), di sini kredensial korban benar-benar berubah. Korban bahkan dapat ter-lock out dari akunnya sendiri.

**Fix**

Berikan exception jika email yang dikirim pada body request tidak sesuai dengan email pengirim request.

```php
throw_if($userReset->email !== $user->email, new AuthException(
    message: 'Tidak bisa mengubah akun orang lain',
    code: 403,
));
```

Atau, gunakan parameter user yang berisi informasi login dari user yang mengirim request

```php
#[Override]
public function resetPassword(User $user, array $data): array
{
    $user->update([
        'password' => Hash::make($data['password']),
    ]);

    return [
        'name' => $user->name,
        'email' => $user->email,
    ];
}
```

---

### 4. Security Misconfiguration — Verbose Error Message

**Endpoint**: `POST /api/v1/auth/login`
**CWE**: CWE-209 (Information Exposure Through an Error Message), API8:2023 Security Misconfiguration

**Deskripsi**
Saat `APP_DEBUG=true`, error SQL syntax yang dipicu payload SQLi ([#2](#2-sql-injection--login)) tidak valid ditampilkan lengkap ke client, termasuk query asli dan detail koneksi database.

**PoC**

Request:

```json
{ "email": "hanni@newjeans.com", "password": "password1234'" }
```

Response (`APP_DEBUG=true`):

```json
{
    "message": "SQLSTATE[42000]: Syntax error or access violation: 1064 ... (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: db_flawme, SQL: SELECT * FROM users WHERE password = 'password1234'' AND email = 'hanni@newjeans.com')"
}
```

Response (`APP_DEBUG=false`):

```json
{ "message": "Server Error" }
```

**Impact**
Error message menjadi sinyal bagi attacker untuk mengonfirmasi keberadaan SQL Injection (error-based detection) sebelum melakukan eksploitasi lebih lanjut, serta membocorkan detail infrastruktur backend (host, port, nama database).

**Fix**
Pastikan `APP_DEBUG=false` pada environment production/staging. Gunakan custom exception handler untuk mengembalikan pesan generik ke client sambil tetap mencatat detail error ke log internal.

---

### 5. Plaintext Password Storage

**Lokasi**: `users.password` (database), `App\Models\User`
**CWE**: CWE-256 (Plaintext Storage of a Password)

**Deskripsi**
Password disimpan dalam bentuk plaintext, tanpa hashing. Hal ini dilakukan agar proses login dapat berjalan normal melalui raw query (`WHERE password = '...'`), yang sekaligus membuat celah SQL Injection terdeteksi secara alami ketika user memasukkan karakter quote (`'`) ke dalam password.

**Impact**
Jika database bocor melalui jalur apa pun (SQLi lain, backup file exposure, dll), seluruh password user langsung dapat dibaca tanpa perlu proses cracking.

**Fix**

```php
protected function casts(): array
{
    return ['password' => 'hashed'];
}
```

Serta lakukan verifikasi password melalui `Hash::check()`, bukan perbandingan string langsung di query SQL.

---

### 6. Forced Session Termination

**Endpoint**: `POST /api/v1/auth/login`
**CWE**: CWE-613 (Insufficient Session Expiration — related)

**Root Cause**

```php
$user->tokens()->delete();
$token = $user->createToken('auth_token')->plainTextToken;
```

Setiap kali endpoint login berhasil dieksekusi (termasuk melalui SQL Injection), seluruh token aktif milik user tersebut dihapus dan diganti dengan token baru.

**Impact (dua arah)**

- **Mitigasi tidak disengaja**: Token yang didapat attacker melalui SQL Injection ([#2](#2-sql-injection--login)) bersifat sementara, begitu korban login kembali secara normal, token attacker otomatis invalid. Hal ini memaksa attacker yang menginginkan akses permanen untuk melakukan chaining ke BOLA reset password ([#3](#3-broken-object-level-authorization-bola--reset-password)).
- **Side-effect negatif**: Ketika attacker mengeksploitasi SQLi terhadap korban, seluruh sesi aktif korban (termasuk di device lain) ikut ter-invalidate secara paksa tanpa notifikasi, berpotensi menjadi indikator tidak langsung bahwa akunnya sedang diserang, namun juga mengganggu pengalaman pengguna yang sah.

---

## Attack Chaining

Vulnerability di atas tidak berdiri sendiri — beberapa dapat dirangkai untuk mencapai dampak yang lebih besar (account takeover).

```
┌─────────────────────────┐
│  GET /test/users        │  → Membocorkan daftar email seluruh user
└───────────┬─────────────┘
            │
            ▼
   ┌────────────────┐           ┌──────────────────────────┐
   │  Jalur A: SQLi │           │  Jalur B: BOLA           │
   │  (temporary)   │           │  (persistent)            │
   └────────┬───────┘           └────────────┬─────────────┘
            │                                │
  POST /login                      1. POST /register (akun attacker)
  password: "x' OR email=          2. POST /reset-password
  'korban@mail.com'-- "                   (pakai token attacker,
            │                             target email korban)
            ▼                                 │
  Token korban didapat                        ▼
  (hilang saat korban              Password korban berubah permanen
   login ulang)                    3. POST /login (kredensial baru)
                                             │
                                             ▼
                                   Token korban didapat (persisten)
```

**Catatan**: Dua jalur ini bersifat independen namun saling melengkapi. Jika SQL Injection pada login diperbaiki, BOLA pada reset-password tetap menjadi jalur eksploitasi yang valid, dan sebaliknya. Token hasil SQLi (Jalur A) bahkan dapat digunakan sebagai pengganti langkah 1 pada Jalur B (tidak perlu register akun sendiri), mempercepat proses menuju persistent takeover.
