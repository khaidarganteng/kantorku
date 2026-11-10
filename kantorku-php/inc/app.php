<?php
date_default_timezone_set('Asia/Jakarta');
$MODUL = require __DIR__ . '/modul.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function env(string $k, string $d = ''): string { $v = getenv($k); return ($v === false || $v === '') ? $d : $v; }
function q(string $n): string { return '"' . str_replace('"', '', $n) . '"'; }
function rp($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function tgl($v): string { return $v ? date('d/m/Y', strtotime($v)) : ''; }
function go(string $u): void { header('Location: ' . $u); exit; }

/* ---------- Database (Postgres/Supabase lewat pooler) ---------- */
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $dsn = 'pgsql:host=' . env('DB_HOST') . ';port=' . env('DB_PORT', '6543') . ';dbname=' . env('DB_NAME', 'postgres') . ';sslmode=require';
        // EMULATE_PREPARES wajib true agar kompatibel dengan pooler mode transaksi
        $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
    }
    return $pdo;
}

function install(array $M): void {
    $db = db();
    $db->exec('CREATE TABLE IF NOT EXISTS users (id BIGSERIAL PRIMARY KEY, nama VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT now())');
    foreach ($M as $t => $c) {
        $kol = [];
        foreach ($c['fields'] as $f) {
            $tipe = match ($f[2]) { 'date' => 'DATE', 'number' => 'BIGINT', 'textarea' => 'TEXT', default => 'VARCHAR(500)' };
            $kol[] = q($f[0]) . ' ' . $tipe;
        }
        $db->exec('CREATE TABLE IF NOT EXISTS ' . q($t) . ' (id BIGSERIAL PRIMARY KEY, user_id BIGINT, ' . implode(', ', $kol) . ', created_at TIMESTAMP DEFAULT now())');
        $db->exec('ALTER TABLE ' . q($t) . ' ADD COLUMN IF NOT EXISTS user_id BIGINT');
        $db->exec('CREATE INDEX IF NOT EXISTS ' . q('idx_' . $t . '_user') . ' ON ' . q($t) . ' (user_id)');
    }
    // Akun pertama dibuat otomatis dari ADMIN_EMAIL dan ADMIN_PASSWORD (jika diisi)
    $e = strtolower(env('ADMIN_EMAIL')); $p = env('ADMIN_PASSWORD');
    if ($e !== '' && $p !== '') {
        $st = $db->prepare('INSERT INTO users (nama, email, password_hash) VALUES (:n, :e, :p) ON CONFLICT (email) DO NOTHING');
        $st->execute([':n' => 'Sekretaris Imut', ':e' => $e, ':p' => password_hash($p, PASSWORD_DEFAULT)]);
    }
}

