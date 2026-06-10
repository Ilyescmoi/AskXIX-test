<?php
/**
 * index.php — page d'accueil : upload du CSV + bouton « Générer le rapport ».
 * UI uniquement (design custom + Bootstrap 5). L'orchestration est dans generate.php.
 */
$config = require __DIR__ . '/config.php';
$aiReady = $config['ai']['api_key'] !== '';
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($config['report']['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b1020;
            --card: rgba(255, 255, 255, .045);
            --card-border: rgba(255, 255, 255, .09);
            --text: #e8eaf4;
            --muted: #9aa0b8;
            --accent: #7c83ff;
            --accent-2: #22d3ee;
            --danger: #ff7a8a;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text);
            background: var(--bg);
            background-image:
                radial-gradient(900px 600px at 12% -10%, rgba(124, 131, 255, .28), transparent 60%),
                radial-gradient(800px 600px at 100% 0%, rgba(34, 211, 238, .18), transparent 55%),
                radial-gradient(700px 700px at 50% 120%, rgba(236, 72, 153, .15), transparent 60%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .shell { width: 100%; max-width: 600px; }

        .brand {
            display: flex; align-items: center; gap: 12px;
            justify-content: center; margin-bottom: 22px;
        }
        .brand .spark {
            width: 42px; height: 42px; border-radius: 13px;
            background: linear-gradient(135deg, var(--accent), #b69bff 55%, var(--accent-2));
            display: grid; place-items: center;
            box-shadow: 0 10px 30px -8px rgba(124, 131, 255, .65);
        }
        .brand .spark svg { width: 22px; height: 22px; }
        .brand .name { font-weight: 700; letter-spacing: .2px; font-size: 1.05rem; }
        .brand .name span { color: var(--muted); font-weight: 500; }

        .hero { text-align: center; margin-bottom: 26px; }
        .hero h1 {
            font-size: clamp(1.7rem, 4.5vw, 2.4rem);
            font-weight: 800; line-height: 1.12; margin: 0 0 12px;
            background: linear-gradient(120deg, #ffffff 18%, #c5c9ff 55%, var(--accent-2));
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero p { color: var(--muted); margin: 0; font-size: .98rem; line-height: 1.55; }

        .card-glass {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            padding: 26px;
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 30px 70px -30px rgba(0, 0, 0, .8), inset 0 1px 0 rgba(255, 255, 255, .06);
        }

        .alert-soft {
            display: flex; gap: 10px; align-items: flex-start;
            background: rgba(255, 122, 138, .1);
            border: 1px solid rgba(255, 122, 138, .35);
            color: #ffd7dd; border-radius: 14px; padding: 12px 14px;
            font-size: .9rem; margin-bottom: 18px;
        }
        .alert-soft svg { flex: 0 0 auto; margin-top: 1px; }

        /* Zone d'upload */
        .dropzone {
            position: relative;
            border: 1.5px dashed rgba(255, 255, 255, .2);
            border-radius: 16px;
            padding: 26px 18px; text-align: center;
            transition: border-color .2s, background .2s, transform .15s;
            cursor: pointer; background: rgba(255, 255, 255, .02);
        }
        .dropzone:hover { border-color: rgba(124, 131, 255, .7); background: rgba(124, 131, 255, .07); }
        .dropzone.dragover { border-color: var(--accent-2); background: rgba(34, 211, 238, .1); transform: scale(1.01); }
        .dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .dropzone .dz-icon {
            width: 48px; height: 48px; margin: 0 auto 12px; border-radius: 14px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, rgba(124, 131, 255, .25), rgba(34, 211, 238, .25));
            border: 1px solid rgba(255, 255, 255, .12);
        }
        .dropzone .dz-title { font-weight: 600; margin-bottom: 3px; }
        .dropzone .dz-sub { color: var(--muted); font-size: .82rem; }
        .dropzone .dz-file { display: none; color: var(--accent-2); font-weight: 600; word-break: break-all; }
        .dropzone.has-file .dz-default { display: none; }
        .dropzone.has-file .dz-file { display: block; }

        .hint-cols {
            margin-top: 10px; font-size: .78rem; color: var(--muted); text-align: center;
        }
        .hint-cols code {
            background: rgba(255, 255, 255, .07); color: #cdd2f0;
            padding: 1px 6px; border-radius: 6px; font-size: .76rem;
        }

        /* Switch IA */
        .ai-row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            margin: 18px 0 4px; padding: 12px 14px;
            background: rgba(255, 255, 255, .03); border: 1px solid var(--card-border);
            border-radius: 14px;
        }
        .ai-row .lbl { font-size: .9rem; font-weight: 500; }
        .ai-row .sub { font-size: .78rem; color: var(--muted); }
        .pill { font-size: .72rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
        .pill-on { background: rgba(52, 211, 153, .16); color: #6ee7b7; border: 1px solid rgba(52, 211, 153, .35); }
        .pill-off { background: rgba(255, 122, 138, .14); color: #ffb0b9; border: 1px solid rgba(255, 122, 138, .32); }
        .form-check-input { width: 2.4em; height: 1.3em; background-color: rgba(255,255,255,.1); border-color: rgba(255,255,255,.2); cursor: pointer; }
        .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }

        /* Boutons */
        .btn-generate {
            width: 100%; border: 0; border-radius: 14px; padding: 15px 18px;
            font-weight: 700; font-size: 1rem; color: #fff; cursor: pointer;
            background: linear-gradient(120deg, var(--accent), #9a7bff 50%, var(--accent-2));
            background-size: 180% 180%;
            box-shadow: 0 16px 38px -14px rgba(124, 131, 255, .8);
            transition: transform .15s, box-shadow .2s, background-position .4s;
            margin-top: 18px;
        }
        .btn-generate:hover { transform: translateY(-2px); background-position: 100% 0; box-shadow: 0 20px 46px -14px rgba(124, 131, 255, .95); }
        .btn-generate:active { transform: translateY(0); }
        .btn-sample {
            width: 100%; margin-top: 10px; background: transparent;
            border: 1px solid var(--card-border); color: var(--muted);
            border-radius: 14px; padding: 11px; font-size: .9rem; font-weight: 500; cursor: pointer;
            transition: border-color .2s, color .2s;
        }
        .btn-sample:hover { color: var(--text); border-color: rgba(255, 255, 255, .25); }

        .footnote { text-align: center; color: var(--muted); font-size: .78rem; margin-top: 20px; }
        .chips { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 16px; }
        .chip {
            font-size: .74rem; color: #c8cdec; padding: 5px 11px; border-radius: 999px;
            background: rgba(255, 255, 255, .04); border: 1px solid var(--card-border);
            display: inline-flex; align-items: center; gap: 5px;
        }
        .chip .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent-2); }
    </style>
</head>
<body>
<div class="shell">

    <div class="brand">
        <div class="spark">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/>
            </svg>
        </div>
        <div class="name">Chatbot Analytics <span>· rapport PDF</span></div>
    </div>

    <div class="hero">
        <h1>Transforme tes logs de chatbot<br>en rapport PDF</h1>
        <p>
            Charge un CSV, obtiens une analyse en un clic.<br>
            Les chiffres sont calculés en PHP — l'IA ne fait que classer le texte.
        </p>
    </div>

    <div class="card-glass">

        <?php if ($error !== ''): ?>
            <div class="alert-soft">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <form action="generate.php" method="post" enctype="multipart/form-data" id="report-form">

            <label class="dropzone" id="dropzone">
                <input type="file" id="csv" name="csv" accept=".csv,text/csv">
                <div class="dz-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dfe3ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5-5 5 5"/><path d="M12 5v12"/>
                    </svg>
                </div>
                <div class="dz-default">
                    <div class="dz-title">Dépose ton fichier CSV ici</div>
                    <div class="dz-sub">ou clique pour parcourir</div>
                </div>
                <div class="dz-file" id="dz-file"></div>
            </label>

            <div class="hint-cols">
                Colonnes détectées automatiquement :
                <code>date</code> <code>user_id</code> <code>question</code> <code>reponse</code>
            </div>

            <div class="ai-row">
                <div>
                    <div class="lbl">Analyse IA</div>
                    <div class="sub">Intentions · sujets · qualité estimée</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($aiReady): ?>
                        <span class="pill pill-on">clé détectée</span>
                    <?php else: ?>
                        <span class="pill pill-off">pas de clé</span>
                    <?php endif; ?>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="use_ai" name="use_ai" value="1"
                               <?= $aiReady ? 'checked' : 'disabled' ?>>
                    </div>
                </div>
            </div>
            <?php if (!$aiReady): ?>
                <div class="sub" style="font-size:.76rem;color:var(--muted);padding:2px 4px 0;">
                    Sans clé, le rapport reste <strong>factuel</strong> (étiquettes « indetermine »). Ajoute <code>MISTRAL_API_KEY</code> dans le <code>.env</code>.
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-generate">Générer le rapport →</button>
            <button type="submit" name="sample" value="1" class="btn-sample" formnovalidate>
                ou tester avec le jeu d'exemple
            </button>
        </form>
    </div>

    <div class="chips">
        <span class="chip"><span class="dot"></span> Calculs déterministes</span>
        <span class="chip"><span class="dot"></span> Anti-hallucination</span>
        <span class="chip"><span class="dot"></span> Export mPDF</span>
    </div>

    <div class="footnote">Génération 100 % locale · aucune donnée n'est calculée par l'IA</div>

</div>

<script>
    // Affichage du nom de fichier + états de glisser-déposer.
    const dz = document.getElementById('dropzone');
    const input = document.getElementById('csv');
    const fileLabel = document.getElementById('dz-file');

    function showFile() {
        if (input.files && input.files.length) {
            fileLabel.textContent = '✓ ' + input.files[0].name;
            dz.classList.add('has-file');
        } else {
            dz.classList.remove('has-file');
        }
    }
    input.addEventListener('change', showFile);

    ['dragenter', 'dragover'].forEach(ev =>
        dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(ev =>
        dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
    dz.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; showFile(); }
    });
</script>
</body>
</html>
