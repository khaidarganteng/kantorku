<?php
require __DIR__ . '/../inc/app.php';

$p = $_GET['p'] ?? 'home';
$m = $_GET['m'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

/* Manifest agar bisa dipasang ke layar utama HP */
if ($p === 'manifest') {
    header('Content-Type: application/manifest+json');
    echo json_encode(['name' => 'Kantorku Imut', 'short_name' => 'Kantorku', 'start_url' => '/', 'display' => 'standalone',
        'background_color' => '#fff5f9', 'theme_color' => '#ff8fb8',
        'icons' => [['src' => "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🌸%3C/text%3E%3C/svg%3E", 'sizes' => 'any', 'type' => 'image/svg+xml']]]);
    exit;
}

/* Instalasi tabel (sekali saja): /?p=install&key=APP_SECRET */
if ($p === 'install') {
    if (env('APP_SECRET') === '' || !hash_equals(env('APP_SECRET'), (string)($_GET['key'] ?? ''))) { http_response_code(403); exit('Kunci salah.'); }
    try { install($MODUL); exit('Tabel siap ✅ Sekarang buka halaman utama untuk masuk.'); }
    catch (Throwable $e) { http_response_code(500); exit('Gagal terhubung ke database: ' . h($e->getMessage())); }
}

/* Login & daftar */
if ($p === 'login' || $p === 'daftar') {
    $daftar = $p === 'daftar';
    $boleh = env('ALLOW_REGISTER', '1') === '1';
    if ($daftar && !$boleh) go('/?p=login');
    $err = '';
    if ($post) {
        try {
            siapkan($MODUL);
            $email = strtolower(trim((string)($_POST['email'] ?? ''))); $pw = (string)($_POST['password'] ?? '');
            if ($daftar) {
                $nama = trim((string)($_POST['nama'] ?? ''));
                if ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama wajib diisi (maksimal 100 huruf).';
                elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $err = 'Format email belum benar.';
                elseif (strlen($pw) < 8) $err = 'Kata sandi minimal 8 karakter.';
                elseif ($pw !== (string)($_POST['password2'] ?? '')) $err = 'Konfirmasi kata sandi tidak sama.';
                else {
                    $st = db()->prepare('SELECT 1 FROM users WHERE email=:e'); $st->execute([':e' => $email]);
                    if ($st->fetchColumn()) { $err = 'Email itu sudah terdaftar. Silakan masuk.'; }
                    else {
                        $st = db()->prepare('INSERT INTO users (nama, email, password_hash) VALUES (:n, :e, :p) RETURNING id');
                        $st->execute([':n' => $nama, ':e' => $email, ':p' => password_hash($pw, PASSWORD_DEFAULT)]);
                        set_login((int)$st->fetchColumn()); go('/');
                    }
                }
            } else {
                $st = db()->prepare('SELECT id, password_hash FROM users WHERE email=:e'); $st->execute([':e' => $email]);
                $u = $st->fetch();
                if ($u && password_verify($pw, $u['password_hash'])) { set_login((int)$u['id']); go('/'); }
                $err = 'Email atau kata sandi salah.';
            }
        } catch (Throwable $e) { $err = 'Database belum siap: ' . sebab($e); }
    }
    echo head($daftar ? 'Daftar' : 'Masuk'), '<div class="wrap login"><div class="hero" style="text-align:center"><div style="font-size:48px">🌸</div><h2>Kantorku Imut</h2><div>',
      $daftar ? 'Buat akun barumu, gratis!' : 'Masuk dulu ya, calon sekretaris hebat!', '</div></div><div class="card">',
      $err ? '<div class="err">' . h($err) . '</div>' : '',
      '<form method="post" action="/?p=', $daftar ? 'daftar' : 'login', '">',
      $daftar ? '<label for="nama"><b>Nama</b></label><input id="nama" name="nama" required maxlength="100" autocomplete="name" value="' . h($_POST['nama'] ?? '') . '"><div style="height:8px"></div>' : '',
      '<label for="email"><b>Email</b></label><input id="email" name="email" type="email" required autocomplete="email" value="' . h($_POST['email'] ?? '') . '">',
      '<label for="pw" style="display:block;margin-top:8px"><b>Kata sandi</b></label><input id="pw" name="password" type="password" required autocomplete="' . ($daftar ? 'new-password' : 'current-password') . '"' . ($daftar ? ' minlength="8"' : '') . '>',
      $daftar ? '<label for="pw2" style="display:block;margin-top:8px"><b>Ulangi kata sandi</b></label><input id="pw2" name="password2" type="password" required minlength="8" autocomplete="new-password">' : '',
      '<button class="btn" style="width:100%;margin-top:12px">', $daftar ? 'Daftar 🎀' : 'Masuk 💖', '</button></form>',
      '<p class="ln" style="text-align:center;margin:12px 0 0">', $daftar ? 'Sudah punya akun? <a href="/?p=login"><b>Masuk</b></a>' : ($boleh ? 'Belum punya akun? <a href="/?p=daftar"><b>Daftar</b></a>' : ''), '</p></div></div></body></html>';
    exit;
}
if ($p === 'keluar' && $post) { clear_login(); go('/?p=login'); }
if (!login_ok()) go('/?p=login');

try {
    $pdo = db(); $uid = uid();

    if (in_array($p, ['modul', 'cetak'], true)) {
        if (!isset($MODUL[$m])) { http_response_code(404); exit('Modul tidak ditemukan.'); }
        $c = $MODUL[$m];
    }

    /* Cetak bentuk surat resmi (mengikuti contoh surat: kop berlogo, Yth., Perihal, isi rata kiri-kanan, tanda tangan di kanan) */
    if ($p === 'surat') {
        $st = $pdo->prepare('SELECT * FROM surat WHERE id=:i AND user_id=:u'); $st->execute([':i' => (int)($_GET['id'] ?? 0), ':u' => $uid]);
        $r = $st->fetch();
        if (!$r) { http_response_code(404); exit('Surat tidak ditemukan.'); }
        $bln = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $ts = strtotime($r['tgl'] ?: 'now');
        $tglPanjang = (int)date('j', $ts) . ' ' . $bln[(int)date('n', $ts)] . ' ' . date('Y', $ts);
        $teks = ['k_nama' => 'Nama instansi (kop surat)', 'k_alamat' => 'Alamat instansi', 'k_telp' => 'Telepon dan Fax', 'k_email' => 'Email dan Website', 'k_kota' => 'Kota (untuk tanggal surat)', 't_nama' => 'Nama penandatangan (beserta gelar)', 't_jab' => 'Jabatan penandatangan'];
        $tujuan = trim((string)($r['tujuan'] ?? ''));
        $yth = $tujuan !== '' ? $tujuan : (string)($r['pihak'] ?? '');
        $isi = trim((string)($r['isi'] ?? '')); if ($isi === '') $isi = 'Dengan hormat,';
        $isiHtml = '';
        foreach (preg_split('/\R{2,}/u', $isi) as $b) {
            $rows = []; $kv = true;
            foreach (preg_split('/\R/u', $b) as $l) {
                if (preg_match('/^\s*([^:]{1,24}?)\s*:\s*(.+)$/u', $l, $mm)) $rows[] = [$mm[1], $mm[2]]; else { $kv = false; break; }
            }
            if ($kv && count($rows) >= 2) {
                $isiHtml .= '<table class="kv">';
                foreach ($rows as $x) $isiHtml .= '<tr><td>' . h($x[0]) . '</td><td>: ' . h($x[1]) . '</td></tr>';
                $isiHtml .= '</table>';
            } else { $isiHtml .= '<p>' . nl2br(h($b)) . '</p>'; }
        }
        $css = <<<'KSS'
.kertas{background:#fff;color:#000;width:100%;max-width:794px;margin:0 auto;padding:48px 56px;font:12pt/1.4 Aptos,Calibri,"Segoe UI",Arial,sans-serif;border:1px solid #ccc;text-align:justify}
.kop{position:relative;text-align:center;border-bottom:3px double #000;padding:0 96px 8px;margin-bottom:14px;min-height:80px}
.kop #img_logo{position:absolute;left:0;top:0;height:84px;max-width:90px;object-fit:contain}
.kop b{font-size:13pt;text-transform:uppercase}
.baris{display:flex;justify-content:space-between;gap:12px;margin-bottom:12px;text-align:left}
.yth{margin-bottom:12px;text-align:left}
.perihal{text-align:center;margin:0 0 12px}
.kertas p{margin:0 0 10px}
.kertas table.kv{width:auto;margin:0 0 10px 36px;border-collapse:collapse;font-size:inherit}
.kertas .kv td{border:0;padding:1px 8px 1px 0;font-size:inherit;vertical-align:top}
.ttd{margin:28px 0 0 58%;text-align:left}
.sp{height:80px}.sp img{max-height:76px;max-width:170px}
.fl{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center}
@media print{@page{size:A4;margin:20mm}.kertas{border:0;padding:0;max-width:none}.wrap{padding:0;max-width:none}}
KSS;
        $js = <<<'KJS'
function kota(){var k=document.getElementById("k_kota").textContent;document.getElementById("tgl").textContent=(k?k+", ":"")+D}
function pasang(k,d){var el=document.getElementById("img_"+k);if(d){el.src=d;el.style.display="block"}else{el.removeAttribute("src");el.style.display="none"}}
function simpan(k,el){document.getElementById(k).textContent=el.value;try{localStorage.setItem(k,el.value)}catch(e){}if(k==="k_kota")kota()}
function gambar(k,inp){var f=inp.files[0];if(!f)return;var rd=new FileReader();rd.onload=function(){var im=new Image();im.onload=function(){var s=Math.min(1,300/Math.max(im.width,im.height)),c=document.createElement("canvas");c.width=Math.round(im.width*s);c.height=Math.round(im.height*s);c.getContext("2d").drawImage(im,0,0,c.width,c.height);var d=c.toDataURL("image/png");pasang(k,d);try{localStorage.setItem("img_"+k,d)}catch(e){}};im.src=rd.result};rd.readAsDataURL(f)}
function hapus(k){pasang(k,"");try{localStorage.removeItem("img_"+k)}catch(e){}}
F.forEach(function(k){var v="";try{v=localStorage.getItem(k)||""}catch(e){}document.getElementById("i_"+k).value=v;document.getElementById(k).textContent=v});
["logo","ttd"].forEach(function(k){var d="";try{d=localStorage.getItem("img_"+k)||""}catch(e){}pasang(k,d)});
kota();
KJS;
        echo head('Cetak Surat'), '<style>', $css, '</style><div class="wrap">';
        echo '<div class="noprint card" style="margin-bottom:12px"><b>Data kop dan tanda tangan</b> <span class="ln">(tersimpan di browser ini, cukup diisi sekali)</span>';
        foreach ($teks as $k => $lbl) echo '<label class="ln" style="display:block;margin-top:6px">', h($lbl), '</label><input id="i_', $k, '" oninput="simpan(\'', $k, '\',this)">';
        foreach (['logo' => 'Logo instansi (gambar)', 'ttd' => 'Gambar tanda tangan (opsional)'] as $k => $lbl)
            echo '<label class="ln" style="display:block;margin-top:6px">', h($lbl), '</label><div class="fl"><input type="file" accept="image/*" onchange="gambar(\'', $k, '\',this)"><button type="button" class="btn g" onclick="hapus(\'', $k, '\')">Hapus</button></div>';
        echo '<div class="act" style="margin-top:12px"><button class="btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button><a href="/?p=modul&m=surat">‹ Kembali</a></div></div>';
        echo '<div class="kertas"><div class="kop"><img id="img_logo" alt="" style="display:none"><b id="k_nama"></b><div id="k_alamat"></div><div id="k_telp"></div><div id="k_email"></div></div>',
          '<div class="baris"><span>Nomor : ', h($r['nomor'] ?? ''), '</span><span id="tgl"></span></div>',
          !empty($r['lampiran']) ? '<div style="margin-bottom:8px;text-align:left">Lampiran : ' . h($r['lampiran']) . '</div>' : '',
          '<div class="yth">Yth.<br>', nl2br(h($yth)), '</div>',
          '<p class="perihal">Perihal : ', h($r['perihal'] ?? ''), '</p>',
          $isiHtml,
          '<div class="ttd">Hormat kami,<div class="sp"><img id="img_ttd" alt="" style="display:none"></div><div id="t_nama"></div><div id="t_jab"></div></div></div></div>',
          '<script>const F=', json_encode(array_keys($teks)), ',D=', json_encode($tglPanjang), ';', $js, '</script></body></html>';
        exit;
    }

    /* Aksi tulis (POST). Cookie SameSite=Lax menahan pengiriman form dari situs lain (proteksi CSRF) */
    if ($p === 'modul' && $post) {
        $a = $_POST['aksi'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        $to = (($_POST['back'] ?? '') === 'home') ? '/?' : '/?p=modul&m=' . $m . '&';
        if ($a === 'hapus') {
            $pdo->prepare('DELETE FROM ' . q($m) . ' WHERE id=:i AND user_id=:u')->execute([':i' => $id, ':u' => $uid]);
            go($to . 'ok=hapus');
        }
        if ($a === 'selesai' && isset($c['selesai'])) {
            $pdo->prepare('UPDATE ' . q($m) . ' SET ' . q($c['selesai'][0]) . '=:v WHERE id=:i AND user_id=:u')->execute([':v' => $c['selesai'][1], ':i' => $id, ':u' => $uid]);
            go($to . 'ok=selesai');
        }
        if ($a === 'simpan') {
            [$d, $e] = cek($c, $_POST);
            if ($e) go('/?p=modul&m=' . $m . '&err=' . urlencode($e));
            if ($m === 'surat' && empty($d['nomor'])) $d['nomor'] = nomorSurat($d);
            if ($id) {
                $set = implode(', ', array_map(fn($k) => q($k) . '=:' . $k, array_keys($d)));
                $sql = 'UPDATE ' . q($m) . ' SET ' . $set . ' WHERE id=:__id AND user_id=:__u'; $d['__id'] = $id; $d['__u'] = $uid;
            } else {
                $d['user_id'] = $uid;
                $sql = 'INSERT INTO ' . q($m) . ' (' . implode(', ', array_map('q', array_keys($d))) . ') VALUES (' . implode(', ', array_map(fn($k) => ':' . $k, array_keys($d))) . ')';
            }
            $pdo->prepare($sql)->execute(array_combine(array_map(fn($k) => ':' . $k, array_keys($d)), array_values($d)));
            go('/?p=modul&m=' . $m . '&ok=simpan');
        }
        go('/?p=modul&m=' . $m);
    }

    /* Cetak laporan */
    if ($p === 'cetak') {
        $rows = $pdo->query('SELECT * FROM ' . q($m) . ' WHERE user_id=' . $uid . ' ORDER BY tgl ' . (($c['urut'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC') . ', id DESC')->fetchAll();
        echo head('Cetak ' . $c['judul']), '<div class="wrap"><button class="btn noprint" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>',
          '<h2>Laporan ', h($c['judul']), '</h2><div class="ln">Dicetak: ', date('d/m/Y H:i'), ' WIB</div><div style="overflow-x:auto"><table><tr><th>No</th>';
        foreach ($c['fields'] as $f) echo '<th>', h($f[1]), '</th>';
        echo '</tr>';
        foreach ($rows as $i => $r) {
            echo '<tr><td>', $i + 1, '</td>';
            foreach ($c['fields'] as $f) echo '<td>', h($f[2] === 'number' ? rp($r[$f[0]]) : ($f[2] === 'date' ? tgl($r[$f[0]]) : $r[$f[0]])), '</td>';
            echo '</tr>';
        }
        echo '</table></div>';
        if ($m === 'kas') { [$i, $o, $s] = ringkasKas(); echo '<p><b>Pemasukan:</b> ', rp($i), ' · <b>Pengeluaran:</b> ', rp($o), ' · <b>Saldo:</b> ', rp($s), '</p>'; }
        echo '</div></body></html>';
        exit;
    }

    /* Daftar modul */
    if ($p === 'modul') {
        $pg = max(1, (int)($_GET['h'] ?? 1)); $per = 12; $cari = trim((string)($_GET['q'] ?? ''));
        $w = ' WHERE user_id=' . $uid; $par = [];
        if ($cari !== '') {
            $or = [];
            foreach ($c['fields'] as $i => $f) { $or[] = q($f[0]) . '::text ILIKE :q' . $i; $par[':q' . $i] = '%' . $cari . '%'; }
            $w .= ' AND (' . implode(' OR ', $or) . ')';
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM ' . q($m) . $w); $st->execute($par); $total = (int)$st->fetchColumn();
        $st = $pdo->prepare('SELECT * FROM ' . q($m) . $w . ' ORDER BY tgl ' . (($c['urut'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC') . ', id DESC LIMIT ' . $per . ' OFFSET ' . (($pg - 1) * $per));
        $st->execute($par); $rows = $st->fetchAll();
        $qs = '/?p=modul&m=' . $m . ($cari !== '' ? '&q=' . urlencode($cari) : '');

        ob_start(); ?>
<div class="bar"><h2><?= $c['e'] ?> <?= h($c['judul']) ?></h2>
 <form class="s" method="get" action="/"><input type="hidden" name="p" value="modul"><input type="hidden" name="m" value="<?= h($m) ?>"><input name="q" value="<?= h($cari) ?>" placeholder="Cari…"></form>
 <button class="btn" type="button" onclick="buka()">＋ Tambah</button>
 <a class="btn g" href="/?p=cetak&m=<?= h($m) ?>" target="_blank">🖨️ Cetak</a></div>
<?php if ($m === 'kas') { [$i, $o, $s] = ringkasKas(); ?>
<div class="sum"><div style="background:var(--c0)">Pemasukan<b><?= rp($i) ?></b></div><div style="background:var(--pink2)">Pengeluaran<b><?= rp($o) ?></b></div><div style="background:var(--c2)">Saldo<b><?= rp($s) ?></b></div></div>
<?php } ?>
<div class="grid"><?php if (!$rows) echo '<div class="empty"><div class="em">🧸</div>Belum ada data. Yuk tambah yang pertama! ✨</div>';
 foreach ($rows as $r) echo kartu($m, $c, $r); ?></div>
<div class="pg"><?php if ($pg > 1) echo '<a class="btn g" href="', $qs, '&h=', $pg - 1, '">‹ Sebelumnya</a>'; ?>
 <span>Hal. <?= $pg ?> / <?= max(1, (int)ceil($total / $per)) ?></span>
 <?php if ($pg * $per < $total) echo '<a class="btn g" href="', $qs, '&h=', $pg + 1, '">Berikutnya ›</a>'; ?></div>
<dialog id="dlg"><form method="post" id="frm" action="/?p=modul&m=<?= h($m) ?>">
 <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" id="rid" value="0">
 <h3><?= $c['e'] ?> <?= h($c['judul']) ?></h3>
 <?php if ($m === 'surat'): ?>
  <label for="tpl">Template surat (opsional)</label>
  <select id="tpl" onchange="isiTpl(this.value)"><option value="">– pilih template –</option><option>Undangan</option><option>Pemberitahuan</option><option>Permohonan</option><option>Surat Keterangan</option><option>Surat Tugas</option><option>Permohonan Kerja Sama</option><option>Persetujuan Kerja Sama</option></select>
 <?php endif; ?>
 <?php foreach ($c['fields'] as $f): $n = h($f[0]); ?>
  <label for="f_<?= $n ?>"><?= h($f[1]) ?></label>
  <?php if ($f[2] === 'select'): ?><select id="f_<?= $n ?>" name="<?= $n ?>"><?php foreach ($f[3] as $op) echo '<option>', h($op), '</option>'; ?></select>
  <?php elseif ($f[2] === 'textarea'): ?><textarea id="f_<?= $n ?>" name="<?= $n ?>" rows="3"></textarea>
  <?php else: ?><input id="f_<?= $n ?>" name="<?= $n ?>" type="<?= $f[2] === 'number' ? 'number' : h($f[2]) ?>"<?= $f[2] === 'number' ? ' min="0" inputmode="numeric"' : '' ?>>
  <?php endif; endforeach; ?>
 <div class="act" style="margin-top:14px"><button type="button" onclick="dlg.close()">Batal</button><button class="btn">Simpan 💾</button></div>
</form></dialog>
<script>
const dlg=document.getElementById('dlg'),frm=document.getElementById('frm'),rid=document.getElementById('rid');
function buka(r){frm.reset();rid.value=r?r.id:0;
 if(r){for(const k in r)if(frm.elements[k]&&k!=='id')frm.elements[k].value=r[k]??'';}
 else if(frm.elements.tgl){frm.elements.tgl.value=new Date().toLocaleDateString('sv-SE');}
 dlg.showModal();}
const TPL={
 'Permohonan Kerja Sama':['Permohonan Kerja Sama [bidang kerja sama]','Dengan hormat,\n\nSehubungan dengan [alasan/kebutuhan], kami dari [nama instansi] bermaksud mengajukan permohonan kerja sama dengan [nama instansi tujuan] dalam hal [bidang kerja sama].\n\nKami memilih [nama instansi tujuan] karena [alasan]. Kami berharap dapat melakukan diskusi lebih lanjut mengenai teknis pelaksanaan, lini masa, serta anggaran biaya yang diperlukan.\n\nDemikian surat permohonan ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.'],
 'Persetujuan Kerja Sama':['Persetujuan Kerja Sama [bidang kerja sama]','Dengan hormat,\n\nTerima kasih atas surat permohonan kerja sama nomor [nomor surat] yang kami terima pada tanggal [tanggal] perihal [perihal surat].\n\nMelalui surat ini, kami menyatakan setuju untuk menjalin kerja sama tersebut. Sebagai langkah awal, kami mengundang tim [nama instansi] untuk menghadiri rapat koordinasi pada:\n\nHari/Tanggal : [hari, tanggal]\nWaktu : [pukul]\nTempat : [tempat]\n\nDemikian surat persetujuan ini kami sampaikan. Kami menantikan kehadiran tim Bapak/Ibu.'],
 'Undangan':['Undangan [nama acara]','Dengan hormat,\n\nSehubungan dengan akan dilaksanakannya [nama acara], kami mengundang Bapak/Ibu untuk hadir pada:\n\nHari/tanggal : [hari, tanggal]\nWaktu : [pukul]\nTempat : [tempat]\n\nDemikian undangan ini kami sampaikan. Atas perhatian dan kehadiran Bapak/Ibu, kami ucapkan terima kasih.'],
 'Pemberitahuan':['Pemberitahuan [hal]','Dengan hormat,\n\nSehubungan dengan [alasan], kami memberitahukan bahwa [isi pemberitahuan].\n\nDemikian pemberitahuan ini kami sampaikan. Atas perhatian Bapak/Ibu, kami ucapkan terima kasih.'],
 'Permohonan':['Permohonan [hal]','Dengan hormat,\n\nSehubungan dengan [keperluan], dengan ini kami mengajukan permohonan [hal yang dimohon].\n\nDemikian permohonan ini kami sampaikan. Atas perhatian dan kebijaksanaan Bapak/Ibu, kami ucapkan terima kasih.'],
 'Surat Keterangan':['Surat Keterangan [jenis]','Yang bertanda tangan di bawah ini menerangkan bahwa:\n\nNama : [nama]\nJabatan/Kelas : [jabatan]\n\nBenar yang bersangkutan [keterangan].\n\nDemikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.'],
 'Surat Tugas':['Surat Tugas','Yang bertanda tangan di bawah ini memberikan tugas kepada:\n\nNama : [nama]\nJabatan : [jabatan]\n\nUntuk melaksanakan [tugas] pada hari/tanggal [tanggal] bertempat di [tempat].\n\nDemikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.']};
function isiTpl(k){if(!TPL[k])return;frm.elements.perihal.value=TPL[k][0];frm.elements.isi.value=TPL[k][1];}
</script>
<?php
        layout($c['judul'], ob_get_clean(), $m);
        exit;
    }

    /* Beranda */
    $jml = $blm = [];
    foreach ($MODUL as $k => $c) {
        $jml[$k] = (int)$pdo->query('SELECT COUNT(*) FROM ' . q($k) . ' WHERE user_id=' . $uid)->fetchColumn();
        if (isset($c['selesai'])) {
            $w = q($c['selesai'][0]) . "<>'" . $c['selesai'][1] . "'" . ($k === 'agenda' ? " AND status<>'Batal'" : '');
            $blm[$k] = (int)$pdo->query('SELECT COUNT(*) FROM ' . q($k) . ' WHERE user_id=' . $uid . ' AND ' . $w)->fetchColumn();
        }
    }
    $hari = date('Y-m-d'); $jam = (int)date('G');
    $st = $pdo->prepare("SELECT * FROM agenda WHERE user_id=:u AND tgl=:t AND status='Terjadwal' ORDER BY jam"); $st->execute([':u' => $uid, ':t' => $hari]); $agenda = $st->fetchAll();
    $tugas = $pdo->query("SELECT * FROM tugas WHERE user_id=" . $uid . " AND status<>'Selesai' ORDER BY tgl LIMIT 5")->fetchAll();
    $sapa = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));
    $st = $pdo->prepare('SELECT nama FROM users WHERE id=:i'); $st->execute([':i' => $uid]); $nama = $st->fetchColumn() ?: 'calon sekretaris hebat';
    $tips = ['Satu surat selesai hari ini, satu beban hilang 🌷', 'Arsip rapi, hati pun tenang 🗂️', 'Kerja pelan-pelan tapi tertata itu keren banget 💖', 'Catat sekarang, tidak perlu pusing nanti ✨'][(int)date('j') % 4];

    ob_start(); ?>
<div class="hero"><h2><?= $sapa ?>, <?= h($nama) ?>! 🎀</h2><div><?= $tips ?></div></div>
<div class="grid"><?php foreach ($MODUL as $k => $c): ?>
 <a class="card tile" href="/?p=modul&m=<?= $k ?>"><div class="em"><?= $c['e'] ?></div><b><?= h($c['judul']) ?></b>
  <span><?= $k === 'kas' ? 'Saldo ' . rp(ringkasKas()[2]) : $jml[$k] . ' catatan' ?><?php if (!empty($blm[$k])) echo ' · <b class="warn">', $blm[$k], ' belum selesai</b>'; ?></span></a>
<?php endforeach; ?></div>
<h3 style="margin:18px 0 8px">📌 Agenda pimpinan hari ini</h3>
<div class="grid"><?php if (!$agenda) echo '<div class="card empty"><div class="em">🍵</div>Tidak ada agenda hari ini. Santai dulu ya!</div>';
 foreach ($agenda as $a) echo '<div class="card"><span class="chip c1">', h($a['jam'] ?: '--:--'), '</span><h3>', h($a['kegiatan']), '</h3><div class="ln">', h($a['tempat']), '</div></div>'; ?></div>
<h3 style="margin:18px 0 8px">⏰ Pengingat tugas terdekat</h3>
<div class="grid"><?php if (!$tugas) echo '<div class="card empty"><div class="em">🎉</div>Semua tugas beres. Hebat!</div>';
 foreach ($tugas as $t): $telat = $t['tgl'] < $hari; ?>
 <div class="card"><span class="chip <?= $telat ? 'c2' : 'c0' ?>"><?= $telat ? 'Terlambat' : 'Tenggat' ?> · <?= tgl($t['tgl']) ?></span><span class="chip c1"><?= h($t['prioritas']) ?></span>
  <h3><?= h($t['tugas']) ?></h3>
  <div class="act"><form method="post" action="/?p=modul&m=tugas"><input type="hidden" name="aksi" value="selesai"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="back" value="home"><button>✔ Selesai</button></form></div></div>
<?php endforeach; ?></div>
<?php
    layout('Beranda', ob_get_clean(), 'home');

} catch (Throwable $e) {
    // Kolom/tabel belum ada di database lama: perbarui otomatis lalu coba sekali lagi
    if (in_array((string)$e->getCode(), ['42703', '42P01'], true) && !isset($_GET['r'])) {
        try { install($MODUL); } catch (Throwable $x) {}
        go('/?p=' . urlencode((string)$p) . '&m=' . urlencode((string)$m) . '&id=' . (int)($_GET['id'] ?? 0) . '&r=1');
    }
    http_response_code(500);
    $pesan = sebab($e);
    echo head('Oops'), '<div class="wrap login"><div class="hero"><h2>Oops 🥺</h2><div>', h($pesan), '</div></div></div></body></html>';
}