/* ---------- Login berbasis cookie bertanda tangan (Vercel tidak punya session) ---------- */
function sign(string $s): string { return hash_hmac('sha256', $s, env('APP_SECRET', 'ganti-ini')); }
function uid(): int {
    $p = explode('.', (string)($_COOKIE['kantorku'] ?? ''), 3);
    if (count($p) !== 3 || !ctype_digit($p[0]) || !ctype_digit($p[1])) return 0;
    return ((int)$p[1] > time() && hash_equals(sign('u|' . $p[0] . '|' . $p[1]), $p[2])) ? (int)$p[0] : 0;
}
function login_ok(): bool { return uid() > 0; }
function cookie_opts(int $exp): array {
    $https = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ['expires' => $exp, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'];
}
function set_login(int $uid): void { $exp = time() + 86400 * 14; setcookie('kantorku', $uid . '.' . $exp . '.' . sign('u|' . $uid . '|' . $exp), cookie_opts($exp)); }
function clear_login(): void { setcookie('kantorku', '', cookie_opts(1)); }

/* ---------- Validasi form berdasarkan config ---------- */
function cek(array $c, array $in): array {
    $d = [];
    foreach ($c['fields'] as $f) {
        $k = $f[0]; $v = trim((string)($in[$k] ?? ''));
        if ($v === '') { if (!($f[4] ?? false)) return [null, $k]; $d[$k] = null; continue; }
        if ($f[2] === 'select' && !in_array($v, $f[3], true)) return [null, $k];
        if ($f[2] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return [null, $k];
        if ($f[2] === 'number' && (!ctype_digit($v) || strlen($v) > 15)) return [null, $k];
        $d[$k] = mb_substr($v, 0, $f[2] === 'textarea' ? 2000 : 500);
    }
    return [$d, null];
}

function nomorSurat(array $d): string {
    $thn = (int)substr($d['tgl'], 0, 4); $bln = (int)substr($d['tgl'], 5, 2);
    $s = db()->prepare('SELECT COUNT(*) FROM surat WHERE user_id=:u AND jenis=:j AND EXTRACT(YEAR FROM tgl)=:y AND EXTRACT(MONTH FROM tgl)=:m');
    $s->execute([':u' => uid(), ':j' => $d['jenis'], ':y' => $thn, ':m' => $bln]);
    $romawi = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$bln];
    return sprintf('%03d/%s/%s/%d', (int)$s->fetchColumn() + 1, $d['jenis'] === 'Surat Masuk' ? 'SM' : 'SK', $romawi, $thn);
}

/* ---------- Tampilan ---------- */
function css(): string { return <<<'CSS'
:root{color-scheme:light;--bg:#fff5f9;--card:#fff;--ink:#4a3550;--mute:#8a7590;--pink:#ff8fb8;--pink2:#ffd6e7;--c0:#c9f2e0;--c1:#fff1b8;--c2:#d9c8ff;--line:#f3d9e6;--sh:0 6px 18px rgba(255,143,184,.18)}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){color-scheme:dark;--bg:#1f1626;--card:#2b2034;--ink:#f6e9f5;--mute:#b9a5c0;--pink:#ff9cc2;--pink2:#4a2f45;--c0:#25473c;--c1:#4d4528;--c2:#45386a;--line:#402f4a;--sh:0 6px 18px rgba(0,0,0,.3)}}
:root[data-theme="dark"]{color-scheme:dark;--bg:#1f1626;--card:#2b2034;--ink:#f6e9f5;--mute:#b9a5c0;--pink:#ff9cc2;--pink2:#4a2f45;--c0:#25473c;--c1:#4d4528;--c2:#45386a;--line:#402f4a;--sh:0 6px 18px rgba(0,0,0,.3)}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{margin:0;background:var(--bg);color:var(--ink);font:400 15px/1.5 "Nunito",system-ui,sans-serif;min-height:100vh;padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left)}
a{color:inherit;text-decoration:none}
.wrap{max-width:960px;margin:0 auto;padding:16px}
header{display:flex;align-items:center;gap:8px;margin-bottom:10px}
header h1{font-size:21px;margin:0;font-weight:800;flex:1}
header small{display:block;color:var(--mute);font-weight:400;font-size:12px}
.logo{font-size:30px}
nav{display:flex;gap:8px;overflow-x:auto;padding:4px 2px 10px;position:sticky;top:0;background:var(--bg);z-index:5}
nav a{flex:none;border:2px solid var(--line);background:var(--card);border-radius:999px;padding:8px 14px;font-weight:700;font-size:13px}
nav a.on{background:var(--pink);border-color:var(--pink);color:#fff}
.hero{background:linear-gradient(135deg,var(--pink2),var(--c2));border-radius:24px;padding:20px;box-shadow:var(--sh);margin-bottom:16px}
.hero h2{margin:0 0 4px;font-size:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
.card{background:var(--card);border:2px solid var(--line);border-radius:20px;padding:14px;box-shadow:var(--sh)}
.tile{text-align:center;display:block;transition:transform .15s}
.tile:active{transform:scale(.97)}
.tile .em{font-size:34px}.tile b{display:block}.tile span{color:var(--mute);font-size:13px}
.warn{color:var(--pink)}
.bar{display:flex;gap:8px;margin:8px 0 12px;flex-wrap:wrap}
.bar h2{margin:0;flex:1 1 100%;font-size:20px}
.bar form.s{flex:1;display:flex;min-width:140px}
input,select,textarea{width:100%;border:2px solid var(--line);background:var(--card);color:var(--ink);border-radius:14px;padding:10px 12px;font:inherit;font-size:16px}
.btn,.act button{border:0;border-radius:14px;padding:10px 16px;font:800 14px "Nunito",sans-serif;cursor:pointer;background:var(--pink);color:#fff;display:inline-block}
.btn.g{background:var(--pink2);color:var(--ink)}
.chip{display:inline-block;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:700;margin:0 4px 6px 0;color:var(--ink)}
.c0{background:var(--c0)}.c1{background:var(--c1)}.c2{background:var(--c2)}
.card h3{margin:0 0 4px;font-size:16px}
.ln{font-size:13px;color:var(--mute)}.ln b{color:var(--ink)}
.act{display:flex;gap:6px;margin-top:10px}
.act form{flex:1;display:flex}
.act button{flex:1;padding:9px 6px;font-size:12px;background:var(--pink2);color:var(--ink)}
.sum{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px}
.sum div{border-radius:16px;padding:10px 6px;text-align:center;font-size:12px;font-weight:700}
.sum b{display:block;font-size:14px}
.empty{grid-column:1/-1;text-align:center;padding:30px;color:var(--mute)}
.empty .em{font-size:44px}
.ok,.err{border-radius:16px;padding:10px 14px;margin-bottom:12px;font-weight:700}
.ok{background:var(--c0)}.err{background:var(--pink2)}
dialog{border:0;border-radius:24px;padding:20px;width:calc(100% - 32px);max-width:440px;background:var(--card);color:var(--ink)}
dialog::backdrop{background:rgba(40,20,50,.45)}
dialog label{display:block;font-size:13px;font-weight:700;margin:8px 0 3px}
.pg{margin-top:14px;display:flex;justify-content:center;gap:8px;align-items:center;font-size:13px}
.login{max-width:380px;margin:10vh auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{border:1px solid #999;padding:6px 8px;text-align:left}
@media print{.noprint{display:none}body{background:#fff;color:#000}}
CSS;
}

function head(string $judul): string {
    $ico = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🌸%3C/text%3E%3C/svg%3E";
    return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
     . '<meta name="theme-color" content="#ff8fb8"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">'
     . '<link rel="manifest" href="/?p=manifest"><link rel="icon" href="' . $ico . '"><link rel="apple-touch-icon" href="' . $ico . '">'
     . '<title>' . h($judul) . ' – Kantorku Imut</title>'
     . '<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&display=swap" rel="stylesheet">'
     . '<style>' . css() . '</style>'
     . '<script>try{var t=localStorage.getItem("tema");if(t)document.documentElement.dataset.theme=t}catch(e){}</script></head><body>';
}

function layout(string $judul, string $isi, string $aktif = 'home'): void {
    global $MODUL;
    $ok = ['simpan' => 'Tersimpan! ✨', 'selesai' => 'Yeay, selesai! 🎉', 'hapus' => 'Dihapus 🗑️'][$_GET['ok'] ?? ''] ?? '';
    $err = '';
    if (isset($_GET['err'])) {
        $lbl = 'isian';
        foreach ($MODUL as $c) foreach ($c['fields'] as $f) if ($f[0] === $_GET['err']) $lbl = $f[1];
        $err = 'Isian “' . $lbl . '” belum lengkap atau tidak valid.';
    }
    echo head($judul), '<div class="wrap"><header><span class="logo">🌸</span><h1>Kantorku Imut<small>Asisten pintar anak MPLB ✨</small></h1>',
      '<button class="btn g" type="button" onclick="tema()" aria-label="Ganti tema">🌓</button>',
      '<form method="post" action="/?p=keluar"><button class="btn g">Keluar</button></form></header><nav>',
      '<a href="/" class="', $aktif === 'home' ? 'on' : '', '">🏠 Beranda</a>';
    foreach ($MODUL as $k => $c) echo '<a href="/?p=modul&m=', $k, '" class="', $aktif === $k ? 'on' : '', '">', $c['e'], ' ', h($c['judul']), '</a>';
    echo '</nav>', $ok ? '<div class="ok">' . $ok . '</div>' : '', $err ? '<div class="err">' . h($err) . '</div>' : '', $isi, '</div>',
      '<script>function tema(){var r=document.documentElement,d=r.dataset.theme?r.dataset.theme==="dark":matchMedia("(prefers-color-scheme:dark)").matches;r.dataset.theme=d?"light":"dark";try{localStorage.setItem("tema",r.dataset.theme)}catch(e){}}</script></body></html>';
}

function chipCls(array $c, string $k, $v): string {
    foreach ($c['fields'] as $f) if ($f[0] === $k) { $i = array_search($v, $f[3] ?? [], true); return 'c' . (($i === false ? 0 : $i) % 3); }
    return 'c0';
}

function kartu(string $m, array $c, array $r): string {
    $o = '<div class="card">';
    foreach ($c['chips'] as $ck) if (!empty($r[$ck])) $o .= '<span class="chip ' . chipCls($c, $ck, $r[$ck]) . '">' . h($r[$ck]) . '</span>';
    $o .= '<h3>' . h($r[$c['main']]) . '</h3>';
    foreach ($c['fields'] as $f) {
        $k = $f[0];
        if ($k === $c['main'] || in_array($k, $c['chips'], true) || ($r[$k] ?? '') === '' || $r[$k] === null) continue;
        $v = $f[2] === 'number' ? rp($r[$k]) : ($f[2] === 'date' ? tgl($r[$k]) : $r[$k]);
        $isi = $k === 'no' ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $r[$k])) . '">📱 ' . h($v) . '</a>' : nl2br(h($v));
        $o .= '<div class="ln"><b>' . h($f[1]) . ':</b> ' . $isi . '</div>';
    }
    $u = '/?p=modul&m=' . $m;
    $o .= '<div class="act">';
    if (isset($c['selesai']) && $r[$c['selesai'][0]] !== $c['selesai'][1])
        $o .= '<form method="post" action="' . $u . '"><input type="hidden" name="aksi" value="selesai"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button>✔ Selesai</button></form>';
    $o .= '<button type="button" data-row="' . h(json_encode($r)) . '" onclick="buka(JSON.parse(this.dataset.row))">✏️ Ubah</button>'
        . '<form method="post" action="' . $u . '" onsubmit="return confirm(\'Hapus data ini?\')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button>🗑️ Hapus</button></form></div></div>';
    return $o;
}

function ringkasKas(): array {
    $i = (int)db()->query("SELECT COALESCE(SUM(jumlah),0) FROM kas WHERE jenis='Pemasukan' AND user_id=" . uid())->fetchColumn();
    $o = (int)db()->query("SELECT COALESCE(SUM(jumlah),0) FROM kas WHERE jenis='Pengeluaran' AND user_id=" . uid())->fetchColumn();
    return [$i, $o, $i - $o];
}

/* Penjelasan penyebab gagal koneksi (tanpa membocorkan nilai rahasia) */
function sebab(Throwable $e): string {
    foreach (['DB_HOST', 'DB_USER', 'DB_PASS'] as $k)
        if (env($k) === '') return $k . ' kosong: variabel belum terbaca. Simpan di Vercel lalu Redeploy.';
    if (str_contains(env('DB_HOST'), 'host-pooler') || str_contains(env('DB_USER'), 'PROJECTREF') || str_contains(env('DB_PASS'), 'kata-sandi-database'))
        return 'Masih ada teks contoh di DB_HOST, DB_USER, atau DB_PASS. Ganti dengan nilai asli dari Supabase.';
    $m = $e->getMessage();
    if (str_contains($m, 'could not find driver')) return 'Driver Postgres (pdo_pgsql) tidak tersedia di server ini.';
    if (str_contains($m, 'password authentication failed')) return 'DB_PASS salah. Reset kata sandi di Supabase lalu perbarui di Vercel.';
    if (str_contains($m, 'Tenant or user not found')) return 'DB_USER atau DB_HOST tidak cocok dengan project Supabase.';
    if (str_contains($m, 'translate host') || str_contains($m, 'Name or service')) return 'DB_HOST tidak dikenali. Salin ulang dari Supabase.';
    if (str_contains($m, 'timeout') || str_contains($m, 'timed out') || str_contains($m, 'unreachable')) return 'Server database tidak terjangkau (cek DB_HOST dan DB_PORT 6543).';
    return 'Kesalahan database lain (kode ' . $e->getCode() . ').';
}

/* Buat tabel otomatis kalau belum ada, jadi tidak perlu alamat install */
function siapkan(array $M): void {
    try { db()->query('SELECT 1 FROM users LIMIT 1'); }
    catch (Throwable $e) {
        if (str_contains($e->getMessage(), '42P01') || str_contains($e->getMessage(), 'does not exist')) install($M);
        else throw $e;
    }
}
