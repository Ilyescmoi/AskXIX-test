<?php
use App\Libraries\Text;
/**
 * Gabarit du DOCUMENT DE TRAÇABILITÉ (Document 2/2) — rendu par mPDF.
 * Style « clair & moderne ». AFFICHAGE UNIQUEMENT.
 *
 * Ouverture : « La preuve en un coup d'œil » (source scellée, statut IA, chiffres
 * clés → où vérifier). Puis les sections détaillées, allégées :
 *  §1 mode d'emploi & chaîne de preuve   §2 lecture du fichier source
 *  §3 formule de chaque chiffre + refs    §4 décomposition des agrégats
 *  §5 non-réponses (détail)               §6 hallucinations signalées
 *  §7 journal du raisonnement IA          §8 garanties & limites
 *  §9 registre COMPLET des messages (paysage)   Annexe A : prompts in extenso
 *
 * @var string $generated_at, $title, $subtitle, $token, $csv_name
 * @var bool   $ai_enabled, $anchors_enabled
 * @var array  $ai_params, $source, $csv_info, $period, $dashboard
 * @var int    $total_messages, $ref_width, $refs_inline_max
 * @var array  $pipeline, $methodology, $aggregates, $non_responses, $hallucinations
 * @var array  $ai, $taxonomies, $registry_groups, $codes_legend
 */

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('cut')) {
    function cut($s, int $n): string {
        $s = trim((string) $s);
        return mb_strlen($s, 'UTF-8') > $n ? mb_substr($s, 0, $n, 'UTF-8') . '…' : $s;
    }
}
if (!function_exists('refs')) {
    function refs(?array $ids, int $width, int $max): string {
        return $ids === null || $ids === [] ? '—' : e(Text::refRanges($ids, $width, $max));
    }
}
if (!function_exists('reg_anchor')) {
    function reg_anchor(int $id, array $groups): ?string {
        foreach ($groups as $g) {
            if ($id >= $g['from'] && $id <= $g['to']) { return $g['anchor']; }
        }
        return null;
    }
}
if (!function_exists('ref_link')) {
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
            ? '<span class="badge b-estim">estimé (IA)</span>'
            : '<span class="badge b-fact">calculé</span>';
    }
}
if (!function_exists('sec_head')) {
    // Titre de section homogène : kicker + n° + titre + filet d'accent.
    function sec_head(string $anchor, string $num, string $title, string $color, string $kick, string $badge = ''): string {
        return '<a name="' . e($anchor) . '"></a>'
            . '<div class="sec"><div class="kick">' . e($kick) . '</div>'
            . '<h2>' . e($num) . ' · ' . e($title) . ' ' . $badge . '</h2>'
            . '<div class="dash" style="background:' . $color . ';"></div></div>';
    }
}

$delims = [',' => 'virgule (,)', ';' => 'point-virgule (;)', "\t" => 'tabulation', '|' => 'barre (|)'];
$db = $dashboard;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
<?php include __DIR__ . '/partials/theme.php'; ?>

