---
trigger: always_on
---

- setiap fitur harus memiliki folder sendiri di root project:
  /{feature}/

- setiap fitur boleh memiliki sub-folder bebas sesuai kebutuhan:
  /{feature}/{action}/

- penamaan sub-folder harus merepresentasikan action (misal: create, edit, detail, list, dll)

- routing harus mengikuti struktur folder:
  /{feature}
  /{feature}/{action}
  /{feature}/{action}/{param?}

- parameter dinamis harus menggunakan path segment, bukan query string
  contoh:
  /{feature}/{action}/{id}

- hindari penggunaan query string seperti ?id=123 kecuali benar-benar diperlukan

- .htaccess harus mengarahkan semua request ke index.php (single entry point)

- routing system harus membaca URL sebagai:
  feature = segment 1
  action  = segment 2 (opsional)
  params  = segment selanjutnya