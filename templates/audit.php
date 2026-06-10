<?php
/**
 * Gabarit HTML du DOCUMENT DE TRAÇABILITÉ (rendu par mPDF). AFFICHAGE UNIQUEMENT.
 *
 * Justifie le rapport principal de bout en bout :
 *  §1 mode d'emploi & chaîne de preuve   §2 lecture du fichier source
 *  §3 formule de chaque chiffre + refs    §4 décomposition des agrégats
 *  §5 non-réponses (détail)               §6 hallucinations signalées
 *  §7 journal du raisonnement IA          §8 garanties & limites
 *  §9 registre COMPLET des messages (paysage)
 *
 * @var string $generated_at
 * @var string $title
 * @var string $subtitle
 * @var string $token
 * @var bool   $ai_enabled
 * @var array  $ai_params       model, endpoint_host, temperature, batch_size, max_field_chars, timeout, max_retries, retry_delay
 * @var array  $source          file, bytes, sha256, token
 * @var array  $csv_info        mapping, delimiter, skipped_lines
 * @var array  $period
 * @var int    $total_messages
 * @var int    $ref_width
 * @var bool   $anchors_enabled
 * @var int    $refs_inline_max
 * @var array  $pipeline        étapes : step, in, out, nature, verif
 * @var array  $methodology     indicator, value, formula, nature(fact|estim), ids|null, ids_note
 * @var array  $aggregates      topics/intents/quality (key,name,count,percent,ids) + gaps (…, no_answer_ids)
 * @var array  $non_responses   id, date, question, reponse, regle
 * @var array  $hallucinations  id, date, question, reponse, lot
 * @var array  $ai              enabled, summary, components, batches, corrections
 * @var array  $taxonomies      intents[], topics[], quality[]
 * @var array  $registry_groups from, to, anchor, rows
 * @var array  $codes_legend    intents/topics/quality/nr/statut => code => libellé
 * @var string $csv_name
 */

if (!function_exists('e')) {
    // ENT_SUBSTITUTE : un octet UTF-8 invalide résiduel donne U+FFFD au lieu de
    // vider TOUT le texte (la traçabilité interdit de perdre un message en silence).
    function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('cut')) {
    function cut($s, int $n): string {
        $s = trim((string) $s);
        return mb_strlen($s, 'UTF-8') > $n ? mb_substr($s, 0, $n, 'UTF-8') . '…' : $s;
    }
}
if (!function_exists('refs')) {
    // Plages de références compactées : "#001–#004, #007 … (+N autres)".
    function refs(?array $ids, int $width, int $max): string {
        return $ids === null || $ids === [] ? '—' : e(Text::refRanges($ids, $width, $max));
    }
}
if (!function_exists('reg_anchor')) {
    // Ancre du groupe de registre contenant un id (les groupes sont contigus et triés).
    function reg_anchor(int $id, array $groups): ?string {
        foreach ($groups as $g) {
            if ($id >= $g['from'] && $id <= $g['to']) { return $g['anchor']; }
        }
        return null;
    }
}
if (!function_exists('ref_link')) {
    // Pastille #NNN, cliquable vers le groupe du registre si les ancres sont actives.
    function ref_link(int $id, int $width, array $groups, bool $anchors): string {
        $label = e(Text::ref($id, $width));
        if ($anchors && ($a = reg_anchor($id, $groups)) !== null) {
            return '<a class="ref" href="#' . e($a) . '">' . $label . '</a>';
        }
        return '<span class="ref">' . $label . '</span>';
    }
}
if (!function_exists('nature_badge')) {
    function nature_badge(string $nature): string {
        return $nature === 'estim'
            ? '<span class="estim">estimé (IA)</span>'
            : '<span class="fact">calculé</span>';
    }
}

