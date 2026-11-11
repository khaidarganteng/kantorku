# Kantorku Imut (PHP Native + Supabase + Vercel) 🌸
Asisten kantor siswa MPLB: surat-menyurat, pengarsipan, telepon, pelayanan pelanggan, agenda pimpinan,
kas kecil, tugas & pengingat. PHP native tanpa framework, database online Supabase (Postgres),
berjalan di Vercel lewat runtime komunitas `vercel-php`, dan nyaman dipakai di HP.

## 1. Database online (Supabase, gratis)
1. Buat project di https://supabase.com (catat kata sandi database).
2. Klik tombol **Connect** di dashboard → pilih **Transaction pooler** (port 6543).
   Dari situ ambil: Host (…pooler.supabase.com), User (postgres.XXXX), Database (postgres).
   Pakai pooler, bukan direct connection, karena Vercel membutuhkan koneksi IPv4.

## 2. Deploy ke Vercel
1. Upload folder ini ke repo GitHub (atau pakai `npm i -g vercel` lalu `vercel`).
2. Import repo di https://vercel.com/new (Framework Preset: Other).
3. Isi Environment Variables sesuai `.env.example`
   (APP_SECRET = teks acak panjang, ADMIN_EMAIL dan ADMIN_PASSWORD = akun login kamu).
4. Deploy, lalu buka sekali: `https://NAMA-APP.vercel.app/?p=install&key=ISI_APP_SECRET`
   → tabel dibuat otomatis. Setelah itu buka halaman utama dan login.

## Akun dan pendaftaran
- Halaman daftar: `/?p=daftar`. Tiap akun punya data sendiri, tidak saling terlihat.
- Saat install, satu akun dibuat otomatis dari `ADMIN_EMAIL` dan `ADMIN_PASSWORD`.
- Setelah semua teman mendaftar, isi `ALLOW_REGISTER=0` di Vercel lalu Redeploy untuk menutup pendaftaran.
- Kalau memperbarui dari versi lama, buka lagi `/?p=install&key=APP_SECRET` (aman diulang).

## 3. Pakai di HP
Buka link Vercel di Chrome/Safari → menu browser → **Tambah ke Layar Utama**. Tampil seperti aplikasi.
Nomor telepon di modul Telepon bisa langsung diketuk untuk menelepon.

## Jalankan lokal (opsional)
Butuh PHP 8.1+ dengan ekstensi pdo_pgsql. Atur env var lalu: `php -S localhost:8000 api/index.php`

## Catatan
- Login memakai cookie bertanda tangan (Vercel tidak menyimpan session PHP). Pakai kata sandi yang kuat.
- Tambah modul baru: tambah blok di `inc/modul.php`, deploy ulang, buka lagi URL install.
- Runtime `vercel-php` adalah proyek komunitas (bukan resmi Vercel), jadi cek versinya jika ada pembaruan.
