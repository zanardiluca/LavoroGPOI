<?php
/* ============================================================
   SPORT ERP — FILE COMPLETO (adattato al DB dbgpoi.sql)

   SETUP:
   1. Importa il file dbgpoi.sql nel tuo MySQL
   2. Cambia DB_NAME, DB_USER e DB_PASS qui sotto
   3. Carica il file sul server PHP+MySQL
   4. Accedi: http://localhost/sg.php

   CREDENZIALI (password in chiaro come da dbgpoi.sql):
   admin        / Admin!
   m.bianchi    / Direttore1
   g.ferraro    / Direttore2
   a.russo      / CapoRusso!
   c.marino     / CapoMarino!
   p.greco      / UtenteGreco!
   t.ricci      / UtenteRicci!
============================================================ */

// ============================================================
// SEZIONE PHP — CONFIGURAZIONE
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbgpoi');         // <-- nome del tuo database
define('DB_USER', 'root');          // <-- cambia con il tuo utente MySQL
define('DB_PASS', '');          // <-- cambia con la tua password MySQL

session_start();

// ── Connessione PDO ─────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die("
            <!DOCTYPE html><html><head>
            <meta charset='UTF-8'>
            <link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap'>
            <style>body{font-family:Poppins,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f7f7f7;margin:0;}
            .err{background:#fff;border:1px solid #e0e0e0;padding:32px;max-width:480px;text-align:center;}
            h2{font-size:16px;margin-bottom:8px;color:#c62828;} p{font-size:12px;color:#888;margin-bottom:16px;}
            code{font-size:11px;background:#f7f7f7;padding:4px 8px;display:block;margin-top:8px;text-align:left;}
            a{font-size:12px;color:#1d1d1d;}</style></head><body>
            <div class='err'>
                <h2>Errore connessione database</h2>
                <p>Assicurati di aver importato dbgpoi.sql e di aver impostato correttamente DB_USER, DB_PASS e DB_NAME.</p>
                <code>".htmlspecialchars($e->getMessage())."</code>
            </div></body></html>");
        }
    }
    return $pdo;
}

// ============================================================
// SEZIONE PHP — LOGICA AUTENTICAZIONE
// ============================================================

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: sg.php");
    exit();
}

// Login
// Il DB dbgpoi.sql salva le password in chiaro (es. 'Admin!')
// quindi confrontiamo direttamente con ===
$errore_login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        $errore_login = "Inserisci username e password.";
    } else {
        try {
            // Colonne: nome_utente, password, id_ruolo → ruoli.nome_ruolo, ruoli.livello
            $stmt = db()->prepare("
                SELECT u.id, u.nome, u.cognome, u.password,
                       r.nome_ruolo AS ruolo, r.livello,
                       u.id_filiale
                FROM utenti u
                JOIN ruoli r ON u.id_ruolo = r.id
                WHERE u.nome_utente = ? LIMIT 1
            ");
            $stmt->execute([$username]);
            $utente = $stmt->fetch();

            if (!$utente || $utente['password'] !== $password) {
                $errore_login = "Credenziali non valide.";
            } else {
                session_regenerate_id(true);
                $_SESSION['id']         = $utente['id'];
                $_SESSION['nome']       = $utente['nome'].' '.$utente['cognome'];
                $_SESSION['ruolo']      = $utente['ruolo'];
                $_SESSION['livello']    = $utente['livello'];
                $_SESSION['id_filiale'] = $utente['id_filiale'];
                header("Location: sg.php");
                exit();
            }
        } catch (PDOException $e) {
            $errore_login = "Errore DB: ".htmlspecialchars($e->getMessage());
        }
    }
}

// Variabili sessione
$loggato       = isset($_SESSION['id']);
$sess_nome     = $_SESSION['nome']       ?? '';
$sess_ruolo    = $_SESSION['ruolo']      ?? '';
$sess_livello  = $_SESSION['livello']    ?? 0;
$sess_filiale  = $_SESSION['id_filiale'] ?? null;
$sess_initiali = $sess_nome
    ? strtoupper(substr($sess_nome,0,1).substr(strrchr($sess_nome,' '),1,1))
    : '--';

