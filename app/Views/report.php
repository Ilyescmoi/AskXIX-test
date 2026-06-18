<?php
use App\Libraries\Text;
/**
 * Gabarit du RAPPORT principal (Document 1/2) — rendu par mPDF. AFFICHAGE UNIQUEMENT.
 * Style « clair & moderne » : fond blanc, cartes douces, accents colorés.
 * Toutes les valeurs viennent de ReportData::build(). Aucun calcul de donnée ici.
 *
 * @var string $generated_at
 * @var string $app_name
 * @var string $subtitle
 * @var bool   $ai_enabled
 * @var int    $ref_width
 * @var int    $refs_max
 * @var int    $best_n
 * @var array  $source        ['file','bytes','sha256','token']
 * @var array  $stats
 * @var array  $engagement
 * @var array  $highlights    type, label, text, ids[], annexe
 * @var array  $intents       key, name, count, percent, ids[]
 * @var array  $topics
 * @var array  $quality
 * @var array  $hallucination ['count','rate','ids']
 * @var array  $gaps          ..., no_answer_ids[]
 * @var array  $ai_issues     failed_classification, failed_quality, corrected, missing
 * @var array  $top_questions question, count, topic, ids[]
 * @var array  $unanswered    id, question, reponse, topic, regle, date
 * @var array  $best_answers  id, question, reponse
 * @var int    $best_answers_eligible
 */

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('ref_pill')) {
    function ref_pill(int $id, int $width): string {
        return '<span class="ref">' . e(Text::ref($id, $width)) . '</span>';
    }
}
if (!function_exists('refs_inline')) {
    function refs_inline(array $ids, int $width, int $max): string {
        if ($ids === []) { return ''; }
        $shown = array_slice($ids, 0, $max);
        $out = implode(' ', array_map(static fn($id) => ref_pill((int) $id, $width), $shown));
        $rest = count($ids) - count($shown);
        return $out . ($rest > 0 ? ' <span class="soft small">+' . $rest . '</span>' : '');
    }
}
if (!function_exists('hl_accent')) {
    // Couleur d'accent d'un point de synthèse selon son type.
    function hl_accent(string $t): string {
        return match ($t) {
            'risk'    => '#dc2626',
            'warning' => '#d97706',
            'good'    => '#059669',
            default   => '#2563eb', // info
        };
    }
}
if (!function_exists('rate_accent')) {
    // Couleur d'un taux de non-réponse (plus c'est haut, plus c'est rouge).
    function rate_accent(float $rate): string {
        if ($rate >= 30) return '#dc2626';
        if ($rate >= 15) return '#d97706';
        return '#059669';
    }
}
if (!function_exists('bar')) {
    // Barre horizontale en TABLE imbriquée (mPDF ne rend que les fonds de td).
    function bar(float $pct, string $color = '#3b82f6'): string {
        $w = (int) round(max(0, min(100, $pct)));
        $cell = static fn(string $bg, string $width = '', string $round = '') =>
            '<td' . ($width !== '' ? ' width="' . $width . '"' : '') . ' class="vb" style="background:' . $bg . ';'
            . $round . ' height:3.4mm;">&nbsp;</td>';
        if ($w <= 0) {
            return '<table class="barwrap"><tr>' . $cell('#eef1f5', '', 'border-radius:3px;') . '</tr></table>';
        }
        if ($w >= 100) {
            return '<table class="barwrap"><tr>' . $cell($color, '', 'border-radius:3px;') . '</tr></table>';
        }
        return '<table class="barwrap"><tr>'
             . $cell($color, $w . '%', 'border-radius:3px 0 0 3px;') . $cell('#eef1f5', '', 'border-radius:0 3px 3px 0;')
             . '</tr></table>';
    }
}
if (!function_exists('fmt_peak')) {
    function fmt_peak(?array $peak, callable $fmt): string {
        if ($peak === null) { return 'n/d'; }
        return $fmt($peak['key']) . ($peak['ties'] > 1 ? '*' : '');
    }
}

