# undangan-dw

## Penyimpanan SQLite

Aplikasi memerlukan PHP dengan ekstensi `pdo_sqlite` dan `curl`. Database dibuat otomatis di `../undangan-dw.sqlite`, di luar folder publik, dengan izin file terbatas. Lokasinya dapat diubah melalui environment variable `UNDANGAN_DB_PATH`.

Pada penggunaan pertama, buka dashboard untuk membuat akun admin. Akun hanya dapat dibuat sekali dan password disimpan sebagai hash di SQLite. Sebagai alternatif, akun pertama dapat dibuat sebelum dashboard dibuka dengan mengisi `UNDANGAN_DASHBOARD_EMAIL` dan `UNDANGAN_DASHBOARD_PASSWORD`; kredensial environment tidak mengubah akun yang sudah tersimpan.

```sh
export UNDANGAN_DASHBOARD_EMAIL="admin@example.com"
export UNDANGAN_DASHBOARD_PASSWORD="ganti-dengan-password-kuat"
export UNDANGAN_BASE_URL="https://undangan.example.com"
php -S 0.0.0.0:8000
```

`UNDANGAN_BASE_URL` adalah alamat publik HTTPS untuk undangan, termasuk awalan path bila dipasang di subdirektori. Broadcast tidak akan aktif jika alamat tepercaya ini tidak dikonfigurasi; tautan undangan tidak dibentuk dari header `Host` permintaan.

Data tamu, pengaturan Fonnte, dan histori dari file PHP lama akan diimpor otomatis satu kali. File lama tidak dihapus; perubahan baru hanya disimpan di database. Jangan simpan token Fonnte di source control. Gunakan variabel `FONNTE_TOKEN` atau penyimpanan dashboard. Karena token pernah berada di berkas pengaturan lama, cabut token tersebut di Fonnte dan atur token pengganti sebelum mengirim pesan.