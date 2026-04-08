=== DOKUMENTASI ===

WEBSITE E-COMMERCE MENGGUNAKAN NATIVE PHP
FILE DATABASE: database/ilham.sql
NAMA DATABASE: ecommerce_ilham_enh

STRUKTUR FOLDER:
├── Admin/          → Halaman panel admin (dashboard, kelola data)
├── Auth/           → Halaman autentikasi (login, logout)
├── Petugas/        → Halaman panel petugas (dashboard, kelola data)
├── User/           → Halaman panel user (produk, keranjang, checkout)
├── config/         → Konfigurasi (database.php)
├── helpers/        → Helper functions (cart.php)
├── database/       → File SQL dump (ilham.sql)
├── uploads/        → File upload (gambar produk)
│   └── products/   → Gambar produk
├── backups/        → Folder backup database
├── index.php       → Halaman utama (katalog publik)
└── README.md       → Dokumentasi

KREDENSIAL LOGIN:
--- ADMIN
Email: admin@ecommerce.com
Password: password

--- PETUGAS
Email: petugas@ecommerce.com
Password: password

--- USER
Email: user@ecommerce.com
Password: password

CARA IMPORT DATABASE KE PHPMYADMIN

--- CARA PERTAMA
1. BUKA XAMPP, NYALAKAN APACHE & MYSQL
2. PERGI KE: localhost/phpmyadmin
3. BUAT DATABASE: ecommerce_ilham_enh
4. PERGI KE TAB IMPORT
5. KLIK PADA 'Choose File'
6. UNGGAH FILE 'database/ilham.sql'

--- CARA KEDUA
1. BUKA XAMPP, NYALAKAN APACHE & MYSQL
2. PERGI KE: localhost/phpmyadmin
3. BUAT DATABASE: ecommerce_ilham_enh
4. PERGI KE TAB SQL
5. SALIN KODE DARI FILE 'database/ilham.sql'
6. TEMPELKAN DI TAB SQL
7. KLIK PADA 'GO'

=== DOKUMENTASI ===