/* ---------- Spécifique audit ---------- */
.cover .kick { font-size: 8.5pt; letter-spacing: 2.6px; text-transform: uppercase; color: #9aa3b2; font-weight: 700; }
.cover h1 { font-size: 23pt; font-weight: 800; margin: 8px 0 0; color: #161e2e; }
table.accent { border-collapse: separate; border-spacing: 3px 0; margin: 12px 0 2px; }
table.accent td { height: 4px; width: 28px; border-radius: 2px; }

table.dash3 { width: 100%; border-collapse: separate; border-spacing: 8px; margin-top: 4px; }
table.dash3 td { background: #f7f9fc; border: 0.2mm solid #eef1f5; border-radius: 12px; padding: 12px 13px; vertical-align: top; }
.dash3 .k { font-size: 7.4pt; text-transform: uppercase; letter-spacing: .5px; color: #9aa3b2; font-weight: 700; }
.dash3 .v { font-size: 11pt; font-weight: 700; color: #161e2e; margin-top: 3px; }
.dash3 .d { font-size: 8pt; color: #7b8494; margin-top: 2px; }
.ok-tick { color: #0f9d58; font-weight: 700; }

table.toc { width: 100%; border-collapse: collapse; }
table.toc td { padding: 7px 2px; border-bottom: 0.2mm solid #f0f3f7; font-size: 9.4pt; }
table.toc a { color: #161e2e; text-decoration: none; font-weight: 600; }
table.toc .n { color: #3457e0; font-weight: 700; width: 8%; }
table.toc .d { color: #9aa3b2; font-size: 8.3pt; }

pre.prompt { background: #f7f9fc; border: 0.2mm solid #eef1f5; border-radius: 10px; padding: 11px 13px;
             font-family: monospace; font-size: 7.6pt; color: #2b3546; white-space: pre-wrap; }

/* Registre dense (paysage) */
h3.reg-h { font-size: 10pt; color: #161e2e; margin: 5mm 0 1.5mm; }
table.reg { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
table.reg th { background: #f1f4f9; color: #5b6573; font-size: 6.5pt; font-weight: 700; padding: 1.4mm 1mm;
               text-align: left; text-transform: uppercase; letter-spacing: .3px; border-bottom: 0.3mm solid #d8dee8; }
table.reg td { font-size: 7pt; padding: 1mm; border-bottom: 0.2mm solid #f0f3f7; vertical-align: top; }
table.reg td.mono, table.reg th.mono { font-family: monospace; font-size: 6.8pt; }
.st-ok { display: inline-block; font-size: 7.5pt; font-weight: 700; color: #047857; background: #e7f7ef; border-radius: 7px; padding: 0.5px 7px; }
.st-ko { display: inline-block; font-size: 7.5pt; font-weight: 700; color: #c81e1e; background: #fdeceb; border-radius: 7px; padding: 0.5px 7px; }
</style>
</head>
<body>

<htmlpageheader name="hdr">
    <table class="run-hd"><tr>
        <td class="brand"><?= e($subtitle) ?></td>
        <td align="right" class="doc">Document 2/2 · Traçabilité</td>
    </tr></table>
</htmlpageheader>
<htmlpagefooter name="ftr">
    <table class="run-ft"><tr>
        <td>Traçabilité · généré le <?= e($generated_at) ?> · dossier <?= e($token) ?></td>
        <td align="right">Page {PAGENO} / {nbpg}</td>
    </tr></table>
</htmlpagefooter>

<!-- ===================== PAGE 1 : LA PREUVE EN UN COUP D'ŒIL ===================== -->
<bookmark content="La preuve en un coup d'œil" level="0" />
<div class="cover">
    <div class="kick">Traçabilité &amp; raisonnement · Document 2/2</div>
    <h1><?= e($title) ?></h1>
    <table class="accent"><tr>
        <td style="background:#10b981;"></td><td style="background:#3b82f6;"></td>
        <td style="background:#8b5cf6;"></td><td style="background:#f59e0b;"></td>
    </tr></table>
</div>

<table class="dash3">
    <tr>
        <td style="width:34%;">
            <div class="k">Fichier source</div>
            <div class="v" style="font-size:9.5pt;"><?= e($db['source']['file']) ?></div>
            <div class="d"><?= $db['source']['sha_ok'] ? '<span class="ok-tick">✓</span> SHA-256 ' . e($db['source']['sha']) . '…' : 'empreinte n/d' ?> · <?= number_format($db['source']['bytes'], 0, ',', ' ') ?> o</div>
        </td>
        <td style="width:33%;">
            <div class="k">Volumétrie</div>
            <div class="v"><?= (int) $db['source']['messages'] ?> messages</div>
            <div class="d"><?= e($db['source']['period']) ?></div>
        </td>
        <td style="width:33%;">
            <div class="k">Analyse IA</div>
            <?php if ($db['ai']['enabled']): ?>
                <div class="v"><?= $db['ai']['batches'] ?> lots · <span class="<?= $db['ai']['ko'] > 0 ? 'st-ko' : 'ok-tick' ?>"><?= $db['ai']['ko'] > 0 ? $db['ai']['ko'] . ' échec' : '0 échec' ?></span></div>
                <div class="d"><?= e($db['ai']['model']) ?> · <?= $db['ai']['corrected'] ?> corr. · <?= $db['ai']['missing'] ?> sans rép.</div>
            <?php else: ?>
                <div class="v">Désactivée</div>
                <div class="d">factuel pur · tout « Non classé »</div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<div class="callout-ok" style="margin-top:13px;">
    <span class="strong" style="color:#146c4a;">Garantie anti-invention.</span>
    Tous les chiffres du rapport sont des comptages déterministes. L'IA n'a produit aucun nombre : elle a seulement
    classé chaque texte dans des listes fermées (toute valeur douteuse = « Non classé », §7). Ce document et le CSV
    permettent de tout recalculer, message par message.
</div>

<div class="sec" style="margin-top:18px;">
    <div class="kick">Vérifiable en un coup d'œil</div>
    <h2>Chaque chiffre clé, et où le vérifier</h2>
    <div class="dash" style="background:#3b82f6;"></div>
</div>
<table class="data">
    <tr><th style="width:30%;">Chiffre du rapport</th><th style="width:22%;">Valeur</th><th style="width:13%;">Nature</th><th style="width:13%;">Vérifier</th><th>Messages sources</th></tr>
    <?php foreach ($db['claims'] as $c): ?>
        <tr>
            <td class="name"><?= e($c['label']) ?></td>
            <td class="strong"><?= e($c['value']) ?></td>
            <td><?= nature_badge($c['nature']) ?></td>
            <td><span class="pill"><?= e($c['verif']) ?></span></td>
            <td class="small"><span class="ok-tick">✓</span> <?= e($c['sources']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="foot-note">
    Chaîne de preuve : <span class="strong">Rapport</span> → <span class="strong">§3/§4</span> (formule + références)
    → <span class="strong">§9 registre</span> (texte de chaque message) → <span class="strong">CSV</span> (texte intégral)
    → <span class="strong">fichier source</span> (scellé SHA-256). Les sections détaillées suivent.
</p>

<pagebreak />
<sethtmlpageheader name="hdr" value="on" show-this-page="1" />
<sethtmlpagefooter name="ftr" value="on" />

<!-- ===================== §1 MODE D'EMPLOI ===================== -->
<?= sec_head('sec1', '§1', 'Comment lire ce document', '#3b82f6', 'Mode d\'emploi') ?>
<div class="callout-info">
    <span class="strong" style="color:#2440b8;">La référence <span class="ref"><?= e(Text::ref(12, $ref_width)) ?></span></span>
    identifie un message : la N-ième ligne de données du fichier (<?= e(Text::ref(1, $ref_width)) ?> = 1ʳᵉ ligne après l'en-tête<?= ((int) $csv_info['skipped_lines']) > 0 ? ', ' . (int) $csv_info['skipped_lines'] . ' ligne(s) vide(s) ignorée(s)' : '' ?>).
    Une plage « <?= e(Text::ref(12, $ref_width)) ?>–<?= e(Text::ref(19, $ref_width)) ?> » couvre tous les messages consécutifs entre les deux.
    <br>Pour vérifier un chiffre : <span class="strong" style="color:#2440b8;">§3</span> donne sa formule et ses sources →
    <span class="strong" style="color:#2440b8;">§4–§6</span> décomposent les tableaux →
    <span class="strong" style="color:#2440b8;">§7</span> journalise l'IA →
    <span class="strong" style="color:#2440b8;">§9</span> contient tous les messages<?= $anchors_enabled ? ' (références cliquables)' : '' ?> →
    le <span class="strong" style="color:#2440b8;">CSV</span> donne les textes intégraux.
</div>

<h3 class="sec" style="margin-bottom:6px;"><span style="font-size:10.5pt; color:#161e2e;">Sommaire</span></h3>
<table class="toc">
    <tr><td class="n">§2</td><td><a href="#sec2">Lecture du fichier source</a> <span class="d">— séparateur, colonnes, lignes ignorées</span></td></tr>
    <tr><td class="n">§3</td><td><a href="#sec3">Comment chaque chiffre est calculé</a> <span class="d">— formule + sources de chaque indicateur</span></td></tr>
    <tr><td class="n">§4</td><td><a href="#sec4">Décomposition des agrégats</a> <span class="d">— sujets, intentions, qualité, lacunes</span></td></tr>
    <tr><td class="n">§5</td><td><a href="#sec5">Non-réponses (détail)</a> <span class="d">— la règle exacte déclenchée</span></td></tr>
    <tr><td class="n">§6</td><td><a href="#sec6">Réponses signalées (hallucinations)</a> <span class="d">— estimations IA à vérifier</span></td></tr>
    <tr><td class="n">§7</td><td><a href="#sec7">Journal du raisonnement IA</a> <span class="d">— lots, appels HTTP, corrections, échecs</span></td></tr>
    <tr><td class="n">§8</td><td><a href="#sec8">Garanties &amp; limites</a></td></tr>
    <tr><td class="n">§9</td><td><a href="#sec9">Registre complet des messages</a> <span class="d">— les <?= (int) $total_messages ?> messages</span></td></tr>
    <?php if ($ai_enabled): ?><tr><td class="n">A</td><td><a href="#secA">Annexe — consignes exactes données à l'IA</a> <span class="d">— prompts in extenso</span></td></tr><?php endif; ?>
</table>

<h3 class="sec" style="margin:16px 0 6px;"><span style="font-size:10.5pt; color:#161e2e;">Chaîne de traitement</span></h3>
<table class="data">
    <tr><th style="width:27%;">Étape</th><th style="width:22%;">Entrée</th><th style="width:28%;">Sortie</th><th style="width:13%;">Nature</th><th>Vérif.</th></tr>
    <?php foreach ($pipeline as $p): ?>
        <tr><td class="name"><?= e($p['step']) ?></td><td class="small"><?= e($p['in']) ?></td><td class="small"><?= e($p['out']) ?></td><td class="small"><?= e($p['nature']) ?></td><td class="small"><?= e($p['verif']) ?></td></tr>
    <?php endforeach; ?>
</table>

<!-- ===================== §2 LECTURE DU FICHIER ===================== -->
<?= sec_head('sec2', '§2', 'Lecture du fichier source', '#10b981', 'Provenance', '<span class="badge b-fact">calculé</span>') ?>
<p class="lead">Comment le fichier a été interprété — première étape de la chaîne, tout en découle.</p>
<table class="data">
    <tr><th style="width:30%;">Élément</th><th>Valeur constatée</th></tr>
    <tr><td class="name">Fichier</td><td><?= e($source['file'] ?? 'n/d') ?> · <?= number_format((int) ($source['bytes'] ?? 0), 0, ',', ' ') ?> octets</td></tr>
    <tr><td class="name">Empreinte SHA-256</td><td class="mono small"><?= e(($source['sha256'] ?? '') ?: 'n/d') ?></td></tr>
    <tr><td class="name">Séparateur détecté</td><td><?= e($delims[$csv_info['delimiter']] ?? ($csv_info['delimiter'] === '' ? 'n/d' : $csv_info['delimiter'])) ?></td></tr>
    <tr><td class="name">Lignes de données</td><td><?= (int) $total_messages ?> (références <?= e(Text::ref(1, $ref_width)) ?> à <?= e(Text::ref((int) $total_messages, $ref_width)) ?>)</td></tr>
    <tr><td class="name">Lignes vides ignorées</td><td><?= (int) $csv_info['skipped_lines'] ?><?php if (($csv_info['skipped_line_numbers'] ?? []) !== []): ?> <span class="small soft">(lignes <?= e(implode(', ', array_slice($csv_info['skipped_line_numbers'], 0, 20))) ?><?= count($csv_info['skipped_line_numbers']) > 20 ? '…' : '' ?> du fichier — les références suivantes sont décalées d'autant)</span><?php endif; ?></td></tr>
</table>
<h3 class="sec" style="margin:14px 0 6px;"><span style="font-size:10pt; color:#161e2e;">Correspondance des colonnes</span></h3>
<table class="data">
    <tr><th style="width:26%;">Champ attendu</th><th style="width:32%;">Colonne du fichier</th><th>Utilisation</th></tr>
    <?php
    $usages = [
        'date' => 'Période, activité par heure/jour', 'user_id' => 'Visiteurs uniques, engagement',
        'question' => 'Indicateurs et classifications', 'reponse' => 'Non-réponses, qualité estimée',
        'erreur' => 'Comptage des erreurs explicites',
    ];
    foreach (($csv_info['mapping'] ?? []) as $field => $col): ?>
        <tr><td class="name"><?= e($field) ?></td><td><?= $col === null ? '<em class="muted">non trouvée — indicateurs liés non calculables</em>' : e($col) ?></td><td class="small"><?= e($usages[$field] ?? '') ?></td></tr>
    <?php endforeach; ?>
</table>

<?php if (!empty($base_enabled)): ?>
<!-- ===================== §2bis PROVENANCE DE LA BASE ===================== -->
<?= sec_head('sec2bis', '§2bis', 'Provenance de la base de connaissance', '#0f9d58', 'Base comparée, scellée', '<span class="badge b-fact">calculé</span>') ?>
<p class="lead">La base à laquelle les réponses ont été comparées, scellée par une empreinte SHA-256 (preuve de la version exacte utilisée).</p>
<table class="data">
    <tr><th style="width:30%;">Élément</th><th>Valeur constatée</th></tr>
    <tr><td class="name">Documents texte</td><td><?= (int) $base['docs'] ?></td></tr>
    <tr><td class="name">Images indexées</td><td><?= (int) $base['images'] ?></td></tr>
    <tr><td class="name">Taille totale</td><td><?= number_format((int) $base['bytes'], 0, ',', ' ') ?> octets<?= $base['truncated'] ? ' · contexte tronqué pour l\'IA (' . number_format((int) $base['context_full_chars'], 0, ',', ' ') . ' caractères)' : '' ?></td></tr>
    <tr><td class="name">Empreinte SHA-256 (scellé)</td><td class="mono small"><?= e($base['sha256'] ?: 'n/d') ?></td></tr>
</table>
<h3 class="sec" style="margin:12px 0 5px;"><span style="font-size:10pt; color:#161e2e;">Fichiers de la base</span></h3>
<table class="data">
    <thead><tr><th style="width:50%;">Fichier</th><th style="width:12%;">Type</th><th style="width:12%;">Taille</th><th>Empreinte SHA-256</th></tr></thead>
    <?php foreach ($base['files'] as $bf): ?>
        <tr><td class="name mono small"><?= e($bf['path']) ?></td><td class="small"><?= $bf['kind'] === 'image' ? 'image' : 'texte' ?></td><td class="num small"><?= number_format((int) $bf['bytes'], 0, ',', ' ') ?> o</td><td class="small mono"><?= e(substr((string) $bf['sha256'], 0, 16)) ?>…</td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- ===================== §3 MÉTHODOLOGIE ===================== -->
<?= sec_head('sec3', '§3', 'Comment chaque chiffre est calculé', '#3b82f6', 'Formules & sources') ?>
<p class="lead">Chaque indicateur du rapport : valeur, formule exacte, nature et messages sources.</p>
<table class="data">
    <tr><th style="width:17%;">Indicateur</th><th style="width:14%;">Valeur</th><th style="width:30%;">Méthode de calcul</th><th style="width:10%;">Nature</th><th>Sources</th></tr>
    <?php foreach ($methodology as $m): ?>
        <tr>
            <td class="name"><?= e($m['indicator']) ?></td>
            <td class="strong"><?= e($m['value']) ?></td>
            <td class="small"><?= e($m['formula']) ?></td>
            <td><?= nature_badge($m['nature']) ?></td>
            <td class="small mono"><?php if ($m['ids'] === null): ?><?= e($m['ids_note']) ?><?php else: ?><?= $m['ids_note'] !== '' ? e($m['ids_note']) . ' ' : '' ?><?= refs($m['ids'], $ref_width, $refs_inline_max) ?><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="foot-note">Listes longues compactées en plages ; liste exhaustive reconstituable depuis le registre §9 ou le CSV.</p>

<!-- ===================== §4 DÉCOMPOSITION ===================== -->
<?= sec_head('sec4', '§4', 'Décomposition des agrégats', '#8b5cf6', 'Chiffre → liste de références', '<span class="badge b-estim">estimé (IA)</span>') ?>
<p class="lead">Chaque tableau du rapport, avec les références qui composent chaque ligne. La somme de chaque tableau vaut <?= (int) $total_messages ?>.</p>
<?php
$aggTitles = ['topics' => 'Sujets', 'intents' => 'Intentions', 'quality' => 'Qualité des réponses'];
foreach ($aggTitles as $aggKey => $aggTitle): ?>
    <h3 class="sec" style="margin:12px 0 5px;"><span style="font-size:10pt; color:#161e2e;"><?= e($aggTitle) ?></span></h3>
    <table class="data">
        <tr><th style="width:24%;">Étiquette</th><th style="width:8%;">Nb</th><th style="width:8%;">Part</th><th>Messages sources</th></tr>
        <?php foreach ($aggregates[$aggKey] as $row): ?>
            <tr><td class="name"><?= e($row['name']) ?></td><td class="num"><?= (int) $row['count'] ?></td><td class="num"><?= e($row['percent']) ?>%</td><td class="small mono"><?= refs($row['ids'], $ref_width, $refs_inline_max) ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endforeach; ?>
<h3 class="sec" style="margin:12px 0 5px;"><span style="font-size:10pt; color:#161e2e;">Lacunes par sujet (factuel × estimé)</span></h3>
<table class="data">
    <tr><th style="width:24%;">Sujet</th><th style="width:8%;">Quest.</th><th style="width:10%;">Sans rép.</th><th style="width:8%;">Taux</th><th>Références sans réponse</th></tr>
    <?php foreach ($aggregates['gaps'] as $g): ?>
        <tr><td class="name"><?= e($g['name']) ?></td><td class="num"><?= (int) $g['count'] ?></td><td class="num"><?= (int) $g['no_answer'] ?></td><td class="num"><?= e($g['rate']) ?>%</td><td class="small mono"><?= refs($g['no_answer_ids'], $ref_width, $refs_inline_max) ?></td></tr>
    <?php endforeach; ?>
</table>

<!-- ===================== §5 NON-RÉPONSES ===================== -->
<?= sec_head('sec5', '§5', 'Détail des non-réponses (' . count($non_responses) . ')', '#dc2626', 'Règle exacte', '<span class="badge b-fact">calculé</span>') ?>
<p class="lead">Chaque ligne comptée « non-réponse » par une règle automatique explicite (aucun jugement IA).</p>
<?php if ($non_responses === []): ?>
    <p class="muted">Aucune non-réponse détectée selon les règles.</p>
<?php else: ?>
    <table class="data">
        <thead><tr><th style="width:8%;">Réf.</th><th style="width:13%;">Date</th><th style="width:30%;">Question</th><th style="width:25%;">Réponse</th><th>Règle déclenchée</th></tr></thead>
        <?php foreach ($non_responses as $x): ?>
            <tr><td class="mono"><?= ref_link((int) $x['id'], $ref_width, $registry_groups, $anchors_enabled) ?></td><td class="small"><?= e($x['date']) ?></td><td class="small"><?= e(cut($x['question'], 80)) ?></td><td class="small"><?= $x['reponse'] === '' ? '<em>(vide)</em>' : e(cut($x['reponse'], 70)) ?></td><td class="small"><?= e($x['regle']) ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<!-- ===================== §6 HALLUCINATIONS ===================== -->
<?= sec_head('sec6', '§6', 'Réponses signalées comme inventées (' . count($hallucinations) . ')', '#f43f5e', 'À vérifier humainement', '<span class="badge b-estim">estimé (IA)</span>') ?>
<p class="lead">Réponses que l'IA a estimées « hallucination » d'après le seul texte. Signalements à vérifier, pas des faits établis.</p>
<?php if ($hallucinations === []): ?>
    <p class="muted">Aucune réponse signalée comme potentiellement inventée.</p>
<?php else: ?>
    <?php foreach ($hallucinations as $x): ?>
        <div class="qa">
            <div class="q"><?= ref_link((int) $x['id'], $ref_width, $registry_groups, $anchors_enabled) ?> <?= e(cut($x['question'], 120)) ?> <span class="small soft">— <?= e($x['date']) ?><?= $x['lot'] !== null ? ' · lot qualité n°' . (int) $x['lot'] : '' ?></span></div>
            <div class="a"><?= e(cut($x['reponse'], 320)) ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($base_enabled)): ?>
<!-- ===================== §6bis FIDÉLITÉ À LA BASE ===================== -->
<?= sec_head('sec6bis', '§6bis', 'Fidélité des réponses à la base', '#14b8a6', 'Comparaison avec la base de connaissance') ?>
<p class="lead">Chaque réponse a été comparée à la base entière du bot (scellée en §2bis). La vérification des images est un calcul ; l'ancrage est estimé par l'IA. Comptages PHP.</p>
<?php if (!empty($grounding) && $ai_enabled): ?>
<table class="data">
    <thead><tr><th style="width:24%;">Ancrage</th><th style="width:8%;">Nb</th><th style="width:8%;">Part</th><th>Messages sources (références)</th></tr></thead>
    <?php foreach ($grounding as $row): ?>
        <tr><td class="name"><?= e($row['name']) ?></td><td class="num"><?= (int) $row['count'] ?></td><td class="num"><?= e($row['percent']) ?>%</td><td class="small mono"><?= refs($row['ids'], $ref_width, $refs_inline_max) ?></td></tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p class="muted">Ancrage non évalué (IA désactivée). La vérification des images reste effectuée.</p>
<?php endif; ?>

<h3 class="sec" style="margin:12px 0 5px;"><span style="font-size:10pt; color:#161e2e;">Réponses non fondées (<?= count($grounding_unfounded) ?>)</span></h3>
<?php if ($grounding_unfounded === []): ?>
    <p class="muted">Aucune réponse étiquetée « non fondée ».</p>
<?php else: ?>
    <p class="lead">Réponses affirmant des faits absents de la base. Comparées à la base entière (§2bis).</p>
    <?php foreach ($grounding_unfounded as $x): ?>
        <div class="qa">
            <div class="q"><?= ref_link((int) $x['id'], $ref_width, $registry_groups, $anchors_enabled) ?> <?= e(cut($x['question'], 120)) ?></div>
            <div class="a"><?= e(cut($x['reponse'], 320)) ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h3 class="sec" style="margin:12px 0 5px;"><span style="font-size:10pt; color:#161e2e;">Images citées introuvables (<?= (int) ($broken_images['count'] ?? 0) ?>) <span class="badge b-fact">calculé</span></span></h3>
<?php if (empty($broken_images['items'])): ?>
    <p class="muted">Toutes les images citées existent dans la base.</p>
<?php else: ?>
    <table class="data">
        <thead><tr><th style="width:8%;">Réf.</th><th style="width:55%;">Lien cité</th><th>Fichier introuvable</th></tr></thead>
        <?php foreach ($broken_images['items'] as $it): ?>
            <tr><td class="mono"><?= ref_link((int) $it['id'], $ref_width, $registry_groups, $anchors_enabled) ?></td><td class="small mono"><?= e(cut($it['url'], 80)) ?></td><td class="small mono"><?= e($it['fichier']) ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<?php endif; ?>

<!-- ===================== §7 JOURNAL IA ===================== -->
<?= sec_head('sec7', '§7', 'Journal du raisonnement IA', '#6366f1', 'Ce que l\'IA a reçu et répondu') ?>
<?php if (!$ai_enabled): ?>
    <div class="callout-warn">
        <span class="strong" style="color:#92560a;">Analyse IA désactivée.</span> Aucune requête émise : toutes les étiquettes
        valent « Non classé » par construction (statut <span class="mono">ia_desactivee</span> dans le CSV). Les indicateurs
        factuels (§3, §5) ne sont pas concernés.
    </div>
<?php else: $sum = $ai['summary']; ?>
    <p class="lead">Ce que l'IA a reçu, répondu et ce qui en a été retenu, appel par appel. L'IA reçoit UNIQUEMENT le texte des messages listés et choisit dans des listes fermées.</p>

    <h3 class="sec" style="margin:6px 0 5px;"><span style="font-size:10pt; color:#161e2e;">§7.1 · Paramètres</span></h3>
    <table class="data">
        <tr><th>Modèle</th><th>Hôte</th><th>Temp.</th><th>Lot</th><th>Troncature</th><th>Appels</th><th>Relances</th><th>Durée réseau</th></tr>
        <tr>
            <td class="name"><?= e($ai_params['model']) ?></td><td class="small"><?= e($ai_params['endpoint_host']) ?></td>
            <td class="num"><?= e($ai_params['temperature']) ?></td><td class="num"><?= (int) $ai_params['batch_size'] ?></td>
            <td class="num"><?= (int) $ai_params['max_field_chars'] ?> c.</td><td class="num"><?= (int) ($sum['http_calls'] ?? 0) ?></td>
            <td class="num"><?= (int) ($sum['retries'] ?? 0) ?></td><td class="num"><?= number_format((int) ($sum['duration_ms'] ?? 0), 0, ',', ' ') ?> ms</td>
        </tr>
    </table>
    <p class="foot-note">Durées = temps des appels HTTP (hors attentes entre relances, <?= (int) $ai_params['retry_delay'] ?> s × n° de tentative). La troncature ne s'applique qu'au texte <em>envoyé à l'IA</em> ; les statistiques factuelles utilisent toujours le texte intégral. Consignes exactes (prompts) : <a href="#secA" style="color:#3457e0;">Annexe A</a>.</p>

    <h3 class="sec" style="margin:14px 0 5px;"><span style="font-size:10pt; color:#161e2e;">§7.2 · Les appels, lot par lot</span></h3>
    <p class="lead">
        <?= (int) ($sum['batches'] ?? 0) ?> lots · <span class="ok-tick"><?= (int) ($sum['batches_ok'] ?? 0) ?> réussi(s)</span> ·
        <?= (int) ($sum['batches_ko'] ?? 0) ?> en échec · <?= (int) ($sum['corrected'] ?? 0) ?> corrigée(s) ·
        <?= (int) ($sum['missing'] ?? 0) ?> sans réponse IA · <?= (int) ($sum['unknown'] ?? 0) ?> id(s) rejeté(s).
        <span class="strong">OK sans correction</span> = étiquette retenue mot pour mot celle du modèle ; toute divergence est en §7.3.
    </p>
    <?php
    $phaseTitles = ['classification' => 'Classification', 'qualite' => 'Qualité'];
    foreach ($phaseTitles as $phaseKey => $phaseTitle):
        $phaseBatches = array_values(array_filter($ai['batches'], static fn($b) => $b['phase'] === $phaseKey));
        if ($phaseBatches === []) { continue; } ?>
        <h4 style="font-size:8.8pt; color:#5b6573; margin:9px 0 3px;"><?= e($phaseTitle) ?> — <?= count($phaseBatches) ?> lot(s)</h4>
        <table class="data">
            <thead><tr><th style="width:5%;">Lot</th><th style="width:24%;">Messages envoyés</th><th style="width:8%;">Env.</th><th style="width:9%;">Exploités</th><th style="width:8%;">Corr.</th><th style="width:9%;">Manq.</th><th style="width:13%;">HTTP</th><th style="width:9%;">Durée</th><th>Statut</th></tr></thead>
            <?php foreach ($phaseBatches as $b): ?>
                <tr>
                    <td class="num"><?= (int) $b['index'] ?></td>
                    <td class="small mono"><?= refs($b['ids'], $ref_width, 6) ?></td>
                    <td class="num"><?= count($b['ids']) ?></td>
                    <td class="num"><?= (int) $b['applied'] ?></td>
                    <td class="num"><?= (int) $b['corrected'] ?></td>
                    <td class="num"><?= count($b['missing_ids']) ?></td>
                    <td class="small"><?php $att = []; foreach ($b['attempts'] as $a) { $att[] = $a['http'] > 0 ? $a['http'] : 'réseau'; } echo e($att === [] ? '—' : implode(' → ', $att)); ?></td>
                    <td class="num"><?= number_format((int) $b['duration_ms'], 0, ',', ' ') ?> ms</td>
                    <td><?= $b['status'] === 'ok' ? '<span class="st-ok">OK</span>' : '<span class="st-ko">ÉCHEC</span>' ?></td>
                </tr>
                <?php if ($b['status'] !== 'ok'): ?>
                    <tr><td></td><td colspan="8" class="small" style="color:#c81e1e;">Erreur : <?= e($b['error']) ?> — les <?= count($b['ids']) ?> messages (<?= refs($b['ids'], $ref_width, 6) ?>) sont restés « Non classé ». Aucune invention.</td></tr>
                <?php elseif ($b['missing_ids'] !== [] || $b['unknown_ids'] !== [] || $b['corrected'] > 0): ?>
                    <tr><td></td><td colspan="8" class="small" style="color:#92560a;">
                        <?php if ($b['missing_ids'] !== []): ?>Absents de la réponse (« Non classé ») : <?= refs($b['missing_ids'], $ref_width, 10) ?>. <?php endif; ?>
                        <?php if ($b['unknown_ids'] !== []): ?>Ids inconnus rejetés : <?= e(implode(', ', $b['unknown_ids'])) ?>. <?php endif; ?>
                        <?php if ($b['corrected'] > 0): ?><?= (int) $b['corrected'] ?> étiquette(s) corrigée(s) (§7.3). <?php endif; ?>
                        <?php if (($b['response_excerpt'] ?? '') !== ''): ?><br>Réponse brute (extrait) : <span class="mono"><?= e($b['response_excerpt']) ?></span><?php endif; ?>
                    </td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <h3 class="sec" style="margin:14px 0 5px;"><span style="font-size:10pt; color:#161e2e;">§7.3 · Corrections : valeur brute IA → valeur retenue</span></h3>
    <?php if (($ai['corrections'] ?? []) === []): ?>
        <?php if ((int) ($sum['batches_ko'] ?? 0) === 0): ?>
            <p class="muted">Aucune correction : toutes les étiquettes renvoyées appartenaient aux listes fermées, chaque message a reçu une réponse.</p>
        <?php else: ?>
            <p class="muted">Aucune étiquette hors liste parmi les lots réussis ; les messages des lots en échec (« Non classé ») sont en §7.2.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="lead">Chaque divergence entre la réponse du modèle et ce qui figure au rapport. La barrière anti-hallucination en action.</p>
        <table class="data">
            <thead><tr><th style="width:8%;">Réf.</th><th style="width:13%;">Phase</th><th style="width:6%;">Lot</th><th style="width:33%;">Valeur brute de l'IA</th><th style="width:24%;">Valeur retenue</th><th>Motif</th></tr></thead>
            <?php foreach ($ai['corrections'] as $c): ?>
                <tr><td class="mono"><?= ref_link((int) $c['id'], $ref_width, $registry_groups, $anchors_enabled) ?></td><td class="small"><?= e($c['phase']) ?></td><td class="num"><?= $c['lot'] !== null ? (int) $c['lot'] : '—' ?></td><td class="small mono"><?= $c['brut'] !== '' ? e($c['brut']) : '<em>(absent)</em>' ?></td><td class="small"><?= e($c['retenu']) ?></td><td class="small"><?= $c['motif'] === 'corrige' ? 'Hors liste fermée' : 'Absent de la réponse' ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<!-- ===================== §8 GARANTIES ===================== -->
<div style="page-break-inside:avoid;">
<?= sec_head('sec8', '§8', 'Garanties & limites', '#0f9d58', 'Ce qui est prouvé, ce qui ne l\'est pas') ?>
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:50%; padding:4px; vertical-align:top;">
            <div class="callout-ok"><span class="strong" style="color:#146c4a;">Prouvé.</span> Chaque chiffre est un comptage déterministe dont les sources sont listées (§3–§5) ; chaque étiquette IA est rattachée à son message, son lot et, si corrigée, à la valeur brute du modèle (§7) ; registre §9 + CSV reconstituent tout depuis le fichier source scellé (§2).</div>
        </td>
        <td style="width:50%; padding:4px; vertical-align:top;">
            <div class="callout-warn"><span class="strong" style="color:#92560a;">Non prouvé.</span> Les étiquettes IA restent des estimations (listes fermées, température 0, mais pas infaillibles). Les « hallucinations » (§6) sont à contrôler humainement. Les textes de §5/§6/§9 sont tronqués pour l'impression ; le texte intégral fait foi dans le CSV.</div>
        </td>
    </tr>
</table>
<div class="note" style="margin-top:8px;">
    <span class="strong">CSV de traçabilité (<?= e($csv_name) ?>).</span> 1 ligne = 1 message. Colonnes : <span class="mono">ref</span>, <span class="mono">id</span>,
    <span class="mono">date</span>, <span class="mono">user_id</span>, <span class="mono">question</span>/<span class="mono">reponse</span> (intégraux),
    <span class="mono">longueur_reponse</span>, <span class="mono">erreur_source</span>, <span class="mono">non_reponse</span>/<span class="mono">regle_non_reponse</span>,
    étiquettes retenues, puis par phase IA : <span class="mono">lot_*</span>, <span class="mono">statut_ia_*</span>
    (ok / corrige / absent_reponse / lot_echec / ia_desactivee) et <span class="mono">brut_ia_*</span> si corrigée.
    Toute cellule de texte commençant par <span class="mono">= + - @</span> est préfixée d'une apostrophe (anti-formule tableur ; non présente dans le texte d'origine).
</div>
</div>

<!-- ===================== §9 REGISTRE (PAYSAGE) ===================== -->
<pagebreak orientation="landscape" />
<?= sec_head('sec9', '§9', 'Registre complet des ' . (int) $total_messages . ' messages', '#3457e0', 'Tous les messages') ?>
<p class="lead">Chaque message du fichier, dans l'ordre, avec ses étiquettes et ses lots IA. Textes tronqués pour l'impression (intégraux dans le CSV).</p>
<table class="data" style="margin-bottom:4mm;">
    <tr><th style="width:33%;">Intentions (Int.)</th><th style="width:42%;">Sujets (Suj.)</th><th>Qualité (Qual.)</th></tr>
    <tr>
        <td class="small"><?php $p = []; foreach ($codes_legend['intents'] as $c => $l) { $p[] = '<span class="strong">' . e($c) . '</span> ' . e($l); } echo implode(' · ', $p); ?></td>
        <td class="small"><?php $p = []; foreach ($codes_legend['topics'] as $c => $l) { $p[] = '<span class="strong">' . e($c) . '</span> ' . e($l); } echo implode(' · ', $p); ?></td>
        <td class="small"><?php $p = []; foreach ($codes_legend['quality'] as $c => $l) { $p[] = '<span class="strong">' . e($c) . '</span> ' . e($l); } echo implode(' · ', $p); ?></td>
    </tr>
    <tr><th>NR — non-réponse</th><th>IA — statut (le plus défavorable des 2 phases ; détail par phase dans le CSV)</th><th>H — hallucination</th></tr>
    <tr>
        <td class="small"><?php $p = []; foreach ($codes_legend['nr'] as $c => $l) { $p[] = '<span class="strong">' . e($c) . '</span> ' . e($l); } echo implode(' · ', $p); ?></td>
        <td class="small"><?php $p = []; foreach ($codes_legend['statut'] as $c => $l) { $p[] = '<span class="strong">' . e($c) . '</span> ' . e($l); } echo implode(' · ', $p); ?></td>
        <td class="small"><span class="strong">!</span> signalée (§6) · <span class="strong">—</span> non</td>
    </tr>
</table>

<?php foreach ($registry_groups as $g): ?>
    <?php if ($anchors_enabled): ?><a name="<?= e($g['anchor']) ?>"></a><?php endif; ?>
    <bookmark content="Registre <?= e(Text::ref($g['from'], $ref_width)) ?>–<?= e(Text::ref($g['to'], $ref_width)) ?>" level="1" />
    <h3 class="reg-h">Messages <?= e(Text::ref($g['from'], $ref_width)) ?> à <?= e(Text::ref($g['to'], $ref_width)) ?></h3>
    <table class="reg">
        <thead><tr>
            <th class="mono" width="5%">Réf.</th><th width="7%">Date</th><th width="7%">Visiteur</th>
            <th width="28%">Question (extrait)</th><th width="29%">Réponse (extrait)</th>
            <th width="4%">Long.</th><th width="3%">NR</th><th width="3%">Int.</th><th width="3%">Suj.</th>
            <th width="3%">Qual.</th><th width="2%">H</th><th width="2%">Lot C</th><th width="2%">Lot Q</th><th width="2%">IA</th>
        </tr></thead>
        <tbody>
        <?php foreach ($g['rows'] as $r): ?>
            <tr>
                <td class="mono"><?= e($r['ref']) ?></td><td><?= e($r['date']) ?></td><td class="mono"><?= e($r['user']) ?></td>
                <td><?= $r['question'] === '' ? '<em class="muted">(vide)</em>' : e($r['question']) ?></td>
                <td><?= $r['reponse'] === '' ? '<em class="muted">(vide)</em>' : e($r['reponse']) ?></td>
                <td class="num"><?= (int) $r['rep_len'] ?></td><td><?= e($r['nr']) ?></td><td><?= e($r['intent']) ?></td>
                <td><?= e($r['topic']) ?></td><td><?= e($r['quality']) ?></td>
                <td><?= $r['halluc'] ? '<span class="strong" style="color:#dc2626;">!</span>' : '—' ?></td>
                <td class="num"><?= $r['lot_c'] !== null ? (int) $r['lot_c'] : '—' ?></td><td class="num"><?= $r['lot_q'] !== null ? (int) $r['lot_q'] : '—' ?></td><td><?= e($r['statut']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>
<?php $hasAnnexe = $ai_enabled && ($ai['components'] ?? []) !== []; ?>
<?php if (!$hasAnnexe): ?>
<p class="foot-note">Fin du registre — <?= (int) $total_messages ?> messages. Texte intégral d'un message : ouvrir <?= e($csv_name) ?> et filtrer la colonne <span class="mono">ref</span>.</p>
<?php endif; ?>

<?php if ($hasAnnexe): ?>
<!-- ===================== ANNEXE A — PROMPTS ===================== -->
<pagebreak orientation="portrait" />
<?= sec_head('secA', 'Annexe A', 'Consignes exactes données à l\'IA', '#6366f1', 'Prompts in extenso') ?>
<p class="lead"><span class="mono">{ITEMS}</span> est remplacé par les messages du lot (références en §7.2) ; les listes fermées autorisées sont rappelées en bas.</p>
<?php
$componentTitles = ['classification' => 'Phase 1 — Classification (intention + sujet)', 'qualite' => 'Phase 2 — Jugement de qualité'];
foreach (($ai['components'] ?? []) as $phase => $comp): ?>
    <h3 class="sec" style="margin:10px 0 5px;"><span style="font-size:9.8pt; color:#161e2e;"><?= e($componentTitles[$phase] ?? $phase) ?></span> <span class="small soft">(gabarit : <?= e($comp['prompt_file']) ?>)</span></h3>
    <pre class="prompt"><span class="strong">Consigne système :</span>
<?= e($comp['system']) ?>

<span class="strong">Gabarit du message :</span>
<?= e($comp['template']) ?></pre>
<?php endforeach; ?>
<table class="data">
    <tr><th style="width:22%;">Liste fermée</th><th>Valeurs autorisées (toute autre valeur → « Non classé »)</th></tr>
    <tr><td class="name">Intentions</td><td class="mono small"><?= e(implode(' · ', $taxonomies['intents'])) ?></td></tr>
    <tr><td class="name">Sujets</td><td class="mono small"><?= e(implode(' · ', $taxonomies['topics'])) ?></td></tr>
    <tr><td class="name">Qualité</td><td class="mono small"><?= e(implode(' · ', $taxonomies['quality'])) ?></td></tr>
</table>
<?php endif; ?>

</body>
</html>