// Palette d'accents cyclée pour les catégories (sujets, intentions).
$CAT = ['#3b82f6', '#06b6d4', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#6366f1', '#14b8a6', '#f43f5e', '#0ea5e9', '#a855f7'];

$period  = $stats['period'];
$nonResp = $stats['non_response'];
$hours   = $stats['by_hour'];
$maxHour = max(1, max($hours));

$peakHour = $stats['peak_hour'];
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
<?php include __DIR__ . '/partials/theme.php'; ?>

/* ---------- Spécifique rapport ---------- */
.cover { padding: 6mm 2mm 0; }
.cover .kick { font-size: 8.5pt; letter-spacing: 2.6px; text-transform: uppercase; color: #9aa3b2; font-weight: 700; }
.cover h1 { font-size: 30pt; font-weight: 800; line-height: 1.08; margin: 9px 0 0; color: #161e2e; }
.cover .sub { font-size: 11.5pt; color: #6b7280; margin: 11px 0 0; max-width: 470px; }
table.accent { border-collapse: separate; border-spacing: 3px 0; margin: 15px 0 4px; }
table.accent td { height: 4px; width: 32px; border-radius: 2px; }
.cover-foot { color: #9aa3b2; font-size: 8.5pt; margin-top: 7px; }

.brief td { padding: 4px; vertical-align: top; }
.brief .item { background: #fff; border: 0.2mm solid #eef1f5; border-radius: 11px; padding: 10px 12px; }
.brief .lab { font-size: 7.6pt; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
.brief .txt { font-size: 9.6pt; color: #2b3546; margin-top: 3px; }

table.hours { width: 100%; border-collapse: collapse; margin-top: 3mm; table-layout: fixed; }
table.hours td.hcell { width: 4.16%; vertical-align: bottom; padding: 0 0.5mm; border-bottom: 0.3mm solid #d8dee8; }
table.hours table.hcol { width: 100%; border-collapse: collapse; }
table.hours tr.hlbl td { border-bottom: none; font-size: 6.5pt; color: #9aa3b2; text-align: center; padding-top: 1mm; }
</style>
</head>
<body>

<htmlpageheader name="hdr">
    <table class="run-hd"><tr>
        <td class="brand"><?= e($app_name) ?></td>
        <td align="right" class="doc">Document 1/2 · Rapport</td>
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
<div class="cover">
    <div class="kick">Rapport d'analyse · Document 1/2</div>
    <h1><?= e($app_name) ?></h1>
    <table class="accent"><tr>
        <td style="background:#3b82f6;"></td><td style="background:#8b5cf6;"></td>
        <td style="background:#06b6d4;"></td><td style="background:#f59e0b;"></td><td style="background:#10b981;"></td>
    </tr></table>
    <div class="sub"><?= e($subtitle) ?></div>
</div>

<table class="kpi" style="margin-top:20px;">
    <tr>
        <td>
            <div class="num" style="font-size:12pt;"><?= e($period['start'] ?? 'n/d') ?></div>
            <div class="lbl">au <?= e($period['end'] ?? 'n/d') ?></div>
        </td>
        <td><div class="num"><?= (int) $stats['total_messages'] ?></div><div class="lbl">Messages</div></td>
        <td><div class="num"><?= (int) $stats['unique_users'] ?></div><div class="lbl">Visiteurs uniques</div></td>
    </tr>
</table>

<div class="callout-info" style="margin-top:16px;">
    <span class="strong" style="color:#2440b8;">Chaque chiffre de ce rapport est traçable.</span>
    Chaque message porte une référence <span class="ref"><?= e(Text::ref(1, $ref_width)) ?></span>…<span class="ref"><?= e(Text::ref((int) $stats['total_messages'], $ref_width)) ?></span>,
    commune à ce rapport, au <span class="strong" style="color:#2440b8;">Document de traçabilité (2/2)</span> et au CSV.
    Le document 2/2 justifie chaque indicateur, journalise les analyses IA et liste tous les messages.
    <br>Source : <span class="strong" style="color:#2440b8;"><?= e($source['file'] ?? 'n/d') ?></span><?php if ($shaShort !== ''): ?> · SHA-256 <span class="mono"><?= e($shaShort) ?>…</span><?php endif; ?>
</div>
<div class="cover-foot">
    Généré le <?= e($generated_at) ?> ·
    <?= $ai_enabled ? 'Analyse IA activée (typologies estimées, listes fermées)' : 'Mode factuel uniquement (sans IA)' ?>
    · Indicateurs chiffrés calculés de façon déterministe.
</div>

<pagebreak />
<sethtmlpageheader name="hdr" value="on" show-this-page="1" />
<sethtmlpagefooter name="ftr" value="on" />

<!-- ===================== EN BREF ===================== -->
<bookmark content="En bref" level="0" />
<div class="sec">
    <div class="kick">Synthèse</div>
    <h2>En bref <?= $ai_enabled ? '<span class="badge b-fact">calculé</span> <span class="badge b-estim">estimé</span>' : '<span class="badge b-fact">calculé</span>' ?></h2>
    <div class="dash" style="background:#3b82f6;"></div>
</div>
<p class="lead">Les points clés de la période, générés automatiquement à partir des chiffres. Sous chaque point : les messages sources.</p>
<?php
$render_brief = static function (array $hgt) use ($ref_width, $refs_max): string {
    $ac = hl_accent($hgt['type']);
    $src = '';
    if (!empty($hgt['ids'])) {
        $src = '<div class="src">Sources : ' . refs_inline($hgt['ids'], $ref_width, $refs_max)
             . ' · vérif. Traçabilité ' . e($hgt['annexe']) . '</div>';
    }
    return '<div class="item"><span class="dot" style="background:' . $ac . ';"></span>'
         . '<span class="lab" style="color:' . $ac . ';"> ' . e($hgt['label']) . '</span>'
         . '<div class="txt">' . e($hgt['text']) . '</div>' . $src . '</div>';
};
$hc = count($highlights);
?>
<table style="width:100%; border-collapse:collapse;" class="brief">
    <?php for ($i = 0; $i < $hc; $i += 2): ?>
    <tr>
        <?php if ($i + 1 < $hc): // deux cartes sur la ligne ?>
            <td style="width:50%;"><?= $render_brief($highlights[$i]) ?></td>
            <td style="width:50%;"><?= $render_brief($highlights[$i + 1]) ?></td>
        <?php else: // dernière carte seule -> pleine largeur (pas de cellule vide) ?>
            <td colspan="2"><?= $render_brief($highlights[$i]) ?></td>
        <?php endif; ?>
    </tr>
    <?php endfor; ?>
</table>

<!-- ===================== INDICATEURS CLÉS ===================== -->
<bookmark content="Indicateurs clés" level="0" />
<div class="sec">
    <div class="kick">Volumétrie &amp; activité</div>
    <h2>Indicateurs clés <span class="badge b-fact">calculé</span></h2>
    <div class="dash" style="background:#10b981;"></div>
</div>
<table class="kpi">
    <tr>
        <td><div class="num"><?= (int) $stats['total_messages'] ?></div><div class="lbl">Messages</div></td>
        <td><div class="num"><?= (int) $stats['unique_users'] ?></div><div class="lbl">Visiteurs</div></td>
        <td><div class="num"><?= e($stats['messages_per_user']) ?></div><div class="lbl">Quest. / visiteur</div></td>
        <td><div class="num"><?= e($engagement['returning_rate']) ?>%</div><div class="lbl">Visiteurs revenus</div></td>
    </tr>
    <tr>
        <td><div class="num" style="color:<?= rate_accent((float) $nonResp['rate']) ?>;"><?= e($nonResp['rate']) ?>%</div><div class="lbl">Non-réponse</div></td>
        <td><div class="num"><?= $period['span_days'] !== null ? (int) $period['span_days'] : 'n/d' ?></div><div class="lbl">Jours couverts</div></td>
        <td><div class="num"><?= e($peakHourLabel) ?></div><div class="lbl">Heure de pic</div></td>
        <td><div class="num" style="font-size:13pt;"><?= e($peakDayLabel) ?></div><div class="lbl">Jour le plus actif</div></td>
    </tr>
</table>
<p class="verif">
    Engagement : <?= (int) $engagement['returning'] ?> visiteur(s) ont posé plusieurs questions, <?= (int) $engagement['single'] ?> une seule.
    <?php if ($hasTies): ?>* ex æquo : plusieurs heures/jours au même maximum (détail Traçabilité §3).<?php endif; ?>
    Formule de chaque indicateur : Traçabilité §3.
</p>

<!-- ===================== ACTIVITÉ PAR HEURE ===================== -->
<bookmark content="Activité par heure" level="0" />
<div class="sec">
    <div class="kick">Temporalité</div>
    <h2>Quand vos visiteurs écrivent <span class="badge b-fact">calculé</span></h2>
    <div class="dash" style="background:#06b6d4;"></div>
</div>
<?php if ((int) ($stats['timed_rows'] ?? 0) === 0): ?>
    <p class="muted">Le fichier source ne contient pas d'heures exploitables : profil horaire non calculable (Traçabilité §3).</p>
<?php else: ?>
    <p class="lead">Répartition des <?= (int) $stats['timed_rows'] ?> messages horodatés par heure. La barre dorée marque l'heure de pic (<?= e($peakHourLabel) ?>, <?= (int) ($peakHour['count'] ?? 0) ?> messages).</p>
    <table class="hours">
        <tr>
        <?php foreach ($hours as $h => $c):
            $mm = round(16 * $c / $maxHour, 1);
            $isPeak = $peakHour !== null && $h === $peakHour['key'] && $c > 0; ?>
            <td class="hcell">
                <?php if ($c > 0): ?>
                <table class="hcol"><tr>
                    <td class="vb" style="height:<?= max(0.8, $mm) ?>mm; background:<?= $isPeak ? '#f59e0b' : '#3b82f6' ?>; border-radius:2px 2px 0 0;">&nbsp;</td>
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
        <p class="foot-note"><?= $noTime ?> message(s) datés sans heure, hors de ce profil (références Traçabilité §3).</p>
    <?php endif; ?>
<?php endif; ?>

<!-- ===================== CENTRES D'INTÉRÊT ===================== -->
<bookmark content="Centres d'intérêt" level="0" />
<div class="sec">
    <div class="kick">Ce qui intéresse les visiteurs</div>
    <h2>Centres d'intérêt <span class="badge b-estim">estimé (IA)</span></h2>
    <div class="dash" style="background:#8b5cf6;"></div>
</div>
<p class="lead">Répartition des questions par thème. Les pourcentages sont des comptages ; seul le classement de chaque message dans un thème est estimé.</p>
<table class="data">
    <tr><th style="width:34%;">Sujet</th><th style="width:9%;">Quest.</th><th style="width:9%;">Part</th><th></th></tr>
    <?php foreach ($topics as $k => $row): $col = $CAT[$k % count($CAT)]; ?>
        <tr>
            <td class="name"><span class="dot" style="background:<?= $col ?>;"></span> <?= e($row['name']) ?></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td class="num strong"><?= e($row['percent']) ?>%</td>
            <td><?= bar($row['percent'], $col) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="foot-note">Liste des messages de chaque ligne : Traçabilité §4 et CSV (colonne « sujet »).</p>

<!-- ===================== LACUNES ===================== -->
<bookmark content="Lacunes d'information" level="0" />
<div class="sec">
    <div class="kick">Priorités à enrichir</div>
    <h2>Lacunes d'information par sujet <span class="badge b-fact">calculé</span> <span class="badge b-estim">estimé</span></h2>
    <div class="dash" style="background:#f43f5e;"></div>
</div>
<p class="lead">Sujets (estimés) croisés avec les non-réponses (règles factuelles) : là où l'assistant échoue le plus à répondre.</p>
<table class="data">
    <tr><th style="width:34%;">Sujet</th><th style="width:9%;">Quest.</th><th style="width:13%;">Sans réponse</th><th style="width:9%;">Taux</th><th></th></tr>
    <?php foreach ($gaps as $g): $ac = rate_accent((float) $g['rate']); ?>
        <tr>
            <td class="name"><?= e($g['name']) ?>
                <?php if ($g['no_answer'] > 0 && count($g['no_answer_ids']) <= 5): ?>
                    <div class="src"><?= refs_inline($g['no_answer_ids'], $ref_width, 5) ?></div>
                <?php endif; ?>
            </td>
            <td class="num"><?= (int) $g['count'] ?></td>
            <td class="num"><?= (int) $g['no_answer'] ?></td>
            <td class="num"><span class="strong" style="color:<?= $ac ?>;"><?= e($g['rate']) ?>%</span></td>
            <td><?= bar($g['rate'], $ac) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="foot-note">Références des questions sans réponse par sujet : Traçabilité §4 et §5.</p>

<!-- ===================== INTENTIONS ===================== -->
<bookmark content="Intentions" level="0" />
<div class="sec">
    <div class="kick">Pourquoi ils écrivent</div>
    <h2>Intentions des visiteurs <span class="badge b-estim">estimé (IA)</span></h2>
    <div class="dash" style="background:#6366f1;"></div>
</div>
<table class="data">
    <tr><th style="width:34%;">Intention</th><th style="width:9%;">Msg</th><th style="width:9%;">Part</th><th></th></tr>
    <?php foreach ($intents as $k => $row): $col = $CAT[($k + 3) % count($CAT)]; ?>
        <tr>
            <td class="name"><span class="dot" style="background:<?= $col ?>;"></span> <?= e($row['name']) ?></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td class="num strong"><?= e($row['percent']) ?>%</td>
            <td><?= bar($row['percent'], $col) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== QUALITÉ ===================== -->
<bookmark content="Qualité des réponses" level="0" />
<div class="sec">
    <div class="kick">Comment l'assistant répond</div>
    <h2>Qualité des réponses <span class="badge b-estim">estimé (IA)</span></h2>
    <div class="dash" style="background:#10b981;"></div>
</div>
<table class="data">
    <tr><th style="width:34%;">Qualité estimée</th><th style="width:9%;">Rép.</th><th style="width:9%;">Part</th><th></th></tr>
    <?php foreach ($quality as $row):
        $qc = $row['key'] === 'coherente' ? '#059669' : ($row['key'] === 'partielle' ? '#d97706' : ($row['key'] === 'indetermine' ? '#9aa3b2' : '#dc2626')); ?>
        <tr>
            <td class="name"><span class="dot" style="background:<?= $qc ?>;"></span> <?= e($row['name']) ?></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td class="num strong"><?= e($row['percent']) ?>%</td>
            <td><?= bar($row['percent'], $qc) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p class="verif">
    Réponses potentiellement à risque (hallucination estimée) : <span class="strong"><?= (int) $hallucination['count'] ?></span> (<?= e($hallucination['rate']) ?>%).
    <?php if ($hallucination['count'] > 0): ?>Sources : <?= refs_inline($hallucination['ids'], $ref_width, $refs_max) ?> · texte intégral Traçabilité §6.<?php endif; ?>
</p>

<?php if (!empty($base_enabled)): ?>
<!-- ===================== FIDÉLITÉ À LA BASE ===================== -->
<bookmark content="Fidélité à la base" level="0" />
<div class="sec">
    <div class="kick">Comparaison avec la base de connaissance</div>
    <h2>Fidélité des réponses à la base <span class="badge b-fact">calculé</span> <span class="badge b-estim">estimé (IA)</span></h2>
    <div class="dash" style="background:#14b8a6;"></div>
</div>
<p class="lead">Chaque réponse est comparée à la base du bot. La vérification des <strong>images</strong> est un calcul (présence du fichier dans la base) ; l'<strong>ancrage</strong> (la réponse est-elle étayée par la base ?) est estimé par l'IA d'après la base fournie. Les pourcentages sont des comptages.</p>
<?php if (!empty($grounding) && $ai_enabled): ?>
<table class="data">
    <tr><th style="width:34%;">Ancrage</th><th style="width:9%;">Rép.</th><th style="width:9%;">Part</th><th></th></tr>
    <?php foreach ($grounding as $row):
        $gc = $row['key'] === 'fondee' ? '#059669' : ($row['key'] === 'partielle' ? '#d97706' : (in_array($row['key'], ['hors_base', 'indetermine'], true) ? '#9aa3b2' : '#dc2626')); ?>
        <tr>
            <td class="name"><span class="dot" style="background:<?= $gc ?>;"></span> <?= e($row['name']) ?></td>
            <td class="num"><?= (int) $row['count'] ?></td>
            <td class="num strong"><?= e($row['percent']) ?>%</td>
            <td><?= bar($row['percent'], $gc) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p class="muted">Ancrage non évalué (analyse IA désactivée). La vérification des images, elle, reste effectuée.</p>
<?php endif; ?>

<?php if (!empty($grounding_unfounded)): ?>
<h3 class="sec" style="margin:14px 0 6px;"><span style="font-size:10.5pt; color:#161e2e;">Réponses non fondées (à vérifier)</span></h3>
<p class="foot-note">Réponses affirmant des faits absents de la base. Comparées à la base entière — détail Traçabilité §6bis.</p>
<?php foreach ($grounding_unfounded as $row): ?>
    <div class="qa">
        <div class="q"><?= ref_pill((int) $row['id'], $ref_width) ?> <?= e($row['question']) ?></div>
        <div class="a"><?= e(mb_substr($row['reponse'], 0, 200)) ?><?= mb_strlen($row['reponse']) > 200 ? '…' : '' ?></div>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<h3 class="sec" style="margin:14px 0 6px;"><span style="font-size:10.5pt; color:#161e2e;">Images citées <span class="badge b-fact">calculé</span></span></h3>
<?php if (empty($broken_images['items'])): ?>
    <p class="muted">Toutes les images citées par l'assistant existent dans la base.</p>
<?php else: ?>
    <p class="foot-note"><?= (int) $broken_images['count'] ?> image(s) citée(s) introuvable(s) dans la base (lien cassé / image inexistante).</p>
    <table class="data">
        <tr><th style="width:8%;">Réf.</th><th style="width:55%;">Lien cité</th><th>Fichier introuvable</th></tr>
        <?php foreach ($broken_images['items'] as $it): ?>
            <tr>
                <td><?= ref_pill((int) $it['id'], $ref_width) ?></td>
                <td class="small mono"><?= e(mb_substr($it['url'], 0, 70)) ?></td>
                <td class="small mono"><?= e($it['fichier']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<?php endif; ?>

<!-- ===================== QUESTIONS FRÉQUENTES ===================== -->
<bookmark content="Questions fréquentes" level="0" />
<div class="sec">
    <div class="kick">Le plus demandé</div>
    <h2>Questions les plus fréquentes <span class="badge b-fact">calculé</span></h2>
    <div class="dash" style="background:#0ea5e9;"></div>
</div>
<table class="data">
    <tr><th style="width:5%;">#</th><th>Question</th><th style="width:22%;">Sujet (1ʳᵉ occ.)</th><th style="width:8%;">Occur.</th><th style="width:16%;">Exemples</th></tr>
    <?php foreach ($top_questions as $i => $row): ?>
        <tr>
            <td class="num soft"><?= $i + 1 ?></td>
            <td class="name"><?= e($row['question']) ?></td>
            <td><span class="pill"><?= e($row['topic']) ?></span></td>
            <td class="num strong"><?= (int) $row['count'] ?></td>
            <td><?= refs_inline($row['ids'], $ref_width, 3) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- ===================== SANS RÉPONSE ===================== -->
<bookmark content="Questions sans réponse" level="0" />
<div class="sec">
    <div class="kick">À traiter en priorité</div>
    <h2>Questions restées sans réponse <span class="badge b-fact">calculé</span></h2>
    <div class="dash" style="background:#dc2626;"></div>
</div>
<p class="lead">Ce que les prospects demandent et que l'assistant ne sait pas couvrir. Chaque carte indique la règle déclenchée.</p>
<?php if ($unanswered === []): ?>
    <p class="muted">Aucune non-réponse détectée selon les règles.</p>
<?php else: ?>
    <?php foreach ($unanswered as $row): ?>
        <div class="qa">
            <div class="q"><?= ref_pill((int) $row['id'], $ref_width) ?> <?= e($row['question']) ?></div>
            <div class="a">Réponse : <?= $row['reponse'] === '' ? '<em>(vide)</em>' : e($row['reponse']) ?></div>
            <div class="meta-line"><span class="pill"><?= e($row['topic']) ?></span> · Règle : <?= e($row['regle']) ?><?= $row['date'] !== '' ? ' · ' . e($row['date']) : '' ?></div>
        </div>
    <?php endforeach; ?>
    <?php if (count($nonResp['ids']) > count($unanswered)): ?>
        <p class="foot-note">Liste complète des <?= (int) $nonResp['count'] ?> non-réponses : Traçabilité §5.</p>
    <?php endif; ?>
<?php endif; ?>

<!-- ===================== RÉPONSES PERTINENTES ===================== -->
<bookmark content="Exemples de réponses pertinentes" level="0" />
<div class="sec">
    <div class="kick">Ce qui fonctionne bien</div>
    <h2>Exemples de réponses pertinentes <span class="badge b-estim">estimé (IA)</span></h2>
    <div class="dash" style="background:#059669;"></div>
</div>
<?php if ($best_answers === []): ?>
    <p class="muted">Aucune réponse jugée « pertinente » (analyse IA désactivée ou indéterminée).</p>
<?php else: ?>
    <?php $shown = array_slice($best_answers, 0, $best_n); ?>
    <p class="lead">
        Les <?= count($shown) ?> premières (ordre du fichier) des <?= (int) ($best_answers_eligible ?? count($best_answers)) ?> réponses étiquetées
        « Réponse pertinente » par l'IA, sans hallucination et hors non-réponses. Ce n'est pas un classement de mérite (critère Traçabilité §3, liste complète dans le CSV).
    </p>
    <?php foreach ($shown as $row): ?>
        <div class="qa">
            <div class="q"><?= ref_pill((int) $row['id'], $ref_width) ?> <?= e($row['question']) ?></div>
            <div class="a"><?= e(mb_substr($row['reponse'], 0, 220)) ?><?= mb_strlen($row['reponse']) > 220 ? '…' : '' ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ===================== MÉTHODOLOGIE ===================== -->
<bookmark content="Méthodologie" level="0" />
<div class="sec">
    <div class="kick">Comment lire ce rapport</div>
    <h2>Méthodologie &amp; vérification</h2>
    <div class="dash" style="background:#3b82f6;"></div>
</div>
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:33%; padding:4px; vertical-align:top;">
            <div class="callout-ok"><span class="strong" style="color:#146c4a;">calculé</span> — indicateur déterministe, recalculable depuis le fichier (volumétrie, période, taux de non-réponse).</div>
        </td>
        <td style="width:33%; padding:4px; vertical-align:top;">
            <div class="callout-warn"><span class="strong" style="color:#92560a;">estimé (IA)</span> — classement de chaque texte dans une liste fermée (toute valeur douteuse = « Non classé »). Les pourcentages restent des comptages.</div>
        </td>
        <td style="width:33%; padding:4px; vertical-align:top;">
            <div class="callout-info"><span class="strong" style="color:#2440b8;">vérifier un chiffre</span> — suivez la référence <span class="ref"><?= e(Text::ref(12, $ref_width)) ?></span> citée vers le Document 2/2 : §3 la formule, §4–§7 le détail, §9 tous les messages ; le CSV donne les textes intégraux.</div>
        </td>
    </tr>
</table>
<?php if (!$ai_enabled): ?>
    <p class="note" style="margin-top:8px;">Analyse IA désactivée : toutes les étiquettes valent « Non classé », les indicateurs factuels restent complets.</p>
<?php elseif ($ai_issues['failed_classification'] !== [] || $ai_issues['failed_quality'] !== []): ?>
    <p class="note" style="margin-top:8px;">
        <?php if ($ai_issues['failed_classification'] !== []): ?><span class="strong"><?= count($ai_issues['failed_classification']) ?> message(s)</span> sans classification (lot en échec, « Non classé » dans Sujets/Intentions). <?php endif; ?>
        <?php if ($ai_issues['failed_quality'] !== []): ?><span class="strong"><?= count($ai_issues['failed_quality']) ?> message(s)</span> sans jugement de qualité (lot en échec, « Non évalué »). <?php endif; ?>
        Détail Traçabilité §7.
    </p>
<?php endif; ?>

</body>
</html>
