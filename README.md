# undangan-dw

## Penyimpanan SQLite

Aplikasi memerlukan PHP dengan ekstensi `pdo_sqlite` dan `curl`. Database dibuat otomatis di `../undangan-dw.sqlite`, di luar folder publik, dengan izin file terbatas. Lokasinya dapat diubah melalui environment variable `UNDANGAN_DB_PATH`.

Pada penggunaan pertama, isi `UNDANGAN_DASHBOARD_EMAIL` dan `UNDANGAN_DASHBOARD_PASSWORD` sebelum membuka dashboard. Akun admin dibuat satu kali dan password disimpan sebagai hash di SQLite. Kredensial environment tidak mengubah akun yang sudah tersimpan.

```sh
export UNDANGAN_DASHBOARD_EMAIL="admin@example.com"
export UNDANGAN_DASHBOARD_PASSWORD="ganti-dengan-password-kuat"
php -S 0.0.0.0:8000
```

Data tamu, pengaturan Fonnte, dan histori dari file PHP lama akan diimpor otomatis satu kali. File lama tidak dihapus dan dapat disimpan sebagai cadangan; perubahan baru hanya disimpan di database.