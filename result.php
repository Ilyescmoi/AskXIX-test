<?php
/**
 * result.php — page de résultats affichée après génération (Post/Redirect/Get).
 * Propose le téléchargement du rapport, de l'annexe d'audit et du CSV de traçabilité.
 */
$config = require __DIR__ . '/config.php';

$t = (string) ($_GET['t'] ?? '');
if (!preg_match('/^\d{8}-\d{6}$/', $t)) {
    header('Location: index.php');
    exit;
}

$dir   = __DIR__ . '/storage/reports';
$files = [
    'report' => "rapport-$t.pdf",
    'audit'  => "audit-$t.pdf",
    'csv'    => "tracabilite-$t.csv",
];
$meta = is_file("$dir/meta-$t.json")
    ? (json_decode((string) file_get_contents("$dir/meta-$t.json"), true) ?: [])
    : [];

$has = static fn(string $f): bool => is_file(__DIR__ . '/storage/reports/' . $f);
$dl  = static fn(string $f): string => 'download.php?f=' . rawurlencode($f);
$h   = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapport prêt · <?= $h($config['report']['app_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0b1020; --card:rgba(255,255,255,.045); --bd:rgba(255,255,255,.09);
                --text:#e8eaf4; --muted:#9aa0b8; --accent:#7c83ff; --accent-2:#22d3ee; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:'Inter',system-ui,sans-serif; color:var(--text);
               background:var(--bg);
               background-image:
                 radial-gradient(900px 600px at 12% -10%, rgba(124,131,255,.28), transparent 60%),
                 radial-gradient(800px 600px at 100% 0%, rgba(34,211,238,.18), transparent 55%);
               display:flex; align-items:center; justify-content:center; padding:32px 16px; }
        .shell { width:100%; max-width:560px; }
        .check { width:54px; height:54px; border-radius:16px; margin:0 auto 16px; display:grid; place-items:center;
                 background:linear-gradient(135deg,#34d399,#22d3ee); box-shadow:0 12px 34px -10px rgba(34,211,238,.7); }
        h1 { text-align:center; font-size:1.5rem; font-weight:800; margin:0 0 6px; }
        .sub { text-align:center; color:var(--muted); margin:0 0 22px; font-size:.92rem; }
        .summary { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin-bottom:22px; }
        .stat { background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:10px 14px; text-align:center; min-width:96px; }
        .stat .v { font-size:1.15rem; font-weight:700; }
        .stat .k { font-size:.7rem; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
        .card { display:flex; align-items:center; gap:14px; text-decoration:none; color:inherit;
                background:var(--card); border:1px solid var(--bd); border-radius:14px; padding:15px 16px; margin-bottom:10px;
                transition:border-color .2s, transform .15s, background .2s; }
        .card:hover { border-color:rgba(124,131,255,.7); transform:translateY(-1px); background:rgba(124,131,255,.06); }
        .card.disabled { opacity:.5; pointer-events:none; }
        .ic { width:42px; height:42px; border-radius:11px; flex:0 0 auto; display:grid; place-items:center;
              background:linear-gradient(135deg, rgba(124,131,255,.3), rgba(34,211,238,.25)); border:1px solid var(--bd); }
        .ic.gold { background:linear-gradient(135deg, rgba(184,146,79,.35), rgba(124,131,255,.2)); }
        .ic.green { background:linear-gradient(135deg, rgba(52,211,153,.3), rgba(34,211,238,.22)); }
        .meta { flex:1; }
        .meta .t { font-weight:600; }
        .meta .d { font-size:.8rem; color:var(--muted); }
        .arrow { color:var(--muted); font-size:1.2rem; }
        .back { display:block; text-align:center; margin-top:18px; color:var(--muted); text-decoration:none; font-size:.9rem; }
        .back:hover { color:var(--text); }
        .note { margin-top:16px; font-size:.78rem; color:var(--muted); text-align:center; line-height:1.5; }
    </style>
</head>
<body>
<div class="shell">

    <div class="check">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#06281f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </div>
    <h1>Votre rapport est prêt</h1>
    <p class="sub">Généré le <?= $h($meta['generated_at'] ?? '—') ?><?= empty($meta['ai_enabled']) ? ' · mode factuel' : ' · analyse IA' ?></p>

    <?php if ($meta): ?>
    <div class="summary">
        <div class="stat"><div class="v"><?= (int) ($meta['messages'] ?? 0) ?></div><div class="k">Messages</div></div>
        <div class="stat"><div class="v"><?= (int) ($meta['users'] ?? 0) ?></div><div class="k">Visiteurs</div></div>
        <div class="stat"><div class="v"><?= $h($meta['non_response_rate'] ?? 0) ?>%</div><div class="k">Non-réponse</div></div>
        <?php if (!empty($meta['ai_enabled'])): ?>
        <div class="stat"><div class="v" style="font-size:.85rem;"><?= $h($meta['top_topic'] ?? '—') ?></div><div class="k">Sujet métier n°1</div></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Rapport principal -->
    <a class="card <?= $has($files['report']) ? '' : 'disabled' ?>" href="<?= $h($dl($files['report'])) ?>">
        <span class="ic">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dfe3ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
        </span>
        <span class="meta"><span class="t">Rapport d'analyse (1/2)</span><span class="d">PDF · synthèse, sujets, qualité — chaque exemple porte sa référence #</span></span>
        <span class="arrow">↓</span>
    </a>

    <!-- Document de traçabilité -->
    <a class="card <?= $has($files['audit']) ? '' : 'disabled' ?>" href="<?= $h($dl($files['audit'])) ?>">
        <span class="ic gold">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f1e2c4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3l7 4v5c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V7z"/></svg>
        </span>
        <span class="meta"><span class="t">Traçabilité &amp; raisonnement (2/2)</span><span class="d">PDF · sources de chaque chiffre, journal des appels IA, registre complet des messages</span></span>
        <span class="arrow">↓</span>
    </a>

    <!-- CSV de traçabilité -->
    <a class="card <?= $has($files['csv']) ? '' : 'disabled' ?>" href="<?= $h($dl($files['csv'])) ?>">
        <span class="ic green">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c6f6e2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M4 10h16M10 4v16"/></svg>
        </span>
        <span class="meta"><span class="t">Registre exploitable (CSV)</span><span class="d">CSV · textes intégraux, étiquettes brutes IA, lots et statuts — tout recalculable</span></span>
        <span class="arrow">↓</span>
    </a>

    <div class="note">Chaque message porte une référence #NNN commune aux trois fichiers : un chiffre du rapport se vérifie dans le document de traçabilité, puis dans le CSV, jusqu'au fichier source (empreinte SHA-256).</div>
    <a class="back" href="index.php">← Nouvelle analyse</a>

</div>
</body>
</html>
