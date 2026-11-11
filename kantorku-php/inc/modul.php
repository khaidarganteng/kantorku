<?php
// Format field: [nama_kolom, label, tipe(text|textarea|date|time|number|select), opsi_select, opsional?]
// Semua modul wajib punya kolom 'tgl'. Tambah modul baru cukup dengan menambah blok di sini,
// lalu buka lagi /?p=install&key=APP_SECRET untuk membuat tabelnya.
return [
 'surat' => ['judul'=>'Surat-Menyurat','e'=>'💌','main'=>'perihal','chips'=>['status'],'selesai'=>['status','Selesai'],'fields'=>[
  ['jenis','Jenis Surat','select',['Surat Masuk','Surat Keluar']],
  ['nomor','Nomor Surat (kosongkan = otomatis)','text',null,true],
  ['tgl','Tanggal','date'],
  ['pihak','Pengirim / Tujuan','text'],
  ['perihal','Perihal','text'],
  ['lampiran','Lampiran (misal: 1 berkas)','text',null,true],
  ['isi','Isi Surat','textarea',null,true],
  ['status','Status','select',['Baru','Diproses','Selesai']],
 ]],
 'arsip' => ['judul'=>'Pengarsipan','e'=>'🗂️','main'=>'nama','chips'=>['kategori'],'fields'=>[
  ['nama','Nama Dokumen','text'],
  ['kode','Kode Arsip','text'],
  ['kategori','Kategori','select',['Surat Masuk','Surat Keluar','Laporan','Keuangan','Lainnya']],
  ['tgl','Tanggal Arsip','date'],
  ['lokasi','Lokasi Simpan (rak/map)','text',null,true],
 ]],
 'telepon' => ['judul'=>'Telepon','e'=>'📞','main'=>'nama','chips'=>['arah','status'],'selesai'=>['status','Selesai'],'fields'=>[
  ['nama','Nama Penelepon / Dituju','text'],
  ['no','Nomor Telepon','text'],
  ['arah','Arah','select',['Masuk','Keluar']],
  ['tgl','Tanggal','date'],
  ['pesan','Pesan / Keperluan','textarea'],
  ['status','Status','select',['Menunggu','Selesai']],
 ]],
 'layanan' => ['judul'=>'Pelayanan Pelanggan','e'=>'🛎️','main'=>'nama','chips'=>['jenis','status'],'selesai'=>['status','Selesai'],'fields'=>[
  ['nama','Nama Pelanggan','text'],
  ['jenis','Jenis','select',['Pertanyaan','Keluhan','Permintaan']],
  ['tgl','Tanggal','date'],
  ['isi','Isi Layanan','textarea'],
  ['solusi','Solusi / Respon','textarea',null,true],
  ['status','Status','select',['Baru','Diproses','Selesai']],
 ]],
 'agenda' => ['judul'=>'Agenda Pimpinan','e'=>'📅','main'=>'kegiatan','chips'=>['status'],'urut'=>'asc','selesai'=>['status','Selesai'],'fields'=>[
  ['kegiatan','Kegiatan','text'],
  ['tgl','Tanggal','date'],
  ['jam','Jam','time'],
  ['tempat','Tempat','text',null,true],
  ['peserta','Peserta / Catatan','text',null,true],
  ['status','Status','select',['Terjadwal','Selesai','Batal']],
 ]],
 'kas' => ['judul'=>'Kas Kecil','e'=>'💰','main'=>'ket','chips'=>['jenis'],'fields'=>[
  ['jenis','Jenis','select',['Pemasukan','Pengeluaran']],
  ['tgl','Tanggal','date'],
  ['ket','Keterangan','text'],
  ['jumlah','Jumlah (Rp)','number'],
 ]],
 'tugas' => ['judul'=>'Tugas & Pengingat','e'=>'✅','main'=>'tugas','chips'=>['prioritas','status'],'urut'=>'asc','selesai'=>['status','Selesai'],'fields'=>[
  ['tugas','Tugas','text'],
  ['tgl','Tenggat','date'],
  ['prioritas','Prioritas','select',['Rendah','Sedang','Tinggi']],
  ['catatan','Catatan','text',null,true],
  ['status','Status','select',['Belum','Selesai']],
 ]],
];
