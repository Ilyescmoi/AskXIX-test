<?php
/**
 * Gabarit HTML du rapport principal (rendu par mPDF). AFFICHAGE UNIQUEMENT.
 * Toutes les valeurs viennent de ReportData::build(). Aucun calcul de donnée ici
 * (juste de la mise en forme : couleurs selon seuils, pic d'activité, etc.).
 *
 * TRAÇABILITÉ : chaque exemple cité porte sa référence #NNN ; chaque section
 * renvoie vers la section du Document de traçabilité (2/2) qui la justifie.
 *
 * @var string $generated_at
 * @var string $app_name
 * @var string $subtitle
 * @var bool   $ai_enabled
 * @var int    $ref_width
 * @var int    $refs_max
 * @var array  $source        ['file','bytes','sha256','token']
 * @var array  $stats
 * @var array  $engagement
 * @var array  $highlights    chaque point : type, label, text, ids[], annexe
 * @var array  $intents       chaque ligne : key, name, count, percent, ids[]
 * @var array  $topics
 * @var array  $quality
 * @var array  $hallucination ['count','rate','ids']
 * @var array  $gaps          chaque ligne : ..., no_answer_ids[]
 * @var array  $ai_issues     ['failed_ids','corrected','missing']
 * @var array  $top_questions chaque ligne : question, count, topic, ids[]
 * @var array  $unanswered    chaque carte : id, question, reponse, topic, regle, date
 * @var array  $best_answers  chaque carte : id, question, reponse
 */

if (!function_exists('e')) {
    // ENT_SUBSTITUTE : un octet UTF-8 invalide résiduel donne U+FFFD au lieu de
    // vider TOUT le texte (la traçabilité interdit de perdre un message en silence).
    function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('ref_pill')) {
    // Pastille de référence #NNN (notation commune aux 3 fichiers de l'analyse).
    function ref_pill(int $id, int $width): string {
        return '<span class="ref">' . e(Text::ref($id, $width)) . '</span>';
    }
}
if (!function_exists('refs_inline')) {
    // Liste courte de références : #004, #012, #019 (+9). Cap pour rester lisible.
    function refs_inline(array $ids, int $width, int $max): string {
        if ($ids === []) { return ''; }
        $shown = array_slice($ids, 0, $max);
        $out = implode(' ', array_map(static fn($id) => ref_pill((int) $id, $width), $shown));
        $rest = count($ids) - count($shown);
        return $out . ($rest > 0 ? ' <span class="src-more">(+' . $rest . ')</span>' : '');
    }
}
if (!function_exists('hl_style')) {
    // Couleur [texte, fond, bord] selon le type de point de synthèse.
    function hl_style(string $t): array {
        return match ($t) {
            'risk'    => ['#b3322a', '#fbeceb', '#e7b4ae'],
            'warning' => ['#8a5e12', '#fdf4e5', '#eed9ad'],
            'good'    => ['#23694a', '#e9f5ee', '#abd8bf'],
            default   => ['#2b4a73', '#eaf1fa', '#b9cce6'], // info
        };
    }
}
if (!function_exists('rate_color')) {
    // Couleur [texte, fond] d'un taux de non-réponse (plus c'est haut, plus c'est rouge).
    function rate_color(float $rate): array {
        if ($rate >= 30) return ['#b3322a', '#fbeceb'];
        if ($rate >= 15) return ['#8a5e12', '#fdf4e5'];
        return ['#23694a', '#e9f5ee'];
    }
}
if (!function_exists('bar')) {
    // Barre horizontale en TABLE imbriquée : mPDF ne rend ni les div à fond/hauteur
    // dans une cellule, ni display:block sur un span — les fonds de td, si.
    // Le &nbsp; en 1pt est indispensable : mPDF effondre les cellules vides.
    function bar(float $pct, string $color = '#34598c'): string {
        $w = (int) round(max(0, min(100, $pct)));
        $cell = static fn(string $bg, string $width = '') =>
            '<td' . ($width !== '' ? ' width="' . $width . '"' : '') . ' class="vb" style="background:' . $bg . '; height:3mm;">&nbsp;</td>';
        if ($w <= 0) {
            return '<table class="barwrap" cellpadding="0" cellspacing="0" width="100%"><tr>' . $cell('#eef2f7') . '</tr></table>';
        }
        if ($w >= 100) {
            return '<table class="barwrap" cellpadding="0" cellspacing="0" width="100%"><tr>' . $cell($color) . '</tr></table>';
        }
        return '<table class="barwrap" cellpadding="0" cellspacing="0" width="100%"><tr>'
             . $cell($color, $w . '%') . $cell('#eef2f7')
             . '</tr></table>';
    }
}
if (!function_exists('fmt_peak')) {
    // Valeur d'un pic ('peak_hour'/'peak_day' de Stats) + mention d'ex æquo.
    function fmt_peak(?array $peak, callable $fmt): string {
        if ($peak === null) { return 'n/d'; }
        return $fmt($peak['key']) . ($peak['ties'] > 1 ? '*' : '');
    }
}