// Permessi per ruolo (basati su livello: Admin=4, Direttore=3, Caporeparto=2, Utente=1)
// [dashboard globale, tutte le filiali, gestione utenti, report finanziari, propria filiale]
$permessi = [
    $sess_livello >= 4,  // dashboard globale → solo Admin
    $sess_livello >= 4,  // tutte le filiali  → solo Admin
    $sess_livello >= 4,  // gestione utenti   → solo Admin
    $sess_livello >= 3,  // report finanziari → Admin + Direttore
    $sess_livello >= 2,  // propria filiale   → Admin, Direttore, Caporeparto
];

// Pagina corrente
$pagina = $_GET['p'] ?? 'dashboard';

// ── Dati dashboard dal DB ─────────────────────────────────────
$kpi_ricavi     = '—';
$kpi_dip        = '—';
$kpi_prodotti   = '—';
$kpi_vendite    = '—';
$categorie      = [];
$utenti_lista   = [];

if ($loggato) {
    try {
        // KPI: totale ricavi dalle vendite
        $tot = db()->query("
            SELECT SUM(dv.quantita * dv.prezzo_unitario)
            FROM dettaglio_vendite dv
        ")->fetchColumn();
        $kpi_ricavi = $tot ? '€'.number_format((float)$tot, 0, ',', '.') : '€0';

        // KPI: numero utenti attivi
        $kpi_dip = db()->query("SELECT COUNT(*) FROM utenti")->fetchColumn();

        // KPI: numero prodotti a magazzino
        $kpi_prodotti = db()->query("SELECT SUM(quantita_magazzino) FROM prodotti")->fetchColumn() ?? 0;

        // KPI: numero vendite totali
        $kpi_vendite = db()->query("SELECT COUNT(*) FROM vendite")->fetchColumn();

        // Grafico torta: ricavi per categoria prodotto
        $categorie = db()->query("
            SELECT cp.nome_categoria AS categoria,
                   SUM(dv.quantita * dv.prezzo_unitario) AS importo
            FROM dettaglio_vendite dv
            JOIN prodotti p ON dv.prodotto_id = p.id
            JOIN categorie_prodotti cp ON p.id_categoria = cp.id
            GROUP BY cp.id, cp.nome_categoria
            ORDER BY importo DESC
        ")->fetchAll();

        // Calcola percentuale per il grafico torta
        $totale_cat = array_sum(array_column($categorie, 'importo'));
        foreach ($categorie as &$c) {
            $c['percentuale'] = $totale_cat > 0 ? round($c['importo'] / $totale_cat * 100) : 0;
        }
        unset($c);

        // Lista utenti: Admin e Direttore vedono tutti, Caporeparto solo la propria filiale
        if ($sess_livello >= 3) {
            $utenti_lista = db()->query("
                SELECT u.nome, u.cognome, r.nome_ruolo AS ruolo,
                       f.città AS filiale
                FROM utenti u
                JOIN ruoli r ON u.id_ruolo = r.id
                LEFT JOIN filiali f ON u.id_filiale = f.id
                ORDER BY r.livello DESC, u.cognome
            ")->fetchAll();
        } else {
            $s = db()->prepare("
                SELECT u.nome, u.cognome, r.nome_ruolo AS ruolo,
                       f.città AS filiale
                FROM utenti u
                JOIN ruoli r ON u.id_ruolo = r.id
                LEFT JOIN filiali f ON u.id_filiale = f.id
                WHERE u.id_filiale = ?
                ORDER BY r.livello DESC, u.cognome
            ");
            $s->execute([$sess_filiale]);
            $utenti_lista = $s->fetchAll();
        }

    } catch (PDOException $e) {
        $kpi_ricavi   = 'N/D';
        $kpi_dip      = 'N/D';
        $kpi_prodotti = 'N/D';
        $kpi_vendite  = 'N/D';
    }
}

// ── Dati pagina Filiali ──────────────────────────────────────
$filiali = [];
if ($loggato && $pagina === 'filiale') {
    try {
        if ($sess_livello >= 3) {
            $filiali = db()->query("SELECT * FROM filiali ORDER BY città")->fetchAll();
        } else {
            $s = db()->prepare("SELECT * FROM filiali WHERE id = ?");
            $s->execute([$sess_filiale]);
            $filiali = $s->fetchAll();
        }
    } catch (PDOException $e) { $filiali = []; }
}

// ── Dati pagina Prodotti ─────────────────────────────────────
$prodotti = [];
if ($loggato && $pagina === 'prodotti') {
    try {
        $prodotti = db()->query("
            SELECT p.id, p.nome, p.prezzo, p.quantita_magazzino,
                   cp.nome_categoria AS categoria
            FROM prodotti p
            JOIN categorie_prodotti cp ON p.id_categoria = cp.id
            ORDER BY cp.nome_categoria, p.nome
        ")->fetchAll();
    } catch (PDOException $e) { $prodotti = []; }
}

// ── Dati pagina Vendite ──────────────────────────────────────
$vendite = [];
if ($loggato && $pagina === 'vendite') {
    try {
        $vendite = db()->query("
            SELECT v.id, v.data,
                   u.nome AS utente_nome, u.cognome AS utente_cognome,
                   SUM(dv.quantita * dv.prezzo_unitario) AS totale
            FROM vendite v
            JOIN utenti u ON v.id_utente = u.id
            JOIN dettaglio_vendite dv ON dv.vendita_id = v.id
            GROUP BY v.id
            ORDER BY v.data DESC
        ")->fetchAll();
    } catch (PDOException $e) { $vendite = []; }
}

// ── Funzione PHP: genera grafico a torta in SVG ──────────────
function torta_svg(array $dati): string {
    $colori = ['#1d1d1d','#555','#888','#aaa','#ccc'];
    $cx = 100; $cy = 100; $r = 85;
    $angolo = -M_PI / 2;
    $svg = '<svg viewBox="0 0 200 200" width="200" height="200" xmlns="http://www.w3.org/2000/svg">';
    foreach ($dati as $i => $v) {
        $perc  = (float)$v['percentuale'];
        if ($perc <= 0) continue;
        $delta = ($perc / 100) * 2 * M_PI;
        $x1 = $cx + $r * cos($angolo);
        $y1 = $cy + $r * sin($angolo);
        $x2 = $cx + $r * cos($angolo + $delta);
        $y2 = $cy + $r * sin($angolo + $delta);
        $large = $delta > M_PI ? 1 : 0;
        $col = $colori[$i % count($colori)];
        $svg .= sprintf(
            '<path d="M%s %s L%s %s A%s %s 0 %s 1 %s %s Z" fill="%s" stroke="#fff" stroke-width="2"/>',
            $cx, $cy, round($x1,1), round($y1,1), $r, $r, $large, round($x2,1), round($y2,1), $col
        );
        if ($perc >= 8) {
            $mid = $angolo + $delta / 2;
            $lx  = $cx + ($r * 0.62) * cos($mid);
            $ly  = $cy + ($r * 0.62) * sin($mid);
            $svg .= sprintf(
                '<text x="%s" y="%s" text-anchor="middle" dominant-baseline="middle" fill="#fff" font-size="10" font-family="sans-serif">%s%%</text>',
                round($lx,1), round($ly,1), $perc
            );
        }
        $angolo += $delta;
    }
    return $svg . '</svg>';
}

$colori_leg = ['#1d1d1d','#555','#888','#aaa','#ccc'];
$classi_ruolo = [
    'admin'            => 'r-admin',
    'direttore filiale'=> 'r-dir',
    'caporeparto'      => 'r-capo',
    'utente base'      => 'r-user',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Gestionale</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        :root {
            --black:#1d1d1d; --white:#fff; --border:#e0e0e0;
            --muted:#888; --surface:#f7f7f7;
            --green:#2e7d32; --red:#c62828; --blue:#1565c0;
        }

        /* ── LOGIN ── */
        .login-body { background:var(--surface); display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .login-box { background:var(--white); border:1px solid var(--border); padding:36px 32px; width:340px; }
        .login-logo { font-size:18px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .login-sub { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-top:3px; padding-bottom:20px; border-bottom:1px solid var(--border); margin-bottom:24px; }
        .login-error { background:#fff0f0; border:1px solid #ffcccc; color:var(--red); font-size:12px; padding:8px 12px; margin-bottom:16px; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:11px; font-weight:500; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
        .form-group input { width:100%; padding:9px 12px; border:1px solid var(--border); font-family:'Poppins',sans-serif; font-size:13px; outline:none; transition:border-color .2s; }
        .form-group input:focus { border-color:var(--black); }
        .btn-login { width:100%; padding:10px; background:var(--black); color:var(--white); border:1.5px solid var(--black); font-family:'Poppins',sans-serif; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; cursor:pointer; margin-top:8px; transition:background .3s, color .3s; }
        .btn-login:hover { background:var(--white); color:var(--black); }

        /* ── LAYOUT ── */
        .erp { display:flex; min-height:100vh; }
        .sidenav { width:190px; min-width:190px; background:var(--black); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; padding:24px 0; }
        .brand { padding:0 20px 24px; border-bottom:1px solid #333; }
        .brand .logo { font-size:15px; font-weight:600; color:var(--white); text-transform:uppercase; letter-spacing:.5px; }
        .brand .sub { font-size:10px; color:#555; text-transform:uppercase; letter-spacing:1px; margin-top:3px; }
        .sidenav ul { list-style:none; margin-top:16px; flex:1; }
        .sidenav ul li a { display:block; padding:10px 20px; font-size:12px; font-weight:500; color:#888; text-decoration:none; text-transform:uppercase; letter-spacing:.5px; border-left:2px solid transparent; transition:color .2s, border-color .2s; }
        .sidenav ul li a:hover, .sidenav ul li a.active { color:var(--white); border-left-color:var(--white); }
        .role-section { padding:16px 20px; border-top:1px solid #333; }
        .rlabel { font-size:10px; color:#555; text-transform:uppercase; letter-spacing:.8px; margin-bottom:6px; }
        .user-info { font-size:12px; color:#aaa; font-weight:500; margin-bottom:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .logout-btn { display:block; font-size:10px; font-weight:500; text-transform:uppercase; letter-spacing:.5px; padding:5px 10px; border:1px solid #444; color:#888; text-decoration:none; text-align:center; transition:all .2s; }
        .logout-btn:hover { border-color:#aaa; color:#ccc; }
        .main { flex:1; margin-left:190px; display:flex; flex-direction:column; min-height:100vh; }

        /* ── TOPBAR ── */
        .topbar { display:flex; justify-content:space-between; align-items:center; padding:16px 28px; border-bottom:1px solid var(--border); }
        .topbar h1 { font-size:20px; font-weight:700; letter-spacing:-.5px; }
        .topbar h1 span { font-weight:300; }
        .topbar-right { display:flex; align-items:center; gap:12px; }
        .role-badge { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.8px; padding:4px 10px; background:var(--black); color:var(--white); border:1.5px solid var(--black); }
        .avatar-circle { width:32px; height:32px; border-radius:50%; background:var(--black); color:var(--white); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; }

        /* ── KPI ── */
        .kpi-row { display:grid; grid-template-columns:repeat(4,1fr); border-bottom:1px solid var(--border); }
        .kpi-row.kpi-off { opacity:.25; pointer-events:none; }
        .kpi-row.kpi-dim { opacity:.55; }
        .kpi-card { padding:18px 22px; border-right:1px solid var(--border); }
        .kpi-card:last-child { border-right:none; }
        .kpi-label { font-size:10px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); margin-bottom:5px; }
        .kpi-val { font-size:24px; font-weight:700; letter-spacing:-1px; }
        .kpi-delta { font-size:10px; margin-top:2px; }
        .up { color:var(--green); }
        .down { color:var(--red); }

        /* ── CONTENT ── */
        .content { display:grid; grid-template-columns:1fr 260px; flex:1; }
        .chart-area { padding:22px 28px; border-right:1px solid var(--border); }
        .sec-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .sec-title { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.8px; }
        .chart-layout { display:flex; align-items:center; gap:32px; flex-wrap:wrap; }
        .legenda { display:flex; flex-direction:column; gap:10px; }
        .leg-item { display:flex; align-items:center; gap:8px; font-size:12px; }
        .leg-item strong { margin-left:auto; font-weight:600; }
        .leg-dot { width:10px; height:10px; border-radius:2px; flex-shrink:0; }

        /* ── TABELLA UTENTI ── */
        .team-panel { padding:22px 18px; }
        .team-table { width:100%; font-size:11px; border-collapse:collapse; margin-top:10px; }
        .team-table th { font-size:9px; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); padding:4px 6px; text-align:left; border-bottom:1px solid var(--border); }
        .team-table td { padding:7px 6px; border-bottom:.5px solid var(--border); vertical-align:middle; }
        .team-table tr:last-child td { border-bottom:none; }
        .rpill { font-size:8px; font-weight:600; text-transform:uppercase; padding:2px 6px; }
        .r-admin { background:#1d1d1d; color:#fff; }
        .r-dir   { background:#1565c0; color:#fff; }
        .r-capo  { background:#2e7d32; color:#fff; }
        .r-user  { background:#f0f0f0; color:#555; border:1px solid #ccc; }

        /* ── PERMESSI ── */
        .perm-box { background:var(--surface); padding:12px 14px; margin-top:14px; }
        .perm-row { display:flex; justify-content:space-between; align-items:center; padding:4px 0; font-size:11px; border-bottom:.5px solid var(--border); }
        .perm-row:last-child { border-bottom:none; }
        .pyes { color:var(--green); font-weight:600; font-size:10px; }
        .pno  { color:#ccc; font-size:10px; }

        /* ── FILIALI ── */
        .filiali-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; padding:24px 28px; }
        .filiale-card { border:1px solid var(--border); padding:18px 20px; background:var(--surface); }
        .filiale-nome { font-size:14px; font-weight:600; margin-bottom:4px; }
        .filiale-citta { font-size:12px; color:var(--muted); }
        .no-data { font-size:12px; color:var(--muted); padding:20px 28px; }

        /* ── TABELLA GENERICA ── */
        .page-content { padding:24px 28px; }
        .data-table { width:100%; font-size:12px; border-collapse:collapse; }
        .data-table th { font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); padding:8px 10px; text-align:left; border-bottom:2px solid var(--border); }
        .data-table td { padding:9px 10px; border-bottom:1px solid var(--border); }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:var(--surface); }
        .badge { font-size:10px; font-weight:600; padding:3px 8px; }
        .badge-cat { background:#eee; color:#555; }
    </style>
</head>
<body>

<?php if (!$loggato): ?>

    <!-- ── PAGINA LOGIN ── -->
    <div class="login-body">
        <div class="login-box">
            <div class="login-logo">ERP Gestionale</div>
            <div class="login-sub">Sistema di gestione aziendale</div>

            <?php if ($errore_login): ?>
                <div class="login-error"><?= htmlspecialchars($errore_login) ?></div>
            <?php endif; ?>

            <form method="POST" action="sg.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <input type="hidden" name="login" value="1">
                <button type="submit" class="btn-login">Accedi</button>
            </form>
        </div>
    </div>

<?php else: ?>

    <!-- ── LAYOUT ERP ── -->
    <div class="erp">

        <nav class="sidenav">
            <div class="brand">
                <div class="logo">ERP</div>
                <div class="sub">Gestionale</div>
            </div>
            <ul>
                <li><a href="sg.php?p=dashboard"  <?= $pagina==='dashboard' ?'class="active"':'' ?>>Dashboard</a></li>
                <li><a href="sg.php?p=filiale"    <?= $pagina==='filiale'   ?'class="active"':'' ?>>Filiali</a></li>
                <li><a href="sg.php?p=prodotti"   <?= $pagina==='prodotti'  ?'class="active"':'' ?>>Prodotti</a></li>
                <li><a href="sg.php?p=vendite"    <?= $pagina==='vendite'   ?'class="active"':'' ?>>Vendite</a></li>
            </ul>
            <div class="role-section">
                <div class="rlabel">Connesso come</div>
                <div class="user-info"><?= htmlspecialchars($sess_nome) ?></div>
                <a href="sg.php?logout=1" class="logout-btn">Logout</a>
            </div>
        </nav>

        <div class="main">

            <?php if ($pagina === 'dashboard'): ?>

            <!-- ── DASHBOARD ── -->
            <div class="topbar">
                <h1>Panoramica <span>Operativa</span></h1>
                <div class="topbar-right">
                    <span class="role-badge"><?= htmlspecialchars($sess_ruolo) ?></span>
                    <div class="avatar-circle"><?= htmlspecialchars($sess_initiali) ?></div>
                </div>
            </div>

            <?php
            $kpi_class = '';
            if ($sess_livello <= 1)     $kpi_class = 'kpi-off';
            elseif ($sess_livello == 2) $kpi_class = 'kpi-dim';
            ?>
            <div class="kpi-row <?= $kpi_class ?>">
                <div class="kpi-card">
                    <div class="kpi-label">Ricavi Vendite</div>
                    <div class="kpi-val"><?= htmlspecialchars($kpi_ricavi) ?></div>
                    <div class="kpi-delta up">da dettaglio_vendite</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Vendite Totali</div>
                    <div class="kpi-val"><?= htmlspecialchars($kpi_vendite) ?></div>
                    <div class="kpi-delta up">ordini registrati</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Utenti Sistema</div>
                    <div class="kpi-val"><?= htmlspecialchars($kpi_dip) ?></div>
                    <div class="kpi-delta">registrati</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Prodotti a Magazzino</div>
                    <div class="kpi-val"><?= htmlspecialchars($kpi_prodotti) ?></div>
                    <div class="kpi-delta">unità totali</div>
                </div>
            </div>

            <div class="content">
                <div class="chart-area">
                    <div class="sec-head">
                        <div class="sec-title">Ricavi per Categoria Prodotto</div>
                    </div>
                    <?php if (!empty($categorie)): ?>
                    <div class="chart-layout">
                        <?= torta_svg($categorie) ?>
                        <div class="legenda">
                            <?php foreach ($categorie as $i => $v): ?>
                            <div class="leg-item">
                                <span class="leg-dot" style="background:<?= $colori_leg[$i % 5] ?>"></span>
                                <?= htmlspecialchars($v['categoria']) ?>
                                <strong><?= $v['percentuale'] ?>%</strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                        <p class="no-data">Nessun dato di vendita disponibile.</p>
                    <?php endif; ?>
                </div>

                <div class="team-panel">
                    <div class="sec-title">Utenti Sistema</div>
                    <?php if (!empty($utenti_lista)): ?>
                    <table class="team-table">
                        <thead>
                            <tr><th>Utente</th><th>Ruolo</th><th>Filiale</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utenti_lista as $u):
                                $cls = $classi_ruolo[strtolower($u['ruolo'])] ?? 'r-user';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($u['cognome'].' '.substr($u['nome'],0,1)) ?>.</td>
                                <td><span class="rpill <?= $cls ?>"><?= htmlspecialchars($u['ruolo']) ?></span></td>
                                <td style="font-size:10px;color:var(--muted)"><?= htmlspecialchars($u['filiale'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="no-data">Nessun utente trovato.</p>
                    <?php endif; ?>

                    <div class="sec-title" style="margin-top:16px;">Permessi</div>
                    <div class="perm-box">
                        <?php
                        $voci_perm = [
                            'Dashboard globale',
                            'Tutte le filiali',
                            'Gestione utenti',
                            'Report finanziari',
                            'Propria filiale',
                        ];
                        foreach ($voci_perm as $i => $voce):
                            $ok = $permessi[$i] ?? false;
                        ?>
                        <div class="perm-row">
                            <span><?= htmlspecialchars($voce) ?></span>
                            <span class="<?= $ok ? 'pyes' : 'pno' ?>"><?= $ok ? '✓' : '–' ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php elseif ($pagina === 'filiale'): ?>

            <!-- ── FILIALI ── -->
            <div class="topbar">
                <h1>Gestione <span>Filiali</span></h1>
                <div class="topbar-right">
                    <span class="role-badge"><?= htmlspecialchars($sess_ruolo) ?></span>
                    <div class="avatar-circle"><?= htmlspecialchars($sess_initiali) ?></div>
                </div>
            </div>

            <?php if (empty($filiali)): ?>
                <p class="no-data">Nessuna filiale disponibile per il tuo ruolo.</p>
            <?php else: ?>
            <div class="filiali-grid">
                <?php foreach ($filiali as $f): ?>
                <div class="filiale-card">
                    <div class="filiale-nome"><?= htmlspecialchars($f['città']) ?></div>
                    <div class="filiale-citta">Filiale #<?= $f['id'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($pagina === 'prodotti'): ?>

            <!-- ── PRODOTTI ── -->
            <div class="topbar">
                <h1>Gestione <span>Prodotti</span></h1>
                <div class="topbar-right">
                    <span class="role-badge"><?= htmlspecialchars($sess_ruolo) ?></span>
                    <div class="avatar-circle"><?= htmlspecialchars($sess_initiali) ?></div>
                </div>
            </div>

            <?php if ($sess_livello < 2): ?>
                <p class="no-data">Accesso non autorizzato per il tuo ruolo.</p>
            <?php elseif (empty($prodotti)): ?>
                <p class="no-data">Nessun prodotto trovato.</p>
            <?php else: ?>
            <div class="page-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prodotto</th>
                            <th>Categoria</th>
                            <th>Prezzo</th>
                            <th>Q.tà Magazzino</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prodotti as $p): ?>
                        <tr>
                            <td style="color:var(--muted)"><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['nome']) ?></td>
                            <td><span class="badge badge-cat"><?= htmlspecialchars($p['categoria']) ?></span></td>
                            <td>€<?= number_format($p['prezzo'], 0, ',', '.') ?></td>
                            <td><?= $p['quantita_magazzino'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php elseif ($pagina === 'vendite'): ?>

            <!-- ── VENDITE ── -->
            <div class="topbar">
                <h1>Storico <span>Vendite</span></h1>
                <div class="topbar-right">
                    <span class="role-badge"><?= htmlspecialchars($sess_ruolo) ?></span>
                    <div class="avatar-circle"><?= htmlspecialchars($sess_initiali) ?></div>
                </div>
            </div>

            <?php if ($sess_livello < 3): ?>
                <p class="no-data">Accesso ai report finanziari riservato ad Admin e Direttori.</p>
            <?php elseif (empty($vendite)): ?>
                <p class="no-data">Nessuna vendita registrata.</p>
            <?php else: ?>
            <div class="page-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Data</th>
                            <th>Operatore</th>
                            <th>Totale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendite as $v): ?>
                        <tr>
                            <td style="color:var(--muted)"><?= $v['id'] ?></td>
                            <td><?= htmlspecialchars($v['data']) ?></td>
                            <td><?= htmlspecialchars($v['utente_cognome'].' '.$v['utente_nome']) ?></td>
                            <td><strong>€<?= number_format($v['totale'], 0, ',', '.') ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php endif; ?>

        </div><!-- /main -->
    </div><!-- /erp -->

<?php endif; ?>

</body>
</html>