$sha = (string) ($source['sha256'] ?? '');
$delims = [',' => 'virgule (,)', ';' => 'point-virgule (;)', "\t" => 'tabulation', '|' => 'barre (|)'];
$sum = $ai['summary'] ?? [];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; color: #283544; font-size: 10pt; line-height: 1.45; }
    .muted { color: #7a8594; }
    .small { font-size: 8.5pt; }
    .mono { font-family: monospace; font-size: 8.5pt; }

    .fact  { display: inline-block; font-size: 7.5pt; color: #23694a; background: #e9f5ee;
             border: 1px solid #abd8bf; border-radius: 8px; padding: 1px 7px; vertical-align: middle; }
    .estim { display: inline-block; font-size: 7.5pt; color: #8a5e12; background: #fdf4e5;
             border: 1px solid #eed9ad; border-radius: 8px; padding: 1px 7px; vertical-align: middle; }
    .st-ok { display: inline-block; font-size: 7.5pt; font-weight: 700; color: #23694a; background: #e9f5ee;
             border: 1px solid #abd8bf; border-radius: 8px; padding: 0.5px 7px; }
    .st-ko { display: inline-block; font-size: 7.5pt; font-weight: 700; color: #b3322a; background: #fbeceb;
             border: 1px solid #e7b4ae; border-radius: 8px; padding: 0.5px 7px; }
    .ref { display: inline-block; font-size: 7pt; font-family: monospace; color: #2b4a73;
           background: #eaf1fa; border: 1px solid #b9cce6; border-radius: 8px; padding: 0.5px 5px;
           text-decoration: none; }

    .run-hd { width: 100%; font-size: 7.5pt; color: #7a8594; border-bottom: 0.4mm solid #b8924f; }
    .run-hd td { padding-bottom: 1.4mm; }
    .run-hd .brand { color: #14263d; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .run-ft { width: 100%; font-size: 7.5pt; color: #9aa3b0; border-top: 0.2mm solid #e2e8f0; }
    .run-ft td { padding-top: 1.4mm; }

    .cover-band { background: #14263d; color: #fff; padding: 40px 40px 34px; }
    .cover-kicker { font-size: 9pt; letter-spacing: 3px; color: #b8924f; text-transform: uppercase; }
    .cover-band h1 { font-size: 22pt; font-weight: 800; margin: 12px 0 0; color: #fff; }
    .cover-rule { width: 60px; height: 3px; background: #b8924f; margin: 15px 0; }
    .cover-sub { font-size: 10.5pt; color: #c5cfdb; }
    table.provcards { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 18px 32px 0; }
    table.provcards td { background: #f5f7fa; border: 1px solid #e2e8f0; border-radius: 10px; padding: 11px 13px; }
    .provcards .k { font-size: 7.5pt; color: #7a8594; text-transform: uppercase; letter-spacing: .5px; }
    .provcards .v { font-size: 10.5pt; font-weight: 700; color: #14263d; margin-top: 2px; }

    h2.section { font-size: 13pt; color: #14263d; border-left: 4px solid #b8924f; padding-left: 11px; margin: 24px 0 10px; }
    h3.sub { font-size: 10.5pt; color: #14263d; margin: 14px 0 6px; }
    .lead { color: #5b6573; margin: 0 0 10px; font-size: 9.5pt; }
    .guarantee { background: #e9f5ee; border: 1px solid #abd8bf; color: #23694a; border-radius: 10px; padding: 12px 14px; margin: 16px 0 4px; font-size: 9.5pt; }
    .howto { background: #eaf1fa; border: 1px solid #b9cce6; color: #2b4a73; border-radius: 10px; padding: 12px 14px; font-size: 9.2pt; }
    .warnbox { background: #fdf4e5; border: 1px solid #eed9ad; color: #8a5e12; border-radius: 10px; padding: 12px 14px; font-size: 9.2pt; }

    table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.data th, table.data td { border-bottom: 1px solid #e8edf2; padding: 6px 9px; text-align: left; font-size: 9pt; vertical-align: top; }
    table.data th { background: #14263d; color: #fff; border-bottom: none; font-weight: 600; font-size: 8.5pt; }
    table.data td.num { text-align: right; white-space: nowrap; }
    table.data tr:nth-child(even) td { background: #f8fafc; }

    table.toc { width: 100%; border-collapse: separate; border-spacing: 0 3px; margin-top: 6px; }
    table.toc td { padding: 6px 11px; background: #f5f7fa; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 9.5pt; }
    table.toc a { color: #14263d; text-decoration: none; font-weight: 600; }
    table.toc .d { color: #7a8594; font-size: 8.5pt; }

    .qa { border: 1px solid #e8edf2; border-radius: 9px; padding: 8px 11px; margin-bottom: 6px; page-break-inside: avoid; }
    .qa .q { font-weight: 700; color: #14263d; font-size: 9.5pt; }
    .qa .a { color: #56616f; font-size: 8.5pt; }
    .note { background: #f5f7fa; border: 1px solid #e2e8f0; border-radius: 9px; padding: 10px 13px; font-size: 8.8pt; color: #4b5563; }
    pre.prompt { background: #f5f7fa; border: 1px solid #e2e8f0; border-radius: 9px; padding: 10px 13px;
                 font-family: monospace; font-size: 7.6pt; color: #2b3744; white-space: pre-wrap; }

    /* ---------- Registre dense (paysage) ---------- */
    h3.reg-h { font-size: 10pt; color: #14263d; margin: 5mm 0 1.5mm; }
    table.reg { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
    table.reg th { background: #14263d; color: #fff; font-size: 6.5pt; font-weight: 600; padding: 1.2mm 1mm; text-align: left; }
    table.reg td { font-size: 7pt; padding: 0.9mm 1mm; border-bottom: 0.2mm solid #e8edf2; vertical-align: top; }
    table.reg tr:nth-child(even) td { background: #f8fafc; }
    table.reg td.num { text-align: right; }
    table.reg td.mono, table.reg th.mono { font-family: monospace; font-size: 6.8pt; }
</style>
</head>
<body>

<htmlpageheader name="hdr">
    <table class="run-hd"><tr>
        <td class="brand"><?= e($subtitle) ?></td>
        <td align="right">Document 2/2 — Traçabilité</td>
    </tr></table>
</htmlpageheader>
<htmlpagefooter name="ftr">
    <table class="run-ft"><tr>
        <td>Traçabilité · généré le <?= e($generated_at) ?> · dossier <?= e($token) ?></td>
        <td align="right">Page {PAGENO} / {nbpg}</td>
    </tr></table>
</htmlpagefooter>

<!-- ===================== COUVERTURE ===================== -->
<bookmark content="Couverture" level="0" />
<div class="cover-band">
    <div class="cover-kicker">Traçabilité &amp; raisonnement · Document 2/2</div>
    <h1><?= e($title) ?></h1>
    <div class="cover-rule"></div>
    <div class="cover-sub">
        <?= e($subtitle) ?><br>
        Période : <?= e($period['start'] ?? 'n/d') ?> &rarr; <?= e($period['end'] ?? 'n/d') ?> ·
        <?= (int) $total_messages ?> messages · généré le <?= e($generated_at) ?>
    </div>
</div>
<table class="provcards">
    <tr>
        <td style="width:34%;">
            <div class="k">Fichier source analysé</div>
            <div class="v" style="font-size:9.5pt;"><?= e($source['file'] ?? 'n/d') ?></div>
            <div class="small muted"><?= number_format((int) ($source['bytes'] ?? 0), 0, ',', ' ') ?> octets</div>
        </td>
        <td style="width:36%;">
            <div class="k">Empreinte SHA-256 (intégrité)</div>
            <div class="v mono" style="font-size:7.5pt; font-weight:400;"><?= e($sha !== '' ? wordwrap($sha, 32, "\n", true) : 'n/d') ?></div>
        </td>
        <td style="width:30%;">
            <div class="k">Mode d'analyse</div>
            <div class="v" style="font-size:9.5pt;"><?= $ai_enabled ? 'IA activée' : 'Factuel pur (sans IA)' ?></div>
            <?php if ($ai_enabled): ?>
                <div class="small muted"><?= e($ai_params['model']) ?> · température <?= e($ai_params['temperature']) ?></div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<div class="guarantee" style="margin:16px 32px 0;">
    <strong>Garantie anti-invention.</strong> Tous les chiffres du rapport principal sont calculés de façon
    déterministe à partir du fichier source. L'IA n'a produit aucun nombre : elle a seulement classé chaque
    texte dans des listes fermées (toute valeur douteuse devient « Non classé », voir §7). Ce document et le
    CSV de traçabilité permettent de <strong>tout recalculer et tout vérifier</strong>, message par message.
</div>

<pagebreak />
<sethtmlpageheader name="hdr" value="on" show-this-page="1" />
<sethtmlpagefooter name="ftr" value="on" />

<!-- ===================== §1 MODE D'EMPLOI ===================== -->
<a name="sec1"></a>
<bookmark content="§1 Mode d'emploi" level="0" />
<h2 class="section">§1 · Comment lire ce document (chaîne de preuve)</h2>
<div class="howto">
    <strong>La référence <span class="ref"><?= e(Text::ref(12, $ref_width)) ?></span></strong> identifie un message :
    c'est la <strong>N-ième ligne de données</strong> du fichier source (<?= e(Text::ref(1, $ref_width)) ?> = première ligne
    après l'en-tête<?= ((int) $csv_info['skipped_lines']) > 0 ? ', ' . (int) $csv_info['skipped_lines'] . ' ligne(s) vide(s) ignorée(s)' : '' ?>).
    Une plage « <?= e(Text::ref(12, $ref_width)) ?>–<?= e(Text::ref(19, $ref_width)) ?> » désigne tous les messages consécutifs entre les deux.
    <br><br>
    <strong>Chaîne de preuve</strong> — pour vérifier n'importe quel chiffre du rapport :
    <br>1. <strong>§3</strong> donne sa formule exacte et les références de ses messages sources ;
    <br>2. <strong>§4 à §6</strong> décomposent chaque tableau du rapport, ligne par ligne, avec leurs références ;
    <br>3. <strong>§7</strong> journalise le raisonnement IA : quels messages sont partis dans quel appel, ce que le modèle a répondu, ce qui a été corrigé ;
    <br>4. <strong>§9</strong> (registre) contient TOUS les messages avec leur texte<?= $anchors_enabled ? ' — les références y sont cliquables' : '' ?> ;
    <br>5. le <strong>CSV de traçabilité</strong> (<?= e($csv_name) ?>) reprend les mêmes références avec les textes intégraux : tout est recalculable.
</div>
<table class="toc">
    <tr><td><a href="#sec2">§2 · Lecture du fichier source</a> <span class="d">— séparateur, colonnes reconnues, lignes ignorées</span></td></tr>
    <tr><td><a href="#sec3">§3 · Comment chaque chiffre est calculé</a> <span class="d">— formule + messages sources de chaque indicateur</span></td></tr>
    <tr><td><a href="#sec4">§4 · Décomposition des agrégats</a> <span class="d">— sujets, intentions, qualité, lacunes : les références de chaque ligne</span></td></tr>
    <tr><td><a href="#sec5">§5 · Non-réponses (détail)</a> <span class="d">— chaque non-réponse et la règle exacte déclenchée</span></td></tr>
    <tr><td><a href="#sec6">§6 · Réponses signalées (hallucinations)</a> <span class="d">— estimations IA à vérifier humainement</span></td></tr>
    <tr><td><a href="#sec7">§7 · Journal du raisonnement IA</a> <span class="d">— prompts, lots, appels HTTP, corrections, échecs</span></td></tr>
    <tr><td><a href="#sec8">§8 · Garanties &amp; limites</a> <span class="d">— ce que ce document prouve, et ce qu'il ne prouve pas</span></td></tr>
    <tr><td><a href="#sec9">§9 · Registre complet des messages</a> <span class="d">— les <?= (int) $total_messages ?> messages, avec étiquettes et lots IA</span></td></tr>
</table>

<h3 class="sub">Chaîne de traitement (vue d'ensemble)</h3>
<table class="data">
    <tr><th style="width:26%;">Étape</th><th style="width:22%;">Entrée</th><th style="width:28%;">Sortie</th><th style="width:14%;">Nature</th><th>Vérif.</th></tr>
    <?php foreach ($pipeline as $p): ?>
        <tr>
            <td><strong><?= e($p['step']) ?></strong></td>
            <td class="small"><?= e($p['in']) ?></td>
            <td class="small"><?= e($p['out']) ?></td>
            <td class="small"><?= e($p['nature']) ?></td>
            <td class="small"><?= e($p['verif']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== §2 LECTURE DU FICHIER ===================== -->
<a name="sec2"></a>
<bookmark content="§2 Lecture du fichier source" level="0" />
<h2 class="section">§2 · Lecture du fichier source <span class="fact">calculé</span></h2>
<p class="lead">Comment le fichier a été interprété — c'est la première étape de la chaîne, tout le reste en découle.</p>
<table class="data">
    <tr><th style="width:30%;">Élément</th><th>Valeur constatée</th></tr>
    <tr><td><strong>Fichier</strong></td><td><?= e($source['file'] ?? 'n/d') ?> (<?= number_format((int) ($source['bytes'] ?? 0), 0, ',', ' ') ?> octets)</td></tr>
    <tr><td><strong>Empreinte SHA-256</strong></td><td class="mono"><?= e($sha ?: 'n/d') ?></td></tr>
    <tr><td><strong>Séparateur détecté</strong></td><td><?= e($delims[$csv_info['delimiter']] ?? ($csv_info['delimiter'] === '' ? 'n/d' : $csv_info['delimiter'])) ?></td></tr>
    <tr><td><strong>Lignes de données lues</strong></td><td><?= (int) $total_messages ?> (références <?= e(Text::ref(1, $ref_width)) ?> à <?= e(Text::ref((int) $total_messages, $ref_width)) ?>)</td></tr>
    <tr><td><strong>Lignes vides ignorées</strong></td>
        <td><?= (int) $csv_info['skipped_lines'] ?><?php if (($csv_info['skipped_line_numbers'] ?? []) !== []): ?>
            (lignes <?= e(implode(', ', array_slice($csv_info['skipped_line_numbers'], 0, 20))) ?><?= count($csv_info['skipped_line_numbers']) > 20 ? '…' : '' ?> du fichier — les références #NNN suivantes sont décalées d'autant par rapport au numéro de ligne du fichier)
        <?php endif; ?></td></tr>
</table>
<h3 class="sub">Correspondance des colonnes</h3>
<p class="lead">Les noms de colonnes du fichier sont reconnus de façon souple ; voici la correspondance retenue.</p>
<table class="data">
    <tr><th style="width:30%;">Champ attendu</th><th style="width:35%;">Colonne du fichier</th><th>Utilisation</th></tr>
    <?php
    $usages = [
        'date'     => 'Période, activité par heure/jour',
        'user_id'  => 'Visiteurs uniques, engagement',
        'question' => 'Tous les indicateurs et classifications',
        'reponse'  => 'Non-réponses (règles), qualité estimée',
        'erreur'   => 'Comptage des erreurs explicites',
    ];
    foreach (($csv_info['mapping'] ?? []) as $field => $col): ?>
        <tr>
            <td><strong><?= e($field) ?></strong></td>
            <td><?= $col === null ? '<em class="muted">non trouvée — indicateurs liés non calculables</em>' : e($col) ?></td>
            <td class="small"><?= e($usages[$field] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== §3 MÉTHODOLOGIE ===================== -->
<a name="sec3"></a>
<bookmark content="§3 Comment chaque chiffre est calculé" level="0" />
<h2 class="section">§3 · Comment chaque chiffre est calculé</h2>
<p class="lead">Chaque indicateur du rapport : sa valeur, sa formule exacte, sa nature et ses messages sources.</p>
<table class="data">
    <thead><tr><th style="width:17%;">Indicateur</th><th style="width:14%;">Valeur</th><th style="width:30%;">Méthode de calcul</th><th style="width:10%;">Nature</th><th>Messages sources</th></tr></thead>
    <?php foreach ($methodology as $m): ?>
        <tr>
            <td><strong><?= e($m['indicator']) ?></strong></td>
            <td><?= e($m['value']) ?></td>
            <td class="small"><?= e($m['formula']) ?></td>
            <td><?= nature_badge($m['nature']) ?></td>
            <td class="small mono">
                <?php if ($m['ids'] === null): ?>
                    <?= e($m['ids_note']) ?>
                <?php else: ?>
                    <?= $m['ids_note'] !== '' ? e($m['ids_note']) . ' ' : '' ?><?= refs($m['ids'], $ref_width, $refs_inline_max) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="small muted">Les listes longues sont compactées en plages ; la liste exhaustive est toujours reconstituable depuis le registre §9 ou le CSV (filtrer la colonne concernée).</p>

<!-- ===================== §4 DÉCOMPOSITION DES AGRÉGATS ===================== -->
<a name="sec4"></a>
<bookmark content="§4 Décomposition des agrégats" level="0" />
<h2 class="section">§4 · Décomposition des agrégats <span class="estim">estimé (IA)</span></h2>
<p class="lead">
    Chaque tableau du rapport principal, reproduit avec les références des messages qui composent chaque ligne.
    La somme des effectifs de chaque tableau vaut <?= (int) $total_messages ?> (tous les messages, sans exception).
</p>
<?php
$aggTitles = [
    'topics'  => 'Sujets (« Centres d\'intérêt des visiteurs »)',
    'intents' => 'Intentions des visiteurs',
    'quality' => 'Qualité des réponses',
];
foreach ($aggTitles as $aggKey => $aggTitle): ?>
    <h3 class="sub"><?= e($aggTitle) ?></h3>
    <table class="data">
        <thead><tr><th style="width:24%;">Étiquette</th><th style="width:8%;">Nb</th><th style="width:8%;">Part</th><th>Messages sources (références)</th></tr></thead>
        <?php foreach ($aggregates[$aggKey] as $row): ?>
            <tr>
                <td><?= e($row['name']) ?></td>
                <td class="num"><?= (int) $row['count'] ?></td>
                <td class="num"><?= e($row['percent']) ?>%</td>
                <td class="small mono"><?= refs($row['ids'], $ref_width, $refs_inline_max) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endforeach; ?>

<h3 class="sub">Lacunes d'information par sujet (croisement factuel × estimé)</h3>
<table class="data">
    <thead><tr><th style="width:24%;">Sujet</th><th style="width:8%;">Quest.</th><th style="width:10%;">Sans rép.</th><th style="width:8%;">Taux</th><th>Références des questions sans réponse</th></tr></thead>
    <?php foreach ($aggregates['gaps'] as $g): ?>
        <tr>
            <td><?= e($g['name']) ?></td>
            <td class="num"><?= (int) $g['count'] ?></td>
            <td class="num"><?= (int) $g['no_answer'] ?></td>
            <td class="num"><?= e($g['rate']) ?>%</td>
            <td class="small mono"><?= refs($g['no_answer_ids'], $ref_width, $refs_inline_max) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== §5 NON-RÉPONSES ===================== -->
<a name="sec5"></a>
<bookmark content="§5 Non-réponses" level="0" />
<h2 class="section">§5 · Détail des non-réponses (<?= count($non_responses) ?>) <span class="fact">calculé</span></h2>
<p class="lead">Chaque ligne a été comptée « non-réponse » par une règle automatique explicite, indiquée à droite. Aucun jugement IA ici.</p>
<?php if ($non_responses === []): ?>
    <p class="muted">Aucune non-réponse détectée selon les règles.</p>
<?php else: ?>
    <table class="data">
        <thead><tr><th style="width:8%;">Réf.</th><th style="width:13%;">Date</th><th style="width:30%;">Question</th><th style="width:25%;">Réponse</th><th>Règle déclenchée</th></tr></thead>
        <?php foreach ($non_responses as $x): ?>
            <tr>
                <td class="mono"><?= ref_link((int) $x['id'], $ref_width, $registry_groups, $anchors_enabled) ?></td>
                <td class="small"><?= e($x['date']) ?></td>
                <td class="small"><?= e(cut($x['question'], 80)) ?></td>
                <td class="small"><?= $x['reponse'] === '' ? '<em>(vide)</em>' : e(cut($x['reponse'], 70)) ?></td>
                <td class="small"><?= e($x['regle']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<!-- ===================== §6 HALLUCINATIONS ===================== -->
<a name="sec6"></a>
<bookmark content="§6 Réponses signalées" level="0" />
<h2 class="section">§6 · Réponses signalées comme potentiellement inventées (<?= count($hallucinations) ?>) <span class="estim">estimé (IA)</span></h2>
<p class="lead">
    Réponses que l'IA a estimées « hallucination » d'après le seul texte question/réponse.
    Ce sont des <strong>signalements à vérifier humainement</strong>, pas des faits établis.
</p>
<?php if ($hallucinations === []): ?>
    <p class="muted">Aucune réponse signalée comme potentiellement inventée.</p>
<?php else: ?>
    <?php foreach ($hallucinations as $x): ?>
        <div class="qa">
            <div class="q">
                <?= ref_link((int) $x['id'], $ref_width, $registry_groups, $anchors_enabled) ?>
                <?= e(cut($x['question'], 120)) ?>
                <span class="small muted">— <?= e($x['date']) ?><?= $x['lot'] !== null ? ' · lot qualité n°' . (int) $x['lot'] : '' ?></span>
            </div>
            <div class="a"><?= e(cut($x['reponse'], 320)) ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ===================== §7 JOURNAL IA ===================== -->
<a name="sec7"></a>
<bookmark content="§7 Journal du raisonnement IA" level="0" />
<h2 class="section">§7 · Journal du raisonnement IA</h2>

<?php if (!$ai_enabled): ?>
    <div class="warnbox">
        <strong>Analyse IA désactivée pour cette génération.</strong> Aucune requête n'a été émise vers un
        modèle : toutes les étiquettes (sujet, intention, qualité) valent « Non classé » par construction
        (statut <span class="mono">ia_desactivee</span> dans le CSV). Les indicateurs factuels (§3, §5) ne
        sont pas concernés : ils sont calculés sans IA.
    </div>
<?php else: ?>
    <p class="lead">
        Ce que l'IA a reçu, ce qu'elle a répondu, et ce qui en a été retenu — appel par appel.
        L'IA ne « cherche » nulle part ailleurs : elle reçoit UNIQUEMENT le texte des messages listés ci-dessous,
        et doit choisir dans des listes fermées.
    </p>

    <h3 class="sub">§7.1 · Paramètres d'exécution</h3>
    <table class="data">
        <tr>
            <th>Modèle</th><th>Hôte</th><th>Température</th><th>Taille de lot</th>
            <th>Troncature d'envoi</th><th>Appels HTTP</th><th>Relances</th><th>Durée réseau totale</th>
        </tr>
        <tr>
            <td><?= e($ai_params['model']) ?></td>
            <td><?= e($ai_params['endpoint_host']) ?></td>
            <td class="num"><?= e($ai_params['temperature']) ?> (déterminisme max.)</td>
            <td class="num"><?= (int) $ai_params['batch_size'] ?> messages</td>
            <td class="num"><?= (int) $ai_params['max_field_chars'] ?> car./champ</td>
            <td class="num"><?= (int) ($sum['http_calls'] ?? 0) ?></td>
            <td class="num"><?= (int) ($sum['retries'] ?? 0) ?></td>
            <td class="num"><?= number_format((int) ($sum['duration_ms'] ?? 0), 0, ',', ' ') ?> ms</td>
        </tr>
    </table>
    <p class="small muted">
        Les durées sont les temps des appels HTTP (hors attentes entre relances, <?= (int) $ai_params['retry_delay'] ?> s × n° de tentative).
        La troncature d'envoi ne concerne que le texte transmis à l'IA : les statistiques factuelles utilisent toujours le texte complet.
    </p>

    <h3 class="sub">§7.2 · Consignes exactes données à l'IA (prompts)</h3>
    <p class="lead">Reproduites in extenso. <span class="mono">{ITEMS}</span> est remplacé par les messages du lot (références en §7.3) ; les listes fermées autorisées sont rappelées sous chaque consigne.</p>
    <?php
    $componentTitles = ['classification' => 'Phase 1 — Classification (intention + sujet)', 'qualite' => 'Phase 2 — Jugement de qualité'];
    foreach (($ai['components'] ?? []) as $phase => $comp): ?>
        <h3 class="sub" style="font-size:9.5pt;"><?= e($componentTitles[$phase] ?? $phase) ?> <span class="small muted">(gabarit : <?= e($comp['prompt_file']) ?>)</span></h3>
        <pre class="prompt"><strong>Consigne système :</strong>
<?= e($comp['system']) ?>

<strong>Gabarit du message :</strong>
<?= e($comp['template']) ?></pre>
    <?php endforeach; ?>
    <table class="data">
        <tr><th style="width:22%;">Liste fermée</th><th>Valeurs autorisées (toute autre valeur est rejetée → « Non classé »)</th></tr>
        <tr><td><strong>Intentions</strong></td><td class="mono small"><?= e(implode(' · ', $taxonomies['intents'])) ?></td></tr>
        <tr><td><strong>Sujets</strong></td><td class="mono small"><?= e(implode(' · ', $taxonomies['topics'])) ?></td></tr>
        <tr><td><strong>Qualité</strong></td><td class="mono small"><?= e(implode(' · ', $taxonomies['quality'])) ?></td></tr>
    </table>

    <h3 class="sub">§7.3 · Les appels, lot par lot</h3>
    <p class="lead">
        Synthèse : <?= (int) ($sum['batches'] ?? 0) ?> lots ·
        <?= (int) ($sum['batches_ok'] ?? 0) ?> réussi(s) ·
        <?= (int) ($sum['batches_ko'] ?? 0) ?> en échec ·
        <?= (int) ($sum['corrected'] ?? 0) ?> étiquette(s) corrigée(s) ·
        <?= (int) ($sum['missing'] ?? 0) ?> message(s) sans réponse IA ·
        <?= (int) ($sum['unknown'] ?? 0) ?> id(s) inconnu(s) rejeté(s).
        <br>Statut <span class="st-ok">OK</span> sans correction = chaque étiquette retenue est
        <strong>mot pour mot</strong> celle renvoyée par le modèle pour ce message ;
        toute divergence est listée en §7.4. Pour un lot réussi mais à corrections,
        manquants ou ids inconnus, un extrait de la réponse brute du modèle est reproduit
        sous la ligne du lot ; pour un lot en échec, le message d'erreur est affiché.
    </p>
    <?php
    $phaseTitles = ['classification' => 'Classification', 'qualite' => 'Qualité'];
    foreach ($phaseTitles as $phaseKey => $phaseTitle):
        $phaseBatches = array_values(array_filter($ai['batches'], static fn($b) => $b['phase'] === $phaseKey));
        if ($phaseBatches === []) { continue; } ?>
        <h3 class="sub" style="font-size:9.5pt;"><?= e($phaseTitle) ?> — <?= count($phaseBatches) ?> lot(s)</h3>
        <table class="data">
            <thead><tr>
                <th style="width:6%;">Lot</th><th style="width:26%;">Messages envoyés</th>
                <th style="width:8%;">Envoyés</th><th style="width:8%;">Exploités</th>
                <th style="width:8%;">Corrigés</th><th style="width:9%;">Manquants</th>
                <th style="width:13%;">Tentatives HTTP</th><th style="width:9%;">Durée</th><th>Statut</th>
            </tr></thead>
            <?php foreach ($phaseBatches as $b): ?>
                <tr>
                    <td class="num"><?= (int) $b['index'] ?></td>
                    <td class="small mono"><?= refs($b['ids'], $ref_width, 6) ?></td>
                    <td class="num"><?= count($b['ids']) ?></td>
                    <td class="num"><?= (int) $b['applied'] ?></td>
                    <td class="num"><?= (int) $b['corrected'] ?></td>
                    <td class="num"><?= count($b['missing_ids']) ?></td>
                    <td class="small">
                        <?php
                        $att = [];
                        foreach ($b['attempts'] as $a) { $att[] = ($a['http'] > 0 ? $a['http'] : 'réseau') ; }
                        echo e($att === [] ? '—' : implode(' → ', $att));
                        ?>
                    </td>
                    <td class="num"><?= number_format((int) $b['duration_ms'], 0, ',', ' ') ?> ms</td>
                    <td><?= $b['status'] === 'ok' ? '<span class="st-ok">OK</span>' : '<span class="st-ko">ÉCHEC</span>' ?></td>
                </tr>
                <?php if ($b['status'] !== 'ok'): ?>
                    <tr><td></td><td colspan="8" class="small" style="color:#b3322a;">
                        Erreur : <?= e($b['error']) ?> — les <?= count($b['ids']) ?> messages de ce lot
                        (<?= refs($b['ids'], $ref_width, 6) ?>) sont restés « Non classé ». Aucune invention.
                    </td></tr>
                <?php elseif ($b['missing_ids'] !== [] || $b['unknown_ids'] !== [] || $b['corrected'] > 0): ?>
                    <tr><td></td><td colspan="8" class="small" style="color:#8a5e12;">
                        <?php if ($b['missing_ids'] !== []): ?>
                            Absents de la réponse IA (restés « Non classé ») : <?= refs($b['missing_ids'], $ref_width, 10) ?>.
                        <?php endif; ?>
                        <?php if ($b['unknown_ids'] !== []): ?>
                            Ids renvoyés par l'IA mais inconnus du lot (rejetés) : <?= e(implode(', ', $b['unknown_ids'])) ?>.
                        <?php endif; ?>
                        <?php if ($b['corrected'] > 0): ?>
                            <?= (int) $b['corrected'] ?> étiquette(s) corrigée(s) dans ce lot (détail §7.4).
                        <?php endif; ?>
                        <?php if (($b['response_excerpt'] ?? '') !== ''): ?>
                            <br>Réponse brute du modèle (extrait) : <span class="mono"><?= e($b['response_excerpt']) ?></span>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <h3 class="sub">§7.4 · Corrections : valeur brute de l'IA → valeur retenue</h3>
    <?php if (($ai['corrections'] ?? []) === []): ?>
        <?php if ((int) ($sum['batches_ko'] ?? 0) === 0): ?>
            <p class="muted">Aucune correction : toutes les étiquettes renvoyées par l'IA appartenaient aux listes fermées et chaque message envoyé a reçu une réponse.</p>
        <?php else: ?>
            <p class="muted">Aucune étiquette hors liste parmi les lots réussis ; les messages des lots en échec (restés « Non classé ») sont listés en §7.3.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="lead">Chaque divergence entre ce que le modèle a répondu et ce qui figure dans le rapport. C'est la barrière anti-hallucination en action.</p>
        <table class="data">
            <thead><tr><th style="width:8%;">Réf.</th><th style="width:13%;">Phase</th><th style="width:6%;">Lot</th><th style="width:33%;">Valeur brute renvoyée par l'IA</th><th style="width:24%;">Valeur retenue</th><th>Motif</th></tr></thead>
            <?php foreach ($ai['corrections'] as $c): ?>
                <tr>
                    <td class="mono"><?= ref_link((int) $c['id'], $ref_width, $registry_groups, $anchors_enabled) ?></td>
                    <td class="small"><?= e($c['phase']) ?></td>
                    <td class="num"><?= $c['lot'] !== null ? (int) $c['lot'] : '—' ?></td>
                    <td class="small mono"><?= $c['brut'] !== '' ? e($c['brut']) : '<em>(absent de la réponse)</em>' ?></td>
                    <td class="small"><?= e($c['retenu']) ?></td>
                    <td class="small"><?= $c['motif'] === 'corrige' ? 'Hors liste fermée' : 'Absent de la réponse IA' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<!-- ===================== §8 GARANTIES & LIMITES ===================== -->
<a name="sec8"></a>
<bookmark content="§8 Garanties et limites" level="0" />
<h2 class="section">§8 · Garanties &amp; limites</h2>
<div class="note">
    <strong>Ce que ce document prouve.</strong> Chaque chiffre du rapport est un comptage déterministe dont
    les messages sources sont listés (§3–§5) ; chaque étiquette IA est rattachée à son message, à son lot
    d'appel et, en cas de correction, à la valeur brute renvoyée par le modèle (§7) ; le registre §9 et le
    CSV permettent de reconstituer l'intégralité des calculs à partir du fichier source, scellé par son
    empreinte SHA-256 (§2).
    <br><br>
    <strong>Ce que ce document ne prouve pas.</strong> Les étiquettes IA (sujet, intention, qualité,
    hallucination) restent des <strong>estimations</strong> : contraintes à des listes fermées et à
    température 0, mais pas infaillibles. Les signalements « hallucination » (§6) doivent être contrôlés
    humainement. Les textes affichés en §5, §6 et §9 sont tronqués pour l'impression (marqueur « … »,
    liens remplacés par [lien]) : le texte INTÉGRAL fait foi dans le CSV de traçabilité.
    <br><br>
    <strong>CSV de traçabilité (<?= e($csv_name) ?>).</strong> 1 ligne = 1 message. Colonnes :
    <span class="mono">ref</span> (référence commune aux PDF), <span class="mono">id</span>,
    <span class="mono">date</span>, <span class="mono">user_id</span>, <span class="mono">question</span> et
    <span class="mono">reponse</span> (textes intégraux), <span class="mono">longueur_reponse</span>,
    <span class="mono">erreur_source</span>, <span class="mono">non_reponse</span> et
    <span class="mono">regle_non_reponse</span> (règle exacte), étiquettes retenues
    (<span class="mono">intention</span>, <span class="mono">sujet</span>, <span class="mono">qualite_estimee</span>,
    <span class="mono">hallucination_estimee</span>), puis pour chaque phase IA : n° de lot
    (<span class="mono">lot_*</span>), statut (<span class="mono">statut_ia_*</span> :
    ok / corrige / absent_reponse / lot_echec / ia_desactivee) et valeur brute si corrigée
    (<span class="mono">brut_ia_*</span>).
    Protection tableur : toute cellule de texte libre commençant par <span class="mono">= + - @</span>
    est préfixée d'une apostrophe pour empêcher son exécution comme formule dans Excel/LibreOffice
    (l'apostrophe ne fait pas partie du texte d'origine).
</div>

<!-- ===================== §9 REGISTRE COMPLET (PAYSAGE) ===================== -->
<pagebreak orientation="landscape" />
<a name="sec9"></a>
<bookmark content="§9 Registre complet des messages" level="0" />
<h2 class="section">§9 · Registre complet des <?= (int) $total_messages ?> messages</h2>
<p class="lead">
    Chaque message du fichier source, dans l'ordre, avec ses étiquettes et ses lots IA.
    Textes tronqués pour l'impression (intégraux dans le CSV, mêmes références).
</p>
<table class="data" style="margin-bottom:5mm;">
    <tr>
        <th style="width:33%;">Intentions (Int.)</th>
        <th style="width:42%;">Sujets (Suj.)</th>
        <th>Qualité (Qual.)</th>
    </tr>
    <tr>
        <td class="small"><?php $p = []; foreach ($codes_legend['intents'] as $code => $lbl) { $p[] = '<strong>' . e($code) . '</strong> ' . e($lbl); } echo implode(' · ', $p); ?></td>
        <td class="small"><?php $p = []; foreach ($codes_legend['topics'] as $code => $lbl) { $p[] = '<strong>' . e($code) . '</strong> ' . e($lbl); } echo implode(' · ', $p); ?></td>
        <td class="small"><?php $p = []; foreach ($codes_legend['quality'] as $code => $lbl) { $p[] = '<strong>' . e($code) . '</strong> ' . e($lbl); } echo implode(' · ', $p); ?></td>
    </tr>
    <tr>
        <th style="width:33%;">NR — règle de non-réponse</th>
        <th style="width:42%;">IA — statut combiné (le plus défavorable des deux phases ; détail par phase dans le CSV)</th>
        <th>H — hallucination estimée</th>
    </tr>
    <tr>
        <td class="small"><?php $p = []; foreach ($codes_legend['nr'] as $code => $lbl) { $p[] = '<strong>' . e($code) . '</strong> ' . e($lbl); } echo implode(' · ', $p); ?></td>
        <td class="small"><?php $p = []; foreach ($codes_legend['statut'] as $code => $lbl) { $p[] = '<strong>' . e($code) . '</strong> ' . e($lbl); } echo implode(' · ', $p); ?></td>
        <td class="small"><strong>!</strong> signalée (à vérifier, §6) · <strong>—</strong> non signalée</td>
    </tr>
</table>

<?php foreach ($registry_groups as $g): ?>
    <?php if ($anchors_enabled): ?><a name="<?= e($g['anchor']) ?>"></a><?php endif; ?>
    <bookmark content="Registre <?= e(Text::ref($g['from'], $ref_width)) ?>–<?= e(Text::ref($g['to'], $ref_width)) ?>" level="1" />
    <h3 class="reg-h">Messages <?= e(Text::ref($g['from'], $ref_width)) ?> à <?= e(Text::ref($g['to'], $ref_width)) ?></h3>
    <table class="reg">
        <thead>
        <tr>
            <th class="mono" width="5%">Réf.</th><th width="7%">Date</th><th width="7%">Visiteur</th>
            <th width="28%">Question (extrait)</th><th width="29%">Réponse (extrait)</th>
            <th width="4%">Long.</th><th width="3%">NR</th><th width="3%">Int.</th><th width="3%">Suj.</th>
            <th width="3%">Qual.</th><th width="2%">H</th><th width="2%">Lot C</th><th width="2%">Lot Q</th><th width="2%">IA</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($g['rows'] as $r): ?>
            <tr>
                <td class="mono"><?= e($r['ref']) ?></td>
                <td><?= e($r['date']) ?></td>
                <td class="mono"><?= e($r['user']) ?></td>
                <td><?= $r['question'] === '' ? '<em class="muted">(vide)</em>' : e($r['question']) ?></td>
                <td><?= $r['reponse'] === '' ? '<em class="muted">(vide)</em>' : e($r['reponse']) ?></td>
                <td class="num"><?= (int) $r['rep_len'] ?></td>
                <td><?= e($r['nr']) ?></td>
                <td><?= e($r['intent']) ?></td>
                <td><?= e($r['topic']) ?></td>
                <td><?= e($r['quality']) ?></td>
                <td><?= $r['halluc'] ? '<strong style="color:#b3322a;">!</strong>' : '—' ?></td>
                <td class="num"><?= $r['lot_c'] !== null ? (int) $r['lot_c'] : '—' ?></td>
                <td class="num"><?= $r['lot_q'] !== null ? (int) $r['lot_q'] : '—' ?></td>
                <td><?= e($r['statut']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<p class="small muted">
    Fin du registre — <?= (int) $total_messages ?> messages. Pour le texte intégral d'un message,
    ouvrir <?= e($csv_name) ?> et filtrer la colonne <span class="mono">ref</span>.
</p>

</body>
</html>