$period  = $stats['period'];
$nonResp = $stats['non_response'];
$hours   = $stats['by_hour'];
$maxHour = max(1, max($hours));

// Pics calculés par Stats (tie-break documenté en annexe §3) — AUCUN calcul ici.
$peakHour = $stats['peak_hour']; // ['key','count','ties'] ou null
$peakDay  = $stats['peak_day'];
$peakHourLabel = fmt_peak($peakHour, static fn($k) => str_pad((string) $k, 2, '0', STR_PAD_LEFT) . 'h');
$peakDayLabel  = fmt_peak($peakDay, static fn($k) => (new DateTimeImmutable((string) $k))->format('d/m/Y'));
$hasTies = ($peakHour['ties'] ?? 1) > 1 || ($peakDay['ties'] ?? 1) > 1;
$shaShort = isset($source['sha256']) ? substr((string) $source['sha256'], 0, 12) : '';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; color: #283544; font-size: 10.5pt; line-height: 1.45; }
    .muted { color: #7a8594; }
    .small { font-size: 8.5pt; }

    /* ---------- Badges de nature (clé de lecture du rapport) ---------- */
    .fact  { display: inline-block; font-size: 7.5pt; color: #23694a; background: #e9f5ee;
             border: 1px solid #abd8bf; border-radius: 8px; padding: 1px 7px; vertical-align: middle; }
    .estim { display: inline-block; font-size: 7.5pt; color: #8a5e12; background: #fdf4e5;
             border: 1px solid #eed9ad; border-radius: 8px; padding: 1px 7px; vertical-align: middle; }

    /* ---------- Pastilles de référence #NNN ---------- */
    .ref { display: inline-block; font-size: 7pt; font-family: monospace; color: #2b4a73;
           background: #eaf1fa; border: 1px solid #b9cce6; border-radius: 8px; padding: 0.5px 5px; }
    .src { font-size: 7.5pt; color: #8a93a3; margin-top: 3px; }
    .src-more { font-size: 7pt; color: #8a93a3; }

    /* ---------- En-tête / pied de page courants ---------- */
    .run-hd { width: 100%; font-size: 7.5pt; color: #7a8594; border-bottom: 0.4mm solid #b8924f; }
    .run-hd td { padding-bottom: 1.4mm; }
    .run-hd .brand { color: #14263d; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .run-ft { width: 100%; font-size: 7.5pt; color: #9aa3b0; border-top: 0.2mm solid #e2e8f0; }
    .run-ft td { padding-top: 1.4mm; }

    /* ---------- Page de garde ---------- */
    .cover-band { background: #14263d; color: #fff; padding: 46px 40px 40px; }
    .cover-kicker { font-size: 9pt; letter-spacing: 3px; color: #b8924f; text-transform: uppercase; }
    .cover-band h1 { font-size: 26pt; font-weight: 800; margin: 14px 0 0; color: #fff; line-height: 1.1; }
    .cover-rule { width: 64px; height: 3px; background: #b8924f; margin: 18px 0; }
    .cover-sub { font-size: 11pt; color: #c5cfdb; max-width: 460px; }
    .cover-meta { padding: 26px 40px 10px; }
    table.metacards { width: 100%; border-collapse: separate; border-spacing: 10px 0; }
    table.metacards td { width: 33%; background: #f5f7fa; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; }
    .metacards .k { font-size: 8pt; color: #7a8594; text-transform: uppercase; letter-spacing: .5px; }
    .metacards .v { font-size: 14pt; font-weight: 700; color: #14263d; margin-top: 3px; }
    .cover-trace { margin: 14px 40px 0; background: #eaf1fa; border: 1px solid #b9cce6; border-radius: 10px;
                   padding: 12px 14px; font-size: 8.8pt; color: #2b4a73; }
    .cover-foot { padding: 14px 40px 0; color: #9aa3b0; font-size: 8.5pt; }

    /* ---------- Sections ---------- */
    h2.section { font-size: 13.5pt; color: #14263d; border-left: 4px solid #b8924f;
                 padding-left: 11px; margin: 26px 0 12px; }
    .lead { color: #5b6573; margin: 0 0 12px; font-size: 9.5pt; }
    .verif { font-size: 7.8pt; color: #8a93a3; margin: 4px 0 0; }

    /* ---------- Synthèse "En bref" ---------- */
    table.brief { width: 100%; border-collapse: separate; border-spacing: 0 7px; }
    table.brief td { padding: 10px 13px; border-radius: 9px; vertical-align: top; }
    .brief .b-label { font-size: 8pt; text-transform: uppercase; letter-spacing: .6px; font-weight: 700; }
    .brief .b-text { font-size: 10pt; color: #2b3744; }

    /* ---------- Cartes KPI ---------- */
    table.kpi { width: 100%; border-collapse: separate; border-spacing: 8px; }
    table.kpi td { width: 25%; background: #fff; border: 1px solid #e2e8f0; border-radius: 11px;
                   padding: 12px 10px; text-align: center; }
    .kpi .num { font-size: 17pt; font-weight: 800; color: #14263d; }
    .kpi .lbl { font-size: 8pt; color: #7a8594; margin-top: 2px; }

    /* ---------- Tables de données ---------- */
    table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.data th, table.data td { border-bottom: 1px solid #e8edf2; padding: 6px 9px; text-align: left; font-size: 9.5pt; }
    table.data th { background: #14263d; color: #fff; border-bottom: none; font-weight: 600; font-size: 9pt; }
    table.data td.num { text-align: right; white-space: nowrap; }
    table.data tr:nth-child(even) td { background: #f8fafc; }
    .pill { display: inline-block; font-size: 8pt; padding: 1px 8px; border-radius: 9px;
            background: #eef2f7; color: #45556b; }
    .tfoot-note { font-size: 7.8pt; color: #8a93a3; margin: 5px 0 0; }

    /* ---------- Barres (tables imbriquées — seuls les fonds de td sont fiables en mPDF) ---------- */
    table.barwrap { border-collapse: collapse; }
    .vb { font-size: 1pt; line-height: 1pt; } /* remplissage &nbsp; sans gonfler la hauteur */

    /* ---------- Activité par heure (colonnes en tables imbriquées) ---------- */
    table.hours { width: 100%; border-collapse: collapse; margin-top: 3mm; table-layout: fixed; }
    table.hours td.hcell { width: 4.16%; vertical-align: bottom; padding: 0 0.5mm; border-bottom: 0.4mm solid #14263d; }
    table.hours table.hcol { width: 100%; border-collapse: collapse; }
    table.hours tr.hlbl td { border-bottom: none; font-size: 6.5pt; color: #7a8594;
                             text-align: center; padding-top: 1mm; }

    /* ---------- Q/R ---------- */
    .qa { border: 1px solid #e8edf2; border-radius: 9px; padding: 9px 12px; margin-bottom: 6px;
          page-break-inside: avoid; }
    .qa .q { font-weight: 700; color: #14263d; }
    .qa .a { color: #56616f; font-size: 9pt; }
    .qa .tag { float: right; font-size: 7.5pt; }
    .qa .meta-line { font-size: 7.8pt; color: #8a93a3; margin-top: 3px; }

    .note { background: #f5f7fa; border: 1px solid #e2e8f0; border-radius: 9px; padding: 9px 12px; font-size: 8.5pt; color: #5b6573; }
    table.legend { width: 100%; border-collapse: separate; border-spacing: 0 4px; }
    table.legend td { padding: 7px 11px; border-radius: 8px; font-size: 8.8pt; vertical-align: top; }
</style>
</head>
<body>

<!-- Pied/en-tête courants : définis ici, activés APRÈS la page de garde. -->
<htmlpageheader name="hdr">
    <table class="run-hd"><tr>
        <td class="brand"><?= e($app_name) ?></td>
        <td align="right">Document 1/2 — Rapport</td>
    </tr></table>
</htmlpageheader>
<htmlpagefooter name="ftr">
    <table class="run-ft"><tr>
        <td>Généré le <?= e($generated_at) ?> · dossier <?= e($source['token'] ?? '') ?></td>
        <td align="right">Page {PAGENO} / {nbpg}</td>
    </tr></table>
</htmlpagefooter>

<!-- ===================== PAGE DE GARDE ===================== -->
<bookmark content="Page de garde" level="0" />
<div class="cover-band">
    <div class="cover-kicker">Rapport d'analyse · Document 1/2</div>
    <h1><?= e($app_name) ?></h1>
    <div class="cover-rule"></div>
    <div class="cover-sub"><?= e($subtitle) ?></div>
</div>
<div class="cover-meta">
    <table class="metacards">
        <tr>
            <td>
                <div class="k">Période couverte</div>
                <div class="v" style="font-size:11pt;"><?= e($period['start'] ?? 'n/d') ?><br><span class="muted small">au</span> <?= e($period['end'] ?? 'n/d') ?></div>
            </td>
            <td>
                <div class="k">Messages analysés</div>
                <div class="v"><?= (int) $stats['total_messages'] ?></div>
            </td>
            <td>
                <div class="k">Visiteurs uniques</div>
                <div class="v"><?= (int) $stats['unique_users'] ?></div>
            </td>
        </tr>
    </table>
</div>
<div class="cover-trace">
    <strong>Chaque chiffre de ce rapport est traçable.</strong>
    Chaque message du fichier source porte une référence <span class="ref"><?= e(Text::ref(1, $ref_width)) ?></span>…<span class="ref"><?= e(Text::ref((int) $stats['total_messages'], $ref_width)) ?></span>,
    commune à ce rapport, au <strong>Document de traçabilité (2/2)</strong> — qui justifie chaque indicateur,
    journalise les analyses IA et liste l'intégralité des messages — et au CSV de traçabilité.
    <br>Fichier source : <strong><?= e($source['file'] ?? 'n/d') ?></strong>
    <?php if ($shaShort !== ''): ?> · empreinte SHA-256 <span style="font-family:monospace;"><?= e($shaShort) ?>…</span><?php endif; ?>
</div>
<div class="cover-foot">
    Généré le <?= e($generated_at) ?> ·
    <?php if ($ai_enabled): ?>Analyse IA activée (typologies estimées, listes fermées)<?php else: ?>Mode factuel uniquement (sans IA)<?php endif; ?>
    · Les indicateurs chiffrés sont calculés de façon déterministe.
</div>

<pagebreak />
<sethtmlpageheader name="hdr" value="on" show-this-page="1" />
<sethtmlpagefooter name="ftr" value="on" />

<!-- ===================== SYNTHÈSE EN BREF ===================== -->
<bookmark content="En bref" level="0" />
<h2 class="section">En bref <?= $ai_enabled ? '<span class="estim">calculé + estimé</span>' : '<span class="fact">calculé</span>' ?></h2>
<p class="lead">Les points clés de la période, générés automatiquement à partir des chiffres. Sous chaque point : les messages sources.</p>
<table class="brief">
    <?php foreach ($highlights as $hgt): [$fg, $bgc, $bd] = hl_style($hgt['type']); ?>
        <tr>
            <td style="background: <?= $bgc ?>; border: 1px solid <?= $bd ?>;">
                <div class="b-label" style="color: <?= $fg ?>;"><?= e($hgt['label']) ?></div>
                <div class="b-text"><?= e($hgt['text']) ?></div>
                <?php if (!empty($hgt['ids'])): ?>
                    <div class="src">Sources : <?= refs_inline($hgt['ids'], $ref_width, $refs_max) ?>
                        · vérifiable : Traçabilité <?= e($hgt['annexe']) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== INDICATEURS CLÉS ===================== -->
<bookmark content="Indicateurs clés" level="0" />
<h2 class="section">Indicateurs clés <span class="fact">calculé</span></h2>
<table class="kpi">
    <tr>
        <td><div class="num"><?= (int) $stats['total_messages'] ?></div><div class="lbl">Messages</div></td>
        <td><div class="num"><?= (int) $stats['unique_users'] ?></div><div class="lbl">Visiteurs uniques</div></td>
        <td><div class="num"><?= e($stats['messages_per_user']) ?></div><div class="lbl">Questions / visiteur</div></td>
        <td><div class="num"><?= e($engagement['returning_rate']) ?>%</div><div class="lbl">Visiteurs revenus</div></td>
    </tr>
    <tr>
        <?php [$nrFg] = rate_color((float) $nonResp['rate']); ?>
        <td><div class="num" style="color: <?= $nrFg ?>;"><?= e($nonResp['rate']) ?>%</div><div class="lbl">Taux de non-réponse</div></td>
        <td><div class="num"><?= $period['span_days'] !== null ? (int) $period['span_days'] : 'n/d' ?></div><div class="lbl">Jours couverts</div></td>
        <td><div class="num"><?= e($peakHourLabel) ?></div><div class="lbl">Heure de pic</div></td>
        <td><div class="num" style="font-size:12pt;"><?= e($peakDayLabel) ?></div><div class="lbl">Jour le plus actif</div></td>
    </tr>
</table>
<p class="verif">
    Engagement : <?= (int) $engagement['returning'] ?> visiteur(s) ont posé plusieurs questions,
    <?= (int) $engagement['single'] ?> n'en ont posé qu'une seule.
    <?php if ($hasTies): ?>* valeur ex æquo : plusieurs heures/jours atteignent le même maximum (détail : Traçabilité §3).<?php endif; ?>
    Formule de chaque indicateur : Document de traçabilité, §3.
</p>

<!-- ===================== ACTIVITÉ PAR HEURE ===================== -->
<bookmark content="Activité par heure" level="0" />
<h2 class="section">Quand vos visiteurs écrivent <span class="fact">calculé</span></h2>
<?php if ((int) ($stats['timed_rows'] ?? 0) === 0): ?>
    <p class="muted">Le fichier source ne contient pas d'heures exploitables (dates sans heure) : le profil horaire n'est pas calculable. Détail : Traçabilité §3.</p>
<?php else: ?>
    <p class="lead">Répartition des <?= (int) $stats['timed_rows'] ?> messages horodatés par heure de la journée.
        La barre dorée est l'heure de pic (<?= e($peakHourLabel) ?>, <?= (int) ($peakHour['count'] ?? 0) ?> messages).</p>
    <table class="hours">
        <tr>
        <?php foreach ($hours as $h => $c):
            $mm = round(16 * $c / $maxHour, 1);
            $isPeak = $peakHour !== null && $h === $peakHour['key'] && $c > 0; ?>
            <td class="hcell">
                <?php if ($c > 0): ?>
                <table class="hcol" cellpadding="0" cellspacing="0"><tr>
                    <td class="vb" style="height:<?= max(0.8, $mm) ?>mm; background:<?= $isPeak ? '#b8924f' : '#34598c' ?>;">&nbsp;</td>
                </tr></table>
                <?php endif; ?>
            </td>
        <?php endforeach; ?>
        </tr>
        <tr class="hlbl">
        <?php foreach (array_keys($hours) as $h): ?>
            <td><?= $h % 3 === 0 ? $h . 'h' : '' ?></td>
        <?php endforeach; ?>
        </tr>
    </table>
    <?php if (($noTime = count($stats['no_time_ids'] ?? [])) > 0): ?>
        <p class="tfoot-note"><?= $noTime ?> message(s) datés sans heure ne figurent pas dans ce profil (références : Traçabilité §3).</p>
    <?php endif; ?>
<?php endif; ?>

<!-- ===================== CENTRES D'INTÉRÊT (SUJETS) ===================== -->
<bookmark content="Centres d'intérêt" level="0" />
<h2 class="section">Centres d'intérêt des visiteurs <span class="estim">estimé (IA)</span></h2>
<p class="lead">Répartition des questions par thème — ce qui intéresse le plus les prospects. Les pourcentages sont des comptages ; seul le classement de chaque message dans un thème est estimé.</p>
<table class="data">
    <tr><th>Sujet</th><th>Questions</th><th>Part</th><th style="width:42%;"></th></tr>
    <?php foreach ($topics as $row): ?>
        <tr>
            <td><?= e($row['name']) ?></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td class="num"><?= e($row['percent']) ?>%</td>
            <td><?= bar($row['percent']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="tfoot-note">La liste des messages de chaque ligne figure dans le Document de traçabilité, §4, et dans le CSV (colonne « sujet »).</p>

<!-- ===================== LACUNES D'INFORMATION ===================== -->
<bookmark content="Lacunes d'information" level="0" />
<h2 class="section">Lacunes d'information par sujet <span class="estim">calculé × estimé</span></h2>
<p class="lead">
    Croisement des sujets (estimés) avec les non-réponses (règles factuelles) :
    les thèmes où l'assistant échoue le plus à répondre = priorités à enrichir.
</p>
<table class="data">
    <tr><th>Sujet</th><th>Questions</th><th>Sans réponse</th><th>Taux</th><th style="width:26%;"></th></tr>
    <?php foreach ($gaps as $g): [$cFg, $cBg] = rate_color((float) $g['rate']); ?>
        <tr>
            <td><?= e($g['name']) ?>
                <?php if ($g['no_answer'] > 0 && count($g['no_answer_ids']) <= 5): ?>
                    <div class="src"><?= refs_inline($g['no_answer_ids'], $ref_width, 5) ?></div>
                <?php endif; ?>
            </td>
            <td class="num"><?= (int) $g['count'] ?></td>
            <td class="num"><?= (int) $g['no_answer'] ?></td>
            <td class="num"><span class="pill" style="color: <?= $cFg ?>; background: <?= $cBg ?>;"><?= e($g['rate']) ?>%</span></td>
            <td><?= bar($g['rate'], $cFg) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="tfoot-note">Les références des questions sans réponse de chaque sujet : Traçabilité §4 et §5.</p>

<!-- ===================== INTENTIONS ===================== -->
<bookmark content="Intentions" level="0" />
<h2 class="section">Intentions des visiteurs <span class="estim">estimé (IA)</span></h2>
<table class="data">
    <tr><th>Intention</th><th>Messages</th><th>Part</th><th style="width:42%;"></th></tr>
    <?php foreach ($intents as $row): ?>
        <tr>
            <td><?= e($row['name']) ?></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td class="num"><?= e($row['percent']) ?>%</td>
            <td><?= bar($row['percent']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== QUALITÉ ===================== -->
<bookmark content="Qualité des réponses" level="0" />
<h2 class="section">Qualité des réponses <span class="estim">estimé (IA)</span></h2>
<table class="data">
    <tr><th>Qualité estimée</th><th>Réponses</th><th>Part</th><th style="width:42%;"></th></tr>
    <?php foreach ($quality as $row):
        $qc = $row['key'] === 'coherente' ? '#23694a' : ($row['key'] === 'partielle' ? '#8a5e12' : '#b3322a'); ?>
        <tr>
            <td><?= e($row['name']) ?></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td class="num"><?= e($row['percent']) ?>%</td>
            <td><?= bar($row['percent'], $qc) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="small">
    Réponses potentiellement à risque (hallucination estimée) :
    <strong><?= (int) $hallucination['count'] ?></strong> (<?= e($hallucination['rate']) ?>%).
    <?php if ($hallucination['count'] > 0): ?>
        <span class="src">Sources : <?= refs_inline($hallucination['ids'], $ref_width, $refs_max) ?> · texte intégral : Traçabilité §6.</span>
    <?php endif; ?>
</p>

<!-- ===================== TOP QUESTIONS ===================== -->
<bookmark content="Questions fréquentes" level="0" />
<h2 class="section">Questions les plus fréquentes <span class="fact">calculé</span></h2>
<table class="data">
    <tr><th style="width:6%;">#</th><th>Question</th><th>Sujet (1ʳᵉ occurrence)</th><th>Occur.</th><th style="width:18%;">Exemples</th></tr>
    <?php foreach ($top_questions as $i => $row): ?>
        <tr>
            <td class="num"><?= $i + 1 ?></td>
            <td><?= e($row['question']) ?></td>
            <td><span class="pill"><?= e($row['topic']) ?></span></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td><?= refs_inline($row['ids'], $ref_width, 3) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== SANS RÉPONSE ===================== -->
<bookmark content="Questions sans réponse" level="0" />
<h2 class="section">Questions restées sans réponse <span class="fact">calculé</span></h2>
<p class="lead">À traiter en priorité : ce que les prospects demandent et que l'assistant ne sait pas couvrir. Chaque carte indique la règle qui a déclenché le constat.</p>
<?php if ($unanswered === []): ?>
    <p class="muted">Aucune non-réponse détectée selon les règles.</p>
<?php else: ?>
    <?php foreach ($unanswered as $row): ?>
        <div class="qa">
            <div class="q"><?= ref_pill((int) $row['id'], $ref_width) ?> <?= e($row['question']) ?></div>
            <div class="a">Réponse : <?= $row['reponse'] === '' ? '<em>(vide)</em>' : e($row['reponse']) ?></div>
            <div class="meta-line">Sujet : <span class="pill"><?= e($row['topic']) ?></span> ·
                Règle déclenchée : <?= e($row['regle']) ?><?= $row['date'] !== '' ? ' · ' . e($row['date']) : '' ?></div>
        </div>
    <?php endforeach; ?>
    <?php if (count($nonResp['ids']) > count($unanswered)): ?>
        <p class="tfoot-note">Liste complète des <?= (int) $nonResp['count'] ?> non-réponses : Traçabilité §5.</p>
    <?php endif; ?>
<?php endif; ?>

<!-- ===================== MEILLEURES RÉPONSES ===================== -->
<bookmark content="Exemples de réponses pertinentes" level="0" />
<h2 class="section">Exemples de réponses pertinentes <span class="estim">estimé (IA)</span></h2>
<?php if ($best_answers === []): ?>
    <p class="muted">Aucune réponse jugée « pertinente » (analyse IA désactivée ou indéterminée).</p>
<?php else: ?>
    <?php $shown = array_slice($best_answers, 0, $best_n); ?>
    <p class="lead">
        Les <?= count($shown) ?> premières (dans l'ordre du fichier) des
        <?= (int) ($best_answers_eligible ?? count($best_answers)) ?> réponses étiquetées « Réponse pertinente »
        par l'IA, sans signal d'hallucination et hors non-réponses. Ce n'est pas un classement de mérite —
        critère détaillé en Traçabilité §3, liste complète dans le CSV (colonne « qualite_estimee »).
    </p>
    <?php foreach ($shown as $row): ?>
        <div class="qa">
            <div class="q"><?= ref_pill((int) $row['id'], $ref_width) ?> <?= e($row['question']) ?></div>
            <div class="a"><?= e(mb_substr($row['reponse'], 0, 220)) ?><?= mb_strlen($row['reponse']) > 220 ? '…' : '' ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ===================== MÉTHODOLOGIE & LÉGENDE ===================== -->
<bookmark content="Méthodologie" level="0" />
<h2 class="section">Méthodologie &amp; comment vérifier</h2>
<table class="legend">
    <tr><td style="background:#e9f5ee; border:1px solid #abd8bf; color:#23694a;">
        <strong>calculé</strong> — indicateur déterministe, recalculable depuis le fichier :
        volumétrie, période, activité, taux de non-réponse (règles explicites).
    </td></tr>
    <tr><td style="background:#fdf4e5; border:1px solid #eed9ad; color:#8a5e12;">
        <strong>estimé (IA)</strong> — classement de chaque texte dans une liste FERMÉE
        (sujet, intention, qualité). Toute valeur douteuse devient « Non classé ».
        L'IA ne produit jamais un chiffre : les pourcentages sont des comptages.
    </td></tr>
    <tr><td style="background:#eaf1fa; border:1px solid #b9cce6; color:#2b4a73;">
        <strong>Vérifier un chiffre</strong> — notez la référence <span class="ref"><?= e(Text::ref(12, $ref_width)) ?></span> citée,
        ouvrez le Document de traçabilité (2/2) : §3 donne la formule, §4–§7 les détails,
        §9 le registre complet des messages ; le CSV de traçabilité contient les textes intégraux.
    </td></tr>
</table>
<?php if (!$ai_enabled): ?>
    <p class="note" style="margin-top:8px;">Analyse IA désactivée pour ce rapport : toutes les étiquettes valent « Non classé », les indicateurs factuels restent complets.</p>
<?php elseif ($ai_issues['failed_classification'] !== [] || $ai_issues['failed_quality'] !== []): ?>
    <p class="note" style="margin-top:8px;">
        <?php if ($ai_issues['failed_classification'] !== []): ?>
            <strong><?= count($ai_issues['failed_classification']) ?> message(s)</strong> sans classification (lot en échec, comptés « Non classé » dans Sujets/Intentions).
        <?php endif; ?>
        <?php if ($ai_issues['failed_quality'] !== []): ?>
            <strong><?= count($ai_issues['failed_quality']) ?> message(s)</strong> sans jugement de qualité (lot en échec, comptés « Non évalué »).
        <?php endif; ?>
        Détail : Traçabilité §7.
    </p>
<?php endif; ?>

</body>
</html>
