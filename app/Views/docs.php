<?php
/**
 * Documentation développeur de l'API — page autonome (CSS inline).
 *
 * @var string $base   URL de base déduite de la requête (ex. http://localhost:8787)
 * @var string $apiKey clé d'exemple (vraie clé en dev, sinon placeholder)
 */
helper('text');
$b = esc($base);
$k = esc($apiKey);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API — Vérificateur de chatbot · Doc développeur</title>
<style>
  :root{
    --bg:#f8fafc; --panel:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e2e8f0;
    --accent:#4f46e5; --accent-soft:#eef2ff; --code-bg:#0f172a; --code-ink:#e2e8f0;
    --green:#047857; --green-bg:#ecfdf5; --amber:#b45309; --amber-bg:#fffbeb; --red:#b91c1c; --red-bg:#fef2f2;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{margin:0;background:var(--bg);color:var(--ink);
    font:15px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    -webkit-font-smoothing:antialiased}
  a{color:var(--accent);text-decoration:none}
  a:hover{text-decoration:underline}
  code{font-family:"SF Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.86em}
  .layout{display:flex;max-width:1180px;margin:0 auto;gap:40px;padding:0 24px}

  /* Sidebar */
  aside{width:248px;flex:0 0 248px;position:sticky;top:0;align-self:flex-start;
    height:100vh;overflow-y:auto;padding:28px 0;border-right:1px solid var(--line)}
  aside .brand{font-weight:700;font-size:15px;padding:0 14px 4px}
  aside .brand small{display:block;font-weight:500;color:var(--muted);font-size:12px;margin-top:2px}
  nav a{display:block;padding:6px 14px;color:var(--muted);font-size:13.5px;border-left:2px solid transparent}
  nav a:hover{color:var(--ink);text-decoration:none;background:var(--accent-soft)}
  nav .grp{margin-top:16px;padding:0 14px 4px;font-size:11px;font-weight:700;letter-spacing:.06em;
    text-transform:uppercase;color:#94a3b8}

  /* Main */
  main{flex:1;min-width:0;padding:40px 0 120px}
  header.hero{margin-bottom:8px}
  header.hero h1{font-size:30px;margin:0 0 6px;letter-spacing:-.02em}
  header.hero p{color:var(--muted);margin:0;font-size:16px}
  section{padding-top:40px;margin-top:8px;border-top:1px solid var(--line)}
  section:first-of-type{border-top:none}
  h2{font-size:22px;margin:6px 0 14px;letter-spacing:-.01em}
  h3{font-size:16px;margin:26px 0 10px}
  p,li{color:#334155}
  ul{padding-left:20px}

  /* Code */
  pre{background:var(--code-bg);color:var(--code-ink);border-radius:10px;padding:16px 18px;
    overflow-x:auto;font-size:13px;line-height:1.6;margin:14px 0}
  pre code{font-size:13px}
  p code,li code,td code{background:#eef2f7;color:#0f172a;padding:1.5px 6px;border-radius:5px}

  /* Tables */
  table{width:100%;border-collapse:collapse;margin:14px 0;font-size:14px}
  th,td{text-align:left;padding:9px 12px;border-bottom:1px solid var(--line);vertical-align:top}
  th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);font-weight:700}
  td code{white-space:nowrap}

  /* Method badges */
  .ep{display:flex;align-items:center;gap:10px;background:var(--panel);border:1px solid var(--line);
    border-radius:10px;padding:12px 14px;margin:14px 0 6px;font-family:ui-monospace,monospace;font-size:14px}
  .m{font-weight:700;font-size:11px;padding:3px 9px;border-radius:6px;letter-spacing:.04em}
  .m.get{background:#eff6ff;color:#1d4ed8}
  .m.post{background:#ecfdf5;color:#047857}

  /* Callouts */
  .note{border-radius:10px;padding:12px 16px;margin:16px 0;font-size:14px}
  .note.info{background:var(--accent-soft);border:1px solid #c7d2fe}
  .note.warn{background:var(--amber-bg);border:1px solid #fde68a}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}
  .pill.ok{background:var(--green-bg);color:var(--green)}
  .pill.warn{background:var(--amber-bg);color:var(--amber)}
  .pill.err{background:var(--red-bg);color:var(--red)}
  .req{color:var(--red);font-weight:700}
  footer{margin-top:60px;padding-top:20px;border-top:1px solid var(--line);color:var(--muted);font-size:13px}

  @media (max-width:860px){
    aside{display:none}
    .layout{padding:0 18px}
  }
</style>
</head>
<body>
<div class="layout">

  <aside>
    <div class="brand">Vérificateur de chatbot<small>Documentation API</small></div>
    <nav>
      <a href="#intro">Vue d'ensemble</a>
      <a href="#demarrage">Démarrage</a>
      <a href="#auth">Authentification</a>
      <div class="grp">Endpoints</div>
      <a href="#post-rapport">POST&nbsp;/rapport</a>
      <a href="#get-rapport">GET&nbsp;/rapport</a>
      <a href="#telecharger">GET&nbsp;/telecharger</a>
      <div class="grp">Référence</div>
      <a href="#parametres">Paramètres</a>
      <a href="#csv">Format du CSV</a>
      <a href="#base">Base de connaissance</a>
      <a href="#reponse">Réponse JSON</a>
      <a href="#erreurs">Codes d'erreur</a>
      <a href="#etiquettes">Étiquettes</a>
      <div class="grp">Intégration</div>
      <a href="#exemples">Exemples de code</a>
    </nav>
  </aside>

  <main>
    <header class="hero">
      <h1>API — Vérificateur de chatbot</h1>
      <p>Analyse de conversations + vérification de fidélité à une base de connaissance, en un appel HTTP.</p>
    </header>

    <!-- ===================== INTRO ===================== -->
    <section id="intro">
      <h2>Vue d'ensemble</h2>
      <p>
        Tu fournis <strong>les conversations d'un bot</strong> (CSV) et sa <strong>base de connaissance</strong>
        (documents + images, configurée côté serveur). L'API analyse les échanges, vérifie que les réponses
        collent à la base, et renvoie un <strong>rapport PDF</strong> (ou un résumé JSON). Deux autres documents
        sont générés au passage : un <strong>audit</strong> détaillé ligne par ligne et un <strong>CSV de traçabilité</strong>.
      </p>
      <table>
        <tr><th>Document</th><th>Contenu</th></tr>
        <tr><td><code>rapport</code></td><td>Synthèse lisible : statistiques, qualité des réponses, taux de non-réponse, fidélité.</td></tr>
        <tr><td><code>audit</code></td><td>Détail ligne par ligne, traçabilité de chaque échange.</td></tr>
        <tr><td><code>csv</code></td><td>Les données d'audit en CSV exploitable.</td></tr>
      </table>
      <div class="note info">
        <strong>Principe :</strong> tous les chiffres sont des comptages PHP réels ; l'IA n'attribue que des
        étiquettes dans des listes fermées (elle n'invente aucun nombre). La source CSV et la base sont scellées (SHA-256).
      </div>
    </section>

    <!-- ===================== DEMARRAGE ===================== -->
    <section id="demarrage">
      <h2>Démarrage</h2>
      <p>URL de base de cette instance :</p>
      <pre><code><?= $b ?></code></pre>
      <p>Démarrage du serveur de développement (depuis la racine du projet) :</p>
      <pre><code>php spark serve --port 8787</code></pre>
      <div class="note warn">
        <strong>localhost, pas 127.0.0.1.</strong> Le serveur de dev peut n'écouter qu'en IPv6&nbsp;:
        utilise <code>http://localhost:8787</code> (l'adresse <code>127.0.0.1</code> peut renvoyer « site inaccessible »).
      </div>
    </section>

    <!-- ===================== AUTH ===================== -->
    <section id="auth">
      <h2>Authentification</h2>
      <p>
        Les routes <code>/rapport</code> et <code>/telecharger</code> sont protégées par une clé API
        (<code>REPORT_API_KEY</code> dans le <code>.env</code>). Fournis-la de deux façons&nbsp;:
      </p>
      <ul>
        <li>en paramètre d'URL : <code>?key=<?= $k ?></code></li>
        <li>ou en en-tête : <code>X-Api-Key: <?= $k ?></code></li>
      </ul>
      <pre><code># en query string
curl "<?= $b ?>/rapport/demo?key=<?= $k ?>"

# en en-tête
curl -H "X-Api-Key: <?= $k ?>" "<?= $b ?>/rapport/demo"</code></pre>
      <p>Sans clé valide (quand une clé est configurée) : <span class="pill err">401</span> <code>Clé API invalide ou absente</code>.</p>
    </section>

    <!-- ===================== POST /rapport ===================== -->
    <section id="post-rapport">
      <h2>Envoyer le CSV dans la requête</h2>
      <div class="ep"><span class="m post">POST</span><span>/rapport/&lt;bot&gt;</span></div>
      <p>
        Génère le rapport à partir d'un CSV <strong>transmis dans l'appel</strong> (idéal pour intégrer depuis
        une autre plateforme). La base de connaissance reste celle du bot configurée côté serveur.
      </p>

      <h3>Deux façons d'envoyer le CSV</h3>
      <p><strong>a) Upload de fichier</strong> (multipart) — champ <code>file</code> (ou <code>csv</code>, <code>conversations</code>) :</p>
      <pre><code>curl -X POST "<?= $b ?>/rapport/demo?key=<?= $k ?>" \
     -F "file=@./conversations.csv" \
     -o rapport.pdf</code></pre>

      <p><strong>b) Corps brut</strong> de la requête — <code>Content-Type: text/csv</code> :</p>
      <pre><code>curl -X POST "<?= $b ?>/rapport/demo?key=<?= $k ?>" \
     -H "Content-Type: text/csv" \
     --data-binary @./conversations.csv \
     -o rapport.pdf</code></pre>

      <div class="note info">
        <strong>Priorité de la source :</strong> fichier uploadé → corps brut → fichier disque du bot.
        Si le bot n'existe pas encore côté serveur, l'analyse se fait <strong>sans contrôle de fidélité</strong>
        (pas d'erreur 404). En-tête optionnel <code>X-Filename</code> pour nommer la source (corps brut).
      </div>
    </section>

    <!-- ===================== GET /rapport ===================== -->
    <section id="get-rapport">
      <h2>Générer depuis le fichier serveur</h2>
      <div class="ep"><span class="m get">GET</span><span>/rapport/&lt;bot&gt;</span></div>
      <p>
        Génère le rapport à partir du fichier déposé sur le serveur dans
        <code>writable/bots/&lt;bot&gt;/conversations.csv</code>.
      </p>
      <pre><code># télécharge le PDF du rapport
<?= $b ?>/rapport/demo?key=<?= $k ?>

# résumé JSON au lieu du PDF
<?= $b ?>/rapport/demo?key=<?= $k ?>&amp;format=json

# analyse factuelle, sans IA (rapide)
<?= $b ?>/rapport/demo?key=<?= $k ?>&amp;ai=0</code></pre>
    </section>

    <!-- ===================== GET /telecharger ===================== -->
    <section id="telecharger">
      <h2>Récupérer l'audit / le CSV</h2>
      <div class="ep"><span class="m get">GET</span><span>/telecharger/&lt;bot&gt;/&lt;type&gt;</span></div>
      <p>
        <code>/rapport</code> ne renvoie que le rapport PDF. Pour récupérer l'<strong>audit</strong> ou le
        <strong>CSV</strong> du dernier run (sans tout régénérer) :
      </p>
      <table>
        <tr><th><code>type</code></th><th>Document servi</th></tr>
        <tr><td><code>rapport</code></td><td>dernier <code>rapport-*.pdf</code></td></tr>
        <tr><td><code>audit</code></td><td>dernier <code>audit-*.pdf</code></td></tr>
        <tr><td><code>csv</code></td><td>dernier <code>tracabilite-*.csv</code></td></tr>
      </table>
      <pre><code># l'audit du dernier rapport généré pour « demo »
<?= $b ?>/telecharger/demo/audit?key=<?= $k ?>

# le CSV de traçabilité
<?= $b ?>/telecharger/demo/csv?key=<?= $k ?></code></pre>
    </section>

    <!-- ===================== PARAMETRES ===================== -->
    <section id="parametres">
      <h2>Paramètres de requête</h2>
      <table>
        <tr><th>Param</th><th>Valeurs</th><th>Effet</th></tr>
        <tr><td><code>key</code> <span class="req">requis</span></td><td>la clé <code>REPORT_API_KEY</code></td><td>Authentification (ou en-tête <code>X-Api-Key</code>).</td></tr>
        <tr><td><code>format</code></td><td><code>json</code></td><td>Renvoie un résumé JSON au lieu du PDF.</td></tr>
        <tr><td><code>ai</code></td><td><code>0</code></td><td>Désactive l'appel à l'IA (plus rapide, sans coût).</td></tr>
      </table>
    </section>

    <!-- ===================== CSV ===================== -->
    <section id="csv">
      <h2>Format du CSV</h2>
      <p>Seule la colonne <code>question</code> est <strong>obligatoire</strong>. Les noms de colonnes sont souples : de nombreuses variantes sont reconnues automatiquement.</p>
      <pre><code>date,user_id,question,reponse,erreur
2026-05-04 09:12:00,u001,"Quels services ?","Café, resto, crèche.",
2026-05-04 09:20:00,u001,"Le café ouvre le week-end ?","Du lundi au vendredi, 8h-18h.",</code></pre>
      <table>
        <tr><th>Champ</th><th>Alias acceptés</th></tr>
        <tr><td><code>question</code> <span class="req">requis</span></td><td>question, message, user_message, input, demande, prompt, query, texte</td></tr>
        <tr><td><code>reponse</code></td><td>reponse, response, answer, bot_message, bot_response, output, reply</td></tr>
        <tr><td><code>date</code></td><td>date, datetime, timestamp, created_at, horodatage, time, jour</td></tr>
        <tr><td><code>user_id</code></td><td>user_id, userid, user, utilisateur, id_utilisateur, session_id, session, client_id, distinct_id</td></tr>
        <tr><td><code>erreur</code></td><td>erreur, error, is_error, has_error, statut_erreur</td></tr>
      </table>
      <p>Le séparateur (<code>,</code> ou <code>;</code>) et le format de date sont détectés automatiquement.</p>
    </section>

    <!-- ===================== BASE ===================== -->
    <section id="base">
      <h2>Base de connaissance</h2>
      <p>
        Optionnelle mais recommandée. Elle vit côté serveur dans <code>writable/bots/&lt;bot&gt;/base/</code>
        et sert à vérifier la <strong>fidélité</strong> des réponses + l'<strong>existence des images</strong> citées.
      </p>
      <table>
        <tr><th>Type</th><th>Extensions</th><th>Rôle</th></tr>
        <tr><td>Texte</td><td><code>.txt</code>, <code>.md</code></td><td>Référence comparée aux réponses du bot.</td></tr>
        <tr><td>Images</td><td><code>.png</code>, <code>.jpg</code>, <code>.jpeg</code>, <code>.webp</code>, <code>.gif</code></td><td>Vérif d'existence des images citées.</td></tr>
      </table>
      <p>Sans base, l'analyse fonctionne (statistiques + qualité) mais sans contrôle de fidélité.</p>
    </section>

    <!-- ===================== REPONSE JSON ===================== -->
    <section id="reponse">
      <h2>Réponse JSON</h2>
      <p>Avec <code>format=json</code>, l'appel renvoie un résumé de l'exécution :</p>
      <pre><code>{
  "bot": "demo",
  "generated_at": "18/06/2026 11:49",
  "messages": 14,
  "non_response_rate": 0,
  "base_enabled": true,
  "base_sha256": "c1cc5620…",
  "broken_images": 1,
  "ai_enabled": false,
  "token": "20260618-114931",
  "files":    { "rapport": "rapport-<token>.pdf", "audit": "audit-<token>.pdf", "csv": "tracabilite-<token>.csv" },
  "download": { "rapport": "telecharger/demo/rapport", "audit": "telecharger/demo/audit", "csv": "telecharger/demo/csv" }
}</code></pre>
      <p>Le bloc <code>download</code> donne les chemins prêts à l'emploi pour récupérer l'audit et le CSV.</p>
    </section>

    <!-- ===================== ERREURS ===================== -->
    <section id="erreurs">
      <h2>Codes d'erreur</h2>
      <table>
        <tr><th>Code</th><th>Cause</th></tr>
        <tr><td><span class="pill ok">200</span></td><td>Succès (PDF, JSON, ou fichier téléchargé).</td></tr>
        <tr><td><span class="pill err">401</span></td><td>Clé API absente ou invalide.</td></tr>
        <tr><td><span class="pill err">404</span></td><td>Bot inconnu (et aucun CSV fourni), ou type de document inconnu.</td></tr>
        <tr><td><span class="pill warn">422</span></td><td>Conversations introuvables, ou CSV invalide (ex. colonne <code>question</code> absente).</td></tr>
      </table>
    </section>

    <!-- ===================== ETIQUETTES ===================== -->
    <section id="etiquettes">
      <h2>Étiquettes</h2>
      <h3>Qualité des réponses</h3>
      <p><code>coherente</code> · <code>partielle</code> · <code>non_reponse</code> · <code>hors_sujet</code> · <code>indetermine</code></p>
      <h3>Fidélité à la base</h3>
      <table>
        <tr><th>Clé</th><th>Signification</th></tr>
        <tr><td><code>fondee</code></td><td>Réponse fondée sur la base</td></tr>
        <tr><td><code>partielle</code></td><td>Partiellement fondée</td></tr>
        <tr><td><code>non_fondee</code></td><td>Non fondée (contredit ou absente de la base)</td></tr>
        <tr><td><code>hors_base</code></td><td>Sujet hors du périmètre de la base</td></tr>
        <tr><td><code>indetermine</code></td><td>Non évalué</td></tr>
      </table>
    </section>

    <!-- ===================== EXEMPLES ===================== -->
    <section id="exemples">
      <h2>Exemples de code</h2>

      <h3>cURL</h3>
      <pre><code>curl -X POST "<?= $b ?>/rapport/demo?key=<?= $k ?>&amp;format=json" \
     -F "file=@./conversations.csv"</code></pre>

      <h3>JavaScript (fetch)</h3>
      <pre><code>const form = new FormData();
form.append("file", csvBlob, "conversations.csv"); // csvBlob = File ou Blob

const res = await fetch("<?= $b ?>/rapport/demo?key=<?= $k ?>&amp;format=json", {
  method: "POST",
  body: form,            // ne pas fixer Content-Type, le navigateur s'en charge
});
const meta = await res.json();
// récupérer l'audit ensuite :
// fetch("<?= $b ?>/" + meta.download.audit + "?key=<?= $k ?>")</code></pre>

      <h3>Python (requests)</h3>
      <pre><code>import requests

with open("conversations.csv", "rb") as f:
    r = requests.post(
        "<?= $b ?>/rapport/demo",
        params={"key": "<?= $k ?>", "format": "json"},
        files={"file": ("conversations.csv", f, "text/csv")},
    )
print(r.json())</code></pre>

      <h3>PHP (cURL)</h3>
      <pre><code>$ch = curl_init("<?= $b ?>/rapport/demo?key=<?= $k ?>&amp;format=json");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_POST           =&gt; true,
    CURLOPT_POSTFIELDS     =&gt; ["file" =&gt; new CURLFile("conversations.csv", "text/csv")],
]);
$meta = json_decode(curl_exec($ch), true);
curl_close($ch);</code></pre>
    </section>

    <footer>
      Documentation générée par l'application · voir aussi le <code>README.md</code> du projet.
    </footer>
  </main>
</div>
</body>
</html>
