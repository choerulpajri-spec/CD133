<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

/* Kalau sudah login, redirect ke halaman sesuai role */
if (isset($_SESSION['id_pengguna'])) {
    switch ($_SESSION['role']) {
        case 'produksi': header('Location: bagian_produksi/dashboard.php'); break;
        case 'owner':    header('Location: owner/dashboard.php'); break;
        default:         header('Location: admin/index.php');
    }
    exit;
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Email dan password wajib diisi.';
    } else {
        try {
            $stmt = $koneksi->prepare("
                SELECT id_pengguna, nama, email, password, role
                FROM pengguna
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['id_pengguna'] = $user['id_pengguna'];
                $_SESSION['nama']        = $user['nama'];
                $_SESSION['email']       = $user['email'];
                $_SESSION['role']        = $user['role'];

                switch ($user['role']) {
                    case 'produksi':
                        header('Location: bagian_produksi/dashboard.php');
                        break;
                    case 'owner':
                        header('Location: owner/dashboard.php');
                        break;
                    default: // admin
                        header('Location: admin/index.php');
                }
                exit;
            } else {
                $errors[] = 'Email atau password salah.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Terjadi kesalahan sistem, coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Panel Internal — CD 133 Production</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/public.css">
<style>
:root {
    --ink:#0f172a;
    --ink-soft:#475569;
    --border:#e2e8f0;
    --bg:#f8fafc;
    --denim:#1e40af;
    --denim-light:#3b82f6;
    --denim-ink:#1e3a8a;
    --radius-sm:10px;
    --radius-lg:20px;
    --font-body:'Inter',system-ui,sans-serif;
    --shadow-lg:0 20px 40px -10px rgba(30,64,175,.15), 0 8px 20px -6px rgba(15,23,42,.08);
}

*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
    font-family:var(--font-body);
    color:var(--ink);
    background:#f1f5f9;
    -webkit-font-smoothing:antialiased;
}

/* ---------- Layout ---------- */
.login-wrap{
    min-height:100vh;
    display:grid;
    grid-template-columns: 1.05fr 1fr;
    background:var(--bg);
    overflow:hidden;
}

/* ---------- Kiri: Hero ---------- */
.login-hero{
    position:relative;
    padding:48px;
    color:#fff;
    background:
        radial-gradient(ellipse at top left, rgba(96,165,250,.4), transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(59,130,246,.5), transparent 55%),
        linear-gradient(135deg, #1e3a8a 0%, #1e40af 45%, #2563eb 100%);
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    overflow:hidden;
    isolation:isolate;
}
.login-hero::before{
    content:"";
    position:absolute;
    inset:-50%;
    background-image:
        radial-gradient(circle at 20% 30%, rgba(255,255,255,.08) 1px, transparent 1px),
        radial-gradient(circle at 70% 80%, rgba(255,255,255,.06) 1px, transparent 1px);
    background-size: 44px 44px, 66px 66px;
    z-index:-1;
}
.login-hero__brand{
    display:flex;
    align-items:center;
    gap:12px;
    position:relative;
}
.login-hero__mark{
    width:44px; height:44px;
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.25);
    border-radius:12px;
    padding:6px;
    backdrop-filter: blur(6px);
}
.login-hero__brand strong{
    display:block;
    font-family:'Sora',sans-serif;
    font-size:16px;
    letter-spacing:.2px;
}
.login-hero__brand span{
    font-size:12px;
    opacity:.75;
}

.login-hero__content h2{
    font-family:'Sora',sans-serif;
    font-size:clamp(28px, 3vw, 40px);
    line-height:1.15;
    margin:0 0 14px;
    letter-spacing:-.5px;
}
.login-hero__content p{
    font-size:15px;
    line-height:1.6;
    opacity:.85;
    margin:0 0 32px;
    max-width:420px;
}

.login-hero__features{
    list-style:none;
    padding:0;
    margin:0;
    display:flex;
    flex-direction:column;
    gap:14px;
}
.login-hero__features li{
    display:flex;
    align-items:center;
    gap:12px;
    font-size:14px;
    opacity:.95;
}
.login-hero__features svg{
    flex-shrink:0;
    width:20px; height:20px;
    background:rgba(255,255,255,.15);
    border-radius:50%;
    padding:4px;
}

.login-hero__foot{
    display:flex;
    align-items:center;
    justify-content:space-between;
    font-size:12px;
    opacity:.7;
    position:relative;
}

/* ---------- Kanan: Form ---------- */
.login-form-side{
    padding:48px;
    display:flex;
    align-items:center;
    justify-content:center;
    animation: fadeUp .55s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes fadeUp{
    from{ opacity:0; transform:translateY(14px); }
    to{   opacity:1; transform:translateY(0); }
}

.login-card{
    width:100%;
    max-width:420px;
}
.login-card__top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:28px;
}
.login-card__top-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:11.5px;
    font-weight:600;
    letter-spacing:.5px;
    text-transform:uppercase;
    color:var(--denim);
    background:rgba(59,130,246,.08);
    border:1px solid rgba(59,130,246,.2);
    padding:6px 12px;
    border-radius:999px;
}
.login-card__top-badge::before{
    content:"";
    width:6px; height:6px;
    background:var(--denim-light);
    border-radius:50%;
    box-shadow:0 0 0 4px rgba(59,130,246,.15);
}
.btn-back{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:13px;
    color:var(--ink-soft);
    text-decoration:none;
    font-weight:500;
    transition: color .2s ease;
}
.btn-back:hover{ color:var(--denim); }
.btn-back svg{ transition: transform .2s ease; }
.btn-back:hover svg{ transform:translateX(-2px); }

.login-card h1{
    font-family:'Sora',sans-serif;
    font-size:30px;
    font-weight:700;
    margin:0 0 8px;
    letter-spacing:-.5px;
    color:var(--ink);
}
.login-card p.sub{
    margin:0 0 32px;
    font-size:14.5px;
    color:var(--ink-soft);
    line-height:1.5;
}

/* ---------- Fields ---------- */
.field{ margin-bottom:18px; }
.field label{
    display:block;
    font-size:12.5px;
    font-weight:600;
    color:var(--ink);
    margin-bottom:8px;
    letter-spacing:.1px;
}
.field__input{
    position:relative;
    display:flex;
    align-items:center;
}
.field__input svg{
    position:absolute;
    left:14px;
    width:18px; height:18px;
    color:var(--ink-soft);
    pointer-events:none;
    transition: color .2s ease;
}
.field input{
    width:100%;
    padding:13px 14px 13px 42px;
    border:1.5px solid var(--border);
    border-radius:var(--radius-sm);
    font-size:14.5px;
    font-family:var(--font-body);
    background:#fff;
    color:var(--ink);
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}
.field input::placeholder{ color:#94a3b8; }
.field input:hover{ border-color:#cbd5e1; }
.field input:focus{
    border-color:var(--denim-light);
    outline:none;
    box-shadow:0 0 0 4px rgba(59,130,246,.12);
    background:#fff;
}
.field input:focus + svg,
.field__input:focus-within svg{ color:var(--denim-light); }

.toggle-pass{
    position:absolute;
    right:10px;
    background:none;
    border:none;
    padding:6px 8px;
    border-radius:6px;
    cursor:pointer;
    color:var(--ink-soft);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    transition: background .15s ease, color .15s ease;
}
.toggle-pass:hover{ background:#f1f5f9; color:var(--denim); }
.toggle-pass svg{ width:18px; height:18px; pointer-events:none; }

.field__row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin: 4px 0 24px;
    font-size:13px;
}
.check{
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:var(--ink-soft);
    cursor:pointer;
    user-select:none;
}
.check input{
    appearance:none;
    width:16px; height:16px;
    border:1.5px solid #cbd5e1;
    border-radius:4px;
    cursor:pointer;
    position:relative;
    transition: all .15s ease;
}
.check input:checked{
    background:var(--denim);
    border-color:var(--denim);
}
.check input:checked::after{
    content:"";
    position:absolute;
    left:4px; top:1px;
    width:5px; height:9px;
    border:solid #fff;
    border-width:0 2px 2px 0;
    transform:rotate(45deg);
}
.field__row a{
    color:var(--denim);
    text-decoration:none;
    font-weight:500;
}
.field__row a:hover{ text-decoration:underline; }

/* ---------- Button ---------- */
.btn-submit{
    width:100%;
    padding:14px 16px;
    border:none;
    border-radius:var(--radius-sm);
    font-size:15px;
    font-weight:600;
    font-family:var(--font-body);
    color:#fff;
    cursor:pointer;
    background: linear-gradient(135deg, var(--denim) 0%, var(--denim-light) 100%);
    box-shadow:0 8px 18px -6px rgba(30,64,175,.4);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition: transform .15s ease, box-shadow .2s ease, filter .2s ease;
}
.btn-submit:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 24px -6px rgba(30,64,175,.5);
    filter:brightness(1.05);
}
.btn-submit:active{ transform:translateY(0); }
.btn-submit svg{ width:16px; height:16px; }

/* ---------- Alert ---------- */
.alert-err{
    margin-bottom:20px;
    padding:12px 14px;
    border:1px solid rgba(220,38,38,.2);
    background:rgba(254,226,226,.6);
    border-radius:10px;
    color:#b91c1c;
    font-size:13px;
    display:flex;
    align-items:flex-start;
    gap:10px;
}
.alert-err svg{ width:18px; height:18px; flex-shrink:0; margin-top:1px; }
.alert-err p{ margin:0; }

.foot-note{
    margin-top:28px;
    text-align:center;
    font-size:12.5px;
    color:var(--ink-soft);
}
.foot-note svg{ width:12px; height:12px; vertical-align:-1px; margin:0 2px; }

/* ---------- Responsive ---------- */
@media (max-width: 900px){
    .login-wrap{ grid-template-columns: 1fr; }
    .login-hero{ display:none; }
    .login-form-side{ padding:32px 24px; }
}
@media (max-width: 480px){
    .login-form-side{ padding:24px 18px; }
    .login-card h1{ font-size:26px; }
    .login-card__top{ flex-wrap:wrap; gap:10px; }
}
</style>
</head>
<body>
<div class="login-wrap">

    <!-- ========= SISI KIRI: HERO ========= -->
    <aside class="login-hero">
        <div class="login-hero__brand">
            <img src="assets/img/logo.svg" alt="CD 133 Production" class="login-hero__mark">
            <div>
                <strong>CD 133 Production</strong>
                <span>Panel Internal</span>
            </div>
        </div>

        <div class="login-hero__content">
            <h2>Kelola produksi lebih cepat, di satu tempat.</h2>
            <p>
                Masuk ke dashboard internal untuk memantau progress produksi, laporan kinerja, dan keputusan bisnis secara real-time.
            </p>
            <ul class="login-hero__features">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Akses berdasarkan peran (Admin / Produksi / Owner)
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Data produksi & laporan selalu ter-update
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Aman dengan sesi terenkripsi
                </li>
            </ul>
        </div>

        <div class="login-hero__foot">
            <span>&copy; <?= date('Y') ?> CD 133 Production</span>
            <span>v2.1</span>
        </div>
    </aside>

    <!-- ========= SISI KANAN: FORM ========= -->
    <section class="login-form-side">
        <div class="login-card">
            <div class="login-card__top">
                <span class="login-card__top-badge">Area Login</span>
                <a href="customer/dashboard.php" class="btn-back">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Kembali ke Beranda
                </a>
            </div>

            <h1>Selamat datang kembali 👋</h1>
            <p class="sub">Masukkan kredensial Anda untuk mengakses panel internal.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert-err">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <p><?= htmlspecialchars($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate autocomplete="on">
                <div class="field">
                    <label for="email">Alamat Email</label>
                    <div class="field__input">
                        <input type="email" id="email" name="email" required autofocus
                               value="<?= htmlspecialchars($email) ?>"
                               placeholder="nama@cd133.com">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="field__input">
                        <input type="password" id="password" name="password" required
                               placeholder="Minimal 8 karakter" autocomplete="current-password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <button type="button" class="toggle-pass" aria-label="Tampilkan password" data-target="password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="field__row">
                    <label class="check">
                        <input type="checkbox" name="remember">
                        <span>Ingat saya</span>
                    </label>
                    <a href="#">Lupa password?</a>
                </div>

                <button type="submit" class="btn-submit">
                    Masuk ke Panel
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <p class="foot-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Koneksi Anda dilindungi. Data tidak disimpan di sisi klien.
            </p>
        </div>
    </section>
</div>

<script>
// Toggle password (ringan, ~20 baris)
document.querySelectorAll('.toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        if (!target) return;
        const isPass = target.type === 'password';
        target.type = isPass ? 'text' : 'password';
        btn.setAttribute('aria-label', isPass ? 'Sembunyikan password' : 'Tampilkan password');
    });
});
</script>
</body>
</html>