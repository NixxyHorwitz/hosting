---
description: Panduan arsitektur & routing sistem Hosting untuk AI Agent
---
# Panduan Arsitektur & Aturan Codebase Web Hosting

Dokumen ini adalah aturan wajib (*mandatory rules*) bagi AI agent manapun yang memodifikasi atau menganalisis *codebase* hosting. Terapkan aturan ini untuk menghemat _token inference_ dan menghindari *fatal error* akibat kesalahan _pathing_.

## 1. Konsep Single Entry Point (Root Routing)
Sistem ini menggunakan arsitektur *Single Entry Point*. Semua request HTTP dialirkan oleh `.htaccess` menuju file **`index.php`** di *root directory*. 
- **Skema routing:** URL `/feature/action` akan diproses sebagai `index.php?route=feature/action`.
- Jika URL merujuk ke `/hosting`, maka router akan mencari file `hosting/index.php`. File inilah yang menjadi *Dashboard* utama dari _User/Client_.
- Jika URL merujuk ke `/hosting/services`, maka router memanggil file `hosting/services.php` (halaman pemesanan layanan).

## 2. WAJIB: Aturan Absolut "Path Inclusion"
Karena semua file pada dasarnya dieksekusi dari *root directory* (oleh `index.php`), maka penggunaan _relative path_ telanjang (seperti `require '../config/database.php';`) **PASTI AKAN ERROR**.

- **JANGAN PERNAH** menggunakan metode include/require ini:
  ```php
  // ❌ SALAH (Akan memicu Fatal Error Path)
  include '../library/header.php';
  require_once '../../config/database.php';
  ```
- **SELALU GUNAKAN** `__DIR__` untuk setiap `include` atau `require` di dalam sub-folder mana pun:
  ```php
  // ✅ BENAR
  include __DIR__ . '/../library/header.php';
  require_once __DIR__ . '/../../config/database.php';
  ```

## 3. Aturan Resolusi URL & Frontend Redirects
Sistem menghindari pemakaian kata `index` di dalam URL bersih untuk User Frontend. Ikuti struktur URL berikut saat memanipulasi *redirect* (`header("Location: ...")`) dan link di dalam HTML (`href="..."`):
- **Client Dashboard:** `base_url('hosting')` atau `/hosting` (bukan `/hosting/index` atau `/hosting/dashboard`).
- **Pesan Layanan / Services:** `base_url('hosting/services')` atau `/hosting/services`.
- **System Tickets:** `base_url('hosting/tickets')` untuk list tiket, dan `base_url('hosting/tickets/create')` untuk form tiket baru. (HARUS memiliki *prefix* `/hosting/`, jangan tertukar dengan `/tickets` langsung di root).
- **Admin Area:** Selalu awali dengan `/admin/`. Contoh: `/admin`, `/admin/login`, `/admin/tickets`.

## 4. Keamanan Session (DRY)
Jangan menuliskan logika session proteksi manual (contoh: mengecek `$_SESSION['admin_id']` lalu redirect) secara berulang-ulang di bawah tag `<?php`.
- **Admin Module:** Panggil via `require_once __DIR__ . '/library/admin_session.php';`.
- **Client Module:** Session dikoordinasikan secara cerdas, pastikan logika redirect *unauthorized* user membuang user ke `/auth/login` dan setelah auth valid ke `/hosting`.

## 5. Menghindari "Cannot Redeclare Error"
Ketika memasukkan script dari `/core/` seperti `api_helper.php` atau `mailer.php`, jangan pernah menggunakan `include`.
Selalu gunakan **`require_once`** agar mencegah *function redeclaration fatal error* akibat inklusi berulang saat dimuat berantai dari struktur layout dan routing utama.

## 6. Aturan Parameter URL Bersih (Clean URLs)
Sistem *Single Entry Point* telah dikonfigurasi untuk secara otomatis mengonversi struktur URL bersegmen menjadi paramater PHP.
**JANGAN PERNAH** merender tautan dengan *Parameter Query mentah (Raw Query Params)* seperti `?id=123`.

- **SALAH:** `base_url('hosting/invoice?id=8')`
- **BENAR:** `base_url('hosting/invoice/8')`

Sistem secara otomatis akan mencerna segment pertama di belakang path routing utama sebagai `$_GET['id']`.
Demikian halnya untuk segala jenis interaksi klik yang diarahkan kepada pengguna. Pastikan struktur URL bersih, rapi, dan sesuai standar Single Entry Point.
