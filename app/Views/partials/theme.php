<?php
/**
 * theme.php — feuille de style COMMUNE aux deux documents PDF (rapport + audit).
 *
 * Inclus à l'intérieur du <style> de chaque gabarit pour garantir une identité
 * visuelle cohérente : palette « claire & moderne » (fond blanc, cartes douces,
 * accents colorés), avec la sémantique vert = calculé / ambre = estimé (IA).
 *
 * Contraintes mPDF respectées : pas de flex/grid/box-shadow/gradient/var()/calc() ;
 * border-radius uniquement sur tables en border-collapse:separate ou blocs ;
 * barres en tables imbriquées (fonds de td), jamais en div/span.
 */
?>
/* ===================== BASE ===================== */
* { box-sizing: border-box; }
body { font-family: sans-serif; color: #3a4456; font-size: 10.5pt; line-height: 1.5; }
h1, h2, h3, h4 { color: #161e2e; }
p { margin: 0 0 8px; }
.muted { color: #7b8494; }
.soft  { color: #9aa3b2; }
.small { font-size: 8.5pt; }
.mono  { font-family: monospace; }
.strong { color: #161e2e; font-weight: 700; }
.center { text-align: center; }

/* ===================== EN-TÊTE / PIED COURANTS ===================== */
.run-hd { width: 100%; font-size: 7.5pt; color: #9aa3b2; }
.run-hd td { padding-bottom: 2mm; border-bottom: 0.2mm solid #edf0f4; }
.run-hd .brand { color: #161e2e; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; font-size: 7.5pt; }
.run-hd .doc { color: #3457e0; font-weight: 700; }
.run-ft { width: 100%; font-size: 7.5pt; color: #9aa3b2; }
.run-ft td { padding-top: 2mm; border-top: 0.2mm solid #edf0f4; }

/* ===================== TITRES DE SECTION ===================== */
/* Puce d'accent colorée + titre sombre, sans bordure lourde. */
.sec { margin: 22px 0 11px; page-break-inside: avoid; }
.sec .kick { font-size: 7.5pt; letter-spacing: 1.4px; text-transform: uppercase; color: #9aa3b2; font-weight: 700; }
.sec h2 { font-size: 14pt; font-weight: 800; margin: 2px 0 0; color: #161e2e; }
.sec .dash { width: 26px; height: 3px; border-radius: 2px; margin-top: 7px; }
.lead { color: #6b7280; margin: 0 0 12px; font-size: 9.5pt; }
.verif { font-size: 7.8pt; color: #9aa3b2; margin: 5px 0 0; }
.foot-note { font-size: 7.8pt; color: #9aa3b2; margin: 6px 0 0; }

/* ===================== BADGES NATURE (clé de lecture) ===================== */
.badge { display: inline-block; font-size: 8.5pt; font-weight: 700; border-radius: 7px;
         padding: 1.5px 9px; vertical-align: middle; }
.b-fact  { color: #056544; background: #def0e6; }   /* vert  = calculé / déterministe */
.b-estim { color: #8a4d04; background: #fbeccf; }   /* ambre = estimé par l'IA        */
.b-risk  { color: #b81818; background: #fbe0de; }   /* rouge = risque / à vérifier    */
.b-info  { color: #1f39a8; background: #e6ecff; }   /* bleu  = information             */

/* ===================== PASTILLE DE RÉFÉRENCE #NNN ===================== */
.ref { font-family: monospace; font-size: 7pt; color: #2440b8; background: #eef2ff;
       border-radius: 6px; padding: 0.5px 5px; text-decoration: none; }
.src { font-size: 7.5pt; color: #9aa3b2; margin-top: 4px; }

/* ===================== CARTES KPI (cartes douces, hauteur homogène) ===================== */
table.kpi { width: 100%; border-collapse: separate; border-spacing: 9px; }
table.kpi td { background: #f7f9fc; border: 0.2mm solid #eef1f5; border-radius: 12px;
               padding: 11px 12px; text-align: center; height: 19mm; vertical-align: middle; }
.kpi .num { font-size: 19pt; font-weight: 800; color: #161e2e; }
.kpi .lbl { font-size: 7.8pt; color: #7b8494; margin-top: 3px; text-transform: uppercase; letter-spacing: .4px; }

/* ===================== CARTE GÉNÉRIQUE ===================== */
.card { background: #fff; border: 0.2mm solid #eef1f5; border-radius: 12px; padding: 13px 15px; }
.card-soft { background: #f7f9fc; border: 0.2mm solid #eef1f5; border-radius: 12px; padding: 13px 15px; }

/* ===================== TABLES DE DONNÉES (légères) ===================== */
table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
table.data th { color: #7b8494; font-weight: 700; font-size: 7.6pt; text-transform: uppercase; letter-spacing: .4px;
                text-align: left; padding: 5px 9px; border-bottom: 0.4mm solid #e6eaf0; }
table.data td { padding: 7px 9px; border-bottom: 0.2mm solid #f0f3f7; font-size: 9.3pt; vertical-align: top; }
table.data td.num { text-align: right; white-space: nowrap; }
table.data .name { color: #161e2e; font-weight: 600; }

/* ===================== BARRES (tables imbriquées — seuls les fonds de td sont fiables) ===================== */
table.barwrap { border-collapse: collapse; width: 100%; }
.vb { font-size: 1pt; line-height: 1pt; }       /* remplissage &nbsp; sans gonfler la hauteur */

/* ===================== PASTILLE / PUCE COLORÉE ===================== */
.dot { display: inline-block; width: 7px; height: 7px; border-radius: 4px; }
.pill { display: inline-block; font-size: 7.8pt; padding: 1px 8px; border-radius: 7px;
        background: #f1f4f9; color: #5b6573; }

/* ===================== Q / R (cartes) ===================== */
.qa { border: 0.2mm solid #eef1f5; border-radius: 11px; padding: 10px 13px; margin-bottom: 8px; page-break-inside: avoid; }
.qa .q { font-weight: 700; color: #161e2e; }
.qa .a { color: #6b7280; font-size: 9pt; margin-top: 2px; }
.qa .meta-line { font-size: 7.8pt; color: #9aa3b2; margin-top: 4px; }

/* ===================== ENCADRÉS ===================== */
.note  { background: #f7f9fc; border: 0.2mm solid #eef1f5; border-radius: 11px; padding: 11px 14px; font-size: 8.8pt; color: #5b6573; }
.callout-ok   { background: #e7f7ef; border: 0.2mm solid #c2ead5; border-radius: 11px; padding: 12px 14px; color: #146c4a; font-size: 9.3pt; }
.callout-info { background: #eef2ff; border: 0.2mm solid #cfd9fb; border-radius: 11px; padding: 12px 14px; color: #2440b8; font-size: 9.3pt; }
.callout-warn { background: #fdf3e2; border: 0.2mm solid #f1ddb3; border-radius: 11px; padding: 12px 14px; color: #92560a; font-size: 9.3pt; }
